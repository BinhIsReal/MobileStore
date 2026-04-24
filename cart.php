<?php
session_start();
include 'config/db.php';
include_once 'includes/flash_sale_helper.php';
include 'includes/header.php';
$user_id = $_SESSION['user_id'] ?? 0;
$cart_data = [];

// Khởi tạo mảng thông tin user (rỗng nếu là khách vãng lai)
$current_user = [
    'username' => '',
    'phone' => '',
    'address' => ''
];

if ($user_id > 0) {
    // 1. LẤY THÔNG TIN USER ĐỂ TỰ ĐỘNG ĐIỀN VÀO FORM CHECKOUT
    $user_sql = "SELECT username, phone, address FROM users WHERE id = $user_id";
    $user_result = $conn->query($user_sql);
    if ($user_result && $user_result->num_rows > 0) {
        $current_user = $user_result->fetch_assoc();
    }

    // 2. LẤY DỮ LIỆU GIỎ HÀNG TỪ DATABASE
    $sql = "SELECT p.id as p_id, p.name as p_name, p.image, p.price as base_price, p.sale_price, c.quantity, c.variation_id, 
                   COALESCE(pv.price, p.price) as final_price, pv.attributes as var_attrs
            FROM cart c
            JOIN products p ON c.product_id = p.id
            LEFT JOIN product_variations pv ON c.variation_id = pv.id
            WHERE c.user_id = $user_id";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cart_data[] = $row;
        }
    }
} else {
    // LẤY DỮ LIỆU GIỎ HÀNG CỦA KHÁCH VÃNG LAI (TỪ SESSION)
    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $key => $qty) {
            $parts = explode('_', $key);
            $pid = (int)$parts[0];
            $vid = isset($parts[1]) ? (int)$parts[1] : 0;
            
            $sql = "SELECT p.id as p_id, p.name as p_name, p.image, p.price as base_price, p.sale_price, COALESCE(pv.price, p.price) as final_price, pv.attributes as var_attrs
                    FROM products p
                    LEFT JOIN product_variations pv ON pv.id = $vid
                    WHERE p.id = $pid";
            $res = $conn->query($sql);
            if ($res && $row = $res->fetch_assoc()) {
                $row['quantity'] = $qty;
                $row['variation_id'] = $vid;
                $cart_data[] = $row;
            }
        }
    }
}

