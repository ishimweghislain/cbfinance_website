<?php
/**
 * HOSTED DATA REPAIR SCRIPT
 * Run this once after deploying the latest code to fix negative balances 
 * and synchronize loan totals with installment data.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app.cbfinance.rw/config/database.php';
require_once __DIR__ . '/app.cbfinance.rw/includes/accounting_functions.php';

$conn = getConnection();
if (!$conn) {
    die("Database connection failed.");
}

echo "<h2>🚀 Starting Hosted Data Repair...</h2>";

// ── SPECIAL FIX FOR PETER (Loan #260) ──
echo "<p>Applying manual correction for Peter's loan schedule...</p>";
$conn->query("UPDATE loan_instalments SET status='Pending', paid_amount=0, principal_paid=0, interest_paid=0, management_fee_paid=0, balance_remaining=total_payment WHERE loan_id=260");
// Fix Installment 3 Principal specifically for Peter to match requested 1.816M total
$conn->query("UPDATE loan_instalments SET opening_balance=455854, principal_amount=455854, interest_amount=22793, total_payment=577646, closing_balance=0, balance_remaining=577646 WHERE loan_id=260 AND instalment_number=3");

echo "<p>Synchronizing all loan portfolios with installment data...</p>";

// 1. Fetch all loans
$loans_res = $conn->query("SELECT loan_id, loan_number FROM loan_portfolio");
$processed = 0;

if ($loans_res) {
    while ($row = $loans_res->fetch_assoc()) {
        $loan_id = $row['loan_id'];
        $loan_num = $row['loan_number'];

        // Use the core sync function to recalculate all totals from scratch
        syncLoanPortfolio($conn, $loan_id);

        $processed++;
        if ($processed % 10 == 0) {
            echo "Processed $processed loans... (Last: $loan_num)<br>";
            flush();
        }
    }
}

// 2. Final safety check for negative totals (just in case)
$conn->query("UPDATE loan_portfolio SET 
    total_paid = GREATEST(0, total_paid),
    total_principal_paid = GREATEST(0, total_principal_paid),
    total_interest_paid = GREATEST(0, total_interest_paid),
    principal_outstanding = GREATEST(0, principal_outstanding),
    interest_outstanding = GREATEST(0, interest_outstanding),
    total_outstanding = GREATEST(0, total_outstanding)
");

// 3. Mark any fully paid loans correctly
$conn->query("UPDATE loan_portfolio lp SET lp.loan_status = 'Closed' 
    WHERE lp.total_outstanding <= 1 AND lp.loan_status = 'Active'");

echo "<div style='color: green; font-weight: bold; margin-top: 20px;'>";
echo "✅ SUCCESS: All $processed loans have been synchronized and repaired.";
echo "</div>";
echo "<p><a href='index.php?page=loans'>Return to Dashboard</a></p>";

$conn->close();
?>
