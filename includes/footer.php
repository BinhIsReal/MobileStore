    <div id="toast-container"></div>

<div id="custom-confirm" class="confirm-overlay">
    <div class="confirm-box">
        <i class="fa-solid fa-circle-question confirm-icon-danger"></i>
        <h3 id="confirm-title">Xác nhận</h3>
        <p id="confirm-msg">Bạn có chắc chắn muốn thực hiện?</p>
        <div class="confirm-actions">
            <button class="btn-no" onclick="closeConfirm()">Hủy bỏ</button>
            <button class="btn-yes" id="btn-confirm-yes">Đồng ý</button>
        </div>
    </div>
</div>

<footer>
    <div class="container">
        <div class="footer-top">
            <div class="ft-col">
                <h4>Hỗ trợ - Dịch vụ</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>/pages/policy_installment.php">Chính sách mua hàng trả góp</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/policy_warranty.php">Chính sách bảo hành đổi mới</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/policy_shipping.php">Chính sách vận chuyển</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/policy_complaint.php">Chính sách giải quyết khiếu nại</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/policy_privacy.php">Chính sách bảo mật thông tin</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/guide_payment.php">Hướng dẫn thanh toán</a></li>
                </ul>
            </div>
            <div class="ft-col">
                <h4>Thông tin liên hệ</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>/pages/about.php">Giới thiệu về TechMate</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/store_system.php"> Hệ thống cửa hàng (124 chi nhánh)</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/warranty_centers.php"> Trung tâm bảo hành chính hãng</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/careers.php">Tuyển dụng nhân tài</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/order_tracking.php">Tra cứu đơn hàng</a></li>
                </ul>
            </div>
            <div class="ft-col">
                <h4>Thanh toán miễn phí</h4>
                <div class="pay-grid">
                    <div class="pay-icon" title="Visa"><i class="fa-brands fa-cc-visa"></i></div>
                    <div class="pay-icon" title="MasterCard"><i class="fa-brands fa-cc-mastercard"></i></div>
                    <div class="pay-icon" title="JCB"><i class="fa-brands fa-cc-jcb"></i></div>
                    <div class="pay-icon" title="Apple Pay"><i class="fa-brands fa-apple"></i></div>
                    <div class="pay-icon" title="Tiền mặt"><i class="fa-solid fa-money-bill-wave"></i></div>
                    <div class="pay-icon" title="QR Code"><i class="fa-solid fa-qrcode"></i></div>
                </div>
                <h4>Vận chuyển</h4>
                <div class="pay-grid">
                    <div class="pay-icon"><i class="fa-solid fa-truck-fast"></i></div>
                    <div class="pay-icon"><i class="fa-solid fa-box"></i></div>
                </div>
            </div>

            <div class="ft-col">
                <h4>Tư vấn mua hàng</h4>
                <div class="hotline-box">
                    <span class="hotline-num">1900.1011</span>
                    <span class="hotline-desc">(Nhánh 1: 8h30 - 21h30)</span>
                </div>

                <h4>Hỗ trợ kỹ thuật</h4>
                <div class="hotline-box">
                    <span class="hotline-num">1900.2205</span>
                    <span class="hotline-desc">(Nhánh 2: 8h30 - 21h30)</span>
                </div>

                <h4>Kết nối với chúng tôi</h4>
                <div class="social-list">
                    <a href="#" class="social-item"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" class="social-item"><i class="fa-brands fa-youtube"></i></a>
                    <a href="#" class="social-item"><i class="fa-brands fa-tiktok"></i></a>
                    <a href="#" class="social-item"><i class="fa-brands fa-instagram"></i></a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2026 CÔNG TY CỔ PHẦN TECHMATE VIỆT NAM. MST: 10112004.</p>
            <p>Địa chỉ: Đường Z 115, Quyết Thắng, Thái Nguyên 250000, Việt Nam.</p>
            <p>Điện thoại: 024.7300.xxxx - Email: cskh@techmate.com.vn</p>
            <div class="footer-dmca">
                <img src="https://images.dmca.com/Badges/dmca_protected_sml_120n.png"
                     alt="DMCA Protected - TechMate"
                     width="120" height="38"
                     loading="lazy">
            </div>
        </div>
    </div>
