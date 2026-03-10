<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_NAME'] = 'localhost';
require_once 'config/database.php';
$conn = getConnection();

echo "--- LOAN_INSTALMENTS TABLE ---\n";
$res = $conn->query("DESC loan_instalments");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
