<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

// =============================================
// SECURITY: Kiểm tra session tồn tại trước khi truy cập key
// FIXED: isset($_SESSION['role']) trước, tránh PHP Warning & bypass khi session chưa có
// =============================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

// -----------------------------------------------
// ACTION: UPDATE ORDER STATUS
// -----------------------------------------------
if ($action === 'update_status') {
    // SECURITY: Xác thực CSRF token trước khi thay đổi trạng thái đơn hàng
    csrf_verify_or_die();
    $order_id = (int)($_POST['order_id'] ?? 0);

    // SECURITY: Whitelist các giá trị status hợp lệ
    $allowed_statuses = ['pending', 'shipping', 'completed', 'cancelled'];
    $status = $_POST['status'] ?? '';
    if (!in_array($status, $allowed_statuses, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Trạng thái không hợp lệ']);
        exit;
    }

    if ($order_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID đơn hàng không hợp lệ']);
        exit;
    }

    // Lấy dữ liệu cũ phục vụ Log
    $stmt_old = $conn->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt_old->bind_param("i", $order_id);
    $stmt_old->execute();
    $old_data = $stmt_old->get_result()->fetch_assoc();
    $stmt_old->close();

    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $order_id);

    if ($stmt->execute()) {
        $stmt->close();
        
        // Lấy thông tin user_id để gửi thông báo
        $stmt_u = $conn->prepare("SELECT user_id, order_code FROM orders WHERE id = ?");
        $stmt_u->bind_param("i", $order_id);
        $stmt_u->execute();
        $u_info = $stmt_u->get_result()->fetch_assoc();
        $stmt_u->close();

        if ($u_info && $u_info['user_id'] > 0) {
            $n_msg = "Đơn hàng #{$u_info['order_code']} của bạn đã chuyển sang trạng thái: " . strtoupper($status);
            $n_link = "/order_history.php";
            $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'order_status', 'Cập nhật đơn hàng', ?, ?, 0, NOW())");
            if ($n_stmt) {
                $n_stmt->bind_param("iss", $u_info['user_id'], $n_msg, $n_link);
                $n_stmt->execute();
                $n_stmt->close();
            }
        }

        include_once '../includes/admin_logger.php';
        logAdminAction(
            $conn,
            'Cập nhật Đơn hàng',
            'api/admin_api.php',
            "Đổi trạng thái đơn #$order_id thành: $status",
            $old_data,
            ['status' => $status]
        );
        echo json_encode(['status' => 'success']);
    } else {
        error_log("Update order status DB Error: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Cập nhật thất bại']);
    }
    exit;
}

// -----------------------------------------------
// ACTION: GET ORDER DETAIL
// -----------------------------------------------
if ($action === 'get_order_detail') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    if ($order_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID không hợp lệ']);
        exit;
    }

    $stmt = $conn->prepare("SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng']);
        exit;
    }

    $stmt_items = $conn->prepare("SELECT od.*, p.name, p.image FROM order_details od JOIN products p ON od.product_id = p.id WHERE od.order_id = ?");
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $res_items = $stmt_items->get_result();
    $stmt_items->close();

    $items = [];
    while ($row = $res_items->fetch_assoc()) {
        $items[] = $row;
    }

    echo json_encode(['status' => 'success', 'order' => $order, 'items' => $items]);
    exit;
}
?>