</footer>

<!-- ==============================================
     MOBILE APP-LIKE BOTTOM NAV AND SHEETS
     ============================================== -->
<div class="mobile-bottom-nav">
    <a href="<?= BASE_URL ?>/index.php" class="m-nav-item <?= (basename($_SERVER['PHP_SELF']) === 'index.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-house"></i>
        <span>Trang chủ</span>
    </a>
    <div class="m-nav-item" id="m-btn-categories">
        <i class="fa-solid fa-bars"></i>
        <span>Danh mục</span>
    </div>
    <a href="<?= BASE_URL ?>/cart.php" class="m-nav-item <?= (basename($_SERVER['PHP_SELF']) === 'cart.php') ? 'active' : '' ?>" id="m-btn-cart">
        <i class="fa-solid fa-cart-shopping"></i>
        <?php 
            $m_cart_qty = 0;
            if(isset($conn) && isset($_SESSION['user_id'])) {
                $uid = $_SESSION['user_id'];
                $res = $conn->query("SELECT SUM(quantity) as t FROM cart WHERE user_id=$uid");
                if($res && $r = $res->fetch_assoc()) $m_cart_qty = $r['t'] ?? 0;
            } else {
                $m_cart_qty = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
            }
            if ($m_cart_qty > 0): 
        ?>
            <span class="m-badge"><?= $m_cart_qty ?></span>
        <?php endif; ?>
        <span>Giỏ hàng</span>
    </a>
    <div class="m-nav-item" id="m-btn-notif">
        <i class="fa-solid fa-bell"></i>
        <span id="m-nav-notif-badge" class="m-badge" style="display:none;">0</span>
        <span>Thông báo</span>
    </div>
    <div class="m-nav-item <?= (in_array(basename($_SERVER['PHP_SELF']), ['profile.php', 'order_history.php', 'login.php'])) ? 'active' : '' ?>" id="m-btn-user">
        <i class="fa-solid fa-circle-user"></i>
        <span>Tài khoản</span>
    </div>
</div>

<!-- COMMON BACKDROP FOR ALL DRAWERS/SHEETS -->
<div class="m-backdrop" id="m-backdrop"></div>

<!-- USER BOTTOM SHEET -->
<div class="m-bottom-sheet" id="m-user-sheet">
    <div class="m-sheet-header">
        <b>Tài khoản</b>
        <button class="m-close-sheet"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="m-sheet-content">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= BASE_URL ?>/profile.php" class="m-sheet-item"><i class="fa-solid fa-file-invoice"></i> Thông tin cá nhân</a>
            <a href="<?= BASE_URL ?>/order_history.php" class="m-sheet-item"><i class="fa-solid fa-box"></i> Đơn hàng</a>
            <a href="<?= BASE_URL ?>/my_vouchers.php" class="m-sheet-item"><i class="fa-solid fa-ticket-simple"></i> Kho Voucher</a>
            <a href="<?= BASE_URL ?>/wishlist.php" class="m-sheet-item"><i class="fa-solid fa-heart"></i> Yêu thích</a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="m-sheet-item"><i class="fa-solid fa-gauge"></i> Quản trị</a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/api/auth_api.php?logout=1" class="m-sheet-item" style="color:red;"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/login.php" class="m-sheet-item"><i class="fa-solid fa-right-to-bracket"></i> Đăng nhập</a>
            <a href="<?= BASE_URL ?>/register.php" class="m-sheet-item"><i class="fa-solid fa-user-plus"></i> Đăng ký ngay</a>
        <?php endif; ?>
    </div>
</div>

