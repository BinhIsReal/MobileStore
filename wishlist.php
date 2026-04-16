<?php
session_start();
include 'config/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title><?= __('nav.wishlist', 'Danh sách Yêu thích') ?> - MobileStore</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php require_once 'includes/navbar.php'; ?>

    <div class="wishlist-wrapper">
        <div class="wishlist-header">
            <h2><i class="fa-solid fa-heart"></i> <?= __('nav.wishlist', 'Sản phẩm Yêu thích') ?></h2>
            <a href="index.php" class="btn-view-detail"><i class="fa fa-arrow-left"></i> <?= __('nav.continue_shopping', 'Tiếp tục mua sắm') ?></a>
        </div>

        <div id="wishlist-grid-container">
            <div style="text-align:center; padding:40px; color:#999;">
                <i class="fa fa-spinner fa-spin fa-2x"></i><br><?= __('loading', 'Đang tải...') ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    const fmt = new Intl.NumberFormat('vi-VN');

    function loadWishlist() {
        $.post('api/wishlist_api.php', {
            action: 'get_my_wishlist'
        }, function(res) {
            const container = $('#wishlist-grid-container');
            if (!res || !res.data || res.data.length === 0) {
                container.html(`
                    <div class="wishlist-empty">
                        <i class="fa-regular fa-heart"></i>
                        <p style="font-size:16px; margin-bottom:15px;"><?= __('wishlist.empty', 'Chưa có sản phẩm yêu thích nào') ?></p>
                        <a href="index.php" class="btn-view-detail"><?= __('wishlist.explore', 'Khám phá sản phẩm') ?></a>
                    </div>`);
                return;
            }

            let html = '<div class="wishlist-grid">';
            res.data.forEach(function(item) {
                const imgSrc = item.image && item.image.startsWith('http') ? item.image :
                    'assets/img/' + item.image;
                const dropBadge = item.price_drop ?
                    `<span class="wishlist-drop-badge">-${fmt.format(item.drop_amount)}đ</span>` : '';
                const alertBadge = item.price_drop ?
                    `<span class="alert-badge">🔥 <?= __('wishlist.discount', 'Giảm giá!') ?></span>` : '';
                const oldPrice = item.price_drop ?
                    `<span class="wishlist-old-price">${fmt.format(item.price_at_add)}đ</span>` : '';

                html += `
                <div class="wishlist-card">
                    ${alertBadge}
                    <a href="product_detail.php?id=${item.product_id}">
                        <img src="${imgSrc}" alt="${item.name}" onerror="this.src='assets/img/placeholder.jpg'">
                    </a>
                    <div class="wishlist-card-body">
                        <a href="product_detail.php?id=${item.product_id}">${item.name}</a>
                        <div>
                            <span class="wishlist-price">${fmt.format(item.current_price)}đ</span>
                            ${oldPrice} ${dropBadge}
                        </div>
                        <div class="wishlist-actions">
                        <button class="btn-add-wish-cart js-add-to-cart" data-id="${item.product_id}" data-type="simple">
                                <i class="fa fa-cart-plus"></i> <?= __('wishlist.add_to_cart', 'Thêm giỏ') ?>
                            </button>
                            <button class="btn-remove-wish" onclick="removeFromWishlist(${item.product_id})">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>`;
            });
            html += '</div>';
            container.html(html);
        });
    }

    function removeFromWishlist(productId) {
        $.post('api/wishlist_api.php', {
            action: 'remove',
            product_id: productId
        }, function(res) {
            if (res && res.status === 'success') loadWishlist();
        });
    }

    loadWishlist();
    </script>
    <?php require_once 'includes/footer.php'; ?>
</body>

</html>