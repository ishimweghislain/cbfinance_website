<?php
// edit_account.php
require_once __DIR__ . '/../config/database.php';
$conn = getConnection();

// Check if account ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ?page=chart_of_accounts');
    exit();
}

$account_id = $_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_account'])) {
        $account_code = $_POST['account_code'];
        $account_name = $_POST['account_name'];
        $class_id = $_POST['class_id'];
        $parent_account_id = $_POST['parent_account_id'] ?: NULL;
        $description = $_POST['description'];
        $is_control_account = isset($_POST['is_control_account']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE chart_of_accounts SET account_code = ?, account_name = ?, class_id = ?, parent_account_id = ?, description = ?, is_control_account = ?, is_active = ? WHERE account_id = ?");
        $stmt->bind_param("ssiissii", $account_code, $account_name, $class_id, $parent_account_id, $description, $is_control_account, $is_active, $account_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $success_message = "Account updated successfully!";
        } else {
            $error_message = "No changes made or failed to update account.";
        }
        $stmt->close();
    }
}

// Fetch account data
$stmt = $conn->prepare("SELECT * FROM chart_of_accounts WHERE account_id = ?");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();
$account = $result->fetch_assoc();
$stmt->close();

// Check if account exists
if (!$account) {
    header('Location: ?page=chart_of_accounts');
    exit();
}

// Get account classes
$classes = $conn->query("SELECT * FROM account_classes ORDER BY display_order");

// Get parent accounts (excluding current account)
$parent_accounts = $conn->query("SELECT account_id, account_code, account_name FROM chart_of_accounts WHERE parent_account_id IS NULL AND is_active = TRUE AND account_id != $account_id ORDER BY account_code");
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="h4 fw-bold text-primary">Edit Account</h2>
        <p class="text-muted">Update account information</p>
        <a href="?page=chart_of_accounts" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back to Chart of Accounts
        </a>
    </div>
</div>

<?php if (isset($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Edit Account: <?php echo htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']); ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="?page=edit_account&id=<?php echo $account_id; ?>">
                    <div class="mb-3">
                        <label class="form-label">Account Code *</label>
                        <input type="text" class="form-control" name="account_code" 
                               value="<?php echo htmlspecialchars($account['account_code']); ?>" 
                               required pattern="[0-9]{1,4}(?:\.[0-9]{1,2})?" 
                               title="Account code format: 1100 or 1100.10">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Account Name *</label>
                        <input type="text" class="form-control" name="account_name" 
                               value="<?php echo htmlspecialchars($account['account_name']); ?>" 
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Account Class *</label>
                        <select class="form-select" name="class_id" required>
                            <option value="">Select Class</option>
                            <?php while($class = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $class['class_id']; ?>" 
                                <?php echo ($account['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_code'] . ' - ' . $class['class_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Parent Account</label>
                        <select class="form-select" name="parent_account_id">
                            <option value="">None (Top Level)</option>
                            <?php while($parent = $parent_accounts->fetch_assoc()): ?>
                            <option value="<?php echo $parent['account_id']; ?>"
                                <?php echo ($account['parent_account_id'] == $parent['account_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($parent['account_code'] . ' - ' . $parent['account_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Opening Balance</label>
                        <input type="text" class="form-control" 
                               value=" <?php echo number_format($account['opening_balance'], 2); ?>" 
                               readonly>
                        <small class="text-muted">Opening balance cannot be changed after creation</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Balance</label>
                        <input type="text" class="form-control" 
                               value=" <?php echo number_format($account['current_balance'], 2); ?>" 
                               readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($account['description']); ?></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_control_account" 
                               id="controlAccount" <?php echo ($account['is_control_account'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="controlAccount">Is Control Account</label>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" 
                               id="isActive" <?php echo ($account['is_active'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="isActive">Active Account</label>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" name="update_account" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Update Account
                        </button>
                        <a href="?page=chart_of_accounts" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
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
</div>

<?php $conn->close(); ?>
