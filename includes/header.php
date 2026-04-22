<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Store</title>

    <?php
    if (!session_id()) session_start();
    if (!function_exists('csrf_token')) {
        include_once dirname(__DIR__) . '/includes/security.php';
    }
    ?>
    <!-- CSRF Meta Tag cho frontend AJAX -->
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">

    <!-- Preconnect CDNs để giảm latency kết nối -->
    <link rel="preconnect" href="https://cdn.tgdd.vn">
    <link rel="preconnect" href="https://cdnv2.tgdd.vn">
    <link rel="preconnect" href="https://cdn2.cellphones.com.vn">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://code.jquery.com">

    <!-- CSS Critical (đồng bộ) -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Mobile Responsive -->
    <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/assets/css/mobile.css?v=<?= time() ?>">

    <!-- FontAwesome non-critical (async) -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"></noscript>

    <!-- jQuery defer (không block render) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>
</head>

<body>

    <?php 
include __DIR__ . '/navbar.php'; 
?>