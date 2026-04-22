<?php session_start();
include '../config/db.php'; ?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giải quyết khiếu nại - TechMate</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link rel="stylesheet" href="../assets/css/mobile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container">
        <div class="page-wrapper">
            <h2 class="page-title">Chính sách giải quyết khiếu nại</h2>
            <div class="page-content">
                <p>TechMate luôn coi trọng ý kiến của khách hàng. Quy trình giải quyết khiếu nại được thực hiện qua 4
                    bước:</p>

                <h3>Bước 1: Tiếp nhận phản ánh</h3>
                <p>Khách hàng có thể gửi khiếu nại qua:</p>
                <ul>
                    <li>Hotline: 1900.2091 (Nhánh 2).</li>
                    <li>Email: cskh@TechMate.com.vn.</li>
                    <li>Fanpage Facebook / Zalo OA chính thức.</li>
                </ul>

                <h3>Bước 2: Xác minh thông tin</h3>
                <p>Bộ phận CSKH sẽ liên hệ lại quý khách trong vòng 24h để xác minh sự việc và kiểm tra lại camera/ghi
                    âm (nếu có sự cố tại cửa hàng).</p>

                <h3>Bước 3: Đề xuất giải pháp</h3>
                <p>Chúng tôi sẽ đưa ra phương án xử lý (đổi hàng, hoàn tiền, hoặc đền bù voucher) dựa trên quy định và
                    quyền lợi của khách hàng.</p>

                <h3>Bước 4: Hoàn tất</h3>
                <p>Thực hiện giải pháp đã thống nhất và gọi điện xác nhận sự hài lòng của quý khách.</p>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>