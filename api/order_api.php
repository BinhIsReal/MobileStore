<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập!']);
    exit;
}

// XỬ LÝ HỦY ĐƠN HÀNG
if ($action == 'cancel_order') {
    csrf_verify_or_die();
    $order_id = intval($_POST['order_id']);

    // 1. Kiểm tra đơn hàng có tồn tại và thuộc về user này không?
    // Đồng thời kiểm tra trạng thái hiện tại phải là 'pending'
    $sql_check = "SELECT status FROM orders WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Đơn hàng không tồn tại hoặc bạn không có quyền truy cập.']);
        exit;
    }

    $order = $res->fetch_assoc();

    // 2. Chỉ cho phép hủy nếu trạng thái là 'pending'
    if ($order['status'] !== 'pending') {
        echo json_encode(['status' => 'error', 'message' => 'Không thể hủy đơn hàng này (Đang giao hoặc đã hoàn thành).']);
        exit;
    }

    // 3. Thực hiện hủy đơn
    $sql_update = "UPDATE orders SET status = 'cancelled' WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("i", $order_id);

    if ($stmt_update->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Đã hủy đơn hàng thành công.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống, vui lòng thử lại.']);
    }
    exit;
}
// --- THÊM VÀO api/order_api.php ---

if ($action == 'guest_track_order') {
    $oid = intval($_POST['order_id']);
    $phone = trim($_POST['phone']);

    // Query kiểm tra khớp cả ID và SĐT (Bảo mật cơ bản)
    $sql = "SELECT * FROM orders WHERE id = ? AND phone = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $oid, $phone);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {

        $status_map = [
            'pending' => 'Chờ xử lý',
            'shipping' => 'Đang giao hàng',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy'
        ];

        $data = [
            'id' => $row['id'],
            'name' => $row['name'], 
            'created_at' => date('d/m/Y H:i', strtotime($row['created_at'])),
            'total_price' => number_format($row['total_price'], 0, ',', '.') . ' ₫',
            'status_code' => $row['status'],
            'status_text' => $status_map[$row['status']] ?? 'Không xác định'
        ];

        echo json_encode(['status' => 'success', 'data' => $data]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng! Vui lòng kiểm tra lại Mã đơn và SĐT.']);
    }
    exit;
}
?>