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
        $resubmitted_track = [];
        $upload_dir = "uploads/documents/";

        foreach ($flagged as $field) {
            if (empty($field)) continue;
            
            if (strpos($field, 'doc_') === 0) {
                if (isset($_FILES[$field]) && $_FILES[$field]['error'] == 0) {
                    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                    $filename = $field . "_" . time() . "_" . mt_rand(1000, 9999) . "." . $ext;
                    move_uploaded_file($_FILES[$field]['tmp_name'], $upload_dir . $filename);
                    $path = $upload_dir . $filename;
                    $update_parts[] = "$field = '$path'";
                    $resubmitted_track[] = $field;
                }
            } else {
                if (isset($_POST[$field])) {
                    $val = $conn->real_escape_string($_POST[$field]);
                    $update_parts[] = "$field = '$val'";
                    $resubmitted_track[] = $field;
                }
            }
        }

        if (!empty($update_parts)) {
            $resub_json = implode(',', $resubmitted_track);
            // After client resubmits, it goes back to 'Pending' for admin review
            $sql = "UPDATE customers SET " . implode(', ', $update_parts) . ", status = 'Pending', client_resubmitted = 1, resubmitted_fields = '$resub_json' WHERE customer_id = $cid";
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
            if($track_email) {
                // Delete previous rejections to allow fresh start
                $conn->query("DELETE FROM customers WHERE email = '$track_email' AND status = 'Rejected'");
            }
        } elseif ($track_email) {
            // Pick THE LATEST application for this email to avoid showing old rejections
            $res = $conn->query("SELECT * FROM customers WHERE email = '$track_email' ORDER BY created_at DESC LIMIT 1");
            if ($res && $res->num_rows > 0) {
                $found_customer = $res->fetch_assoc();
                $success = "found"; 
            } else {
                $error = "No application found with this email.";
            }
        }
    }
}

// Handle New Application Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_application'])) {
    $conn = getWebsiteConnection();
    if ($conn) {
        $email = $conn->real_escape_string($_POST['email']);
        
        // Final check: Don't allow new if they have an active pending/action needed one
        $check = $conn->query("SELECT customer_id, status FROM customers WHERE email = '$email'");
        if ($check && $check->num_rows > 0) {
            $existing = $check->fetch_assoc();
            $curr_status = strtolower(trim($existing['status']));
            if ($curr_status !== 'rejected') {
                $already_applied = true;
                $error = "You already have an active application. Track it using your email above.";
            } else {
                // If they are rejected, we clear the old one so they can start fresh
                $conn->query("DELETE FROM customers WHERE customer_id = " . $existing['customer_id']);
            }
        }
        
        if (!$already_applied) {
            // Sanitize all inputs
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

            if (!$already_applied) {
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
                else $error = "Submission Error: " . $conn->error;
            }
        }
    }
}
?>

