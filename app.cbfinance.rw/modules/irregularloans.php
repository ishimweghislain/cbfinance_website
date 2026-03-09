<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../config/database.php';
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

function calculateMonthlyPayment($totalDisbursed, $annualRate, $months) {
    if ($totalDisbursed <= 0 || $annualRate <= 0 || $months <= 0) {
        return 0;
    }
    $monthlyRate = ($annualRate / 100) / 12;
    $numerator = $totalDisbursed * $monthlyRate * pow(1 + $monthlyRate, $months);
    $denominator = pow(1 + $monthlyRate, $months) - 1;
    if ($denominator == 0) {
        return 0;
    }
    $payment = $numerator / $denominator;
    return $payment;
}

function calculateTotalInterest($totalDisbursed, $monthlyPayment, $annualRate, $months) {
    $totalInterest = 0;
    $balance = $totalDisbursed;
    $monthlyRate = ($annualRate / 100) / 12;
    
    for ($i = 1; $i <= $months; $i++) {
        $interest = $balance * $monthlyRate;
        $principalPayment = $monthlyPayment - $interest;
        $totalInterest += $interest;
        $balance -= $principalPayment;
    }
    return $totalInterest;
}

// GET BEGINNING BALANCE FUNCTION
function getBeginningBalance($conn, $account_code, $transaction_date) {
    $sql = "SELECT ending_balance FROM ledger
            WHERE account_code = '" . mysqli_real_escape_string($conn, $account_code) . "'
            AND transaction_date <= '" . mysqli_real_escape_string($conn, $transaction_date) . "'
            ORDER BY transaction_date DESC, ledger_id DESC
            LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return floatval($row['ending_balance']);
    }
    return 0.00;
}

// SIMPLIFIED LEDGER FUNCTION FOR IRREGULAR CUSTOMERS - NO FEES, NO VAT
function createLedgerEntriesForIrregularLoan($conn, $loan_id, $loan_number, $customer_code, $customer_name,
                                            $disbursement_date, $cash_amount, $bank_amount, $total_disbursed,
                                            $created_by) {
    $accounts = [
        'cash_bank' => [
            'code' => '1101',
            'name' => 'Cash on Hand',
            'class' => 'Asset'
        ],
        'bank_account' => [
            'code' => '1102',
            'name' => 'Bank Account',
            'class' => 'Asset'
        ],
        'loan_to_customer' => [
            'code' => '1201',
            'name' => 'Loans to Customers',
            'class' => 'Asset'
        ]
    ];
    
    $voucher_number = $customer_code;
    $narration = "Irregular Loan #" . $loan_number . " to " . $customer_name;
    
    // ENTRY 1: Debit - Loans to Customers
    $beginning_balance1 = getBeginningBalance($conn, $accounts['loan_to_customer']['code'], $disbursement_date);
    $movement1 = $total_disbursed;
    $ending_balance1 = $beginning_balance1 + $movement1;
    
    $sql1 = "INSERT INTO ledger (
                transaction_date,
                class,
                account_code,
                account_name,
                particular,
                voucher_number,
                narration,
                beginning_balance,
                debit_amount,
                credit_amount,
                movement,
                ending_balance,
                reference_type,
                reference_id,
                created_by,
                created_at,
                updated_at
            ) VALUES (
                '" . mysqli_real_escape_string($conn, $disbursement_date) . "',
                '" . mysqli_real_escape_string($conn, $accounts['loan_to_customer']['class']) . "',
                '" . mysqli_real_escape_string($conn, $accounts['loan_to_customer']['code']) . "',
                '" . mysqli_real_escape_string($conn, $accounts['loan_to_customer']['name']) . "',
                'Irregular Loan Disbursement',
                '" . mysqli_real_escape_string($conn, $voucher_number) . "',
                '" . mysqli_real_escape_string($conn, $narration) . "',
                " . floatval($beginning_balance1) . ",
                " . floatval($total_disbursed) . ",
                0,
                " . floatval($movement1) . ",
                " . floatval($ending_balance1) . ",
                'irregular_loan',
                " . intval($loan_id) . ",
                " . intval($created_by) . ",
                NOW(),
                NOW()
            )";
    
    // ENTRY 2: Credit - Cash on Hand
    if ($cash_amount > 0) {
        $beginning_balance2 = getBeginningBalance($conn, $accounts['cash_bank']['code'], $disbursement_date);
        $movement2 = -$cash_amount;
        $ending_balance2 = $beginning_balance2 + $movement2;
        
        $sql2 = "INSERT INTO ledger (
                    transaction_date,
                    class,
                    account_code,
                    account_name,
                    particular,
                    voucher_number,
                    narration,
                    beginning_balance,
                    debit_amount,
                    credit_amount,
                    movement,
                    ending_balance,
                    reference_type,
                    reference_id,
                    created_by,
                    created_at,
                    updated_at
                ) VALUES (
                    '" . mysqli_real_escape_string($conn, $disbursement_date) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['cash_bank']['class']) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['cash_bank']['code']) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['cash_bank']['name']) . "',
                    'Cash Payment',
                    '" . mysqli_real_escape_string($conn, $voucher_number) . "',
                    '" . mysqli_real_escape_string($conn, $narration) . "',
                    " . floatval($beginning_balance2) . ",
                    0,
                    " . floatval($cash_amount) . ",
                    " . floatval($movement2) . ",
                    " . floatval($ending_balance2) . ",
                    'irregular_loan',
                    " . intval($loan_id) . ",
                    " . intval($created_by) . ",
                    NOW(),
                    NOW()
                )";
    }
    
    // ENTRY 3: Credit - Bank Account
    if ($bank_amount > 0) {
        $beginning_balance3 = getBeginningBalance($conn, $accounts['bank_account']['code'], $disbursement_date);
        $movement3 = -$bank_amount;
        $ending_balance3 = $beginning_balance3 + $movement3;
        
        $sql3 = "INSERT INTO ledger (
                    transaction_date,
                    class,
                    account_code,
                    account_name,
                    particular,
                    voucher_number,
                    narration,
                    beginning_balance,
                    debit_amount,
                    credit_amount,
                    movement,
                    ending_balance,
                    reference_type,
                    reference_id,
                    created_by,
                    created_at,
                    updated_at
                ) VALUES (
                    '" . mysqli_real_escape_string($conn, $disbursement_date) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['bank_account']['class']) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['bank_account']['code']) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['bank_account']['name']) . "',
                    'Bank Transfer',
                    '" . mysqli_real_escape_string($conn, $voucher_number) . "',
                    '" . mysqli_real_escape_string($conn, $narration) . "',
                    " . floatval($beginning_balance3) . ",
                    0,
                    " . floatval($bank_amount) . ",
                    " . floatval($movement3) . ",
                    " . floatval($ending_balance3) . ",
                    'irregular_loan',
                    " . intval($loan_id) . ",
                    " . intval($created_by) . ",
                    NOW(),
                    NOW()
                )";
    }
    
    // Execute all queries
    $queries = [$sql1];
    if ($cash_amount > 0) {
        $queries[] = $sql2;
    }
    if ($bank_amount > 0) {
        $queries[] = $sql3;
    }
    
    foreach ($queries as $index => $sql) {
        if (!mysqli_query($conn, $sql)) {
            error_log("ERROR: Ledger entry " . ($index + 1) . " failed: " . mysqli_error($conn));
            return false;
        }
    }
    
    return true;
}

