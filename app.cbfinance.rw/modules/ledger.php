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

// Handle actions
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'clear_ledger_data') {
        unset($_SESSION['ledger_transaction']);
        unset($_SESSION['ledger_success']);
        header("Location: index.php?page=ledger");
        exit();
    }

    // NEW: Sync all account balances to fix historical 'Balance Jumps'
    if ($_GET['action'] === 'sync_all_balances') {
        $accounts_query = "SELECT DISTINCT account_code FROM ledger";
        $accounts_res = mysqli_query($conn, $accounts_query);
        while ($acc = mysqli_fetch_assoc($accounts_res)) {
            recalculateAccountEndingBalances($conn, $acc['account_code']);
        }
        $_SESSION['success_message'] = "All historical balances have been recalculated and repaired!";
        header("Location: index.php?page=ledger");
        exit();
    }
}

// Helper functions with consistent rounding
function formatMoney($amount, $decimals = 2)
{
    return number_format(round($amount, $decimals), $decimals, '.', ',');
}

function roundAmount($amount, $decimals = 2)
{
    return round($amount, $decimals);
}

function generateVoucherNumber($type = 'RV')
{
    $date = date('Ymd');
    $sql = "SELECT COUNT(*) as count FROM ledger WHERE voucher_number LIKE '{$type}-{$date}-%'";
    $result = mysqli_query($GLOBALS['conn'], $sql);
    $count = 1;
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $count = $row['count'] + 1;
    }
    return $type . '-' . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
}

function calculateBeginningBalance($conn, $account_code, $transaction_date = null)
{
    // Sanitize inputs
    $account_code = mysqli_real_escape_string($conn, $account_code);

    // Get the absolute latest ending balance currently recorded in the system for this account.
    // In a chronological system, the very last entry in the DB for this code is the current state.
    $sql = "SELECT ending_balance 
            FROM ledger 
            WHERE account_code = '$account_code' 
            ORDER BY transaction_date DESC, ledger_id DESC 
            LIMIT 1";

    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return roundAmount(floatval($row['ending_balance']));
    }
    else {
        return 0.00;
    }
}

// Function to recalculate ALL ending balances for an account
function recalculateAccountEndingBalances($conn, $account_code)
{
    // Get ALL entries for this account in chronological order
    $sql = "SELECT ledger_id, transaction_date, debit_amount, credit_amount 
            FROM ledger 
            WHERE account_code = '$account_code' 
            ORDER BY transaction_date ASC, ledger_id ASC";

    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $current_balance = 0.00;

        // Process ALL entries from beginning to end
        while ($entry = mysqli_fetch_assoc($result)) {
            $movement = roundAmount($entry['debit_amount'] - $entry['credit_amount']);
            $ending_balance = roundAmount($current_balance + $movement);

            // Update this entry
            $update_sql = "UPDATE ledger SET 
                          beginning_balance = $current_balance,
                          movement = $movement,
                          ending_balance = $ending_balance
                          WHERE ledger_id = " . $entry['ledger_id'];

            mysqli_query($conn, $update_sql);

            // Set current balance for next iteration
            $current_balance = $ending_balance;
        }
    }

    return true;
}

// Get current user
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1;

// Handle form submission
$error_message = '';
$success_message = '';

