<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Bảo vệ các action admin — get_flash_sale là public
if ($action !== 'get_flash_sale') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
}

// ============================================================
// 1. Tạo bảng nếu chưa có
// ============================================================
$conn->query("
    CREATE TABLE IF NOT EXISTS flash_sale_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) DEFAULT 'HOT SALE',
        end_time DATETIME NOT NULL,
        default_discount INT DEFAULT 20,
        is_active TINYINT(1) DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");
$conn->query("
    CREATE TABLE IF NOT EXISTS flash_sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        discount_type ENUM('percent','fixed') DEFAULT 'percent',
        discount_value DECIMAL(10,2) DEFAULT 20.00,
        UNIQUE KEY uq_product (product_id)
    )
");



if ($action === 'ai_suggest') {
    // Chọn sản phẩm bán chạy nhất dựa trên tổng số lượng đã bán từ đơn hàng hoàn thành
    $suggested = [];

    // Bước 1: Top sản phẩm có total_sold > 0, sắp xếp bán chạy → ít bán
    $sql_hot = "
        SELECT p.id, p.name, p.price, p.image,
               COALESCE(SUM(oi.quantity), 0) AS total_sold
        FROM products p
        LEFT JOIN order_items oi ON oi.product_id = p.id
        LEFT JOIN orders o ON o.id = oi.order_id AND o.status = 'completed'
        WHERE p.stock > 0
        GROUP BY p.id
        HAVING total_sold > 0
        ORDER BY total_sold DESC
        LIMIT 8
    ";
    $res_hot = $conn->query($sql_hot);
    if ($res_hot) {
        while ($row = $res_hot->fetch_assoc()) {
            if (count($suggested) >= 8) break;
            $suggested[$row['id']] = $row;
        }
    }

    // Bước 2: Nếu chưa đủ 8 (ít đơn hàng hoàn thành), bổ sung bằng RAND()
    if (count($suggested) < 8) {
        $exclude_ids = implode(',', array_keys($suggested) ?: [0]);
        $sql_fill = "
            SELECT p.id, p.name, p.price, p.image
            FROM products p
            WHERE p.stock > 0
              AND p.id NOT IN ($exclude_ids)
            ORDER BY RAND()
            LIMIT " . (8 - count($suggested));
        $res_fill = $conn->query($sql_fill);
        if ($res_fill) {
            while ($row = $res_fill->fetch_assoc()) {
                $suggested[$row['id']] = $row;
            }
        }
    }

    echo json_encode([
        'status'   => 'success',
        'products' => array_values($suggested)
    ]);
    exit;
}

if ($action === 'random_suggest') {
    // Chọn 8 sản phẩm hoàn toàn ngẫu nhiên
    $sql_rand = "
        SELECT p.id, p.name, p.price, p.image
        FROM products p
        WHERE p.stock > 0
        ORDER BY RAND()
        LIMIT 8
    ";
    $res_rand = $conn->query($sql_rand);
    $random_products = [];
    if ($res_rand) {
        while ($row = $res_rand->fetch_assoc()) {
            $random_products[] = $row;
        }
    }
    echo json_encode([
        'status'   => 'success',
        'products' => $random_products
    ]);
    exit;
}

if ($action === 'save_flash_sale') {
    $title            = $_POST['title'] ?? '🔥 HOT SALE CUỐI TUẦN';
    // Fix datetime-local format -> MySQL datetime
    $end_time_raw     = $_POST['end_time'] ?? '';
    $end_time         = $end_time_raw ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $end_time_raw))) : '';
    $default_discount = (int)($_POST['default_discount'] ?? 20);
    $is_active        = (int)($_POST['is_active'] ?? 1);
    $items_json       = $_POST['items'] ?? '[]';

    if (empty($end_time)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng chọn thời gian kết thúc!']);
        exit;
    }

    $items = json_decode($items_json, true);
    if (!is_array($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Dữ liệu sản phẩm không hợp lệ!']);
        exit;
    }

    // Upsert config
    $existing = $conn->query("SELECT id FROM flash_sale_config LIMIT 1");
    if ($existing && $existing->num_rows > 0) {
        $cfg_id = $existing->fetch_assoc()['id'];
        $stmt = $conn->prepare(
            "UPDATE flash_sale_config SET title=?, end_time=?, default_discount=?, is_active=? WHERE id=?"
        );
        $stmt->bind_param('ssiii', $title, $end_time, $default_discount, $is_active, $cfg_id);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO flash_sale_config (title, end_time, default_discount, is_active) VALUES (?,?,?,?)"
        );
        $stmt->bind_param('ssii', $title, $end_time, $default_discount, $is_active);
    }
    $stmt->execute();
    $stmt->close();

    // Clear old items & insert new
    $conn->query("DELETE FROM flash_sale_items");
    if (!empty($items)) {
        $stmt2 = $conn->prepare(
            "INSERT INTO flash_sale_items (product_id, discount_type, discount_value) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE discount_type=VALUES(discount_type), discount_value=VALUES(discount_value)"
        );
        foreach ($items as $item) {
            $pid     = (int)$item['product_id'];
            $dtype   = in_array($item['discount_type'], ['percent','fixed']) ? $item['discount_type'] : 'percent';
            $dvalue  = (float)$item['discount_value'];
            $stmt2->bind_param('isd', $pid, $dtype, $dvalue);
            $stmt2->execute();
        }
        $stmt2->close();
    }

    echo json_encode(['status' => 'success', 'message' => 'Lưu Flash Sale thành công!']);
    exit;
}

if ($action === 'get_flash_sale') {
    // API Public: Lấy dữ liệu flash sale cho index.php
    $cfg = null;
    $res = $conn->query("SELECT * FROM flash_sale_config WHERE is_active = 1 LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $cfg = $res->fetch_assoc();
    }

    if (!$cfg) {
        echo json_encode(['status' => 'inactive']);
        exit;
    }

    // Kiểm tra hết hạn
    if (strtotime($cfg['end_time']) < time()) {
        echo json_encode(['status' => 'expired']);
        exit;
    }

    $products = [];
    $res_items = $conn->query("
        SELECT fsi.discount_type, fsi.discount_value,
               p.id, p.name, p.price, p.sale_price, p.image, p.stock
        FROM flash_sale_items fsi
        JOIN products p ON p.id = fsi.product_id
        WHERE p.stock > 0
        LIMIT 8
    ");
    if ($res_items) {
        while ($row = $res_items->fetch_assoc()) {
            $original_price = (float)$row['price'];
            $disc_type  = $row['discount_type'];
            $disc_val   = (float)$row['discount_value'];

            if ($disc_type === 'percent') {
                $sale_price = $original_price * (1 - $disc_val / 100);
            } else {
                $sale_price = $original_price - $disc_val;
            }
            $sale_price = max(0, $sale_price);

            $row['flash_price'] = round($sale_price);
            $row['discount_display'] = $disc_type === 'percent'
                ? "-{$disc_val}%"
                : "-" . number_format($disc_val) . "đ";
            $products[] = $row;
        }
    }

    echo json_encode([
        'status'   => 'active',
        'config'   => $cfg,
        'products' => $products
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
