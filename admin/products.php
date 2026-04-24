<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../login.php"); 
    exit(); 
}
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    if ($del_id <= 0) {
        header("Location: products.php");
        exit;
    }

    // FIXED: Dùng Prepared Statement lấy dữ liệu cũ
    $stmt_old = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt_old->bind_param("i", $del_id);
    $stmt_old->execute();
    $old_data = $stmt_old->get_result()->fetch_assoc();
    $stmt_old->close();
    
    // FIXED: Dùng Prepared Statement xóa gallery
    $stmt_gal = $conn->prepare("DELETE FROM product_gallery WHERE product_id = ?");
    $stmt_gal->bind_param("i", $del_id);
    $stmt_gal->execute();
    $stmt_gal->close();

    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    if ($stmt->execute()) {
        $stmt->close();
        include_once '../includes/admin_logger.php';
        $product_name = $old_data['name'] ?? "Sản phẩm ID #$del_id";
        logAdminAction($conn, 'Xóa Sản Phẩm', 'admin/products.php', "Xóa sản phẩm: $product_name", $old_data, null);
        // SECURITY: Dùng header redirect thay vì in script
        header("Location: products.php?msg=deleted");
        exit;
    } else {
        error_log("Delete product error: " . $stmt->error);
        header("Location: products.php?msg=error");
        exit;
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

$sql = "SELECT p.*, c.name as cat_name, b.name as brand_name,
               (SELECT SUM(stock) FROM product_variations WHERE product_id = p.id) as var_total_stock
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

            <div class="table-responsive table-responsive-box">
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
                                <div class="product-name-text"><?= $row['name'] ?></div>
                                <?php 
                                    $display_stock = isset($row['stock']) ? $row['stock'] : 0;
                                    if (isset($row['product_type']) && $row['product_type'] === 'variable') {
                                        $display_stock = isset($row['var_total_stock']) ? $row['var_total_stock'] : $display_stock;
                                    }
                                ?>
                                <small class="product-stock-text">Kho: <?= $display_stock ?> <?= (isset($row['product_type']) && $row['product_type'] == 'variable') ? '<span style="color:#d70018; font-size:11px;">(Là tổng các biến thể)</span>' : '' ?></small>
                            </td>
                            <td><?= $row['cat_name'] ?></td>
                            <td><?= $row['brand_name'] ?></td>
                            <td>
                                <div class="price-tag"><?= number_format($row['price'], 0, ',', '.') ?>đ</div>
                                <?php if($row['sale_price'] > 0): ?>
                                <small class="product-sale-old">
                                    <?= number_format($row['sale_price'], 0, ',', '.') ?>đ
                                </small>
                                <?php endif; ?>
                            </td>
                            <td class="action-btns">
                                <a href="product_form.php?id=<?= $row['id'] ?>" class="btn-edit" title="Sửa">
                                    <i class="fa-solid fa-pen-to-square"
                                        style="color:#f39c12; font-size:14px; color:white;  "></i>
                                </a>

                                <a href="javascript:void(0)"
                                    onclick="confirmDelete(<?= $row['id'] ?>)" class="btn-delete"
                                    title="Xóa">
                                    <i class="fa-solid fa-trash-can"
                                        style="color:#eb3e51; font-size:14px;color:white;"></i>
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

            <div class="result-count-bar">
                Hiển thị <?= $result->num_rows ?> kết quả
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmDelete(id) {
            Swal.fire({
                title: "Xác nhận xóa?",
                text: "Bạn chắc chắn muốn xóa sản phẩm này?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d70018",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Hủy bỏ",
                customClass: {
                    popup: 'confirm-box-swal'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `products.php?delete_id=${id}`;
                }
            });
        }
    </script>
</body>

</html>