// Auto-create ledger entries from payment transaction
if (isset($_GET['action']) && $_GET['action'] === 'create_from_payment' && isset($_SESSION['ledger_transaction'])) {
    $transaction_data = $_SESSION['ledger_transaction'];

    // Determine cash/bank account based on payment method
    $payment_method = $transaction_data['payment_method'];
    $cash_account_code = '1101'; // Cash Account
    $bank_account_code = '1102'; // Bank Account

    // Use bank account for bank transfers, cash for everything else
    $receivable_account = ($payment_method === 'Bank Transfer') ? $bank_account_code : $cash_account_code;
    $receivable_account_name = ($payment_method === 'Bank Transfer') ? 'Bank Account' : 'Cash Account';

    // Define all accounts with their codes and classes
    $accounts = [
        'cash_bank' => [
            'code' => $receivable_account,
            'name' => $receivable_account_name,
            'class' => 'Assets',
            'normal_balance' => 'Debit'
        ],
        'loan_portfolio' => [
            'code' => '1201',
            'name' => 'Loan Portfolio – Performing',
            'class' => 'Assets',
            'normal_balance' => 'Debit'
        ],
        'interest_income' => [
            'code' => '4101',
            'name' => 'Interest on Loans',
            'class' => 'Revenue',
            'normal_balance' => 'Credit'
        ],
        'monitoring_fee' => [
            'code' => '4202',
            'name' => 'Monitoring Fee Income',
            'class' => 'Fee Income',
            'normal_balance' => 'Credit'
        ],
        'vat_payable' => [
            'code' => '2105',
            'name' => 'VAT Payable',
            'class' => 'Liability',
            'normal_balance' => 'Credit'
        ]
    ];

    // Get breakdown amounts with rounding
    $breakdown = $transaction_data['breakdown'];
    $principal = roundAmount($breakdown['principal']);
    $interest = roundAmount($breakdown['interest']);
    $total_monitoring_fee = roundAmount($breakdown['monitoring_fee']);
    $net_monitoring_fee = roundAmount($total_monitoring_fee / 1.18);
    $vat_amount = roundAmount($total_monitoring_fee - $net_monitoring_fee);
    $total_payment = roundAmount($breakdown['total_payment']);

    // Verify rounded amounts still balance
    $rounded_total = roundAmount($principal + $interest + $net_monitoring_fee + $vat_amount);
    if (abs($total_payment - $rounded_total) > 0.01) {
        // Adjust principal if rounding causes imbalance
        $principal = roundAmount($principal + ($total_payment - $rounded_total));
    }

    // ── VOUCHER NAMING FIX ──────────────────────────────────────────
    // Standardize naming: RV-CustomerCode-Timestamp for clear auditing
    $cust_ref = $transaction_data['customer_code'] ?: 'CUST';
    $voucher_number = 'RV-' . $cust_ref . '-' . date('His');

    // Narration and particular (same for all entries in this transaction)
    $narration = $transaction_data['narration'] ?:
        "Payment from " . $transaction_data['customer_name'] .
        " - Instalment #" . $transaction_data['instalment_number'];

    // Start database transaction
    mysqli_begin_transaction($conn);

    try {
        $created_entries = [];
        $affected_accounts = []; // Track which accounts we're modifying

        // Get the exact timestamp for all entries to maintain consistent ordering
        $timestamp = date('Y-m-d H:i:s');

        // 1. DEBIT: Cash/Bank Account (Dr) - Total payment received
        $beginning_balance1 = calculateBeginningBalance($conn, $accounts['cash_bank']['code'], $transaction_data['transaction_date']);
        $movement1 = $total_payment; // Increase in assets (debit)
        $ending_balance1 = roundAmount($beginning_balance1 + $movement1);

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
                    created_by, 
                    created_at
                ) VALUES (
                    '" . mysqli_real_escape_string($conn, $transaction_data['transaction_date']) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['cash_bank']['class']) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['cash_bank']['code']) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['cash_bank']['name']) . "',
                    '" . mysqli_real_escape_string($conn, $narration) . "',
                    '" . mysqli_real_escape_string($conn, $voucher_number) . "',
                    '" . mysqli_real_escape_string($conn, $narration) . "',
                    " . floatval($beginning_balance1) . ",
                    " . floatval($total_payment) . ",
                    0,
                    " . floatval($movement1) . ",
                    " . floatval($ending_balance1) . ",
                    " . intval($user_id) . ",
                    '" . $timestamp . "'
                )";

        if (!mysqli_query($conn, $sql1)) {
            throw new Exception("Failed to create cash/bank entry: " . mysqli_error($conn));
        }
        $created_entries[] = [
            'account' => $accounts['cash_bank']['code'] . ' - ' . $accounts['cash_bank']['name'],
            'debit' => $total_payment,
            'credit' => 0,
            'beginning' => $beginning_balance1,
            'movement' => $movement1,
            'ending' => $ending_balance1
        ];
        $affected_accounts[] = $accounts['cash_bank']['code'];

        // 2. CREDIT: Loan Portfolio (Cr) - Principal portion
        $beginning_balance2 = calculateBeginningBalance($conn, $accounts['loan_portfolio']['code'], $transaction_data['transaction_date']);
        $movement2 = roundAmount(-$principal); // Decrease in assets (credit)
        $ending_balance2 = roundAmount($beginning_balance2 + $movement2);

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
                    created_by, 
                    created_at
                ) VALUES (
                    '" . mysqli_real_escape_string($conn, $transaction_data['transaction_date']) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['loan_portfolio']['class']) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['loan_portfolio']['code']) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['loan_portfolio']['name']) . "',
                    '" . mysqli_real_escape_string($conn, $narration) . "',
                    '" . mysqli_real_escape_string($conn, $voucher_number) . "',
                    '" . mysqli_real_escape_string($conn, $narration) . "',
                    " . floatval($beginning_balance2) . ",
                    0,
                    " . floatval($principal) . ",
                    " . floatval($movement2) . ",
                    " . floatval($ending_balance2) . ",
                    " . intval($user_id) . ",
                    '" . $timestamp . "'
                )";

        if (!mysqli_query($conn, $sql2)) {
            throw new Exception("Failed to create loan portfolio entry: " . mysqli_error($conn));
        }
        $created_entries[] = [
            'account' => $accounts['loan_portfolio']['code'] . ' - ' . $accounts['loan_portfolio']['name'],
            'debit' => 0,
            'credit' => $principal,
            'beginning' => $beginning_balance2,
            'movement' => $movement2,
            'ending' => $ending_balance2
        ];
        $affected_accounts[] = $accounts['loan_portfolio']['code'];

        // 3. CREDIT: Interest Income (Cr) - Interest portion
        $beginning_balance3 = calculateBeginningBalance($conn, $accounts['interest_income']['code'], $transaction_data['transaction_date']);
        $movement3 = roundAmount(-$interest); // Increase in revenue (credit)
        $ending_balance3 = roundAmount($beginning_balance3 + $movement3);

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
                    created_by, 
                    created_at
                ) VALUES (
                    '" . mysqli_real_escape_string($conn, $transaction_data['transaction_date']) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['interest_income']['class']) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['interest_income']['code']) . "',
                    '" . mysqli_real_escape_string($conn, $accounts['interest_income']['name']) . "',
                    '" . mysqli_real_escape_string($conn, $narration) . "',
                    '" . mysqli_real_escape_string($conn, $voucher_number) . "',
                    '" . mysqli_real_escape_string($conn, $narration) . "',
                    " . floatval($beginning_balance3) . ",
                    0,
                    " . floatval($interest) . ",
                    " . floatval($movement3) . ",
                    " . floatval($ending_balance3) . ",
                    " . intval($user_id) . ",
                    '" . $timestamp . "'
                )";

        if (!mysqli_query($conn, $sql3)) {
            throw new Exception("Failed to create interest income entry: " . mysqli_error($conn));
        }
        $created_entries[] = [
            'account' => $accounts['interest_income']['code'] . ' - ' . $accounts['interest_income']['name'],
            'debit' => 0,
            'credit' => $interest,
            'beginning' => $beginning_balance3,
            'movement' => $movement3,
            'ending' => $ending_balance3
        ];
        $affected_accounts[] = $accounts['interest_income']['code'];

        // 4. CREDIT: Monitoring Fee Income (Cr) - NET amount (without VAT)
        if ($net_monitoring_fee > 0) {
            $beginning_balance4 = calculateBeginningBalance($conn, $accounts['monitoring_fee']['code'], $transaction_data['transaction_date']);
            $movement4 = roundAmount(-$net_monitoring_fee); // Increase in fee income (credit)
            $ending_balance4 = roundAmount($beginning_balance4 + $movement4);

            $sql4 = "INSERT INTO ledger (
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
                        created_by, 
                        created_at
                    ) VALUES (
                        '" . mysqli_real_escape_string($conn, $transaction_data['transaction_date']) . "',
                        '" . mysqli_real_escape_string($conn, $accounts['monitoring_fee']['class']) . "',
                        '" . mysqli_real_escape_string($conn, $accounts['monitoring_fee']['code']) . "',
                        '" . mysqli_real_escape_string($conn, $accounts['monitoring_fee']['name']) . "',
                        '" . mysqli_real_escape_string($conn, $narration) . "',
                        '" . mysqli_real_escape_string($conn, $voucher_number) . "',
                        '" . mysqli_real_escape_string($conn, $narration) . "',
                        " . floatval($beginning_balance4) . ",
                        0,
                        " . floatval($net_monitoring_fee) . ",
                        " . floatval($movement4) . ",
                        " . floatval($ending_balance4) . ",
                        " . intval($user_id) . ",
                        '" . $timestamp . "'
                    )";

            if (!mysqli_query($conn, $sql4)) {
                throw new Exception("Failed to create monitoring fee entry: " . mysqli_error($conn));
            }
            $created_entries[] = [
                'account' => $accounts['monitoring_fee']['code'] . ' - ' . $accounts['monitoring_fee']['name'],
                'debit' => 0,
                'credit' => $net_monitoring_fee,
                'beginning' => $beginning_balance4,
                'movement' => $movement4,
                'ending' => $ending_balance4
            ];
            $affected_accounts[] = $accounts['monitoring_fee']['code'];
        }

        // 5. CREDIT: VAT Payable (Cr) - VAT portion of monitoring fee
        if ($vat_amount > 0) {
            $beginning_balance5 = calculateBeginningBalance($conn, $accounts['vat_payable']['code'], $transaction_data['transaction_date']);
            $movement5 = roundAmount(-$vat_amount); // Increase in liability (credit)
            $ending_balance5 = roundAmount($beginning_balance5 + $movement5);

            $sql5 = "INSERT INTO ledger (
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
                        created_by, 
                        created_at
                    ) VALUES (
                        '" . mysqli_real_escape_string($conn, $transaction_data['transaction_date']) . "',
                        '" . mysqli_real_escape_string($conn, $accounts['vat_payable']['class']) . "',
                        '" . mysqli_real_escape_string($conn, $accounts['vat_payable']['code']) . "',
                        '" . mysqli_real_escape_string($conn, $accounts['vat_payable']['name']) . "',
                        '" . mysqli_real_escape_string($conn, $narration) . "',
                        '" . mysqli_real_escape_string($conn, $voucher_number) . "',
                        '" . mysqli_real_escape_string($conn, $narration) . "',
                        " . floatval($beginning_balance5) . ",
                        0,
                        " . floatval($vat_amount) . ",
                        " . floatval($movement5) . ",
                        " . floatval($ending_balance5) . ",
                        " . intval($user_id) . ",
                        '" . $timestamp . "'
                    )";

            if (!mysqli_query($conn, $sql5)) {
                throw new Exception("Failed to create VAT entry: " . mysqli_error($conn));
            }
            $created_entries[] = [
                'account' => $accounts['vat_payable']['code'] . ' - ' . $accounts['vat_payable']['name'],
                'debit' => 0,
                'credit' => $vat_amount,
                'beginning' => $beginning_balance5,
                'movement' => $movement5,
                'ending' => $ending_balance5
            ];
            $affected_accounts[] = $accounts['vat_payable']['code'];
        }

        // Verify double-entry accounting: Debits must equal Credits
        $total_debits = $total_payment;
        $total_credits = roundAmount($principal + $interest + $net_monitoring_fee + $vat_amount);

        if (abs($total_debits - $total_credits) > 0.01) {
            throw new Exception("Accounting equation not balanced! Debits: " . $total_debits . " ≠ Credits: " . $total_credits);
        }

        mysqli_commit($conn);

        // CRITICAL: After inserting new entries, recalculate ALL ending balances for affected accounts
        foreach (array_unique($affected_accounts) as $account_code) {
            recalculateAccountEndingBalances($conn, $account_code);
        }

        // Store success data
        $_SESSION['ledger_success'] = [
            'voucher' => $voucher_number,
            'customer' => $transaction_data['customer_name'],
            'amount' => $total_payment,
            'entries_created' => count($created_entries),
            'date' => $transaction_data['transaction_date'],
            'details' => $created_entries
        ];

        // Clear the transaction data
        unset($_SESSION['ledger_transaction']);

        // Redirect to show success
        header("Location: index.php?page=ledger&success=1&voucher=" . urlencode($voucher_number));
        exit();

    }
    catch (Exception $e) {
        mysqli_rollback($conn);
        $error_message = "Failed to create ledger entries: " . $e->getMessage();
    }
}

