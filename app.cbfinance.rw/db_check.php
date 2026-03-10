<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_NAME'] = 'localhost';
require_once 'config/database.php';
$conn = getConnection();

echo "--- USERS TABLE ---\n";
$res = $conn->query("DESC users");
while($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\n--- TABLES ---\n";
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}
