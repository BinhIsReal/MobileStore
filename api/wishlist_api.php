<?php
/**
 * wishlist_api.php
 * MODULE 1: Wishlist + Price Alert
 * Actions: add, remove, get_my_wishlist, check_price_changes
 */
session_start();
include '../config/db.php';
include_once '../includes/security.php';
include_once '../includes/flash_sale_helper.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

// -----------------------------------------------
// 1. THÊM VÀO WISHLIST
// -----------------------------------------------
if ($action === 'add') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    if ($product_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Sản phẩm không hợp lệ']);
        exit;
    }

    // Lấy giá hiệu lực: Flash Sale > sale_price > price
    $price_stmt = $conn->prepare("SELECT price, sale_price FROM products WHERE id = ?");
    $price_stmt->bind_param("i", $product_id);
    $price_stmt->execute();
    $p = $price_stmt->get_result()->fetch_assoc();
    $price_stmt->close();

    if (!$p) {
        echo json_encode(['status' => 'error', 'message' => 'Sản phẩm không tồn tại']);
        exit;
    }

    $price_info    = get_effective_price($conn, $product_id, $p['price'], $p['sale_price']);
    $current_price = $price_info['effective_price'];

    $stmt = $conn->prepare(
        "INSERT INTO wishlists (user_id, product_id, price_at_add, alert_enabled)
         VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE price_at_add = ?, alert_enabled = 1"
    );
    $stmt->bind_param("iidd", $user_id, $product_id, $current_price, $current_price);

    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'Đã thêm vào Yêu thích!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống']);
    }
    exit;
}

// -----------------------------------------------
// 2. XÓA KHỎI WISHLIST
// -----------------------------------------------
if ($action === 'remove') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success', 'message' => 'Đã xóa khỏi Yêu thích']);
    exit;
}

// -----------------------------------------------
// 3. BẬT/TẮT PRICE ALERT
// -----------------------------------------------
if ($action === 'toggle_alert') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $enabled    = (int)($_POST['enabled'] ?? 1);
    $stmt = $conn->prepare("UPDATE wishlists SET alert_enabled = ? WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("iii", $enabled, $user_id, $product_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success']);
    exit;
}

// -----------------------------------------------
// 4. LẤY DANH SÁCH WISHLIST CỦA USER
// -----------------------------------------------
if ($action === 'get_my_wishlist') {
    $stmt = $conn->prepare("
        SELECT w.id, w.product_id, w.price_at_add, w.alert_enabled, w.created_at,
               p.name, p.price, p.sale_price, p.image, p.stock,
               CASE
                 WHEN p.sale_price > 0 THEN p.sale_price
                 ELSE p.price
               END AS current_price
        FROM wishlists w
        JOIN products p ON w.product_id = p.id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $items = [];
    $product_ids = [];
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[]        = $row;
        $product_ids[] = (int)$row['product_id'];
    }

    // Batch lấy giá Flash Sale cho toàn bộ wishlist (1 query)
    $flash_map = get_flash_prices_bulk($conn, $product_ids);

    foreach ($rows as $row) {
        $pid = (int)$row['product_id'];
        // Ưu tiên Flash Sale
        if (isset($flash_map[$pid])) {
            $row['current_price']   = $flash_map[$pid]['flash_price'];
            $row['is_flash_sale']   = true;
            $row['discount_label']  = $flash_map[$pid]['discount_label'];
        } else {
            $row['is_flash_sale']  = false;
            $row['discount_label'] = '';
        }
        $row['price_drop']  = ($row['current_price'] < $row['price_at_add']);
        $row['drop_amount'] = max(0, $row['price_at_add'] - $row['current_price']);
        $items[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $items]);
    exit;
}

// -----------------------------------------------
// 5. KIỂM TRA SẢN PHẨM CÓ TRONG WISHLIST KHÔNG
// -----------------------------------------------
if ($action === 'check') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $stmt = $conn->prepare("SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    echo json_encode(['status' => 'success', 'in_wishlist' => $exists]);
    exit;
}

// -----------------------------------------------
// 6. CRONJOB: QUÉT THAY ĐỔI GIÁ & GỬI NOTI
// (Gọi từ cron: GET /api/wishlist_api.php?action=cron_price_check&cron_key=SECRET)
// -----------------------------------------------
if ($action === 'cron_price_check') {
    $cron_key = $_GET['cron_key'] ?? '';
    $env = file_exists(__DIR__ . '/../.env') ? parse_ini_file(__DIR__ . '/../.env') : [];
    $expected_key = $env['CRON_SECRET'] ?? 'good_phone_cron_2024';

    if ($cron_key !== $expected_key) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }

    // Lấy tất cả sản phẩm đang nằm trong wishlist của ai đó
    $products_sql = "
        SELECT DISTINCT p.id, p.name,
               CASE WHEN p.sale_price > 0 THEN p.sale_price ELSE p.price END AS current_price
        FROM wishlists w
        JOIN products p ON w.product_id = p.id
        WHERE w.alert_enabled = 1
    ";
    $products_res = $conn->query($products_sql);

    $notified = 0;
    while ($prod = $products_res->fetch_assoc()) {
        // Lấy giá cũ nhất trong price_history
        $hist = $conn->query("
            SELECT new_price FROM price_history
            WHERE product_id = {$prod['id']}
            ORDER BY changed_at DESC LIMIT 1
        ")->fetch_assoc();

        $last_price = $hist ? $hist['new_price'] : null;

        if ($last_price !== null && abs($prod['current_price'] - $last_price) > 0) {
            // Giá đã thay đổi → Lưu vào price_history
            $conn->query("
                INSERT INTO price_history (product_id, old_price, new_price)
                VALUES ({$prod['id']}, $last_price, {$prod['current_price']})
            ");

            if ($prod['current_price'] < $last_price) {
                // Giá giảm → Gửi thông báo đến từng user có sản phẩm này trong wishlist
                $users_sql = "SELECT user_id FROM wishlists WHERE product_id = {$prod['id']} AND alert_enabled = 1";
                $users_res = $conn->query($users_sql);
                $drop = number_format($last_price - $prod['current_price'], 0, ',', '.');
                $new_price_fmt = number_format($prod['current_price'], 0, ',', '.');

                while ($u = $users_res->fetch_assoc()) {
                    $uid = (int)$u['user_id'];
                    $title = "🔥 Giá giảm: " . mb_substr($prod['name'], 0, 40, 'UTF-8');
                    $msg   = "Sản phẩm \"{$prod['name']}\" trong Wishlist của bạn vừa giảm {$drop}đ, còn {$new_price_fmt}đ!";
                    $link  = "/product_detail.php?id={$prod['id']}";

                    $ns = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'price_drop', ?, ?, ?)");
                    $ns->bind_param("isss", $uid, $title, $msg, $link);
                    $ns->execute();
                    $ns->close();
                    $notified++;
                }

                // Cập nhật price_at_add để lần sau không bị alert lại
                $conn->query("UPDATE wishlists SET price_at_add = {$prod['current_price']} WHERE product_id = {$prod['id']} AND alert_enabled = 1");
            }
        } elseif ($last_price === null) {
            // Lần đầu chạy cron, seed giá ban đầu
            $conn->query("INSERT IGNORE INTO price_history (product_id, old_price, new_price) VALUES ({$prod['id']}, {$prod['current_price']}, {$prod['current_price']})");
        }
    }

    echo json_encode(['status' => 'success', 'notifications_sent' => $notified]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