// Handle regular journal entry submission - SIMPLE VERSION
if (isset($_POST['save_journal_entry'])) {
    error_log("Manual journal entry POST received");

    // Basic input validation
    $transaction_date = mysqli_real_escape_string($conn, $_POST['transaction_date']);
    $voucher_number = mysqli_real_escape_string($conn, $_POST['voucher_number']);

    // Get all particulars
    $particulars = [];
    $total_debit = 0;
    $total_credit = 0;
    $affected_accounts = [];

    if (isset($_POST['particulars']) && is_array($_POST['particulars'])) {
        foreach ($_POST['particulars'] as $index => $particular) {
            if (!empty($particular['account_code'])) {
                $account_code = mysqli_real_escape_string($conn, $particular['account_code']);
                $debit = isset($particular['debit_amount']) ? roundAmount(floatval($particular['debit_amount'])) : 0.00;
                $credit = isset($particular['credit_amount']) ? roundAmount(floatval($particular['credit_amount'])) : 0.00;

                if ($debit < 0 || $credit < 0) {
                    continue;
                }

                // ── KEY FIX: store the raw (un-escaped) narration text so we can
                //    escape it fresh at INSERT time inside the per-entry loop.
                $particulars[] = [
                    'account_code' => $account_code,
                    'account_name' => isset($particular['account_name']) ? $particular['account_name'] : '',
                    'class' => isset($particular['class']) ? $particular['class'] : '',
                    'debit_amount' => $debit,
                    'credit_amount' => $credit,
                    // Keep the raw value — we will escape it in the INSERT below
                    'particular_narration' => isset($particular['particular_narration']) ? trim($particular['particular_narration']) : '',
                ];

                $total_debit += $debit;
                $total_credit += $credit;

                if (!in_array($account_code, $affected_accounts)) {
                    $affected_accounts[] = $account_code;
                }

                error_log("Particular $index: Account=" . $account_code . ", Debit=$debit, Credit=$credit");
            }
        }
    }

    error_log("Total Debit: $total_debit, Total Credit: $total_credit");

    // Basic validation
    $errors = [];
    if (empty($transaction_date))
        $errors[] = "Transaction date is required";
    if (empty($voucher_number))
        $errors[] = "Voucher number is required";
    if (count($particulars) < 1)
        $errors[] = "At least one journal entry is required";
    if (abs($total_debit - $total_credit) > 0.01) {
        $errors[] = "Journal entry must balance! Debits (" . formatMoney($total_debit) . ") must equal Credits (" . formatMoney($total_credit) . ")";
    }

    if (empty($errors)) {
        $timestamp = date('Y-m-d H:i:s');
        $success_count = 0;

        mysqli_begin_transaction($conn);

        try {
            foreach ($particulars as $particular) {
                $account_code = $particular['account_code'];

                // Fetch account details from chart_of_accounts
                $account_sql = "SELECT class, account_name, normal_balance FROM chart_of_accounts WHERE account_code = '$account_code'";
                $account_result = mysqli_query($conn, $account_sql);

                if ($account_result && mysqli_num_rows($account_result) > 0) {
                    $account = mysqli_fetch_assoc($account_result);
                }
                else {
                    $account = [
                        'class' => !empty($particular['class']) ? $particular['class'] : 'Assets',
                        'account_name' => !empty($particular['account_name']) ? $particular['account_name'] : 'Unknown Account',
                        'normal_balance' => 'Debit',
                    ];
                }

                // Beginning / ending balance
                $beginning_balance = calculateBeginningBalance($conn, $account_code, $transaction_date);
                $debit_amount = $particular['debit_amount'];
                $credit_amount = $particular['credit_amount'];
                $movement = roundAmount($debit_amount - $credit_amount);
                $ending_balance = roundAmount($beginning_balance + $movement);

                // ── THE FIX ──────────────────────────────────────────────────────────
                // Use THIS entry's own narration/particular text for BOTH the
                // `particular` column AND the `narration` column, not a shared value.
                $entry_particular = mysqli_real_escape_string($conn, $particular['particular_narration']);

                // Fallback: if the user left it blank, build a sensible default
                if ($entry_particular === '') {
                    $entry_particular = mysqli_real_escape_string(
                        $conn,
                        'Manual Journal Entry - ' . $voucher_number
                    );
                }
                // ─────────────────────────────────────────────────────────────────────

                $reference_type = 'MANUAL_JOURNAL';
                $reference_id = 'MJE-' . date('YmdHis') . '-' . $voucher_number;

                $insert_sql = "INSERT INTO ledger (
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
                    '$transaction_date',
                    '" . mysqli_real_escape_string($conn, $account['class']) . "',
                    '$account_code',
                    '" . mysqli_real_escape_string($conn, $account['account_name']) . "',
                    '$entry_particular',
                    '$voucher_number',
                    '$entry_particular',
                    $beginning_balance,
                    $debit_amount,
                    $credit_amount,
                    $movement,
                    $ending_balance,
                    '$reference_type',
                    '$reference_id',
                    $user_id,
                    '$timestamp',
                    '$timestamp'
                )";

                error_log("Executing SQL: " . $insert_sql);
                error_log("Beginning Balance for $account_code: $beginning_balance, Movement: $movement, Ending: $ending_balance");

                if (mysqli_query($conn, $insert_sql)) {
                    $success_count++;
                    $ledger_id = mysqli_insert_id($conn);
                    error_log("Successfully inserted ledger entry ID: $ledger_id for account: $account_code");

                    // Update reference_id with actual ledger_id
                    $update_ref_sql = "UPDATE ledger SET 
                        reference_id = CONCAT('MJE-', ledger_id, '-', '$voucher_number') 
                        WHERE ledger_id = $ledger_id";
                    mysqli_query($conn, $update_ref_sql);
                }
                else {
                    $error_msg = mysqli_error($conn);
                    error_log("Failed to insert ledger entry for account $account_code: " . $error_msg);
                    throw new Exception("Failed to insert ledger entry for account $account_code: " . $error_msg);
                }
            }

            mysqli_commit($conn);

            if ($success_count == count($particulars)) {
                error_log("All journal entries saved successfully. Voucher: $voucher_number, Total entries: $success_count");

                // Recalculate ending balances for all affected accounts
                foreach (array_unique($affected_accounts) as $account_code) {
                    recalculateAccountEndingBalances($conn, $account_code);
                }

                if (session_status() === PHP_SESSION_NONE)if (session_status() === PHP_SESSION_NONE) {
                    session_start();                }
                $_SESSION['success_message'] = "Journal entry saved successfully! Voucher: $voucher_number";

                echo "<script>window.location.href='?page=ledger'</script>";
                exit();
            }

        }
        catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = "Transaction failed: " . $e->getMessage();
            error_log("Transaction rolled back: " . $error_message);

            if (session_status() === PHP_SESSION_NONE)if (session_status() === PHP_SESSION_NONE) {
                session_start();            }
            $_SESSION['error_message'] = $error_message;

            echo "<script>window.location.href='?page=ledger'</script>";
            exit();
        }

    }
    else {
        $error_message = implode("<br>", $errors);
        error_log("Validation errors: " . $error_message);

        if (session_status() === PHP_SESSION_NONE)if (session_status() === PHP_SESSION_NONE) {
            session_start();        }
        $_SESSION['error_message'] = $error_message;
        $_SESSION['form_data'] = $_POST;

        header("Location: index.php?page=ledger&error=1");
        exit();
    }
}

