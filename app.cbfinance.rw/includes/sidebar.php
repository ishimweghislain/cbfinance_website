<?php
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

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
?>
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-primary sidebar">
    <div class="position-sticky pt-3">
        <div class="sidebar-header text-center py-4">
            <h4 class="text-white">
                <i class="bi bi-calculator"></i>
                <span class="ms-2">Accounting System</span>
            </h4>
        </div>
        
        <ul class="nav flex-column">
            
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white-50">
                    <span>Loan Management</span>
                </h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'customers' ? 'active' : ''; ?>" href="?page=customers">
                    <i class="bi bi-people"></i>
                     Customers
                </a>
            </li>
          
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'loans' ? 'active' : ''; ?>" href="?page=loans">
                    <i class="bi bi-bank"></i>
                    Loans 
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
                    <i class="bi bi-hourglass-split"></i>
                    Requested Loans
                    <?php if($pending_count > 0): ?>
                        <span class="badge bg-danger rounded-pill float-end"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'rejected_customers' ? 'active' : ''; ?>" href="?page=rejected_customers">
                    <i class="bi bi-person-x"></i>
                    Rejected Loans
                </a>
            </li>

            <!-- Notifications with due-today badge -->
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between
                          <?php echo $current_page == 'notifications' ? 'active' : ''; ?>"
                   href="?page=notifications">
                    <span>
                        <i class="bi bi-bell me-1"></i>
                        Notifications
                    </span>
                    <?php if ($sidebar_due_today_count > 0): ?>
                        <span class="badge rounded-pill bg-danger"
                              style="font-size:.68rem;min-width:20px;padding:3px 7px;"
                              title="<?php echo $sidebar_due_today_count; ?> instalment(s) due today">
                            <?php echo $sidebar_due_today_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white-50">
                    <span>Reports</span>
                </h6>
                <a class="nav-link <?php echo $current_page == 'reports' ? 'active' : ''; ?>" href="?page=reports">
                    <i class="bi bi-bank"></i>
                    Reports</a>
            </li>
            
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white-50">
                    <span>System</span>
                </h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link bg-danger <?php echo $current_page == 'logout' ? 'active' : ''; ?>" href="?page=logout">
                    <i class="bi bi-bar-chart"></i>
                    Logout
                </a>
            </li>
            
        </ul>
    </div>
</nav>