<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$filter   = $_GET['filter']   ?? 'month'; 
$date_val = $_GET['date_val'] ?? date('Y-m-d'); 

switch ($filter) {
    case 'day':
        $d           = date('Y-m-d', strtotime($date_val));
        $where_time  = "DATE(o.created_at) = '$d'";
        $period_label = date('d/m/Y', strtotime($d));
        break;

    case 'week':
        $monday  = date('Y-m-d', strtotime('monday this week', strtotime($date_val)));
        $sunday  = date('Y-m-d', strtotime('sunday this week', strtotime($date_val)));
        $where_time  = "DATE(o.created_at) BETWEEN '$monday' AND '$sunday'";
        $period_label = date('d/m/Y', strtotime($monday)) . ' – ' . date('d/m/Y', strtotime($sunday));
        break;

    case 'year':
        $y           = date('Y', strtotime($date_val));
        $where_time  = "YEAR(o.created_at) = $y";
        $period_label = "Năm $y";
        break;

    case 'month':
    default:
        $m           = date('m', strtotime($date_val));
        $y           = date('Y', strtotime($date_val));
        $where_time  = "MONTH(o.created_at) = $m AND YEAR(o.created_at) = $y";
        $period_label = "Tháng $m/$y";
        break;
}

$total_res = $conn->query("
    SELECT
        SUM(total_price - discount_amount) AS total_revenue,
        COUNT(*) AS total_orders
    FROM orders o
    WHERE status = 'completed' AND $where_time
")->fetch_assoc();

$orders_res = $conn->query("
    SELECT
        o.id,
        o.order_code,
        o.created_at,
        o.payment_method,
        o.name,
        (o.total_price - o.discount_amount) AS final_price
    FROM orders o
    WHERE o.status = 'completed' AND $where_time
    ORDER BY o.created_at DESC
");

$orders = [];
while ($row = $orders_res->fetch_assoc()) {
    $orders[] = $row;
}

$breakdown_res = $conn->query("
    SELECT
        DATE(o.created_at) AS day_date,
        SUM(o.total_price - o.discount_amount) AS day_revenue,
        COUNT(*) AS day_orders
    FROM orders o
    WHERE o.status = 'completed' AND $where_time
    GROUP BY DATE(o.created_at)
    ORDER BY day_date ASC
");

$breakdown = [];
while ($row = $breakdown_res->fetch_assoc()) {
    $breakdown[] = $row;
}

echo json_encode([
    'status'        => 'success',
    'period_label'  => $period_label,
    'total_revenue' => (float)($total_res['total_revenue'] ?? 0),
    'total_orders'  => (int)($total_res['total_orders']   ?? 0),
    'orders'        => $orders,
    'breakdown'     => $breakdown,
]);
