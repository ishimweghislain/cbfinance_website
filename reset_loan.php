<?php
require_once __DIR__ . '/app.cbfinance.rw/config/database.php';
$conn = getConnection();

if (!isset($_GET['loan_id'])) {
    die("<h3>Please provide a loan_id in the URL. Example: ?loan_id=260</h3>");
}
$loan_id = intval($_GET['loan_id']);

try {
    $conn->begin_transaction();

    // 1. Fetch Loan Details
    $res = $conn->query("SELECT * FROM loan_portfolio WHERE loan_id = $loan_id");
    $loan = $res->fetch_assoc();
    if (!$loan) die("Loan ID $loan_id not found.");

    $amount = floatval($loan['disbursement_amount']);
    $int_rate = floatval($loan['interest_rate']) / 100;
    
    // 2. Delete ALL payments and ledgers tied to this loan
    $conn->query("DELETE FROM ledger WHERE reference_type = 'loan_payment' AND reference_id IN (SELECT instalment_id FROM loan_instalments WHERE loan_id = $loan_id)");
    $conn->query("DELETE FROM loan_payments WHERE loan_id = $loan_id");

    // 3. Reset Loan Portfolio Summary
    $conn->query("UPDATE loan_portfolio SET 
        total_paid = 0,
        total_principal_paid = 0,
        total_interest_paid = 0,
        total_management_fees_paid = 0,
        principal_outstanding = disbursement_amount,
        interest_outstanding = total_expected_interest,
        total_outstanding = disbursement_amount + total_expected_interest,
        loan_status = 'Active'
        WHERE loan_id = $loan_id");

    // 4. Financial Calculation Functions
    function pmt_calc($rate, $nper, $pv) {
        if ($rate == 0) return $pv / $nper;
        return $pv * ($rate * pow(1 + $rate, $nper)) / (pow(1 + $rate, $nper) - 1);
    }
    
    // 5. Rebuild Original Instalment Schedule
    $inst_res = $conn->query("SELECT * FROM loan_instalments WHERE loan_id = $loan_id ORDER BY instalment_number ASC");
    $instalments = $inst_res->fetch_all(MYSQLI_ASSOC);
    $nper = count($instalments);
    
    if ($nper > 0) {
        $pv = $amount;
        $balance = $pv;
        
        foreach ($instalments as $i => $inst) {
            $period = $i + 1;
            $inst_id = $inst['instalment_id'];
            
            // Recompute principal part for this period:
            $pmt = pmt_calc($int_rate, $nper, $pv);
            $temp_bal = $pv;
            for ($k=1; $k<$period; $k++) {
                $temp_int = $temp_bal * $int_rate;
                $temp_bal -= ($pmt - $temp_int);
            }
            $princ = round($pmt - ($temp_bal * $int_rate), 2);
            
            // Adjust last period or rounding to match balance
            if ($period == $nper || ($balance - $princ) < 1) {
                $princ = $balance;
            }
            
            $int = round($balance * $int_rate, 2);
            $fee = floatval($inst['management_fee']); // keep existing fee
            
            $total = round($princ + $int + $fee, 2);
            $cb = max(0, round($balance - $princ, 2));
            
            $conn->query("UPDATE loan_instalments SET
                opening_balance = $balance,
                principal_amount = $princ,
                interest_amount = $int,
                total_payment = $total,
                closing_balance = $cb,
                paid_amount = 0,
                principal_paid = 0,
                interest_paid = 0,
                management_fee_paid = 0,
                penalty_paid = 0,
                balance_remaining = $total,
                status = 'Pending',
                days_overdue = 0,
                payment_date = NULL,
                updated_at = NOW()
                WHERE instalment_id = $inst_id");
                
            $balance = $cb;
        }
    }

    $conn->commit();
    echo "<h2 style='color:green;'>✅ Loan #$loan_id successfully RESET from scratch!</h2>";
    echo "<p>All payments deleted, balances reverted, and schedule recalculated to its original state.</p>";
    echo "<a href='app.cbfinance.rw/index.php?page=viewloandetails&id=$loan_id' style='font-size:18px; color:blue; font-weight:bold;'>&larr; Go Back to Loan Details</a>";

} catch (Exception $e) {
    $conn->rollback();
    echo "<h3 style='color:red;'>Error resetting loan: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
?>
