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

// **CALCULATE TOTAL OUTSTANDING FROM loan_instalments TABLE**
$total_outstanding_query = "SELECT SUM(COALESCE(principal_amount, 0)) as total_outstanding FROM loan_instalments WHERE payment_date IS NULL";
$total_outstanding_result = $conn->query($total_outstanding_query);
$total_outstanding_all = 0;
if ($total_outstanding_result && $row = $total_outstanding_result->fetch_assoc()) {
    $total_outstanding_all = floatval($row['total_outstanding']);
}

// **CALCULATE TOTAL DUE (Principal + Interest) FROM loan_instalments TABLE**
$total_due_query = "SELECT SUM(COALESCE(principal_amount, 0) + COALESCE(interest_amount, 0)) as total_due 
                    FROM loan_instalments 
                    WHERE payment_date IS NULL";
$total_due_result = $conn->query($total_due_query);
$total_due_all = 0;
if ($total_due_result && $row = $total_due_result->fetch_assoc()) {
    $total_due_all = floatval($row['total_due']);
}

// Calculate outstanding and total due for filtered loans only
$filtered_outstanding = 0;
$filtered_total_due = 0;
$temp_loans = [];

if ($loans && $loans->num_rows > 0) {
    $temp_loans = $loans->fetch_all(MYSQLI_ASSOC);
    foreach ($temp_loans as $loan) {
        // Outstanding (principal only)
        $loan_outstanding_query = "SELECT SUM(COALESCE(principal_amount, 0)) as loan_outstanding 
                                   FROM loan_instalments 
                                   WHERE loan_id = ? AND payment_date IS NULL";
        $stmt_outstanding = $conn->prepare($loan_outstanding_query);
        if ($stmt_outstanding) {
            $stmt_outstanding->bind_param("i", $loan['loan_id']);
            $stmt_outstanding->execute();
            $stmt_outstanding->bind_result($loan_outstanding);
            $stmt_outstanding->fetch();
            $filtered_outstanding += floatval($loan_outstanding ?? 0);
            $stmt_outstanding->close();
        }

        // Total due (principal + interest)
        $loan_due_query = "SELECT SUM(COALESCE(principal_amount, 0) + COALESCE(interest_amount, 0)) as loan_due 
                           FROM loan_instalments 
                           WHERE loan_id = ? AND payment_date IS NULL";
        $stmt_due = $conn->prepare($loan_due_query);
        if ($stmt_due) {
            $stmt_due->bind_param("i", $loan['loan_id']);
            $stmt_due->execute();
            $stmt_due->bind_result($loan_due);
            $stmt_due->fetch();
            $filtered_total_due += floatval($loan_due ?? 0);
            $stmt_due->close();
        }
    }
    mysqli_data_seek($loans, 0);
}

// Use filtered values if filter is active, otherwise use totals
$total_outstanding = ($filter_status != 'all') ? $filtered_outstanding : $total_outstanding_all;
$total_due        = ($filter_status != 'all') ? $filtered_total_due   : $total_due_all;

$total_disbursed     = 0;
$filtered_loan_count = 0;

if ($loans && $loans->num_rows > 0) {
    $loans_data = $loans->fetch_all(MYSQLI_ASSOC);
    foreach ($loans_data as $loan) {
        $total_disbursed += $loan['total_disbursed'];
    }
    $filtered_loan_count = $loans->num_rows;
    mysqli_data_seek($loans, 0);
}

// Get total counts without filter for the statistics
$total_query = "SELECT loan_status, COUNT(*) as count FROM loan_portfolio GROUP BY loan_status";
$total_result = $conn->query($total_query);
$total_active    = 0;
$total_closed    = 0;
$total_written_off = 0;
$total_defaulted = 0;
$total_all_loans = 0;

if ($total_result) {
    while ($row = $total_result->fetch_assoc()) {
        $total_all_loans += $row['count'];
        if ($row['loan_status'] == 'Active')       $total_active      = $row['count'];
        elseif ($row['loan_status'] == 'Closed')   $total_closed      = $row['count'];
        elseif ($row['loan_status'] == 'Written-off') $total_written_off = $row['count'];
        elseif ($row['loan_status'] == 'Defaulted') $total_defaulted   = $row['count'];
    }
}
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

