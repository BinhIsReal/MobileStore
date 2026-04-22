<?php
session_start();
include '../config/db.php'; 
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Tra cứu đơn hàng - TechMate</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/pages.css?v=<?= time() ?>">
   <link rel="stylesheet" href="../assets/css/mobile.css?v=<?php echo filemtime('../assets/css/mobile.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-wrapper">
            <h2 class="page-title text-center">Tra cứu tình trạng đơn hàng</h2>

            <div class="tracking-box">
                <p style="margin-bottom:20px;">Nhập Mã đơn hàng và Số điện thoại đặt hàng để kiểm tra.</p>

                <div class="tracking-input-group">
                    <input type="number" id="track-id" class="tracking-input" placeholder="Mã đơn (Ví dụ: 123)">
                    <input type="text" id="track-phone" class="tracking-input" placeholder="Số điện thoại nhận hàng">
                </div>

                <button id="btn-track" onclick="trackOrder()" class="btn-confirm" style="width:100%;">
                    <i class="fa fa-search"></i> TRA CỨU NGAY
                </button>

                <div id="tracking-result" class="tracking-result"></div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="../assets/js/pages.js?v=<?= time() ?>"></script>
</body>

</html>