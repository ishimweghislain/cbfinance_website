<?php
require_once __DIR__ . '/../config/database.php';
$conn = getConnection();

$report_type     = $_GET['report_type']   ?? 'portfolio';
$start_date      = $_GET['start_date']    ?? date('Y-m-d', strtotime('-30 days'));
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
        case 'customers':   $title = 'Customers Report';         $q = buildCustomersQuery($conn, $start_date, $end_date); break;
        case 'overdue':     $title = 'Overdue Loans Report';     $q = buildOverdueQuery($conn, $start_date, $end_date, $customer_filter); break;
        case 'payments':    $title = 'Payments Report';          $q = buildPaymentsQuery($conn, $start_date, $end_date, $customer_filter); break;
        case 'provisions':  $title = 'Provisions Report';        $q = buildProvisionsQuery($conn, $start_date, $end_date, $customer_filter); break;
        case 'summary':     $title = 'Portfolio Summary Report'; $q = buildSummaryQuery($conn, $start_date, $end_date); break;
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
    $w  = ["lp.created_at BETWEEN '{$sd} 00:00:00' AND '{$ed} 23:59:59'"];
    if ($cf) $w[] = "lp.customer_id = " . intval($cf);
    if ($sf && $sf !== 'all') $w[] = "lp.loan_status = '" . mysqli_real_escape_string($conn, $sf) . "'";
    $wc = implode(' AND ', $w);
    return "SELECT lp.*, c.customer_name, c.customer_code, c.phone, c.email, c.address,
        (SELECT MAX(days_overdue) FROM loan_instalments WHERE loan_id = lp.loan_id AND payment_date IS NULL AND days_overdue > 0) as max_days_overdue,
        (SELECT COUNT(*) FROM loan_instalments WHERE loan_id = lp.loan_id) as total_instalments,
        (SELECT COUNT(*) FROM loan_instalments WHERE loan_id = lp.loan_id AND payment_date IS NOT NULL) as paid_instalments,
        (SELECT SUM(paid_amount) FROM loan_instalments WHERE loan_id = lp.loan_id) as total_collected
        FROM loan_portfolio lp LEFT JOIN customers c ON lp.customer_id = c.customer_id
        WHERE {$wc} ORDER BY lp.created_at DESC";
}

function buildInstalmentsQuery($conn, $sd, $ed, $cf, $sf) {
    $sd = mysqli_real_escape_string($conn, $sd);
    $ed = mysqli_real_escape_string($conn, $ed);
    $w  = ["li.created_at BETWEEN '{$sd} 00:00:00' AND '{$ed} 23:59:59'"];
    if ($cf) $w[] = "lp.customer_id = " . intval($cf);
    if ($sf && $sf !== 'all') {
        if ($sf === 'paid')        $w[] = "li.payment_date IS NOT NULL";
        elseif ($sf === 'unpaid')  $w[] = "li.payment_date IS NULL";
        elseif ($sf === 'overdue') $w[] = "li.payment_date IS NULL AND li.days_overdue > 0";
    }
    $wc = implode(' AND ', $w);
    return "SELECT li.*, lp.loan_number, c.customer_name, c.customer_code, lp.interest_rate, lp.loan_status
        FROM loan_instalments li
        LEFT JOIN loan_portfolio lp ON li.loan_id = lp.loan_id
        LEFT JOIN customers c ON lp.customer_id = c.customer_id
        WHERE {$wc} ORDER BY li.due_date DESC";
}

function buildCustomersQuery($conn, $sd, $ed) {
    $sd = mysqli_real_escape_string($conn, $sd);
    $ed = mysqli_real_escape_string($conn, $ed);
    return "SELECT c.*, COUNT(DISTINCT lp.loan_id) as total_loans,
        SUM(lp.total_disbursed) as total_disbursed, SUM(lp.total_outstanding) as total_outstanding,
        SUM(lp.total_paid) as total_paid, MAX(lp.created_at) as last_loan_date
        FROM customers c LEFT JOIN loan_portfolio lp ON c.customer_id = lp.customer_id
        WHERE c.created_at BETWEEN '{$sd} 00:00:00' AND '{$ed} 23:59:59'
        GROUP BY c.customer_id ORDER BY c.customer_name";
}

