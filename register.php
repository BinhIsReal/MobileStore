<?php 
session_start();
include_once __DIR__ . '/includes/security.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Đăng ký tài khoản</title>
    <!-- CSRF Meta Tag -->
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/auth.js"></script>
</head>

<body>
    <div class="login-box">
        <h2 style="text-align:center;font-size:24px; color: var(--primary)">Đăng Ký</h2>
        
        <div id="reg-msg" style="display:none; padding:10px; margin-bottom:15px; border-radius:5px; text-align:center;"></div>

        <input type="text" id="reg-user" placeholder="Tên đăng nhập">

        <div class="form-group">
            <input type="email" id="reg-email" class="form-control" required placeholder="Email: example@gmail.com">
        </div>
        <div class="form-group">
            <input type="text" id="reg-phone" class="form-control" required placeholder="Số điện thoại">
        </div>

        <input type="password" id="reg-pass" placeholder="Mật khẩu">

        <button type="button" id="btn-register">Tạo tài khoản</button>
        <p style="margin-top:15px; text-align:center"><a href="login.php">Đã có tài khoản? <b>Đăng nhập</b></a></p>
    </div>


</body>

</html>