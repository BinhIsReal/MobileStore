<?php
/**
 * notification_api.php
 * Lấy & đánh dấu đã đọc notifications của User
 */
session_start();
include '../config/db.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

// Lấy danh sách notifications
if ($action === 'get_notifications') {
    $limit = min(20, (int)($_GET['limit'] ?? 10));
    $stmt = $conn->prepare("
        SELECT id, type, title, message, link, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $items = [];
    while ($row = $res->fetch_assoc()) $items[] = $row;

    $unread_stmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
    $unread_stmt->bind_param("i", $user_id);
    $unread_stmt->execute();
    $unread = $unread_stmt->get_result()->fetch_assoc()['c'];
    $unread_stmt->close();

    echo json_encode(['status' => 'success', 'data' => $items, 'unread' => $unread]);
    exit;
}

// Đánh dấu đã đọc 1 hoặc tất cả
if ($action === 'mark_read') {
    $noti_id = (int)($_POST['id'] ?? 0);
    if ($noti_id > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $noti_id, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success']);
    exit;
}

// Đếm số noti chưa đọc (dùng cho badge)
if ($action === 'count_unread') {
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    echo json_encode(['unread' => $count]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
