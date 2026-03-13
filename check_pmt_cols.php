<?php
require_once __DIR__ . '/app.cbfinance.rw/config/database.php';
$conn = getConnection();
$res = $conn->query("DESC loan_payments");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