// SIMPLIFIED ACCRUAL FUNCTION FOR IRREGULAR CUSTOMERS
function createAccrualEntriesForIrregularLoan($conn, $loan_id, $loan_number, $customer_code, $customer_name,
                                             $disbursement_date, $first_instalment_due_date,
                                             $interest_rate, $total_disbursed, $created_by) {
    try {
        $disbursement_obj = new DateTime($disbursement_date);
        $due_date_obj = new DateTime($first_instalment_due_date);
        
        $accounts = [
            'interest_receivable' => [
                'code' => '1203',
                'name' => 'Interest Receivable',
                'class' => 'Asset'
            ],
            'interest_on_loans' => [
                'code' => '4101',
                'name' => 'Interest on Loans',
                'class' => 'Revenue'
            ]
        ];
        
        $voucher_number = $customer_code;
        $narration = "Accruals for Irregular Customer " . $customer_code;
        
        // Period 1: From disbursement date to end of month
        $month_end = clone $disbursement_obj;
        $month_end->modify('last day of this month');
        $month_end_date = $month_end->format('Y-m-d');
        
        $period1_days = $disbursement_obj->diff($month_end)->days + 1;
        if ($period1_days < 0) $period1_days = 0;
        
        // Period 2: Remaining days to complete 30 days
        $period2_days = 30 - $period1_days;
        if ($period2_days < 0) $period2_days = 0;
        
        // Calculate daily interest
        $monthly_interest = ($total_disbursed * ($interest_rate / 100)) / 12;
        $daily_interest = $monthly_interest / 30;
        
        // Create entries for PERIOD 1
        if ($period1_days > 0) {
            $interest_accrued = $daily_interest * $period1_days;
            
            if ($interest_accrued > 0) {
                // ENTRY 1A: Debit - Interest Receivable
                $beginning_balance1 = getBeginningBalance($conn, $accounts['interest_receivable']['code'], $month_end_date);
                $movement1 = $interest_accrued;
                $ending_balance1 = $beginning_balance1 + $movement1;
                
                $sql1 = "INSERT INTO ledger (
                            transaction_date,
                            class,
                            account_code,
                            account_name,
                            particular,
                            voucher_number,
                            narration,
                            beginning_balance,
                            debit_amount,
                            credit_amount,
                            movement,
                            ending_balance,
                            reference_type,
                            reference_id,
                            created_by,
                            created_at,
                            updated_at
                        ) VALUES (
                            '" . mysqli_real_escape_string($conn, $month_end_date) . "',
                            '" . mysqli_real_escape_string($conn, $accounts['interest_receivable']['class']) . "',
                            '" . mysqli_real_escape_string($conn, $accounts['interest_receivable']['code']) . "',
                            '" . mysqli_real_escape_string($conn, $accounts['interest_receivable']['name']) . "',
                            'Accrual - Interest (Irregular)',
                            '" . mysqli_real_escape_string($conn, $voucher_number) . "',
                            '" . mysqli_real_escape_string($conn, $narration) . "',
                            " . floatval($beginning_balance1) . ",
                            " . floatval($interest_accrued) . ",
                            0,
                            " . floatval($movement1) . ",
                            " . floatval($ending_balance1) . ",
                            'irregular_loan_accrual',
                            " . intval($loan_id) . ",
                            " . intval($created_by) . ",
                            NOW(),
                            NOW()
                        )";
                
                // ENTRY 2A: Credit - Interest on Loans
                $beginning_balance2 = getBeginningBalance($conn, $accounts['interest_on_loans']['code'], $month_end_date);
                $movement2 = $interest_accrued;
                $ending_balance2 = $beginning_balance2 + $movement2;
                
                $sql2 = "INSERT INTO ledger (
                            transaction_date,
                            class,
                            account_code,
                            account_name,
                            particular,
                            voucher_number,
                            narration,
                            beginning_balance,
                            debit_amount,
                            credit_amount,
                            movement,
                            ending_balance,
                            reference_type,
                            reference_id,
                            created_by,
                            created_at,
                            updated_at
                        ) VALUES (
                            '" . mysqli_real_escape_string($conn, $month_end_date) . "',
                            '" . mysqli_real_escape_string($conn, $accounts['interest_on_loans']['class']) . "',
                            '" . mysqli_real_escape_string($conn, $accounts['interest_on_loans']['code']) . "',
                            '" . mysqli_real_escape_string($conn, $accounts['interest_on_loans']['name']) . "',
                            'Accrual - Interest (Irregular)',
                            '" . mysqli_real_escape_string($conn, $voucher_number) . "',
                            '" . mysqli_real_escape_string($conn, $narration) . "',
                            " . floatval($beginning_balance2) . ",
                            0,
                            " . floatval($interest_accrued) . ",
                            " . floatval($movement2) . ",
                            " . floatval($ending_balance2) . ",
                            'irregular_loan_accrual',
                            " . intval($loan_id) . ",
                            " . intval($created_by) . ",
                            NOW(),
                            NOW()
                        )";
                
                if (!mysqli_query($conn, $sql1) || !mysqli_query($conn, $sql2)) {
                    error_log("ERROR: Period 1 interest accrual entries failed for irregular loan");
                    return false;
                }
            }
        }
        
        // Create entries for PERIOD 2
        if ($period2_days > 0) {
            $interest_accrued2 = $daily_interest * $period2_days;
            
            if ($interest_accrued2 > 0) {
                // ENTRY 1B: Debit - Interest Receivable for period 2
                $beginning_balance1b = getBeginningBalance($conn, $accounts['interest_receivable']['code'], $first_instalment_due_date);
                $movement1b = $interest_accrued2;
                $ending_balance1b = $beginning_balance1b + $movement1b;
                
                $sql1b = "INSERT INTO ledger (
                            transaction_date,
                            class,
                            account_code,
                            account_name,
                            particular,
                            voucher_number,
                            narration,
                            beginning_balance,
                            debit_amount,
                            credit_amount,
                            movement,
                            ending_balance,
                            reference_type,
                            reference_id,
                            created_by,
                            created_at,
                            updated_at
                        ) VALUES (
                            '" . mysqli_real_escape_string($conn, $first_instalment_due_date) . "',
                            '" . mysqli_real_escape_string($conn, $accounts['interest_receivable']['class']) . "',
                            '" . mysqli_real_escape_string($conn, $accounts['interest_receivable']['code']) . "',
                            '" . mysqli_real_escape_string($conn, $accounts['interest_receivable']['name']) . "',
                            'Accrual - Interest (Irregular)',
                            '" . mysqli_real_escape_string($conn, $voucher_number) . "',
                            '" . mysqli_real_escape_string($conn, $narration) . "',
                            " . floatval($beginning_balance1b) . ",
                            " . floatval($interest_accrued2) . ",
                            0,
                            " . floatval($movement1b) . ",
                            " . floatval($ending_balance1b) . ",
                            'irregular_loan_accrual',
                            " . intval($loan_id) . ",
                            " . intval($created_by) . ",
                            NOW(),
                            NOW()
                        )";
                
                // ENTRY 2B: Credit - Interest on Loans for period 2
                $beginning_balance2b = getBeginningBalance($conn, $accounts['interest_on_loans']['code'], $first_instalment_due_date);
                $movement2b = $interest_accrued2;
                $ending_balance2b = $beginning_balance2b + $movement2b;
                
                $sql2b = "INSERT INTO ledger (
                            transaction_date,
                            class,
                            account_code,
                            account_name,
                            particular,
                            voucher_number,
                            narration,
                            beginning_balance,
                            debit_amount,
                            credit_amount,
                            movement,
                            ending_balance,
                            reference_type,
                            reference_id,
                            created_by,
                            created_at,
                            updated_at
                        ) VALUES (
                            '" . mysqli_real_escape_string($conn, $first_instalment_due_date) . "',
                            '" . mysqli_real_escape_string($conn, $accounts['interest_on_loans']['class']) . "',
                            '" . mysqli_real_escape_string($conn, $accounts['interest_on_loans']['code']) . "',
                            '" . mysqli_real_escape_string($conn, $accounts['interest_on_loans']['name']) . "',
                            'Accrual - Interest (Irregular)',
                            '" . mysqli_real_escape_string($conn, $voucher_number) . "',
                            '" . mysqli_real_escape_string($conn, $narration) . "',
                            " . floatval($beginning_balance2b) . ",
                            0,
                            " . floatval($interest_accrued2) . ",
                            " . floatval($movement2b) . ",
                            " . floatval($ending_balance2b) . ",
                            'irregular_loan_accrual',
                            " . intval($loan_id) . ",
                            " . intval($created_by) . ",
                            NOW(),
                            NOW()
                        )";
                
                if (!mysqli_query($conn, $sql1b) || !mysqli_query($conn, $sql2b)) {
                    error_log("ERROR: Period 2 interest accrual entries failed for irregular loan");
                    return false;
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("ERROR creating accrual entries for irregular loan: " . $e->getMessage());
        return false;
    }
}

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

