
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/approval_helper.php';
$conn = getConnection();

// Initialize messages
$success_message = '';
$error_message = '';

// Check for success from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $added_code = isset($_GET['added_code']) ? htmlspecialchars($_GET['added_code']) : '';
    $success_message = "Customer added successfully!" . ($added_code ? " (Code: $added_code)" : "");
}

// Check for update success
if (isset($_GET['update_success']) && $_GET['update_success'] == 1) {
    $success_message = "Customer updated successfully!";
}

// Check for delete success
if (isset($_GET['delete_success']) && $_GET['delete_success'] == 1) {
    $success_message = "Customer deleted successfully!";
}

// Check for delete error
if (isset($_GET['error']) && $_GET['error'] == 'has_active_loans') {
    $error_message = "Cannot delete customer. Customer has active loans!";
}

if (isset($_GET['error']) && $_GET['error'] == 'delete_failed') {
    $error_message = "Failed to delete customer. Please try again!";
}

// Get all ACTIVE customers
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "SELECT * FROM customers WHERE is_active = TRUE";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (customer_name LIKE ? OR customer_code LIKE ? OR id_number LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    $params = [$search_term, $search_term, $search_term, $search_term];
    $types = "ssss";
}

$query .= " ORDER BY created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $customers = $stmt->get_result();
    } else {
        $error_message = "Failed to prepare search query: " . $conn->error;
        $customers = false;
    }
} else {
    $customers = $conn->query($query);
}

if (!$customers) {
    $error_message = "Failed to fetch customers: " . $conn->error;
}

// Handle delete customer request — submit to approvals instead of direct delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer_id'])) {
    $delete_id = intval($_POST['delete_customer_id']);

    if ($delete_id > 0) {
        // Fetch customer name for the description
        $cust_row = $conn->query("SELECT customer_name, customer_code FROM customers WHERE customer_id = $delete_id");
        $cust_info = $cust_row ? $cust_row->fetch_assoc() : null;
        $cust_name = $cust_info['customer_name'] ?? 'Customer #' . $delete_id;

        $approval_data = [
            'customer_id'   => $delete_id,
            'customer_name' => $cust_name,
            'customer_code' => $cust_info['customer_code'] ?? '',
            'action_note'   => 'Permanent deletion of customer and all related records',
        ];

        if (submitForApproval($conn, 'delete', 'customer', $delete_id, $approval_data, "Delete customer: $cust_name")) {
            $success_message = "⏳ Deletion request for <strong>$cust_name</strong> has been submitted for approval by Director or MD. The customer will only be deleted after approval.";
        } else {
            $error_message = "Could not submit deletion for approval: " . $conn->error;
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="h4 fw-bold text-primary">Customers</h2>
        <p class="text-muted">Manage your loan customers</p>
    </div>
</div>

<!-- Display Messages -->
<?php if (!empty($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($success_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($error_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <form method="GET" action="" class="d-flex">
            <input type="hidden" name="page" value="customers">
            <input type="text" class="form-control me-2" name="search" placeholder="Search customers..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search"></i>
            </button>
            <?php if (!empty($search)): ?>
            <a href="?page=customers" class="btn btn-outline-secondary ms-2">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=add_customer" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> Add New Customer
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Customer List</h5>
                <span class="badge bg-primary"><?php echo $customers ? $customers->num_rows : 0; ?> Customers</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Customer Name</th>
                                <th>ID Number</th>
                                <th>Gender</th>
                                <th>Phone</th>
                                <th>Risk Rating</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($customers && $customers->num_rows > 0): ?>
                                <?php while($customer = $customers->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($customer['customer_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['id_number']); ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php echo $customer['gender'] == 'Male' ? 'bg-primary' : 
                                                   ($customer['gender'] == 'Female' ? 'bg-pink' : 'bg-secondary'); ?>">
                                            <?php echo $customer['gender']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php echo $customer['risk_rating'] == 'Low' ? 'bg-success' : 
                                                   ($customer['risk_rating'] == 'Medium' ? 'bg-warning' : 'bg-danger'); ?>">
                                            <?php echo $customer['risk_rating']; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a href="?page=view_customer&id=<?php echo $customer['customer_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger delete-btn"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal"
                                                data-customer-id="<?php echo $customer['customer_id']; ?>"
                                                data-customer-name="<?php echo htmlspecialchars($customer['customer_name']); ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">No customers found. <a href="?page=add_customer">Add your first customer!</a></div>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete customer: <strong id="customerName"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action will permanently delete:
                    <ul class="mb-0 mt-2">
                        <li>Customer information</li>
                        <li>All related loans</li>
                        <li>All payment records</li>
                        <li>All documents and notes</li>
                    </ul>
                </div>
                <p class="text-danger"><strong>This action cannot be undone!</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" id="deleteCustomerId" name="delete_customer_id" value="">
                    <button type="submit" class="btn btn-danger">Delete Permanently</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (isset($conn)) $conn->close(); ?>

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
.bg-pink {
    background-color: #e83e8c !important;
}
</style>

<script>
// JavaScript for delete confirmation modal
document.addEventListener('DOMContentLoaded', function() {
    const deleteModal = document.getElementById('deleteModal');
    const customerNameElement = document.getElementById('customerName');
    const deleteCustomerIdElement = document.getElementById('deleteCustomerId');
    
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const customerId = button.getAttribute('data-customer-id');
            const customerName = button.getAttribute('data-customer-name');
            
            // Set the customer name in the modal
            customerNameElement.textContent = customerName;
            
            // Set the customer ID in the hidden input
            deleteCustomerIdElement.value = customerId;
        });
    }
});
</script>
