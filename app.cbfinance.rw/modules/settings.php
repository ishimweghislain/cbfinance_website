<?php
require_once __DIR__ . '/../config/database.php';
$conn = getConnection();
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_collateral_type'])) {
        $collateral_type_name = $_POST['collateral_type_name'];
        $depreciation_rate = $_POST['depreciation_rate'];
        $description = $_POST['description'];
        
        $stmt = $conn->prepare("INSERT INTO collateral_types (collateral_type_name, depreciation_rate, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sds", $collateral_type_name, $depreciation_rate, $description);
        $stmt->execute();
        
        $success_message = "Collateral type added successfully!";
        $stmt->close();
    }
    
    if (isset($_POST['add_voucher_type'])) {
        $voucher_type_code = $_POST['voucher_type_code'];
        $voucher_type_name = $_POST['voucher_type_name'];
        $prefix = $_POST['prefix'];
        $description = $_POST['description'];
        
        $stmt = $conn->prepare("INSERT INTO voucher_types (voucher_type_code, voucher_type_name, prefix, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $voucher_type_code, $voucher_type_name, $prefix, $description);
        $stmt->execute();
        
        $success_message = "Voucher type added successfully!";
        $stmt->close();
    }
}

// Get collateral types
$collateral_types = $conn->query("SELECT * FROM collateral_types WHERE is_active = TRUE ORDER BY collateral_type_name");

// Get voucher types
$voucher_types = $conn->query("SELECT * FROM voucher_types WHERE is_active = TRUE ORDER BY voucher_type_name");

// Get account classes
$account_classes = $conn->query("SELECT * FROM account_classes ORDER BY display_order");
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="h4 fw-bold text-primary">System Settings</h2>
        <p class="text-muted">Configure system parameters and options</p>
    </div>
</div>

<?php if (isset($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Account Classes -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Account Classes</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Class Name</th>
                                <th>Type</th>
                                <th>Normal Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($class = $account_classes->fetch_assoc()): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($class['class_code']); ?></code></td>
                                <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                <td>
                                    <span class="badge 
                                        <?php echo $class['class_type'] == 'Assets' ? 'bg-primary' : 
                                               ($class['class_type'] == 'Liabilities' ? 'bg-success' : 
                                               ($class['class_type'] == 'Owners Equity' ? 'bg-info' : 
                                               ($class['class_type'] == 'Revenue' ? 'bg-warning' : 'bg-danger'))); ?>">
                                        <?php echo $class['class_type']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge 
                                        <?php echo $class['normal_balance'] == 'Debit' ? 'bg-primary' : 'bg-success'; ?>">
                                        <?php echo $class['normal_balance']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Collateral Types -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Collateral Types</h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCollateralTypeModal">
                    <i class="bi bi-plus"></i> Add
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Type Name</th>
                                <th>Depreciation Rate</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($collateral = $collateral_types->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($collateral['collateral_type_name']); ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo number_format($collateral['depreciation_rate'], 1); ?>%
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $collateral['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $collateral['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
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
    <!-- Voucher Types -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Voucher Types</h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addVoucherTypeModal">
                    <i class="bi bi-plus"></i> Add
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Prefix</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($voucher = $voucher_types->fetch_assoc()): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($voucher['voucher_type_code']); ?></code></td>
                                <td><?php echo htmlspecialchars($voucher['voucher_type_name']); ?></td>
                                <td><code><?php echo htmlspecialchars($voucher['prefix']); ?></code></td>
                                <td>
                                    <span class="badge <?php echo $voucher['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $voucher['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">System Information</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between">
                        <span>Database Version</span>
                        <span class="text-muted">MySQL</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span>Application Version</span>
                        <span class="text-muted">1.0.0</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span>Total Accounts</span>
                        <?php 
                        $total_accounts = $conn->query("SELECT COUNT(*) FROM chart_of_accounts WHERE is_active = TRUE")->fetch_row()[0];
                        ?>
                        <span class="badge bg-primary"><?php echo $total_accounts; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span>Total Customers</span>
                        <?php 
                        $total_customers = $conn->query("SELECT COUNT(*) FROM customers WHERE is_active = TRUE")->fetch_row()[0];
                        ?>
                        <span class="badge bg-primary"><?php echo $total_customers; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span>Total Loans</span>
                        <?php 
                        $total_loans = $conn->query("SELECT COUNT(*) FROM loan_portfolio")->fetch_row()[0];
                        ?>
                        <span class="badge bg-primary"><?php echo $total_loans; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span>Server Time</span>
                        <span class="text-muted"><?php echo date('Y-m-d H:i:s'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Collateral Type Modal -->
<div class="modal fade" id="addCollateralTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Collateral Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Collateral Type Name *</label>
                        <input type="text" class="form-control" name="collateral_type_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Annual Depreciation Rate (%)</label>
                        <input type="number" class="form-control" name="depreciation_rate" value="0.00" step="0.01" min="0" max="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_collateral_type" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Voucher Type Modal -->
<div class="modal fade" id="addVoucherTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Voucher Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Voucher Type Code *</label>
                        <input type="text" class="form-control" name="voucher_type_code" required maxlength="10">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Voucher Type Name *</label>
                        <input type="text" class="form-control" name="voucher_type_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Prefix</label>
                        <input type="text" class="form-control" name="prefix" maxlength="10">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_voucher_type" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $conn->close(); ?>
