<?php
// mass_sync.php
// This script runs the updated syncLoanPortfolio for ALL loans to fix negatives and inconsistencies.

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app.cbfinance.rw/config/database.php';
require_once __DIR__ . '/app.cbfinance.rw/includes/accounting_functions.php';

$conn = getConnection();
if (!$conn) die("Connection failed");

echo "<h3>Mass Syncing Loan Balances...</h3>";
echo "<ul>";

$res = $conn->query("SELECT loan_id, loan_number FROM loan_portfolio");
$count = 0;
while ($loan = $res->fetch_assoc()) {
    $id = $loan['loan_id'];
    syncLoanPortfolio($conn, $id);
    $count++;
    if ($count % 10 == 0) echo "<li>Synced $count loans...</li>";
}

echo "</ul>";
echo "<h4>✅ Successfully synced $count loans.</h4>";
echo "<p>Negative Principal/Interest Outstanding values should now be corrected to 0.</p>";
echo "<a href='index.php'>Return to Dashboard</a>";
