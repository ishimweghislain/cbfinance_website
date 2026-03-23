<?php
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$conn = getConnection();

// Get report type
$report_type = isset($_GET['type']) ? $_GET['type'] : 'trial_balance';

// Handle date range filters
$start_date = !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Validate/Swap date range BEFORE any calculations
if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

$query_end_date = $end_date . ' 23:59:59';

// Helper functions
function formatMoney($amount, $decimals = 0) {
    return number_format(round($amount, $decimals), $decimals, '.', ',');
}

function roundAmount($amount, $decimals = 2) {
    return round($amount, $decimals);
}

// ========================================
// CORE FUNCTION: Calculate Trial Balance
// ========================================
function calculateTrialBalance($conn, $start_date, $end_date) {
    $trial_data = [];
    $query_end_date = $end_date . ' 23:59:59';
    
    // Get all accounts from chart_of_accounts
    $accounts_sql = "SELECT account_code, account_name, class, normal_balance 
                    FROM chart_of_accounts 
                    WHERE is_active = 1 
                    ORDER BY class, account_code";
    $accounts_result = mysqli_query($conn, $accounts_sql);
    
    if (!$accounts_result) {
        error_log("Error fetching accounts: " . mysqli_error($conn));
        return [];
    }

    $all_accounts = [];
    while ($account = mysqli_fetch_assoc($accounts_result)) {
        $all_accounts[] = $account;
    }

    // Ensure Disbursement Fee account (4202) exists in our working list
    $has_4202 = false;
    foreach ($all_accounts as $acc) { if ($acc['account_code'] === '4202') $has_4202 = true; }
    if (!$has_4202) {
        $all_accounts[] = [
            'account_code' => '4202',
            'account_name' => 'Disbursement Management Fee Income',
            'class' => 'Fee Income',
            'normal_balance' => 'Credit',
            'is_active' => 1
        ];
    }
    
    foreach ($all_accounts as $account) {
        $account_code = mysqli_real_escape_string($conn, $account['account_code']);
        $account_name = $account['account_name'];
        $class = $account['class'];
        $normal_balance = $account['normal_balance'];
        
        // Filter out specifically "Deferred VAT" accounts as requested
        if ($account_code === '2403' || $account_code === '2406' || 
            stripos($account_name, 'Deferred VAT') !== false) {
            continue;
        }
        
        // ==========================================
        // CUSTOM LOGIC: Paid-Basis Income Correction for Accounts 4101, 4201, 4202, 4205
        // Users want these accounts to reflect ACTUAL PAYMENTS (from loan_instalments)
        // EXCEPT Account 4202 which comes from loan_portfolio (Disbursement Fee)
        // ==========================================
        if (in_array($account_code, ['4101', '4201', '4202', '4205', '4301'])) {
            
            $initial_balance = 0;
            $period_debit = 0;
            $period_credit = 0;

            if ($account_code === '4202') {
                // Disbursement Fee (One-time, upfront) from loan_portfolio
                // Rule: Only counts if the very first instalment has a management fee = 0
                $res_open = mysqli_query($conn, "SELECT SUM(management_fee_amount) as op FROM loan_portfolio lp WHERE disbursement_date < '$start_date' AND (SELECT management_fee FROM loan_instalments WHERE loan_id = lp.loan_id AND instalment_number = 1 LIMIT 1) = 0");
                if ($res_open && $row_open = mysqli_fetch_assoc($res_open)) {
                    $initial_balance = -roundAmount(floatval($row_open['op'] ?? 0));
                }
                
                $res_move = mysqli_query($conn, "SELECT SUM(management_fee_amount) as mp FROM loan_portfolio lp WHERE disbursement_date BETWEEN '$start_date 00:00:00' AND '$query_end_date' AND IFNULL((SELECT management_fee FROM loan_instalments WHERE loan_id = lp.loan_id AND instalment_number = 1 LIMIT 1), 0) = 0");
                if ($res_move && $row_move = mysqli_fetch_assoc($res_move)) {
                    $period_credit = roundAmount(floatval($row_move['mp'] ?? 0));
                }
            } elseif (in_array($account_code, ['4101', '4201', '4205'])) {
                // Accounts from loan_instalments table
                // Interest 4101, Periodic Mgmt Fee 4201, Penalties 4205
                $exp_col = ''; $paid_col = '';
                if ($account_code === '4101') { $exp_col = 'interest_amount'; $paid_col = 'interest_paid'; }
                elseif ($account_code === '4201') { $exp_col = 'management_fee'; $paid_col = 'management_fee_paid'; }
                elseif ($account_code === '4205') { $exp_col = ''; $paid_col = 'penalty_paid'; } // Penalty: collected only
                
                $calc_field = $exp_col ? "CASE WHEN balance_remaining <= 0 THEN $exp_col ELSE $paid_col END" : "$paid_col";
                
                $res_open = mysqli_query($conn, "SELECT SUM($calc_field) as op FROM loan_instalments WHERE payment_date < '$start_date 00:00:00'");
                if ($res_open && $row_open = mysqli_fetch_assoc($res_open)) {
                    $initial_balance = -roundAmount(floatval($row_open['op'] ?? 0));
                }
                
                $res_move = mysqli_query($conn, "SELECT SUM($calc_field) as mp FROM loan_instalments WHERE payment_date BETWEEN '$start_date 00:00:00' AND '$query_end_date 23:59:59'");
                if ($res_move && $row_move = mysqli_fetch_assoc($res_move)) {
                    $period_credit = roundAmount(floatval($row_move['mp'] ?? 0));
                }
            } elseif ($account_code === '4301') {
                // For 4301 fallback to ledger
                $res_open = mysqli_query($conn, "SELECT SUM(credit_amount - debit_amount) as op FROM ledger WHERE account_code = '$account_code' AND transaction_date < '$start_date'");
                if ($res_open && $row_open = mysqli_fetch_assoc($res_open)) {
                    $initial_balance = -roundAmount(floatval($row_open['op'] ?? 0));
                }
                
                $res_move = mysqli_query($conn, "SELECT SUM(debit_amount) as d, SUM(credit_amount) as c FROM ledger WHERE account_code = '$account_code' AND transaction_date BETWEEN '$start_date' AND '$query_end_date'");
                if ($res_move && $row_move = mysqli_fetch_assoc($res_move)) {
                    $period_debit = roundAmount(floatval($row_move['d'] ?? 0));
                    $period_credit = roundAmount(floatval($row_move['c'] ?? 0));
                }
            }
            
            $closing_balance = $initial_balance + $period_debit - $period_credit;
            
        } else {
            // ==========================================
            // STEP 1: Get OPENING BALANCE (before start_date)
            // ==========================================
            $opening_sql = "SELECT 
                            SUM(debit_amount) as total_debit,
                            SUM(credit_amount) as total_credit
                            FROM ledger 
                            WHERE account_code = '$account_code' 
                            AND transaction_date < '$start_date'";
            $opening_result = mysqli_query($conn, $opening_sql);
            
            $opening_debit = 0;
            $opening_credit = 0;
            if ($opening_result && mysqli_num_rows($opening_result) > 0) {
                $row = mysqli_fetch_assoc($opening_result);
                $opening_debit = roundAmount(floatval($row['total_debit'] ?? 0));
                $opening_credit = roundAmount(floatval($row['total_credit'] ?? 0));
            }
            
            // Calculate initial balance (opening balance)
            $initial_balance = $opening_debit - $opening_credit;
            $initial_balance = roundAmount($initial_balance);
            
            // ==========================================
            // STEP 2: Get PERIOD MOVEMENTS (between start and end date)
            // ==========================================
            $movement_sql = "SELECT 
                             SUM(debit_amount) as period_debit,
                             SUM(credit_amount) as period_credit
                             FROM ledger 
                             WHERE account_code = '$account_code' 
                             AND transaction_date BETWEEN '$start_date' AND '$query_end_date'";
            $movement_result = mysqli_query($conn, $movement_sql);
            
            $period_debit = 0;
            $period_credit = 0;
            if ($movement_result && mysqli_num_rows($movement_result) > 0) {
                $row = mysqli_fetch_assoc($movement_result);
                $period_debit = roundAmount(floatval($row['period_debit'] ?? 0));
                $period_credit = roundAmount(floatval($row['period_credit'] ?? 0));
            }
            
            // ==========================================
            // STEP 3: Calculate CLOSING BALANCE
            // ==========================================
            // Closing Balance = Initial Balance + Period Debit - Period Credit
            $closing_balance = $initial_balance + $period_debit - $period_credit;
            $closing_balance = roundAmount($closing_balance);
        }
        
        $trial_data[] = [
            'account_code' => $account_code,
            'account_name' => $account_name,
            'class' => $class,
            'normal_balance' => $normal_balance,
            'initial_balance' => $initial_balance,  // Opening balance
            'period_debit' => $period_debit,       // Movements - Debit
            'period_credit' => $period_credit,     // Movements - Credit
            'closing_balance' => $closing_balance,  // Final balance
            'is_calculated' => false
        ];
    }
    
    return $trial_data;
}

