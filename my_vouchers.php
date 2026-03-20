<?php
session_start();
include 'config/db.php';

// Bắt buộc đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 1. Đánh dấu tất cả Voucher mới thành "Đã xem" (Tắt popup)
$conn->query("UPDATE user_vouchers SET is_new = 0 WHERE user_id = $user_id AND is_new = 1");

// 2. Lấy danh sách Voucher của User (Còn hạn và còn lượt dùng)
$sql = "SELECT v.*, uv.usage_limit, uv.used_count 
        FROM user_vouchers uv
        JOIN vouchers v ON uv.voucher_id = v.id
        WHERE uv.user_id = ? 
          AND v.expiry_date >= CURDATE() 
          AND uv.usage_limit > uv.used_count
        ORDER BY v.expiry_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vouchers = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Kho Voucher Của Tôi</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <?php include 'includes/header.php'; ?>

    <div class="container voucher-page-wrapper">
        <div class="voucher-header">
            <i class="fa-solid fa-ticket-simple" style="font-size: 30px; color: #00487a;"></i>
            <h2>Kho Voucher Của Tôi</h2>
        </div>

        <?php if ($vouchers->num_rows == 0): ?>
        <div style="text-align: center; padding: 50px 0; color: #888;">
            <i class="fa-solid fa-box-open" style="font-size: 60px; color: #ccc; margin-bottom: 15px;"></i>
            <h3>Kho voucher trống</h3>
            <p>Bạn chưa có mã giảm giá nào hoặc các mã đã hết hạn.</p>
            <a href="index.php" class="btn-use" style="display:inline-block; margin-top: 15px;">Mua sắm ngay</a>
        </div>
        <?php else: ?>

        <div class="voucher-list">
            <?php while ($v = $vouchers->fetch_assoc()): 
                    // Xử lý chuỗi hiển thị mức giảm
                    $discount_text = "";
                    if ($v['type'] == 'percent') {
                        $discount_text = "Giảm " . (float)$v['discount_amount'] . "%";
                        $max_desc = $v['max_discount'] > 0 ? " Giảm tối đa " . number_format($v['max_discount']) . "đ" : "";
                    } else {
                        $discount_text = "Giảm " . number_format($v['discount_amount']) . "đ";
                        $max_desc = "";
                    }
                    
                    $min_order = $v['min_order_value'] > 0 ? "Đơn tối thiểu " . number_format($v['min_order_value']) . "đ." : "Áp dụng cho mọi đơn hàng.";
                    $remains = $v['usage_limit'] - $v['used_count'];
                ?>

            <div class="voucher-ticket">
                <div class="voucher-left">
                    <i class="fa-solid fa-gift"></i>
                    <span>MobileStore<br>Voucher</span>
                </div>

                <div class="voucher-right">
                    <div class="voucher-info">
                        <div class="voucher-code"><?= htmlspecialchars($v['code']) ?></div>
                        <h3><?= $discount_text ?></h3>
                        <p class="voucher-desc">
                            <?= $min_order ?><br>
                            <?= $max_desc ?>
                        </p>
                        <div class="voucher-expiry"><i class="fa-regular fa-clock"></i> HSD:
                            <?= date('d/m/Y', strtotime($v['expiry_date'])) ?></div>
                    </div>

                    <div class="voucher-action">
                        <a href="cart.php" class="btn-use">Dùng ngay</a>
                        <span class="usage-count">Còn <?= $remains ?> lượt</span>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>

</html>