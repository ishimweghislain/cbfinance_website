<?php
require_once __DIR__ . '/config/database.php';
$conn = getConnection();

// Get report parameters
$report_type = $_GET['report'] ?? 'overview';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$status_filter = $_GET['status'] ?? '';

// Overview Report
if ($report_type == 'overview') {
    $report_query = "SELECT 
        COUNT(*) as total_loans,
        SUM(disbursement_amount) as total_disbursed,
        SUM(total_outstanding) as total_outstanding,
        SUM(principal_outstanding) as principal_outstanding,
        SUM(interest_outstanding) as interest_outstanding,
        AVG(interest_rate) as avg_interest_rate,
        SUM(CASE WHEN loan_status = 'Active' THEN 1 ELSE 0 END) as active_loans,
        SUM(CASE WHEN loan_status = 'Closed' THEN 1 ELSE 0 END) as closed_loans,
        SUM(CASE WHEN days_overdue > 0 THEN 1 ELSE 0 END) as overdue_loans,
        SUM(collateral_value) as total_collateral
        FROM loan_portfolio
        WHERE disbursement_date BETWEEN ? AND ?";
    
    if ($status_filter) {
        $report_query .= " AND loan_status = ?";
        $stmt = $conn->prepare($report_query);
        $stmt->bind_param('sss', $start_date, $end_date, $status_filter);
    } else {
        $stmt = $conn->prepare($report_query);
        $stmt->bind_param('ss', $start_date, $end_date);
    }
    $stmt->execute();
    $report_data = $stmt->get_result()->fetch_assoc();
}

