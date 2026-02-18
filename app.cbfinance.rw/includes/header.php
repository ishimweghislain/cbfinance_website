<?php
// Get page title based on current page
$page_titles = [
    'dashboard' => 'Dashboard',
    'chart_of_accounts' => 'Chart of Accounts',
    'customers' => 'Customers',
    'loans' => 'Loan Portfolio',
    'reports' => 'Reports',
    'settings' => 'Settings'
];

$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$page_title = $page_titles[$current_page] ?? 'Dashboard';
?>
<header class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
    <div class="container-fluid">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="?page=dashboard" class="text-primary">Home</a></li>
                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
            </ol>
        </nav>
        
        <div class="d-flex align-items-center">
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i> <?php echo $_SESSION['user_name']; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#"><i class="bi bi-person"></i> Profile</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-gear"></i> Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="?page=logout"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</header>