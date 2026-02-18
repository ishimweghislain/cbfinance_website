<?php 
require_once 'includes/db_connect.php';
include 'includes/head.php'; 
include 'includes/navbar.php'; 

$success = false;
$error = "";
$already_applied = false;

// Handle Correction Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_correction'])) {
    $conn = getWebsiteConnection();
    if ($conn) {
        $cid = intval($_POST['customer_id']);
        $flagged = explode(',', $_POST['flagged_fields'] ?? '');
        $update_parts = [];
        $upload_dir = "uploads/documents/";

        foreach ($flagged as $field) {
            if (empty($field)) continue;
            
            if (strpos($field, 'doc_') === 0) {
                if (isset($_FILES[$field]) && $_FILES[$field]['error'] == 0) {
                    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                    $filename = $field . "_" . time() . "_" . mt_rand(100, 999) . "." . $ext;
                    move_uploaded_file($_FILES[$field]['tmp_name'], $upload_dir . $filename);
                    $path = $upload_dir . $filename;
                    $update_parts[] = "$field = '$path'";
                }
            } else {
                if (isset($_POST[$field])) {
                    $val = $conn->real_escape_string($_POST[$field]);
                    $update_parts[] = "$field = '$val'";
                }
            }
        }

        if (!empty($update_parts)) {
            $sql = "UPDATE customers SET " . implode(', ', $update_parts) . ", status = 'Pending', client_resubmitted = 1 WHERE customer_id = $cid";
            if ($conn->query($sql)) {
                $success = "updated";
            } else {
                $error = "Update failed: " . $conn->error;
            }
        } else {
            $error = "No changes were submitted.";
        }
    }
}

// Handle Status Check / Login
if (isset($_GET['track_email']) || isset($_GET['reapply'])) {
    $conn = getWebsiteConnection();
    if ($conn) {
        $track_email = $conn->real_escape_string($_GET['track_email'] ?? '');
        
        if (isset($_GET['reapply'])) {
            // Show form
        } elseif ($track_email) {
            $res = $conn->query("SELECT * FROM customers WHERE email = '$track_email' LIMIT 1");
            if ($res && $res->num_rows > 0) {
                $found_customer = $res->fetch_assoc();
                $success = "found"; 
            } else {
                $error = "No application found with this email.";
            }
        }
    }
}

