<?php
require_once __DIR__ . '/../config/database.php';
$conn = getConnection();

// Initialize messages
$success_message = '';
$error_message = '';

// Check for success from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $added_code = isset($_GET['added_code']) ? htmlspecialchars($_GET['added_code']) : '';
    $success_message = "Customer added successfully!" . ($added_code ? " (Code: $added_code)" : "");
}

if (isset($_GET['update_success']) && $_GET['update_success'] == 1) {
    $success_message = "Customer updated successfully!";
}

if (isset($_GET['delete_success']) && $_GET['delete_success'] == 1) {
    $success_message = "Customer deleted successfully!";
}

if (isset($_GET['error']) && $_GET['error'] == 'has_active_loans') {
    $error_message = "Cannot delete customer. Customer has active loans!";
}

if (isset($_GET['error']) && $_GET['error'] == 'delete_failed') {
    $error_message = "Failed to delete customer. Please try again!";
}

// Handle Approve Customer Request (Basic/Quick Approve)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_customer_id'])) {
    $approve_id = intval($_POST['approve_customer_id']);
    
    // Get current customer code to see if it needs update
    $stmt = $conn->prepare("SELECT customer_code FROM customers WHERE customer_id = ?");
    $stmt->bind_param("i", $approve_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $current_code = $res['customer_code'];
    
    $new_code = $current_code;
    if (strpos($current_code, 'PEND-') === 0) {
        $max_res = $conn->query("SELECT MAX(customer_code) as max_code FROM customers WHERE customer_code LIKE 'C%' AND customer_code NOT LIKE 'PEND-%'");
        $max_row = $max_res->fetch_assoc();
        $max_code = $max_row['max_code'];
        if ($max_code) {
            $num = intval(substr($max_code, 1)) + 1;
            $new_code = 'C' . str_pad($num, 3, '0', STR_PAD_LEFT);
        } else {
            $new_code = 'C001';
        }
    }
    
    $update_stmt = $conn->prepare("UPDATE customers SET is_active = TRUE, status = 'Approved', customer_code = ?, client_resubmitted = 0, correction_fields = NULL, admin_note = NULL WHERE customer_id = ?");
    $update_stmt->bind_param("si", $new_code, $approve_id);
    if ($update_stmt->execute()) {
        $success_message = "Customer approved successfully!";
    } else {
        $error_message = "Failed to approve: " . $conn->error;
    }
}

// Handle Reject Customer Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_customer_id'])) {
    $reject_id = intval($_POST['reject_customer_id']);
    $update_stmt = $conn->prepare("UPDATE customers SET status = 'Rejected', client_resubmitted = 0, correction_fields = NULL, admin_note = NULL WHERE customer_id = ?");
    $update_stmt->bind_param("i", $reject_id);
    if ($update_stmt->execute()) {
        $success_message = "Application rejected.";
    } else {
        $error_message = "Failed to reject: " . $conn->error;
    }
}

// Get all customers
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1';
$query = "SELECT * FROM customers";
$params = [];
$types = "";

$where_clauses = [];
if (!$show_inactive) {
    // Show ONLY approved customers on this page
    $where_clauses[] = "status = 'Approved'";
}

if (!empty($search)) {
    $where_clauses[] = "(customer_name LIKE ? OR customer_code LIKE ? OR id_number LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $params = array_fill(0, 5, $search_term);
    $types = "sssss";
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " ORDER BY record_date desc";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $error_message = "Search failed: " . $conn->error;
        $result = false;
    }
} else {
    $result = $conn->query($query);
}

if (!$result) {
    $error_message = "Data fetch failed: " . $conn->error;
}

// Fetch all rows into array so we can assign CBF numbers bottom-to-top
$customers_array = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $customers_array[] = $row;
    }
}

// Total rows — CBF-001 = last row (bottom), CBF-N = first row (top)
$total = count($customers_array);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer_id'])) {
    $delete_id = intval($_POST['delete_customer_id']);
    if ($delete_id > 0) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        if ($conn->query("DELETE FROM customers WHERE customer_id = $delete_id")) {
             $conn->query("SET FOREIGN_KEY_CHECKS = 1");
             echo "<script>window.href.location='?page=customers'</script>";
             exit();
        }
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $error_message = "Deletion failed.";
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="h4 fw-bold text-primary">All Approved Members</h2>
        <p class="text-muted">Manage active members and view their full history.</p>
    </div>