<main class="min-h-screen py-16 px-4 md:px-0 gradient-bg font-sans">
    <div class="max-w-4xl mx-auto">
        
        <!-- Tracking Dashboard Shortcut -->
        <div class="mb-10 flex justify-end">
            <form action="" method="GET" class="flex items-center gap-2 glass-panel p-2 rounded-2xl border border-white/20">
                <input type="email" name="track_email" placeholder="Track with registered email..." required class="bg-white/90 rounded-xl px-4 py-2.5 text-xs text-gray-800 focus:ring-2 focus:ring-primary-blue outline-none min-w-[220px] font-medium">
                <button type="submit" class="bg-primary-blue hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-xs font-black transition-all shadow-lg active:scale-95">Track</button>
            </form>
        </div>

        <?php if ($success === "found" && isset($found_customer)): ?>
            <!-- Status Result View -->
            <div class="glass-card rounded-[3rem] shadow-2xl p-10 md:p-14 text-center border border-white/30 animate-in">
                <h2 class="text-3xl font-black text-gray-900 mb-2">Hello, <?php echo htmlspecialchars(explode(' ', $found_customer['customer_name'])[0]); ?></h2>
                <p class="text-primary-blue text-[11px] font-black uppercase tracking-[0.3em] mb-10">Tracking ID: <?php echo $found_customer['customer_code']; ?></p>

                <div class="max-w-2xl mx-auto p-10 rounded-[2.5rem] bg-white/40 border border-white/50 shadow-xl backdrop-blur-md">
                    <?php 
                    $stat = trim($found_customer['status']); // Clean status
                    
                    if ($stat == 'Pending'): ?>
                        <div class="w-20 h-20 bg-amber-100 text-amber-600 rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-inner"><i class="fas fa-hourglass-half text-3xl"></i></div>
                        <h4 class="text-2xl font-black text-amber-600 uppercase tracking-tight">Review in Progress</h4>
                        <p class="text-sm text-gray-600 mt-4 leading-relaxed font-medium">Our credit officers are currently validating your details. We will contact you shortly.</p>
                        
                    <?php elseif ($stat == 'Approved'): ?>
                        <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-inner"><i class="fas fa-check-circle text-3xl"></i></div>
                        <h4 class="text-2xl font-black text-emerald-600 uppercase tracking-tight">Application Approved</h4>
                        <div class="mt-6 p-6 bg-emerald-500/10 rounded-2xl border border-emerald-500/20 text-emerald-900 text-sm font-bold leading-relaxed">
                            <i class="fas fa-map-marker-alt me-2"></i> Visit <strong>Ikaze House, 2nd Floor</strong> or contact <strong>+250 796 880 272</strong> to sign your agreement.
                        </div>

                    <?php elseif ($stat == 'Action Required'): ?>
                        <div class="w-20 h-20 bg-blue-100 text-primary-blue rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-inner"><i class="fas fa-edit text-3xl"></i></div>
                        <h4 class="text-2xl font-black text-primary-blue uppercase tracking-tight">Update Required</h4>
                        <div class="mt-6 p-5 bg-blue-500/10 rounded-2xl border border-blue-500/20 mb-10 text-left">
                            <p class="text-[10px] font-black text-primary-blue uppercase mb-2 tracking-widest">Admin Note:</p>
                            <p class="text-sm text-blue-900 font-bold italic">"<?php echo htmlspecialchars($found_customer['admin_note'] ?: 'Please correct the fields below.'); ?>"</p>
                        </div>
                        
                        <div class="text-left border-t border-blue-100 pt-10">
                            <form action="" method="POST" enctype="multipart/form-data" class="space-y-8">
                                <input type="hidden" name="customer_id" value="<?php echo $found_customer['customer_id']; ?>">
                                <input type="hidden" name="flagged_fields" value="<?php echo $found_customer['correction_fields']; ?>">
                                <?php 
                                $flagged = explode(',', $found_customer['correction_fields']);
                                $field_labels = [
                                    'customer_name' => 'Full Name', 'id_number' => 'National ID', 'phone' => 'Phone Number',
                                    'email' => 'Email Address', 'doc_id' => 'ID Document', 'doc_contract' => 'Job Contract',
                                    'doc_statement' => 'Bank Statement', 'doc_payslip' => 'Latest Payslip', 
                                    'doc_marital' => 'Marital Status', 'doc_rdb' => 'RDB Certificate'
                                ];
                                foreach($flagged as $f): 
                                    if(empty($f)) continue;
                                    $label = $field_labels[$f] ?? $f;
                                ?>
                                    <div class="space-y-2">
                                        <label class="text-[11px] font-black uppercase text-gray-500 pl-2 tracking-widest"><?php echo $label; ?> *</label>
                                        <?php if(strpos($f, 'doc_') === 0): ?>
                                            <div class="relative group">
                                                <input type="file" name="<?php echo $f; ?>" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)">
                                                <div class="p-5 bg-white border-2 border-dashed border-gray-200 rounded-2xl flex items-center justify-between text-sm font-bold text-gray-400 group-hover:border-primary-blue group-hover:bg-blue-50/30 transition-all">
                                                    <span class="file-name-display text-truncate">Select New Document...</span>
                                                    <span class="bg-gray-100 px-4 py-2 rounded-xl text-[10px] text-gray-600">BROWSE</span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <input type="text" name="<?php echo $f; ?>" required class="w-full text-sm bg-white p-4 rounded-2xl border border-gray-200 outline-none focus:ring-4 focus:ring-primary-blue/10 focus:border-primary-blue transition-all font-bold" value="<?php echo htmlspecialchars($found_customer[$f]); ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <button type="submit" name="submit_correction" class="w-full bg-primary-blue text-white py-5 rounded-2xl font-black text-sm shadow-2xl transition-all hover:scale-[1.02] active:scale-95">RESUBMIT DOCUMENTS</button>
                            </form>
                        </div>

                    <?php else: ?>
                        <!-- REJECTED VIEW -->
                        <div class="w-20 h-20 bg-rose-100 text-rose-600 rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-inner"><i class="fas fa-times-circle text-3xl"></i></div>
                        <h4 class="text-2xl font-black text-rose-600 uppercase tracking-tight">Application Rejected</h4>
                        <div class="mt-6 p-6 bg-rose-500/10 rounded-2xl border border-rose-500/20 mb-10 text-left">
                            <p class="text-[10px] font-black text-rose-400 uppercase mb-2">Notice from Capital Bridge:</p>
                            <p class="text-sm text-rose-900 font-bold italic">"<?php echo htmlspecialchars($found_customer['rejection_reason'] ?: 'Unfortunately, your application does not meet our current requirements.'); ?>"</p>
                        </div>
                        <a href="apply.php?reapply=true&track_email=<?php echo urlencode($found_customer['email']); ?>" class="inline-block bg-primary-blue text-white px-12 py-5 rounded-2xl font-black text-sm shadow-2xl transition-all hover:scale-[1.05] active:scale-95 hover:bg-blue-700">START FRESH APPLICATION</a>
                    <?php endif; ?>
                </div>
                <div class="mt-12">
                    <a href="apply.php" class="text-gray-400 text-[11px] font-black hover:text-primary-blue transition-colors uppercase tracking-[0.4em]"><i class="fas fa-chevron-left me-2"></i> Exit Tracking</a>
                </div>
            </div>

        <?php elseif ($success === "updated" || $success === "created"): ?>
            <!-- Success Landing -->
            <div class="glass-card rounded-[3rem] p-16 text-center shadow-2xl animate-in">
                <div class="w-24 h-24 <?php echo ($success === 'updated') ? 'bg-blue-100 text-primary-blue' : 'bg-emerald-100 text-emerald-600'; ?> rounded-[2.5rem] flex items-center justify-center mx-auto mb-10 shadow-lg"><i class="fas fa-paper-plane text-4xl"></i></div>
                <h3 class="text-4xl font-black text-gray-900 mb-4"><?php echo ($success === 'updated') ? 'Resubmitted!' : 'Application Sent!'; ?></h3>
                <p class="text-gray-500 text-sm font-bold leading-relaxed max-w-sm mx-auto">Your details are being processed. View progress using the tracker.</p>
                <div class="mt-12 flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="index.php" class="bg-gray-100 text-gray-600 px-10 py-5 rounded-2xl font-black text-xs hover:bg-gray-200 transition-all">HOME</a>
                    <a href="apply.php?track_email=<?php echo urlencode($_POST['email'] ?? ''); ?>" class="bg-primary-blue text-white px-10 py-5 rounded-2xl font-black text-xs shadow-xl hover:bg-blue-700 transition-all">TRACK STATUS</a>
                </div>
            </div>

        <?php else: ?>
            <!-- Core Application Form -->
            <div id="form-container" class="glass-card rounded-[3rem] shadow-2xl p-10 md:p-16 border border-white/40 relative animate-in">
                <div class="text-center mb-14">
                    <h1 class="text-4xl font-black text-gray-900 mb-2 tracking-tighter">Instant Loan Application</h1>
                    <div class="h-1.5 w-24 bg-primary-green mx-auto rounded-full mb-4"></div>
                    <p class="text-gray-400 text-[11px] font-black uppercase tracking-[0.4em]">Finance your dreams today</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-rose-50 text-rose-600 p-5 rounded-2xl mb-10 text-xs text-center border border-rose-100 font-bold animate-pulse shadow-sm"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form id="loanForm" method="POST" enctype="multipart/form-data" class="space-y-12">
                    <input type="hidden" name="submit_application" value="1">
                    
                    <!-- STEP 1: Categories & Identity -->
                    <div id="step-1" class="form-step space-y-12">
                        <div class="bg-blue-50/30 p-8 rounded-[2.5rem] border border-blue-100/50 shadow-inner">
                            <label class="block text-[11px] font-black text-primary-blue mb-8 text-center tracking-[0.3em] uppercase">What type of loan do you need?</label>
                            <div class="grid grid-cols-2 gap-6">
                                <label class="cursor-pointer group block">
                                    <input type="radio" name="loan_type" value="Salary" required class="hidden peer" onchange="updateDocFields()">
                                    <div class="bg-white border-2 border-transparent peer-checked:border-primary-green p-6 rounded-3xl text-center transition-all group-hover:bg-gray-50/50 shadow-sm peer-checked:bg-green-50/50">
                                        <div class="w-12 h-12 bg-green-100 text-primary-green rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform"><i class="fas fa-briefcase text-lg"></i></div>
                                        <span class="block text-[12px] font-black uppercase text-gray-800 tracking-wider">Salary Loan</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer group block">
                                    <input type="radio" name="loan_type" value="Business" required class="hidden peer" onchange="updateDocFields()">
                                    <div class="bg-white border-2 border-transparent peer-checked:border-primary-blue p-6 rounded-3xl text-center transition-all group-hover:bg-gray-50/50 shadow-sm peer-checked:bg-blue-50/50">
                                        <div class="w-12 h-12 bg-blue-100 text-primary-blue rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform"><i class="fas fa-store-alt text-lg"></i></div>
                                        <span class="block text-[12px] font-black uppercase text-gray-800 tracking-wider">Business Loan</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Full Name</label><input type="text" name="customer_name" required class="w-full p-4 bg-gray-50/50 border border-gray-100 rounded-2xl text-[13px] font-bold outline-none focus:bg-white focus:ring-4 focus:ring-primary-blue/5 focus:border-primary-blue transition-all"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Email Address</label><input type="email" name="email" required class="w-full p-4 bg-gray-50/50 border border-gray-100 rounded-2xl text-[13px] font-bold outline-none focus:bg-white focus:ring-4 focus:ring-primary-blue/5 focus:border-primary-blue transition-all"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Phone Number</label><input type="tel" name="phone" required placeholder="07XX XXX XXX" class="w-full p-4 bg-gray-50/50 border border-gray-100 rounded-2xl text-[13px] font-bold outline-none focus:bg-white focus:ring-4 focus:ring-primary-blue/5 focus:border-primary-blue transition-all"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">National ID No.</label><input type="text" name="nationalId" required class="w-full p-4 bg-gray-50/50 border border-gray-100 rounded-2xl text-[13px] font-bold outline-none focus:bg-white focus:ring-4 focus:ring-primary-blue/5 focus:border-primary-blue transition-all"></div>
                        </div>

                        <button type="button" onclick="nextPrev(1)" class="w-full bg-primary-blue text-white py-5 rounded-2xl font-black text-sm shadow-2xl transition-all flex items-center justify-center gap-3 group active:scale-[0.98] hover:bg-blue-700">NEXT: PERSONAL INFO <i class="fas fa-chevron-right text-[10px] transition-transform group-hover:translate-x-1"></i></button>
                    </div>

                    <!-- STEP 2: Background -->
                    <div id="step-2" class="form-step space-y-12 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Birth Place</label><input type="text" name="birth_place" required class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold outline-none"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Gender</label><select name="gender" class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold">
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Date of Birth</label><input type="date" name="dob" required class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold outline-none"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Father's Name</label><input type="text" name="father_name" required class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold outline-none"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Mother's Name</label><input type="text" name="mother_name" required class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold outline-none"></div>
                        </div>
                        <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Marital Regime</label><select name="marriage_type" id="marriage_type" onchange="toggleSpouseFields()" class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold outline-none">
                            <option value="Single">Single</option>
                            <option value="Ivanga mutungo">Umutungo ugizwe n'ibintu byose (Ivanga)</option>
                            <option value="Ivangura mutungo">Umutungo ugizwe n'ibintu byihariye (Ivangura)</option>
                            <option value="Muhahano">Muhahano</option>
                        </select></div>
                        <div id="spouse_fields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Spouse Name</label><input type="text" name="spouse" class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Spouse Phone</label><input type="tel" name="spouse_phone" class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold"></div>
                        </div>
                        <div class="flex gap-4 pt-10"><button type="button" onclick="nextPrev(-1)" class="w-1/3 bg-gray-100 p-5 rounded-2xl font-black text-gray-400 text-xs shadow-inner">BACK</button><button type="button" onclick="nextPrev(1)" class="w-2/3 bg-primary-blue text-white p-5 rounded-2xl font-black text-xs shadow-2xl">CONTINUE TO WORK INFO</button></div>
                    </div>

                    <!-- STEP 3: Professional -->
                    <div id="step-3" class="form-step space-y-12 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Occupation / Business</label><input type="text" name="occupation" required class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Bank Account No.</label><input type="text" name="account_number" required class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold"></div>
                        </div>
                        <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Home Residential Address</label><input type="text" name="address" required class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold"></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Loan Purpose (Project)</label><input type="text" name="project" required class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Living Location</label><input type="text" name="location" required class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Project Location</label><input type="text" name="project_location" required class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4">Collateral Location</label><input type="text" name="caution_location" required class="w-full p-4 bg-gray-50/50 rounded-2xl border text-[13px] font-bold"></div>
                        </div>
                        <div class="flex gap-4 pt-10"><button type="button" onclick="nextPrev(-1)" class="w-1/3 bg-gray-100 p-5 rounded-2xl font-black text-gray-400 text-xs">BACK</button><button type="button" onclick="nextPrev(1)" class="w-2/3 bg-primary-blue text-white p-5 rounded-2xl font-black text-xs shadow-2xl">NEXT: UPLOAD DOCUMENTS</button></div>
                    </div>

                    <!-- STEP 4: Files -->
                    <div id="step-4" class="form-step space-y-12 hidden">
                        <div class="bg-primary-blue text-white p-8 rounded-[2.5rem] shadow-2xl flex items-center justify-between border border-blue-400/30">
                            <div><h4 class="text-sm font-black uppercase tracking-widest"><i class="fas fa-file-shield text-xl me-3"></i> Upload Required Docs</h4><p class="text-[10px] text-blue-200 mt-2 font-bold italic tracking-wide">Accepted formats: PDF, JPG, PNG (Max 5MB)</p></div>
                            <i class="fas fa-fingerprint opacity-20 text-4xl"></i>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-3 group"><label class="text-[11px] font-black text-gray-500 pl-3 uppercase tracking-widest">National ID Copy *</label><div class="relative"><input type="file" name="doc_id" required accept=".pdf,.jpg,.jpeg,.png" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)"><div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-[2rem] flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/20 transition-all shadow-sm"><span class="text-[12px] font-bold text-gray-400 file-name-display text-truncate">Click to Upload...</span><span class="text-[10px] font-black bg-gray-100 px-4 py-2.5 rounded-2xl text-gray-600">BROWSE</span></div></div></div>
                            <div class="space-y-3 group"><label class="text-[11px] font-black text-gray-500 pl-3 uppercase tracking-widest">Marital Certificate *</label><div class="relative"><input type="file" name="doc_marital" required accept=".pdf,.jpg,.jpeg,.png" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)"><div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-[2rem] flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/20 transition-all shadow-sm"><span class="text-[12px] font-bold text-gray-400 file-name-display text-truncate">Click to Upload...</span><span class="text-[10px] font-black bg-gray-100 px-4 py-2.5 rounded-2xl text-gray-600">BROWSE</span></div></div></div>
                            
                            <div class="space-y-3 group doc-salary"><label class="text-[11px] font-black text-gray-500 pl-3 uppercase tracking-widest">Job Contract *</label><div class="relative"><input type="file" name="doc_contract" accept=".pdf,.jpg,.jpeg,.png" class="salary-req absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)"><div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-[2rem] flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/20 transition-all shadow-sm"><span class="text-[12px] font-bold text-gray-400 file-name-display text-truncate">Click to Upload...</span><span class="text-[10px] font-black bg-gray-100 px-4 py-2.5 rounded-2xl text-gray-600">BROWSE</span></div></div></div>
                            <div class="space-y-3 group doc-salary"><label class="text-[11px] font-black text-gray-500 pl-3 uppercase tracking-widest">Recent Bank Statement *</label><div class="relative"><input type="file" name="doc_statement" accept=".pdf,.jpg,.jpeg,.png" class="salary-req absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)"><div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-[2rem] flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/20 transition-all shadow-sm"><span class="text-[12px] font-bold text-gray-400 file-name-display text-truncate">Click to Upload...</span><span class="text-[10px] font-black bg-gray-100 px-4 py-2.5 rounded-2xl text-gray-600">BROWSE</span></div></div></div>
                            <div class="space-y-3 group doc-salary"><label class="text-[11px] font-black text-gray-500 pl-3 uppercase tracking-widest">Last 3 Months Payslip *</label><div class="relative"><input type="file" name="doc_payslip" accept=".pdf,.jpg,.jpeg,.png" class="salary-req absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)"><div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-[2rem] flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/20 transition-all shadow-sm"><span class="text-[12px] font-bold text-gray-400 file-name-display text-truncate">Click to Upload...</span><span class="text-[10px] font-black bg-gray-100 px-4 py-2.5 rounded-2xl text-gray-600">BROWSE</span></div></div></div>
                            
                            <div class="space-y-3 group doc-business hidden"><label class="text-[11px] font-black text-gray-500 pl-3 uppercase tracking-widest">RDB Registration Cert *</label><div class="relative"><input type="file" name="doc_rdb" accept=".pdf,.jpg,.jpeg,.png" class="business-req absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)"><div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-[2rem] flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/20 transition-all shadow-sm"><span class="text-[12px] font-bold text-gray-400 file-name-display text-truncate">Click to Upload...</span><span class="text-[10px] font-black bg-gray-100 px-4 py-2.5 rounded-2xl text-gray-600">BROWSE</span></div></div></div>
                            <div class="space-y-3 group doc-business hidden"><label class="text-[11px] font-black text-gray-500 pl-3 uppercase tracking-widest">Business Income Proof *</label><div class="relative"><input type="file" name="doc_statement_alt" accept=".pdf,.jpg,.jpeg,.png" class="business-req absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)"><div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-[2rem] flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/20 transition-all shadow-sm"><span class="text-[12px] font-bold text-gray-400 file-name-display text-truncate">Click to Upload...</span><span class="text-[10px] font-black bg-gray-100 px-4 py-2.5 rounded-2xl text-gray-600">BROWSE</span></div></div></div>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-4 mt-16 py-6 border-t border-gray-100">
                            <button type="button" onclick="nextPrev(-1)" class="w-full sm:w-1/3 bg-gray-100 p-6 rounded-3xl font-black text-gray-400 text-xs shadow-inner">GO BACK</button>
                            <button type="submit" name="submit_application" class="w-full sm:w-2/3 bg-primary-green text-white p-6 rounded-3xl font-black text-xs shadow-2xl transition-all hover:scale-[1.02] active:scale-[0.98] hover:bg-green-600 flex items-center justify-center gap-2">SUBMIT FINAL APPLICATION <i class="fas fa-check-double text-[10px]"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
