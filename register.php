<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Đăng ký tài khoản</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
    .login-box {
        width: 350px;
        margin: 100px auto;
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    input {
        width: 100%;
        margin: 10px 0;
        padding: 10px;
        border: 1px solid #ccc;
    }

    button {
        width: 100%;
        padding: 10px;
        background: var(--primary);
        color: white;
        border: none;
        cursor: pointer;
    }
    </style>
</head>

<body>
    <div class="login-box">
        <h2 style="text-align:center; color: var(--primary)">Đăng Ký</h2>
        <input type="text" id="reg-user" placeholder="Tên đăng nhập">
        <input type="password" id="reg-pass" placeholder="Mật khẩu">
        <button onclick="register()">Tạo tài khoản</button>
        <p style="margin-top:15px; text-align:center"><a href="login.php">Đã có tài khoản? <b>Đăng nhập</b></a></p>
    </div>

    <script>
    function register() {
        let u = $('#reg-user').val();
        let p = $('#reg-pass').val();
        $.post('api/auth_api.php', {
            action: 'register',
            username: u,
            password: p
        }, function(data) {
            let res = JSON.parse(data);
            if (res.status == 'success') {
                alert('Đăng ký thành công! Vui lòng đăng nhập.');
                window.location.href = 'login.php';
            } else {
                alert(res.message);
            }
        });
    }
    </script>
</body>

</html>