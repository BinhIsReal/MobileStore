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
</head>

<body>
    <div class="login-box">
        <h2 style="text-align:center; color: var(--primary)">Đăng Ký</h2>

        <input type="text" id="reg-user" placeholder="Tên đăng nhập">

        <div class="form-group">
            <input type="email" id="reg-email" class="form-control" required placeholder="Email: example@gmail.com">
        </div>
        <div class="form-group">
            <input type="text" id="reg-phone" class="form-control" required placeholder="Số điện thoại">
        </div>

        <input type="password" id="reg-pass" placeholder="Mật khẩu">

        <button onclick="register()">Tạo tài khoản</button>
        <p style="margin-top:15px; text-align:center"><a href="login.php">Đã có tài khoản? <b>Đăng nhập</b></a></p>
    </div>

    <script>
    $(document).ajaxSend(function(event, jqXHR, settings) {
        if (settings.type === "POST" || settings.type === "post") {
            const token = $('meta[name="csrf-token"]').attr("content");
            if (token) {
                jqXHR.setRequestHeader("X-CSRF-Token", token);
                if (typeof settings.data === "string") {
                    settings.data += "&csrf_token=" + encodeURIComponent(token);
                }
            }
        }
    });

    function register() {
        let u = $('#reg-user').val();
        let p = $('#reg-pass').val();
        let e = $('#reg-email').val(); // Lấy email
        let t = $('#reg-phone').val(); // Lấy phone

        if (!u || !p || !e || !t) {
            alert("Vui lòng nhập đầy đủ thông tin!");
            return;
        }

        $.post('api/auth_api.php', {
            action: 'register',
            username: u,
            password: p,
            email: e, // Gửi email sang API
            phone: t // Gửi phone sang API
        }, function(data) {
            try {
                let res = JSON.parse(data);
                if (res.status == 'success') {
                    alert('Đăng ký thành công! Vui lòng đăng nhập.');
                    window.location.href = 'login.php';
                } else {
                    alert(res.message);
                }
            } catch (e) {
                console.error("Lỗi phản hồi:", data);
            }
        });
    }
    </script>
</body>

</html>