<?php
require_once __DIR__ . '/../config/database.php';
$conn = getConnection();

// Initialize messages
$error_message = '';
$success_message = '';

// Get customer ID from URL
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Redirect if no valid ID
if ($customer_id <= 0) {
    header("Location: ?page=customers&error=invalid_id");
    exit();
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $action = $_POST['action_type'];
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("SELECT customer_code FROM customers WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
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
        $update_stmt->bind_param("si", $new_code, $customer_id);
        if ($update_stmt->execute()) {
            header("Location: ?page=customers&update_success=1");
            exit();
        }
    } elseif ($action === 'reject') {
        $reason = $conn->real_escape_string($_POST['rejection_reason'] ?: 'Does not meet requirements');
        $conn->query("UPDATE customers SET status = 'Rejected', rejection_reason = '$reason', client_resubmitted = 0, resubmitted_fields = NULL, correction_fields = NULL, admin_note = NULL WHERE customer_id = $customer_id");
        header("Location: ?page=rejected_customers&rejected=1");
        exit();
    } elseif ($action === 'request_correction') {
        $fields = isset($_POST['fields']) ? implode(',', $_POST['fields']) : '';
        $note = $conn->real_escape_string($_POST['admin_note']);
        // When asking for correction, we clear the 'client_resubmitted' and 'resubmitted_fields' so we can track the NEXT batch
        $conn->query("UPDATE customers SET status = 'Action Required', correction_fields = '$fields', admin_note = '$note', client_resubmitted = 0, resubmitted_fields = NULL WHERE customer_id = $customer_id");
        header("Location: ?page=pending_customers&correction_sent=1");
        exit();
    }
}

// Fetch customer data
$customer = null;
$sql = "SELECT * FROM customers WHERE customer_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();
    
    if (!$customer) {
        header("Location: ?page=customers&error=customer_not_found");
        exit();
    }
} else {
    $error_message = "Failed to fetch customer data: " . $conn->error;
}

