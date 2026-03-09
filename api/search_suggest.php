<?php
include '../config/db.php';
header('Content-Type: application/json');

$keyword = $_GET['q'] ?? '';
$keyword = trim($keyword);

if (strlen($keyword) < 2) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id, name, price, image FROM products WHERE name LIKE ? LIMIT 5";
$stmt = $conn->prepare($sql);
$param = "%$keyword%";
$stmt->bind_param("s", $param);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode($products);
?>