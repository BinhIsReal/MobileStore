<?php 
session_start(); 
include '../config/db.php';

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
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Dashboard Thống Kê</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="admin-content">
            <h2 style="margin-bottom:25px;">Báo cáo kinh doanh</h2>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-clock"></i></div>
                    <div class="stat-info">
                        <h3><?= $pending_count ?></h3>
                        <p>Chờ xử lý</p>
                    </div>
                </div>
                <div class="stat-card" style="border-left-color:#27ae60">
                    <div class="stat-icon" style="color:#27ae60"><i class="fa fa-coins"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($revenue_month / 1000000, 1) ?>M</h3>
                        <p>Doanh thu tháng</p>
                    </div>
                </div>
                <div class="stat-card" style="border-left-color:#f39c12">
                    <div class="stat-icon" style="color:#f39c12"><i class="fa fa-user-tag"></i></div>
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