// Generate unique loan number
$loan_number = "IR-LN-" . date('Ymd-His');

// Calculate default values
$default_principal = 2118000;
$default_rate = 60;
$default_instalments = 3;

// Calculate values for display WITHOUT FEES
$default_total_disbursed = $default_principal;
$default_payment = calculateMonthlyPayment($default_total_disbursed, $default_rate, $default_instalments);
$default_total_interest = calculateTotalInterest($default_total_disbursed, $default_payment, $default_rate, $default_instalments);
$default_total_outstanding = $default_total_disbursed + $default_total_interest;
$default_total_payable = $default_total_outstanding;
$default_final_payment = $default_payment;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("=== IRREGULAR LOAN FORM SUBMITTED ===");
    
    $required_fields = [
        'customer_id', 'disbursement_amount', 'interest_rate',
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
        $loan_number = trim($_POST['loan_number'] ?? $loan_number);
        $disbursement_amount = parseMoney($_POST['disbursement_amount']);
        $cash_amount = parseMoney($_POST['cash_amount'] ?? '0');
        $bank_amount = parseMoney($_POST['bank_amount'] ?? '0');
        $interest_rate = floatval($_POST['interest_rate']);
        $number_of_instalments = intval($_POST['number_of_instalments']);
        
        // Get the FINAL PAYMENT from the hidden input field
        $final_payment_input = $_POST['final_payment_input'] ?? '';
        $final_payment_amount = parseMoney($final_payment_input);
        
        $disbursement_date = trim($_POST['disbursement_date']);
        $maturity_date = trim($_POST['maturity_date']);
        
        if (!empty($disbursement_date) && validateDate($disbursement_date)) {
            $disbursement_date_obj = DateTime::createFromFormat('Y-m-d', $disbursement_date);
            $disbursement_date = $disbursement_date_obj->format('Y-m-d');
        }
        
        if (!empty($maturity_date) && validateDate($maturity_date)) {
            $maturity_date_obj = DateTime::createFromFormat('Y-m-d', $maturity_date);
            $maturity_date = $maturity_date_obj->format('Y-m-d');
        }
        
        $collateral_type = mysqli_real_escape_string($conn, $_POST['collateral_type'] ?? '');
        $collateral_description = mysqli_real_escape_string($conn, $_POST['collateral_description'] ?? '');
        $collateral_value = parseMoney($_POST['collateral_value'] ?? '0');
        $collateral_net_value = parseMoney($_POST['collateral_net_value'] ?? '0');
        
        // Validate cash and bank amounts
        if (($cash_amount + $bank_amount) != $disbursement_amount) {
            $error_message = "Cash amount + Bank amount must equal the total disbursement amount. Cash: " . formatMoney($cash_amount) . " + Bank: " . formatMoney($bank_amount) . " = " . formatMoney($cash_amount + $bank_amount) . " (Should be: " . formatMoney($disbursement_amount) . ")";
        } else {
            // Set days overdue to 0 for all installments
            $days_overdue_array = [];
            for ($i = 1; $i <= $number_of_instalments; $i++) {
                $days_overdue_array[$i] = 0;
            }
            
            // Validate
            if (empty($disbursement_date) || !validateDate($disbursement_date)) {
                $error_message = "Invalid disbursement date format. Please use YYYY-MM-DD format";
            } elseif (empty($maturity_date) || !validateDate($maturity_date)) {
                $error_message = "Invalid maturity date format. Please use YYYY-MM-DD format";
            } elseif ($maturity_date <= $disbursement_date) {
                $error_message = "Maturity date must be after disbursement date";
            } elseif ($disbursement_amount <= 0) {
                $error_message = "Disbursement amount must be greater than 0";
            } elseif ($interest_rate <= 0 || $interest_rate > 100) {
                $error_message = "Interest rate must be between 0.01% and 100%";
            } elseif ($number_of_instalments <= 0 || $number_of_instalments > 360) {
                $error_message = "Number of instalments must be between 1 and 360";
            } elseif ($final_payment_amount <= 0) {
                $error_message = "Final payment amount must be greater than 0";
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
                        
                        // Check loan number
                        $check_sql = "SELECT loan_id FROM loan_portfolio WHERE loan_number = '" . mysqli_real_escape_string($conn, $loan_number) . "'";
                        $check_result = mysqli_query($conn, $check_sql);
                        
                        if (!$check_result) {
                            $error_message = "Database error: " . mysqli_error($conn);
                        } else {
                            if (mysqli_num_rows($check_result) > 0) {
                                $error_message = "Loan number already exists. Please use a different loan number.";
                                mysqli_free_result($check_result);
                            } else {
                                mysqli_free_result($check_result);
                                
                                // Calculate amounts WITHOUT FEES
                                $disbursement_fees_calc = 0;
                                $disbursement_vat_calc = 0;
                                $total_disbursed = $disbursement_amount;
                                $principal_outstanding = $disbursement_amount;
                                
                                $monthly_rate = ($interest_rate / 100) / 12;
                                $numerator = $total_disbursed * $monthly_rate * pow(1 + $monthly_rate, $number_of_instalments);
                                $denominator = pow(1 + $monthly_rate, $number_of_instalments) - 1;
                                $monthly_payment = ($denominator != 0) ? $numerator / $denominator : 0;
                                
                                // Calculate total interest
                                $total_interest = 0;
                                $balance = $total_disbursed;
                                for ($i = 1; $i <= $number_of_instalments; $i++) {
                                    $interest = $balance * $monthly_rate;
                                    $principalPayment = $monthly_payment - $interest;
                                    $total_interest += $interest;
                                    $balance -= $principalPayment;
                                }
                                
                                $interest_outstanding = $total_interest;
                                $total_outstanding = $total_disbursed + $total_interest;
                                
                                // NO MONITORING FEES
                                $monitoring_fees = 0;
                                $monitoring_fees_vat = 0;
                                $total_fees = 0;
                                $total_payable = $total_outstanding;
                                
                                $final_payment_stored = $final_payment_amount;
                                
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
                                    $accrued_days = 0;
                                }
                                
                                // Set default values
                                $loan_status = 'Active';
                                $penalties = 0.0;
                                $accrued_interest = 0.0;
                                $accrued_monitoring_fees = 0.0;
                                $accrued_monitoring_fees_vat = 0.0;
                                $deferred_disbursement_fees = 0;
                                $deferred_disbursement_fees_vat = 0;
                                $accumulated_loan_amount = $total_disbursed;
                                $created_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1;
                                $total_days_overdue = 0;
                                
                                // Start transaction
                                mysqli_begin_transaction($conn);
                                
                                try {
                                    // Create loan in loan_portfolio
                                    $sql = "INSERT INTO loan_portfolio (
                                                customer_id, loan_number, disbursement_amount, interest_rate,
                                                disbursement_date, maturity_date, number_of_instalments, instalment_amount,
                                                loan_status, total_disbursed, principal_outstanding, interest_outstanding, total_outstanding,
                                                disbursement_fees, disbursement_fees_vat, total_fees, total_interest, total_payable,
                                                monitoring_fees, monitoring_fees_vat, accrued_interest, accrued_days,
                                                accrued_monitoring_fees, accrued_monitoring_fees_vat,
                                                deferred_disbursement_fees, deferred_disbursement_fees_vat, days_overdue,
                                                penalties, accumulated_loan_amount, collateral_type, collateral_description,
                                                collateral_value, collateral_net_value, provisional_rate, general_provision,
                                                net_book_value, created_by, created_at, updated_at
                                            ) VALUES (
                                                " . intval($customer_id) . ",
                                                '" . mysqli_real_escape_string($conn, $loan_number) . "',
                                                " . floatval($disbursement_amount) . ",
                                                " . floatval($interest_rate) . ",
                                                '" . mysqli_real_escape_string($conn, $disbursement_date) . "',
                                                '" . mysqli_real_escape_string($conn, $maturity_date) . "',
                                                " . intval($number_of_instalments) . ",
                                                " . floatval($final_payment_stored) . ",
                                                'Active',
                                                " . floatval($total_disbursed) . ",
                                                " . floatval($principal_outstanding) . ",
                                                " . floatval($total_interest) . ",
                                                " . floatval($total_outstanding) . ",
                                                0,
                                                0,
                                                0,
                                                " . floatval($total_interest) . ",
                                                " . floatval($total_payable) . ",
                                                0,
                                                0,
                                                0.0,
                                                " . intval($accrued_days) . ",
                                                0.0,
                                                0.0,
                                                0,
                                                0,
                                                " . intval(0) . ",
                                                0.0,
                                                " . floatval($accumulated_loan_amount) . ",
                                                '" . mysqli_real_escape_string($conn, $collateral_type) . "',
                                                '" . mysqli_real_escape_string($conn, $collateral_description) . "',
                                                " . floatval($collateral_value) . ",
                                                " . floatval($collateral_net_value) . ",
                                                1.0,
                                                " . floatval($general_provision) . ",
                                                " . floatval($net_book_value) . ",
                                                " . intval($created_by) . ",
                                                NOW(),
                                                NOW()
                                            )";
                                    
                                    error_log("Inserting irregular loan: " . $sql);
                                    
                                    if (!mysqli_query($conn, $sql)) {
                                        throw new Exception("Failed to add irregular loan: " . mysqli_error($conn));
                                    }
                                    
                                    $loan_id = mysqli_insert_id($conn);
                                    error_log("Irregular loan created with ID: " . $loan_id);
                                    
                                    // Get customer information
                                    $customer_info_query = "SELECT customer_code, customer_name FROM customers WHERE customer_id = " . intval($customer_id);
                                    $customer_info_result = mysqli_query($conn, $customer_info_query);
                                    
                                    if (!$customer_info_result || mysqli_num_rows($customer_info_result) == 0) {
                                        throw new Exception("Failed to fetch customer information for ledger");
                                    }
                                    
                                    $customer_info = mysqli_fetch_assoc($customer_info_result);
                                    mysqli_free_result($customer_info_result);
                                    
                                    // CREATE LEDGER ENTRIES
                                    $ledger_success = createLedgerEntriesForIrregularLoan(
                                        $conn,
                                        $loan_id,
                                        $loan_number,
                                        $customer_info['customer_code'],
                                        $customer_info['customer_name'],
                                        $disbursement_date,
                                        $cash_amount,
                                        $bank_amount,
                                        $total_disbursed,
                                        $created_by
                                    );
                                    
                                    if (!$ledger_success) {
                                        throw new Exception("Failed to create ledger entries for irregular loan");
                                    }
                                    
                                    // Update customer balance
                                    $update_sql = "UPDATE customers SET
                                                    current_balance = current_balance + " . floatval($total_disbursed) . ",
                                                    total_loans = total_loans + " . floatval($total_disbursed) . ",
                                                    updated_at = NOW()
                                                    WHERE customer_id = " . intval($customer_id);
                                    
                                    if (!mysqli_query($conn, $update_sql)) {
                                        throw new Exception("Failed to update customer balance: " . mysqli_error($conn));
                                    }
                                    
                                    // Create installment schedule
                                    error_log("Creating installment schedule for loan #$loan_id");
                                    $instalment_success = createIrregularInstallmentSchedule(
                                        $conn,
                                        $loan_id,
                                        $loan_number,
                                        $monthly_payment, // Use monthly payment, not final_payment_stored
                                        $disbursement_date,
                                        $number_of_instalments,
                                        $created_by,
                                        $total_disbursed,
                                        $interest_rate,
                                        $days_overdue_array
                                    );
                                    
                                    if (!$instalment_success) {
                                        throw new Exception("Failed to create installment schedule");
                                    }
                                    
                                    // Verify installments were created
                                    $verify_instalments_sql = "SELECT COUNT(*) as count FROM loan_instalments WHERE loan_id = " . intval($loan_id);
                                    $verify_instalments_result = mysqli_query($conn, $verify_instalments_sql);
                                    if ($verify_instalments_result) {
                                        $row = mysqli_fetch_assoc($verify_instalments_result);
                                        error_log("Created " . $row['count'] . " installments for loan #" . $loan_id);
                                    }
                                    
                                    // Create transaction record
                                    createIrregularTransactionRecord($conn, $loan_id, $loan_number, 'Disbursement',
                                        $disbursement_date, $total_disbursed,
                                        "Irregular loan disbursement", $created_by);
                                    
                                    // CREATE ACCRUAL ENTRIES
                                    $first_instalment_due_date = date('Y-m-d', strtotime($disbursement_date . ' +30 days'));
                                    
                                    $accrual_success = createAccrualEntriesForIrregularLoan(
                                        $conn,
                                        $loan_id,
                                        $loan_number,
                                        $customer_info['customer_code'],
                                        $customer_info['customer_name'],
                                        $disbursement_date,
                                        $first_instalment_due_date,
                                        $interest_rate,
                                        $total_disbursed,
                                        $created_by
                                    );
                                    
                                    if (!$accrual_success) {
                                        error_log("WARNING: Accrual entries could not be created for irregular loan");
                                    }
                                    
                                    // Commit transaction
                                    mysqli_commit($conn);
                                    
                                    $_SESSION['success_message'] = "Irregular loan created successfully! Loan Number: " . htmlspecialchars($loan_number);
                                    echo "<script>
                                            window.location.href = '?page=loans';
                                          </script>";
                                    exit();
                                    
                                } catch (Exception $e) {
                                    mysqli_rollback($conn);
                                    $error_message = $e->getMessage();
                                    error_log("Irregular loan creation error: " . $e->getMessage());
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Function to create installment schedule for irregular customers (NO FEES) - FIXED
function createIrregularInstallmentSchedule($conn, $loan_id, $loan_number, $monthly_payment,
                                           $disbursement_date, $number_of_instalments, $user_id,
                                           $total_disbursed, $interest_rate,
                                           $days_overdue_array = []) {
    try {
        error_log("Creating irregular installment schedule for loan #$loan_id");
        error_log("Monthly payment: $monthly_payment, Total disbursed: $total_disbursed, Interest rate: $interest_rate");
        
        // Use actual interest rate for calculation
        $monthly_rate = ($interest_rate / 100) / 12;
        $balance = $total_disbursed;
        
        for ($i = 1; $i <= $number_of_instalments; $i++) {
            // Calculate due date: disbursement date + (i * 30 days)
            $due_date = date('Y-m-d', strtotime($disbursement_date . ' +' . ($i * 30) . ' days'));
            
            // Calculate interest for this installment
            $interest_amount = $balance * $monthly_rate;
            
            // Calculate principal payment
            $principal_amount = $monthly_payment - $interest_amount;
            
            // For the last installment, adjust to clear the balance
            if ($i == $number_of_instalments) {
                $principal_amount = $balance;
                $total_amount = $principal_amount + $interest_amount;
            } else if ($principal_amount <= 0) {
                $principal_amount = 0;
                $total_amount = $interest_amount;
            } else {
                $total_amount = $monthly_payment;
            }
            
            // Ensure principal doesn't exceed remaining balance
            if ($principal_amount > $balance) {
                $principal_amount = $balance;
                $total_amount = $principal_amount + $interest_amount;
            }
            
            // Update balance for next iteration
            $new_balance = $balance - $principal_amount;
            
            // Build SQL query
            $sql = "INSERT INTO loan_instalments (
                        loan_id, loan_number, instalment_number, due_date,
                        amount, interest_amount, principal_amount,
                        fees_amount, monitoring_fee, total_amount,
                        paid_amount, balance, status,
                        days_overdue, penalty_amount, created_by, created_at
                    ) VALUES (
                        " . intval($loan_id) . ",
                        '" . mysqli_real_escape_string($conn, $loan_number) . "',
                        " . intval($i) . ",
                        '" . mysqli_real_escape_string($conn, $due_date) . "',
                        " . floatval($total_amount) . ",
                        " . floatval($interest_amount) . ",
                        " . floatval($principal_amount) . ",
                        0,
                        0,
                        " . floatval($total_amount) . ",
                        0,
                        " . floatval($total_amount) . ",
                        'Pending',
                        0,
                        0,
                        " . intval($user_id) . ",
                        NOW()
                    )";
            
            error_log("SQL for installment #$i: " . $sql);
            
            if (!mysqli_query($conn, $sql)) {
                error_log("Failed to create installment #$i: " . mysqli_error($conn));
                return false;
            } else {
                error_log("Created installment #$i with due date: $due_date, Amount: $total_amount");
            }
            
            // Update balance for next iteration
            $balance = $new_balance;
        }
        
        error_log("Successfully created $number_of_instalments installments for loan #$loan_id");
        return true;
    } catch (Exception $e) {
        error_log("ERROR in createIrregularInstallmentSchedule: " . $e->getMessage());
        return false;
    }
}

// Function to create transaction record for irregular loans
function createIrregularTransactionRecord($conn, $loan_id, $loan_number, $type, $date, $amount, $description, $user_id) {
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
            error_log("Failed to create irregular transaction: " . mysqli_error($conn));
        }
    } catch (Exception $e) {
        error_log("Error creating irregular transaction: " . $e->getMessage());
    }
}
?>

<!-- HTML FORM -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-warning">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Irregular Customer Loan Details</h5>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger mt-3">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" id="irregularLoanForm" novalidate>
                    <input type="hidden" id="final_payment_input" name="final_payment_input" value="<?php echo isset($_POST['final_payment_input']) ? htmlspecialchars($_POST['final_payment_input']) : formatMoney($default_final_payment); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_id" class="form-label">Customer <span class="text-danger">*</span></label>
                                <select class="form-select" id="customer_id" name="customer_id" required>
                                    <option value="">Select Irregular Customer</option>
                                    <?php if ($customers && mysqli_num_rows($customers) > 0): ?>
                                        <?php mysqli_data_seek($customers, 0); ?>
                                        <?php while($customer = mysqli_fetch_assoc($customers)): ?>
                                        <option value="<?php echo htmlspecialchars($customer['customer_id']); ?>"
                                            <?php echo isset($_POST['customer_id']) && $_POST['customer_id'] == $customer['customer_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($customer['customer_name']); ?>
                                            (<?php echo htmlspecialchars($customer['customer_code']); ?>)
                                            - Balance: <?php echo formatMoney($customer['current_balance']); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <option value="">No irregular customers found</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="loan_number" class="form-label">Loan Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="loan_number" name="loan_number"
                                       value="<?php echo htmlspecialchars($loan_number); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="disbursement_amount" class="form-label">Amount given <span class="text-danger">*</span></label>
                                <input type="text" class="form-control money-input" id="disbursement_amount"
                                       name="disbursement_amount" required
                                       value="<?php echo isset($_POST['disbursement_amount']) ? formatMoney(parseMoney($_POST['disbursement_amount'])) : formatMoney($default_principal); ?>"
                                       onchange="calculateIrregularInstalment()" onkeyup="formatMoneyInput(this); calculateIrregularInstalment();">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="cash_amount" class="form-label">Cash Amount</label>
                                <input type="text" class="form-control money-input" id="cash_amount"
                                       name="cash_amount"
                                       value="<?php echo isset($_POST['cash_amount']) ? formatMoney(parseMoney($_POST['cash_amount'])) : '0'; ?>"
                                       onkeyup="formatMoneyInput(this); updateIrregularDisbursementAmount();">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="bank_amount" class="form-label">Bank Transfer</label>
                                <input type="text" class="form-control money-input" id="bank_amount"
                                       name="bank_amount"
                                       value="<?php echo isset($_POST['bank_amount']) ? formatMoney(parseMoney($_POST['bank_amount'])) : '0'; ?>"
                                       onkeyup="formatMoneyInput(this); updateIrregularDisbursementAmount();">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="interest_rate" class="form-label">Annual Interest Rate (%) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="interest_rate"
                                       name="interest_rate" step="0.01" min="0.01" max="100" required
                                       value="<?php echo isset($_POST['interest_rate']) ? htmlspecialchars($_POST['interest_rate']) : number_format($default_rate, 2, '.', ''); ?>"
                                       onchange="calculateIrregularInstalment()" onkeyup="calculateIrregularInstalment()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="number_of_instalments" class="form-label">Number of Instalments (Months) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="number_of_instalments"
                                       name="number_of_instalments" min="1" max="360" required
                                       value="<?php echo isset($_POST['number_of_instalments']) ? htmlspecialchars($_POST['number_of_instalments']) : $default_instalments; ?>"
                                       onchange="calculateIrregularInstalment(); calculateIrregularMaturityDate();"
                                       onkeyup="calculateIrregularInstalment(); calculateIrregularMaturityDate();">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="instalment_amount" class="form-label">Generated Monthly Instalment <span class="text-danger">*</span></label>
                                <input type="text" class="form-control money-input" id="instalment_amount"
                                       name="instalment_amount" required 
                                       value="<?php echo isset($_POST['instalment_amount']) ? formatMoney(parseMoney($_POST['instalment_amount'])) : formatMoney($default_payment); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="disbursement_date" class="form-label">Disbursement Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="disbursement_date"
                                       name="disbursement_date" required
                                       value="<?php echo isset($_POST['disbursement_date']) ? htmlspecialchars($_POST['disbursement_date']) : date('Y-m-d'); ?>"
                                       onchange="calculateIrregularMaturityDate()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="maturity_date" class="form-label">Maturity Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="maturity_date"
                                       name="maturity_date" required 
                                       value="<?php
                                        if (isset($_POST['maturity_date']) && !empty($_POST['maturity_date'])) {
                                            echo htmlspecialchars($_POST['maturity_date']);
                                        } else {
                                            echo date('Y-m-d', strtotime('+' . ($default_instalments * 30) . ' days'));
                                        }
                                       ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Total Amount Disbursed </label>
                                <input type="text" class="form-control bg-light money-display" id="total_disbursed"
                                       value="<?php echo formatMoney($default_total_disbursed); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Final Payment </label>
                                <input type="text" class="form-control bg-light money-display" id="final_payment"
                                       value="<?php echo formatMoney($default_final_payment); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Sum of Cash + Bank</label>
                                <input type="text" class="form-control bg-light money-display" id="sum_cash_bank"
                                       value="0" readonly>
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
                                       value="<?php echo isset($_POST['collateral_value']) ? formatMoney(parseMoney($_POST['collateral_value'])) : '0'; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="collateral_net_value" class="form-label">Net Value</label>
                                <input type="text" class="form-control money-input" id="collateral_net_value"
                                       name="collateral_net_value"
                                       value="<?php echo isset($_POST['collateral_net_value']) ? formatMoney(parseMoney($_POST['collateral_net_value'])) : '0'; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="collateral_description" class="form-label">Collateral Description</label>
                        <textarea class="form-control" id="collateral_description"
                                  name="collateral_description" rows="2"><?php echo isset($_POST['collateral_description']) ? htmlspecialchars($_POST['collateral_description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-warning" name="submit">
                            <i class="fas fa-exclamation-circle me-2"></i>Create Irregular Loan
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
<script>
// JavaScript functions for irregular customers
function formatNumber(number) {
    if (isNaN(number) || number === 0) return '0';
    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function parseNumber(formattedNumber) {
    if (!formattedNumber) return 0;
    return parseFloat(formattedNumber.toString().replace(/,/g, '')) || 0;
}

// FIXED: Better formatMoneyInput function
function formatMoneyInput(input) {
    const originalValue = input.value;
    let numericValue = originalValue.replace(/[^\d]/g, '');
    
    // Store raw value in data attribute
    input.setAttribute('data-raw-value', numericValue);
    
    if (numericValue) {
        input.value = formatNumber(parseInt(numericValue));
    } else {
        input.value = '';
        input.setAttribute('data-raw-value', '0');
    }
    
    return numericValue;
}

// FIXED: Round to nearest whole number
function roundToWhole(number) {
    return Math.round(number);
}

function calculateIrregularPMT(totalDisbursed, annualRate, months) {
    if (totalDisbursed <= 0 || annualRate <= 0 || months <= 0) {
        return 0;
    }
    
    const monthlyRate = (annualRate / 100) / 12;
    const numerator = totalDisbursed * monthlyRate * Math.pow(1 + monthlyRate, months);
    const denominator = Math.pow(1 + monthlyRate, months) - 1;
    
    if (denominator === 0) {
        return 0;
    }
    
    const payment = numerator / denominator;
    return roundToWhole(payment);
}

// FIXED: Update disbursement amount
function updateIrregularDisbursementAmount() {
    const cashAmount = parseNumber(document.getElementById('cash_amount').value) || 0;
    const bankAmount = parseNumber(document.getElementById('bank_amount').value) || 0;
    const disbursementAmount = cashAmount + bankAmount;
    
    // Update the disbursement amount field
    document.getElementById('disbursement_amount').value = formatNumber(disbursementAmount);
    document.getElementById('disbursement_amount').setAttribute('data-raw-value', disbursementAmount);
    
    // Update the sum display
    document.getElementById('sum_cash_bank').value = formatNumber(disbursementAmount);
    
    // Recalculate installment
    calculateIrregularInstalment();
}

// FIXED: Main calculation function
function calculateIrregularInstalment() {
    // Get raw values from data attributes or parse formatted values
    const principalInput = document.getElementById('disbursement_amount');
    const principal = parseNumber(principalInput.getAttribute('data-raw-value')) || parseNumber(principalInput.value) || 0;
    
    const interestRateInput = document.getElementById('interest_rate');
    const annualRate = parseFloat(interestRateInput.value) || 0;
    
    const instalmentsInput = document.getElementById('number_of_instalments');
    const instalments = parseInt(instalmentsInput.value) || 1;
    
    console.log('Calculating with values:', { 
        principal: principal, 
        annualRate: annualRate, 
        instalments: instalments 
    });
    
    if (principal > 0 && instalments > 0 && annualRate > 0) {
        const totalDisbursed = principal;
        const payment = calculateIrregularPMT(totalDisbursed, annualRate, instalments);
        
        console.log('Calculated payment:', payment);
        
        // Update all fields with formatted values
        document.getElementById('instalment_amount').value = formatNumber(payment);
        document.getElementById('instalment_amount').setAttribute('data-raw-value', payment);
        
        document.getElementById('total_disbursed').value = formatNumber(totalDisbursed);
        
        document.getElementById('final_payment').value = formatNumber(payment);
        
        // Update hidden input with raw value
        document.getElementById('final_payment_input').value = payment;
        
        // Also update sum of cash + bank
        const cashAmount = parseNumber(document.getElementById('cash_amount').value) || 0;
        const bankAmount = parseNumber(document.getElementById('bank_amount').value) || 0;
        document.getElementById('sum_cash_bank').value = formatNumber(cashAmount + bankAmount);
    } else {
        // Reset fields if invalid inputs
        document.getElementById('instalment_amount').value = '0';
        document.getElementById('total_disbursed').value = '0';
        document.getElementById('final_payment').value = '0';
        document.getElementById('final_payment_input').value = '0';
        document.getElementById('sum_cash_bank').value = '0';
    }
}

function calculateIrregularMaturityDate() {
    const disbursementDateInput = document.getElementById('disbursement_date');
    const maturityDateInput = document.getElementById('maturity_date');
    const instalmentsInput = document.getElementById('number_of_instalments');
    
    const disbursementDate = disbursementDateInput.value;
    const instalments = parseInt(instalmentsInput.value) || 1;
    
    if (disbursementDate && instalments > 0) {
        const parts = disbursementDate.split('-');
        const date = new Date(parts[0], parts[1] - 1, parts[2]);
        date.setDate(date.getDate() + instalments * 30);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        maturityDateInput.value = `${year}-${month}-${day}`;
    }
}

// FIXED: Better event handling
function initializeEventListeners() {
    // Get all input elements
    const disbursementAmountInput = document.getElementById('disbursement_amount');
    const interestRateInput = document.getElementById('interest_rate');
    const instalmentsInput = document.getElementById('number_of_instalments');
    const cashAmountInput = document.getElementById('cash_amount');
    const bankAmountInput = document.getElementById('bank_amount');
    
    // Disbursement amount events
    if (disbursementAmountInput) {
        disbursementAmountInput.addEventListener('input', function(e) {
            formatMoneyInput(this);
            setTimeout(() => calculateIrregularInstalment(), 100);
        });
        disbursementAmountInput.addEventListener('blur', calculateIrregularInstalment);
    }
    
    // Interest rate events
    if (interestRateInput) {
        interestRateInput.addEventListener('input', calculateIrregularInstalment);
        interestRateInput.addEventListener('blur', calculateIrregularInstalment);
    }
    
    // Instalments events
    if (instalmentsInput) {
        instalmentsInput.addEventListener('input', function() {
            calculateIrregularInstalment();
            calculateIrregularMaturityDate();
        });
        instalmentsInput.addEventListener('blur', function() {
            calculateIrregularInstalment();
            calculateIrregularMaturityDate();
        });
    }
    
    // Cash amount events
    if (cashAmountInput) {
        cashAmountInput.addEventListener('input', function(e) {
            formatMoneyInput(this);
            setTimeout(() => updateIrregularDisbursementAmount(), 100);
        });
        cashAmountInput.addEventListener('blur', updateIrregularDisbursementAmount);
    }
    
    // Bank amount events
    if (bankAmountInput) {
        bankAmountInput.addEventListener('input', function(e) {
            formatMoneyInput(this);
            setTimeout(() => updateIrregularDisbursementAmount(), 100);
        });
        bankAmountInput.addEventListener('blur', updateIrregularDisbursementAmount);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM loaded, initializing calculations...');
    
    // Initialize data attributes for all money inputs
    const moneyInputs = document.querySelectorAll('.money-input');
    moneyInputs.forEach(input => {
        const currentValue = input.value;
        const rawValue = parseNumber(currentValue);
        input.setAttribute('data-raw-value', rawValue);
        input.value = formatNumber(rawValue);
    });
    
    // Setup event listeners
    initializeEventListeners();
    
    // Initial calculations
    setTimeout(function() {
        calculateIrregularInstalment();
        calculateIrregularMaturityDate();
        
        // Initialize cash/bank sum
        const cashAmount = parseNumber(document.getElementById('cash_amount').value) || 0;
        const bankAmount = parseNumber(document.getElementById('bank_amount').value) || 0;
        document.getElementById('sum_cash_bank').value = formatNumber(cashAmount + bankAmount);
    }, 200);
});

// FIXED: Also handle form submission to ensure raw values are used
document.getElementById('irregularLoanForm')?.addEventListener('submit', function(e) {
    // Ensure all hidden fields have correct values
    const finalPaymentInput = document.getElementById('final_payment_input');
    const instalmentAmountInput = document.getElementById('instalment_amount');
    
    if (finalPaymentInput && instalmentAmountInput) {
        const rawValue = parseNumber(instalmentAmountInput.getAttribute('data-raw-value')) || 
                         parseNumber(instalmentAmountInput.value);
        finalPaymentInput.value = rawValue;
    }
});
</script>
<?php
if (isset($conn)) {
    mysqli_close($conn);
}
