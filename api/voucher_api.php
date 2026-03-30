<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

// =============================================
// SECURITY: Bắt buộc đăng nhập
// =============================================
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action  = $_POST['action'] ?? '';
$user_id = (int)$_SESSION['user_id'];

// Phân quyền Admin cho các action tạo/gán/sửa/xóa Voucher
$admin_only_actions = ['create_voucher', 'assign_voucher', 'update_voucher', 'delete_voucher'];
if (in_array($action, $admin_only_actions)) {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized admin action']);
        exit;
    }
    // SECURITY: Xác thực CSRF token cho các action thay đổi dữ liệu
    csrf_verify_or_die();
}

// -----------------------------------------------
// 1. TẠO VOUCHER MỚI
// -----------------------------------------------
if ($action === 'create_voucher') {
    $code             = strtoupper(trim($_POST['code'] ?? ''));
    $type             = $_POST['type'] ?? '';
    $discount_amount  = (float)($_POST['discount_amount'] ?? 0);
    $max_discount     = (float)(!empty($_POST['max_discount']) ? $_POST['max_discount'] : 0);
    $min_order_value  = (float)(!empty($_POST['min_order_value']) ? $_POST['min_order_value'] : 0);
    $expiry_date      = $_POST['expiry_date'] ?? '';

    // SECURITY: Validate input
    if (empty($code) || !preg_match('/^[A-Z0-9_\-]{3,20}$/', $code)) {
        echo json_encode(['status' => 'error', 'message' => 'Mã Voucher không hợp lệ (3-20 ký tự A-Z, 0-9)']);
        exit;
    }

    // SECURITY: Whitelist type
    if (!in_array($type, ['percent', 'fixed'], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Loại giảm giá không hợp lệ']);
        exit;
    }

    // SECURITY: Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry_date)) {
        echo json_encode(['status' => 'error', 'message' => 'Ngày hết hạn không hợp lệ']);
        exit;
    }

    // FIXED: Kiểm tra trùng code dùng Prepared Statement
    $stmt = $conn->prepare("SELECT id FROM vouchers WHERE code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $check = $stmt->get_result();
    $stmt->close();

    if ($check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Mã Voucher này đã tồn tại!']);
        exit;
    }

    $sql  = "INSERT INTO vouchers (code, type, discount_amount, max_discount, min_order_value, expiry_date) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssddds", $code, $type, $discount_amount, $max_discount, $min_order_value, $expiry_date);

    if ($stmt->execute()) {
        $stmt->close();
        include_once '../includes/admin_logger.php';
        logAdminAction($conn, 'Thêm Voucher', 'api/voucher_api.php', "Tạo mã giảm giá mới: $code", null, [
            'code' => $code, 'type' => $type, 'discount_amount' => $discount_amount
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Tạo Voucher thành công!']);
    } else {
        error_log("Create voucher DB Error: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống, vui lòng thử lại.']);
    }
    exit;
}

// -----------------------------------------------
// 1.5 CẬP NHẬT VOUCHER
// -----------------------------------------------
if ($action === 'update_voucher') {
    $voucher_id      = (int)($_POST['voucher_id'] ?? 0);
    $type            = $_POST['type'] ?? '';
    $discount_amount = (float)($_POST['discount_amount'] ?? 0);
    $max_discount    = (float)(!empty($_POST['max_discount']) ? $_POST['max_discount'] : 0);
    $min_order_value = (float)(!empty($_POST['min_order_value']) ? $_POST['min_order_value'] : 0);
    $expiry_date     = $_POST['expiry_date'] ?? '';

    if ($voucher_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Voucher không hợp lệ']);
        exit;
    }

    // SECURITY: Whitelist type
    if (!in_array($type, ['percent', 'fixed'], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Loại giảm giá không hợp lệ']);
        exit;
    }

    // FIXED: Dùng Prepared Statement lấy old_data
    $stmt_old = $conn->prepare("SELECT * FROM vouchers WHERE id = ?");
    $stmt_old->bind_param("i", $voucher_id);
    $stmt_old->execute();
    $res_old = $stmt_old->get_result();
    $stmt_old->close();

    if ($res_old->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy Voucher!']);
        exit;
    }
    $old_data = $res_old->fetch_assoc();

    $stmt = $conn->prepare("UPDATE vouchers SET type=?, discount_amount=?, max_discount=?, min_order_value=?, expiry_date=? WHERE id=?");
    $stmt->bind_param("sdddsi", $type, $discount_amount, $max_discount, $min_order_value, $expiry_date, $voucher_id);

    if ($stmt->execute()) {
        $stmt->close();
        include_once '../includes/admin_logger.php';
        logAdminAction($conn, 'Sửa Voucher', 'api/voucher_api.php', "Cập nhật mã giảm giá: " . ($old_data['code'] ?? ''), $old_data, [
            'type' => $type, 'discount_amount' => $discount_amount, 'max_discount' => $max_discount,
            'min_order_value' => $min_order_value, 'expiry_date' => $expiry_date
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Cập nhật Voucher thành công!']);
    } else {
        error_log("Update voucher DB Error: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống, vui lòng thử lại.']);
    }
    exit;
}

// -----------------------------------------------
// 2. GÁN VOUCHER CHO USER
// -----------------------------------------------
if ($action === 'assign_voucher') {
    $voucher_id   = (int)($_POST['voucher_id'] ?? 0);
    $usage_limit  = max(1, (int)($_POST['usage_limit'] ?? 1));
    $assign_all   = (int)($_POST['assign_all'] ?? 0);

    if ($voucher_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Voucher không hợp lệ']);
        exit;
    }

    $user_ids = [];
    if ($assign_all === 1) {
        $res = $conn->query("SELECT id FROM users WHERE role != 'admin'");
        while ($r = $res->fetch_assoc()) $user_ids[] = (int)$r['id'];
    } else {
        // SECURITY: ép kiểu int, loại bỏ giá trị không hợp lệ
        $raw_ids  = $_POST['user_ids'] ?? [];
        $user_ids = array_filter(array_map('intval', $raw_ids), fn($v) => $v > 0);
    }

    if (empty($user_ids)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng chọn ít nhất 1 người dùng!']);
        exit;
    }

    $success_count = 0;
    $stmt = $conn->prepare("INSERT INTO user_vouchers (user_id, voucher_id, usage_limit, is_new) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE usage_limit = usage_limit + ?");

    foreach ($user_ids as $uid) {
        $stmt->bind_param("iiii", $uid, $voucher_id, $usage_limit, $usage_limit);
        if ($stmt->execute()) $success_count++;
    }
    $stmt->close();

    if ($success_count > 0) {
        include_once '../includes/admin_logger.php';
        logAdminAction($conn, 'Gán Voucher', 'api/voucher_api.php', "Phát Voucher ID #$voucher_id cho $success_count người dùng", null, [
            'voucher_id' => $voucher_id, 'users_count' => $success_count, 'usage_limit' => $usage_limit
        ]);
    }

    echo json_encode(['status' => 'success', 'message' => "Đã gán thành công cho $success_count người dùng."]);
    exit;
}

// -----------------------------------------------
// 3. LẤY VOUCHER CỦA USER (DÀNH CHO CLIENT)
// -----------------------------------------------
if ($action === 'get_my_vouchers') {
    $sql = "SELECT v.id, v.code, v.type, v.discount_amount, v.max_discount, v.min_order_value
            FROM user_vouchers uv
            JOIN vouchers v ON uv.voucher_id = v.id
            WHERE uv.user_id = ?
              AND (v.expiry_date >= CURDATE() OR v.expiry_date = '0000-00-00')
              AND uv.usage_limit > uv.used_count
            ORDER BY v.discount_amount DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("get_my_vouchers prepare error: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống']);
        exit;
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $vouchers = [];
    while ($row = $result->fetch_assoc()) {
        $vouchers[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $vouchers]);
    exit;
}
?>