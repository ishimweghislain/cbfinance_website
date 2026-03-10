<?php
// Start session
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

$conn->set_charset("utf8mb4");

$error_message = '';
$success_message = '';
$salary_calculations = [];

// Handle form submission for adding expense
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    
    // Get form data
    $expense_date         = $_POST['expense_date'];
    $expense_account_code = $_POST['expense_account_code'];
    $expense_account_name = $_POST['expense_account_name'];
    $payment_account_code = $_POST['payment_account_code'];
    $payment_account_name = $_POST['payment_account_name'];
    $expense_amount       = (float)$_POST['expense_amount'];
    $description          = $_POST['description'] ?? '';
    $salary_payment_type  = $_POST['salary_payment_type'] ?? 'direct_paid';
    
    // Simple validation
    if (empty($expense_date)) {
        $error_message = "Expense date is required";
    } elseif (empty($expense_account_code)) {
        $error_message = "Expense account is required";
    } elseif (empty($payment_account_code) && $salary_payment_type != 'accumulated_salary') {
        $error_message = "Payment account is required";
    } elseif ($expense_amount <= 0) {
        $error_message = "Expense amount must be greater than 0";
    } else {
        // Verify expense account exists in chart_of_accounts
        $expense_account_check = $conn->query("SELECT account_code, account_name, class FROM chart_of_accounts WHERE account_code = '" . mysqli_real_escape_string($conn, $expense_account_code) . "'");
        
        // Verify payment account exists in chart_of_accounts (skip if accumulated salary)
        if ($salary_payment_type != 'accumulated_salary' && !empty($payment_account_code)) {
            $payment_account_check = $conn->query("SELECT account_code, account_name, class FROM chart_of_accounts WHERE account_code = '" . mysqli_real_escape_string($conn, $payment_account_code) . "'");
        } else {
            $payment_account_check = null;
        }
        
        if (!$expense_account_check || $expense_account_check->num_rows == 0) {
            $error_message = "Invalid expense account selected";
        } elseif ($salary_payment_type != 'accumulated_salary' && (!$payment_account_check || $payment_account_check->num_rows == 0)) {
            $error_message = "Invalid payment account selected";
        } else {
            $expense_account_data  = $expense_account_check->fetch_assoc();
            $expense_account_class = $expense_account_data['class'];
            
            if ($salary_payment_type != 'accumulated_salary' && $payment_account_check) {
                $payment_account_data  = $payment_account_check->fetch_assoc();
                $payment_account_class = $payment_account_data['class'];
            } else {
                $payment_account_data  = null;
                $payment_account_class = null;
            }
            
            // Check if this is a salary expense (5101)
            $is_salary_expense = ($expense_account_code == '5101');
            
            // Generate reference
            $expense_reference = "EXP-" . date('Ymd-His');
            $created_by = $_SESSION['user_id'] ?? 1;

            // ── THE FIX ──────────────────────────────────────────────────────
            // Use the user-entered description as the `particular` value in the
            // ledger. Fall back to a meaningful default if the field is blank.
            $particular = !empty(trim($description))
                ? trim($description)
                : ($is_salary_expense ? 'Salary Payment' : 'Expense - ' . $expense_account_name);
            // ─────────────────────────────────────────────────────────────────
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert into expenses table
                $sql1 = "INSERT INTO expenses (
                        expense_reference, 
                        expense_date, 
                        account_name, 
                        account_code,
                        expense_amount, 
                        description, 
                        created_by
                    ) VALUES (
                        '" . mysqli_real_escape_string($conn, $expense_reference) . "',
                        '" . mysqli_real_escape_string($conn, $expense_date) . "',
                        '" . mysqli_real_escape_string($conn, $expense_account_name) . "',
                        '" . mysqli_real_escape_string($conn, $expense_account_code) . "',
                        " . floatval($expense_amount) . ",
                        '" . mysqli_real_escape_string($conn, $description) . "',
                        " . intval($created_by) . "
                    )";
                
                if (!$conn->query($sql1)) {
                    throw new Exception("Failed to insert expense: " . $conn->error);
                }
                
                $insert_id = $conn->insert_id;
                
                // If this is a salary expense (5101), create multiple ledger entries
                if ($is_salary_expense) {
                    // Calculate all salary components
                    $basic_salary = $expense_amount;
                    
                    // 1. Calculate PAYEE (Employee tax)
                    if ($basic_salary < 60000) {
                        $payee = 0;
                    } elseif ($basic_salary < 100000) {
                        $payee = ($basic_salary - 60000) * 0.1;
                    } elseif ($basic_salary < 200000) {
                        $payee = 4000 + ($basic_salary - 100000) * 0.2;
                    } else {
                        $payee = 24000 + ($basic_salary - 200000) * 0.3;
                    }
                    
                    // 2. CSR (6%) - Employee pension contribution
                    $csr_6_percent = $basic_salary * 0.06;
                    
                    // 3. CSR (8%) - Employer pension contribution
                    $csr_8_percent = $basic_salary * 0.08;
                    
                    // 4. Maternity Leave - Employer (0.3%)
                    $maternity_employer = $basic_salary * 0.003;
                    
                    // 5. Maternity Leave - Employee (0.3%)
                    $maternity_employee = $basic_salary * 0.003;
                    
                    // 6. Mutuel De Sante (0.5% of (Basic - CSR(6%) - PAYEE))
                    $mutuel_base      = $basic_salary - $csr_6_percent - $payee;
                    $mutuel_de_sante  = $mutuel_base * 0.005;
                    
                    // 7. Total Gross Salary
                    $total_gross = $basic_salary + $csr_8_percent + $maternity_employer;
                    
                    // 8. Net Salary (cash payment to employee)
                    $cash_payment = $basic_salary - $payee - $csr_6_percent - $maternity_employee - $mutuel_de_sante;
                    
                    if ($cash_payment < 0) {
                        throw new Exception("Invalid salary calculation: Net salary cannot be negative");
                    }
                    
                    // Store calculations for display
                    $salary_calculations = [
                        'basic_salary'       => $basic_salary,
                        'payee'              => $payee,
                        'csr_6_percent'      => $csr_6_percent,
                        'csr_8_percent'      => $csr_8_percent,
                        'maternity_employer' => $maternity_employer,
                        'maternity_employee' => $maternity_employee,
                        'mutuel_de_sante'    => $mutuel_de_sante,
                        'total_gross'        => $total_gross,
                        'cash_payment'       => $cash_payment
                    ];
                    
                    // Narration carries the description; $particular already set above
                    $narration = "Salary Payment: " . mysqli_real_escape_string($conn, $description);
                    
                    // ===== DEBIT ENTRIES =====
                    createLedgerEntry($conn, $expense_date, 'Expenses', '5101', 'Salary and Wages',
                        $particular, $expense_reference, $narration, $basic_salary, 0,
                        $basic_salary, 'expense', $insert_id, $created_by);
                    
                    createLedgerEntry($conn, $expense_date, 'Expenses', '5109', 'Employer Pension Contributions',
                        $particular, $expense_reference, $narration, $csr_8_percent, 0,
                        $csr_8_percent, 'expense', $insert_id, $created_by);
                    
                    createLedgerEntry($conn, $expense_date, 'Expenses', '5110', 'Employer Maternity Leave Contributions',
                        $particular, $expense_reference, $narration, $maternity_employer, 0,
                        $maternity_employer, 'expense', $insert_id, $created_by);
                    
                    // ===== CREDIT ENTRIES =====
                    createLedgerEntry($conn, $expense_date, 'Liabilities', '2106', 'Accrued PAYEE',
                        $particular, $expense_reference, $narration, 0, $payee,
                        -$payee, 'expense', $insert_id, $created_by);
                    
                    createLedgerEntry($conn, $expense_date, 'Liabilities', '2107', 'Accrued Pension',
                        $particular, $expense_reference, $narration, 0, $csr_6_percent,
                        -$csr_6_percent, 'expense', $insert_id, $created_by);
                    
                    createLedgerEntry($conn, $expense_date, 'Liabilities', '2107', 'Accrued Pension',
                        $particular, $expense_reference, $narration, 0, $csr_8_percent,
                        -$csr_8_percent, 'expense', $insert_id, $created_by);
                    
                    createLedgerEntry($conn, $expense_date, 'Liabilities', '2108', 'Maternity Leave',
                        $particular, $expense_reference, $narration, 0, $maternity_employer,
                        -$maternity_employer, 'expense', $insert_id, $created_by);
                    
                    createLedgerEntry($conn, $expense_date, 'Liabilities', '2108', 'Maternity Leave',
                        $particular, $expense_reference, $narration, 0, $maternity_employee,
                        -$maternity_employee, 'expense', $insert_id, $created_by);
                    
                    createLedgerEntry($conn, $expense_date, 'Liabilities', '2109', 'Accrued Mutuel',
                        $particular, $expense_reference, $narration, 0, $mutuel_de_sante,
                        -$mutuel_de_sante, 'expense', $insert_id, $created_by);
                    
                    if ($salary_payment_type === 'accumulated_salary') {
                        createLedgerEntry($conn, $expense_date, 'Liabilities', '2103', 'Accrued Salaries',
                            $particular, $expense_reference, $narration, 0, $cash_payment,
                            -$cash_payment, 'expense', $insert_id, $created_by);
                    } else {
                        createLedgerEntry($conn, $expense_date, 'Assets', $payment_account_code, $payment_account_name,
                            $particular, $expense_reference, $narration, 0, $cash_payment,
                            -$cash_payment, 'expense', $insert_id, $created_by);
                    }
                    
                } else {
                    // Regular expense (non-salary)
                    $expense_balance_sql = "SELECT ending_balance FROM ledger WHERE account_code = '" . mysqli_real_escape_string($conn, $expense_account_code) . "' ORDER BY ledger_id DESC LIMIT 1";
                    $expense_balance_result = $conn->query($expense_balance_sql);
                    $beginning_balance_expense = $expense_balance_result && $expense_balance_result->num_rows > 0
                        ? $expense_balance_result->fetch_assoc()['ending_balance'] : 0;
                    
                    $payment_balance_sql = "SELECT ending_balance FROM ledger WHERE account_code = '" . mysqli_real_escape_string($conn, $payment_account_code) . "' ORDER BY ledger_id DESC LIMIT 1";
                    $payment_balance_result = $conn->query($payment_balance_sql);
                    $beginning_balance_payment = $payment_balance_result && $payment_balance_result->num_rows > 0
                        ? $payment_balance_result->fetch_assoc()['ending_balance'] : 0;
                    
                    $movement_expense  = $expense_amount;
                    $ending_balance_expense = $beginning_balance_expense + $movement_expense;
                    
                    $movement_payment  = -$expense_amount;
                    $ending_balance_payment = $beginning_balance_payment + $movement_payment;
                    
                    // Narration carries full context; $particular already set to description above
                    $narration = "Expense: " . mysqli_real_escape_string($conn, $expense_account_name);
                    if (!empty($description)) {
                        $narration .= " - " . mysqli_real_escape_string($conn, $description);
                    }
                    
                    // Entry 1: DEBIT Expense Account
                    $ledger_sql1 = "INSERT INTO ledger (
                            transaction_date, class, account_code, account_name,
                            particular, voucher_number, narration,
                            beginning_balance, debit_amount, credit_amount,
                            movement, ending_balance,
                            reference_type, reference_id, created_by, created_at
                        ) VALUES (
                            '" . mysqli_real_escape_string($conn, $expense_date) . "',
                            '" . mysqli_real_escape_string($conn, $expense_account_class) . "',
                            '" . mysqli_real_escape_string($conn, $expense_account_code) . "',
                            '" . mysqli_real_escape_string($conn, $expense_account_name) . "',
                            '" . mysqli_real_escape_string($conn, $particular) . "',
                            '" . mysqli_real_escape_string($conn, $expense_reference) . "',
                            '" . mysqli_real_escape_string($conn, $narration) . "',
                            " . floatval($beginning_balance_expense) . ",
                            " . floatval($expense_amount) . ",
                            0,
                            " . floatval($movement_expense) . ",
                            " . floatval($ending_balance_expense) . ",
                            'expense',
                            " . intval($insert_id) . ",
                            " . intval($created_by) . ",
                            NOW()
                        )";
                    
                    if (!$conn->query($ledger_sql1)) {
                        throw new Exception("Failed to insert ledger entry 1 (debit): " . $conn->error);
                    }
                    
                    // Entry 2: CREDIT Payment Account
                    $ledger_sql2 = "INSERT INTO ledger (
                            transaction_date, class, account_code, account_name,
                            particular, voucher_number, narration,
                            beginning_balance, debit_amount, credit_amount,
                            movement, ending_balance,
                            reference_type, reference_id, created_by, created_at
                        ) VALUES (
                            '" . mysqli_real_escape_string($conn, $expense_date) . "',
                            '" . mysqli_real_escape_string($conn, $payment_account_class) . "',
                            '" . mysqli_real_escape_string($conn, $payment_account_code) . "',
                            '" . mysqli_real_escape_string($conn, $payment_account_name) . "',
                            '" . mysqli_real_escape_string($conn, $particular) . "',
                            '" . mysqli_real_escape_string($conn, $expense_reference) . "',
                            '" . mysqli_real_escape_string($conn, $narration) . "',
                            " . floatval($beginning_balance_payment) . ",
                            0,
                            " . floatval($expense_amount) . ",
                            " . floatval($movement_payment) . ",
                            " . floatval($ending_balance_payment) . ",
                            'expense',
                            " . intval($insert_id) . ",
                            " . intval($created_by) . ",
                            NOW()
                        )";
                    
                    if (!$conn->query($ledger_sql2)) {
                        throw new Exception("Failed to insert ledger entry 2 (credit): " . $conn->error);
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                if ($is_salary_expense) {
                    $payment_type_text = $salary_payment_type === 'accumulated_salary'
                        ? 'Accumulated Salary (2103)'
                        : 'Direct Payment (' . $payment_account_code . ')';
                    $success_message = "✅ Salary expense recorded successfully!<br>
                                    Reference: <strong>$expense_reference</strong><br>
                                    Payment Type: <strong>$payment_type_text</strong><br>
                                    Basic Salary: <strong>Rwf " . number_format($basic_salary, 2) . "</strong><br>
                                    PAYEE: <strong>Rwf " . number_format($payee, 2) . "</strong><br>
                                    CSR Employee (6%): <strong>Rwf " . number_format($csr_6_percent, 2) . "</strong><br>
                                    CSR Employer (8%): <strong>Rwf " . number_format($csr_8_percent, 2) . "</strong><br>
                                    Maternity Employee: <strong>Rwf " . number_format($maternity_employee, 2) . "</strong><br>
                                    Maternity Employer: <strong>Rwf " . number_format($maternity_employer, 2) . "</strong><br>
                                    Mutuel De Sante: <strong>Rwf " . number_format($mutuel_de_sante, 2) . "</strong><br>
                                    <strong class='text-primary'>Net Payment to Employee: Rwf " . number_format($cash_payment, 2) . "</strong><br>
                                    Date: " . date('d/m/Y', strtotime($expense_date));
                } else {
                    $success_message = "✅ Expense recorded successfully!<br>
                                    Reference: <strong>$expense_reference</strong><br>
                                    Expense: <strong>" . htmlspecialchars($expense_account_name) . "</strong><br>
                                    Payment From: <strong>" . htmlspecialchars($payment_account_name) . "</strong><br>
                                    Amount: <strong>Rwf " . number_format($expense_amount, 2) . "</strong><br>
                                    Date: " . date('d/m/Y', strtotime($expense_date));
                }
                
                // Clear form
                $_POST = array();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Transaction failed: " . $e->getMessage();
            }
        }
    }
}

