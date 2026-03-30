<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

include '../config/db.php';
include_once '../includes/security.php';
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
    // SECURITY: Xác thực CSRF Token
    csrf_verify_or_die();
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

            // 1. TÍCH HỢP BỘ NHỚ NGỮ CẢNH (MEMORY)
            $history_messages = [];
            $history_sql = "SELECT message, role FROM chat_messages 
                            WHERE (sender_id = $current_chat_id AND receiver_id = 9999) 
                               OR (sender_id = 9999 AND receiver_id = $current_chat_id) 
                            ORDER BY created_at DESC LIMIT 10";
            $history_res = $conn->query($history_sql);
            if ($history_res && $history_res->num_rows > 0) {
                while($row = $history_res->fetch_assoc()) {
                    $history_messages[] = [
                        "role" => ($row['role'] == 'bot') ? 'model' : 'user',
                        "parts" => [["text" => $row['message']]]
                    ];
                }
            }
            $history_messages = array_reverse($history_messages); // Đảo ngược thành từ cũ đến mới

            // 2. TÍCH HỢP RAG TÌM KIẾM SẢN PHẨM KHỚP Ý ĐỊNH MUA HÀNG
            $categories = ['điện thoại', 'màn hình', 'đồng hồ', 'linh kiện', 'pc', 'loa', 'máy ảnh', 'laptop', 'tai nghe', 'chuột', 'bàn phím', 'phụ kiện', 'smartwatch'];
            $brands = ['iphone', 'samsung', 'xiaomi', 'oppo', 'vivo', 'asus', 'dell', 'hp', 'macbook', 'apple', 'sony', 'jbl', 'lg'];
            $action_keywords = ['giá', 'mua', 'bao nhiêu', 'rẻ', 'tư vấn', 'tìm', 'có'];
            $keywords = array_merge($categories, $brands, $action_keywords);
            
            $msg_lower = mb_strtolower($msg, 'UTF-8');
            $has_intent = false;
            foreach ($keywords as $kw) {
                if (strpos($msg_lower, $kw) !== false) {
                    $has_intent = true;
                    break;
                }
            }

            $product_context = "";
            if ($has_intent) {
                $search_term = '';
                
                // Trích xuất thương hiệu hoặc loại sản phẩm
                foreach (array_merge($brands, $categories) as $item) {
                    if (strpos($msg_lower, $item) !== false) {
                        $search_term = $item;
                        break;
                    }
                }
                
                if (empty($search_term)) {
                     // Dự phòng tìm kiếm từ khóa bất kỳ nếu chỉ hỏi "tư vấn", "giá",...
                     preg_match_all('/\b\w+\b/u', mb_strtolower(str_replace($action_keywords, '', $msg), 'UTF-8'), $matches);
                     if (!empty($matches[0])) {
                         $search_term = implode(' ', array_slice($matches[0], 0, 2));
                     }
                }
                
                $like_term = "%{$search_term}%";
                if ($search_term == '') $like_term = "%";
                $search_stmt = $conn->prepare("SELECT id, name, price FROM products WHERE name LIKE ? LIMIT 5");
                $search_stmt->bind_param("s", $like_term);
                $search_stmt->execute();
                $search_res = $search_stmt->get_result();
                
                if ($search_res->num_rows > 0) {
                    $product_context = "Hiện tại kho đang có các sản phẩm sau:\n";
                    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
                    
                    while ($p = $search_res->fetch_assoc()) {
                        $price_format = number_format($p['price'], 0, ',', '.') . 'đ';
                        $url_product = $base_url . "/product_detail.php?id=" . $p['id']; 
                        $product_context .= "- {$p['name']} (Giá: {$price_format} - Link: {$url_product})\n";
                    }
                } else {
                    $product_context = "Dạ hiện tại sản phẩm mẫu này bên trong kho đang tạm hết hàng...";
                }
                $search_stmt->close();
            }

            // 3. TỐI ƯU PERSONA & SYSTEM INSTRUCTIONS
            $system_prompt = "Bạn là chuyên gia tư vấn công nghệ của cửa hàng MobileStore (bán điện thoại, máy tính, laptop, đồng hồ thông minh, phụ kiện, âm thanh). Xưng hô (Dạ/Thưa, Em/Anh/Chị), câu trả lời ngắn gọn, xuống dòng RÕ RÀNG để không bị đặc chữ, ưu tiên tập trung chốt sale.\nBẮT BUỘC trả về link sản phẩm dưới dạng Markdown [Tên Sản Phẩm](Link).\nDựa vào thông tin kho hàng sau đây để tư vấn:\n" . $product_context;

            $data = [
                "system_instruction" => [
                    "parts" => [
                        [
                            "text" => $system_prompt
                        ]
                    ]
                ],
                "contents" => $history_messages
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
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Xử lý lỗi & Timeout (Error Handling)
            $bot_reply = "Dạ hiện tại hệ thống AI đang bảo trì, anh/chị vui lòng để lại lời nhắn, nhân viên thật của Shop sẽ hỗ trợ anh/chị ngay ạ!";
            
            if ($response && !$curl_err && $http_code == 200) {
                $res_data = json_decode($response, true);
                
                if (isset($res_data['candidates'][0]['content']['parts'][0]['text'])) {
                    $raw_reply = trim($res_data['candidates'][0]['content']['parts'][0]['text']);
                    
                    // Gemini đôi khi trả về thẻ <br> dở dang, ta chuẩn hóa thành \n trước khi htmlspecialchars
                    $raw_reply = str_ireplace(['<br>', '<br/>', '<br />', '&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;'], "\n", $raw_reply);
                    
                    // NÉN NHIỀU KHOẢNG TRẮNG XUỐNG DÒNG (THU GỌN KHOẢNG CÁCH)
                    $raw_reply = preg_replace("/\n+/", "\n", $raw_reply);
                    
                    $raw_reply = htmlspecialchars($raw_reply);
                    // Lọc Markdown (in đậm)
                    $raw_reply = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $raw_reply);
                    // Lọc Markdown đường dẫn link (RAG)
                    $raw_reply = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank" style="color:#0d47a1; font-weight:bold;">$1</a>', $raw_reply);
                    
                    $bot_reply = nl2br($raw_reply);
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
    // SECURITY: Xác thực CSRF Token
    csrf_verify_or_die();
    $message = $_POST['message'] ?? '';
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    
    if ($message != '' && $receiver_id > 0) {
        // sender_id = 0 mặc định là Admin, role = 'shop' để phân biệt giao diện
        $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, role, is_read) VALUES (0, ?, ?, 'shop', 0)");
        $stmt->bind_param("is", $receiver_id, $message);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// 4. CHECK NOTIFICATION CỦA ADMIN GỬI USER
if ($action == 'check_notification') {
    $unread = 0;
    if ($real_user_id > 0) {
        $res = $conn->query("SELECT COUNT(id) as c FROM chat_messages WHERE receiver_id = $real_user_id AND sender_id = 0 AND is_read = 0");
        $unread = $res->fetch_assoc()['c'] ?? 0;
    } else if ($current_chat_id < 0) {
        $res = $conn->query("SELECT COUNT(id) as c FROM chat_messages WHERE receiver_id = $current_chat_id AND sender_id = 0 AND is_read = 0");
        $unread = $res->fetch_assoc()['c'] ?? 0;
    }
    echo json_encode(['unread' => $unread]);
    exit;
}

// 5. ĐÁNH DẤU ĐÃ ĐỌC
if ($action == 'mark_read') {
    // SECURITY: Xác thực CSRF Token
    csrf_verify_or_die();
    $target_id = intval($_POST['target_id'] ?? 0);
    if ($target_id > 0) {
        $conn->query("UPDATE chat_messages SET is_read = 1 WHERE sender_id = $target_id AND receiver_id = 0");
    }
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action == 'mark_read_user' && $real_user_id > 0) {
    // SECURITY: Xác thực CSRF Token
    csrf_verify_or_die();
    $conn->query("UPDATE chat_messages SET is_read=1 WHERE receiver_id=$real_user_id AND sender_id=0");
    echo json_encode(['status'=>'success']); exit;
}


?>