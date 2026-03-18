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
$report_title = "Financial Report";
if ($report_type == 'trial_balance') $report_title = "Trial Balance";
elseif ($report_type == 'balance_sheet') $report_title = "Balance Sheet";
elseif ($report_type == 'income_statement') $report_title = "Income Statement";
elseif ($report_type == 'income_analysis') $report_title = "Income Analysis (By Customer)";

// Helper functions (exact copy from financial_report.php)
function formatMoney($amount, $decimals = 0) {
    return number_format(round($amount, $decimals), $decimals, '.', ',');
}

function roundAmount($amount, $decimals = 2) {
    return round($amount, $decimals);
}

// ---------------------------------------------------------
// DATA CALCULATION
// ---------------------------------------------------------
if ($report_type == 'income_analysis') {
    $report_data = [];
    $analysis_sql = "SELECT 
        lp.loan_id, lp.loan_number, c.customer_name,
        SUM(CASE WHEN li.payment_date BETWEEN '$start_date' AND '$query_end_date' THEN li.interest_paid ELSE 0 END) as period_interest_paid,
        SUM(CASE WHEN li.payment_date BETWEEN '$start_date' AND '$query_end_date' THEN li.management_fee_paid ELSE 0 END) as period_fee_paid,
        SUM(CASE WHEN li.payment_date BETWEEN '$start_date' AND '$query_end_date' THEN li.penalty_paid ELSE 0 END) as period_penalty_paid,
        SUM(li.interest_paid) as total_interest_paid,
        SUM(li.management_fee_paid) as total_fee_paid,
        SUM(li.penalty_paid) as total_penalty_paid,
        SUM(li.interest_amount) as total_interest_exp,
        SUM(li.management_fee) as total_fee_exp
        FROM loan_portfolio lp
        JOIN customers c ON lp.customer_id = c.customer_id
        JOIN loan_instalments li ON lp.loan_id = li.loan_id
        GROUP BY lp.loan_id
        ORDER BY c.customer_name";
    $analysis_res = mysqli_query($conn, $analysis_sql);
    while ($row = mysqli_fetch_assoc($analysis_res)) {
        $report_data[] = $row;
    }
} else {
    // Other reports use Trial Data
    function getTrialData($conn, $start_date, $end_date, $query_end_date) {
        $trial_data = [];
        $accounts_sql = "SELECT account_code, account_name, class, normal_balance 
                        FROM chart_of_accounts WHERE is_active = 1 ORDER BY class, account_code";
        $accounts_result = mysqli_query($conn, $accounts_sql);
        
        while ($account = mysqli_fetch_assoc($accounts_result)) {
            $account_code = $account['account_code'];
            $account_name = $account['account_name'];
            $class = $account['class'];
            
            // CUSTOM LOGIC: Paid-Basis
            if (in_array($account_code, ['4101', '4201', '4202', '4205'])) {
                $col_paid = '';
                if ($account_code === '4101') $col_paid = 'interest_paid';
                elseif ($account_code === '4201') $col_paid = 'management_fee_paid';
                elseif ($account_code === '4205') $col_paid = 'penalty_paid';
                
                if ($col_paid) {
                    $res_open = mysqli_query($conn, "SELECT SUM($col_paid) as op FROM loan_instalments WHERE payment_date < '$start_date'");
                    $row_open = mysqli_fetch_assoc($res_open);
                    $initial_balance = -roundAmount(floatval($row_open['op'] ?? 0));
                    $res_move = mysqli_query($conn, "SELECT SUM($col_paid) as mp FROM loan_instalments WHERE payment_date BETWEEN '$start_date' AND '$query_end_date'");
                    $row_move = mysqli_fetch_assoc($res_move);
                    $period_debit = 0; $period_credit = roundAmount(floatval($row_move['mp'] ?? 0));
                } else {
                    $res_open = mysqli_query($conn, "SELECT SUM(credit_amount - debit_amount) as op FROM ledger WHERE account_code = '$account_code' AND transaction_date < '$start_date'");
                    $row_open = mysqli_fetch_assoc($res_open);
                    $initial_balance = -roundAmount(floatval($row_open['op'] ?? 0));
                    $res_move = mysqli_query($conn, "SELECT SUM(debit_amount) as d, SUM(credit_amount) as c FROM ledger WHERE account_code = '$account_code' AND transaction_date BETWEEN '$start_date' AND '$query_end_date'");
                    $row_move = mysqli_fetch_assoc($res_move);
                    $period_debit = roundAmount(floatval($row_move['d'] ?? 0));
                    $period_credit = roundAmount(floatval($row_move['c'] ?? 0));
                }
                $closing_balance = $initial_balance + $period_debit - $period_credit;
            } else {
                // Standard Logic
                $res_open = mysqli_query($conn, "SELECT SUM(debit_amount) as d, SUM(credit_amount) as c FROM ledger WHERE account_code = '$account_code' AND transaction_date < '$start_date'");
                $row_open = mysqli_fetch_assoc($res_open);
                $initial_balance = roundAmount(floatval($row_open['d'] ?? 0) - floatval($row_open['c'] ?? 0));
                $res_move = mysqli_query($conn, "SELECT SUM(debit_amount) as d, SUM(credit_amount) as c FROM ledger WHERE account_code = '$account_code' AND transaction_date BETWEEN '$start_date' AND '$query_end_date'");
                $row_move = mysqli_fetch_assoc($res_move);
                $period_debit = roundAmount(floatval($row_move['d'] ?? 0));
                $period_credit = roundAmount(floatval($row_move['c'] ?? 0));
                $closing_balance = $initial_balance + $period_debit - $period_credit;
            }
            $trial_data[] = ['account_code' => $account_code, 'account_name' => $account_name, 'class' => $class, 'initial_balance' => $initial_balance, 'period_debit' => $period_debit, 'period_credit' => $period_credit, 'closing_balance' => $closing_balance];
        }
        return $trial_data;
    }
    $trial_data = getTrialData($conn, $start_date, $end_date, $query_end_date);
    
    // Earnings
    $tr = 0; $tx = 0;
    foreach ($trial_data as $acc) {
        if (substr($acc['account_code'], 0, 1) == '4') $tr += abs($acc['closing_balance']);
        if (in_array(substr($acc['account_code'], 0, 1), ['5', '6'])) $tx += $acc['closing_balance'];
    }
    $earnings = roundAmount($tr - $tx);
    $res_pl = mysqli_query($conn, "SELECT SUM(CASE WHEN SUBSTRING(account_code, 1, 1) = '4' THEN credit_amount - debit_amount ELSE 0 END) as r, SUM(CASE WHEN SUBSTRING(account_code, 1, 1) IN ('5', '6') THEN debit_amount - credit_amount ELSE 0 END) as e FROM ledger WHERE transaction_date <= '$query_end_date'");
    $pl_row = mysqli_fetch_assoc($res_pl);
    $retained = roundAmount(floatval($pl_row['r'] ?? 0) - floatval($pl_row['e'] ?? 0) - $earnings);
    $trial_data[] = ['account_code' => '3102', 'account_name' => 'Retained Earnings', 'class' => 'Equity', 'closing_balance' => -$retained];
    $trial_data[] = ['account_code' => '3101', 'account_name' => 'Current Period Earnings/Loss', 'class' => 'Equity', 'closing_balance' => -$earnings];
}

