<?php
require 'config/db.php';
$res = $conn->query("SELECT id, name FROM products WHERE name LIKE '%sạc%' OR name LIKE '%cáp%' OR name LIKE '%pin%' LIMIT 20");
while($r = $res->fetch_assoc()) {
    echo $r['id'] . ' - ' . $r['name'] . "\n";
}
