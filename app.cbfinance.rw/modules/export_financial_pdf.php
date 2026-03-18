<?php
require_once __DIR__ . '/../config/database.php';
// Load TCPDF from vendor
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$conn = getConnection();

// Get report parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'trial_balance';
$start_date = !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Validate/Swap date range
if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}
$query_end_date = $end_date . ' 23:59:59';

// Setup Title
$report_title = ($report_type == 'balance_sheet') ? "Balance Sheet" : (($report_type == 'income_statement') ? "Income Statement" : "Trial Balance");

// Helper functions
function formatMoney($amount, $decimals = 0) {
    return number_format(round($amount, $decimals), $decimals, '.', ',');
}

function roundAmount($amount, $decimals = 2) {
    return round($amount, $decimals);
}

// ---------------------------------------------------------
// DATA CALCULATION (Exact sync with financial_report.php)
// ---------------------------------------------------------
function getTrialData($conn, $start_date, $end_date, $query_end_date) {
    $trial_data = [];
    $accounts_sql = "SELECT account_code, account_name, class, normal_balance 
                    FROM chart_of_accounts WHERE is_active = 1 ORDER BY class, account_code";
    $accounts_result = mysqli_query($conn, $accounts_sql);
    
    while ($account = mysqli_fetch_assoc($accounts_result)) {
        $account_code = $account['account_code'];
        $account_name = $account['account_name'];
        $class = $account['class'];
        
        // Custom Paid-Basis Logic for 4101 and 4201
        if ($account_code === '4101' || $account_code === '4201') {
            $col_paid = ($account_code === '4101') ? 'interest_paid' : 'management_fee_paid';
            
            // Initial Balance
            $res_open = mysqli_query($conn, "SELECT SUM($col_paid) as total_paid FROM loan_instalments WHERE payment_date < '$start_date'");
            $row_open = mysqli_fetch_assoc($res_open);
            $initial_balance = -roundAmount(floatval($row_open['total_paid'] ?? 0));
            
            // Period Movements
            $res_move = mysqli_query($conn, "SELECT SUM($col_paid) as period_paid FROM loan_instalments WHERE payment_date BETWEEN '$start_date' AND '$query_end_date'");
            $row_move = mysqli_fetch_assoc($res_move);
            $period_debit = 0;
            $period_credit = roundAmount(floatval($row_move['period_paid'] ?? 0));
            
            if ($account_code === '4201') {
                $res_extra = mysqli_query($conn, "SELECT SUM(credit_amount) as extra_credit FROM ledger WHERE account_code = '4201' AND reference_type NOT IN ('loan_payment', 'loan_prepayment') AND transaction_date BETWEEN '$start_date' AND '$query_end_date'");
                $row_extra = mysqli_fetch_assoc($res_extra);
                $period_credit += roundAmount(floatval($row_extra['extra_credit'] ?? 0));
                
                $res_extra_open = mysqli_query($conn, "SELECT SUM(credit_amount) as extra_credit FROM ledger WHERE account_code = '4201' AND reference_type NOT IN ('loan_payment', 'loan_prepayment') AND transaction_date < '$start_date'");
                $row_extra_open = mysqli_fetch_assoc($res_extra_open);
                $initial_balance -= roundAmount(floatval($row_extra_open['extra_credit'] ?? 0));
            }
            $closing_balance = $initial_balance + $period_debit - $period_credit;
        } else {
            // Standard Ledger Logic
            $res_open = mysqli_query($conn, "SELECT SUM(debit_amount) as d, SUM(credit_amount) as c FROM ledger WHERE account_code = '$account_code' AND transaction_date < '$start_date'");
            $row_open = mysqli_fetch_assoc($res_open);
            $initial_balance = roundAmount(floatval($row_open['d'] ?? 0) - floatval($row_open['c'] ?? 0));
            
            $res_move = mysqli_query($conn, "SELECT SUM(debit_amount) as d, SUM(credit_amount) as c FROM ledger WHERE account_code = '$account_code' AND transaction_date BETWEEN '$start_date' AND '$query_end_date'");
            $row_move = mysqli_fetch_assoc($res_move);
            $period_debit = roundAmount(floatval($row_move['d'] ?? 0));
            $period_credit = roundAmount(floatval($row_move['c'] ?? 0));
            $closing_balance = $initial_balance + $period_debit - $period_credit;
        }
        
        $trial_data[] = [
            'account_code' => $account_code,
            'account_name' => $account_name,
            'class' => $class,
            'initial_balance' => $initial_balance,
            'period_debit' => $period_debit,
            'period_credit' => $period_credit,
            'closing_balance' => $closing_balance
        ];
    }
    return $trial_data;
}

$trial_data = getTrialData($conn, $start_date, $end_date, $query_end_date);

// Calculate Totals for RE/Earnings
$rev = 0; $exp = 0;
foreach ($trial_data as $acc) {
    if (substr($acc['account_code'], 0, 1) == '4') $rev += abs($acc['closing_balance']);
    if (in_array(substr($acc['account_code'], 0, 1), ['5', '6'])) $exp += $acc['closing_balance'];
}
$earnings = roundAmount($rev - $exp);
$res_pl = mysqli_query($conn, "SELECT SUM(CASE WHEN SUBSTRING(account_code, 1, 1) = '4' THEN credit_amount - debit_amount ELSE 0 END) as r, SUM(CASE WHEN SUBSTRING(account_code, 1, 1) IN ('5', '6') THEN debit_amount - credit_amount ELSE 0 END) as e FROM ledger WHERE transaction_date <= '$query_end_date'");
$pl_row = mysqli_fetch_assoc($res_pl);
$retained = roundAmount(floatval($pl_row['r'] ?? 0) - floatval($pl_row['e'] ?? 0) - $earnings);

