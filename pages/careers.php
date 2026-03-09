<?php session_start();
include '../config/db.php'; ?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Tuyển dụng - MobileStore</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container">
        <div class="page-wrapper">
            <h2 class="page-title">Cơ hội nghề nghiệp</h2>
            <p style="margin-bottom:20px;">MobileStore đang tìm kiếm những đồng đội nhiệt huyết để cùng phát triển. Chào
                mừng bạn gia nhập đội ngũ của chúng tôi!</p>

            <div class="job-item">
                <div>
                    <div class="job-title">Nhân viên Bán hàng (Sales)</div>
                    <div class="job-meta">
                        <i class="fa fa-map-marker-alt"></i> Hà Nội & TP.HCM |
                        <i class="fa fa-dollar-sign"></i> 8 - 15 Triệu |
                        <i class="fa fa-clock"></i> Full-time
                    </div>
                </div>
                <button class="btn-apply">Ứng tuyển ngay</button>
            </div>

            <div class="job-item">
                <div>
                    <div class="job-title">Kỹ thuật viên Sửa chữa phần cứng</div>
                    <div class="job-meta">
                        <i class="fa fa-map-marker-alt"></i> Toàn quốc |
                        <i class="fa fa-dollar-sign"></i> 10 - 20 Triệu |
                        <i class="fa fa-clock"></i> Full-time
                    </div>
                </div>
                <button class="btn-apply">Ứng tuyển ngay</button>
            </div>

            <div class="job-item">
                <div>
                    <div class="job-title">Content Writer / Reviewer Công nghệ</div>
                    <div class="job-meta">
                        <i class="fa fa-map-marker-alt"></i> Hà Nội |
                        <i class="fa fa-dollar-sign"></i> 9 - 12 Triệu |
                        <i class="fa fa-clock"></i> Office hour
                    </div>
                </div>
                <button class="btn-apply">Ứng tuyển ngay</button>
            </div>

            <div class="page-content" style="margin-top:20px; font-size:13px; color:#666;">
                <p>Gửi CV về email: <strong>tuyendung@mobilestore.com.vn</strong> với tiêu đề [Vị trí] - [Họ tên].</p>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>