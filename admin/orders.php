<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';
// SECURITY: Kiểm tra quyền admin + exit() bắt buộc sau header
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Quản lý Đơn hàng</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/orders.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="admin-content">
            <h2>Danh sách đơn hàng</h2>
            <div class="adm-order-filter-wrapper">
                <form method="GET" class="adm-order-filter-form">
                    <div class="adm-order-filter-item">
                        <label>Tìm kiếm</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                            class="adm-order-filter-control" placeholder="ID hoặc Tên khách...">
                    </div>

                    <div class="adm-order-filter-item">
                        <label>Trạng thái</label>
                        <select name="status" class="adm-order-filter-control">
                            <option value="">Tất cả trạng thái</option>
                            <option value="pending" <?= ($_GET['status'] ?? '') == 'pending' ? 'selected' : '' ?>>Đang
                                chờ duyệt</option>
                            <option value="shipping" <?= ($_GET['status'] ?? '') == 'shipping' ? 'selected' : '' ?>>Đang
                                vận chuyển</option>
                            <option value="completed" <?= ($_GET['status'] ?? '') == 'completed' ? 'selected' : '' ?>>
                                Giao thành công</option>
                            <option value="cancelled" <?= ($_GET['status'] ?? '') == 'cancelled' ? 'selected' : '' ?>>
                                Yêu cầu hủy</option>
                        </select>
                    </div>

                    <div class="adm-order-filter-item">
                        <label>Từ ngày</label>
                        <input type="date" name="date_from" value="<?= $_GET['date_from'] ?? '' ?>"
                            class="adm-order-filter-control">
                    </div>

                    <div class="adm-order-filter-item">
                        <label>Đến ngày</label>
                        <input type="date" name="date_to" value="<?= $_GET['date_to'] ?? '' ?>"
                            class="adm-order-filter-control">
                    </div>

                    <div class="adm-order-filter-item">
                        <label>Sắp xếp giá</label>
                        <select name="sort_price" class="adm-order-filter-control">
                            <option value="">Mặc định (Mới nhất)</option>
                            <option value="desc" <?= ($_GET['sort_price'] ?? '') == 'desc' ? 'selected' : '' ?>>Giá: Cao
                                đến Thấp</option>
                            <option value="asc" <?= ($_GET['sort_price'] ?? '') == 'asc' ? 'selected' : '' ?>>Giá: Thấp
                                đến Cao</option>
                        </select>
                    </div>

                    <button type="submit" class="adm-order-filter-btn">
                        <i class="fa fa-search"></i> LỌC
                    </button>

                    <a href="orders.php" class="adm-order-filter-btn adm-order-filter-btn-reset">
                        <i class="fa fa-undo"></i> RESET
                    </a>
                </form>
            </div>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Khách hàng</th>
                        <th>Tổng tiền</th>
                        <th>Ngày đặt</th>
                        <th>Trạng thái</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // --- LOGIC BỘ LỌC VÀ TÌM KIẾM ---
                    $where = ["1=1"];
                    $params = [];
                    $types = "";
                    if (!empty($_GET['q'])) {
                        $q = "%" . $_GET['q'] . "%";
                        $where[] = "(orders.id = ? OR orders.name LIKE ?)";
                        $params[] = intval($_GET['q']);
                        $params[] = $q;
                        $types .= "is";
                    }
                    if (!empty($_GET['date_from'])) {
                        $where[] = "DATE(orders.created_at) >= ?";
                        $params[] = $_GET['date_from'];
                        $types .= "s";
                    }
                    if (!empty($_GET['date_to'])) {
                        $where[] = "DATE(orders.created_at) <= ?";
                        $params[] = $_GET['date_to'];
                        $types .= "s";
                    }
                    if (!empty($_GET['status'])) {
                        $where[] = "orders.status = ?";
                        $params[] = $_GET['status'];
                        $types .= "s";
                    }

                    $sort_order = ($_GET['sort_price'] ?? '') == 'asc' ? 'ASC' : 'DESC';
                    $order_by = "orders.created_at DESC"; 
                    if (!empty($_GET['sort_price'])) {
                        $dir = ($_GET['sort_price'] == 'asc') ? 'ASC' : 'DESC';
                        $order_by = "orders.total_price $dir, orders.created_at DESC";
                    }
                    $sql = "SELECT orders.*, users.username FROM orders 
                            LEFT JOIN users ON orders.user_id = users.id 
                            WHERE " . implode(" AND ", $where) . " 
                            ORDER BY $order_by";

                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res->num_rows > 0):
                        while($row = $res->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= $row['id'] ?></td>
                        <td><?= $row['username'] ?? 'Khách lẻ' ?></td>
                        <?php $final_admin = max(0, $row['total_price'] - $row['discount_amount']); ?>
                        <td class="order-total-price"><?= number_format($final_admin) ?> đ</td>
                        <td><?= $row['created_at'] ?></td>
                        <td>
                            <select onchange="updateStatus(<?= $row['id'] ?>, this.value)"
                                class="status-badge bg-<?= $row['status'] ?> status-badge-select">
                                <option value="pending" <?= $row['status']=='pending'?'selected':'' ?>>Chờ xử lý
                                </option>
                                <option value="shipping" <?= $row['status']=='shipping'?'selected':'' ?>>Đang giao
                                </option>
                                <option value="completed" <?= $row['status']=='completed'?'selected':'' ?>>Hoàn thành
                                </option>
                                <option value="cancelled" <?= $row['status']=='cancelled'?'selected':'' ?>>Đã hủy
                                </option>
                            </select>
                        </td>
                        <td>
                            <button onclick="viewOrderDetail(<?= $row['id'] ?>)" title="Xem chi tiết" class="btn-view-order">
                                <i class="fa fa-eye"></i> Xem
                            </button>
                        </td>
                    </tr>
                    <?php endwhile;
                    else: ?>
                    <tr>
                        <td colspan="6" class="text-center-pad">Chưa có đơn hàng nào.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>

    <!-- MODAL CHI TIẾT ĐƠN HÀNG -->
    <div id="orderDetailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header-bar">
                <h3>Chi tiết đơn hàng #<span id="modal-order-id"></span></h3>
                <span class="btn-close-modal" onclick="$('#orderDetailModal').fadeOut()">&times;</span>
            </div>
            
            <div id="order-detail-content" class="modal-body-pad">
                <div style="text-align:center;"><i class="fa fa-spinner fa-spin fa-2x"></i> Đang tải dữ liệu...</div>
            </div>

        </div>
    </div>

    <script src="../assets/js/admin_main.js"></script>
    <script src="../assets/js/orders.js"></script>
</body>

</html>