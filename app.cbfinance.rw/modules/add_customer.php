<?php
require_once __DIR__ . '/../config/database.php';
$conn = getConnection();

$success_message = '';
$error_message = '';
$form_data = [];

if (isset($_POST['add_customer'])) {

    // ── Basic Info ──────────────────────────────────────────────────────────
    $customer_code      = trim($_POST['customer_code']      ?? '');
    $customer_name      = trim($_POST['customer_name']      ?? '');
    $id_number          = trim($_POST['id_number']          ?? '');
    $account_number     = trim($_POST['account_number']     ?? '');
    $occupation         = trim($_POST['occupation']         ?? '');
    $gender             = $_POST['gender']                  ?? 'Male';
    $date_of_birth      = !empty($_POST['date_of_birth'])   ? $_POST['date_of_birth'] : null;
    $record_date        = !empty($_POST['record_date'])     ? $_POST['record_date']   : date('Y-m-d'); // ✅ NEW
    $phone              = trim($_POST['phone']              ?? '');
    $email              = trim($_POST['email']              ?? '');
    $organization       = trim($_POST['organization']       ?? 'Capital Bridge Finance');
    $created_by         = trim($_POST['created_by']         ?? 'admin');
    $loan_type          = trim($_POST['loan_type']          ?? '');
    $project            = trim($_POST['project']            ?? '');
    $project_location   = trim($_POST['project_location']   ?? '');
    $caution_location   = trim($_POST['caution_location']   ?? '');

    // ── Birth Place (structured → single string) ────────────────────────────
    $birth_province     = trim($_POST['birth_province']     ?? '');
    $birth_district     = trim($_POST['birth_district']     ?? '');
    $birth_sector       = trim($_POST['birth_sector']       ?? '');
    $birth_cell         = trim($_POST['birth_cell']         ?? '');
    $birth_place        = implode(', ', array_filter([$birth_cell, $birth_sector, $birth_district, $birth_province]));

    // ── Current Location (structured → single string) ───────────────────────
    $province           = trim($_POST['province']           ?? '');
    $district           = trim($_POST['district']           ?? '');
    $sector             = trim($_POST['sector']             ?? '');
    $cell               = trim($_POST['cell']               ?? '');
    $location           = implode(', ', array_filter([$cell, $sector, $district, $province]));
    $address            = trim($_POST['address']            ?? '');

    // ── Family & Marital ────────────────────────────────────────────────────
    $father_name        = trim($_POST['father_name']        ?? '');
    $mother_name        = trim($_POST['mother_name']        ?? '');
    $marriage_type      = $_POST['marriage_type']           ?? 'Single';
    $spouse             = trim($_POST['spouse']             ?? '');
    $spouse_id          = trim($_POST['spouse_id']          ?? '');
    $spouse_occupation  = trim($_POST['spouse_occupation']  ?? '');
    $spouse_phone       = trim($_POST['spouse_phone']       ?? '');

    // ── Guarantor ───────────────────────────────────────────────────────────
    $has_guarantor          = $_POST['has_guarantor']           ?? 'No';
    $guarantor_name         = trim($_POST['guarantor_name']     ?? '');
    $guarantor_id           = trim($_POST['guarantor_id']       ?? '');
    $guarantor_phone        = trim($_POST['guarantor_phone']    ?? '');
    $guarantor_occupation   = trim($_POST['guarantor_occupation'] ?? '');

    // ── File Uploads ────────────────────────────────────────────────────────
    $upload_dir = __DIR__ . '/../uploads/documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $doc_id         = handle_file_upload('doc_id',       $upload_dir);
    $doc_contract   = handle_file_upload('doc_contract', $upload_dir);
    $doc_statement  = handle_file_upload('doc_statement',$upload_dir);
    $doc_payslip    = handle_file_upload('doc_payslip',  $upload_dir);
    $doc_marital    = handle_file_upload('doc_marital',  $upload_dir);
    $doc_rdb        = handle_file_upload('doc_rdb',      $upload_dir);

    // Store for form re-population
    $form_data = $_POST;

    // ── Validation ──────────────────────────────────────────────────────────
    $errors = [];
    if (empty($customer_name)) $errors[] = "Customer Name is required.";
    if (empty($id_number))     $errors[] = "ID Number is required.";
    if (empty($phone))         $errors[] = "Phone is required.";

    // Validate ENUM values
    if (!in_array($gender,        ['Male', 'Female', 'Other']))          $gender        = 'Male';
    if (!in_array($marriage_type, ['Single', 'Married', 'Divorced']))    $marriage_type = 'Single';
    if (!in_array($has_guarantor, ['Yes', 'No']))                        $has_guarantor = 'No';

    if (!empty($errors)) {
        $error_message = implode("<br>", $errors);
    } else {
        try {
            // ── Auto-generate customer_code (format: CUST-dd/mm/yy/hh/mm/ss) ──
            if (empty($customer_code)) {
                $customer_code = 'CUST-' . date('d/m/y/H/i/s');
            }

            // ── INSERT ──────────────────────────────────────────────────────
            // 41 bound columns (record_date added)
            $sql = "INSERT INTO customers (
                customer_code,
                customer_name,
                birth_place,
                id_number,
                account_number,
                occupation,
                gender,
                date_of_birth,
                record_date,
                phone,
                email,
                organization,
                father_name,
                mother_name,
                marriage_type,
                spouse,
                spouse_id,
                spouse_occupation,
                spouse_phone,
                address,
                location,
                project,
                project_location,
                caution_location,
                loan_type,
                created_by,
                created_at,
                updated_at,
                is_active,
                status,
                doc_id,
                doc_contract,
                doc_statement,
                doc_payslip,
                doc_marital,
                doc_rdb,
                has_guarantor,
                guarantor_name,
                guarantor_id,
                guarantor_phone,
                guarantor_occupation
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, NOW(), NOW(), 1, 'Approved',
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?
            )";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            // 37 bound params  (created_at, updated_at, is_active, status are literals)
            // Columns bound:
            //  1  customer_code
            //  2  customer_name
            //  3  birth_place
            //  4  id_number
            //  5  account_number
            //  6  occupation
            //  7  gender
            //  8  date_of_birth
            //  9  record_date        ← NEW
            //  10 phone
            //  11 email
            //  12 organization
            //  13 father_name
            //  14 mother_name
            //  15 marriage_type
            //  16 spouse
            //  17 spouse_id
            //  18 spouse_occupation
            //  19 spouse_phone
            //  20 address
            //  21 location
            //  22 project
            //  23 project_location
            //  24 caution_location
            //  25 loan_type
            //  26 created_by
            //  27 doc_id
            //  28 doc_contract
            //  29 doc_statement
            //  30 doc_payslip
            //  31 doc_marital
            //  32 doc_rdb
            //  33 has_guarantor
            //  34 guarantor_name
            //  35 guarantor_id
            //  36 guarantor_phone
            //  37 guarantor_occupation

            $stmt->bind_param(
                "sssssssssssssssssssssssssssssssssssss",
                $customer_code,
                $customer_name,
                $birth_place,
                $id_number,
                $account_number,
                $occupation,
                $gender,
                $date_of_birth,
                $record_date,           // ✅ NEW
                $phone,
                $email,
                $organization,
                $father_name,
                $mother_name,
                $marriage_type,
                $spouse,
                $spouse_id,
                $spouse_occupation,
                $spouse_phone,
                $address,
                $location,
                $project,
                $project_location,
                $caution_location,
                $loan_type,
                $created_by,
                $doc_id,
                $doc_contract,
                $doc_statement,
                $doc_payslip,
                $doc_marital,
                $doc_rdb,
                $has_guarantor,
                $guarantor_name,
                $guarantor_id,
                $guarantor_phone,
                $guarantor_occupation
            );

            if ($stmt->execute()) {
                $new_id = $stmt->insert_id;
                $success_message = "Customer <strong>{$customer_name}</strong> added successfully! (ID: {$new_id}, Code: {$customer_code})";
                $form_data = []; // clear form on success
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();

        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// ── File upload helper ───────────────────────────────────────────────────────
function handle_file_upload($field, $upload_dir) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $original   = basename($_FILES[$field]['name']);
        $ext        = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed    = ['jpg', 'jpeg', 'png', 'pdf', 'gif', 'webp'];

        if (!in_array($ext, $allowed)) {
            return null;
        }

        $filename = $field . '_' . time() . '_' . rand(10, 99) . '.' . $ext;
        $target   = $upload_dir . $filename;

        if (move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
            return 'uploads/documents/' . $filename;
        }
    }
    return null;
}
?>

<!-- ── UI ─────────────────────────────────────────────────────────────────── -->

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 fw-bold text-primary">Add New Customer</h2>
                <p class="text-muted mb-0">Manually register a pre-approved customer</p>
            </div>
            <a href="?page=customers" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Customers
            </a>
        </div>
    </div>
</div>

<?php if (!empty($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white py-2">
                <h6 class="mb-0"><i class="bi bi-person-plus me-2"></i>Manual Registration</h6>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">

                    <!-- Basic Information -->
                    <h6 class="section-title">Basic Information</h6>
                    <div class="row">
                        <!-- Customer Code is always auto-generated -->
                        <input type="hidden" name="customer_code" value="">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="customer_name"
                                value="<?php echo htmlspecialchars($form_data['customer_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">ID Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="id_number"
                                value="<?php echo htmlspecialchars($form_data['id_number'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="phone"
                                value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email"
                                value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender">
                                <option value="Male"   <?php echo ($form_data['gender'] ?? 'Male') === 'Male'   ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($form_data['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other"  <?php echo ($form_data['gender'] ?? '') === 'Other'  ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="date_of_birth"
                                value="<?php echo htmlspecialchars($form_data['date_of_birth'] ?? ''); ?>">
                        </div>
                        <!-- ✅ NEW: Record Date field -->
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Record Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="record_date"
                                value="<?php echo htmlspecialchars($form_data['record_date'] ?? date('Y-m-d')); ?>" required>
                            <small class="text-muted">Date this customer record was created</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Occupation</label>
                            <input type="text" class="form-control" name="occupation"
                                value="<?php echo htmlspecialchars($form_data['occupation'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Account Number</label>
                            <input type="text" class="form-control" name="account_number"
                                value="<?php echo htmlspecialchars($form_data['account_number'] ?? ''); ?>">
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Current Residence -->
                    <h6 class="section-title">Current Residence</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Province</label>
                            <select class="form-select" name="province" id="province">
                                <option value="">Loading...</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">District</label>
                            <select class="form-select" name="district" id="district" disabled>
                                <option value="">Select Province first</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Sector</label>
                            <select class="form-select" name="sector" id="sector" disabled>
                                <option value="">Select District first</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Cell</label>
                            <select class="form-select" name="cell" id="cell" disabled>
                                <option value="">Select Sector first</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Street / Address</label>
                            <input type="text" class="form-control" name="address"
                                value="<?php echo htmlspecialchars($form_data['address'] ?? ''); ?>">
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Birth Place -->
                    <!--<h6 class="section-title">Birth Place</h6>-->
                    <!--<div class="row">-->
                    <!--    <div class="col-md-3 mb-3">-->
                    <!--        <label class="form-label">Province</label>-->
                    <!--        <select class="form-select" name="birth_province" id="birth_province">-->
                    <!--            <option value="">Loading...</option>-->
                    <!--        </select>-->
                    <!--    </div>-->
                    <!--    <div class="col-md-3 mb-3">-->
                    <!--        <label class="form-label">District</label>-->
                    <!--        <select class="form-select" name="birth_district" id="birth_district" disabled>-->
                    <!--            <option value="">Select Province first</option>-->
                    <!--        </select>-->
                    <!--    </div>-->
                    <!--    <div class="col-md-3 mb-3">-->
                    <!--        <label class="form-label">Sector</label>-->
                    <!--        <select class="form-select" name="birth_sector" id="birth_sector" disabled>-->
                    <!--            <option value="">Select District first</option>-->
                    <!--        </select>-->
                    <!--    </div>-->
                    <!--    <div class="col-md-3 mb-3">-->
                    <!--        <label class="form-label">Cell</label>-->
                    <!--        <select class="form-select" name="birth_cell" id="birth_cell" disabled>-->
                    <!--            <option value="">Select Sector first</option>-->
                    <!--        </select>-->
                    <!--    </div>-->
                    <!--</div>-->

                    <!--<hr class="my-3">-->

                    <!-- Family & Marital -->
                    <h6 class="section-title">Family & Marital Information</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Father's Name</label>
                            <input type="text" class="form-control" name="father_name"
                                value="<?php echo htmlspecialchars($form_data['father_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Mother's Name</label>
                            <input type="text" class="form-control" name="mother_name"
                                value="<?php echo htmlspecialchars($form_data['mother_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Marital Status</label>
                            <select class="form-select" name="marriage_type" id="marriage_type" onchange="toggleSpouseSection()">
                                <option value="Single"   <?php echo ($form_data['marriage_type'] ?? 'Single') === 'Single'   ? 'selected' : ''; ?>>Single</option>
                                <option value="Married"  <?php echo ($form_data['marriage_type'] ?? '') === 'Married'  ? 'selected' : ''; ?>>Married</option>
                                <option value="Divorced" <?php echo ($form_data['marriage_type'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                            </select>
                        </div>
                    </div>

                    <div id="spouse_section" class="<?php echo ($form_data['marriage_type'] ?? 'Single') === 'Married' ? '' : 'd-none'; ?>">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Spouse Name</label>
                                <input type="text" class="form-control" name="spouse"
                                    value="<?php echo htmlspecialchars($form_data['spouse'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Spouse ID</label>
                                <input type="text" class="form-control" name="spouse_id"
                                    value="<?php echo htmlspecialchars($form_data['spouse_id'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Spouse Occupation</label>
                                <input type="text" class="form-control" name="spouse_occupation"
                                    value="<?php echo htmlspecialchars($form_data['spouse_occupation'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Spouse Phone</label>
                                <input type="text" class="form-control" name="spouse_phone"
                                    value="<?php echo htmlspecialchars($form_data['spouse_phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Guarantor -->
                    <h6 class="section-title">Guarantor Information</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Has Guarantor?</label>
                            <select class="form-select" name="has_guarantor" id="has_guarantor" onchange="toggleGuarantorSection()">
                                <option value="No"  <?php echo ($form_data['has_guarantor'] ?? 'No') === 'No'  ? 'selected' : ''; ?>>No</option>
                                <option value="Yes" <?php echo ($form_data['has_guarantor'] ?? '') === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                            </select>
                        </div>
                    </div>

                    <div id="guarantor_section" class="<?php echo ($form_data['has_guarantor'] ?? 'No') === 'Yes' ? '' : 'd-none'; ?>">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Guarantor Name</label>
                                <input type="text" class="form-control" name="guarantor_name"
                                    value="<?php echo htmlspecialchars($form_data['guarantor_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Guarantor ID</label>
                                <input type="text" class="form-control" name="guarantor_id"
                                    value="<?php echo htmlspecialchars($form_data['guarantor_id'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Guarantor Phone</label>
                                <input type="text" class="form-control" name="guarantor_phone"
                                    value="<?php echo htmlspecialchars($form_data['guarantor_phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Guarantor Occupation</label>
                                <input type="text" class="form-control" name="guarantor_occupation"
                                    value="<?php echo htmlspecialchars($form_data['guarantor_occupation'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Loan Information -->
                    <h6 class="section-title">Loan Information</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Type of Loan</label>
                            <select class="form-select" name="loan_type">
                                <option value=""             <?php echo empty($form_data['loan_type'] ?? '')              ? 'selected' : ''; ?>>Select Loan Type</option>
                                <option value="Personal Loan"<?php echo ($form_data['loan_type'] ?? '') === 'Personal Loan'? 'selected' : ''; ?>>Personal Loan</option>
                                <option value="Business Loan"<?php echo ($form_data['loan_type'] ?? '') === 'Business Loan'? 'selected' : ''; ?>>Business Loan</option>
                                <option value="Salary Loan"  <?php echo ($form_data['loan_type'] ?? '') === 'Salary Loan'  ? 'selected' : ''; ?>>Salary Loan</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Project (if applicable)</label>
                            <input type="text" class="form-control" name="project"
                                value="<?php echo htmlspecialchars($form_data['project'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Project Location</label>
                            <input type="text" class="form-control" name="project_location"
                                value="<?php echo htmlspecialchars($form_data['project_location'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Collateral Location</label>
                            <input type="text" class="form-control" name="caution_location"
                                value="<?php echo htmlspecialchars($form_data['caution_location'] ?? ''); ?>">
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Documents -->
                    <h6 class="section-title">Supporting Documents</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">National ID</label>
                            <input type="file" class="form-control" name="doc_id" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Work Contract</label>
                            <input type="file" class="form-control" name="doc_contract" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bank Statement</label>
                            <input type="file" class="form-control" name="doc_statement" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payslip</label>
                            <input type="file" class="form-control" name="doc_payslip" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Marital Status Certificate</label>
                            <input type="file" class="form-control" name="doc_marital" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">RDB Certificate</label>
                            <input type="file" class="form-control" name="doc_rdb" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="?page=customers" class="btn btn-secondary px-4">Cancel</a>
                        <button type="submit" name="add_customer" class="btn btn-primary px-5 fw-bold">
                            <i class="bi bi-save me-2"></i>Save Customer
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
const jsonUrl = 'https://raw.githubusercontent.com/ngabovictor/Rwanda/master/data.json';
let rwandaData = null;

function populateSelect(id, options) {
    const select = document.getElementById(id);
    select.innerHTML = '<option value="">Select...</option>';
    options.forEach(opt => {
        const o = document.createElement('option');
        o.value = opt;
        o.textContent = opt;
        select.appendChild(o);
    });
    select.disabled = false;
}

function resetSelects(...ids) {
    ids.forEach(id => {
        const s = document.getElementById(id);
        s.innerHTML = '<option value="">Select...</option>';
        s.disabled = true;
    });
}

async function loadRwandaData() {
    try {
        const res = await fetch(jsonUrl);
        rwandaData = await res.json();
        const provinces = Object.keys(rwandaData);
        populateSelect('province', provinces);
        populateSelect('birth_province', provinces);
    } catch (err) {
        console.error('Failed to load Rwanda location data:', err);
    }
}

// ── Current Residence cascade ──────────────────────────────────────────────
document.getElementById('province')?.addEventListener('change', function () {
    resetSelects('district', 'sector', 'cell');
    if (this.value && rwandaData?.[this.value]) {
        populateSelect('district', Object.keys(rwandaData[this.value]));
    }
});
document.getElementById('district')?.addEventListener('change', function () {
    const prov = document.getElementById('province').value;
    resetSelects('sector', 'cell');
    if (prov && this.value && rwandaData?.[prov]?.[this.value]) {
        populateSelect('sector', Object.keys(rwandaData[prov][this.value]));
    }
});
document.getElementById('sector')?.addEventListener('change', function () {
    const prov = document.getElementById('province').value;
    const dist = document.getElementById('district').value;
    resetSelects('cell');
    if (prov && dist && this.value && rwandaData?.[prov]?.[dist]?.[this.value]) {
        populateSelect('cell', Object.keys(rwandaData[prov][dist][this.value]));
    }
});

// ── Birth Place cascade ────────────────────────────────────────────────────
document.getElementById('birth_province')?.addEventListener('change', function () {
    resetSelects('birth_district', 'birth_sector', 'birth_cell');
    if (this.value && rwandaData?.[this.value]) {
        populateSelect('birth_district', Object.keys(rwandaData[this.value]));
    }
});
document.getElementById('birth_district')?.addEventListener('change', function () {
    const prov = document.getElementById('birth_province').value;
    resetSelects('birth_sector', 'birth_cell');
    if (prov && this.value && rwandaData?.[prov]?.[this.value]) {
        populateSelect('birth_sector', Object.keys(rwandaData[prov][this.value]));
    }
});
document.getElementById('birth_sector')?.addEventListener('change', function () {
    const prov = document.getElementById('birth_province').value;
    const dist = document.getElementById('birth_district').value;
    resetSelects('birth_cell');
    if (prov && dist && this.value && rwandaData?.[prov]?.[dist]?.[this.value]) {
        populateSelect('birth_cell', Object.keys(rwandaData[prov][dist][this.value]));
    }
});

// ── Toggle sections ────────────────────────────────────────────────────────
function toggleSpouseSection() {
    const show = document.getElementById('marriage_type').value === 'Married';
    document.getElementById('spouse_section').classList.toggle('d-none', !show);
}
function toggleGuarantorSection() {
    const show = document.getElementById('has_guarantor').value === 'Yes';
    document.getElementById('guarantor_section').classList.toggle('d-none', !show);
}

// ── Init ───────────────────────────────────────────────────────────────────
loadRwandaData();
toggleSpouseSection();
toggleGuarantorSection();
</script>

<style>
.section-title {
    font-size: 13px;
    font-weight: 700;
    border-left: 4px solid #0d6efd;
    padding-left: 10px;
    margin-bottom: 1rem;
    color: #0d6efd;
}
.form-label {
    font-weight: 500;
    font-size: 11px;
    text-transform: uppercase;
    color: #555;
    margin-bottom: 2px;
}
.form-control, .form-select {
    font-size: 13px;
    border-radius: 8px;
}
</style>
