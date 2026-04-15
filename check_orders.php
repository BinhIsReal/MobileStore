<?php
require 'config/db.php';
$tables = ['orders', 'order_items', 'order_status_history'];
foreach ($tables as $t) {
    $r = $conn->query("DESCRIBE $t");
    if ($r) {
        echo "=== $t ===\n";
        while ($row = $r->fetch_assoc()) {
            echo $row['Field'] . ' (' . $row['Type'] . ")\n";
        }
    } else {
        echo "Table $t not found\n";
    }
}
// Sample order
$r = $conn->query("SELECT * FROM orders LIMIT 1");
if ($r && $r->num_rows > 0) {
    echo "\n=== Sample order ===\n";
    print_r($r->fetch_assoc());
}
