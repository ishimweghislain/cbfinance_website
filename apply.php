<?php 
require_once 'includes/db_connect.php';
include 'includes/head.php'; 
include 'includes/navbar.php'; 

$success = false;
$error = "";
$already_applied = false;

$conn = getWebsiteConnection();

// Initial track email if coming from URL
$track_url_email = isset($_GET['track_email']) ? trim($_GET['track_email']) : '';
$track_url_nid   = isset($_GET['track_nid'])   ? trim($_GET['track_nid'])   : '';
$nid_step        = !empty($track_url_email) && empty($track_url_nid) && !isset($_GET['reapply']); // Show NID field if email given but not NID yet

// 1. HANDLE RE-APPLY (RESET TRACKING TO SHOW FORM)
if (isset($_GET['reapply']) && $_GET['reapply'] == 'true' && !empty($track_url_email)) {
    // Clear the tracking email so the dashboard isn't shown and the form appears
    $track_url_email = '';
    // Optional: We no longer delete rejected records to keep track of history
}

// 2. HANDLE CORRECTION SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_correction'])) {
    if ($conn) {
        $cid = intval($_POST['customer_id']);
        $flagged = explode(',', $_POST['flagged_fields'] ?? '');
        $update_parts = [];
        $resub_tracker = [];
        $upload_dir = "app.cbfinance.rw/uploads/documents/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        foreach ($flagged as $field) {
            if (empty($field)) continue;
            
            if (strpos($field, 'doc_') === 0) {
                if (isset($_FILES[$field]) && $_FILES[$field]['error'] == 0) {
                    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                    $filename = $field . "_" . time() . "_" . mt_rand(100, 999) . "." . $ext;
                    if(move_uploaded_file($_FILES[$field]['tmp_name'], $upload_dir . $filename)) {
                        $path = "uploads/documents/" . $filename;
                        $update_parts[] = "$field = '$path'";
                        $resub_tracker[] = $field;
                    }
                }
            } else {
                if (isset($_POST[$field])) {
                    $val = $conn->real_escape_string($_POST[$field]);
                    $update_parts[] = "$field = '$val'";
                    $resub_tracker[] = $field;
                }
            }
        }

        if (!empty($update_parts)) {
            $resub_json = implode(',', $resub_tracker);
            $sql = "UPDATE customers SET " . implode(', ', $update_parts) . ", status = 'Pending', client_resubmitted = 1, resubmitted_fields = '$resub_json' WHERE customer_id = $cid";
            if ($conn->query($sql)) { $success = "updated"; }
            else { $error = "Update failed: " . $conn->error; }
        }
    }
}

