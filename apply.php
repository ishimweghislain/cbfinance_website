<?php 
require_once 'includes/db_connect.php';
include 'includes/head.php'; 
include 'includes/navbar.php'; 

$success = false;
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_application'])) {
    $conn = getWebsiteConnection();
    if ($conn) {
        // Sanitize inputs
        $customer_name = $conn->real_escape_string($_POST['customer_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $id_number = $conn->real_escape_string($_POST['nationalId']);
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
        
        // Default organization
        $organization = "Capital Bridge Finance";
        
        // Auto-generate a pending customer code
        $customer_code = "PEND-" . mt_rand(100000, 999999);
        
        // Check if ID already exists
        $check = $conn->query("SELECT customer_id FROM customers WHERE id_number = '$id_number' OR email = '$email'");
        if ($check && $check->num_rows > 0) {
            $error = "An application with this ID or Email already exists.";
        } else {
            // Insert into customers table
            $sql = "INSERT INTO customers (
                customer_code, customer_name, birth_place, id_number, account_number, 
                occupation, gender, date_of_birth, email, phone, father_name, 
                mother_name, spouse, spouse_occupation, spouse_phone, marriage_type, 
                address, location, project, project_location, caution_location, 
                organization, is_active, status
            ) VALUES (
                '$customer_code', '$customer_name', '$birth_place', '$id_number', '$account_number',
                '$occupation', '$gender', '$dob', '$email', '$phone', '$father_name',
                '$mother_name', '$spouse', '$spouse_occupation', '$spouse_phone', '$marriage_type',
                '$address', '$location', '$project', '$project_location', '$caution_location',
                '$organization', 0, 'Pending'
            )";

            if ($conn->query($sql)) {
                $success = true;
            } else {
                $error = "Error: " . $conn->error;
            }
        }
        $conn->close();
    } else {
        $error = "Unable to connect to the database.";
    }
}
?>

