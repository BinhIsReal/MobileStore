<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Đổi mật khẩu</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="password-container">
        <h2 style="text-align: center; margin-bottom: 20px;">
            <i class="fa fa-lock"></i> Đổi Mật Khẩu
        </h2>

        <div class="form-group">
            <label>Mật khẩu cũ <span style="color:red">*</span></label>
            <input type="password" id="old-pass" placeholder="Nhập mật khẩu hiện tại...">
        </div>
        <div class="form-group">
            <label>Mật khẩu mới <span style="color:red">*</span></label>
            <input type="password" id="new-pass" placeholder="Nhập mật khẩu mới...">
        </div>

        <button class="btn-save" onclick="changePassword()">
            <i class="fa fa-check-circle"></i> Xác Nhận Đổi
        </button>
        <a href="profile.php" class="back-link" style="display: block; text-align: center; margin-top: 15px;">
            <i class="fa fa-arrow-left"></i> Quay lại Thông tin cá nhân
        </a>
    </div>

    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
</body>

</html>