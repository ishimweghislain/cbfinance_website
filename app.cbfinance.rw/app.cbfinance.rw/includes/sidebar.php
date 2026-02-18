<?php
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
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