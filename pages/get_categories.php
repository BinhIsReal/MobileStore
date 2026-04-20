<?php
require 'config/db.php';
$res = $conn->query("SELECT id, name FROM categories");
while($r = $res->fetch_assoc()) {
    echo $r['id'] . ' - ' . $r['name'] . "\n";
}
