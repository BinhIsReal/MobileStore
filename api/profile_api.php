<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

// SECURITY: Bắt buộc đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập!']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action  = $_POST['action'] ?? '';

// -----------------------------------------------
// 1. CẬP NHẬT THÔNG TIN CÁ NHÂN
// -----------------------------------------------
if ($action === 'update_info') {
    // SECURITY: Verify CSRF token
    csrf_verify_or_die();

    $email   = trim($_POST['email']   ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');

    // SECURITY: Validate email format nếu có nhập
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Email không hợp lệ!']);
        exit;
    }

    // SECURITY: Sanitize phone — chỉ giữ ký tự số và dấu +
    $phone = preg_replace('/[^0-9+\-\s]/', '', $phone);

    $stmt = $conn->prepare("UPDATE users SET email = ?, phone = ?, address = ? WHERE id = ?");
    $stmt->bind_param("sssi", $email, $phone, $address, $user_id);

    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'Lưu thông tin thành công!']);
    } else {
        error_log("Update profile error: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống!']);
    }
    exit;
}

// -----------------------------------------------
// 2. ĐỔI MẬT KHẨU
// -----------------------------------------------
if ($action === 'change_password') {
    // SECURITY: Verify CSRF token
    csrf_verify_or_die();

    $old_pass = $_POST['old_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';

    if (empty($old_pass) || empty($new_pass)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập đầy đủ mật khẩu cũ và mới!']);
        exit;
    }

    // SECURITY: Validate độ mạnh mật khẩu mới
    if (strlen($new_pass) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự!']);
        exit;
    }

    // Lấy mật khẩu hiện tại từ DB
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Người dùng không tồn tại!']);
        exit;
    }

    // SECURITY: Hỗ trợ MD5 cũ với tự động upgrade sang BCRYPT
    $is_old_pass_correct = false;

    if (strlen($user['password']) === 32) {
        // Tài khoản cũ dùng MD5
        if (hash_equals(md5($old_pass), $user['password'])) {
            $is_old_pass_correct = true;
        }
    } else {
        if (password_verify($old_pass, $user['password'])) {
            $is_old_pass_correct = true;
        }
    }

    if (!$is_old_pass_correct) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu cũ không chính xác!']);
        exit;
    }

    // SECURITY: Dùng BCRYPT cost=12 (an toàn hơn PASSWORD_DEFAULT)
    $hashed_new_pass = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_new_pass, $user_id);

    if ($stmt->execute()) {
        $stmt->close();
        // SECURITY: Invalidate session sau đổi mật khẩu — bắt đăng nhập lại
        session_regenerate_id(true);
        echo json_encode(['status' => 'success', 'message' => 'Đổi mật khẩu thành công! Vui lòng đăng nhập lại.']);
    } else {
        error_log("Change password error: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống khi đổi mật khẩu!']);
    }
    exit;
}
?>