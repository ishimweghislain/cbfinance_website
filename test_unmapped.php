<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once __DIR__ . '/app.cbfinance.rw/config/database.php';
$conn = getConnection();
$ledger_sql = "SELECT account_code, credit_amount, debit_amount, narration FROM ledger WHERE account_code IN ('4101', '4201') AND (credit_amount - debit_amount) > 0";
$ledger_res = $conn->query($ledger_sql);
$unmapped = [];
$total_ledger = 0;
while ($l = $ledger_res->fetch_assoc()) {
    $total_ledger += ($l['credit_amount'] - $l['debit_amount']);
    $unmapped[] = $l;
}

$loan_sql = "SELECT lp.loan_number, c.customer_code FROM loan_portfolio lp JOIN customers c ON lp.customer_id = c.customer_id";
$loan_res = $conn->query($loan_sql);
$loans = [];
while ($l = $loan_res->fetch_assoc()) {
    $loans[] = $l;
}

$groups = [];
foreach ($unmapped as $entry) {
    $matched = false;
    foreach ($loans as $loan) {
        if (strpos($entry['narration'], $loan['loan_number']) !== false || (!empty($loan['customer_code']) && preg_match('/\b' . preg_quote($loan['customer_code'], '/') . '\b/i', $entry['narration']))) {
            $matched = true;
            break; 
        }
    }
    if (!$matched) {
        $n = $entry['narration'];
        if(!isset($groups[$n])) $groups[$n] = 0;
        $groups[$n] += ($entry['credit_amount'] - $entry['debit_amount']);
    }
}
arsort($groups);
echo "Top Unmapped:\n";
$i=0;
foreach($groups as $k => $v) {
    if($i++ > 15) break;
    echo "$k | $v\n";
}
