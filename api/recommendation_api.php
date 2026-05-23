<?php
/**
 * recommendation_api.php
 * MODULE 4: Recommendation Engine — "Frequently Bought Together"
 * Actions:
 *   get_recommendations?product_id=X  → Cross-sell gợi ý từ Product Association Rules
 *   rebuild_associations               → Admin rebuilds association matrix từ lịch sử mua hàng
 */
session_start();
include '../config/db.php';
include_once '../includes/flash_sale_helper.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

$action     = $_POST['action'] ?? $_GET['action'] ?? '';
$product_id = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);

// -----------------------------------------------
// 1. LẤY GỢI Ý CROSS-SELL CHO 1 SẢN PHẨM
// -----------------------------------------------
if ($action === 'get_recommendations') {
    if ($product_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'product_id required']);
        exit;
    }

    $limit = min(8, (int)($_GET['limit'] ?? 4));

   

    $cat_stmt = $conn->prepare("SELECT category_id, name, price, brand_id FROM products WHERE id = ?");
    $cat_stmt->bind_param("i", $product_id);
    $cat_stmt->execute();
    $cat_res = $cat_stmt->get_result()->fetch_assoc();
    $cat_stmt->close();
    
    $source_cat_id = (int)($cat_res['category_id'] ?? 0);
    $source_name = mb_strtolower($cat_res['name'] ?? '', 'UTF-8');
    $source_price = (float)($cat_res['price'] ?? 0);
    $source_brand = (int)($cat_res['brand_id'] ?? 0);

    // 1. Flexibility & Complementary Logic
    $target_categories = []; 
    $include_keywords = [];

    switch ($source_cat_id) {
        case 1: // Điện thoại
            $target_categories = [15, 20]; 
            $include_keywords = ['sạc dự phòng', 'củ sạc', 'cáp sạc', 'tai nghe', 'ốp lưng', 'cường lực'];
            break;
        case 2: // Laptop
            $target_categories = [15, 20];
            $include_keywords = ['chuột', 'balo', 'túi chống sốc', 'tai nghe', 'đế tản nhiệt', 'lót chuột'];
            break;
        case 3: // Máy tính bảng
            $target_categories = [15, 20];
            $include_keywords = ['bao da', 'bút cảm ứng', 'củ sạc', 'cáp sạc', 'tai nghe'];
            break;
        case 5: // Đồng hồ
            $target_categories = [20];
            $include_keywords = ['dây đồng hồ', 'đế sạc', 'cáp sạc đồng hồ', 'kính cường lực đồng hồ'];
            break;
        case 7: // PC
            $target_categories = [10, 13, 20]; 
            $include_keywords = ['màn hình', 'chuột', 'phím', 'bàn phím', 'lót chuột', 'webcam'];
            break;
        case 17: // Camera
            $target_categories = [20];
            $include_keywords = ['thẻ nhớ', 'túi đựng máy ảnh', 'tripod', 'ống kính', 'pin máy ảnh'];
            break;
        case 10: // Màn hình / Tivi
            $target_categories = [20, 15];
            $include_keywords = ['cáp hdmi', 'giá treo', 'loa', 'soundbar'];
            break;
        case 15: // Âm thanh (Tai nghe, Loa)
            $target_categories = [20];
            $include_keywords = ['giá đỡ tai nghe', 'hộp đựng tai nghe', 'cáp âm thanh', 'jack chuyển'];
            break;
    }

    $cat_in = implode(',', array_filter($target_categories, 'is_numeric'));
    if (empty($cat_in)) {
        $cat_in = '0';
    }
    
    $filter_sql = "p.category_id IN ($cat_in)";

    if (!empty($include_keywords)) {
        $include_conditions = [];
        foreach ($include_keywords as $kw) {
            $include_conditions[] = "p.name LIKE '%" . $conn->real_escape_string($kw) . "%'";
        }
        $filter_sql .= " AND (" . implode(" OR ", $include_conditions) . ")";
        $include_score = "(CASE WHEN " . implode(" OR ", $include_conditions) . " THEN 5 ELSE 0 END)";
    } else {
        $filter_sql .= " AND 1=0"; // Không có phụ kiện phù hợp -> Không gợi ý
        $include_score = "0";
    }

    // 2. Real-world Context (Price & Brand synergy)
    $min_price = $source_price * 0.005;
    $max_price = $source_price * 0.4;
    $price_boost = "CASE WHEN p.price BETWEEN $min_price AND $max_price THEN 2 ELSE 0 END";
    $brand_boost = "CASE WHEN p.brand_id = $source_brand AND $source_brand > 0 THEN 3 ELSE 0 END";

    $order_by = "(pa.co_count * 2) + ($price_boost) + ($brand_boost) + $include_score DESC";
    $fallback_order_by = "($price_boost) + ($brand_boost) + $include_score DESC, p.id DESC";

    $fetch_limit = 30; 

    $stmt = $conn->prepare("
        SELECT 
            p.id, p.name, p.image, p.price, p.sale_price, p.stock, p.brand_id, p.category_id,
            pa.co_count,
            ROUND(IFNULL(AVG(r.rating), 0), 1) AS avg_rating
        FROM product_associations pa
        JOIN products p ON (
            CASE
                WHEN pa.product_a = ? THEN p.id = pa.product_b
                ELSE p.id = pa.product_a
            END
        )
        LEFT JOIN product_reviews r ON r.product_id = p.id
        WHERE (pa.product_a = ? OR pa.product_b = ?)
          AND p.id != ?
          AND p.stock > 0
          AND $filter_sql
        GROUP BY p.id, pa.co_count
        ORDER BY $order_by
        LIMIT ?
    ");
    $stmt->bind_param("iiiii", $product_id, $product_id, $product_id, $product_id, $fetch_limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $pool = [];
    while ($row = $res->fetch_assoc()) {
        $pool[] = $row;
    }

    $existing_ids = count($pool) > 0 ? implode(',', array_column($pool, 'id')) : '0';
    $cat_stmt = $conn->prepare("
        SELECT p.id, p.name, p.image, p.price, p.sale_price, p.stock, p.brand_id, p.category_id,
               ROUND(IFNULL(AVG(r.rating), 0), 1) AS avg_rating,
               0 AS co_count
        FROM products p
        LEFT JOIN product_reviews r ON r.product_id = p.id
        WHERE p.id != ?
          AND p.id NOT IN ($existing_ids)
          AND p.stock > 0
          AND $filter_sql
        GROUP BY p.id
        ORDER BY $fallback_order_by
        LIMIT ?
    ");
    $cat_stmt->bind_param("ii", $product_id, $fetch_limit);
    $cat_stmt->execute();
    $cat_res = $cat_stmt->get_result();
    $cat_stmt->close();

    while ($row = $cat_res->fetch_assoc()) {
        $pool[] = $row;
    }

    // 3. Diversity 
    $get_product_type = function($name) {
        $name = mb_strtolower($name, 'UTF-8');
        if (strpos($name, 'sạc dự phòng') !== false) return 'power_bank';
        if (strpos($name, 'củ sạc') !== false || strpos($name, 'adapter') !== false) return 'charger';
        if (strpos($name, 'cáp') !== false || strpos($name, 'dây sạc') !== false) return 'cable';
        if (strpos($name, 'ốp lưng') !== false || strpos($name, 'bao da') !== false) return 'case';
        if (strpos($name, 'tai nghe') !== false) return 'audio';
        if (strpos($name, 'chuột') !== false) return 'mouse';
        if (strpos($name, 'phím') !== false || strpos($name, 'bàn phím') !== false) return 'keyboard';
        if (strpos($name, 'màn hình') !== false) return 'monitor';
        if (strpos($name, 'thẻ nhớ') !== false) return 'memory_card';
        if (strpos($name, 'balo') !== false || strpos($name, 'túi') !== false) return 'bag';
        if (strpos($name, 'dây đồng hồ') !== false) return 'watch_strap';
        return 'other';
    };

    $recommendations = [];
    $seen_types = [];

    foreach ($pool as $row) {
        if (count($recommendations) >= $limit) break;
        
        $type = $get_product_type($row['name']);
        $max_per_type = ($type === 'other') ? 2 : 1; 

        if (($seen_types[$type] ?? 0) < $max_per_type) {
            $price_info = get_effective_price($conn, (int)$row['id'], $row['price'], $row['sale_price']);
            $row['display_price']   = $price_info['effective_price'];
            $row['is_flash_sale']   = $price_info['is_flash_sale'];
            $row['discount_label']  = $price_info['discount_label'];
            $row['avg_rating']      = (float)($row['avg_rating'] ?? 0);
            $row['image_url'] = (strpos($row['image'], 'http') === 0)
                ? $row['image']
                : '/assets/img/' . $row['image'];
            $row['product_url'] = '/product_detail.php?id=' . $row['id'];
            
            $recommendations[] = $row;
            $seen_types[$type] = ($seen_types[$type] ?? 0) + 1;
        }
    }

    echo json_encode(['status' => 'success', 'data' => $recommendations, 'source_product_id' => $product_id]);
    exit;

}

// -----------------------------------------------
// Chạy sau khi có thêm đơn hàng mới (Admin only)
// -----------------------------------------------
if ($action === 'rebuild_associations') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $conn->query("TRUNCATE TABLE product_associations");

    $rebuild_sql = "
        INSERT INTO product_associations (product_a, product_b, co_count)
        SELECT
            od1.product_id,
            od2.product_id,
            COUNT(*) AS co_count
        FROM order_details od1
        JOIN order_details od2 ON od1.order_id = od2.order_id AND od1.product_id < od2.product_id
        JOIN orders o ON od1.order_id = o.id AND o.status = 'completed'
        GROUP BY od1.product_id, od2.product_id
        ON DUPLICATE KEY UPDATE co_count = VALUES(co_count)
    ";
    $conn->query($rebuild_sql);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Đã tái tính Association Rules',
        'pairs'   => $conn->affected_rows
    ]);
    exit;
}

