<?php 
session_start(); 
include '../config/db.php';
include_once '../includes/security.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../login.php"); exit(); 
}

// --- TRUY VẤN DỮ LIỆU THỰC TẾ ---
$pending_count = $conn->query("SELECT COUNT(*) as t FROM orders WHERE status = 'pending'")->fetch_assoc()['t'];
$revenue_month_res = $conn->query("SELECT SUM(total_price - discount_amount) as t FROM orders WHERE status = 'completed' AND MONTH(created_at) = MONTH(NOW())");
$revenue_month = $revenue_month_res ? ($revenue_month_res->fetch_assoc()['t'] ?? 0) : 0;
$user_count = $conn->query("SELECT COUNT(*) as t FROM users WHERE role = 'user'")->fetch_assoc()['t'];

// Dữ liệu Doanh thu chi tiết (Trong tháng) 
// để show popup modal
$rev_cod = [];
$rev_bank = [];
$rev_detail_res = $conn->query("
    SELECT id, order_code, created_at, payment_method, (total_price - discount_amount) as final_price, name 
    FROM orders 
    WHERE status = 'completed' AND MONTH(created_at) = MONTH(NOW())
    ORDER BY created_at DESC
");
if ($rev_detail_res && $rev_detail_res->num_rows > 0) {
    while($row = $rev_detail_res->fetch_assoc()) {
        if ($row['payment_method'] == 'banking') {
            $rev_bank[] = $row;
        } else {
            $rev_cod[] = $row;
        }
    }
}

// Dữ liệu 7 ngày gần nhất
$days = []; $revenues = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[] = date('d/m', strtotime($d));
    $daily = $conn->query("SELECT SUM(total_price) as t FROM orders WHERE status = 'completed' AND DATE(created_at) = '$d'")->fetch_assoc();
    $revenues[] = $daily['t'] ?? 0;
}

// Tỉ lệ trạng thái
$st_labels = []; $st_values = [];
$status_res = $conn->query("SELECT status, COUNT(*) as c FROM orders GROUP BY status");
while($r = $status_res->fetch_assoc()) {
    $st_labels[] = $r['status'];
    $st_values[] = $r['c'];
}

