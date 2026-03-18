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
    
    while ($account = mysqli_fetch_assoc($accounts_result)) {
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
        // NOT what is in the ledger (which might contain accruals).
        // ==========================================
        if (in_array($account_code, ['4101', '4201', '4202', '4205'])) {
            
            // 1. Initial Balance (Opening)
            $col_paid = '';
            if ($account_code === '4101') $col_paid = 'interest_paid';
            elseif ($account_code === '4201') $col_paid = 'management_fee_paid';
            elseif ($account_code === '4205') $col_paid = 'penalty_paid';
            
            if ($col_paid) {
                $res_open = mysqli_query($conn, "SELECT SUM($col_paid) as op FROM loan_instalments WHERE payment_date < '$start_date'");
                $row_open = mysqli_fetch_assoc($res_open);
                $initial_balance = -roundAmount(floatval($row_open['op'] ?? 0));
                
                $res_move = mysqli_query($conn, "SELECT SUM($col_paid) as mp FROM loan_instalments WHERE payment_date BETWEEN '$start_date' AND '$query_end_date'");
                $row_move = mysqli_fetch_assoc($res_move);
                $period_debit = 0;
                $period_credit = roundAmount(floatval($row_move['mp'] ?? 0));
            } else {
                // For 4202 (Disbursement Fee), we take directly from ledger because it's not in installments
                $res_open = mysqli_query($conn, "SELECT SUM(credit_amount - debit_amount) as op FROM ledger WHERE account_code = '$account_code' AND transaction_date < '$start_date'");
                $row_open = mysqli_fetch_assoc($res_open);
                $initial_balance = -roundAmount(floatval($row_open['op'] ?? 0));
                
                $res_move = mysqli_query($conn, "SELECT SUM(debit_amount) as d, SUM(credit_amount) as c FROM ledger WHERE account_code = '$account_code' AND transaction_date BETWEEN '$start_date' AND '$query_end_date'");
                $row_move = mysqli_fetch_assoc($res_move);
                $period_debit = roundAmount(floatval($row_move['d'] ?? 0));
                $period_credit = roundAmount(floatval($row_move['c'] ?? 0));
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

// Calculate revenues and expenses for CURRENT PERIOD
foreach ($trial_data as $account) {
    $account_code = $account['account_code'];
    $first_digit = substr($account_code, 0, 1);
    $closing_balance = $account['closing_balance'];
    
    // Revenue accounts (4xxx) - Credit balances are revenues
    if ($first_digit == '4') {
        // Revenue has negative balance (credit), so we take absolute value
        $total_revenues += abs($closing_balance);
    }
    
    // Expense accounts (5xxx, 6xxx) - Debit balances are expenses
    if ($first_digit == '5' || $first_digit == '6') {
        // Expenses have positive balance (debit)
        $total_expenses += $closing_balance;
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
        
        // Sort each section by class first, then account code â€” keeps groups together
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
        
        // Income Statement: Revenue (4), Expenses (5, 6)
        foreach ($trial_data as &$account) {
            $first_digit = substr($account['account_code'], 0, 1);
            
            // Only include income statement accounts
            if ($first_digit == '4' || $first_digit == '5' || $first_digit == '6') {
                $report_data[] = $account;
                
                $closing_balance = $account['closing_balance'];
                
                // Calculate totals
                if ($first_digit == '4') {
                    $total_revenue += abs($closing_balance); // Revenue is negative (credit)
                } elseif ($first_digit == '5' || $first_digit == '6') {
                    $total_expenses += $closing_balance; // Expense is positive (debit)
                }
            }
        }
        
        // Calculate net income
        $net_income = $total_revenue - $total_expenses;
        $net_income = roundAmount($net_income);
        
        break;
        
    case 'income_analysis':
        $report_title = "Income Analysis (By Customer)";
        
        // Fetch detailed loan income data
        $analysis_sql = "SELECT 
            lp.loan_id, lp.loan_number, c.customer_name,
            -- Paid during period
            SUM(CASE WHEN li.payment_date BETWEEN '$start_date' AND '$query_end_date' THEN li.interest_paid ELSE 0 END) as period_interest_paid,
            SUM(CASE WHEN li.payment_date BETWEEN '$start_date' AND '$query_end_date' THEN li.management_fee_paid ELSE 0 END) as period_fee_paid,
            SUM(CASE WHEN li.payment_date BETWEEN '$start_date' AND '$query_end_date' THEN li.penalty_paid ELSE 0 END) as period_penalty_paid,
            -- Totals to date
            SUM(li.interest_paid) as total_interest_paid,
            SUM(li.management_fee_paid) as total_fee_paid,
            SUM(li.penalty_paid) as total_penalty_paid,
            -- Total expected for comparison
            SUM(li.interest_amount) as total_interest_exp,
            SUM(li.management_fee) as total_fee_exp,
            SUM(li.penalty_amount) as total_penalty_exp
            FROM loan_portfolio lp
            JOIN customers c ON lp.customer_id = c.customer_id
            JOIN loan_instalments li ON lp.loan_id = li.loan_id
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
