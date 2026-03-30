<?php 
session_start(); 
include '../config/db.php';
include_once '../includes/security.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../login.php"); exit(); 
}

// --- TRUY VẤN DỮ LIỆU THỰC TẾ ---
$pending_count = $conn->query("SELECT COUNT(*) as t FROM orders WHERE status = 'pending'")->fetch_assoc()['t'];
$revenue_month = $conn->query("SELECT SUM(total_price) as t FROM orders WHERE status = 'completed' AND MONTH(created_at) = MONTH(NOW())")->fetch_assoc()['t'] ?? 0;
$user_count = $conn->query("SELECT COUNT(*) as t FROM users WHERE role = 'user'")->fetch_assoc()['t'];

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
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-clock"></i></div>
                    <div class="stat-info">
                        <h3><?= $pending_count ?></h3>
                        <p>Chờ xử lý</p>
                    </div>
                </div>
                <div class="stat-card stat-card-revenue">
                    <div class="stat-icon"><i class="fa fa-coins"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($revenue_month / 1000000, 1) ?>M</h3>
                        <p>Doanh thu tháng</p>
                    </div>
                </div>
                <div class="stat-card stat-card-customers">
                    <div class="stat-icon"><i class="fa fa-user-tag"></i></div>
                    <div class="stat-info">
                        <h3><?= $user_count ?></h3>
                        <p>Khách hàng</p>
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

    <script src="../assets/js/admin_main.js"></script>
    <script>
    const revLabels = <?= json_encode($days) ?>;
    const revData = <?= json_encode($revenues) ?>;
    const stLabels = <?= json_encode($st_labels) ?>;
    const stData = <?= json_encode($st_values) ?>;

    $(document).ready(function() {
        initDashboardCharts(revLabels, revData, stLabels, stData);
    });
    </script>
</body>

</html>