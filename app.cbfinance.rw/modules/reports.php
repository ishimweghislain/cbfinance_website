<?php
require_once __DIR__ . '/../config/database.php';
$conn = getConnection();

$report_type     = $_GET['report_type']   ?? 'portfolio';
$start_date      = $_GET['start_date']    ?? '2025-01-01'; 
$end_date        = $_GET['end_date']      ?? date('Y-m-d');
$customer_filter = $_GET['customer_id']   ?? '';
$status_filter   = $_GET['status_filter'] ?? '';
$export_format   = $_GET['export_format'] ?? '';



// ─── CSV EXPORT — MUST RUN BEFORE ANY HTML OUTPUT ────────────────────────────
if ($export_format === 'csv') {
    while (ob_get_level() > 0) ob_end_clean();

    switch ($report_type) {
        case 'portfolio':   $title = 'Loan Portfolio Report';    $q = buildPortfolioQuery($conn, $start_date, $end_date, $customer_filter, $status_filter); break;
        case 'instalments': $title = 'Loan Instalments Report';  $q = buildInstalmentsQuery($conn, $start_date, $end_date, $customer_filter, $status_filter); break;
        case 'customers':   $title = 'Customers Report';         $q = buildCustomersQuery($conn, $start_date, $end_date, $customer_filter); break;
        case 'overdue':     $title = 'Overdue Loans Report';     $q = buildOverdueQuery($conn, $start_date, $end_date, $customer_filter); break;
        case 'payments':    $title = 'Payments Report';          $q = buildPaymentsQuery($conn, $start_date, $end_date, $customer_filter); break;
        case 'provisions':  $title = 'Provisions Report';        $q = buildProvisionsQuery($conn, $start_date, $end_date, $customer_filter); break;
        case 'summary':     $title = 'Portfolio Summary Report'; $q = buildSummaryQuery($conn, $start_date, $end_date, $customer_filter); break;
        default:            $title = 'Report'; $q = '';
    }

    $data = [];
    if ($q) {
        $res = $conn->query($q);
        if ($res && $res->num_rows > 0)
            while ($row = $res->fetch_assoc()) $data[] = $row;
    }

    generateCsv($data, $report_type, $title, $start_date, $end_date);
    $conn->close();
    exit;
}

// ─── QUERY BUILDERS ──────────────────────────────────────────────────────────