<main class="min-h-screen py-12 px-4 sm:px-6 lg:px-8 gradient-blue flex items-center justify-center">
    <div class="max-w-4xl w-full">
        <!-- Header -->
        <div class="text-center mb-10 <?php echo $success ? 'hidden' : ''; ?>">
            <h1 class="text-4xl font-extrabold text-white mb-2">Join CB Finance</h1>
            <p class="text-blue-100">Become a member and apply for financial assistance. It's fast and secure.</p>
        </div>

        <!-- Form Card -->
        <div class="bg-white rounded-[2rem] shadow-2xl p-8 md:p-12 relative overflow-hidden">
            <?php if (!$success): ?>
            <!-- Progress Bar -->
            <div class="mb-12">
                <div class="flex items-center justify-between mb-4">
                    <div class="step-indicator flex flex-col items-center flex-1">
                        <div id="step-dot-1" class="w-10 h-10 rounded-full border-2 border-primary-green flex items-center justify-center font-bold mb-2 transition-all duration-300 step-active">1</div>
                        <span class="text-[10px] md:text-sm font-semibold text-gray-400">Personal</span>
                    </div>
                    <div class="h-[2px] bg-gray-200 flex-1 -mt-6"></div>
                    <div class="step-indicator flex flex-col items-center flex-1">
                        <div id="step-dot-2" class="w-10 h-10 rounded-full border-2 border-gray-200 flex items-center justify-center font-bold mb-2 transition-all duration-300">2</div>
                        <span class="text-[10px] md:text-sm font-semibold text-gray-400">Family</span>
                    </div>
                    <div class="h-[2px] bg-gray-200 flex-1 -mt-6"></div>
                    <div class="step-indicator flex flex-col items-center flex-1">
                        <div id="step-dot-3" class="w-10 h-10 rounded-full border-2 border-gray-200 flex items-center justify-center font-bold mb-2 transition-all duration-300">3</div>
                        <span class="text-[10px] md:text-sm font-semibold text-gray-400">Location</span>
                    </div>
                    <div class="h-[2px] bg-gray-200 flex-1 -mt-6"></div>
                    <div class="step-indicator flex flex-col items-center flex-1">
                        <div id="step-dot-4" class="w-10 h-10 rounded-full border-2 border-gray-200 flex items-center justify-center font-bold mb-2 transition-all duration-300">4</div>
                        <span class="text-[10px] md:text-sm font-semibold text-gray-400">Confirmation</span>
                    </div>
                </div>
                <!-- Line Progress -->
                <div class="w-full bg-gray-100 h-2 rounded-full mt-8 overflow-hidden">
                    <div id="progress-bar-fill" class="bg-primary-green h-full transition-all duration-500" style="width: 25%"></div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i>
                    <p class="text-sm font-bold"><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <!-- Form Steps -->
            <form id="loanForm" method="POST" class="relative">
                <input type="hidden" name="submit_application" value="1">
                
                <!-- STEP 1: Personal Information -->
                <div id="step-1" class="form-step space-y-6">
                    <h3 class="text-2xl font-bold text-neutral-heading flex items-center gap-3">
                        <i class="fas fa-user text-primary-green"></i> Basic Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Full Name</label>
                            <input type="text" name="customer_name" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="First & Last Name">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Birth Place</label>
                            <input type="text" name="birth_place" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Place of Birth">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Email Address</label>
                            <input type="email" name="email" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="email@example.com">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Phone Number</label>
                            <input type="tel" name="phone" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="+250 7...">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">National ID</label>
                            <input type="text" name="nationalId" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="ID Number">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Account No.</label>
                            <input type="text" name="account_number" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Bank Account">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Occupation</label>
                            <input type="text" name="occupation" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Profession">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Date of Birth</label>
                            <input type="date" name="dob" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Gender</label>
                            <select name="gender" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none appearance-none bg-[url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_1rem_center]">
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Family Information -->
                <div id="step-2" class="form-step space-y-6 hidden">
                    <h3 class="text-2xl font-bold text-neutral-heading flex items-center gap-3">
                        <i class="fas fa-people-roof text-primary-green"></i> Family Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Father's Full Name</label>
                            <input type="text" name="father_name" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Father's Name">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Mother's Full Name</label>
                            <input type="text" name="mother_name" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Mother's Name">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Marriage Type</label>
                        <select name="marriage_type" id="marriage_type" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none appearance-none bg-[url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_1rem_center]" onchange="toggleSpouse()">
                            <option value="Single">Single</option>
                            <option value="Ivanga mutungo">Ivanga mutungo</option>
                            <option value="Ivangura mutungo">Ivangura mutungo</option>
                            <option value="Muhahano">Muhahano</option>
                        </select>
                    </div>
                    
                    <div id="spouse_section" class="hidden space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-sm font-bold text-gray-700">Spouse Full Name</label>
                                <input type="text" name="spouse" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Spouse Name">
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-bold text-gray-700">Spouse Occupation</label>
                                <input type="text" name="spouse_occupation" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Spouse Work">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Spouse Phone Number</label>
                            <input type="tel" name="spouse_phone" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Spouse Phone">
                        </div>
                    </div>
                </div>

                <!-- STEP 3: Location & Project -->
                <div id="step-3" class="form-step space-y-6 hidden">
                    <h3 class="text-2xl font-bold text-neutral-heading flex items-center gap-3">
                        <i class="fas fa-location-dot text-primary-green"></i> Location & Project
                    </h3>
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Complete Address</label>
                        <textarea name="address" required rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none resize-none" placeholder="Residential Address (District, Sector, Cell, Village)"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">General Location</label>
                            <input type="text" name="location" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="e.g. Gasabo">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Project Name</label>
                            <input type="text" name="project" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Business/Project Name">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Project Location</label>
                            <input type="text" name="project_location" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Where project is located">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Caution Location</label>
                            <input type="text" name="caution_location" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Caution address">
                        </div>
                    </div>
                </div>

                <!-- STEP 4: Documents -->
                <div id="step-4" class="form-step space-y-8 hidden">
                    <h3 class="text-2xl font-bold text-neutral-heading flex items-center gap-3">
                        <i class="fas fa-file-upload text-primary-green"></i> Verification
                    </h3>
                    
                    <div class="bg-blue-50 border border-blue-100 rounded-2xl p-6 space-y-3">
                        <div class="flex items-center gap-3 text-primary-blue font-bold">
                            <i class="fas fa-info-circle"></i>
                            <h4>Next Steps</h4>
                        </div>
                        <p class="text-xs text-gray-500 leading-relaxed">
                            By submitting this application, you agree to join CB Finance. Once your application is reviewed and approved by the administrator, you will be notified to visit our office with your original documents to complete the membership process.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="confirm" required class="w-5 h-5 rounded border-gray-300 text-primary-green focus:ring-primary-green cursor-pointer" onchange="toggleSubmit()">
                        <label for="confirm" class="text-sm text-gray-600 cursor-pointer select-none">I confirm that all information provided is accurate and I agree to the terms of membership.</label>
                    </div>
                </div>

                <!-- Footer Navigation -->
                <div class="flex items-center justify-between mt-12 pt-8 border-t border-gray-100">
                    <button type="button" id="prevBtn" onclick="nextPrev(-1)" class="hidden text-gray-400 font-bold hover:text-primary-blue transition-colors flex items-center gap-2">
                        <i class="fas fa-arrow-left text-xs"></i> Back
                    </button>
                    <button type="button" id="nextBtn" onclick="nextPrev(1)" class="bg-primary-green hover:bg-green-600 text-white px-10 py-4 rounded-xl font-bold shadow-lg transition-all transform hover:scale-[1.05] flex items-center gap-3 ml-auto">
                        <span>Next Step</span> <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                    <button type="submit" id="submitBtn" class="hidden bg-primary-green opacity-50 cursor-not-allowed text-white px-10 py-4 rounded-xl font-bold shadow-lg transition-all ml-auto flex items-center gap-3" disabled>
                        <span>Submit Application</span> <i class="fas fa-check-circle"></i>
                    </button>
                </div>
            </form>
            <?php else: ?>
            <!-- Success Page -->
            <div id="successContent" class="text-center py-10 space-y-8 animate-[fadeIn_0.5s_ease-out]">
                <div class="w-24 h-24 bg-green-100 text-primary-green rounded-full flex items-center justify-center mx-auto mb-6 scale-0 animate-[scaleUp_0.5s_0.3s_forwards]">
                    <i class="fas fa-check text-4xl"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-extrabold text-neutral-heading mb-3">Application Received!</h2>
                    <p class="text-gray-500">Your application has been received and is currently in a <strong>Pending</strong> state. Our team will review your details and contact you for the next steps.</p>
                </div>
                
                <div class="bg-gray-50 rounded-2xl p-8 max-w-sm mx-auto text-left space-y-4 shadow-inner">
                    <h4 class="font-bold text-gray-700 text-sm">What happens next?</h4>
                    <div class="space-y-4">
                        <div class="flex gap-4">
                            <div class="w-6 h-6 rounded-full bg-white flex items-center justify-center text-[10px] font-bold text-primary-green shadow-sm">1</div>
                            <p class="text-xs text-gray-500">Admin reviews your information</p>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-6 h-6 rounded-full bg-white flex items-center justify-center text-[10px] font-bold text-primary-green shadow-sm">2</div>
                            <p class="text-xs text-gray-500">Verification of your details</p>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-6 h-6 rounded-full bg-white flex items-center justify-center text-[10px] font-bold text-primary-green shadow-sm">3</div>
                            <p class="text-xs text-gray-500">Approval / Membership activation</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="index.php" class="bg-primary-blue text-white px-8 py-4 rounded-xl font-bold shadow-lg hover:bg-blue-800 transition-all flex items-center justify-center gap-2">
                        <i class="fas fa-home"></i> Back to Home
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
@keyframes scaleUp {
    from { transform: scale(0); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
    let currentStep = 1;
    const totalSteps = 4;

    function nextPrev(n) {
        const steps = document.getElementsByClassName("form-step");
        if (n === 1 && !validateForm()) return false;

        steps[currentStep - 1].classList.add("hidden");
        currentStep = currentStep + n;

        if (currentStep > totalSteps) return false;
        showStep(currentStep);
    }

    function showStep(n) {
        const steps = document.getElementsByClassName("form-step");
        steps[n - 1].classList.remove("hidden");
        
        document.getElementById("prevBtn").style.display = (n === 1) ? "none" : "flex";
        if (n === totalSteps) {
            document.getElementById("nextBtn").style.display = "none";
            document.getElementById("submitBtn").style.display = "flex";
        } else {
            document.getElementById("nextBtn").style.display = "flex";
            document.getElementById("submitBtn").style.display = "none";
        }

        updateProgress(n);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function updateProgress(n) {
        for (let i = 1; i <= totalSteps; i++) {
            const dot = document.getElementById("step-dot-" + i);
            if (i < n) {
                dot.style.backgroundColor = "#10b981";
                dot.style.borderColor = "#10b981";
                dot.style.color = "white";
                dot.innerHTML = '<i class="fas fa-check text-xs"></i>';
            } else if (i === n) {
                dot.style.backgroundColor = "transparent";
                dot.style.borderColor = "#10b981";
                dot.style.color = "#10b981";
                dot.innerHTML = i;
            } else {
                dot.style.backgroundColor = "transparent";
                dot.style.borderColor = "#e5e7eb";
                dot.style.color = "#9ca3af";
                dot.innerHTML = i;
            }
        }
        const percent = ((n - 1) / (totalSteps - 1)) * 100;
        document.getElementById("progress-bar-fill").style.width = percent + "%";
    }

    function validateForm() {
        const currentStepDiv = document.getElementById("step-" + currentStep);
        const inputs = currentStepDiv.querySelectorAll("input[required], select[required], textarea[required]");
        let valid = true;
        inputs.forEach(input => {
            if (!input.value) {
                input.classList.add("border-red-500");
                valid = false;
            } else {
                input.classList.remove("border-red-500");
            }
        });
        return valid;
    }

    function toggleSubmit() {
        const checkbox = document.getElementById("confirm");
        const submitBtn = document.getElementById("submitBtn");
        if (checkbox.checked) {
            submitBtn.disabled = false;
            submitBtn.classList.remove("opacity-50", "cursor-not-allowed");
        } else {
            submitBtn.disabled = true;
            submitBtn.classList.add("opacity-50", "cursor-not-allowed");
        }
    }

    function toggleSpouse() {
        const type = document.getElementById("marriage_type").value;
        const section = document.getElementById("spouse_section");
        const inputs = section.querySelectorAll("input");
        if (type === "Single") {
            section.classList.add("hidden");
            inputs.forEach(i => i.required = false);
        } else {
            section.classList.remove("hidden");
            inputs.forEach(i => i.required = true);
        }
    }
</script>

<?php 
include 'includes/bottom_nav.php'; 
include 'includes/footer.php'; 
?>
