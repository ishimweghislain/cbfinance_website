<?php 
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/approval_helper.php';
$conn = getConnection();
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Account type constants
define('ACCOUNT_TYPE_ASSET', 'asset');
define('ACCOUNT_TYPE_LIABILITY', 'liability');
define('ACCOUNT_TYPE_EQUITY', 'equity');
define('ACCOUNT_TYPE_REVENUE', 'revenue');
define('ACCOUNT_TYPE_EXPENSE', 'expense');
define('ACCOUNT_TYPE_FEE_INCOME', 'fee_income');

// Top-up type constants
define('TOPUP_TYPE_INCREASING', 'increasing');
define('TOPUP_TYPE_DECREASING', 'decreasing');

// Functions
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function validateDateDMY($date) {
    $d = DateTime::createFromFormat('d/m/Y', $date);
    return $d && $d->format('d/m/Y') === $date;
}

function convertDMYtoYMD($date) {
    $d = DateTime::createFromFormat('d/m/Y', $date);
    return $d ? $d->format('Y-m-d') : false;
}

function convertYMDtoDMY($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('d/m/Y') : $date;
}

function formatMoney($amount, $decimals = 0) {
    return number_format($amount, $decimals, '.', ',');
}

function parseMoney($moneyString) {
    return floatval(str_replace(',', '', $moneyString));
}

/**
 * Calculate management fee per month from total disbursed
 */
function calculateManagementFeeFromDisbursed($total_disbursed, $management_fee_rate = 5.5) {
    return round($total_disbursed * ($management_fee_rate / 100), 2);
}

/**
 * Calculate loan amount from total disbursed based on fee deduction option
 */
function calculateLoanAmountFromDisbursed($total_disbursed, $management_fee_rate = 5.5, $deduct_fee = true) {
    if ($deduct_fee) {
        $management_fee = calculateManagementFeeFromDisbursed($total_disbursed, $management_fee_rate);
        return round($total_disbursed - $management_fee, 2);
    } else {
        return round($total_disbursed, 2);
    }
}

/**
 * Calculate PPMT (Principal Payment) using Excel's PPMT formula
 */
function PPMT($rate, $period, $nper, $pv) {
    if ($rate == 0) {
        return -$pv / $nper;
    }
    $pmt = PMT($rate, $nper, $pv);
    $ipmt = IPMT($rate, $period, $nper, $pv);
    return $pmt - $ipmt;
}

function PMT($rate, $nper, $pv) {
    if ($rate == 0) {
        return -$pv / $nper;
    }
    return -$pv * ($rate * pow(1 + $rate, $nper)) / (pow(1 + $rate, $nper) - 1);
}

function IPMT($rate, $period, $nper, $pv) {
    if ($period == 1) {
        return -$pv * $rate;
    }
    $pmt = PMT($rate, $nper, $pv);
    $remaining_balance = $pv;
    for ($i = 1; $i < $period; $i++) {
        $interest = -$remaining_balance * $rate;
        $principal = $pmt - $interest;
        $remaining_balance += $principal;
    }
    return -$remaining_balance * $rate;
}

function generateLoanSchedule($total_disbursed, $interest_rate, $term, $management_fee_rate = 5.5, $deduct_fee = true) {
    $schedule = [];
    $monthly_rate = $interest_rate / 100;
    $management_fee_per_month = round($total_disbursed * ($management_fee_rate / 100), 2);
    $opening_balance = $total_disbursed;
    $total_interest = 0;
    $total_management_fees = 0;
    $total_principal = 0;
    
    for ($i = 1; $i <= $term; $i++) {
        $interest = round($opening_balance * $monthly_rate, 2);
        $total_interest += $interest;
        $principal = round(-PPMT($monthly_rate, $i, $term, $total_disbursed), 2);
        
        if ($i == 1) {
            $management_fee = $deduct_fee ? 0 : $management_fee_per_month;
        } else {
            $management_fee = $management_fee_per_month;
        }
        
        if ($management_fee > 0) {
            $total_management_fees += $management_fee;
        }
        
        $principal = round($principal / 10) * 10;
        $interest = round($interest / 10) * 10;
        $management_fee = round($management_fee / 10) * 10;
        
        $total_payment = $principal + $interest + $management_fee;
        $closing_balance = $opening_balance - $principal;
        
        if ($closing_balance < 0.01) $closing_balance = 0;
        
        $total_principal += $principal;
        
        $schedule[] = [
            'instalment_number' => $i,
            'opening_balance' => round($opening_balance, 2),
            'principal' => $principal,
            'interest' => $interest,
            'management_fee' => $management_fee,
            'total_payment' => $total_payment,
            'closing_balance' => round($closing_balance, 2)
        ];
        
        $opening_balance = $closing_balance;
    }
    
    $middle_payments = [];
    for ($i = 1; $i < count($schedule) - 1; $i++) {
        $middle_payments[] = $schedule[$i]['total_payment'];
    }
    $monthly_payment = count($middle_payments) > 0 ? round(array_sum($middle_payments) / count($middle_payments), 2) : 0;
    
    return [
        'schedule' => $schedule,
        'total_interest' => round($total_interest, 2),
        'total_management_fees' => round($total_management_fees, 2),
        'total_payment' => round($total_principal + $total_interest + $total_management_fees, 2),
        'monthly_payment' => $monthly_payment
    ];
}