function buildPortfolioQuery($conn, $sd, $ed, $cf, $sf) {
    $sd = mysqli_real_escape_string($conn, $sd);
    $ed = mysqli_real_escape_string($conn, $ed);
    $w = [];
    if ($sf && $sf !== 'all') {
        $w[] = "lp.loan_status = '" . mysqli_real_escape_string($conn, $sf) . "'";
        // If searching specifically for ACTIVE/PERFORMING, don't restrict by date to match dashboard
        if (in_array($sf, ['Active', 'Performing']) && empty($_GET['start_date'])) {
             // Let it be open-ended
        } else {
             $w[] = "lp.disbursement_date BETWEEN '{$sd}' AND '{$ed}'";
        }
    } else {
        $w[] = "lp.disbursement_date BETWEEN '{$sd}' AND '{$ed}'";
    }
    if ($cf) $w[] = "lp.customer_id = " . intval($cf);
    $wc = implode(' AND ', $w);
    return "SELECT lp.*, c.customer_name, c.customer_code, c.phone, c.email, c.address,
        (SELECT MAX(DATEDIFF(CURDATE(), due_date)) FROM loan_instalments WHERE loan_id = lp.loan_id AND payment_date IS NULL AND due_date < CURDATE()) as max_days_overdue,
        (SELECT IFNULL(SUM(balance_remaining), 0) FROM loan_instalments WHERE loan_id = lp.loan_id AND due_date < CURDATE() AND lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off')) as total_overdue_amount,
        (CASE WHEN lp.loan_status = 'Closed' THEN 0 ELSE (SELECT IFNULL(SUM(GREATEST(0, principal_amount - principal_paid)), 0) FROM loan_instalments WHERE loan_id = lp.loan_id) END) as live_principal_outstanding,
        (CASE WHEN lp.loan_status = 'Closed' THEN 0 ELSE (SELECT IFNULL(SUM(GREATEST(0, interest_amount - interest_paid)), 0) FROM loan_instalments WHERE loan_id = lp.loan_id) END) as live_interest_outstanding,
        (CASE WHEN lp.loan_status = 'Closed' THEN 0 ELSE (SELECT IFNULL(SUM(GREATEST(0, principal_amount - principal_paid + interest_amount - interest_paid)), 0) FROM loan_instalments WHERE loan_id = lp.loan_id) END) as live_total_outstanding,
        (SELECT COUNT(*) FROM loan_instalments WHERE loan_id = lp.loan_id) as total_instalments,
        (SELECT COUNT(*) FROM loan_instalments WHERE loan_id = lp.loan_id AND payment_date IS NOT NULL) as paid_instalments,
        (SELECT SUM(paid_amount) FROM loan_instalments WHERE loan_id = lp.loan_id) as live_total_paid,
        (SELECT SUM(penalty_paid) FROM loan_instalments WHERE loan_id = lp.loan_id) as total_penalties_paid
        FROM loan_portfolio lp LEFT JOIN customers c ON lp.customer_id = c.customer_id
        WHERE {$wc} ORDER BY lp.created_at DESC";
}

function buildInstalmentsQuery($conn, $sd, $ed, $cf, $sf) {
    $sd = mysqli_real_escape_string($conn, $sd);
    $ed = mysqli_real_escape_string($conn, $ed);
    $w  = ["li.due_date BETWEEN '{$sd}' AND '{$ed}'"];
    if ($cf) $w[] = "lp.customer_id = " . intval($cf);

    if ($sf && $sf !== 'all') {
        if ($sf === 'paid')        $w[] = "li.balance_remaining <= 0";
        elseif ($sf === 'unpaid')  $w[] = "li.balance_remaining > 0";
        elseif ($sf === 'overdue') $w[] = "li.balance_remaining > 0 AND li.due_date < CURDATE()";
    }
    $wc = implode(' AND ', $w);
    return "SELECT li.*, lp.loan_number, c.customer_name, c.customer_code, lp.interest_rate, lp.loan_status
        FROM loan_instalments li
        LEFT JOIN loan_portfolio lp ON li.loan_id = lp.loan_id
        LEFT JOIN customers c ON lp.customer_id = c.customer_id
        WHERE {$wc} ORDER BY li.due_date DESC";
}

function buildCustomersQuery($conn, $sd, $ed, $cf) {
    $sd = mysqli_real_escape_string($conn, $sd);
    $ed = mysqli_real_escape_string($conn, $ed);
    $w = ["c.created_at BETWEEN '{$sd} 00:00:00' AND '{$ed} 23:59:59'"];
    if ($cf) $w[] = "c.customer_id = " . intval($cf);
    $wc = implode(' AND ', $w);
    return "SELECT c.*, COUNT(DISTINCT lp.loan_id) as total_loans,
        SUM(lp.total_disbursed) as total_disbursed, 
        SUM((SELECT IFNULL(SUM(balance_remaining), 0) FROM loan_instalments WHERE loan_id = lp.loan_id)) as total_outstanding,
        SUM((SELECT IFNULL(SUM(paid_amount), 0) FROM loan_instalments WHERE loan_id = lp.loan_id)) as total_paid, 
        MAX(lp.created_at) as last_loan_date
        FROM customers c LEFT JOIN loan_portfolio lp ON c.customer_id = lp.customer_id
        WHERE {$wc}
        GROUP BY c.customer_id ORDER BY c.customer_name";
}

function buildOverdueQuery($conn, $sd, $ed, $cf) {
    $sd = mysqli_real_escape_string($conn, $sd);
    $ed = mysqli_real_escape_string($conn, $ed);
    $w  = ["li.balance_remaining > 0", "li.due_date < CURDATE()", "li.due_date BETWEEN '{$sd}' AND '{$ed}'"];
    if ($cf) $w[] = "lp.customer_id = " . intval($cf);

    $wc = implode(' AND ', $w);
    return "SELECT li.*, lp.loan_number, lp.interest_rate, lp.loan_status, lp.collateral_net_value,
        c.customer_name, c.customer_code, c.phone,
        DATEDIFF(CURDATE(), li.due_date) as live_days_overdue,
        CASE
            WHEN DATEDIFF(CURDATE(), li.due_date) BETWEEN 1   AND 89  THEN '1-89 Days (1%)'
            WHEN DATEDIFF(CURDATE(), li.due_date) BETWEEN 90  AND 179 THEN '90-179 Days (20%)'
            WHEN DATEDIFF(CURDATE(), li.due_date) BETWEEN 180 AND 359 THEN '180-359 Days (50%)'
            WHEN DATEDIFF(CURDATE(), li.due_date) >= 360              THEN '360+ Days (100%)'
        END as provision_category
        FROM loan_instalments li
        LEFT JOIN loan_portfolio lp ON li.loan_id = lp.loan_id
        LEFT JOIN customers c ON lp.customer_id = c.customer_id
        WHERE {$wc} ORDER BY live_days_overdue DESC, li.due_date";
}

function buildPaymentsQuery($conn, $sd, $ed, $cf) {
    $sd = mysqli_real_escape_string($conn, $sd);
    $ed = mysqli_real_escape_string($conn, $ed);
    $w  = ["li.payment_date BETWEEN '{$sd}' AND '{$ed}'"];
    if ($cf) $w[] = "lp.customer_id = " . intval($cf);
    $wc = implode(' AND ', $w);
    return "SELECT li.instalment_id, li.loan_number, li.instalment_number, li.due_date, li.payment_date,
        li.principal_amount, li.interest_amount, li.management_fee,
        li.total_payment, li.paid_amount, li.principal_paid, li.interest_paid,
        li.management_fee_paid, li.penalty_paid, li.balance_remaining, c.customer_name, c.customer_code, lp.loan_status
        FROM loan_instalments li
        LEFT JOIN loan_portfolio lp ON li.loan_id = lp.loan_id
        LEFT JOIN customers c ON lp.customer_id = c.customer_id
        WHERE {$wc} ORDER BY li.payment_date DESC";
}

function buildProvisionsQuery($conn, $sd, $ed, $cf) {
    // A loan needs provision if any instalment is overdue OR if the loan status is 'Overdue' / 'Written-off'
    $w = ["(SELECT COUNT(*) FROM loan_instalments WHERE loan_id = lp.loan_id AND balance_remaining > 0 AND due_date < CURDATE()) > 0",
          "lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off')"];
    if ($cf) $w[] = "lp.customer_id = " . intval($cf);
    $wc = implode(' AND ', $w);
    return "SELECT lp.loan_id, lp.loan_number, c.customer_name, c.customer_code,
        (SELECT SUM(balance_remaining) FROM loan_instalments WHERE loan_id = lp.loan_id) as total_outstanding,
        COALESCE(lp.collateral_net_value,0) as collateral_net_value,
        ((SELECT SUM(balance_remaining) FROM loan_instalments WHERE loan_id = lp.loan_id) - COALESCE(lp.collateral_net_value,0)) as exposure,
        MAX(DATEDIFF(CURDATE(), li.due_date)) as max_days_overdue,
        CASE
            WHEN MAX(DATEDIFF(CURDATE(), li.due_date)) >= 360 THEN ((SELECT SUM(balance_remaining) FROM loan_instalments WHERE loan_id = lp.loan_id) - COALESCE(lp.collateral_net_value,0)) * 1.00
            WHEN MAX(DATEDIFF(CURDATE(), li.due_date)) >= 180 THEN ((SELECT SUM(balance_remaining) FROM loan_instalments WHERE loan_id = lp.loan_id) - COALESCE(lp.collateral_net_value,0)) * 0.50
            WHEN MAX(DATEDIFF(CURDATE(), li.due_date)) >= 90  THEN ((SELECT SUM(balance_remaining) FROM loan_instalments WHERE loan_id = lp.loan_id) - COALESCE(lp.collateral_net_value,0)) * 0.20
            ELSE                                  ((SELECT SUM(balance_remaining) FROM loan_instalments WHERE loan_id = lp.loan_id) - COALESCE(lp.collateral_net_value,0)) * 0.01
        END as provision_amount,
        CASE
            WHEN MAX(DATEDIFF(CURDATE(), li.due_date)) >= 360 THEN '100%'
            WHEN MAX(DATEDIFF(CURDATE(), li.due_date)) >= 180 THEN '50%'
            WHEN MAX(DATEDIFF(CURDATE(), li.due_date)) >= 90  THEN '20%'
            ELSE '1%'
        END as provision_rate,
        lp.last_provision_date, lp.loan_status
        FROM loan_portfolio lp
        LEFT JOIN customers c ON lp.customer_id = c.customer_id
        LEFT JOIN loan_instalments li ON lp.loan_id = li.loan_id
        WHERE {$wc} GROUP BY lp.loan_id ORDER BY provision_amount DESC";
}

function buildSummaryQuery($conn, $sd, $ed, $cf) {
    $sd = mysqli_real_escape_string($conn, $sd);
    $ed = mysqli_real_escape_string($conn, $ed);
    
    // If we're looking at "today's" auto-report, match the dashboard by not filtering active loans by date
    $is_all_time = (empty($_GET['start_date']));
    $date_clause = $is_all_time ? "1=1" : "lp.disbursement_date BETWEEN '{$sd}' AND '{$ed}'";
    if ($cf) $date_clause .= " AND lp.customer_id = " . intval($cf);

    $penalty_clause = "lp2.disbursement_date BETWEEN '{$sd}' AND '{$ed}'";
    if ($cf) $penalty_clause .= " AND lp2.customer_id = " . intval($cf);

    return "SELECT
        COUNT(DISTINCT lp.loan_id) as total_loans, COUNT(DISTINCT lp.customer_id) as total_customers,
        SUM(lp.total_disbursed) as total_disbursed,
        
        -- GLOBAL RECONCILIATION FIELDS (Matches Loans Page Header)
        SUM((SELECT IFNULL(SUM(principal_amount - principal_paid), 0) FROM loan_instalments WHERE loan_id = lp.loan_id AND payment_date IS NULL)) as global_principal_residue,
        SUM((SELECT IFNULL(SUM(principal_amount - principal_paid + interest_amount - interest_paid), 0) FROM loan_instalments WHERE loan_id = lp.loan_id AND payment_date IS NULL)) as global_total_residue,
        
        -- ACTIVE PORTFOLIO FIELDS (Matches Dashboard Cards)
        SUM(CASE WHEN lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off') THEN 
            (SELECT IFNULL(SUM(GREATEST(0, principal_amount - principal_paid)), 0) FROM loan_instalments WHERE loan_id = lp.loan_id) 
        ELSE 0 END) as active_principal,
        
        SUM(CASE WHEN lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off') THEN 
            (SELECT IFNULL(SUM(GREATEST(0, interest_amount - interest_paid)), 0) FROM loan_instalments WHERE loan_id = lp.loan_id) 
        ELSE 0 END) as active_interest,
        
        SUM(CASE WHEN lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off') THEN 
            (SELECT IFNULL(SUM(GREATEST(0, principal_amount - principal_paid + interest_amount - interest_paid)), 0) FROM loan_instalments WHERE loan_id = lp.loan_id) 
        ELSE 0 END) as portfolio_value,

        SUM(CASE WHEN lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off') THEN 
            (SELECT IFNULL(SUM(balance_remaining), 0) FROM loan_instalments WHERE loan_id = lp.loan_id AND due_date < CURDATE()) 
        ELSE 0 END) as total_overdue,
        
        SUM((SELECT IFNULL(SUM(paid_amount), 0) FROM loan_instalments WHERE loan_id = lp.loan_id)) as total_paid,
        SUM(lp.total_principal_paid) as total_principal_paid,
        SUM(lp.total_interest_paid) as total_interest_paid,
        SUM(lp.total_management_fees_paid) as total_mgmt_fees_paid,
        (SELECT SUM(penalty_paid) FROM loan_instalments li JOIN loan_portfolio lp2 ON li.loan_id = lp2.loan_id WHERE {$penalty_clause}) as total_penalties_paid,
        SUM(CASE WHEN lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off') THEN 1 ELSE 0 END) as active_loans_count,
        SUM(CASE WHEN (SELECT COUNT(*) FROM loan_instalments WHERE loan_id = lp.loan_id AND payment_date IS NULL AND due_date < CURDATE()) > 0 AND lp.loan_status IN ('Active', 'Performing', 'Overdue') THEN 1 ELSE 0 END) as overdue_loans_count,
        SUM(CASE WHEN lp.loan_status='Suspended' THEN 1 ELSE 0 END) as suspended_loans,
        SUM(CASE WHEN lp.loan_status='Closed'    THEN 1 ELSE 0 END) as closed_loans,
        AVG(lp.interest_rate) as avg_interest_rate,
        SUM(COALESCE(lp.collateral_net_value,0)) as total_collateral_value
        FROM loan_portfolio lp
        WHERE {$date_clause}";
}

// ─── CSV GENERATOR ───────────────────────────────────────────────────────────

function generateCsv($data, $report_type, $title, $sd, $ed) {
    $filename = strtolower(str_replace(' ', '_', $title)) . '_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // UTF-8 BOM so Excel opens the file with correct encoding
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    // ── Meta header block ────────────────────────────────────────────────────
    fputcsv($out, ['CAPITAL BRIDGE FINANCE']);
    fputcsv($out, ['Kigali, Rwanda | Tel: +250 785 973 036 | Email: info@cbfinance.rw']);
    fputcsv($out, [strtoupper($title)]);
    fputcsv($out, ['Period: ' . date('F d, Y', strtotime($sd)) . ' to ' . date('F d, Y', strtotime($ed))]);
    fputcsv($out, ['Generated: ' . date('F d, Y H:i:s')]);
    fputcsv($out, []); // blank separator row

    // ── Column headers ───────────────────────────────────────────────────────
    fputcsv($out, getHeaders($report_type));

    // ── Data rows ────────────────────────────────────────────────────────────
    [$rows, $totals] = formatRows($report_type, $data);

    foreach ($rows as $row) {
        fputcsv($out, $row);
    }

    // ── Totals row ───────────────────────────────────────────────────────────
    if (!empty($totals)) {
        fputcsv($out, []); // blank line before totals
        fputcsv($out, $totals);
    }

    fclose($out);
}

// ─── HEADER DEFINITIONS ──────────────────────────────────────────────────────

function getHeaders($type) {
    switch ($type) {
        case 'portfolio':
            return ['Loan Number', 'Customer Name', 'Customer Code', 'Phone', 'Loan Amount',
                    'Total Disbursed', 'Total Paid', 'Penalties Paid', 'Principal Outstanding', 'Interest Outstanding', 'Total Outstanding', 
                    'Overdue Amount', 'Interest Rate', 'No. of Instalments', 'Disbursement Date', 'Maturity Date', 'Collateral Value',
                    'Loan Status', 'Days Overdue'];
        case 'instalments':
            return ['Loan Number', 'Customer Name', 'Instalment #', 'Due Date', 'Payment Date',
                    'Opening Balance', 'Closing Balance', 'Principal', 'Interest', 'Mgmt Fee',
                    'Total Payment', 'Paid (P+I+F)', 'Penalty Paid', 'Total Collected', 'Balance Remaining', 'Days Overdue', 'Status'];
        case 'customers':
            return ['Customer Code', 'Customer Name', 'Phone', 'Email', 'Address',
                    'Total Loans', 'Total Disbursed', 'Total Outstanding', 'Total Paid', 'Last Loan Date'];
        case 'overdue':
            return ['Loan Number', 'Customer Name', 'Instalment #', 'Due Date', 'Days Overdue',
                    'Principal', 'Interest', 'Mgmt Fee', 'Total Due', 'Balance Remaining', 'Provision Category'];
        case 'payments':
            return ['Loan Number', 'Customer Name', 'Instalment #', 'Due Date', 'Payment Date',
                    'Principal Paid', 'Interest Paid', 'Mgmt Fee Paid', 'Penalty Paid', 'Total Collected', 'Balance Remaining'];
        case 'provisions':
            return ['Loan Number', 'Customer Name', 'Total Outstanding', 'Collateral Net Value',
                    'Exposure', 'Max Days Overdue', 'Provision Rate', 'Provision Amount',
                    'Last Provision Date', 'Loan Status'];
        case 'summary':
            return ['Metric', 'Value'];
        default:
            return [];
    }
}

// ─── ROW FORMATTERS ──────────────────────────────────────────────────────────

function formatRows($type, $data) {
    $rows = []; $totals = [];

    switch ($type) {
        case 'portfolio':
            $td = $tp = $tpen = 0;
            $act_count = 0; 
            $act_to = $act_po = $act_io = $act_tov = 0;
            $glob_residue_po = $glob_residue_to = 0;

            foreach ($data as $r) {
                // Global Reconciliation Sums (Matches Loans Page Header)
                $td   += $r['total_disbursed'];
                $tp   += $r['live_total_paid'];
                $tpen += $r['total_penalties_paid'];
                
                // Logic for the 150M/166M "Residue" (Anything not fully swiped/closed)
                // We use the 'payment_date IS NULL' logic to match the current Loans/Dashboard legacy cards
                $glob_residue_po += $r['live_principal_outstanding'];
                $glob_residue_to += $r['live_total_outstanding'];

                // Active Sums (Specifically matching the Dashboard "Active Portfolio" card)
                if (in_array($r['loan_status'], ['Active', 'Performing', 'Overdue', 'Written-off'])) {
                    $act_count++;
                    $act_to   += $r['live_total_outstanding'];
                    $act_po   += $r['live_principal_outstanding'];
                    $act_io   += $r['live_interest_outstanding'];
                    $act_tov  += $r['total_overdue_amount'];
                }
                
                $rows[] = [
                    $r['loan_number'], $r['customer_name'], $r['customer_code'], $r['phone'],
                    number_format($r['loan_amount'] ?? 0, 2),
                    number_format($r['total_disbursed'], 2),
                    number_format($r['live_total_paid'], 2),
                    number_format($r['total_penalties_paid'] ?? 0, 2),
                    number_format($r['live_principal_outstanding'], 2),
                    number_format($r['live_interest_outstanding'], 2),
                    number_format($r['live_total_outstanding'], 2),
                    number_format($r['total_overdue_amount'] ?? 0, 2),
                    $r['interest_rate'] . '%',
                    $r['number_of_instalments'] ?? '',
                    $r['disbursement_date'] ? date('Y-m-d', strtotime($r['disbursement_date'])) : '',
                    $r['maturity_date']     ? date('Y-m-d', strtotime($r['maturity_date']))     : '',
                    number_format($r['collateral_net_value'] ?? 0, 2),
                    $r['loan_status'],
                    ($r['max_days_overdue'] > 0) ? $r['max_days_overdue'] : '',
                ];
            }
            if (!empty($data)) {
                $rows[] = array_fill(0, 19, ''); // Spacer
                
                // Active Portfolio Total (Matches Dashboard)
                $totals = array_fill(0, 19, '');
                $totals[0] = "ACTIVE PORTFOLIO TOTAL ($act_count loans)";
                $totals[8] = number_format($act_po, 2);
                $totals[9] = number_format($act_io, 2);
                $totals[10] = number_format($act_to, 2);
                $totals[11] = number_format($act_tov, 2);
                $rows[] = $totals;

                // Global Reconciliation (Total Disbursed, Total Collected)
                $g_reconcile = array_fill(0, 19, '');
                $g_reconcile[0] = "GLOBAL RECONCILIATION (" . count($data) . " loans)";
                $g_reconcile[5] = number_format($td, 2);
                $g_reconcile[6] = number_format($tp, 2);
                $g_reconcile[7] = number_format($tpen, 2);
                $rows[] = $g_reconcile;

                // Global Residue (Matches Loans Page Header: Principal Only / P+I Remaining)
                $g_residue = array_fill(0, 19, '');
                $g_residue[0] = "GLOBAL RESIDUE (Principal only: " . number_format($glob_residue_po, 2) . ")";
                $g_residue[8] = number_format($glob_residue_po, 2);
                $g_residue[10] = number_format($glob_residue_to, 2);
                $g_residue[12] = " (P+I remaining: " . number_format($glob_residue_to, 2) . ")";
                $rows[] = $g_residue;
            }
            break;

        case 'instalments':
            foreach ($data as $r) {
                $is_overdue = ($r['balance_remaining'] > 0 && strtotime($r['due_date']) < time());
                $status = $r['balance_remaining'] <= 0 ? 'Paid' : ($is_overdue ? 'Overdue' : 'Pending');
                
                // Use live DATEDIFF if overdue
                $live_days = $is_overdue ? floor((time() - strtotime($r['due_date'])) / 86400) : 0;
                $rows[] = [
                    $r['loan_number'],
                    $r['customer_name'],
                    $r['instalment_number'],
                    $r['due_date']     ? date('Y-m-d', strtotime($r['due_date']))     : '',
                    $r['payment_date'] ? date('Y-m-d', strtotime($r['payment_date'])) : 'Not Paid',
                    number_format($r['opening_balance'] ?? 0, 2),
                    number_format($r['closing_balance'] ?? 0, 2),
                    number_format($r['principal_amount'], 2),
                    number_format($r['interest_amount'], 2),
                    number_format($r['management_fee'], 2),
                    number_format($r['total_payment'], 2),
                    number_format($r['paid_amount'], 2),
                    number_format($r['penalty_paid'] ?? 0, 2),
                    number_format($r['paid_amount'] + ($r['penalty_paid'] ?? 0), 2),
                    number_format($r['balance_remaining'], 2),
                    ($live_days > 0) ? $live_days : '',
                    $status,
                ];
            }
            break;

        case 'customers':
            foreach ($data as $r) {
                $rows[] = [
                    $r['customer_code'], $r['customer_name'], $r['phone'], $r['email'], $r['address'],
                    $r['total_loans'] ?? 0,
                    number_format($r['total_disbursed'] ?? 0, 2),
                    number_format($r['total_outstanding'] ?? 0, 2),
                    number_format($r['total_paid'] ?? 0, 2),
                    $r['last_loan_date'] ? date('Y-m-d', strtotime($r['last_loan_date'])) : 'N/A',
                ];
            }
            break;

        case 'overdue':
            $tp = $ti = $tm = $tt = $tb = 0;
            foreach ($data as $r) {
                $tp += $r['principal_amount'];
                $ti += $r['interest_amount'];
                $tm += $r['management_fee'];
                $tt += $r['total_payment'];
                $tb += $r['balance_remaining'];
                
                $rows[] = [
                    $r['loan_number'], $r['customer_name'], $r['instalment_number'],
                    $r['due_date'] ? date('Y-m-d', strtotime($r['due_date'])) : '',
                    $r['live_days_overdue'] . ' days',
                    number_format($r['principal_amount'], 2),
                    number_format($r['interest_amount'], 2),
                    number_format($r['management_fee'], 2),
                    number_format($r['total_payment'], 2),
                    number_format($r['balance_remaining'], 2),
                    $r['provision_category'],
                ];
            }
            if (!empty($data)) {
                $totals = array_fill(0, 11, '');
                $totals[0] = "TOTAL OVERDUE (" . count($data) . " instalments)";
                $totals[5] = number_format($tp, 2);
                $totals[6] = number_format($ti, 2);
                $totals[7] = number_format($tm, 2);
                $totals[8] = number_format($tt, 2);
                $totals[9] = number_format($tb, 2);
            }
            break;

        case 'payments':
            $tp = 0; $tpen = 0;
            foreach ($data as $r) {
                $tp += $r['paid_amount'];
                $tpen += $r['penalty_paid'];
                $rows[] = [
                    $r['loan_number'], $r['customer_name'], $r['instalment_number'],
                    $r['due_date']     ? date('Y-m-d', strtotime($r['due_date']))     : '',
                    $r['payment_date'] ? date('Y-m-d', strtotime($r['payment_date'])) : '',
                    number_format($r['principal_paid'], 2),
                    number_format($r['interest_paid'], 2),
                    number_format($r['management_fee_paid'], 2),
                    number_format($r['penalty_paid'], 2),
                    number_format($r['paid_amount'] + $r['penalty_paid'], 2),
                    number_format($r['balance_remaining'], 2),
                ];
            }
            if (!empty($data)) {
                $totals = array_fill(0, 11, '');
                $totals[0] = 'TOTAL COLLECTED';
                $totals[8] = number_format($tpen, 2);
                $totals[9] = number_format($tp + $tpen, 2);
            }
            break;

        case 'provisions':
            $tp = 0;
            foreach ($data as $r) {
                $tp += $r['provision_amount'];
                $rows[] = [
                    $r['loan_number'], $r['customer_name'],
                    number_format($r['total_outstanding'], 2),
                    number_format($r['collateral_net_value'], 2),
                    number_format($r['exposure'], 2),
                    $r['max_days_overdue'] . ' days',
                    $r['provision_rate'],
                    number_format($r['provision_amount'], 2),
                    $r['last_provision_date'] ? date('Y-m-d', strtotime($r['last_provision_date'])) : 'Not Set',
                    $r['loan_status'],
                ];
            }
            if (!empty($data)) {
                $totals = array_fill(0, 10, '');
                $totals[0] = 'TOTAL PROVISION REQUIRED';
                $totals[7] = number_format($tp, 2);
            }
            break;

        case 'summary':
            if (!empty($data)) {
                $r  = $data[0];
                $cr = $r['total_disbursed'] > 0 ? ($r['total_paid'] / $r['total_disbursed']) * 100 : 0;
                $rows = [
                    ['── GLOBAL RECONCILIATION ──────────────', ''],
                    ['Total Loans',            number_format($r['total_loans'])],
                    ['Total Customers',        number_format($r['total_customers'])],
                    ['Total Distributed',      number_format($r['total_disbursed'], 2)],
                    ['Principal only (Global Residue)', number_format($r['global_principal_residue'], 2)],
                    ['Principal + Interest remaining (Global Residue)', number_format($r['global_total_residue'], 2)],
                    ['', ''],
                    ['── ACTIVE PORTFOLIO ONLY ──────────────', ''],
                    ['Active Loans count',     number_format($r['active_loans_count'])],
                    ['Active Principal',       number_format($r['active_principal'], 2)],
                    ['Active Interest',        number_format($r['active_interest'], 2)],
                    ['Portfolio Value (P+I)',  number_format($r['portfolio_value'], 2)],
                    ['Total Overdue',          number_format($r['total_overdue'], 2)],
                    ['Overdue Loans count',    number_format($r['overdue_loans_count'])],
                    ['', ''],
                    ['── RECORDED PAYMENTS ──────────────────', ''],
                    ['Total Collected (Inc Pen)',   number_format($r['total_paid'], 2)],
                    ['Total Principal Paid',   number_format($r['total_principal_paid'], 2)],
                    ['Total Interest Paid',    number_format($r['total_interest_paid'], 2)],
                    ['Total Mgmt Fees Paid',   number_format($r['total_mgmt_fees_paid'], 2)],
                    ['Total Penalties Paid',   number_format($r['total_penalties_paid'], 2)],
                    ['', ''],
                    ['── OTHER METRICS ──────────────────────', ''],
                    ['Average Interest Rate',  number_format($r['avg_interest_rate'], 2) . '%'],
                    ['Total Collateral Value', number_format($r['total_collateral_value'], 2)],
                    ['Collection Rate',        number_format($cr, 2) . '%'],
                ];
            }
            break;
    }

    return [$rows, $totals];
}

// ─── PAGE DATA (ONLY EXECUTED IF NOT EXPORTING) ──────────────────────────────

$customers_result = $conn->query(
    "SELECT customer_id, customer_name, customer_code FROM customers ORDER BY customer_name"
);

$sd_esc = mysqli_real_escape_string($conn, $start_date);
$ed_esc = mysqli_real_escape_string($conn, $end_date);

$stats_sql = "SELECT 
        COUNT(*) as total_loans, 
        SUM(total_disbursed) as total_distributed,
        COALESCE(SUM(CASE WHEN lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off') THEN 
            (SELECT SUM(GREATEST(0, principal_amount - principal_paid)) FROM loan_instalments WHERE loan_id = lp.loan_id) 
        ELSE 0 END), 0) as active_principal,
        COALESCE(SUM(CASE WHEN lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off') THEN 
            (SELECT SUM(GREATEST(0, principal_amount - principal_paid + interest_amount - interest_paid)) FROM loan_instalments WHERE loan_id = lp.loan_id) 
        ELSE 0 END), 0) as portfolio_value,
        COALESCE(SUM(CASE WHEN lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off') THEN 
            (SELECT SUM(balance_remaining) FROM loan_instalments WHERE loan_id = lp.loan_id AND due_date < CURDATE()) 
        ELSE 0 END), 0) as total_overdue
     FROM loan_portfolio lp
     WHERE disbursement_date BETWEEN '{$sd_esc}' AND '{$ed_esc}'";
if ($customer_filter) $stats_sql .= " AND lp.customer_id = " . intval($customer_filter);
$stats = $conn->query($stats_sql)->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Capital Bridge Finance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        /* ── Font size overrides only ── */
        body                         { font-size: 12px; }
        h2.h4                        { font-size: 14px; }
        h3.mb-0                      { font-size: 15px; }
        h5, h5.mb-0, h5.card-title   { font-size: 12px; }
        h6                           { font-size: 11px; }
        small, .small                { font-size: 10px; }
        .form-label                  { font-size: 11px; }
        .form-select, .form-control  { font-size: 11px; }
        .btn                         { font-size: 11px; }
        .btn-lg                      { font-size: 12px; }
        .btn-sm                      { font-size: 10px; }
        .card-text                   { font-size: 11px; }
        p.text-muted                 { font-size: 11px; }

        .report-card { transition: transform .2s; cursor: pointer; }
        .report-card:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,.12); }
        .stat-card { border-left: 4px solid; }
        .stat-card.primary { border-color: #0d6efd; }
        .stat-card.success { border-color: #198754; }
        .stat-card.warning { border-color: #ffc107; }
        .stat-card.danger  { border-color: #dc3545; }
        .report-card.active-report { border: 2px solid #198754; background: #f0fff4; }
    </style>
</head>
<body>
<div class="container-fluid py-4">

    <div class="row mb-4">
        <div class="col-12">
            <h2 class="h4 fw-bold text-primary">Reports Center</h2>
            <p class="text-muted">Generate and download excel reports with custom timeframes</p>
        </div>
    </div>



    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Report Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" id="reportForm">
                <input type="hidden" name="page" value="reports">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Report Type</label>
                        <select name="report_type" id="reportTypeSelect" class="form-select" required>
                            <option value="portfolio"   <?= $report_type=='portfolio'  ?'selected':'' ?>>Loan Portfolio</option>
                            <option value="instalments" <?= $report_type=='instalments'?'selected':'' ?>>Loan Instalments</option>
                            <option value="customers"   <?= $report_type=='customers'  ?'selected':'' ?>>Customers</option>
                            <option value="overdue"     <?= $report_type=='overdue'    ?'selected':'' ?>>Overdue Loans</option>
                            <option value="payments"    <?= $report_type=='payments'   ?'selected':'' ?>>Payments</option>
                            <option value="provisions"  <?= $report_type=='provisions' ?'selected':'' ?>>Provisions</option>
                            <option value="summary"     <?= $report_type=='summary'    ?'selected':'' ?>>Portfolio Summary</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Start Date</label>
                        <input type="date" class="form-control" name="start_date" id="startDate"
                               onchange="validateAndTrigger()"
                               value="<?= htmlspecialchars($start_date) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">End Date</label>
                        <input type="date" class="form-control" name="end_date" id="endDate"
                               onchange="validateAndTrigger()"
                               value="<?= htmlspecialchars($end_date) ?>" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Customer</label>
                        <select name="customer_id" class="form-select">
                            <option value="">All Customers</option>
                            <?php if ($customers_result && $customers_result->num_rows > 0):
                                  while ($c = $customers_result->fetch_assoc()): ?>
                            <option value="<?= $c['customer_id'] ?>"
                                <?= $customer_filter==$c['customer_id']?'selected':'' ?>>
                                <?= htmlspecialchars($c['customer_name']).' ('.$c['customer_code'].')' ?>
                            </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status_filter" class="form-select">
                            <option value="all">All Status</option>
                            <option value="Active"    <?= $status_filter=='Active'   ?'selected':'' ?>>Active</option>
                            <option value="Overdue"   <?= $status_filter=='Overdue'  ?'selected':'' ?>>Overdue</option>
                            <option value="Suspended" <?= $status_filter=='Suspended'?'selected':'' ?>>Suspended</option>
                            <option value="Closed"    <?= $status_filter=='Closed'   ?'selected':'' ?>>Closed</option>
                            <option value="paid"      <?= $status_filter=='paid'     ?'selected':'' ?>>Paid (Instalments)</option>
                            <option value="unpaid"    <?= $status_filter=='unpaid'   ?'selected':'' ?>>Unpaid (Instalments)</option>
                        </select>
                    </div>
                    </div>
                </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Quick Month Selection</label>
                        <div class="input-group">
                            <input type="month" id="selectedMonth" class="form-control" 
                                   onchange="applyMonthMacro(this.value)"
                                   value="<?= date('Y-m', strtotime($start_date)) ?>">
                        </div>
                    </div>
                    <div class="col-md-9 d-flex align-items-center gap-3">
                        <button type="button" class="btn btn-success btn-lg px-4" onclick="downloadCsv()">
                            <i class="bi bi-filetype-csv me-2"></i>Download Excel Report
                        </button>

                        <div id="dateErrorMessage" class="text-danger small d-none">
                            <i class="bi bi-exclamation-circle me-1"></i> Invalid date range selected!
                        </div>
                    </div>
                </div>

            </form>

            <!-- Quick Timeframes -->
            <hr class="my-3">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <small class="text-muted me-1">Quick select:</small>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setRange('today')">Today</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setRange('week')">This Week</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setRange('month')">This Month</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setRange('quarter')">This Quarter</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setRange('year')">This Year</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setRange('last_month')">Last Month</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setRange('last_quarter')">Last Quarter</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setRange('last_year')">Last Year</button>
            </div>
        </div>
    </div>

    <!-- Report Type Cards -->
    <h6 class="text-muted mb-3">Click a card to select that report type, then download above</h6>
    <div class="row">
        <?php
        $cards = [
            ['portfolio',   'briefcase',           'primary', 'Loan Portfolio Report',   'Comprehensive overview of all loans with customer details, disbursement info, and status'],
            ['instalments', 'calendar3',            'success', 'Instalments Report',      'Detailed breakdown of all loan instalments including payment status and schedules'],
            ['customers',   'people',               'info',    'Customers Report',        'Customer portfolio summary with loan counts and financial metrics'],
            ['overdue',     'exclamation-triangle', 'warning', 'Overdue Loans Report',   'Track overdue instalments with days overdue and provision categories'],
            ['payments',    'cash-coin',             'success', 'Payments Report',         'Complete payment history with principal, interest, and fee breakdowns'],
            ['provisions',  'shield-exclamation',   'danger',  'Provisions Report',       'Loan loss provision calculations based on exposure and overdue periods'],
            ['summary',     'graph-up',              'primary', 'Portfolio Summary Report','High-level portfolio metrics and key performance indicators'],
        ];
        foreach ($cards as [$val, $icon, $color, $label, $desc]):
            $active = $report_type === $val ? 'active-report' : '';
        ?>
        <div class="col-md-4 mb-3">
            <div class="card report-card h-100 <?= $active ?>" onclick="selectReport('<?= $val ?>')">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-<?= $icon ?> text-<?= $color ?> me-2"></i><?= $label ?>
                    </h5>
                    <p class="card-text text-muted small mb-0"><?= $desc ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function validateDates() {
    const s = document.getElementById('startDate').value;
    const e = document.getElementById('endDate').value;
    const err = document.getElementById('dateErrorMessage');
    
    if (!s || !e) return false;
    
    const start = new Date(s);
    const end = new Date(e);
    const today = new Date();
    today.setHours(23,59,59,999);
    
    // Rule: Start must be before or equal to End
    if (start > end) {
        err.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Start date cannot be after end date!';
        err.classList.remove('d-none');
        return false;
    }
    
    // User mentioned "between tomorrow and yesterday" as an example of false dates
    // Usually reports don't include tomorrow
    if (start > today) {
        err.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Future dates not allowed for generated reports!';
        err.classList.remove('d-none');
        return false;
    }

    err.classList.add('d-none');
    return true;
}

function applyMonthMacro(val) {
    if (!val) return;
    const parts = val.split('-');
    const y = parseInt(parts[0]);
    const m = parseInt(parts[1]);
    
    // Set start to 1st of that month
    const start = new Date(y, m - 1, 1);
    // Set end to last day of that month
    const end = new Date(y, m, 0);
    
    document.getElementById('startDate').value = fmt(start);
    document.getElementById('endDate').value = fmt(end);
    
    validateAndTrigger();
}

function validateAndTrigger() {
    if (validateDates()) {
        const form = document.getElementById('reportForm');
        // Prevent immediate download during auto-trigger
        const format = form.querySelector('input[name="export_format"]');
        if (format) format.remove();
        form.submit();
    }
}

function downloadCsv() {
    if (!validateDates()) return;
    
    const form = document.getElementById('reportForm');
    const originalTarget = form.target;
    
    // Create a hidden input for the export format
    let existing = form.querySelector('input[name="export_format"]');
    if (existing) existing.remove();

    const inp = document.createElement('input');
    inp.type  = 'hidden';
    inp.name  = 'export_format';
    inp.value = 'csv';
    form.appendChild(inp);
    
    // Use target _blank to initiate download in background/new tab
    // so the current page (with its sidebar) stays put
    form.target = '_blank';
    form.submit();
    
    // Restore original target immediately so future normal filters stay in-page
    setTimeout(() => {
        form.target = originalTarget;
        if (inp.parentNode) inp.parentNode.removeChild(inp);
    }, 500);
}



function selectReport(type) {
    const select = document.getElementById('reportTypeSelect');
    if (!select) return;
    
    select.value = type;
    
    // Highlight UI cards
    document.querySelectorAll('.report-card').forEach(c => c.classList.remove('active-report'));
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active-report');
    }
    
    // Trigger preview update
    validateAndTrigger();
}

function setRange(range) {
    const now = new Date();
    let s, e = new Date(now);

    switch (range) {
        case 'today':        s = new Date(now); e = new Date(now); break;
        case 'week':         s = new Date(now); s.setDate(now.getDate() - now.getDay()); break;
        case 'month':        s = new Date(now.getFullYear(), now.getMonth(), 1); break;
        case 'quarter':      s = new Date(now.getFullYear(), Math.floor(now.getMonth()/3)*3, 1); break;
        case 'year':         s = new Date(now.getFullYear(), 0, 1); break;
        case 'last_month':   s = new Date(now.getFullYear(), now.getMonth()-1, 1);
                             e = new Date(now.getFullYear(), now.getMonth(), 0); break;
        case 'last_quarter': const lq = Math.floor(now.getMonth()/3) - 1;
                             s = new Date(now.getFullYear(), lq*3, 1);
                             e = new Date(now.getFullYear(), (lq+1)*3, 0); break;
        case 'last_year':    s = new Date(now.getFullYear()-1, 0, 1);
                             e = new Date(now.getFullYear()-1, 11, 31); break;
        default:             s = new Date(now); e = new Date(now);
    }

    document.querySelector('[name="start_date"]').value = fmt(s);
    document.querySelector('[name="end_date"]').value   = fmt(e);
}

function fmt(d) {
    return d.getFullYear() + '-' +
           String(d.getMonth() + 1).padStart(2, '0') + '-' +
           String(d.getDate()).padStart(2, '0');
}
</script>
</body>
</html>
<?php if (isset($conn)) $conn->close(); ?>
