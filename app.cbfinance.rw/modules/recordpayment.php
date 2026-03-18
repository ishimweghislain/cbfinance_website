<?php

// Show ALL errors immediately — never blank page
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Catch fatal errors that would otherwise produce a blank page
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: text/html');
        }
        echo '<div style="background:#f8d7da;color:#721c24;padding:20px;font-family:monospace;border:2px solid #f5c6cb;margin:20px;">';
        echo '<strong>Fatal PHP Error:</strong><br>';
        echo htmlspecialchars($error['message']) . '<br>';
        echo 'File: ' . htmlspecialchars($error['file']) . ' Line: ' . $error['line'];
        echo '</div>';
    }
});

require_once('vendor/autoload.php');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Include accounting functions for schedule recalculation, sync, etc.
require_once __DIR__ . '/../includes/accounting_functions.php';



$success_message = '';
$error_message = '';
$loan_info = [];
$existing_instalments = [];
$payment_methods = ['Cash', 'Bank Transfer', 'Mobile Money', 'Cheque', 'Other'];
$loan_id = 0;


if (!isset($_GET['loan_id']) || empty($_GET['loan_id'])) {
    $_SESSION['error_message'] = "Loan ID is required";
    header("Location: index.php?page=loans");
    exit();
}

$loan_id = intval($_GET['loan_id']);

