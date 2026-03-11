<?php
require_once __DIR__ . '/app.cbfinance.rw/config/database.php';
$conn = getConnection();

function inspectLoan($conn, $id) {
    echo "--- Inspecting Loan $id ---\n";
    $res = $conn->query("SELECT loan_id, loan_number, total_disbursed, loan_amount, principal_outstanding, total_outstanding FROM loan_portfolio WHERE loan_id = $id");
    if (!$res) return;
    $loan = $res->fetch_assoc();
    if (!$loan) { echo "Loan $id not found.\n"; return; }
    print_r($loan);

    echo "\nInstalments:\n";
    $res = $conn->query("SELECT instalment_number, opening_balance, principal_amount, interest_amount, management_fee, total_payment, closing_balance, balance_remaining, status FROM loan_instalments WHERE loan_id = $id ORDER BY instalment_number");
    while($row = $res->fetch_assoc()) {
        printf("#%d | OB: %s | Princ: %s | Int: %s | Fee: %s | Total: %s | CB: %s | Rem: %s | Status: %s\n",
            $row['instalment_number'],
            number_format($row['opening_balance'], 0),
            number_format($row['principal_amount'], 0),
            number_format($row['interest_amount'], 0),
            number_format($row['management_fee'], 0),
            number_format($row['total_payment'], 0),
            number_format($row['closing_balance'], 0),
            number_format($row['balance_remaining'], 0),
            $row['status']
        );
    }
    echo "\n";
}

inspectLoan($conn, 221); // Regis?
inspectLoan($conn, 260); // Peter
