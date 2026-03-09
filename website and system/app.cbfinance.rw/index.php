<?php
include 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <style>
             /* Add these styles to the existing style section */
body {
    font-size: 12px !important;
    overflow-x: hidden;
}

/* Sidebar Fixes */
#sidebar {
    position: fixed !important;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 100 !important;
    padding: 0 !important;
    height: 100vh !important;
    overflow-y: auto !important;
}

/* Custom scrollbar for sidebar */
#sidebar::-webkit-scrollbar {
    width: 4px;
}
#sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
}

/* Main content wrapper to prevent overlap */
.main-wrapper {
    margin-left: 16.666667%; /* col-lg-2 */
}

@media (max-width: 991.98px) {
    .main-wrapper {
        margin-left: 25%; /* col-md-3 */
    }
}

@media (max-width: 767.98px) {
    #sidebar {
        position: static !important;
        height: auto !important;
        width: 100% !important;
    }
    .main-wrapper {
        margin-left: 0 !important;
    }
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
        .shake {
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% {transform: translateX(0);}
            10%, 30%, 50%, 70%, 90% {transform: translateX(-5px);}
            20%, 40%, 60%, 80% {transform: translateX(5px);}
        }
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting & Loan Management System</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/CAPITAL BRIDGE LOGO.png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Authentication Check Script -->
    <script>
        // Authentication check
        (function() {
            const authSession = localStorage.getItem('authSession');
            const expiry = localStorage.getItem('authExpiry');
            
            // Check if session exists and is valid
            if (!authSession) {
                // No session, redirect to login
                window.location.href = 'login.php';
                return;
            }
            
            const session = JSON.parse(authSession);
            
            // Check expiry if not session-based
            if (expiry !== 'session' && expiry) {
                if (new Date().getTime() > parseInt(expiry)) {
                    // Session expired
                    localStorage.removeItem('authSession');
                    localStorage.removeItem('authExpiry');
                    window.location.href = 'login.php';
                    return;
                }
                
                // Auto-extend session if within 1 hour of expiry (for remember me)
                if (parseInt(expiry) - new Date().getTime() < 3600000) {
                    localStorage.setItem('authExpiry', (new Date().getTime() + (7 * 24 * 60 * 60 * 1000)).toString());
                }
            }
            
            if (!session.loggedIn) {
                // Not logged in, redirect to login
                window.location.href = 'login.php';
                return;
            }
            
            // User is authenticated, continue loading page
            // Store user info in global variable for use in other scripts
            window.currentUser = session;
        })();
    </script>
    
    <div class="container-fluid">
        <!-- Add logout button to header -->
        <script>
            // Function to modify header to include logout button
            function addLogoutButton() {
                const header = document.querySelector('.navbar-nav.ms-auto');
                if (header && window.currentUser) {
                    const logoutHtml = `
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> ${window.currentUser.username}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="#"><i class="bi bi-person"></i> Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="logout()"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    `;
                    header.innerHTML += logoutHtml;
                }
            }
            
            // Logout function
            function logout() {
                localStorage.removeItem('authSession');
                localStorage.removeItem('authExpiry');
                window.location.href = 'login.php';
            }
            
            // Session timeout warning (optional)
            let idleTimer;
            function resetIdleTimer() {
                clearTimeout(idleTimer);
                idleTimer = setTimeout(() => {
                    // Show warning after 30 minutes of inactivity
                    if (confirm('Your session will expire due to inactivity. Continue?')) {
                        resetIdleTimer();
                    } else {
                        logout();
                    }
                }, 30 * 60 * 1000); // 30 minutes
            }
            
            // Set up idle timer
            document.addEventListener('DOMContentLoaded', function() {
                if (localStorage.getItem('authExpiry') === 'session') {
                    resetIdleTimer();
                    
                    // Reset timer on user activity
                    ['mousemove', 'keypress', 'click', 'scroll'].forEach(event => {
                        document.addEventListener(event, resetIdleTimer);
                    });
                }
                
                addLogoutButton();
            });
        </script>
        
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-wrapper">
                <?php 
                // Modify header.php to include user info
                include 'includes/header.php'; 
                ?>
                
                <!-- Page Content -->
                <div class="container-fluid py-4">
                    <?php
                    // Route to appropriate module
                    $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
                    $module_file = "modules/{$page}.php";
                    
                    if (file_exists($module_file)) {
                        include $module_file;
                    } else {
                        echo '<div class="alert alert-danger">Page not found!</div>';
                    }
                    ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JS -->
    <!-- <script src="assets/js/script.js"></script> -->
</body>
</html>