try {
    // Include database connection
    require_once __DIR__ . '/../config/database.php';
    $conn = getConnection();

    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Fetch loan information
    $loan_query = "SELECT lp.*, c.customer_name, c.customer_code 
                FROM loan_portfolio lp 
                LEFT JOIN customers c ON lp.customer_id = c.customer_id 
                WHERE lp.loan_id = ?";

    $loan_stmt = $conn->prepare($loan_query);
    $loan_stmt->bind_param("i", $loan_id);
    $loan_stmt->execute();
    $loan_result = $loan_stmt->get_result();

    if ($loan_result->num_rows === 0) {
        $_SESSION['error_message'] = "Loan not found";
        header("Location: index.php?page=loans");
        exit();
    }

    $loan_info = $loan_result->fetch_assoc();
    $loan_stmt->close();

    // ═══════════════════════════════════════════════════════════
    // DYNAMIC RATES — read from loan_portfolio columns
    // Falls back to 5.0 / 5.5 if the column is absent or zero
    // Column names assumed: interest_rate  and  management_fee_rate
    // Adjust the key names below if your schema uses different names.
    // ═══════════════════════════════════════════════════════════
    $interest_rate_pct = isset($loan_info['interest_rate']) && floatval($loan_info['interest_rate']) > 0
        ? floatval($loan_info['interest_rate']) : 5.0;
    $mgmt_fee_rate_pct = isset($loan_info['management_fee_rate']) && floatval($loan_info['management_fee_rate']) > 0
        ? floatval($loan_info['management_fee_rate']) : 5.5;

    // Decimal equivalents used in calculations
    $interest_rate = $interest_rate_pct / 100; // e.g. 0.05
    $mgmt_fee_rate = $mgmt_fee_rate_pct / 100; // e.g. 0.055

    // Fetch all installments
    $all_instalments_query = "SELECT 
        instalment_id,
        instalment_number,
        due_date,
        opening_balance,
        closing_balance,
        principal_amount,
        interest_amount,
        management_fee,
        total_payment,
        paid_amount,
        principal_paid,
        interest_paid,
        management_fee_paid,
        balance_remaining,
        status,
        days_overdue,
        penalty_amount,
        penalty_paid,
        payment_date
        FROM loan_instalments 
        WHERE loan_id = ? 
        ORDER BY instalment_number ASC";

    $all_instalments_stmt = $conn->prepare($all_instalments_query);
    $all_instalments_stmt->bind_param("i", $loan_id);
    $all_instalments_stmt->execute();
    $all_instalments_result = $all_instalments_stmt->get_result();
    $existing_instalments = $all_instalments_result->fetch_all(MYSQLI_ASSOC);
    $all_instalments_stmt->close();

    // Function to get beginning balance from ledger
    function getBeginningBalance($conn, $account_code, $date)
    {
        $query = "SELECT ending_balance FROM ledger 
                WHERE account_code = ? 
                AND transaction_date <= ?
                ORDER BY transaction_date DESC, ledger_id DESC 
                LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $account_code, $date);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return floatval($row['ending_balance']);
        }
        return 0.00;
    }

    // Function to create ledger entry
    function createLedgerEntry($conn, $data)
    {
        $sql = "INSERT INTO ledger (
            transaction_date, class, account_code, account_name, particular,
            voucher_number, narration, beginning_balance, debit_amount, credit_amount, 
            movement, ending_balance, reference_type, reference_id, created_by, 
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssddddssii",
            $data['transaction_date'],
            $data['class'],
            $data['account_code'],
            $data['account_name'],
            $data['particular'],
            $data['voucher_number'],
            $data['narration'],
            $data['beginning_balance'],
            $data['debit_amount'],
            $data['credit_amount'],
            $data['movement'],
            $data['ending_balance'],
            $data['reference_type'],
            $data['reference_id'],
            $data['created_by']
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to create ledger entry for {$data['account_code']}: " . $stmt->error);
        }
        $stmt->close();
    }

    // Handle payment processing
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

        // --- FILE UPLOAD HANDLER ---
        $evidence_filename = null;
        if (isset($_FILES['payment_evidence']) && $_FILES['payment_evidence']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/payments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['payment_evidence']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_ext, $allowed_exts)) {
                $new_filename = 'pmt_' . time() . '_' . uniqid() . '.' . $file_ext;
                if (move_uploaded_file($_FILES['payment_evidence']['tmp_name'], $upload_dir . $new_filename)) {
                    $evidence_filename = $new_filename;
                }
            }
        }

        if ($_POST['action'] === 'update_days_overdue') {
            $instalment_id = intval($_POST['instalment_id'] ?? 0);
            $days_overdue = intval($_POST['days_overdue'] ?? 0);

            if ($instalment_id <= 0) {
                $error_message = "Invalid instalment ID";
            }
            else {
                try {
                    $conn->begin_transaction();

                    $update_query = "UPDATE loan_instalments 
                                  SET days_overdue = ?, 
                                      updated_at = NOW() 
                                  WHERE instalment_id = ? AND loan_id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("iii", $days_overdue, $instalment_id, $loan_id);

                    if ($update_stmt->execute()) {
                        $success_message = "Days overdue updated successfully to " . $days_overdue . " days";
                    }
                    else {
                        throw new Exception("Failed to update days overdue");
                    }

                    $update_stmt->close();
                    $conn->commit();

                    // Refresh instalments data
                    $all_instalments_stmt = $conn->prepare($all_instalments_query);
                    $all_instalments_stmt->bind_param("i", $loan_id);
                    $all_instalments_stmt->execute();
                    $all_instalments_result = $all_instalments_stmt->get_result();
                    $existing_instalments = $all_instalments_result->fetch_all(MYSQLI_ASSOC);
                    $all_instalments_stmt->close();

                }
                catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Error: " . $e->getMessage();
                }
            }
        }

        // ═══════════════════════════════════════════════════════════
        // PREPAYMENT PROCESSING
        // Current instalment  → paid in full (principal + interest + mgmt fee)
        // Future instalments  → principal ONLY collected; interest & mgmt fee WAIVED
        // ═══════════════════════════════════════════════════════════
        if ($_POST['action'] === 'process_prepayment') {
            $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
            $payment_method = $_POST['payment_method'] ?? '';
            $payment_reference = $_POST['payment_reference'] ?? '';
            // prepay_total_amount = current_full_balance + sum(future_principals)
            $prepay_amount = floatval($_POST['prepay_total_amount'] ?? 0);
            $current_inst_id = intval($_POST['current_instalment_id'] ?? 0);
            $created_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1;

            $prepay_instalment_ids = json_decode($_POST['prepay_instalment_ids'] ?? '[]', true) ?: [];
            $prepay_principal_amounts = json_decode($_POST['prepay_principal_amounts'] ?? '[]', true) ?: [];
            $prepay_is_current = json_decode($_POST['prepay_is_current'] ?? '[]', true) ?: [];

            if (empty($payment_method)) {
                $error_message = "Please select a payment method";
            }
            elseif ($prepay_amount <= 0) {
                $error_message = "Prepayment amount must be greater than zero";
            }
            elseif (empty($prepay_instalment_ids)) {
                $error_message = "No instalments selected for prepayment";
            }
            else {
                $conn->begin_transaction();
                try {
                    $voucher_number = $loan_info['customer_code'] ?? 'UNKNOWN';
                    $narration = "PREPAYMENT - Loan #" . $loan_info['loan_number'] .
                        " - Reference: " . $payment_reference;
                    $debit_account_code = ($payment_method === 'Cash') ? '1101' : '1102';
                    $debit_account_name = ($payment_method === 'Cash') ? 'Cash on Hand' : 'Bank Account';

                    // ── DEBIT: Cash/Bank – amount actually received from borrower ──
                    $debit_beg = getBeginningBalance($conn, $debit_account_code, $payment_date);
                    createLedgerEntry($conn, [
                        'transaction_date' => $payment_date,
                        'class' => 'Assets',
                        'account_code' => $debit_account_code,
                        'account_name' => $debit_account_name,
                        'particular' => 'Loan Prepayment Received',
                        'voucher_number' => $voucher_number,
                        'narration' => $narration,
                        'beginning_balance' => $debit_beg,
                        'debit_amount' => $prepay_amount,
                        'credit_amount' => 0,
                        'movement' => $prepay_amount,
                        'ending_balance' => $debit_beg + $prepay_amount,
                        'reference_type' => 'loan_prepayment',
                        'reference_id' => $current_inst_id,
                        'created_by' => $created_by
                    ]);

                    // Accumulators for ledger credits
                    $total_principal_credited = 0;
                    $total_interest_credited = 0;
                    $total_mgmt_credited = 0;
                    $total_future_interest_waived = 0;
                    $total_future_mgmt_waived = 0;

                    foreach ($prepay_instalment_ids as $idx => $inst_id) {
                        $inst_id = intval($inst_id);
                        $is_current = !empty($prepay_is_current[$idx]);

                        $row_stmt = $conn->prepare("SELECT * FROM loan_instalments WHERE instalment_id = ?");
                        $row_stmt->bind_param("i", $inst_id);
                        $row_stmt->execute();
                        $inst_row = $row_stmt->get_result()->fetch_assoc();
                        $row_stmt->close();
                        if (!$inst_row)
                            continue;

                        if ($is_current) {
                            // ── Current instalment: collect everything ──
                            $ir = max(0, floatval($inst_row['interest_amount']) - floatval($inst_row['interest_paid']));
                            $mf = max(0, floatval($inst_row['management_fee']) - floatval($inst_row['management_fee_paid']));
                            $p = max(0, floatval($inst_row['balance_remaining']) - $ir - $mf);

                            $principal_paid_now = $p;
                            $interest_paid_now = $ir;
                            $mgmt_paid_now = $mf;
                            $instalment_paid = $p + $ir + $mf;
                            $new_balance = max(0, floatval($inst_row['balance_remaining']) - $instalment_paid);

                            $total_interest_credited += $ir;
                            $total_mgmt_credited += $mf;

                        }
                        else {
                            // ── Future instalments: collect PRINCIPAL ONLY; waive interest & fees ──
                            $principal_paid_now = floatval($prepay_principal_amounts[$idx]);
                            $interest_paid_now = 0;
                            $mgmt_paid_now = 0;
                            $instalment_paid = $principal_paid_now;
                            $new_balance = 0;

                            $waived_interest = max(0, floatval($inst_row['interest_amount']) - floatval($inst_row['interest_paid']));
                            $waived_mgmt = max(0, floatval($inst_row['management_fee']) - floatval($inst_row['management_fee_paid']));
                            $total_future_interest_waived += $waived_interest;
                            $total_future_mgmt_waived += $waived_mgmt;
                        }

                        $new_status = ($new_balance <= 0) ? 'Fully Paid' : (($instalment_paid > 0) ? 'Partially Paid' : $inst_row['status']);

                        $upd = $conn->prepare(
                            "UPDATE loan_instalments 
                             SET paid_amount         = paid_amount + ?,
                                 principal_paid      = principal_paid + ?,
                                 interest_paid       = interest_paid + ?,
                                 management_fee_paid = management_fee_paid + ?,
                                 balance_remaining   = ?,
                                 status              = ?,
                                 payment_date        = ?,
                                 updated_at          = NOW()
                             WHERE instalment_id = ?"
                        );
                        $upd->bind_param("dddddssi",
                            $instalment_paid,
                            $principal_paid_now,
                            $interest_paid_now,
                            $mgmt_paid_now,
                            $new_balance,
                            $new_status,
                            $payment_date,
                            $inst_id
                        );
                        $upd->execute();
                        $upd->close();


                        $total_principal_credited += $principal_paid_now;

                        // --- RECORD IN LOAN_PAYMENTS TABLE ---
                        $month_paid = date('F Y', strtotime($payment_date));
                        $pmt_sql = "INSERT INTO loan_payments (
                                        loan_id, loan_instalment_id, month_paid, payment_date, 
                                        beginning_balance, payment_amount, interest_amount, 
                                        principal_amount, monitoring_fee, penalties, 
                                        payment_method, reference_number, notes, created_at,
                                        payment_evidence
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
                        $pmt_stmt = $conn->prepare($pmt_sql);
                        $pmt_notes = "Prepayment Allocation. IsCurrent: " . ($is_current ? 'Yes' : 'No');
                        $penalty_null = 0;
                        $pmt_stmt->bind_param("iisssdddddssss",
                            $loan_id,
                            $inst_id,
                            $month_paid,
                            $payment_date,
                            $inst_row['opening_balance'],
                            $instalment_paid,
                            $interest_paid_now,
                            $principal_paid_now,
                            $mgmt_paid_now,
                            $penalty_null,
                            $payment_method,
                            $payment_reference,
                            $pmt_notes,
                            $evidence_filename
                        );
                        $pmt_stmt->execute();
                        $pmt_stmt->close();
                    }

                    if ($total_future_interest_waived > 0 || $total_future_mgmt_waived > 0) {
                        $narration .= " | Future interest waived: " . number_format($total_future_interest_waived, 0) .
                            ", Future fees waived: " . number_format($total_future_mgmt_waived, 0);
                    }

                    if ($total_principal_credited > 0) {
                        $p_beg = getBeginningBalance($conn, '1201', $payment_date);
                        createLedgerEntry($conn, [
                            'transaction_date' => $payment_date,
                            'class' => 'Assets',
                            'account_code' => '1201',
                            'account_name' => 'Loans to Customers',
                            'particular' => 'Principal Prepayment',
                            'voucher_number' => $voucher_number,
                            'narration' => $narration,
                            'beginning_balance' => $p_beg,
                            'debit_amount' => 0,
                            'credit_amount' => $total_principal_credited,
                            'movement' => -$total_principal_credited,
                            'ending_balance' => $p_beg - $total_principal_credited,
                            'reference_type' => 'loan_prepayment',
                            'reference_id' => $current_inst_id,
                            'created_by' => $created_by
                        ]);
                    }

                    if ($total_interest_credited > 0) {
                        $i_beg = getBeginningBalance($conn, '4101', $payment_date);
                        createLedgerEntry($conn, [
                            'transaction_date' => $payment_date,
                            'class' => 'Revenue',
                            'account_code' => '4101',
                            'account_name' => 'Interest Income',
                            'particular' => 'Interest Income - Current Instalment Only (Future Waived)',
                            'voucher_number' => $voucher_number,
                            'narration' => $narration,
                            'beginning_balance' => $i_beg,
                            'debit_amount' => 0,
                            'credit_amount' => $total_interest_credited,
                            'movement' => $total_interest_credited,
                            'ending_balance' => $i_beg + $total_interest_credited,
                            'reference_type' => 'loan_prepayment',
                            'reference_id' => $current_inst_id,
                            'created_by' => $created_by
                        ]);
                    }

                    if ($total_mgmt_credited > 0) {
                        $m_beg = getBeginningBalance($conn, '4201', $payment_date);
                        createLedgerEntry($conn, [
                            'transaction_date' => $payment_date,
                            'class' => 'Fee Income',
                            'account_code' => '4201',
                            'account_name' => 'Management Fee Income',
                            'particular' => 'Management Fee - Current Instalment Only (Future Waived)',
                            'voucher_number' => $voucher_number,
                            'narration' => $narration,
                            'beginning_balance' => $m_beg,
                            'debit_amount' => 0,
                            'credit_amount' => $total_mgmt_credited,
                            'movement' => $total_mgmt_credited,
                            'ending_balance' => $m_beg + $total_mgmt_credited,
                            'reference_type' => 'loan_prepayment',
                            'reference_id' => $current_inst_id,
                            'created_by' => $created_by
                        ]);
                    }

                    $check_pending_stmt = $conn->prepare(
                        "SELECT COUNT(*) AS pending_count FROM loan_instalments 
                         WHERE loan_id = ? AND status != 'Fully Paid'"
                    );
                    $check_pending_stmt->bind_param("i", $loan_id);
                    $check_pending_stmt->execute();
                    $pending_result = $check_pending_stmt->get_result()->fetch_assoc();
                    $check_pending_stmt->close();

                    if (intval($pending_result['pending_count']) === 0) {
                        $close_loan_stmt = $conn->prepare(
                            "UPDATE loan_portfolio SET loan_status = 'Closed', updated_at = NOW() WHERE loan_id = ?"
                        );
                        $close_loan_stmt->bind_param("i", $loan_id);
                        $close_loan_stmt->execute();
                        $close_loan_stmt->close();
                    }

                    $conn->commit();

                    $_SESSION['success_message'] = "Prepayment of " . number_format($prepay_amount, 0) .
                        " processed successfully across " . count($prepay_instalment_ids) . " instalment(s)!";
                    if ($total_future_interest_waived > 0 || $total_future_mgmt_waived > 0) {
                        $_SESSION['success_message'] .= " | Future interest waived: " . number_format($total_future_interest_waived, 0) .
                            ", Future fees waived: " . number_format($total_future_mgmt_waived, 0) . ".";
                    }

                    header("Location: " . $_SERVER['PHP_SELF'] . "?page=recordpayment&loan_id=" . $loan_id);
                    exit();

                }
                catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Prepayment failed: " . $e->getMessage();
                    error_log("Prepayment error: " . $e->getMessage());
                }
            }
        }

        // ═══════════════════════════════════════════════════════════
        // REGULAR PAYMENT PROCESSING
        // ═══════════════════════════════════════════════════════════
        if ($_POST['action'] === 'process_payment') {
            $instalment_id = intval($_POST['instalment_id'] ?? 0);
            $instalment_number = intval($_POST['instalment_number'] ?? 0);
            $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
            $payment_method = $_POST['payment_method'] ?? '';
            $principal_amount = floatval($_POST['principal_amount'] ?? 0);
            $interest_amount = floatval($_POST['interest_amount'] ?? 0);
            $management_fee = floatval($_POST['management_fee'] ?? 0);

            $days_overdue = intval($_POST['days_overdue'] ?? 0);
            $penalties = floatval($_POST['penalties'] ?? 0);
            $penalty_reduction = floatval($_POST['penalty_reduction_amount'] ?? 0);
            $actual_payment_amount = floatval($_POST['actual_payment_amount'] ?? 0);
            $payment_reference = $_POST['payment_reference'] ?? '';
            $notes = $_POST['notes'] ?? '';

            $adjusted_penalties = max(0, $penalties - $penalty_reduction);
            $created_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1;

            if ($instalment_id <= 0) {
                $error_message = "Invalid instalment";
            }
            elseif (empty($payment_method)) {
                $error_message = "Please select a payment method";
            }
            elseif ($actual_payment_amount <= 0) {
                $error_message = "Payment amount must be greater than zero";
            }
            else {
                $conn->begin_transaction();

                try {
                    $get_instalment_query = "SELECT opening_balance, balance_remaining, total_payment, instalment_number
                                            FROM loan_instalments WHERE instalment_id = ?";
                    $get_instalment_stmt = $conn->prepare($get_instalment_query);
                    $get_instalment_stmt->bind_param("i", $instalment_id);
                    $get_instalment_stmt->execute();
                    $instalment_result = $get_instalment_stmt->get_result();
                    $current_instalment = $instalment_result->fetch_assoc();
                    $get_instalment_stmt->close();

                    if (!$current_instalment) {
                        throw new Exception("Instalment not found");
                    }

                    $current_balance = floatval($current_instalment['balance_remaining']);
                    $current_opening = floatval($current_instalment['opening_balance']);
                    $total_payment_due = floatval($current_instalment['total_payment']);
                    $current_inst_num = intval($current_instalment['instalment_number']);

                    $update_days_query = "UPDATE loan_instalments 
                                        SET days_overdue = ?,
                                            updated_at = NOW()
                                        WHERE instalment_id = ?";
                    $update_days_stmt = $conn->prepare($update_days_query);
                    $update_days_stmt->bind_param("ii", $days_overdue, $instalment_id);
                    $update_days_stmt->execute();
                    $update_days_stmt->close();

                    $customer_code = $loan_info['customer_code'] ?? 'UNKNOWN';
                    $voucher_number = $customer_code;
                    $narration = "Loan payment - Instalment #" . $instalment_number .
                        " - Loan #" . $loan_info['loan_number'] .
                        " - Reference: " . $payment_reference;

                    $debit_account_code = ($payment_method === 'Cash') ? '1101' : '1102';
                    $debit_account_name = ($payment_method === 'Cash') ? 'Cash on Hand' : 'Bank Account';

                    $debit_beginning = getBeginningBalance($conn, $debit_account_code, $payment_date);
                    createLedgerEntry($conn, [
                        'transaction_date' => $payment_date,
                        'class' => 'Assets',
                        'account_code' => $debit_account_code,
                        'account_name' => $debit_account_name,
                        'particular' => 'Loan Payment Received',
                        'voucher_number' => $voucher_number,
                        'narration' => $narration,
                        'beginning_balance' => $debit_beginning,
                        'debit_amount' => $actual_payment_amount,
                        'credit_amount' => 0,
                        'movement' => $actual_payment_amount,
                        'ending_balance' => $debit_beginning + $actual_payment_amount,
                        'reference_type' => 'loan_payment',
                        'reference_id' => $instalment_id,
                        'created_by' => $created_by
                    ]);

                    $remaining_to_allocate = $actual_payment_amount;

                    $penalty_paid = min($adjusted_penalties, $remaining_to_allocate);
                    $remaining_to_allocate -= $penalty_paid;

                    if ($penalty_paid > 0) {
                        $penalty_beg = getBeginningBalance($conn, '4205', $payment_date);
                        createLedgerEntry($conn, [
                            'transaction_date' => $payment_date,
                            'class' => 'Revenue',
                            'account_code' => '4205',
                            'account_name' => 'Other Operating Income',
                            'particular' => 'Penalty for Late Payment',
                            'voucher_number' => $voucher_number,
                            'narration' => $narration,
                            'beginning_balance' => $penalty_beg,
                            'debit_amount' => 0,
                            'credit_amount' => $penalty_paid,
                            'movement' => $penalty_paid,
                            'ending_balance' => $penalty_beg + $penalty_paid,
                            'reference_type' => 'loan_payment',
                            'reference_id' => $instalment_id,
                            'created_by' => $created_by
                        ]);
                    }

                    $interest_paid = min($interest_amount, $remaining_to_allocate);
                    $remaining_to_allocate -= $interest_paid;

                    $mgmt_fee_paid = min($management_fee, $remaining_to_allocate);
                    $remaining_to_allocate -= $mgmt_fee_paid;

                    $principal_paid = min($principal_amount, $remaining_to_allocate);
                    if ($remaining_to_allocate > $principal_amount) {
                        $principal_paid = $remaining_to_allocate;
                    }

                    if ($principal_paid > 0) {
                        $principal_beg = getBeginningBalance($conn, '1201', $payment_date);
                        createLedgerEntry($conn, [
                            'transaction_date' => $payment_date,
                            'class' => 'Assets',
                            'account_code' => '1201',
                            'account_name' => 'Loans to Customers',
                            'particular' => 'Principal Repayment',
                            'voucher_number' => $voucher_number,
                            'narration' => $narration,
                            'beginning_balance' => $principal_beg,
                            'debit_amount' => 0,
                            'credit_amount' => $principal_paid,
                            'movement' => -$principal_paid,
                            'ending_balance' => $principal_beg - $principal_paid,
                            'reference_type' => 'loan_payment',
                            'reference_id' => $instalment_id,
                            'created_by' => $created_by
                        ]);
                    }

                    if ($interest_paid > 0) {
                        $interest_beg = getBeginningBalance($conn, '4101', $payment_date);
                        createLedgerEntry($conn, [
                            'transaction_date' => $payment_date,
                            'class' => 'Revenue',
                            'account_code' => '4101',
                            'account_name' => 'Interest on Loans',
                            'particular' => 'Interest Income',
                            'voucher_number' => $voucher_number,
                            'narration' => $narration,
                            'beginning_balance' => $interest_beg,
                            'debit_amount' => 0,
                            'credit_amount' => $interest_paid,
                            'movement' => $interest_paid,
                            'ending_balance' => $interest_beg + $interest_paid,
                            'reference_type' => 'loan_payment',
                            'reference_id' => $instalment_id,
                            'created_by' => $created_by
                        ]);
                    }

                    if ($mgmt_fee_paid > 0) {
                        $mgmt_beg = getBeginningBalance($conn, '4201', $payment_date);
                        createLedgerEntry($conn, [
                            'transaction_date' => $payment_date,
                            'class' => 'Fee Income',
                            'account_code' => '4201',
                            'account_name' => 'Disbursement Fee Income',
                            'particular' => 'Management Fee',
                            'voucher_number' => $voucher_number,
                            'narration' => $narration,
                            'beginning_balance' => $mgmt_beg,
                            'debit_amount' => 0,
                            'credit_amount' => $mgmt_fee_paid,
                            'movement' => $mgmt_fee_paid,
                            'ending_balance' => $mgmt_beg + $mgmt_fee_paid,
                            'reference_type' => 'loan_payment',
                            'reference_id' => $instalment_id,
                            'created_by' => $created_by
                        ]);
                    }


                    // --- RECORD IN LOAN_PAYMENTS TABLE (Required for History & Deletion) ---
                    $month_paid = date('F Y', strtotime($payment_date));
                    $pmt_sql = "INSERT INTO loan_payments (
                                    loan_id, loan_instalment_id, month_paid, payment_date, 
                                    beginning_balance, payment_amount, interest_amount, 
                                    principal_amount, monitoring_fee, penalties, 
                                    payment_method, reference_number, notes, created_at,
                                    payment_evidence
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
                    $pmt_stmt = $conn->prepare($pmt_sql);
                    $pmt_notes = "Recorded via Record Payment module. Narration: " . $narration;
                    $pmt_stmt->bind_param("iisssdddddssss",
                        $loan_id,
                        $instalment_id,
                        $month_paid,
                        $payment_date,
                        $current_balance,
                        $actual_payment_amount,
                        $interest_paid,
                        $principal_paid,
                        $mgmt_fee_paid,
                        $penalty_paid,
                        $payment_method,
                        $payment_reference,
                        $pmt_notes,
                        $evidence_filename
                    );
                    $pmt_stmt->execute();
                    $pmt_stmt->close();

                    $total_paid = $actual_payment_amount - $penalty_paid;
                    $new_balance_remaining = max(0, $current_balance - $total_paid);

                    if ($new_balance_remaining <= 0) {
                        $new_status = 'Fully Paid';
                    }
                    elseif ($new_balance_remaining < $total_payment_due) {
                        $new_status = 'Partially Paid';
                    }
                    else {
                        $new_status = 'Pending';
                    }

                    // Calculate the ACTUAL closing balance after the principal payment
                    $actual_closing_balance_for_row = max(0, $current_opening - $principal_paid);

                    $update_instalment_query = "UPDATE loan_instalments 
                                            SET paid_amount          = paid_amount + ?,
                                                principal_paid       = principal_paid + ?,
                                                interest_paid        = interest_paid + ?,
                                                management_fee_paid  = management_fee_paid + ?,
                                                balance_remaining    = ?,
                                                closing_balance      = ?,
                                                penalty_paid         = penalty_paid + ?,
                                                status               = ?,
                                                days_overdue         = ?,
                                                payment_date         = ?,
                                                updated_at           = NOW()
                                            WHERE instalment_id = ?";
                    $update_stmt = $conn->prepare($update_instalment_query);
                    $update_stmt->bind_param("dddddddsisi",
                        $total_paid,
                        $principal_paid,
                        $interest_paid,
                        $mgmt_fee_paid,
                        $new_balance_remaining,
                        $actual_closing_balance_for_row,
                        $penalty_paid,
                        $new_status,
                        $days_overdue,
                        $payment_date,
                        $instalment_id
                    );
                    $update_stmt->execute();
                    $update_stmt->close();

                    // --- LOAN PORTFOLIO SYNC ---
                    // Automatically update summary totals in loan_portfolio
                    $portfolio_sync = "UPDATE loan_portfolio SET 
                                        total_paid = total_paid + ?,
                                        total_principal_paid = total_principal_paid + ?,
                                        total_interest_paid = total_interest_paid + ?,
                                        total_management_fees_paid = total_management_fees_paid + ?,
                                        principal_outstanding = principal_outstanding - ?,
                                        interest_outstanding = interest_outstanding - ?,
                                        total_outstanding = principal_outstanding + interest_outstanding,
                                        updated_at = NOW()
                                      WHERE loan_id = ?";
                    $port_stmt = $conn->prepare($portfolio_sync);
                    $port_stmt->bind_param("ddddddi",
                        $actual_payment_amount,
                        $principal_paid,
                        $interest_paid,
                        $mgmt_fee_paid,
                        $principal_paid,
                        $interest_paid,
                        $loan_id
                    );
                    $port_stmt->execute();
                    $port_stmt->close();

                    // --- EARLY REPAYMENT / SCHEDULE RECALCULATION ---

                    // Trigger recalculation for the rest of the schedule
                    if ($total_paid > $current_balance + 1) {
                        recalculateRemainingSchedule($conn, $loan_id, $current_inst_num, $actual_closing_balance_for_row, $interest_rate, $mgmt_fee_rate);
                    } else {
                        syncLoanPortfolio($conn, $loan_id);
                    }

                    $check_pending_stmt = $conn->prepare(
                        "SELECT COUNT(*) AS pending_count FROM loan_instalments 
                         WHERE loan_id = ? AND status != 'Fully Paid'"
                    );
                    $check_pending_stmt->bind_param("i", $loan_id);
                    $check_pending_stmt->execute();
                    $pending_result = $check_pending_stmt->get_result()->fetch_assoc();
                    $check_pending_stmt->close();

                    if (intval($pending_result['pending_count']) === 0) {
                        $close_loan_stmt = $conn->prepare(
                            "UPDATE loan_portfolio SET loan_status = 'Closed', updated_at = NOW() WHERE loan_id = ?"
                        );
                        $close_loan_stmt->bind_param("i", $loan_id);
                        $close_loan_stmt->execute();
                        $close_loan_stmt->close();
                    }

                    $conn->commit();

                    $_SESSION['success_message'] = "Payment recorded successfully! Amount: " . number_format($actual_payment_amount, 0);
                    header("Location: " . $_SERVER['PHP_SELF'] . "?page=recordpayment&loan_id=" . $loan_id);
                    exit();

                }
                catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Transaction failed: " . $e->getMessage();
                    error_log("Payment processing error: " . $e->getMessage());
                }
            }
        }
    }

    $conn->close();

}
catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    error_log("record_payment.php critical error: " . $e->getMessage());
}

