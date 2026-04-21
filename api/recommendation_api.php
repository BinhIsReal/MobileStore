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

    $limit = min(8, (int)($_GET['limit'] ?? 4));

    // Xác định category của sản phẩm gốc để quyết định tập gợi ý phụ kiện
    $cat_stmt = $conn->prepare("SELECT category_id FROM products WHERE id = ?");
    $cat_stmt->bind_param("i", $product_id);
    $cat_stmt->execute();
    $cat_res = $cat_stmt->get_result()->fetch_assoc();
    $cat_stmt->close();
    
    $source_cat_id = $cat_res['category_id'] ?? 0;

    // Xây dựng điều kiện lọc phụ kiện tương ứng
    if (in_array($source_cat_id, [1, 3, 5])) { 
        // Điện thoại, Máy tính bảng, Đồng hồ: Gợi ý sạc, cáp, tai nghe... (Loại trừ chuột, phím, đế, bảng vẽ)
        $filter_sql = "(p.category_id IN (15, 20) AND p.name NOT LIKE '%chuột%' AND p.name NOT LIKE '%phím%' AND p.name NOT LIKE '%đế%' AND p.name NOT LIKE '%bảng vẽ%')";
    } elseif (in_array($source_cat_id, [2, 7, 10, 13])) { 
        // Laptop, PC, Màn hình: Gợi ý chuột, phím, lót chuột, đế... (Loại trừ củ sạc, pin sạc đt)
        $filter_sql = "(p.category_id IN (13, 15, 20) AND p.name NOT LIKE '%pin sạc dự phòng%' AND p.name NOT LIKE '%củ sạc%' AND p.name NOT LIKE '%cáp sạc%')";
    } elseif ($source_cat_id == 17) {
        // Camera: Phụ kiện hoặc thiết bị liên quan
        $filter_sql = "(p.category_id = 20)";
    } else {
        // Mặc định
        $filter_sql = "(p.category_id = 20 OR p.category_id = $source_cat_id)";
    }

    // 1. Ưu tiên lấy các phụ kiện được mua cùng nhiều nhất từ product_associations (Có filter)
    $stmt = $conn->prepare("
        SELECT 
            p.id, p.name, p.image, p.price, p.sale_price, p.stock,
            pa.co_count
        FROM product_associations pa
        JOIN products p ON (
            CASE
                WHEN pa.product_a = ? THEN p.id = pa.product_b
                ELSE p.id = pa.product_a
            END
        )
        WHERE (pa.product_a = ? OR pa.product_b = ?)
          AND p.id != ?
          AND p.stock > 0
          AND $filter_sql
        ORDER BY pa.co_count DESC
        LIMIT ?
    ");
    $stmt->bind_param("iiiii", $product_id, $product_id, $product_id, $product_id, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $recommendations = [];
    while ($row = $res->fetch_assoc()) {
        // Ưu tiên Flash Sale > sale_price > price
        $price_info = get_effective_price($conn, (int)$row['id'], $row['price'], $row['sale_price']);
        $row['display_price']   = $price_info['effective_price'];
        $row['is_flash_sale']   = $price_info['is_flash_sale'];
        $row['discount_label']  = $price_info['discount_label'];
        $row['image_url'] = (strpos($row['image'], 'http') === 0)
            ? $row['image']
            : '/assets/img/' . $row['image'];
        $row['product_url'] = '/product_detail.php?id=' . $row['id'];
        $recommendations[] = $row;
    }

    // 2. Fallback: Nếu không đủ gợi ý từ association, bổ sung dựa trên filter quy định (Phụ kiện tương thích)
    if (count($recommendations) < $limit) {
        $fill_limit = $limit - count($recommendations);
        $existing_ids = count($recommendations) > 0 ? implode(',', array_column($recommendations, 'id')) : '0';
        
        $cat_stmt = $conn->prepare("
            SELECT p.id, p.name, p.image, p.price, p.sale_price, p.stock
            FROM products p
            WHERE p.id != ?
              AND p.id NOT IN ($existing_ids)
              AND p.stock > 0
              AND $filter_sql
            ORDER BY p.id DESC
            LIMIT ?
        ");
        $cat_stmt->bind_param("ii", $product_id, $fill_limit);
        $cat_stmt->execute();
        $cat_res = $cat_stmt->get_result();
        $cat_stmt->close();

        while ($row = $cat_res->fetch_assoc()) {
            $price_info = get_effective_price($conn, (int)$row['id'], $row['price'], $row['sale_price']);
            $row['display_price']   = $price_info['effective_price'];
            $row['is_flash_sale']   = $price_info['is_flash_sale'];
            $row['discount_label']  = $price_info['discount_label'];
            $row['image_url'] = (strpos($row['image'], 'http') === 0)
                ? $row['image']
                : '/assets/img/' . $row['image'];
            $row['product_url'] = '/product_detail.php?id=' . $row['id'];
            $row['co_count']    = 0;
            $recommendations[]  = $row;
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

    // Xóa dữ liệu cũ và tính lại toàn bộ từ order_details
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
// 3. HOOK: Được gọi từ cart_api.php sau checkout thành công
// Cập nhật association từ đơn hàng mới (incremental update)
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

echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
