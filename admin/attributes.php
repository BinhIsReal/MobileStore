<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit(); }

// Xử lý Thêm
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $type = $_POST['type']; // 'brand' hoặc 'category'
    
    if (!empty($name)) {
        $table = ($type == 'brand') ? 'brands' : 'categories';
        $stmt = $conn->prepare("INSERT INTO $table (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        if ($stmt->execute()) {
            include_once '../includes/admin_logger.php';
            $log_action = ($type == 'brand') ? 'Thêm Hãng' : 'Thêm Danh Mục';
            logAdminAction($conn, $log_action, 'admin/attributes.php', "Thêm mới $type: $name", null, ['name' => $name]);
        }
    }
    header("Location: attributes.php?msg=add_success"); exit();
}

// Xử lý Xóa
if (isset($_GET['del_type']) && isset($_GET['id'])) {
    $table = ($_GET['del_type'] == 'brand') ? 'brands' : 'categories';
    $id = intval($_GET['id']);
    
    $res_old = $conn->query("SELECT * FROM $table WHERE id=$id");
    $old_data = $res_old->fetch_assoc();
    
    if ($conn->query("DELETE FROM $table WHERE id=$id") && $old_data) {
        include_once '../includes/admin_logger.php';
        $log_action = ($_GET['del_type'] == 'brand') ? 'Xóa Hãng' : 'Xóa Danh Mục';
        $type_text = ($_GET['del_type'] == 'brand') ? 'Hãng' : 'Danh mục';
        logAdminAction($conn, $log_action, 'admin/attributes.php', "Xóa $type_text: " . $old_data['name'], $old_data, null);
    }
    header("Location: attributes.php?msg=del_success"); exit();
}

$brands = $conn->query("SELECT * FROM brands");
$cats = $conn->query("SELECT * FROM categories");
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Quản lý Thuộc tính</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        <div class="admin-content">
            <h2 style="margin-bottom: 20px;">Quản lý Hãng & Danh mục</h2>

            <div class="attr-grid">
                <div class="card">
                    <h3>🏭 Hãng sản xuất</h3>
                    <form method="POST" class="mini-form">
                        <input type="hidden" name="type" value="brand">
                        <input type="text" name="name" placeholder="Nhập tên hãng (VD: Nokia)..." required>
                        <button type="submit"><i class="fa fa-plus"></i> Thêm</button>
                    </form>
                    <div>
                        <?php while($b = $brands->fetch_assoc()): ?>
                        <div class="list-item">
                            <span><?= $b['name'] ?></span>
                            <a href="?del_type=brand&id=<?= $b['id'] ?>" class="btn-del"
                                onclick="return confirm('Xóa hãng này?')"><i class="fa fa-trash"></i></a>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="card">
                    <h3>📂 Danh mục sản phẩm</h3>
                    <form method="POST" class="mini-form">
                        <input type="hidden" name="type" value="category">
                        <input type="text" name="name" placeholder="Nhập tên danh mục (VD: Phụ kiện)..." required>
                        <button type="submit"><i class="fa fa-plus"></i> Thêm</button>
                    </form>
                    <div>
                        <?php while($c = $cats->fetch_assoc()): ?>
                        <div class="list-item">
                            <span><?= $c['name'] ?></span>
                            <a href="?del_type=category&id=<?= $c['id'] ?>" class="btn-del"
                                onclick="return confirm('Xóa danh mục này?')"><i class="fa fa-trash"></i></a>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>