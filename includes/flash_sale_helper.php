<?php
/**
 * flash_sale_helper.php
 * ==============================================
 * Single source of truth cho logic giá Flash Sale.
 * Include file này ở BẤT KỲ đâu cần tính giá sản phẩm.
 *
 * Hàm chính: get_effective_price($conn, $product_id, $base_price, $default_sale_price)
 *   - Kiểm tra sản phẩm có đang trong Flash Sale active (chưa hết hạn) không.
 *   - Nếu YES → trả về giá Flash Sale.
 *   - Nếu NO  → trả về giá khuyến mãi thường (sale_price) hoặc giá gốc.
 *
 * Hàm phụ: get_flash_sale_config($conn)
 *   - Trả về config Flash Sale đang active, hoặc null nếu không active / hết hạn.
 *
 * Hàm phụ: get_flash_prices_bulk($conn, array $product_ids)
 *   - Batch query: trả về mapping [product_id => flash_price] cho danh sách sản phẩm.
 *   - Dùng cho cart, wishlist để tránh N+1 queries.
 */

// -----------------------------------------------
// 1. Lấy config Flash Sale đang active
// -----------------------------------------------
function get_flash_sale_config($conn) {
    static $cached_config = null;
    static $cache_checked = false;

    if ($cache_checked) return $cached_config;
    $cache_checked = true;

    $res = $conn->query(
        "SELECT * FROM flash_sale_config
         WHERE is_active = 1
           AND end_time > NOW()
         LIMIT 1"
    );

    if ($res && $res->num_rows > 0) {
        $cached_config = $res->fetch_assoc();
    }

    return $cached_config;
}

// -----------------------------------------------
// 2. Tính giá hiệu lực cho 1 sản phẩm
// -----------------------------------------------
/**
 * @param mysqli  $conn
 * @param int     $product_id
 * @param float   $base_price         Giá gốc (products.price)
 * @param float   $default_sale_price Giá KM thường (products.sale_price), 0 nếu không có
 * @return array  [
 *   'effective_price' => float,   // Giá thực tế để tính tiền
 *   'original_price'  => float,   // Giá gốc để hiển thị gạch ngang
 *   'is_flash_sale'   => bool,    // TRUE nếu đang apply Flash Sale
 *   'discount_label'  => string,  // Nhãn hiển thị: "-20%" / "-100,000đ" / ""
 * ]
 */
function get_effective_price($conn, $product_id, $base_price, $default_sale_price = 0) {
    $base_price         = (float)$base_price;
    $default_sale_price = (float)$default_sale_price;

    $flash_config = get_flash_sale_config($conn);

    if ($flash_config) {
        // Kiểm tra sản phẩm có trong flash_sale_items không
        $stmt = $conn->prepare(
            "SELECT discount_type, discount_value
             FROM flash_sale_items
             WHERE product_id = ?
             LIMIT 1"
        );
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $fs_item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($fs_item) {
            $disc_type = $fs_item['discount_type'];
            $disc_val  = (float)$fs_item['discount_value'];

            if ($disc_type === 'percent') {
                $flash_price   = $base_price * (1 - $disc_val / 100);
                $discount_label = "-{$disc_val}%";
            } else {
                $flash_price   = $base_price - $disc_val;
                $discount_label = "-" . number_format($disc_val, 0, ',', '.') . "đ";
            }
            $flash_price = max(0, round($flash_price));

            return [
                'effective_price' => $flash_price,
                'original_price'  => $base_price,
                'is_flash_sale'   => true,
                'discount_label'  => $discount_label,
            ];
        }
    }

    // Không có Flash Sale → dùng sale_price thường hoặc giá gốc
    $sale = ($default_sale_price > 0) ? $default_sale_price : $base_price;

    $discount_label = '';
    if ($default_sale_price > 0 && $base_price > $default_sale_price) {
        $pct = round((1 - $default_sale_price / $base_price) * 100);
        $discount_label = "-{$pct}%";
    }

    return [
        'effective_price' => $sale,
        'original_price'  => $base_price,
        'is_flash_sale'   => false,
        'discount_label'  => $discount_label,
    ];
}

// -----------------------------------------------
// 3. Batch: Lấy giá Flash Sale cho nhiều sản phẩm
// -----------------------------------------------
/**
 * @param mysqli $conn
 * @param int[]  $product_ids
 * @return array [product_id => ['flash_price' => float, 'discount_label' => string]]
 *         Chỉ chứa sản phẩm ĐANG trong Flash Sale active.
 */
function get_flash_prices_bulk($conn, array $product_ids) {
    $result = [];

    if (empty($product_ids)) return $result;

    $flash_config = get_flash_sale_config($conn);
    if (!$flash_config) return $result;

    $ids          = array_map('intval', $product_ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types        = str_repeat('i', count($ids));

    $stmt = $conn->prepare(
        "SELECT fsi.product_id, fsi.discount_type, fsi.discount_value, p.price
         FROM flash_sale_items fsi
         JOIN products p ON p.id = fsi.product_id
         WHERE fsi.product_id IN ($placeholders)"
    );

    $bind_args = [$types];
    foreach ($ids as &$id) $bind_args[] = &$id;
    call_user_func_array([$stmt, 'bind_param'], $bind_args);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    while ($row = $res->fetch_assoc()) {
        $base      = (float)$row['price'];
        $disc_type = $row['discount_type'];
        $disc_val  = (float)$row['discount_value'];

        if ($disc_type === 'percent') {
            $fp    = $base * (1 - $disc_val / 100);
            $label = "-{$disc_val}%";
        } else {
            $fp    = $base - $disc_val;
            $label = "-" . number_format($disc_val, 0, ',', '.') . "đ";
        }

        $result[(int)$row['product_id']] = [
            'flash_price'     => max(0, round($fp)),
            'original_price'  => $base,
            'discount_label'  => $label,
        ];
    }

    return $result;
}
