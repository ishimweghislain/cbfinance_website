<?php
$conn = new mysqli('localhost', 'root', '', 'cbfinance_accounting_loan_system');
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// First aggregate loan_payments by loan_id (fast - no big join)
$pay_sql = "
    SELECT loan_id, 
           SUM(interest_amount) AS interest_paid,
           SUM(monitoring_fee) AS fee_paid
    FROM loan_payments
    GROUP BY loan_id
";
$pay_result = $conn->query($pay_sql);
$payments = [];
while ($p = $pay_result->fetch_assoc()) {
    $payments[$p['loan_id']] = $p;
}

// Then get all loans
$loan_sql = "
    SELECT lp.loan_id, lp.loan_number, lp.loan_amount, lp.interest_rate, lp.management_fee_rate, lp.loan_status, c.customer_name
    FROM loan_portfolio lp
    JOIN customers c ON lp.customer_id = c.customer_id
    ORDER BY lp.loan_number ASC
";
$loan_result = $conn->query($loan_sql);
$rows = [];
$total_interest = 0;
$total_fee = 0;
while ($loan = $loan_result->fetch_assoc()) {
    $lid = $loan['loan_id'];
    $interest = isset($payments[$lid]) ? floatval($payments[$lid]['interest_paid']) : 0;
    $fee      = isset($payments[$lid]) ? floatval($payments[$lid]['fee_paid']) : 0;
    $loan['interest_paid'] = $interest;
    $loan['fee_paid']      = $fee;
    $rows[] = $loan;
    $total_interest += $interest;
    $total_fee      += $fee;
}

$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Interest & Fee Audit (Actual Cash Paid)</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; font-size: 13px; }
h2 { color: #2c3e50; }
.summary { background: #eaf4fb; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #3498db; }
table { border-collapse: collapse; width: 100%; }
th { background: #2c3e50; color: white; padding: 10px; text-align: left; position: sticky; top: 0; }
td { padding: 8px 10px; border-bottom: 1px solid #ddd; }
tr:nth-child(even) { background: #f9f9f9; }
tr:hover { background: #eaf4fb; }
.zero { color: #e74c3c; font-weight: bold; }
.amount { text-align: right; font-family: monospace; }
tfoot td { background: #2c3e50; color: white; font-weight: bold; padding: 10px; }
</style>
</head>
<body>
<h2>Actual Cash Paid: Interest &amp; Management Fee (All Loans)</h2>
<div class="summary">
    <strong>Total Loans:</strong> ' . count($rows) . ' &nbsp;&nbsp;|&nbsp;&nbsp;
    <strong>Total Interest Gained (Cash Paid):</strong> ' . number_format($total_interest, 2) . ' FRW &nbsp;&nbsp;|&nbsp;&nbsp;
    <strong>Total Management Fee Gained (Cash Paid):</strong> ' . number_format($total_fee, 2) . ' FRW
</div>
<table>
<thead>
<tr>
    <th>N</th>
    <th>Customer Name</th>
    <th>Loan Number</th>
    <th>Loan Amount (FRW)</th>
    <th>Status</th>
    <th>Int. Rate</th>
    <th>Fee Rate</th>
    <th class="amount">Interest Paid</th>
    <th class="amount">Management Fee Paid</th>
</tr>
</thead>
<tbody>';

$n = 1;
foreach ($rows as $row) {
    $int = $row['interest_paid'];
    $fee = $row['fee_paid'];
    $int_cls = $int == 0 ? ' class="zero"' : '';
    $fee_cls = $fee == 0 ? ' class="zero"' : '';
    $html .= '<tr>
        <td>' . $n++ . '</td>
        <td>' . htmlspecialchars($row['customer_name']) . '</td>
        <td>' . htmlspecialchars($row['loan_number']) . '</td>
        <td class="amount">' . number_format($row['loan_amount'], 2) . '</td>
        <td>' . htmlspecialchars($row['loan_status']) . '</td>
        <td>' . $row['interest_rate'] . '%</td>
        <td>' . $row['management_fee_rate'] . '%</td>
        <td class="amount"' . $int_cls . '>' . number_format($int, 2) . '</td>
        <td class="amount"' . $fee_cls . '>' . number_format($fee, 2) . '</td>
    </tr>';
}

$html .= '</tbody>
<tfoot>
<tr>
    <td colspan="7">GRAND TOTAL (' . count($rows) . ' loans)</td>
    <td class="amount">' . number_format($total_interest, 2) . ' FRW</td>
    <td class="amount">' . number_format($total_fee, 2) . ' FRW</td>
</tr>
</tfoot>
</table>
</body>
</html>';

file_put_contents(__DIR__ . '/interest_audit_report.html', $html);
echo "Done! " . count($rows) . " loans. Interest = " . number_format($total_interest,2) . " | Fee = " . number_format($total_fee,2);
$conn->close();
?>
