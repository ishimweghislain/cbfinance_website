<?php
require_once __DIR__ . '/app.cbfinance.rw/config/database.php';
$conn = getConnection();

echo "Table: loan_portfolio stats (Active/Performing):\n";
$res = $conn->query("SELECT SUM(principal_outstanding) as p_out, SUM(interest_outstanding) as i_out, SUM(total_outstanding) as t_out FROM loan_portfolio WHERE loan_status IN ('Active', 'Performing')");
print_r($res->fetch_assoc());

echo "\nSample Loan Data:\n";
$res = $conn->query("SELECT loan_number, principal_outstanding, interest_outstanding, total_outstanding FROM loan_portfolio WHERE loan_status IN ('Active', 'Performing') LIMIT 5");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
