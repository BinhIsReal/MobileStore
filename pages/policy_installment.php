<?php session_start();
include '../config/db.php';?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chính sách trả góp - TechMate</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link rel="stylesheet" href="../assets/css/mobile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-wrapper">
            <h2 class="page-title">Chính sách mua hàng trả góp</h2>

            <div class="page-content">
                <h3>1. Điều kiện trả góp</h3>
                <ul>
                    <li>Công dân Việt Nam từ 20 - 60 tuổi.</li>
                    <li>Có CMND/CCCD còn hạn sử dụng.</li>
                    <li>Có bằng lái xe hoặc hộ khẩu (nếu khoản vay > 10 triệu).</li>
                </ul>

                <h3>2. Các đơn vị tài chính hỗ trợ</h3>
                <p>Chúng tôi liên kết với: Home Credit, FE Credit, HD Saison để mang lại lãi suất tốt nhất cho bạn.</p>

                <h3>3. Quy trình mua hàng</h3>
                <ul>
                    <li>Bước 1: Chọn sản phẩm muốn mua.</li>
                    <li>Bước 2: Chọn hình thức trả góp (Qua thẻ tín dụng hoặc Công ty tài chính).</li>
                    <li>Bước 3: Chờ duyệt hồ sơ (Khoảng 15 phút).</li>
                    <li>Bước 4: Nhận máy ngay tại cửa hàng.</li>
                </ul>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>

</html>