<?php session_start(); 
include '../config/db.php';?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Trung tâm bảo hành - MobileStore</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container">
        <div class="page-wrapper">
            <h2 class="page-title">Hệ thống trung tâm bảo hành</h2>
            <div class="store-grid">
                <div class="store-card">
                    <span class="store-name">TTBH Số 1 - Hà Nội</span>
                    <div class="store-address"><i class="fa fa-map-marker-alt"></i> 194 Lê Duẩn, Q. Đống Đa</div>
                    <div class="store-address"><i class="fa fa-phone"></i> 1900.2091 (Nhánh 2)</div>
                </div>

                <div class="store-card">
                    <span class="store-name">TTBH Số 2 - Đà Nẵng</span>
                    <div class="store-address"><i class="fa fa-map-marker-alt"></i> 50 Nguyễn Văn Linh, Q. Hải Châu
                    </div>
                    <div class="store-address"><i class="fa fa-phone"></i> 0236.355.6789</div>
                </div>

                <div class="store-card">
                    <span class="store-name">TTBH Số 3 - TP.HCM</span>
                    <div class="store-address"><i class="fa fa-map-marker-alt"></i> 55B Trần Quang Khải, Q.1</div>
                    <div class="store-address"><i class="fa fa-phone"></i> 1900.2091 (Nhánh 2)</div>
                </div>

                <div class="store-card">
                    <span class="store-name">TTBH Số 4 - Cần Thơ</span>
                    <div class="store-address"><i class="fa fa-map-marker-alt"></i> 123 Đường 3/2, Q. Ninh Kiều</div>
                    <div class="store-address"><i class="fa fa-phone"></i> 0292.388.9999</div>
                </div>
            </div>
            <div class="page-content" style="margin-top:30px;">
                <p><i>* Ngoài ra, quý khách có thể gửi bảo hành tại bất kỳ cửa hàng bán lẻ nào của MobileStore.</i></p>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>