function buildOverdueQuery($conn, $sd, $ed, $cf) {
    $sd = mysqli_real_escape_string($conn, $sd);
    $ed = mysqli_real_escape_string($conn, $ed);
    $w  = ["li.days_overdue > 0"];
    if ($cf) $w[] = "lp.customer_id = " . intval($cf);
    $wc = implode(' AND ', $w);
    return "SELECT li.*, lp.loan_number, lp.interest_rate, lp.loan_status, lp.collateral_net_value,
        c.customer_name, c.customer_code, c.phone,
        CASE
            WHEN li.days_overdue BETWEEN 1   AND 89  THEN '1-89 Days (1%)'
            WHEN li.days_overdue BETWEEN 90  AND 179 THEN '90-179 Days (20%)'
            WHEN li.days_overdue BETWEEN 180 AND 359 THEN '180-359 Days (50%)'
            WHEN li.days_overdue >= 360              THEN '360+ Days (100%)'
        END as provision_category
        FROM loan_instalments li
        LEFT JOIN loan_portfolio lp ON li.loan_id = lp.loan_id
        LEFT JOIN customers c ON lp.customer_id = c.customer_id
        WHERE {$wc} ORDER BY li.days_overdue DESC, li.due_date";
}

function buildPaymentsQuery($conn, $sd, $ed, $cf) {
    $sd = mysqli_real_escape_string($conn, $sd);
    $ed = mysqli_real_escape_string($conn, $ed);
    $w  = ["li.payment_date BETWEEN '{$sd}' AND '{$ed}'"];
    if ($cf) $w[] = "lp.customer_id = " . intval($cf);
    $wc = implode(' AND ', $w);
    return "SELECT li.instalment_id, li.loan_number, li.instalment_number, li.due_date, li.payment_date,
        li.principal_amount, li.interest_amount, li.management_fee, li.monitoring_fee_total,
        li.total_payment, li.paid_amount, li.principal_paid, li.interest_paid,
        li.management_fee_paid, li.balance_remaining, c.customer_name, c.customer_code, lp.loan_status
        FROM loan_instalments li
        LEFT JOIN loan_portfolio lp ON li.loan_id = lp.loan_id
        LEFT JOIN customers c ON lp.customer_id = c.customer_id
        WHERE {$wc} ORDER BY li.payment_date DESC";
}

function buildProvisionsQuery($conn, $sd, $ed, $cf) {
    $w = ["li.payment_date IS NULL", "li.days_overdue > 0",
          "lp.total_outstanding > COALESCE(lp.collateral_net_value, 0)"];
    if ($cf) $w[] = "lp.customer_id = " . intval($cf);
    $wc = implode(' AND ', $w);
    return "SELECT lp.loan_id, lp.loan_number, c.customer_name, c.customer_code,
        lp.total_outstanding, COALESCE(lp.collateral_net_value,0) as collateral_net_value,
        (lp.total_outstanding - COALESCE(lp.collateral_net_value,0)) as exposure,
        MAX(li.days_overdue) as max_days_overdue,
        CASE
            WHEN MAX(li.days_overdue) >= 360 THEN (lp.total_outstanding - COALESCE(lp.collateral_net_value,0)) * 1.00
            WHEN MAX(li.days_overdue) >= 180 THEN (lp.total_outstanding - COALESCE(lp.collateral_net_value,0)) * 0.50
            WHEN MAX(li.days_overdue) >= 90  THEN (lp.total_outstanding - COALESCE(lp.collateral_net_value,0)) * 0.20
            ELSE                                  (lp.total_outstanding - COALESCE(lp.collateral_net_value,0)) * 0.01
        END as provision_amount,
        CASE
            WHEN MAX(li.days_overdue) >= 360 THEN '100%'
            WHEN MAX(li.days_overdue) >= 180 THEN '50%'
            WHEN MAX(li.days_overdue) >= 90  THEN '20%'
            ELSE '1%'
        END as provision_rate,
        lp.last_provision_date, lp.loan_status
        FROM loan_portfolio lp
        LEFT JOIN customers c ON lp.customer_id = c.customer_id
        LEFT JOIN loan_instalments li ON lp.loan_id = li.loan_id
        WHERE {$wc} GROUP BY lp.loan_id ORDER BY provision_amount DESC";
}

