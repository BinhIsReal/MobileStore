<?php
session_start();
include_once __DIR__ . '/config/db.php'; 
$cat_id = isset($_GET['cat_id']) ? intval($_GET['cat_id']) : 0;
$keyword = $_GET['q'] ?? '';
$brand = $_GET['brand'] ?? ''; 
$sort = $_GET['sort'] ?? 'desc'; 
$display_title = "Tất cả sản phẩm";
$category_name = "";
if ($cat_id > 0) {
    $stmt_cat = $conn->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt_cat->bind_param("i", $cat_id);
    $stmt_cat->execute();
    $res_cat = $stmt_cat->get_result();
    if ($r = $res_cat->fetch_assoc()) {
        $category_name = $r['name'];
        $display_title = "Danh mục: " . $category_name;
    }
} elseif ($keyword) {
    $display_title = "Tìm kiếm: " . htmlspecialchars($keyword);
}
if ($brand) {
    $display_title .= " - Hãng: " . htmlspecialchars($brand);
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $display_title ?> - TechMate</title>
    <!-- CSRF Meta Tag -->
    <?php include_once __DIR__ . '/includes/security.php'; ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/mobile.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <div class="container" style="margin-top: 30px; margin-bottom: 50px; min-height: 60vh;">

        <div
            style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            <h2 style="font-size: 24px; font-weight: 600; color: #333;">
                <span style="color:##1e62b5;"><?= $display_title ?></span>
            </h2>

            <div class="sort-wrap">
                <select onchange="location = this.value;"
                    style="padding: 5px 10px; border-radius: 4px; border: 1px solid #ddd;">
                    <?php 
                        $url_params = $_GET;
                        unset($url_params['sort']);
                        $base_link = "?" . http_build_query($url_params) . "&sort=";
                    ?>
                    <option value="<?= $base_link ?>desc" <?= $sort=='desc'?'selected':'' ?>>Mới nhất</option>
                    <option value="<?= $base_link ?>price_asc" <?= $sort=='price_asc'?'selected':'' ?>>Giá thấp - cao
                    </option>
                    <option value="<?= $base_link ?>price_desc" <?= $sort=='price_desc'?'selected':'' ?>>Giá cao - thấp
                    </option>
                </select>
            </div>
        </div>

        <?php if($cat_id > 0 || empty($keyword)): ?>
        <div class="filter-bar">
            <strong><i class="fa fa-filter"></i> Lọc theo hãng:</strong>

            <a href="search.php?cat_id=<?= $cat_id ?>" class="filter-link <?= empty($brand) ? 'active' : '' ?>">
                Tất cả
            </a>

            <?php 
                    $sql_b = "SELECT DISTINCT b.name FROM products p 
                            JOIN brands b ON p.brand_id = b.id 
                            WHERE p.category_id = ?";
                    $stmt_b = $conn->prepare($sql_b);
                    $stmt_b->bind_param("i", $cat_id);
                    $stmt_b->execute();
                    $res_b = $stmt_b->get_result();

                    while($b = $res_b->fetch_assoc()): 
                        $b_name = $b['name'];
                        $isActive = ($brand == $b_name) ? 'active' : '';
                    ?>
            <a href="search.php?cat_id=<?= $cat_id ?>&brand=<?= urlencode($b_name) ?>"
                class="filter-link <?= $isActive ?>">
                <?= $b_name ?>
            </a>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
        <?php
        // 1. CÂU TRUY VẤN CƠ BẢN
        $sql = "SELECT * FROM products WHERE 1=1";
        $params = []; 
        $types = "";

        // 2. LỌC THEO DANH MỤC
        if ($cat_id > 0) {
            $sql .= " AND category_id = ?";
            $params[] = $cat_id; 
            $types .= "i";
        }

        // 3. LỌC THEO TỪ KHÓA HOẶC MỨC GIÁ (Ô Search box)
        if (!empty($keyword)) {
            // Chuẩn hóa chuỗi tìm kiếm: Chuyển về chữ thường và xóa khoảng trắng thừa
            $kw_normalized = mb_strtolower(trim($keyword), 'UTF-8');
            $kw_normalized = preg_replace('/\s+/', ' ', $kw_normalized);

            $is_price_filter = false;

            // Kiểm tra xem từ khóa có chứa các cụm từ chỉ giá tiền không
            if (strpos($kw_normalized, 'dưới 2 triệu') !== false || $kw_normalized === 'duoi-2-trieu') {
                $sql .= " AND price < 2000000";
                $is_price_filter = true;
            } 
            elseif (strpos($kw_normalized, '2 - 4 triệu') !== false || $kw_normalized === '2-4-trieu') {
                $sql .= " AND price BETWEEN 2000000 AND 4000000";
                $is_price_filter = true;
            } 
            elseif (strpos($kw_normalized, '4 - 7 triệu') !== false || $kw_normalized === '4-7-trieu') {
                $sql .= " AND price BETWEEN 4000000 AND 7000000";
                $is_price_filter = true;
            } 
            elseif (strpos($kw_normalized, '7 - 13 triệu') !== false || $kw_normalized === '7-13-trieu') {
                $sql .= " AND price BETWEEN 7000000 AND 13000000";
                $is_price_filter = true;
            } 
            elseif (strpos($kw_normalized, 'trên 13 triệu') !== false || $kw_normalized === 'tren-13-trieu') {
                $sql .= " AND price > 13000000";
                $is_price_filter = true;
            }

            // Nếu KHÔNG PHẢI là thao tác lọc giá, thì mới đem đi tìm kiếm theo tên sản phẩm
            if (!$is_price_filter) {
                $words = preg_split('/\s+/', trim($keyword));
                if (count($words) > 0) {
                    $sql .= " AND (";
                    $name_conds = [];
                    foreach ($words as $w) {
                        if (!empty($w)) {
                            $name_conds[] = "name LIKE ?";
                            $params[] = "%$w%";
                            $types .= "s";
                        }
                    }
                    $sql .= implode(" AND ", $name_conds) . ")";
                }
            }
        }

        // 4. LỌC THEO HÃNG (BRAND) 
       if (!empty($brand)) {
            $brand_lower = mb_strtolower(trim($brand), 'UTF-8');

            if ($brand_lower === 'apple') {
                // Tìm kiếm bao quát toàn bộ sản phẩm của Apple.
                // Do đã có lọc theo category_id ở bước 2, nên nếu đang ở danh mục Laptop 
                // nó sẽ tự động chỉ lấy MacBook, không lo bị lẫn lộn iPhone hay iPad.
                $sql .= " AND (name LIKE '%apple%' 
                            OR name LIKE '%ipad%' 
                            OR name LIKE '%macbook%' 
                            OR name LIKE '%iphone%' 
                            OR name LIKE '%watch%' 
                            OR name LIKE '%airpods%'
                            OR name LIKE '%imac%')";
            } 
            else {
                // Xử lý bài toán chữ bị ngăn cách (VD: "Samsung Tab" tìm được "Samsung Galaxy Tab")
                $brand_words = preg_split('/\s+/', trim($brand));
                if (count($brand_words) > 0) {
                    $sql .= " AND (";
                    $brand_conds = [];
                    foreach ($brand_words as $bw) {
                        if (!empty($bw)) {
                            // Bắt buộc TẤT CẢ các chữ trong tên Hãng đều phải xuất hiện trong tên
                            $brand_conds[] = "name LIKE ?";
                            $params[] = "%$bw%";
                            $types .= "s";
                        }
                    }
                    $sql .= implode(" AND ", $brand_conds) . ")";
                }
            }
        }

        // 5. SẮP XẾP SẢN PHẨM
        if ($sort == 'price_asc') {
            $sql .= " ORDER BY price ASC";
        } elseif ($sort == 'price_desc') {
            $sql .= " ORDER BY price DESC";
        } else {
            $sql .= " ORDER BY id DESC";
        }

        // 6. THỰC THI TRUY VẤN
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
             die("<div style='color:red; text-align:center;'>Lỗi truy vấn SQL: " . $conn->error . "</div>");
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        ?>

        <?php if ($result->num_rows > 0): ?>
        <div class="product-grid">
            <?php while ($row = $result->fetch_assoc()): 
                    $price = number_format($row['price'], 0, ',', '.') . ' ₫';
                    $img = strpos($row['image'], 'http') === 0 ? $row['image'] : "assets/img/{$row['image']}";
                ?>
            <div class="product-card">
                <a href="product_detail.php?id=<?= $row['id'] ?>">
                    <img src="<?= $img ?>" alt="<?= $row['name'] ?>">
                    <h3><?= $row['name'] ?></h3>
                </a>
                <p class="price"><?= $price ?></p>

                <button type="button" class="js-add-to-cart btn-add" data-id="<?= $row['id'] ?>">
                    THÊM VÀO GIỎ
                </button>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div style="text-align:center; padding:50px; color:#777;">
            <i class="fa fa-search" style="font-size: 50px; margin-bottom: 20px; color: #ddd;"></i>
            <p>Không tìm thấy sản phẩm nào phù hợp.</p>
            <a href="search.php" style="color: #1e62b5;">Xem tất cả sản phẩm</a>
        </div>
        <?php endif; ?>

    </div>

    <?php include 'includes/footer.php'; ?>
</body>

</html>