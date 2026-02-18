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
            // Show new application form
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
                <button type="submit" class="bg-primary-green hover:bg-green-600 text-white px-4 py-1.5 rounded-xl text-xs font-bold transition-all shadow-md">Check Status</button>
            </form>
        </div>

        <?php if ($success === "found" && isset($found_customer)): ?>
            <!-- CLIENT DASHBOARD VIEW -->
            <div class="bg-white rounded-[2.5rem] shadow-2xl p-10 md:p-16 text-center animate-[fadeIn_0.5s_ease-out]">
                <h2 class="text-3xl font-black text-gray-800 mb-2">Hello, <?php echo htmlspecialchars(explode(' ', $found_customer['customer_name'])[0]); ?></h2>
                <p class="text-gray-400 mb-8 font-bold">Application ID: <span class="text-primary-blue"><?php echo $found_customer['customer_code']; ?></span></p>

                <div class="max-w-xl mx-auto p-10 rounded-[2.5rem] bg-gray-50 border border-gray-100 shadow-inner">
                    <?php 
                    $stat = $found_customer['status'];
                    if ($stat == 'Pending'): ?>
                        <div class="w-20 h-20 bg-yellow-100 text-yellow-600 rounded-full flex items-center justify-center mx-auto mb-6"><i class="fas fa-clock text-3xl"></i></div>
                        <h4 class="text-2xl font-black text-yellow-600 uppercase tracking-tight">Pending Review</h4>
                        <p class="text-sm text-gray-500 mt-3 leading-relaxed">Our team is currently verifying your documentation. Please check back in 24 hours.</p>
                        
                    <?php elseif ($stat == 'Approved'): ?>
                        <div class="w-20 h-20 bg-green-100 text-primary-green rounded-full flex items-center justify-center mx-auto mb-6"><i class="fas fa-check-circle text-3xl"></i></div>
                        <h4 class="text-2xl font-black text-primary-green uppercase tracking-tight">Application Approved!</h4>
                        <div class="mt-6 p-6 bg-green-50 rounded-2xl border border-green-100 text-green-800 shadow-sm">
                            <p class="font-black flex items-center justify-center gap-2 mb-2"><i class="fas fa-bullhorn rotate-[-20deg]"></i> CONGRATULATIONS!</p>
                            <p class="text-xs leading-relaxed font-bold">Your application is approved. Visit Ikaze House, 2nd Floor or call <strong>+250 796 880 272</strong> to finalize.</p>
                        </div>

                    <?php elseif ($stat == 'Action Required'): ?>
                        <div class="w-20 h-20 bg-blue-100 text-primary-blue rounded-full flex items-center justify-center mx-auto mb-6 animate-pulse"><i class="fas fa-exclamation-triangle text-3xl"></i></div>
                        <h4 class="text-2xl font-black text-primary-blue uppercase tracking-tight">Correction Required</h4>
                        <div class="mt-3 p-4 bg-blue-50 rounded-2xl border border-blue-100 mb-8">
                            <p class="text-[10px] font-black text-primary-blue uppercase mb-1 flex items-center justify-center gap-2"><i class="fas fa-info-circle"></i> Admin Instructions:</p>
                            <p class="text-sm text-blue-900 font-bold"><?php echo htmlspecialchars($found_customer['admin_note'] ?: 'Please update the following fields.'); ?></p>
                        </div>
                        
                        <!-- Correction Form -->
                        <div class="mt-8 text-left border-t border-gray-200 pt-8">
                            <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                                <input type="hidden" name="customer_id" value="<?php echo $found_customer['customer_id']; ?>">
                                <input type="hidden" name="flagged_fields" value="<?php echo $found_customer['correction_fields']; ?>">
                                <?php 
                                $flagged = explode(',', $found_customer['correction_fields']);
                                $field_labels = [
                                    'customer_name' => 'Full Name', 'id_number' => 'National ID', 'phone' => 'Phone Number',
                                    'email' => 'Email', 'doc_id' => 'National ID Copy', 'doc_contract' => 'Work Contract',
                                    'doc_statement' => 'Bank Statement', 'doc_payslip' => 'Latest Payslip', 
                                    'doc_marital' => 'Marital Status Cert.', 'doc_rdb' => 'RDB Certificate'
                                ];
                                foreach($flagged as $f): 
                                    if(empty($f)) continue;
                                    $label = $field_labels[$f] ?? $f;
                                ?>
                                    <div class="space-y-2">
                                        <label class="text-[11px] font-black uppercase text-gray-400 pl-1 tracking-wider"><?php echo $label; ?> *</label>
                                        <?php if(strpos($f, 'doc_') === 0): ?>
                                            <input type="file" name="<?php echo $f; ?>" required class="w-full text-xs bg-white p-4 rounded-2xl border-2 border-dashed border-gray-200 focus:border-primary-blue outline-none transition-colors">
                                        <?php else: ?>
                                            <input type="text" name="<?php echo $f; ?>" required class="w-full text-sm bg-white p-4 rounded-2xl border border-gray-200 focus:ring-2 focus:ring-primary-blue outline-none transition-all" value="<?php echo htmlspecialchars($found_customer[$f]); ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <button type="submit" name="submit_correction" class="w-full bg-primary-blue hover:bg-blue-800 text-white py-4 rounded-2xl font-black text-sm shadow-xl transition-all transform hover:scale-[1.02]">
                                    <i class="fas fa-paper-plane me-2"></i> RESUBMIT FOR REVIEW
                                </button>
                            </form>
                        </div>

                    <?php else: ?>
                        <div class="w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-6"><i class="fas fa-times-circle text-3xl"></i></div>
                        <h4 class="text-2xl font-black text-red-600 uppercase tracking-tight">Application Rejected</h4>
                        <div class="mt-4 p-5 bg-red-50 rounded-2xl border border-red-100 mb-8 shadow-sm">
                            <p class="text-[10px] font-black text-red-400 uppercase mb-1">Reason for Rejection:</p>
                            <p class="text-sm text-red-700 font-bold"><?php echo htmlspecialchars($found_customer['rejection_reason'] ?: 'Requirements not met.'); ?></p>
                        </div>
                        <a href="apply.php?reapply=true" class="inline-flex items-center gap-3 bg-primary-blue hover:bg-blue-800 text-white px-10 py-4 rounded-2xl font-black text-sm shadow-2xl transition-all transform hover:scale-[1.02]">
                           <i class="fas fa-redo-alt"></i> START NEW APPLICATION
                        </a>
                    <?php endif; ?>
                </div>
                <div class="mt-12">
                    <a href="apply.php" class="text-gray-400 text-xs font-black hover:text-primary-blue flex items-center justify-center gap-2 transition-colors">
                        <i class="fas fa-sign-out-alt"></i> CLOSE DASHBOARD
                    </a>
                </div>
            </div>

        <?php elseif ($success === "updated"): ?>
            <div class="bg-white rounded-[3rem] shadow-2xl p-16 text-center animate-[fadeIn_0.5s_ease-out]">
                <div class="w-24 h-24 bg-blue-100 text-primary-blue rounded-full flex items-center justify-center mx-auto mb-8"><i class="fas fa-check text-4xl"></i></div>
                <h3 class="text-3xl font-black text-gray-800 mb-3">Correction Sent!</h3>
                <p class="text-gray-500 text-sm max-w-sm mx-auto leading-relaxed">Your updates have been received. Our team will re-prioritize your application immediately.</p>
                <div class="mt-10 flex flex-col gap-4">
                    <a href="index.php" class="bg-primary-blue text-white px-12 py-4 rounded-2xl font-black shadow-xl hover:bg-blue-800 transition-all">Back to Home</a>
                    <a href="apply.php" class="text-gray-400 font-bold text-xs">View another status</a>
                </div>
            </div>

        <?php elseif ($success === "created"): ?>
            <div class="bg-white rounded-[3rem] shadow-2xl p-16 text-center animate-[fadeIn_0.5s_ease-out]">
                <div class="w-24 h-24 bg-green-100 text-primary-green rounded-full flex items-center justify-center mx-auto mb-8"><i class="fas fa-check text-4xl"></i></div>
                <h3 class="text-3xl font-black text-gray-800 mb-3">Application Received!</h3>
                <p class="text-gray-500 text-sm max-w-sm mx-auto leading-relaxed">Your application has been submitted successfully. Track your progress below.</p>
                <div class="mt-10">
                    <a href="apply.php?track_email=<?php echo urlencode($email); ?>" class="inline-block bg-primary-green text-white px-12 py-4 rounded-2xl font-black shadow-xl hover:bg-green-600 transition-all">TRACK MY LOAN</a>
                </div>
            </div>

        <?php else: ?>
            <!-- MAIN APPLICATION FORM -->
            <div id="form-container" class="bg-white rounded-[3rem] shadow-2xl p-10 md:p-14 relative overflow-hidden animate-[fadeIn_0.5s_ease-out]">
                <div class="text-center mb-12">
                    <h1 class="text-4xl font-black text-primary-blue mb-2 tracking-tighter">Loan Application</h1>
                    <p class="text-gray-400 text-sm font-bold">Join Capital Bridge Finance & Empower your future.</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 text-red-600 p-5 rounded-2xl mb-8 text-xs text-center border border-red-100 font-bold flex items-center justify-center gap-3">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form id="loanForm" method="POST" enctype="multipart/form-data" class="space-y-8">
                    <input type="hidden" name="submit_application" value="1">
                    
                    <!-- STEP 1: LOAN TYPE & BASIC INFO -->
                    <div id="step-1" class="form-step space-y-8">
                        <div class="bg-blue-50/50 p-8 rounded-[2.5rem] border border-blue-50">
                            <label class="block text-[11px] font-black text-primary-blue mb-6 text-center tracking-widest uppercase">CHOOSE YOUR LOAN CATEGORY</label>
                            <div class="grid grid-cols-2 gap-6">
                                <label class="cursor-pointer group">
                                    <input type="radio" name="loan_type" value="Salary" required class="hidden peer" onchange="updateDocFields()">
                                    <div class="bg-white border-2 border-gray-100 peer-checked:border-primary-green p-6 rounded-3xl text-center transition-all shadow-sm group-hover:shadow-md">
                                        <div class="w-12 h-12 bg-green-50 text-primary-green rounded-2xl flex items-center justify-center mx-auto mb-3 transition-colors peer-checked:bg-primary-green peer-checked:text-white">
                                            <i class="fas fa-id-card text-xl"></i>
                                        </div>
                                        <span class="block text-xs font-black uppercase text-gray-700 tracking-tight">Salary Loan</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer group">
                                    <input type="radio" name="loan_type" value="Business" required class="hidden peer" onchange="updateDocFields()">
                                    <div class="bg-white border-2 border-gray-100 peer-checked:border-primary-blue p-6 rounded-3xl text-center transition-all shadow-sm group-hover:shadow-md">
                                        <div class="w-12 h-12 bg-blue-50 text-primary-blue rounded-2xl flex items-center justify-center mx-auto mb-3 transition-colors peer-checked:bg-primary-blue peer-checked:text-white">
                                            <i class="fas fa-store text-xl"></i>
                                        </div>
                                        <span class="block text-xs font-black uppercase text-gray-700 tracking-tight">Business Loan</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4">Full Name</label>
                                <input type="text" name="customer_name" required placeholder="John Doe" class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:bg-white focus:ring-2 focus:ring-primary-blue transition-all">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4">Email Address</label>
                                <input type="email" name="email" required placeholder="example@mail.com" class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:bg-white focus:ring-2 focus:ring-primary-blue transition-all">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4">Phone Number</label>
                                <input type="tel" name="phone" required placeholder="+250 7..." class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:bg-white focus:ring-2 focus:ring-primary-blue transition-all">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4">National ID</label>
                                <input type="text" name="nationalId" required placeholder="16-digit number" class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:bg-white focus:ring-2 focus:ring-primary-blue transition-all">
                            </div>
                        </div>

                        <button type="button" onclick="nextPrev(1)" class="w-full bg-primary-blue text-white py-5 rounded-2xl font-black shadow-2xl hover:bg-blue-800 transition-all flex items-center justify-center gap-3 group">
                            CONTINUE TO PERSONAL DETAILS <i class="fas fa-arrow-right text-xs transition-transform group-hover:translate-x-1"></i>
                        </button>
                    </div>

                    <!-- STEP 2: FAMILY & MARITAL -->
                    <div id="step-2" class="form-step space-y-8 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-1"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Birth Place</label><input type="text" name="birth_place" required class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none focus:bg-white"></div>
                            <div class="space-y-1"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Gender</label><select name="gender" class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none focus:bg-white"><option value="male">Male</option><option value="female">Female</option></select></div>
                            <div class="space-y-1"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Date of Birth</label><input type="date" name="dob" required class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none focus:bg-white"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Father's Name</label><input type="text" name="father_name" required class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none focus:bg-white"></div>
                            <div class="space-y-1"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Mother's Name</label><input type="text" name="mother_name" required class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none focus:bg-white"></div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase text-gray-400 pl-4">Marriage Status</label>
                            <select name="marriage_type" id="marriage_type" onchange="toggleSpouseFields()" class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none focus:bg-white">
                                <option value="Single">Single</option><option value="Ivanga mutungo">Ivanga mutungo</option><option value="Ivangura mutungo">Ivangura mutungo</option><option value="Muhahano">Muhahano</option>
                            </select>
                        </div>
                        <div id="spouse_fields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Spouse Name</label><input type="text" name="spouse" class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none"></div>
                            <div class="space-y-1"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Spouse Phone</label><input type="tel" name="spouse_phone" class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none"></div>
                        </div>
                        <div class="flex gap-4">
                            <button type="button" onclick="nextPrev(-1)" class="w-1/3 bg-gray-100 p-5 rounded-2xl font-black text-gray-400 transition-colors hover:bg-gray-200">BACK</button>
                            <button type="button" onclick="nextPrev(1)" class="w-2/3 bg-primary-blue text-white p-5 rounded-2xl font-black shadow-xl hover:bg-blue-800 transition-all">NEXT: PROFESSIONAL INFO</button>
                        </div>
                    </div>

                    <!-- STEP 3: OCCUPATION & PROJECT -->
                    <div id="step-3" class="form-step space-y-8 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Current Occupation</label><input type="text" name="occupation" required class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none"></div>
                            <div class="space-y-1"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Bank Account Number</label><input type="text" name="account_number" required class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none"></div>
                        </div>
                        <div class="space-y-1"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Residential Address</label><input type="text" name="address" required placeholder="District, Sector, Cell, Village" class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none"></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Project Name</label><input type="text" name="project" required class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none"></div>
                            <div class="space-y-1"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Primary Location</label><input type="text" name="location" required class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none"></div>
                            <div class="space-y-1"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Project Location</label><input type="text" name="project_location" required class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none focus:bg-white"></div>
                            <div class="space-y-1"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Caution Location</label><input type="text" name="caution_location" required class="w-full p-4 bg-gray-50 rounded-2xl border text-sm outline-none focus:bg-white"></div>
                        </div>
                        <div class="flex gap-4">
                            <button type="button" onclick="nextPrev(-1)" class="w-1/3 bg-gray-100 p-5 rounded-2xl font-black text-gray-400">BACK</button>
                            <button type="button" onclick="nextPrev(1)" class="w-2/3 bg-primary-blue text-white p-5 rounded-2xl font-black shadow-xl">NEXT: UPLOAD DOCUMENTS</button>
                        </div>
                    </div>

                    <!-- STEP 4: DOCUMENT UPLOADS -->
                    <div id="step-4" class="form-step space-y-8 hidden">
                        <div class="bg-primary-blue text-white p-8 rounded-[2.5rem] mb-6 shadow-lg">
                            <h4 class="text-xs font-black uppercase flex items-center gap-3"><i class="fas fa-file-upload text-xl"></i> Secure Document Upload</h4>
                            <p class="text-[10px] text-blue-200 mt-2 font-bold uppercase tracking-widest">Only PDF, JPG, and PNG files are accepted.</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2"><label class="text-[11px] font-black text-gray-400 pl-4 uppercase tracking-tighter">National ID *</label><input type="file" name="doc_id" required accept=".pdf,.jpg,.jpeg,.png" class="w-full text-[11px] bg-gray-50 p-4 rounded-3xl border-2 border-dashed border-gray-100 hover:border-primary-blue transition-colors"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black text-gray-400 pl-4 uppercase tracking-tighter">Marital Status Cert *</label><input type="file" name="doc_marital" required accept=".pdf,.jpg,.jpeg,.png" class="w-full text-[11px] bg-gray-50 p-4 rounded-3xl border-2 border-dashed border-gray-100 hover:border-primary-blue transition-colors"></div>
                            
                            <!-- DIVS FOR LOAN TYPE -->
                            <div class="space-y-2 doc-salary"><label class="text-[11px] font-black text-gray-400 pl-4 uppercase tracking-tighter">Work Contract *</label><input type="file" name="doc_contract" accept=".pdf,.jpg,.jpeg,.png" class="salary-req w-full text-[11px] bg-gray-100 p-4 rounded-3xl border-2 border-dashed"></div>
                            <div class="space-y-2 doc-salary"><label class="text-[11px] font-black text-gray-400 pl-4 uppercase tracking-tighter">Bank Statement *</label><input type="file" name="doc_statement" accept=".pdf,.jpg,.jpeg,.png" class="salary-req w-full text-[11px] bg-gray-100 p-4 rounded-3xl border-2 border-dashed"></div>
                            <div class="space-y-2 doc-salary"><label class="text-[11px] font-black text-gray-400 pl-4 uppercase tracking-tighter">Latest Payslip *</label><input type="file" name="doc_payslip" accept=".pdf,.jpg,.jpeg,.png" class="salary-req w-full text-[11px] bg-gray-100 p-4 rounded-3xl border-2 border-dashed"></div>
                            
                            <div class="space-y-2 doc-business hidden"><label class="text-[11px] font-black text-gray-400 pl-4 uppercase tracking-tighter">RDB Certificate *</label><input type="file" name="doc_rdb" accept=".pdf,.jpg,.jpeg,.png" class="business-req w-full text-[11px] bg-gray-100 p-4 rounded-3xl border-2 border-dashed"></div>
                            <div class="space-y-2 doc-business hidden"><label class="text-[11px] font-black text-gray-400 pl-4 uppercase tracking-tighter">Proof of Business Income *</label><input type="file" name="doc_statement_alt" accept=".pdf,.jpg,.jpeg,.png" class="business-req w-full text-[11px] bg-gray-100 p-4 rounded-3xl border-2 border-dashed"></div>
                        </div>
                        <div class="flex gap-4 mt-12 pb-6">
                            <button type="button" onclick="nextPrev(-1)" class="w-1/3 bg-gray-100 p-6 rounded-3xl font-black text-gray-400">BACK</button>
                            <button type="submit" class="w-2/3 bg-primary-green text-white p-6 rounded-3xl font-black shadow-2xl transition-all hover:scale-[1.02] active:scale-95">SUBMIT FINAL APPLICATION</button>
                        </div>
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
        if (!input.value) { 
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
            input.classList.add("border-red-500", "ring-2", "ring-red-100"); 
            return false; 
        }
        input.classList.remove("border-red-500", "ring-2", "ring-red-100");
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
    const salaryReqs = document.querySelectorAll('.salary-req');
    const businessReqs = document.querySelectorAll('.business-req');

    if (type === 'Salary') {
        salaryDocs.forEach(d => d.classList.remove('hidden'));
        businessDocs.forEach(d => d.classList.add('hidden'));
        salaryReqs.forEach(r => r.required = true);
        businessReqs.forEach(r => r.required = false);
    } else {
        salaryDocs.forEach(d => d.classList.add('hidden'));
        businessDocs.forEach(d => d.classList.remove('hidden'));
        salaryReqs.forEach(r => r.required = false);
        businessReqs.forEach(r => r.required = true);
    }
}
</script>

<style>
@keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
.gradient-blue { background: linear-gradient(135deg, #0f172a 0%, #1e40af 100%); }
.animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
</style>

<?php 
include 'includes/bottom_nav.php'; 
include 'includes/footer.php'; 
?>
