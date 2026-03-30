<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Truy vấn danh sách lịch sử (JOIN với users để lấy tên Admin)
$sql = "SELECT l.*, u.username as admin_name 
        FROM admin_logs l 
        LEFT JOIN users u ON l.admin_id = u.id 
        ORDER BY l.id DESC LIMIT 200";
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
                            
                            // Map đường dẫn file thành Tên trang dễ hiểu
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
                            $row['display_page'] = $display_page; // Lưu lại để JS dùng cho Popup
                        ?>
                        <tr>
                            <td>#<?= $row['id'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
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
            
            <div class="log-modal-footer">
                <button onclick="$('#logDetailModal').fadeOut()" class="adm-btn">Đóng</button>
            </div>
        </div>
    </div>

<script src="../assets/js/admin.js"></script>
</body>
</html>