// Build prepayment data for JS (pending instalments only)
$pending_instalments_for_prepay = [];
foreach ($existing_instalments as $inst) {
    if ($inst['status'] !== 'Fully Paid') {
        $is_first_pending = (count($pending_instalments_for_prepay) === 0);
        $principal_due = floatval($inst['principal_amount']) - floatval($inst['principal_paid']);
        $interest_due = floatval($inst['interest_amount']) - floatval($inst['interest_paid']);
        $fee_due = floatval($inst['management_fee']) - floatval($inst['management_fee_paid']);
        $full_balance = floatval($inst['balance_remaining']);
        $amount_due = $is_first_pending ? $full_balance : $principal_due;

        $pending_instalments_for_prepay[] = [
            'id' => $inst['instalment_id'],
            'number' => $inst['instalment_number'],
            'due_date' => $inst['due_date'],
            'principal' => floatval($inst['principal_amount']),
            'principal_due' => $principal_due,
            'interest' => floatval($inst['interest_amount']),
            'interest_due' => $interest_due,
            'management_fee' => floatval($inst['management_fee']),
            'fee_due' => $fee_due,
            'total_payment' => floatval($inst['total_payment']),
            'balance' => $full_balance,
            'paid' => floatval($inst['paid_amount']),
            'status' => $inst['status'],
            'amount_due' => $amount_due,
            'is_first_pending' => $is_first_pending,
        ];
    }
}
$prepay_json = json_encode($pending_instalments_for_prepay);

