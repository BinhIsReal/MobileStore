<?php
include '../config/db.php';
include_once '../includes/flash_sale_helper.php';
header('Content-Type: application/json');

$keyword = $_GET['q'] ?? '';
$keyword = trim($keyword);

if (strlen($keyword) < 2) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id, name, price, sale_price, image FROM products WHERE name LIKE ? LIMIT 5";
$stmt = $conn->prepare($sql);
$param = "%$keyword%";
$stmt->bind_param("s", $param);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $price_info = get_effective_price($conn, (int)$row['id'], $row['price'], $row['sale_price']);
    $row['price'] = $price_info['effective_price']; 
    $products[] = $row;
}

echo json_encode($products);
?>