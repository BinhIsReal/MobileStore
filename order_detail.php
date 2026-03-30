<?php
session_start();
include 'config/db.php';

// 1. Kiểm tra ID đơn hàng
if (!isset($_GET['id']) || !isset($_SESSION['user_id'])) {
    header("Location: order_history.php");
    exit();
}

$order_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$sql_order = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql_order);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$res_order = $stmt->get_result();

if ($res_order->num_rows == 0) {
    die("<H1> ERROR: Không tìm thấy đơn hàng hoặc bạn không có quyền truy cập.</H1>.");
}
$order = $res_order->fetch_assoc();

$sql_items = "SELECT od.*, p.name, p.image 
              FROM order_details od 
              JOIN products p ON od.product_id = p.id 
              WHERE od.order_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$res_items = $stmt_items->get_result();

// --- LOGIC TRẠNG THÁI ---
$status_steps = [
    'pending'   => ['icon' => 'fa-file-invoice', 'label' => 'Đã đặt hàng'],
    'shipping'  => ['icon' => 'fa-truck-fast', 'label' => 'Đang giao hàng'],
    'completed' => ['icon' => 'fa-check-circle', 'label' => 'Hoàn thành'],
    'cancelled' => ['icon' => 'fa-xmark', 'label' => 'Đã hủy']
];

$current_status = $order['status'];
$is_cancelled = ($current_status == 'cancelled');

// Logic Disable nút Hủy
$allow_cancel = ($current_status == 'pending');

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Chi tiết đơn hàng <?= $order_id ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="detail-wrapper">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <div>
                    <a href="order_history.php" style="color:#666; font-size:13px;"><i class="fa fa-arrow-left"></i>
                        Quay lại</a>
                    <h2 style="margin:5px 0 0; color:var(--primary);">Đơn hàng <?= $order_id ?></h2>
                    <span style="font-size:13px; color:#888;">Ngày đặt:
                        <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
                </div>

                <?php if($allow_cancel): ?>
                <button class="btn-cancel-order" onclick="confirmCancel(<?= $order_id ?>)">
                    <i class="fa fa-trash"></i> Hủy đơn hàng
                </button>
                <?php elseif($current_status == 'shipping'): ?>
                <button disabled
                    style="background:#eee; border:none; color:#999; padding:8px 15px; border-radius:4px; cursor:not-allowed;">
                    <i class="fa fa-ban"></i> Không thể hủy (Đang giao)
                </button>
                <?php endif; ?>
            </div>

            <?php if(!$is_cancelled): ?>
            <div class="timeline">
                <?php 
                $passed = true;
                foreach($status_steps as $key => $step): 
                    if($key == 'cancelled') continue; 
                    $active = ($key == $current_status) ? 'active' : '';
                  
                    if($key == $current_status) $passed = false;
                ?>
                <div class="step <?= $active ?>">
                    <div class="step-icon"><i class="fa-solid <?= $step['icon'] ?>"></i></div>
                    <div class="step-label"><?= $step['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="alert"
                style="background:#ffecec; color:#d70018; padding:15px; border-radius:6px; text-align:center; margin-bottom:30px; font-weight:bold;">
                <i class="fa fa-circle-exclamation"></i> Đơn hàng này đã bị hủy.
            </div>
            <?php endif; ?>

            <div class="info-grid">
                <div class="info-box">
                    <span class="info-title">Địa chỉ nhận hàng</span>
                    <div class="info-row">
                        <i class="fa fa-user"></i> <b><?= htmlspecialchars($_SESSION['username'] ?? 'Khách') ?></b>
                    </div>
                    <div class="info-row">
                        <i class="fa fa-phone"></i> <?= htmlspecialchars($order['phone']) ?>
                    </div>
                    <div class="info-row">
                        <i class="fa fa-map-marker-alt"></i> <?= htmlspecialchars($order['address']) ?>
                    </div>
                </div>

                <div class="info-box">
                    <span class="info-title">Thông tin thanh toán</span>
                    <div class="info-row">
                        <i class="fa fa-credit-card"></i> Phương thức:
                        <b><?= $order['payment_method'] == 'banking' ? 'Chuyển khoản Ngân hàng' : 'Thanh toán khi nhận hàng (COD)' ?></b>
                    </div>
                    <div class="info-row">
                        <i class="fa fa-file-invoice-dollar"></i> Trạng thái:
                        <?php if($order['payment_status'] == 'paid'): ?>
                        <span style="color:green; font-weight:bold;">Đã thanh toán</span>
                        <?php else: ?>
                        <span style="color:#f39c12; font-weight:bold;">Chưa thanh toán</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>



            <div style="border:1px solid #eee; border-radius:8px; overflow:hidden; margin-bottom:20px;">
                <div
                    style="background:#f8f9fa; padding:12px 20px; font-weight:bold; color:#555; border-bottom:1px solid #eee;">
                    Sản phẩm
                </div>
                <div style="padding:0 20px;">
                    <?php 
                    $subtotal = 0;
                    while($item = $res_items->fetch_assoc()): 
                        $item_total = $item['price'] * $item['quantity'];
                        $subtotal += $item_total;
                        $img_url = strpos($item['image'], 'http') === 0 ? $item['image'] : "assets/img/{$item['image']}";
                    ?>
                    <div class="item-row">
                        <img src="<?= $img_url ?>" class="item-img" alt="Product">
                        <div class="item-info">
                            <a href="product_detail.php?id=<?= $item['product_id'] ?>"
                                class="item-name"><?= $item['name'] ?></a>
                            <div class="item-meta">
                                Số lượng: x<?= $item['quantity'] ?>
                            </div>
                        </div>
                        <div class="item-total">
                            <?= number_format($item['price'], 0, ',', '.') ?> ₫
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="summary-box">
                <div class="sum-row">
                    <span class="sum-label">Tạm tính:</span>
                    <span class="sum-val"><?= number_format($subtotal, 0, ',', '.') ?> ₫</span>
                </div>

                <?php if($order['discount_amount'] > 0): ?>
                <div class="sum-row" style="color:#27ae60;">
                    <span class="sum-label">Giảm giá:</span>
                    <span class="sum-val">- <?= number_format($order['discount_amount'], 0, ',', '.') ?> ₫</span>
                </div>
                <?php endif; ?>

                <div class="sum-row">
                    <span class="sum-label">Phí vận chuyển:</span>
                    <span class="sum-val">Miễn phí</span>
                </div>

                <div class="sum-row final-total">
                    <span class="sum-label" style="color:#333; margin-right:10px;">Tổng cộng:</span>
                    <span><?= number_format($order['total_price'], 0, ',', '.') ?> ₫</span>
                </div>
            </div>

        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>

</html>