// Handle form submission for deleting expense
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_expense'])) {
    $expense_id  = (int)$_POST['expense_id'];
    $expense_ref = $_POST['expense_reference'];
    
    $conn->begin_transaction();
    
    try {
        $delete_sql = "DELETE FROM expenses WHERE expense_id = " . intval($expense_id);
        if (!$conn->query($delete_sql)) {
            throw new Exception("Failed to delete expense: " . $conn->error);
        }
        
        $delete_ledger_sql = "DELETE FROM ledger WHERE reference_type = 'expense' AND voucher_number = '" . mysqli_real_escape_string($conn, $expense_ref) . "'";
        if (!$conn->query($delete_ledger_sql)) {
            throw new Exception("Failed to delete ledger entries: " . $conn->error);
        }
        
        $conn->commit();
        $success_message = "✅ Expense deleted successfully!";
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Delete failed: " . $e->getMessage();
    }
}

// Get expense accounts from chart_of_accounts
$expense_accounts_result = $conn->query("
    SELECT account_code, account_name, class 
    FROM chart_of_accounts 
    WHERE (account_code LIKE '5%' OR class LIKE '%Expense%' OR class LIKE '%expense%')
    ORDER BY account_code
");

// Get asset accounts from chart_of_accounts
$asset_accounts_result = $conn->query("
    SELECT account_code, account_name, class 
    FROM chart_of_accounts 
    WHERE (account_code LIKE '1%' OR class LIKE '%Asset%' OR class LIKE '%asset%')
    AND (account_name LIKE '%cash%' OR account_name LIKE '%bank%' OR account_code IN ('1101', '1102', '1103'))
    ORDER BY account_code
");

// Get recent expenses for display (no users table join - users managed via session)
$expenses_result = $conn->query("
    SELECT e.*
    FROM expenses e 
    ORDER BY e.expense_date DESC, e.expense_id DESC 
    LIMIT 10
");

// Get recent ledger entries for expenses
$ledger_entries = $conn->query("
    SELECT * FROM ledger 
    WHERE reference_type = 'expense' 
    ORDER BY transaction_date DESC, 
            CASE WHEN debit_amount > 0 THEN 1 ELSE 2 END,
            ledger_id DESC 
    LIMIT 20
");

// Calculate totals for display
$today_total = $conn->query("SELECT SUM(expense_amount) as total FROM expenses WHERE expense_date = CURDATE()")->fetch_assoc();
$month_total = $conn->query("SELECT SUM(expense_amount) as total FROM expenses WHERE MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())")->fetch_assoc();

// Function to create ledger entry
// NOTE: $particular is passed in directly (already set to the user's description)
function createLedgerEntry($conn, $date, $class, $account_code, $account_name, $particular, $voucher, $narration, $debit, $credit, $movement, $ref_type, $ref_id, $created_by) {
    
    // Get beginning balance
    $balance_sql    = "SELECT ending_balance FROM ledger WHERE account_code = '" . mysqli_real_escape_string($conn, $account_code) . "' ORDER BY ledger_id DESC LIMIT 1";
    $balance_result = $conn->query($balance_sql);
    $beginning_balance = $balance_result && $balance_result->num_rows > 0
        ? $balance_result->fetch_assoc()['ending_balance'] : 0;
    
    $ending_balance = $beginning_balance + $movement;
    
    $ledger_sql = "INSERT INTO ledger (
            transaction_date, class, account_code, account_name,
            particular, voucher_number, narration,
            beginning_balance, debit_amount, credit_amount,
            movement, ending_balance,
            reference_type, reference_id, created_by, created_at
        ) VALUES (
            '" . mysqli_real_escape_string($conn, $date) . "',
            '" . mysqli_real_escape_string($conn, $class) . "',
            '" . mysqli_real_escape_string($conn, $account_code) . "',
            '" . mysqli_real_escape_string($conn, $account_name) . "',
            '" . mysqli_real_escape_string($conn, $particular) . "',
            '" . mysqli_real_escape_string($conn, $voucher) . "',
            '" . mysqli_real_escape_string($conn, $narration) . "',
            " . floatval($beginning_balance) . ",
            " . floatval($debit) . ",
            " . floatval($credit) . ",
            " . floatval($movement) . ",
            " . floatval($ending_balance) . ",
            '" . mysqli_real_escape_string($conn, $ref_type) . "',
            " . intval($ref_id) . ",
            " . intval($created_by) . ",
            NOW()
        )";
    
    if (!$conn->query($ledger_sql)) {
        throw new Exception("Failed to insert ledger entry for $account_code: " . $conn->error);
    }
    
    return true;
}
?>

<div class="container-fluid">
    <!-- Messages -->
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="h4 fw-bold text-danger">Expenses Management</h2>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Today's Expenses</h6>
                    <h3 class="card-title text-danger">Rwf <?php echo number_format($today_total['total'] ?? 0, 2); ?></h3>
                    <p class="card-text small"><?php echo date('d M Y'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">This Month</h6>
                    <h3 class="card-title text-danger">Rwf <?php echo number_format($month_total['total'] ?? 0, 2); ?></h3>
                    <p class="card-text small"><?php echo date('F Y'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Expenses</h6>
                    <?php 
                    $all_total = $conn->query("SELECT SUM(expense_amount) as total FROM expenses")->fetch_assoc();
                    ?>
                    <h3 class="card-title text-danger">Rwf <?php echo number_format($all_total['total'] ?? 0, 2); ?></h3>
                    <p class="card-text small">All time</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 opacity-75">Salary Entries</h6>
                    <h3 class="card-title">
                        <?php 
                        $salary_count = $conn->query("SELECT COUNT(*) as cnt FROM expenses WHERE account_code = '5101'")->fetch_assoc();
                        echo $salary_count['cnt'] ?? 0;
                        ?>
                    </h3>
                    <p class="card-text small opacity-75">Salary expense transactions</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- LEFT: Expense Form -->
        <div class="col-md-6 mb-4">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Record New Expense</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="expenseForm">
                        <div class="mb-3">
                            <label class="form-label">Expense Account <span class="text-danger">*</span></label>
                            <select name="expense_account_code" class="form-select" id="expenseAccountSelect" required>
                                <option value="">-- Select Expense Account --</option>
                                <?php if ($expense_accounts_result): ?>
                                    <?php while($acc = $expense_accounts_result->fetch_assoc()): ?>
                                        <option value="<?php echo $acc['account_code']; ?>"
                                            data-account-name="<?php echo htmlspecialchars($acc['account_name']); ?>"
                                            <?php echo isset($_POST['expense_account_code']) && $_POST['expense_account_code'] == $acc['account_code'] ? 'selected' : ''; ?>>
                                            <?php echo $acc['account_code']; ?> - <?php echo htmlspecialchars($acc['account_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                            <input type="hidden" name="expense_account_name" id="expenseAccountName">
                        </div>

                        <!-- Salary Payment Type Radio Buttons -->
                        <div class="mb-3" id="salaryPaymentTypeSection" style="display: none;">
                            <label class="form-label">Salary Payment Type <span class="text-danger">*</span></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="salary_payment_type" id="directPaid" value="direct_paid" checked>
                                <label class="form-check-label" for="directPaid">
                                    <strong>Direct Paid</strong> - Pay now via Cash/Bank (1101/1102)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="salary_payment_type" id="accumulatedSalary" value="accumulated_salary">
                                <label class="form-check-label" for="accumulatedSalary">
                                    <strong>Accumulated Salary</strong> - Record to Accrued Salaries (2103)
                                </label>
                            </div>
                        </div>

                        <div class="mb-3" id="paymentAccountSection">
                            <label class="form-label">Payment Account <span class="text-danger">*</span></label>
                            <select name="payment_account_code" class="form-select" id="paymentAccountSelect" required>
                                <option value="">-- Select Cash/Bank Account --</option>
                                <?php if ($asset_accounts_result): ?>
                                    <?php while($asset = $asset_accounts_result->fetch_assoc()): ?>
                                        <option value="<?php echo $asset['account_code']; ?>"
                                            data-account-name="<?php echo htmlspecialchars($asset['account_name']); ?>"
                                            <?php echo isset($_POST['payment_account_code']) && $_POST['payment_account_code'] == $asset['account_code'] ? 'selected' : ''; ?>>
                                            <?php echo $asset['account_code']; ?> - <?php echo htmlspecialchars($asset['account_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                            <input type="hidden" name="payment_account_name" id="paymentAccountName">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                                <input type="date" name="expense_date" class="form-control" 
                                    value="<?php echo isset($_POST['expense_date']) ? $_POST['expense_date'] : date('Y-m-d'); ?>" 
                                    required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount (FRW) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rwf</span>
                                    <input type="number" name="expense_amount" class="form-control" 
                                        value="<?php echo isset($_POST['expense_amount']) ? $_POST['expense_amount'] : 100000; ?>" 
                                        min="1" step="0.01" required>
                                </div>
                                <small class="text-muted" id="salaryHint" style="display: none;">
                                    <i class="fas fa-info-circle"></i> This will be treated as basic salary
                                </small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description / Particular <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="2"
                                placeholder="Enter description — this will be saved as the Particular in the ledger..."
                                ><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            <small class="text-muted"><i class="fas fa-info-circle me-1"></i>This text is recorded as the <strong>Particular</strong> on every ledger entry for this expense.</small>
                        </div>

                        <div class="alert alert-info" id="accountingPreviewBox">
                            <h6><i class="fas fa-list-alt me-2"></i>Accounting Entry Preview</h6>
                            <div id="accountingPreview" class="small">
                                <div id="regularPreview" style="display: block;">
                                    <div class="row">
                                        <div class="col-6">
                                            <strong class="text-danger">Debit:</strong><br>
                                            <span id="previewDebit">[Expense Account]</span>
                                        </div>
                                        <div class="col-6">
                                            <strong class="text-success">Credit:</strong><br>
                                            <span id="previewCredit">[Payment Account]</span>
                                        </div>
                                    </div>
                                </div>
                                <div id="salaryPreview" style="display: none;">
                                    <strong>Salary Expense (10 ledger entries):</strong><br>
                                    <small>
                                        • <strong class="text-danger">Debits:</strong> 5101, 5109, 5110<br>
                                        • <strong class="text-success">Credits:</strong> 2106, 2107, 2108, 2109, <span id="salaryFinalAccount">Cash/Bank</span><br>
                                        • <strong>Net Payment:</strong> Basic - (PAYEE + CSR 6% + Maternity + Mutuel)
                                    </small>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="add_expense" class="btn btn-danger w-100">
                            <i class="fas fa-save me-2"></i>Save Expense &amp; Ledger Entries
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- RIGHT: Ledger Info -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-book me-2"></i>Accounting Info</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exchange-alt me-2"></i>Double Entry Rule</h6>
                        <p class="mb-1 small"><strong class="text-danger">Debit:</strong> Expense Account (increase)</p>
                        <p class="mb-0 small"><strong class="text-success">Credit:</strong> Asset/Liability Account (decrease)</p>
                    </div>
                    
                    <div class="alert alert-success" id="salaryCalculationBox" style="display: <?php echo !empty($salary_calculations) ? 'block' : 'none'; ?>;">
                        <h6><i class="fas fa-calculator me-2"></i>Salary Calculation Breakdown</h6>
                        <?php if (!empty($salary_calculations)): ?>
                        <div class="small">
                            <div class="row border-bottom">
                                <div class="col-6"><strong>Basic Salary:</strong></div>
                                <div class="col-6 text-end">Rwf <?php echo number_format($salary_calculations['basic_salary'], 2); ?></div>
                            </div>
                            <div class="row border-bottom">
                                <div class="col-6 text-danger">Deductions:</div>
                                <div class="col-6 text-end text-danger"></div>
                            </div>
                            <div class="row">
                                <div class="col-6">• PAYEE:</div>
                                <div class="col-6 text-end">- Rwf <?php echo number_format($salary_calculations['payee'], 2); ?></div>
                            </div>
                            <div class="row">
                                <div class="col-6">• CSR (6%):</div>
                                <div class="col-6 text-end">- Rwf <?php echo number_format($salary_calculations['csr_6_percent'], 2); ?></div>
                            </div>
                            <div class="row">
                                <div class="col-6">• Maternity (Emp):</div>
                                <div class="col-6 text-end">- Rwf <?php echo number_format($salary_calculations['maternity_employee'], 2); ?></div>
                            </div>
                            <div class="row">
                                <div class="col-6">• Mutuel De Sante:</div>
                                <div class="col-6 text-end">- Rwf <?php echo number_format($salary_calculations['mutuel_de_sante'], 2); ?></div>
                            </div>
                            <div class="row border-top mt-1 pt-1">
                                <div class="col-6"><strong>Net Payment:</strong></div>
                                <div class="col-6 text-end fw-bold text-primary">Rwf <?php echo number_format($salary_calculations['cash_payment'], 2); ?></div>
                            </div>
                            <div class="row border-top mt-1 pt-1">
                                <div class="col-6 text-success">Employer Costs:</div>
                                <div class="col-6 text-end text-success"></div>
                            </div>
                            <div class="row">
                                <div class="col-6">• CSR (8%):</div>
                                <div class="col-6 text-end">+ Rwf <?php echo number_format($salary_calculations['csr_8_percent'], 2); ?></div>
                            </div>
                            <div class="row">
                                <div class="col-6">• Maternity (Emp):</div>
                                <div class="col-6 text-end">+ Rwf <?php echo number_format($salary_calculations['maternity_employer'], 2); ?></div>
                            </div>
                            <div class="row border-top mt-1 pt-1">
                                <div class="col-6"><strong>Total Cost:</strong></div>
                                <div class="col-6 text-end fw-bold">Rwf <?php echo number_format($salary_calculations['total_gross'], 2); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($ledger_entries && $ledger_entries->num_rows > 0): ?>
                        <h6 class="mt-3">Recent Ledger Entries:</h6>
                        <div style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-borderless">
                                <thead>
                                    <tr>
                                        <th>Account</th>
                                        <th>Particular</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($entry = $ledger_entries->fetch_assoc()): ?>
                                    <tr>
                                        <td><small><code><?php echo $entry['account_code']; ?></code></small></td>
                                        <td><small><?php echo htmlspecialchars(substr($entry['particular'], 0, 20)); ?>...</small></td>
                                        <td class="text-end">
                                            <?php if ($entry['debit_amount'] > 0): ?>
                                                <span class="text-danger">Dr <?php echo number_format($entry['debit_amount'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-success">Cr <?php echo number_format($entry['credit_amount'], 2); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const expenseAccountSelect    = document.getElementById('expenseAccountSelect');
    const expenseAccountNameInput = document.getElementById('expenseAccountName');
    const paymentAccountSelect    = document.getElementById('paymentAccountSelect');
    const paymentAccountNameInput = document.getElementById('paymentAccountName');
    const salaryHint              = document.getElementById('salaryHint');
    const regularPreview          = document.getElementById('regularPreview');
    const salaryPreview           = document.getElementById('salaryPreview');
    const salaryCalculationBox    = document.getElementById('salaryCalculationBox');
    const salaryPaymentTypeSection = document.getElementById('salaryPaymentTypeSection');
    const paymentAccountSection   = document.getElementById('paymentAccountSection');
    const salaryFinalAccount      = document.getElementById('salaryFinalAccount');
    const directPaidRadio         = document.getElementById('directPaid');
    const accumulatedSalaryRadio  = document.getElementById('accumulatedSalary');
    const previewDebit            = document.getElementById('previewDebit');
    const previewCredit           = document.getElementById('previewCredit');
    
    function updateAccountingPreview() {
        const expenseOption = expenseAccountSelect.options[expenseAccountSelect.selectedIndex];
        const paymentOption = paymentAccountSelect.options[paymentAccountSelect.selectedIndex];
        
        if (expenseOption.value) {
            expenseAccountNameInput.value = expenseOption.getAttribute('data-account-name');
            
            if (expenseOption.value === '5101') {
                salaryHint.style.display = 'block';
                regularPreview.style.display = 'none';
                salaryPreview.style.display = 'block';
                salaryCalculationBox.style.display = 'block';
                salaryPaymentTypeSection.style.display = 'block';
                previewDebit.textContent = 'Salary and Wages';
                updateSalaryPaymentPreview();
            } else {
                salaryHint.style.display = 'none';
                regularPreview.style.display = 'block';
                salaryPreview.style.display = 'none';
                salaryCalculationBox.style.display = 'none';
                salaryPaymentTypeSection.style.display = 'none';
                paymentAccountSection.style.display = 'block';
                previewDebit.textContent = expenseOption.value + ' - ' + expenseOption.getAttribute('data-account-name');
                if (paymentOption.value) {
                    paymentAccountNameInput.value = paymentOption.getAttribute('data-account-name');
                    previewCredit.textContent = paymentOption.value + ' - ' + paymentOption.getAttribute('data-account-name');
                } else {
                    paymentAccountNameInput.value = '';
                    previewCredit.textContent = '[Payment Account]';
                }
            }
        } else {
            expenseAccountNameInput.value = '';
            salaryHint.style.display = 'none';
            regularPreview.style.display = 'block';
            salaryPreview.style.display = 'none';
            salaryPaymentTypeSection.style.display = 'none';
            paymentAccountSection.style.display = 'block';
            previewDebit.textContent = '[Expense Account]';
        }
        
        if (paymentOption.value && expenseOption.value !== '5101') {
            paymentAccountNameInput.value = paymentOption.getAttribute('data-account-name');
            previewCredit.textContent = paymentOption.value + ' - ' + paymentOption.getAttribute('data-account-name');
        } else if (expenseOption.value !== '5101') {
            paymentAccountNameInput.value = '';
            previewCredit.textContent = '[Payment Account]';
        }
    }
    
    function updateSalaryPaymentPreview() {
        const paymentOption = paymentAccountSelect.options[paymentAccountSelect.selectedIndex];
        if (accumulatedSalaryRadio.checked) {
            paymentAccountSection.style.display = 'none';
            paymentAccountSelect.removeAttribute('required');
            salaryFinalAccount.textContent = '2103 - Accrued Salaries';
            previewCredit.textContent = 'Multiple Accounts (Net to 2103 - Accrued Salaries)';
        } else {
            paymentAccountSection.style.display = 'block';
            paymentAccountSelect.setAttribute('required', 'required');
            if (paymentOption.value) {
                paymentAccountNameInput.value = paymentOption.getAttribute('data-account-name');
                salaryFinalAccount.textContent = paymentOption.value + ' - ' + paymentOption.getAttribute('data-account-name');
                previewCredit.textContent = 'Multiple Accounts (Net to ' + paymentOption.value + ' - ' + paymentOption.getAttribute('data-account-name') + ')';
            } else {
                paymentAccountNameInput.value = '';
                salaryFinalAccount.textContent = 'Cash/Bank';
                previewCredit.textContent = 'Multiple Accounts (Net to Cash/Bank)';
            }
        }
    }
    
    expenseAccountSelect.addEventListener('change', updateAccountingPreview);
    paymentAccountSelect.addEventListener('change', updateAccountingPreview);
    directPaidRadio.addEventListener('change', updateSalaryPaymentPreview);
    accumulatedSalaryRadio.addEventListener('change', updateSalaryPaymentPreview);
    
    updateAccountingPreview();
    
    document.getElementById('expenseForm').addEventListener('submit', function(e) {
        const expenseAccount  = document.getElementById('expenseAccountSelect');
        const paymentAccount  = document.getElementById('paymentAccountSelect');
        const date   = document.querySelector('input[name="expense_date"]');
        const amount = document.querySelector('input[name="expense_amount"]');
        let valid = true;
        
        if (!expenseAccount.value) { expenseAccount.classList.add('is-invalid'); valid = false; }
        else { expenseAccount.classList.remove('is-invalid'); }
        
        if (paymentAccountSection.style.display !== 'none' && !paymentAccount.value) {
            paymentAccount.classList.add('is-invalid'); valid = false;
        } else { paymentAccount.classList.remove('is-invalid'); }
        
        if (!date.value) { date.classList.add('is-invalid'); valid = false; }
        else { date.classList.remove('is-invalid'); }
        
        if (!amount.value || parseFloat(amount.value) <= 0) { amount.classList.add('is-invalid'); valid = false; }
        else { amount.classList.remove('is-invalid'); }
        
        if (!valid) {
            e.preventDefault();
            const firstError = document.querySelector('.is-invalid');
            if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
    
    document.getElementById('expenseAccountSelect').focus();
    
    document.querySelector('input[name="expense_amount"]').addEventListener('blur', function() {
        if (this.value) this.value = parseFloat(this.value).toFixed(2);
    });
});
</script>

<style>
.card { margin-bottom: 20px; }
.form-label { font-weight: 500; }
.table-sm th, .table-sm td { padding: 0.5rem; }
.btn-outline-danger { border-width: 1px; }
#accountingPreview { font-size: 0.9em; }
.badge { font-size: 0.7em; }
.border-bottom { border-bottom: 1px solid #dee2e6 !important; }
.border-top { border-top: 1px solid #dee2e6 !important; }
.form-check { margin-bottom: 0.5rem; }
.form-check-label { cursor: pointer; }
</style>

<?php 
$conn->close();
?>
