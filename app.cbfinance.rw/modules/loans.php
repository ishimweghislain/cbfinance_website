<?php
// **HANDLE STATUS UPDATE VIA AJAX FIRST - BEFORE ANY OUTPUT**
if (isset($_POST['update_status']) && isset($_POST['loan_id']) && isset($_POST['new_status'])) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    require_once __DIR__ . '/../config/database.php';
    $conn = getConnection();
    
    header('Content-Type: application/json');
    
    $loan_id = intval($_POST['loan_id']);
    $new_status = trim($_POST['new_status']);
    
    // Validate status
    $valid_statuses = ['Active', 'Closed', 'Written-off', 'Pending', 'Overdue', 'Suspended', 'Defaulted'];
    
    if (!in_array($new_status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status: ' . $new_status]);
        exit;
    }
    
    if ($loan_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid loan ID']);
        exit;
    }
    
    try {
        $update_sql = "UPDATE loan_portfolio SET loan_status = ? WHERE loan_id = ?";
        $stmt = $conn->prepare($update_sql);
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("si", $new_status, $loan_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Status updated successfully to ' . $new_status]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No changes made. Loan may not exist.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Execute error: ' . $stmt->error]);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
    
    $conn->close();
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/approval_helper.php';
$conn = getConnection();

// Initialize messages
$success_message = '';
$error_message = '';

// **DELETE LOAN FUNCTIONALITY**
if (isset($_POST['delete_loan_id']) && !empty($_POST['delete_loan_id'])) {
    $delete_id = intval($_POST['delete_loan_id']);
    
    if ($delete_id > 0) {
        try {
            // Get loan number and customer info for the approval request
            $get_loan_sql = "SELECT lp.loan_number, lp.customer_id, c.customer_name 
                             FROM loan_portfolio lp
                             LEFT JOIN customers c ON lp.customer_id = c.customer_id
                             WHERE lp.loan_id = ?";
            $get_loan_stmt = $conn->prepare($get_loan_sql);
            if ($get_loan_stmt) {
                $get_loan_stmt->bind_param("i", $delete_id);
                $get_loan_stmt->execute();
                $get_loan_stmt->bind_result($loan_number, $customer_id, $customer_name);
                $get_loan_stmt->fetch();
                $get_loan_stmt->close();
            }

            if (!empty($loan_number)) {
                $approval_data = [
                    'loan_id' => $delete_id,
                    'loan_number' => $loan_number,
                    'customer_id' => $customer_id,
                    'customer_name' => $customer_name,
                    'action_note' => 'Permanent deletion of loan and all related instalments/payments'
                ];

                if (submitForApproval($conn, 'delete', 'loan', $delete_id, $approval_data, "Delete loan #$loan_number ($customer_name)")) {
                    $success_message = "⏳ Deletion request for loan <strong>#$loan_number</strong> submitted for approval by Director or MD.";
                } else {
                    $error_message = "Could not submit loan deletion for approval: " . $conn->error;
                }
            } else {
                $error_message = "Loan not found.";
            }
        } catch (Exception $e) {
            $error_message = "Error submitting loan deletion: " . $e->getMessage();
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Loan added successfully!";
}

if (isset($_GET['update_success']) && $_GET['update_success'] == 1) {
    $success_message = "Loan updated successfully!";
}

if (isset($_GET['delete_success']) && $_GET['delete_success'] == 1) {
    $success_message = "Loan deleted successfully!";
}

if (isset($_GET['error']) && $_GET['error'] == 'delete_failed') {
    $error_message = "Failed to delete loan. Please try again.";
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

$query = "SELECT lp.*, c.customer_name, c.customer_code 
          FROM loan_portfolio lp 
          LEFT JOIN customers c ON lp.customer_id = c.customer_id 
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (lp.loan_number LIKE ? OR c.customer_name LIKE ? OR c.customer_code LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

if (!empty($filter_status) && $filter_status != 'all') {
    $query .= " AND lp.loan_status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$query .= " ORDER BY lp.record_date DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $loans = $stmt->get_result();
    } else {
        $error_message = "Failed to prepare search query: " . $conn->error;
        $loans = false;
    }
} else {
    $loans = $conn->query($query);
}

if (!$loans) {
    $error_message = "Failed to fetch loans: " . $conn->error;
}

// ================================================
// UNIFIED PORTFOLIO METRICS — per status, using loan_portfolio columns
// (principal_outstanding, interest_outstanding, total_outstanding are
//  maintained by the system and match what financial_report.php uses)
// ================================================

// --- 1. Count loans per status ---
$status_counts = [];
$all_statuses_list = ['Active','Closed','Written-off','Defaulted','Pending','Overdue','Suspended'];
foreach ($all_statuses_list as $s) $status_counts[$s] = 0;

$count_res = $conn->query("SELECT loan_status, COUNT(*) as cnt FROM loan_portfolio GROUP BY loan_status");
if ($count_res) {
    while ($cr = $count_res->fetch_assoc()) {
        $status_counts[$cr['loan_status']] = (int)$cr['cnt'];
    }
}
$total_all_loans   = array_sum($status_counts);
$total_active      = $status_counts['Active'];
$total_closed      = $status_counts['Closed'];
$total_written_off = $status_counts['Written-off'];
$total_defaulted   = $status_counts['Defaulted'];

// --- 2. Pre-compute metrics per status for instant JS toggling ---
// Uses loan_portfolio columns directly — same source as financial_report.php
$metrics_by_status = [];
$metrics_sql = "SELECT 
    loan_status,
    COALESCE(SUM(total_disbursed), 0)           AS total_distributed,
    COALESCE(SUM(GREATEST(0, principal_outstanding)), 0) AS active_principal,
    COALESCE(SUM(GREATEST(0, interest_outstanding)), 0)  AS active_interest,
    COALESCE(SUM(GREATEST(0, total_outstanding)), 0)     AS portfolio_value,
    COALESCE(SUM(penalties), 0)                 AS total_overdue
    FROM loan_portfolio
    GROUP BY loan_status";
$mres = $conn->query($metrics_sql);
if ($mres) {
    while ($mr = $mres->fetch_assoc()) {
        $metrics_by_status[$mr['loan_status']] = $mr;
    }
}
// ALL (aggregate)
$all_sql = "SELECT
    COALESCE(SUM(total_disbursed), 0)           AS total_distributed,
    COALESCE(SUM(GREATEST(0, principal_outstanding)), 0) AS active_principal,
    COALESCE(SUM(GREATEST(0, interest_outstanding)), 0)  AS active_interest,
    COALESCE(SUM(GREATEST(0, total_outstanding)), 0)     AS portfolio_value,
    COALESCE(SUM(penalties), 0)                 AS total_overdue
    FROM loan_portfolio";
$all_res = $conn->query($all_sql);
$metrics_all = $all_res ? $all_res->fetch_assoc() : ['total_distributed'=>0,'active_principal'=>0,'active_interest'=>0,'portfolio_value'=>0,'total_overdue'=>0];
$metrics_by_status['all'] = $metrics_all;

// --- 3. Choose metrics for current view ---
$ps = $metrics_by_status[$filter_status] ?? $metrics_all;

$filtered_loan_count = ($filter_status == 'all') ? $total_all_loans : ($status_counts[$filter_status] ?? 0);
?>

<style>
    * { box-sizing: border-box; }

    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        font-size: 16px;
        line-height: 1.5;
    }

    .container-fluid {
        padding-right: 15px;
        padding-left: 15px;
        margin-right: auto;
        margin-left: auto;
    }

    .card {
        margin-bottom: 1.5rem;
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .card-body { padding: 1.25rem; }

    .alert {
        margin-bottom: 1rem;
        border-radius: 0.375rem;
    }

    .form-select,
    .form-control {
        font-size: 1rem;
        padding: 0.5rem 0.75rem;
    }

    .btn {
        white-space: nowrap;
        padding: 0.5rem 1rem;
    }

    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .badge {
        padding: 0.35em 0.65em;
        font-size: 0.85em;
        font-weight: 500;
    }

    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-bottom: 1rem;
    }

    .table {
        width: 100%;
        margin-bottom: 1rem;
        min-width: 800px;
    }

    .table th,
    .table td {
        padding: 0.75rem;
        vertical-align: middle;
        white-space: nowrap;
    }

    .table thead th {
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
    }

    /* Row color coding */
    .loan-row-active     { background-color: #ffffff; }
    .loan-row-closed     { background-color: #fff3cd; }
    .loan-row-written-off { background-color: #f8d7da; }

    .loan-row-active:hover,
    .loan-row-closed:hover,
    .loan-row-written-off:hover { opacity: 0.9; }

    /* Status Dropdown */
    .status-select {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        border-radius: 0.25rem;
        cursor: pointer;
    }

    .status-select:focus {
        outline: 2px solid #0d6efd;
        outline-offset: 2px;
    }

    /* Total Due card highlight */
    .card.border-danger .card-title {
        color: #dc3545;
    }

    .total-due-label {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 0.2rem;
        display: block;
    }

    /* Filter Buttons */
    .filter-buttons .btn { margin: 0.25rem; }

    /* Scrollbar */
    .table-responsive::-webkit-scrollbar { height: 8px; }
    .table-responsive::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .table-responsive::-webkit-scrollbar-thumb { background: #888; border-radius: 4px; }
    .table-responsive::-webkit-scrollbar-thumb:hover { background: #555; }

    /* Accessibility */
    .btn:focus,
    .form-control:focus,
    .form-select:focus {
        outline: 2px solid #0d6efd;
        outline-offset: 2px;
    }

    /* Mobile (up to 576px) */
    @media (max-width: 576px) {
        body { font-size: 14px; }
        h2.h4 { font-size: 1.25rem; }
        h5 { font-size: 1.1rem; }
        h3.card-title { font-size: 1.3rem; }
        .card-body { padding: 0.85rem; }
        .row.g-3 > div { margin-bottom: 0.75rem; }
        .btn { width: 100%; margin-bottom: 0.5rem; }
        .d-flex.justify-content-end { flex-direction: column; }
        .d-flex.justify-content-end > * { width: 100%; margin-bottom: 0.5rem; }
        .d-flex.me-2 { flex-direction: column; }
        .d-flex .form-control { margin-bottom: 0.5rem; margin-right: 0 !important; }
        .d-flex .btn { margin-left: 0 !important; }
        .badge { font-size: 0.75rem; padding: 0.3em 0.5em; }
        .table { font-size: 0.85rem; min-width: 600px; }
        .table th, .table td { padding: 0.5rem 0.25rem; }
        .table .btn-sm { padding: 0.2rem 0.4rem; font-size: 0.75rem; margin: 0.1rem; }
        .card-title { font-size: 1.1rem; }
        .card-subtitle { font-size: 0.85rem; }
        .card-text { font-size: 0.875rem; }
        .modal-dialog { margin: 0.5rem; }
        .modal-body { padding: 1rem; }
        .modal-body ul { padding-left: 1.25rem; }
    }

    /* Tablets (577px to 768px) */
    @media (min-width: 577px) and (max-width: 768px) {
        .table { font-size: 0.9rem; min-width: 700px; }
        .table th, .table td { padding: 0.6rem 0.4rem; }
        .btn-sm { padding: 0.3rem 0.5rem; font-size: 0.8rem; }
        .col-md-3, .col-md-2 { flex: 0 0 50%; max-width: 50%; }
    }

    /* Medium (769px to 992px) */
    @media (min-width: 769px) and (max-width: 992px) {
        .table { font-size: 0.95rem; }
        .col-md-3 { flex: 0 0 50%; max-width: 50%; }
    }

    /* Large (993px+) */
    @media (min-width: 993px) {
        .table { min-width: 100%; }
    }
</style>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteModalLabel">Confirm Loan Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone!
                    </div>
                    <p>Are you sure you want to delete <strong><span id="loanNumberDisplay"></span></strong>?</p>
                    <p class="text-danger">This will permanently delete:</p>
                    <ul class="text-danger">
                        <li>The loan record from portfolio</li>
                        <li>All loan instalments</li>
                        <li>All loan payments</li>
                    </ul>
                    <input type="hidden" name="delete_loan_id" id="delete_loan_id" value="">
                    <input type="hidden" name="page" value="loans">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Loan Permanently</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <h2 class="h4 fw-bold text-primary">Loan Portfolio</h2>
        <p class="text-muted">Manage your loan portfolio</p>
    </div>
</div>

<!-- Alerts -->
<?php if (!empty($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i><?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ============================================================
     PORTFOLIO SUMMARY CARDS — update instantly on status filter
     data-metrics on each filter btn drives the JS update
     ============================================================ -->
<?php
// The current metrics for the selected filter
$current_label = ($filter_status == 'all') ? 'All Loans' : $filter_status;
?>
<div class="mb-2 d-flex align-items-center gap-2">
    <span class="badge bg-primary fs-6" id="current-filter-label"><?php echo htmlspecialchars($current_label); ?></span>
    <small class="text-muted">— Summary cards reflect the selected status filter below</small>
</div>
<div class="row mb-2 g-2" id="metrics-cards">
    <!-- Card 1: Total Distributed -->
    <div class="col">
        <div class="card border-start border-4 border-primary h-100 shadow-sm" style="border-radius:0.5rem;">
            <div class="card-body py-2 px-3">
                <div class="text-muted text-uppercase" style="font-size:0.65rem;font-weight:700;letter-spacing:0.05em;">Total Distributed</div>
                <div class="fw-bold mt-1" id="card-distributed" style="font-size:1.1rem;"><?php echo number_format($ps['total_distributed'], 2); ?></div>
                <div class="text-muted" style="font-size:0.7rem;">Disbursement total</div>
            </div>
        </div>
    </div>
    <!-- Card 2: Active Principal -->
    <div class="col">
        <div class="card border-start border-4 border-success h-100 shadow-sm" style="border-radius:0.5rem;">
            <div class="card-body py-2 px-3">
                <div class="text-muted text-uppercase" style="font-size:0.65rem;font-weight:700;letter-spacing:0.05em;">Principal Outstanding</div>
                <div class="fw-bold mt-1" id="card-principal" style="font-size:1.1rem;"><?php echo number_format($ps['active_principal'], 2); ?></div>
                <div class="text-muted" style="font-size:0.7rem;">Remaining capital</div>
            </div>
        </div>
    </div>
    <!-- Card 3: Active Interest -->
    <div class="col">
        <div class="card border-start border-4 border-info h-100 shadow-sm" style="border-radius:0.5rem;">
            <div class="card-body py-2 px-3">
                <div class="text-muted text-uppercase" style="font-size:0.65rem;font-weight:700;letter-spacing:0.05em;">Interest Outstanding</div>
                <div class="fw-bold mt-1" id="card-interest" style="font-size:1.1rem;"><?php echo number_format($ps['active_interest'], 2); ?></div>
                <div class="text-muted" style="font-size:0.7rem;">Expected unearned</div>
            </div>
        </div>
    </div>
    <!-- Card 4: Portfolio Value (P+I) -->
    <div class="col">
        <div class="card border-start border-4 border-warning h-100 shadow-sm" style="border-radius:0.5rem;">
            <div class="card-body py-2 px-3">
                <div class="text-muted text-uppercase" style="font-size:0.65rem;font-weight:700;letter-spacing:0.05em;">Portfolio Value (P+I)</div>
                <div class="fw-bold mt-1" id="card-portfolio" style="font-size:1.1rem;"><?php echo number_format($ps['portfolio_value'], 2); ?></div>
                <div class="text-muted" style="font-size:0.7rem;">Total outstanding</div>
            </div>
        </div>
    </div>
    <!-- Card 5: Accrued Penalties -->
    <div class="col">
        <div class="card border-start border-4 border-danger h-100 shadow-sm" style="border-radius:0.5rem;">
            <div class="card-body py-2 px-3">
                <div class="text-muted text-uppercase" style="font-size:0.65rem;font-weight:700;letter-spacing:0.05em;">Accrued Penalties</div>
                <div class="fw-bold mt-1 text-danger" id="card-overdue" style="font-size:1.1rem;"><?php echo number_format($ps['total_overdue'], 2); ?></div>
                <div class="text-muted" style="font-size:0.7rem;">Late payment charges</div>
            </div>
        </div>
    </div>
</div>

<!-- Status Filters + Search -->
<div class="row mb-3 align-items-start">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-2">
                <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                    <strong class="me-1 small text-muted text-uppercase">Filter by Status:</strong>
                    <?php
                    $btn_defs = [
                        'all'         => ['label' => 'All',         'color' => 'primary',   'count' => $total_all_loans],
                        'Active'      => ['label' => 'Active',      'color' => 'success',   'count' => $status_counts['Active']],
                        'Pending'     => ['label' => 'Pending',     'color' => 'warning',   'count' => $status_counts['Pending']],
                        'Overdue'     => ['label' => 'Overdue',     'color' => 'orange',    'count' => $status_counts['Overdue']],
                        'Defaulted'   => ['label' => 'Defaulted',   'color' => 'dark',      'count' => $status_counts['Defaulted']],
                        'Suspended'   => ['label' => 'Suspended',   'color' => 'secondary', 'count' => $status_counts['Suspended']],
                        'Written-off' => ['label' => 'Written-off', 'color' => 'danger',    'count' => $status_counts['Written-off']],
                        'Closed'      => ['label' => 'Closed',      'color' => 'info',      'count' => $status_counts['Closed']],
                    ];
                    foreach ($btn_defs as $status_key => $btn):
                        $is_active = ($filter_status == $status_key);
                        $color = $btn['color'];
                        $btn_class = $is_active ? "btn-$color" : "btn-outline-$color";
                        $href = "?page=loans&status=" . urlencode($status_key) . (!empty($search) ? '&search='.urlencode($search) : '');
                    ?>
                    <a href="<?php echo $href; ?>"
                       class="btn btn-sm <?php echo $btn_class; ?> status-filter-btn"
                       data-status="<?php echo htmlspecialchars($status_key); ?>"
                       data-metrics='<?php echo htmlspecialchars(json_encode($metrics_by_status[$status_key] ?? $metrics_all)); ?>'>
                        <?php echo $btn['label']; ?>
                        <span class="badge bg-white text-dark ms-1" style="font-size:0.7rem;"><?php echo $btn['count']; ?></span>
                    </a>
                    <?php endforeach; ?>

                    <div class="ms-auto d-flex align-items-center gap-1">
                        <form method="GET" action="" class="d-flex m-0">
                            <input type="hidden" name="page" value="loans">
                            <?php if ($filter_status != 'all'): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                            <?php endif; ?>
                            <input type="text" class="form-control form-control-sm me-1" name="search"
                                   placeholder="Search loans..." value="<?php echo htmlspecialchars($search); ?>" style="width:190px;">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-search"></i>
                            </button>
                        </form>
                        <a href="?page=applicationfees" class="btn btn-sm btn-outline-primary">App Fees</a>
                        <a href="?page=createloan" class="btn btn-sm btn-success">
                            <i class="bi bi-plus-lg"></i> New
                        </a>
                    </div>
                </div>

                <?php if ($filter_status != 'all'): ?>
                <div class="d-flex align-items-center gap-2" style="font-size:0.8rem;">
                    <i class="bi bi-funnel-fill text-primary"></i>
                    <strong>Filtered:</strong> Showing <span class="badge bg-secondary"><?php echo htmlspecialchars($filter_status); ?></span> loans only.
                    <a href="?page=loans<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="ms-1 text-danger text-decoration-none"><i class="bi bi-x-circle"></i> Clear</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>



<!-- Loan Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Loan Portfolio</h5>
                <span class="badge bg-primary"><?php echo $loans ? $loans->num_rows : 0; ?> Loans</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Loan #</th>
                                <th>Customer</th>
                                <th>Disbursed</th>
                                <th>Collateral Value</th>
                                <th>Interest Rate</th>
                                <th>Days Overdue</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($loans && $loans->num_rows > 0): ?>
                                <?php while ($loan = $loans->fetch_assoc()):
                                    $days_overdue = 0;
                                    $max_overdue_query = "SELECT MAX(days_overdue) as max_days_overdue 
                                                         FROM loan_instalments 
                                                         WHERE loan_id = ? AND payment_date IS NULL";
                                    $stmt = $conn->prepare($max_overdue_query);
                                    if ($stmt) {
                                        $stmt->bind_param("i", $loan['loan_id']);
                                        $stmt->execute();
                                        $stmt->bind_result($max_days);
                                        $stmt->fetch();
                                        $days_overdue = $max_days ? $max_days : 0;
                                        $stmt->close();
                                    }

                                    $collateral_net_value = floatval($loan['collateral_net_value'] ?? 0);

                                    $row_class = 'loan-row-active';
                                    if ($loan['loan_status'] == 'Closed')       $row_class = 'loan-row-closed';
                                    elseif ($loan['loan_status'] == 'Written-off') $row_class = 'loan-row-written-off';

                                    $status_colors = [
                                        'Active'     => 'success',
                                        'Pending'    => 'warning',
                                        'Overdue'    => 'danger',
                                        'Closed'     => 'warning',
                                        'Defaulted'  => 'dark',
                                        'Suspended'  => 'warning',
                                        'Written-off'=> 'danger'
                                    ];
                                    $status_color = $status_colors[$loan['loan_status']] ?? 'secondary';
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><strong><?php echo htmlspecialchars($loan['loan_number']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($loan['customer_name']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($loan['customer_code']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo number_format($loan['total_disbursed'], 2); ?><br>
                                        <small class="text-muted"><?php echo date('d/m/Y', strtotime($loan['disbursement_date'])); ?></small>
                                    </td>
                                    <td>
                                        <?php echo number_format($collateral_net_value, 2); ?><br>
                                        <?php if (!empty($loan['collateral_description'])): ?>
                                        <small class="text-muted" title="<?php echo htmlspecialchars($loan['collateral_description']); ?>">
                                            <i class="bi bi-info-circle"></i>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($loan['interest_rate'], 2); ?>%</td>
                                    <td>
                                        <?php if ($days_overdue > 0): ?>
                                        <span class="badge bg-danger"><?php echo $days_overdue; ?> days</span>
                                        <?php else: ?>
                                        <span class="badge bg-success">Current</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <select class="status-select form-select form-select-sm"
                                                data-loan-id="<?php echo $loan['loan_id']; ?>"
                                                onchange="updateLoanStatus(this)">
                                            <option value="Active"      <?php echo $loan['loan_status'] == 'Active'      ? 'selected' : ''; ?>>Active</option>
                                            <option value="Pending"     <?php echo $loan['loan_status'] == 'Pending'     ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Overdue"     <?php echo $loan['loan_status'] == 'Overdue'     ? 'selected' : ''; ?>>Overdue</option>
                                            <option value="Defaulted"   <?php echo $loan['loan_status'] == 'Defaulted'   ? 'selected' : ''; ?>>Defaulted</option>
                                            <option value="Suspended"   <?php echo $loan['loan_status'] == 'Suspended'   ? 'selected' : ''; ?>>Suspended</option>
                                            <option value="Written-off" <?php echo $loan['loan_status'] == 'Written-off' ? 'selected' : ''; ?>>Written-off</option>
                                            <option value="Closed"      <?php echo $loan['loan_status'] == 'Closed'      ? 'selected' : ''; ?>>Closed</option>
                                        </select>
                                    </td>
                                    <td>
                                        <a href="?page=viewloandetails&id=<?php echo intval($loan['loan_id']); ?>"
                                           class="btn btn-sm btn-outline-primary" title="View Loan Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="?page=editloan&id=<?php echo $loan['loan_id']; ?>"
                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button onclick="confirmDelete(<?php echo $loan['loan_id']; ?>, '<?php echo htmlspecialchars($loan['loan_number']); ?>')"
                                                class="btn btn-sm btn-outline-danger" title="Delete Loan">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">No loans found. <a href="?page=addloan">Add your first loan!</a></div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Overdue btn */
.btn-orange { color: #fff; background-color: #fd7e14; border-color: #fd7e14; }
.btn-outline-orange { color: #fd7e14; border-color: #fd7e14; }
.btn-outline-orange:hover { background-color: #fd7e14; color: #fff; }
</style>

<script>
// =====================================================
// STATUS FILTER CARDS UPDATE — instant client-side toggle
// =====================================================
const ALL_METRICS = <?php echo json_encode($metrics_by_status); ?>;

function formatNum(val) {
    const n = parseFloat(val) || 0;
    return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updateCards(status) {
    const m = ALL_METRICS[status] || ALL_METRICS['all'];
    const label = status === 'all' ? 'All Loans' : status;
    
    document.getElementById('card-distributed').textContent = formatNum(m.total_distributed);
    document.getElementById('card-principal').textContent   = formatNum(m.active_principal);
    document.getElementById('card-interest').textContent    = formatNum(m.active_interest);
    document.getElementById('card-portfolio').textContent   = formatNum(m.portfolio_value);
    document.getElementById('card-overdue').textContent     = formatNum(m.total_overdue);
    
    const lbl = document.getElementById('current-filter-label');
    if (lbl) lbl.textContent = label;

    // Animate the cards briefly
    document.querySelectorAll('#metrics-cards .card').forEach(c => {
        c.style.transition = 'opacity 0.15s';
        c.style.opacity = '0.4';
        setTimeout(() => { c.style.opacity = '1'; }, 200);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Whenever user clicks a status filter button, update cards immediately
    // without waiting for page reload (optimistic UI)
    document.querySelectorAll('.status-filter-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const status = this.dataset.status;
            updateCards(status);
            // Highlight active button
            document.querySelectorAll('.status-filter-btn').forEach(b => {
                b.classList.remove('active');
            });
            this.classList.add('active');
            // Allow the link to navigate normally after updating UI
        });
    });
});

function confirmDelete(loanId, loanNumber) {
    document.getElementById('delete_loan_id').value = loanId;
    document.getElementById('loanNumberDisplay').textContent = loanNumber;
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}

function updateLoanStatus(selectElement) {
    const loanId       = selectElement.getAttribute('data-loan-id');
    const newStatus    = selectElement.value;
    const originalValue = selectElement.getAttribute('data-original-value') || selectElement.value;

    if (!confirm(`Are you sure you want to change the loan status to "${newStatus}"?`)) {
        selectElement.value = originalValue;
        return;
    }

    // Show loading state
    selectElement.disabled = true;
    selectElement.style.opacity = '0.6';

    const formData = new FormData();
    formData.append('update_status', '1');
    formData.append('loan_id', loanId);
    formData.append('new_status', newStatus);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        return response.text().then(text => {
            if (contentType && contentType.includes('application/json')) {
                try { return JSON.parse(text); }
                catch (e) { throw new Error('Invalid JSON response: ' + text.substring(0, 100)); }
            } else {
                try { return JSON.parse(text); }
                catch (e) { throw new Error('Server did not return JSON. Got: ' + text.substring(0, 100)); }
            }
        });
    })
    .then(data => {
        if (data.success) {
            selectElement.setAttribute('data-original-value', newStatus);

            const row = selectElement.closest('tr');
            updateFinancialStats(row, originalValue, newStatus);
            updateRowColor(selectElement, newStatus);
            updateStatistics(originalValue, newStatus);

            const urlParams    = new URLSearchParams(window.location.search);
            const currentFilter = urlParams.get('status') || 'all';

            if (currentFilter !== 'all' && newStatus !== currentFilter) {
                row.style.transition = 'opacity 0.5s';
                row.style.opacity    = '0.3';
                setTimeout(() => {
                    row.style.display = 'none';
                    showAlert('success', data.message + ' (Row hidden - no longer matches filter)');
                }, 500);
            } else {
                showAlert('success', data.message);
            }
        } else {
            selectElement.value = originalValue;
            showAlert('danger', data.message || 'Failed to update status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        selectElement.value = originalValue;
        showAlert('danger', 'An error occurred: ' + error.message);
    })
    .finally(() => {
        selectElement.disabled     = false;
        selectElement.style.opacity = '1';
    });
}

function updateRowColor(selectElement, newStatus) {
    const row = selectElement.closest('tr');
    row.classList.remove('loan-row-active', 'loan-row-closed', 'loan-row-written-off');
    if (newStatus === 'Closed')       row.classList.add('loan-row-closed');
    else if (newStatus === 'Written-off') row.classList.add('loan-row-written-off');
    else                              row.classList.add('loan-row-active');
}

function updateStatistics(oldStatus, newStatus) {
    const activeSpan     = document.getElementById('active-count');
    const closedSpan     = document.getElementById('closed-count');
    const writtenOffSpan = document.getElementById('written-off-count');

    if (activeSpan && closedSpan && writtenOffSpan) {
        let activeCount     = parseInt(activeSpan.textContent)     || 0;
        let closedCount     = parseInt(closedSpan.textContent)     || 0;
        let writtenOffCount = parseInt(writtenOffSpan.textContent) || 0;

        if (oldStatus === 'Active')      activeCount--;
        else if (oldStatus === 'Closed') closedCount--;
        else if (oldStatus === 'Written-off') writtenOffCount--;

        if (newStatus === 'Active')      activeCount++;
        else if (newStatus === 'Closed') closedCount++;
        else if (newStatus === 'Written-off') writtenOffCount++;

        activeSpan.textContent     = activeCount;
        closedSpan.textContent     = closedCount;
        writtenOffSpan.textContent = writtenOffCount;
    }

    const totalLoansCount = document.getElementById('total-loans-count');
    if (totalLoansCount) {
        const urlParams     = new URLSearchParams(window.location.search);
        const currentFilter = urlParams.get('status') || 'all';
        if (currentFilter !== 'all') {
            let currentCount = parseInt(totalLoansCount.textContent) || 0;
            if (oldStatus === currentFilter) currentCount--;
            if (newStatus === currentFilter) currentCount++;
            totalLoansCount.textContent = currentCount;
        }
    }
}

function updateFinancialStats(row, oldStatus, newStatus) {
    const disbursedCell = row.cells[2];
    if (!disbursedCell) return;

    const disbursedText   = disbursedCell.textContent.trim().split('\n')[0];
    const disbursedAmount = parseFloat(disbursedText.replace(/,/g, '')) || 0;

    const urlParams     = new URLSearchParams(window.location.search);
    const currentFilter = urlParams.get('status') || 'all';

    if (currentFilter !== 'all') {
        const disbursedElement = document.getElementById('total-disbursed-amount');
        if (disbursedElement) {
            let currentDisbursed = parseFloat(disbursedElement.textContent.replace(/,/g, '')) || 0;
            if (oldStatus === currentFilter) currentDisbursed -= disbursedAmount;
            if (newStatus === currentFilter) currentDisbursed += disbursedAmount;
            disbursedElement.textContent = currentDisbursed.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
    }
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'} me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    const container = document.querySelector('.row.mb-4');
    container.parentNode.insertBefore(alertDiv, container);
    setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(alertDiv);
        bsAlert.close();
    }, 5000);
}

document.addEventListener('DOMContentLoaded', function () {
    // Store original values for all status selects
    document.querySelectorAll('.status-select').forEach(select => {
        select.setAttribute('data-original-value', select.value);
    });

    // Auto-dismiss existing alerts after 5 seconds
    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>

<?php if (isset($conn)) $conn->close(); ?>