// Handle New Application
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_application'])) {
    $conn = getWebsiteConnection();
    if ($conn) {
        $email = $conn->real_escape_string($_POST['email']);
        
        $check = $conn->query("SELECT customer_id, status FROM customers WHERE email = '$email'");
        if ($check && $check->num_rows > 0) {
            $existing = $check->fetch_assoc();
            if ($existing['status'] !== 'Rejected') {
                $already_applied = true;
                $error = "You have already applied. Please enter your email above to track your status.";
            } else {
                $conn->query("DELETE FROM customers WHERE customer_id = " . $existing['customer_id']);
            }
        }
        
        if (!$already_applied) {
            $customer_name = $conn->real_escape_string($_POST['customer_name']);
            $phone = $conn->real_escape_string($_POST['phone']);
            $id_number = $conn->real_escape_string($_POST['nationalId']);
            $loan_type = $conn->real_escape_string($_POST['loan_type']);
            $dob = $conn->real_escape_string($_POST['dob']);
            $gender = ucfirst($conn->real_escape_string($_POST['gender']));
            $birth_place = $conn->real_escape_string($_POST['birth_place']);
            $account_number = $conn->real_escape_string($_POST['account_number']);
            $occupation = $conn->real_escape_string($_POST['occupation']);
            $father_name = $conn->real_escape_string($_POST['father_name']);
            $mother_name = $conn->real_escape_string($_POST['mother_name']);
            $marriage_type = $conn->real_escape_string($_POST['marriage_type']);
            $spouse = $conn->real_escape_string($_POST['spouse'] ?? '');
            $spouse_occupation = $conn->real_escape_string($_POST['spouse_occupation'] ?? '');
            $spouse_phone = $conn->real_escape_string($_POST['spouse_phone'] ?? '');
            $address = $conn->real_escape_string($_POST['address']);
            $location = $conn->real_escape_string($_POST['location']);
            $project = $conn->real_escape_string($_POST['project']);
            $project_location = $conn->real_escape_string($_POST['project_location']);
            $caution_location = $conn->real_escape_string($_POST['caution_location']);
            
            $upload_dir = "uploads/documents/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $doc_paths = [];
            $required_docs = ($loan_type == 'Salary') ? 
                ['doc_id', 'doc_contract', 'doc_statement', 'doc_payslip', 'doc_marital'] : 
                ['doc_id', 'doc_rdb', 'doc_statement_alt', 'doc_marital'];

            $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
            foreach ($required_docs as $doc_field) {
                if (isset($_FILES[$doc_field]) && $_FILES[$doc_field]['error'] == 0) {
                    $ext = strtolower(pathinfo($_FILES[$doc_field]['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed_exts)) { $error = "Invalid file type."; $already_applied = true; break; }
                    $filename = $doc_field . "_" . time() . "_" . mt_rand(100, 999) . "." . $ext;
                    move_uploaded_file($_FILES[$doc_field]['tmp_name'], $upload_dir . $filename);
                    $doc_paths[$doc_field] = $upload_dir . $filename;
                }
            }

            if (isset($doc_paths['doc_statement_alt'])) $doc_paths['doc_statement'] = $doc_paths['doc_statement_alt'];

            $all_docs = ['doc_id', 'doc_contract', 'doc_statement', 'doc_payslip', 'doc_marital', 'doc_rdb'];
            foreach ($all_docs as $d) if (!isset($doc_paths[$d])) $doc_paths[$d] = null;

            $customer_code = "PEND-" . mt_rand(100000, 999999);
            $sql = "INSERT INTO customers (
                customer_code, customer_name, email, phone, id_number, loan_type, 
                doc_id, doc_contract, doc_statement, doc_payslip, doc_marital, doc_rdb,
                birth_place, account_number, occupation, gender, date_of_birth,
                father_name, mother_name, spouse, spouse_occupation, spouse_phone, marriage_type,
                address, location, project, project_location, caution_location,
                is_active, status, organization
            ) VALUES (
                '$customer_code', '$customer_name', '$email', '$phone', '$id_number', '$loan_type',
                '{$doc_paths['doc_id']}', '{$doc_paths['doc_contract']}', '{$doc_paths['doc_statement']}', '{$doc_paths['doc_payslip']}', '{$doc_paths['doc_marital']}', '{$doc_paths['doc_rdb']}',
                '$birth_place', '$account_number', '$occupation', '$gender', '$dob',
                '$father_name', '$mother_name', '$spouse', '$spouse_occupation', '$spouse_phone', '$marriage_type',
                '$address', '$location', '$project', '$project_location', '$caution_location',
                0, 'Pending', 'Capital Bridge Finance'
            )";

            if ($conn->query($sql)) $success = "created";
            else $error = "Error: " . $conn->error;
        }
    }
}
?>

<main class="min-h-screen py-12 px-4 sm:px-6 lg:px-8 gradient-blue font-sans">
    <div class="max-w-4xl mx-auto">
        <!-- Tracking Bar -->
        <div class="mb-8 flex justify-end">
            <form action="" method="GET" class="flex items-center gap-2 bg-white/10 p-2 rounded-2xl backdrop-blur-md border border-white/20">
                <input type="email" name="track_email" placeholder="Track application email" required class="bg-white rounded-xl px-3 py-1.5 text-xs text-gray-800 focus:ring-2 focus:ring-primary-green outline-none min-w-[200px]">
                <button type="submit" class="bg-primary-green hover:bg-green-600 text-white px-4 py-1.5 rounded-xl text-xs font-bold transition-all">Check Status</button>
            </form>
        </div>

        <?php if ($success === "found" && isset($found_customer)): ?>
            <div class="bg-white rounded-[2.5rem] shadow-2xl p-10 md:p-16 text-center">
                <h2 class="text-3xl font-black text-gray-800 mb-2">Hello, <?php echo htmlspecialchars(explode(' ', $found_customer['customer_name'])[0]); ?></h2>
                <p class="text-gray-400 mb-8">Application Status ID: <span class="text-primary-blue font-bold"><?php echo $found_customer['customer_code']; ?></span></p>

                <div class="max-w-md mx-auto p-8 rounded-[2rem] bg-gray-50 border border-gray-100">
                    <?php 
                    $stat = $found_customer['status'];
                    if ($stat == 'Pending'): ?>
                        <div class="w-16 h-16 bg-yellow-100 text-yellow-600 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-clock text-2xl"></i></div>
                        <h4 class="text-xl font-bold text-yellow-600">PENDING REVIEW</h4>
                        <p class="text-xs text-gray-400 mt-2">Our team is verifying your data. Please check back in 24 hours.</p>
                    <?php elseif ($stat == 'Approved'): ?>
                        <div class="w-16 h-16 bg-green-100 text-primary-green rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-check-circle text-2xl"></i></div>
                        <h4 class="text-xl font-bold text-primary-green">APPROVED!</h4>
                        <p class="text-xs text-gray-600 mt-4 leading-relaxed">Visit Ikaze House, 2nd Floor to finalize your loan.</p>
                    <?php elseif ($stat == 'Action Required'): ?>
                        <div class="w-16 h-16 bg-blue-100 text-primary-blue rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-exclamation-triangle text-2xl"></i></div>
                        <h4 class="text-xl font-bold text-primary-blue">ACTION REQUIRED</h4>
                        <p class="text-xs text-gray-500 mt-2 font-bold"><?php echo htmlspecialchars($found_customer['admin_note'] ?: 'Some documents need correction.'); ?></p>
                        
                        <!-- Correction Form -->
                        <div class="mt-8 text-left border-t pt-6">
                            <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                                <input type="hidden" name="customer_id" value="<?php echo $found_customer['customer_id']; ?>">
                                <input type="hidden" name="flagged_fields" value="<?php echo $found_customer['correction_fields']; ?>">
                                <?php 
                                $flagged = explode(',', $found_customer['correction_fields']);
                                $field_labels = [
                                    'customer_name' => 'Full Name', 'id_number' => 'National ID', 'phone' => 'Phone Number',
                                    'email' => 'Email', 'doc_id' => 'ID Copy', 'doc_contract' => 'Work Contract',
                                    'doc_statement' => 'Bank Statement', 'doc_payslip' => 'Payslip', 
                                    'doc_marital' => 'Marital Cert.', 'doc_rdb' => 'RDB Cert.'
                                ];
                                foreach($flagged as $f): 
                                    if(empty($f)) continue;
                                    $label = $field_labels[$f] ?? $f;
                                ?>
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-black uppercase text-gray-400 pl-1"><?php echo $label; ?></label>
                                        <?php if(strpos($f, 'doc_') === 0): ?>
                                            <input type="file" name="<?php echo $f; ?>" required class="w-full text-xs bg-white p-2 rounded-xl border border-dashed">
                                        <?php else: ?>
                                            <input type="text" name="<?php echo $f; ?>" required class="w-full text-xs bg-white p-3 rounded-xl border" value="<?php echo htmlspecialchars($found_customer[$f]); ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <button type="submit" name="submit_correction" class="w-full bg-primary-blue text-white py-3 rounded-xl font-bold text-xs shadow-lg hover:bg-blue-800">RESUBMIT UPDATES</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-times-circle text-2xl"></i></div>
                        <h4 class="text-xl font-bold text-red-600">APPLICATION REJECTED</h4>
                        <div class="mt-4 p-4 bg-red-50 rounded-2xl border border-red-100 mb-6">
                            <p class="text-[10px] font-black text-red-400 uppercase mb-1">Reason for Rejection:</p>
                            <p class="text-xs text-red-700 font-bold"><?php echo htmlspecialchars($found_customer['rejection_reason'] ?: 'Requirements not met.'); ?></p>
                        </div>
                        <a href="apply.php?reapply=true" class="inline-block bg-primary-blue text-white px-8 py-3 rounded-xl font-bold text-xs shadow-xl">START NEW APPLICATION</a>
                    <?php endif; ?>
                </div>
                <div class="mt-10"><a href="apply.php" class="text-gray-400 text-xs font-bold hover:text-primary-blue"><i class="fas fa-sign-out-alt"></i> Logout View</a></div>
            </div>

        <?php elseif ($success === "updated"): ?>
            <div class="bg-white rounded-[2.5rem] shadow-2xl p-16 text-center">
                <div class="w-20 h-20 bg-blue-100 text-primary-blue rounded-full flex items-center justify-center mx-auto mb-6"><i class="fas fa-check text-3xl"></i></div>
                <h3 class="text-2xl font-black mb-2">Correction Sent!</h3>
                <p class="text-gray-400 text-sm">Your updates have been received. We will review them shortly.</p>
                <div class="mt-8"><a href="index.php" class="bg-primary-blue text-white px-10 py-3 rounded-xl font-bold">Back to Home</a></div>
            </div>

        <?php elseif ($success === "created"): ?>
            <div class="bg-white rounded-[2.5rem] shadow-2xl p-16 text-center">
                <div class="w-20 h-20 bg-green-100 text-primary-green rounded-full flex items-center justify-center mx-auto mb-6"><i class="fas fa-check text-3xl"></i></div>
                <h3 class="text-2xl font-black mb-2">Application Received!</h3>
                <p class="text-gray-400 text-sm">Track your progress using your email on this page.</p>
                <div class="mt-8"><a href="apply.php?track_email=<?php echo $email; ?>" class="bg-primary-green text-white px-10 py-3 rounded-xl font-bold">Track Status</a></div>
            </div>

        <?php else: ?>
            <div class="bg-white rounded-[2.5rem] shadow-2xl p-8 md:p-12 relative overflow-hidden">
                <div class="text-center mb-10"><h1 class="text-3xl font-black text-primary-blue">Loan Application</h1></div>
                <?php if ($error): ?><div class="bg-red-50 text-red-600 p-4 rounded-2xl mb-6 text-xs text-center border border-red-100 font-bold"><?php echo $error; ?></div><?php endif; ?>
                
                <form id="loanForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="submit_application" value="1">
                    <!-- Steps logic same as before but minimalized for implementation -->
                    <div id="step-1" class="form-step space-y-6">
                        <div class="bg-blue-50 p-6 rounded-[2rem] mb-8">
                            <label class="block text-xs font-black text-primary-blue mb-4 text-center">CHOOSE LOAN TYPE</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="cursor-pointer">
                                    <input type="radio" name="loan_type" value="Salary" required class="hidden peer" onchange="updateDocFields()">
                                    <div class="bg-white border-2 border-transparent peer-checked:border-primary-green p-4 rounded-2xl text-center"><span class="text-xs font-black">SALARY</span></div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="loan_type" value="Business" required class="hidden peer" onchange="updateDocFields()">
                                    <div class="bg-white border-2 border-transparent peer-checked:border-primary-blue p-4 rounded-2xl text-center"><span class="text-xs font-black">BUSINESS</span></div>
                                </label>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="text" name="customer_name" required placeholder="Full Name" class="p-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:bg-white focus:ring-2 focus:ring-primary-blue">
                            <input type="email" name="email" required placeholder="Email Address" class="p-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:bg-white focus:ring-2 focus:ring-primary-blue">
                            <input type="tel" name="phone" required placeholder="Phone Number" class="p-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:bg-white focus:ring-2 focus:ring-primary-blue">
                            <input type="text" name="nationalId" required placeholder="National ID" class="p-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:bg-white focus:ring-2 focus:ring-primary-blue">
                        </div>
                        <button type="button" onclick="nextPrev(1)" class="w-full bg-primary-blue text-white py-4 rounded-2xl font-black shadow-xl">CONTINUE</button>
                    </div>

                    <div id="step-2" class="form-step space-y-6 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <input type="text" name="birth_place" required placeholder="Birth Place" class="p-3 bg-gray-50 rounded-xl text-xs border">
                            <select name="gender" class="p-3 bg-gray-50 rounded-xl text-xs border"><option value="male">Male</option><option value="female">Female</option></select>
                            <input type="date" name="dob" required class="p-3 bg-gray-50 rounded-xl text-xs border">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="text" name="father_name" required placeholder="Father's Name" class="p-3 bg-gray-50 rounded-xl text-xs border">
                            <input type="text" name="mother_name" required placeholder="Mother's Name" class="p-3 bg-gray-50 rounded-xl text-xs border">
                        </div>
                        <select name="marriage_type" id="marriage_type" onchange="toggleSpouseFields()" class="w-full p-3 bg-gray-50 rounded-xl text-xs border">
                            <option value="Single">Single</option><option value="Ivanga mutungo">Ivanga mutungo</option><option value="Ivangura mutungo">Ivangura mutungo</option><option value="Muhahano">Muhahano</option>
                        </select>
                        <div id="spouse_fields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="text" name="spouse" placeholder="Spouse Name" class="p-3 bg-gray-50 rounded-xl text-xs border">
                            <input type="tel" name="spouse_phone" placeholder="Spouse Phone" class="p-3 bg-gray-50 rounded-xl text-xs border">
                        </div>
                        <div class="flex gap-4"><button type="button" onclick="nextPrev(-1)" class="w-1/3 bg-gray-100 p-4 rounded-2xl font-bold">BACK</button><button type="button" onclick="nextPrev(1)" class="w-2/3 bg-primary-blue text-white p-4 rounded-2xl font-bold">NEXT</button></div>
                    </div>

                    <div id="step-3" class="form-step space-y-6 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="text" name="occupation" required placeholder="Occupation" class="p-3 bg-gray-50 rounded-xl text-xs border">
                            <input type="text" name="account_number" required placeholder="Bank Account Number" class="p-3 bg-gray-50 rounded-xl text-xs border">
                        </div>
                        <input type="text" name="address" required placeholder="Home Address" class="p-3 bg-gray-50 rounded-xl text-xs border w-full">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="text" name="project" required placeholder="Project Name" class="p-3 bg-gray-50 rounded-xl text-xs border">
                            <input type="text" name="location" required placeholder="Location" class="p-3 bg-gray-50 rounded-xl text-xs border">
                            <input type="text" name="project_location" required placeholder="Project Location" class="p-3 bg-gray-50 rounded-xl text-xs border">
                            <input type="text" name="caution_location" required placeholder="Caution Location" class="p-3 bg-gray-50 rounded-xl text-xs border">
                        </div>
                        <div class="flex gap-4"><button type="button" onclick="nextPrev(-1)" class="w-1/3 bg-gray-100 p-4 rounded-2xl font-bold">BACK</button><button type="button" onclick="nextPrev(1)" class="w-2/3 bg-primary-blue text-white p-4 rounded-2xl font-bold">NEXT</button></div>
                    </div>

                    <div id="step-4" class="form-step space-y-6 hidden">
                        <div class="bg-primary-blue text-white p-6 rounded-3xl mb-4 text-center"><h4 class="text-xs font-black">UPLOAD DOCUMENTS</h4></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1"><label class="text-[9px] font-bold text-gray-400 pl-1 uppercase">National ID *</label><input type="file" name="doc_id" required accept=".pdf,.jpg,.jpeg,.png" class="w-full text-[10px] bg-gray-50 p-2 rounded-xl border border-dashed"></div>
                            <div class="space-y-1"><label class="text-[9px] font-bold text-gray-400 pl-1 uppercase">Marital Cert *</label><input type="file" name="doc_marital" required accept=".pdf,.jpg,.jpeg,.png" class="w-full text-[10px] bg-gray-50 p-2 rounded-xl border border-dashed"></div>
                            <div class="space-y-1 doc-salary"><label class="text-[9px] font-bold text-gray-400 pl-1 uppercase">Work Contract *</label><input type="file" name="doc_contract" accept=".pdf,.jpg,.jpeg,.png" class="salary-req w-full text-[10px] bg-gray-50 p-2 rounded-xl border border-dashed"></div>
                            <div class="space-y-1 doc-salary"><label class="text-[9px] font-bold text-gray-400 pl-1 uppercase">Bank Statement *</label><input type="file" name="doc_statement" accept=".pdf,.jpg,.jpeg,.png" class="salary-req w-full text-[10px] bg-gray-50 p-2 rounded-xl border border-dashed"></div>
                            <div class="space-y-1 doc-salary"><label class="text-[9px] font-bold text-gray-400 pl-1 uppercase">Latest Payslip *</label><input type="file" name="doc_payslip" accept=".pdf,.jpg,.jpeg,.png" class="salary-req w-full text-[10px] bg-gray-50 p-2 rounded-xl border border-dashed"></div>
                            <div class="space-y-1 doc-business hidden"><label class="text-[9px] font-bold text-gray-400 pl-1 uppercase">RDB Certificate *</label><input type="file" name="doc_rdb" accept=".pdf,.jpg,.jpeg,.png" class="business-req w-full text-[10px] bg-gray-50 p-2 rounded-xl border border-dashed"></div>
                            <div class="space-y-1 doc-business hidden"><label class="text-[9px] font-bold text-gray-400 pl-1 uppercase">Bank/Momo Statement *</label><input type="file" name="doc_statement_alt" accept=".pdf,.jpg,.jpeg,.png" class="business-req w-full text-[10px] bg-gray-50 p-2 rounded-xl border border-dashed"></div>
                        </div>
                        <div class="flex gap-4"><button type="button" onclick="nextPrev(-1)" class="w-1/3 bg-gray-100 p-4 rounded-2xl font-bold">BACK</button><button type="submit" class="w-2/3 bg-primary-green text-white p-4 rounded-2xl font-black shadow-xl">SUBMIT NOW</button></div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
let currentStep = 1;
function nextPrev(n) {
    const steps = document.querySelectorAll(".form-step");
    if (n === 1 && !validateStep()) return;
    steps[currentStep-1].classList.add("hidden");
    currentStep += n;
    steps[currentStep-1].classList.remove("hidden");
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function validateStep() {
    const activeStep = document.getElementById("step-" + currentStep);
    const inputs = activeStep.querySelectorAll("input[required], select[required]");
    for (let input of inputs) {
        if (!input.value) { input.classList.add("border-red-500"); return false; }
        input.classList.remove("border-red-500");
    }
    return true;
}

function toggleSpouseFields() {
    const val = document.getElementById('marriage_type').value;
    const fields = document.getElementById('spouse_fields');
    if (val === 'Single') fields.classList.add('hidden');
    else fields.classList.remove('hidden');
}

function updateDocFields() {
    const type = document.querySelector('input[name="loan_type"]:checked').value;
    const salaryDocs = document.querySelectorAll('.doc-salary');
    const businessDocs = document.querySelectorAll('.doc-business');
    if (type === 'Salary') {
        salaryDocs.forEach(d => d.classList.remove('hidden'));
        businessDocs.forEach(d => d.classList.add('hidden'));
    } else {
        salaryDocs.forEach(d => d.classList.add('hidden'));
        businessDocs.forEach(d => d.classList.remove('hidden'));
    }
}
</script>

<style>
.gradient-blue { background: linear-gradient(135deg, #0f172a 0%, #1e40af 100%); }
.animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
</style>

<?php 
include 'includes/bottom_nav.php'; 
include 'includes/footer.php'; 
?>
