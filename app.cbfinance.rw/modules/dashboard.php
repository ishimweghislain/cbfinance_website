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
$role = $_SESSION['role'] ?? 'Secretary';


$conn = getConnection();

// Initialize variables
$stats = [];
$recent_loans = null;
$classes_summary = null;

try {
    // Get dashboard statistics
    
    // 1. Total Active Loans
    $result = $conn->query("SELECT COUNT(*) as total_loans, COALESCE(SUM(total_outstanding), 0) as total_outstanding FROM loan_portfolio WHERE loan_status IN ('Active', 'Performing')");
    if ($result) {
        $stats['loans'] = $result->fetch_assoc();
    }
    
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
    
    // 4. Total Revenue - Calculate from ledger where account_code starts with 4
    $result = $conn->query("SELECT COALESCE(SUM(credit_amount - debit_amount), 0) as total_revenue FROM ledger WHERE account_code LIKE '4%'");
    if ($result) {
        $stats['revenue'] = $result->fetch_assoc();
    }
    
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
    
    // 13. Overdue Loans
    $result = $conn->query("SELECT COUNT(*) as overdue_loans, COALESCE(SUM(total_outstanding), 0) as overdue_amount FROM loan_portfolio WHERE days_overdue > 0 AND loan_status = 'Active'");
    if ($result) {
        $stats['overdue'] = $result->fetch_assoc();
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

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <!-- Total Loans Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2 stat-card">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                Active Loans
                            </div>
                            <div class="h5 mb-0 fw-bold text-dark">
                                <?php echo number_format($stats['loans']['total_loans'] ?? 0); ?>
                            </div>
                            <div class="text-xs text-muted mt-1">
                                <i class="fas fa-money-bill-wave fa-fw me-1"></i>
                                <?php echo number_format($stats['loans']['total_outstanding'] ?? 0, 2); ?> Outstanding
                            </div>
                            <?php if(isset($stats['overdue']) && $stats['overdue']['overdue_loans'] > 0): ?>
                            <div class="small-stat text-danger mt-1">
                                <i class="fas fa-exclamation-triangle fa-fw me-1"></i>
                                <?php echo $stats['overdue']['overdue_loans']; ?> overdue <?php echo number_format($stats['overdue']['overdue_amount'], 2); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hand-holding-usd fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Customers Card -->
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
                                <?php echo number_format($stats['pending']['pending_applications'] ?? 0); ?> Pending Applications
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
        <div class="col-lg-full mb-4">
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
