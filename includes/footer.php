<div id="toast-container"></div>

<div id="custom-confirm" class="confirm-overlay">
    <div class="confirm-box">
        <i class="fa-solid fa-circle-question" style="font-size: 50px; color: #d70018; margin-bottom: 15px;"></i>
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
                    <li><a href="<?= BASE_URL ?>/pages/about.php">Giới thiệu về MobileStore</a></li>
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
            <p>© 2025 CÔNG TY CỔ PHẦN MOBILE STORE VIỆT NAM. MST: 0101234567.</p>
            <p>Địa chỉ: Số 89 Đường Tam Trinh, Phường Vĩnh Tuy, Quận Hai Bà Trưng, Thành Phố Hà Nội, Việt Nam.</p>
            <p>Điện thoại: 024.7300.xxxx - Email: cskh@mobilestore.com.vn</p>
            <div style="margin-top: 10px;">
                <img src="https://images.dmca.com/Badges/dmca_protected_sml_120n.png" alt="DMCA"
                    style="height: 25px; display:inline-block; opacity:0.8;">
            </div>
        </div>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
const BASE_URL = "<?= BASE_URL ?>";
</script>

<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?php echo time(); ?>"></script>
<?php 
// =========================================================
// 1. HIỂN THỊ THÔNG BÁO CHÀO MỪNG SAU KHI ĐĂNG NHẬP
// =========================================================
if (isset($_SESSION['login_success_msg'])): 
?>
<script>
$(document).ready(function() {
    if (typeof showToast === "function") {
        showToast({
            title: "Thành công!",
            message: "<?= $_SESSION['login_success_msg'] ?>",
            type: "success"
        });
    }
});
</script>
<?php 
    // Xóa biến session để thông báo không hiện lại khi f5 trang
    unset($_SESSION['login_success_msg']); 
endif; 
?>


<?php 
// =========================================================
// 2. HIỂN THỊ POPUP KHI CÓ VOUCHER MỚI
// =========================================================
// Kiểm tra nếu User đã đăng nhập thì mới check xem có voucher mới không
if (isset($_SESSION['user_id'])): 
    $uid = $_SESSION['user_id'];
    $check_new = $conn->query("SELECT COUNT(*) as new_count FROM user_vouchers WHERE user_id = $uid AND is_new = 1");
    $new_vouchers = $check_new->fetch_assoc()['new_count'] ?? 0;
    
    if ($new_vouchers > 0):
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
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
        allowOutsideClick: false // Bắt buộc phải bấm chọn
    }).then((result) => {
        if (result.isConfirmed) {
            // Chuyển hướng sang trang Kho Voucher
            window.location.href = 'my_vouchers.php';
        }
    });
});
</script>
<?php 
    endif;
endif; 
?>