let currentStep = 1;

function updateFileName(input) {
    const display = input.parentElement.querySelector('.file-name-display');
    if (input.files.length > 0) {
        display.innerText = input.files[0].name;
        display.classList.remove('text-gray-400');
        display.classList.add('text-primary-blue');
    }
}

function nextPrev(n) {
    const steps = document.querySelectorAll(".form-step");
    if (n === 1 && !validateStep()) return;
    
    // Smooth transition
    steps[currentStep-1].classList.add("hidden");
    currentStep += n;
    steps[currentStep-1].classList.remove("hidden");
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function validateStep() {
    const activeStep = document.getElementById("step-" + currentStep);
    const inputs = activeStep.querySelectorAll("input[required], select[required]");
    let valid = true;
    
    for (let input of inputs) {
        if (!input.value) { 
            input.classList.add("border-rose-300", "bg-rose-50/30");
            valid = false;
        } else {
            input.classList.remove("border-rose-300", "bg-rose-50/30");
        }
    }
    
    if(!valid) {
        const firstError = activeStep.querySelector(".border-rose-300");
        if(firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    return valid;
}

function toggleSpouseFields() {
    const val = document.getElementById('marriage_type').value;
    const fields = document.getElementById('spouse_fields');
    if (val === 'Single') fields.classList.add('hidden');
    else fields.classList.remove('hidden');
}

function updateDocFields() {
    const radioSelected = document.querySelector('input[name="loan_type"]:checked');
    if(!radioSelected) return;
    
    const type = radioSelected.value;
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

// Ensure first step is visible
document.addEventListener('DOMContentLoaded', () => {
    updateDocFields();
});
</script>

<style>
@keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
.animate-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.gradient-bg { background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); }
.glass-panel { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); }
.glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
</style>

<?php 
include 'includes/bottom_nav.php'; 
include 'includes/footer.php'; 
?>
