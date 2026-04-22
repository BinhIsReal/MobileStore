<?php
session_start();
include 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử đơn hàng - TechMate</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/mobile.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>

    <?php require_once 'includes/navbar.php'; ?>

    <div class="container">
        <div class="history-wrapper">
            <div class="history-header">
                <h2><i class="fa-solid fa-clock-rotate-left"></i> Đơn hàng của tôi</h2>
                <a href="index.php" class="btn-view-detail"><i class="fa-solid fa-arrow-left"></i> Tiếp tục mua sắm</a>
            </div>

            <table class="history-table">
                <thead>
                    <tr>
                        <th width="10%">Mã đơn</th>
                        <th width="20%">Ngày đặt</th>
                        <th width="20%">Tổng tiền</th>
                        <th width="20%">Trạng thái</th>
                        <th width="15%" style="text-align:right;">Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $status_class = 'bg-secondary';
                            $status_text = 'Chưa rõ';

                            switch ($row['status']) {
                                case 'pending':
                                    $status_class = 'bg-pending';
                                    $status_text = 'Chờ xử lý';
                                    break;
                                case 'shipping':
                                    $status_class = 'bg-shipping';
                                    $status_text = 'Đang giao';
                                    break;
                                case 'completed':
                                    $status_class = 'bg-completed';
                                    $status_text = 'Hoàn thành';
                                    break;
                                case 'cancelled':
                                    $status_class = 'bg-cancelled';
                                    $status_text = 'Đã hủy';
                                    break;
                            }
                            ?>
                    <tr>
                        <td><b>#<?= $row['order_code'] ?? $row['id'] ?></b></td>
                        <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                        <?php $final_history = max(0, $row['total_price'] - $row['discount_amount']); ?>
                        <td class="total-money"><?= number_format($final_history, 0, ',', '.') ?> ₫</td>
                        <td>
                            <span class="badge-status <?= $status_class ?>">
                                <?= $status_text ?>
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <a href="order_detail.php?id=<?= $row['id'] ?>" class="btn-view-detail"
                                title="Xem chi tiết">
                                <i class="fa-solid fa-eye"></i> Xem
                            </a>
                        </td>
                    </tr>
                    <?php
                        }
                    } else {
                        echo '<tr><td colspan="5" style="text-align:center; padding: 50px;">
                                <img src="https://cdn-icons-png.flaticon.com/512/2038/2038854.png" width="80" style="margin:0 auto 15px; opacity:0.5;">
                                <p>Bạn chưa có đơn hàng nào.</p>
                                <a href="index.php" style="color:#d70018; font-weight:bold;">Mua sắm ngay</a>
                              </td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php require_once "includes/footer.php"; ?>

</body>

</html>