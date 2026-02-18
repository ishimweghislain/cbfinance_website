<?php 
require_once 'includes/db_connect.php';
include 'includes/head.php'; 
include 'includes/navbar.php'; 

$success = false;
$error = "";
$already_applied = false;

// Handle Status Check / Login
if (isset($_GET['track_email'])) {
    $conn = getWebsiteConnection();
    $track_email = $conn->real_escape_string($_GET['track_email']);
    $res = $conn->query("SELECT * FROM customers WHERE email = '$track_email' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $found_customer = $res->fetch_assoc();
        // Redirect to a dashboard-like view or show here
        $success = "found"; 
    } else {
        $error = "No application found with this email.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_application'])) {
    $conn = getWebsiteConnection();
    if ($conn) {
        $email = $conn->real_escape_string($_POST['email']);
        
        // Check if email already exists
        $check = $conn->query("SELECT customer_id, status FROM customers WHERE email = '$email'");
        if ($check && $check->num_rows > 0) {
            $existing = $check->fetch_assoc();
            if ($existing['status'] !== 'Rejected') {
                $already_applied = true;
                $error = "You have already applied. Please enter your email above to track your status.";
            } else {
                // It was rejected, so we'll allow re-submission by removing the rejected record
                $conn->query("DELETE FROM customers WHERE customer_id = " . $existing['customer_id']);
                // Code will now proceed to insert a new application
            }
        }
        
        if (!$already_applied) {
            // Process basic fields
            $customer_name = $conn->real_escape_string($_POST['customer_name']);
            $phone = $conn->real_escape_string($_POST['phone']);
            $id_number = $conn->real_escape_string($_POST['nationalId']);
            $loan_type = $conn->real_escape_string($_POST['loan_type']);
            // ... (other fields from previous version)
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
            
            // File Upload Handling
            $upload_dir = "uploads/documents/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $doc_paths = [];
            $required_docs = ($loan_type == 'Salary') ? 
                ['doc_id', 'doc_contract', 'doc_statement', 'doc_payslip'] : 
                ['doc_id', 'doc_rdb', 'doc_statement_alt'];
            
            if ($marriage_type !== 'Single') $required_docs[] = 'doc_marital';

            $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
            foreach ($required_docs as $doc_field) {
                if (isset($_FILES[$doc_field]) && $_FILES[$doc_field]['error'] == 0) {
                    $ext = strtolower(pathinfo($_FILES[$doc_field]['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed_exts)) {
                        $error = "Error: Only PDF, JPG, and PNG files are allowed for uploads.";
                        $already_applied = true; // Block submission
                        break;
                    }
                    $filename = $doc_field . "_" . time() . "_" . mt_rand(100, 999) . "." . $ext;
                    move_uploaded_file($_FILES[$doc_field]['tmp_name'], $upload_dir . $filename);
                    $doc_paths[$doc_field] = $upload_dir . $filename;
                } else {
                    $doc_paths[$doc_field] = null;
                }
            }

            // Ensure both salary and business statements go into the same db column
            if (isset($doc_paths['doc_statement_alt'])) {
                $doc_paths['doc_statement'] = $doc_paths['doc_statement_alt'];
            }

            // Fill missing doc paths for DB consistency
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

            if ($conn->query($sql)) {
                $success = "created";
            } else {
                $error = "Error: " . $conn->error;
            }
        }
    }
}
?>

<main class="min-h-screen py-12 px-4 sm:px-6 lg:px-8 gradient-blue">
    <div class="max-w-4xl mx-auto">
        <!-- Tracking / Login Bar -->
        <div class="mb-8 flex justify-end">
            <form action="" method="GET" class="flex items-center gap-2 bg-white/10 p-2 rounded-2xl backdrop-blur-md border border-white/20">
                <span class="text-white text-xs font-bold hidden md:block pl-2">Track Application:</span>
                <input type="email" name="track_email" placeholder="Enter your email" required class="bg-white rounded-xl px-3 py-1.5 text-xs text-gray-800 focus:ring-2 focus:ring-primary-green outline-none min-w-[200px]">
                <button type="submit" class="bg-primary-green hover:bg-green-600 text-white px-4 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2">
                    <i class="fas fa-search"></i> Check
                </button>
            </form>
        </div>

        <?php if ($success === "found" && isset($found_customer)): ?>
            <!-- CLIENT DASHBOARD VIEW -->
            <div class="bg-white rounded-[2rem] shadow-2xl p-10 md:p-16 text-center animate-[fadeIn_0.5s_ease-out]">
                <div class="mb-8">
                    <h2 class="text-3xl font-black text-primary-heading">Hello, <?php echo explode(' ', $found_customer['customer_name'])[0]; ?>!</h2>
                    <p class="text-gray-500">Here is the current state of your application.</p>
                </div>

                <div class="flex flex-col items-center justify-center p-8 bg-gray-50 rounded-3xl mb-10">
                    <?php 
                    $stat = $found_customer['status'];
                    if ($stat == 'Pending'): ?>
                        <div class="w-16 h-16 bg-yellow-100 text-yellow-600 rounded-full flex items-center justify-center mb-4"><i class="fas fa-clock text-2xl"></i></div>
                        <h4 class="text-xl font-bold text-yellow-600">PENDING REVIEW</h4>
                        <p class="text-xs text-gray-400 mt-2 max-w-sm">Our team is currently verifying your documents. This usually takes 24-48 hours. Please check back later.</p>
                    <?php elseif ($stat == 'Approved'): ?>
                        <div class="w-16 h-16 bg-green-100 text-primary-green rounded-full flex items-center justify-center mb-4"><i class="fas fa-check-circle text-2xl"></i></div>
                        <h4 class="text-xl font-bold text-primary-green">APPLICATION APPROVED!</h4>
                        <div class="mt-4 p-6 bg-green-50 rounded-2xl border border-green-100 text-green-800">
                            <p class="font-bold flex items-center justify-center gap-2 mb-2"><i class="fas fa-bullhorn rotate-[-20deg]"></i> CONGRATULATIONS!</p>
                            <p class="text-xs leading-relaxed">Your membership has been approved. Please <strong>visit our office</strong> at Ikaze House, 2nd Floor with your original documents or <strong>call us at +250 796 880 272</strong> to finalize your loan processing.</p>
                        </div>
                    <?php else: ?>
                        <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mb-4"><i class="fas fa-times-circle text-2xl"></i></div>
                        <h4 class="text-xl font-bold text-red-600">APPLICATION REJECTED</h4>
                        <p class="text-xs text-gray-400 mt-2">Unfortunately, your application does not meet our current requirements. Please contact our office for more details.</p>
                    <?php endif; ?>
                </div>

                <a href="apply.php" class="text-gray-400 font-bold hover:text-primary-blue text-xs flex items-center justify-center gap-2">
                    <i class="fas fa-arrow-left"></i> Logout / Back
                </a>
            </div>

        <?php elseif ($success === "created"): ?>
            <!-- Success Page -->
            <div class="bg-white rounded-[2rem] shadow-2xl p-12 text-center">
                <div class="w-24 h-24 bg-green-100 text-primary-green rounded-full flex items-center justify-center mx-auto mb-6"><i class="fas fa-check text-4xl"></i></div>
                <h2 class="text-3xl font-extrabold text-neutral-heading mb-3">Submission Successful!</h2>
                <p class="text-gray-500">Your documents have been uploaded and your application is now in <strong>Pending</strong> state. You can track your status by entering your email anytime on this page.</p>
                <div class="mt-8 flex justify-center gap-4">
                    <a href="index.php" class="bg-primary-blue text-white px-8 py-3 rounded-xl font-bold shadow-lg h-12 flex items-center">Back to Home</a>
                    <a href="apply.php?track_email=<?php echo $email; ?>" class="bg-primary-green text-white px-8 py-3 rounded-xl font-bold shadow-lg h-12 flex items-center">View Status</a>
                </div>
            </div>

        <?php else: ?>
            <!-- FORM STEPS -->
            <div id="form-container" class="bg-white rounded-[2rem] shadow-2xl p-8 md:p-12 relative overflow-hidden">
                <div class="text-center mb-10">
                    <h1 class="text-3xl font-black text-primary-blue">Loan Application</h1>
                    <p class="text-gray-400 text-sm">Join Capital Bridge Finance and empower your goals.</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 flex items-center gap-3 border border-red-100">
                        <i class="fas fa-exclamation-circle"></i>
                        <p class="text-xs font-bold"><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>

                <form id="loanForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="submit_application" value="1">
                    
                    <div id="step-1" class="form-step space-y-6">
                        <div class="bg-blue-50 p-6 rounded-3xl mb-8">
                            <label class="block text-sm font-bold text-primary-blue mb-4 text-center">CHOOSE YOUR LOAN TYPE</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="cursor-pointer">
                                    <input type="radio" name="loan_type" value="Salary" required class="hidden peer" onchange="updateDocFields()">
                                    <div class="bg-white border-2 border-transparent peer-checked:border-primary-green p-4 rounded-2xl text-center transition-all hover:bg-gray-50">
                                        <i class="fas fa-money-check-alt text-2xl text-primary-green mb-2"></i>
                                        <span class="block text-xs font-black uppercase text-gray-700">Salary Loan</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="loan_type" value="Business" required class="hidden peer" onchange="updateDocFields()">
                                    <div class="bg-white border-2 border-transparent peer-checked:border-primary-blue p-4 rounded-2xl text-center transition-all hover:bg-gray-50">
                                        <i class="fas fa-store text-2xl text-primary-blue mb-2"></i>
                                        <span class="block text-xs font-black uppercase text-gray-700">Business Loan</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest pl-1">Full Name</label>
                                <input type="text" name="customer_name" required class="w-full border border-gray-100 bg-gray-50/50 rounded-2xl px-4 py-3.5 focus:ring-2 focus:ring-primary-green focus:bg-white outline-none" placeholder="John Doe">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest pl-1">Email Address</label>
                                <input type="email" name="email" required class="w-full border border-gray-100 bg-gray-50/50 rounded-2xl px-4 py-3.5 focus:ring-2 focus:ring-primary-green focus:bg-white outline-none" placeholder="john@example.com">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest pl-1">Phone Number</label>
                                <input type="tel" name="phone" required class="w-full border border-gray-100 bg-gray-50/50 rounded-2xl px-4 py-3.5 focus:ring-2 focus:ring-primary-green focus:bg-white outline-none" placeholder="+250 7...">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest pl-1">National ID</label>
                                <input type="text" name="nationalId" required class="w-full border border-gray-100 bg-gray-50/50 rounded-2xl px-4 py-3.5 focus:ring-2 focus:ring-primary-green focus:bg-white outline-none" placeholder="ID Number">
                            </div>
                        </div>

                        <button type="button" onclick="nextPrev(1)" class="w-full bg-primary-blue hover:bg-blue-800 text-white font-black py-4 rounded-2xl transition-all shadow-xl flex items-center justify-center gap-3 group">
                            Personal Details <i class="fas fa-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
                        </button>
                    </div>

                    <div id="step-2" class="form-step space-y-6 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2"><label class="text-[10px] font-bold text-gray-400 uppercase">Birth Place</label><input type="text" name="birth_place" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm" required></div>
                            <div class="space-y-2"><label class="text-[10px] font-bold text-gray-400 uppercase">Gender</label><select name="gender" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm" required><option value="male">Male</option><option value="female">Female</option></select></div>
                            <div class="space-y-2"><label class="text-[10px] font-bold text-gray-400 uppercase">Date of Birth</label><input type="date" name="dob" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm" required></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2"><label class="text-[10px] font-bold text-gray-400 uppercase">Father's Name</label><input type="text" name="father_name" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm" required></div>
                            <div class="space-y-2"><label class="text-[10px] font-bold text-gray-400 uppercase">Mother's Name</label><input type="text" name="mother_name" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm" required></div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-bold text-gray-400 uppercase">Marriage Status</label>
                            <select name="marriage_type" id="marriage_type" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm" required onchange="toggleSpouseFields()">
                                <option value="Single">Single</option>
                                <option value="Ivanga mutungo">Ivanga mutungo</option>
                                <option value="Ivangura mutungo">Ivangura mutungo</option>
                                <option value="Muhahano">Muhahano</option>
                            </select>
                        </div>
                        <div id="spouse_fields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2"><label class="text-[10px] font-bold text-gray-400 uppercase">Spouse Name</label><input type="text" name="spouse" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm"></div>
                            <div class="space-y-2"><label class="text-[10px] font-bold text-gray-400 uppercase">Spouse Phone</label><input type="tel" name="spouse_phone" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm"></div>
                        </div>

                        <div class="flex gap-4">
                            <button type="button" onclick="nextPrev(-1)" class="w-1/3 bg-gray-100 text-gray-500 font-bold py-4 rounded-2xl hover:bg-gray-200 transition-all">Back</button>
                            <button type="button" onclick="nextPrev(1)" class="w-2/3 bg-primary-blue text-white font-bold py-4 rounded-2xl hover:bg-blue-800 shadow-lg">Professional Info</button>
                        </div>
                    </div>

                    <div id="step-3" class="form-step space-y-6 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2"><label class="text-[10px] font-bold text-gray-400 uppercase">Occupation</label><input type="text" name="occupation" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm" required></div>
                            <div class="space-y-2"><label class="text-[10px] font-bold text-gray-400 uppercase">Bank Account</label><input type="text" name="account_number" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm" required></div>
                        </div>
                        <div class="space-y-2"><label class="text-[10px] font-bold text-gray-400 uppercase">Home Address</label><input type="text" name="address" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm" required placeholder="District, Sector..."></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2"><label class="text-[10px] font-bold text-gray-400 uppercase">Project Name</label><input type="text" name="project" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm" required></div>
                            <div class="space-y-2"><label class="text-[10px] font-bold text-gray-400 uppercase">Location</label><input type="text" name="location" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm" required></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2"><label class="text-[10px] font-bold text-gray-400 uppercase">Project Loc.</label><input type="text" name="project_location" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm" required></div>
                            <div class="space-y-2"><label class="text-[10px] font-bold text-gray-400 uppercase">Caution Loc.</label><input type="text" name="caution_location" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-3 text-sm" required></div>
                        </div>

                        <div class="flex gap-4">
                            <button type="button" onclick="nextPrev(-1)" class="w-1/3 bg-gray-100 text-gray-500 font-bold py-4 rounded-2xl">Back</button>
                            <button type="button" onclick="nextPrev(1)" class="w-2/3 bg-primary-blue text-white font-bold py-4 rounded-2xl">Upload Documents</button>
                        </div>
                    </div>

                    <div id="step-4" class="form-step space-y-6 hidden">
                        <div class="bg-primary-blue text-white p-6 rounded-[2rem] mb-6">
                            <h4 class="text-sm font-black uppercase flex items-center gap-2"><i class="fas fa-file-upload"></i> Required Documents</h4>
                            <p class="text-[10px] text-blue-200 mt-1">Upload clear images or PDF files (Max 5MB each)</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="documentation_fields">
                            <!-- Field: ID -->
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-gray-500 uppercase">Copy of ID (Pic/PDF) *</label>
                                <input type="file" name="doc_id" required accept=".pdf,.jpg,.jpeg,.png" class="w-full file:bg-primary-green file:text-white file:border-none file:px-4 file:py-2 file:rounded-xl file:text-xs text-xs text-gray-400 bg-gray-50 p-2 rounded-2xl border border-dashed border-gray-200">
                            </div>

                            <!-- Field: Marital (Conditional) -->
                            <div class="space-y-2 marital-doc hidden">
                                <label class="text-[10px] font-bold text-gray-500 uppercase">Marital Status Cert. *</label>
                                <input type="file" name="doc_marital" accept=".pdf,.jpg,.jpeg,.png" class="w-full file:bg-primary-green file:text-white file:border-none file:px-4 file:py-2 file:rounded-xl file:text-xs text-xs text-gray-400 bg-gray-50 p-2 rounded-2xl border border-dashed border-gray-200">
                            </div>

                            <!-- SALARY ONLY -->
                            <div class="space-y-2 doc-salary">
                                <label class="text-[10px] font-bold text-gray-500 uppercase">Work Contract (PDF/Img) *</label>
                                <input type="file" name="doc_contract" accept=".pdf,.jpg,.jpeg,.png" class="salary-req w-full file:bg-primary-green file:text-white file:border-none file:px-4 file:py-2 file:rounded-xl file:text-xs text-xs text-gray-400 bg-gray-50 p-2 rounded-2xl border border-dashed border-gray-200">
                            </div>
                            <div class="space-y-2 doc-salary">
                                <label class="text-[10px] font-bold text-gray-500 uppercase">Bank Statement *</label>
                                <input type="file" name="doc_statement" accept=".pdf,.jpg,.jpeg,.png" class="salary-req w-full file:bg-primary-green file:text-white file:border-none file:px-4 file:py-2 file:rounded-xl file:text-xs text-xs text-gray-400 bg-gray-50 p-2 rounded-2xl border border-dashed border-gray-200">
                            </div>
                            <div class="space-y-2 doc-salary">
                                <label class="text-[10px] font-bold text-gray-500 uppercase">Latest Payslip *</label>
                                <input type="file" name="doc_payslip" accept=".pdf,.jpg,.jpeg,.png" class="salary-req w-full file:bg-primary-green file:text-white file:border-none file:px-4 file:py-2 file:rounded-xl file:text-xs text-xs text-gray-400 bg-gray-50 p-2 rounded-2xl border border-dashed border-gray-200">
                            </div>

                            <!-- BUSINESS ONLY -->
                            <div class="space-y-2 doc-business hidden">
                                <label class="text-[10px] font-bold text-gray-500 uppercase">RDB Certificate *</label>
                                <input type="file" name="doc_rdb" accept=".pdf,.jpg,.jpeg,.png" class="business-req w-full file:bg-primary-green file:text-white file:border-none file:px-4 file:py-2 file:rounded-xl file:text-xs text-xs text-gray-400 bg-gray-50 p-2 rounded-2xl border border-dashed border-gray-200">
                            </div>
                            <div class="space-y-2 doc-business hidden">
                                <label class="text-[10px] font-bold text-gray-500 uppercase">Bank/Momo Statement *</label>
                                <input type="file" name="doc_statement_alt" accept=".pdf,.jpg,.jpeg,.png" class="business-req w-full file:bg-primary-green file:text-white file:border-none file:px-4 file:py-2 file:rounded-xl file:text-xs text-xs text-gray-400 bg-gray-50 p-2 rounded-2xl border border-dashed border-gray-200">
                            </div>
                        </div>

                        <div class="flex gap-4 mt-8">
                            <button type="button" onclick="nextPrev(-1)" class="w-1/3 bg-gray-100 text-gray-500 font-bold py-4 rounded-2xl">Back</button>
                            <button type="submit" class="w-2/3 bg-primary-green text-white font-black py-4 rounded-2xl shadow-xl hover:scale-105 transition-all">Submit Application</button>
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
    const steps = document.getElementsByClassName("form-step");
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
        // Validation for file type
        if (input.type === 'file' && input.files.length > 0) {
            const ext = input.files[0].name.split('.').pop().toLowerCase();
            const allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!allowed.includes(ext)) {
                alert("Error: Only PDF, JPG, and PNG files are allowed.");
                return false;
            }
        }
        
        if (input.type === 'radio') {
            const rads = activeStep.querySelectorAll(`input[name="${input.name}"]`);
            let checked = false;
            for(let r of rads) if(r.checked) checked = true;
            if(!checked) return false;
        } else if (!input.value && input.type !== 'file') {
            input.classList.add("border-red-500");
            return false;
        }
        input.classList.remove("border-red-500");
    }
    return true;
}

function toggleSpouseFields() {
    const val = document.getElementById('marriage_type').value;
    const fields = document.getElementById('spouse_fields');
    const maritalDoc = document.querySelectorAll('.marital-doc');
    
    if (val === 'Single') {
        fields.classList.add('hidden');
        maritalDoc.forEach(d => d.classList.add('hidden'));
        document.getElementsByName('doc_marital')[0].required = false;
    } else {
        fields.classList.remove('hidden');
        maritalDoc.forEach(d => d.classList.remove('hidden'));
        document.getElementsByName('doc_marital')[0].required = true;
    }
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
@keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php 
include 'includes/bottom_nav.php'; 
include 'includes/footer.php'; 
?>