function buildSummaryQuery($conn, $sd, $ed) {
    $sd = mysqli_real_escape_string($conn, $sd);
    $ed = mysqli_real_escape_string($conn, $ed);
    return "SELECT
        COUNT(DISTINCT lp.loan_id) as total_loans, COUNT(DISTINCT lp.customer_id) as total_customers,
        SUM(lp.total_disbursed) as total_disbursed, SUM(lp.total_outstanding) as total_outstanding,
        SUM(lp.total_paid) as total_paid, SUM(lp.total_principal_paid) as total_principal_paid,
        SUM(lp.total_interest_paid) as total_interest_paid,
        SUM(lp.total_management_fees_paid) as total_mgmt_fees_paid,
        SUM(CASE WHEN lp.loan_status='Active'    THEN 1 ELSE 0 END) as active_loans,
        SUM(CASE WHEN lp.loan_status='Overdue'   THEN 1 ELSE 0 END) as overdue_loans,
        SUM(CASE WHEN lp.loan_status='Suspended' THEN 1 ELSE 0 END) as suspended_loans,
        SUM(CASE WHEN lp.loan_status='Closed'    THEN 1 ELSE 0 END) as closed_loans,
        AVG(lp.interest_rate) as avg_interest_rate,
        SUM(COALESCE(lp.collateral_net_value,0)) as total_collateral_value
        FROM loan_portfolio lp
        WHERE lp.created_at BETWEEN '{$sd} 00:00:00' AND '{$ed} 23:59:59'";
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
            // CHANGED: removed 'Total Paid' column
            return ['Loan Number', 'Customer Name', 'Customer Code', 'Phone', 'Loan Amount',
                    'Total Disbursed', 'Total Outstanding', 'Interest Rate',
                    'No. of Instalments', 'Disbursement Date', 'Maturity Date', 'Collateral Value',
                    'Loan Status', 'Days Overdue'];
        case 'instalments':
            // CHANGED: removed 'Monitoring Fee', added 'Customer Name'
            return ['Loan Number', 'Customer Name', 'Instalment #', 'Due Date', 'Payment Date',
                    'Opening Balance', 'Closing Balance', 'Principal', 'Interest', 'Mgmt Fee',
                    'Total Payment', 'Paid Amount', 'Balance Remaining', 'Days Overdue', 'Status'];
        case 'customers':
            return ['Customer Code', 'Customer Name', 'Phone', 'Email', 'Address',
                    'Total Loans', 'Total Disbursed', 'Total Outstanding', 'Total Paid', 'Last Loan Date'];
        case 'overdue':
            return ['Loan Number', 'Customer Name', 'Instalment #', 'Due Date', 'Days Overdue',
                    'Principal', 'Interest', 'Mgmt Fee', 'Total Due', 'Balance Remaining', 'Provision Category'];
        case 'payments':
            return ['Loan Number', 'Customer Name', 'Instalment #', 'Due Date', 'Payment Date',
                    'Principal Paid', 'Interest Paid', 'Mgmt Fee Paid', 'Total Paid', 'Balance Remaining'];
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
            // CHANGED: removed total_paid accumulation and column; Days Overdue only when > 0
            $td = $to = 0;
            foreach ($data as $r) {
                $td += $r['total_disbursed'];
                $to += $r['total_outstanding'];
                $rows[] = [
                    $r['loan_number'], $r['customer_name'], $r['customer_code'], $r['phone'],
                    number_format($r['loan_amount'] ?? 0, 2),
                    number_format($r['total_disbursed'], 2),
                    number_format($r['total_outstanding'], 2),
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
                $totals = array_fill(0, 14, '');
                $totals[0] = 'TOTAL (' . count($data) . ' loans)';
                $totals[5] = number_format($td, 2);
                $totals[6] = number_format($to, 2);
            }
            break;

        case 'instalments':
            // CHANGED: removed monitoring_fee_total column; added customer_name; Days Overdue only when > 0
            foreach ($data as $r) {
                $status = $r['payment_date'] ? 'Paid' : ($r['days_overdue'] > 0 ? 'Overdue' : 'Pending');
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
                    number_format($r['balance_remaining'], 2),
                    ($r['days_overdue'] > 0) ? $r['days_overdue'] : '',
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
            foreach ($data as $r) {
                $rows[] = [
                    $r['loan_number'], $r['customer_name'], $r['instalment_number'],
                    $r['due_date'] ? date('Y-m-d', strtotime($r['due_date'])) : '',
                    $r['days_overdue'] . ' days',
                    number_format($r['principal_amount'], 2),
                    number_format($r['interest_amount'], 2),
                    number_format($r['management_fee'], 2),
                    number_format($r['total_payment'], 2),
                    number_format($r['balance_remaining'], 2),
                    $r['provision_category'],
                ];
            }
            break;

        case 'payments':
            $tp = 0;
            foreach ($data as $r) {
                $tp += $r['paid_amount'];
                $rows[] = [
                    $r['loan_number'], $r['customer_name'], $r['instalment_number'],
                    $r['due_date']     ? date('Y-m-d', strtotime($r['due_date']))     : '',
                    $r['payment_date'] ? date('Y-m-d', strtotime($r['payment_date'])) : '',
                    number_format($r['principal_paid'], 2),
                    number_format($r['interest_paid'], 2),
                    number_format($r['management_fee_paid'], 2),
                    number_format($r['paid_amount'], 2),
                    number_format($r['balance_remaining'], 2),
                ];
            }
            if (!empty($data)) {
                $totals = array_fill(0, 10, '');
                $totals[0] = 'TOTAL COLLECTED';
                $totals[8] = number_format($tp, 2);
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
                    ['── PORTFOLIO SUMMARY ──────────────────', ''],
                    ['Total Loans',            number_format($r['total_loans'])],
                    ['Total Customers',        number_format($r['total_customers'])],
                    ['Total Disbursed',        number_format($r['total_disbursed'], 2)],
                    ['Total Outstanding',      number_format($r['total_outstanding'], 2)],
                    ['Total Collected',        number_format($r['total_paid'], 2)],
                    ['Total Principal Paid',   number_format($r['total_principal_paid'], 2)],
                    ['Total Interest Paid',    number_format($r['total_interest_paid'], 2)],
                    ['Total Mgmt Fees Paid',   number_format($r['total_mgmt_fees_paid'], 2)],
                    ['', ''],
                    ['── LOAN STATUS BREAKDOWN ──────────────', ''],
                    ['Active Loans',           number_format($r['active_loans'])],
                    ['Overdue Loans',          number_format($r['overdue_loans'])],
                    ['Suspended Loans',        number_format($r['suspended_loans'])],
                    ['Closed Loans',           number_format($r['closed_loans'])],
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

$stats = $conn->query(
    "SELECT COUNT(*) as total_loans, SUM(total_disbursed) as total_disbursed,
     SUM(total_outstanding) as total_outstanding, SUM(total_paid) as total_paid
     FROM loan_portfolio
     WHERE created_at BETWEEN '{$sd_esc} 00:00:00' AND '{$ed_esc} 23:59:59'"
)->fetch_assoc();
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
                        <input type="date" class="form-control" name="start_date"
                               value="<?= htmlspecialchars($start_date) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">End Date</label>
                        <input type="date" class="form-control" name="end_date"
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
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-outline-secondary w-100"
                                title="Reset filters"
                                onclick="window.location.href='?page=reports'">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12 d-flex align-items-center gap-3">
                        <button type="button" class="btn btn-success btn-lg px-4" onclick="downloadCsv()">
                            <i class="bi bi-filetype-csv me-2"></i>Download excel Report
                        </button>
                        <span class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i>
                            Downloads a .csv file — opens in Excel, LibreOffice, and Google Sheets.
                        </span>
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
function downloadCsv() {
    const form = document.getElementById('reportForm');
    const existing = form.querySelector('input[name="export_format"]');
    if (existing) existing.remove();

    const inp = document.createElement('input');
    inp.type  = 'hidden';
    inp.name  = 'export_format';
    inp.value = 'csv';
    form.appendChild(inp);
    form.submit();
}

function selectReport(type) {
    document.getElementById('reportTypeSelect').value = type;
    document.querySelectorAll('.report-card').forEach(c => c.classList.remove('active-report'));
    event.currentTarget.classList.add('active-report');
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