<?php session_start(); ?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Đăng nhập - MobileStore</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="auth-wrapper">
        <div class="auth-box">
            <h2>Đăng Nhập</h2>
            <form class="auth-form" onsubmit="event.preventDefault(); login();">
                <input type="text" id="username" placeholder="Tên đăng nhập" required>
                <input type="password" id="password" placeholder="Mật khẩu" required>
                <button type="submit" class="auth-btn">Đăng nhập</button>
            </form>
            <div class="auth-link">
                Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
                <br>
                <a href="index.php" style="color:#666; font-weight:normal; margin-top:10px; display:inline-block;">← Về
                    trang chủ</a>
            </div>
            <p id="error-msg" style="color:red; margin-top:10px;"></p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function login() {
        let u = $('#username').val();
        let p = $('#password').val();
        $.post('api/auth_api.php', {
            action: 'login',
            username: u,
            password: p
        }, function(data) {
            let res = JSON.parse(data);
            if (res.status == 'success') {
                window.location.href = res.redirect;
            } else {
                $('#error-msg').text(res.message);
            }
        });
    }
    </script>
</body>

</html>