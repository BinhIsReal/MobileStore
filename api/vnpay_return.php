<?php
/**
 * VNPay Payment Return Handler
 * Xử lý callback từ VNPay sau khi user thanh toán xong.
 * 
 * Flow: VNPay redirect về URL này với params chứa kết quả giao dịch.
 * 1. Verify chữ ký (checksum)
 * 2. Cập nhật payment_status trong DB
 * 3. Redirect user về trang kết quả
 */
session_start();
include '../config/db.php';
include '../config/vnpay_config.php';

$vnp_ResponseCode = $_GET['vnp_ResponseCode'] ?? '';
$vnp_TxnRef       = $_GET['vnp_TxnRef'] ?? '';
$vnp_SecureHash   = $_GET['vnp_SecureHash'] ?? '';

// Lấy tất cả params trừ vnp_SecureHash và vnp_SecureHashType
$inputData = [];
foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) === 'vnp_' && $key !== 'vnp_SecureHash' && $key !== 'vnp_SecureHashType') {
        $inputData[$key] = $value;
    }
}

ksort($inputData);

$hashData = '';
$i = 0;
foreach ($inputData as $key => $value) {
    if ($i > 0) {
        $hashData .= '&';
    }
    $hashData .= urlencode($key) . '=' . urlencode($value); 
    $i++;
}

// Verify checksum
$vnp_HashSecret = trim(VNP_HASH_SECRET);
$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
$isValidSignature = hash_equals($secureHash, $vnp_SecureHash);

$txnParts   = explode('_', $vnp_TxnRef);
array_pop($txnParts);
$order_code = implode('_', $txnParts);

if (!empty($_SESSION['vnpay_order_code'])) {
    $order_code = $_SESSION['vnpay_order_code'];
    unset($_SESSION['vnpay_order_code']);
}

if (!$isValidSignature) {
    header('Location: ../cart.php?vnpay_status=invalid_signature');
    exit;
}

if ($vnp_ResponseCode === '00') {
    // Thanh toán thành công → Cập nhật trạng thái
    $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE order_code = ? AND payment_status = 'unpaid'");
    $stmt->bind_param("s", $order_code);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    // Chỉ thực hiện cấp thưởng/xoá giỏ hàng NẾU đây là lần cập nhật đầu tiên (tránh lặp khi F5)
    if ($affected > 0) {
        $stmt_get = $conn->prepare("SELECT id, user_id, total_price, discount_amount FROM orders WHERE order_code = ?");
        $stmt_get->bind_param("s", $order_code);
        $stmt_get->execute();
        $ord = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        if ($ord) {
            $oid = $ord['id'];
            $uid = $ord['user_id'];
            
            // Xoá giỏ hàng (chỉ xoá những sản phẩm nằm trong đơn hàng này)
            $del_cart = $conn->prepare("DELETE cart FROM cart JOIN order_details ON cart.product_id = order_details.product_id WHERE cart.user_id = ? AND order_details.order_id = ?");
            $del_cart->bind_param("ii", $uid, $oid);
            $del_cart->execute();
            $del_cart->close();

            // Gửi Notification đặt hàng thành công
            $n_success_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'order_success', 'Đặt hàng thành công', ?, '/order_history.php', 0, NOW())");
            if ($n_success_stmt) {
                $msg = "Đơn hàng #{$order_code} đã được thanh toán và đặt thành công. Cảm ơn bạn đã mua sắm!";
                $n_success_stmt->bind_param("is", $uid, $msg);
                $n_success_stmt->execute();
                $n_success_stmt->close();
            }

            // Tặng Reward voucher
            $final_paid = $ord['total_price'] - $ord['discount_amount'];
            $reward_sql = "SELECT id, code FROM vouchers WHERE is_reward_template = 1 AND reward_min_order <= ? AND (expiry_date >= CURDATE() OR expiry_date = '0000-00-00') ORDER BY reward_min_order DESC LIMIT 1";
            $r_stmt = $conn->prepare($reward_sql);
            $r_stmt->bind_param("d", $final_paid);
            $r_stmt->execute();
            $reward_v = $r_stmt->get_result()->fetch_assoc();
            $r_stmt->close();

            if ($reward_v) {
                $grant = $conn->prepare("INSERT INTO user_vouchers (user_id, voucher_id, usage_limit, is_new) VALUES (?, ?, 1, 1) ON DUPLICATE KEY UPDATE usage_limit = usage_limit + 1, is_new = 1");
                $grant->bind_param("ii", $uid, $reward_v['id']);
                $grant->execute();
                $grant->close();

                $noti_msg = "🎁 Bạn nhận được voucher thưởng \"{$reward_v['code']}\" cho đơn hàng VNPay vừa hoàn thành!";
                $conn->query("INSERT INTO notifications (user_id, type, title, message, link) VALUES ($uid, 'reward_voucher', 'Nhận Voucher Thưởng!', '$noti_msg', '/my_vouchers.php')");
            }

            // Cập nhật Association Rules (Gợi ý sản phẩm mua cùng nhau)
            $pairs_stmt = $conn->prepare("SELECT od1.product_id AS a, od2.product_id AS b FROM order_details od1 JOIN order_details od2 ON od1.order_id = od2.order_id AND od1.product_id < od2.product_id WHERE od1.order_id = ?");
            $pairs_stmt->bind_param("i", $oid);
            $pairs_stmt->execute();
            $pairs_res = $pairs_stmt->get_result();
            $pairs_stmt->close();

            $ins_assoc = $conn->prepare("INSERT INTO product_associations (product_a, product_b, co_count) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE co_count = co_count + 1");
            while ($pair = $pairs_res->fetch_assoc()) {
                $ins_assoc->bind_param("ii", $pair['a'], $pair['b']);
                $ins_assoc->execute();
            }
            $ins_assoc->close();
        }
    }

    header('Location: ../order_history.php?vnpay_status=success');
    exit;
} else {
    // Thanh toán thất bại hoặc bị hủy
    $status_map = [
        '07' => 'suspected',    // Nghi ngờ gian lận
        '09' => 'not_registered', // Chưa đăng ký InternetBanking
        '10' => 'auth_failed',  // Xác thực quá 3 lần
        '11' => 'timeout',      // Hết hạn chờ
        '12' => 'card_locked',  // Thẻ bị khóa
        '13' => 'wrong_otp',    // Sai OTP
        '24' => 'cancelled',    // Khách hủy giao dịch
        '51' => 'insufficient', // Tài khoản không đủ
        '65' => 'over_limit',   // Vượt hạn mức
        '75' => 'bank_maintenance', // Ngân hàng bảo trì
        '79' => 'wrong_password', // Sai mật khẩu thanh toán
        '99' => 'unknown',      // Lỗi khác
    ];
    $error_code = $status_map[$vnp_ResponseCode] ?? 'failed';

    header("Location: ../order_history.php?vnpay_status=failed&error_code={$error_code}");
    exit;
}
?>