<!-- Statistics Cards -->
<div class="row mb-4">

    <!-- Total Loans -->
    <div class="col-md-2 col-sm-6 mb-3">
        <div class="card border-primary h-100">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted">
                    <?php echo $filter_status != 'all' ? 'Filtered Loans (' . htmlspecialchars($filter_status) . ')' : 'Total Loans'; ?>
                </h6>
                <h3 class="card-title" id="total-loans-count"><?php echo $filtered_loan_count; ?></h3>
            </div>
        </div>
    </div>

    <!-- Total Disbursed -->
    <div class="col-md-2 col-sm-6 mb-3">
        <div class="card border-success h-100">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted">
                    <?php echo $filter_status != 'all' ? 'Disbursed (' . htmlspecialchars($filter_status) . ')' : 'Total Disbursed'; ?>
                </h6>
                <h3 class="card-title" id="total-disbursed-amount"><?php echo number_format($total_disbursed, 2); ?></h3>
            </div>
        </div>
    </div>

    <!-- Total Outstanding (Principal Only) -->
    <div class="col-md-2 col-sm-6 mb-3">
        <div class="card border-warning h-100">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted">
                    <?php echo $filter_status != 'all' ? 'Outstanding (' . htmlspecialchars($filter_status) . ')' : 'Total Outstanding'; ?>
                </h6>
                <h3 class="card-title" id="total-outstanding-amount"><?php echo number_format($total_outstanding, 2); ?></h3>
                <span class="total-due-label">Principal only</span>
            </div>
        </div>
    </div>

    <!-- Total Due (Principal + Interest) — NEW CARD -->
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card border-danger h-100">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted">
                    <?php echo $filter_status != 'all' ? 'Total Due (' . htmlspecialchars($filter_status) . ')' : 'Total Due'; ?>
                </h6>
                <h3 class="card-title" id="total-due-amount"><?php echo number_format($total_due, 2); ?></h3>
                <span class="total-due-label">Principal + Interest remaining</span>
            </div>
        </div>
    </div>

    <!-- Filter By Status -->
    <div class="col-md-3 col-sm-12 mb-3">
        <div class="card border-info h-100">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted">Filter By Status</h6>
                <div class="filter-buttons">
                    <a href="?page=loans&status=all<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"
                       class="btn btn-sm <?php echo $filter_status == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        All
                    </a>
                    <a href="?page=loans&status=Active<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"
                       class="btn btn-sm <?php echo $filter_status == 'Active' ? 'btn-success' : 'btn-outline-success'; ?>">
                        Active
                    </a>
                    <a href="?page=loans&status=Closed<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"
                       class="btn btn-sm <?php echo $filter_status == 'Closed' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                        Closed
                    </a>
                    <a href="?page=loans&status=Written-off<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"
                       class="btn btn-sm <?php echo $filter_status == 'Written-off' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                        Written-off
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Active Filter Banner -->
<?php if ($filter_status != 'all'): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="bi bi-funnel-fill me-2"></i>
    <strong>Active Filter:</strong> Showing <?php echo htmlspecialchars($filter_status); ?> loans only
    <a href="?page=loans<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="alert-link ms-2">Clear Filter</a>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Search + Action Buttons -->
<div class="row mb-4">
    <div class="col-md-6">
        <form method="GET" action="" class="d-flex">
            <input type="hidden" name="page" value="loans">
            <?php if ($filter_status != 'all'): ?>
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
            <?php endif; ?>
            <input type="text" class="form-control me-2" name="search"
                   placeholder="Search loans..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search"></i>
            </button>
            <?php if (!empty($search) || $filter_status != 'all'): ?>
            <a href="?page=loans" class="btn btn-outline-secondary ms-2">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="col-md-6">
        <div class="d-flex justify-content-end">
            <div class="me-3">
                <a href="?page=applicationfees">
                    <button class="btn btn-primary">Application Fees</button>
                </a>
            </div>
            <a href="?page=addloan" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add New Loan
            </a>
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
                                            <option value="Closed"      <?php echo $loan['loan_status'] == 'Closed'      ? 'selected' : ''; ?>>Closed</option>
                                            <option value="Written-off" <?php echo $loan['loan_status'] == 'Written-off' ? 'selected' : ''; ?>>Written-off</option>
                                            <option value="Defaulted"   <?php echo $loan['loan_status'] == 'Defaulted'   ? 'selected' : ''; ?>>Defaulted</option>
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

<script>
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
