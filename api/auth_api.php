<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

$action = $_POST['action'] ?? '';

// -----------------------------------------------
// 1. LOGIN
// -----------------------------------------------
if ($action === 'login') {
    // SECURITY: Xác thực CSRF Token chống Login CSRF
    csrf_verify_or_die();

    // SECURITY: Rate limit — tối đa 5 lần thử trong 5 phút
    $rate_key = 'login_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!rate_limit_check($rate_key, 5, 300)) {
        $wait = rate_limit_wait($rate_key, 300);
        echo json_encode([
            'status'  => 'error',
            'message' => "Quá nhiều lần đăng nhập thất bại. Vui lòng đợi {$wait} giây."
        ]);
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // SECURITY: Validate input rỗng
    if (empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập đầy đủ thông tin!']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // SECURITY: Hỗ trợ migration từ MD5 sang password_hash
        // MD5 là thuật toán không an toàn, cần tự động upgrade khi user đăng nhập
        $is_password_correct = false;

        if (strlen($user['password']) === 32) {
            // Tài khoản cũ dùng MD5 - verify và upgrade ngay
            if (hash_equals(md5($password), $user['password'])) {
                $is_password_correct = true;
                // TỰ ĐỘNG NÂNG CẤP: Hash lại bằng password_hash an toàn
                $new_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt_upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt_upd->bind_param("si", $new_hash, $user['id']);
                $stmt_upd->execute();
                $stmt_upd->close();
            }
        } else {
            if (password_verify($password, $user['password'])) {
                $is_password_correct = true;
                // SECURITY: Tự động re-hash nếu cost factor đã lỗi thời
                if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
                    $new_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmt_upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                    $stmt_upd->bind_param("si", $new_hash, $user['id']);
                    $stmt_upd->execute();
                    $stmt_upd->close();
                }
            }
        }

        if ($is_password_correct) {
            // SECURITY: Regenerate session ID để chống Session Fixation Attack
            session_regenerate_id(true);
            // SECURITY: Reset rate limit sau khi login thành công
            rate_limit_reset($rate_key);

            $_SESSION['user_id']  = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            if ($user['role'] !== 'admin') {
                // SECURITY: Escape tên user trước khi lưu vào session message
                $_SESSION['login_success_msg'] = 'Chào mừng <b>' . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . '</b> đến với MobileStore!';
            }

            // LOGIC GỘP GIỎ HÀNG (Auto Merge) - dùng Prepared Statement
            if (!empty($_SESSION['cart'])) {
                $stmt_check = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt_upd   = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                $stmt_ins   = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");

                foreach ($_SESSION['cart'] as $p_id => $session_qty) {
                    $p_id       = (int)$p_id;
                    $session_qty = (int)$session_qty;
                    $uid        = (int)$user['id'];

                    $stmt_check->bind_param("ii", $uid, $p_id);
                    $stmt_check->execute();
                    $check_res = $stmt_check->get_result();

                    if ($check_res->num_rows > 0) {
                        $row     = $check_res->fetch_assoc();
                        $new_qty = (int)$row['quantity'] + $session_qty;
                        $row_id  = (int)$row['id'];
                        $stmt_upd->bind_param("ii", $new_qty, $row_id);
                        $stmt_upd->execute();
                    } else {
                        $stmt_ins->bind_param("iii", $uid, $p_id, $session_qty);
                        $stmt_ins->execute();
                    }
                }

                $stmt_check->close();
                $stmt_upd->close();
                $stmt_ins->close();
                unset($_SESSION['cart']);
            }

            $redirect = ($user['role'] === 'admin') ? 'admin/products.php' : 'index.php';
            echo json_encode(['status' => 'success', 'redirect' => $redirect]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Sai tài khoản hoặc mật khẩu!']);
        }
    } else {
        // SECURITY: Thông báo lỗi mơ hồ, không tiết lộ username có tồn tại hay không
        echo json_encode(['status' => 'error', 'message' => 'Sai tài khoản hoặc mật khẩu!']);
    }
    exit;
}

// -----------------------------------------------
// 2. LOGOUT
// -----------------------------------------------
if (isset($_GET['logout'])) {
    // SECURITY: Xóa sạch session và destroy hoàn toàn
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    // Tạo session mới để lưu thông báo
    session_start();
    session_regenerate_id(true);
    $_SESSION['logout_success_msg'] = "Bạn đã đăng xuất tài khoản thành công!";

    // SECURITY: relative redirect an toàn (không dùng header từ input user)
    header("Location: ../index.php");
    exit;
}

// -----------------------------------------------
// 3. REGISTER
// -----------------------------------------------
if ($action === 'register') {
    // SECURITY: Xác thực CSRF Token chống CSRF giả mạo
    csrf_verify_or_die();

    // Lấy & Trim dữ liệu
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');

    // SECURITY: Validate format input
    if (strlen($username) < 3 || strlen($username) > 50) {
        echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập phải từ 3-50 ký tự!']);
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập chỉ được chứa chữ cái, số và gạch dưới!']);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu phải có ít nhất 6 ký tự!']);
        exit;
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Email không hợp lệ!']);
        exit;
    }

    // FIXED: Kiểm tra trùng username bằng Prepared Statement
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $check = $stmt->get_result();
    $stmt->close();

    if ($check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập đã tồn tại!']);
        exit;
    }

    // SECURITY: Dùng BCRYPT cost=12 (an toàn hơn PASSWORD_DEFAULT)
    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $sql  = "INSERT INTO users (username, password, email, phone, role) VALUES (?, ?, ?, ?, 'user')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $username, $hashed_password, $email, $phone);

    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['status' => 'success']);
    } else {
        error_log("Register DB Error: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống, vui lòng thử lại!']);
    }
    exit;
}
?>