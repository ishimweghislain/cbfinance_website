<?php
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$user_role = $_SESSION['role'] ?? 'Secretary';

require_once __DIR__ . '/approval_helper.php';

// ── Due-today count for the sidebar badge ──────────────────────────────────
if (isset($due_today) && is_array($due_today)) {
    $sidebar_due_today_count = count($due_today);
} else {
    $sidebar_due_today_count = 0;
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn_sb = getConnection();
        if ($conn_sb) {
            $stmt_sb = $conn_sb->prepare("
                SELECT COUNT(*) AS cnt
                FROM loan_instalments li
                LEFT JOIN loan_portfolio lp ON li.loan_id = lp.loan_id
                WHERE li.status NOT IN ('Fully Paid')
                  AND li.due_date = CURDATE()
                  AND lp.loan_status NOT IN ('Closed', 'Written Off')
            ");
            if ($stmt_sb) {
                $stmt_sb->execute();
                $row_sb = $stmt_sb->get_result()->fetch_assoc();
                $sidebar_due_today_count = (int)($row_sb['cnt'] ?? 0);
                $stmt_sb->close();
            }
            $conn_sb->close();
        }
    } catch (Exception $e) {
        // Silent fail — never let a sidebar query break the page
    }
}

// ── Role Permissions Check Helpers ──────────────────────────────────────────
function canSeeAccounting($role) {
    return in_array($role, ['Director', 'Accountant']);
}
function canSeeBusinessReports($role) {
    return in_array($role, ['Director', 'MD', 'Accountant']);
}
function canSeeLoanManagement($role) {
    return in_array($role, ['Director', 'MD', 'Secretary']);
}
function canSeeReports($role) {
    return in_array($role, ['Director', 'MD']);
}
?>
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-primary sidebar">
    <div class="position-sticky pt-3">
        <div class="sidebar-header text-center py-4">
            <h4 class="text-white">
                <i class="bi bi-calculator"></i>
                <span class="ms-2">CB Finance</span>
            </h4>
            <div class="small text-white-50 mt-1"><?php echo htmlspecialchars($user_role); ?> Portal</div>
        </div>
        
        <ul class="nav flex-column">
            <!-- ── Accounting System (Director & Accountant) ── -->
            <?php if (canSeeAccounting($user_role)): ?>
            <li class="nav-item">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-2 mb-1 text-white-50">
                    <span>Accounting System</span>
                </h6>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'ledger' ? 'active' : ''; ?>" href="?page=ledger">
                    <i class="bi bi-journal-text me-2"></i> Ledger
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'chart_of_accounts' ? 'active' : ''; ?>" href="?page=chart_of_accounts">
                    <i class="bi bi-list-columns-reverse me-2"></i> Chart of Accounts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'expenses' ? 'active' : ''; ?>" href="?page=expenses">
                    <i class="bi bi-wallet2 me-2"></i> Expenses
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'assets' ? 'active' : ''; ?>" href="?page=assets">
                    <i class="bi bi-safe2 me-2"></i> Assets
                </a>
            </li>
            <?php endif; ?>

            <!-- ── Business Reports (Director, MD, Accountant) ── -->
            <?php if (canSeeBusinessReports($user_role)): ?>
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white-50">
                    <span>Business Reports</span>
                </h6>
                <a class="nav-link <?php echo $current_page == 'financial_report' ? 'active' : ''; ?>" href="?page=financial_report">
                    <i class="bi bi-graph-up-arrow me-2"></i> Financial Reports
                </a>
            </li>
            <?php endif; ?>

            <!-- ── Loan Management (Director, MD, Secretary) ── -->
            <?php if (canSeeLoanManagement($user_role)): ?>
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white-50">
                    <span>Loan Management</span>
                </h6>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'customers' ? 'active' : ''; ?>" href="?page=customers">
                    <i class="bi bi-people me-2"></i> Customers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'loans' ? 'active' : ''; ?>" href="?page=loans">
                    <i class="bi bi-cash-stack me-2"></i> Loans
                </a>
            </li>
            <?php
            $count_conn = getConnection();
            $pending_count = 0;
            if ($count_conn) {
                $c_res = $count_conn->query("SELECT COUNT(*) as total FROM customers WHERE status = 'Pending' OR status = 'Action Required' OR client_resubmitted = 1");
                if ($c_res) $pending_count = $c_res->fetch_assoc()['total'];
            }
            ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'pending_customers' ? 'active' : ''; ?>" href="?page=pending_customers">
                    <i class="bi bi-clock-history me-2"></i> Requested Loans
                    <?php if($pending_count > 0): ?>
                        <span class="badge bg-danger rounded-pill float-end"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'rejected_customers' ? 'active' : ''; ?>" href="?page=rejected_customers">
                    <i class="bi bi-person-x me-2"></i> Rejected Loans
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'overdue' ? 'active' : ''; ?>" href="?page=overdue">
                    <i class="bi bi-exclamation-octagon me-2"></i> Overdue Loans
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between <?php echo $current_page == 'notifications' ? 'active' : ''; ?>" href="?page=notifications">
                    <span><i class="bi bi-bell me-2"></i> Notifications</span>
                    <?php if ($sidebar_due_today_count > 0): ?>
                        <span class="badge rounded-pill bg-danger" style="font-size:.68rem;min-width:20px;padding:3px 7px;">
                            <?php echo $sidebar_due_today_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>

            <!-- ── Reports (Director, MD) ── -->
            <?php if (canSeeReports($user_role)): ?>
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white-50">
                    <span>Reports</span>
                </h6>
                <a class="nav-link <?php echo $current_page == 'reports' ? 'active' : ''; ?>" href="https://app.cbfinance.rw/modules/reports.php" target="_blank">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i> Export Reports
                </a>
            </li>
            <?php endif; ?>
            
            <!-- ── Approvals (Hide for Developer) ── -->
            <?php if (strtolower($user_role) !== 'developer'): 
                $approval_conn = getConnection();
                $pending_approvals_count = $approval_conn ? countPendingApprovals($approval_conn) : 0;
                if ($approval_conn) $approval_conn->close();
                ?>
                <li class="nav-item">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white-50">
                        <span>Approvals</span>
                    </h6>
                    <a class="nav-link d-flex align-items-center justify-content-between <?php echo $current_page == 'approvals' ? 'active' : ''; ?>" href="?page=approvals">
                        <span><i class="bi bi-shield-check me-2"></i> Approval Center</span>
                        <?php if ($pending_approvals_count > 0): ?>
                            <span class="badge rounded-pill bg-warning text-dark" style="font-size:.68rem;min-width:20px;padding:3px 7px;">
                                <?php echo $pending_approvals_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($user_role === 'Developer'): ?>
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white-50">
                    <span>Developer Tools</span>
                </h6>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'activity_logs' ? 'active' : ''; ?>" href="?page=activity_logs">
                    <i class="bi bi-clock-history me-2"></i> Activity Logs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'user_management' ? 'active' : ''; ?>" href="?page=user_management">
                    <i class="bi bi-people me-2"></i> User Management
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item mt-auto pt-4">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</nav>