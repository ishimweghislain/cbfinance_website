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

// Functions
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function formatMoney($amount, $decimals = 0) {
    return number_format($amount, $decimals, '.', ',');
}

function parseMoney($moneyString) {
    return floatval(str_replace(',', '', $moneyString));
}

/**
 * Calculate management fee per month from total disbursed
 * Formula: Management Fee per Month = Total Disbursed × Management Fee Rate%
 * This fee is charged EVERY MONTH except the first month
 */
function calculateManagementFeeFromDisbursed($total_disbursed, $management_fee_rate = 5.5) {
    return round($total_disbursed * ($management_fee_rate / 100), 2);
}

/**
 * Calculate loan amount from total disbursed
 * Formula: Loan Amount = Total Disbursed - Management Fee
 */
function calculateLoanAmountFromDisbursed($total_disbursed, $management_fee_rate = 5.5) {
    $management_fee = calculateManagementFeeFromDisbursed($total_disbursed, $management_fee_rate);
    return round($total_disbursed - $management_fee, 2);
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

/**
 * Calculate PMT (Payment) using Excel's PMT formula
 */
function PMT($rate, $nper, $pv) {
    if ($rate == 0) {
        return -$pv / $nper;
    }
    
    return -$pv * ($rate * pow(1 + $rate, $nper)) / (pow(1 + $rate, $nper) - 1);
}

/**
 * Calculate IPMT (Interest Payment) using Excel's IPMT formula
 */
function IPMT($rate, $period, $nper, $pv) {
    if ($period == 1) {
        return -$pv * $rate;
    }
    
    $pmt = PMT($rate, $nper, $pv);
    
    // Calculate remaining balance after (period - 1) payments
    $remaining_balance = $pv;
    for ($i = 1; $i < $period; $i++) {
        $interest = -$remaining_balance * $rate;
        $principal = $pmt - $interest;
        $remaining_balance += $principal;
    }
    
    return -$remaining_balance * $rate;
}

/**
 * Generate complete loan schedule using TOTAL DISBURSED as beginning balance
 */
function generateLoanSchedule($total_disbursed, $interest_rate, $term, $management_fee_rate = 5.5) {
    $schedule = [];
    $monthly_rate = $interest_rate / 100;
    
    // Calculate management fee per month
    $management_fee_per_month = round($total_disbursed * ($management_fee_rate / 100), 2);
    
    // Start with TOTAL DISBURSED as opening balance
    $opening_balance = $total_disbursed;
    $total_interest = 0;
    $total_management_fees = 0;
    $total_principal = 0;
    
    for ($i = 1; $i <= $term; $i++) {
        // Calculate interest on opening balance
        $interest = round($opening_balance * $monthly_rate, 2);
        $total_interest += $interest;
        
        // Calculate principal using PPMT formula
        $principal = round(-PPMT($monthly_rate, $i, $term, $total_disbursed), 2);
        
        if ($i == 1) {
            // First installment: No management fee
            $management_fee = 0;
        } else {
            // All other installments: Management fee charged
            $management_fee = $management_fee_per_month;
            $total_management_fees += $management_fee;
        }
        
        $total_payment = $principal + $interest + $management_fee;
        $closing_balance = $opening_balance - $principal;
        
        if ($closing_balance < 0.01) $closing_balance = 0;
        
        $total_principal += $principal;
        
        $schedule[] = [
            'instalment_number' => $i,
            'opening_balance' => round($opening_balance, 2),
            'principal' => round($principal, 2),
            'interest' => round($interest, 2),
            'management_fee' => round($management_fee, 2),
            'total_payment' => round($total_payment, 2),
            'closing_balance' => round($closing_balance, 2)
        ];
        
        $opening_balance = $closing_balance;
    }
    
    // Calculate average monthly payment (excluding first and last)
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

function calculateMonthlyPayment($total_disbursed, $interest_rate, $months, $management_fee_rate = 5.5) {
    if ($total_disbursed <= 0 || $interest_rate <= 0 || $months <= 0) {
        return 0;
    }
    
    $schedule_data = generateLoanSchedule($total_disbursed, $interest_rate, $months, $management_fee_rate);
    return $schedule_data['monthly_payment'];
}

function calculateTotalInterest($total_disbursed, $interest_rate, $months, $management_fee_rate = 5.5) {
    if ($total_disbursed <= 0 || $interest_rate <= 0 || $months <= 0) {
        return 0;
    }
    
    $schedule_data = generateLoanSchedule($total_disbursed, $interest_rate, $months, $management_fee_rate);
    return $schedule_data['total_interest'];
}

// Check if loan ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Loan ID is required";
    header("Location: index.php?page=loans");
    exit();
}

$loan_id = intval($_GET['id']);

// Fetch loan details
$loan_query = "SELECT * FROM loan_portfolio WHERE loan_id = $loan_id";
$loan_result = mysqli_query($conn, $loan_query);

if (!$loan_result || mysqli_num_rows($loan_result) === 0) {
    $_SESSION['error_message'] = "Loan not found";
    header("Location: index.php?page=loans");
    exit();
}

$loan = mysqli_fetch_assoc($loan_result);

// Get customers
$customers_query = "SELECT customer_id, customer_name, customer_code, current_balance 
                    FROM customers 
                    ORDER BY customer_name";
$customers = mysqli_query($conn, $customers_query);

if (!$customers) {
    die("Error fetching customers: " . mysqli_error($conn));
}

$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    error_log("=== EDIT FORM SUBMITTED ===");
    error_log("POST data: " . print_r($_POST, true));
    
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
        // Get form data
        $customer_id = intval($_POST['customer_id']);
        $loan_number = trim($_POST['loan_number']);
        
        // PRIMARY INPUT: Total Disbursed
        $total_disbursed = parseMoney($_POST['total_disbursed']);
        
        // Get customizable rates
        $interest_rate = floatval($_POST['interest_rate']);
        $management_fee_rate = floatval($_POST['management_fee_rate']);
        
        // CALCULATE from Total Disbursed using custom management fee rate
        $management_fee = calculateManagementFeeFromDisbursed($total_disbursed, $management_fee_rate);
        $loan_amount = calculateLoanAmountFromDisbursed($total_disbursed, $management_fee_rate);
        
        $cash_amount = parseMoney($_POST['cash_amount'] ?? '0');
        $bank_amount = parseMoney($_POST['bank_amount'] ?? '0');
        $number_of_instalments = intval($_POST['number_of_instalments']);
        
        // Get dates directly from POST
        $disbursement_date = trim($_POST['disbursement_date']);
        $maturity_date = trim($_POST['maturity_date']);
        
        // Validate and format both dates
        if (!empty($disbursement_date) && validateDate($disbursement_date)) {
            $disbursement_date_obj = DateTime::createFromFormat('Y-m-d', $disbursement_date);
            $disbursement_date = $disbursement_date_obj->format('Y-m-d');
        }
        
        if (!empty($maturity_date) && validateDate($maturity_date)) {
            $maturity_date_obj = DateTime::createFromFormat('Y-m-d', $maturity_date);
            $maturity_date = $maturity_date_obj->format('Y-m-d');
        }
        
        error_log("Processed dates - Disbursement: '" . $disbursement_date . "', Maturity: '" . $maturity_date . "'");
        
        $collateral_type = mysqli_real_escape_string($conn, $_POST['collateral_type'] ?? '');
        $collateral_description = mysqli_real_escape_string($conn, $_POST['collateral_description'] ?? '');
        $collateral_value = parseMoney($_POST['collateral_value'] ?? '0');
        $collateral_net_value = parseMoney($_POST['collateral_net_value'] ?? '0');
        
        // Validate cash and bank amounts equal total disbursed
        if (($cash_amount + $bank_amount) != $total_disbursed) {
            $error_message = "Cash amount + Bank amount must equal the Total Disbursed (" . formatMoney($total_disbursed) . ").";
        } else {
            // Validate
            error_log("Validating dates - Disbursement: '" . $disbursement_date . "', Maturity: '" . $maturity_date . "'");
            
            if (empty($disbursement_date) || !validateDate($disbursement_date)) {
                $error_message = "Invalid disbursement date format. Got: '" . $disbursement_date . "'. Please use YYYY-MM-DD format";
            } elseif (empty($maturity_date) || !validateDate($maturity_date)) {
                $error_message = "Invalid maturity date format. Got: '" . $maturity_date . "'. Please use YYYY-MM-DD format";
            } elseif ($maturity_date <= $disbursement_date) {
                $error_message = "Maturity date must be after disbursement date. Disbursement: " . $disbursement_date . ", Maturity: " . $maturity_date;
            } elseif ($total_disbursed <= 0) {
                $error_message = "Total disbursed must be greater than 0";
            } elseif ($interest_rate <= 0 || $interest_rate > 100) {
                $error_message = "Interest rate must be between 0.01% and 100%";
            } elseif ($management_fee_rate < 0 || $management_fee_rate > 100) {
                $error_message = "Management fee rate must be between 0% and 100%";
            } elseif ($number_of_instalments <= 0 || $number_of_instalments > 360) {
                $error_message = "Number of instalments must be between 1 and 360";
            } else {
                // Check customer exists
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
                        
                        // Check loan number (excluding current loan)
                        $check_sql = "SELECT loan_id FROM loan_portfolio WHERE loan_number = '" . 
                                    mysqli_real_escape_string($conn, $loan_number) . "' AND loan_id != " . intval($loan_id);
                        $check_result = mysqli_query($conn, $check_sql);
                        
                        if (!$check_result) {
                            $error_message = "Database error: " . mysqli_error($conn);
                        } else {
                            if (mysqli_num_rows($check_result) > 0) {
                                $error_message = "Loan number already exists. Please use a different loan number.";
                                mysqli_free_result($check_result);
                            } else {
                                mysqli_free_result($check_result);
                                
                                // Get old values for balance adjustment
                                $old_loan_amount = floatval($loan['loan_amount']);
                                $old_customer_id = intval($loan['customer_id']);
                                
                                // Generate loan schedule using TOTAL DISBURSED and custom rates
                                $schedule_data = generateLoanSchedule($total_disbursed, $interest_rate, $number_of_instalments, $management_fee_rate);
                                $total_interest = $schedule_data['total_interest'];
                                $total_management_fees = $schedule_data['total_management_fees'];
                                $total_payment = $schedule_data['total_payment'];
                                $monthly_payment = $schedule_data['monthly_payment'];
                                
                                // Outstanding balances based on TOTAL DISBURSED
                                $principal_outstanding = $total_disbursed;
                                $interest_outstanding = $total_interest;
                                $total_outstanding = $total_disbursed + $total_interest;
                                
                                // Calculate provision
                                $provisional_rate = 1.0;
                                $general_provision = $principal_outstanding * ($provisional_rate / 100);
                                $net_book_value = $total_outstanding - $general_provision;
                                
                                // Calculate accrued days
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
                                
                                // Get existing values that shouldn't change
                                $loan_status = $loan['loan_status'];
                                $created_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1;
                                
                                // ── APPROVAL WORKFLOW ──
                                // Collect all loan data into an array and submit for approval
                                $approval_data = [
                                    'loan_id'                => $loan_id,
                                    'customer_id'            => $customer_id,
                                    'loan_number'            => $loan_number,
                                    'loan_amount'            => $loan_amount,
                                    'management_fee_rate'    => $management_fee_rate,
                                    'management_fee_amount'  => $management_fee,
                                    'total_disbursed'        => $total_disbursed,
                                    'interest_rate'          => $interest_rate,
                                    'number_of_instalments'  => $number_of_instalments,
                                    'disbursement_date'      => $disbursement_date,
                                    'maturity_date'          => $maturity_date,
                                    'total_interest'         => $total_interest,
                                    'total_management_fees'  => $total_management_fees,
                                    'total_payment'          => $total_payment,
                                    'monthly_payment'        => $monthly_payment,
                                    'principal_outstanding'  => $principal_outstanding,
                                    'interest_outstanding'   => $interest_outstanding,
                                    'total_outstanding'      => $total_outstanding,
                                    'cash_amount'            => $cash_amount,
                                    'bank_amount'            => $bank_amount,
                                    'collateral_type'        => $collateral_type,
                                    'collateral_description' => $collateral_description,
                                    'collateral_value'       => $collateral_value,
                                    'collateral_net_value'   => $collateral_net_value,
                                    'provisional_rate'       => 1.0,
                                    'general_provision'      => $general_provision,
                                    'net_book_value'         => $net_book_value,
                                    'accrued_days'           => $accrued_days,
                                    'loan_status'            => $loan_status,
                                    'old_loan_amount'        => $old_loan_amount,
                                    'old_customer_id'        => $old_customer_id
                                ];

                                // Get customer name for description
                                $cname_res = $conn->query("SELECT customer_name FROM customers WHERE customer_id = " . intval($customer_id));
                                $cname = $cname_res ? $cname_res->fetch_assoc()['customer_name'] : 'Customer #' . $customer_id;

                                if (submitForApproval($conn, 'edit', 'loan', $loan_id, $approval_data, "Update loan $loan_number for $cname")) {
                                    $_SESSION['success_message'] = "⏳ Loan update for <strong>$loan_number</strong> submitted for approval by Director or MD.";
                                    echo "<script>window.location.href='?page=loans'</script>";
                                    exit();
                                } else {
                                    $error_message = "Could not submit loan update for approval: " . $conn->error;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Function to create installment schedule
function createInstallmentSchedule($conn, $loan_id, $loan_number, $disbursement_date, 
                                 $number_of_instalments, $user_id, $total_disbursed, $interest_rate, $management_fee_rate = 5.5) {
    try {
        error_log("Creating installment schedule for loan #$loan_number");
        
        // Generate schedule using TOTAL DISBURSED
        $schedule_data = generateLoanSchedule($total_disbursed, $interest_rate, $number_of_instalments, $management_fee_rate);
        $schedule = $schedule_data['schedule'];
        
        // Parse the disbursement date
        $disbursement_date_obj = new DateTime($disbursement_date);
        
        foreach ($schedule as $instalment) {
            $instalment_number = $instalment['instalment_number'];
            
            // Calculate due date by adding months
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

// Function to create transaction record
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

// Calculate default values for display
$default_management_fee_rate = floatval($loan['management_fee_rate'] ?? 5.5);
$default_total_disbursed = floatval($loan['total_disbursed']);
$default_loan_amount = calculateLoanAmountFromDisbursed($default_total_disbursed, $default_management_fee_rate);
$default_management_fee = calculateManagementFeeFromDisbursed($default_total_disbursed, $default_management_fee_rate);

$schedule_data = generateLoanSchedule($default_total_disbursed, $loan['interest_rate'], $loan['number_of_instalments'], $default_management_fee_rate);
$default_monthly_payment = $schedule_data['monthly_payment'];
$default_total_interest = $schedule_data['total_interest'];
$default_total_management_fees = $schedule_data['total_management_fees'];
$default_total_payment = $schedule_data['total_payment'];
?>

<!-- HTML FORM -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Edit Loan Details</h5>
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger mt-3">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>
                <div class="text-muted small">
                    Status: <span class="badge bg-<?php 
                        switch($loan['loan_status']) {
                            case 'Active': echo 'success'; break;
                            case 'Closed': echo 'secondary'; break;
                            case 'Defaulted': echo 'danger'; break;
                            default: echo 'warning';
                        }
                    ?>"><?php echo htmlspecialchars($loan['loan_status']); ?></span>
                    | Created: <?php echo date('d/m/Y', strtotime($loan['created_at'])); ?>
                    | Last Updated: <?php echo date('d/m/Y', strtotime($loan['updated_at'])); ?>
                </div>
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
                                        <?php while($customer = mysqli_fetch_assoc($customers)): ?>
                                        <option value="<?php echo htmlspecialchars($customer['customer_id']); ?>"
                                                <?php echo (isset($_POST['customer_id']) && $_POST['customer_id'] == $customer['customer_id']) || 
                                                         (!isset($_POST['customer_id']) && $loan['customer_id'] == $customer['customer_id']) ? 'selected' : ''; ?>>
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
                                       value="<?php echo isset($_POST['loan_number']) ? htmlspecialchars($_POST['loan_number']) : htmlspecialchars($loan['loan_number']); ?>" required>
                                <small class="text-muted">Unique loan identifier</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Total Disbursed:</strong> This is the starting balance for all payment calculations. Management fee is charged monthly except month 1.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="total_disbursed" class="form-label"><strong>Total Disbursed</strong> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control money-input" id="total_disbursed" 
                                       name="total_disbursed" required 
                                       value="<?php echo isset($_POST['total_disbursed']) ? formatMoney(parseMoney($_POST['total_disbursed'])) : formatMoney($loan['total_disbursed']); ?>"
                                       onchange="calculateFromDisbursed()" onkeyup="formatMoneyInput(this); calculateFromDisbursed();"
                                       data-original-value="<?php echo isset($_POST['total_disbursed']) ? parseMoney($_POST['total_disbursed']) : $loan['total_disbursed']; ?>">
                                <small class="text-muted">Starting balance for payment schedule</small>
                                <div class="invalid-feedback">Please enter a valid amount</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="loan_amount" class="form-label">Amount Given to Customer</label>
                                <input type="text" class="form-control bg-light money-display" id="loan_amount"
                                       value="<?php echo formatMoney($default_loan_amount); ?>" readonly>
                                <small class="text-muted">For accounting entry only</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="cash_amount" class="form-label">Cash Amount</label>
                                <input type="text" class="form-control money-input" id="cash_amount"
                                       name="cash_amount"
                                       value="<?php echo isset($_POST['cash_amount']) ? formatMoney(parseMoney($_POST['cash_amount'])) : formatMoney($loan['cash_amount']); ?>"
                                       onkeyup="formatMoneyInput(this);"
                                       data-original-value="<?php echo isset($_POST['cash_amount']) ? parseMoney($_POST['cash_amount']) : $loan['cash_amount']; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bank_amount" class="form-label">Bank Amount</label>
                                <input type="text" class="form-control money-input" id="bank_amount"
                                       name="bank_amount"
                                       value="<?php echo isset($_POST['bank_amount']) ? formatMoney(parseMoney($_POST['bank_amount'])) : formatMoney($loan['bank_amount']); ?>"
                                       onkeyup="formatMoneyInput(this);"
                                       data-original-value="<?php echo isset($_POST['bank_amount']) ? parseMoney($_POST['bank_amount']) : $loan['bank_amount']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="interest_rate" class="form-label">Monthly Interest Rate (%) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="interest_rate" 
                                       name="interest_rate" step="0.1" min="0.1" max="100" required
                                       value="<?php echo isset($_POST['interest_rate']) ? htmlspecialchars($_POST['interest_rate']) : number_format($loan['interest_rate'], 1, '.', ''); ?>"
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
                                <label for="number_of_instalments" class="form-label">Instalments <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="number_of_instalments" 
                                       name="number_of_instalments" min="1" max="360" required
                                       value="<?php echo isset($_POST['number_of_instalments']) ? htmlspecialchars($_POST['number_of_instalments']) : $loan['number_of_instalments']; ?>"
                                       onchange="calculateFromDisbursed(); calculateMaturityDate();" 
                                       onkeyup="calculateFromDisbursed(); calculateMaturityDate();">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="disbursement_date" class="form-label">Disbursement Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="disbursement_date" 
                                       name="disbursement_date" required
                                       value="<?php echo isset($_POST['disbursement_date']) ? htmlspecialchars($_POST['disbursement_date']) : $loan['disbursement_date']; ?>"
                                       onchange="calculateMaturityDate()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="maturity_date" class="form-label">Maturity Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="maturity_date" 
                                       name="maturity_date" required
                                       value="<?php echo isset($_POST['maturity_date']) ? htmlspecialchars($_POST['maturity_date']) : $loan['maturity_date']; ?>">
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
                                <small class="text-muted">Charged in months 2-<?php echo $loan['number_of_instalments']; ?></small>
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
                                <label class="form-label">Total Interest</label>
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
                                    $current_type = isset($_POST['collateral_type']) ? $_POST['collateral_type'] : $loan['collateral_type'];
                                    foreach ($collateral_types as $type):
                                        $selected = ($current_type == $type) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $type; ?>" <?php echo $selected; ?>>
                                        <?php echo $type; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="collateral_value" class="form-label">Collateral Value</label>
                                <input type="text" class="form-control money-input" id="collateral_value" 
                                       name="collateral_value"
                                       value="<?php echo isset($_POST['collateral_value']) ? formatMoney(parseMoney($_POST['collateral_value'])) : formatMoney($loan['collateral_value']); ?>"
                                       onkeyup="formatMoneyInput(this)"
                                       data-original-value="<?php echo isset($_POST['collateral_value']) ? parseMoney($_POST['collateral_value']) : $loan['collateral_value']; ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="collateral_net_value" class="form-label">Net Value</label>
                                <input type="text" class="form-control money-input" id="collateral_net_value" 
                                       name="collateral_net_value"
                                       value="<?php echo isset($_POST['collateral_net_value']) ? formatMoney(parseMoney($_POST['collateral_net_value'])) : formatMoney($loan['collateral_net_value']); ?>"
                                       onkeyup="formatMoneyInput(this)"
                                       data-original-value="<?php echo isset($_POST['collateral_net_value']) ? parseMoney($_POST['collateral_net_value']) : $loan['collateral_net_value']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="collateral_description" class="form-label">Collateral Description</label>
                        <textarea class="form-control" id="collateral_description" 
                                  name="collateral_description" rows="2"><?php echo isset($_POST['collateral_description']) ? htmlspecialchars($_POST['collateral_description']) : htmlspecialchars($loan['collateral_description']); ?></textarea>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary" name="submit">
                            <i class="fas fa-save me-2"></i>Update Loan
                        </button>
                        <a href="index.php?page=loans" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <a href="index.php?page=viewloandetails&id=<?php echo $loan_id; ?>" class="btn btn-info">
                            <i class="fas fa-eye me-2"></i>View Details
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// SAME JAVASCRIPT FUNCTIONS AS ADD_LOAN.PHP
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
    const commaCountAfter = (input.value.substring(0, cursorPosition).match(/,/g) || []).length;
    newCursorPosition += commaCountAfter - commaCountBefore;
    input.setSelectionRange(newCursorPosition, newCursorPosition);
    
    return numericValue;
}

// MAIN CALCULATION: From Total Disbursed with customizable rates
function calculateFromDisbursed() {
    const totalDisbursed = parseNumber(document.getElementById('total_disbursed').value) || 0;
    const interestRate = parseFloat(document.getElementById('interest_rate').value) || 0;
    const managementFeeRate = parseFloat(document.getElementById('management_fee_rate').value) || 0;
    const instalments = parseInt(document.getElementById('number_of_instalments').value) || 1;
    
    if (totalDisbursed > 0) {
        // Calculate Management Fee: Total Disbursed × Management Fee Rate%
        const managementFeePerMonth = Math.round(totalDisbursed * (managementFeeRate / 100));
        
        // Calculate Loan Amount (for accounting only): Total Disbursed - Management Fee
        const loanAmount = totalDisbursed - managementFeePerMonth;
        
        // Update displays
        document.getElementById('loan_amount').value = formatNumber(loanAmount);
        document.getElementById('management_fee').value = formatNumber(managementFeePerMonth);
        
        // Calculate schedule (using total disbursed as basis)
        if (interestRate > 0 && instalments > 0) {
            const monthlyRate = interestRate / 100;
            
            // Simplified interest calculation
            const avgBalance = totalDisbursed / 2;
            const avgInterest = avgBalance * monthlyRate;
            const totalInterest = Math.round(avgInterest * instalments);
            
            // Management fee charged every month except month 1
            const totalManagementFees = managementFeePerMonth * (instalments - 1);
            
            // Approximate monthly payment
            const monthlyPayment = instalments > 1 ? Math.round((totalDisbursed + totalInterest + totalManagementFees) / instalments) : 0;
            const totalPayment = totalDisbursed + totalInterest + totalManagementFees;
            
            document.getElementById('monthly_payment').value = formatNumber(monthlyPayment);
            document.getElementById('total_interest').value = formatNumber(totalInterest);
            document.getElementById('total_payment').value = formatNumber(totalPayment);
        }
    }
}

function calculateMaturityDate() {
    const disbursementDateInput = document.getElementById('disbursement_date');
    const maturityDateInput = document.getElementById('maturity_date');
    const instalmentsInput = document.getElementById('number_of_instalments');
    
    const disbursementDate = disbursementDateInput.value;
    const instalments = parseInt(instalmentsInput.value) || 1;
    
    if (disbursementDate && instalments > 0) {
        const parts = disbursementDate.split('-');
        const date = new Date(parts[0], parts[1] - 1, parts[2]);
        
        // Add months to keep the same day of month
        date.setMonth(date.getMonth() + instalments);
        
        maturityDateInput.value = date.toISOString().split('T')[0];
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const moneyInputs = document.querySelectorAll('.money-input');
    moneyInputs.forEach(input => {
        if (input.value && input.value !== '0') {
            const numericValue = parseNumber(input.value);
            input.value = formatNumber(numericValue);
            input.setAttribute('data-original-value', numericValue);
        }
        
        input.addEventListener('blur', function() {
            formatMoneyInput(this);
        });
        
        input.addEventListener('keydown', function(e) {
            if ([46, 8, 9, 27, 13].includes(e.keyCode) ||
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true) ||
                (e.keyCode >= 35 && e.keyCode <= 39)) {
                return;
            }
            
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    });
    
    const moneyDisplays = document.querySelectorAll('.money-display');
    moneyDisplays.forEach(display => {
        if (display.tagName === 'INPUT') {
            const value = parseNumber(display.value) || 0;
            display.value = formatNumber(value);
        } else if (display.tagName === 'SPAN') {
            const value = parseNumber(display.textContent) || 0;
            display.textContent = formatNumber(value);
        }
    });
    
    calculateFromDisbursed();
    calculateMaturityDate();
});

document.getElementById('loanForm').addEventListener('submit', function(e) {
    calculateMaturityDate();
    
    const maturityDateValue = document.getElementById('maturity_date').value;
    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateRegex.test(maturityDateValue)) {
        alert('Error: Maturity date is not in correct format (YYYY-MM-DD). Got: ' + maturityDateValue);
        e.preventDefault();
        return false;
    }
    
    const moneyInputs = document.querySelectorAll('.money-input');
    moneyInputs.forEach(input => {
        const originalValue = input.getAttribute('data-original-value') || '0';
        input.value = originalValue;
    });
    
    let isValid = true;
    const form = this;
    
    form.classList.remove('was-validated');
    const inputs = form.querySelectorAll('.form-control, .form-select, .money-input');
    inputs.forEach(input => {
        input.classList.remove('is-invalid');
    });
    
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        const value = field.value.trim();
        
        if (field.classList.contains('money-input')) {
            const numValue = parseNumber(value);
            if (!value || numValue <= 0 || isNaN(numValue)) {
                field.classList.add('is-invalid');
                isValid = false;
            }
        } else {
            if (!value) {
                field.classList.add('is-invalid');
                isValid = false;
            }
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        e.stopPropagation();
        form.classList.add('was-validated');
        
        const firstError = form.querySelector('.is-invalid');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
        
        alert('Please fix the validation errors before submitting.');
        return false;
    }
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    submitBtn.disabled = true;
    
    return true;
});
</script>

<?php 
if (isset($conn)) {
    mysqli_close($conn);
}
?>
