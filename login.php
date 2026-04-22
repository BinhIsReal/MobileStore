<?php 
session_start();
include_once __DIR__ . '/includes/security.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - TechMate</title>
    <!-- CSRF Meta Tag -->
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/mobile.css?v=<?php echo filemtime('assets/css/mobile.css'); ?>">
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
            
            <div style="text-align: center; margin: 20px 0; position: relative;">
                <hr style="position: absolute; top: 50%; left: 0; right: 0; margin: 0; border: none; border-top: 1px solid #ddd; z-index: 0;">
                <span style="background: white; padding: 0 10px; color: #999; font-size: 13px; position: relative; z-index: 1;">Hoặc</span>
            </div>
            
            <div id="g_id_onload"
                 data-client_id="302593077362-5g9tdnjsh85r3bmqgptef626gpq2ku8t.apps.googleusercontent.com"
                 data-context="signin"
                 data-ux_mode="popup"
                 data-callback="handleGoogleLogin"
                 data-auto_prompt="false">
            </div>

            <div class="g_id_signin"
                 data-type="standard"
                 data-shape="rectangular"
                 data-theme="outline"
                 data-text="signin_with"
                 data-size="large"
                 data-logo_alignment="left"
                 style="display: flex; justify-content: center; margin-bottom: 20px;">
            </div>

            <div class="auth-link">
                Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
                <br>
                <a href="index.php" style="color:#666; font-weight:bold; margin-top:10px; display:inline-block;">← Về
                    trang chủ</a>
            </div>
            <p id="error-msg" style="color:red; margin-top:10px;"></p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

    function login() {
        let u = $('#username').val();
        let p = $('#password').val();
        $.post('api/auth_api.php', {
            action: 'login',
            username: u,
            password: p
        }, function(data) {
            let res = typeof data === 'string' ? JSON.parse(data) : data;
            if (res.status == 'success') {
                window.location.href = res.redirect;
            } else {
                $('#error-msg').text(res.message);
            }
        });
    }

    function handleGoogleLogin(response) {
        if (response.credential) {
            let btn = $('.auth-btn');
            let originalText = btn.text();
            btn.prop('disabled', true).text('Đang xác thực Google...');
            
            $.post('api/auth_api.php', {
                action: 'google_login',
                token: response.credential
            }, function(res) {
                btn.prop('disabled', false).text(originalText);
                try {
                    let obj = typeof res === 'object' ? res : JSON.parse(res);
                    if (obj.status === 'success') {
                        window.location.href = obj.redirect;
                    } else {
                        $('#error-msg').css({'display':'block', 'color':'red'}).html(obj.message);
                    }
                } catch(e) {
                     $('#error-msg').css({'display':'block', 'color':'red'}).html('Lỗi server xử lý kết quả!');
                }
            }).fail(function() {
                btn.prop('disabled', false).text(originalText);
                $('#error-msg').css({'display':'block', 'color':'red'}).html('Lỗi kết nối máy chủ!');
            });
        }
    }
    </script>
</body>

</html>