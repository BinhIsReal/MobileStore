<?php
/**
 * VNPay Sandbox Configuration
 * API Version: 2.1.0
 * Docs: https://sandbox.vnpayment.vn/apis/
 */

// Load cấu hình từ file .env
$env_path = dirname(__DIR__) . '/.env';
$env_vars = [];
if (file_exists($env_path)) {
    $env_vars = parse_ini_file($env_path);
}

define('VNP_TMN_CODE', $env_vars['VNP_TMN_CODE'] ?? '5S2D9UJX');
define('VNP_HASH_SECRET', $env_vars['VNP_HASH_SECRET'] ?? 'NFUPX25XDD88MGGCECDDC9BGT83U1ALS');
define('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');

// URL nhận kết quả trả về sau thanh toán
define('VNP_RETURN_URL', BASE_URL . '/api/vnpay_return.php');
?>
