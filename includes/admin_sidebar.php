<div class="admin-sidebar">
    <h3>Admin Panel</h3>

    <a href="../index.php" class="sb-link" style="background: #2c3e50;">
        <span><i class="fa-solid fa-arrow-left"></i> Về trang web</span>
    </a>

    <a href="products.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='products.php' ? 'active' : '' ?>">
        <span><i class="fa fa-box"></i> Quản lý Sản phẩm</span>
    </a>

    <a href="attributes.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='attributes.php' ? 'active' : '' ?>">
        <span><i class="fa fa-tags"></i> QL Hãng & Danh mục</span>
    </a>

    <a href="orders.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='orders.php'?'active':'' ?>">
        <span><i class="fa-solid fa-file-invoice-dollar"></i> Quản lý Đơn hàng</span>
        <span id="badge-orders" class="nav-badge" style="display: none;"></span>
    </a>

    <a href="dashboard.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>">
        <span><i class="fa-solid fa-chart-line"></i> Thống kê doanh thu
        </span>
    </a>

    <a href="vouchers.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='vouchers.php'?'active':'' ?>">
        <span><i class="fa-solid fa-gift"></i> Quản lý Voucher
        </span>
    </a>

    <a href="customers.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='customers.php'?'active':'' ?>">
        <span><i class="fa-solid fa-users"></i> Quản lý khách hàng
        </span>
    </a>

    <a href="chat.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='chat.php'?'active':'' ?>">
        <span><i class="fa-solid fa-comments"></i> Chat Support</span>
        <span id="badge-chat" class="nav-badge" style="display: none;"></span>
    </a>

    <a href="activity_logs.php" class="sb-link <?= basename($_SERVER['PHP_SELF'])=='activity_logs.php'?'active':'' ?>">
        <span><i class="fa-solid fa-history"></i> Lịch sử hoạt động</span>
    </a>

    <a href="../api/auth_api.php?logout=1" class="sb-link logout-btn">
        <span><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</span>
    </a>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../assets/js/admin_main.js?v=<?php echo time(); ?>"></script>