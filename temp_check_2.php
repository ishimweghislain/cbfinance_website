<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ===== BASE ===== */
        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .report-header {
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .table th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
        }
        .amount-cell {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        .positive-balance { color: #198754; }
        .negative-balance { color: #dc3545; }
        .group-header {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .total-row {
            background-color: #f8f9fa !important;
            font-weight: bold;
            border-top: 2px solid #000;
        }
        .grand-total-row {
            background-color: #e9ecef !important;
            font-weight: bold;
            border-top: 3px double #000;
            border-bottom: 3px double #000;
        }
        .account-code {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .timeframe-filter {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .nav-tabs .nav-link.active {
            background-color: #f8f9fa;
            border-bottom-color: #f8f9fa;
            font-weight: bold;
        }
        .quick-date-btn {
            border-radius: 5px;
            margin: 2px;
        }
        .financial-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            border: 1px solid #dee2e6;
        }
        .accounting-equation {
            background: #d1ecf1;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            border: 1px solid #bee5eb;
        }

        /* ===================================
           BALANCE SHEET â€” ENHANCED CLARITY
        ==================================== */
        .bs-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border: 2px solid #c8d0e0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        }

        .bs-col {
            display: flex;
            flex-direction: column;
            background: #fff;
            min-width: 0;
        }

        .bs-col:first-child {
            border-right: 2px solid #c8d0e0;
        }

        /* Section title bar */
        .bs-section-title {
            padding: 14px 18px;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .bs-section-title.assets-title  { background: linear-gradient(90deg, #1a6fc4, #2196f3); }
        .bs-section-title.equity-title  { background: linear-gradient(90deg, #5c35a8, #7c4dff); }
        .bs-section-title.liab-title    { background: linear-gradient(90deg, #b5460a, #e65100); }

        /* Subsection label (e.g. "Fixed Assets") */
        .bs-sub-label {
            background: #eef3fb;
            border-left: 4px solid #2196f3;
            padding: 7px 16px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #1a3a5c;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-top: 2px;
        }

        .bs-sub-label.equity-sub  { border-left-color: #7c4dff; background: #f3f0ff; color: #3d1f8a; }
        .bs-sub-label.liab-sub    { border-left-color: #e65100; background: #fff3ee; color: #7a2800; }

        /* Individual account row */
        .bs-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 7px 18px;
            border-bottom: 1px solid #f0f3f8;
            font-size: 0.875rem;
            transition: background 0.15s;
        }
        .bs-row:hover { background: #f7f9ff; }

        .bs-row .acct-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }

        .bs-row .acct-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #2c3e50;
        }

        .bs-row .acct-amount {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1a6fc4;
            white-space: nowrap;
            padding-left: 12px;
        }

        .bs-row .acct-amount.equity-amt { color: #5c35a8; }
        .bs-row .acct-amount.liab-amt   { color: #b5460a; }

        /* Subtotal row (within a section) */
        .bs-subtotal {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 18px;
            background: #e8f0fd;
            border-top: 1px solid #b8cfee;
            border-bottom: 1px solid #b8cfee;
            font-size: 0.85rem;
            font-weight: 700;
            color: #1a3a5c;
            margin-top: 4px;
        }
        .bs-subtotal.equity-sub-total  { background: #ede8ff; border-color: #c5b5f5; color: #3d1f8a; }
        .bs-subtotal.liab-sub-total    { background: #ffeee6; border-color: #f5c4a8; color: #7a2800; }

        /* Grand total bar */
        .bs-grand-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 18px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #fff;
            margin-top: auto;
        }
        .bs-grand-total.assets-total   { background: linear-gradient(90deg, #1a6fc4, #2196f3); }
        .bs-grand-total.equity-total   { background: linear-gradient(90deg, #5c35a8, #7c4dff); }
        .bs-grand-total.liab-total     { background: linear-gradient(90deg, #b5460a, #e65100); }
        .bs-grand-total.el-total       { background: linear-gradient(90deg, #2e7d32, #43a047); }

        /* Spacer between equity and liabilities in right column */
        .bs-section-gap { height: 16px; background: #f0f2f5; }

        /* balance sheet old styles kept for compatibility */
        .balance-sheet-table { width: 100%; table-layout: fixed; }
        .balance-sheet-table td { vertical-align: top; padding: 0 10px; width: 50%; }

        /* ===================================
           INCOME STATEMENT â€” ENHANCED CLARITY
        ==================================== */
        .is-wrapper {
            border: 2px solid #c8d0e0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        }

        /* Two-column income statement layout */
        .is-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            align-items: start;
        }

        .is-col {
            background: #fff;
            min-width: 0;
        }

        .is-col:first-child {
            border-right: 2px solid #c8d0e0;
        }

        /* Section header */
        .is-section-title {
            padding: 14px 18px;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .is-section-title.rev-title  { background: linear-gradient(90deg, #1a6fc4, #2196f3); }
        .is-section-title.exp-title  { background: linear-gradient(90deg, #b5460a, #e65100); }

        /* Subsection label */
        .is-sub-label {
            padding: 7px 16px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-top: 2px;
        }
        .is-sub-label.rev-sub  { background: #eef3fb; border-left: 4px solid #2196f3; color: #1a3a5c; }
        .is-sub-label.exp-sub  { background: #fff3ee; border-left: 4px solid #e65100; color: #7a2800; }

        /* Account row */
        .is-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 7px 18px;
            border-bottom: 1px solid #f0f3f8;
            font-size: 0.875rem;
            transition: background 0.15s;
        }
        .is-row:hover { background: #f7f9ff; }

        .is-row .acct-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }

        .is-row .acct-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #2c3e50;
        }

        .is-row .acct-amount {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            font-weight: 600;
            white-space: nowrap;
            padding-left: 12px;
        }
        .is-row .acct-amount.rev-amt { color: #1a6fc4; }
        .is-row .acct-amount.exp-amt { color: #b5460a; }

        /* Class subtotal */
        .is-class-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 18px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-top: 4px;
        }
        .is-class-total.rev-class-total { background: #e8f0fd; border-top: 1px solid #b8cfee; color: #1a3a5c; }
        .is-class-total.exp-class-total { background: #ffeee6; border-top: 1px solid #f5c4a8; color: #7a2800; }

        /* Section grand total */
        .is-section-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 18px;
            font-size: 0.88rem;
            font-weight: 700;
            color: #fff;
        }
        .is-section-total.rev-total { background: linear-gradient(90deg, #1a6fc4, #2196f3); }
        .is-section-total.exp-total { background: linear-gradient(90deg, #b5460a, #e65100); }

        /* Net income / loss banner */
        .is-net-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
        }
        .is-net-bar.profit { background: linear-gradient(90deg, #2e7d32, #43a047); }
        .is-net-bar.loss   { background: linear-gradient(90deg, #b71c1c, #e53935); }
        .is-net-bar .net-label { font-size: 0.82rem; letter-spacing: 0.1em; text-transform: uppercase; opacity: 0.85; }
        .is-net-bar .net-amount { font-size: 1.25rem; }

        /* ===== SHARED UTILITIES ===== */
        .account-code {
            font-family: 'Courier New', monospace;
            background: #f0f3f8;
            padding: 1px 5px;
            border-radius: 3px;
            border: 1px solid #d0d8e8;
            font-size: 0.78rem;
            white-space: nowrap;
        }

        @media print {
            .no-print { display: none; }
            body { background: #fff; }
            .bs-wrapper, .is-wrapper { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Report Header -->
        <div class="row mb-4 no-print">
            <div class="col-12">
                <h2 class="h4 fw-bold text-primary">Financial Reports</h2>
                <p class="text-muted">Generate and view financial reports from your General Ledger</p>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="row mb-3 no-print">
            <div class="col-12">
                <div class="timeframe-filter">
                    <h5><i class="fas fa-calendar-alt me-2"></i>Report Period</h5>
                    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3 align-items-end" id="reportFilter">
                        <?php
                        // Preserve ALL existing GET params except the ones we control, so the router doesn't lose its page key
                        foreach ($_GET as $key => $value) {
                            if (!in_array($key, ['type', 'start_date', 'end_date'])) {
                                echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                            }
                        }
                        // If no 'page' param exists in GET (direct file access), still emit it
                        if (!isset($_GET['page'])) {
                            echo '<input type="hidden" name="page" value="financial_reports">';
                        }
                        ?>
                        <input type="hidden" name="type" id="report_type_input" value="<?php echo htmlspecialchars($report_type); ?>">
                        
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" id="finStartDate"
                                   onchange="this.form.submit()"
                                   value="<?php echo htmlspecialchars($start_date); ?>" required>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" id="finEndDate"
                                   onchange="this.form.submit()"
                                   value="<?php echo htmlspecialchars($end_date); ?>" required>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filter
                            </button>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Quick Select:</label>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary quick-date-btn" onclick="setDateRange('today')">
                                    Today
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary quick-date-btn" onclick="setDateRange('month')">
                                    This Month
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary quick-date-btn" onclick="setDateRange('year')">
                                    This Year
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

