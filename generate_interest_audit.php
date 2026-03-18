<?php
require_once __DIR__ . '/app.cbfinance.rw/config/database.php';
$conn = getConnection();

// Fetch all ledger entries for 4101 and 4201
$ledger_sql = "SELECT account_code, credit_amount, debit_amount, narration FROM ledger WHERE account_code IN ('4101', '4201')";
$ledger_res = $conn->query($ledger_sql);
$ledger_entries = [];
while ($l = $ledger_res->fetch_assoc()) {
    $ledger_entries[] = $l;
}

// Then get all loans
$loan_sql = "
    SELECT lp.loan_id, lp.loan_number, lp.loan_amount, lp.interest_rate, lp.management_fee_rate, lp.loan_status, c.customer_name, c.customer_code
    FROM loan_portfolio lp
    JOIN customers c ON lp.customer_id = c.customer_id
    ORDER BY lp.loan_number ASC
";
$loan_result = $conn->query($loan_sql);
$rows = [];
$total_interest = 0;
$total_fee = 0;

while ($loan = $loan_result->fetch_assoc()) {
    $interest = 0;
    $fee = 0;
    $loan_number = $loan['loan_number'];
    $customer_code = $loan['customer_code'];
    
    // Find all ledger entries that belong to this loan
    foreach ($ledger_entries as $entry) {
        $narration = $entry['narration'];
        // Check if narration contains the exact loan number OR the exact customer code (e.g., 'Accruals for C0060')
        if (strpos($narration, $loan_number) !== false || (!empty($customer_code) && preg_match('/\b' . preg_quote($customer_code, '/') . '\b/i', $narration))) {
            $amount = floatval($entry['credit_amount']) - floatval($entry['debit_amount']);
            if ($entry['account_code'] === '4101') {
                $interest += $amount;
            } elseif ($entry['account_code'] === '4201') {
                $fee += $amount;
            }
        }
    }
    
    $loan['interest_paid'] = $interest;
    $loan['fee_paid']      = $fee;
    $rows[] = $loan;
    $total_interest += $interest;
    $total_fee      += $fee;
}

// Find all left-over ledger entries that didn't match any existing loan
$deleted_loans = [];
foreach ($ledger_entries as $entry) {
    $matched = false;
    $narration = $entry['narration'];
    foreach ($rows as $loan) {
        $loan_number = $loan['loan_number'];
        $customer_code = $loan['customer_code'];
        if (strpos($narration, $loan_number) !== false || (!empty($customer_code) && preg_match('/\b' . preg_quote($customer_code, '/') . '\b/i', $narration))) {
            $matched = true;
            break;
        }
    }
    
    if (!$matched) {
        if (!isset($deleted_loans[$narration])) {
            $deleted_loans[$narration] = ['interest' => 0, 'fee' => 0];
        }
        $amount = floatval($entry['credit_amount']) - floatval($entry['debit_amount']);
        if ($entry['account_code'] === '4101') {
            $deleted_loans[$narration]['interest'] += $amount;
            $total_interest += $amount;
        } elseif ($entry['account_code'] === '4201') {
            $deleted_loans[$narration]['fee'] += $amount;
            $total_fee += $amount;
        }
    }
}

$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Interest & Fee Audit (Ledger Recon)</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; font-size: 13px; }
h2 { color: #2c3e50; }
.summary { background: #eaf4fb; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #3498db; }
table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
th { background: #2c3e50; color: white; padding: 10px; text-align: left; position: sticky; top: 0; }
td { padding: 8px 10px; border-bottom: 1px solid #ddd; }
tr:nth-child(even) { background: #f9f9f9; }
tr:hover { background: #eaf4fb; }
.zero { color: #e74c3c; font-weight: bold; }
.amount { text-align: right; font-family: monospace; }
tfoot td { background: #2c3e50; color: white; font-weight: bold; padding: 10px; }
.section-header { background: #34495e; color: white; font-weight: bold; padding: 10px; margin-top: 20px; }
</style>
</head>
<body>
<h2>Ledger Reconciliation: Interest &amp; Management Fee</h2>
<div class="summary">
    <strong>Active/Closed Loans Found:</strong> ' . count($rows) . ' &nbsp;&nbsp;|&nbsp;&nbsp;
    <strong>Total Interest Gained (Ledger Match):</strong> ' . number_format($total_interest, 2) . ' FRW &nbsp;&nbsp;|&nbsp;&nbsp;
    <strong>Total Management Fee Gained (Ledger Match):</strong> ' . number_format($total_fee, 2) . ' FRW
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
$active_int = 0;
$active_fee = 0;
foreach ($rows as $row) {
    $int = $row['interest_paid'];
    $fee = $row['fee_paid'];
    $active_int += $int;
    $active_fee += $fee;
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

$html .= '<tr class="section-header">
    <td colspan="7">SUBTOTAL (Current Loans)</td>
    <td class="amount">' . number_format($active_int, 2) . ' FRW</td>
    <td class="amount">' . number_format($active_fee, 2) . ' FRW</td>
</tr>';

$html .= '<tr style="background: #e74c3c; color: white;"><td colspan="9" style="text-align: center; font-weight: bold; padding: 10px;">DELETED / UNMAPPED LOANS (Found in Ledger but missing in System)</td></tr>';

$deleted_int = 0;
$deleted_fee = 0;
foreach ($deleted_loans as $narration => $amounts) {
    if ($amounts['interest'] == 0 && $amounts['fee'] == 0) continue;
    $deleted_int += $amounts['interest'];
    $deleted_fee += $amounts['fee'];
    $html .= '<tr>
        <td colspan="7" style="color: #c0392b;">' . htmlspecialchars($narration) . '</td>
        <td class="amount">' . number_format($amounts['interest'], 2) . '</td>
        <td class="amount">' . number_format($amounts['fee'], 2) . '</td>
    </tr>';
}

$html .= '<tr class="section-header">
    <td colspan="7">SUBTOTAL (Deleted Loans)</td>
    <td class="amount">' . number_format($deleted_int, 2) . ' FRW</td>
    <td class="amount">' . number_format($deleted_fee, 2) . ' FRW</td>
</tr>';


$html .= '</tbody>
<tfoot>
<tr>
    <td colspan="7">GRAND TOTAL (Exact Ledger Match)</td>
    <td class="amount">' . number_format($total_interest, 2) . ' FRW</td>
    <td class="amount">' . number_format($total_fee, 2) . ' FRW</td>
</tr>
</tfoot>
</table>
</body>
</html>';

file_put_contents(__DIR__ . '/interest_audit_report.html', $html);
echo "Done! " . count($rows) . " loans mapped + " . count($deleted_loans) . " deleted records. Total Interest = " . number_format($total_interest,2) . " | Total Fee = " . number_format($total_fee,2);
$conn->close();
?>