// -----------------------------------------------
// 3. HOOK
// -----------------------------------------------
if ($action === 'update_from_order') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    if ($order_id <= 0) {
        echo json_encode(['status' => 'error']); exit;
    }

    $pairs_sql = "
        SELECT od1.product_id AS a, od2.product_id AS b
        FROM order_details od1
        JOIN order_details od2 ON od1.order_id = od2.order_id AND od1.product_id < od2.product_id
        WHERE od1.order_id = ?
    ";
    $pairs_stmt = $conn->prepare($pairs_sql);
    $pairs_stmt->bind_param("i", $order_id);
    $pairs_stmt->execute();
    $pairs_res = $pairs_stmt->get_result();
    $pairs_stmt->close();

    $ins = $conn->prepare("
        INSERT INTO product_associations (product_a, product_b, co_count)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE co_count = co_count + 1
    ");

    while ($pair = $pairs_res->fetch_assoc()) {
        $ins->bind_param("ii", $pair['a'], $pair['b']);
        $ins->execute();
    }
    $ins->close();

    echo json_encode(['status' => 'success']);
    exit;
}

// -----------------------------------------------
// 4. GET CART RECOMMENDATIONS (Dựa trên lịch sử xem & sp trong giỏ)
// -----------------------------------------------
if ($action === 'get_cart_recommendations') {
    $cart_ids_json = $_GET['cart_ids'] ?? $_POST['cart_ids'] ?? '[]';
    $cart_ids = json_decode($cart_ids_json, true);
    if (!is_array($cart_ids)) $cart_ids = [];
    
    // Lọc bỏ ID không hợp lệ và unique
    $cart_ids = array_unique(array_filter(array_map('intval', $cart_ids), function($id) { return $id > 0; }));
    
    $viewed_ids = isset($_SESSION['viewed_history']) && is_array($_SESSION['viewed_history']) ? $_SESSION['viewed_history'] : [];
    $viewed_ids = array_unique(array_filter(array_map('intval', $viewed_ids), function($id) { return $id > 0; }));

    // Loại bỏ những sản phẩm đã có trong giỏ khỏi danh sách gợi ý
    $valid_viewed = array_diff($viewed_ids, $cart_ids);

    $limit = 12; // Tổng số lượng trả về
    $half = floor($limit / 2);

    $recommendations = [];
    $used_ids = $cart_ids; // Mảng này sẽ lưu những ID đã lấy để tránh trùng lặp

    // Hàm tiện ích để get product details
    $get_products_by_ids = function($ids) use ($conn) {
        if (empty($ids)) return [];
        $ids_str = implode(',', $ids);
        $sql = "
            SELECT p.id, p.name, p.image, p.price, p.sale_price, p.stock, p.brand_id, p.category_id,
                   ROUND(IFNULL(AVG(r.rating), 0), 1) AS avg_rating
            FROM products p
            LEFT JOIN product_reviews r ON r.product_id = p.id
            WHERE p.id IN ($ids_str) AND p.stock > 0
            GROUP BY p.id
        ";
        $res = $conn->query($sql);
        $list = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $list[$row['id']] = $row;
            }
        }
        
        // Giữ đúng thứ tự của $ids
        $sorted_list = [];
        foreach ($ids as $id) {
            if (isset($list[$id])) {
                $sorted_list[] = $list[$id];
            }
        }
        return $sorted_list;
    };

    // 1. Lấy danh sách từ lịch sử xem
    $viewed_products = [];
    if (!empty($valid_viewed)) {
        // Lấy $limit sản phẩm từ lịch sử xem (phòng hờ luồng kia không có)
        $top_viewed = array_slice($valid_viewed, 0, $limit);
        $viewed_products = $get_products_by_ids($top_viewed);
    }

    // 2. Lấy danh sách từ Product Associations (Dựa trên cart_ids)
    $assoc_products = [];
    if (!empty($cart_ids)) {
        $cart_ids_str = implode(',', $cart_ids);
        // Exclude used_ids (giỏ hàng) và valid_viewed (để không trùng với luồng 1)
        $exclude_ids = array_merge($cart_ids, $valid_viewed);
        $exclude_str = !empty($exclude_ids) ? implode(',', $exclude_ids) : '0';

        $assoc_sql = "
            SELECT 
                p.id, p.name, p.image, p.price, p.sale_price, p.stock, p.brand_id, p.category_id,
                SUM(pa.co_count) as total_co_count,
                ROUND(IFNULL(AVG(r.rating), 0), 1) AS avg_rating
            FROM product_associations pa
            JOIN products p ON (
                CASE
                    WHEN pa.product_a IN ($cart_ids_str) THEN p.id = pa.product_b
                    ELSE p.id = pa.product_a
                END
            )
            LEFT JOIN product_reviews r ON r.product_id = p.id
            WHERE (pa.product_a IN ($cart_ids_str) OR pa.product_b IN ($cart_ids_str))
              AND p.id NOT IN ($exclude_str)
              AND p.stock > 0
            GROUP BY p.id
            ORDER BY total_co_count DESC, p.id DESC
            LIMIT $limit
        ";
        $res = $conn->query($assoc_sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $assoc_products[] = $row;
            }
        }
    }

    // 3. MERGE LOGIC (50/50)
    $final_pool = [];
    $viewed_count = count($viewed_products);
    $assoc_count = count($assoc_products);

    $take_viewed = min($half, $viewed_count);
    $take_assoc = min($limit - $take_viewed, $assoc_count);

    // Nếu assoc ít hơn dự kiến, bù thêm từ viewed
    if ($take_assoc < $half) {
        $take_viewed = min($limit - $take_assoc, $viewed_count);
    }

    $final_pool = array_merge(
        array_slice($viewed_products, 0, $take_viewed),
        array_slice($assoc_products, 0, $take_assoc)
    );

    // Format kết quả cuối cùng
    $recommendations = [];
    foreach ($final_pool as $row) {
        $price_info = get_effective_price($conn, (int)$row['id'], $row['price'], $row['sale_price']);
        $row['display_price']   = $price_info['effective_price'];
        $row['is_flash_sale']   = $price_info['is_flash_sale'];
        $row['discount_label']  = $price_info['discount_label'];
        $row['avg_rating']      = (float)($row['avg_rating'] ?? 0);
        $row['image_url'] = (strpos($row['image'], 'http') === 0)
            ? $row['image']
            : 'assets/img/' . $row['image'];
        $row['product_url'] = 'product_detail.php?id=' . $row['id'];
        
        $recommendations[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $recommendations]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
