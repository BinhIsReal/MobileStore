<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../login.php"); 
    exit(); 
}
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM product_gallery WHERE product_id = $del_id");
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    if ($stmt->execute()) {
        echo "<script>alert('Đã xóa sản phẩm!'); window.location='products.php';</script>";
    } else {
        echo "<script>alert('Lỗi xóa: " . $conn->error . "');</script>";
    }
}

$cats = $conn->query("SELECT * FROM categories");
$brands = $conn->query("SELECT * FROM brands");
$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($_GET['q'])) {
    $keyword = "%" . trim($_GET['q']) . "%";
    $where_clauses[] = "p.name LIKE ?";
    $params[] = $keyword;
    $types .= "s";
}

if (!empty($_GET['cat_id'])) {
    $where_clauses[] = "p.category_id = ?";
    $params[] = intval($_GET['cat_id']);
    $types .= "i";
}

if (!empty($_GET['brand_id'])) {
    $where_clauses[] = "p.brand_id = ?";
    $params[] = intval($_GET['brand_id']);
    $types .= "i";
}

$sql = "SELECT p.*, c.name as cat_name, b.name as brand_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN brands b ON p.brand_id = b.id 
        WHERE " . implode(" AND ", $where_clauses) . " 
        ORDER BY p.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Quản lý Sản phẩm</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="admin-content">
            <div class="header-action">
                <h2>Danh sách sản phẩm</h2>
                <a href="product_form.php" class="btn-add-new">
                    <i class="fa fa-plus"></i> Thêm mới
                </a>
            </div>

            <form method="GET" class="filter-wrapper">
                <div class="filter-item" style="flex: 2;">
                    <label>Từ khóa</label>
                    <input type="text" name="q" class="form-control-sm" placeholder="Nhập tên sản phẩm..."
                        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                </div>

                <div class="filter-item">
                    <label>Danh mục</label>
                    <select name="cat_id" class="form-control-sm">
                        <option value="">-- Tất cả --</option>
                        <?php while($c = $cats->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= ($_GET['cat_id']??'') == $c['id'] ? 'selected' : '' ?>>
                            <?= $c['name'] ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label>Thương hiệu</label>
                    <select name="brand_id" class="form-control-sm">
                        <option value="">-- Tất cả --</option>
                        <?php while($b = $brands->fetch_assoc()): ?>
                        <option value="<?= $b['id'] ?>" <?= ($_GET['brand_id']??'') == $b['id'] ? 'selected' : '' ?>>
                            <?= $b['name'] ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn-filter"><i class="fa fa-search"></i> Lọc</button>
                    <a href="products.php" class="btn-reset"><i class="fa fa-refresh"></i> Reset</a>
                </div>
            </form>

            <div class="table-responsive"
                style="background:white; border-radius:8px; padding:10px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th style="width: 80px;">Hình ảnh</th>
                            <th>Tên sản phẩm</th>
                            <th>Danh mục</th>
                            <th>Hãng</th>
                            <th>Giá bán</th>
                            <th style="width: 100px; text-align:center;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <?php 
                                    $img_src = (strpos($row['image'], 'http') === 0) 
                                        ? $row['image'] 
                                        : "../assets/img/" . $row['image'];
                                ?>
                        <tr>
                            <td>#<?= $row['id'] ?></td>
                            <td>
                                <img src="<?= $img_src ?>" class="product-thumb" alt="Img">
                            </td>
                            <td>
                                <div style="font-weight:600; color:#00487a;"><?= $row['name'] ?></div>
                                <small style="color:#777;">Kho: 100 (Demo)</small>
                            </td>
                            <td><?= $row['cat_name'] ?></td>
                            <td><?= $row['brand_name'] ?></td>
                            <td>
                                <div class="price-tag"><?= number_format($row['price'], 0, ',', '.') ?>đ</div>
                                <?php if($row['sale_price'] > 0): ?>
                                <small style="text-decoration:line-through; color:#999;">
                                    <?= number_format($row['sale_price'], 0, ',', '.') ?>đ
                                </small>
                                <?php endif; ?>
                            </td>
                            <td class="action-btns" style="text-align:center; gap: 6px">
                                <a href="product_form.php?id=<?= $row['id'] ?>" class="btn-edit" title="Sửa"
                                    style="  margin-bottom: 6px;">
                                    <i class="fa-solid fa-pen-to-square"
                                        style="color:#f39c12; font-size:14px; color:white;  "></i>
                                </a>

                                <a href="products.php?delete_id=<?= $row['id'] ?>"
                                    onclick="return confirm('Bạn chắc chắn muốn xóa sản phẩm này?');" class="btn-delete"
                                    title="Xóa">
                                    <i class="fa-solid fa-trash-can"
                                        style="color:#e74c3c; font-size:14px;color:white;"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:30px; color:#777;">
                                Không tìm thấy sản phẩm nào.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:20px; text-align:right; font-size:13px; color:#666;">
                Hiển thị <?= $result->num_rows ?> kết quả
            </div>
        </div>
    </div>
</body>

</html>