$resubbed = !empty($customer['resubmitted_fields']) ? explode(',', $customer['resubmitted_fields']) : [];
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 fw-bold text-primary">Customer Profile Review</h2>
                <p class="text-muted small">ID: <?php echo $customer['customer_code']; ?></p>
            </div>
            <a href="?page=pending_customers" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Pending
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Profile Info -->
        <div class="card mb-4 shadow-sm border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-circle text-primary me-2"></i> Personal Profile</h6>
                <?php 
                $stat = $customer['status'];
                if (empty($stat) && !empty($customer['correction_fields'])) $stat = 'Action Required';
                if (empty($stat)) $stat = 'Pending';

                $status_class = 'bg-secondary';
                if ($stat == 'Approved') $status_class = 'bg-success';
                if ($stat == 'Pending') $status_class = 'bg-warning text-dark';
                if ($stat == 'Action Required') $status_class = 'bg-info text-white';
                if ($stat == 'Rejected') $status_class = 'bg-danger';
                ?>
                <div>
                    <?php if($customer['client_resubmitted']): ?>
                        <span class="badge bg-danger animate-pulse me-2"><i class="bi bi-lightning-fill"></i> UPDATED BY CLIENT</span>
                    <?php endif; ?>
                    <span class="badge <?php echo $status_class; ?> p-2 px-3">
                        STATUS: <?php echo strtoupper(htmlspecialchars($stat)); ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="text-muted small mb-1 uppercase tracking-wider font-bold">Full Name <?php if(in_array('customer_name', $resubbed)) echo '<span class="text-danger">(NEW)</span>'; ?></label>
                        <p class="fw-bold mb-3"><?php echo htmlspecialchars($customer['customer_name']); ?></p>
                        
                        <label class="text-muted small mb-1 uppercase tracking-wider font-bold">National ID <?php if(in_array('id_number', $resubbed)) echo '<span class="text-danger">(NEW)</span>'; ?></label>
                        <p class="fw-bold mb-3"><?php echo htmlspecialchars($customer['id_number']); ?></p>

                        <label class="text-muted small mb-1 uppercase tracking-wider font-bold">Date of Birth</label>
                        <p class="fw-bold mb-3"><?php echo htmlspecialchars($customer['date_of_birth'] ?: 'N/A'); ?></p>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small mb-1 uppercase tracking-wider font-bold">Phone Number <?php if(in_array('phone', $resubbed)) echo '<span class="text-danger">(NEW)</span>'; ?></label>
                        <p class="fw-bold mb-3"><?php echo htmlspecialchars($customer['phone']); ?></p>
                        
                        <label class="text-muted small mb-1 uppercase tracking-wider font-bold">Email Address</label>
                        <p class="fw-bold mb-3"><?php echo htmlspecialchars($customer['email'] ?: 'N/A'); ?></p>

                        <label class="text-muted small mb-1 uppercase tracking-wider font-bold">Loan Details</label>
                        <p class="mb-0 font-bold text-primary"><?php echo htmlspecialchars($customer['loan_type']); ?> LOAN</p>
                        <p class="mb-0 fw-black text-dark">Amount: <?php echo number_format($customer['requested_amount'] ?? 0); ?> RWF</p>
                        <p class="mb-3 x-small fw-bold text-muted">Period: <?php echo $customer['loan_duration'] ?? 0; ?> Months</p>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small mb-1 uppercase tracking-wider font-bold">Province & District</label>
                        <p class="fw-bold mb-0 text-primary"><?php echo htmlspecialchars($customer['province'] ?: 'N/A'); ?></p>
                        <p class="fw-bold mb-3"><?php echo htmlspecialchars($customer['location'] ?: 'N/A'); ?></p>

                        <label class="text-muted small mb-1 uppercase tracking-wider font-bold">Occupation</label>
                        <p class="fw-bold mb-3"><?php echo htmlspecialchars($customer['occupation'] ?: 'N/A'); ?></p>
                    </div>
                </div>
                <hr class="my-4 op-20">
                <div class="row g-4">
                    <div class="col-md-6 border-end">
                        <label class="small fw-black text-muted mb-3 block uppercase tracking-tighter">Marriage & Family</label>
                        <label class="text-muted x-small mb-1">Marriage Status</label>
                        <p class="fw-bold small mb-0"><?php echo htmlspecialchars($customer['marriage_type']); ?></p>
                        <?php if(!empty($customer['spouse'])): ?>
                            <div class="mt-3 p-3 bg-light rounded-3 border">
                                <p class="x-small text-muted mb-1 uppercase font-black">Spouse Details</p>
                                <p class="small fw-bold mb-0"><?php echo htmlspecialchars($customer['spouse']); ?></p>
                                <p class="x-small text-muted"><?php echo htmlspecialchars($customer['spouse_phone']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-black text-muted mb-3 block uppercase tracking-tighter">Project & Collateral</label>
                        <label class="text-muted x-small mb-1">Project Purpose</label>
                        <p class="fw-bold small mb-3"><?php echo htmlspecialchars($customer['project'] ?: 'N/A'); ?></p>
                        
                        <div class="row">
                            <div class="col-6">
                                <label class="text-muted x-small mb-1">Project Loc.</label>
                                <p class="fw-bold x-small mb-3"><?php echo htmlspecialchars($customer['project_location'] ?: 'N/A'); ?></p>
                            </div>
                            <div class="col-6">
                                <label class="text-muted x-small mb-1">Caution Loc.</label>
                                <p class="fw-bold x-small mb-3"><?php echo htmlspecialchars($customer['caution_location'] ?: 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents Section -->
        <div class="card mb-4 shadow-sm border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-file-earmark-pdf-fill me-2"></i> Documents Review</h6>
            </div>
            <div class="card-body bg-light/30">
                <div class="row g-3">
                    <?php 
                    $docs = [
                        'doc_id' => 'National ID',
                        'doc_contract' => 'Work Contract',
                        'doc_statement' => 'Bank Statement',
                        'doc_payslip' => 'Latest Payslip',
                        'doc_marital' => 'Marital Certificate',
                        'doc_rdb' => 'RDB Certificate'
                    ];
                    
                    foreach($docs as $key => $label): 
                        if(!empty($customer[$key])):
                            $doc_url = $customer[$key]; 
                            $file_ext = strtolower(pathinfo($customer[$key], PATHINFO_EXTENSION));
                            $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']);
                    ?>
                        <div class="col-md-6 mb-3">
                            <div class="p-3 border rounded-4 bg-white shadow-sm h-100 <?php if(in_array($key, $resubbed)) echo 'border-danger border-2'; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <span class="d-block small text-muted"><?php echo $label; ?> <?php if(in_array($key, $resubbed)) echo '<span class="text-danger fw-bold">(NEW)</span>'; ?></span>
                                        <div class="small fw-bold text-truncate" style="max-width: 120px;"><?php echo basename($customer[$key]); ?></div>
                                    </div>
                                    <div class="btn-group">
                                        <a href="<?php echo $doc_url; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                        <a href="<?php echo $doc_url; ?>" download class="btn btn-sm btn-primary"><i class="bi bi-download"></i></a>
                                    </div>
                                </div>
                                
                                <?php if($is_image): ?>
                                    <div class="text-center bg-light rounded-3 p-1 border" style="height: 140px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                                        <img src="<?php echo $doc_url; ?>" class="img-fluid" style="max-height: 100%; object-fit: contain;">
                                    </div>
                                <?php else: ?>
                                    <div class="text-center bg-light rounded-3 p-3 border d-flex flex-column align-items-center justify-content-center" style="height: 140px;">
                                        <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 3rem;"></i>
                                        <p class="text-[10px] text-muted uppercase mt-2 fw-bold">PDF Document</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions Panel -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-bottom"><h6 class="mb-0 fw-bold">Review Verdict</h6></div>
            <div class="card-body">
                <?php if($customer['status'] != 'Approved'): ?>
                    <div class="d-grid gap-2 mb-4">
                        <form method="POST">
                            <input type="hidden" name="action_type" value="approve">
                            <button type="submit" class="btn btn-success w-100 py-3 fw-bold rounded-3 shadow-sm" onclick="return confirm('Approve this member?')">APPROVE MEMBER</button>
                        </form>
                    </div>

                    <div class="mb-4">
                        <p class="small fw-bold text-info mb-2"><i class="bi bi-pencil-square"></i> REQUEST CORRECTION</p>
                        <form method="POST">
                            <input type="hidden" name="action_type" value="request_correction">
                            <div class="bg-light p-3 rounded-3 mb-3" style="max-height: 200px; overflow-y: auto;">
                                <?php 
                                $all_fields = [
                                    'customer_name' => 'Full Name', 'id_number' => 'National ID', 'phone' => 'Phone Number',
                                    'requested_amount' => 'Requested Amount', 'loan_duration' => 'Loan Period',
                                    'email' => 'Email', 'dob' => 'Date of Birth', 'occupation' => 'Occupation',
                                    'province' => 'Province', 'location' => 'District/Area',
                                    'doc_id' => 'ID Copy', 'doc_contract' => 'Contract',
                                    'doc_statement' => 'Statement', 'doc_payslip' => 'Payslip', 
                                    'doc_marital' => 'Marital Cert', 'doc_rdb' => 'RDB Cert'
                                ];
                                foreach($all_fields as $f_key => $f_label): 
                                ?>
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" name="fields[]" value="<?php echo $f_key; ?>" id="f_<?php echo $f_key; ?>">
                                        <label class="form-check-label small" for="f_<?php echo $f_key; ?>"><?php echo $f_label; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mb-3">
                                <label class="x-small fw-bold text-muted mb-1">Note to Client</label>
                                <textarea name="admin_note" class="form-control form-control-sm" rows="3" placeholder="Explain what to fix..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-info w-100 fw-bold border-2">Send Request</button>
                        </form>
                    </div>

                    <div class="pt-3 border-top">
                        <p class="small fw-bold text-danger mb-2">REJECT APPLICATION</p>
                        <form method="POST">
                            <input type="hidden" name="action_type" value="reject">
                            <div class="mb-3">
                                <label class="x-small fw-bold text-muted mb-1">Reason</label>
                                <textarea name="rejection_reason" class="form-control form-control-sm" rows="2" placeholder="Why reject?"></textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-danger w-100 btn-sm font-bold border-2" onclick="return confirm('Confirm rejection?')">REJECT NOW</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success text-center py-4 border-0 rounded-4">
                        <i class="bi bi-patch-check-fill display-5 mb-2"></i>
                        <p class="fw-bold mb-0">ENABLED MEMBER</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.x-small { font-size: 11px; }
.animate-pulse { animation: pulse 1.5s infinite; }
@keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }
</style>

<?php if (isset($conn)) $conn->close(); ?>