// Check for success messages from session
if (isset($_SESSION['ledger_success'])) {
    $ledger_success = $_SESSION['ledger_success'];
    $success_message = "<div class='alert alert-success'>
        <h5><i class='fas fa-check-circle me-2'></i>Payment Ledger Entries Created Successfully!</h5>
        <p><strong>Voucher Number:</strong> " . htmlspecialchars($ledger_success['voucher']) . "</p>
        <p><strong>Customer:</strong> " . htmlspecialchars($ledger_success['customer']) . "</p>
        <p><strong>Total Amount:</strong>  " . formatMoney($ledger_success['amount']) . "</p>
        <p><strong>Transaction Date:</strong> " . htmlspecialchars($ledger_success['date']) . "</p>
        <p><strong>Entries Created:</strong> " . $ledger_success['entries_created'] . "</p>
        <hr>
        <h6>Entry Details:</h6>
        <ul>";

    foreach ($ledger_success['details'] as $entry) {
        $debit = $entry['debit'] > 0 ? "Dr: " . formatMoney($entry['debit']) : "";
        $credit = $entry['credit'] > 0 ? "Cr: " . formatMoney($entry['credit']) : "";
        $movement = isset($entry['movement']) ? " (Movement: " . ($entry['movement'] >= 0 ? '+' : '') . formatMoney($entry['movement']) . ")" : "";
        $beginning = isset($entry['beginning']) ? " Beg: " . formatMoney($entry['beginning']) : "";
        $ending = isset($entry['ending']) ? " End: " . formatMoney($entry['ending']) . "</li>" : "";
        $success_message .= "<li>" . htmlspecialchars($entry['account']) . " - " . $debit . $credit . $movement . $beginning . $ending;
    }

    $success_message .= "</ul></div>";
    unset($_SESSION['ledger_success']);
}

if (isset($_SESSION['success_message'])) {
    $success_message = "<div class='alert alert-success'><i class='fas fa-check-circle me-2'></i>" . $_SESSION['success_message'] . "</div>";
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get search parameters
$search_date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$search_date_to = $_GET['date_to'] ?? date('Y-m-d');
$view_type = $_GET['view_type'] ?? 'range';
$search_month = $_GET['month'] ?? date('Y-m');
$search_day = $_GET['day'] ?? date('Y-m-d');
$search_account = $_GET['account'] ?? '';
$search_voucher = $_GET['voucher'] ?? '';

if ($view_type === 'month' && !empty($search_month)) {
    $search_date_from = date('Y-m-01', strtotime($search_month . '-01'));
    $search_date_to = date('Y-m-t', strtotime($search_month . '-01'));
}

if ($view_type === 'day' && !empty($search_day)) {
    $search_date_from = $search_day;
    $search_date_to = $search_day;
}

// Build WHERE clause
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search_date_from) && !empty($search_date_to)) {
    $where_clauses[] = "l.transaction_date BETWEEN ? AND ?";
    $params[] = $search_date_from;
    $params[] = $search_date_to;
    $types .= 'ss';
}

if (!empty($search_account)) {
    $where_clauses[] = "(l.account_code LIKE ? OR l.account_name LIKE ?)";
    $params[] = "%" . $search_account . "%";
    $params[] = "%" . $search_account . "%";
    $types .= 'ss';
}

