<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../login.php"); 
    exit(); 
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$product = null;
$msg_type = ''; 
$msg_content = '';

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // SECURITY: Xác thực CSRF Token
    csrf_verify_or_redirect('product_form.php' . ($id > 0 ? "?id=$id" : ''));

    // SECURITY: trim() và validate bắt buộc trước khi xử lý
    $name = trim($_POST['name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);

    if (empty($name)) {
        $msg_type    = 'error';
        $msg_content = 'Tên sản phẩm không được để trống!';
        goto render_form;
    }
    if ($price <= 0) {
        $msg_type    = 'error';
        $msg_content = 'Giá sản phẩm không hợp lệ!';
        goto render_form;
    }
    
    // Xử lý giá khuyến mãi
    $sale_input = $_POST['sale_price'] ?? 0;
    $sale_price = 0;
    if (!empty($sale_input)) {
        if (strpos($sale_input, '%') !== false) {
            $percent = floatval(str_replace('%', '', $sale_input));
            $sale_price = $price - ($price * $percent / 100);
        } else {
            $sale_price = floatval(str_replace([',', '.'], '', $sale_input));
        }
    }

    $brand_id = intval($_POST['brand_id']);
    $category_id = intval($_POST['category_id']);
    $colors = trim($_POST['colors'] ?? '');
    // SECURITY: Không dùng htmlspecialchars ở đây vì sẽ mã hóa khi in ra HTML; strip_tags đủ để loại XSS ở field text
    $desc = trim($_POST['description'] ?? '');
    
    // Xử lý số lượng thực tế
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    
    // Xử lý ảnh
    $image = $_POST['image_link'] ?? '';
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === 0) {
        // SECURITY: Chỉ chấp nhận các định dạng ảnh hợp lệ
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
        $file_mime = mime_content_type($_FILES['image_file']['tmp_name']);

        if (in_array($file_ext, $allowed_exts) && in_array($file_mime, $allowed_mimes)) {
            // SECURITY: Đặt lại tên file an toàn (không dùng tên gốc từ user)
            $safe_name = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
            $target = '../assets/img/' . $safe_name;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target)) {
                $image = $safe_name;
            }
        }
    }
    if (empty($image) && $product) {
        $image = $product['image'];
    }

    $specs = json_encode([
        'screen' => $_POST['spec_screen'] ?? '',
        'cpu' => $_POST['spec_cpu'] ?? '',
        'ram' => $_POST['spec_ram'] ?? '',
        'storage' => $_POST['spec_storage'] ?? ''
    ], JSON_UNESCAPED_UNICODE);

    // INSERT / UPDATE
    if ($id > 0) {
        $sql = "UPDATE products SET name=?, price=?, sale_price=?, image=?, brand_id=?, category_id=?, colors=?, specs=?, description=?, stock=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sddsiisssii", $name, $price, $sale_price, $image, $brand_id, $category_id, $colors, $specs, $desc, $stock, $id);
    } else {
        $sql = "INSERT INTO products (name, price, sale_price, image, brand_id, category_id, colors, specs, description, stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sddsiisssi", $name, $price, $sale_price, $image, $brand_id, $category_id, $colors, $specs, $desc, $stock);
    }

    if ($stmt->execute()) {
        $current_pid = ($id > 0) ? $id : $stmt->insert_id;
        $stmt->close(); 

        // GHI LOG THAO TÁC SẢN PHẨM
        include_once '../includes/admin_logger.php';
        $new_data = [
            'name' => $name, 'price' => $price, 'sale_price' => $sale_price, 
            'brand_id' => $brand_id, 'category_id' => $category_id, 'colors' => $colors, 'stock' => $stock
        ];
        if ($id > 0) {
            logAdminAction($conn, 'Sửa Sản Phẩm', 'admin/product_form.php', "Cập nhật sản phẩm: $name", $product, $new_data);
        } else {
            logAdminAction($conn, 'Thêm Sản Phẩm', 'admin/product_form.php', "Thêm mới sản phẩm: $name", null, $new_data);
        }

        // Xử lý Gallery
        // FIXED: Dùng Prepared Statement thay vì ghép $current_pid
        $stmt_del_gal = $conn->prepare("DELETE FROM product_gallery WHERE product_id = ?");
        $stmt_del_gal->bind_param("i", $current_pid);
        $stmt_del_gal->execute();
        $stmt_del_gal->close();

        if (!empty($_POST['gallery'])) {
            $stmt_gal = $conn->prepare("INSERT INTO product_gallery (product_id, image_url) VALUES (?, ?)");
            foreach ($_POST['gallery'] as $img_url) {
                $clean_url = trim($img_url);
                if (!empty($clean_url)) {
                    $stmt_gal->bind_param("is", $current_pid, $clean_url);
                    $stmt_gal->execute();
                }
            }
            $stmt_gal->close();
        }

        $msg_type = 'success';
        $msg_content = 'Lưu sản phẩm thành công! Đang chuyển hướng...';
    } else {
        $msg_type = 'error';
        // SECURITY: Không expose chi tiết lỗi DB ra ngoài, ghi log thay thế
        error_log("Product form DB Error: " . $stmt->error);
        $msg_content = 'Lỗi hệ thống, vui lòng thử lại!';
    }
}