// 3. HANDLE NEW APPLICATION
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_application'])) {
    if ($conn) {
        $email = $conn->real_escape_string(trim($_POST['email']));
        
        // Final sanity check for existing active applications
        $check = $conn->query("SELECT customer_id, status FROM customers WHERE email = '$email' ORDER BY created_at DESC LIMIT 1");
        if ($check && $check->num_rows > 0) {
            $existing = $check->fetch_assoc();
            $curr_stat = strtolower(trim($existing['status']));
            
            // Fallback for DB enum issues
            if (empty($curr_stat) && !empty($existing['correction_fields'])) {
                $curr_stat = 'action required';
            }
            
            if ($curr_stat == 'rejected') {
                // Keep the record for history, allow fresh application
                // No action needed here, just let it proceed to INSERT
            } elseif ($curr_stat == 'action required') {
                $already_applied = true;
                $error = "CORRECTION NEEDED: Admin has requested updates on your previous application. Please use the tracking bar at the top right to fix and resubmit.";
            } elseif ($curr_stat == 'pending') {
                $already_applied = true;
                $error = "You already have a PENDING application. Our team is reviewing it. Use the tracking bar above for live updates.";
            } elseif ($curr_stat == 'approved') {
                $already_applied = true;
                $error = "Your previous application for this email was already APPROVED. Please contact support if you need a new loan.";
            } else {
                // Handle cases like empty status or unknown
                $conn->query("DELETE FROM customers WHERE customer_id = " . $existing['customer_id']);
            }
        }
        
        if (!$already_applied) {
            $data = [];
            foreach($_POST as $key => $val) { $data[$key] = $conn->real_escape_string($val); }
            
            // Define upload directory - pointing into the app directory so admin can see them
            $upload_dir = "app.cbfinance.rw/uploads/documents/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $doc_paths = [];
            $required_files = ($data['loan_type'] == 'Salary') ? 
                ['doc_id', 'doc_contract', 'doc_statement', 'doc_payslip', 'doc_marital'] : 
                ['doc_id', 'doc_rdb', 'doc_statement_alt', 'doc_marital'];

            foreach ($required_files as $df) {
                if (isset($_FILES[$df]) && $_FILES[$df]['error'] == 0) {
                    $ext = strtolower(pathinfo($_FILES[$df]['name'], PATHINFO_EXTENSION));
                    $fname = $df . "_" . time() . "_" . mt_rand(10, 99) . "." . $ext;
                    move_uploaded_file($_FILES[$df]['tmp_name'], $upload_dir . $fname);
                    // Store the relative path from the app root for DB
                    $doc_paths[$df] = "uploads/documents/" . $fname;
                }
            }

            if (isset($doc_paths['doc_statement_alt'])) $doc_paths['doc_statement'] = $doc_paths['doc_statement_alt'];
            $cols = ['doc_id', 'doc_contract', 'doc_statement', 'doc_payslip', 'doc_marital', 'doc_rdb'];
            foreach($cols as $c) if(!isset($doc_paths[$c])) $doc_paths[$c] = null;

            $code = "PEND-" . mt_rand(100000, 999999);
            $sql = "INSERT INTO customers (
                customer_code, customer_name, email, phone, id_number, loan_type, 
                requested_amount, loan_duration,
                doc_id, doc_contract, doc_statement, doc_payslip, doc_marital, doc_rdb,
                birth_place, account_number, occupation, gender, date_of_birth,
                spouse, spouse_occupation, spouse_phone, marriage_type,
                province, address, location, project, project_location, caution_location, status
            ) VALUES (
                '$code', '$data[customer_name]', '$email', '$data[phone]', '$data[nationalId]', '$data[loan_type]',
                '$data[requested_amount]', '$data[loan_duration]',
                '{$doc_paths['doc_id']}', '{$doc_paths['doc_contract']}', '{$doc_paths['doc_statement']}', '{$doc_paths['doc_payslip']}', '{$doc_paths['doc_marital']}', '{$doc_paths['doc_rdb']}',
                '$data[birth_place]', '$data[account_number]', '$data[occupation]', '$data[gender]', '$data[dob]',
                '$data[spouse]', '$data[spouse_occupation]', '$data[spouse_phone]', '$data[marriage_type]',
                '$data[province]', '$data[address]', '$data[location]', '$data[project]', '$data[project_location]', '$data[caution_location]', 'Pending'
            )";

            if ($conn->query($sql)) $success = "created";
            else $error = "Db Error: " . $conn->error;
        }
    }
}

// 4. HANDLE TRACKING SEARCH (Two-step: email + national ID)
if (!empty($track_url_email) && !isset($_POST['submit_application']) && !isset($_POST['submit_correction'])) {
    if ($conn && !empty($track_url_nid)) {
        // Both email and NID provided — verify together
        $safe_email = $conn->real_escape_string($track_url_email);
        $safe_nid   = $conn->real_escape_string($track_url_nid);
        $res = $conn->query("SELECT * FROM customers WHERE email = '$safe_email' AND id_number = '$safe_nid' ORDER BY created_at DESC LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $found_customer = $res->fetch_assoc();
            $success = "found";
            $nid_step = false;
        } elseif (!isset($_GET['reapply'])) {
            // Check if email alone exists (don't reveal which field failed for security)
            $email_check = $conn->query("SELECT customer_id FROM customers WHERE email = '$safe_email' LIMIT 1");
            if ($email_check && $email_check->num_rows > 0) {
                $error = "Verification failed. The National ID does not match our records for this email.";
            } else {
                $error = "No application found for this email.";
            }
            $nid_step = false;
        }
    }
    // If only email given ($nid_step = true), we just show the NID input — no DB query yet
}
?>

