<?php
session_start();
include '../config/db.php';

$action = $_POST['action'] ?? '';

// 1. LOGIN VÀ GỘP GIỎ HÀNG
if ($action == 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // 1. Kiểm tra tài khoản (Chỉ lấy username ra trước, lấy cả password để verify sau)
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // KIỂM TRA MẬT KHẨU (Hỗ trợ cả MD5 cũ và Password Hash mới)
        $is_password_correct = false;
        
        // Kiểm tra xem mật khẩu trong DB đang dùng chuẩn nào
        if (strlen($user['password']) == 32) {
            // Nếu là 32 ký tự -> Chắc chắn là MD5 (Tài khoản cũ)
            if (md5($password) === $user['password']) {
                $is_password_correct = true;
            }
        } else {
            // Nếu không phải 32 ký tự -> Là mã hóa an toàn password_hash (Tài khoản mới đăng ký)
            if (password_verify($password, $user['password'])) {
                $is_password_correct = true;
            }
        }

        if ($is_password_correct) {
            // Đăng nhập đúng mật khẩu
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

           if ($user['role'] !== 'admin') {
                $_SESSION['login_success_msg'] = 'Chào mừng <b>' . $user['username'] . '</b> đến với MobileStore!';
            }

            // 2. LOGIC GỘP GIỎ HÀNG (Auto Merge) - Giữ nguyên của bạn
            if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
                foreach ($_SESSION['cart'] as $p_id => $session_qty) {
                    $check_sql = "SELECT id, quantity FROM cart WHERE user_id = {$user['id']} AND product_id = $p_id";
                    $check_res = $conn->query($check_sql);

                    if ($check_res->num_rows > 0) {
                        $row = $check_res->fetch_assoc();
                        $new_qty = $row['quantity'] + $session_qty;
                        $conn->query("UPDATE cart SET quantity = $new_qty WHERE id = {$row['id']}");
                    } else {
                        $conn->query("INSERT INTO cart (user_id, product_id, quantity) VALUES ({$user['id']}, $p_id, $session_qty)");
                    }
                }
                unset($_SESSION['cart']);
            }

            // Redirect theo Role
            $redirect = ($user['role'] == 'admin') ? 'admin/products.php' : 'index.php';
            echo json_encode(['status' => 'success', 'redirect' => $redirect]);
        } else {
            // Sai mật khẩu
            echo json_encode(['status' => 'error', 'message' => 'Sai tài khoản hoặc mật khẩu!']);
        }
    } else {
        // Không tìm thấy username
        echo json_encode(['status' => 'error', 'message' => 'Sai tài khoản hoặc mật khẩu!']);
    }
    exit;
}

// 2. LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    session_start();
    $_SESSION['logout_success_msg'] = "Bạn đã đăng xuất tài khoản thành công!";
    header("Location: ../index.php"); 
    exit();
}

// 3. REGISTER
if ($action == 'register') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';

    // 1. MÃ HÓA MẬT KHẨU (Bắt buộc để bảo mật)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if ($check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập đã tồn tại!']);
        exit;
    }

    // 2. LƯU MẬT KHẨU ĐÃ MÃ HÓA
    $sql = "INSERT INTO users (username, password, email, phone, role) VALUES (?, ?, ?, ?, 'user')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $username, $hashed_password, $email, $phone);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống!']);
    }
    exit;
}
?>