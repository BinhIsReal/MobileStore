<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

include '../config/db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$tab = $_POST['tab'] ?? 'bot';

// --- XÁC ĐỊNH ID NGƯỜI DÙNG ---
$real_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

if ($real_user_id > 0) {
    // Đã đăng nhập -> Dùng ID thật
    $current_chat_id = $real_user_id;
} else {
    $crc = crc32(session_id()); 
    $crc = sprintf('%u', $crc); 
    $guest_id = -1 * ($crc % 2000000000); 
    
    if ($guest_id == 0) $guest_id = -1;
    
    $current_chat_id = $guest_id;
}

// Hàm lấy giá tiền
function extractPrice($str) {
    $str = mb_strtolower($str, 'UTF-8');
    preg_match('/(\d+([.,]\d+)?)\s*(tr|triệu|k|nghìn)?/', $str, $matches);
    if (!empty($matches[1])) {
        $num = floatval(str_replace(',', '.', $matches[1]));
        $unit = $matches[3] ?? '';
        if ($unit == 'tr' || $unit == 'triệu') return $num * 1000000;
        if ($unit == 'k' || $unit == 'nghìn') return $num * 1000;
        if ($num < 1000) return $num * 1000000;
        return $num;
    }
    return 0;
}

// ---------------------------------------------------------
// 1. GỬI TIN NHẮN
// ---------------------------------------------------------
if ($action == 'send_message') {
    $msg = trim($_POST['message'] ?? '');

    // CHECK QUYỀN: Chat Shop bắt buộc đăng nhập
    if ($tab == 'shop' && $real_user_id == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập để chat với nhân viên!']);
        exit;
    }

    if (!empty($msg)) {
        // Chat Bot -> ID 9999 | Chat Shop -> ID 0
        $receiver_id = ($tab == 'bot') ? 9999 : 0;
        
        $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, role, created_at) VALUES (?, ?, ?, 'user', NOW())");
        $stmt->bind_param("iis", $current_chat_id, $receiver_id, $msg);
        
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $stmt->error]);
            exit;
        }

        // --- BOT TRẢ LỜI (Chỉ khi tab = bot) ---
        if ($tab == 'bot') {
            $bot_reply = "";
            $msg_lower = mb_strtolower($msg, 'UTF-8');

            // 1. Rẻ nhất / Đắt nhất
            if (strpos($msg_lower, 'rẻ nhất') !== false) {
                $res = $conn->query("SELECT * FROM products ORDER BY price ASC LIMIT 1");
                if ($p = $res->fetch_assoc()) $bot_reply = "Rẻ nhất là: <b>{$p['name']}</b> giá " . number_format($p['price']) . "đ.";
            } elseif (strpos($msg_lower, 'đắt nhất') !== false) {
                $res = $conn->query("SELECT * FROM products ORDER BY price DESC LIMIT 1");
                if ($p = $res->fetch_assoc()) $bot_reply = "Cao cấp nhất là: <b>{$p['name']}</b> giá " . number_format($p['price']) . "đ.";
            }

            // 2. Khoảng giá
            if (empty($bot_reply) && preg_match('/(triệu|tr|giá)/', $msg_lower)) {
                $price = extractPrice($msg_lower);
                if ($price > 0) {
                    $min = $price - 2000000; $max = $price + 2000000;
                    if (strpos($msg_lower, 'dưới') !== false) { $min = 0; $max = $price; }
                    if (strpos($msg_lower, 'trên') !== false) { $min = $price; $max = 100000000; }
                    
                    $res = $conn->query("SELECT * FROM products WHERE price BETWEEN $min AND $max LIMIT 3");
                    if ($res && $res->num_rows > 0) {
                        $bot_reply = "Tìm thấy:<br>";
                        while ($p = $res->fetch_assoc()) $bot_reply .= "- <a href='product_detail.php?id={$p['id']}'>{$p['name']}</a>: " . number_format($p['price']) . "đ<br>";
                    } else $bot_reply = "Không tìm thấy máy nào tầm giá này ạ.";
                }
            }

            // 3. Kịch bản tư vấn
            if (empty($bot_reply)) {
                $kb = [
                    ['keys' => ['game'], 'reply' => 'Chơi game nên chọn Xiaomi hoặc iPhone chip khỏe.'],
                    ['keys' => ['chụp', 'ảnh'], 'reply' => 'Chụp ảnh đẹp chọn Samsung S hoặc iPhone.'],
                    ['keys' => ['pin', 'sạc'], 'reply' => 'Pin 5000mAh dùng thoải mái cả ngày.'],
                    ['keys' => ['góp', 'khuyến mãi'], 'reply' => 'Shop hỗ trợ trả góp 0% nhé.'],
                    ['keys' => ['chào', 'hi'], 'reply' => 'Chào bạn! Bạn cần tìm máy nào?']
                ];
                foreach ($kb as $item) {
                    foreach ($item['keys'] as $k) { if (strpos($msg_lower, $k) !== false) { $bot_reply = $item['reply']; break 2; } }
                }
            }

            // 4. Tìm theo tên
           if (empty($bot_reply)) {
            $kw = trim(str_replace(['giá', 'mua', 'tìm', 'điện thoại'], '', $msg_lower));
            if (strlen($kw) > 1) {
                // Sử dụng JOIN để tìm kiếm cả trong tên sản phẩm và tên hãng
                $sql_bot = "SELECT p.* FROM products p 
                            LEFT JOIN brands b ON p.brand_id = b.id 
                            WHERE p.name LIKE ? OR b.name LIKE ? 
                            LIMIT 3";
                $stmt_bot = $conn->prepare($sql_bot);
                $search_kw = "%$kw%";
                $stmt_bot->bind_param("ss", $search_kw, $search_kw);
                $stmt_bot->execute();
                $res = $stmt_bot->get_result();

                if ($res && $res->num_rows > 0) {
                    $bot_reply = "MobileStore tìm thấy sản phẩm phù hợp:<br>";
                    while ($p = $res->fetch_assoc()) {
                        $bot_reply .= "- <a href='product_detail.php?id={$p['id']}'>{$p['name']}</a>: " . number_format($p['price']) . "đ<br>";
            }
        }
    }
}

            if (empty($bot_reply)) $bot_reply = "Bạn thử hỏi 'giá iphone' hoặc 'tầm 5 triệu' xem sao?";

            // Lưu tin Bot trả lời
            $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, role, created_at) VALUES (9999, ?, ?, 'bot', NOW())");
            $stmt->bind_param("is", $current_chat_id, $bot_reply);
            $stmt->execute();
        }
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// ---------------------------------------------------------
// 2. LẤY TIN NHẮN
// ---------------------------------------------------------
if ($action == 'get_messages') {
    if ($tab == 'bot') {
        // Bot: Lấy theo ID hiện tại (User thật hoặc Khách tạm)
        $sql = "SELECT * FROM chat_messages 
                WHERE (sender_id = $current_chat_id AND receiver_id = 9999) 
                   OR (sender_id = 9999 AND receiver_id = $current_chat_id) 
                ORDER BY created_at ASC";
    } else {
        // Shop: Chỉ lấy khi đã đăng nhập
        if ($real_user_id == 0) { echo json_encode([]); exit; }
        
        $sql = "SELECT * FROM chat_messages 
                WHERE (sender_id = $real_user_id AND receiver_id = 0) 
                   OR (sender_id = 0 AND receiver_id = $real_user_id) 
                ORDER BY created_at ASC";
    }

    $result = $conn->query($sql);
    $msgs = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $msgs[] = [
                'sender_id' => $row['sender_id'],
                'message' => $row['message'],
                'role' => $row['role'], 
                'time' => date('H:i', strtotime($row['created_at']))
            ];
        }
    }
    echo json_encode($msgs);
    exit;
}