// Add RE/Earnings to trial_data
$trial_data[] = ['account_code' => '3102', 'account_name' => 'Retained Earnings', 'class' => 'Equity', 'closing_balance' => -$retained];
$trial_data[] = ['account_code' => '3101', 'account_name' => 'Current Period Earnings/Loss', 'class' => 'Equity', 'closing_balance' => -$earnings];

// ---------------------------------------------------------
// PDF GENERATION
// ---------------------------------------------------------
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'CAPITAL BRIDGE FINANCE', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, 'Accounting & Loan Management System', 0, 1, 'C');
        $this->Line(10, $this->GetY() + 3, 200, $this->GetY() + 3);
    }
}

$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Capital Bridge Finance');
$pdf->SetTitle($report_title);
$pdf->SetMargins(15, 30, 15);
$pdf->AddPage();

$html = '<h2>' . $report_title . '</h2>';
$html .= '<p>Period: ' . date('d/m/Y', strtotime($start_date)) . ' to ' . date('d/m/Y', strtotime($end_date)) . '</p>';

if ($report_type == 'trial_balance') {
    $html .= '<table border="1" cellpadding="4">
    <thead>
        <tr bgcolor="#f2f2f2">
            <th><b>Account</b></th>
            <th align="right"><b>Opening</b></th>
            <th align="right"><b>Debit</b></th>
            <th align="right"><b>Credit</b></th>
            <th align="right"><b>Closing</b></th>
        </tr>
    </thead><tbody>';
    foreach ($trial_data as $row) {
        if ($row['closing_balance'] == 0 && $row['period_debit'] == 0 && $row['period_credit'] == 0) continue;
        $html .= '<tr>
            <td>' . $row['account_code'] . ' ' . $row['account_name'] . '</td>
            <td align="right">' . formatMoney($row['initial_balance']) . '</td>
            <td align="right">' . formatMoney($row['period_debit']) . '</td>
            <td align="right">' . formatMoney($row['period_credit']) . '</td>
            <td align="right">' . formatMoney($row['closing_balance']) . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
} elseif ($report_type == 'balance_sheet') {
    $html .= '<h3>Assets</h3><table border="1" cellpadding="4"><tbody>';
    $ta = 0; $tl = 0; $te = 0;
    foreach ($trial_data as $row) {
        if (substr($row['account_code'], 0, 1) == '1') {
            $html .= '<tr><td>' . $row['account_name'] . '</td><td align="right">' . formatMoney($row['closing_balance']) . '</td></tr>';
            $ta += $row['closing_balance'];
        }
    }
    $html .= '<tr bgcolor="#eee"><td><b>Total Assets</b></td><td align="right"><b>' . formatMoney($ta) . '</b></td></tr></tbody></table>';
    
    $html .= '<h3>Liabilities & Equity</h3><table border="1" cellpadding="4"><tbody>';
    foreach ($trial_data as $row) {
        if (substr($row['account_code'], 0, 1) == '2') {
            $html .= '<tr><td>' . $row['account_name'] . '</td><td align="right">' . formatMoney(abs($row['closing_balance'])) . '</td></tr>';
            $tl += abs($row['closing_balance']);
        }
    }
    foreach ($trial_data as $row) {
        if (substr($row['account_code'], 0, 1) == '3') {
            $html .= '<tr><td>' . $row['account_name'] . '</td><td align="right">' . formatMoney(abs($row['closing_balance'])) . '</td></tr>';
            $te += abs($row['closing_balance']);
        }
    }
    $html .= '<tr bgcolor="#eee"><td><b>Total Liab & Equity</b></td><td align="right"><b>' . formatMoney($tl + $te) . '</b></td></tr></tbody></table>';
} elseif ($report_type == 'income_statement') {
    $html .= '<h3>Revenue</h3><table border="1" cellpadding="4"><tbody>';
    $tr = 0; $tx = 0;
    foreach ($trial_data as $row) {
        if (substr($row['account_code'], 0, 1) == '4') {
            $html .= '<tr><td>' . $row['account_name'] . '</td><td align="right">' . formatMoney(abs($row['closing_balance'])) . '</td></tr>';
            $tr += abs($row['closing_balance']);
        }
    }
    $html .= '<tr bgcolor="#eee"><td><b>Total Revenue</b></td><td align="right"><b>'.formatMoney($tr).'</b></td></tr></tbody></table>';
    
    $html .= '<h3>Expenses</h3><table border="1" cellpadding="4"><tbody>';
    foreach ($trial_data as $row) {
        if (in_array(substr($row['account_code'], 0, 1), ['5', '6'])) {
            $html .= '<tr><td>' . $row['account_name'] . '</td><td align="right">' . formatMoney($row['closing_balance']) . '</td></tr>';
            $tx += $row['closing_balance'];
        }
    }
    $html .= '<tr bgcolor="#eee"><td><b>Total Expenses</b></td><td align="right"><b>'.formatMoney($tx).'</b></td></tr></tbody></table>';
    $net = $tr - $tx;
    $html .= '<h3 style="color:' . ($net>=0?'green':'red') . '">Net ' . ($net>=0?'Income':'Loss') . ': ' . formatMoney(abs($net)) . '</h3>';
}

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('CB_Report_' . date('Ymd') . '.pdf', 'I');
