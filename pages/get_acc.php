<?php
require 'config/db.php';
$res = $conn->query("SELECT id, name FROM products WHERE category_id = 20");
while($r = $res->fetch_assoc()) {
    echo $r['id'] . ' - ' . $r['name'] . "\n";
}
