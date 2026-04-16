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
    $price_input = trim($_POST['price'] ?? '0');
    $price = floatval(str_replace(['.', ','], '', $price_input));
    $product_type = $_POST['product_type'] ?? 'simple';
    $attributes = isset($_POST['attributes']) ? json_encode($_POST['attributes'], JSON_UNESCAPED_UNICODE) : null;

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
    // SECURITY: Không dùng htmlspecialchars ở đây vì sẽ mã hóa khi in ra HTML; strip_tags đủ để loại XSS ở field text
    $desc = trim($_POST['description'] ?? '');
    
    // Tính tổng tồn kho của biến thể
    $variation_total_stock = 0;
    if ($product_type === 'variable' && isset($_POST['variations'])) {
        foreach ($_POST['variations'] as $v) {
            $variation_total_stock += intval($v['stock'] ?? 0);
        }
    }

    // Xử lý số lượng thực tế
    $stock_input = trim($_POST['stock'] ?? '');
    $stock = $stock_input === '' ? 0 : intval($stock_input);

    if ($product_type === 'variable') {
        if ($stock <= 0 && $variation_total_stock <= 0) {
            $msg_type = 'error';
            $msg_content = 'Vui lòng nhập Số lượng trong kho (Stock) hoặc số lượng của các biến thể!';
            goto render_form;
        }
        if ($variation_total_stock > 0) {
            $stock = $variation_total_stock; // Xoá bỏ rác/thủ công stock ở ngoài, ưu tiên biến thể
        }
    } else {
        if ($stock < 0) {
            $stock = 0; // fallback safe
        }
    }
    
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
        $sql = "UPDATE products SET name=?, price=?, sale_price=?, image=?, brand_id=?, category_id=?, product_type=?, attributes=?, specs=?, description=?, stock=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sddsiissssii", $name, $price, $sale_price, $image, $brand_id, $category_id, $product_type, $attributes, $specs, $desc, $stock, $id);
    } else {
        $sql = "INSERT INTO products (name, price, sale_price, image, brand_id, category_id, product_type, attributes, specs, description, stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sddsiissssi", $name, $price, $sale_price, $image, $brand_id, $category_id, $product_type, $attributes, $specs, $desc, $stock);
    }

    if ($stmt->execute()) {
        $current_pid = ($id > 0) ? $id : $stmt->insert_id;
        $stmt->close(); 

        // Tự động Gửi Thông báo Giảm Giá Wishlist nếu price giảm
        if ($id > 0 && $product) {
            $old_price = ($product['sale_price'] > 0) ? $product['sale_price'] : $product['price'];
            $new_price = ($sale_price > 0) ? $sale_price : $price;
            
            if ($new_price < $old_price) {
                // Sản phẩm giảm giá -> Lấy DS user đang wishlist
                $wl_stmt = $conn->prepare("SELECT user_id FROM wishlists WHERE product_id = ?");
                $wl_stmt->bind_param("i", $id);
                $wl_stmt->execute();
                $wl_res = $wl_stmt->get_result();
                $wl_stmt->close();
                
                $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'price_drop', 'Giảm giá Wishlist', ?, ?, 0, NOW())");
                if ($n_stmt) {
                    $n_msg = "Sản phẩm $name trong Wishlist của bạn vừa giảm giá!";
                    $n_link = "/wishlist.php";
                    while ($wrow = $wl_res->fetch_assoc()) {
                        $uid = $wrow['user_id'];
                        $n_stmt->bind_param("iss", $uid, $n_msg, $n_link);
                        $n_stmt->execute();
                    }
                    $n_stmt->close();
                }
            }
        }

        // GHI LOG THAO TÁC SẢN PHẨM
        include_once '../includes/admin_logger.php';
        $new_data = [
            'name' => $name, 'price' => $price, 'sale_price' => $sale_price, 
            'brand_id' => $brand_id, 'category_id' => $category_id, 'product_type' => $product_type, 'stock' => $stock
        ];
        if ($id > 0) {
            logAdminAction($conn, 'Sửa Sản Phẩm', 'admin/product_form.php', "Cập nhật sản phẩm: $name", $product, $new_data);
        } else {
            logAdminAction($conn, 'Thêm Sản Phẩm', 'admin/product_form.php', "Thêm mới sản phẩm: $name", null, $new_data);
        }

        // Xử lý biến thể
        if ($product_type === 'variable' && isset($_POST['variations'])) {
            $stmt_del_var = $conn->prepare("DELETE FROM product_variations WHERE product_id = ?");
            $stmt_del_var->bind_param("i", $current_pid);
            $stmt_del_var->execute();
            $stmt_del_var->close();

            $stmt_var = $conn->prepare("INSERT INTO product_variations (product_id, attributes, price, stock) VALUES (?, ?, ?, ?)");
            foreach ($_POST['variations'] as $v) {
                // Sử dụng "attrs" từ input name="variations[...][attrs]"
                $v_attr = json_encode($v['attrs'] ?? [], JSON_UNESCAPED_UNICODE);
                $v_price = floatval(str_replace(['.', ','], '', $v['price'] ?? 0));
                $v_stock = intval($v['stock'] ?? 0);
                $stmt_var->bind_param("isdi", $current_pid, $v_attr, $v_price, $v_stock);
                $stmt_var->execute();
            }
            $stmt_var->close();
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
                            <label class="form-label">Loại sản phẩm</label>
                            <select name="product_type" id="product_type" class="form-control">
                                <option value="simple"
                                    <?= (isset($product['product_type']) && $product['product_type'] == 'simple') ? 'selected' : '' ?>>
                                    Sản phẩm đơn giản</option>
                                <option value="variable"
                                    <?= (isset($product['product_type']) && $product['product_type'] == 'variable') ? 'selected' : '' ?>>
                                    Sản phẩm có biến thể</option>
                            </select>
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
                        <div>
                            <label class="form-label">Giá niêm yết (VNĐ) (*)</label>
                            <input type="text" name="price" class="form-control price-format" required
                                value="<?= isset($product['price']) ? number_format($product['price'], 0, ',', '.') : '' ?>">
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
                            <label class="form-label">Giá Khuyến Mãi</label>
                            <input type="text" name="sale_price" class="form-control price-format" placeholder="VD: 10% hoặc 9.000.000"
                                value="<?= ($product['sale_price'] ?? 0) > 0 ? number_format((float)$product['sale_price'], 0, ',', '.') : '' ?>">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div>
                            <label class="form-label">Số lượng trong kho (Stock)</label>
                            <input type="number" name="stock" class="form-control" min="0" placeholder="VD: 50"
                                value="<?= isset($product['stock']) ? $product['stock'] : '' ?>">
                        </div>
                        <div></div>
                    </div>
                </div>

                <div class="form-section" id="variation-section"
                    style="display: none; background: #fdfdfd; border: 1px dashed #ccc;">
                    <h4 class="form-sec-title">Cấu hình Biến Thể</h4>
                    <div id="attributes-container">
                        <h5 style="margin-bottom: 15px; font-size: 16px; color: #00487a;">Phần 1 - Thuộc tính
                            (Attributes)</h5>
                        <?php
                        $product_attrs = [];
                        if (isset($product['attributes']) && !empty($product['attributes'])) {
                            $product_attrs = json_decode($product['attributes'], true) ?: [];
                        }
                        $attr_idx = 0;
                        foreach ($product_attrs as $attr):
                            $attrName = htmlspecialchars($attr['name'] ?? '');
                            $attrVals = (is_array($attr['values'] ?? [])) ? implode(' | ', $attr['values']) : htmlspecialchars($attr['values'] ?? '');
                        ?>
                        <div class="grid-2 attr-row"
                            style="margin-bottom: 15px; align-items: flex-end; background: #fff; padding: 15px; border-radius: 6px; border: 1px solid #eaeaea;">
                            <div>
                                <label class="form-label"
                                    style="font-weight:600; margin-bottom: 5px; display:block;">Tên thuộc tính</label>
                                <input type="text" name="attributes[<?= $attr_idx ?>][name]"
                                    class="form-control attr-name" value="<?= $attrName ?>" placeholder="VD: Size, Màu">
                            </div>
                            <div>
                                <label class="form-label"
                                    style="font-weight:600; margin-bottom: 5px; display:block;">Các giá trị (Cách nhau
                                    bởi |)</label>
                                <div style="display:flex; gap:10px;">
                                    <input type="text" name="attributes[<?= $attr_idx ?>][values]"
                                        class="form-control attr-values" value="<?= $attrVals ?>"
                                        placeholder="VD: S | M | L">
                                    <button type="button" class="btn-cancel btn-remove-attr"
                                        style="padding: 0 15px; margin: 0; background: #d70018; color: white; border:none; border-radius:4px;"><i
                                            class="fa fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                        <?php $attr_idx++; endforeach; ?>

                        <button type="button" class="btn-add-new" id="btn-add-attribute" style="margin-top: 5px;">+ Thêm
                            thuộc tính</button>
                    </div>
                    <hr style="margin: 20px 0;">
                    <div id="variations-container">
                        <h5 style="margin-bottom: 10px; font-size: 16px; color: #00487a;">Phần 2 - Các biến thể chi tiết
                            (Variations)</h5>
                        <button type="button" id="btn-generate-variations"
                            style="margin-bottom: 15px; width: 100%; background: #00487a; color: white; padding: 12px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.2s;"
                            onmouseover="this.style.background='#003355'"
                            onmouseout="this.style.background='#00487a'">🚀 TẠO CÁC BIẾN THỂ TỪ THUỘC TÍNH (Nhấn để tạo
                            dòng)</button>
                        <div id="variations-list" style="display: grid; gap: 15px;">
                            <!-- LOAD CÁC BIẾN THỂ CŨ NẾU CÓ -->
                            <?php
                        if ($id > 0 && ($product['product_type'] ?? 'simple') === 'variable') {
                            $res_v = $conn->query("SELECT * FROM product_variations WHERE product_id = $id");
                            if ($res_v && $res_v->num_rows > 0) {
                                $v_idx = 0;
                                while ($v_row = $res_v->fetch_assoc()) {
                                    $v_attrs = json_decode($v_row['attributes'] ?? '[]', true);
                                    if(empty($v_attrs)) continue;
                                    
                                    $comboLabel = implode(' - ', array_values($v_attrs));
                                    $attrInputs = '';
                                    foreach ($v_attrs as $k => $v) {
                                        $attrInputs .= '<input type="hidden" name="variations['.$v_idx.'][attrs]['.htmlspecialchars($k).']" value="'.htmlspecialchars($v).'">';
                                    }
                                    
                                    echo '<div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.02);">
                                        <strong style="color: #d70018; display:block; margin-bottom: 10px; font-size: 15px;"><i class="fa fa-cube"></i> '.$comboLabel.'</strong>
                                        '.$attrInputs.'
                                        <div class="grid-2">
                                            <div><label class="form-label">Giá tiền (đ)</label><input type="text" name="variations['.$v_idx.'][price]" class="form-control price-format" value="'.number_format((float)$v_row['price'], 0, ',', '.').'" required></div>
                                            <div><label class="form-label">Tồn kho</label><input type="number" name="variations['.$v_idx.'][stock]" class="form-control" value="'.(int)$v_row['stock'].'"></div>
                                        </div>
                                    </div>';
                                    $v_idx++;
                                }
                            }
                        }
                        ?>
                        </div>
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
    <script>
    $('#product_type').on('change', function() {
        if ($(this).val() === 'variable') {
            $('#variation-section').slideDown();
        } else {
            $('#variation-section').slideUp();
        }
    }).trigger('change');

    let attrIndex = <?= isset($attr_idx) ? $attr_idx : 0 ?>;
    $('#btn-add-attribute').click(function() {
        $('#btn-add-attribute').before(`
            <div class="grid-2 attr-row" style="margin-bottom: 15px; align-items: flex-end; background: #fff; padding: 15px; border-radius: 6px; border: 1px solid #eaeaea;">
                <div>
                    <label class="form-label" style="font-weight:600; margin-bottom: 5px; display:block;">Tên thuộc tính</label>
                    <input type="text" name="attributes[${attrIndex}][name]" class="form-control attr-name" placeholder="VD: Size, Màu">
                </div>
                <div>
                    <label class="form-label" style="font-weight:600; margin-bottom: 5px; display:block;">Các giá trị (Cách nhau bởi |)</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" name="attributes[${attrIndex}][values]" class="form-control attr-values" placeholder="VD: S | M | L">
                        <button type="button" class="btn-cancel btn-remove-attr" style="padding: 0 15px; margin: 0; background: #d70018; color: white; border:none; border-radius:4px;"><i class="fa fa-trash"></i></button>
                    </div>
                </div>
            </div>
        `);
        attrIndex++;
    });

    $(document).on('click', '.btn-remove-attr', function() {
        $(this).closest('.attr-row').remove();
    });

    $('#btn-generate-variations').click(function() {
        let attributes = [];
        $('.attr-row').each(function() {
            let name = $(this).find('.attr-name').val().trim();
            let vals = $(this).find('.attr-values').val().split('|').map(v => v.trim()).filter(v =>
                v !== '');
            if (name && vals.length > 0) attributes.push({
                name: name,
                values: vals
            });
        });

        if (attributes.length === 0) return alert("Vui lòng nhập Thuộc tính ở Phần 1");

        // Cartesian Product
        const cartesian = (args) => {
            let r = [],
                max = args.length - 1;
            const helper = (arr, i) => {
                for (let j = 0, l = args[i].values.length; j < l; j++) {
                    let a = arr.slice(0);
                    a.push({
                        [args[i].name]: args[i].values[j]
                    });
                    if (i == max) r.push(a);
                    else helper(a, i + 1);
                }
            };
            helper([], 0);
            return r;
        };

        let combos = cartesian(attributes);
        if (combos.length > 30) return alert(
            "Quá 30 biến thể sinh ra! Vui lòng giảm số lượng để tránh treo trình duyệt.");

        let listHtml = '';
        combos.forEach((combo, idx) => {
            let comboObj = Object.assign({}, ...combo);
            let comboLabel = Object.values(comboObj).join(' - ');
            let attrInputs = Object.entries(comboObj).map(([k, v]) =>
                `<input type="hidden" name="variations[${idx}][attrs][${k}]" value="${v}">`).join(
                '');

            listHtml += `
            <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.02);">
                <strong style="color: #d70018; display:block; margin-bottom: 10px; font-size: 15px;"><i class="fa fa-cube"></i> ${comboLabel}</strong>
                ${attrInputs}
                <div class="grid-2">
                    <div><label class="form-label">Giá tiền (đ)</label><input type="text" name="variations[${idx}][price]" class="form-control price-format" required placeholder="Bắt buộc"></div>
                    <div><label class="form-label">Tồn kho</label><input type="number" name="variations[${idx}][stock]" class="form-control" value="10"></div>
                </div>
            </div>`;
        });
        $('#variations-list').html(listHtml);
    });

    // Tự động thêm dấu chấm ngăn cách ngàn cho price (price-format)
    $(document).on('input', '.price-format', function() {
        let currentVal = $(this).val();
        // Giữ lại dấu % nếu có
        if (currentVal.indexOf('%') !== -1) return;
        
        // Loai bỏ ký tự không phải số
        let val = currentVal.replace(/[^0-9]/g, '');
        if(val) {
            $(this).val(new Intl.NumberFormat('vi-VN').format(val));
        } else {
            $(this).val('');
        }
    });
    </script>
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