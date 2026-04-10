<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

// Xử lý ID từ tham số
$ids_param = $_GET['ids'] ?? '';
$ids_array = array_filter(explode(',', $ids_param), function($id) {
    return is_numeric($id) && $id > 0;
});

// Bắt lỗi không có sản phẩm
if (empty($ids_array)) {
    echo "<div class='container' style='padding:60px 20px; text-align:center; min-height:50vh;'>
            <img src='https://cdn-icons-png.flaticon.com/512/6134/6134065.png' style='width:100px; opacity:0.5; margin-bottom:20px;'>
            <h3>Chưa có sản phẩm nào để so sánh.</h3>
            <p style='color:#666; margin-bottom:20px;'>Vui lòng chọn sản phẩm và thử lại.</p>
            <a href='index.php' class='btn-buy-now' style='display:inline-block; max-width:200px;'>Về trang chủ</a>
          </div>";
    include 'includes/footer.php';
    exit();
}

// Giới hạn số lượng (Desktop: 3, Mobile CSS tự scroll)
if (count($ids_array) > 3) {
    // Có thể show toast thông báo cắt item sau.
    $ids_array = array_slice($ids_array, 0, 3);
}

$id_list = implode(',', $ids_array);

// Lấy danh sách sản phẩm
$sql = "SELECT p.*, b.name as brand_name FROM products p LEFT JOIN brands b ON b.id = p.brand_id WHERE p.id IN ($id_list)";
$res = $conn->query($sql);

$products = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['specs_data'] = !empty($row['specs']) ? json_decode($row['specs'], true) : [];
        // Lấy đường dẫn ảnh
        $row['final_image'] = strpos($row['image'], 'http') === 0 ? $row['image'] : "assets/img/" . $row['image'];
        // Lấy giá
        $row['final_price'] = $row['sale_price'] > 0 ? $row['sale_price'] : $row['price'];
        
        $products[$row['id']] = $row;
    }
}

// Sắp xếp lại theo đúng trình tự trên URL
$ordered_products = [];
foreach ($ids_array as $id) {
    if (isset($products[$id])) {
        $ordered_products[] = $products[$id];
    }
}

// Cập nhật lại mảng ID hợp lệ
$valid_ids = array_column($ordered_products, 'id');
$current_url_ids = implode(',', $valid_ids);

if (empty($ordered_products)) {
    echo "<div class='container' style='padding:60px 20px; text-align:center; min-height:50vh;'>
            <h3>Sản phẩm không tồn tại hoặc đã bị xóa.</h3>
            <a href='index.php' class='btn-buy-now' style='display:inline-block; max-width:200px; margin-top:20px;'>Về trang chủ</a>
          </div>";
    include 'includes/footer.php';
    exit();
}