if ($action == 'init_admin_chat') {
    $target_id = intval($_POST['user_id'] ?? 0);
    
    if ($target_id > 0) {
        // Kiểm tra xem đã có tin nhắn nào giữa Shop và User này chưa
        $check_sql = "SELECT id FROM chat_messages WHERE (sender_id = $target_id AND receiver_id = 0) OR (sender_id = 0 AND receiver_id = $target_id) LIMIT 1";
        $check_res = $conn->query($check_sql);
        
        if ($check_res->num_rows == 0) {
            // $system_msg = "MobileStore Xin kính chào quý khách.";
            $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, is_read) VALUES (0, ?, ?, 1)");
            $stmt->bind_param("is", $target_id, $system_msg);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}
// ---------------------------------------------------------
// 3. ADMIN 
// ---------------------------------------------------------
// 1. LẤY DANH SÁCH USER (Có tích hợp Tìm kiếm và Ép hiển thị User đang chọn)
if ($action == 'get_chat_users') {
    $search = $_POST['search'] ?? '';
    $active_user = intval($_POST['active_user'] ?? 0);
    $search_esc = $conn->real_escape_string($search);

    // Mặc định: Chỉ lấy những khách đã từng chat
    $where = "(u.id IN (SELECT sender_id FROM chat_messages) OR u.id IN (SELECT receiver_id FROM chat_messages))";

    // Nếu Admin vừa click từ trang Khách hàng sang (có active_user), ép lấy cả người đó
    if ($active_user > 0) {
        $where = "($where OR u.id = $active_user)";
    }

    // Nếu Admin đang gõ tìm kiếm
    if ($search !== '') {
        $where = "(u.id = '$search_esc' OR u.username LIKE '%$search_esc%')";
    }

    $sql = "SELECT u.id, u.username,
            (SELECT COUNT(id) FROM chat_messages WHERE sender_id = u.id AND is_read = 0 AND receiver_id = 0) as unread
            FROM users u
            WHERE u.role = 'user' AND $where
            ORDER BY unread DESC, u.id DESC";
            
    $res = $conn->query($sql);
    $users = [];
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $users[] = $row;
        }
    }
    echo json_encode($users);
    exit;
}

// 2. LẤY NỘI DUNG CUỘC TRÒ CHUYỆN
if ($action == 'get_conversation') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $sql = "SELECT * FROM chat_messages 
            WHERE (sender_id = $user_id AND receiver_id = 0) 
               OR (sender_id = 0 AND receiver_id = $user_id) 
            ORDER BY created_at ASC";
    $res = $conn->query($sql);
    $msgs = [];
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $msgs[] = $row;
        }
    }
    echo json_encode($msgs);
    exit;
}

// 3. ADMIN GỬI TIN NHẮN
if ($action == 'send_msg') {
    $message = $_POST['message'] ?? '';
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    
    if ($message != '' && $receiver_id > 0) {
        // sender_id = 0 mặc định là Admin
        $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, is_read) VALUES (0, ?, ?, 0)");
        $stmt->bind_param("is", $receiver_id, $message);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// 4. ĐÁNH DẤU ĐÃ ĐỌC
if ($action == 'mark_read') {
    $target_id = intval($_POST['target_id'] ?? 0);
    if ($target_id > 0) {
        $conn->query("UPDATE chat_messages SET is_read = 1 WHERE sender_id = $target_id AND receiver_id = 0");
    }
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action == 'mark_read_user' && $real_user_id > 0) {
    $conn->query("UPDATE chat_messages SET is_read=1 WHERE receiver_id=$real_user_id AND sender_id=0");
    echo json_encode(['status'=>'success']); exit;
}


?>