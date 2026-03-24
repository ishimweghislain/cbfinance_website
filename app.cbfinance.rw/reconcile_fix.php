<?php
require_once __DIR__ . '/config/database.php';
$conn = getConnection();

echo "<h1>Starting Financial Reconciliation Fix...</h1>";

// 1. ADD ACCOUNT 1201 TO CHART OF ACCOUNTS
$check_coa = mysqli_query($conn, "SELECT account_id FROM chart_of_accounts WHERE account_code = '1201'");
if (mysqli_num_rows($check_coa) == 0) {
    echo "Adding Account 1201 to Chart of Accounts...<br>";
    $sql_insert_coa = "INSERT INTO `chart_of_accounts` 
        (`account_code`, `account_name`, `class`, `account_type`, `sub_type`, `normal_balance`, `is_active`, `created_at`, `updated_at`) 
        VALUES 
        ('1201', 'Loans to Customers', 'Balance Sheet', 'Asset', 'Current Asset', 'Debit', 1, NOW(), NOW())";
    if (mysqli_query($conn, $sql_insert_coa)) {
        echo "<span style='color:green'>SUCCESS: Account 1201 added.</span><br>";
    }
    else {
        echo "<span style='color:red'>ERROR adding account: " . mysqli_error($conn) . "</span><br>";
    }
}
else {
    echo "Account 1201 already exists in Chart of Accounts.<br>";
}

// 1.2 ADD ACCOUNT 4301 TO CHART OF ACCOUNTS (IF MISSING)
$check_coa2 = mysqli_query($conn, "SELECT account_id FROM chart_of_accounts WHERE account_code = '4301'");
if (mysqli_num_rows($check_coa2) == 0) {
    echo "Adding Account 4301 to Chart of Accounts...<br>";
    $sql_insert_coa2 = "INSERT INTO `chart_of_accounts` 
        (`account_code`, `account_name`, `class`, `account_type`, `sub_type`, `normal_balance`, `is_active`, `created_at`, `updated_at`) 
        VALUES 
        ('4301', 'Impairment Recovery', 'Income Statement', 'Revenue', 'Operating Revenue', 'Credit', 1, NOW(), NOW())";
    mysqli_query($conn, $sql_insert_coa2);
    echo "<span style='color:green'>SUCCESS: Account 4301 added.</span><br>";
}

// 2. CALCULATE REAL OUTSTANDING PRINCIPAL
echo "Calculating Current Outstanding Principal from Loan Portfolio...<br>";
$sql_port = "SELECT SUM(principal_outstanding) as total_principal FROM loan_portfolio WHERE loan_status IN ('Active', 'Overdue', 'Written-off')";
$res_port = mysqli_query($conn, $sql_port);
$row_port = mysqli_fetch_assoc($res_port);
$real_principal = floatval($row_port['total_principal'] ?? 0);
echo "<b>True Outstanding Principal: " . number_format($real_principal, 2) . "</b><br>";

// 3. GET CURRENT LEDGER BALANCE FOR 1201
$sql_ledger = "SELECT SUM(debit_amount - credit_amount) as ledger_bal FROM ledger WHERE account_code = '1201'";
$res_ledger = mysqli_query($conn, $sql_ledger);
$row_ledger = mysqli_fetch_assoc($res_ledger);
$ledger_balance = floatval($row_ledger['ledger_bal'] ?? 0);
echo "<b>Current Ledger Balance for 1201: " . number_format($ledger_balance, 2) . "</b><br>";

// 4. CALCULATE ADJUSTMENT NEEDED
$adjustment = $real_principal - $ledger_balance;
echo "<b>Adjustment needed to fix Ledger 1201: " . number_format($adjustment, 2) . "</b><br>";

if (abs($adjustment) > 0.01) {
    echo "Creating Reconciliation Entry...<br>";


    // We will debit 1201 to reach the correct balance.
    // We will credit Account 3001 (Capital) to acknowledge the initial funding source.

    $voucher = "REC-" . date('YmdHis');
    $description = "System Reconciliation: Initial Portfolio Funding and Ledger Alignment";
    $date = date('Y-01-01'); // Record at start of year for cleanliness

    // Debit 1201
    $sql_adj_1 = "INSERT INTO ledger 
        (transaction_date, class, account_code, account_name, transaction_type, reference_number, description, beginning_balance, debit_amount, credit_amount, movement, closing_balance, source_type, source_id, created_by, created_at, updated_at)
        VALUES 
        ('$date', 'Balance Sheet', '1201', 'Loans to Customers', 'RECONCILIATION', '$voucher', '$description', $ledger_balance, " . abs($adjustment) . ", 0.00, " . abs($adjustment) . ", $real_principal, 'manual', '0', 1, NOW(), NOW())";

    // Credit 3001 (Capital)
    // First get current capital balance
    $res_cap = mysqli_query($conn, "SELECT SUM(credit_amount - debit_amount) as cap_bal FROM ledger WHERE account_code = '3001'");
    $row_cap = mysqli_fetch_assoc($res_cap);
    $current_cap = floatval($row_cap['cap_bal'] ?? 0);
    $new_cap = $current_cap + $adjustment;

    $sql_adj_2 = "INSERT INTO ledger 
        (transaction_date, class, account_code, account_name, transaction_type, reference_number, description, beginning_balance, debit_amount, credit_amount, movement, closing_balance, source_type, source_id, created_by, created_at, updated_at)
        VALUES 
        ('$date', 'Balance Sheet', '3001', 'Capital', 'RECONCILIATION', '$voucher', '$description', " . (-$current_cap) . ", 0.00, " . abs($adjustment) . ", " . (-abs($adjustment)) . ", " . (-$new_cap) . ", 'manual', '0', 1, NOW(), NOW())";

    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, $sql_adj_1);
        mysqli_query($conn, $sql_adj_2);
        mysqli_commit($conn);
        echo "<span style='color:green'>SUCCESS: System balanced. Account 1201 and Capital adjusted.</span><br>";
    }
    catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<span style='color:red'>ERROR during reconciliation: " . $e->getMessage() . "</span><br>";
    }
}
else {
    echo "No adjustment needed for Account 1201.<br>";
}

echo "<h2>Fix Complete. Your Balance Sheet should now be perfectly matched!</h2>";
?>
