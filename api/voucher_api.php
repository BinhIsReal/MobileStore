<?php
session_start();
include '../config/db.php';

// Kiểm tra đăng nhập chung
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

// Phân quyền: Mặc định chỉ Admin mới được tạo và gán Voucher
if (in_array($action, ['create_voucher', 'assign_voucher'])) {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized admin action']);
        exit;
    }
}

// 1. TẠO VOUCHER MỚI
if ($action === 'create_voucher') {
    $code = strtoupper(trim($_POST['code']));
    $type = $_POST['type'];
    $discount_amount = $_POST['discount_amount'];
    $max_discount = !empty($_POST['max_discount']) ? $_POST['max_discount'] : 0;
    
    // Lấy giá trị min_order_value từ form
    $min_order_value = !empty($_POST['min_order_value']) ? $_POST['min_order_value'] : 0;
    
    $expiry_date = $_POST['expiry_date'];

    // Kiểm tra trùng code
    $check = $conn->query("SELECT id FROM vouchers WHERE code = '$code'");
    if ($check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Mã Voucher này đã tồn tại!']);
        exit;
    }

    // Câu lệnh INSERT có đầy đủ 6 cột
    $sql = "INSERT INTO vouchers (code, type, discount_amount, max_discount, min_order_value, expiry_date) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    // Ràng buộc dữ liệu: 2 chuỗi (s), 3 số thực (d), 1 chuỗi ngày tháng (s) -> ssddds
    $stmt->bind_param("ssddds", $code, $type, $discount_amount, $max_discount, $min_order_value, $expiry_date);
    
    if ($stmt->execute()) {
        include_once '../includes/admin_logger.php';
        logAdminAction($conn, 'Thêm Voucher', 'api/voucher_api.php', "Tạo mã giảm giá mới: $code", null, [
            'code' => $code, 'type' => $type, 'discount_amount' => $discount_amount
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Tạo Voucher thành công!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi DB: ' . $conn->error]);
    }
}

// 1.5 CẬP NHẬT VOUCHER
if ($action === 'update_voucher') {
    $voucher_id = intval($_POST['voucher_id']);
    $type = $_POST['type'];
    $discount_amount = $_POST['discount_amount'];
    $max_discount = !empty($_POST['max_discount']) ? $_POST['max_discount'] : 0;
    $min_order_value = !empty($_POST['min_order_value']) ? $_POST['min_order_value'] : 0;
    $expiry_date = $_POST['expiry_date'];

    // Lấy dữ liệu cũ phục vụ Log
    $res_old = $conn->query("SELECT * FROM vouchers WHERE id = $voucher_id");
    if ($res_old->num_rows == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy Voucher!']);
        exit;
    }
    $old_data = $res_old->fetch_assoc();

    $sql = "UPDATE vouchers SET type=?, discount_amount=?, max_discount=?, min_order_value=?, expiry_date=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdddsi", $type, $discount_amount, $max_discount, $min_order_value, $expiry_date, $voucher_id);
    
    if ($stmt->execute()) {
        include_once '../includes/admin_logger.php';
        logAdminAction($conn, 'Sửa Voucher', 'api/voucher_api.php', "Cập nhật mã giảm giá: " . $old_data['code'], $old_data, [
            'type' => $type, 'discount_amount' => $discount_amount, 'max_discount' => $max_discount, 'min_order_value' => $min_order_value, 'expiry_date' => $expiry_date
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Cập nhật Voucher thành công!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi DB: ' . $conn->error]);
    }
}

// 2. GÁN VOUCHER CHO USER
if ($action === 'assign_voucher') {
    $voucher_id = intval($_POST['voucher_id']);
    $usage_limit = intval($_POST['usage_limit']);
    $assign_all = intval($_POST['assign_all']);
    
    $user_ids = [];

    if ($assign_all === 1) {
        // Lấy tất cả user
        $res = $conn->query("SELECT id FROM users WHERE role != 'admin'");
        while ($r = $res->fetch_assoc()) {
            $user_ids[] = $r['id'];
        }
    } else {
        $user_ids = $_POST['user_ids'] ?? [];
    }

    if (empty($user_ids)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng chọn ít nhất 1 người dùng!']);
        exit;
    }

    $success_count = 0;
    $stmt = $conn->prepare("INSERT INTO user_vouchers (user_id, voucher_id, usage_limit, is_new) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE usage_limit = usage_limit + ?");
    
    // Chú ý: Cột is_new = 1 dùng để báo hiệu Popup cho user biết họ có quà mới

    foreach ($user_ids as $uid) {
        $stmt->bind_param("iiii", $uid, $voucher_id, $usage_limit, $usage_limit);
        if($stmt->execute()) $success_count++;
    }
    
    if ($success_count > 0) {
        include_once '../includes/admin_logger.php';
        logAdminAction($conn, 'Gán Voucher', 'api/voucher_api.php', "Phát Voucher ID #$voucher_id cho $success_count người dùng", null, ['voucher_id' => $voucher_id, 'users_count' => $success_count, 'usage_limit' => $usage_limit]);
    }

    echo json_encode(['status' => 'success', 'message' => "Đã gán thành công cho $success_count người dùng."]);
}

if ($action === 'get_my_vouchers') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập.']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Lấy voucher còn hiệu lực
    $sql = "SELECT v.id, v.code, v.type, v.discount_amount, v.max_discount, v.min_order_value 
            FROM user_vouchers uv
            JOIN vouchers v ON uv.voucher_id = v.id
            WHERE uv.user_id = ? 
              AND (v.expiry_date >= CURDATE() OR v.expiry_date = '0000-00-00')
              AND uv.usage_limit > uv.used_count
            ORDER BY v.discount_amount DESC";
            
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
         echo json_encode(['status' => 'error', 'message' => 'Lỗi SQL: ' . $conn->error]);
         exit;
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $vouchers = [];
    while($row = $result->fetch_assoc()) {
        $vouchers[] = $row;
    }
    
    echo json_encode(['status' => 'success', 'data' => $vouchers]);
    exit;
}
?>