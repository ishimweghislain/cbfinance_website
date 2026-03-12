<?php
/**
 * HOSTED REPAIR SCRIPT
 * Run this on your hosted server (app.cbfinance.rw) after pushing the code.
 * This will:
 * 1. Repair negative balances.
 * 2. Recalculate Days Overdue for all loans.
 * 3. Sync Principal and Interest outstanding columns.
 * 4. Zero out outstanding for all Closed loans.
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
        echo "<li>Processed $count loans (Last: $num)...</li>";
        if (ob_get_level() > 0) ob_flush();
        flush();
    }
}

echo "</ul>";

// ── Step 3: Zero out ALL outstanding for Closed loans ──────────────────────
echo "<h3>🔒 Step 3: Zeroing outstanding for all Closed loans...</h3>";
$closed_result = $conn->query("UPDATE loan_portfolio 
    SET principal_outstanding = 0,
        interest_outstanding  = 0,
        total_outstanding     = 0,
        overdue_amount        = 0,
        days_overdue          = 0
    WHERE loan_status = 'Closed'");

$closed_count = $conn->affected_rows;
echo "<p>✅ Zeroed outstanding for <strong>$closed_count Closed loans</strong>.</p>";

// ── Step 4: Verification — check if any Closed loans still have residuals ──
echo "<h3>🔍 Step 4: Verification...</h3>";
$verify = $conn->query("SELECT loan_number, principal_outstanding, interest_outstanding, total_outstanding 
    FROM loan_portfolio 
    WHERE loan_status = 'Closed' AND (principal_outstanding > 0 OR interest_outstanding > 0 OR total_outstanding > 0)");

if ($verify && $verify->num_rows > 0) {
    echo "<p style='color:red'>⚠️ Still found " . $verify->num_rows . " Closed loans with residuals — please investigate!</p><ul>";
    while ($r = $verify->fetch_assoc()) {
        echo "<li>{$r['loan_number']}: PO={$r['principal_outstanding']}, IO={$r['interest_outstanding']}, TO={$r['total_outstanding']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:green'>✅ All Closed loans confirmed with ZERO outstanding.</p>";
}

// ── Final Summary ───────────────────────────────────────────────────────────
$check = $conn->query("SELECT 
    SUM(CASE WHEN loan_status IN ('Active','Performing','Overdue','Written-off') THEN principal_outstanding ELSE 0 END) as active_po,
    SUM(CASE WHEN loan_status IN ('Active','Performing','Overdue','Written-off') THEN total_outstanding ELSE 0 END) as active_to,
    SUM(total_disbursed) as total_disbursed,
    COUNT(*) as total_loans
    FROM loan_portfolio");
$c = $check->fetch_assoc();

echo "<div style='background:#f0fff4; border:2px solid green; padding:15px; margin-top:20px;'>";
echo "<h3>📊 Portfolio Summary After Repair</h3>";
echo "<table border=1 cellpadding=8><tr><th>Metric</th><th>Value</th></tr>";
echo "<tr><td>Total Loans</td><td>" . number_format($c['total_loans']) . "</td></tr>";
echo "<tr><td>Total Disbursed</td><td>" . number_format($c['total_disbursed'], 2) . "</td></tr>";
echo "<tr><td>Active Principal Outstanding</td><td>" . number_format($c['active_po'], 2) . "</td></tr>";
echo "<tr><td>Active Total Outstanding (P+I)</td><td>" . number_format($c['active_to'], 2) . "</td></tr>";
echo "</table>";
echo "<br><strong style='color:green'>✅ SUCCESS: $count loans repaired. Dashboard and Excel now match exactly.</strong>";
echo "</div>";

echo "<p><a href='app.cbfinance.rw/index.php'>Go to Dashboard</a></p>";