<!-- NOTIFICATION BOTTOM SHEET -->
<div class="m-bottom-sheet" id="m-notif-sheet">
    <div class="m-sheet-header">
        <b>Thông báo</b>
        <div class="m-sheet-header-top">
            <button id="m-btn-mark-all-read" style="background:none;border:none;color:var(--primary);font-size:13px;margin-right:10px;">Đánh dấu đã đọc</button>
            <button class="m-close-sheet"><i class="fa-solid fa-xmark"></i></button>
        </div>
    </div>
    <div class="m-sheet-content" id="m-notif-list" style="max-height: 65vh; overflow-y: auto;">
        <?php if (!isset($_SESSION['user_id'])): ?>
        <div style="padding: 30px; text-align: center; color: #888;">
            Vui lòng đăng nhập để xem thông báo.
        </div>
        <?php endif; ?>
    </div>
</div>
<!-- SweetAlert2 (defer) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
<script>
const BASE_URL = "<?= BASE_URL ?>";
</script>

<!-- Main JS (defer) -->
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?php echo filemtime(dirname(__DIR__).'/assets/js/main.js'); ?>" defer></script>
<?php 
// =========================================================
// 1. HIỂN THỊ THÔNG BÁO CHÀO MỪNG SAU KHI ĐĂNG NHẬP
// =========================================================
if (isset($_SESSION['login_success_msg'])): 
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    Swal.fire({
        title: 'Đăng nhập thành công!',
        html: '<?= $_SESSION['login_success_msg'] ?>',
        icon: 'success',
        timer: 3000,
        showConfirmButton: false
    });
});
</script>
<?php 
    // Xóa biến session để thông báo không hiện lại khi f5 trang
    unset($_SESSION['login_success_msg']); 
endif; 

// =========================================================
// 1.5. HIỂN THỊ THÔNG BÁO ĐĂNG XUẤT
// =========================================================
if (isset($_SESSION['logout_success_msg'])): 
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    Swal.fire({
        title: 'Đã đăng xuất!',
        text: '<?= $_SESSION['logout_success_msg'] ?>',
        icon: 'info',
        timer: 3000,
        showConfirmButton: false
    });
});
</script>
<?php 
    unset($_SESSION['logout_success_msg']); 
endif; 
?>


<?php 
// =========================================================
// 2. HIỂN THỊ POPUP KHI CÓ VOUCHER MỚI
// =========================================================
if (isset($_SESSION['user_id'])): 
    $uid = $_SESSION['user_id'];
    $check_new = $conn->query("SELECT COUNT(*) as new_count FROM user_vouchers WHERE user_id = $uid AND is_new = 1");
    $new_vouchers = $check_new->fetch_assoc()['new_count'] ?? 0;
    
    if ($new_vouchers > 0):
        // ✔ Reset is_new = 0 NGAY khi render — tránh hiện lại khi F5 hoặc chuyển trang
        $conn->query("UPDATE user_vouchers SET is_new = 0 WHERE user_id = $uid AND is_new = 1");
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Guard: tránh hiện 2 lần trong cùng 1 session nho trình duyệt
    var guardKey = 'voucher_popup_shown_<?= $uid ?>_<?= date('YmdH') ?>';
    if (localStorage.getItem(guardKey)) return;
    localStorage.setItem(guardKey, '1');
    // Xóa key sau 1 giờ
    setTimeout(function() { localStorage.removeItem(guardKey); }, 3600000);

    Swal.fire({
        title: '🎉 Quà tặng bất ngờ!',
        html: 'Bạn vừa nhận được <b><?= $new_vouchers ?> mã giảm giá mới</b> từ cửa hàng.<br>Hãy kiểm tra ngay nhé!',
        icon: 'info',
        iconHtml: '<i class="fa-solid fa-gift"></i>',
        showCancelButton: true,
        confirmButtonText: 'Xem Kho Voucher',
        cancelButtonText: 'Để sau',
        confirmButtonColor: '#00487a',
        cancelButtonColor: '#6c757d',
        allowOutsideClick: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'my_vouchers.php';
        }
        // isConfirmed=false / isDismissed: không làm gì, popup đã được reset phía server
    });
});
</script>
<?php 
    endif;
endif; 
?>