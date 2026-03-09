<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

$sql = "SELECT p.*, b.name as brand_name, c.name as cat_name 
        FROM products p 
        LEFT JOIN brands b ON p.brand_id = b.id 
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = $id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo "<div class='container' style='padding:50px; text-align:center;'><h2>Sản phẩm không tồn tại!</h2><a href='index.php'>Về trang chủ</a></div>";
    include 'includes/footer.php';
    exit();
}

$product     = $result->fetch_assoc();
$gallery     = !empty($product['gallery']) ? json_decode($product['gallery'], true) : [];
$specs       = !empty($product['specs']) ? json_decode($product['specs'], true) : [];
$colors      = !empty($product['colors']) ? explode(',', $product['colors']) : [];
$main_img    = strpos($product['image'], 'http') === 0 ? $product['image'] : "assets/img/" . $product['image'];
$price       = $product['price'];
$sale_price  = $product['sale_price'];
$is_sale     = ($sale_price > 0);
$final_price = $is_sale ? $sale_price : $price;
$percent     = $is_sale ? round((($price - $sale_price) / $price) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <title><?= htmlspecialchars($product['name']) ?> - MobileStore</title>
</head>

<body data-user-id="<?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0 ?>">
    <div class="container" style="margin-top: 20px;">
        <div style="font-size:14px; color:#666; margin-bottom:15px;">
            <a href="index.php" style="color:#666;">Trang chủ</a> /
            <a href="#" style="color:#666;"><?= $product['cat_name'] ?></a> /
            <span style="color:#000; font-weight:500;"><?= $product['name'] ?></span>
        </div>

        <div class="pd-wrapper">
            <div class="pd-header">
                <div class="pd-gallery-container">
                    <div class="pd-main-img-wrap">
                        <img src="<?= $main_img ?>" id="pd-main-img" class="pd-main-img" alt="<?= $product['name'] ?>">
                    </div>
                    <?php if (!empty($gallery)): ?>
                    <div class="pd-thumbs">
                        <img src="<?= $main_img ?>" class="pd-thumb-item active">
                        <?php foreach($gallery as $img_link): 
                             $thumb = strpos($img_link, 'http') === 0 ? $img_link : "assets/img/" . $img_link;
                        ?>
                        <img src="<?= $thumb ?>" class="pd-thumb-item">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="pd-info-container">
                    <h1 class="pd-title"><?= $product['name'] ?></h1>

                    <div class="pd-price-box">
                        <span class="pd-price-current"><?= number_format($final_price, 0, ',', '.') ?> ₫</span>
                        <?php if ($is_sale): ?>
                        <span class="pd-price-old"><?= number_format($price, 0, ',', '.') ?> ₫</span>
                        <span class="pd-label-sale">Giảm <?= $percent ?>%</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($colors)): ?>
                    <div class="pd-color-option">
                        <p style="font-weight:600; margin-bottom:8px;">Chọn màu sắc:</p>
                        <div>
                            <?php foreach($colors as $index => $color): ?>
                            <button class="btn-color <?= $index == 0 ? 'active' : '' ?>">
                                <?= trim($color) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="pd-promo-box">
                        <div class="pd-promo-header"><i class="fa fa-gift"></i> Ưu đãi đặc biệt</div>
                        <ul class="pd-promo-list">
                            <li><i class="fa-solid fa-circle-check"></i> Tặng gói bảo hành vàng 12 tháng.</li>
                            <li><i class="fa-solid fa-circle-check"></i> Giảm thêm 5% khi thanh toán qua QR Code.</li>
                            <li><i class="fa-solid fa-truck-fast"></i> Miễn phí vận chuyển toàn quốc.</li>
                        </ul>
                    </div>

                    <div class="pd-actions">
                        <button class="btn-buy-now js-buy-now" data-id="<?= $product['id'] ?>">
                            <strong>MUA NGAY</strong>
                            <span>(Giao tận nơi hoặc lấy tại cửa hàng)</span>
                        </button>
                        <button class="btn-add-cart-large js-add-to-cart" data-id="<?= $product['id'] ?>">
                            <i class="fa fa-cart-plus"></i>
                            <span>Thêm vào giỏ</span>
                        </button>
                    </div>
                </div>
            </div>

            <hr style="border:0; border-top:1px solid #eee; margin:30px 0;">

            <div style="display: grid; grid-template-columns: 65% 35%; gap:30px;">
                <div class="pd-description">
                    <h3
                        style="color:#d70018; margin-bottom:15px; border-bottom:2px solid #d70018; display:inline-block; padding-bottom:5px;">
                        Đặc điểm nổi bật</h3>
                    <div class="content-body" style="line-height:1.6; color:#444;">
                        <?= nl2br($product['description'] ? $product['description'] : "Đang cập nhật nội dung...") ?>
                    </div>
                </div>

                <div class="pd-specs">
                    <h3 style="margin-bottom:15px;">Thông số kỹ thuật</h3>
                    <table class="table" style="width:100%; border-collapse:collapse; font-size:14px;">
                        <?php if(!empty($specs)): ?>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:10px; color:#666;">Màn hình:</td>
                            <td style="padding:10px;"><?= $specs['screen'] ?? '...' ?></td>
                        </tr>
                        <tr style="border-bottom:1px solid #eee; background:#f9f9f9;">
                            <td style="padding:10px; color:#666;">CPU:</td>
                            <td style="padding:10px;"><?= $specs['cpu'] ?? '...' ?></td>
                        </tr>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:10px; color:#666;">RAM:</td>
                            <td style="padding:10px;"><?= $specs['ram'] ?? '...' ?></td>
                        </tr>
                        <tr style="border-bottom:1px solid #eee; background:#f9f9f9;">
                            <td style="padding:10px; color:#666;">Bộ nhớ:</td>
                            <td style="padding:10px;"><?= $specs['storage'] ?? '...' ?></td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td colspan="2">Chưa có thông số chi tiết</td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <?php
        $cat_id = $product['category_id'];
        $current_id = $product['id'];
        $sql_rel = "SELECT * FROM products WHERE category_id = $cat_id AND id != $current_id LIMIT 10";
        $res_rel = $conn->query($sql_rel);
        $related_products = ($res_rel) ? $res_rel->fetch_all(MYSQLI_ASSOC) : [];
        ?>

        <?php if (!empty($related_products)): ?>
        <div class="related-wrapper">
            <h3 class="related-title">Sản phẩm liên quan</h3>

            <div class="related-slider-container">
                <?php if(count($related_products) > 4): ?>
                <button class="slider-btn prev" id="btn-prev-rel"><i class="fa fa-angle-left"></i></button>
                <button class="slider-btn next" id="btn-next-rel"><i class="fa fa-angle-right"></i></button>
                <?php endif; ?>

                <div class="related-track" id="related-track">
                    <?php foreach ($related_products as $rel): 
                        $r_img = strpos($rel['image'], 'http') === 0 ? $rel['image'] : "assets/img/" . $rel['image'];
                        $r_price = $rel['sale_price'] > 0 ? $rel['sale_price'] : $rel['price'];
                    ?>
                    <div class="related-card">
                        <a href="product_detail.php?id=<?= $rel['id'] ?>" style="text-decoration:none;">
                            <img src="<?= $r_img ?>" alt="<?= $rel['name'] ?>">
                            <h3><?= $rel['name'] ?></h3>
                            <div class="price"><?= number_format($r_price, 0, ',', '.') ?> ₫</div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>


        <?php
    $sql_avg = "SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM product_reviews WHERE product_id = $id";
    $avg_res = $conn->query($sql_avg);
    $avg_data = ($avg_res) ? $avg_res->fetch_assoc() : ['avg_rating' => 0, 'total' => 0];
    
    $avg_score = round($avg_data['avg_rating'] ?? 0, 1); 
    $total_reviews = $avg_data['total'] ?? 0;

    $stats = [];
    for($i=1; $i<=5; $i++) {
        $sql_stat = "SELECT COUNT(*) as t FROM product_reviews WHERE product_id = $id AND rating = $i";
        $r = $conn->query($sql_stat)->fetch_assoc();
        $stats[$i] = $r['t'] ?? 0;
    }
    ?>

        <div class="container review-section">
            <h3
                style="margin-bottom:20px; text-transform:uppercase; border-bottom:1px solid #eee; padding-bottom:10px;">
                Đánh giá sản phẩm
            </h3>

            <div class="review-header-box">
                <div class="review-score-box">
                    <h1><?= $avg_score ?>/5</h1>
                    <div class="stars">
                        <?php 
                    // Logic vẽ ngôi sao vàng/xám
                    for($i=1; $i<=5; $i++) {
                        if ($i <= round($avg_score)) {
                            echo '<i class="fa-solid fa-star"></i>'; // Sao vàng
                        } else {
                            echo '<i class="fa-regular fa-star" style="color:#ccc"></i>'; // Sao xám
                        }
                    }
                    ?>
                    </div>
                    <p>dựa trên <b><?= $total_reviews ?></b> đánh giá</p>
                </div>

                <div class="review-progress-box">
                    <?php for($i=5; $i>=1; $i--): 
                    $count = $stats[$i];
                    // Tránh chia cho 0
                    $percent = ($total_reviews > 0) ? ($count / $total_reviews) * 100 : 0;
                ?>
                    <div class="progress-item">
                        <span style="width:35px; font-weight:bold;"><?= $i ?> <i class="fa-solid fa-star"
                                style="font-size:10px; color:#999"></i></span>
                        <div class="progress-bg">
                            <div class="progress-fill" style="width: <?= $percent ?>%"></div>
                        </div>
                        <span style="width:30px; text-align:right; color:#666;"><?= $count ?></span>
                    </div>
                    <?php endfor; ?>
                </div>

                <div class="review-action-box">
                    <p style="margin-bottom:10px; font-size:13px;">Bạn đã dùng sản phẩm này?</p>
                    <button class="btn-write-review js-btn-write-review">
                        <i class="fa-solid fa-pen-to-square"></i> Viết đánh giá
                    </button>
                </div>
            </div>

            <div class="review-filter">
                <button class="filter-btn active" data-star="all">Tất cả</button>
                <button class="filter-btn" data-star="5">5 Sao</button>
                <button class="filter-btn" data-star="4">4 Sao</button>
                <button class="filter-btn" data-star="3">3 Sao</button>
                <button class="filter-btn" data-star="image">Có hình ảnh</button>
            </div>

            <div id="review-list-container" class="review-list">
                <div style="text-align:center; padding:20px;">
                    <i class="fa fa-spinner fa-spin"></i> Đang tải đánh giá...
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div id="review-modal" class="modal">
        <div class="modal-content review-modal-size">
            <div class="modal-header">
                <h3>Đánh giá sản phẩm</h3>
                <span class="close-modal js-close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="form-product-review" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" value="<?= $id ?>">

                    <div class="rating-input">
                        <p>Bạn cảm thấy sản phẩm thế nào?</p>
                        <div class="star-rating">
                            <input type="radio" name="rating" value="5" id="5-stars" checked><label
                                for="5-stars">&#9733;</label>
                            <input type="radio" name="rating" value="4" id="4-stars"><label
                                for="4-stars">&#9733;</label>
                            <input type="radio" name="rating" value="3" id="3-stars"><label
                                for="3-stars">&#9733;</label>
                            <input type="radio" name="rating" value="2" id="2-stars"><label
                                for="2-stars">&#9733;</label>
                            <input type="radio" name="rating" value="1" id="1-stars"><label
                                for="1-stars">&#9733;</label>
                        </div>
                    </div>

                    <textarea name="comment" class="modal-input" rows="4"
                        placeholder="Hãy chia sẻ trải nghiệm của bạn về sản phẩm này (chất lượng, tính năng, v.v.)"
                        required></textarea>

                    <div class="image-upload-wrap">
                        <label for="review-images-input" class="upload-label">
                            <i class="fa fa-camera"></i> Thêm ảnh thực tế (Tối đa 3 ảnh)
                        </label>
                        <input type="file" id="review-images-input" name="images[]" multiple accept="image/*"
                            style="display:none">
                        <div id="preview-images"
                            style="margin-top:10px; display:flex; gap:10px; justify-content:center;"></div>
                    </div>

                    <button type="submit" class="btn-submit-review">GỬI ĐÁNH GIÁ</button>
                </form>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>

</html>