// Tạo tiêu đề trang SEO dựa trên các sản phẩm so sánh
$titles = array_column($ordered_products, 'name');
$page_title = "So sánh " . implode(' và ', $titles);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <title><?= htmlspecialchars($page_title) ?> - MobileStore</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/compare.css?v=<?= time() ?>">
</head>
<body data-user-id="<?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0 ?>">

    <div class="container compare-wrapper">
        <!-- Breadcrumb -->
        <div class="compare-breadcrumb">
            <a href="index.php"><i class="fa fa-home"></i></a>
            <span>></span>
            <a href="#">So sánh sản phẩm</a>
            <span>></span>
            <span class="active-badge"><?= htmlspecialchars($page_title) ?></span>
        </div>

        <div class="compare-table-container">
            <table class="compare-table">
                <!-- HÀNG HEADER SẢN PHẨM -->
                <thead>
                    <tr>
                        <!-- Cột 1: Hiển thị tên các SP được chọn để người dùng dễ nhìn bên góc -->
                        <th class="compare-title-cell">
                            <h3>So sánh sản phẩm</h3>
                            <?php foreach ($ordered_products as $idx => $p): ?>
                                <p style="font-weight:bold; font-size:16px; margin:10px 0;">
                                    <?= $p['name'] ?>
                                </p>
                                <?= ($idx < count($ordered_products)-1) ? '<p style="font-size:12px; color:#999; margin:5px 0;">&</p>' : '' ?>
                            <?php endforeach; ?>
                        </th>

                        <!-- Cột sản phẩm -->
                        <?php foreach ($ordered_products as $p): ?>
                            <th class="compare-product-cell">
                                <button class="btn-remove-compare" data-id="<?= $p['id'] ?>" title="Xóa khỏi danh sách">
                                    <i class="fa fa-minus-circle"></i>
                                </button>
                                <img src="<?= $p['final_image'] ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                                <div class="pd-name"><?= $p['name'] ?></div>
                                <div class="pd-price-row">
                                    <span class="pd-current-money"><?= number_format($p['final_price'], 0, ',', '.') ?> ₫</span>
                                    <?php if ($p['sale_price'] > 0): ?>
                                        <span class="pd-old-money"><?= number_format($p['price'], 0, ',', '.') ?> ₫</span>
                                    <?php endif; ?>
                                </div>
                                <p class="pd-vat">Giá đã bao gồm 10% VAT</p>
                            </th>
                        <?php endforeach; ?>

                        <!-- Cột thêm sản phẩm (chỉ hiện nếu số lg < 3) -->
                        <?php if (count($ordered_products) < 3): ?>
                            <th class="compare-add-cell">
                                <i class="fa fa-plus-circle" style="font-size:30px; color:#e0e0e0; margin-bottom:15px; display:block;"></i>
                                <p style="font-weight:bold; color:#444; margin-bottom:15px;">Bạn muốn so sánh thêm sản phẩm?</p>
                                <div class="compare-search-wrap">
                                    <input type="text" id="compare-search-input" placeholder="Tìm kiếm sản phẩm" autocomplete="off">
                                    <!-- Search Suggestions Box -->
                                    <div id="compare-suggestions" class="compare-suggestions-box"></div>
                                </div>
                            </th>
                        <?php endif; ?>
                    </tr>
                </thead>

                <!-- HÀNG THÔNG SỐ (SPECS) -->
                <tbody>
                    <tr class="spec-section-header">
                        <td colspan="<?= count($ordered_products) + (count($ordered_products)<3?2:1) ?>">
                            <b>Màn hình</b>
                        </td>
                    </tr>
                    <tr>
                        <td class="spec-label">Thông tin màn hình</td>
                        <?php foreach ($ordered_products as $p): ?>
                            <td><?= $p['specs_data']['screen'] ?? '-' ?></td>
                        <?php endforeach; ?>
                        <?php if (count($ordered_products) < 3): ?> <td class="spec-empty"></td> <?php endif; ?>
                    </tr>

                    <tr class="spec-section-header">
                        <td colspan="<?= count($ordered_products) + (count($ordered_products)<3?2:1) ?>">
                            <b>Cấu hình & Hiệu năng</b>
                        </td>
                    </tr>
                    <tr>
                        <td class="spec-label">Vi xử lý (CPU)</td>
                        <?php foreach ($ordered_products as $p): ?>
                            <td><?= $p['specs_data']['cpu'] ?? '-' ?></td>
                        <?php endforeach; ?>
                        <?php if (count($ordered_products) < 3): ?> <td class="spec-empty"></td> <?php endif; ?>
                    </tr>
                    <tr>
                        <td class="spec-label">RAM</td>
                        <?php foreach ($ordered_products as $p): ?>
                            <td><?= $p['specs_data']['ram'] ?? '-' ?></td>
                        <?php endforeach; ?>
                        <?php if (count($ordered_products) < 3): ?> <td class="spec-empty"></td> <?php endif; ?>
                    </tr>
                    <tr>
                        <td class="spec-label">Bộ nhớ trong</td>
                        <?php foreach ($ordered_products as $p): ?>
                            <td><?= $p['specs_data']['storage'] ?? '-' ?></td>
                        <?php endforeach; ?>
                        <?php if (count($ordered_products) < 3): ?> <td class="spec-empty"></td> <?php endif; ?>
                    </tr>

                    <tr class="spec-section-header">
                        <td colspan="<?= count($ordered_products) + (count($ordered_products)<3?2:1) ?>">
                            <b>Nhà sản xuất</b>
                        </td>
                    </tr>
                    <tr>
                        <td class="spec-label">Hãng sx</td>
                        <?php foreach ($ordered_products as $p): ?>
                            <td><?= $p['brand_name'] ?? '-' ?></td>
                        <?php endforeach; ?>
                        <?php if (count($ordered_products) < 3): ?> <td class="spec-empty"></td> <?php endif; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        const CURRENT_IDS = [<?= implode(',', $valid_ids) ?>];

        // 1. Chức năng Xóa sản phẩm khỏi mảng so sánh
        $('.btn-remove-compare').click(function() {
            let idToRemove = $(this).data('id');
            // Xóa ID khỏi mảng
            let newIds = CURRENT_IDS.filter(id => id !== idToRemove);
            
            // Nếu xóa hết, về trang chủ
            if (newIds.length === 0) {
                window.location.href = 'index.php';
            } else {
                window.location.href = 'compare.php?ids=' + newIds.join(',');
            }
        });

        // 2. Chức năng Tìm kiếm tự động (Auto-Suggest)
        let compareSearchTimeout = null;
        $('#compare-search-input').on('input', function() {
            let keyword = $(this).val().trim();
            let box = $('#compare-suggestions');
            
            clearTimeout(compareSearchTimeout);
            
            if (keyword.length < 2) {
                box.hide();
                return;
            }

            compareSearchTimeout = setTimeout(function() {
                $.get('api/search_suggest.php', { q: keyword }, function(data) {
                    try {
                        let products = typeof data === 'string' ? JSON.parse(data) : data;
                        if (products && products.length > 0) {
                            let html = '';
                            let fmt = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' });
                            
                            products.forEach(p => {
                                // Bỏ qua SP đã có trong danh sách
                                if (CURRENT_IDS.includes(parseInt(p.id))) return;

                                let img = p.image.startsWith('http') ? p.image : `assets/img/${p.image}`;
                                let price = p.sale_price > 0 ? p.sale_price : p.price;
                                
                                html += `
                                    <div class="compare-suggest-item" onclick="addCompare(${p.id})">
                                        <img src="${img}" alt="${p.name}">
                                        <div class="cs-info">
                                            <div class="cs-name">${p.name}</div>
                                            <div class="cs-price">${fmt.format(price)}</div>
                                        </div>
                                    </div>
                                `;
                            });

                            if (html === '') {
                                box.html('<div style="padding:10px;text-align:center;color:#999;font-size:13px;">Không có SP khả dụng</div>').show();
                            } else {
                                box.html(html).show();
                            }
                        } else {
                            box.html('<div style="padding:10px;text-align:center;color:#999;font-size:13px;">Không tìm thấy</div>').show();
                        }
                    } catch(e) {
                         box.hide();
                    }
                });
            }, 500); // 500ms debounce
        });

        // Ẩn search box khi click ra ngoài
        $(document).click(function(e) {
            if (!$(e.target).closest('.compare-search-wrap').length) {
                $('#compare-suggestions').hide();
            }
        });

        function addCompare(id) {
            if(CURRENT_IDS.length >= 3) {
                alert("Bạn chỉ có thể so sánh tối đa 3 sản phẩm cùng lúc.");
                return;
            }
            // Thêm ID mới và reload
            let newIds = [...CURRENT_IDS, id];
            window.location.href = 'compare.php?ids=' + newIds.join(',');
        }
    </script>
</body>
</html>
