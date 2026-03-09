<?php session_start();
include '../config/db.php';
 ?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Giới thiệu - MobileStore</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container">
        <div class="page-wrapper">
            <h2 class="page-title">Về MobileStore</h2>
            <div class="page-content">
                <p><strong>MobileStore</strong> được thành lập từ năm 2015, là một trong những nhà bán lẻ điện thoại di
                    động, máy tính bảng và phụ kiện công nghệ uy tín hàng đầu tại Việt Nam.</p>
                <p>Với triết lý kinh doanh "Khách hàng là trọng tâm", chúng tôi cam kết mang đến những sản phẩm chính
                    hãng với mức giá tốt nhất thị trường.</p>

                <h3>Tầm nhìn & Sứ mệnh</h3>
                <ul>
                    <li><strong>Tầm nhìn:</strong> Trở thành chuỗi bán lẻ công nghệ Top 3 tại Việt Nam vào năm 2030.
                    </li>
                    <li><strong>Sứ mệnh:</strong> Phổ cập công nghệ đến mọi người dân Việt Nam với chi phí hợp lý nhất.
                    </li>
                </ul>

                <h3>Giá trị cốt lõi</h3>
                <p>Trung thực - Tận tâm - Chuyên nghiệp.</p>

                <h3>Chứng nhận</h3>
                <p>MobileStore tự hào là Đại lý ủy quyền chính thức (Authorized Reseller) của Apple, Samsung, Xiaomi,
                    OPPO tại Việt Nam.</p>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>