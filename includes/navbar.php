<?php 
if (session_status() === PHP_SESSION_NONE) session_start();
$_is_logged    = isset($_SESSION['user_id']);
?>
    <!-- Đảm bảo mobile.css luôn được load đúng đường dẫn (Responsive UI) -->
    <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/assets/css/mobile.css?v=<?= time() ?>">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<nav>
    <div class="container nav-content">
        <a href="<?= BASE_URL ?>/index.php" class="logo">
            <i class="fa-solid fa-mobile-screen-button"></i> TechMate
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

            <!-- ===== CART ===== -->
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
                <?php if ($cart_qty > 0): ?>
                <span class="menu-cart-badge"><?= $cart_qty ?></span>
                <?php endif; ?>
                <span>Giỏ hàng</span>
            </a>

            <?php if ($_is_logged): ?>
            <!-- ===== NOTIFICATION BELL ===== -->
            <div class="menu-item nav-notif-wrap" id="nav-notif-btn" title="Thông báo">
                <i class="fa-solid fa-bell"></i>
                <!-- Badge chỉ render khi count > 0 qua JS -->
                <span id="nav-notif-badge" style="display:none;" class="menu-cart-badge">0</span>
                <div id="notif-dropdown">
                    <div class="notif-header">
                        <b>🔔 Thông báo</b>
                        <a href="#" id="btn-mark-all-read">Đánh dấu tất cả đã đọc</a>
                    </div>
                    <div id="notif-list">
                        <div class="notif-empty"><i class="fa fa-spinner fa-spin"></i></div>
                    </div>

                </div>
            </div>
            <?php endif; ?>

            <!-- ===== USER DROPDOWN ===== -->
            <div class="menu-item user-dropdown-container">
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-box-trigger">
                    <i class="fa-solid fa-circle-user user-avatar-icon"></i>
                    <span class="user-name-text"><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <i class="fa-solid fa-caret-down user-caret-icon"></i>
                </div>

                <div class="dropdown-content" id="userDropdown">
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                    <a href="<?= BASE_URL ?>/admin/dashboard.php"><i class="fa-solid fa-gauge"></i> Quản trị</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/profile.php"><i class="fa-solid fa-file-invoice"></i> Thông tin cá nhân</a>
                    <a href="<?= BASE_URL ?>/order_history.php"><i class="fa-solid fa-file-invoice"></i> Đơn hàng</a>
                    <a href="<?= BASE_URL ?>/my_vouchers.php"><i class="fa-solid fa-ticket-simple"></i> Kho Voucher</a>
                    <a href="<?= BASE_URL ?>/wishlist.php"><i class="fa-solid fa-heart"></i> Yêu thích</a>
                    <a href="<?= BASE_URL ?>/api/auth_api.php?logout=1" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
                </div>
                <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php" class="user-box-trigger">
                    <i class="fa-solid fa-circle-user user-avatar-icon"></i> Đăng nhập
                </a>
                <?php endif; ?>
            </div>

        </div><!-- /.menu -->
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const IS_LOGGED = <?= $_is_logged ? 'true' : 'false' ?>;

    /* =========================================================
       1. NOTIFICATION DROPDOWN + BADGE (SYNC DESKTOP & MOBILE)
       ========================================================= */
    if (!IS_LOGGED) return;

    // Desktop Elements
    const notifBtn = document.getElementById('nav-notif-btn');
    const notifDropdown = document.getElementById('notif-dropdown');
    const notifBadge = document.getElementById('nav-notif-badge');
    const notifList = document.getElementById('notif-list');
    const markAllBtn = document.getElementById('btn-mark-all-read');

    // Mobile Elements
    const mNotifBtn = document.getElementById('m-btn-notif');
    const mNotifBadge = document.getElementById('m-nav-notif-badge');
    const mNotifList = document.getElementById('m-notif-list');
    const mMarkAllBtn = document.getElementById('m-btn-mark-all-read');

    // Deep-link theo type
    const TYPE_ICON = {
        price_drop: { icon: '🔥', label: 'Giảm giá Wishlist' },
        reward_voucher: { icon: '🎁', label: 'Voucher thưởng' },
        order_status: { icon: '📦', label: 'Cập nhật đơn hàng' },
        system: { icon: 'ℹ️', label: 'Hệ thống' }
    };
    const TYPE_LINK = {
        price_drop: '/wishlist.php',
        reward_voucher: '/my_vouchers.php',
        order_status: '/order_history.php',
        system: '#'
    };

    // --- Escape HTML ---
    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    // --- Render 1 notification item với deep-link ---
    function renderNotifItem(n) {
        const meta = TYPE_ICON[n.type] || TYPE_ICON.system;
        const deepLink = n.link || TYPE_LINK[n.type] || '#';
        const unread = n.is_read == 0;
        const timeStr = n.created_at ? n.created_at.substring(0, 16).replace('T', ' ') : '';

        const el = document.createElement('div');
        el.className = 'notif-item' + (unread ? ' unread' : '');
        el.setAttribute('role', 'button');
        el.setAttribute('tabindex', '0');
        el.innerHTML = `
            <span class="notif-icon">${meta.icon}</span>
            <div class="notif-body">
                <div class="notif-type-label">${meta.label}</div>
                <div class="notif-title">${escHtml(n.title)}</div>
                <div class="notif-msg">${escHtml(n.message)}</div>
                <div class="notif-time">${timeStr}</div>
            </div>
            ${unread ? '<span class="notif-dot"></span>' : ''}
        `;

        // Click: mark_read → redirect
        el.addEventListener('click', function() {
            fetch('/api/notification_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_read&id=' + encodeURIComponent(n.id)
            }).finally(function() {
                if (deepLink && deepLink !== '#') {
                    window.location.href = deepLink;
                } else {
                    el.classList.remove('unread');
                    const dot = el.querySelector('.notif-dot');
                    if (dot) dot.remove();
                    refreshBadges();
                }
            });
        });
        return el;
    }

    // --- Load + render danh sách (Cả Desktop và Mobile) ---
    function loadNotifList() {
        if (notifList) notifList.innerHTML = '<div class="notif-empty"><i class="fa fa-spinner fa-spin"></i> Đang tải...</div>';
        if (mNotifList) mNotifList.innerHTML = '<div style="padding: 30px; text-align: center; color: #888;"><i class="fa fa-spinner fa-spin"></i> Đang tải...</div>';

        fetch('/api/notification_api.php?action=get_notifications&limit=15')
            .then(r => r.json())
            .then(function(res) {
                if (notifList) notifList.innerHTML = '';
                if (mNotifList) mNotifList.innerHTML = '';

                if (!res.data || res.data.length === 0) {
                    if (notifList) notifList.innerHTML = '<div class="notif-empty">Không có thông báo nào</div>';
                    if (mNotifList) mNotifList.innerHTML = '<div style="padding: 30px; text-align: center; color: #888;">Không có thông báo nào</div>';
                    return;
                }
                
                res.data.forEach(function(n) {
                    if (notifList) notifList.appendChild(renderNotifItem(n));
                    if (mNotifList) mNotifList.appendChild(renderNotifItem(n)); 
                });
            })
            .catch(function() {
                if (notifList) notifList.innerHTML = '<div class="notif-empty">Lỗi tải thông báo</div>';
                if (mNotifList) mNotifList.innerHTML = '<div style="padding: 30px; text-align: center; color: #888;">Lỗi tải thông báo</div>';
            });
    }

    // --- Refresh badges: CHỈ hiện khi count > 0 ---
    function refreshBadges() {
        fetch('/api/notification_api.php?action=count_unread')
            .then(r => r.json())
            .then(function(res) {
                const count = parseInt(res.unread) || 0;
                const badgeText = count > 99 ? '99+' : count;

                if (count > 0) {
                    if (notifBadge) {
                        notifBadge.textContent = badgeText;
                        notifBadge.style.display = '';
                    }
                    if (mNotifBadge) {
                        mNotifBadge.textContent = badgeText;
                        mNotifBadge.style.display = 'flex'; // m-badge uses display: flex natively
                    }
                } else {
                    if (notifBadge) {
                        notifBadge.style.display = 'none';
                        notifBadge.textContent = '0';
                    }
                    if (mNotifBadge) {
                        mNotifBadge.style.display = 'none';
                        mNotifBadge.textContent = '0';
                    }
                }
            }).catch(function() {
                if (notifBadge) notifBadge.style.display = 'none';
                if (mNotifBadge) mNotifBadge.style.display = 'none';
            });
    }

    // --- Toggle dropdown (Desktop) ---
    if (notifBtn) {
        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = notifDropdown.classList.contains('open');
            notifDropdown.classList.toggle('open');
            if (!isOpen) loadNotifList();
        });
    }

    // --- Click Outside (Desktop) ---
    document.addEventListener('click', function(e) {
        if (notifBtn && notifDropdown && !notifBtn.contains(e.target)) {
            notifDropdown.classList.remove('open');
        }
    });

    // --- Mark all read (Desktop & Mobile) ---
    function doMarkAllRead(e) {
        e.preventDefault();
        e.stopPropagation();
        fetch('/api/notification_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_read'
        }).then(function() {
            loadNotifList();
            refreshBadges();
        });
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', doMarkAllRead);
    }
    if (mMarkAllBtn) {
        mMarkAllBtn.addEventListener('click', doMarkAllRead);
    }

    // --- Wishlist badge refresh ---
    function refreshWishlistBadge() {
        const wishBadge = document.getElementById('nav-wishlist-count');
        if (!wishBadge) return;
        fetch('/api/wishlist_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_my_wishlist'
            })
            .then(r => r.json())
            .then(function(res) {
                const count = (res.data || []).length;
                if (count > 0) {
                    wishBadge.textContent = count;
                    wishBadge.style.display = '';
                } else {
                    wishBadge.style.display = 'none';
                }
            }).catch(function() {});
    }

    // --- Auto-refresh mỗi 30 giây ---
    refreshBadges();
    setInterval(refreshBadges, 30000);

    // Expose ra global để main.js gọi được
    window._navRefreshBadges = refreshBadges;
    window._navRefreshWishlist = refreshWishlistBadge;
    window._loadNotifList = loadNotifList;
});
</script>