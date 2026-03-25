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

        // --- BOT TRẢ LỜI BẰNG GEMINI API ---
        if ($tab == 'bot') {
            $apiKey = "AIzaSyBdJWezPzPSaLMN7XMHyFyxa0bUryw1-Vg";
            $model = "gemini-2.5-flash"; 
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $data = [
                "system_instruction" => [
                    "parts" => [
                        [
                            "text" => "Bạn là một nhân viên tư vấn bán hàng điện thoại nhiệt tình của cửa hàng MobileStore. Hãy trả lời bằng tiếng Việt thật tự nhiên, ngắn gọn (dưới 50 từ), thân thiện và tập trung vào việc tư vấn sản phẩm."
                        ]
                    ]
                ],
                "contents" => [
                    [
                        "role" => "user",
                        "parts" => [
                            [ "text" => $msg ]
                        ]
                    ]
                ]
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $curl_err = curl_error($ch);
            curl_close($ch);
            
            $bot_reply = "Xin lỗi, hiện tại BOT đang quá tải do nhiều người liên hệ cùng lúc. Vui lòng chọn chat trực tiếp với Shop nhé!";
            
            if ($response && !$curl_err) {
                $res_data = json_decode($response, true);
                
                if (isset($res_data['candidates'][0]['content']['parts'][0]['text'])) {
                    $raw_reply = trim($res_data['candidates'][0]['content']['parts'][0]['text']);
                    
                    // Lọc Markdown (in đậm)
                    $raw_reply = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $raw_reply);
                    $bot_reply = nl2br(htmlspecialchars($raw_reply));
                    $bot_reply = str_replace(['&lt;b&gt;', '&lt;/b&gt;'], ['<b>', '</b>'], $bot_reply);
                }
            }

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