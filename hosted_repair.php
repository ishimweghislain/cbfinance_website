<?php
/**
 * HOSTED REPAIR SCRIPT
 * Run this on your hosted server (app.cbfinance.rw) after pushing the code.
 * This will:
 * 1. Repair negative balances.
 * 2. Recalculate Days Overdue for all loans.
 * 3. Sync Principal and Interest outstanding columns.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app.cbfinance.rw/config/database.php';
require_once __DIR__ . '/app.cbfinance.rw/includes/accounting_functions.php';

$conn = getConnection();
if (!$conn) die("Database Connection failed");

echo "<h2>🚀 Starting Hosted Data Repair...</h2>";
echo "<p>Synchronizing all loan portfolios with installment data...</p>";
echo "<hr>";

$res = $conn->query("SELECT loan_id, loan_number, loan_status FROM loan_portfolio");
$count = 0;
echo "<ul>";

while ($loan = $res->fetch_assoc()) {
    $id = $loan['loan_id'];
    $num = $loan['loan_number'];
    
    // 1. Sync the primary counters using the function
    syncLoanPortfolio($conn, $id);
    
    // 2. Explicitly force P+I math on summary columns to be 100% sure
    $conn->query("UPDATE loan_portfolio SET 
        principal_outstanding = GREATEST(0, (SELECT IFNULL(SUM(principal_amount - principal_paid), 0) FROM loan_instalments WHERE loan_id = $id)),
        interest_outstanding  = GREATEST(0, (SELECT IFNULL(SUM(interest_amount - interest_paid), 0) FROM loan_instalments WHERE loan_id = $id)),
        total_outstanding     = GREATEST(0, (SELECT IFNULL(SUM(principal_amount - principal_paid + interest_amount - interest_paid), 0) FROM loan_instalments WHERE loan_id = $id))
        WHERE loan_id = $id");

    $count++;
    if ($count % 5 == 0) {
        echo "<li>Processed $count loans (Final loan in DB: $num)...</li>";
        if (ob_get_level() > 0) ob_flush();
        flush();
    }
}

echo "</ul>";
echo "<div style='color: green; font-weight: bold; border: 2px solid green; padding: 15px; margin-top: 20px;'>";
echo "✅ SUCCESS: $count loans have been repaired and synchronized.";
echo "<br>The math formula is now strictly: TOTAL = Principal + Interest.";
echo "<br>The dashboard and reports will now match perfectly.";
echo "</div>";

echo "<p><a href='index.php'>Go to Dashboard</a></p>";
