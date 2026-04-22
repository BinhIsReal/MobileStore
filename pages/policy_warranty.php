<?php session_start();
include '../config/db.php'; ?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chính sách bảo hành - TechMate</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link rel="stylesheet" href="../assets/css/mobile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container">
        <div class="page-wrapper">
            <h2 class="page-title">Chính sách bảo hành đổi mới</h2>
            <div class="page-content">
                <h3>1. Chính sách bao xài đổi trả</h3>
                <ul>
                    <li><strong>30 ngày đầu:</strong> 1 đổi 1 nếu có lỗi phần cứng từ nhà sản xuất.</li>
                    <li><strong>Điều kiện:</strong> Máy không trầy xước, móp méo, còn đầy đủ hộp và phụ kiện.</li>
                    <li>Nếu không đủ điều kiện đổi mới (máy trầy, mất hộp...), TechMate sẽ thu phí theo quy định
                        (10-20%).</li>
                </ul>

                <h3>2. Thời gian bảo hành tiêu chuẩn</h3>
                <ul>
                    <li>Điện thoại iPhone: 12 tháng (Chính hãng VN/A).</li>
                    <li>Điện thoại Samsung/Xiaomi/OPPO: 12 - 18 tháng (Tùy model).</li>
                    <li>Phụ kiện (Sạc, cáp, tai nghe): 12 tháng 1 đổi 1.</li>
                    <li>Pin điện thoại: Bảo hành 12 tháng (hoặc chai quá 20%).</li>
                </ul>

                <h3>3. Địa điểm bảo hành</h3>
                <p>Quý khách có thể mang máy đến bất kỳ cửa hàng nào của TechMate trên toàn quốc hoặc đến trực tiếp
                    Trung tâm bảo hành chính hãng của hãng (Apple, Samsung...).</p>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>