</div>

<!-- Display Messages -->
<?php if (!empty($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
    <?php echo htmlspecialchars($success_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
    <?php echo htmlspecialchars($error_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <form method="GET" action="" class="d-flex">
            <input type="hidden" name="page" value="customers">
            <input type="text" class="form-control me-2" name="search" placeholder="Search members..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <div class="col-md-6 text-end">
        <div class="d-flex gap-2 justify-content-end align-items-center">
            <div class="form-check form-switch me-3">
                <input class="form-check-input" type="checkbox" id="showInactiveToggle" 
                       <?php echo $show_inactive ? 'checked' : ''; ?>
                       onchange="toggleInactiveCustomers(this)">
                <label class="form-check-label small" for="showInactiveToggle">Show All (Incl. Pending/Rejected)</label>
            </div>
            <a href="?page=add_customer" class="btn btn-primary btn-sm">
                <i class="bi bi-person-plus"></i> Add Manually
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Member Directory</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width:100px;">Member #</th>
                                <th style="width:100px;">Code</th>
                                <th>Name</th>
                                <th>Phone/Email</th>
                                <th>ID Number</th>
                                <th>Status</th>
                                <th class="text-center pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($customers_array)): ?>
                                <?php foreach ($customers_array as $index => $customer): ?>
                                <?php
                                    // CBF-001 = bottom row (oldest/last in DESC order), increases upward
                                    $cbf_number = $total - $index;
                                    $cbf_label  = 'CBF-' . str_pad($cbf_number, 3, '0', STR_PAD_LEFT);
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge cbf-badge px-2 py-1 fw-bold"><?php echo $cbf_label; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($customer['customer_code']); ?></td>
                                    <td>
                                    <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                    <td>
                                    <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                    <td>
                                        <div class="small fw-bold"><?php echo htmlspecialchars($customer['phone']); ?></div>
                                        <div class="text-muted x-small"><?php echo htmlspecialchars($customer['email']); ?></div>
                                    </td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($customer['id_number']); ?></td>
                                    <td>
                                        <?php 
                                        $status = $customer['status'];
                                        $badge = 'bg-success';
                                        if($status == 'Pending') $badge = 'bg-warning text-dark';
                                        if($status == 'Rejected') $badge = 'bg-danger';
                                        if($status == 'Action Required') $badge = 'bg-info';
                                        ?>
                                        <span class="badge <?php echo $badge; ?> px-2"><?php echo strtoupper($status); ?></span>
                                    </td>
                                    <td class="text-center pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <a href="?page=view_customer&id=<?php echo $customer['customer_id']; ?>" class="btn btn-outline-primary" title="View Profile"><i class="bi bi-eye"></i></a>
                                            <a href="?page=edit_customer&id=<?php echo $customer['customer_id']; ?>" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <button type="button" class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $customer['customer_id']; ?>, '<?php echo addslashes($customer['customer_name']); ?>')"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted">No approved members found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST">
    <input type="hidden" name="delete_customer_id" id="deleteId">
</form>

<script>
function toggleInactiveCustomers(checkbox) {
    const url = new URL(window.location.href);
    if (checkbox.checked) { url.searchParams.set('show_inactive', '1'); } 
    else { url.searchParams.delete('show_inactive'); }
    window.location.href = url.toString();
}

function confirmDelete(id, name) {
    if (confirm("Delete '" + name + "'? This will remove all history and records!")) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<style>
.x-small { font-size: 11px; }
.card { border-radius: 1rem; }
.badge { border-radius: 2rem; }
.cbf-badge {
    background-color: #1a3c5e;
    color: #ffffff;
    font-size: 11px;
    letter-spacing: 0.5px;
    border-radius: 6px !important;
}
</style>
<?php if (isset($conn)) $conn->close(); ?>