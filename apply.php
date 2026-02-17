<?php include 'includes/head.php'; ?>
<?php include 'includes/navbar.php'; ?>

<main class="min-h-screen py-12 px-4 sm:px-6 lg:px-8 gradient-blue flex items-center justify-center">
    <div class="max-w-3xl w-full">
        <!-- Header -->
        <div class="text-center mb-10">
            <h1 class="text-4xl font-extrabold text-white mb-2">Apply for a Loan</h1>
            <p class="text-blue-100">Complete the application form below. It's simple, fast, and secure.</p>
        </div>

        <!-- Form Card -->
        <div class="bg-white rounded-[2rem] shadow-2xl p-8 md:p-12 relative overflow-hidden">
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
                        <span class="text-[10px] md:text-sm font-semibold text-gray-400">Loan Details</span>
                    </div>
                    <div class="h-[2px] bg-gray-200 flex-1 -mt-6"></div>
                    <div class="step-indicator flex flex-col items-center flex-1">
                        <div id="step-dot-3" class="w-10 h-10 rounded-full border-2 border-gray-200 flex items-center justify-center font-bold mb-2 transition-all duration-300">3</div>
                        <span class="text-[10px] md:text-sm font-semibold text-gray-400">Employment</span>
                    </div>
                    <div class="h-[2px] bg-gray-200 flex-1 -mt-6"></div>
                    <div class="step-indicator flex flex-col items-center flex-1">
                        <div id="step-dot-4" class="w-10 h-10 rounded-full border-2 border-gray-200 flex items-center justify-center font-bold mb-2 transition-all duration-300">4</div>
                        <span class="text-[10px] md:text-sm font-semibold text-gray-400">Documents</span>
                    </div>
                </div>
                <!-- Line Progress -->
                <div class="w-full bg-gray-100 h-2 rounded-full mt-8 overflow-hidden">
                    <div id="progress-bar-fill" class="bg-primary-green h-full transition-all duration-500" style="width: 25%"></div>
                </div>
            </div>

            <!-- Form Steps -->
            <form id="loanForm" class="relative">
                
                <!-- STEP 1: Personal Information -->
                <div id="step-1" class="form-step space-y-6">
                    <h3 class="text-2xl font-bold text-neutral-heading flex items-center gap-3">
                        <i class="fas fa-user text-primary-green"></i> Personal Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">First Name</label>
                            <input type="text" name="firstName" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="First Name">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Last Name</label>
                            <input type="text" name="lastName" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Last Name">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Email Address</label>
                        <input type="email" name="email" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="email@example.com">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Phone Number</label>
                            <input type="tel" name="phone" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="+250 7...">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">National ID Number</label>
                            <input type="text" name="nationalId" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="ID Number">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Date of Birth</label>
                            <input type="date" name="dob" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Gender</label>
                            <select name="gender" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none appearance-none bg-[url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_1rem_center]">
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Loan Details -->
                <div id="step-2" class="form-step space-y-6 hidden">
                    <h3 class="text-2xl font-bold text-neutral-heading flex items-center gap-3">
                        <i class="fas fa-coins text-primary-green"></i> Loan Details
                    </h3>
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Loan Type</label>
                        <select name="loanType" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none appearance-none bg-[url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_1rem_center]">
                            <option value="">Select Loan Type</option>
                            <option value="business">Business Loan</option>
                            <option value="personal">Personal Loan</option>
                            <option value="micro">Micro Loan</option>
                            <option value="asset">Asset Finance</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Loan Amount (RWF)</label>
                            <input type="number" min="10000" name="loanAmount" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Min. 10,000">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Loan Term (Months)</label>
                            <input type="number" min="1" max="60" name="loanTerm" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="1-60 months">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Purpose of Loan</label>
                        <select name="purpose" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none appearance-none bg-[url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_1rem_center]">
                            <option value="">Select Purpose</option>
                            <option value="expansion">Business Expansion</option>
                            <option value="education">Education</option>
                            <option value="healthcare">Healthcare</option>
                            <option value="housing">Housing</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Detailed Purpose Description</label>
                        <textarea name="purposeDescription" rows="4" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none resize-none" placeholder="Tell us more..."></textarea>
                    </div>
                </div>

                <!-- STEP 3: Employment Information -->
                <div id="step-3" class="form-step space-y-6 hidden">
                    <h3 class="text-2xl font-bold text-neutral-heading flex items-center gap-3">
                        <i class="fas fa-briefcase text-primary-green"></i> Employment Information
                    </h3>
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700">Employment Status</label>
                        <select name="employmentStatus" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none appearance-none bg-[url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_1rem_center]">
                            <option value="">Select Status</option>
                            <option value="employed">Employed</option>
                            <option value="self-employed">Self-Employed</option>
                            <option value="business-owner">Business Owner</option>
                            <option value="student">Student</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Employer / Business Name</label>
                            <input type="text" name="employerName" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Company Name">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Job Title / Position</label>
                            <input type="text" name="jobTitle" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="Your Role">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Monthly Income (RWF)</label>
                            <input type="number" name="monthlyIncome" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="0.00">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700">Employment Duration (Months)</label>
                            <input type="number" name="duration" required class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-green focus:border-primary-green outline-none" placeholder="e.g. 12">
                        </div>
                    </div>
                </div>

                <!-- STEP 4: Required Documents -->
                <div id="step-4" class="form-step space-y-8 hidden">
                    <h3 class="text-2xl font-bold text-neutral-heading flex items-center gap-3">
                        <i class="fas fa-file-upload text-primary-green"></i> Required Documents
                    </h3>
                    
                    <div class="space-y-6">
                        <!-- ID Upload -->
                        <div class="space-y-3">
                            <label class="text-sm font-bold text-gray-700">National ID Copy</label>
                            <div class="relative group">
                                <input type="file" name="idCopy" id="idCopy" accept=".pdf,.jpg,.jpeg,.png" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)">
                                <div class="border-2 border-dashed border-gray-200 group-hover:border-primary-green rounded-2xl p-6 transition-all bg-gray-50 flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 group-hover:text-primary-green transition-colors shadow-sm">
                                            <i class="fas fa-id-card"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-gray-600 fileName">Upload ID Card</p>
                                            <p class="text-[10px] text-gray-400">PDF, JPG or PNG (Max 5MB)</p>
                                        </div>
                                    </div>
                                    <i class="fas fa-plus text-xs text-gray-300"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Income Proof -->
                        <div class="space-y-3">
                            <label class="text-sm font-bold text-gray-700">Proof of Income</label>
                            <div class="relative group">
                                <input type="file" name="incomeProof" id="incomeProof" accept=".pdf,.jpg,.jpeg,.png" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)">
                                <div class="border-2 border-dashed border-gray-200 group-hover:border-primary-blue rounded-2xl p-6 transition-all bg-gray-50 flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 group-hover:text-primary-blue transition-colors shadow-sm">
                                            <i class="fas fa-money-check-alt"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-gray-600 fileName">Upload Pay Slips / Statments</p>
                                            <p class="text-[10px] text-gray-400">PDF, JPG or PNG (Max 5MB)</p>
                                        </div>
                                    </div>
                                    <i class="fas fa-plus text-xs text-gray-300"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Address Proof -->
                        <div class="space-y-3">
                            <label class="text-sm font-bold text-gray-700">Proof of Address</label>
                            <div class="relative group">
                                <input type="file" name="addressProof" id="addressProof" accept=".pdf,.jpg,.jpeg,.png" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)">
                                <div class="border-2 border-dashed border-gray-200 group-hover:border-primary-light rounded-2xl p-6 transition-all bg-gray-50 flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 group-hover:text-primary-light transition-colors shadow-sm">
                                            <i class="fas fa-home"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-gray-600 fileName">Upload Utility Bill</p>
                                            <p class="text-[10px] text-gray-400">PDF, JPG or PNG (Max 5MB)</p>
                                        </div>
                                    </div>
                                    <i class="fas fa-plus text-xs text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Important Information -->
                    <div class="bg-blue-50 border border-blue-100 rounded-2xl p-6 space-y-3">
                        <div class="flex items-center gap-3 text-primary-blue font-bold">
                            <i class="fas fa-info-circle"></i>
                            <h4>Important Information</h4>
                        </div>
                        <ul class="text-xs text-gray-500 space-y-2 list-disc ml-5">
                            <li>All documents must be clear and readable</li>
                            <li>Minimum resolution of 300dpi is recommended for images</li>
                            <li>Maximum file size per document is 5MB</li>
                            <li>Information must match your application details exactly</li>
                        </ul>
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="confirm" required class="w-5 h-5 rounded border-gray-300 text-primary-green focus:ring-primary-green cursor-pointer" onchange="toggleSubmit()">
                        <label for="confirm" class="text-sm text-gray-600 cursor-pointer select-none">I confirm that all information provided is accurate and I agree to the terms of processing.</label>
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

            <!-- Success Page (Hidden) -->
            <div id="successContent" class="hidden text-center py-10 space-y-8 animate-[fadeIn_0.5s_ease-out]">
                <div class="w-24 h-24 bg-green-100 text-primary-green rounded-full flex items-center justify-center mx-auto mb-6 scale-0 animate-[scaleUp_0.5s_0.3s_forwards]">
                    <i class="fas fa-check text-4xl"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-extrabold text-neutral-heading mb-3">Application Submitted!</h2>
                    <p class="text-gray-500">Your application has been received. Our team will review it and get back to you within 24–48 hours.</p>
                </div>
                
                <div class="bg-gray-50 rounded-2xl p-8 max-w-sm mx-auto text-left space-y-4 shadow-inner">
                    <h4 class="font-bold text-gray-700 text-sm">Next Steps:</h4>
                    <div class="space-y-4">
                        <div class="flex gap-4">
                            <div class="w-6 h-6 rounded-full bg-white flex items-center justify-center text-[10px] font-bold text-primary-green shadow-sm">1</div>
                            <p class="text-xs text-gray-500">Initial document verification & credit score check</p>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-6 h-6 rounded-full bg-white flex items-center justify-center text-[10px] font-bold text-primary-green shadow-sm">2</div>
                            <p class="text-xs text-gray-500">SMS confirmation of your application status</p>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-6 h-6 rounded-full bg-white flex items-center justify-center text-[10px] font-bold text-primary-green shadow-sm">3</div>
                            <p class="text-xs text-gray-500">Verification call from a loan officer if needed</p>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-6 h-6 rounded-full bg-white flex items-center justify-center text-[10px] font-bold text-primary-green shadow-sm">4</div>
                            <p class="text-xs text-gray-500">Final approval & fund disbursement</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="index.php" class="bg-primary-blue text-white px-8 py-4 rounded-xl font-bold shadow-lg hover:bg-blue-800 transition-all flex items-center justify-center gap-2">
                        <i class="fas fa-home"></i> Back to Home
                    </a>
                    <a href="services.php#calculator" class="bg-gray-100 text-gray-700 px-8 py-4 rounded-xl font-bold hover:bg-gray-200 transition-all flex items-center justify-center gap-2">
                        <i class="fas fa-calculator"></i> Loan Calculator
                    </a>
                </div>
            </div>
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
        
        // Validation check for going forward
        if (n === 1 && !validateForm()) return false;

        steps[currentStep - 1].classList.add("hidden");
        currentStep = currentStep + n;

        if (currentStep > totalSteps) {
            // This is handled by form submit now
            return false;
        }
        
        showStep(currentStep);
    }

    function showStep(n) {
        const steps = document.getElementsByClassName("form-step");
        steps[n - 1].classList.remove("hidden");
        
        // Update Buttons
        document.getElementById("prevBtn").style.display = (n === 1) ? "none" : "flex";
        if (n === totalSteps) {
            document.getElementById("nextBtn").style.display = "none";
            document.getElementById("submitBtn").style.display = "flex";
        } else {
            document.getElementById("nextBtn").style.display = "flex";
            document.getElementById("submitBtn").style.display = "none";
        }

        // Update Circles
        updateProgress(n);
    }

    function updateProgress(n) {
        // Dot states
        for (let i = 1; i <= totalSteps; i++) {
            const dot = document.getElementById("step-dot-" + i);
            if (i < n) {
                dot.classList.add("step-completed");
                dot.classList.remove("step-active");
                dot.innerHTML = '<i class="fas fa-check"></i>';
            } else if (i === n) {
                dot.classList.add("step-active");
                dot.classList.remove("step-completed");
                dot.innerHTML = i;
            } else {
                dot.classList.remove("step-active", "step-completed");
                dot.innerHTML = i;
            }
        }

        // Progress Bar
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

        if (!valid) {
            // Simple shake animation effect could be added here
        }
        
        return valid;
    }

    function updateFileName(input) {
        const fileNameDisplay = input.parentElement.querySelector(".fileName");
        if (input.files && input.files[0]) {
            fileNameDisplay.innerHTML = input.files[0].name;
            fileNameDisplay.classList.add("text-primary-green");
            input.parentElement.querySelector(".border-dashed").classList.add("bg-green-50", "border-primary-green");
        }
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

    // Handle form submission
    document.getElementById("loanForm").addEventListener("submit", function(e) {
        e.preventDefault();
        
        // Animation for success
        this.classList.add("animate-[fadeOut_0.5s_forwards]");
        setTimeout(() => {
            this.style.display = "none";
            document.getElementById("successContent").classList.remove("hidden");
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }, 500);
    });
</script>

<?php include 'includes/bottom_nav.php'; ?>
<?php include 'includes/footer.php'; ?>
