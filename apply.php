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
            // Check if email exists and is rejected, if so, we can clear it now to avoid "Already Exists" error during submission
            if($track_email) {
                $conn->query("DELETE FROM customers WHERE email = '$track_email' AND (status = 'Rejected' OR status = 'Action Required')");
            }
            // Now show the form
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
        
        // CRITICAL FIX: Always allow application if it was rejected
        $check = $conn->query("SELECT customer_id, status FROM customers WHERE email = '$email'");
        if ($check && $check->num_rows > 0) {
            $existing = $check->fetch_assoc();
            $status = strtolower($existing['status']);
            if ($status !== 'rejected' && $status !== 'action required') {
                $already_applied = true;
                $error = "You have an active/pending application. Use your email above to track status.";
            } else {
                // If rejected or action required, we delete the old one to allow a fresh start if they chose "New Application"
                $conn->query("DELETE FROM customers WHERE customer_id = " . $existing['customer_id']);
                // Proceeds to insert...
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
                    if (!in_array($ext, $allowed_exts)) { $error = "Invalid file type for $doc_field."; $already_applied = true; break; }
                    $filename = $doc_field . "_" . time() . "_" . mt_rand(1000, 9999) . "." . $ext;
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
                else $error = "Database Error: " . $conn->error;
            }
        }
    }
}
?>

