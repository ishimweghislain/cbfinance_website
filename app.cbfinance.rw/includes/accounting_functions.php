<?php
// Functions for loan accounting operations

function createNewLoan($conn, $data)
{
    try {
        $conn->begin_transaction();

        // Calculate values
        $totalLoan = calculateTotalLoanAmount($data);
        $instalment = calculateInstalmentAmount($data);

        // Insert loan
        $stmt = $conn->prepare("
            INSERT INTO loan_portfolio (
                loan_number, customer_id, loan_purpose, product_type, loan_officer_id,
                disbursement_amount, interest_rate, disbursement_date, maturity_date,
                number_of_instalments, instalment_amount, application_fees, 
                disbursement_fees, disbursement_fees_vat, monitoring_fees, monitoring_fees_vat,
                penalty_rate, collateral_type_id, collateral_value, collateral_description,
                collateral_location, collateral_ref_number, collateral_bnr_rate,
                principal_outstanding, total_outstanding, loan_status, next_payment_date,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param("sisssddssidddddddidssssddss",
            $data['loan_number'], $data['customer_id'], $data['loan_purpose'],
            $data['product_type'], $data['loan_officer_id'], $data['disbursement_amount'],
            $data['interest_rate'], $data['disbursement_date'], $data['maturity_date'],
            $data['number_of_instalments'], $instalment, $data['application_fees'],
            $data['disbursement_fees'], $data['disbursement_fees_vat'],
            $data['monitoring_fees'], $data['monitoring_fees_vat'], $data['penalty_rate'],
            $data['collateral_type_id'], $data['collateral_value'],
            $data['collateral_description'], $data['collateral_location'],
            $data['collateral_ref_number'], $data['collateral_bnr_rate'],
            $data['disbursement_amount'], $totalLoan, 'Active', $data['first_payment_date']
        );

        $stmt->execute();
        $loan_id = $conn->insert_id;

        // Create accounting entries
        createDisbursementJournalEntries($conn, $loan_id, $data);

        // Generate payment schedule
        generatePaymentSchedule($conn, $loan_id, $data);

        // Create initial accruals
        createInitialAccruals($conn, $loan_id, $data);

        $conn->commit();
        return true;

    }
    catch (Exception $e) {
        $conn->rollback();
        error_log("Error creating loan: " . $e->getMessage());
        return false;
    }
}

function createDisbursementJournalEntries($conn, $loan_id, $data)
{
    // 1. Application Fees Entry
    $appFeeNet = $data['application_fees'] / 1.18;
    $appFeeVAT = $data['application_fees'] - $appFeeNet;

    createJournalEntry($conn, [
        'journal_number' => 'J-' . date('Ymd') . '-' . str_pad($loan_id, 4, '0', STR_PAD_LEFT) . '-1',
        'description' => 'Application Fees - Loan ' . $data['loan_number'],
        'entry_date' => date('Y-m-d'),
        'reference_id' => $loan_id,
        'reference_type' => 'loan',
        'entries' => [
            ['account_code' => '1101', 'debit' => $data['application_fees'], 'credit' => 0, 'description' => 'Bank/Cash'],
            ['account_code' => '4204', 'debit' => 0, 'credit' => $appFeeNet, 'description' => 'Application Fee Income'],
            ['account_code' => '2105', 'debit' => 0, 'credit' => $appFeeVAT, 'description' => 'VAT Payable']
        ]
    ]);

    // 2. Loan Disbursement Entry
    createJournalEntry($conn, [
        'journal_number' => 'J-' . date('Ymd') . '-' . str_pad($loan_id, 4, '0', STR_PAD_LEFT) . '-2',
        'description' => 'Loan Disbursement - ' . $data['loan_number'],
        'entry_date' => date('Y-m-d'),
        'reference_id' => $loan_id,
        'reference_type' => 'loan',
        'entries' => [
            ['account_code' => '1201', 'debit' => $data['disbursement_amount'], 'credit' => 0, 'description' => 'Loan to Customers'],
            ['account_code' => '1201', 'debit' => $data['disbursement_fees'], 'credit' => 0, 'description' => 'Deferred Disbursement Fees'],
            ['account_code' => '1201', 'debit' => $data['disbursement_fees_vat'], 'credit' => 0, 'description' => 'Disbursement VAT Portion'],
            ['account_code' => $data['bank_account_id'], 'debit' => 0, 'credit' => $data['disbursement_amount'], 'description' => 'Bank Account'],
            ['account_code' => '4202', 'debit' => 0, 'credit' => $data['disbursement_fees'], 'description' => 'Disbursement Fee Income'],
            ['account_code' => '2105', 'debit' => 0, 'credit' => $data['disbursement_fees_vat'], 'description' => 'VAT Payable (Disbursement Fee Portion)']
        ]
    ]);
}

function createInitialAccruals($conn, $loan_id, $data)
{
    $disbursement_date = new DateTime($data['disbursement_date']);
    $month_end = new DateTime($disbursement_date->format('Y-m-t'));
    $days_in_month = (int)$month_end->format('d');
    $days_remaining = $days_in_month - (int)$disbursement_date->format('d');

    if ($days_remaining > 0) {
        // Calculate daily interest
        $daily_interest_rate = $data['interest_rate'] / 365 / 100;
        $interest_accrual = $data['disbursement_amount'] * $daily_interest_rate * $days_remaining;

        // Calculate monitoring fees (pro-rated)
        $monthly_monitoring = $data['monitoring_fees'];
        $daily_monitoring = $monthly_monitoring / $days_in_month;
        $monitoring_accrual = $daily_monitoring * $days_remaining;
        $monitoring_vat_accrual = $monitoring_accrual * 18 / 118;

        // Insert accruals
        $stmt = $conn->prepare("
            INSERT INTO loan_accruals (loan_id, accrual_date, accrual_type, 
                interest_amount, monitoring_fee_amount, vat_amount, total_amount, status)
            VALUES (?, ?, 'Initial', ?, ?, ?, ?, 'Active')
        ");

        $total_accrual = $interest_accrual + $monitoring_accrual + $monitoring_vat_accrual;
        $stmt->bind_param("isdddd", $loan_id, $month_end->format('Y-m-d'),
            $interest_accrual, $monitoring_accrual, $monitoring_vat_accrual, $total_accrual);
        $stmt->execute();

        // Update loan with accrued amounts
        $conn->query("
            UPDATE loan_portfolio SET 
                accrued_interest = $interest_accrual,
                accrued_monitoring_fees = $monitoring_accrual,
                accrued_monitoring_fees_vat = $monitoring_vat_accrual,
                accrued_days = $days_remaining
            WHERE loan_id = $loan_id
        ");

        // Create journal entry for accruals
        createJournalEntry($conn, [
            'journal_number' => 'J-' . date('Ymd') . '-' . str_pad($loan_id, 4, '0', STR_PAD_LEFT) . '-3',
            'description' => 'Initial Accruals - Loan ' . $data['loan_number'],
            'entry_date' => date('Y-m-d'),
            'reference_id' => $loan_id,
            'reference_type' => 'loan',
            'entries' => [
                ['account_code' => '1203', 'debit' => $interest_accrual, 'credit' => 0, 'description' => 'Interest Receivable'],
                ['account_code' => '1204', 'debit' => $monitoring_accrual, 'credit' => 0, 'description' => 'Monitoring Fees Receivable'],
                ['account_code' => '1206', 'debit' => $monitoring_vat_accrual, 'credit' => 0, 'description' => 'VAT Receivable'],
                ['account_code' => '4101', 'debit' => 0, 'credit' => $interest_accrual, 'description' => 'Interest Income'],
                ['account_code' => '4202', 'debit' => 0, 'credit' => $monitoring_accrual, 'description' => 'Monitoring Fee Income'],
                ['account_code' => '2105', 'debit' => 0, 'credit' => $monitoring_vat_accrual, 'description' => 'VAT Payable']
            ]
        ]);
    }
}

function calculateTotalLoanAmount($data)
{
    return $data['disbursement_amount'] +
        $data['application_fees'] +
        $data['disbursement_fees'] +
        $data['disbursement_fees_vat'];
}

function calculateInstalmentAmount($data)
{
    $principal = $data['disbursement_amount'];
    $interest_rate = $data['interest_rate'] / 100;
    $term_months = $data['number_of_instalments'];

    // Monthly interest rate
    $monthly_rate = $interest_rate / 12;

    // Calculate instalment using annuity formula
    if ($monthly_rate > 0) {
        $instalment = $principal * $monthly_rate * pow(1 + $monthly_rate, $term_months) /
            (pow(1 + $monthly_rate, $term_months) - 1);
    }
    else {
        $instalment = $principal / $term_months;
    }

    return round($instalment, 2);
}

function updateLoan($conn, $loan_id, $data)
{
    $stmt = $conn->prepare("
        UPDATE loan_portfolio SET 
            customer_id = ?, loan_officer_id = ?, loan_purpose = ?,
            interest_rate = ?, instalment_amount = ?, number_of_instalments = ?,
            disbursement_date = ?, maturity_date = ?, application_fees = ?,
            monitoring_fees = ?, penalty_rate = ?, disbursement_fees = ?,
            collateral_type_id = ?, collateral_value = ?, collateral_description = ?,
            collateral_location = ?, notes = ?
        WHERE loan_id = ?
    ");

    $stmt->bind_param("iisddiissdddissssi",
        $data['customer_id'], $data['loan_officer_id'], $data['loan_purpose'],
        $data['interest_rate'], $data['instalment_amount'], $data['number_of_instalments'],
        $data['disbursement_date'], $data['maturity_date'], $data['application_fees'],
        $data['monitoring_fees'], $data['penalty_rate'], $data['disbursement_fees'],
        $data['collateral_type_id'], $data['collateral_value'], $data['collateral_description'],
        $data['collateral_location'], $data['notes'], $loan_id
    );

    return $stmt->execute();
}

function createJournalEntry($conn, $entry_data)
{
    $stmt = $conn->prepare("
        INSERT INTO journal_entries (journal_number, description, entry_date, 
            reference_id, reference_type, total_debit, total_credit, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Posted')
    ");

    // Calculate totals
    $total_debit = 0;
    $total_credit = 0;

    foreach ($entry_data['entries'] as $line) {
        $total_debit += $line['debit'];
        $total_credit += $line['credit'];
    }

    $stmt->bind_param("sssissdd",
        $entry_data['journal_number'], $entry_data['description'],
        $entry_data['entry_date'], $entry_data['reference_id'],
        $entry_data['reference_type'], $total_debit, $total_credit
    );

    $stmt->execute();
    $journal_id = $conn->insert_id;

    // Insert journal lines
    foreach ($entry_data['entries'] as $line) {
        $line_stmt = $conn->prepare("
            INSERT INTO journal_lines (journal_id, account_code, 
                debit_amount, credit_amount, description)
            VALUES (?, ?, ?, ?, ?)
        ");

        $line_stmt->bind_param("isdds",
            $journal_id, $line['account_code'],
            $line['debit'], $line['credit'], $line['description']
        );

        $line_stmt->execute();
    }

    return $journal_id;
}

/**
 * Financial Calculation Functions (PMT / PPMT / IPMT)
 */
function PMT($rate, $nper, $pv)
{
    if ($rate == 0)
        return -$pv / $nper;
    return -$pv * ($rate * pow(1 + $rate, $nper)) / (pow(1 + $rate, $nper) - 1);
}

function IPMT($rate, $period, $nper, $pv)
{
    if ($period == 1)
        return -$pv * $rate;
    $pmt = PMT($rate, $nper, $pv);
    $remaining_balance = $pv;
    for ($i = 1; $i < $period; $i++) {
        $interest = -$remaining_balance * $rate;
        $principal = $pmt - $interest;
        $remaining_balance += $principal;
    }
    return -$remaining_balance * $rate;
}

function PPMT($rate, $period, $nper, $pv)
{
    return PMT($rate, $nper, $pv) - IPMT($rate, $period, $nper, $pv);
}

/**
 * Re-amortizes the remaining loan schedule based on a new principal balance.
 * Typically used after an early repayment or a payment deletion.
 */
function recalculateRemainingSchedule($conn, $loan_id, $current_instalment_number, $new_closing_balance, $interest_rate, $mgmt_fee_rate)
{
    if ($new_closing_balance < 0.01) {
        $new_closing_balance = 0;
    }

    // 1. Fetch all future instalments
    $query = "SELECT * FROM loan_instalments WHERE loan_id = ? AND instalment_number > ? ORDER BY instalment_number ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $loan_id, $current_instalment_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $future_instalments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($future_instalments)) {
        // Even if no future installments, sync portfolio
        syncLoanPortfolio($conn, $loan_id);
        return;
    }

    // If balance reached zero, mark all future as fully paid and zero them out
    if ($new_closing_balance <= 0) {
        foreach ($future_instalments as $inst) {
            $upd = $conn->prepare("UPDATE loan_instalments SET opening_balance=0, principal_amount=0, interest_amount=0, total_payment=0, closing_balance=0, balance_remaining=0, status='Fully Paid', updated_at=NOW() WHERE instalment_id=?");
            $upd->bind_param("i", $inst['instalment_id']);
            $upd->execute();
            $upd->close();
        }
        syncLoanPortfolio($conn, $loan_id);
        return;
    }

    $num_remaining = count($future_instalments);
    $opening_balance = $new_closing_balance;

    for ($i = 0; $i < $num_remaining; $i++) {
        $inst = $future_instalments[$i];
        $inst_id = $inst['instalment_id'];
        $inst_num = $i + 1;

        // Recalculate principal to amortize the rest over remaining periods
        $principal = round(-PPMT($interest_rate, $inst_num, $num_remaining, $new_closing_balance), 2);

        if ($i == $num_remaining - 1 || ($opening_balance - $principal) < 1) {
            $principal = $opening_balance;
        }

        $interest = round($opening_balance * $interest_rate, 2);
        $mgmt_fee = floatval($inst['management_fee']);

        $total_payment = round($principal + $interest + $mgmt_fee, 2);
        $closing_balance = max(0, round($opening_balance - $principal, 2));

        // If it was already fully paid, keep it paid? 
        // No, if we are recalculating due to deletion, it might become pending again.
        // But if it was partially paid, we should preserve the paid amount.
        $paid_amt = floatval($inst['paid_amount']);
        $new_bal_rem = max(0, $total_payment - $paid_amt);
        $new_status = ($new_bal_rem <= 0.01) ? 'Fully Paid' : (($paid_amt > 0) ? 'Partially Paid' : 'Pending');

        $update_query = "UPDATE loan_instalments SET 
            opening_balance = ?, principal_amount = ?, interest_amount = ?, management_fee = ?, 
            total_payment = ?, closing_balance = ?, balance_remaining = ?, status = ?, updated_at = NOW()
            WHERE instalment_id = ?";
        $upd_stmt = $conn->prepare($update_query);
        $upd_stmt->bind_param("dddddddsi",
            $opening_balance, $principal, $interest, $mgmt_fee, $total_payment, $closing_balance, $new_bal_rem, $new_status, $inst_id
        );
        $upd_stmt->execute();
        $upd_stmt->close();

        $opening_balance = $closing_balance;
        if ($opening_balance <= 0) {
            for ($j = $i + 1; $j < $num_remaining; $j++) {
                $future_id = $future_instalments[$j]['instalment_id'];
                $conn->query("UPDATE loan_instalments SET opening_balance=0, principal_amount=0, interest_amount=0, total_payment=0, closing_balance=0, balance_remaining=0, status='Fully Paid' WHERE instalment_id=$future_id");
            }
            break;
        }
    }

    syncLoanPortfolio($conn, $loan_id);
}

/**
 * Synchronizes the loan_portfolio summary columns with the latest installment schedule data.
 * Also repairs continuity (Opening = Previous Closing) for pending installments.
 */
function syncLoanPortfolio($conn, $loan_id)
{
    // 0. Update days_overdue for installments
    $conn->query("UPDATE loan_instalments SET 
        days_overdue = GREATEST(0, DATEDIFF(CURDATE(), due_date)) 
        WHERE loan_id = $loan_id AND payment_date IS NULL");

    // 0b. REPAIR CONTINUITY: Ensure each pending installment opens with the previous one's closing balance
    $inst_res = $conn->query("SELECT * FROM loan_instalments WHERE loan_id = $loan_id ORDER BY instalment_number ASC");
    $prev_closing = null;
    while ($inst = $inst_res->fetch_assoc()) {
        if ($prev_closing !== null && $inst['status'] !== 'Fully Paid') {
            if (abs(floatval($inst['opening_balance']) - $prev_closing) > 0.01) {
                $curr_id = $inst['instalment_id'];
                $conn->query("UPDATE loan_instalments SET opening_balance = $prev_closing WHERE instalment_id = $curr_id");
            }
        }
        $prev_closing = floatval($inst['closing_balance']);
    }

    // 1. Recalibrate summary paid columns
    $conn->query("UPDATE loan_portfolio lp SET 
        lp.total_principal_paid = (SELECT IFNULL(SUM(principal_paid), 0) FROM loan_instalments WHERE loan_id = lp.loan_id),
        lp.total_interest_paid  = (SELECT IFNULL(SUM(interest_paid), 0)  FROM loan_instalments WHERE loan_id = lp.loan_id),
        lp.total_management_fees_paid = (SELECT IFNULL(SUM(management_fee_paid), 0) FROM loan_instalments WHERE loan_id = lp.loan_id),
        lp.total_paid = (SELECT IFNULL(SUM(paid_amount), 0) FROM loan_instalments WHERE loan_id = lp.loan_id),
        lp.days_overdue = (SELECT IFNULL(MAX(days_overdue), 0) FROM loan_instalments WHERE loan_id = lp.loan_id AND payment_date IS NULL)
        WHERE lp.loan_id = $loan_id");

    // 2. Update outstanding balances
    // total_outstanding should be the SUM of balance_remaining from all installments
    $sync_q = "UPDATE loan_portfolio lp SET 
               lp.principal_outstanding = GREATEST(0, (SELECT IFNULL(SUM(principal_amount), 0) FROM loan_instalments WHERE loan_id = lp.loan_id) - lp.total_principal_paid),
               lp.interest_outstanding  = GREATEST(0, (SELECT IFNULL(SUM(interest_amount), 0)  FROM loan_instalments WHERE loan_id = lp.loan_id) - lp.total_interest_paid),
               lp.total_outstanding     = (SELECT IFNULL(SUM(balance_remaining), 0)        FROM loan_instalments WHERE loan_id = lp.loan_id),
               lp.updated_at            = NOW()
               WHERE lp.loan_id = ?";
    $sync_st = $conn->prepare($sync_q);
    $sync_st->bind_param("i", $loan_id);
    $sync_st->execute();
    $sync_st->close();
}
?>