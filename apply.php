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

// 1. HANDLE RE-APPLY (DELETE OLD REJECTED RECORD)
if (isset($_GET['reapply']) && $_GET['reapply'] == 'true' && !empty($track_url_email)) {
    if ($conn) {
        $clean_email = $conn->real_escape_string($track_url_email);
        // Only delete if it's actually rejected to allow a fresh start
        $conn->query("DELETE FROM customers WHERE email = '$clean_email' AND (status = 'Rejected' OR status = 'Rejected ')");
    }
}

// 2. HANDLE CORRECTION SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_correction'])) {
    if ($conn) {
        $cid = intval($_POST['customer_id']);
        $flagged = explode(',', $_POST['flagged_fields'] ?? '');
        $update_parts = [];
        $resub_tracker = [];
        $upload_dir = "uploads/documents/";

        foreach ($flagged as $field) {
            if (empty($field)) continue;
            
            if (strpos($field, 'doc_') === 0) {
                if (isset($_FILES[$field]) && $_FILES[$field]['error'] == 0) {
                    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                    $filename = $field . "_" . time() . "_" . mt_rand(100, 999) . "." . $ext;
                    if(move_uploaded_file($_FILES[$field]['tmp_name'], $upload_dir . $filename)) {
                        $path = $upload_dir . $filename;
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
            if ($curr_stat != 'rejected') {
                $already_applied = true;
                $error = "You already have an active application ($existing[status]). Use the tracking bar above.";
            } else {
                // If rejected, remove it to prevent unique key/email conflicts
                $conn->query("DELETE FROM customers WHERE customer_id = " . $existing['customer_id']);
            }
        }
        
        if (!$already_applied) {
            $data = [];
            foreach($_POST as $key => $val) { $data[$key] = $conn->real_escape_string($val); }
            
            $upload_dir = "uploads/documents/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $doc_paths = [];
            $required_files = ($data['loan_type'] == 'Salary') ? 
                ['doc_id', 'doc_contract', 'doc_statement', 'doc_payslip', 'doc_marital'] : 
                ['doc_id', 'doc_rdb', 'doc_statement_alt', 'doc_marital'];

            foreach ($required_files as $df) {
                if (isset($_FILES[$df]) && $_FILES[$df]['error'] == 0) {
                    $ext = strtolower(pathinfo($_FILES[$df]['name'], PATHINFO_EXTENSION));
                    $fname = $df . "_" . time() . "_" . mt_rand(10, 99) . "." . $ext;
                    move_uploaded_file($_FILES[$df]['tmp_name'], $upload_dir . $fname);
                    $doc_paths[$df] = $upload_dir . $fname;
                }
            }

            if (isset($doc_paths['doc_statement_alt'])) $doc_paths['doc_statement'] = $doc_paths['doc_statement_alt'];
            $cols = ['doc_id', 'doc_contract', 'doc_statement', 'doc_payslip', 'doc_marital', 'doc_rdb'];
            foreach($cols as $c) if(!isset($doc_paths[$c])) $doc_paths[$c] = null;

            $code = "PEND-" . mt_rand(100000, 999999);
            $sql = "INSERT INTO customers (
                customer_code, customer_name, email, phone, id_number, loan_type, 
                doc_id, doc_contract, doc_statement, doc_payslip, doc_marital, doc_rdb,
                birth_place, account_number, occupation, gender, date_of_birth,
                father_name, mother_name, spouse, spouse_occupation, spouse_phone, marriage_type,
                address, location, project, project_location, caution_location, status
            ) VALUES (
                '$code', '$data[customer_name]', '$email', '$data[phone]', '$data[nationalId]', '$data[loan_type]',
                '{$doc_paths['doc_id']}', '{$doc_paths['doc_contract']}', '{$doc_paths['doc_statement']}', '{$doc_paths['doc_payslip']}', '{$doc_paths['doc_marital']}', '{$doc_paths['doc_rdb']}',
                '$data[birth_place]', '$data[account_number]', '$data[occupation]', '$data[gender]', '$data[dob]',
                '$data[father_name]', '$data[mother_name]', '$data[spouse]', '$data[spouse_occupation]', '$data[spouse_phone]', '$data[marriage_type]',
                '$data[address]', '$data[location]', '$data[project]', '$data[project_location]', '$data[caution_location]', 'Pending'
            )";

            if ($conn->query($sql)) $success = "created";
            else $error = "Db Error: " . $conn->error;
        }
    }
}

// 4. HANDLE TRACKING SEARCH
if (!empty($track_url_email) && !isset($_POST['submit_application']) && !isset($_POST['submit_correction'])) {
    if ($conn) {
        $res = $conn->query("SELECT * FROM customers WHERE email = '" . $conn->real_escape_string($track_url_email) . "' ORDER BY created_at DESC LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $found_customer = $res->fetch_assoc();
            $success = "found";
        } elseif(!isset($_GET['reapply'])) {
            $error = "No application found for this email.";
        }
    }
}
?>

<main class="min-h-screen py-16 px-4 md:px-0 gradient-bg font-sans">
    <div class="max-w-4xl mx-auto">
        
        <!-- TRACKING BAR -->
        <div class="mb-12 flex justify-end">
            <form action="" method="GET" class="flex p-2 glass-panel rounded-2xl border border-white/20 shadow-2xl">
                <input type="email" name="track_email" placeholder="Track existing application..." required 
                       class="bg-white/95 rounded-xl px-5 py-3 text-xs text-black outline-none w-64 font-bold border-0 shadow-inner" 
                       value="<?php echo htmlspecialchars($track_url_email); ?>">
                <button type="submit" class="bg-primary-blue hover:bg-blue-600 text-white px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all ml-2 shadow-lg">Track</button>
            </form>
        </div>

        <?php if ($success === "found" && isset($found_customer)): ?>
            <!-- DASHBOARD VIEW -->
            <div class="glass-card rounded-[3rem] shadow-2xl p-10 md:p-16 text-center border border-white/40 animate-in">
                <h2 class="text-3xl font-black text-gray-900 mb-2">Hello, <?php echo htmlspecialchars(explode(' ', $found_customer['customer_name'])[0]); ?></h2>
                <div class="inline-block px-4 py-1.5 bg-blue-50 rounded-full text-[10px] font-black text-primary-blue uppercase tracking-widest mb-10 shadow-sm">ID: <?php echo $found_customer['customer_code']; ?></div>

                <div class="max-w-2xl mx-auto p-12 rounded-[2.5rem] bg-white shadow-2xl border border-gray-100 relative">
                    <?php 
                    $stat = trim($found_customer['status']);
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
                                                    <span class="file-name-display text-xs font-bold text-gray-400">Click to Select New File...</span>
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
                <p class="text-gray-500 text-sm font-bold max-w-sm mx-auto">Our team has received your details. Track live status using your email anytime.</p>
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
                        <div class="bg-gray-50 p-10 rounded-[2.5rem] border border-gray-100 shadow-inner">
                            <label class="block text-[10px] font-black text-gray-400 mb-10 text-center tracking-[0.5em] uppercase">Choose Application Path</label>
                            <div class="grid grid-cols-2 gap-8">
                                <label class="cursor-pointer group">
                                    <input type="radio" name="loan_type" value="Salary" required class="hidden peer" onchange="udf()">
                                    <div class="bg-white border-4 border-transparent peer-checked:border-primary-green p-8 rounded-3xl text-center transition-all shadow-xl group-hover:scale-[1.03]">
                                        <i class="fas fa-wallet text-2xl text-primary-green mb-4 block"></i>
                                        <span class="block text-xs font-black uppercase text-gray-800">Salary Loan</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer group">
                                    <input type="radio" name="loan_type" value="Business" required class="hidden peer" onchange="udf()">
                                    <div class="bg-white border-4 border-transparent peer-checked:border-primary-blue p-8 rounded-3xl text-center transition-all shadow-xl group-hover:scale-[1.03]">
                                        <i class="fas fa-landmark text-2xl text-primary-blue mb-4 block"></i>
                                        <span class="block text-xs font-black uppercase text-gray-800">Business Loan</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4 tracking-widest">Full Name</label><input type="text" name="customer_name" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold shadow-sm outline-none focus:border-primary-blue transition-all placeholder:text-gray-200" placeholder="e.g. John Doe"></div>
                            <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4 tracking-widest">Email Address</label><input type="email" name="email" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold shadow-sm outline-none focus:border-primary-blue transition-all" placeholder="name@example.com"></div>
                            <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4 tracking-widest">Phone</label><input type="tel" name="phone" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold shadow-sm outline-none focus:border-primary-blue transition-all" placeholder="07XXXXXXXX"></div>
                            <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4 tracking-widest">National ID</label><input type="text" name="nationalId" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold shadow-sm outline-none focus:border-primary-blue transition-all" placeholder="119XXXXXXXXXXXXX"></div>
                        </div>

                        <button type="button" onclick="np(1)" class="w-full bg-primary-blue text-white py-5 rounded-2xl font-black text-xs shadow-2xl hover:bg-blue-700 transition-all flex items-center justify-center gap-3 active:scale-95">CONTINUE <i class="fas fa-arrow-right text-[10px]"></i></button>
                    </div>

                    <!-- STEP 2 -->
                    <div id="step-2" class="form-step space-y-10 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Birth Place</label><input type="text" name="birth_place" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div>
                            <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Gender</label><select name="gender" class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"><option value="male">Male</option><option value="female">Female</option></select></div>
                            <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Date of Birth</label><input type="date" name="dob" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Father Name</label><input type="text" name="father_name" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div>
                            <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Mother Name</label><input type="text" name="mother_name" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div>
                        </div>
                        <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Marriage Status</label><select name="marriage_type" id="marriage_type" onchange="tsf()" class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"><option value="Single">Single</option><option value="Ivanga mutungo">Ivanga</option><option value="Ivangura mutungo">Ivangura</option><option value="Muhahano">Muhahano</option></select></div>
                        <div id="spouse_fields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-8"><div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Spouse Name</label><input type="text" name="spouse" class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div><div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Spouse Phone</label><input type="tel" name="spouse_phone" class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div></div>
                        <div class="flex gap-4 pt-10"><button type="button" onclick="np(-1)" class="w-1/3 bg-gray-100 p-5 rounded-2xl font-black text-gray-400 text-xs">BACK</button><button type="button" onclick="np(1)" class="w-2/3 bg-primary-blue text-white p-5 rounded-2xl font-black text-xs shadow-2xl">NEXT: WORK INFO</button></div>
                    </div>

                    <!-- STEP 3 -->
                    <div id="step-3" class="form-step space-y-10 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8"><div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Occupation</label><input type="text" name="occupation" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div><div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Account Number</label><input type="text" name="account_number" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div></div>
                        <div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Resident Address</label><input type="text" name="address" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6"><div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Project</label><input type="text" name="project" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div><div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Location</label><input type="text" name="location" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div><div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Project Loc.</label><input type="text" name="project_location" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div><div class="space-y-2"><label class="text-[10px] font-black uppercase text-gray-400 pl-4">Caution Loc.</label><input type="text" name="caution_location" required class="w-full p-4 bg-white border-2 border-gray-100 rounded-2xl text-sm font-bold"></div></div>
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
let cs = 1;
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
function val() {
    const active = document.getElementById("step-" + cs);
    const inputs = active.querySelectorAll("input[required], select[required], input[type='radio'][required]");
    let ok = true;
    for (let i of inputs) {
        if (i.type === 'radio') {
            const group = active.querySelectorAll(`input[name="${i.name}"]`);
            const checked = Array.from(group).some(r => r.checked);
            if (!checked) { ok = false; group[0].closest('.bg-gray-50').classList.add("border-rose-400"); }
        } else if (!i.value) { i.classList.add("border-rose-400"); ok = false; }
        else { i.classList.remove("border-rose-400"); if(i.name === 'loan_type') i.closest('.bg-gray-50').classList.remove("border-rose-400"); }
    }
    return ok;
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
