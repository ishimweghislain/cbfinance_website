<?php
require_once __DIR__ . '/app.cbfinance.rw/config/database.php';
$conn = getConnection();
$id = 260; // Peter
$res = $conn->query("SELECT total_paid, total_principal_paid, total_interest_paid, total_management_fees_paid FROM loan_portfolio WHERE loan_id = $id");
$loan = $res->fetch_assoc();
echo "Loan 260 Portfolio Summary:\n";
print_r($loan);

$res = $conn->query("SELECT SUM(paid_amount) as sum_paid, SUM(penalty_paid) as sum_penalties FROM loan_instalments WHERE loan_id = $id");
$inst = $res->fetch_assoc();
echo "\nInstalment Sums:\n";
print_r($inst);

echo "\nTotal Paid (from portfolio) vs Sum of paid columns:\n";
$calc_total = $loan['total_principal_paid'] + $loan['total_interest_paid'] + $loan['total_management_fees_paid'];
echo "Sum of P+I+F: $calc_total\n";
echo "Difference (should be penalties): " . ($loan['total_paid'] - $calc_total) . "\n";
