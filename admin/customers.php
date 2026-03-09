<?php
session_start();
include '../config/db.php';

// Check quyền Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../login.php"); 
    exit(); 
}

// Xử lý Tìm kiếm (chỉ tìm theo username)
$search = $_GET['search'] ?? '';
$where = "role = 'user'";
if ($search !== '') {
    $search_esc = $conn->real_escape_string($search);
    $where .= " AND (username LIKE '%$search_esc%')";
}

// Lấy danh sách khách hàng (Đã XÓA tính tổng tiền total_money)
$sql = "SELECT u.id, u.username, u.created_at,
        (SELECT COUNT(id) FROM orders WHERE user_id = u.id) as total_orders
        FROM users u 
        WHERE $where 
        ORDER BY u.id DESC";
        
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Quản lý Khách hàng</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="admin-content">
            <div class="header-action">
                <h2>Danh sách Khách hàng</h2>
            </div>

            <form method="GET" class="filter-wrapper"
                style="display:flex; gap:10px; margin-bottom:20px; background:#fff; padding:15px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
                <input type="text" name="search" class="form-control-sm"
                    style="flex:1; padding:10px; border:1px solid #ddd; border-radius:4px;"
                    placeholder="Tìm theo tên đăng nhập..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-filter"
                    style="background:#00487a; color:white; padding:10px 20px; border:none; border-radius:4px; cursor:pointer;">
                    <i class="fa fa-search"></i> Tìm kiếm
                </button>
                <a href="customers.php" class="btn-reset"
                    style="background:#6c757d; color:white; padding:10px 20px; text-decoration:none; border-radius:4px;">
                    <i class="fa fa-refresh"></i> Reset
                </a>
            </form>

            <div class="table-responsive"
                style="background:white; border-radius:8px; padding:10px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
                <table class="admin-table" style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f4f6f8; text-align:left;">
                            <th style="padding:12px; border-bottom:2px solid #ddd;">ID</th>
                            <th style="padding:12px; border-bottom:2px solid #ddd;">Khách hàng</th>
                            <th style="padding:12px; border-bottom:2px solid #ddd; text-align:center;">Tổng đơn</th>
                            <th style="padding:12px; border-bottom:2px solid #ddd;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td style="padding:12px; border-bottom:1px solid #eee;">#<?= $row['id'] ?></td>
                            <td style="padding:12px; border-bottom:1px solid #eee;">
                                <b><?= htmlspecialchars($row['username']) ?></b><br>
                                <small style="color:#777;">Ngày ĐK:
                                    <?= date('d/m/Y', strtotime($row['created_at'])) ?></small>
                            </td>
                            <td style="padding:12px; border-bottom:1px solid #eee; text-align:center;">
                                <span
                                    style="background:#e9ecef; padding:3px 8px; border-radius:12px; font-weight:bold; font-size:12px;">
                                    <?= $row['total_orders'] ?>
                                </span>
                            </td>
                            <td style="padding:12px; border-bottom:1px solid #eee;">
                                <a href="chat.php?user_id=<?= $row['id'] ?>"
                                    style="display:inline-block; background:#28a745; color:white; padding:6px 12px; border-radius:4px; text-decoration:none; font-size:13px; font-weight:bold;">
                                    <i class="fa-solid fa-comment-dots"></i> Nhắn tin
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding:30px; color:#777;">Không tìm thấy khách
                                hàng nào.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>