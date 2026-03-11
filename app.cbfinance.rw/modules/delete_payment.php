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

        // Restore balance_remaining
        $new_bal_rem   = max(0, floatval($inst_check['balance_remaining']) + $total_loan_part + $penalty_paid);
        // Restore closing_balance (back toward opening_balance)
        $new_closing   = floatval($inst_check['opening_balance']) - $new_princ;

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
    }

    // ── 4. Reverse loan portfolio totals ─────────────────────────────────────
    $port_stmt = $conn->prepare(
        "UPDATE loan_portfolio SET
            total_paid                  = GREATEST(0, total_paid - ?),
            total_principal_paid        = GREATEST(0, total_principal_paid - ?),
            total_interest_paid         = GREATEST(0, total_interest_paid - ?),
            total_management_fees_paid  = GREATEST(0, total_management_fees_paid - ?),
            principal_outstanding       = principal_outstanding + ?,
            interest_outstanding        = interest_outstanding + ?,
            total_outstanding           = (principal_outstanding + ?) + (interest_outstanding + ?),
            loan_status                 = 'Active',
            updated_at                  = NOW()
         WHERE loan_id = ?"
    );
    $port_stmt->bind_param("ddddddddi",
        $amount, $principal_paid, $interest_paid, $mgmt_fee_paid,
        $principal_paid, $interest_paid, $principal_paid, $interest_paid, $loan_id
    );
    $port_stmt->execute();
    $port_stmt->close();

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
