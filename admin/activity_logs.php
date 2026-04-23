<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Lấy bộ lọc ngày và phân trang mặc định là ngày hôm nay
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Format ngày SQL
$start_date_esc = $conn->real_escape_string($start_date);
$end_date_esc = $conn->real_escape_string($end_date);

$where_clause = "DATE(l.created_at) >= '$start_date_esc' AND DATE(l.created_at) <= '$end_date_esc'";

// Lấy tổng số dòng để tính số trang
$count_query = "SELECT COUNT(*) as total FROM admin_logs l WHERE $where_clause";
$count_result = $conn->query($count_query);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Truy vấn danh sách lịch sử (JOIN với users để lấy tên Admin)
$sql = "SELECT l.*, u.username as admin_name 
        FROM admin_logs l 
        LEFT JOIN users u ON l.admin_id = u.id 
        WHERE $where_clause
        ORDER BY l.id DESC LIMIT $limit OFFSET $offset";
$logs = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <title>Lịch sử hoạt động</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <div class="admin-content">
            <h2 style="margin-bottom:20px;">Lịch sử hoạt động (Activity Logs)</h2>
            
            <form method="GET" style="margin-bottom: 20px; display: flex; gap: 15px; align-items: center; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label style="font-weight: bold;">Từ ngày:</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label style="font-weight: bold;">Đến ngày:</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
                </div>
                <button type="submit" class="adm-btn" style="padding: 8px 15px;"><i class="fa fa-filter"></i> Lọc dữ liệu</button>
            </form>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Thời gian</th>
                        <th>Người thực hiện</th>
                        <th>Hành động</th>
                        <th>Trang/File thao tác</th>
                        <th>Mô tả</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs && $logs->num_rows > 0): ?>
                        <?php while($row = $logs->fetch_assoc()): 
                            $action_class = explode(' ', trim($row['action']))[0]; 
                            
                            // Map đường dẫn file thành Tên trang 
                            $page_map = [
                                'admin/products.php' => 'Quản lý Sản phẩm',
                                'admin/product_form.php' => 'Quản lý Sản phẩm',
                                'admin/attributes.php' => 'QL Hãng & Danh mục',
                                'admin/orders.php' => 'Quản lý Đơn hàng',
                                'api/admin_api.php' => 'Quản lý Đơn hàng',
                                'admin/vouchers.php' => 'Quản lý Voucher',
                                'api/voucher_api.php' => 'Quản lý Voucher',
                                'admin/dashboard.php' => 'Thống kê doanh thu'
                            ];
                            $display_page = isset($page_map[$row['page_name']]) ? $page_map[$row['page_name']] : $row['page_name'];
                            $row['display_page'] = $display_page; 
                        ?>
                        <tr>
                            <td>#<?= $row['id'] ?></td>
                            <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                            <td><b class="admin-name-text"><i class="fa fa-user-shield"></i> <?= htmlspecialchars($row['admin_name'] ?? 'Unknown') ?></b></td>
                            <td><span class="badge <?= $action_class ?>"><?= htmlspecialchars($row['action']) ?></span></td>
                            <td><b><?= htmlspecialchars($display_page) ?></b></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td>
                                <button onclick='viewLogDetail(<?= json_encode($row) ?>)' class="adm-btn btn-view-log" title="Xem chi tiết">
                                    <i class="fa fa-search-plus"></i> Xem
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="empty-row">Lịch sử ghi nhận hiện đang trống.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
            <div style="margin-top: 20px; display: flex; gap: 8px; justify-content: center;">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&page=<?= $i ?>" style="padding: 6px 12px; text-decoration: none; border-radius: 4px; <?= ($page == $i) ? 'background-color: #3498db; color: #fff; font-weight: bold;' : 'background-color: #fff; color: #333; border: 1px solid #ddd;' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL CHI TIẾT LOG -->
    <div id="logDetailModal" class="modal log-modal-wrap">
        <div class="modal-content log-modal-content">
            <div class="log-modal-header">
                <h3><i class="fa fa-history"></i> Chi tiết Thao tác #<span id="modal-log-id"></span></h3>
                <span class="btn-close-modal" onclick="$('#logDetailModal').fadeOut()">&times;</span>
            </div>
            
            <div class="log-modal-body">
                <table class="log-info-table">
                    <tr>
                        <td class="log-info-td-label"><b>Người thực hiện:</b></td>
                        <td class="log-info-td" id="modal-log-admin"></td>
                        <td class="log-info-td-time-label"><b>Thời gian:</b></td>
                        <td class="log-info-td" id="modal-log-time"></td>
                    </tr>
                    <tr>
                        <td class="log-info-td-label"><b>Hành động:</b></td>
                        <td class="log-info-td" id="modal-log-action"></td>
                        <td class="log-info-td-label"><b>File xử lý:</b></td>
                        <td class="log-info-td"><code class="log-code-tag" id="modal-log-file"></code></td>
                    </tr>
                    <tr>
                        <td class="log-info-td-label"><b>Mô tả nội dung:</b></td>
                        <td class="log-info-td" colspan="3" id="modal-log-desc"></td>
                    </tr>
                </table>
                
                <div class="diff-row">
                    <div class="diff-col" id="col-old-data" style="display:none;">
                        <div class="diff-title log-old-data-title"><i class="fa fa-minus-circle"></i> Dữ liệu cũ (Trước thay đổi)</div>
                        <div class="log-details-box" id="modal-log-old" style="border-left: 3px solid #e74c3c; background:#fff;"></div>
                    </div>
                    <div class="diff-col" id="col-new-data" style="display:none;">
                        <div class="diff-title log-new-data-title"><i class="fa fa-plus-circle"></i> Dữ liệu mới (Sau thay đổi)</div>
                        <div class="log-details-box" id="modal-log-new" style="border-left: 3px solid #27ae60; background:#fff;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script src="../assets/js/admin_main.js"></script>
</body>
</html>
