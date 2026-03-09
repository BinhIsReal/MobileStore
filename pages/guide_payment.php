<?php session_start(); 
include '../config/db.php';?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Hướng dẫn thanh toán - MobileStore</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container">
        <div class="page-wrapper">
            <h2 class="page-title">Hướng dẫn thanh toán</h2>
            <div class="page-content">
                <h3>1. Thanh toán tiền mặt (COD)</h3>
                <p>Áp dụng cho đơn hàng dưới 5.000.000đ. Quý khách thanh toán trực tiếp cho nhân viên giao hàng khi nhận
                    sản phẩm.</p>

                <h3>2. Chuyển khoản ngân hàng (Khuyên dùng)</h3>
                <p>Hỗ trợ quét mã VietQR tự động. Hệ thống xác nhận đơn hàng ngay lập tức sau khi chuyển khoản thành
                    công.</p>
                <ul>
                    <li>Ngân hàng: BIDV (Đầu tư và Phát triển)</li>
                    <li>STK: 0334960320</li>
                    <li>Chủ TK: DAM NGOC BINH</li>
                </ul>

                <h3>3. Trả góp Home PayLater</h3>
                <p>Mua trước trả sau, lãi suất 0% qua ứng dụng Home Credit. Chọn phương thức này ở bước thanh toán cuối
                    cùng.</p>

                <h3>4. Cà thẻ (POS)</h3>
                <p>Hỗ trợ cà thẻ Visa/Master/JCB/Amex tại tất cả các cửa hàng (Phí 0%).</p>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>