// FIXED: Dùng Prepared Statement thay vì ghép biến $id trực tiếp
$brands_res = $conn->query("SELECT * FROM brands ORDER BY name ASC");
$cats_res   = $conn->query("SELECT * FROM categories ORDER BY name ASC");
$spec_data  = ($product && !empty($product['specs'])) ? json_decode($product['specs'], true) : [];
render_form:
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <title><?= $id > 0 ? 'Sửa sản phẩm' : 'Thêm sản phẩm' ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div id="toast-container"></div>

    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="admin-content">
            <div class="header-action">
                <h2><?= $id > 0 ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm mới' ?></h2>
                <a href="products.php" class="btn-back"><i class="fa fa-arrow-left"></i> Quay lại</a>
            </div>

            <form method="POST" enctype="multipart/form-data" id="product-form">
                <?= csrf_field() ?>
                <div class="form-section">
                    <h4 class="form-sec-title">1. Thông tin & Giá bán</h4>
                    <div class="grid-2">
                        <div>
                            <label class="form-label">Tên sản phẩm (*)</label>
                            <input type="text" name="name" class="form-control" required
                                value="<?= htmlspecialchars($product['name'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="form-label">Hãng sản xuất</label>
                            <select name="brand_id" class="form-control">
                                <option value="">-- Chọn Hãng --</option>
                                <?php if($brands_res) while($b = $brands_res->fetch_assoc()): ?>
                                <option value="<?= $b['id'] ?>"
                                    <?= ($product['brand_id']??0) == $b['id'] ? 'selected' : '' ?>>
                                    <?= $b['name'] ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div>
                            <label class="form-label">Giá niêm yết (VNĐ) (*)</label>
                            <input type="number" name="price" class="form-control" required
                                value="<?= $product['price'] ?? '' ?>">
                        </div>
                        <div>
                            <label class="form-label">Giá Khuyến Mãi</label>
                            <input type="text" name="sale_price" class="form-control" placeholder="VD: 10%"
                                value="<?= ($product['sale_price'] ?? 0) > 0 ? (float)$product['sale_price'] : '' ?>">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div>
                            <label class="form-label">Danh mục</label>
                            <select name="category_id" class="form-control">
                                <option value="">-- Chọn Danh Mục --</option>
                                <?php if($cats_res) while($c = $cats_res->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>"
                                    <?= ($product['category_id']??0) == $c['id'] ? 'selected' : '' ?>>
                                    <?= $c['name'] ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Màu sắc</label>
                            <input type="text" name="colors" class="form-control"
                                value="<?= htmlspecialchars($product['colors'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div>
                            <label class="form-label">Số lượng trong kho (Stock) (*)</label>
                            <input type="number" name="stock" class="form-control" required min="0" placeholder="VD: 50"
                                value="<?= isset($product['stock']) ? $product['stock'] : 0 ?>">
                        </div>
                        <div></div>
                    </div>
                </div>

                <div class="form-section">
                    <h4 class="form-sec-title">2. Hình ảnh</h4>
                    <label class="form-label">Ảnh đại diện chính</label>
                    <div class="image-upload-row">
                        <input type="text" name="image_link" class="form-control" placeholder="Link ảnh online..."
                            value="<?= htmlspecialchars($product['image'] ?? '') ?>">
                        <input type="file" name="image_file" class="form-control form-control-file">
                    </div>
                    <label class="form-label">Bộ sưu tập ảnh (Gallery)</label>
                    <div id="gallery-wrapper">
                        <?php 
                        if ($id > 0) {
                            // FIXED: Dùng Prepared Statement
                            $res_gal = $conn->prepare("SELECT image_url FROM product_gallery WHERE product_id = ?");
                            $res_gal->bind_param("i", $id);
                            $res_gal->execute();
                            $res_gal = $res_gal->get_result();
                            if ($res_gal && $res_gal->num_rows > 0) {
                                while ($g = $res_gal->fetch_assoc()): 
                        ?>
                        <div class="gallery-row">
                            <input type="text" name="gallery[]" class="form-control"
                                value="<?= htmlspecialchars($g['image_url']) ?>">
                            <button type="button" class="btn-del-gal" onclick="this.parentElement.remove()"><i
                                    class="fa fa-trash"></i></button>
                        </div>
                        <?php endwhile; }} ?>
                        <?php if(empty($res_gal) || $res_gal->num_rows == 0): ?>
                        <div class="gallery-row">
                            <input type="text" name="gallery[]" class="form-control" placeholder="Link ảnh phụ...">
                        </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="btn-add-gallery" class="btn-add-new btn-add-gallery">
                        <i class="fa fa-plus"></i> Thêm dòng ảnh
                    </button>
                </div>

                <div class="form-section">
                    <h4 class="form-sec-title">3. Cấu hình & Mô tả</h4>
                    <div class="grid-2">
                        <div><label class="form-label">Màn hình</label><input type="text" name="spec_screen"
                                class="form-control" value="<?= $spec_data['screen'] ?? '' ?>"></div>
                        <div><label class="form-label">CPU</label><input type="text" name="spec_cpu"
                                class="form-control" value="<?= $spec_data['cpu'] ?? '' ?>"></div>
                    </div>
                    <div class="grid-2">
                        <div><label class="form-label">RAM</label><input type="text" name="spec_ram"
                                class="form-control" value="<?= $spec_data['ram'] ?? '' ?>"></div>
                        <div><label class="form-label">Bộ nhớ</label><input type="text" name="spec_storage"
                                class="form-control" value="<?= $spec_data['storage'] ?? '' ?>"></div>
                    </div>
                    <label class="form-label form-label-mt">Mô tả chi tiết</label>
                    <textarea name="description" class="form-control"
                        rows="5"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn-submit"><i class="fa fa-save"></i> LƯU SẢN PHẨM</button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/product_form.js"></script>
    <?php if (!empty($msg_type)): ?>
    <script>
    $(document).ready(function() {
        showToast({
            title: "<?= $msg_type === 'success' ? 'Thành công' : 'Lỗi' ?>",
            // SECURITY: Escape nội dung trước khi nhúng vào JS string
            message: "<?= htmlspecialchars($msg_content, ENT_QUOTES, 'UTF-8') ?>",
            type: "<?= htmlspecialchars($msg_type, ENT_QUOTES, 'UTF-8') ?>"
        });
        <?php if ($msg_type == 'success'): ?>
        setTimeout(function() {
            window.location.href = 'products.php';
        }, 1500);
        <?php endif; ?>
    });
    </script>
    <?php endif; ?>
</body>

</html>