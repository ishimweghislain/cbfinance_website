<?php
require_once __DIR__ . '/../config/database.php';
$conn = getConnection();

// Initialize messages
$success_message = '';
$error_message = '';

// Quick Approve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_customer_id'])) {
    $approve_id = intval($_POST['approve_customer_id']);
    
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
    
    $update_stmt = $conn->prepare("UPDATE customers SET is_active = TRUE, status = 'Approved', customer_code = ?, client_resubmitted = 0, resubmitted_fields = NULL, correction_fields = NULL, admin_note = NULL WHERE customer_id = ?");
    $update_stmt->bind_param("si", $new_code, $approve_id);
    if ($update_stmt->execute()) {
        $success_message = "Application approved!";
    }
}

// Get all non-approved, non-rejected people
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
// We show everyone who is Pending OR Action Required OR has recently resubmitted (even if status is empty due to enum issues)
$query = "SELECT * FROM customers WHERE status IN ('Pending', 'Action Required') OR (status = '' AND (correction_fields IS NOT NULL AND correction_fields != ''))";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (customer_name LIKE ? OR customer_code LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $params = array_fill(0, 4, $search_term);
    $types = "ssss";
}

$query .= " ORDER BY client_resubmitted DESC, created_at ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $customers = $stmt->get_result();
    } else {
        $customers = false;
    }
} else {
    $customers = $conn->query($query);
}
?>

<div class="row mb-4">
    <div class="col-12 py-3">
        <h2 class="h4 fw-bold text-primary"><i class="bi bi-hourglass-split me-2"></i> Requested Loans</h2>
        <p class="text-muted small">Manage applications and document resubmissions.</p>
    </div>
</div>

<?php if (isset($_GET['correction_sent'])): ?>
<div class="alert alert-info py-2 shadow-sm border-0 border-start border-info border-4" role="alert">
    <i class="bi bi-info-circle-fill me-2"></i> Correction request sent. Applicant stays here until they resubmit.
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-5">
        <form method="GET" action="" class="d-flex">
            <input type="hidden" name="page" value="pending_customers">
            <input type="text" class="form-control me-2 rounded-3 text-sm" name="search" placeholder="Search requests..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary btn-sm px-4">Search</button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between">
        <h6 class="mb-0 fw-bold">Active Requests</h6>
        <span class="badge bg-primary px-3"><?php echo $customers ? $customers->num_rows : 0; ?> Total</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-sm">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Applicant</th>
                        <th>Loan Type</th>
                        <th>Status</th>
                        <th class="text-center pe-4">Action Required</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($customers && $customers->num_rows > 0): ?>
                        <?php while($customer = $customers->fetch_assoc()): ?>
                        <tr class="<?php echo $customer['client_resubmitted'] ? 'bg-primary bg-opacity-10' : ''; ?>">
                            <td class="ps-4">
                                <div class="fw-bold"><?php echo htmlspecialchars($customer['customer_name']); ?></div>
                                <div class="text-muted x-small"><?php echo htmlspecialchars($customer['customer_code']); ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo $customer['loan_type']; ?></span></td>
                            <td>
                                <?php 
                                $stat = $customer['status'];
                                if (empty($stat) && !empty($customer['correction_fields'])) $stat = 'Action Required';
                                $badge = 'bg-warning text-dark';
                                if($stat == 'Action Required') $badge = 'bg-info text-white';
                                ?>
                                <span class="badge <?php echo $badge; ?>"><?php echo strtoupper($stat ?: 'PENDING'); ?></span>
                                <?php if($customer['client_resubmitted']): ?>
                                    <span class="badge bg-danger ms-1 animate-pulse"><i class="bi bi-lightning-fill"></i> UPDATED</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-4">
                                <a href="?page=view_customer&id=<?php echo $customer['customer_id']; ?>" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm">
                                    REVIEW & DECIDE
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-5">No pending requests.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.x-small { font-size: 11px; }
.animate-pulse { animation: pulse 2s infinite; }
@keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
</style>
<?php if (isset($conn)) $conn->close(); ?>

