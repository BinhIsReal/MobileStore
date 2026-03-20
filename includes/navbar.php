<?php 
if (session_status() === PHP_SESSION_NONE) session_start(); 
?>

<nav>
    <div class="container nav-content">
        <a href="<?= BASE_URL ?>/index.php" class="logo">
            <i class="fa-solid fa-mobile-screen-button"></i> MobileStore
        </a>

        <form action="search.php" method="GET" class="search-box search-form-wrap">
            <input type="text" id="search-input" name="q" placeholder="Bạn cần tìm gì hôm nay?"
                value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>" autocomplete="off">
            <button type="submit"><i class="fa fa-search"></i></button>
            <div id="search-suggestions" class="search-suggestions-box"></div>
        </form>

        <div class="menu">
            <a href="<?= BASE_URL ?>/pages/order_tracking.php" class="menu-item hide-on-mobile">
                <i class="fa-solid fa-folder"></i> Tra cứu đơn hàng
            </a>

            <a href="<?= BASE_URL ?>/pages/store_system.php" class="menu-item hide-on-mobile">
                <i class="fa-solid fa-location-dot"></i> Cửa hàng
            </a>

            <a href="<?= BASE_URL ?>/cart.php" class="menu-item menu-cart-box">
                <i class="fa-solid fa-cart-shopping menu-cart-icon"></i>

                <?php
                    $cart_qty = 0;
                    if(isset($conn) && isset($_SESSION['user_id'])) {
                        $uid = $_SESSION['user_id'];
                        $res = $conn->query("SELECT SUM(quantity) as t FROM cart WHERE user_id=$uid");
                        if($res && $r = $res->fetch_assoc()) $cart_qty = $r['t'] ?? 0;
                    } else {
                        $cart_qty = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
                    }
                ?>
                <span class="menu-cart-badge <?= $cart_qty > 0 ? '' : 'hidden' ?>">
                    <?= $cart_qty ?>
                </span>

                <span>Giỏ hàng</span>
            </a>

            <div class="menu-item user-dropdown-container">
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-box-trigger">
                    <i class="fa-solid fa-circle-user user-avatar-icon"></i>
                    <span class="user-name-text"><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <i class="fa-solid fa-caret-down user-caret-icon"></i>
                </div>

                <div class="dropdown-content" id="userDropdown">
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                    <a href="<?= BASE_URL ?>/admin/products.php"><i class="fa-solid fa-gauge"></i> Quản trị</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/profile.php"><i class="fa-solid fa-file-invoice"></i> Thông tin cá nhân</a>
                    <a href="<?= BASE_URL ?>/order_history.php"><i class="fa-solid fa-file-invoice"></i> Đơn hàng</a>
                    <a href="<?= BASE_URL ?>/my_vouchers.php"><i class="fa-solid fa-ticket-simple"></i> Kho Voucher</a>
                    <a href="<?= BASE_URL ?>/api/auth_api.php?logout=1" class="logout-link"><i
                            class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
                </div>
                <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php" class="user-box-trigger">
                    <i class="fa-solid fa-circle-user user-avatar-icon"></i> Đăng nhập
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>