<main class="min-h-screen py-10 px-4 md:px-0 gradient-blue font-sans overflow-x-hidden">
    <!-- Background Decor -->
    <div class="fixed top-0 left-0 w-full h-full pointer-events-none opacity-20">
        <div class="absolute top-[-10%] right-[-10%] w-[400px] h-[400px] bg-primary-blue rounded-full blur-[100px]"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[400px] h-[400px] bg-primary-green rounded-full blur-[100px]"></div>
    </div>

    <div class="max-w-4xl mx-auto relative z-10">
        <!-- Floating Tracking Bar -->
        <div class="mb-12 flex justify-center md:justify-end animate-[fadeIn_0.5s_ease-out]">
            <form action="" method="GET" class="flex flex-col md:flex-row items-center gap-3 bg-white/15 p-2 rounded-3xl backdrop-blur-xl border border-white/30 shadow-2xl">
                <input type="email" name="track_email" placeholder="Track using email" required class="bg-white/90 rounded-2xl px-5 py-2.5 text-sm text-gray-800 focus:ring-4 focus:ring-primary-green outline-none min-w-[250px] font-medium placeholder:text-gray-400">
                <button type="submit" class="w-full md:w-auto bg-primary-green hover:bg-green-600 hover:scale-105 active:scale-95 text-white px-8 py-2.5 rounded-2xl text-sm font-black transition-all shadow-lg flex items-center justify-center gap-2">
                    <i class="fas fa-search-location"></i> CHECK STATUS
                </button>
            </form>
        </div>

        <?php if ($success === "found" && isset($found_customer)): ?>
            <!-- CLIENT DASHBOARD VIEW -->
            <div class="bg-white/95 backdrop-blur-md rounded-[3rem] shadow-[0_32px_64px_-16px_rgba(0,0,0,0.3)] p-10 md:p-16 text-center animate-[fadeIn_0.5s_ease-out] border border-white">
                <div class="inline-flex p-1 bg-gray-100 rounded-2xl mb-6">
                    <span class="px-4 py-1 text-[10px] font-black tracking-widest text-primary-blue uppercase">Member Portal</span>
                </div>
                <h2 class="text-4xl font-black text-gray-900 mb-2 leading-tight">Hello, <?php echo htmlspecialchars(explode(' ', $found_customer['customer_name'])[0]); ?></h2>
                <p class="text-gray-400 mb-10 font-bold uppercase tracking-widest text-[10px]">Reference: <span class="bg-blue-50 text-primary-blue px-3 py-1 rounded-lg ml-1"><?php echo $found_customer['customer_code']; ?></span></p>

                <div class="max-w-2xl mx-auto p-12 rounded-[3.5rem] bg-gradient-to-b from-gray-50 to-white border border-gray-100 shadow-[inset_0_2px_4px_rgba(0,0,0,0.05)]">
                    <?php 
                    $stat = $found_customer['status'];
                    if ($stat == 'Pending'): ?>
                        <div class="w-24 h-24 bg-yellow-400/10 text-yellow-500 rounded-[2rem] flex items-center justify-center mx-auto mb-8 rotate-12"><i class="fas fa-hourglass-half text-4xl"></i></div>
                        <h4 class="text-3xl font-black text-gray-800 uppercase tracking-tighter">Under Review</h4>
                        <p class="text-sm text-gray-400 mt-4 leading-relaxed max-w-sm mx-auto">We're verifying your details. You will receive a call or SMS once processed. Stay tuned!</p>
                        
                    <?php elseif ($stat == 'Approved'): ?>
                        <div class="w-24 h-24 bg-green-500/10 text-primary-green rounded-[2rem] flex items-center justify-center mx-auto mb-8"><i class="fas fa-check-double text-4xl"></i></div>
                        <h4 class="text-3xl font-black text-primary-green uppercase tracking-tighter">You're Approved!</h4>
                        <div class="mt-8 p-8 bg-green-50 rounded-[2.5rem] border border-green-100 text-green-900 shadow-sm relative overflow-hidden group">
                           <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:rotate-12 transition-transform"><i class="fas fa-award text-6xl"></i></div>
                            <p class="font-black flex items-center justify-center gap-2 mb-3 text-lg"><i class="fas fa-glass-cheers"></i> CONGRATULATIONS!</p>
                            <p class="text-sm leading-relaxed font-bold">Your application for the <strong><?php echo $found_customer['loan_type']; ?> Loan</strong> is successful. Please visit our main office at Ikaze House, 2nd Floor.</p>
                        </div>

                    <?php elseif ($stat == 'Action Required'): ?>
                        <div class="w-24 h-24 bg-blue-500/10 text-primary-blue rounded-[2rem] flex items-center justify-center mx-auto mb-8 animate-pulse"><i class="fas fa-edit text-4xl"></i></div>
                        <h4 class="text-3xl font-black text-primary-blue uppercase tracking-tighter">Action Required</h4>
                        <div class="mt-6 p-6 bg-blue-50/50 rounded-3xl border border-blue-100/50 mb-10 inline-block">
                            <p class="text-[11px] font-black text-primary-blue uppercase flex items-center justify-center gap-3 mb-2"><i class="fas fa-comment-dots text-lg"></i> Admin Message:</p>
                            <p class="text-lg text-blue-900 font-extrabold tracking-tight"><?php echo htmlspecialchars($found_customer['admin_note'] ?: 'Please correct the information below.'); ?></p>
                        </div>
                        
                        <div class="text-left border-t border-gray-100 pt-12">
                            <form action="" method="POST" enctype="multipart/form-data" class="space-y-8">
                                <input type="hidden" name="customer_id" value="<?php echo $found_customer['customer_id']; ?>">
                                <input type="hidden" name="flagged_fields" value="<?php echo $found_customer['correction_fields']; ?>">
                                <?php 
                                $flagged = explode(',', $found_customer['correction_fields']);
                                $field_labels = [
                                    'customer_name' => 'Full Name', 'id_number' => 'National ID', 'phone' => 'Phone Number',
                                    'email' => 'Email', 'doc_id' => 'ID Document', 'doc_contract' => 'Contract',
                                    'doc_statement' => 'Bank Statement', 'doc_payslip' => 'Payslip', 
                                    'doc_marital' => 'Marital Certificate', 'doc_rdb' => 'RDB Cert.'
                                ];
                                foreach($flagged as $f): 
                                    if(empty($f)) continue;
                                    $label = $field_labels[$f] ?? $f;
                                ?>
                                    <div class="space-y-3 group">
                                        <label class="text-[12px] font-black uppercase text-gray-500 pl-2 tracking-widest group-focus-within:text-primary-blue transition-colors"><?php echo $label; ?></label>
                                        <?php if(strpos($f, 'doc_') === 0): ?>
                                            <div class="relative group">
                                                <input type="file" name="<?php echo $f; ?>" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)">
                                                <div class="p-5 bg-white border-2 border-dashed border-gray-200 rounded-3xl flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/30 transition-all shadow-sm">
                                                    <div class="flex items-center gap-4">
                                                        <div class="w-12 h-12 bg-gray-100 text-gray-400 rounded-2xl flex items-center justify-center group-hover:bg-primary-blue group-hover:text-white transition-all"><i class="fas fa-upload"></i></div>
                                                        <span class="text-sm font-bold text-gray-400 file-name-display">Choose new file...</span>
                                                    </div>
                                                    <span class="bg-gray-100 text-gray-400 text-[10px] font-black px-4 py-2 rounded-xl group-hover:bg-primary-blue group-hover:text-white transition-all">BROWSE</span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <input type="text" name="<?php echo $f; ?>" required class="w-full text-lg bg-white p-5 rounded-[2rem] border border-gray-100 focus:ring-4 focus:ring-primary-blue/10 focus:border-primary-blue outline-none transition-all font-bold text-gray-800 shadow-sm" value="<?php echo htmlspecialchars($found_customer[$f]); ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <button type="submit" name="submit_correction" class="w-full bg-primary-blue hover:bg-blue-800 text-white py-6 rounded-[2rem] font-black text-lg shadow-2xl transition-all transform hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-4">
                                    <i class="fas fa-rocket"></i> RESUBMIT APPLICATION
                                </button>
                            </form>
                        </div>

                    <?php else: ?>
                        <div class="w-24 h-24 bg-red-500/10 text-red-600 rounded-[2rem] flex items-center justify-center mx-auto mb-8"><i class="fas fa-times-circle text-4xl"></i></div>
                        <h4 class="text-3xl font-black text-red-600 uppercase tracking-tighter">Application Rejected</h4>
                        <div class="mt-6 p-8 bg-red-50/50 rounded-[2.5rem] border border-red-100 mb-10 shadow-sm">
                            <p class="text-[11px] font-black text-red-400 uppercase mb-2">Rejection Reason:</p>
                            <p class="text-lg text-red-900 font-extrabold tracking-tight"><?php echo htmlspecialchars($found_customer['rejection_reason'] ?: 'Does not meet requirements.'); ?></p>
                        </div>
                        <div class="flex flex-col gap-4">
                            <a href="apply.php?reapply=true&track_email=<?php echo urlencode($found_customer['email']); ?>" class="inline-flex items-center justify-center gap-4 bg-primary-blue hover:bg-blue-800 text-white px-12 py-6 rounded-[2rem] font-black text-lg shadow-2xl transition-all transform hover:scale-[1.02] active:scale-95">
                                <i class="fas fa-plus-circle"></i> START NEW APPLICATION
                            </a>
                            <p class="text-xs text-gray-400 font-bold">This will clear your previous record.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-16">
                    <a href="apply.php" class="text-gray-400 text-xs font-black hover:text-primary-blue flex items-center justify-center gap-3 transition-colors uppercase tracking-widest">
                        <i class="fas fa-chevron-left"></i> EXIT DASHBOARD
                    </a>
                </div>
            </div>

        <?php elseif ($success === "updated"): ?>
            <div class="bg-white rounded-[3.5rem] shadow-2xl p-20 text-center animate-[fadeIn_0.5s_ease-out]">
                <div class="w-32 h-32 bg-blue-500/10 text-primary-blue rounded-[3rem] flex items-center justify-center mx-auto mb-10 animate-bounce"><i class="fas fa-check-circle text-5xl"></i></div>
                <h3 class="text-4xl font-black text-gray-900 mb-4">Updates Received!</h3>
                <p class="text-gray-400 text-lg max-w-sm mx-auto leading-relaxed font-medium">Your corrections have been submitted. Our team will review them immediately.</p>
                <div class="mt-14 flex flex-col md:flex-row gap-4 justify-center">
                    <a href="index.php" class="bg-primary-blue text-white px-12 py-5 rounded-[2rem] font-black text-lg shadow-xl hover:bg-blue-800 transition-all hover:scale-105">GO HOME</a>
                    <a href="apply.php" class="bg-gray-100 text-gray-500 px-12 py-5 rounded-[2rem] font-black text-lg hover:bg-gray-200 transition-all">EXIT</a>
                </div>
            </div>

        <?php elseif ($success === "created"): ?>
            <div class="bg-white rounded-[3.5rem] shadow-2xl p-20 text-center animate-[fadeIn_0.5s_ease-out]">
                <div class="w-32 h-32 bg-green-500/10 text-primary-green rounded-[3rem] flex items-center justify-center mx-auto mb-10"><i class="fas fa-clipboard-check text-5xl"></i></div>
                <h3 class="text-4xl font-black text-gray-900 mb-4">Success!</h3>
                <p class="text-gray-400 text-lg max-w-sm mx-auto leading-relaxed font-medium">Your application has been logged. Use your email to track progress anytime.</p>
                <div class="mt-14">
                    <a href="apply.php?track_email=<?php echo urlencode($email); ?>" class="inline-flex items-center gap-4 bg-primary-green text-white px-12 py-5 rounded-[2rem] font-black text-lg shadow-2xl hover:bg-green-600 transition-all hover:scale-105">
                        <i class="fas fa-eye"></i> TRACK MY LOAN
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- MAIN APPLICATION FORM -->
            <div id="form-container" class="bg-white/95 backdrop-blur-md rounded-[3.5rem] shadow-2xl p-10 md:p-16 relative overflow-hidden animate-[fadeIn_0.5s_ease-out] border border-white">
                <div class="text-center mb-16">
                    <div class="inline-flex p-1 bg-blue-50 rounded-2xl mb-4">
                        <span class="px-4 py-1 text-[10px] font-black tracking-widest text-primary-blue uppercase">Application Step 1/4</span>
                    </div>
                    <h1 class="text-5xl font-black text-gray-900 mb-3 tracking-tighter leading-tight">Apply for a Loan</h1>
                    <p class="text-gray-400 text-lg font-bold">Empower your small business or career goals.</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 text-red-600 p-6 rounded-[2rem] mb-12 text-sm text-center border border-red-100 font-bold flex items-center justify-center gap-4 animate-shake">
                        <i class="fas fa-exclamation-circle text-xl"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form id="loanForm" method="POST" enctype="multipart/form-data" class="space-y-12">
                    <input type="hidden" name="submit_application" value="1">
                    
                    <!-- STEP 1: CHOICE & BASICS -->
                    <div id="step-1" class="form-step space-y-10">
                        <div class="bg-gradient-to-br from-blue-50/50 to-white p-10 rounded-[3rem] border border-blue-50 shadow-inner">
                            <label class="block text-[12px] font-black text-primary-blue mb-8 text-center tracking-[0.2em] uppercase">SELECT YOUR LOAN TYPE</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <label class="cursor-pointer group">
                                    <input type="radio" name="loan_type" value="Salary" required class="hidden peer" onchange="updateDocFields()">
                                    <div class="bg-white border-2 border-gray-100 peer-checked:border-primary-green peer-checked:bg-green-50/30 p-8 rounded-[2.5rem] text-center transition-all shadow-sm group-hover:shadow-xl group-hover:scale-105 active:scale-95">
                                        <div class="w-16 h-16 bg-green-50 text-primary-green rounded-[1.5rem] flex items-center justify-center mx-auto mb-4 transition-all peer-checked:bg-primary-green peer-checked:text-white group-hover:rotate-6">
                                            <i class="fas fa-wallet text-2xl"></i>
                                        </div>
                                        <span class="block text-sm font-black uppercase text-gray-800 tracking-tight">Salary Loan</span>
                                        <p class="text-[10px] text-gray-400 mt-2 font-bold">For Employed Individuals</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer group">
                                    <input type="radio" name="loan_type" value="Business" required class="hidden peer" onchange="updateDocFields()">
                                    <div class="bg-white border-2 border-gray-100 peer-checked:border-primary-blue peer-checked:bg-blue-50/30 p-8 rounded-[2.5rem] text-center transition-all shadow-sm group-hover:shadow-xl group-hover:scale-105 active:scale-95">
                                        <div class="w-16 h-16 bg-blue-50 text-primary-blue rounded-[1.5rem] flex items-center justify-center mx-auto mb-4 transition-all peer-checked:bg-primary-blue peer-checked:text-white group-hover:rotate-6">
                                            <i class="fas fa-chart-line text-2xl"></i>
                                        </div>
                                        <span class="block text-sm font-black uppercase text-gray-800 tracking-tight">Business Loan</span>
                                        <p class="text-[10px] text-gray-400 mt-2 font-bold">For Small Enterprises</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2 group">
                                <label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest group-focus-within:text-primary-blue transition-colors">Full Name</label>
                                <input type="text" name="customer_name" required placeholder="Ghis Ishimwe" class="w-full p-5 bg-gray-50 border border-gray-100 rounded-[1.5rem] text-sm outline-none focus:bg-white focus:ring-4 focus:ring-primary-blue/10 focus:border-primary-blue transition-all font-bold">
                            </div>
                            <div class="space-y-2 group">
                                <label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest group-focus-within:text-primary-blue transition-colors">Email Address</label>
                                <input type="email" name="email" required placeholder="contact@example.com" class="w-full p-5 bg-gray-50 border border-gray-100 rounded-[1.5rem] text-sm outline-none focus:bg-white focus:ring-4 focus:ring-primary-blue/10 focus:border-primary-blue transition-all font-bold">
                            </div>
                            <div class="space-y-2 group">
                                <label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest group-focus-within:text-primary-blue transition-colors">Phone Number</label>
                                <input type="tel" name="phone" required placeholder="+250 788..." class="w-full p-5 bg-gray-50 border border-gray-100 rounded-[1.5rem] text-sm outline-none focus:bg-white focus:ring-4 focus:ring-primary-blue/10 focus:border-primary-blue transition-all font-bold">
                            </div>
                            <div class="space-y-2 group">
                                <label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest group-focus-within:text-primary-blue transition-colors">National ID (NID)</label>
                                <input type="text" name="nationalId" required placeholder="1 1990 8..." class="w-full p-5 bg-gray-50 border border-gray-100 rounded-[1.5rem] text-sm outline-none focus:bg-white focus:ring-4 focus:ring-primary-blue/10 focus:border-primary-blue transition-all font-bold">
                            </div>
                        </div>

                        <button type="button" onclick="nextPrev(1)" class="w-full bg-primary-blue text-white py-6 rounded-[2rem] font-black text-lg shadow-2xl hover:bg-blue-800 hover:scale-[1.01] active:scale-95 transition-all flex items-center justify-center gap-4 group">
                            CONTINUE APPLICATION <i class="fas fa-arrow-right text-xs transition-transform group-hover:translate-x-2"></i>
                        </button>
                    </div>

                    <!-- STEP 2: PERSONAL & FAMILY -->
                    <div id="step-2" class="form-step space-y-10 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Birth Place</label><input type="text" name="birth_place" required class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm outline-none"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Gender</label><select name="gender" class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm outline-none"><option value="male">Male</option><option value="female">Female</option></select></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Date of Birth</label><input type="date" name="dob" required class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm outline-none"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Father's Name</label><input type="text" name="father_name" required class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm outline-none"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Mother's Name</label><input type="text" name="mother_name" required class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm outline-none"></div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Marital Status</label>
                            <select name="marriage_type" id="marriage_type" onchange="toggleSpouseFields()" class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm outline-none">
                                <option value="Single">Single</option><option value="Ivanga mutungo">Ivanga mutungo</option><option value="Ivangura mutungo">Ivangura mutungo</option><option value="Muhahano">Muhahano</option>
                            </select>
                        </div>
                        <div id="spouse_fields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Spouse Name</label><input type="text" name="spouse" class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Spouse Phone</label><input type="tel" name="spouse_phone" class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm"></div>
                        </div>
                        <div class="flex gap-6">
                            <button type="button" onclick="nextPrev(-1)" class="w-1/3 bg-gray-100 p-6 rounded-[1.5rem] font-black text-gray-400 transition-colors hover:bg-gray-200">BACK</button>
                            <button type="button" onclick="nextPrev(1)" class="w-2/3 bg-primary-blue text-white p-6 rounded-[1.5rem] font-black shadow-xl hover:bg-blue-800 transition-all">NEXT: PROFESSIONAL INFO</button>
                        </div>
                    </div>

                    <!-- STEP 3: WORK & PROJECT -->
                    <div id="step-3" class="form-step space-y-10 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Occupation</label><input type="text" name="occupation" required class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm outline-none"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Bank IBAN/Account</label><input type="text" name="account_number" required class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm outline-none"></div>
                        </div>
                        <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Residential Address</label><input type="text" name="address" required placeholder="District, Sector, Cell..." class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm outline-none"></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Project Name</label><input type="text" name="project" required class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm outline-none"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Primary Office Loc.</label><input type="text" name="location" required class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm outline-none"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Project Site Loc.</label><input type="text" name="project_location" required class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm outline-none"></div>
                            <div class="space-y-2"><label class="text-[11px] font-black uppercase text-gray-400 pl-4 tracking-widest">Caution Location</label><input type="text" name="caution_location" required class="w-full p-5 bg-gray-50 rounded-[1.5rem] border font-bold text-sm outline-none"></div>
                        </div>
                        <div class="flex gap-6">
                            <button type="button" onclick="nextPrev(-1)" class="w-1/3 bg-gray-100 p-6 rounded-[1.5rem] font-black text-gray-400">BACK</button>
                            <button type="button" onclick="nextPrev(1)" class="w-2/3 bg-primary-blue text-white p-6 rounded-[1.5rem] font-black shadow-xl hover:bg-blue-800 transition-all">NEXT: UPLOAD FILES</button>
                        </div>
                    </div>

                    <!-- STEP 4: DOCS -->
                    <div id="step-4" class="form-step space-y-10 hidden">
                        <div class="bg-gradient-to-r from-primary-blue to-blue-600 text-white p-10 rounded-[3rem] shadow-2xl relative overflow-hidden">
                            <div class="relative z-10">
                                <h4 class="text-xl font-black uppercase flex items-center gap-4"><i class="fas fa-file-shield"></i> Document Verification</h4>
                                <p class="text-[11px] text-blue-100 mt-3 font-bold uppercase tracking-[0.2em]">MAX 5MB PER FILE • PDF, JPG, PNG ONLY</p>
                            </div>
                            <i class="fas fa-fingerprint absolute right-[-20px] bottom-[-20px] text-9xl opacity-10"></i>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                            <!-- Premium Custom File Input -->
                            <div class="space-y-3 group">
                                <label class="text-[12px] font-black text-gray-500 pl-4 uppercase tracking-tighter group-hover:text-primary-blue transition-colors">National ID (Double-sided) *</label>
                                <div class="relative">
                                    <input type="file" name="doc_id" required accept=".pdf,.jpg,.jpeg,.png" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)">
                                    <div class="p-6 bg-gray-50 border-2 border-dashed border-gray-100 rounded-[2rem] flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/50 transition-all">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-white text-primary-blue rounded-2xl flex items-center justify-center shadow-sm"><i class="fas fa-id-card"></i></div>
                                            <span class="text-xs font-bold text-gray-400 file-name-display">Choose NID file...</span>
                                        </div>
                                        <span class="text-[10px] font-black px-4 py-2 bg-gray-100 text-gray-400 rounded-xl group-hover:bg-primary-blue group-hover:text-white transition-all">BROWSE</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 group">
                                <label class="text-[12px] font-black text-gray-500 pl-4 uppercase tracking-tighter group-hover:text-primary-blue transition-colors">Marital Status Certification *</label>
                                <div class="relative">
                                    <input type="file" name="doc_marital" required accept=".pdf,.jpg,.jpeg,.png" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)">
                                    <div class="p-6 bg-gray-50 border-2 border-dashed border-gray-100 rounded-[2rem] flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/50 transition-all">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-white text-primary-blue rounded-2xl flex items-center justify-center shadow-sm"><i class="fas fa-heart"></i></div>
                                            <span class="text-xs font-bold text-gray-400 file-name-display">Choose Cert file...</span>
                                        </div>
                                        <span class="text-[10px] font-black px-4 py-2 bg-gray-100 text-gray-400 rounded-xl group-hover:bg-primary-blue group-hover:text-white transition-all">BROWSE</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Dynamic Groups -->
                            <div class="space-y-3 group doc-salary">
                                <label class="text-[12px] font-black text-gray-500 pl-4 uppercase tracking-tighter group-hover:text-primary-blue transition-colors">Notarized Contract *</label>
                                <div class="relative">
                                    <input type="file" name="doc_contract" accept=".pdf,.jpg,.jpeg,.png" class="salary-req absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)">
                                    <div class="p-6 bg-gray-50 border-2 border-dashed border-gray-100 rounded-[2rem] flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/50 transition-all">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-white text-primary-green rounded-2xl flex items-center justify-center shadow-sm"><i class="fas fa-file-contract"></i></div>
                                            <span class="text-xs font-bold text-gray-400 file-name-display">Choose file...</span>
                                        </div>
                                        <span class="text-[10px] font-black px-4 py-2 bg-gray-100 text-gray-400 rounded-xl group-hover:bg-primary-blue group-hover:text-white transition-all">BROWSE</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 group doc-salary">
                                <label class="text-[12px] font-black text-gray-500 pl-4 uppercase tracking-tighter group-hover:text-primary-blue transition-colors">6 Months Bank Statement *</label>
                                <div class="relative">
                                    <input type="file" name="doc_statement" accept=".pdf,.jpg,.jpeg,.png" class="salary-req absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)">
                                    <div class="p-6 bg-gray-50 border-2 border-dashed border-gray-100 rounded-[2rem] flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/50 transition-all">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-white text-primary-green rounded-2xl flex items-center justify-center shadow-sm"><i class="fas fa-landmark"></i></div>
                                            <span class="text-xs font-bold text-gray-400 file-name-display">Choose file...</span>
                                        </div>
                                        <span class="text-[10px] font-black px-4 py-2 bg-gray-100 text-gray-400 rounded-xl group-hover:bg-primary-blue group-hover:text-white transition-all">BROWSE</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 group doc-salary">
                                <label class="text-[12px] font-black text-gray-500 pl-4 uppercase tracking-tighter group-hover:text-primary-blue transition-colors">Latest 3 Payslips *</label>
                                <div class="relative">
                                    <input type="file" name="doc_payslip" accept=".pdf,.jpg,.jpeg,.png" class="salary-req absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)">
                                    <div class="p-6 bg-gray-50 border-2 border-dashed border-gray-100 rounded-[2rem] flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/50 transition-all">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-white text-primary-green rounded-2xl flex items-center justify-center shadow-sm"><i class="fas fa-file-invoice-dollar"></i></div>
                                            <span class="text-xs font-bold text-gray-400 file-name-display">Choose file...</span>
                                        </div>
                                        <span class="text-[10px] font-black px-4 py-2 bg-gray-100 text-gray-400 rounded-xl group-hover:bg-primary-blue group-hover:text-white transition-all">BROWSE</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Business -->
                            <div class="space-y-3 group doc-business hidden">
                                <label class="text-[12px] font-black text-gray-500 pl-4 uppercase tracking-tighter group-hover:text-primary-blue transition-colors">RDB Registration Cert *</label>
                                <div class="relative">
                                    <input type="file" name="doc_rdb" accept=".pdf,.jpg,.jpeg,.png" class="business-req absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)">
                                    <div class="p-6 bg-gray-50 border-2 border-dashed border-gray-100 rounded-[2rem] flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/50 transition-all">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-white text-primary-blue rounded-2xl flex items-center justify-center shadow-sm"><i class="fas fa-certificate"></i></div>
                                            <span class="text-xs font-bold text-gray-400 file-name-display">Choose file...</span>
                                        </div>
                                        <span class="text-[10px] font-black px-4 py-2 bg-gray-100 text-gray-400 rounded-xl group-hover:bg-primary-blue group-hover:text-white transition-all">BROWSE</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 group doc-business hidden">
                                <label class="text-[12px] font-black text-gray-500 pl-4 uppercase tracking-tighter group-hover:text-primary-blue transition-colors">Bank/MoMo Revenue Report *</label>
                                <div class="relative">
                                    <input type="file" name="doc_statement_alt" accept=".pdf,.jpg,.jpeg,.png" class="business-req absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)">
                                    <div class="p-6 bg-gray-50 border-2 border-dashed border-gray-100 rounded-[2rem] flex items-center justify-between group-hover:border-primary-blue group-hover:bg-blue-50/50 transition-all">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-white text-primary-blue rounded-2xl flex items-center justify-center shadow-sm"><i class="fas fa-receipt"></i></div>
                                            <span class="text-xs font-bold text-gray-400 file-name-display">Choose file...</span>
                                        </div>
                                        <span class="text-[10px] font-black px-4 py-2 bg-gray-100 text-gray-400 rounded-xl group-hover:bg-primary-blue group-hover:text-white transition-all">BROWSE</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-8 mt-16 pb-10">
                            <button type="button" onclick="nextPrev(-1)" class="w-1/3 bg-gray-100 p-8 rounded-[2rem] font-black text-gray-400 transition-all hover:bg-gray-200">BACK</button>
                            <button type="submit" class="w-2/3 bg-primary-green text-white p-8 rounded-[2rem] font-black text-xl shadow-[0_20px_40px_-10px_rgba(34,197,94,0.4)] transition-all hover:scale-[1.03] active:scale-95 flex items-center justify-center gap-4">
                                <i class="fas fa-cloud-upload-alt"></i> FINALIZE APPLICATION
                            </button>
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
        input.parentElement.querySelector('div').classList.add('border-primary-blue', 'bg-blue-50/50');
    }
}

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
        if (input.type === 'radio') {
            const rads = activeStep.querySelectorAll(`input[name="${input.name}"]`);
            let checked = false;
            for(let r of rads) if(r.checked) checked = true;
            if(!checked) return false;
        } else if (!input.value) { 
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
            input.classList.add("border-red-500", "ring-4", "ring-red-100"); 
            return false; 
        }
        input.classList.remove("border-red-500", "ring-4", "ring-red-100");
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
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@100;400;700;900&display=swap');
body { font-family: 'Outfit', sans-serif; -webkit-font-smoothing: antialiased; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
@keyframes shake { 0%, 100% { transform: translateX(0); } 10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); } 20%, 40%, 60%, 80% { transform: translateX(5px); } }
.animate-shake { animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both; }
.gradient-blue { background: linear-gradient(135deg, #020617 0%, #1e3a8a 100%); background-attachment: fixed; }
</style>

<?php 
include 'includes/bottom_nav.php'; 
include 'includes/footer.php'; 
?>