// Batch lấy giá Flash Sale cho toàn bộ product trong giỏ (1 query duy nhất)
$cart_product_ids   = array_column($cart_data, 'p_id');
$flash_price_map    = get_flash_prices_bulk($conn, $cart_product_ids);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Giỏ hàng của bạn</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/mobile.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body data-user-id="<?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0 ?>">

    <div class="container" style="margin-top: 30px;">
        <?php if (empty($cart_data)): ?>
        <div class="empty-cart-state">
            <i class="fa-solid fa-cart-shopping" style="font-size: 80px; color: #787878ff; margin-bottom: 20px;"></i>
            <h3 style="color: #555;">Giỏ hàng của bạn đang trống</h3>
            <p style="color: #888; margin-bottom: 20px;">Hãy chọn thêm sản phẩm để mua sắm nhé</p>
            <a href="index.php" style="color: #00487a; font-weight: bold;">
                ← Quay lại trang chủ</a>
        </div>
        <?php else: ?>
        <div class="cart-wrapper">
            <div class="cart-list">
                <div class="cart-header" id="cart-header">
                    <div class="select-all-wrapper">
                        <input type="checkbox" id="check-all" class="header-check" style="width: 18px; height: 18px; cursor: pointer;">
                        <h3 class="select-all" style="margin: 0; font-size: 16px;">Chọn tất cả (<?= count($cart_data) ?> sản phẩm)</h3>
                    </div>

                    <div class="delete-actions">
                        <button id="btn-delete-selected" class="btn-delete-selected" style="display:none;">
                            <i class="fa fa-trash-can"></i> Xóa đã chọn
                        </button>
                        <button id="btn-delete-all" class="btn-delete-all">
                            <i class="fa fa-trash"></i> Xóa tất cả
                        </button>
                    </div>
                </div>

                <?php foreach ($cart_data as $item):
                    $item_id   = $item['p_id'];
                    $item_name = $item['p_name'];

                    if (isset($flash_price_map[$item_id])) {
                        $calc_price = $flash_price_map[$item_id]['flash_price'];
                        $show_original = $flash_price_map[$item_id]['original_price'];
                        $is_sale    = true;
                        $is_flash   = true;
                    } elseif (isset($item['sale_price']) && $item['sale_price'] > 0 && empty($item['variation_id'])) {
                        $calc_price    = (float)$item['sale_price'];
                        $show_original = (float)$item['base_price'];
                        $is_sale       = true;
                        $is_flash      = false;
                    } else {
                        $calc_price    = (float)$item['final_price'];
                        $show_original = (float)$item['base_price'];
                        $is_sale       = false;
                        $is_flash      = false;
                    }

                    $display_name = htmlspecialchars($item_name);
                    $variant_display = "";
                    if (!empty($item['var_attrs'])) {
                        $attrs = json_decode($item['var_attrs'], true);
                        $attr_parts = [];
                        if ($attrs) {
                            foreach($attrs as $k => $v) $attr_parts[] = "$v"; 
                            $variant_display = implode(', ', $attr_parts);
                            // Dành cho desktop
                            $display_name .= " (" . implode(', ', $attr_parts) . ")";
                        }
                    }
                ?>
                <div class="cart-item" id="item-<?= $item_id ?>">
                    <input type="checkbox" class="pay-check" value="<?= $item_id ?>" data-price="<?= $calc_price ?>"
                        data-qty="<?= $item['quantity'] ?>" onchange="calcTotal()">

                    <?php 
                        $imgCart = (strpos($item['image'], 'http') === 0) ? $item['image'] : "assets/img/" . $item['image'];
                    ?>

                    <a href="product_detail.php?id=<?= $item_id ?>" class="cart-item-img-link" style="text-decoration: none;">
                        <img src="<?= $imgCart ?>" alt="<?= htmlspecialchars($item_name) ?>">
                    </a>

                    <div class="cart-item-info">
                        <div class="item-name-group">
                            <a href="product_detail.php?id=<?= $item_id ?>" class="cart-item-title-link" style="text-decoration: none; color: inherit;">
                                <h4 class="cart-item-title"><?= $display_name ?></h4>
                            </a>
                            
                            <div class="cart-item-price">
                                <?php if ($is_sale): ?>
                                    <?php if ($is_flash): ?>
                                        <span class="flash-badge">FLASH SALE</span>
                                    <?php else: ?>
                                        <span class="flash-badge" style="background:#ddd; color:#333;">KHUYẾN MÃI</span>
                                    <?php endif; ?>
                                    <br class="br-mobile">
                                    <span class="current-price"><?= number_format($calc_price, 0, ',', '.') ?> ₫</span>
                                    <del class="old-price"><?= number_format($show_original, 0, ',', '.') ?> ₫</del>
                                <?php else: ?>
                                    <span class="current-price"><?= number_format($calc_price, 0, ',', '.') ?> ₫</span>
                                <?php endif; ?>
                            </div>

                            <div class="cart-variant-box" <?php if(empty($variant_display)){ echo 'style="display:none;"'; } ?>>
                                <span class="variant-text"><?= $variant_display ?></span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                        </div>
                        
                        <div class="qty-control">
                            <button class="qty-btn" data-id="<?= $item_id ?>" data-delta="-1">-</button>
                            <span id="qty-<?= $item_id ?>"><?= $item['quantity'] ?></span>
                            <button class="qty-btn" data-id="<?= $item_id ?>" data-delta="1">+</button>
                        </div>
                         <button class="btn-remove-item" data-id="<?= $item_id ?>">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                    </div>

                   
                </div>
                <?php endforeach; ?>

            </div>

            <div class="checkout-bar">
                <div class="checkout-info">
                    <span>Tổng thanh toán (<b id="selected-count">0</b> sản phẩm):</span>
                    <span class="total-price" id="total-money">0 ₫</span>
                </div>
                <button id="btn-checkout-init" class="btn-checkout-trigger">
                    MUA HÀNG
                </button>
            </div>

            <div id="checkout-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fa-solid fa-file-invoice-dollar"></i> Xác Nhận Đặt Hàng</h2>
                        <span class="close-modal" onclick="$('#checkout-modal').fadeOut()">&times;</span>
                    </div>

                    <div class="modal-body">
                        <div class="form-group">
                            <label>Họ và tên <span style="color:red">*</span></label>
                            <input type="text" id="c-name" class="modal-input"
                                value="<?= htmlspecialchars($current_user['username'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Số điện thoại <span style="color:red">*</span></label>
                            <input type="text" id="c-phone" class="modal-input"
                                value="<?= htmlspecialchars($current_user['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Địa chỉ <span style="color:red">*</span></label>
                            <textarea id="c-address" class="modal-input" rows="2"
                                placeholder="Số nhà, đường..."><?= htmlspecialchars($current_user['address'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Mã giảm giá</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" id="c-coupon" class="modal-input" placeholder="Chọn mã voucher"
                                    readonly style="flex: 1; background-color: #f8f9fa;">
                                <input type="hidden" id="c-voucher-id" value="">

                                <button type="button" class="btn-primary" onclick="openVoucherList()">
                                    <p class="active-code" style="transform: translate(0px, -5px);">Chọn
                                        Mã</p>
                                </button>
                                <button type="button" id="btn-clear-voucher" class="btn-cancel"
                                    onclick="clearVoucher()">X</button>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 15px;">
                            <label style="font-weight:600; display:block; margin-bottom:8px;">Hình thức thanh
                                toán:</label>
                            <div style="display:flex; gap:15px; flex-wrap: wrap;">
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="cod" class="payment-radio" checked>
                                    <div class="payment-content">
                                        <b>Thanh toán khi nhận hàng</b><br>
                                        <span class="payment-sub">COD</span>
                                    </div>
                                </label>
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="banking" class="payment-radio">
                                    <div class="payment-content">
                                        <b>Chuyển khoản ngân hàng</b><br>
                                        <span class="payment-sub">Quét mã QR</span>
                                    </div>
                                </label>
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="vnpay" class="payment-radio">
                                    <div class="payment-content">
                                        <b>Ví điện tử VNPay</b><br>
                                        <span class="payment-sub">Thanh toán qua VNPay</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="modal-summary"
                            style="margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span>Tạm tính:</span>
                                <span id="modal-subtotal" style="font-weight: bold;">0 ₫</span>
                            </div>

                            <div id="voucher-discount-info"
                                style="display: none; justify-content: space-between; margin-bottom: 5px; color: #28a745;">
                                <span>Giảm giá (<span id="discount-label"></span>):</span>
                                <span id="modal-discount-amount" style="font-weight: bold;">-0 ₫</span>
                            </div>

                            <div
                                style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 18px;">
                                <span style="font-weight: bold;">Tổng thanh toán:</span>
                                <del id="modal-old-total"
                                    style="color: #999; font-size: 14px; margin-right: 10px; display: none;">0 ₫</del>
                                <h3 id="modal-total-money" style="color:#d70018; margin: 0;">0 ₫</h3>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn-cancel" onclick="$('#checkout-modal').fadeOut()">Hủy</button>
                        <button id="btn-confirm-order" class="btn-confirm">XÁC NHẬN THANH TOÁN</button>
                    </div>
                </div>
            </div>

            <div id="voucher-list-modal" class="modal" style="z-index: 10000 !important; background: rgba(0,0,0,0.7);">
                <div class="modal-content" style="max-width: 400px; padding: 20px;">
                    <div class="modal-header"
                        style="margin-top:-20px;margin-left:-20px;margin-right:-20px;border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">
                        <h3 style="margin: 0;">Chọn Mã Giảm Giá</h3>
                        <span class="close-modal" onclick="closeVoucherList()">&times;</span>
                    </div>

                    <div class="modal-body" id="voucher-list-container" style="max-height: 400px; overflow-y: auto;">
                        <div style="text-align: center; color: #888; padding: 20px;">
                            <i class="fa fa-spinner fa-spin" style="font-size: 24px;"></i> Đang tải...
                        </div>
                    </div>
                </div>
            </div>

            <div id="banking-modal" class="modal" style="display:none; z-index: 99999;">
                <div class="modal-content"
                    style="width: 400px; text-align: center; border-radius: 12px; overflow: hidden;">
                    <div class="modal-header"
                        style="justify-content: center; background: #00487a; color: white; padding: 15px;">
                        <h3 style="margin:0; font-size: 18px;">THANH TOÁN QR</h3>
                    </div>
                    <div class="modal-body" style="padding: 20px;">
                        <p style="margin-bottom: 10px; color: #555;">Quét mã VietQR để thanh toán nhanh:</p>

                        <div
                            style="position: relative; min-height: 350px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 8px; border: 1px solid #eee;">
                            <div id="qr-loader" style="position: absolute;">
                                <div class="spinner-border text-primary" role="status"
                                    style="width: 3rem; height: 3rem; border: 4px solid #ddd; border-top: 4px solid #00487a; border-radius: 50%; animation: spin 1s linear infinite;">
                                </div>
                                <p style="margin-top: 10px; font-size: 12px;">Đang tạo mã QR...</p>
                            </div>
                            <img id="qr-img" src=""
                                style="width: 100%; max-width: 350px; display: none; border-radius: 8px;" alt="QR Code">
                        </div>

                        <div
                            style="margin-top: 15px; font-size: 13px; background: #eef5ff; padding: 12px; border-radius: 6px; text-align: left;">
                            <p style="margin: 5px 0;">💰 Số tiền: <b id="qr-amount"
                                    style="color: #d70018; font-size: 16px;">...</b></p>
                            <p style="margin: 5px 0;">📝 Nội dung: <b id="qr-content" style="color: #00487a;">...</b>
                            </p>
                        </div>
                    </div>
                    <div class="modal-footer" style="padding: 15px; justify-content: center; background: #fff;">
                        <button id="btn-finish-banking" class="btn-confirm"
                            style="width: 100%; background: #28a745; color: white; padding: 12px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">
                            <i class="fa fa-check-circle"></i> TÔI ĐÃ CHUYỂN KHOẢN
                        </button>
                    </div>
                </div>
            </div>

        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        const urlParams = new URLSearchParams(window.location.search);
        const vnpayStatus = urlParams.get('vnpay_status');
        if (vnpayStatus === 'invalid_signature') {
            Swal.fire({
                icon: 'warning',
                title: 'Lỗi xác thực thanh toán',
                text: 'Chữ ký giao dịch VNPay không hợp lệ. Vui lòng thử lại hoặc liên hệ hỗ trợ.',
                confirmButtonColor: '#ff9800'
            });
            const cleanUrl = window.location.pathname;
            window.history.replaceState({}, '', cleanUrl);
        }
    });
    </script>

    <?php require_once "includes/footer.php"; ?>
</body>

</html>