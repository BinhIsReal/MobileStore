<?php session_start(); 
include '../config/db.php';?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Chính sách bảo mật - MobileStore</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container">
        <div class="page-wrapper">
            <h2 class="page-title">Chính sách bảo mật thông tin</h2>
            <div class="page-content">
                <h3>1. Mục đích thu thập thông tin</h3>
                <p>MobileStore chỉ thu thập các thông tin cơ bản: Tên, Số điện thoại, Địa chỉ giao hàng để phục vụ việc
                    xử lý đơn hàng và bảo hành sản phẩm.</p>

                <h3>2. Phạm vi sử dụng</h3>
                <p>Thông tin chỉ được sử dụng nội bộ và gửi cho đơn vị vận chuyển (GHTK, Viettel Post) để giao hàng.
                    Chúng tôi cam kết KHÔNG bán dữ liệu cho bên thứ ba.</p>

                <h3>3. Thời gian lưu trữ</h3>
                <p>Dữ liệu đơn hàng được lưu trữ trong 05 năm theo quy định của Luật Kế toán để phục vụ việc tra soát
                    thuế và bảo hành.</p>

                <h3>4. Quyền lợi khách hàng</h3>
                <p>Quý khách có quyền yêu cầu chỉnh sửa hoặc xóa thông tin cá nhân của mình khỏi hệ thống bằng cách liên
                    hệ tổng đài 1900.2091.</p>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>