<main class="min-h-screen py-16 px-4 md:px-0 gradient-bg font-sans">
    <div class="max-w-4xl mx-auto">
        
        <!-- TRACKING BAR -->
        <div class="mb-12 flex justify-end">
            <?php if ($nid_step): ?>
                <!-- Step 2: NID verification -->
                <form action="" method="GET" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 p-3 glass-panel rounded-2xl border border-white/20 shadow-2xl">
                    <input type="hidden" name="track_email" value="<?php echo htmlspecialchars($track_url_email); ?>">
                    <div class="flex flex-col">
                        <span class="text-[9px] font-black uppercase tracking-widest text-white/70 mb-1 pl-1">Verify your identity</span>
                        <input type="text" name="track_nid" placeholder="Enter your 16-digit National ID" required maxlength="16" pattern="[0-9]{16}"
                               class="bg-white/95 rounded-xl px-5 py-3 text-xs text-black outline-none w-72 font-bold border-0 shadow-inner">
                    </div>
                    <button type="submit" class="bg-primary-green hover:bg-green-600 text-white px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg">
                        <i class="fas fa-shield-alt mr-1"></i> Verify
                    </button>
                    <a href="apply.php" class="bg-white/20 text-white px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg text-center">Cancel</a>
                </form>
            <?php else: ?>
                <!-- Step 1: Email -->
                <form action="" method="GET" class="flex p-2 glass-panel rounded-2xl border border-white/20 shadow-2xl">
                    <input type="email" name="track_email" placeholder="Track existing application..." required
                           class="bg-white/95 rounded-xl px-5 py-3 text-xs text-black outline-none w-64 font-bold border-0 shadow-inner"
                           value="<?php echo htmlspecialchars($track_url_email); ?>">
                    <button type="submit" class="bg-primary-blue hover:bg-blue-600 text-white px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all ml-2 shadow-lg">Track</button>
                </form>
            <?php endif; ?>      
        </div>

        <?php if ($success === "found" && isset($found_customer)): ?>
            <!-- DASHBOARD VIEW -->
            <div class="glass-card rounded-[3rem] shadow-2xl p-10 md:p-16 text-center border border-white/40 animate-in">
                <h2 class="text-3xl font-black text-gray-900 mb-2">Hello, <?php echo htmlspecialchars(explode(' ', $found_customer['customer_name'])[0]); ?></h2>
                <div class="inline-block px-4 py-1.5 bg-blue-50 rounded-full text-[10px] font-black text-primary-blue uppercase tracking-widest mb-10 shadow-sm">ID: <?php echo $found_customer['customer_code']; ?></div>

                <div class="max-w-2xl mx-auto p-12 rounded-[2.5rem] bg-white shadow-2xl border border-gray-100 relative">
                    <?php 
                    $stat = trim($found_customer['status']);
                    // Fallback for cases where DB enum doesn't yet support 'Action Required'
                    if (empty($stat) && !empty($found_customer['correction_fields'])) {
                        $stat = 'Action Required';
                    }

                    if ($stat == 'Pending'): ?>
                        <div class="w-20 h-20 bg-amber-50 text-amber-500 rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-sm border border-amber-100"><i class="fas fa-clock text-3xl"></i></div>
                        <h4 class="text-2xl font-black text-gray-800 uppercase tracking-tight">Review In Progress</h4>
                        <p class="text-sm text-gray-500 mt-4 leading-relaxed font-bold">We are currently verifying your credentials. You will be notified shortly.</p>
                        
                    <?php elseif ($stat == 'Approved'): ?>
                        <div class="w-20 h-20 bg-emerald-50 text-emerald-500 rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-sm border border-emerald-100"><i class="fas fa-check-double text-3xl"></i></div>
                        <h4 class="text-2xl font-black text-gray-800 uppercase tracking-tight">Congratulations!</h4>
                        <p class="text-sm text-gray-500 mt-4 font-bold">Your application has been approved.</p>
                        <div class="mt-8 p-6 bg-emerald-50 rounded-2xl border border-emerald-100 text-emerald-800 text-xs font-black shadow-inner">
                            <i class="fas fa-map-marked-alt text-xl mb-4 block"></i>
                            VISIT IKAZE HOUSE, 2ND FLOOR <br> OR CALL +250 796 880 272
                        </div>

                    <?php elseif ($stat == 'Action Required'): ?>
                        <div class="w-20 h-20 bg-blue-50 text-primary-blue rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-sm border border-blue-100"><i class="fas fa-file-signature text-3xl"></i></div>
                        <h4 class="text-2xl font-black text-gray-800 uppercase tracking-tight">Updates Needed</h4>
                        <div class="mt-6 p-6 bg-blue-50/50 rounded-2xl border border-blue-100 text-left mb-10">
                            <span class="text-[9px] font-black text-primary-blue uppercase tracking-widest block mb-2">Message from Admin:</span>
                            <p class="text-sm text-blue-900 font-bold italic">"<?php echo htmlspecialchars($found_customer['admin_note'] ?: 'Please fix the fields listed below.'); ?>"</p>
                        </div>
                        
                        <div class="text-left border-t pt-10">
                            <form action="" method="POST" enctype="multipart/form-data" class="space-y-8">
                                <input type="hidden" name="customer_id" value="<?php echo $found_customer['customer_id']; ?>">
                                <input type="hidden" name="flagged_fields" value="<?php echo $found_customer['correction_fields']; ?>">
                                <?php 
                                $fl = explode(',', $found_customer['correction_fields']);
                                $lbls = [
                                    'customer_name' => 'Name', 'id_number' => 'National ID', 'phone' => 'Phone',
                                    'email' => 'Email', 'doc_id' => 'ID Doc', 'doc_contract' => 'Contract',
                                    'doc_statement' => 'Statement', 'doc_payslip' => 'Payslip', 
                                    'doc_marital' => 'Marital Doc', 'doc_rdb' => 'RDB Cert'
                                ];
                                foreach($fl as $f): 
                                    if(empty($f)) continue;
                                ?>
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-black uppercase text-gray-400 pl-2"><?php echo $lbls[$f] ?? $f; ?> *</label>
                                        <?php if(strpos($f, 'doc_') === 0): ?>
                                            <div class="relative group">
                                                <input type="file" name="<?php echo $f; ?>" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="fnUpdate(this)">
                                                <div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-2xl flex items-center justify-between group-hover:border-primary-blue transition-all">
                                                    <span class="file-name-display fn-display text-xs font-bold text-gray-400">Click to Select New File...</span>
                                                    <span class="bg-gray-100 px-4 py-2 rounded-xl text-[9px] font-black">CHOOSE</span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <input type="text" name="<?php echo $f; ?>" required class="w-full text-sm bg-gray-50 p-4 rounded-2xl border border-gray-100 outline-none focus:bg-white focus:border-primary-blue transition-all font-bold" value="<?php echo htmlspecialchars($found_customer[$f]); ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <button type="submit" name="submit_correction" class="w-full bg-primary-blue text-white py-5 rounded-2xl font-black text-xs shadow-2xl hover:bg-blue-700 transition-all active:scale-95">SEND UPDATES NOW</button>
                            </form>
                        </div>

                    <?php else: ?>
                        <!-- REJECTED -->
                        <div class="w-20 h-20 bg-rose-50 text-rose-500 rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-sm border border-rose-100"><i class="fas fa-ban text-3xl"></i></div>
                        <h4 class="text-2xl font-black text-gray-800 uppercase tracking-tight">Application Rejected</h4>
                        <div class="mt-6 p-6 bg-rose-50/50 rounded-2xl border border-rose-100 text-left mb-10">
                            <span class="text-[9px] font-black text-rose-500 uppercase tracking-widest block mb-2">Notice:</span>
                            <p class="text-sm text-rose-900 font-bold italic">"<?php echo htmlspecialchars($found_customer['rejection_reason'] ?: 'Eligibility criteria not met.'); ?>"</p>
                        </div>
                        <a href="apply.php?reapply=true&track_email=<?php echo urlencode($found_customer['email']); ?>" class="bg-primary-blue text-white px-10 py-5 rounded-2xl font-black text-xs shadow-2xl hover:bg-blue-700 transition-all">START NEW APPLICATION</a>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($success === "updated" || $success === "created"): ?>
            <div class="glass-card rounded-[3rem] p-16 text-center shadow-2xl animate-in">
                <div class="w-24 h-24 bg-emerald-500 text-white rounded-[2.5rem] flex items-center justify-center mx-auto mb-10 shadow-2xl shadow-emerald-200"><i class="fas fa-check text-4xl"></i></div>
                <h3 class="text-4xl font-black text-gray-900 mb-4"><?php echo ($success === 'updated') ? 'Resubmitted!' : 'Application Sent!'; ?></h3>
                <p class="text-gray-500 text-sm font-bold max-w-sm mx-auto mb-6">Our team has received your details. Track live status using your email anytime.</p>
                <div class="bg-amber-50 p-6 rounded-2xl border border-amber-100 max-w-md mx-auto">
                    <p class="text-[11px] text-amber-900 font-black uppercase tracking-widest mb-2"><i class="fas fa-exclamation-triangle me-2"></i> Final Confirmation Required</p>
                    <p class="text-[12px] text-amber-800 font-bold leading-relaxed">Please note: Whatever collateral is needed for your specific loan must be physically provided and verified at our offices before final approval can be granted.</p>
                </div>
                <div class="mt-12 flex gap-4 justify-center">
                    <a href="index.php" class="bg-gray-100 text-gray-600 px-10 py-5 rounded-2xl font-black text-xs hover:bg-gray-200 transition-all shadow-sm">HOME</a>
                    <a href="apply.php" class="bg-primary-blue text-white px-10 py-5 rounded-2xl font-black text-xs shadow-xl">FINISH</a>
                </div>
            </div>

        <?php else: ?>
            <!-- NEW APPLICATION FORM -->
            <div id="form-container" class="glass-card rounded-[3rem] shadow-2xl p-10 md:p-16 border border-white/40 animate-in">
                <div class="text-center mb-16">
                    <h1 class="text-4xl font-black text-gray-900 mb-3 tracking-tighter">Instant Loan Portal</h1>
                    <div class="h-1.5 w-24 bg-primary-green mx-auto rounded-full mb-4"></div>
                </div>

                <?php if ($error): ?>
                    <div class="bg-rose-500 text-white p-5 rounded-2xl mb-12 text-xs text-center font-black shadow-2xl shadow-rose-200 animate-pulse border-0"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form id="loanForm" method="POST" enctype="multipart/form-data" class="space-y-16">
                    <input type="hidden" name="submit_application" value="1">
                    
                    <!-- STEP 1 -->
                    <div id="step-1" class="form-step space-y-12">
                        <div class="bg-gray-50 p-6 md:p-10 rounded-[2.5rem] border border-gray-100 shadow-inner">
                            <label class="block text-[10px] font-black text-gray-400 mb-6 md:mb-10 text-center tracking-[0.5em] uppercase">Choose Application Path</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-8">
                                <label class="cursor-pointer group">
                                    <input type="radio" name="loan_type" value="Salary" required class="hidden peer" onchange="udf()">
                                    <div class="bg-white border-4 border-transparent peer-checked:border-primary-green p-5 md:p-8 rounded-3xl text-center transition-all shadow-xl group-hover:scale-[1.03]">
                                        <i class="fas fa-wallet text-xl md:text-2xl text-primary-green mb-3 md:mb-4 block"></i>
                                        <span class="block text-[10px] md:text-xs font-black uppercase text-gray-800">Salary Loan</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer group">
                                    <input type="radio" name="loan_type" value="Business" required class="hidden peer" onchange="udf()">
                                    <div class="bg-white border-4 border-transparent peer-checked:border-primary-blue p-5 md:p-8 rounded-3xl text-center transition-all shadow-xl group-hover:scale-[1.03]">
                                        <i class="fas fa-landmark text-xl md:text-2xl text-primary-blue mb-3 md:mb-4 block"></i>
                                        <span class="block text-[10px] md:text-xs font-black uppercase text-gray-800">Business Loan</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4 tracking-widest">Full Name</label>
                                <input type="text" name="customer_name" id="customer_name" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold shadow-sm outline-none focus:border-primary-blue transition-all placeholder:text-gray-200" placeholder="e.g. John Doe">
                                <p class="err-msg text-[10px] text-rose-500 font-bold pl-4 hidden"></p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4 tracking-widest">Email Address</label>
                                <input type="email" name="email" id="email" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold shadow-sm outline-none focus:border-primary-blue transition-all" placeholder="name@example.com">
                                <p class="err-msg text-[10px] text-rose-500 font-bold pl-4 hidden"></p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4 tracking-widest">Phone</label>
                                <input type="tel" name="phone" id="phone" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold shadow-sm outline-none focus:border-primary-blue transition-all" placeholder="07XXXXXXXX">
                                <p class="err-msg text-[10px] text-rose-500 font-bold pl-4 hidden"></p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4 tracking-widest">National ID</label>
                                <input type="text" name="nationalId" id="nationalId" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold shadow-sm outline-none focus:border-primary-blue transition-all" placeholder="119XXXXXXXXXXXXX">
                                <p class="err-msg text-[10px] text-rose-500 font-bold pl-4 hidden"></p>
                            </div>
                        </div>

                        <button type="button" onclick="np(1)" class="w-full bg-primary-blue text-white py-5 rounded-2xl font-black text-xs shadow-2xl hover:bg-blue-700 transition-all flex items-center justify-center gap-3 active:scale-95">CONTINUE <i class="fas fa-arrow-right text-[10px]"></i></button>
                    </div>

                    <!-- STEP 2 -->
                    <div id="step-2" class="form-step space-y-10 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4">Birth Place</label>
                                <input type="text" name="birth_place" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4">Gender</label>
                                <select name="gender" class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold">
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4">Date of Birth</label>
                                <input type="date" name="dob" id="dob" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold">
                                <p class="err-msg text-[10px] text-rose-500 font-bold pl-4 hidden"></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4">Loan Amount Requested (RWF)</label>
                                <input type="number" name="requested_amount" id="requested_amount" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold shadow-sm outline-none focus:border-primary-blue transition-all" placeholder="e.g. 500000">
                                <p class="err-msg text-[10px] text-rose-500 font-bold pl-4 hidden"></p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4">Repayment Period (Months)</label>
                                <input type="number" name="loan_duration" id="loan_duration" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold shadow-sm outline-none focus:border-primary-blue transition-all" placeholder="e.g. 12">
                                <p class="err-msg text-[10px] text-rose-500 font-bold pl-4 hidden"></p>
                            </div>
                        </div>
                        <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Marriage Status</label><select name="marriage_type" id="marriage_type" onchange="tsf()" class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"><option value="Single">Single</option><option value="Ivanga mutungo">Ivanga</option><option value="Ivangura mutungo">Ivangura</option><option value="Muhahano">Muhahano</option></select></div>
                        <div id="spouse_fields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-8"><div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Spouse Name</label><input type="text" name="spouse" class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div><div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Spouse Phone</label><input type="tel" name="spouse_phone" class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div></div>
                        <div class="flex gap-4 pt-10"><button type="button" onclick="np(-1)" class="w-1/3 bg-gray-100 p-5 rounded-2xl font-black text-gray-400 text-xs">BACK</button><button type="button" onclick="np(1)" class="w-2/3 bg-primary-blue text-white p-5 rounded-2xl font-black text-xs shadow-2xl">NEXT: WORK INFO</button></div>
                    </div>

                    <!-- STEP 3 -->
                    <div id="step-3" class="form-step space-y-10 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8"><div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Occupation</label><input type="text" name="occupation" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div><div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Account Number</label><input type="text" name="account_number" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div></div>
                        <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Resident Address</label><input type="text" name="address" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                            <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Project Purpose</label><input type="text" name="project" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold" placeholder="e.g. Household expansion"></div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4">Select Province</label>
                                <select name="province" id="province" required onchange="updateDistricts()" class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold">
                                    <option value="">Choose Province...</option>
                                    <option value="Kigali City">Kigali City</option>
                                    <option value="Eastern Province">Eastern Province</option>
                                    <option value="Northern Province">Northern Province</option>
                                    <option value="Southern Province">Southern Province</option>
                                    <option value="Western Province">Western Province</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase text-gray-400 pl-4">Select District</label>
                                <select name="location" id="district" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold">
                                    <option value="">Choose District...</option>
                                </select>
                            </div>
                            <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Project Loc.</label><input type="text" name="project_location" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div>
                            <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Caution Loc.</label><input type="text" name="caution_location" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div>
                        </div>
                        <div class="flex gap-4 pt-10"><button type="button" onclick="np(-1)" class="w-1/3 bg-gray-100 p-5 rounded-2xl font-black text-gray-400 text-xs">BACK</button><button type="button" onclick="np(1)" class="w-2/3 bg-primary-blue text-white p-5 rounded-2xl font-black text-xs shadow-2xl">NEXT: FILES</button></div>
                    </div>

                    <!-- STEP 4 -->
                    <div id="step-4" class="form-step space-y-12 hidden">
                        <div class="bg-primary-blue text-white p-8 rounded-[2.5rem] shadow-2xl flex items-center justify-between"><h4 class="text-xs font-black uppercase tracking-widest"><i class="fas fa-cloud-upload-alt text-xl me-3"></i> Upload Required Evidence</h4><i class="fas fa-lock opacity-30 text-2xl"></i></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-3 group"><label class="text-[10px] font-black text-gray-400 pl-3 uppercase">National ID *</label><div class="relative"><input type="file" name="doc_id" required accept=".pdf,.jpg,.jpeg,.png" class="absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer" onchange="fnUpdate(this)"><div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-2xl flex justify-between group-hover:border-primary-blue transition-all"><span class="text-xs font-bold text-gray-400 fn-display">Upload File...</span><span class="bg-gray-100 text-[8px] font-black px-3 py-1.5 rounded-lg">BROWSE</span></div></div></div>
                            <div class="space-y-3 group"><label class="text-[10px] font-black text-gray-400 pl-3 uppercase">Marital Doc *</label><div class="relative"><input type="file" name="doc_marital" required accept=".pdf,.jpg,.jpeg,.png" class="absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer" onchange="fnUpdate(this)"><div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-2xl flex justify-between group-hover:border-primary-blue transition-all"><span class="text-xs font-bold text-gray-400 fn-display">Upload File...</span><span class="bg-gray-100 text-[8px] font-black px-3 py-1.5 rounded-lg">BROWSE</span></div></div></div>
                            <div class="space-y-3 group doc-salary"><label class="text-[10px] font-black text-gray-400 pl-3 uppercase">Job Contract *</label><div class="relative"><input type="file" name="doc_contract" accept=".pdf,.jpg,.jpeg,.png" class="sreq absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer" onchange="fnUpdate(this)"><div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-2xl flex justify-between group-hover:border-primary-blue transition-all"><span class="text-xs font-bold text-gray-400 fn-display">Upload File...</span><span class="bg-gray-100 text-[8px] font-black px-3 py-1.5 rounded-lg">BROWSE</span></div></div></div>
                            <div class="space-y-3 group doc-salary"><label class="text-[10px] font-black text-gray-400 pl-3 uppercase">Statement *</label><div class="relative"><input type="file" name="doc_statement" accept=".pdf,.jpg,.jpeg,.png" class="sreq absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer" onchange="fnUpdate(this)"><div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-2xl flex justify-between group-hover:border-primary-blue transition-all"><span class="text-xs font-bold text-gray-400 fn-display">Upload File...</span><span class="bg-gray-100 text-[8px] font-black px-3 py-1.5 rounded-lg">BROWSE</span></div></div></div>
                            <div class="space-y-3 group doc-salary"><label class="text-[10px] font-black text-gray-400 pl-3 uppercase">Payslip *</label><div class="relative"><input type="file" name="doc_payslip" accept=".pdf,.jpg,.jpeg,.png" class="sreq absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer" onchange="fnUpdate(this)"><div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-2xl flex justify-between group-hover:border-primary-blue transition-all"><span class="text-xs font-bold text-gray-400 fn-display">Upload File...</span><span class="bg-gray-100 text-[8px] font-black px-3 py-1.5 rounded-lg">BROWSE</span></div></div></div>
                            <div class="space-y-3 group doc-business hidden"><label class="text-[10px] font-black text-gray-400 pl-3 uppercase">RDB Cert *</label><div class="relative"><input type="file" name="doc_rdb" accept=".pdf,.jpg,.jpeg,.png" class="breq absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer" onchange="fnUpdate(this)"><div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-2xl flex justify-between group-hover:border-primary-blue transition-all"><span class="text-xs font-bold text-gray-400 fn-display">Upload File...</span><span class="bg-gray-100 text-[8px] font-black px-3 py-1.5 rounded-lg">BROWSE</span></div></div></div>
                            <div class="space-y-3 group doc-business hidden"><label class="text-[10px] font-black text-gray-400 pl-3 uppercase">Business Statement *</label><div class="relative"><input type="file" name="doc_statement_alt" accept=".pdf,.jpg,.jpeg,.png" class="breq absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer" onchange="fnUpdate(this)"><div class="p-5 bg-white border-2 border-dashed border-gray-100 rounded-2xl flex justify-between group-hover:border-primary-blue transition-all"><span class="text-xs font-bold text-gray-400 fn-display">Upload File...</span><span class="bg-gray-100 text-[8px] font-black px-3 py-1.5 rounded-lg">BROWSE</span></div></div></div>
                        </div>
                        <div class="flex gap-4 mt-16 pt-10 border-t border-gray-50"><button type="button" onclick="np(-1)" class="w-1/3 bg-gray-50 p-6 rounded-3xl font-black text-gray-300 text-xs">GO BACK</button><button type="submit" name="submit_application" class="w-2/3 bg-primary-green text-white p-6 rounded-3xl font-black text-xs shadow-2xl hover:scale-[1.03] active:scale-95 transition-all">SUBMIT FINAL APPLICATION</button></div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