// ---------------------------------------------------------
// PDF GENERATION
// ---------------------------------------------------------
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'CAPITAL BRIDGE FINANCE', 0, 1, 'C');
        $this->SetFont('helvetica', '', 9);
        $this->Cell(0, 5, 'Accounting & Loan Management System', 0, 1, 'C');
        $this->Line(15, $this->GetY() + 2, 195, $this->GetY() + 2);
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages() . ' | Generated on ' . date('d/m/Y H:i'), 0, 0, 'C');
    }
}

$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(15, 30, 15);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->AddPage();

$html = '<h2 align="center">' . $report_title . '</h2>';
$html .= '<p align="center">Period: ' . date('d/m/Y', strtotime($start_date)) . ' to ' . date('d/m/Y', strtotime($end_date)) . '</p><br>';

if ($report_type == 'income_analysis') {
    $pdf->SetFont('helvetica', '', 8);
    $html .= '<table border="1" cellpadding="4">
    <thead>
        <tr bgcolor="#333" color="#fff">
            <th width="20%">Customer / Loan</th>
            <th width="11%" align="right">Paid Int (P)</th>
            <th width="11%" align="right">Paid Fee (P)</th>
            <th width="11%" align="right">Paid Pen (P)</th>
            <th width="15%" align="right">Total Int Paid</th>
            <th width="15%" align="right">Total Fee Paid</th>
            <th width="17%" align="right">Int Left</th>
        </tr>
    </thead><tbody>';
    $t_pi = 0; $t_pm = 0; $t_pp = 0; $t_ti = 0; $t_tm = 0;
    foreach ($report_data as $row) {
        $rem_i = $row['total_interest_exp'] - $row['total_interest_paid'];
        $t_pi += $row['period_interest_paid']; $t_pm += $row['period_fee_paid']; $t_pp += $row['period_penalty_paid'];
        $t_ti += $row['total_interest_paid']; $t_tm += $row['total_fee_paid'];
        $html .= '<tr>
            <td><b>' . $row['customer_name'] . '</b><br><small>' . $row['loan_number'] . '</small></td>
            <td align="right">' . formatMoney($row['period_interest_paid']) . '</td>
            <td align="right">' . formatMoney($row['period_fee_paid']) . '</td>
            <td align="right">' . formatMoney($row['period_penalty_paid']) . '</td>
            <td align="right"><b>' . formatMoney($row['total_interest_paid']) . '</b></td>
            <td align="right"><b>' . formatMoney($row['total_fee_paid']) . '</b></td>
            <td align="right" color="red">' . formatMoney($rem_i) . '</td>
        </tr>';
    }
    $html .= '</tbody>
    <tfoot bgcolor="#eee">
        <tr>
            <td><b>TOTALS</b></td>
            <td align="right"><b>'.formatMoney($t_pi).'</b></td>
            <td align="right"><b>'.formatMoney($t_pm).'</b></td>
            <td align="right"><b>'.formatMoney($t_pp).'</b></td>
            <td align="right"><b>'.formatMoney($t_ti).'</b></td>
            <td align="right"><b>'.formatMoney($t_tm).'</b></td>
            <td align="right"><b>---</b></td>
        </tr>
    </tfoot></table>';
} elseif ($report_type == 'trial_balance') {
    $pdf->SetFont('helvetica', '', 9);
    $html .= '<table border="1" cellpadding="4">
    <thead><tr bgcolor="#333" color="#fff"><th>Account</th><th align="right">Initial</th><th align="right">Debit</th><th align="right">Credit</th><th align="right">Final</th></tr></thead><tbody>';
    foreach ($trial_data as $row) {
        if ($row['closing_balance'] == 0 && $row['period_debit'] == 0) continue;
        $html .= '<tr><td>[' . $row['account_code'] . '] ' . $row['account_name'] . '</td><td align="right">' . formatMoney($row['initial_balance']) . '</td><td align="right">' . formatMoney($row['period_debit']) . '</td><td align="right">' . formatMoney($row['period_credit']) . '</td><td align="right">' . formatMoney($row['closing_balance']) . '</td></tr>';
    }
    $html .= '</tbody></table>';
} else {
    // Balance Sheet or Income Statement
    $html .= '<table border="1" cellpadding="6"><tbody>';
    foreach ($trial_data as $row) {
        $digit = substr($row['account_code'],0,1);
        $show = false;
        if ($report_type == 'balance_sheet' && in_array($digit, ['1','2','3'])) $show = true;
        if ($report_type == 'income_statement' && in_array($digit, ['4','5','6'])) $show = true;
        if ($show) {
            $html .= '<tr><td>' . $row['account_name'] . '</td><td align="right"><b>' . formatMoney(abs($row['closing_balance'])) . '</b></td></tr>';
        }
    }
    $html .= '</tbody></table>';
}

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output(str_replace(' ','_',$report_title) . '_' . date('Ymd') . '.pdf', 'D'); // 'D' for immediate download