if (!empty($search_voucher)) {
    $where_clauses[] = "l.voucher_number LIKE ?";
    $params[] = "%" . $search_voucher . "%";
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$ledger_sql = "SELECT 
                l.*,
                (SELECT COALESCE(SUM(l2.movement), 0) 
                 FROM ledger l2 
                 WHERE l2.account_code = l.account_code 
                 AND (l2.transaction_date < l.transaction_date 
                      OR (l2.transaction_date = l.transaction_date AND l2.ledger_id <= l.ledger_id))
                ) as cumulative_movement
              FROM ledger l 
              $where_sql
              ORDER BY l.transaction_date ASC, l.ledger_id ASC 
              LIMIT 1000";

$ledger_stmt = mysqli_prepare($conn, $ledger_sql);
if ($ledger_stmt && !empty($params)) {
    mysqli_stmt_bind_param($ledger_stmt, $types, ...$params);
}
if ($ledger_stmt) {
    mysqli_stmt_execute($ledger_stmt);
    $ledger_result = mysqli_stmt_get_result($ledger_stmt);
}
else {
    $ledger_result = mysqli_query($conn, $ledger_sql);
}

$months_sql = "SELECT DISTINCT DATE_FORMAT(transaction_date, '%Y-%m') as month FROM ledger ORDER BY month DESC";
$months_result = mysqli_query($conn, $months_sql);

$dates_sql = "SELECT DISTINCT transaction_date as date FROM ledger ORDER BY date DESC LIMIT 100";
$dates_result = mysqli_query($conn, $dates_sql);

$accounts_sql = "SELECT account_code, account_name, class, normal_balance FROM chart_of_accounts WHERE is_active = 1 ORDER BY class, account_code";
$accounts_result = mysqli_query($conn, $accounts_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Ledger System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .particular-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
            transition: all 0.3s;
        }
        .particular-card:hover {
            background: #e9ecef;
            border-color: #0d6efd;
        }
        .particular-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .particular-number {
            background: #0d6efd;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        .remove-particular {
            color: #dc3545;
            cursor: pointer;
            font-size: 1.2rem;
        }
        .remove-particular:hover {
            color: #b02a37;
        }
        .debit-input {
            border-left: 3px solid #198754 !important;
        }
        .credit-input {
            border-left: 3px solid #dc3545 !important;
        }
        .btn-add-particular {
            width: 100%;
            padding: 10px;
            font-size: 1.1rem;
        }
        .account-select {
            height: 100px;
        }
        .table th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
        }
        .payment-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .payment-info-card h5 {
            color: white;
        }
        .success-summary {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .amount-badge {
            font-size: 0.9em;
            padding: 3px 8px;
            margin: 0 2px;
        }
        .balance-positive {
            color: #198754;
            font-weight: bold;
        }
        .balance-negative {
            color: #dc3545;
            font-weight: bold;
        }
        .account-code {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .voucher-code {
            font-family: monospace;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
        .total-row {
            background-color: #f8f9fa !important;
            font-weight: bold;
        }
        .text-monospace {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        }
        .timeframe-filter {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .view-type-btn {
            border-radius: 20px !important;
            margin: 0 2px;
        }
        .view-type-btn.active {
            box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.5) !important;
            font-weight: bold;
        }
        .period-badge {
            font-size: 0.85em;
            padding: 3px 8px;
            margin-left: 10px;
        }
        .quick-date-btn {
            border-radius: 5px;
            margin: 2px;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Payment Transaction Info -->
        <?php if (isset($_SESSION['ledger_transaction'])):
    $data = $_SESSION['ledger_transaction'];
?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="payment-info-card">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h5><i class="fas fa-money-bill-wave me-2"></i>Payment Ready for Ledger Entry</h5>
                            <p class="mb-1">
                                <strong>Customer:</strong> <?php echo htmlspecialchars($data['customer_name']); ?> 
                                (<span class="voucher-code"><?php echo htmlspecialchars($data['customer_code']); ?></span>)
                            </p>
                            <p class="mb-1">
                                <strong>Amount:</strong>  <?php echo formatMoney($data['payment_amount']); ?> 
                                | <strong>Date:</strong> <?php echo htmlspecialchars($data['transaction_date']); ?>
                                | <strong>Instalment:</strong> #<?php echo htmlspecialchars($data['instalment_number']); ?>
                            </p>
                            <p class="mb-0">
                                <strong>Breakdown:</strong> 
                                Principal:  <?php echo formatMoney($data['breakdown']['principal']); ?>, 
                                Interest:  <?php echo formatMoney($data['breakdown']['interest']); ?>,
                                Monitoring Fee:  <?php echo formatMoney($data['breakdown']['monitoring_fee']); ?>,
                                VAT:  <?php echo formatMoney($data['breakdown']['vat_amount']); ?>
                            </p>
                        </div>
                        <div class="mt-2 mt-md-0">
                            <a href="index.php?page=ledger&action=create_from_payment" class="btn btn-light btn-lg">
                                <i class="fas fa-plus-circle me-1"></i>Create Ledger Entries
                            </a>
                            <a href="index.php?page=ledger&action=clear_ledger_data" class="btn btn-outline-light btn-lg ms-2">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
endif; ?>

        <!-- Success/Error Messages -->
        <?php if (!empty($error_message)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
        <?php
endif; ?>
        
        <?php if (!empty($success_message)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <?php echo $success_message; ?>
            </div>
        </div>
        <?php
endif; ?>
        <div class="mb-4">
            <a href="index.php?page=ledger_management" class="btn btn-outline-secondary">
                <i class="fas fa-cog me-1"></i>Manage Ledger
            </a>
        </div>
        <!-- Time Frame Filter Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="timeframe-filter">
                    <h5><i class="fas fa-calendar-alt me-2"></i>Time Frame Filter</h5>
                    
                    <div class="mb-3">
                        <div class="btn-group btn-group-sm" role="group">
                            <input type="radio" class="btn-check" name="view_type_radio" id="view_range_radio" 
                                   value="range" <?php echo $view_type === 'range' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-primary view-type-btn" for="view_range_radio">
                                <i class="fas fa-calendar-alt me-1"></i>Date Range
                            </label>
                            
                            <input type="radio" class="btn-check" name="view_type_radio" id="view_month_radio" 
                                   value="month" <?php echo $view_type === 'month' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-primary view-type-btn" for="view_month_radio">
                                <i class="fas fa-calendar me-1"></i>By Month
                            </label>
                            
                            <input type="radio" class="btn-check" name="view_type_radio" id="view_day_radio" 
                                   value="day" <?php echo $view_type === 'day' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-primary view-type-btn" for="view_day_radio">
                                <i class="fas fa-calendar-day me-1"></i>By Day
                            </label>
                        </div>
                    </div>
                    
                    <form method="GET" id="timeFrameForm" class="row g-3 align-items-end">
                        <input type="hidden" name="page" value="ledger">
                        <input type="hidden" name="view_type" id="view_type_input" value="<?php echo $view_type; ?>">
                        
                        <!-- Date Range View -->
                        <div id="range_view" class="col-12 <?php echo $view_type === 'range' ? '' : 'd-none'; ?>">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">From Date</label>
                                    <input type="date" class="form-control" name="date_from" 
                                           value="<?php echo htmlspecialchars($search_date_from); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">To Date</label>
                                    <input type="date" class="form-control" name="date_to" 
                                           value="<?php echo htmlspecialchars($search_date_to); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Quick Select</label>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary quick-date-btn" onclick="setQuickRange('today')">Today</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary quick-date-btn" onclick="setQuickRange('yesterday')">Yesterday</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary quick-date-btn" onclick="setQuickRange('week')">This Week</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary quick-date-btn" onclick="setQuickRange('month')">This Month</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary quick-date-btn" onclick="setQuickRange('last_month')">Last Month</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary quick-date-btn" onclick="setQuickRange('year')">This Year</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Month View -->
                        <div id="month_view" class="col-12 <?php echo $view_type === 'month' ? '' : 'd-none'; ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Select Month</label>
                                    <select class="form-select" name="month" id="month_select">
                                        <option value="">Select Month</option>
                                        <?php if ($months_result && mysqli_num_rows($months_result) > 0): ?>
                                            <?php while ($month = mysqli_fetch_assoc($months_result)): ?>
                                                <option value="<?php echo htmlspecialchars($month['month']); ?>" 
                                                    <?php echo $search_month === $month['month'] ? 'selected' : ''; ?>>
                                                    <?php echo date('F Y', strtotime($month['month'] . '-01')); ?>
                                                </option>
                                            <?php
    endwhile; ?>
                                            <?php mysqli_data_seek($months_result, 0); ?>
                                        <?php
endif; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Day View -->
                        <div id="day_view" class="col-12 <?php echo $view_type === 'day' ? '' : 'd-none'; ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Select Date</label>
                                    <select class="form-select" name="day" id="day_select">
                                        <option value="">Select Date</option>
                                        <?php if ($dates_result && mysqli_num_rows($dates_result) > 0): ?>
                                            <?php while ($date = mysqli_fetch_assoc($dates_result)): ?>
                                                <option value="<?php echo htmlspecialchars($date['date']); ?>" 
                                                    <?php echo $search_day === $date['date'] ? 'selected' : ''; ?>>
                                                    <?php echo date('d M Y', strtotime($date['date'])); ?>
                                                </option>
                                            <?php
    endwhile; ?>
                                            <?php mysqli_data_seek($dates_result, 0); ?>
                                        <?php
endif; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Additional Filters -->
                        <div class="col-md-4">
                            <label class="form-label">Voucher Number</label>
                            <input type="text" class="form-control" name="voucher" 
                                   placeholder="Search voucher..." value="<?php echo htmlspecialchars($search_voucher); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Account</label>
                            <input type="text" class="form-control" name="account" 
                                   placeholder="Account code or name..." value="<?php echo htmlspecialchars($search_account); ?>">
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i>Apply Filter
                                </button>
                                <a href="index.php?page=ledger" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-1"></i>Reset
                                </a>
                            </div>
                        </div>
                    </form>
                    
                    <?php if ($view_type === 'month' && !empty($search_month)): ?>
                    <div class="mt-3">
                        <span class="badge bg-info period-badge">
                            Viewing: <?php echo date('F Y', strtotime($search_month . '-01')); ?>
                        </span>
                    </div>
                    <?php
elseif ($view_type === 'day' && !empty($search_day)): ?>
                    <div class="mt-3">
                        <span class="badge bg-info period-badge">
                            Viewing: <?php echo date('d M Y', strtotime($search_day)); ?>
                        </span>
                    </div>
                    <?php
elseif (!empty($search_date_from) && !empty($search_date_to)): ?>
                    <div class="mt-3"></div>
                    <?php
endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Manual Journal Entry Form -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Manual Journal Entry</h4>
                            <span class="badge bg-light text-dark">Optional</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="journalForm">
                            <!-- Journal Header -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="transaction_date" class="form-label">Date *</label>
                                    <input type="date" class="form-control" id="transaction_date" name="transaction_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="voucher_number" class="form-label">Voucher Number *</label>
                                    <input type="text" class="form-control" id="voucher_number" name="voucher_number" 
                                           value="<?php echo generateVoucherNumber('JV'); ?>" required>
                                    <small class="text-muted">Auto-generated or enter custom voucher</small>
                                </div>
                            </div>

                            <!-- Particulars Container -->
                            <div id="particularsContainer">
                                <h5 class="mb-3"><i class="fas fa-list me-2"></i>Journal Entries</h5>
                                
                                <!-- Particular 1 -->
                                <div class="particular-card" id="particular-1">
                                    <div class="particular-card-header">
                                        <div class="particular-number">Entry #1</div>
                                        <div class="remove-particular" onclick="removeParticular(1)" style="display: none;">
                                            <i class="fas fa-trash"></i>
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Account (Class) *</label>
                                            <select class="form-select account-select" name="particulars[1][account_code]" 
                                                    onchange="updateAccountDetails(this, 1)" required>
                                                <option value="">Select Account</option>
                                                <?php if ($accounts_result && mysqli_num_rows($accounts_result) > 0): ?>
                                                    <?php while ($account = mysqli_fetch_assoc($accounts_result)): ?>
                                                    <option value="<?php echo htmlspecialchars($account['account_code']); ?>" 
                                                            data-class="<?php echo htmlspecialchars($account['class']); ?>"
                                                            data-name="<?php echo htmlspecialchars($account['account_name']); ?>">
                                                        <?php echo htmlspecialchars($account['account_code']); ?> - 
                                                        <?php echo htmlspecialchars($account['account_name']); ?> 
                                                        (<?php echo htmlspecialchars($account['class']); ?>)
                                                    </option>
                                                    <?php
    endwhile; ?>
                                                    <?php mysqli_data_seek($accounts_result, 0); ?>
                                                <?php
endif; ?>
                                            </select>
                                            <input type="hidden" name="particulars[1][class]" id="class_1">
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label">Account Name</label>
                                            <input type="text" class="form-control" name="particulars[1][account_name]" 
                                                   id="account_name_1" readonly>
                                        </div>
                                        
                                        <!-- Narration/Particular — this is saved to BOTH `particular` and `narration` columns -->
                                        <div class="col-md-4">
                                            <label class="form-label">Narration / Particular *</label>
                                            <textarea class="form-control" name="particulars[1][particular_narration]" 
                                                      rows="2" placeholder="Description of this entry" required></textarea>
                                            <small class="text-muted">Saved as the Particular for this entry</small>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label">Debit (Dr)</label>
                                            <div class="input-group">
                                                <span class="input-group-text"> </span>
                                                <input type="number" class="form-control debit-input debit-amount" 
                                                       name="particulars[1][debit_amount]" step="0.01" min="0" 
                                                       placeholder="0.00" oninput="updateTotals()">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label">Credit (Cr)</label>
                                            <div class="input-group">
                                                <span class="input-group-text"> </span>
                                                <input type="number" class="form-control credit-input credit-amount" 
                                                       name="particulars[1][credit_amount]" step="0.01" min="0" 
                                                       placeholder="0.00" oninput="updateTotals()">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="alert alert-light mb-0">
                                                <small>
                                                    <strong>Note:</strong> Beginning balance = Previous ending balance
                                                    <br>Ending balance = Beginning + (Debit - Credit)
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Totals Row -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="alert alert-info">
                                        <div class="d-flex justify-content-between">
                                            <span>Total Debits:</span>
                                            <strong id="total-debit"> 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Total Credits:</span>
                                            <strong id="total-credit"> 0.00</strong>
                                        </div>
                                        <hr class="my-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Difference:</span>
                                            <strong id="total-difference" class="text-danger"> 0.00</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-warning">
                                        <small><i class="fas fa-info-circle me-1"></i> 
                                            Journal entries must balance. Total debits must equal total credits.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mb-4">
                                <button type="button" class="btn btn-success btn-add-particular" onclick="addNewParticular()">
                                    <i class="fas fa-plus-circle me-2"></i>Add Another Journal Entry
                                </button>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" name="save_journal_entry" class="btn btn-primary btn-lg px-5">
                                    <i class="fas fa-save me-2"></i>Save Journal Entry
                                </button>
                                <button type="reset" class="btn btn-secondary btn-lg px-5 ms-2" onclick="resetForm()">
                                    <i class="fas fa-redo me-2"></i>Reset Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Recent Entries -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h4 class="mb-0"><i class="fas fa-history me-2"></i>Recent Ledger Entries</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Voucher</th>
                                        <th>Account</th>
                                        <th>Dr/Cr</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
$recent_sql = "SELECT transaction_date, voucher_number, account_code, 
                                                  account_name, debit_amount, credit_amount
                                                  FROM ledger 
                                                  ORDER BY transaction_date DESC, ledger_id DESC 
                                                  LIMIT 15";
$recent_result = mysqli_query($conn, $recent_sql);

if ($recent_result && mysqli_num_rows($recent_result) > 0):
    while ($recent = mysqli_fetch_assoc($recent_result)):
?>
                                    <tr>
                                        <td><small><?php echo date('d/m', strtotime($recent['transaction_date'])); ?></small></td>
                                        <td><small class="voucher-code"><?php echo htmlspecialchars(substr($recent['voucher_number'], 0, 8)); ?></small></td>
                                        <td><small><?php echo htmlspecialchars($recent['account_code']); ?></small></td>
                                        <td class="text-end">
                                            <?php if ($recent['debit_amount'] > 0): ?>
                                                <span class="badge bg-success">Dr: <?php echo formatMoney($recent['debit_amount']); ?></span>
                                            <?php
        else: ?>
                                                <span class="badge bg-danger">Cr: <?php echo formatMoney($recent['credit_amount']); ?></span>
                                            <?php
        endif; ?>
                                        </td>
                                    </tr>
                                    <?php
    endwhile;
else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">No recent entries</td>
                                    </tr>
                                    <?php
endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="card mt-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Today's Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php
$today = date('Y-m-d');
$today_sql = "SELECT 
                            COUNT(*) as total_entries,
                            SUM(debit_amount) as total_debits,
                            SUM(credit_amount) as total_credits
                            FROM ledger 
                            WHERE transaction_date = '$today'";
$today_result = mysqli_query($conn, $today_sql);
$today_stats = mysqli_fetch_assoc($today_result);
?>
                        <div class="row text-center">
                            <div class="col-4">
                                <h3><?php echo $today_stats['total_entries'] ?? 0; ?></h3>
                                <small class="text-muted">Entries</small>
                            </div>
                            <div class="col-4">
                                <h3 class="text-success"><?php echo formatMoney($today_stats['total_debits'] ?? 0); ?></h3>
                                <small class="text-muted">Debits</small>
                            </div>
                            <div class="col-4">
                                <h3 class="text-danger"><?php echo formatMoney($today_stats['total_credits'] ?? 0); ?></h3>
                                <small class="text-muted">Credits</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- General Ledger Table -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-table me-2"></i>General Ledger</h4>
                            <small>
                                <?php if ($view_type === 'month' && !empty($search_month)): ?>
                                    Viewing: <?php echo date('F Y', strtotime($search_month . '-01')); ?>
                                <?php
elseif ($view_type === 'day' && !empty($search_day)): ?>
                                    Viewing: <?php echo date('d M Y', strtotime($search_day)); ?>
                                <?php
else: ?>
                                    Showing last 1000 entries
                                <?php
endif; ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-body"> 
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-sm table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Account (Class)</th>
                                        <th>Account Name</th>
                                        <th>Vch #</th>
                                        <th>Particular</th>
                                        <th class="text-end">Beginning</th>
                                        <th class="text-end">Dr</th>
                                        <th class="text-end">Cr</th>
                                        <th class="text-end">Movement</th>
                                        <th class="text-end">Ending</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($ledger_result && mysqli_num_rows($ledger_result) > 0):
    $total_debit = 0;
    $total_credit = 0;
    while ($entry = mysqli_fetch_assoc($ledger_result)):
        $entry['beginning_balance'] = roundAmount($entry['beginning_balance']);
        $entry['debit_amount'] = roundAmount($entry['debit_amount']);
        $entry['credit_amount'] = roundAmount($entry['credit_amount']);
        $entry['movement'] = roundAmount($entry['movement']);
        $entry['ending_balance'] = roundAmount($entry['ending_balance']);

        $total_debit += $entry['debit_amount'];
        $total_credit += $entry['credit_amount'];
?>
                                    <tr>
                                        <td class="text-nowrap">
                                            <small><?php echo date('d/m/y', strtotime($entry['transaction_date'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="text-monospace">
                                                <strong><?php echo htmlspecialchars($entry['account_code']); ?></strong>
                                            </div>
                                            <small class="text-muted"><?php echo htmlspecialchars($entry['class']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($entry['account_name']); ?></td>
                                        <td>
                                            <code class="voucher-code"><?php echo htmlspecialchars($entry['voucher_number']); ?></code>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($entry['particular']); ?></small></td>
                                        <td class="text-end <?php echo $entry['beginning_balance'] >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                                            <?php echo formatMoney($entry['beginning_balance']); ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($entry['debit_amount'] > 0): ?>
                                                <span class="text-success fw-bold"><?php echo formatMoney($entry['debit_amount']); ?></span>
                                            <?php
        else: ?>
                                                <span class="text-muted">-</span>
                                            <?php
        endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($entry['credit_amount'] > 0): ?>
                                                <span class="text-danger fw-bold"><?php echo formatMoney($entry['credit_amount']); ?></span>
                                            <?php
        else: ?>
                                                <span class="text-muted">-</span>
                                            <?php
        endif; ?>
                                        </td>
                                        <td class="text-end <?php echo $entry['movement'] >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold">
                                            <?php echo formatMoney($entry['movement']); ?>
                                        </td>
                                        <td class="text-end <?php echo $entry['ending_balance'] >= 0 ? 'balance-positive' : 'balance-negative'; ?> fw-bold">
                                            <?php echo formatMoney($entry['ending_balance']); ?>
                                        </td>
                                    </tr>
                                    <?php
    endwhile; ?>
                                    <tr class="total-row">
                                        <td colspan="6" class="text-end fw-bold">Totals:</td>
                                        <td class="text-end fw-bold text-success"><?php echo formatMoney($total_debit); ?></td>
                                        <td class="text-end fw-bold text-danger"><?php echo formatMoney($total_credit); ?></td>
                                        <td class="text-end fw-bold text-primary"><?php echo formatMoney(roundAmount($total_debit - $total_credit)); ?></td>
                                        <td class="text-end"></td>
                                    </tr>
                                    <?php
else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">
                                            <i class="fas fa-database fa-2x mb-3"></i><br>
                                            No ledger entries found
                                        </td>
                                    </tr>
                                    <?php
endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('input[name="view_type_radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const viewType = this.value;
                document.getElementById('view_type_input').value = viewType;
                document.getElementById('range_view').classList.add('d-none');
                document.getElementById('month_view').classList.add('d-none');
                document.getElementById('day_view').classList.add('d-none');
                document.getElementById(viewType + '_view').classList.remove('d-none');
            });
        });
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('transaction_date');
        if (dateInput) dateInput.value = today;
        updateTotals();
    });
    
    function setQuickRange(range) {
        const today = new Date();
        let fromDate, toDate;
        switch(range) {
            case 'today':
                fromDate = toDate = formatDate(today); break;
            case 'yesterday':
                const y = new Date(today); y.setDate(y.getDate()-1);
                fromDate = toDate = formatDate(y); break;
            case 'week':
                const ws = new Date(today); ws.setDate(ws.getDate()-ws.getDay());
                fromDate = formatDate(ws); toDate = formatDate(today); break;
            case 'month':
                fromDate = formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
                toDate = formatDate(today); break;
            case 'last_month':
                const lms = new Date(today.getFullYear(), today.getMonth()-1, 1);
                const lme = new Date(today.getFullYear(), today.getMonth(), 0);
                fromDate = formatDate(lms); toDate = formatDate(lme); break;
            case 'year':
                fromDate = formatDate(new Date(today.getFullYear(), 0, 1));
                toDate = formatDate(today); break;
        }
        if (fromDate && toDate) {
            document.querySelector('input[name="date_from"]').value = fromDate;
            document.querySelector('input[name="date_to"]').value   = toDate;
            document.getElementById('view_type_input').value = 'range';
            document.getElementById('view_range_radio').checked = true;
            document.getElementById('range_view').classList.remove('d-none');
            document.getElementById('month_view').classList.add('d-none');
            document.getElementById('day_view').classList.add('d-none');
        }
    }
    
    function formatDate(date) {
        return date.getFullYear() + '-' +
               String(date.getMonth()+1).padStart(2,'0') + '-' +
               String(date.getDate()).padStart(2,'0');
    }
    
    let particularCounter = 1;
    
    function addNewParticular() {
        particularCounter++;
        const n = particularCounter;
        
        // Build account options from the first entry's select (clone)
        const firstSelect = document.querySelector('.account-select');
        let optionsHtml = '';
        if (firstSelect) {
            Array.from(firstSelect.options).forEach(opt => {
                optionsHtml += `<option value="${opt.value}" data-class="${opt.dataset.class||''}" data-name="${opt.dataset.name||''}">${opt.text}</option>`;
            });
        }
        
        const html = `
        <div class="particular-card" id="particular-${n}">
            <div class="particular-card-header">
                <div class="particular-number">Entry #${n}</div>
                <div class="remove-particular" onclick="removeParticular(${n})">
                    <i class="fas fa-trash"></i>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Account (Class) *</label>
                    <select class="form-select account-select" name="particulars[${n}][account_code]"
                            onchange="updateAccountDetails(this,${n})" required>
                        ${optionsHtml}
                    </select>
                    <input type="hidden" name="particulars[${n}][class]" id="class_${n}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Account Name</label>
                    <input type="text" class="form-control" name="particulars[${n}][account_name]"
                           id="account_name_${n}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Narration / Particular *</label>
                    <textarea class="form-control" name="particulars[${n}][particular_narration]"
                              rows="2" placeholder="Description of this entry" required></textarea>
                    <small class="text-muted">Saved as the Particular for this entry</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Debit (Dr)</label>
                    <div class="input-group">
                        <span class="input-group-text"> </span>
                        <input type="number" class="form-control debit-input debit-amount"
                               name="particulars[${n}][debit_amount]" step="0.01" min="0"
                               placeholder="0.00" oninput="updateTotals()">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Credit (Cr)</label>
                    <div class="input-group">
                        <span class="input-group-text"> </span>
                        <input type="number" class="form-control credit-input credit-amount"
                               name="particulars[${n}][credit_amount]" step="0.01" min="0"
                               placeholder="0.00" oninput="updateTotals()">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-light mb-0">
                        <small><strong>Note:</strong> Beginning balance = Previous ending balance<br>
                        Ending balance = Beginning + (Debit - Credit)</small>
                    </div>
                </div>
            </div>
        </div>`;
        
        document.getElementById('particularsContainer').insertAdjacentHTML('beforeend', html);
        updateTotals();
    }
    
    function updateAccountDetails(select, index) {
        const opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) return;
        document.getElementById(`account_name_${index}`).value = opt.getAttribute('data-name') || '';
        document.getElementById(`class_${index}`).value        = opt.getAttribute('data-class') || '';
    }
    
    function updateTotals() {
        let totalDebit = 0, totalCredit = 0;
        document.querySelectorAll('.debit-amount').forEach(i  => totalDebit  += parseFloat(i.value)  || 0);
        document.querySelectorAll('.credit-amount').forEach(i => totalCredit += parseFloat(i.value) || 0);
        totalDebit  = Math.round(totalDebit  * 100) / 100;
        totalCredit = Math.round(totalCredit * 100) / 100;
        document.getElementById('total-debit').textContent  = ' ' + totalDebit.toFixed(2);
        document.getElementById('total-credit').textContent = ' ' + totalCredit.toFixed(2);
        const diff = Math.round((totalDebit - totalCredit) * 100) / 100;
        const el   = document.getElementById('total-difference');
        if (Math.abs(diff) < 0.01) {
            el.className = 'text-success';
            el.innerHTML = ' ' + diff.toFixed(2) + ' <i class="fas fa-check"></i>';
        } else {
            el.className = 'text-danger';
            el.innerHTML = ' ' + diff.toFixed(2) + ' <i class="fas fa-times"></i>';
        }
    }
    
    function removeParticular(index) {
        if (document.querySelectorAll('.particular-card').length <= 1) {
            alert('Minimum 1 journal entry is required.'); return;
        }
        const el = document.getElementById(`particular-${index}`);
        if (el) { el.remove(); updateTotals(); }
    }
    
    function resetForm() {
        const cards = document.querySelectorAll('.particular-card');
        for (let i = 1; i < cards.length; i++) cards[i].remove();
        const first = document.getElementById('particular-1');
        if (first) {
            first.querySelectorAll('input, select, textarea').forEach(el => {
                if (el.type === 'hidden') return;
                el.value = el.tagName === 'SELECT' ? '' : '';
                if (el.tagName === 'SELECT') el.selectedIndex = 0;
            });
        }
        particularCounter = 1;
        updateTotals();
    }
    
    document.getElementById('journalForm').addEventListener('submit', function(e) {
        if (document.querySelectorAll('.particular-card').length < 1) {
            e.preventDefault(); alert('At least 1 journal entry is required.'); return false;
        }
        const selects = document.querySelectorAll('.account-select');
        for (let i = 0; i < selects.length; i++) {
            if (!selects[i].value) {
                e.preventDefault();
                alert(`Please select an account for Entry #${i+1}`);
                selects[i].focus(); return false;
            }
        }
        const td   = parseFloat(document.getElementById('total-debit').textContent.replace(/[^\d.-]/g,''))  || 0;
        const tc   = parseFloat(document.getElementById('total-credit').textContent.replace(/[^\d.-]/g,'')) || 0;
        const diff = Math.abs(td - tc);
        if (diff > 0.01) {
            e.preventDefault();
            alert(`Journal entry must balance!\n\nDebits: ${td.toFixed(2)}\nCredits: ${tc.toFixed(2)}\nDifference: ${diff.toFixed(2)}`);
            return false;
        }
    });
    </script>
</body>
</html>

<?php

if (isset($ledger_result))
    mysqli_free_result($ledger_result);
if (isset($accounts_result))
    mysqli_free_result($accounts_result);
if (isset($recent_result))
    mysqli_free_result($recent_result);
if (isset($months_result))
    mysqli_free_result($months_result);
if (isset($dates_result))
    mysqli_free_result($dates_result);
if (isset($conn))
    mysqli_close($conn);
?>
