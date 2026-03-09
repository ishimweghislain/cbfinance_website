<?php
require_once __DIR__ . '/../config/database.php';
$conn = getConnection();

// Initialize messages
$success_message = '';
$error_message = '';

// Handle Restore Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_customer_id'])) {
    $restore_id = intval($_POST['restore_customer_id']);
    $update_stmt = $conn->prepare("
        UPDATE customers 
        SET status = 'Pending', 
            rejection_reason = NULL, 
            client_resubmitted = 0, 
            resubmitted_fields = NULL 
        WHERE customer_id = ?
    ");
    $update_stmt->bind_param("i", $restore_id);
    if ($update_stmt->execute()) {
        $success_message = "Application restored to Pending list.";
    } else {
        $error_message = "Failed to restore: " . $conn->error;
    }
    $update_stmt->close();
}

// Handle Delete Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer_id'])) {
    $delete_id = intval($_POST['delete_customer_id']);
    
    // Optional: You can add extra safety checks here (e.g. only allow delete if really rejected)
    
    $delete_stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ? AND status = 'Rejected'");
    $delete_stmt->bind_param("i", $delete_id);
    
    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            $success_message = "Customer record permanently deleted.";
        } else {
            $error_message = "Record not found or not in Rejected status.";
        }
    } else {
        $error_message = "Failed to delete: " . $conn->error;
    }
    $delete_stmt->close();
}

// Get rejected customers
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "SELECT * FROM customers WHERE TRIM(status) = 'Rejected'";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (customer_name LIKE ? OR customer_code LIKE ? OR id_number LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $params = array_fill(0, 5, $search_term);
    $types = "sssss";
}

$query .= " ORDER BY updated_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $customers = $stmt->get_result();
    } else {
        $error_message = "Search error: " . $conn->error;
        $customers = false;
    }
} else {
    $customers = $conn->query($query);
}
?>

<div class="row mb-4">
    <div class="col-12 py-3">
        <h2 class="h4 fw-bold text-danger"><i class="bi bi-person-x-fill me-2"></i> Rejected Applications</h2>
        <p class="text-muted small">View rejected profiles, restore them or permanently delete them.</p>
    </div>
</div>

<?php if (!empty($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show shadow-sm border-0 border-start border-success border-4" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success_message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 border-start border-danger border-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-5">
        <form method="GET" action="" class="d-flex">
            <input type="hidden" name="page" value="rejected_customers">
            <input type="text" class="form-control me-2 rounded-3 text-sm" name="search" placeholder="Search rejected..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-danger btn-sm px-4">Search</button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between">
        <h6 class="mb-0 fw-bold">Rejected Applicants</h6>
        <span class="badge bg-danger text-white"><?= $customers ? $customers->num_rows : 0 ?> Records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-sm">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Code</th>
                        <th>Name</th>
                        <th>Rejection Reason</th>
                        <th class="text-center pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($customers && $customers->num_rows > 0): ?>
                        <?php while($customer = $customers->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-danger"><?= htmlspecialchars($customer['customer_code']) ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($customer['customer_name']) ?></div>
                                <div class="text-muted x-small"><?= htmlspecialchars($customer['email']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-medium">
                                    <?= htmlspecialchars($customer['rejection_reason'] ?: 'Requirements not met') ?>
                                </span>
                            </td>
                            <td class="text-center pe-4">
                                <!-- Restore -->
                                <form method="POST" class="d-inline" onsubmit="return confirm('Restore to Pending list?');">
                                    <input type="hidden" name="restore_customer_id" value="<?= $customer['customer_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary px-3 fw-bold me-1">
                                        <i class="bi bi-arrow-counterclockwise"></i> RESTORE
                                    </button>
                                </form>

                                <!-- Delete -->
                                <form method="POST" class="d-inline" onsubmit="return confirm('Permanently delete this customer record?\nThis action cannot be undone!');">
                                    <input type="hidden" name="delete_customer_id" value="<?= $customer['customer_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger px-3 fw-bold me-1">
                                        <i class="bi bi-trash3-fill"></i> DELETE
                                    </button>
                                </form>

                                <!-- Review -->
                                <a href="?page=view_customer&id=<?= $customer['customer_id'] ?>" 
                                   class="btn btn-sm btn-link text-decoration-none">Review Profile</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">No rejected applications found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .x-small { font-size: 11px; }
</style>

<?php 
if (isset($conn)) $conn->close(); 
?>
