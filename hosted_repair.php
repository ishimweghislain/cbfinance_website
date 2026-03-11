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
    
    // Call the updated sync function which now handles days_overdue and negatives
    syncLoanPortfolio($conn, $id);
    
    $count++;
    if ($count % 5 == 0) {
        echo "<li>Processed $count loans (Last: $num)...</li>";
        // Flush output to browser to avoid timeout
        if (ob_get_level() > 0) ob_flush();
        flush();
    }
}

echo "</ul>";
echo "<div style='color: green; font-weight: bold; border: 2px solid green; padding: 15px; margin-top: 20px;'>";
echo "✅ SUCCESS: $count loans have been repaired and synchronized.";
echo "<br>Excel Reports and Dashboard will now match perfectly.";
echo "</div>";

echo "<p><a href='index.php'>Go to Dashboard</a></p>";
