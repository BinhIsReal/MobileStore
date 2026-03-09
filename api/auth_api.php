<?php
session_start();
include '../config/db.php';

$action = $_POST['action'] ?? '';

// 1. LOGIN VÀ GỘP GIỎ HÀNG
if ($action == 'login') {
    $username = $_POST['username'];
    $password = md5($_POST['password']);

    // 1. Kiểm tra tài khoản
    $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE username=? AND password=?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // 2. LOGIC GỘP GIỎ HÀNG (Auto Merge)
        // Kiểm tra xem lúc làm Guest (Session) có mua gì không
        if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
            foreach ($_SESSION['cart'] as $p_id => $session_qty) {
                // Kiểm tra sản phẩm này đã có trong DB của User chưa
                $check_sql = "SELECT id, quantity FROM cart WHERE user_id = {$user['id']} AND product_id = $p_id";
                $check_res = $conn->query($check_sql);

                if ($check_res->num_rows > 0) {
                    // TRƯỜNG HỢP A: Đã có trong DB -> Cộng dồn số lượng
                    // (Ví dụ: DB có 1 cái ốp, Session có 1 cái ốp -> Tổng 2)
                    $row = $check_res->fetch_assoc();
                    $new_qty = $row['quantity'] + $session_qty;
                    $conn->query("UPDATE cart SET quantity = $new_qty WHERE id = {$row['id']}");
                } else {
                    // TRƯỜNG HỢP B: Chưa có trong DB -> Thêm mới
                    // (Ví dụ: Session có iPhone, DB chưa có -> Thêm iPhone vào DB)
                    $conn->query("INSERT INTO cart (user_id, product_id, quantity) VALUES ({$user['id']}, $p_id, $session_qty)");
                }
            }
            // 3. Xóa giỏ hàng Session sau khi đã chuyển hết vào DB
            unset($_SESSION['cart']);
        }

        // Redirect theo Role
        $redirect = ($user['role'] == 'admin') ? 'admin/products.php' : 'index.php';
        echo json_encode(['status' => 'success', 'redirect' => $redirect]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Sai tài khoản hoặc mật khẩu!']);
    }
}
// 2. LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../index.php"); 
    exit();
}

// 3. REGISTER
if ($action == 'register') {
    $username = $_POST['username'];
    $password = md5($_POST['password']);
    
    // Check tồn tại
    $check = $conn->query("SELECT id FROM users WHERE username='$username'");
    if ($check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Tài khoản đã tồn tại!']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");
    $stmt->bind_param("ss", $username, $password);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'redirect' => 'login.php']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống!']);
    }
}
?>