// Get trial balance data first
$trial_data = calculateTrialBalance($conn, $start_date, $end_date);

// ========================================
// Calculate Current Period Earnings/Loss and Retained Earnings (for Trial Balance only)
// ========================================
$total_revenues = 0;
$total_expenses = 0;
$current_period_earnings = 0;
$previous_total_profit_loss = 0;
$retained_earnings = 0;

// Calculate revenues and expenses for CURRENT PERIOD ONLY
foreach ($trial_data as $account) {
    $account_code = $account['account_code'];
    $first_digit = substr($account_code, 0, 1);
    
    // Revenue accounts (4xxx)
    if ($first_digit == '4') {
        $total_revenues += abs($account['period_credit'] - $account['period_debit']);
    }
    
    // Expense accounts (5xxx, 6xxx)
    if ($first_digit == '5' || $first_digit == '6') {
        $total_expenses += abs($account['period_debit'] - $account['period_credit']);
    }
}

// Current Period Earnings/Loss = Total Revenues - Total Expenses
$current_period_earnings = roundAmount($total_revenues - $total_expenses);

// Calculate TOTAL PROFIT/LOSS (from beginning of time to end_date)
$escaped_end_date = mysqli_real_escape_string($conn, $end_date);
$total_profit_loss_sql = "SELECT 
    SUM(CASE WHEN SUBSTRING(account_code, 1, 1) = '4' THEN credit_amount - debit_amount ELSE 0 END) as total_revenue,
    SUM(CASE WHEN SUBSTRING(account_code, 1, 1) IN ('5', '6') THEN debit_amount - credit_amount ELSE 0 END) as total_expense
    FROM ledger 
    WHERE transaction_date <= '$query_end_date'";

$total_pl_result = mysqli_query($conn, $total_profit_loss_sql);
if ($total_pl_result && mysqli_num_rows($total_pl_result) > 0) {
    $pl_row = mysqli_fetch_assoc($total_pl_result);
    $cumulative_revenue = roundAmount(floatval($pl_row['total_revenue'] ?? 0));
    $cumulative_expense = roundAmount(floatval($pl_row['total_expense'] ?? 0));
    $previous_total_profit_loss = roundAmount($cumulative_revenue - $cumulative_expense);
}

// Retained Earnings = Total Profit/Loss - Current Period Earnings/Loss
$retained_earnings = roundAmount($previous_total_profit_loss - $current_period_earnings);

// Define class order for TRIAL BALANCE (exact order as specified)
$trial_balance_class_order = [
    'Assets' => 1,
    'Fixed Assets' => 2,
    'Liabilities' => 3,
    'Liabilites' => 3, // Handle typo in data
    'Equity' => 4,
    'Revenue' => 5,
    'Fee Income' => 6,
    'Fee Income ' => 6, // Handle extra space
    'Operating Expenses' => 7,
    'Operating  Expenses' => 7, // Handle double space
    'Board Sitting Allowances Expenses' => 8,
    'Board Sitting Allowances Expenses ' => 8, // Handle extra space
    'Professional & Legal Fees Expenses' => 9,
    'Financial Expenses' => 10,
    'Depreciation & Provisions Expense' => 11
];

// Sort trial balance data by class order
usort($trial_data, function($a, $b) use ($trial_balance_class_order) {
    $a_class = $a['class'];
    $b_class = $b['class'];
    $a_order = isset($trial_balance_class_order[$a_class]) ? $trial_balance_class_order[$a_class] : 999;
    $b_order = isset($trial_balance_class_order[$b_class]) ? $trial_balance_class_order[$b_class] : 999;
    
    if ($a_order == $b_order) {
        return strcmp($a['account_code'], $b['account_code']);
    }
    return $a_order - $b_order;
});

// Now insert the calculated accounts BETWEEN Balance Sheet (1,2,3) and Income Statement (4,5,6) sections
// Find the position after last Equity account
$insert_position = 0;
foreach ($trial_data as $index => $account) {
    $first_digit = substr($account['account_code'], 0, 1);
    if ($first_digit == '3') {
        $insert_position = $index + 1;
    }
}

// Remove any existing 3101 or 3102 accounts to avoid duplicates
$trial_data = array_filter($trial_data, function($account) {
    return !in_array($account['account_code'], ['3101', '3102']);
});
$trial_data = array_values($trial_data); // Re-index array

// Recalculate insert position after filtering
$insert_position = 0;
foreach ($trial_data as $index => $account) {
    $first_digit = substr($account['account_code'], 0, 1);
    if ($first_digit == '3') {
        $insert_position = $index + 1;
    }
}

// Insert Retained Earnings first (so it appears before Current Period Earnings after insertion)
array_splice($trial_data, $insert_position, 0, [[
    'account_code' => '3102',
    'account_name' => 'Retained Earnings',
    'class' => 'Equity',
    'normal_balance' => 'Credit',
    'initial_balance' => 0,
    'period_debit' => 0,
    'period_credit' => 0,
    'closing_balance' => -$retained_earnings,  // Negative because it's equity (credit)
    'is_calculated' => true
]]);

// Insert Current Period Earnings/Loss after Retained Earnings
array_splice($trial_data, $insert_position + 1, 0, [[
    'account_code' => '3101',
    'account_name' => 'Current Period Earnings/Loss',
    'class' => 'Equity',
    'normal_balance' => 'Credit',
    'initial_balance' => 0,
    'period_debit' => 0,
    'period_credit' => 0,
    'closing_balance' => -$current_period_earnings,  // Negative because it's equity (credit)
    'is_calculated' => true
]]);

// Initialize report variables
$report_data = [];
$report_title = "";
$total_assets = 0;
$total_liabilities = 0;
$total_equity = 0;
$total_revenue = 0;
$total_expenses = 0;
$net_income = 0;

// Variables for balance sheet two-column layout
$assets_data = [];
$liabilities_data = [];
$equity_data = [];

// ========================================
// Calculate Net Income from Trial Balance
// ========================================
$net_income_from_trial = 0;
foreach ($trial_data as $account) {
    $class = $account['class'];
    $closing_balance = $account['closing_balance'];
    $first_digit = substr($account['account_code'], 0, 1);
    
    // Revenue accounts (4xxx) - Credits increase income
    if ($first_digit == '4' || stripos($class, 'Revenue') !== false || stripos($class, 'Fee Income') !== false) {
        $net_income_from_trial -= $closing_balance; // Negative balance = revenue (credit)
    }
    
    // Expense accounts (5xxx, 6xxx) - Debits increase expenses
    if ($first_digit == '5' || $first_digit == '6' || 
        stripos($class, 'Expenses') !== false || stripos($class, 'Expense') !== false) {
        $net_income_from_trial -= $closing_balance; // Positive balance = expense (debit)
    }
}
$net_income_from_trial = roundAmount($net_income_from_trial);