// Thống kê sản phẩm BÁN CHẠY (Top 5)
$top_products = $conn->query("
    SELECT p.id, p.name, p.image, SUM(od.quantity) as total_sold 
    FROM order_details od 
    JOIN products p ON od.product_id = p.id 
    JOIN orders o ON od.order_id = o.id
    WHERE o.status = 'completed'
    GROUP BY p.id 
    ORDER BY total_sold DESC 
    LIMIT 5
");

// Thống kê sản phẩm BÁN CHẬM (Top 5)
$worst_products = $conn->query("
    SELECT p.id, p.name, p.image, COALESCE(SUM(od.quantity), 0) as total_sold 
    FROM products p 
    LEFT JOIN order_details od ON p.id = od.product_id
    LEFT JOIN orders o ON od.order_id = o.id AND o.status = 'completed'
    GROUP BY p.id 
    ORDER BY total_sold ASC 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Dashboard Thống Kê</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="admin-content">
            <h2 class="dashboard-report-title">Báo cáo kinh doanh</h2>

            <div class="dashboard-grid">
                <div class="stat-card" onclick="window.location.href='orders.php?status=pending'" style="cursor: pointer; transition: 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'" title="Xem danh sách đơn chờ xử lý">
                    <div class="stat-icon"><i class="fa fa-clock"></i></div>
                    <div class="stat-info">
                        <h3><?= $pending_count ?></h3>
                        <p>Chờ xử lý (Xem <i class="fa fa-arrow-right" style="font-size:10px;"></i>)</p>
                    </div>
                </div>
                <div class="stat-card stat-card-revenue" onclick="$('#revenueDetailModal').fadeIn()" style="cursor: pointer; transition: 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'" title="Nhấn để xem chi tiết">
                    <div class="stat-icon"><i class="fa fa-coins"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($revenue_month / 1000000, 1) ?>M</h3>
                        <p>Doanh thu tháng (Chi tiết <i class="fa fa-external-link-alt" style="font-size:10px;"></i>)</p>
                    </div>
                </div>
                <div class="stat-card stat-card-customers" onclick="window.location.href='customers.php'" style="cursor: pointer; transition: 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'" title="Quản lý khách hàng">
                    <div class="stat-icon"><i class="fa fa-user-tag"></i></div>
                    <div class="stat-info">
                        <h3><?= $user_count ?></h3>
                        <p>Khách hàng (Xem <i class="fa fa-arrow-right" style="font-size:10px;"></i>)</p>
                    </div>
                </div>
            </div>

            <div class="chart-row">
                <div class="chart-container">
                    <div class="chart-title"><i class="fa fa-line-chart"></i> Doanh thu 7 ngày</div>
                    <canvas id="revenueChart"></canvas>
                </div>
                <div class="chart-container">
                    <div class="chart-title"><i class="fa fa-pie-chart"></i> Trạng thái đơn</div>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- THỐNG KÊ SẢN PHẨM BÁN CHẠY & BÁN CHẬM -->
            <div class="chart-row chart-row-products">
                <!-- Cột Bán Chạy -->
                <div class="chart-container chart-col-flex">
                    <div class="chart-title"><i class="fa fa-fire icon-fire"></i> Top 5 Sản Phẩm Bán Chạy Nhất</div>
                    <table class="admin-table product-rank-table">
                        <thead>
                            <tr class="product-rank-thead-row">
                                <th class="product-rank-th">Hình ảnh</th>
                                <th class="product-rank-th">Tên sản phẩm</th>
                                <th class="product-rank-th product-rank-th-center">Đã Bán</th>
                            </tr>
                        </thead>
                        <tbody>
                                <?php if ($top_products && $top_products->num_rows > 0): ?>
                                    <?php while($row = $top_products->fetch_assoc()): ?>
                                    <tr class="product-rank-tbody-row">
                                        <td class="product-rank-td">
                                            <?php $img = (strpos($row['image'], 'http') === 0) ? $row['image'] : "../assets/img/" . $row['image']; ?>
                                            <img src="<?= $img ?>" class="product-rank-img" alt="">
                                        </td>
                                        <td class="product-rank-td product-rank-name"><?= htmlspecialchars($row['name']) ?></td>
                                        <td class="product-rank-td-center product-rank-sold-best"><?= $row['total_sold'] ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="product-rank-empty">Chưa có dữ liệu</td></tr>
                                <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Cột Bán Chậm -->
                <div class="chart-container chart-col-flex">
                    <div class="chart-title"><i class="fa fa-snowflake icon-snow"></i> Top 5 Sản Phẩm Bán Chậm Nhất</div>
                    <table class="admin-table product-rank-table">
                        <thead>
                            <tr class="product-rank-thead-row">
                                <th class="product-rank-th">Hình ảnh</th>
                                <th class="product-rank-th">Tên sản phẩm</th>
                                <th class="product-rank-th product-rank-th-center">Đã Bán</th>
                            </tr>
                        </thead>
                        <tbody>
                                <?php if ($worst_products && $worst_products->num_rows > 0): ?>
                                    <?php while($row = $worst_products->fetch_assoc()): ?>
                                    <tr class="product-rank-tbody-row">
                                        <td class="product-rank-td">
                                            <?php $img = (strpos($row['image'], 'http') === 0) ? $row['image'] : "../assets/img/" . $row['image']; ?>
                                            <img src="<?= $img ?>" class="product-rank-img" alt="">
                                        </td>
                                        <td class="product-rank-td product-rank-name"><?= htmlspecialchars($row['name']) ?></td>
                                        <td class="product-rank-td-center product-rank-sold-worst"><?= $row['total_sold'] ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="product-rank-empty">Chưa có dữ liệu</td></tr>
                                <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- MODAL DOANH THU CHI TIẾT -->
    <div id="revenueDetailModal" class="modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div class="modal-content" style="background:#fff; margin:5% auto; width:80%; max-width:900px; border-radius:8px; overflow:hidden; box-shadow:0 5px 15px rgba(0,0,0,0.3);">
            <div style="background:var(--primary); color:white; padding:15px 20px; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0;"><i class="fa fa-list-alt"></i> Chi tiết Doanh thu tháng</h3>
                <span style="font-size:24px; cursor:pointer;" onclick="$('#revenueDetailModal').fadeOut()">&times;</span>
            </div>
            
            <div style="padding:20px;">
                <!-- Tabs Menu -->
                <div style="display:flex; border-bottom:1px solid #ddd; margin-bottom:20px;">
                    <button id="btn-tab-cod" onclick="switchRevTab('cod')" style="flex:1; padding:10px; background:white; cursor:pointer; font-weight:bold; color:var(--primary); border:none; border-bottom:3px solid var(--primary); font-size:16px;">
                        <i class="fa fa-money-bill-wave"></i> Tiền mặt (<?= count($rev_cod) ?>)
                    </button>
                    <button id="btn-tab-bank" onclick="switchRevTab('bank')" style="flex:1; padding:10px; background:white; cursor:pointer; font-weight:bold; color:#777; border:none; border-bottom:3px solid transparent; font-size:16px;">
                        <i class="fa fa-university"></i> Chuyển khoản (<?= count($rev_bank) ?>)
                    </button>
                </div>

                <!-- Nội dung Tiền mặt -->
                <div id="tab-cod" style="display:block;">
                    <div style="max-height:400px; overflow-y:auto;">
                        <table class="admin-table">
                            <thead style="position: sticky; top: 0; background: #f4f6f9;">
                                <tr>
                                    <th>ID</th>
                                    <th>Thời gian</th>
                                    <th>Khách hàng</th>
                                    <th>Loại tiền</th>
                                    <th>Số tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($rev_cod) > 0): ?>
                                    <?php foreach($rev_cod as $r): ?>
                                        <tr>
                                            <td>#<?= $r['order_code'] ?? $r['id'] ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($r['name']) ?></td>
                                            <td><span style="background:#27ae60; color:white; padding:3px 8px; border-radius:4px; font-size:12px;">Tiền mặt</span></td>
                                            <td style="font-weight:bold; color:#d70018;"><?= number_format($r['final_price']) ?> đ</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align:center;">Chưa có dữ liệu</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Nội dung Chuyển khoản -->
                <div id="tab-bank" style="display:none;">
                    <div style="max-height:400px; overflow-y:auto;">
                        <table class="admin-table">
                            <thead style="position: sticky; top: 0; background: #f4f6f9;">
                                <tr>
                                    <th>ID</th>
                                    <th>Thời gian</th>
                                    <th>Khách hàng</th>
                                    <th>Loại tiền</th>
                                    <th>Số tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($rev_bank) > 0): ?>
                                    <?php foreach($rev_bank as $r): ?>
                                        <tr>
                                            <td>#<?= $r['order_code'] ?? $r['id'] ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($r['name']) ?></td>
                                            <td><span style="background:#2980b9; color:white; padding:3px 8px; border-radius:4px; font-size:12px;">Chuyển khoản</span></td>
                                            <td style="font-weight:bold; color:#d70018;"><?= number_format($r['final_price']) ?> đ</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align:center;">Chưa có dữ liệu</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="../assets/js/admin_main.js"></script>
    <script>
    const revLabels = <?= json_encode($days) ?>;
    const revData = <?= json_encode($revenues) ?>;
    const stLabels = <?= json_encode($st_labels) ?>;
    const stData = <?= json_encode($st_values) ?>;

    $(document).ready(function() {
        initDashboardCharts(revLabels, revData, stLabels, stData);
    });

    // Hàm chuyển Tab Doanh thu
    function switchRevTab(tab) {
        if(tab === 'cod') {
            $('#tab-cod').show();
            $('#tab-bank').hide();
            $('#btn-tab-cod').css({'color': 'var(--primary)', 'border-bottom': '3px solid var(--primary)'});
            $('#btn-tab-bank').css({'color': '#777', 'border-bottom': '3px solid transparent'});
        } else {
            $('#tab-bank').show();
            $('#tab-cod').hide();
            $('#btn-tab-bank').css({'color': 'var(--primary)', 'border-bottom': '3px solid var(--primary)'});
            $('#btn-tab-cod').css({'color': '#777', 'border-bottom': '3px solid transparent'});
        }
    }
    </script>
</body>

</html>