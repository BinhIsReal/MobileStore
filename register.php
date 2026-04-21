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
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="assets/js/auth.js"></script>
</head>

<body>
    <div class="login-box">
        <h2 style="text-align:center;font-size:24px; color: var(--primary)">Đăng Ký</h2>
        
        <div id="reg-msg" style="display:none; padding:10px; margin-bottom:15px; border-radius:5px; text-align:center;"></div>

        <input type="text" id="reg-user" placeholder="Tên đăng nhập">

        <div class="form-group">
            <input type="email" id="reg-email" class="form-control" required placeholder="Email">
        </div>
        <div class="form-group">
            <input type="text" id="reg-phone" class="form-control" required placeholder="Số điện thoại">
        </div>

        <input type="password" id="reg-pass" placeholder="Mật khẩu">
        <input type="password" id="reg-pass-confirm" placeholder="Xác nhận mật khẩu">

        <button type="button" id="btn-register">Tạo tài khoản</button>
        
        <div style="text-align: center; margin: 20px 0; position: relative;">
            <hr style="position: absolute; top: 50%; left: 0; right: 0; margin: 0; border: none; border-top: 1px solid #ddd; z-index: 0;">
            <span style="background: white; padding: 0 10px; color: #999; font-size: 13px; position: relative; z-index: 1;">Hoặc</span>
        </div>
        
        <div id="g_id_onload"
             data-client_id="302593077362-5g9tdnjsh85r3bmqgptef626gpq2ku8t.apps.googleusercontent.com"
             data-context="signup"
             data-ux_mode="popup"
             data-callback="handleGoogleLogin"
             data-auto_prompt="false">
        </div>

        <div class="g_id_signin"
             data-type="standard"
             data-shape="rectangular"
             data-theme="outline"
             data-text="signup_with"
             data-size="large"
             data-logo_alignment="left"
             style="display: flex; justify-content: center;">
        </div>

        <script>
            function handleGoogleLogin(response) {
                if (response.credential) {
                    let btn = $('#btn-register');
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
                                $('#reg-msg').css({'display':'block', 'background':'#f8d7da', 'color':'#721c24'}).html(obj.message);
                            }
                        } catch(e) {
                             $('#reg-msg').css({'display':'block', 'background':'#f8d7da', 'color':'#721c24'}).html('Lỗi server xử lý kết quả!');
                        }
                    }).fail(function() {
                        btn.prop('disabled', false).text(originalText);
                        $('#reg-msg').css({'display':'block', 'background':'#f8d7da', 'color':'#721c24'}).html('Lỗi kết nối máy chủ!');
                    });
                }
            }
        </script>

        <p style="margin-top:20px; text-align:center"><a href="login.php">Đã có tài khoản? <b>Đăng nhập</b></a></p>
    </div>


</body>

</html>