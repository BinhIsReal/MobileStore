<?php
$host = "localhost";
$user = "root";
$pass = "";
$db_name = "mobile_store_db";

$conn = new mysqli($host, $user, $pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Nhận diện giao thức an toàn với Cloudflare / Reverse Proxy headers
$is_https = false;
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $is_https = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $is_https = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
    $is_https = true;
} elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
    $is_https = true;
}

$protocol = $is_https ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . $domain);
?>