// Performance Report
if ($report_type == 'performance') {
    $report_query = "SELECT 
        loan_status,
        COUNT(*) as loan_count,
        SUM(disbursement_amount) as total_amount,
        SUM(total_outstanding) as total_outstanding,
        AVG(interest_rate) as avg_interest,
        AVG(days_overdue) as avg_days_overdue
        FROM loan_portfolio
        WHERE disbursement_date BETWEEN ? AND ?
        GROUP BY loan_status
        ORDER BY loan_status";
    
    $stmt = $conn->prepare($report_query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $performance_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Collection Report
if ($report_type == 'collection') {
    $report_query = "SELECT 
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        COUNT(DISTINCT r.loan_id) as loans_paid,
        COUNT(r.repayment_id) as payments_count,
        SUM(r.principal_amount) as principal_collected,
        SUM(r.interest_amount) as interest_collected,
        SUM(r.fees_amount) as fees_collected,
        SUM(r.penalty_amount) as penalty_collected,
        SUM(r.total_amount) as total_collected
        FROM loan_repayments r
        JOIN loan_portfolio l ON r.loan_id = l.loan_id
        WHERE r.payment_date BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month DESC";
    
    $stmt = $conn->prepare($report_query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $collection_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Overdue Report
if ($report_type == 'overdue') {
    $report_query = "SELECT 
        l.loan_id,
        l.loan_number,
        l.loan_status,
        l.disbursement_amount,
        l.total_outstanding,
        l.principal_outstanding,
        l.disbursement_date,
        c.customer_name,
        c.phone,
        MAX(DATEDIFF(CURDATE(), i.due_date)) as days_overdue_amount,
        SUM(i.balance_remaining) as overdue_balance
        FROM loan_portfolio l
        JOIN customers c ON l.customer_id = c.customer_id
        JOIN loan_instalments i ON l.loan_id = i.loan_id
        WHERE i.due_date < CURDATE()
        AND i.balance_remaining > 0
        AND l.disbursement_date BETWEEN ? AND ?
        GROUP BY l.loan_id
        ORDER BY days_overdue_amount DESC";
    
    $stmt = $conn->prepare($report_query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $overdue_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate grand totals
    $overdue_total_outstanding = array_sum(array_column($overdue_data, 'overdue_balance'));
    $overdue_total_principal   = array_sum(array_column($overdue_data, 'principal_outstanding'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>
    <div class="container-fluid">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
            <div class="container-fluid">
                <a class="navbar-brand" href="index.php">
                    <i class="bi bi-bank me-2"></i>Loan Management System
                </a>
                <div class="collapse navbar-collapse">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-house-door"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="create_loan.php">
                                <i class="bi bi-plus-circle"></i> New Loan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="loan_reports.php">
                                <i class="bi bi-graph-up"></i> Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-graph-up me-2"></i>Loan Reports
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Report Filters -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label>Report Type</label>
                                <select name="report" class="form-select" onchange="this.form.submit()">
                                    <option value="overview" <?php echo $report_type == 'overview' ? 'selected' : ''; ?>>Overview Report</option>
                                    <option value="performance" <?php echo $report_type == 'performance' ? 'selected' : ''; ?>>Performance Report</option>
                                    <option value="collection" <?php echo $report_type == 'collection' ? 'selected' : ''; ?>>Collection Report</option>
                                    <option value="overdue" <?php echo $report_type == 'overdue' ? 'selected' : ''; ?>>Overdue Loans Report</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Start Date</label>
                                <input type="date" name="start_date" class="form-control" 
                                       onchange="this.form.submit()"
                                       value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label>End Date</label>
                                <input type="date" name="end_date" class="form-control" 
                                       onchange="this.form.submit()"
                                       value="<?php echo $end_date; ?>">
                            </div>

                            <div class="col-md-3">
                                <label>Status Filter</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="Active" <?php echo $status_filter == 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Closed" <?php echo $status_filter == 'Closed' ? 'selected' : ''; ?>>Closed</option>
                                    <option value="Performing" <?php echo $status_filter == 'Performing' ? 'selected' : ''; ?>>Performing</option>
                                    <option value="Non-Performing" <?php echo $status_filter == 'Non-Performing' ? 'selected' : ''; ?>>Non-Performing</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">Generate Report</button>
                                <a href="export_report.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                                    <i class="bi bi-download"></i> Export
                                </a>
                            </div>
                        </form>
                        
                        <!-- Report Content -->
                        <?php if ($report_type == 'overview'): ?>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white mb-3">
                                        <div class="card-body">
                                            <h6>Total Loans</h6>
                                            <h3><?php echo number_format($report_data['total_loans']); ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white mb-3">
                                        <div class="card-body">
                                            <h6>Total Disbursed</h6>
                                            <h3><?php echo number_format($report_data['total_disbursed'], 2); ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning text-white mb-3">
                                        <div class="card-body">
                                            <h6>Total Outstanding</h6>
                                            <h3><?php echo number_format($report_data['total_outstanding'], 2); ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info text-white mb-3">
                                        <div class="card-body">
                                            <h6>Avg Interest Rate</h6>
                                            <h3><?php echo number_format($report_data['avg_interest_rate'], 2); ?>%</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6>Active Loans</h6>
                                            <h4><?php echo number_format($report_data['active_loans']); ?></h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6>Overdue Loans</h6>
                                            <h4 class="text-danger"><?php echo number_format($report_data['overdue_loans']); ?></h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6>Total Collateral Value</h6>
                                            <h4><?php echo number_format($report_data['total_collateral'], 2); ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        <?php elseif ($report_type == 'performance'): ?>
                            <table class="table table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Status</th>
                                        <th>Loan Count</th>
                                        <th>Total Amount</th>
                                        <th>Outstanding</th>
                                        <th>Avg Interest</th>
                                        <th>Avg Days Overdue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($performance_data as $row): ?>
                                    <tr>
                                        <td><span class="badge bg-primary"><?php echo $row['loan_status']; ?></span></td>
                                        <td><?php echo number_format($row['loan_count']); ?></td>
                                        <td><?php echo number_format($row['total_amount'], 2); ?></td>
                                        <td><?php echo number_format($row['total_outstanding'], 2); ?></td>
                                        <td><?php echo number_format($row['avg_interest'], 2); ?>%</td>
                                        <td><?php echo number_format($row['avg_days_overdue'], 0); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                        <?php elseif ($report_type == 'collection'): ?>
                            <table class="table table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Month</th>
                                        <th>Loans Paid</th>
                                        <th>Payments</th>
                                        <th>Principal</th>
                                        <th>Interest</th>
                                        <th>Fees</th>
                                        <th>Penalty</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($collection_data as $row): ?>
                                    <tr>
                                        <td><?php echo $row['month']; ?></td>
                                        <td><?php echo number_format($row['loans_paid']); ?></td>
                                        <td><?php echo number_format($row['payments_count']); ?></td>
                                        <td><?php echo number_format($row['principal_collected'], 2); ?></td>
                                        <td><?php echo number_format($row['interest_collected'], 2); ?></td>
                                        <td><?php echo number_format($row['fees_collected'], 2); ?></td>
                                        <td><?php echo number_format($row['penalty_collected'], 2); ?></td>
                                        <td class="fw-bold"><?php echo number_format($row['total_collected'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                        <?php elseif ($report_type == 'overdue'): ?>
                            <div class="alert alert-danger">
                                <h5>Overdue Loans Report</h5>
                                <p>Showing <strong><?php echo count($overdue_data); ?></strong> overdue loans &mdash;
                                   Period: <strong><?php echo date('d M Y', strtotime($start_date)); ?></strong> to <strong><?php echo date('d M Y', strtotime($end_date)); ?></strong>
                                </p>
                            </div>
                            
                            <table class="table table-bordered table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Loan Number</th>
                                        <th>Customer</th>
                                        <th>Contact</th>
                                        <th>Disbursement Date</th>
                                        <th>Disbursed Amount</th>
                                        <th>Principal Outstanding</th>
                                        <th>Overdue Balance</th>
                                        <th>Days Overdue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($overdue_data as $row): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['loan_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($row['disbursement_date'])); ?></td>
                                        <td><?php echo number_format($row['disbursement_amount'], 2); ?></td>
                                        <td><?php echo number_format($row['principal_outstanding'], 2); ?></td>
                                        <td class="text-danger fw-bold"><?php echo number_format($row['overdue_balance'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-danger">
                                                <?php echo $row['days_overdue_amount']; ?> days
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-dark fw-bold">
                                    <tr>
                                        <td colspan="5" class="text-end">TOTALS:</td>
                                        <td><?php echo number_format($overdue_total_principal, 2); ?></td>
                                        <td class="text-danger"><?php echo number_format($overdue_total_outstanding, 2); ?></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                            <style>
                                /* Add these styles to the existing style section */
body {
    font-size: 12px !important;
}

.card {
    margin-bottom: 0.5rem !important;
}

.card-header {
    padding: 0.5rem 1rem !important;
}

.card-body {
    padding: 0.75rem !important;
}

.card-title, .card-header h5, .card-header h6 {
    font-size: 0.9rem !important;
    margin-bottom: 0.25rem !important;
}

.table {
    font-size: 0.8rem !important;
}

.table th, .table td {
    padding: 0.3rem 0.5rem !important;
}

.table-sm th, .table-sm td {
    padding: 0.2rem 0.4rem !important;
}

.form-control, .form-select {
    font-size: 0.85rem !important;
    padding: 0.25rem 0.5rem !important;
    height: calc(1.5em + 0.5rem) !important;
}

.btn {
    font-size: 0.8rem !important;
    padding: 0.25rem 0.5rem !important;
}

.btn-sm {
    font-size: 0.75rem !important;
    padding: 0.2rem 0.4rem !important;
}

.badge {
    font-size: 0.7rem !important;
    padding: 0.15rem 0.35rem !important;
}

.alert {
    font-size: 0.8rem !important;
    padding: 0.5rem 1rem !important;
    margin-bottom: 0.5rem !important;
}

h1, .h1 { font-size: 1.5rem !important; }
h2, .h2 { font-size: 1.3rem !important; }
h3, .h3 { font-size: 1.2rem !important; }
h4, .h4 { font-size: 1.1rem !important; }
h5, .h5 { font-size: 1rem !important; }
h6, .h6 { font-size: 0.9rem !important; }

.mb-1 { margin-bottom: 0.25rem !important; }
.mb-2 { margin-bottom: 0.5rem !important; }
.mb-3 { margin-bottom: 0.75rem !important; }
.mb-4 { margin-bottom: 1rem !important; }
.mb-5 { margin-bottom: 1.25rem !important; }

.mt-1 { margin-top: 0.25rem !important; }
.mt-2 { margin-top: 0.5rem !important; }
.mt-3 { margin-top: 0.75rem !important; }
.mt-4 { margin-top: 1rem !important; }
.mt-5 { margin-top: 1.25rem !important; }

.py-1 { padding-top: 0.25rem !important; padding-bottom: 0.25rem !important; }
.py-2 { padding-top: 0.5rem !important; padding-bottom: 0.5rem !important; }
.py-3 { padding-top: 0.75rem !important; padding-bottom: 0.75rem !important; }
.py-4 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
.py-5 { padding-top: 1.25rem !important; padding-bottom: 1.25rem !important; }

.px-1 { padding-left: 0.25rem !important; padding-right: 0.25rem !important; }
.px-2 { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
.px-3 { padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
.px-4 { padding-left: 1rem !important; padding-right: 1rem !important; }
.px-5 { padding-left: 1.25rem !important; padding-right: 1.25rem !important; }

.input-group-sm > .form-control,
.input-group-sm > .form-select {
    font-size: 0.75rem !important;
    padding: 0.2rem 0.4rem !important;
    height: calc(1.5em + 0.4rem) !important;
}

.btn-group-sm > .btn {
    font-size: 0.7rem !important;
    padding: 0.15rem 0.3rem !important;
}

/* Reduce spacing in flex gaps */
.gap-1 { gap: 0.25rem !important; }
.gap-2 { gap: 0.5rem !important; }
.gap-3 { gap: 0.75rem !important; }

/* Make text-muted even smaller */
.text-muted {
    font-size: 0.75rem !important;
}

/* Reduce line heights */
h1, h2, h3, h4, h5, h6 {
    line-height: 1.2 !important;
}

p, .form-label, .small {
    line-height: 1.3 !important;
}

/* Compact table rows */
.table-hover tbody tr {
    line-height: 1.2 !important;
}

/* Make icons smaller if needed */
.bi {
    font-size: 0.9em !important;
}

/* Report header adjustments */
.report-header h4 { font-size: 1.1rem !important; }
.report-header h5 { font-size: 1rem !important; }
.report-header p { font-size: 0.8rem !important; }

/* Form labels smaller */
.form-label {
    font-size: 0.8rem !important;
    margin-bottom: 0.1rem !important;
}

.form-check-label {
    font-size: 0.8rem !important;
}

/* Reduce spacing in rows */
.row {
    margin-bottom: 0.25rem !important;
}

/* Make the entire UI more compact */
.container-fluid {
    padding: 0.5rem !important;
}

/* Adjust breadcrumb if present */
.breadcrumb {
    padding: 0.25rem 0.5rem !important;
    font-size: 0.8rem !important;
    margin-bottom: 0.5rem !important;
}
                            </style>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