const rwData = {
    'Kigali City': ['Gasabo', 'Kicukiro', 'Nyarugenge'],
    'Eastern Province': ['Bugesera', 'Gatsibo', 'Kayonza', 'Kirehe', 'Ngoma', 'Nyagatare', 'Rwamagana'],
    'Northern Province': ['Burera', 'Gakenke', 'Gicumbi', 'Musanze', 'Rulindo'],
    'Southern Province': ['Gisagara', 'Huye', 'Kamonyi', 'Muhanga', 'Nyamagabe', 'Nyanza', 'Nyaruguru', 'Ruhango'],
    'Western Province': ['Karongi', 'Ngororero', 'Nyabihu', 'Nyamasheke', 'Rubavu', 'Rusizi', 'Rutsiro']
};

function updateDistricts() {
    const prov = document.getElementById('province').value;
    const distSelect = document.getElementById('district');
    distSelect.innerHTML = '<option value="">Choose District...</option>';
    if (rwData[prov]) {
        rwData[prov].forEach(d => {
            const opt = document.createElement('option');
            opt.value = d;
            opt.innerText = d;
            distSelect.appendChild(opt);
        });
    }
}

let cs = 1;

function showError(id, msg) {
    const input = document.getElementById(id);
    const err = input.parentElement.querySelector('.err-msg');
    if (err) {
        err.innerText = msg;
        err.classList.remove('hidden');
        input.classList.add('border-rose-400');
    }
}

