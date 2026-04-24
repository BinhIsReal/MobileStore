<?php
session_start();
include '../config/db.php';
include '../config/vnpay_config.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id === 0) { header('Location: ../login.php'); exit; }

$order_code = trim($_GET['order_code'] ?? '');
$amount     = (int)($_GET['amount'] ?? 0);
if (empty($order_code) || $amount <= 0) { die('Thông tin thanh toán không hợp lệ.'); }

$stmt = $conn->prepare("SELECT id, total_price, discount_amount FROM orders WHERE order_code = ? AND user_id = ? AND status = 'pending' AND payment_status = 'unpaid'");
$stmt->bind_param("si", $order_code, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$order) { die('Đơn hàng không tồn tại hoặc không hợp lệ.'); }

$pay_amount = (float)$order['total_price'] - (float)$order['discount_amount'];
if ($pay_amount <= 0) { die('Số tiền thanh toán không hợp lệ.'); }
$vnp_amount = (int)($pay_amount * 100);

$_SESSION['vnpay_order_code'] = $order_code;
$vnp_TxnRef = $order_code . '_' . time();

date_default_timezone_set('Asia/Ho_Chi_Minh');

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if ($clientIp === '::1' || strpos($clientIp, ':') !== false) {
    $clientIp = '127.0.0.1';
}

$inputData = [
    "vnp_Version"    => "2.1.0",
    "vnp_TmnCode"    => VNP_TMN_CODE,
    "vnp_Amount"     => (string)$vnp_amount,
    "vnp_Command"    => "pay",
    "vnp_CreateDate" => date('YmdHis'),
    "vnp_CurrCode"   => "VND",
    "vnp_ExpireDate" => date('YmdHis', strtotime('+30 minutes')),
    "vnp_IpAddr"     => $clientIp,
    "vnp_Locale"     => "vn",
    "vnp_OrderInfo"  => "ThanhToanDonHang_" . $order_code,
    "vnp_OrderType"  => "other",
    "vnp_ReturnUrl"  => VNP_RETURN_URL,
    "vnp_TxnRef"     => $vnp_TxnRef,
];

ksort($inputData);

$hashdata = '';
$query = '';
$i = 0;
foreach ($inputData as $key => $value) {
    if ($i > 0) {
        $hashdata .= '&';
        $query .= '&';
    }
    $hashdata .= urlencode($key) . '=' . urlencode($value);
$query    .= urlencode($key) . '=' . urlencode($value);
     $i++;
}

$vnp_HashSecret = trim(VNP_HASH_SECRET);

$vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
$vnp_Url = VNP_URL . '?' . $query . '&vnp_SecureHashType=HmacSHA512&vnp_SecureHash=' . $vnpSecureHash;


header('Location: ' . $vnp_Url);
exit;
?>
