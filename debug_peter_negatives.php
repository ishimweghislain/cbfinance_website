<?php
require_once __DIR__ . '/app.cbfinance.rw/config/database.php';
$conn = getConnection();

$loan_id = 260; // Peter
echo "--- Peter Loan Detail ---\n";
$res = $conn->query("SELECT * FROM loan_portfolio WHERE loan_id = $loan_id");
print_r($res->fetch_assoc());

echo "\n--- Peter Installments ---\n";
$res = $conn->query("SELECT * FROM loan_instalments WHERE loan_id = $loan_id ORDER BY instalment_number ASC");
while($row = $res->fetch_assoc()) {
    echo "Inst #{$row['instalment_number']}: Principal Due: {$row['principal_amount']}, Principal Paid: {$row['principal_paid']}, Bal Rem: {$row['balance_remaining']}\n";
}
