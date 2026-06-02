<?php
/**
 * VNPay Sandbox Configuration
 * API Version: 2.1.0
 * Docs: https://sandbox.vnpayment.vn/apis/
 */

$env_path = dirname(__DIR__) . '/.env';
$env_vars = [];
if (file_exists($env_path)) {
    $env_vars = parse_ini_file($env_path);
}

define('VNP_TMN_CODE', $env_vars['VNP_TMN_CODE'] ?? '5S2D9UJX');
define('VNP_HASH_SECRET', $env_vars['VNP_HASH_SECRET'] ?? '729OCLZ0MQ22RBQWUW8LC6D2UIXR7HVZ');
define('VNP_URL', $env_vars['VNP_URL'] ?? 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');

define('VNP_RETURN_URL', BASE_URL . '/api/vnpay_return.php');
?>
