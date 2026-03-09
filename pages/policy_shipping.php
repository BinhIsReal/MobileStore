<?php session_start();
include '../config/db.php'; ?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Chính sách vận chuyển - MobileStore</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container">
        <div class="page-wrapper">
            <h2 class="page-title">Chính sách vận chuyển</h2>
            <div class="page-content">
                <h3>1. Phí vận chuyển</h3>
                <ul>
                    <li><strong>Miễn phí:</strong> Cho đơn hàng > 300.000đ trên toàn quốc.</li>
                    <li><strong>Đồng giá 20.000đ:</strong> Cho đơn hàng dưới 300.000đ.</li>
                    <li><strong>Hỏa tốc (2H):</strong> Chỉ áp dụng tại Nội thành Hà Nội & TP.HCM (Phí tùy app giao
                        hàng).</li>
                </ul>

                <h3>2. Thời gian giao hàng</h3>
                <ul>
                    <li>Nội thành (Hà Nội, TP.HCM): 1 - 2 ngày.</li>
                    <li>Các tỉnh thành khác: 2 - 4 ngày làm việc.</li>
                    <li>Lưu ý: Thời gian có thể chậm hơn vào dịp Lễ, Tết hoặc thiên tai.</li>
                </ul>

                <h3>3. Kiểm tra hàng (Đồng kiểm)</h3>
                <p>MobileStore khuyến khích quý khách <strong>MỞ HỘP KIỂM TRA</strong> ngoại quan (không bật nguồn)
                    trước mặt nhân viên giao hàng. Nếu sản phẩm bị móp méo, vỡ, sai mẫu mã, vui lòng từ chối nhận hàng
                    và gọi ngay hotline 1900.2091.</p>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>