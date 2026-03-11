<?php
/**
 * HEAL LOAN SCRIPT
 * This script recalibrates the individual instalment balances and closing balances 
 * based on the actual recorded payments (principal/interest/fees paid).
 * Use this to fix cases where the "Outstanding Balance" looks wrong because 
 * penalties were accidentally subtracted from the loan balance.
 */

// Show errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app.cbfinance.rw/config/database.php';
require_once __DIR__ . '/app.cbfinance.rw/includes/accounting_functions.php';

$conn = getConnection();

if (!isset($_GET['loan_id'])) {
    die("<h3>Usage: heal_loan.php?loan_id=XXX</h3>");
}

$loan_id = intval($_GET['loan_id']);

try {
    $conn->begin_transaction();

    // 1. Fetch Loan Rates for safety (though not strictly needed for healing)
    $loan_res = $conn->query("SELECT loan_number FROM loan_portfolio WHERE loan_id = $loan_id");
    $loan = $loan_res->fetch_assoc();
    if (!$loan) die("Loan #$loan_id not found.");

    echo "<h3>Healing Balances to for Loan: " . $loan['loan_number'] . "</h3>";
    echo "<ul>";

    // 2. Iterate through all instalments and recalculate balances
    $res = $conn->query("SELECT * FROM loan_instalments WHERE loan_id = $loan_id ORDER BY instalment_number ASC");
    
    while ($inst = $res->fetch_assoc()) {
        $inst_id = $inst['instalment_id'];
        $num = $inst['instalment_number'];
        
        $total_due = floatval($inst['total_payment']);
        $opening   = floatval($inst['opening_balance']);
        
        // Parts of the loan paid
        $p_paid    = floatval($inst['principal_paid']);
        $i_paid    = floatval($inst['interest_paid']);
        $f_paid    = floatval($inst['management_fee_paid']);
        
        // The core fix: Balance Remaining should only be affected by Loan Parts, NOT penalties.
        $loan_parts_paid = $p_paid + $i_paid + $f_paid;
        $correct_balance = max(0, $total_due - $loan_parts_paid);
        
        // Closing Balance: Opening - Principal Paid
        $correct_closing = max(0, $opening - $p_paid);
        
        // Status healing
        if ($correct_balance <= 0.01) {
            $new_status = 'Fully Paid';
        } elseif ($loan_parts_paid > 0) {
            $new_status = 'Partially Paid';
        } else {
            $new_status = 'Pending';
        }

        // Update the instalment row
        $upd_q = "UPDATE loan_instalments SET 
                    balance_remaining = $correct_balance,
                    closing_balance   = $correct_closing,
                    status            = '$new_status',
                    updated_at        = NOW()
                  WHERE instalment_id = $inst_id";
        
        $conn->query($upd_q);
        
        echo "<li>Instalment #$num: Bal Fixed to " . number_format($correct_balance, 2) . " (Closing: " . number_format($correct_closing, 2) . ")</li>";
    }
    echo "</ul>";

    // 3. Final Portfolio Sync
    syncLoanPortfolio($conn, $loan_id);
    
    $conn->commit();
    echo "<h4 style='color:green;'>✅ Healing Complete! Portfolio synced.</h4>";
    echo "<p>Regis's Loan Statistics should now show the correct local balance ($250,898) instead of the incorrect hosted balance.</p>";
    echo "<a href='app.cbfinance.rw/?page=viewloandetails&id=$loan_id'>Go back to Loan Details</a>";

} catch (Exception $e) {
    if ($conn) $conn->rollback();
    echo "<h4 style='color:red;'>Error: " . $e->getMessage() . "</h4>";
}