// ── Format rate labels for display ──
// e.g. "5%" or "5.5%" — strips trailing zeros after decimal
function formatRateLabel(float $rate): string
{
    return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%';
}
$interest_rate_label = formatRateLabel($interest_rate_pct);
$mgmt_fee_rate_label = formatRateLabel($mgmt_fee_rate_pct);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment - Loan #<?php echo htmlspecialchars($loan_info['loan_number'] ?? 'N/A'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .card { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .payment-summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        .status-fully-paid    { background-color: #d4edda !important; }
        .status-partially-paid{ background-color: #fff3cd !important; }
        .status-pending       { background-color: #ffffff !important; }
        tbody tr:hover { opacity: 0.9; }
        .clickable-row { cursor: pointer; }

        .prepay-card { border: 2px solid #6f42c1; border-radius: 10px; }
        .prepay-card .card-header {
            background: linear-gradient(135deg, #6f42c1 0%, #4a2aab 100%);
            border-radius: 8px 8px 0 0;
        }
        .prepay-row.selected-current { background-color: #e8e4f8 !important; font-weight: 600; }
        .prepay-row.selected-future  { background-color: #e8f4fd !important; }
        .prepay-amount-big { font-size: 2rem; font-weight: 700; color: #6f42c1; }

        .print-header { display: none; }
        @media print {
            .no-print { display: none !important; }
            .clickable-row { cursor: default !important; }
            .card { box-shadow: none; border: 1px solid #ddd; page-break-inside: avoid; }
            body { margin: 0; padding: 15px; }
            .print-header {
                display: flex !important;
                align-items: center;
                border-bottom: 3px solid #2c3e50;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            .print-logo { width: 100px; height: 100px; object-fit: contain; margin-right: 20px; }
            .print-company-info h1 { color: #2c3e50; font-size: 24px; margin: 0; font-weight: bold; }
            .print-company-info p  { margin: 2px 0; color: #555; font-size: 12px; }
            .print-title { text-align: center; margin: 20px 0; padding: 10px;
                           background-color: #f8f9fa; border: 1px solid #dee2e6; }
            .print-title h2 { margin: 0; color: #2c3e50; font-size: 20px; }
            table { font-size: 11px; }
            .table thead th {
                background-color: #2c3e50 !important; color: white !important;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            .status-fully-paid {
                background-color: #d4edda !important;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            .status-partially-paid {
                background-color: #fff3cd !important;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            @page { margin: 15mm; }
        }
    </style>
</head>
<body>

<!-- ── Pass PHP rates to JavaScript ── -->
<script>
    const LOAN_INTEREST_RATE_PCT = <?php echo json_encode($interest_rate_pct); ?>;
    const LOAN_MGMT_FEE_RATE_PCT = <?php echo json_encode($mgmt_fee_rate_pct); ?>;
    const LOAN_INTEREST_RATE     = <?php echo json_encode($interest_rate); ?>;
    const LOAN_MGMT_FEE_RATE     = <?php echo json_encode($mgmt_fee_rate); ?>;

    // *** CHANGED: Penalty rate is fixed at 5% monthly (separate from management fee rate) ***
    const LOAN_PENALTY_RATE_PCT  = 5;
    const LOAN_PENALTY_RATE      = LOAN_PENALTY_RATE_PCT / 100;
</script>

<!-- Print Header -->
<div class="print-header">
    <img src="assets/images/logo.png" alt="Company Logo" class="print-logo"
         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect width=%22100%22 height=%22100%22 fill=%22%232c3e50%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%2220%22 fill=%22white%22 text-anchor=%22middle%22 dy=%22.3em%22%3ECBF%3C/text%3E%3C/svg%3E'">
    <div class="print-company-info">
        <h1>CAPITAL BRIDGE FINANCE</h1>
        <p><i class="fas fa-map-marker-alt"></i> Kigali, Rwanda</p>
        <p><i class="fas fa-phone"></i> +250 796 880 272 | <i class="fas fa-envelope"></i> info@cbfinance.rw</p>
        <p><i class="fas fa-globe"></i> www.cbfinance.rw</p>
    </div>
</div>

<?php if ($error_message): ?>
<div style="background:#f8d7da;color:#721c24;padding:15px 20px;font-family:monospace;border-left:5px solid #f5365c;margin:10px;">
    <strong>⚠ Error:</strong> <?php echo htmlspecialchars($error_message); ?>
</div>
<?php
endif; ?>

<div class="print-title">
    <h2>LOAN REPAYMENT SCHEDULE</h2>
    <p style="margin:5px 0 0 0;font-size:14px;">Generated on: <?php echo date('d/m/Y h:i A'); ?></p>
</div>

<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="row mb-4 no-print">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h4 fw-bold text-primary">Record Payment</h2>
                    <p class="text-muted mb-0">
                        Loan #<?php echo htmlspecialchars($loan_info['loan_number'] ?? 'N/A'); ?> &mdash;
                        <?php echo htmlspecialchars($loan_info['customer_name'] ?? 'N/A'); ?>
                    </p>
                </div>
                <div>
                    <button onclick="window.print()" class="btn btn-info me-2">
                        <i class="fas fa-print me-2"></i>Print Schedule
                    </button>
                    <button onclick="generatePDF()" class="btn btn-success me-2">
                        <i class="fas fa-file-pdf me-2"></i>Download PDF
                    </button>
                    <a href="index.php?page=loans" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Loans
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show no-print">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php
endif; ?>
    <?php 
    $disp_success = $success_message;
    if (isset($_SESSION['success_message'])) {
        $disp_success = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
    }
    if ($disp_success): ?>
    <div class="alert alert-success alert-dismissible fade show no-print">
        <i class="fas fa-check-circle me-2"></i><?php echo $disp_success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php
endif; ?>

    <!-- ═══════════════════════════════════════════════════════════
         LOAN SUMMARY CARDS  — rates are now fully dynamic
    ════════════════════════════════════════════════════════════ -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <small class="text-muted">Borrower</small>
                <h6 class="mb-0"><?php echo htmlspecialchars($loan_info['customer_name'] ?? 'N/A'); ?></h6>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="card"><div class="card-body">
                <small class="text-muted">Management Fee</small>
                <!-- ✅ DYNAMIC: read from loan_portfolio.management_fee_rate -->
                <h6 class="mb-0"><?php echo htmlspecialchars($mgmt_fee_rate_label); ?></h6>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="card"><div class="card-body">
                <small class="text-muted">Interest Rate</small>
                <!-- ✅ DYNAMIC: read from loan_portfolio.interest_rate -->
                <h6 class="mb-0"><?php echo htmlspecialchars($interest_rate_label); ?></h6>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="card"><div class="card-body">
                <small class="text-muted">Term (months)</small>
                <h6 class="mb-0"><?php echo $loan_info['number_of_instalments'] ?? 'N/A'; ?></h6>
            </div></div>
        </div>
        <div class="col-md-1">
            <div class="card"><div class="card-body">
                <small class="text-muted">Start Date</small>
                <h6 class="mb-0"><?php echo isset($loan_info['disbursement_date']) ? date('Y-m-d', strtotime($loan_info['disbursement_date'])) : 'N/A'; ?></h6>
            </div></div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         PREPAYMENT CARD
    ════════════════════════════════════════════════════════════ -->
    <?php if (!empty($pending_instalments_for_prepay)): ?>
    <div class="row mb-4 no-print">
        <div class="col-12">
            <div class="card prepay-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-white">
                        <i class="fas fa-forward me-2"></i>Prepayment
                        <span class="badge ms-2" style="background:rgba(255,255,255,.25);font-size:.7rem;">Early Settlement</span>
                    </h5>
                    <button class="btn btn-sm btn-light fw-bold" type="button"
                            data-bs-toggle="collapse" data-bs-target="#prepaySection">
                        <i class="fas fa-chevron-down me-1"></i>Expand / Collapse
                    </button>
                </div>
                <div class="card-body pb-1">
                    <p class="text-muted small mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        The <strong>first (current) instalment</strong> is paid in full
                        (principal + interest + management fee). All subsequent selected instalments:
                        the borrower pays <strong>principal only</strong> &mdash; interest and management fee
                        are <strong>waived and cleared</strong>, marking those instalments as Fully Paid.
                        The <em>Amount to Pay</em> column already reflects this.
                    </p>
                </div>
                <div class="collapse" id="prepaySection">
                    <div class="card-body pt-2">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm mb-1" id="prepayTable">
                                        <thead style="background:#6f42c1;color:#fff;">
                                            <tr>
                                                <th style="width:38px;" class="text-center">
                                                    <input type="checkbox" id="prepaySelectAll" title="Select/Deselect all">
                                                </th>
                                                <th class="text-center">#</th>
                                                <th class="text-center">Due Date</th>
                                                <th class="text-end">Outstanding</th>
                                                <th class="text-end">Principal Due</th>
                                                <th class="text-end">Interest</th>
                                                <th class="text-end">Mgmt Fee</th>
                                                <th class="text-center">Type</th>
                                                <th class="text-end">Amount to Pay</th>
                                            </tr>
                                        </thead>
                                        <tbody id="prepayTbody"></tbody>
                                        <tfoot>
                                            <tr class="table-secondary fw-bold">
                                                <td colspan="8" class="text-end">
                                                    Total to Collect (interest &amp; fees on future instalments waived):
                                                </td>
                                                <td class="text-end" id="prepayFooterTotal" style="color:#6f42c1;">0</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <small class="text-muted">
                                    <span class="badge" style="background:#e8e4f8;color:#4a2aab;">■</span> Current instalment (full payment) &nbsp;
                                    <span class="badge" style="background:#e8f4fd;color:#0c5460;">■</span> Future instalments
                                    (principal only &mdash; interest &amp; fee <strong>waived</strong>)
                                </small>
                            </div>
                            <div class="col-lg-4">
                                <div class="text-center p-3 mb-3 rounded" style="border:2px solid #6f42c1;background:#faf8ff;">
                                    <div class="text-muted small mb-1">Total Prepayment Amount</div>
                                    <div class="prepay-amount-big" id="prepayTotalDisplay">0</div>
                                    <div class="text-muted small" id="prepayBreakdownText"></div>
                                </div>
                                <form method="POST" id="prepayForm" enctype="multipart/form-data">
                                    <input type="hidden" name="action"                  value="process_prepayment">
                                    <input type="hidden" name="prepay_total_amount"     id="prepayTotalHidden">
                                    <input type="hidden" name="current_instalment_id"   id="prepayCurrentInstId">
                                    <input type="hidden" name="prepay_instalment_ids"   id="prepayIdsHidden">
                                    <input type="hidden" name="prepay_principal_amounts" id="prepayPrincipalsHidden">
                                    <input type="hidden" name="prepay_is_current"       id="prepayIsCurrentHidden">

                                    <div class="mb-2">
                                        <label class="form-label fw-semibold">Transaction Date *</label>
                                        <input type="date" class="form-control form-control-sm"
                                               name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label fw-semibold">Payment Method *</label>
                                        <select class="form-select form-select-sm" name="payment_method" required>
                                            <option value="">Select Method</option>
                                            <option value="Cash">Cash</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                            <option value="Mobile Money">Mobile Money</option>
                                            <option value="Cheque">Cheque</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold" style="color:#6f42c1;">Payment Evidence (Photo)</label>
                                        <input type="file" class="form-control form-control-sm border-primary" 
                                               name="payment_evidence" accept="image/*">
                                        <small class="text-muted">Take a photo of the receipt/slip (PNG, JPG) - Optional</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Note / Remarks (Optional)</label>
                                        <input type="text" class="form-control form-control-sm"
                                               name="payment_reference" placeholder="Anything else to note?">
                                    </div>
                                    <button type="submit" id="prepaySubmitBtn" disabled
                                            class="btn w-100 fw-bold"
                                            style="background:#6f42c1;border-color:#6f42c1;color:#fff;">
                                        <i class="fas fa-bolt me-2"></i>Confirm Prepayment
                                    </button>
                                    <div class="text-muted small text-center mt-1" id="prepaySubmitHint">
                                        Select at least one instalment above
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
endif; ?>

    <!-- Repayment Schedule Table -->
    <?php if (!empty($existing_instalments)): ?>
    <div class="row" id="payment-schedule">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white no-print">
                    <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Loan Repayment Schedule</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:50px;">#</th>
                                    <th class="text-center">Payment Date</th>
                                    <th class="text-end">Opening Balance</th>
                                    <th class="text-end">Principal</th>
                                    <th class="text-end">Interest</th>
                                    <th class="text-end">Management Fee</th>
                                    <th class="text-end">Total Payment</th>
                                    <th class="text-end">Closing Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($existing_instalments as $inst):
        $inst_id = $inst['instalment_id'];
        $inst_num = $inst['instalment_number'];
        $due_date = $inst['due_date'];
        $opening_balance = floatval($inst['opening_balance']);
        $principal = floatval($inst['principal_amount']);
        $interest = floatval($inst['interest_amount']);
        $mgmt_fee = floatval($inst['management_fee']);
        $total_payment = floatval($inst['total_payment']);
        $closing_balance = floatval($inst['closing_balance']);
        $paid_amount = floatval($inst['paid_amount']);
        $balance = floatval($inst['balance_remaining']);
        $status = $inst['status'];
        $days_overdue = intval($inst['days_overdue']);
        // *** ADDED: read penalty_paid from instalment row ***
        $penalty_paid_amt = floatval($inst['penalty_paid']);
        // *** CHANGED: penalty now uses fixed 5% rate instead of $mgmt_fee_rate (5.5%) ***
        $penalties = $days_overdue > 0 ? (($balance * 0.05) / 30) * $days_overdue : 0;
        $final_payment = $balance + $penalties;

        if ($status === 'Fully Paid')
            $row_class = 'status-fully-paid';
        elseif ($status === 'Partially Paid')
            $row_class = 'status-partially-paid';
        else
            $row_class = 'status-pending';
?>
                                <tr class="clickable-row <?php echo $row_class; ?>"
                                    onclick="openCheckoutModal(this)"
                                    data-instalment-id="<?php echo $inst_id; ?>"
                                    data-instalment-number="<?php echo $inst_num; ?>"
                                    data-due-date="<?php echo $due_date; ?>"
                                    data-opening-balance="<?php echo $opening_balance; ?>"
                                    data-principal="<?php echo $principal; ?>"
                                    data-interest="<?php echo $interest; ?>"
                                    data-management-fee="<?php echo $mgmt_fee; ?>"
                                    data-total-payment="<?php echo $total_payment; ?>"
                                    data-closing-balance="<?php echo $closing_balance; ?>"
                                    data-balance="<?php echo $balance; ?>"
                                    data-paid-amount="<?php echo $paid_amount; ?>"
                                    data-days-overdue="<?php echo $days_overdue; ?>"
                                    data-penalties="<?php echo $penalties; ?>"
                                    data-final-payment="<?php echo $final_payment; ?>"
                                    data-penalty-paid="<?php echo $penalty_paid_amt; ?>"
                                    data-status="<?php echo $status; ?>">
                                    <td class="text-center"><?php echo $inst_num; ?></td>
                                    <td class="text-center"><?php echo date('d/m/Y', strtotime($due_date)); ?></td>
                                    <td class="text-end"><?php echo number_format($opening_balance, 0); ?></td>
                                    <td class="text-end"><?php echo number_format($principal, 0); ?></td>
                                    <td class="text-end"><?php echo number_format($interest, 0); ?></td>
                                    <td class="text-end"><?php echo number_format($mgmt_fee, 0); ?></td>
                                    <td class="text-end"><?php echo number_format($total_payment, 0); ?></td>
                                    <td class="text-end"><?php echo number_format($closing_balance, 0); ?></td>
                                </tr>
                                <?php
    endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="2" class="text-end">TOTALS:</th>
                                    <th class="text-end"><?php echo number_format($loan_info['total_disbursed'] ?? $loan_info['loan_amount'] ?? 0, 0); ?></th>
                                    <th class="text-end"><?php echo number_format(array_sum(array_column($existing_instalments, 'principal_amount')), 0); ?></th>
                                    <th class="text-end"><?php echo number_format(array_sum(array_column($existing_instalments, 'interest_amount')), 0); ?></th>
                                    <th class="text-end"><?php echo number_format(array_sum(array_column($existing_instalments, 'management_fee')), 0); ?></th>
                                    <th class="text-end"><?php echo number_format(array_sum(array_column($existing_instalments, 'total_payment')), 0); ?></th>
                                    <th class="text-end">-</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="card-footer bg-light no-print">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Click on any row to record a payment for that installment
                                </small>
                                <div class="mt-2">
                                    <small class="text-muted d-block">
                                        <span class="badge" style="background-color:#d4edda;color:#155724;">■</span> Fully Paid
                                        <span class="badge ms-2" style="background-color:#fff3cd;color:#856404;">■</span> Partially Paid
                                        <span class="badge ms-2" style="background-color:#ffffff;color:#000;border:1px solid #dee2e6;">■</span> Pending
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <small class="text-muted">
                                    <strong>Payment Status:</strong>
                                    <?php
    $total_instalments = count($existing_instalments);
    $paid_instalments = count(array_filter($existing_instalments, function ($i) {
        return $i['status'] === 'Fully Paid'; }));
    $pending_count = $total_instalments - $paid_instalments;
?>
                                    <span class="badge bg-success"><?php echo $paid_instalments; ?> Paid</span>
                                    <span class="badge bg-warning"><?php echo $pending_count; ?> Pending</span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
endif; ?>
</div>


<!-- ═══════════════ CHECKOUT MODAL ═══════════════ -->
<div class="modal fade" id="checkoutModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-credit-card me-2"></i>Process Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="checkoutForm" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action"            value="process_payment">
                    <input type="hidden" id="instalment_id"       name="instalment_id">
                    <input type="hidden" id="instalment_number"   name="instalment_number">
                    <input type="hidden" id="principal_amount"    name="principal_amount">
                    <input type="hidden" id="interest_amount"     name="interest_amount">
                    <input type="hidden" id="management_fee"      name="management_fee">
                    
                    <div class="row">
                        <div class="col-md-6 border-end">
                            <h6 class="text-primary mb-3">Installment Details</h6>
                            <div class="payment-summary-item">
                                <span>Installment #:</span>
                                <span id="summary_instalment" class="fw-bold">-</span>
                            </div>
                            <div class="payment-summary-item">
                                <span>Payment Date:</span>
                                <span id="summary_due_date">-</span>
                            </div>
                            <div class="payment-summary-item">
                                <span>Opening Balance:</span>
                                <span id="summary_opening" class="fw-bold">0</span>
                            </div>
                            <hr>
                            <h6 class="text-info mb-3">Payment Breakdown</h6>
                            <div class="payment-summary-item">
                                <span>Principal:</span>
                                <span id="summary_principal">0</span>
                            </div>
                            <div class="payment-summary-item">
                                <!-- ✅ DYNAMIC label rendered from PHP -->
                                <span>Interest (<?php echo htmlspecialchars($interest_rate_label); ?>):</span>
                                <span id="summary_interest">0</span>
                            </div>
                            <div class="payment-summary-item">
                                <!-- ✅ DYNAMIC label rendered from PHP -->
                                <span>Management Fee (<?php echo htmlspecialchars($mgmt_fee_rate_label); ?>):</span>
                                <span id="summary_management">0</span>
                            </div>
                            <div class="payment-summary-item" style="border-top:2px solid #000;background:#e7f3ff;">
                                <strong>Total Due:</strong>
                                <strong id="summary_total">0</strong>
                            </div>
                            <div class="payment-summary-item">
                                <span>Closing Balance:</span>
                                <span id="summary_closing" class="text-info">0</span>
                            </div>
                            <hr>
                            <div class="alert alert-info">
                                <div class="payment-summary-item mb-0">
                                    <span>Already Paid:</span>
                                    <span id="summary_paid" class="text-success fw-bold">0</span>
                                </div>
                                <div class="payment-summary-item mb-0">
                                    <span>Penalties Paid:</span>
                                    <span id="summary_penalty_paid" class="text-warning fw-bold">0</span>
                                </div>
                                <div class="payment-summary-item mb-0">
                                    <span>Outstanding Balance:</span>
                                    <span id="summary_balance" class="text-danger fw-bold">0</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">Payment Information</h6>
                            <div class="mb-3">
                                <label>Transaction Date *</label>
                                <input type="date" class="form-control" id="modal_payment_date"
                                       name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label>Payment Method *</label>
                                <select class="form-select" name="payment_method" required>
                                    <option value="">Select Method</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Mobile Money">Mobile Money</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                            <hr>
                            <h6 class="text-warning mb-3">Overdue &amp; Penalties</h6>
                            <div class="mb-3">
                                <label>Days Overdue *</label>
                                <input type="number" class="form-control" id="days_overdue" name="days_overdue"
                                       min="0" value="0" onchange="updatePenalties()">
                                <small class="text-muted">Auto-calculated from transaction date vs. due date. Editable if needed.</small>
                            </div>
                            <div class="mb-3">
                                <!-- *** CHANGED: label now shows fixed 5% penalty rate instead of mgmt fee rate *** -->
                                <label>Calculated Penalties (5% monthly prorated)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="penalties_display" readonly>
                                    <input type="hidden" id="penalties" name="penalties">
                                </div>
                                <!-- *** CHANGED: formula helper text now shows 5% penalty rate *** -->
                                <small class="text-muted">
                                    [(Balance × 5%) / 30] × Days Overdue
                                </small>
                            </div>
                            <div class="mb-3">
                                <label>Penalty Reduction (Waiver)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="penalty_reduction_display"
                                           oninput="handlePenaltyReductionInput(this)" value="0">
                                    <input type="hidden" id="penalty_reduction_amount" name="penalty_reduction_amount" value="0">
                                </div>
                                <small class="text-muted">Amount to waive from penalties</small>
                            </div>
                            <div class="alert alert-success">
                                <label class="mb-2"><strong>Final Amount to Pay:</strong></label>
                                <div class="h4 mb-0 text-success" id="summary_final">0</div>
                                <small class="text-muted">Outstanding + Adjusted Penalties</small>
                            </div>
                            <div class="mb-3">
                                <label>Actual Payment Amount *</label>
                                <div class="input-group input-group-lg">
                                    <input type="text" class="form-control" id="actual_payment_display"
                                           oninput="handlePaymentInput(this)" required>
                                    <input type="hidden" id="actual_payment_amount" name="actual_payment_amount" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold text-primary">Payment Evidence (Photo)</label>
                                <div class="p-2 border rounded bg-light">
                                    <input type="file" class="form-control" name="payment_evidence" 
                                           accept="image/*">
                                    <small class="text-muted mt-1 d-block"><i class="fas fa-camera me-1"></i>Please upload a photo of the payment receipt. (Optional)</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>Reference / Note (Optional)</label>
                                <input type="text" class="form-control" name="payment_reference"
                                       placeholder="Any extra info...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-circle me-2"></i>Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Days Overdue Modal -->
<div class="modal fade" id="daysOverdueModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-calendar-day me-2"></i>Update Days Overdue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_days_overdue">
                    <input type="hidden" id="update_instalment_id" name="instalment_id">
                    <div class="mb-3">
                        <label>Instalment</label>
                        <input type="text" class="form-control" id="instalment_number_display" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Current Days Overdue</label>
                        <input type="number" class="form-control" id="current_days_overdue" readonly>
                    </div>
                    <div class="mb-3">
                        <label>New Days Overdue *</label>
                        <input type="number" class="form-control" name="days_overdue" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<script>
/* ═══════════════════════════════════════════════════════════════
   UTILITY HELPERS
═══════════════════════════════════════════════════════════════ */
let currentBalance    = 0;
let currentPaidAmount = 0;
let currentDueDate    = '';

function formatNumber(num) {
    return parseFloat(num).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function formatNumberWithCommas(num) {
    const n = typeof num === 'string' ? parseFloat(num.replace(/,/g, '')) : num;
    if (isNaN(n)) return '0';
    return n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function removeCommas(str)  { return str.replace(/,/g, ''); }

function formatDate(dateStr) {
    const d   = new Date(dateStr);
    const day = String(d.getDate()).padStart(2, '0');
    const mon = String(d.getMonth() + 1).padStart(2, '0');
    return day + '/' + mon + '/' + d.getFullYear();
}

/* ═══════════════════════════════════════════════════════════════
   AUTO-CALCULATE DAYS OVERDUE
═══════════════════════════════════════════════════════════════ */
function calcDaysOverdue(transactionDateStr, dueDateStr) {
    if (!transactionDateStr || !dueDateStr) return 0;
    const txDate  = new Date(transactionDateStr);
    const dueDate = new Date(dueDateStr);
    txDate.setHours(0, 0, 0, 0);
    dueDate.setHours(0, 0, 0, 0);
    const diffDays = Math.floor((txDate - dueDate) / (1000 * 60 * 60 * 24));
    return Math.max(0, diffDays);
}

function refreshDaysOverdueFromDate() {
    const paymentDateInput = document.getElementById('modal_payment_date');
    if (!paymentDateInput || !currentDueDate) return;
    const computed = calcDaysOverdue(paymentDateInput.value, currentDueDate);
    document.getElementById('days_overdue').value = computed;
    updatePenalties();
}

/* ═══════════════════════════════════════════════════════════════
   REGULAR PAYMENT MODAL
═══════════════════════════════════════════════════════════════ */
function handlePaymentInput(input) {
    let value = removeCommas(input.value).replace(/[^\d.]/g, '');
    const parts = value.split('.');
    if (parts.length > 2) value = parts[0] + '.' + parts.slice(1).join('');
    input.value = formatNumberWithCommas(value);
    document.getElementById('actual_payment_amount').value = value;
}

function handlePenaltyReductionInput(input) {
    let value = removeCommas(input.value).replace(/[^\d.]/g, '');
    const parts = value.split('.');
    if (parts.length > 2) value = parts[0] + '.' + parts.slice(1).join('');
    input.value = formatNumberWithCommas(value);
    document.getElementById('penalty_reduction_amount').value = value;
    updatePenalties();
}

function openCheckoutModal(row) {
    const form = document.getElementById('checkoutForm');

    const instalmentId    = row.dataset.instalmentId;
    const instalmentNumber= row.dataset.instalmentNumber;
    const dueDate         = row.dataset.dueDate;
    const openingBalance  = parseFloat(row.dataset.openingBalance) || 0;
    const principal       = parseFloat(row.dataset.principal)      || 0;
    const interest        = parseFloat(row.dataset.interest)       || 0;
    const managementFee   = parseFloat(row.dataset.managementFee)  || 0;
    const totalPayment    = parseFloat(row.dataset.totalPayment)   || 0;
    const closingBalance  = parseFloat(row.dataset.closingBalance) || 0;
    const paidAmount      = parseFloat(row.dataset.paidAmount)     || 0;
    const balance         = parseFloat(row.dataset.balance)        || 0;
    // *** ADDED: read penalty_paid from row data attribute ***
    const penaltyPaid     = parseFloat(row.dataset.penaltyPaid)    || 0;

    currentBalance    = balance;
    currentPaidAmount = paidAmount;
    currentDueDate    = dueDate;

    form.querySelector('#instalment_id').value     = instalmentId;
    form.querySelector('#instalment_number').value = instalmentNumber;
    form.querySelector('#principal_amount').value  = principal;
    form.querySelector('#interest_amount').value   = interest;
    form.querySelector('#management_fee').value    = managementFee;

    document.getElementById('summary_instalment').textContent = '#' + instalmentNumber;
    document.getElementById('summary_due_date').textContent   = formatDate(dueDate);
    document.getElementById('summary_opening').textContent    = formatNumber(openingBalance);
    document.getElementById('summary_principal').textContent  = formatNumber(principal);
    document.getElementById('summary_interest').textContent   = formatNumber(interest);
    document.getElementById('summary_management').textContent = formatNumber(managementFee);
    document.getElementById('summary_total').textContent      = formatNumber(totalPayment);
    document.getElementById('summary_closing').textContent    = formatNumber(closingBalance);
    document.getElementById('summary_paid').textContent       = formatNumber(paidAmount);
    // *** ADDED: display penalties already paid for this instalment ***
    document.getElementById('summary_penalty_paid').textContent = formatNumber(penaltyPaid);
    document.getElementById('summary_balance').textContent    = formatNumber(balance);

    document.getElementById('penalty_reduction_display').value = '0';
    document.getElementById('penalty_reduction_amount').value  = '0';

    refreshDaysOverdueFromDate();

    new bootstrap.Modal(document.getElementById('checkoutModal')).show();
}

function updatePenalties() {
    const days      = parseInt(document.getElementById('days_overdue').value) || 0;
    const reduction = parseFloat(document.getElementById('penalty_reduction_amount').value || '0');

    // *** CHANGED: now uses LOAN_PENALTY_RATE (fixed 5%) instead of LOAN_MGMT_FEE_RATE (5.5%) ***
    // Formula: (balance × 5% / 30) × days
    const penalties = days > 0 ? ((currentBalance * LOAN_PENALTY_RATE) / 30) * days : 0;
    const adjusted  = Math.max(0, penalties - reduction);

    document.getElementById('penalties_display').value      = formatNumberWithCommas(adjusted.toFixed(2));
    document.getElementById('penalties').value              = adjusted.toFixed(2);

    const finalAmt = currentBalance + adjusted;

    // Base balance from DB (should include old penalties already corrected)
    document.getElementById('summary_balance').textContent  = formatNumber(currentBalance);
    
    // Final Amount to Pay includes the new penalties
    document.getElementById('summary_final').textContent    = formatNumber(finalAmt);
    document.getElementById('actual_payment_display').value = formatNumberWithCommas(finalAmt.toFixed(2));
    document.getElementById('actual_payment_amount').value  = finalAmt.toFixed(2);
    document.getElementById('summary_balance').title        = 'Base balance remaining from previous payments';
}

function openDaysOverdueModal(button) {
    const row = button.closest('tr');
    document.getElementById('update_instalment_id').value      = row.dataset.instalmentId;
    document.getElementById('instalment_number_display').value = 'Instalment #' + row.dataset.instalmentNumber;
    document.getElementById('current_days_overdue').value      = row.dataset.daysOverdue;
    new bootstrap.Modal(document.getElementById('daysOverdueModal')).show();
}

/* ═══════════════════════════════════════════════════════════════
   PREPAYMENT LOGIC
═══════════════════════════════════════════════════════════════ */
const PENDING_INSTALMENTS = <?php echo $prepay_json ?? '[]'; ?>;

function buildPrepayTable() {
    const tbody = document.getElementById('prepayTbody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!PENDING_INSTALMENTS.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">No pending instalments</td></tr>';
        return;
    }

    PENDING_INSTALMENTS.forEach((inst, idx) => {
        const isCurrent = (idx === 0);
        const amountDue = isCurrent ? inst.balance : inst.principal_due;

        const badge = isCurrent
            ? '<span class="badge" style="background:#6f42c1;font-size:.7rem;">Full Payment</span>'
            : '<span class="badge" style="background:#28a745;font-size:.7rem;">Principal Only (Fees Waived)</span>';

        const interestCell = isCurrent
            ? formatNumber(inst.interest_due)
            : '<span class="text-decoration-line-through text-muted">' + formatNumber(inst.interest_due) + '</span>';
        const feeCell = isCurrent
            ? formatNumber(inst.fee_due)
            : '<span class="text-decoration-line-through text-muted">' + formatNumber(inst.fee_due) + '</span>';

        const tr = document.createElement('tr');
        tr.className         = 'prepay-row ' + (isCurrent ? 'selected-current' : '');
        tr.dataset.idx       = idx;
        tr.dataset.isCurrent = isCurrent ? '1' : '0';
        tr.dataset.instId    = inst.id;
        tr.dataset.principal = inst.principal_due;
        tr.dataset.balance   = inst.balance;
        tr.dataset.amountDue = amountDue;

        tr.innerHTML =
            '<td class="text-center align-middle">' +
                '<input type="checkbox" class="prepay-check" data-idx="' + idx + '"' +
                (isCurrent ? ' checked disabled' : '') +
                ' onchange="onPrepayCheck(this)">' +
            '</td>' +
            '<td class="text-center">'  + inst.number                    + '</td>' +
            '<td class="text-center">'  + formatDate(inst.due_date)       + '</td>' +
            '<td class="text-end">'     + formatNumber(inst.balance)      + '</td>' +
            '<td class="text-end">'     + formatNumber(inst.principal_due) + '</td>' +
            '<td class="text-end">'     + interestCell                    + '</td>' +
            '<td class="text-end">'     + feeCell                         + '</td>' +
            '<td class="text-center">'  + badge                           + '</td>' +
            '<td class="text-end fw-bold">' + formatNumber(amountDue)     + '</td>';

        tbody.appendChild(tr);
    });

    recalcPrepayTotal();
}

function onPrepayCheck(checkbox) {
    const idx  = parseInt(checkbox.dataset.idx);
    const rows = document.querySelectorAll('#prepayTbody .prepay-row');

    if (checkbox.checked) {
        rows.forEach((row, i) => {
            if (i <= idx) {
                const cb = row.querySelector('.prepay-check');
                if (cb && !cb.disabled) cb.checked = true;
                if (i > 0) row.classList.add('selected-future');
            }
        });
    } else {
        rows.forEach((row, i) => {
            if (i >= idx) {
                const cb = row.querySelector('.prepay-check');
                if (cb && !cb.disabled) cb.checked = false;
                if (i > 0) row.classList.remove('selected-future');
            }
        });
    }
    recalcPrepayTotal();
}

function recalcPrepayTotal() {
    const rows = document.querySelectorAll('#prepayTbody .prepay-row');
    let total = 0, ids = [], principals = [], isCurrArr = [];
    let currentInstId = null, futureCount = 0, waivedInterest = 0, waivedFees = 0;

    rows.forEach((row, i) => {
        const cb      = row.querySelector('.prepay-check');
        const checked = cb && (cb.checked || cb.disabled);
        if (!checked) return;

        const isCurr    = row.dataset.isCurrent === '1';
        const amtDue    = parseFloat(row.dataset.amountDue) || 0;
        const principal = parseFloat(row.dataset.principal)  || 0;

        total += amtDue;
        ids.push(parseInt(row.dataset.instId));
        principals.push(principal);
        isCurrArr.push(isCurr ? 1 : 0);

        if (isCurr) {
            currentInstId = parseInt(row.dataset.instId);
        } else {
            futureCount++;
            row.classList.add('selected-future');
            const inst = PENDING_INSTALMENTS[i];
            if (inst) {
                waivedInterest += inst.interest_due || 0;
                waivedFees     += inst.fee_due      || 0;
            }
        }
    });

    let breakdown = '';
    if (currentInstId && futureCount > 0) {
        breakdown = 'Current (full) + ' + futureCount + ' future instalment(s) — principal only';
        if (waivedInterest > 0 || waivedFees > 0) {
            breakdown += ' | Waived → Interest: ' + formatNumber(waivedInterest) +
                         ', Fees: ' + formatNumber(waivedFees);
        }
    } else if (currentInstId) {
        breakdown = 'Current instalment — full payment';
    }

    document.getElementById('prepayTotalDisplay').textContent  = formatNumber(total);
    document.getElementById('prepayFooterTotal').textContent   = formatNumber(total);
    document.getElementById('prepayBreakdownText').textContent = breakdown;

    document.getElementById('prepayTotalHidden').value      = total.toFixed(2);
    document.getElementById('prepayCurrentInstId').value    = currentInstId || '';
    document.getElementById('prepayIdsHidden').value        = JSON.stringify(ids);
    document.getElementById('prepayPrincipalsHidden').value = JSON.stringify(principals);
    document.getElementById('prepayIsCurrentHidden').value  = JSON.stringify(isCurrArr);

    const btn  = document.getElementById('prepaySubmitBtn');
    const hint = document.getElementById('prepaySubmitHint');
    if (btn) {
        btn.disabled     = !(ids.length > 0 && total > 0);
        hint.textContent = ids.length > 0
            ? ids.length + ' instalment(s) selected — ' + formatNumber(total)
            : 'Select at least one instalment above';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    buildPrepayTable();

    const selectAll = document.getElementById('prepaySelectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            const rows = document.querySelectorAll('#prepayTbody .prepay-row');
            rows.forEach((row, i) => {
                const cb = row.querySelector('.prepay-check');
                if (cb && !cb.disabled) {
                    cb.checked = this.checked;
                    if (i > 0) row.classList.toggle('selected-future', this.checked);
                }
            });
            recalcPrepayTotal();
        });
    }

    const modalPaymentDate = document.getElementById('modal_payment_date');
    if (modalPaymentDate) {
        modalPaymentDate.addEventListener('change', refreshDaysOverdueFromDate);
    }
});

/* ═══════════════════════════════════════════════════════════════
   PDF GENERATION — rates are now dynamic via JS constants
═══════════════════════════════════════════════════════════════ */
function generatePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    const logoData = 'data:image/svg+xml;base64,' + btoa(
        '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">' +
        '<rect width="100" height="100" fill="#2c3e50"/>' +
        '<text x="50" y="50" font-size="32" fill="white" text-anchor="middle" dy=".3em" font-weight="bold">CBF</text>' +
        '</svg>'
    );
    try { doc.addImage(logoData, 'PNG', 15, 10, 25, 25); } catch(e) {}

    doc.setFontSize(22); doc.setFont('helvetica', 'bold'); doc.setTextColor(44, 62, 80);
    doc.text('CAPITAL BRIDGE FINANCE', 45, 18);
    doc.setFontSize(9); doc.setFont('helvetica', 'normal'); doc.setTextColor(85, 85, 85);
    doc.text('Kigali, Rwanda', 45, 24);
    doc.text('Tel: +250 796 880 272 | Email: info@cbfinance.rw', 45, 28);
    doc.text('Web: www.cbfinance.rw', 45, 32);

    doc.setDrawColor(44, 62, 80); doc.setLineWidth(0.5); doc.line(15, 38, 195, 38);

    doc.setFontSize(16); doc.setFont('helvetica', 'bold'); doc.setTextColor(44, 62, 80);
    doc.text('LOAN REPAYMENT SCHEDULE', 105, 46, { align: 'center' });
    doc.setFontSize(9); doc.setFont('helvetica', 'normal'); doc.setTextColor(100, 100, 100);
    doc.text('Generated on: ' + new Date().toLocaleDateString('en-GB') + ' ' + new Date().toLocaleTimeString('en-US'), 105, 51, { align: 'center' });

    doc.setFillColor(248, 249, 250);
    doc.rect(15, 56, 180, 28, 'F');
    doc.setDrawColor(222, 226, 230); doc.rect(15, 56, 180, 28);
    doc.setFontSize(12); doc.setFont('helvetica', 'bold'); doc.setTextColor(44, 62, 80);
    doc.text('Borrower Information', 17, 62);
    doc.setFontSize(10); doc.setFont('helvetica', 'normal'); doc.setTextColor(0, 0, 0);
    doc.text('Customer Name: <?php echo addslashes($loan_info['customer_name'] ?? 'N/A'); ?>', 17, 68);
    doc.text('Loan Number: <?php echo addslashes($loan_info['loan_number'] ?? 'N/A'); ?>', 17, 73);
    // ✅ DYNAMIC rates injected from JS constants (which were set from PHP)
    doc.text('Interest Rate: ' + LOAN_INTEREST_RATE_PCT + '%', 110, 68);
    doc.text('Management Fee: ' + LOAN_MGMT_FEE_RATE_PCT + '%', 110, 73);
    doc.text('Term: <?php echo $loan_info['number_of_instalments'] ?? 'N/A'; ?> months', 110, 78);
    doc.text('Start Date: <?php echo isset($loan_info['disbursement_date']) ? date('d/m/Y', strtotime($loan_info['disbursement_date'])) : 'N/A'; ?>', 110, 83);

    const tableData = [
        <?php foreach ($existing_instalments as $inst): ?>
        ['<?php echo $inst['instalment_number']; ?>',
         '<?php echo date('d/m/Y', strtotime($inst['due_date'])); ?>',
         '<?php echo number_format($inst['opening_balance'], 0); ?>',
         '<?php echo number_format($inst['principal_amount'], 0); ?>',
         '<?php echo number_format($inst['interest_amount'], 0); ?>',
         '<?php echo number_format($inst['management_fee'], 0); ?>',
         '<?php echo number_format($inst['total_payment'], 0); ?>',
         '<?php echo number_format($inst['closing_balance'], 0); ?>'],
        <?php
endforeach; ?>
    ];

    doc.autoTable({
        startY: 90,
        head: [['#', 'Payment Date', 'Opening Balance', 'Principal', 'Interest', 'Management Fee', 'Total Payment', 'Closing Balance']],
        body: tableData,
        foot: [['', 'TOTALS:',
            '<?php echo number_format($loan_info['total_disbursed_amount'] ?? 0, 0); ?>',
            '<?php echo number_format(array_sum(array_column($existing_instalments, 'principal_amount')), 0); ?>',
            '<?php echo number_format(array_sum(array_column($existing_instalments, 'interest_amount')), 0); ?>',
            '<?php echo number_format(array_sum(array_column($existing_instalments, 'management_fee')), 0); ?>',
            '<?php echo number_format(array_sum(array_column($existing_instalments, 'total_payment')), 0); ?>',
            '-']],
        theme: 'grid',
        styles: { fontSize: 8, cellPadding: 3, lineColor: [200,200,200], lineWidth: 0.1 },
        headStyles: { fillColor: [44,62,80], textColor: 255, fontStyle: 'bold', halign: 'center', fontSize: 8 },
        footStyles: { fillColor: [248,249,250], textColor: [44,62,80], fontStyle: 'bold', fontSize: 9 },
        columnStyles: {
            0: { halign: 'center', cellWidth: 12 }, 1: { halign: 'center', cellWidth: 25 },
            2: { halign: 'right',  cellWidth: 28 }, 3: { halign: 'right',  cellWidth: 22 },
            4: { halign: 'right',  cellWidth: 22 }, 5: { halign: 'right',  cellWidth: 22 },
            6: { halign: 'right',  cellWidth: 28 }, 7: { halign: 'right',  cellWidth: 28 }
        },
        alternateRowStyles: { fillColor: [252,252,252] }
    });

    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setDrawColor(200,200,200); doc.setLineWidth(0.1);
        doc.line(15, doc.internal.pageSize.height - 20, 195, doc.internal.pageSize.height - 20);
        doc.setFontSize(8); doc.setFont('helvetica','bold'); doc.setTextColor(44,62,80);
        doc.text('CAPITAL BRIDGE FINANCE', 105, doc.internal.pageSize.height - 16, { align: 'center' });
        doc.setFont('helvetica','normal'); doc.setFontSize(7); doc.setTextColor(100,100,100);
        doc.text('Kigali, Rwanda | Tel: +250 796 880 272 | Email: info@cbfinance.rw | Web: www.cbfinance.rw',
                 105, doc.internal.pageSize.height - 12, { align: 'center' });
        doc.text('Page ' + i + ' of ' + pageCount, 195, doc.internal.pageSize.height - 8, { align: 'right' });
        doc.text('Generated: ' + new Date().toLocaleDateString('en-GB'), 15, doc.internal.pageSize.height - 8);
        doc.setFontSize(6); doc.setTextColor(150,150,150);
        doc.text('This is a computer-generated document. For inquiries, please contact our office.',
                 105, doc.internal.pageSize.height - 5, { align: 'center' });
    }

    doc.save('Repayment_Schedule_<?php echo preg_replace('/[^A-Za-z0-9_\-]/', '_', $loan_info['customer_name'] ?? 'Customer'); ?>_<?php echo preg_replace('/[^A-Za-z0-9_\-]/', '_', $loan_info['loan_number'] ?? 'Loan'); ?>.pdf');
}
</script>

</body>
</html>
