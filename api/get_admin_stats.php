<?php
// File: api/get_admin_stats.php
include_once '../config/db.php';

header('Content-Type: application/json');

// Mặc định trả về 0
$response = [
    'order_count' => 0,
    'chat_count' => 0
];

try {
    $sql_ord = "SELECT COUNT(*) as total FROM orders WHERE status = 'pending'";
    $res_ord = $conn->query($sql_ord);
    if ($res_ord && $row = $res_ord->fetch_assoc()) {
        $response['order_count'] = intval($row['total']);
    }

    $sql_chat = "SELECT COUNT(m.id) as total FROM chat_messages m JOIN users u ON m.sender_id = u.id WHERE m.is_read = 0 AND m.receiver_id = 0 AND u.role = 'user'";
    
    $res_chat = $conn->query($sql_chat);
    if ($res_chat && $row = $res_chat->fetch_assoc()) {
        $response['chat_count'] = intval($row['total']);
    }

} catch (Exception $e) {
}

echo json_encode($response);
?>