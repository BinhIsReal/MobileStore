<?php session_start(); 
include '../config/db.php';?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Hệ thống 124 Cửa hàng - MobileStore</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-wrapper">
            <h2 class="page-title">Hệ thống cửa hàng</h2>

            <div class="store-grid">
                <?php for($i=1; $i<=6; $i++): ?>
                <div class="store-card">
                    <span class="store-name">MobileStore Chi nhánh Hà Nội <?= $i ?></span>
                    <div class="store-address">
                        <i class="fa fa-map-marker-alt"></i>
                        <span><?= $i*10 ?> Cầu Giấy, Phường Quan Hoa, Quận Cầu Giấy, Hà Nội</span>
                    </div>
                    <div class="store-address" style="margin-top:5px;">
                        <i class="fa fa-phone"></i>
                        <span>0987.654.32<?= $i ?></span>
                    </div>
                </div>
                <?php endfor; ?>

                <?php for($i=1; $i<=4; $i++): ?>
                <div class="store-card">
                    <span class="store-name">MobileStore Chi nhánh TP.HCM <?= $i ?></span>
                    <div class="store-address">
                        <i class="fa fa-map-marker-alt"></i>
                        <span><?= $i*15 ?> Nguyễn Trãi, Quận 1, TP. Hồ Chí Minh</span>
                    </div>
                    <div class="store-address" style="margin-top:5px;">
                        <i class="fa fa-phone"></i>
                        <span>0912.345.67<?= $i ?></span>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>

</html>