// ========================================
// Generate Reports Based on Type
// ========================================
switch ($report_type) {
    case 'trial_balance':
        $report_title = "Trial Balance";
        $report_data = $trial_data;
        break;
        
    case 'balance_sheet':
        $report_title = "Balance Sheet";
        
        // Separate arrays for each section
        $assets_data = [];
        $liabilities_data = [];
        $equity_data = [];
        
        // Balance Sheet: Assets (1), Liabilities (2), Equity (3)
        foreach ($trial_data as $account) {
            $first_digit = substr($account['account_code'], 0, 1);
            
            $closing_balance = $account['closing_balance'];
            
            // Separate into categories
            if ($first_digit == '1') {
                $assets_data[] = $account;
                $total_assets += $closing_balance;
            } elseif ($first_digit == '2') {
                $liabilities_data[] = $account;
                $total_liabilities += $closing_balance;
            } elseif ($first_digit == '3') {
                $equity_data[] = $account;
                $total_equity += $closing_balance;
            }
        }
        
        // Sort each section by class first, then account code — keeps groups together
        usort($assets_data, function($a, $b) {
            $cls = strcmp(trim($a['class']), trim($b['class']));
            return $cls !== 0 ? $cls : strcmp($a['account_code'], $b['account_code']);
        });
        usort($liabilities_data, function($a, $b) {
            $cls = strcmp(trim($a['class']), trim($b['class']));
            return $cls !== 0 ? $cls : strcmp($a['account_code'], $b['account_code']);
        });
        usort($equity_data, function($a, $b) {
            $cls = strcmp(trim($a['class']), trim($b['class']));
            return $cls !== 0 ? $cls : strcmp($a['account_code'], $b['account_code']);
        });
        
        // Store in report_data for compatibility
        $report_data = array_merge($assets_data, $equity_data, $liabilities_data);
        break;
        
    case 'income_statement':
        $report_title = "Income Statement";
        
        // Fetch ALL customers and their consolidated revenue activity in the period (Unified Table)
        // Using loan_payments table to match ledger totals exactly
        $unified_revenue = [];
        $sql_unified = "SELECT 
            c.customer_id, 
            c.customer_name,
            -- Use the SAME capped logic as the Trial Balance for Interest, Management Fees and Penalties
            (SELECT SUM(CASE WHEN li.balance_remaining <= 0 THEN li.interest_amount ELSE li.interest_paid END) 
             FROM loan_instalments li 
             JOIN loan_portfolio lp2 ON li.loan_id = lp2.loan_id 
             WHERE lp2.customer_id = c.customer_id AND li.payment_date BETWEEN '$start_date 00:00:00' AND '$query_end_date 23:59:59') as int_pd,
            
            (SELECT SUM(CASE WHEN li.balance_remaining <= 0 THEN li.management_fee ELSE li.management_fee_paid END) 
             FROM loan_instalments li 
             JOIN loan_portfolio lp2 ON li.loan_id = lp2.loan_id 
             WHERE lp2.customer_id = c.customer_id AND li.payment_date BETWEEN '$start_date 00:00:00' AND '$query_end_date 23:59:59') as mgmt_pd,
            
            (SELECT SUM(li.penalty_paid) 
             FROM loan_instalments li 
             JOIN loan_portfolio lp2 ON li.loan_id = lp2.loan_id 
             WHERE lp2.customer_id = c.customer_id AND li.payment_date BETWEEN '$start_date 00:00:00' AND '$query_end_date 23:59:59') as pen_pd,
            
            -- Disbursement Fee (Upfront fees)
            (SELECT SUM(lp2.management_fee_amount) 
             FROM loan_portfolio lp2 
             WHERE lp2.customer_id = c.customer_id 
               AND lp2.disbursement_date BETWEEN '$start_date 00:00:00' AND '$query_end_date' 
               AND IFNULL((SELECT management_fee FROM loan_instalments WHERE loan_id = lp2.loan_id AND instalment_number = 1 LIMIT 1), 0) = 0) as disb_pd,
            
            (SELECT SUM(af.amount) 
             FROM application_fees af 
             WHERE af.customer_id = c.customer_id AND af.status = 'Paid' AND af.transaction_date BETWEEN '$start_date' AND '$query_end_date') as app_pd,
            
            COALESCE((SELECT lp3.interest_outstanding FROM loan_portfolio lp3 WHERE lp3.customer_id = c.customer_id AND lp3.loan_status != 'Closed' ORDER BY lp3.loan_id DESC LIMIT 1), 0) as int_bal,
            COALESCE((SELECT lp3.principal_outstanding FROM loan_portfolio lp3 WHERE lp3.customer_id = c.customer_id AND lp3.loan_status != 'Closed' ORDER BY lp3.loan_id DESC LIMIT 1), 0) as princ_bal,
            COALESCE((SELECT lp3.management_fee_amount - lp3.total_management_fees_paid FROM loan_portfolio lp3 WHERE lp3.customer_id = c.customer_id AND lp3.loan_status != 'Closed' ORDER BY lp3.loan_id DESC LIMIT 1), 0) as mgmt_bal
            FROM customers c
            ORDER BY c.customer_name";
            
        $res_unified = mysqli_query($conn, $sql_unified);
        if ($res_unified) {
            while ($r = mysqli_fetch_assoc($res_unified)) {
                $unified_revenue[] = $r;
            }
        }

        // Income Statement: Revenue (4), Expenses (5, 6)
        foreach ($trial_data as &$account) {
            $first_digit = substr($account['account_code'], 0, 1);
            
            // Only include income statement accounts
            if ($first_digit == '4' || $first_digit == '5' || $first_digit == '6') {
                
                // Calculate display balance using PERIOD movement for Income Statement
                $period_move = abs($account['period_credit'] - $account['period_debit']);
                $account['display_balance'] = $period_move;
                
                $report_data[] = $account;
                
                // Calculate totals
                if ($first_digit == '4') {
                    $total_revenue += $period_move;
                } elseif ($first_digit == '5' || $first_digit == '6') {
                    $total_expenses += $period_move;
                }
            }
        }
        
        // Calculate net income
        $net_income = $total_revenue - $total_expenses;
        $net_income = roundAmount($net_income);
        
        break;
        
    case 'income_analysis':
        $report_title = "Income Analysis (By Customer)";
        
        // Fetch detailed loan income data — per loan, using loan_instalments
        // Also includes disbursement management fee from loan_portfolio
        $analysis_sql = "SELECT 
            lp.loan_id, 
            lp.loan_number, 
            c.customer_name,
            
            -- Paid during the selected period (CAPPED to expected if fully paid)
            SUM(CASE WHEN li.payment_date BETWEEN '$start_date' AND '$query_end_date' THEN 
                 (CASE WHEN li.balance_remaining <= 0 THEN li.interest_amount ELSE li.interest_paid END)
            ELSE 0 END) as period_interest_paid,
            
            SUM(CASE WHEN li.payment_date BETWEEN '$start_date' AND '$query_end_date' THEN 
                 (CASE WHEN li.balance_remaining <= 0 THEN li.management_fee ELSE li.management_fee_paid END)
            ELSE 0 END) as period_fee_paid,
            
            SUM(CASE WHEN li.payment_date BETWEEN '$start_date 00:00:00' AND '$query_end_date 23:59:59' THEN 
                 li.penalty_paid
            ELSE 0 END) as period_penalty_paid,
            
            -- Disbursement Management Fee: ONLY if instalment 1 mgmt fee is 0 or NULL
            CASE WHEN IFNULL((SELECT management_fee FROM loan_instalments WHERE loan_id = lp.loan_id AND instalment_number = 1 LIMIT 1), 0) = 0 THEN 
                 (CASE WHEN lp.disbursement_date BETWEEN '$start_date 00:00:00' AND '$query_end_date' THEN lp.management_fee_amount ELSE 0 END)
            ELSE 0 END as disb_fee_period,
            
            CASE WHEN IFNULL((SELECT management_fee FROM loan_instalments WHERE loan_id = lp.loan_id AND instalment_number = 1 LIMIT 1), 0) = 0 THEN 
                 lp.management_fee_amount
            ELSE 0 END as total_disb_fee,
            
            -- Totals to date (all payments EVER made on this loan, capped if fully paid)
            SUM(CASE WHEN li.balance_remaining <= 0 THEN li.interest_amount ELSE li.interest_paid END) as total_interest_paid,
            SUM(CASE WHEN li.balance_remaining <= 0 THEN li.management_fee ELSE li.management_fee_paid END) as total_fee_paid,
            SUM(CASE WHEN li.balance_remaining <= 0 THEN li.penalty_amount ELSE li.penalty_paid END) as total_penalty_paid,
            
            -- Total expected for comparison
            SUM(li.interest_amount) as total_interest_exp,
            SUM(li.management_fee) as total_fee_exp,
            SUM(li.penalty_amount) as total_penalty_exp
            
            FROM loan_portfolio lp
            JOIN customers c ON lp.customer_id = c.customer_id
            LEFT JOIN loan_instalments li ON lp.loan_id = li.loan_id
            GROUP BY lp.loan_id
            ORDER BY c.customer_name";
            
        $analysis_res = mysqli_query($conn, $analysis_sql);
        while ($row = mysqli_fetch_assoc($analysis_res)) {
            $report_data[] = $row;
        }
        break;
        
    default:
        $report_title = "Trial Balance";
        $report_data = $trial_data;
        break;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ===== BASE ===== */
        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .report-header {
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .table th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
        }
        .amount-cell {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        .positive-balance { color: #198754; }
        .negative-balance { color: #dc3545; }
        .group-header {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .total-row {
            background-color: #f8f9fa !important;
            font-weight: bold;
            border-top: 2px solid #000;
        }
        .grand-total-row {
            background-color: #e9ecef !important;
            font-weight: bold;
            border-top: 3px double #000;
            border-bottom: 3px double #000;
        }
        .account-code {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .timeframe-filter {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .nav-tabs .nav-link.active {
            background-color: #f8f9fa;
            border-bottom-color: #f8f9fa;
            font-weight: bold;
        }
        .quick-date-btn {
            border-radius: 5px;
            margin: 2px;
        }
        .financial-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            border: 1px solid #dee2e6;
        }
        .accounting-equation {
            background: #d1ecf1;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            border: 1px solid #bee5eb;
        }

        /* ===================================
           BALANCE SHEET — ENHANCED CLARITY
        ==================================== */
        .bs-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border: 2px solid #c8d0e0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        }

        .bs-col {
            display: flex;
            flex-direction: column;
            background: #fff;
            min-width: 0;
        }

        .bs-col:first-child {
            border-right: 2px solid #c8d0e0;
        }

        /* Section title bar */
        .bs-section-title {
            padding: 14px 18px;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .bs-section-title.assets-title  { background: linear-gradient(90deg, #1a6fc4, #2196f3); }
        .bs-section-title.equity-title  { background: linear-gradient(90deg, #5c35a8, #7c4dff); }
        .bs-section-title.liab-title    { background: linear-gradient(90deg, #b5460a, #e65100); }

        /* Subsection label (e.g. "Fixed Assets") */
        .bs-sub-label {
            background: #eef3fb;
            border-left: 4px solid #2196f3;
            padding: 7px 16px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #1a3a5c;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-top: 2px;
        }

        .bs-sub-label.equity-sub  { border-left-color: #7c4dff; background: #f3f0ff; color: #3d1f8a; }
        .bs-sub-label.liab-sub    { border-left-color: #e65100; background: #fff3ee; color: #7a2800; }

        /* Individual account row */
        .bs-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 7px 18px;
            border-bottom: 1px solid #f0f3f8;
            font-size: 0.875rem;
            transition: background 0.15s;
        }
        .bs-row:hover { background: #f7f9ff; }

        .bs-row .acct-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }

        .bs-row .acct-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #2c3e50;
        }

        .bs-row .acct-amount {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1a6fc4;
            white-space: nowrap;
            padding-left: 12px;
        }

        .bs-row .acct-amount.equity-amt { color: #5c35a8; }
        .bs-row .acct-amount.liab-amt   { color: #b5460a; }

        /* Subtotal row (within a section) */
        .bs-subtotal {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 18px;
            background: #e8f0fd;
            border-top: 1px solid #b8cfee;
            border-bottom: 1px solid #b8cfee;
            font-size: 0.85rem;
            font-weight: 700;
            color: #1a3a5c;
            margin-top: 4px;
        }
        .bs-subtotal.equity-sub-total  { background: #ede8ff; border-color: #c5b5f5; color: #3d1f8a; }
        .bs-subtotal.liab-sub-total    { background: #ffeee6; border-color: #f5c4a8; color: #7a2800; }

        /* Grand total bar */
        .bs-grand-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 18px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #fff;
            margin-top: auto;
        }
        .bs-grand-total.assets-total   { background: linear-gradient(90deg, #1a6fc4, #2196f3); }
        .bs-grand-total.equity-total   { background: linear-gradient(90deg, #5c35a8, #7c4dff); }
        .bs-grand-total.liab-total     { background: linear-gradient(90deg, #b5460a, #e65100); }
        .bs-grand-total.el-total       { background: linear-gradient(90deg, #2e7d32, #43a047); }

        /* Spacer between equity and liabilities in right column */
        .bs-section-gap { height: 16px; background: #f0f2f5; }

        /* balance sheet old styles kept for compatibility */
        .balance-sheet-table { width: 100%; table-layout: fixed; }
        .balance-sheet-table td { vertical-align: top; padding: 0 10px; width: 50%; }

        /* ===================================
           INCOME STATEMENT — ENHANCED CLARITY
        ==================================== */
        .is-wrapper {
            border: 2px solid #c8d0e0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        }

        /* Two-column income statement layout */
        .is-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            align-items: start;
        }

        .is-col {
            background: #fff;
            min-width: 0;
        }

        .is-col:first-child {
            border-right: 2px solid #c8d0e0;
        }

        /* Section header */
        .is-section-title {
            padding: 14px 18px;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .is-section-title.rev-title  { background: linear-gradient(90deg, #1a6fc4, #2196f3); }
        .is-section-title.exp-title  { background: linear-gradient(90deg, #b5460a, #e65100); }

        /* Subsection label */
        .is-sub-label {
            padding: 7px 16px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-top: 2px;
        }
        .is-sub-label.rev-sub  { background: #eef3fb; border-left: 4px solid #2196f3; color: #1a3a5c; }
        .is-sub-label.exp-sub  { background: #fff3ee; border-left: 4px solid #e65100; color: #7a2800; }

        /* Account row */
        .is-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 7px 18px;
            border-bottom: 1px solid #f0f3f8;
            font-size: 0.875rem;
            transition: background 0.15s;
        }
        .is-row:hover { background: #f7f9ff; }

        .is-row .acct-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }

        .is-row .acct-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #2c3e50;
        }

        .is-row .acct-amount {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            font-weight: 600;
            white-space: nowrap;
            padding-left: 12px;
        }
        .is-row .acct-amount.rev-amt { color: #1a6fc4; }
        .is-row .acct-amount.exp-amt { color: #b5460a; }

        /* Class subtotal */
        .is-class-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 18px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-top: 4px;
        }
        .is-class-total.rev-class-total { background: #e8f0fd; border-top: 1px solid #b8cfee; color: #1a3a5c; }
        .is-class-total.exp-class-total { background: #ffeee6; border-top: 1px solid #f5c4a8; color: #7a2800; }

        /* Section grand total */
        .is-section-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 18px;
            font-size: 0.88rem;
            font-weight: 700;
            color: #fff;
        }
        .is-section-total.rev-total { background: linear-gradient(90deg, #1a6fc4, #2196f3); }
        .is-section-total.exp-total { background: linear-gradient(90deg, #b5460a, #e65100); }

        /* Net income / loss banner */
        .is-net-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
        }
        .is-net-bar.profit { background: linear-gradient(90deg, #2e7d32, #43a047); }
        .is-net-bar.loss   { background: linear-gradient(90deg, #b71c1c, #e53935); }
        .is-net-bar .net-label { font-size: 0.82rem; letter-spacing: 0.1em; text-transform: uppercase; opacity: 0.85; }
        .is-net-bar .net-amount { font-size: 1.25rem; }

        /* ===== SHARED UTILITIES ===== */
        .account-code {
            font-family: 'Courier New', monospace;
            background: #f0f3f8;
            padding: 1px 5px;
            border-radius: 3px;
            border: 1px solid #d0d8e8;
            font-size: 0.78rem;
            white-space: nowrap;
        }

        @media print {
            .no-print { display: none; }
            body { background: #fff; }
            .bs-wrapper, .is-wrapper { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Report Header -->
        <div class="row mb-4 no-print">
            <div class="col-12">
                <h2 class="h4 fw-bold text-primary">Financial Reports</h2>
                <p class="text-muted">Generate and view financial reports from your General Ledger</p>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="row mb-3 no-print">
            <div class="col-12">
                <div class="timeframe-filter">
                    <h5><i class="fas fa-calendar-alt me-2"></i>Report Period</h5>
                    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3 align-items-end" id="reportFilter">
                        <?php
                        // Preserve ALL existing GET params except the ones we control, so the router doesn't lose its page key
                        foreach ($_GET as $key => $value) {
                            if (!in_array($key, ['type', 'start_date', 'end_date'])) {
                                echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                            }
                        }
                        // If no 'page' param exists in GET (direct file access), still emit it
                        if (!isset($_GET['page'])) {
                            echo '<input type="hidden" name="page" value="financial_reports">';
                        }
                        ?>
                        <input type="hidden" name="type" id="report_type_input" value="<?php echo htmlspecialchars($report_type); ?>">
                        
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" id="finStartDate"
                                   onchange="this.form.submit()"
                                   value="<?php echo htmlspecialchars($start_date); ?>" required>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" id="finEndDate"
                                   onchange="this.form.submit()"
                                   value="<?php echo htmlspecialchars($end_date); ?>" required>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filter
                            </button>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Quick Select:</label>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary quick-date-btn" onclick="setDateRange('today')">
                                    Today
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary quick-date-btn" onclick="setDateRange('month')">
                                    This Month
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary quick-date-btn" onclick="setDateRange('year')">
                                    This Year
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Report Navigation Tabs -->
        <div class="row mb-4 no-print">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="reportTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $report_type == 'trial_balance' ? 'active' : ''; ?>" 
                                        type="button" onclick="switchReportType('trial_balance')">
                                    <i class="fas fa-balance-scale me-1"></i> Trial Balance
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $report_type == 'balance_sheet' ? 'active' : ''; ?>" 
                                        type="button" onclick="switchReportType('balance_sheet')">
                                    <i class="fas fa-file-invoice me-1"></i> Balance Sheet
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $report_type == 'income_statement' ? 'active' : ''; ?>" 
                                        type="button" onclick="switchReportType('income_statement')">
                                    <i class="fas fa-chart-line me-1"></i> Income Statement
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $report_type == 'income_analysis' ? 'active' : ''; ?>" 
                                        type="button" onclick="switchReportType('income_analysis')">
                                    <i class="fas fa-search-dollar me-1"></i> Income Analysis (Customer)
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Content -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center no-print">
                        <h5 class="mb-0">
                            <i class="fas <?php 
                                echo $report_type == 'trial_balance' ? 'fa-balance-scale' : 
                                     ($report_type == 'balance_sheet' ? 'fa-file-invoice' : 'fa-chart-line'); 
                            ?> me-2"></i>
                            <?php echo htmlspecialchars($report_title); ?>
                        </h5>
                        <div>
                            <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button class="btn btn-outline-primary btn-sm ms-2" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i> Export to Excel
                            </button>
                            <button class="btn btn-outline-danger btn-sm ms-2" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf"></i> Export to PDF
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Report Header Info -->
                        <div class="report-header text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <h4 class="text-white">Accounting & Loan Management System</h4>
                            <h5 class="text-white"><?php echo htmlspecialchars($report_title); ?></h5>
                            <p class="text-white">
                                <?php if ($report_type == 'balance_sheet'): ?>
                                    As of <?php echo date('F d, Y', strtotime($end_date)); ?>
                                <?php else: ?>
                                    Period: <?php echo date('F d, Y', strtotime($start_date)); ?> 
                                    to <?php echo date('F d, Y', strtotime($end_date)); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <?php if (empty($report_data)): ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> No data found for the selected period.
                        </div>
                        <?php else: ?>
                        
                        <?php if ($report_type == 'balance_sheet'): ?>
                        <!-- =============================================
                             BALANCE SHEET — ENHANCED TWO-COLUMN LAYOUT
                        ============================================== -->

                        <?php
                        // Pre-group each section by class for clean rendering
                        $asset_groups = [];
                        foreach ($assets_data as $row) {
                            $cls = trim($row['class']) ?: 'Assets';
                            if (!isset($asset_groups[$cls])) $asset_groups[$cls] = ['rows' => [], 'subtotal' => 0];
                            $asset_groups[$cls]['rows'][]    = $row;
                            $asset_groups[$cls]['subtotal'] += $row['closing_balance'];
                        }

                        $equity_groups = [];
                        foreach ($equity_data as $row) {
                            $cls = trim($row['class']) ?: 'Equity';
                            if (!isset($equity_groups[$cls])) $equity_groups[$cls] = ['rows' => [], 'subtotal' => 0];
                            $equity_groups[$cls]['rows'][]    = $row;
                            $equity_groups[$cls]['subtotal'] += $row['closing_balance'];
                        }

                        $liability_groups = [];
                        foreach ($liabilities_data as $row) {
                            $cls = trim($row['class']) ?: 'Liabilities';
                            if (!isset($liability_groups[$cls])) $liability_groups[$cls] = ['rows' => [], 'subtotal' => 0];
                            $liability_groups[$cls]['rows'][]    = $row;
                            $liability_groups[$cls]['subtotal'] += $row['closing_balance'];
                        }
                        ?>

                        <div class="mt-4 bs-wrapper" id="reportTable">

                            <!-- LEFT COLUMN: ASSETS -->
                            <div class="bs-col">
                                <div class="bs-section-title assets-title">
                                    <i class="fas fa-landmark"></i> Assets
                                </div>

                                <?php foreach ($asset_groups as $group_name => $group): ?>
                                <div class="bs-sub-label"><?php echo htmlspecialchars($group_name); ?></div>

                                <?php foreach ($group['rows'] as $asset): ?>
                                <div class="bs-row">
                                    <div class="acct-info">
                                        <span class="account-code"><?php echo htmlspecialchars($asset['account_code']); ?></span>
                                        <span class="acct-name"><?php echo htmlspecialchars($asset['account_name']); ?></span>
                                    </div>
                                    <span class="acct-amount"><?php echo formatMoney($asset['closing_balance']); ?></span>
                                </div>
                                <?php endforeach; // end rows ?>

                                <?php if (count($asset_groups) > 1): // only show subtotal when there are multiple groups ?>
                                <div class="bs-subtotal">
                                    <span><?php echo htmlspecialchars($group_name); ?> Subtotal</span>
                                    <span><?php echo formatMoney($group['subtotal']); ?></span>
                                </div>
                                <?php endif; ?>

                                <?php endforeach; // end asset groups ?>

                                <div class="bs-grand-total assets-total">
                                    <span><i class="fas fa-sigma me-1"></i> TOTAL ASSETS</span>
                                    <span><?php echo formatMoney($total_assets); ?></span>
                                </div>
                            </div>

                            <!-- RIGHT COLUMN: EQUITY + LIABILITIES -->
                            <div class="bs-col">

                                <!-- EQUITY -->
                                <div class="bs-section-title equity-title">
                                    <i class="fas fa-user-tie"></i> Owner's Equity
                                </div>

                                <?php foreach ($equity_groups as $group_name => $group): ?>
                                <div class="bs-sub-label equity-sub"><?php echo htmlspecialchars($group_name); ?></div>

                                <?php foreach ($group['rows'] as $equity): ?>
                                <div class="bs-row">
                                    <div class="acct-info">
                                        <span class="account-code"><?php echo htmlspecialchars($equity['account_code']); ?></span>
                                        <span class="acct-name"><?php echo htmlspecialchars($equity['account_name']); ?></span>
                                    </div>
                                    <span class="acct-amount equity-amt"><?php echo formatMoney(abs($equity['closing_balance'])); ?></span>
                                </div>
                                <?php endforeach; // end rows ?>

                                <?php if (count($equity_groups) > 1): ?>
                                <div class="bs-subtotal equity-sub-total">
                                    <span><?php echo htmlspecialchars($group_name); ?> Subtotal</span>
                                    <span><?php echo formatMoney(abs($group['subtotal'])); ?></span>
                                </div>
                                <?php endif; ?>

                                <?php endforeach; // end equity groups ?>

                                <div class="bs-grand-total equity-total">
                                    <span><i class="fas fa-sigma me-1"></i> TOTAL EQUITY</span>
                                    <span><?php echo formatMoney(abs($total_equity)); ?></span>
                                </div>

                                <!-- visual separator -->
                                <div class="bs-section-gap"></div>

                                <!-- LIABILITIES -->
                                <div class="bs-section-title liab-title">
                                    <i class="fas fa-file-invoice-dollar"></i> Liabilities
                                </div>

                                <?php foreach ($liability_groups as $group_name => $group): ?>
                                <div class="bs-sub-label liab-sub"><?php echo htmlspecialchars($group_name); ?></div>

                                <?php foreach ($group['rows'] as $liability): ?>
                                <div class="bs-row">
                                    <div class="acct-info">
                                        <span class="account-code"><?php echo htmlspecialchars($liability['account_code']); ?></span>
                                        <span class="acct-name"><?php echo htmlspecialchars($liability['account_name']); ?></span>
                                    </div>
                                    <span class="acct-amount liab-amt"><?php echo formatMoney(abs($liability['closing_balance'])); ?></span>
                                </div>
                                <?php endforeach; // end rows ?>

                                <?php if (count($liability_groups) > 1): ?>
                                <div class="bs-subtotal liab-sub-total">
                                    <span><?php echo htmlspecialchars($group_name); ?> Subtotal</span>
                                    <span><?php echo formatMoney(abs($group['subtotal'])); ?></span>
                                </div>
                                <?php endif; ?>

                                <?php endforeach; // end liability groups ?>

                                <div class="bs-grand-total liab-total">
                                    <span><i class="fas fa-sigma me-1"></i> TOTAL LIABILITIES</span>
                                    <span><?php echo formatMoney(abs($total_liabilities)); ?></span>
                                </div>

                                <div class="bs-grand-total el-total">
                                    <span><i class="fas fa-equals me-1"></i> TOTAL EQUITY &amp; LIABILITIES</span>
                                    <span><?php echo formatMoney(abs($total_equity) + abs($total_liabilities)); ?></span>
                                </div>
                            </div>
                        </div><!-- /.bs-wrapper -->

                        <?php elseif ($report_type == 'trial_balance'): ?>
                        <!-- TRIAL BALANCE TABLE (unchanged) -->
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-sm table-hover" id="reportTable">
                                <thead class="table-light">
                                    <tr>
                                        <th rowspan="2" style="vertical-align: middle;">Group</th>
                                        <th rowspan="2" style="vertical-align: middle;">Account Code</th>
                                        <th rowspan="2" style="vertical-align: middle;">Account Name</th>
                                        <th colspan="3" class="text-center" style="border-bottom: 2px solid #dee2e6;">Opening Balance</th>
                                        <th colspan="2" class="text-center" style="border-bottom: 2px solid #dee2e6;">Movements</th>
                                        <th colspan="2" class="text-center" style="border-bottom: 2px solid #dee2e6;">Closing Balance</th>
                                    </tr>
                                    <tr>
                                        <th class="text-end">Initial Balance</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                        <th class="text-end">Balance</th>
                                        <th class="text-end">Final</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Initialize totals
                                    $grand_initial_balance = 0;
                                    $grand_initial_debit = 0;
                                    $grand_initial_credit = 0;
                                    $grand_movement_debit = 0;
                                    $grand_movement_credit = 0;
                                    $grand_closing_balance = 0;
                                    $grand_closing_debit = 0;
                                    $grand_closing_credit = 0;
                                    
                                    // Group totals
                                    $current_class = '';
                                    $class_initial_balance = 0;
                                    $class_initial_debit = 0;
                                    $class_initial_credit = 0;
                                    $class_movement_debit = 0;
                                    $class_movement_credit = 0;
                                    $class_closing_balance = 0;
                                    $class_closing_debit = 0;
                                    $class_closing_credit = 0;
                                    
                                    foreach ($report_data as $index => $row): 
                                        $initial_balance = $row['initial_balance'];
                                        $period_debit = $row['period_debit'];
                                        $period_credit = $row['period_credit'];
                                        $closing_balance = $row['closing_balance'];
                                        
                                        // Calculate display values for opening balance
                                        $initial_debit = $initial_balance > 0 ? $initial_balance : 0;
                                        $initial_credit = $initial_balance < 0 ? abs($initial_balance) : 0;
                                        
                                        // Movements stay as is
                                        $movement_debit = $period_debit;
                                        $movement_credit = $period_credit;
                                        
                                        // Calculate display values for closing balance
                                        $closing_debit = $closing_balance > 0 ? $closing_balance : 0;
                                        $closing_credit = $closing_balance < 0 ? abs($closing_balance) : 0;
                                        
                                        // Display class header when class changes
                                        if ($row['class'] != $current_class):
                                            // Print class total if not first class
                                            if ($current_class != ''):
                                    ?>
                                    <tr class="table-secondary fw-bold">
                                        <td colspan="3" class="text-end"><?php echo htmlspecialchars($current_class); ?> Total:</td>
                                        <td class="text-end"><?php echo formatMoney($class_initial_balance); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_initial_debit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_initial_credit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_movement_debit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_movement_credit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_closing_balance); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_closing_balance); ?></td>
                                    </tr>
                                    <?php 
                                                // Reset class totals
                                                $class_initial_balance = 0;
                                                $class_initial_debit = 0;
                                                $class_initial_credit = 0;
                                                $class_movement_debit = 0;
                                                $class_movement_credit = 0;
                                                $class_closing_balance = 0;
                                                $class_closing_debit = 0;
                                                $class_closing_credit = 0;
                                            endif;
                                            
                                            $current_class = $row['class'];
                                        endif;
                                        
                                        // Accumulate class totals
                                        $class_initial_balance += $initial_balance;
                                        $class_initial_debit += $initial_debit;
                                        $class_initial_credit += $initial_credit;
                                        $class_movement_debit += $movement_debit;
                                        $class_movement_credit += $movement_credit;
                                        $class_closing_balance += $closing_balance;
                                        $class_closing_debit += $closing_debit;
                                        $class_closing_credit += $closing_credit;
                                        
                                        // Accumulate grand totals
                                        $grand_initial_balance += $initial_balance;
                                        $grand_initial_debit += $initial_debit;
                                        $grand_initial_credit += $initial_credit;
                                        $grand_movement_debit += $movement_debit;
                                        $grand_movement_credit += $movement_credit;
                                        $grand_closing_balance += $closing_balance;
                                        $grand_closing_debit += $closing_debit;
                                        $grand_closing_credit += $closing_credit;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['class']); ?></td>
                                        <td>
                                            <span class="account-code"><?php echo htmlspecialchars($row['account_code']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['account_name']); ?></td>
                                        <td class="text-end amount-cell"><?php echo formatMoney($initial_balance); ?></td>
                                        <td class="text-end amount-cell"><?php echo formatMoney($initial_debit); ?></td>
                                        <td class="text-end amount-cell"><?php echo formatMoney($initial_credit); ?></td>
                                        <td class="text-end amount-cell"><?php echo formatMoney($movement_debit); ?></td>
                                        <td class="text-end amount-cell"><?php echo formatMoney($movement_credit); ?></td>
                                        <td class="text-end amount-cell <?php echo $closing_balance > 0 ? 'text-success' : ($closing_balance < 0 ? 'text-danger' : ''); ?>">
                                            <?php echo formatMoney($closing_balance); ?>
                                        </td>
                                        <td class="text-end amount-cell fw-bold <?php echo $closing_balance > 0 ? 'text-success' : ($closing_balance < 0 ? 'text-danger' : ''); ?>">
                                            <?php echo formatMoney($closing_balance); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php 
                                    // Print final class total
                                    if ($current_class != ''):
                                    ?>
                                    <tr class="table-secondary fw-bold">
                                        <td colspan="3" class="text-end"><?php echo htmlspecialchars($current_class); ?> Total:</td>
                                        <td class="text-end"><?php echo formatMoney($class_initial_balance); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_initial_debit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_initial_credit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_movement_debit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_movement_credit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_closing_balance); ?></td>
                                        <td class="text-end"><?php echo formatMoney($class_closing_balance); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    
                                    <!-- GRAND TOTALS ROW -->
                                    <tr class="grand-total-row">
                                        <td colspan="3" class="text-end">GRAND TOTAL:</td>
                                        <td class="text-end"><?php echo formatMoney($grand_initial_balance); ?></td>
                                        <td class="text-end"><?php echo formatMoney($grand_initial_debit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($grand_initial_credit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($grand_movement_debit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($grand_movement_credit); ?></td>
                                        <td class="text-end"><?php echo formatMoney($grand_closing_balance); ?></td>
                                        <td class="text-end"><?php echo formatMoney($grand_closing_balance); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <?php elseif ($report_type == 'income_statement'): ?>
                        <!-- =============================================
                             INCOME STATEMENT — ENHANCED TWO-COLUMN LAYOUT
                        ============================================== -->
                        <?php
                        // Pre-group income statement data by section and class
                        $revenue_groups = [];
                        $expense_groups = [];
                        $is_rev_total = 0;
                        $is_exp_total = 0;

                        foreach ($report_data as $row) {
                            $first_digit = substr($row['account_code'], 0, 1);
                            $class = $row['class'];
                            
                            // Use the display_balance (period movement) calculated above
                            $period_move = $row['display_balance'] ?? abs($row['period_credit'] - $row['period_debit']);

                            if ($first_digit == '4') {
                                if (!isset($revenue_groups[$class])) $revenue_groups[$class] = ['rows' => [], 'total' => 0];
                                $revenue_groups[$class]['rows'][]    = $row;
                                $revenue_groups[$class]['total'] += $period_move;
                                $is_rev_total += $period_move;
                            } elseif ($first_digit == '5' || $first_digit == '6') {
                                if (!isset($expense_groups[$class])) $expense_groups[$class] = ['rows' => [], 'total' => 0];
                                $expense_groups[$class]['rows'][]    = $row;
                                $expense_groups[$class]['total'] += $period_move;
                                $is_exp_total += $period_move;
                            }
                        }
                        ?>

                        <div class="mt-4 is-wrapper" id="reportTable">
                            <div class="is-two-col">

                                <!-- LEFT: REVENUE -->
                                <div class="is-col">
                                    <div class="is-section-title rev-title">
                                        <i class="fas fa-arrow-trend-up"></i> Revenue
                                    </div>

                                    <?php foreach ($revenue_groups as $class_name => $group): ?>
                                    <div class="is-sub-label rev-sub"><?php echo htmlspecialchars($class_name); ?></div>
                                    <?php foreach ($group['rows'] as $row):
                                         $display = $row['display_balance'] ?? abs($row['period_credit'] - $row['period_debit']);
                                    ?>
                                    <div class="is-row">
                                        <div class="acct-info">
                                            <span class="account-code"><?php echo htmlspecialchars($row['account_code']); ?></span>
                                            <span class="acct-name"><?php echo htmlspecialchars($row['account_name']); ?></span>
                                        </div>
                                        <span class="acct-amount rev-amt"><?php echo formatMoney($display); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <div class="is-class-total rev-class-total">
                                        <span><?php echo htmlspecialchars($class_name); ?> Total</span>
                                        <span><?php echo formatMoney($group['total']); ?></span>
                                    </div>
                                    <?php endforeach; ?>

                                    <!-- Unified Revenue Breakdown by Customer -->
                                    <div class="p-3 bg-light border-top border-bottom no-print">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0 text-primary"><i class="fas fa-users me-2"></i>Revenue Analysis by Customer</h6>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="showCustBreakdown" onchange="toggleBreakdown()">
                                                <label class="form-check-label small" for="showCustBreakdown">View Details</label>
                                            </div>
                                        </div>
                                        
                                        <div id="unifiedBreakdown" style="display: none;">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover border" style="font-size: 0.7rem;">
                                                    <thead class="table-dark">
                                                        <tr>
                                                            <th>Customer Name</th>
                                                            <th class="text-center bg-primary" colspan="5">PAID IN SELECTED PERIOD</th>
                                                            <th class="text-center bg-secondary" colspan="2">OUTSTANDING BALANCE</th>
                                                            <th class="text-end fw-bold">Grand Total</th>
                                                        </tr>
                                                        <tr style="font-size: 0.65rem;">
                                                            <th></th>
                                                            <th class="text-end">Interest</th>
                                                            <th class="text-end">Periodic Mgmt</th>
                                                            <th class="text-end">Disbursement</th>
                                                            <th class="text-end">App Fee</th>
                                                            <th class="text-end">Penalties</th>
                                                            <th class="text-end text-danger">Int. Left</th>
                                                            <th class="text-end text-danger">Principal</th>
                                                            <th class="text-end fw-bold">Period Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $gt_int=0; $gt_mgmt=0; $gt_disb=0; $gt_app=0; $gt_pen=0; $gt_total=0;
                                                        foreach ($unified_revenue as $r): 
                                                            $row_total = $r['int_pd'] + $r['mgmt_pd'] + $r['disb_pd'] + $r['app_pd'] + $r['pen_pd'];
                                                            $gt_int+=$r['int_pd']; $gt_mgmt+=$r['mgmt_pd']; $gt_disb+=$r['disb_pd'];
                                                            $gt_app+=$r['app_pd']; $gt_pen+=$r['pen_pd']; $gt_total+=$row_total;
                                                        ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($r['customer_name']); ?></td>
                                                            <td class="text-end"><?php echo formatMoney($r['int_pd']); ?></td>
                                                            <td class="text-end"><?php echo formatMoney($r['mgmt_pd']); ?></td>
                                                            <td class="text-end"><?php echo formatMoney($r['disb_pd']); ?></td>
                                                            <td class="text-end"><?php echo formatMoney($r['app_pd']); ?></td>
                                                            <td class="text-end"><?php echo formatMoney($r['pen_pd']); ?></td>
                                                            <td class="text-end text-danger"><?php echo formatMoney($r['int_bal']); ?></td>
                                                            <td class="text-end text-danger"><?php echo formatMoney($r['princ_bal']); ?></td>
                                                            <td class="text-end fw-bold"><?php echo formatMoney($row_total); ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                    <tfoot class="table-secondary fw-bold">
                                                        <tr>
                                                            <td>TOTALS</td>
                                                            <td class="text-end"><?php echo formatMoney($gt_int); ?></td>
                                                            <td class="text-end"><?php echo formatMoney($gt_mgmt); ?></td>
                                                            <td class="text-end"><?php echo formatMoney($gt_disb); ?></td>
                                                            <td class="text-end"><?php echo formatMoney($gt_app); ?></td>
                                                            <td class="text-end"><?php echo formatMoney($gt_pen); ?></td>
                                                            <td class="text-end" colspan="2">--</td>
                                                            <td class="text-end fw-bold"><?php echo formatMoney($gt_total); ?></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- End Unified Revenue Breakdown -->
                                            <div class="alert alert-info py-1 px-2 mt-2 mb-0" style="font-size: 0.65rem;">
                                                <i class="fas fa-info-circle me-1"></i> <strong>Note:</strong> "Paid in Period" sums amounts from the <code>loan_payments</code> table for the selected range. "Outstanding Balance" shows current active loan balances.
                                            </div>
                                        </div>
                                    </div>
                                    <script>
                                        function toggleBreakdown() {
                                            const box = document.getElementById('unifiedBreakdown');
                                            box.style.display = document.getElementById('showCustBreakdown').checked ? 'block' : 'none';
                                        }
                                    </script>
                                    <div class="is-section-total rev-total">
                                        <span><i class="fas fa-sigma me-1"></i> TOTAL REVENUE</span>
                                        <span><?php echo formatMoney($is_rev_total); ?></span>
                                    </div>
                                </div>

                                <!-- RIGHT: EXPENSES -->
                                <div class="is-col">
                                    <div class="is-section-title exp-title">
                                        <i class="fas fa-arrow-trend-down"></i> Expenses
                                    </div>

                                    <?php foreach ($expense_groups as $class_name => $group): ?>
                                    <div class="is-sub-label exp-sub"><?php echo htmlspecialchars($class_name); ?></div>
                                    <?php foreach ($group['rows'] as $row):
                                        $display = $row['display_balance'] ?? abs($row['period_debit'] - $row['period_credit']);
                                    ?>
                                    <div class="is-row">
                                        <div class="acct-info">
                                            <span class="account-code"><?php echo htmlspecialchars($row['account_code']); ?></span>
                                            <span class="acct-name"><?php echo htmlspecialchars($row['account_name']); ?></span>
                                        </div>
                                        <span class="acct-amount exp-amt"><?php echo formatMoney($display); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <div class="is-class-total exp-class-total">
                                        <span><?php echo htmlspecialchars($class_name); ?> Total</span>
                                        <span><?php echo formatMoney($group['total']); ?></span>
                                    </div>
                                    <?php endforeach; ?>

                                    <div class="is-section-total exp-total">
                                        <span><i class="fas fa-sigma me-1"></i> TOTAL EXPENSES</span>
                                        <span><?php echo formatMoney($is_exp_total); ?></span>
                                    </div>
                                </div>

                            </div><!-- /.is-two-col -->

                            <!-- NET INCOME / LOSS BANNER (full width) -->
                            <div class="is-net-bar <?php echo $net_income >= 0 ? 'profit' : 'loss'; ?>">
                                <div>
                                    <div class="net-label">
                                        <i class="fas <?php echo $net_income >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?> me-1"></i>
                                        Net <?php echo $net_income >= 0 ? 'Income (Profit)' : 'Loss'; ?>
                                    </div>
                                    <div style="font-size:0.8rem;opacity:0.8;">
                                        <?php echo $total_revenue > 0 ? number_format(($net_income / $total_revenue) * 100, 2) : '0.00'; ?>% Profit Margin
                                    </div>
                                </div>
                                <div class="net-amount"><?php echo formatMoney(abs($net_income)); ?></div>
                            </div>
                        </div><!-- /.is-wrapper -->

                        <?php endif; ?>
                        
                        <!-- Report Specific Summaries -->
                        <?php if ($report_type == 'balance_sheet'): ?>
                        <div class="financial-summary mt-4">
                            <h6><i class="fas fa-balance-scale me-2"></i>Balance Sheet Summary</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card bg-light mb-3">
                                        <div class="card-body text-center">
                                            <h6 class="card-title text-muted">Total Assets</h6>
                                            <p class="card-text h5 <?php echo $total_assets > 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo formatMoney($total_assets); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light mb-3">
                                        <div class="card-body text-center">
                                            <h6 class="card-title text-muted">Total Liabilities</h6>
                                            <p class="card-text h5 text-danger">
                                                <?php echo formatMoney(abs($total_liabilities)); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light mb-3">
                                        <div class="card-body text-center">
                                            <h6 class="card-title text-muted">Total Equity</h6>
                                            <p class="card-text h5 text-primary">
                                                <?php echo formatMoney(abs($total_equity)); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accounting-equation mt-3">
                                <h6><i class="fas fa-equals me-2"></i>Accounting Equation</h6>
                                <div class="text-center">
                                    <h5>
                                        Assets = Liabilities + Equity
                                    </h5>
                                    <p class="mb-0">
                                        <?php echo formatMoney($total_assets); ?> = 
                                        <?php echo formatMoney(abs($total_liabilities)); ?> + 
                                        <?php echo formatMoney(abs($total_equity)); ?>
                                    </p>
                                    <small class="text-muted">
                                        (Equity includes Net Income: <?php echo formatMoney($net_income_from_trial); ?>)
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <?php elseif ($report_type == 'income_statement'): ?>
                        <div class="financial-summary mt-4">
                            <h6><i class="fas fa-chart-line me-2"></i>Income Statement Summary</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card bg-light mb-3">
                                        <div class="card-body text-center">
                                            <h6 class="card-title text-muted">Total Revenue</h6>
                                            <p class="card-text h5 text-success">
                                                <?php echo formatMoney($total_revenue); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light mb-3">
                                        <div class="card-body text-center">
                                            <h6 class="card-title text-muted">Total Expenses</h6>
                                            <p class="card-text h5 text-danger">
                                                <?php echo formatMoney($total_expenses); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accounting-equation mt-3 <?php echo $net_income >= 0 ? 'bg-success' : 'bg-danger'; ?> text-white">
                                <div class="text-center">
                                    <h5>
                                        <i class="fas <?php echo $net_income >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?> me-2"></i>
                                        Net <?php echo $net_income >= 0 ? 'Income (Profit)' : 'Loss'; ?>
                                    </h5>
                                    <h3 class="mb-0">
                                        <?php echo formatMoney(abs($net_income)); ?>
                                    </h3>
                                    <small>
                                        (<?php echo $total_revenue > 0 ? number_format(($net_income / $total_revenue) * 100, 2) : '0.00'; ?>% Profit Margin)
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <?php elseif ($report_type == 'income_analysis'): ?>
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-sm table-hover" id="reportTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th rowspan="2">Customer / Loan</th>
                                        <th class="text-center" colspan="4" style="background:#1a5276;font-size:0.72rem;">PAID IN SELECTED PERIOD</th>
                                        <th class="text-center" colspan="3" style="background:#1e8449;font-size:0.72rem;">ALL-TIME TOTALS</th>
                                        <th class="text-center" colspan="2" style="background:#922b21;font-size:0.72rem;">REMAINING (LEFT)</th>
                                    </tr>
                                    <tr style="font-size:0.7rem;">
                                        <th class="text-end">Interest</th>
                                        <th class="text-end">Periodic Mgmt</th>
                                        <th class="text-end" style="background:#7d6608;color:#fff;" title="Disbursement fee charged at loan start">Disb. Mgmt Fee</th>
                                        <th class="text-end">Penalties</th>
                                        <th class="text-end">Total Interest</th>
                                        <th class="text-end">Total Periodic Mgmt</th>
                                        <th class="text-end" style="background:#7d6608;color:#fff;">Total Disb. Fee</th>
                                        <th class="text-end">Interest Left</th>
                                        <th class="text-end">Mgmt Fee Left</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $t_pi = 0; $t_pm = 0; $t_pp = 0; $t_disb_p = 0; $t_disb_all = 0;
                                    $t_ti = 0; $t_tm = 0;
                                    $t_ri = 0; $t_rm = 0;
                                    
                                    foreach ($report_data as $row) {
                                        $rem_i = max(0, $row['total_interest_exp'] - $row['total_interest_paid']);
                                        $rem_m = max(0, $row['total_fee_exp'] - $row['total_fee_paid']);
                                        
                                        $t_pi      += $row['period_interest_paid'];
                                        $t_pm      += $row['period_fee_paid'];
                                        $t_pp      += $row['period_penalty_paid'];
                                        $t_disb_p  += floatval($row['disb_fee_period']);
                                        $t_disb_all+= floatval($row['total_disb_fee']);
                                        $t_ti      += $row['total_interest_paid'];
                                        $t_tm      += $row['total_fee_paid'];
                                        $t_ri      += $rem_i;
                                        $t_rm      += $rem_m;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['customer_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['loan_number']); ?></small>
                                        </td>
                                        <td class="text-end"><?php echo formatMoney($row['period_interest_paid']); ?></td>
                                        <td class="text-end"><?php echo formatMoney($row['period_fee_paid']); ?></td>
                                        <td class="text-end fw-bold" style="background:#fef9e7;">
                                            <?php echo floatval($row['disb_fee_period']) > 0 ? formatMoney($row['disb_fee_period']) : '<span class="text-muted">&mdash;</span>'; ?>
                                        </td>
                                        <td class="text-end"><?php echo formatMoney($row['period_penalty_paid']); ?></td>
                                        <td class="text-end fw-bold"><?php echo formatMoney($row['total_interest_paid']); ?></td>
                                        <td class="text-end fw-bold"><?php echo formatMoney($row['total_fee_paid']); ?></td>
                                        <td class="text-end fw-bold" style="background:#fef9e7;"><?php echo formatMoney($row['total_disb_fee']); ?></td>
                                        <td class="text-end text-danger"><?php echo $rem_i > 0 ? formatMoney($rem_i) : '<span class="text-success small">&#10003; Clear</span>'; ?></td>
                                        <td class="text-end text-danger"><?php echo $rem_m > 0 ? formatMoney($rem_m) : '<span class="text-success small">&#10003; Clear</span>'; ?></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                                <tfoot class="table-secondary fw-bold" style="font-size:0.75rem;">
                                    <tr>
                                        <td>TOTALS</td>
                                        <td class="text-end"><?php echo formatMoney($t_pi); ?></td>
                                        <td class="text-end"><?php echo formatMoney($t_pm); ?></td>
                                        <td class="text-end" style="background:#fef9e7;"><?php echo formatMoney($t_disb_p); ?></td>
                                        <td class="text-end"><?php echo formatMoney($t_pp); ?></td>
                                        <td class="text-end"><?php echo formatMoney($t_ti); ?></td>
                                        <td class="text-end"><?php echo formatMoney($t_tm); ?></td>
                                        <td class="text-end" style="background:#fef9e7;"><?php echo formatMoney($t_disb_all); ?></td>
                                        <td class="text-end text-danger"><?php echo formatMoney($t_ri); ?></td>
                                        <td class="text-end text-danger"><?php echo formatMoney($t_rm); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-muted text-center">
                        Generated on <?php echo date('F d, Y h:i A'); ?> | 
                        <?php if ($report_type == 'balance_sheet'): ?>
                        As of: <?php echo date('d/m/Y', strtotime($end_date)); ?>
                        <?php else: ?>
                        Period: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function switchReportType(type) {
        const form = document.getElementById('reportFilter');
        // Use the dedicated id to reliably find the type input
        const typeInput = document.getElementById('report_type_input');
        if (typeInput) {
            typeInput.value = type;
        }
        form.submit();
    }
    
    function setDateRange(range) {
        const today = new Date();
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');
        
        if (range === 'today') {
            startDateInput.value = formatDate(today);
            endDateInput.value = formatDate(today);
        } else if (range === 'month') {
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            startDateInput.value = formatDate(firstDay);
            endDateInput.value = formatDate(today);
        } else if (range === 'year') {
            const firstDay = new Date(today.getFullYear(), 0, 1);
            startDateInput.value = formatDate(firstDay);
            endDateInput.value = formatDate(today);
        }
        
        document.getElementById('reportFilter').submit();
    }
    
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    function exportToExcel() {
        const table = document.getElementById('reportTable');
        if (!table) {
            alert("No report data found to export.");
            return;
        }

        let csv = [];
        if (table.tagName === 'TABLE') {
            for (let i = 0; i < table.rows.length; i++) {
                let row = [], cols = table.rows[i].querySelectorAll('td, th');
                for (let j = 0; j < cols.length; j++) {
                    let data = cols[j].innerText.replace(/"/g, '""').trim();
                    row.push('"' + data + '"');
                }
                csv.push(row.join(','));
            }
        } else {
            // Special handling for Div-based reports (Balance Sheet / Income Statement)
            const rows = table.querySelectorAll('.bs-row, .is-row, .bs-section-title, .is-section-title, .bs-grand-total, .is-section-total, .is-net-bar, .bs-sub-label, .is-sub-label');
            rows.forEach(r => {
                let rowData = [];
                if (r.classList.contains('bs-row') || r.classList.contains('is-row')) {
                    const acctName = r.querySelector('.acct-name')?.innerText || '';
                    const acctCode = r.querySelector('.account-code')?.innerText || '';
                    const acctAmt = r.querySelector('.acct-amount')?.innerText || '';
                    rowData.push('"' + (acctCode ? '['+acctCode+'] ' : '') + acctName.replace(/"/g, '""').trim() + '"');
                    rowData.push('"' + acctAmt.replace(/"/g, '""').trim() + '"');
                } else {
                    // Header or Total line
                    const text = r.innerText.replace(/\n/g, ' ').replace(/"/g, '""').trim();
                    const parts = text.split(/\s{2,}/); // Try to split large spaces if any
                    if (parts.length > 1) {
                        rowData.push('"' + parts[0] + '"');
                        rowData.push('"' + parts[parts.length-1] + '"');
                    } else {
                        rowData.push('"' + text + '"');
                    }
                }
                if (rowData.length > 0) csv.push(rowData.join(','));
            });
        }
        const csvString = csv.join('\n');
        const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const reportTitle = "<?php echo $report_title; ?>".replace(/[^a-z0-9]/gi, '_').toLowerCase();
        a.download = reportTitle + '_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    function exportToPDF() {
        const urlParams = new URLSearchParams({
            type: document.getElementById('report_type_input').value,
            start_date: document.querySelector('input[name="start_date"]').value,
            end_date: document.querySelector('input[name="end_date"]').value
        });
        window.open('modules/export_financial_pdf.php?' + urlParams.toString(), '_blank');
    }
    </script>
</body>
</html>
<?php 
// Close connections
if (isset($conn)) mysqli_close($conn); 
?>
