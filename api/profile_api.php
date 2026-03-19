<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập!']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// 1. CẬP NHẬT THÔNG TIN VÀ ĐỊA CHỈ
if ($action == 'update_info') {
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';

    $sql = "UPDATE users SET email = ?, phone = ?, address = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $email, $phone, $address, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Lưu thông tin thành công!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống!']);
    }
    exit;
}

// 2. ĐỔI MẬT KHẨU
if ($action == 'change_password') {
    $old_pass = $_POST['old_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';

    // Kiểm tra không được để trống
    if (empty($old_pass) || empty($new_pass)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập đầy đủ mật khẩu cũ và mới!']);
        exit;
    }

    // Lấy mật khẩu cũ từ DB để so sánh
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Logic kiểm tra mật khẩu (hỗ trợ cả MD5 cũ và Bcrypt mới)
    $is_old_pass_correct = false;
    if (strlen($user['password']) == 32) {
        if (md5($old_pass) === $user['password']) $is_old_pass_correct = true;
    } else {
        if (password_verify($old_pass, $user['password'])) $is_old_pass_correct = true;
    }

    // Nếu mật khẩu cũ nhập vào bị sai
    if (!$is_old_pass_correct) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu cũ không chính xác!']);
        exit;
    }

    // Nếu đúng, tiến hành lưu mật khẩu mới
    $hashed_new_pass = password_hash($new_pass, PASSWORD_DEFAULT);
    $update_sql = "UPDATE users SET password = ? WHERE id = ?";
    $stmt_update = $conn->prepare($update_sql);
    $stmt_update->bind_param("si", $hashed_new_pass, $user_id);

    if ($stmt_update->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Đổi mật khẩu thành công!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống khi đổi mật khẩu!']);
    }
    exit;
}
?>