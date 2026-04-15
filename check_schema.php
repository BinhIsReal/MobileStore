<?php
include 'config/db.php';
echo "=== products columns ===\n";
$r = $conn->query('SHOW COLUMNS FROM products');
while($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}
echo "\n=== categories columns ===\n";
$r2 = $conn->query('SHOW COLUMNS FROM categories');
while($row = $r2->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}
echo "\n=== brands columns ===\n";
$r3 = $conn->query('SHOW COLUMNS FROM brands');
while($row = $r3->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}
echo "\n=== Sample products ===\n";
$r4 = $conn->query('SELECT id, name, price, stock FROM products LIMIT 5');
while($row = $r4->fetch_assoc()) {
    print_r($row);
}
