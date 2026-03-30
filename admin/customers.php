<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../login.php"); 
    exit(); 
}

$search = $_GET['search'] ?? '';
$where = "role = 'user'";
if ($search !== '') {
    $search_esc = $conn->real_escape_string($search);
    $where .= " AND (username LIKE '%$search_esc%' OR email LIKE '%$search_esc%' OR phone LIKE '%$search_esc%')";
}

$sql = "SELECT u.id, u.username, u.email, u.phone, u.created_at,
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

            <form method="GET" class="filter-wrapper customers-filter-form">
                <input type="text" name="search" class="form-control-sm customers-filter-input"
                    placeholder="Tìm theo tên, SĐT hoặc Email..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-filter"><i class="fa fa-search"></i> Tìm kiếm</button>
                <a href="customers.php" class="btn-reset"><i class="fa fa-refresh"></i> Reset</a>
            </form>

            <div class="table-responsive customers-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Khách hàng</th>
                            <th>Liên hệ</th>
                            <th class="text-center">Tổng đơn</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= $row['id'] ?></td>
                            <td>
                                <b><?= htmlspecialchars($row['username']) ?></b><br>
                                <small class="customer-date-small">Ngày ĐK:
                                    <?= date('d/m/Y', strtotime($row['created_at'])) ?></small>
                            </td>
                            <td>
                                <i class="fa fa-phone customer-phone-icon"></i>
                                <?= htmlspecialchars($row['phone'] ?? 'N/A') ?><br>
                                <i class="fa fa-envelope customer-phone-icon"></i>
                                <?= htmlspecialchars($row['email'] ?? 'N/A') ?>
                            </td>
                            <td class="text-center">
                                <span class="badge-total-orders"><?= $row['total_orders'] ?></span>
                            </td>
                            <td>
                                <a href="chat.php?user_id=<?= $row['id'] ?>" class="btn-chat-customer">
                                    <i class="fa-solid fa-comment-dots"></i> Nhắn tin
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center-pad">Không tìm thấy khách hàng nào.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>