function calculateMonthlyPayment($total_disbursed, $interest_rate, $months, $management_fee_rate = 5.5, $deduct_fee = true) {
    if ($total_disbursed <= 0 || $interest_rate <= 0 || $months <= 0) return 0;
    $schedule_data = generateLoanSchedule($total_disbursed, $interest_rate, $months, $management_fee_rate, $deduct_fee);
    return $schedule_data['monthly_payment'];
}

function calculateTotalInterest($total_disbursed, $interest_rate, $months, $management_fee_rate = 5.5, $deduct_fee = true) {
    if ($total_disbursed <= 0 || $interest_rate <= 0 || $months <= 0) return 0;
    $schedule_data = generateLoanSchedule($total_disbursed, $interest_rate, $months, $management_fee_rate, $deduct_fee);
    return $schedule_data['total_interest'];
}

function createInstallmentSchedule($conn, $loan_id, $loan_number, $disbursement_date, 
                                 $number_of_instalments, $user_id, $total_disbursed, $interest_rate, $management_fee_rate = 5.5, $deduct_fee = true) {
    try {
        error_log("Creating installment schedule for loan #$loan_number");
        $schedule_data = generateLoanSchedule($total_disbursed, $interest_rate, $number_of_instalments, $management_fee_rate, $deduct_fee);
        $schedule = $schedule_data['schedule'];
        $disbursement_date_obj = new DateTime($disbursement_date);
        
        foreach ($schedule as $instalment) {
            $instalment_number = $instalment['instalment_number'];
            $due_date_obj = clone $disbursement_date_obj;
            $due_date_obj->modify('+' . $instalment_number . ' months');
            $due_date = $due_date_obj->format('Y-m-d');
            
            $sql = "INSERT INTO loan_instalments (
                loan_id, loan_number, instalment_number, due_date,
                opening_balance, principal_amount, interest_amount,
                management_fee, total_payment, closing_balance,
                paid_amount, principal_paid, interest_paid, management_fee_paid,
                balance_remaining, status, days_overdue, penalty_amount,
                created_by, created_at
            ) VALUES (
                " . intval($loan_id) . ",
                '" . mysqli_real_escape_string($conn, $loan_number) . "',
                " . intval($instalment_number) . ",
                '" . mysqli_real_escape_string($conn, $due_date) . "',
                " . floatval($instalment['opening_balance']) . ",
                " . floatval($instalment['principal']) . ",
                " . floatval($instalment['interest']) . ",
                " . floatval($instalment['management_fee']) . ",
                " . floatval($instalment['total_payment']) . ",
                " . floatval($instalment['closing_balance']) . ",
                0, 0, 0, 0,
                " . floatval($instalment['total_payment']) . ",
                'Pending',
                0, 0,
                " . intval($user_id) . ",
                NOW()
            )";
            
            if (!mysqli_query($conn, $sql)) {
                error_log("ERROR: Failed to create installment #$instalment_number: " . mysqli_error($conn));
                return false;
            }
        }
        
        error_log("SUCCESS: Created $number_of_instalments installments");
        return true;
    } catch (Exception $e) {
        error_log("ERROR in createInstallmentSchedule: " . $e->getMessage());
        return false;
    }
}

function createTransactionRecord($conn, $loan_id, $loan_number, $type, $date, $amount, $description, $user_id) {
    try {
        $sql = "INSERT INTO loan_transactions (
            loan_id, loan_number, transaction_type, transaction_date,
            amount, description, created_by, created_at
        ) VALUES (
            " . intval($loan_id) . ",
            '" . mysqli_real_escape_string($conn, $loan_number) . "',
            '" . mysqli_real_escape_string($conn, $type) . "',
            '" . mysqli_real_escape_string($conn, $date) . "',
            " . floatval($amount) . ",
            '" . mysqli_real_escape_string($conn, $description) . "',
            " . intval($user_id) . ",
            NOW()
        )";
        
        if (!mysqli_query($conn, $sql)) {
            error_log("Failed to create transaction: " . mysqli_error($conn));
        }
    } catch (Exception $e) {
        error_log("Error creating transaction: " . $e->getMessage());
    }
}

// Get customers
$customers_query = "SELECT customer_id, customer_name, customer_code, current_balance
FROM customers ORDER BY customer_name";
$customers = mysqli_query($conn, $customers_query);
if (!$customers) {
    die("Error fetching customers: " . mysqli_error($conn));
}

$error_message = '';
$success_message = '';

// Generate unique loan number
$loan_number = "LN-" . date('Ymd-His');

