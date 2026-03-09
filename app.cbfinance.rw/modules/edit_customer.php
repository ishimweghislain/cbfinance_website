<?php
require_once __DIR__ . '/../config/database.php';
$conn = getConnection();

// Initialize messages
$success_message = '';
$error_message = '';
$form_data = [];

// Get customer ID from URL
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id <= 0) {
    header("Location: ?page=customers&error=invalid_id");
    exit();
}

// Fetch existing customer data (Removed is_active=TRUE to allow editing pending/rejected)
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
    
    // Initialize form data
    $form_data = [
        'customer_code' => $customer['customer_code'],
        'customer_name' => $customer['customer_name'],
        'birth_place' => $customer['birth_place'] ?? '',
        'id_number' => $customer['id_number'],
        'account_number' => $customer['account_number'] ?? '',
        'occupation' => $customer['occupation'] ?? '',
        'gender' => $customer['gender'],
        'date_of_birth' => $customer['date_of_birth'],
        'phone' => $customer['phone'],
        'father_name' => $customer['father_name'] ?? '',
        'mother_name' => $customer['mother_name'] ?? '',
        'spouse' => $customer['spouse'] ?? '',
        'spouse_occupation' => $customer['spouse_occupation'] ?? '',
        'spouse_phone' => $customer['spouse_phone'] ?? '',
        'marriage_type' => $customer['marriage_type'] ?? 'Single',
        'address' => $customer['address'],
        'location' => $customer['location'] ?? '',
        'project' => $customer['project'] ?? '',
        'project_location' => $customer['project_location'] ?? '',
        'caution_location' => $customer['caution_location'] ?? '',
        'email' => $customer['email'] ?? '',
        'organization' => $customer['organization'] ?? 'Capital Bridge Finance',
        'status' => $customer['status'] ?? ($customer['is_active'] ? 'Approved' : 'Pending')
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    // Get form data
    $customer_code = trim($_POST['customer_code']);
    $customer_name = trim($_POST['customer_name']);
    $birth_place = trim($_POST['birth_place']);
    $id_number = trim($_POST['id_number']);
    $account_number = trim($_POST['account_number']);
    $occupation = trim($_POST['occupation']);
    $gender = $_POST['gender'] ?? 'Male';
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $phone = trim($_POST['phone']);
    $father_name = trim($_POST['father_name']);
    $mother_name = trim($_POST['mother_name']);
    $spouse = trim($_POST['spouse'] ?? '');
    $spouse_occupation = trim($_POST['spouse_occupation'] ?? '');
    $spouse_phone = trim($_POST['spouse_phone'] ?? '');
    $marriage_type = $_POST['marriage_type'] ?? 'Single';
    $address = trim($_POST['address'] ?? '');
    $location = trim($_POST['location']);
    $project = trim($_POST['project']);
    $project_location = trim($_POST['project_location']);
    $caution_location = trim($_POST['caution_location']);
    $email = trim($_POST['email']);
    $organization = trim($_POST['organization']) ?: 'Capital Bridge Finance';
    $status = $_POST['status'];
    $is_active = ($status == 'Approved') ? 1 : 0;

    // Update form data for re-population
    $form_data = array_merge($form_data, $_POST);
    
    // Validate
    if (empty($customer_name) || empty($id_number) || empty($phone)) {
        $error_message = "Name, ID Number, and Phone are required.";
    } else {
        $conn->begin_transaction();
        try {
            $update_sql = "UPDATE customers SET 
                customer_code = ?, customer_name = ?, birth_place = ?, id_number = ?, 
                account_number = ?, occupation = ?, gender = ?, date_of_birth = ?, 
                phone = ?, father_name = ?, mother_name = ?, spouse = ?, 
                spouse_occupation = ?, spouse_phone = ?, marriage_type = ?, address = ?, 
                location = ?, project = ?, project_location = ?, caution_location = ?, 
                email = ?, organization = ?, status = ?, is_active = ?, updated_at = NOW() 
                WHERE customer_id = ?";
            
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssssssssssssssssssssssiii", 
                $customer_code, $customer_name, $birth_place, $id_number, $account_number,
                $occupation, $gender, $date_of_birth, $phone, $father_name, $mother_name,
                $spouse, $spouse_occupation, $spouse_phone, $marriage_type, $address,
                $location, $project, $project_location, $caution_location, $email,
                $organization, $status, $is_active, $customer_id
            );
            
            if ($update_stmt->execute()) {
                $conn->commit();
                $success_message = "Customer updated successfully!";
                $form_data['status'] = $status;
            } else {
                throw new Exception($update_stmt->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Update failed: " . $e->getMessage();
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 fw-bold text-primary">Edit Member</h2>
                <p class="text-muted">Modify information for <strong><?php echo htmlspecialchars($form_data['customer_name']); ?></strong></p>
            </div>
            <a href="?page=customers" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
</div>

<?php if (!empty($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-warning py-2">
                <h6 class="mb-0 fw-bold">Update Details</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select border-primary" name="status">
                                <option value="Pending" <?php echo $form_data['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Approved" <?php echo $form_data['status'] == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected" <?php echo $form_data['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" class="form-control" name="customer_code" value="<?php echo htmlspecialchars($form_data['customer_code']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="customer_name" required value="<?php echo htmlspecialchars($form_data['customer_name']); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">ID Number</label><input type="text" class="form-control" name="id_number" value="<?php echo htmlspecialchars($form_data['id_number']); ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Phone</label><input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($form_data['phone']); ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($form_data['email']); ?>"></div>
                    </div>

                    <div class="accordion mb-4" id="detailsAccordion">
                        <div class="accordion-item border-0 shadow-sm mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePersonal">
                                    Personal & Family Info
                                </button>
                            </h2>
                            <div id="collapsePersonal" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-3"><label class="form-label">Birth Place</label><input type="text" class="form-control" name="birth_place" value="<?php echo htmlspecialchars($form_data['birth_place']); ?>"></div>
                                        <div class="col-md-4 mb-3"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value="Male" <?php echo $form_data['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option><option value="Female" <?php echo $form_data['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option></select></div>
                                        <div class="col-md-4 mb-3"><label class="form-label">DOB</label><input type="date" class="form-control" name="date_of_birth" value="<?php echo $form_data['date_of_birth']; ?>"></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3"><label class="form-label">Father's Name</label><input type="text" class="form-control" name="father_name" value="<?php echo htmlspecialchars($form_data['father_name']); ?>"></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Mother's Name</label><input type="text" class="form-control" name="mother_name" value="<?php echo htmlspecialchars($form_data['mother_name']); ?>"></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3"><label class="form-label">Marriage</label><input type="text" class="form-control" name="marriage_type" value="<?php echo htmlspecialchars($form_data['marriage_type']); ?>"></div>
                                        <div class="col-md-4 mb-3"><label class="form-label">Spouse</label><input type="text" class="form-control" name="spouse" value="<?php echo htmlspecialchars($form_data['spouse']); ?>"></div>
                                        <div class="col-md-4 mb-3"><label class="form-label">Spouse Occupation</label><input type="text" class="form-control" name="spouse_occupation" value="<?php echo htmlspecialchars($form_data['spouse_occupation']); ?>"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item border-0 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAccount">
                                    Account & Project Info
                                </button>
                            </h2>
                            <div id="collapseAccount" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3"><label class="form-label">Account No.</label><input type="text" class="form-control" name="account_number" value="<?php echo htmlspecialchars($form_data['account_number']); ?>"></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Occupation</label><input type="text" class="form-control" name="occupation" value="<?php echo htmlspecialchars($form_data['occupation']); ?>"></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3"><label class="form-label">Project</label><input type="text" class="form-control" name="project" value="<?php echo htmlspecialchars($form_data['project']); ?>"></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Caution Loc.</label><input type="text" class="form-control" name="caution_location" value="<?php echo htmlspecialchars($form_data['caution_location']); ?>"></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12 mb-3"><label class="form-label">Address</label><textarea class="form-control" name="address"><?php echo htmlspecialchars($form_data['address']); ?></textarea></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="?page=customers" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_customer" class="btn btn-warning fw-bold">Update Member Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
body { font-size: 13px !important; }
.form-label { font-weight: 500; font-size: 11px; text-transform: uppercase; color: #666; margin-bottom: 2px; }
.accordion-button:not(.collapsed) { background-color: #f8f9fa; color: #333; }
</style>