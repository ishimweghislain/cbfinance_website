<?php

// index.php
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user info from session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$full_name = $_SESSION['full_name'] ?? 'System User';
$email = $_SESSION['email'] ?? 'user@cbfinance.rw';
$role = $_SESSION['role'] ?? 'Guest';


$conn = getConnection();

// Initialize variables
$stats = [];
$recent_loans = null;
$classes_summary = null;

try {
    // Get dashboard statistics
    
    // 1. Unified Portfolio Metrics (Live Math for Accuracy)
    $portfolio_res = $conn->query("SELECT 
        -- Total Disbursed (Global)
        COALESCE(SUM(total_disbursed), 0) as total_distributed,
        
        -- Active Portfolio metrics (Active, Performing, Overdue, Written-off)
        COALESCE(SUM(CASE WHEN lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off') THEN 
            (SELECT SUM(GREATEST(0, principal_amount - principal_paid)) FROM loan_instalments WHERE loan_id = lp.loan_id) 
        ELSE 0 END), 0) as active_principal,
        
        COALESCE(SUM(CASE WHEN lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off') THEN 
            (SELECT SUM(GREATEST(0, interest_amount - interest_paid)) FROM loan_instalments WHERE loan_id = lp.loan_id) 
        ELSE 0 END), 0) as active_interest,
        
        COALESCE(SUM(CASE WHEN lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off') THEN 
            (SELECT SUM(GREATEST(0, principal_amount - principal_paid + interest_amount - interest_paid)) FROM loan_instalments WHERE loan_id = lp.loan_id) 
        ELSE 0 END), 0) as portfolio_value,
        
        -- Total Overdue (Live)
        COALESCE(SUM(CASE WHEN lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off') THEN 
            (SELECT SUM(balance_remaining) FROM loan_instalments WHERE loan_id = lp.loan_id AND due_date < CURDATE()) 
        ELSE 0 END), 0) as total_overdue,
        
        COUNT(CASE WHEN lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off') THEN 1 END) as active_loans_count
        FROM loan_portfolio lp");
    
    $stats['portfolio'] = $portfolio_res->fetch_assoc();
    
    // Legacy mapping (to avoid breaking other code if any)
    $stats['loans']['count'] = $stats['portfolio']['active_loans_count'];
    $stats['global']['total_disbursed'] = $stats['portfolio']['total_distributed'];
    $stats['loans']['principal_outstanding'] = $stats['portfolio']['active_principal'];
    $stats['loans']['total_outstanding'] = $stats['portfolio']['portfolio_value'];
    $stats['overdue']['overdue_amount'] = $stats['portfolio']['total_overdue'];
    
    // 2. Total Active Customers
    $result = $conn->query("SELECT COUNT(*) as total_customers FROM customers WHERE is_active = 1");
    if ($result) {
        $stats['customers'] = $result->fetch_assoc();
    }
    
    // 3. Total Assets - Get current book value from assets table
    $result = $conn->query("SELECT COALESCE(SUM(GREATEST(0, (acquisition_value + additions) - COALESCE(accumulated_depreciation, 0))), 0) as total_assets FROM assets WHERE disposal_date IS NULL");
    if ($result) {
        $stats['assets'] = $result->fetch_assoc();
    }
    
    // 4. Total Revenue — Sophisticated Paid-Basis logic matching Official Reports
    // 4.1 Installments component (Capped logic for accurate recognition)
    $rev_li = $conn->query("SELECT 
        SUM(CASE WHEN balance_remaining <= 0 THEN interest_amount ELSE interest_paid END) as interest,
        SUM(CASE WHEN balance_remaining <= 0 THEN management_fee ELSE management_fee_paid END) as periodic_fee,
        SUM(CASE WHEN balance_remaining <= 0 THEN penalty_amount ELSE penalty_paid END) as penalty
        FROM loan_instalments")->fetch_assoc();

    // 4.2 Upfront Disbursement Fees (Account 4202)
    $rev_up = $conn->query("SELECT SUM(management_fee_amount) as upfront 
        FROM loan_portfolio lp 
        WHERE (SELECT management_fee FROM loan_instalments WHERE loan_id = lp.loan_id AND instalment_number = 1 LIMIT 1) = 0")->fetch_assoc();

    // 4.3 Ledger-only Revenue (Other 4xxx accounts)
    $rev_led = $conn->query("SELECT SUM(credit_amount - debit_amount) as other 
        FROM ledger 
        WHERE SUBSTRING(account_code, 1, 1) = '4' 
        AND account_code NOT IN ('4101', '4201', '4202', '4205')")->fetch_assoc();

    $stats['revenue']['total_revenue'] = ($rev_li['interest'] ?? 0) + ($rev_li['periodic_fee'] ?? 0) + ($rev_li['penalty'] ?? 0) + ($rev_up['upfront'] ?? 0) + ($rev_led['other'] ?? 0);
    
    // 5. Recent Loans
    $recent_loans = $conn->query("SELECT 
        lp.loan_number, 
        c.customer_name, 
        lp.disbursement_amount, 
        lp.disbursement_date, 
        lp.loan_status,
        COALESCE(lp.total_outstanding, 0) as outstanding_amount
    FROM loan_portfolio lp 
    LEFT JOIN customers c ON lp.customer_id = c.customer_id 
    ORDER BY lp.created_at DESC 
    LIMIT 5");
    
    // 6. Account Classes Summary - Using chart_of_accounts structure
    $classes_summary = $conn->query("SELECT 
        class as class_name, 
        COUNT(*) as account_count, 
        COALESCE(SUM(CASE 
            WHEN normal_balance = 'Debit' THEN current_balance 
            ELSE -current_balance 
        END), 0) as total_balance 
    FROM chart_of_accounts 
    WHERE is_active = 1
    GROUP BY class 
    ORDER BY 
        CASE class 
            WHEN 'Assets' THEN 1
            WHEN 'Liabilities' THEN 2
            WHEN 'Equity' THEN 3
            WHEN 'Revenue' THEN 4
            WHEN 'Expenses' THEN 5
            ELSE 6
        END");
    
    // 7. Total Expenses
    $result = $conn->query("SELECT COALESCE(SUM(expense_amount), 0) as total_expenses FROM expenses");
    if ($result) {
        $stats['expenses'] = $result->fetch_assoc();
    }
    
    // 8. Today's Payments from loan_payments
    $result = $conn->query("SELECT COALESCE(SUM(payment_amount), 0) as payments_today FROM loan_payments WHERE DATE(payment_date) = CURDATE()");
    if ($result) {
        $stats['payments_today'] = $result->fetch_assoc();
    }
    
    // 9. Pending Applications from loan_portfolio
    $result = $conn->query("SELECT COUNT(*) as pending_applications FROM loan_portfolio WHERE loan_status = 'Pending'");
    if ($result) {
        $stats['pending'] = $result->fetch_assoc();
    }
    
    // 10. Application Fees Today
    $result = $conn->query("SELECT COALESCE(SUM(income_amount), 0) as fees_today FROM application_fees WHERE DATE(fee_date) = CURDATE()");
    if ($result) {
        $stats['fees_today'] = $result->fetch_assoc();
    }
    
    // 11. Total Liabilities
    $result = $conn->query("SELECT COALESCE(SUM(liability_amount), 0) as total_liabilities FROM liabilities WHERE status = 'Active'");
    if ($result) {
        $stats['liabilities'] = $result->fetch_assoc();
    }
    
    // 12. Total Equity - Calculate as Assets - Liabilities
    $total_assets = $stats['assets']['total_assets'] ?? 0;
    $total_liabilities = $stats['liabilities']['total_liabilities'] ?? 0;
    $stats['equity']['total_equity'] = $total_assets - $total_liabilities;
    
    // 13. Overdue Loans - Live Sync with Portfolio
    $result = $conn->query("SELECT 
        COUNT(*) as overdue_loans, 
        COALESCE(SUM((SELECT SUM(balance_remaining) FROM loan_instalments WHERE loan_id = lp.loan_id AND due_date < CURDATE())), 0) as overdue_amount 
        FROM loan_portfolio lp
        WHERE lp.loan_status IN ('Active', 'Performing', 'Overdue', 'Written-off')
        AND (SELECT COUNT(*) FROM loan_instalments WHERE loan_id = lp.loan_id AND balance_remaining > 0 AND due_date < CURDATE()) > 0");
    if ($result) {
        $stats['overdue'] = $result->fetch_assoc();
        // Sync the value back to the primary portfolio stats for the header card
        $stats['portfolio']['total_overdue'] = $stats['overdue']['overdue_amount'];
    }

    // 14. Latest Activity Logs (for Developers)
    if ($role === 'Developer') {
        $stats['latest_logs'] = $conn->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 6");
    }
    
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    // Continue showing the page even if there are errors
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-size: 14px;
            background-color: #f8f9fa;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
        }
        
        .stat-card {
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .border-left-primary {
            border-left: 4px solid #4e73df !important;
        }
        
        .border-left-success {
            border-left: 4px solid #1cc88a !important;
        }
        
        .border-left-info {
            border-left: 4px solid #36b9cc !important;
        }
        
        .border-left-warning {
            border-left: 4px solid #f6c23e !important;
        }
        
        .border-left-danger {
            border-left: 4px solid #e74a3b !important;
        }
        
        .text-xs {
            font-size: 0.85rem;
        }
        
        .table th {
            font-weight: 600;
            border-top: none;
        }
        
        .badge {
            font-weight: 500;
        }
        
        .h-100 {
            min-height: 120px;
        }
        
        /* Compact styles for better space usage */
        .table-sm th, .table-sm td {
            padding: 0.4rem 0.5rem;
        }
        
        .btn-sm {
            padding: 0.2rem 0.5rem;
            font-size: 0.8rem;
        }
        
        .small-stat {
            font-size: 0.75rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="h4 fw-bold text-primary">Dashboard Overview</h2>
            <p class="text-muted">Welcome to your accounting and loan management system</p>
        </div>
    </div>

    <!-- Unified Portfolio Statistics -->
    <div class="row mb-4">
        <!-- Card 1: Total Distributed -->
        <div class="col-xl-2 col-md-4 mb-4" style="width: 20%;" title="Total amount disbursed across all loans since inception.">
            <div class="card border-left-primary shadow h-100 py-2 stat-card">
                <div class="card-body">
                    <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Distributed</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?php echo number_format($stats['portfolio']['total_distributed'], 2); ?></div>
                    <div class="small-stat text-muted mt-2">All-time disbursement</div>
                </div>
            </div>
        </div>

        <!-- Card 2: Active Principal -->
        <div class="col-xl-2 col-md-4 mb-4" style="width: 20%;" title="Remaining principal (capital) to be collected from active and written-off loans.">
            <div class="card border-left-success shadow h-100 py-2 stat-card">
                <div class="card-body">
                    <div class="text-xs fw-bold text-success text-uppercase mb-1">Active Principal</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?php echo number_format($stats['portfolio']['active_principal'], 2); ?></div>
                    <div class="small-stat text-muted mt-2">Outstanding Principal</div>
                </div>
            </div>
        </div>

        <!-- Card 3: Active Interest -->
        <div class="col-xl-2 col-md-4 mb-4" style="width: 20%;" title="Remaining interest revenue to be collected from the active portfolio.">
            <div class="card border-left-info shadow h-100 py-2 stat-card">
                <div class="card-body">
                    <div class="text-xs fw-bold text-info text-uppercase mb-1">Active Interest</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?php echo number_format($stats['portfolio']['active_interest'], 2); ?></div>
                    <div class="small-stat text-muted mt-2">Expected Interest</div>
                </div>
            </div>
        </div>

        <!-- Card 4: Portfolio Value (P+I) -->
        <div class="col-xl-2 col-md-4 mb-4" style="width: 20%;" title="Total outstanding balance (Principal + Interest) currently in the market.">
            <div class="card border-left-warning shadow h-100 py-2 stat-card">
                <div class="card-body">
                    <div class="text-xs fw-bold text-warning text-uppercase mb-1">Portfolio Value (P+I)</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?php echo number_format($stats['portfolio']['portfolio_value'], 2); ?></div>
                    <div class="small-stat text-muted mt-2">Total remaining balance</div>
                </div>
            </div>
        </div>

        <!-- Card 5: Total Overdue -->
        <div class="col-xl-2 col-md-4 mb-4" style="width: 20%;" title="Total amount from instalments whose due dates have already passed and remain unpaid.">
            <div class="card border-left-danger shadow h-100 py-2 stat-card">
                <div class="card-body">
                    <div class="text-xs fw-bold text-danger text-uppercase mb-1">Total Overdue</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?php echo number_format($stats['portfolio']['total_overdue'], 2); ?></div>
                    <div class="small-stat text-danger mt-2">Due & Unpaid instalments</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Statistics Cards -->
    <div class="row mb-4">
        <!-- Active Customers Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2 stat-card">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                Active Customers
                            </div>
                            <div class="h5 mb-0 fw-bold text-dark">
                                <?php echo number_format($stats['customers']['total_customers'] ?? 0); ?>
                            </div>
                            <div class="text-xs text-muted mt-1">
                                <i class="fas fa-user-check fa-fw me-1"></i>
                                <?php echo number_format($stats['portfolio']['active_loans_count'] ?? 0); ?> Active Loans
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Assets Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2 stat-card">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">
                                Total Assets
                            </div>
                            <div class="h5 mb-0 fw-bold text-dark">
                                <?php echo number_format($stats['assets']['total_assets'] ?? 0, 2); ?>
                            </div>
                            <div class="text-xs text-muted mt-1">
                                <i class="fas fa-chart-line fa-fw me-1"></i>
                                Current Value
                            </div>
                            <?php if(isset($stats['liabilities']) && $stats['liabilities']['total_liabilities'] > 0): ?>
                            <div class="small-stat text-info mt-1">
                                <i class="fas fa-scale-balanced fa-fw me-1"></i>
                                Liabilities: <?php echo number_format($stats['liabilities']['total_liabilities'], 2); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-landmark fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2 stat-card">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                Total Revenue
                            </div>
                            <div class="h5 mb-0 fw-bold text-dark">
                                <?php echo number_format($stats['revenue']['total_revenue'] ?? 0, 2); ?>
                            </div>
                            <div class="text-xs text-muted mt-1">
                                <i class="fas fa-calendar fa-fw me-1"></i>
                                Year to Date
                            </div>
                            <?php if(isset($stats['fees_today']) && $stats['fees_today']['fees_today'] > 0): ?>
                            <div class="small-stat text-success mt-1">
                                <i class="fas fa-file-invoice-dollar fa-fw me-1"></i>
                                Today's Fees: <?php echo number_format($stats['fees_today']['fees_today'], 2); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-trend-up fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        
        <!-- Recent Loans -->
        <div class="<?= ($role === 'Developer') ? 'col-lg-8' : 'col-lg-12' ?> mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">Recent Loans</h6>
                    <a href="?page=loans" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Loan #</th>
                                    <th>Customer</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-center">Date</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($recent_loans && $recent_loans->num_rows > 0):
                                    while($row = $recent_loans->fetch_assoc()): 
                                        $status_class = '';
                                        $status_text = $row['loan_status'];
                                        
                                        if (in_array($row['loan_status'], ['Active', 'Performing'])) {
                                            $status_class = 'bg-success';
                                            $status_text = 'Active';
                                        } elseif (in_array($row['loan_status'], ['Pending'])) {
                                            $status_class = 'bg-warning';
                                            $status_text = 'Pending';
                                        } elseif (in_array($row['loan_status'], ['Closed', 'Paid'])) {
                                            $status_class = 'bg-info';
                                            $status_text = 'Closed';
                                        } elseif (in_array($row['loan_status'], ['Defaulted', 'Non-Performing'])) {
                                            $status_class = 'bg-danger';
                                            $status_text = 'Defaulted';
                                        } else {
                                            $status_class = 'bg-secondary';
                                        }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['loan_number']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['customer_name'] ?? 'N/A'); ?></td>
                                    <td class="text-end fw-bold">
                                        <?php echo number_format($row['disbursement_amount'], 2); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo !empty($row['disbursement_date']) ? date('M d, Y', strtotime($row['disbursement_date'])) : 'N/A'; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">No recent loans found</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($role === 'Developer'): ?>
        <!-- Latest Activity Logs -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-dark text-white">
                    <h6 class="m-0 fw-bold">Latest System Activity</h6>
                    <a href="?page=activity_logs" class="btn btn-sm btn-outline-light">Activity Center</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        <?php 
                        if (isset($stats['latest_logs']) && $stats['latest_logs']->num_rows > 0):
                            while($log = $stats['latest_logs']->fetch_assoc()):
                                $badge = 'secondary';
                                switch($log['action_type']) {
                                    case 'login': $badge = 'success'; break;
                                    case 'delete': $badge = 'danger'; break;
                                    case 'approve': $badge = 'primary'; break;
                                }
                        ?>
                        <li class="list-group-item px-3 py-2 border-0">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold"><?= htmlspecialchars($log['username']) ?></span>
                                <span class="text-muted" style="font-size:0.65rem;"><?= date('H:i', strtotime($log['created_at'])) ?></span>
                            </div>
                            <div class="d-flex align-items-center mt-1">
                                <span class="badge bg-<?= $badge ?> me-2" style="font-size:0.6rem;"><?= strtoupper($log['action_type']) ?></span>
                                <span class="text-truncate" title="<?= htmlspecialchars($log['description']) ?>"><?= htmlspecialchars($log['description']) ?></span>
                            </div>
                        </li>
                        <?php 
                            endwhile;
                        else: 
                        ?>
                        <li class="list-group-item text-center text-muted py-4">No recent activity</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Additional Stats Row -->
    <div class="row">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card shadow">
                <div class="card-body text-center">
                    <i class="fas fa-receipt fa-2x text-info mb-3"></i>
                    <h5 class="fw-bold text-dark">Today's Payments</h5>
                    <h3 class="fw-bold text-success">
                        <?php echo number_format($stats['payments_today']['payments_today'] ?? 0, 2); ?>
                    </h3>
                    <p class="text-muted mb-0">Received today</p>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card shadow">
                <div class="card-body text-center">
                    <i class="fas fa-file-invoice-dollar fa-2x text-warning mb-3"></i>
                    <h5 class="fw-bold text-dark">Expenses</h5>
                    <h3 class="fw-bold text-danger">
                        <?php echo number_format($stats['expenses']['total_expenses'] ?? 0, 2); ?>
                    </h3>
                    <p class="text-muted mb-0">Total expenses</p>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card shadow">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x text-secondary mb-3"></i>
                    <h5 class="fw-bold text-dark">Pending Applications</h5>
                    <h3 class="fw-bold text-warning">
                        <?php echo number_format($stats['pending']['pending_applications'] ?? 0); ?>
                    </h3>
                    <p class="text-muted mb-0">Awaiting review</p>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card shadow">
                <div class="card-body text-center">
                    <i class="fas fa-balance-scale fa-2x text-success mb-3"></i>
                    <h5 class="fw-bold text-dark">Equity</h5>
                    <h3 class="fw-bold text-dark">
                        <?php echo number_format($stats['equity']['total_equity'] ?? 0, 2); ?>
                    </h3>
                    <p class="text-muted mb-0">Total equity</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-refresh dashboard every 60 seconds
setTimeout(function() {
    location.reload();
}, 60000);

// Add some interactivity
document.addEventListener('DOMContentLoaded', function() {
    // Add click effects to stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function() {
            // Add your click action here
            console.log('Card clicked');
        });
    });
});
</script>
</body>
</html>

<?php 
// Clean up
if ($recent_loans) {
    $recent_loans->free();
}
if ($classes_summary) {
    $classes_summary->free();
}
if (isset($conn)) {
    $conn->close();
}
?>