// DEFAULT VALUES
$default_total_disbursed = 2110000;
$default_management_fee_rate = 5.5;
$default_deduct_fee = true;
$default_loan_amount = calculateLoanAmountFromDisbursed($default_total_disbursed, $default_management_fee_rate, $default_deduct_fee);
$default_management_fee = calculateManagementFeeFromDisbursed($default_total_disbursed, $default_management_fee_rate);
$default_rate = 5.0;
$default_instalments = 6;

$schedule_data = generateLoanSchedule($default_total_disbursed, $default_rate, $default_instalments, $default_management_fee_rate, $default_deduct_fee);
$default_monthly_payment = $schedule_data['monthly_payment'];
$default_total_interest = $schedule_data['total_interest'];
$default_total_management_fees = $schedule_data['total_management_fees'];
$default_total_payment = $schedule_data['total_payment'];

// Default dates in dd/mm/yyyy format
$default_disbursement_date = date('d/m/Y');
$default_maturity_date = date('d/m/Y', strtotime('+' . $default_instalments . ' months'));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("=== FORM SUBMISSION STARTED ===");
    
    $required_fields = [
        'customer_id', 'total_disbursed', 'interest_rate', 'management_fee_rate',
        'disbursement_date', 'maturity_date', 'number_of_instalments'
    ];
    
    $missing_fields = [];
    foreach ($required_fields as $field) {
        $field_value = $_POST[$field] ?? '';
        if (empty($field_value) && $field_value !== '0') {
            $missing_fields[] = str_replace('_', ' ', ucfirst($field));
        }
    }
    
    if (!empty($missing_fields)) {
        $error_message = "Missing required fields: " . implode(', ', $missing_fields);
    } else {
        $customer_id = intval($_POST['customer_id']);
        $loan_number = trim($_POST['loan_number'] ?? $loan_number);
        
        $total_disbursed = parseMoney($_POST['total_disbursed']);
        $interest_rate = floatval($_POST['interest_rate']);
        $management_fee_rate = floatval($_POST['management_fee_rate']);
        $deduct_fee = isset($_POST['deduct_fee']) && $_POST['deduct_fee'] == '1';

        // -------------------------------------------------------
        // TOP-UP: Capture is_topup and topup_type
        // -------------------------------------------------------
        $is_topup = isset($_POST['is_topup']) && $_POST['is_topup'] == '1' ? 1 : 0;
        $topup_type_raw = trim($_POST['topup_type'] ?? '');
        $valid_topup_types = [TOPUP_TYPE_INCREASING, TOPUP_TYPE_DECREASING];
        $topup_type = ($is_topup && in_array($topup_type_raw, $valid_topup_types)) ? $topup_type_raw : null;

        // Validate: if is_topup is checked, topup_type must be selected
        if ($is_topup && empty($topup_type)) {
            $error_message = "Please select a top-up type (Increasing or Decreasing) when Top-up Loan is checked.";
        }

        if (empty($error_message)) {
            $management_fee = calculateManagementFeeFromDisbursed($total_disbursed, $management_fee_rate);
            $loan_amount = calculateLoanAmountFromDisbursed($total_disbursed, $management_fee_rate, $deduct_fee);
            
            $cash_amount = parseMoney($_POST['cash_amount'] ?? '0');
            $bank_amount = parseMoney($_POST['bank_amount'] ?? '0');
            $number_of_instalments = intval($_POST['number_of_instalments']);
            
            // DATES: Accept dd/mm/yyyy input → convert to Y-m-d for DB
            $disbursement_date_raw = trim($_POST['disbursement_date']);
            $maturity_date_raw     = trim($_POST['maturity_date']);
            $disbursement_date = convertDMYtoYMD($disbursement_date_raw);
            $maturity_date     = convertDMYtoYMD($maturity_date_raw);

            $collateral_type = mysqli_real_escape_string($conn, $_POST['collateral_type'] ?? '');
            $collateral_description = mysqli_real_escape_string($conn, $_POST['collateral_description'] ?? '');
            $collateral_value = parseMoney($_POST['collateral_value'] ?? '0');
            $collateral_net_value = parseMoney($_POST['collateral_net_value'] ?? '0');
            
            // Validations
            if (!$disbursement_date || !validateDate($disbursement_date)) {
                $error_message = "Invalid disbursement date. Please use dd/mm/yyyy format.";
            } elseif (!$maturity_date || !validateDate($maturity_date)) {
                $error_message = "Invalid maturity date. Please use dd/mm/yyyy format.";
            } elseif ($maturity_date <= $disbursement_date) {
                $error_message = "Maturity date must be after disbursement date.";
            } elseif ($total_disbursed <= 0) {
                $error_message = "Total disbursed must be greater than 0";
            } elseif ($interest_rate <= 0 || $interest_rate > 100) {
                $error_message = "Interest rate must be between 0.01% and 100%";
            } elseif ($management_fee_rate < 0 || $management_fee_rate > 100) {
                $error_message = "Management fee rate must be between 0% and 100%";
            } elseif ($number_of_instalments <= 0 || $number_of_instalments > 360) {
                $error_message = "Number of instalments must be between 1 and 360";
            } else {
                $customer_check_query = "SELECT customer_id FROM customers WHERE customer_id = " . intval($customer_id);
                $customer_check = mysqli_query($conn, $customer_check_query);
                if (!$customer_check) {
                    $error_message = "Database error: " . mysqli_error($conn);
                } else {
                    if (mysqli_num_rows($customer_check) == 0) {
                        $error_message = "Selected customer does not exist or is inactive";
                        mysqli_free_result($customer_check);
                    } else {
                        mysqli_free_result($customer_check);
                        
                        $check_sql = "SELECT loan_id FROM loan_portfolio WHERE loan_number = '" . mysqli_real_escape_string($conn, $loan_number) . "'";
                        $check_result = mysqli_query($conn, $check_sql);
                        if (!$check_result) {
                            $error_message = "Database error: " . mysqli_error($conn);
                        } else {
                            if (mysqli_num_rows($check_result) > 0) {
                                $error_message = "Loan number already exists.";
                                mysqli_free_result($check_result);
                            } else {
                                mysqli_free_result($check_result);
                                
                                $schedule_data = generateLoanSchedule($total_disbursed, $interest_rate, $number_of_instalments, $management_fee_rate, $deduct_fee);
                                $total_interest = $schedule_data['total_interest'];
                                $total_management_fees = $schedule_data['total_management_fees'];
                                $total_payment = $schedule_data['total_payment'];
                                $monthly_payment = $schedule_data['monthly_payment'];
                                
                                $principal_outstanding = $total_disbursed;
                                $interest_outstanding = $total_interest;
                                $total_outstanding = $total_disbursed + $total_interest;
                                
                                $provisional_rate = 1.0;
                                $general_provision = $principal_outstanding * ($provisional_rate / 100);
                                $net_book_value = $total_outstanding - $general_provision;
                                
                                try {
                                    $disbursement_date_obj = new DateTime($disbursement_date);
                                    $month_end = new DateTime($disbursement_date);
                                    $month_end->modify('last day of this month');
                                    $accrued_days = $disbursement_date_obj->diff($month_end)->days + 1;
                                    if ($accrued_days < 0) $accrued_days = 0;
                                } catch (Exception $date_ex) {
                                    error_log("Date calculation error: " . $date_ex->getMessage());
                                    $accrued_days = 0;
                                }
                                
                                $created_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1;
                                
                                // ── APPROVAL WORKFLOW ──
                                // Collect all loan data into an array and submit for approval
                                $approval_data = [
                                    'loan_number'            => $loan_number,
                                    'customer_id'            => $customer_id,
                                    'loan_amount'            => $loan_amount,
                                    'total_disbursed'        => $total_disbursed,
                                    'interest_rate'          => $interest_rate,
                                    'management_fee_rate'    => $management_fee_rate,
                                    'management_fee'         => $management_fee,
                                    'number_of_instalments'  => $number_of_instalments,
                                    'disbursement_date'      => $disbursement_date,
                                    'maturity_date'          => $maturity_date,
                                    'total_interest'         => $total_interest,
                                    'total_management_fees'  => $total_management_fees,
                                    'total_payment'          => $total_payment,
                                    'monthly_payment'        => $monthly_payment,
                                    'cash_amount'            => $cash_amount,
                                    'bank_amount'            => $bank_amount,
                                    'collateral_type'        => $collateral_type,
                                    'collateral_description' => $collateral_description,
                                    'collateral_value'       => $collateral_value,
                                    'collateral_net_value'   => $collateral_net_value,
                                    'is_topup'               => $is_topup,
                                    'topup_type'             => $topup_type,
                                    'deduct_fee'             => $deduct_fee ? 1 : 0,
                                    'submitted_by'           => $_SESSION['username'] ?? 'system',
                                ];

                                // Get customer name for description
                                $cname_res = $conn->query("SELECT customer_name FROM customers WHERE customer_id = " . intval($customer_id));
                                $cname = $cname_res ? $cname_res->fetch_assoc()['customer_name'] : 'Customer #' . $customer_id;

                                if (submitForApproval($conn, 'add', 'loan', null, $approval_data, "Add loan $loan_number for $cname")) {
                                    $_SESSION['success_message'] = "⏳ Loan <strong>$loan_number</strong> submitted for approval. It will be activated once Director or MD approves.";
                                    echo "<script>window.location.href = '?page=loans';</script>";
                                    exit();
                                } else {
                                    $error_message = "Could not submit loan for approval: " . $conn->error;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Repopulate date fields in dd/mm/yyyy on validation error
$form_disbursement_date = '';
$form_maturity_date     = '';
if (isset($_POST['disbursement_date'])) {
    $form_disbursement_date = htmlspecialchars($_POST['disbursement_date']);
} else {
    $form_disbursement_date = $default_disbursement_date;
}
if (isset($_POST['maturity_date'])) {
    $form_maturity_date = htmlspecialchars($_POST['maturity_date']);
} else {
    $form_maturity_date = $default_maturity_date;
}

// Repopulate top-up fields on validation error
$form_is_topup   = isset($_POST['is_topup'])   ? intval($_POST['is_topup'])             : 0;
$form_topup_type = isset($_POST['topup_type'])  ? htmlspecialchars($_POST['topup_type']) : '';
?>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    /* Top-up type cards */
    .topup-type-card {
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 14px 18px;
        cursor: pointer;
        transition: all 0.2s ease;
        background: #fff;
        user-select: none;
    }
    .topup-type-card:hover {
        border-color: #6c757d;
        background: #f8f9fa;
    }
    .topup-type-card.selected-increasing {
        border-color: #198754;
        background: #d1e7dd;
    }
    .topup-type-card.selected-decreasing {
        border-color: #dc3545;
        background: #f8d7da;
    }
    .topup-type-card .topup-icon {
        font-size: 1.5rem;
        margin-bottom: 4px;
    }
    .topup-type-card .topup-label {
        font-weight: 600;
        font-size: 0.95rem;
    }
    .topup-type-card .topup-desc {
        font-size: 0.78rem;
        color: #6c757d;
        margin-top: 2px;
    }
    #topupTypeSection {
        display: none;
        animation: fadeInDown 0.25s ease;
    }
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Create New Loan</h5>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger mt-3">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" id="loanForm" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_id" class="form-label">Customer <span class="text-danger">*</span></label>
                                <select class="form-select" id="customer_id" name="customer_id" required>
                                    <option value="">Select Customer</option>
                                    <?php if ($customers && mysqli_num_rows($customers) > 0): ?>
                                        <?php
                                        mysqli_data_seek($customers, 0);
                                        while($customer = mysqli_fetch_assoc($customers)):
                                        ?>
                                            <option value="<?php echo htmlspecialchars($customer['customer_id']); ?>"
                                                <?php echo isset($_POST['customer_id']) && $_POST['customer_id'] == $customer['customer_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($customer['customer_name']); ?>
                                                (<?php echo htmlspecialchars($customer['customer_code']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <option value="">No customers found</option>
                                    <?php endif; ?>
                                </select>
                                <div class="invalid-feedback">Please select a customer</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="loan_number" class="form-label">Loan Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="loan_number" name="loan_number"
                                       value="<?php echo htmlspecialchars($loan_number); ?>" required readonly>
                                <small class="text-muted">Auto-generated</small>
                            </div>
                        </div>
                    </div>

                    <!-- ================================================
                         TOP-UP SECTION
                    ================================================= -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card border-warning mb-3">
                                <div class="card-body py-3">
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <!-- Top-up checkbox -->
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox"
                                                   role="switch"
                                                   id="is_topup" name="is_topup" value="1"
                                                   <?php echo $form_is_topup ? 'checked' : ''; ?>
                                                   onchange="toggleTopupSection()">
                                            <label class="form-check-label fw-semibold" for="is_topup">
                                                <i class="fas fa-layer-group me-1 text-warning"></i>
                                                This is a Top-up Loan
                                            </label>
                                        </div>
                                        <small class="text-muted">Enable if this loan tops up an existing loan balance</small>
                                    </div>

                                    <!-- Top-up type selector (shown only when checkbox is checked) -->
                                    <div id="topupTypeSection" class="mt-3">
                                        <label class="form-label fw-semibold mb-2">
                                            Top-up Type <span class="text-danger">*</span>
                                        </label>

                                        <!-- Hidden input carries the actual value -->
                                        <input type="hidden" id="topup_type" name="topup_type"
                                               value="<?php echo $form_topup_type; ?>">

                                        <div class="d-flex gap-3 flex-wrap">
                                            <!-- Increasing card -->
                                            <div class="topup-type-card <?php echo $form_topup_type === 'increasing' ? 'selected-increasing' : ''; ?>"
                                                 id="card_increasing"
                                                 onclick="selectTopupType('increasing')"
                                                 style="min-width:180px; max-width:220px;">
                                                <div class="topup-icon text-success">
                                                    <i class="fas fa-arrow-trend-up"></i>
                                                </div>
                                                <div class="topup-label text-success">Increasing Top-up</div>
                                                <div class="topup-desc">Loan balance is increased. Additional funds are disbursed to the customer.</div>
                                            </div>

                                            <!-- Decreasing card -->
                                            <div class="topup-type-card <?php echo $form_topup_type === 'decreasing' ? 'selected-decreasing' : ''; ?>"
                                                 id="card_decreasing"
                                                 onclick="selectTopupType('decreasing')"
                                                 style="min-width:180px; max-width:220px;">
                                                <div class="topup-icon text-danger">
                                                    <i class="fas fa-arrow-trend-down"></i>
                                                </div>
                                                <div class="topup-label text-danger">Decreasing Top-up</div>
                                                <div class="topup-desc">Loan balance is reduced. A partial settlement is applied to the outstanding amount.</div>
                                            </div>
                                        </div>

                                        <div id="topupTypeError" class="text-danger small mt-2" style="display:none;">
                                            Please select a top-up type.
                                        </div>

                                        <!-- Summary badge shown after selection -->
                                        <div id="topupTypeBadge" class="mt-2" style="display:none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ================================================ -->

                    <div class="row">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Total Disbursed:</strong> This is the starting balance for all payment calculations. 
                                Choose whether to deduct management fee upfront or include it in first installment.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="total_disbursed" class="form-label"><strong>Total Disbursed</strong> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control money-input" id="total_disbursed"
                                       name="total_disbursed" required
                                       value="<?php echo isset($_POST['total_disbursed']) ? formatMoney(parseMoney($_POST['total_disbursed'])) : formatMoney($default_total_disbursed); ?>"
                                       onchange="calculateFromDisbursed()" onkeyup="formatMoneyInput(this); calculateFromDisbursed();"
                                       data-original-value="<?php echo isset($_POST['total_disbursed']) ? parseMoney($_POST['total_disbursed']) : $default_total_disbursed; ?>">
                                <small class="text-muted">Starting balance for payment schedule</small>
                                <div class="invalid-feedback">Please enter a valid amount</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="loan_amount" class="form-label">Amount Given to Customer</label>
                                <input type="text" class="form-control bg-light money-display" id="loan_amount"
                                       value="<?php echo formatMoney($default_loan_amount); ?>" readonly>
                                <small class="text-muted">Actual cash given to customer</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="deduct_fee" name="deduct_fee" value="1"
                                        <?php echo (!isset($_POST['deduct_fee']) && $default_deduct_fee) || (isset($_POST['deduct_fee']) && $_POST['deduct_fee'] == '1') ? 'checked' : ''; ?>
                                        onchange="calculateFromDisbursed()">
                                    <label class="form-check-label" for="deduct_fee">
                                        <strong>Deduct management fee from disbursed amount</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="interest_rate" class="form-label">Monthly Interest Rate (%) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="interest_rate"
                                       name="interest_rate" step="0.1" min="0.1" max="100" required
                                       value="<?php echo isset($_POST['interest_rate']) ? htmlspecialchars($_POST['interest_rate']) : number_format($default_rate, 1, '.', ''); ?>"
                                       onchange="calculateFromDisbursed()" onkeyup="calculateFromDisbursed()">
                                <small class="text-muted">Customizable interest rate</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="management_fee_rate" class="form-label">Management Fee Rate (%) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="management_fee_rate"
                                       name="management_fee_rate" step="0.1" min="0" max="100" required
                                       value="<?php echo isset($_POST['management_fee_rate']) ? htmlspecialchars($_POST['management_fee_rate']) : number_format($default_management_fee_rate, 1, '.', ''); ?>"
                                       onchange="calculateFromDisbursed()" onkeyup="calculateFromDisbursed()">
                                <small class="text-muted">Customizable fee rate</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="number_of_instalments" class="form-label">Number of Instalments <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="number_of_instalments"
                                       name="number_of_instalments" min="1" max="360" required
                                       value="<?php echo isset($_POST['number_of_instalments']) ? htmlspecialchars($_POST['number_of_instalments']) : $default_instalments; ?>"
                                       onchange="calculateFromDisbursed(); calculateMaturityDate();"
                                       onkeyup="calculateFromDisbursed(); calculateMaturityDate();">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="disbursement_date" class="form-label">Disbursement Date <span class="text-danger">*</span></label>
                                <input type="text" class="form-control date-picker" id="disbursement_date"
                                       name="disbursement_date" required
                                       placeholder="dd/mm/yyyy"
                                       value="<?php echo $form_disbursement_date; ?>"
                                       autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="maturity_date" class="form-label">Maturity Date <span class="text-danger">*</span></label>
                                <input type="text" class="form-control date-picker" id="maturity_date"
                                       name="maturity_date" required
                                       placeholder="dd/mm/yyyy"
                                       value="<?php echo $form_maturity_date; ?>"
                                       autocomplete="off">
                                <small class="text-muted">Auto-calculated</small>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3">Loan Summary</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Mgmt Fee/Month</label>
                                <input type="text" class="form-control bg-light money-display" id="management_fee"
                                       value="<?php echo formatMoney($default_management_fee); ?>" readonly>
                                <small class="text-muted" id="fee_description">Charged in months 2-<?php echo $default_instalments; ?></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Monthly Payment</label>
                                <input type="text" class="form-control bg-light money-display" id="monthly_payment"
                                       value="<?php echo formatMoney($default_monthly_payment); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Interest</label>
                                <input type="text" class="form-control bg-light money-display" id="total_interest"
                                       value="<?php echo formatMoney($default_total_interest); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Total to Pay</label>
                                <input type="text" class="form-control bg-light money-display" id="total_payment"
                                       value="<?php echo formatMoney($default_total_payment); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3">Collateral Information</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="collateral_type" class="form-label">Collateral Type</label>
                                <select class="form-select" id="collateral_type" name="collateral_type">
                                    <option value="">Select Type</option>
                                    <?php
                                    $collateral_types = ['Land', 'House', 'Vehicle', 'Equipment', 'Guarantor', 'Other'];
                                    foreach ($collateral_types as $type):
                                        $selected = (isset($_POST['collateral_type']) && $_POST['collateral_type'] == $type) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $type; ?>" <?php echo $selected; ?>><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="collateral_value" class="form-label">Collateral Value</label>
                                <input type="text" class="form-control money-input" id="collateral_value"
                                       name="collateral_value"
                                       value="<?php echo isset($_POST['collateral_value']) ? formatMoney(parseMoney($_POST['collateral_value'])) : '0'; ?>"
                                       onkeyup="formatMoneyInput(this);"
                                       data-original-value="<?php echo isset($_POST['collateral_value']) ? parseMoney($_POST['collateral_value']) : '0'; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="collateral_net_value" class="form-label">Net Value</label>
                                <input type="text" class="form-control money-input" id="collateral_net_value"
                                       name="collateral_net_value"
                                       value="<?php echo isset($_POST['collateral_net_value']) ? formatMoney(parseMoney($_POST['collateral_net_value'])) : '0'; ?>"
                                       onkeyup="formatMoneyInput(this)"
                                       data-original-value="<?php echo isset($_POST['collateral_net_value']) ? parseMoney($_POST['collateral_net_value']) : '0'; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="collateral_description" class="form-label">Collateral Description</label>
                        <textarea class="form-control" id="collateral_description"
                                  name="collateral_description" rows="2"><?php echo isset($_POST['collateral_description']) ? htmlspecialchars($_POST['collateral_description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary" name="submit" onclick="return validateTopup()">
                            <i class="fas fa-plus-circle me-2"></i>Create Loan
                        </button>
                        <a href="index.php?page=loans" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
// -------------------------------------------------------
// Top-up section toggle & type selection
// -------------------------------------------------------
function toggleTopupSection() {
    var isChecked = document.getElementById('is_topup').checked;
    var section   = document.getElementById('topupTypeSection');

    if (isChecked) {
        section.style.display = 'block';
    } else {
        section.style.display = 'none';
        // Clear selection when unchecked
        selectTopupType(null);
    }
}

function selectTopupType(type) {
    var hiddenInput  = document.getElementById('topup_type');
    var cardInc      = document.getElementById('card_increasing');
    var cardDec      = document.getElementById('card_decreasing');
    var badge        = document.getElementById('topupTypeBadge');
    var errorEl      = document.getElementById('topupTypeError');

    // Reset card styles
    cardInc.classList.remove('selected-increasing');
    cardDec.classList.remove('selected-decreasing');
    badge.style.display = 'none';
    badge.innerHTML = '';
    errorEl.style.display = 'none';

    if (type === 'increasing') {
        hiddenInput.value = 'increasing';
        cardInc.classList.add('selected-increasing');
        badge.style.display = 'block';
        badge.innerHTML = '<span class="badge bg-success fs-6 px-3 py-2">'
            + '<i class="fas fa-arrow-trend-up me-1"></i>Increasing Top-up selected'
            + '</span>';
    } else if (type === 'decreasing') {
        hiddenInput.value = 'decreasing';
        cardDec.classList.add('selected-decreasing');
        badge.style.display = 'block';
        badge.innerHTML = '<span class="badge bg-danger fs-6 px-3 py-2">'
            + '<i class="fas fa-arrow-trend-down me-1"></i>Decreasing Top-up selected'
            + '</span>';
    } else {
        hiddenInput.value = '';
    }
}

function validateTopup() {
    var isTopup    = document.getElementById('is_topup').checked;
    var topupType  = document.getElementById('topup_type').value;
    var errorEl    = document.getElementById('topupTypeError');

    if (isTopup && !topupType) {
        errorEl.style.display = 'block';
        document.getElementById('topupTypeSection').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    return true;
}

// -------------------------------------------------------
// Money helpers
// -------------------------------------------------------
function formatNumber(number) {
    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function parseNumber(formattedNumber) {
    return parseFloat(formattedNumber.replace(/,/g, '')) || 0;
}

function formatMoneyInput(input) {
    const cursorPosition = input.selectionStart;
    const originalValue = input.value;
    let numericValue = originalValue.replace(/[^\d]/g, '');
    input.setAttribute('data-original-value', numericValue);
    
    if (numericValue) {
        input.value = formatNumber(parseInt(numericValue));
    } else {
        input.value = '';
    }
    
    let newCursorPosition = cursorPosition;
    const commaCountBefore = (originalValue.substring(0, cursorPosition).match(/,/g) || []).length;
    const commaCountAfter  = (input.value.substring(0, cursorPosition).match(/,/g) || []).length;
    newCursorPosition += commaCountAfter - commaCountBefore;
    input.setSelectionRange(newCursorPosition, newCursorPosition);
    return numericValue;
}

// -------------------------------------------------------
// Date helpers (dd/mm/yyyy)
// -------------------------------------------------------
function parseDMY(str) {
    if (!str) return null;
    var parts = str.split('/');
    if (parts.length !== 3) return null;
    var d  = parseInt(parts[0], 10);
    var m  = parseInt(parts[1], 10) - 1;
    var y  = parseInt(parts[2], 10);
    var dt = new Date(y, m, d);
    return isNaN(dt.getTime()) ? null : dt;
}

function formatDMY(date) {
    var d = String(date.getDate()).padStart(2, '0');
    var m = String(date.getMonth() + 1).padStart(2, '0');
    var y = date.getFullYear();
    return d + '/' + m + '/' + y;
}

// -------------------------------------------------------
// Loan calculation
// -------------------------------------------------------
function calculateFromDisbursed() {
    const totalDisbursed    = parseNumber(document.getElementById('total_disbursed').value) || 0;
    const interestRate      = parseFloat(document.getElementById('interest_rate').value) || 0;
    const managementFeeRate = parseFloat(document.getElementById('management_fee_rate').value) || 0;
    const instalments       = parseInt(document.getElementById('number_of_instalments').value) || 1;
    const deductFee         = document.getElementById('deduct_fee').checked;
    
    if (totalDisbursed > 0) {
        const managementFeePerMonth = Math.round(totalDisbursed * (managementFeeRate / 100));
        
        let loanAmount = deductFee ? totalDisbursed - managementFeePerMonth : totalDisbursed;
        
        document.getElementById('loan_amount').value    = formatNumber(loanAmount);
        document.getElementById('management_fee').value = formatNumber(managementFeePerMonth);
        
        const feeDesc = document.getElementById('fee_description');
        feeDesc.textContent = deductFee
            ? 'Fee deducted upfront. No fee in first installment.'
            : 'Fee included in first installment. Charged in months 1-' + instalments;
        
        if (interestRate > 0 && instalments > 0) {
            const avgBalance    = totalDisbursed / 2;
            const avgInterest   = avgBalance * (interestRate / 100);
            const totalInterest = Math.round(avgInterest * instalments);
            
            const totalManagementFees = deductFee
                ? managementFeePerMonth * (instalments - 1)
                : managementFeePerMonth * instalments;
            
            const monthlyPayment = instalments > 1
                ? Math.round((totalDisbursed + totalInterest + totalManagementFees) / instalments)
                : 0;
            const totalPayment = totalDisbursed + totalInterest + totalManagementFees;
            
            document.getElementById('monthly_payment').value = formatNumber(monthlyPayment);
            document.getElementById('total_interest').value  = formatNumber(totalInterest);
            document.getElementById('total_payment').value   = formatNumber(totalPayment);
        }
    }
}

function calculateMaturityDate() {
    const disbursementVal = document.getElementById('disbursement_date').value;
    const instalments = parseInt(document.getElementById('number_of_instalments').value) || 1;
    
    if (disbursementVal && instalments > 0) {
        var date = parseDMY(disbursementVal);
        if (!date) return;
        date.setMonth(date.getMonth() + instalments);
        
        var maturityPicker = document.getElementById('maturity_date')._flatpickr;
        if (maturityPicker) {
            maturityPicker.setDate(date, true);
        } else {
            document.getElementById('maturity_date').value = formatDMY(date);
        }
    }
}

// -------------------------------------------------------
// Initialise on page load
// -------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {

    // Flatpickr – dd/mm/yyyy date pickers
    flatpickr("#disbursement_date", {
        dateFormat: "d/m/Y",
        allowInput: true,
        onChange: function() { calculateMaturityDate(); }
    });
    flatpickr("#maturity_date", {
        dateFormat: "d/m/Y",
        allowInput: true
    });

    // Restore top-up state if PHP re-renders after a validation error
    var isTopupChecked = document.getElementById('is_topup').checked;
    if (isTopupChecked) {
        document.getElementById('topupTypeSection').style.display = 'block';
        var savedType = document.getElementById('topup_type').value;
        if (savedType) selectTopupType(savedType);
    }

    calculateFromDisbursed();
    calculateMaturityDate();
    
    document.getElementById('total_disbursed').addEventListener('input', function() {
        formatMoneyInput(this);
        calculateFromDisbursed();
    });
    document.getElementById('interest_rate').addEventListener('input', calculateFromDisbursed);
    document.getElementById('management_fee_rate').addEventListener('input', calculateFromDisbursed);
    document.getElementById('deduct_fee').addEventListener('change', calculateFromDisbursed);
    document.getElementById('number_of_instalments').addEventListener('input', function() {
        calculateFromDisbursed();
        calculateMaturityDate();
    });
});
</script>

<?php
if (isset($conn)) {
    mysqli_close($conn);
}
?>
