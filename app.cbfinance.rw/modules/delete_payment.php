<?php
if (!isset($_GET['payment_id']) || !isset($_GET['loan_id'])) {
    echo '<div class="alert alert-danger">Missing parameters.</div>';
    return;
}

$payment_id = intval($_GET['payment_id']);
$loan_id    = intval($_GET['loan_id']);
$conn = getConnection();

// Safely include logger if it exists
$logger_path = __DIR__ . '/../includes/activity_logger.php';
if (file_exists($logger_path)) require_once $logger_path;
require_once __DIR__ . '/../includes/accounting_functions.php';

// ─── Start Transaction ────────────────────────────────────────────────────────
$conn->begin_transaction();

try {
    // 1. Fetch payment details — use column names that match the actual loan_payments table
    $pmt_stmt = $conn->prepare("SELECT * FROM loan_payments WHERE payment_id = ?");
    $pmt_stmt->bind_param("i", $payment_id);
    $pmt_stmt->execute();
    $pmt_result = $pmt_stmt->get_result();
    $pmt = $pmt_result->fetch_assoc();
    $pmt_stmt->close();

    if (!$pmt) throw new Exception("Payment record not found (ID: $payment_id).");

    // Fetch loan rates for recalculation
    $rate_res = $conn->query("SELECT interest_rate, management_fee_rate FROM loan_portfolio WHERE loan_id = $loan_id");
    $rates = $rate_res->fetch_assoc();
    $interest_rate = floatval($rates['interest_rate'] ?? 5.0) / 100;
    $mgmt_fee_rate = floatval($rates['management_fee_rate'] ?? 5.5) / 100;

    // Read amounts — handle both possible column naming conventions gracefully
    $amount         = floatval($pmt['payment_amount'] ?? 0);
    // loan_payments stores penalty in 'penalties' column
    $penalty_paid   = floatval($pmt['penalties'] ?? $pmt['penalty_paid'] ?? 0);
    // loan_payments stores principal in 'principal_amount' column
    $principal_paid = floatval($pmt['principal_amount'] ?? $pmt['principal_paid'] ?? 0);
    // loan_payments stores interest in 'interest_amount' column
    $interest_paid  = floatval($pmt['interest_amount'] ?? $pmt['interest_paid'] ?? 0);
    // loan_payments stores fee in 'monitoring_fee' column
    $mgmt_fee_paid  = floatval($pmt['monitoring_fee'] ?? $pmt['management_fee_paid'] ?? 0);
    $instalment_id  = intval($pmt['loan_instalment_id'] ?? 0);

    $total_loan_part = $principal_paid + $interest_paid + $mgmt_fee_paid;

    // ── 2. Fetch current instalment data BEFORE reversing ────────────────────
    $inst_check = null;
    if ($instalment_id > 0) {
        $ic_stmt = $conn->prepare("SELECT * FROM loan_instalments WHERE instalment_id = ?");
        $ic_stmt->bind_param("i", $instalment_id);
        $ic_stmt->execute();
        $inst_check = $ic_stmt->get_result()->fetch_assoc();
        $ic_stmt->close();
    }

    // ── 3. Reverse instalment paid amounts ───────────────────────────────────
    if ($instalment_id > 0 && $inst_check) {
        $new_paid      = max(0, floatval($inst_check['paid_amount'])          - $total_loan_part);
        $new_princ     = max(0, floatval($inst_check['principal_paid'])       - $principal_paid);
        $new_int       = max(0, floatval($inst_check['interest_paid'])        - $interest_paid);
        $new_fee       = max(0, floatval($inst_check['management_fee_paid'])  - $mgmt_fee_paid);
        $new_pen       = max(0, floatval($inst_check['penalty_paid'])         - $penalty_paid);
        $total_due     = floatval($inst_check['total_payment']);

        // Restore balance_remaining by taking total scheduled payment minus what is now left as paid
        $new_bal_rem   = max(0, $total_due - $new_paid);
        
        // Restore closing_balance to what it SHOULD be based on opening balance and scheduled principal
        $new_closing   = floatval($inst_check['opening_balance']) - floatval($inst_check['principal_amount']);

        // Re-determine status
        if ($new_paid <= 0.01) {
            $new_status = 'Pending';
        } elseif ($new_paid >= $total_due - 0.01) {
            $new_status = 'Fully Paid';
        } else {
            $new_status = 'Partially Paid';
        }

        $rev_stmt = $conn->prepare(
            "UPDATE loan_instalments SET
                paid_amount         = ?,
                principal_paid      = ?,
                interest_paid       = ?,
                management_fee_paid = ?,
                penalty_paid        = ?,
                balance_remaining   = ?,
                closing_balance     = ?,
                status              = ?,
                payment_date        = NULL,
                updated_at          = NOW()
             WHERE instalment_id = ?"
        );
        $rev_stmt->bind_param("dddddddsi",
            $new_paid, $new_princ, $new_int, $new_fee, $new_pen,
            $new_bal_rem, $new_closing, $new_status, $instalment_id
        );
        $rev_stmt->execute();
        $rev_stmt->close();

        // ── 3b. Trigger schedule recalculation only if it was an OVERPAYMENT ─────────────────
        // We only re-amortize if the original payment was more than what was due (prepayment).
        // Otherwise, we just sync the portfolio to keep the original fixed schedule.
        $beg_bal = floatval($pmt['beginning_balance'] ?? 0);
        $total_paid_to_loan = $amount - $penalty_paid;

        if ($beg_bal > 0 && $total_paid_to_loan > $beg_bal + 1) { // +1 for rounding buffer
            recalculateRemainingSchedule($conn, $loan_id, $current_inst_num, $new_closing, $interest_rate, $mgmt_fee_rate);
        } else {
            // No recalculation needed; just sync portfolio to reflect the reversed principal/interest
            syncLoanPortfolio($conn, $loan_id);
        }
    }

    // ── 4. Ensure Portfolio is accurately synced ──────────────────────────
    // syncLoanPortfolio is the source of truth—it sums all installments to get totals.
    // We already called it inside the installment reversal logic above, but we call it
    // one more time here to be absolutely sure the portfolio matches the database state.
    syncLoanPortfolio($conn, $loan_id);

    // ── 5. Remove ledger entries tied to this payment ─────────────────────────
    // Try by payment_id reference first, then fall back to instalment_id
    $conn->query("DELETE FROM ledger WHERE reference_type = 'loan_payment' AND reference_id = $payment_id");
    if ($instalment_id > 0) {
        $conn->query("DELETE FROM ledger WHERE reference_type = 'loan_payment' AND reference_id = $instalment_id AND transaction_date = (SELECT payment_date FROM loan_payments WHERE payment_id = $payment_id LIMIT 1)");
    }

    // ── 6. Delete the payment record ─────────────────────────────────────────
    $del_stmt = $conn->prepare("DELETE FROM loan_payments WHERE payment_id = ?");
    $del_stmt->bind_param("i", $payment_id);
    $del_stmt->execute();
    $del_stmt->close();

    // ── 7. Log activity (if logger available) ─────────────────────────────────
    if (function_exists('logActivity')) {
        logActivity($conn, 'delete', 'payment', $payment_id, "Deleted payment ID $payment_id for loan ID $loan_id. Reversed: principal=$principal_paid, interest=$interest_paid, fees=$mgmt_fee_paid, penalties=$penalty_paid.");
    }

    $conn->commit();
    echo "<script>alert('Payment #$payment_id deleted and balances reversed successfully.'); window.location.href='?page=viewloandetails&id=$loan_id';</script>";

} catch (Exception $e) {
    $conn->rollback();
    echo '<div class="alert alert-danger mx-3 mt-3"><strong>Error deleting payment:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<div class="mx-3"><a href="?page=viewloandetails&id='.$loan_id.'" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Loan</a></div>';
}
?>