function clearErrors() {
    document.querySelectorAll('.err-msg').forEach(e => e.classList.add('hidden'));
    document.querySelectorAll('input, select').forEach(i => i.classList.remove('border-rose-400'));
}

function val() {
    clearErrors();
    const active = document.getElementById("step-" + cs);
    const inputs = active.querySelectorAll("input[required], select[required]");
    let ok = true;

    // Basic required check
    for (let i of inputs) {
        if (!i.value) {
            i.classList.add("border-rose-400");
            ok = false;
        }
    }

    // Advanced Validation
    if (cs === 1) {
        const name = document.getElementById('customer_name').value.trim();
        if (name && name.split(' ').length < 2) {
            showError('customer_name', 'Please provide at least 2 names.');
            ok = false;
        }

        const phone = document.getElementById('phone').value.trim();
        if (phone && !/^(078|079|072|073)[0-9]{7}$/.test(phone)) {
            showError('phone', 'Please enter a valid 10-digit phone number (e.g. 078XXXXXXX).');
            ok = false;
        }

        const nid = document.getElementById('nationalId').value.trim();
        if (nid && nid.length !== 16) {
            showError('nationalId', 'Invalid ID. Must be exactly 16 digits.');
            ok = false;
        }
    }

    if (cs === 2) {
        const dob = new Date(document.getElementById('dob').value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        
        if (age < 18) {
            showError('dob', 'Access denied. You must be at least 18 years old.');
            ok = false;
        }

        const amt = document.getElementById('requested_amount').value;
        if (amt && amt <= 0) {
            showError('requested_amount', 'Please enter a valid amount.');
            ok = false;
        }
    }

    return ok;
}

function fnUpdate(i) {
    const d = i.parentElement.querySelector('.fn-display');
    if (i.files.length > 0) { d.innerText = i.files[0].name; d.classList.remove('text-gray-400'); d.classList.add('text-primary-blue'); }
}

function np(n) {
    const s = document.querySelectorAll(".form-step");
    if (n === 1 && !val()) return;
    s[cs-1].classList.add("hidden");
    cs += n;
    s[cs-1].classList.remove("hidden");
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function tsf() {
    const v = document.getElementById('marriage_type').value;
    const f = document.getElementById('spouse_fields');
    if (v === 'Single') f.classList.add('hidden'); else f.classList.remove('hidden');
}

function udf() {
    const r = document.querySelector('input[name="loan_type"]:checked');
    if(!r) return;
    const t = r.value;
    const sd = document.querySelectorAll('.doc-salary'), bd = document.querySelectorAll('.doc-business');
    const sr = document.querySelectorAll('.sreq'), br = document.querySelectorAll('.breq');
    if (t === 'Salary') { sd.forEach(x => x.classList.remove('hidden')); bd.forEach(x => x.classList.add('hidden')); sr.forEach(x => x.required = true); br.forEach(x => x.required = false); }
    else { sd.forEach(x => x.classList.add('hidden')); bd.forEach(x => x.classList.remove('hidden')); sr.forEach(x => x.required = false); br.forEach(x => x.required = true); }
}
</script>

<style>
@keyframes fi { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.animate-in { animation: fi 0.5s ease-out forwards; }
.gradient-bg { background: linear-gradient(135deg, #0f172a 0%, #1e40af 100%); }
.glass-panel { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(14px); }
.glass-card { background: #ffffff; }
input:focus, select:focus { border-color: #1e40af !important; box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.05); }
.border-rose-400 { border-color: #fb7185 !important; border-width: 2px !important; }
</style>

<?php 
include 'includes/bottom_nav.php'; 
include 'includes/footer.php'; 
?>
