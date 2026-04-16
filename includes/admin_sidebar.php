<?php
// Đảm bảo security helper đã được load (có thể đã include từ trang cha)
if (!function_exists('csrf_token')) {
    include_once dirname(__DIR__) . '/includes/security.php';
}
?>
<!-- CSRF Meta Tag — JS đọc token từ đây để gửi kèm AJAX -->
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
<div class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0; white-space: nowrap; overflow: hidden; font-size: 20px;">Admin Panel</h3>
        <button id="toggleSidebar" style="background: transparent; color: white; border: none; font-size: 22px; cursor: pointer; padding: 0;">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
    <a href="../index.php" class="sb-link" style="background: #2c3e50;">
        <span><i class="fa-solid fa-arrow-left"></i> Về trang web</span>
    </a>

    <div class="sb-group">
        <div class="sb-group-title">
            <span><i class="fa-solid fa-box-open"></i> Quản lý Sản phẩm</span>
            <i class="fa-solid fa-chevron-down toggle-icon"></i>
        </div>
        <div class="sb-group-content">
            <a href="products.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='products.php' ? 'active' : '' ?>">
                <span><i class="fa fa-box"></i> Danh sách Sản phẩm</span>
            </a>
            <a href="attributes.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='attributes.php' ? 'active' : '' ?>">
                <span><i class="fa fa-tags"></i> Hãng & Danh mục</span>
            </a>
        </div>
    </div>

    <div class="sb-group">
        <div class="sb-group-title">
            <span>
                <i class="fa-solid fa-chart-line"></i> Kinh doanh & Bán hàng
                <span class="nav-badge badge-parent" style="display: none;"></span>
            </span>
            <i class="fa-solid fa-chevron-down toggle-icon"></i>
        </div>
        <div class="sb-group-content">
            <a href="orders.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='orders.php'?'active':'' ?>">
                <span><i class="fa-solid fa-file-invoice-dollar"></i> Quản lý Đơn hàng</span>
                <span id="badge-orders" class="nav-badge" style="display: none;"></span>
            </a>
            <a href="vouchers.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='vouchers.php'?'active':'' ?>">
                <span><i class="fa-solid fa-gift"></i> Quản lý Voucher</span>
            </a>
            <a href="flash_sale.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='flash_sale.php'?'active':'' ?>">
                <span><i class="fa-solid fa-bolt"></i> Flash Sale</span>
            </a>
            <a href="media_manager.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='media_manager.php'?'active':'' ?>">
                <span><i class="fa-solid fa-images"></i> Media / Banner</span>
            </a>
        </div>
    </div>

    <div class="sb-group">
        <div class="sb-group-title">
            <span>
                 <i class="fa-solid fa-users-cog"></i> Chăm sóc Khách hàng
                 <span class="nav-badge badge-parent" style="display: none;"></span>
            </span>
            <i class="fa-solid fa-chevron-down toggle-icon"></i>
        </div>
        <div class="sb-group-content">
            <a href="customers.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='customers.php'?'active':'' ?>">
                <span><i class="fa-solid fa-users"></i> Danh sách Khách hàng</span>
            </a>
            <a href="chat.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='chat.php'?'active':'' ?>">
                <span><i class="fa-solid fa-comments"></i> Chat Support</span>
                <span id="badge-chat" class="nav-badge" style="display: none;"></span>
            </a>
        </div>
    </div>

    <div class="sb-group">
        <div class="sb-group-title">
            <span><i class="fa-solid fa-cogs"></i> Báo cáo & Hệ thống</span>
            <i class="fa-solid fa-chevron-down toggle-icon"></i>
        </div>
        <div class="sb-group-content">
            <a href="dashboard.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>">
                <span><i class="fa-solid fa-chart-line"></i> Thống kê doanh thu</span>
            </a>
            <a href="activity_logs.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='activity_logs.php'?'active':'' ?>">
                <span><i class="fa-solid fa-history"></i> Lịch sử hoạt động</span>
            </a>
        </div>
    </div>

    <a href="../api/auth_api.php?logout=1" class="sb-link logout-btn" style="background-color: #dc3545; color: white;">
        <span><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</span>
    </a>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../assets/js/admin_main.js?v=<?php echo time(); ?>"></script>