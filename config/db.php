<?php
$env_path = dirname(__DIR__) . '/.env';
$env_vars = [];
if (file_exists($env_path)) {
    $env_vars = parse_ini_file($env_path);
}

$host     = $env_vars['DB_HOST'] ?? "localhost";
$user     = $env_vars['DB_USER'] ?? "root";
$pass     = $env_vars['DB_PASS'] ?? "";
$db_name  = $env_vars['DB_NAME'] ?? "mobile_store_db";

$conn = new mysqli($host, $user, $pass, $db_name);

if ($conn->connect_error) {
    error_log("DB Connection Error: " . $conn->connect_error);
    die("Lỗi hệ thống. Vui lòng thử lại sau.");
}
$conn->set_charset("utf8");

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

$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$dir_path = str_replace('\\', '/', dirname(__DIR__));
$project_dir = '';
if (!empty($doc_root) && stripos($dir_path, $doc_root) === 0) {
    $project_dir = substr($dir_path, strlen($doc_root));
}
$project_dir = '/' . trim(str_replace('\\', '/', $project_dir), '/');
if ($project_dir === '/') {
    $project_dir = '';
}

define('BASE_URL', $protocol . $domain . $project_dir);
?>