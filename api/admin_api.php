<?php
session_start();
include '../config/db.php';

if ($_SESSION['role'] != 'admin') die('Unauthorized');

if (isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    
    // Lấy dữ liệu cũ
    $res_old = $conn->query("SELECT status FROM orders WHERE id = " . intval($order_id));
    $old_data = $res_old->fetch_assoc();
    
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $order_id);
    
    if ($stmt->execute()) {
        include_once '../includes/admin_logger.php';
        logAdminAction($conn, 'Cập nhật Đơn hàng', 'api/admin_api.php', "Đổi trạng thái đơn #$order_id thành: $status", $old_data, ['status' => $status]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'get_order_detail') {
    $order_id = intval($_POST['order_id']);
    
    // Thông tin chung
    $stmt = $conn->prepare("SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    
    if (!$order) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng']);
        exit;
    }
    
    // Chi tiết sản phẩm
    $stmt_items = $conn->prepare("SELECT od.*, p.name, p.image FROM order_details od JOIN products p ON od.product_id = p.id WHERE od.order_id = ?");
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $res_items = $stmt_items->get_result();
    
    $items = [];
    while ($row = $res_items->fetch_assoc()) {
        $items[] = $row;
    }
    
    echo json_encode(['status' => 'success', 'order' => $order, 'items' => $items]);
    exit;
}
?>