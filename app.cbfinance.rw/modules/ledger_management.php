<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';
$conn = getConnection();

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get current user
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1;

// Helper functions with consistent rounding
function formatMoney($amount, $decimals = 2) {
    return number_format(round($amount, $decimals), $decimals, '.', ',');
}

function roundAmount($amount, $decimals = 2) {
    return round($amount, $decimals);
}

// NEW FUNCTION: Format for input fields (no commas)
function formatForInput($amount, $decimals = 2) {
    return number_format(round($amount, $decimals), $decimals, '.', '');
}

// Function to recalculate ALL ending balances for an account
function recalculateAccountEndingBalances($conn, $account_code) {
    // Get ALL entries for this account in chronological order
    $sql = "SELECT ledger_id, transaction_date, debit_amount, credit_amount 
            FROM ledger 
            WHERE account_code = '$account_code' 
            ORDER BY transaction_date ASC, ledger_id ASC";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $current_balance = 0.00;
        
        // Process ALL entries from beginning to end
        while ($entry = mysqli_fetch_assoc($result)) {
            $movement = roundAmount($entry['debit_amount'] - $entry['credit_amount']);
            $ending_balance = roundAmount($current_balance + $movement);
            
            // Update this entry
            $update_sql = "UPDATE ledger SET 
                          beginning_balance = $current_balance,
                          movement = $movement,
                          ending_balance = $ending_balance
                          WHERE ledger_id = " . $entry['ledger_id'];
            
            mysqli_query($conn, $update_sql);
            
            // Set current balance for next iteration
            $current_balance = $ending_balance;
        }
    }
    
    return true;
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $ledger_id = intval($_GET['id']);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // First, get the entry details before deletion
        $get_sql = "SELECT account_code, transaction_date, voucher_number FROM ledger WHERE ledger_id = $ledger_id";
        $get_result = mysqli_query($conn, $get_sql);
        
        if ($get_result && mysqli_num_rows($get_result) > 0) {
            $entry = mysqli_fetch_assoc($get_result);
            $account_code = $entry['account_code'];
            $voucher_number = $entry['voucher_number'];
            
            // Delete the entry
            $delete_sql = "DELETE FROM ledger WHERE ledger_id = $ledger_id";
            
            if (mysqli_query($conn, $delete_sql)) {
                // Recalculate ending balances for the affected account
                recalculateAccountEndingBalances($conn, $account_code);
                
                mysqli_commit($conn);
                
                $_SESSION['success_message'] = "Ledger entry deleted successfully! (Voucher: $voucher_number)";
                header("Location: index.php?page=ledger_management");
                exit();
            } else {
                throw new Exception("Failed to delete entry: " . mysqli_error($conn));
            }
        } else {
            throw new Exception("Entry not found!");
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Delete failed: " . $e->getMessage();
        header("Location: index.php?page=ledger_management");
        exit();
    }
}

// Handle bulk delete selected items
if (isset($_POST['delete_selected']) && isset($_POST['selected_ids']) && !empty($_POST['selected_ids'])) {
    $selected_ids = json_decode($_POST['selected_ids']);
    
    if (!empty($selected_ids)) {
        mysqli_begin_transaction($conn);
        
        try {
            // Convert array to comma-separated string
            $id_list = implode(',', array_map('intval', $selected_ids));
            
            // Get affected accounts for recalculation
            $accounts_sql = "SELECT DISTINCT account_code FROM ledger WHERE ledger_id IN ($id_list)";
            $accounts_result = mysqli_query($conn, $accounts_sql);
            $affected_accounts = [];
            
            while ($row = mysqli_fetch_assoc($accounts_result)) {
                $affected_accounts[] = $row['account_code'];
            }
            
            // Delete selected entries
            $delete_sql = "DELETE FROM ledger WHERE ledger_id IN ($id_list)";
            
            if (!mysqli_query($conn, $delete_sql)) {
                throw new Exception("Failed to delete selected entries: " . mysqli_error($conn));
            }
            
            // Recalculate balances for all affected accounts
            foreach ($affected_accounts as $account_code) {
                recalculateAccountEndingBalances($conn, $account_code);
            }
            
            mysqli_commit($conn);
            
            $_SESSION['success_message'] = count($selected_ids) . " ledger entries deleted successfully!";
            echo "<script>window.location.href='index.php?page=ledger_management';</script>";
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error_message'] = "Bulk delete failed: " . $e->getMessage();
            header("Location: index.php?page=ledger_management");
            exit();
        }
    }
}

// Handle edit action - display form
$edit_entry = null;
$is_editing = false;

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $ledger_id = intval($_GET['id']);
    $edit_sql = "SELECT * FROM ledger WHERE ledger_id = $ledger_id";
    $edit_result = mysqli_query($conn, $edit_sql);
    
    if ($edit_result && mysqli_num_rows($edit_result) > 0) {
        $edit_entry = mysqli_fetch_assoc($edit_result);
        $is_editing = true;
    }
}

// Handle update action - FIXED
if (isset($_POST['update_entry'])) {
    $ledger_id = intval($_POST['ledger_id']);
    $transaction_date = mysqli_real_escape_string($conn, $_POST['transaction_date']);
    $account_code = mysqli_real_escape_string($conn, $_POST['account_code']);
    $account_name = mysqli_real_escape_string($conn, $_POST['account_name']);
    $class = mysqli_real_escape_string($conn, $_POST['class']);
    $voucher_number = mysqli_real_escape_string($conn, $_POST['voucher_number']);
    $particular = mysqli_real_escape_string($conn, $_POST['particular']);
    $narration = mysqli_real_escape_string($conn, $_POST['narration']);
    
    // Fix for amount parsing - handle empty values
    $debit_amount = isset($_POST['debit_amount']) && $_POST['debit_amount'] !== '' ? roundAmount(floatval($_POST['debit_amount'])) : 0.00;
    $credit_amount = isset($_POST['credit_amount']) && $_POST['credit_amount'] !== '' ? roundAmount(floatval($_POST['credit_amount'])) : 0.00;
    
    // Validate that only one amount is provided (debit OR credit)
    if ($debit_amount > 0 && $credit_amount > 0) {
        $_SESSION['error_message'] = "Entry can only have either Debit or Credit, not both!";
        header("Location: index.php?page=ledger_management&action=edit&id=$ledger_id");
        exit();
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // First, get the old values for recalculation
        $old_sql = "SELECT account_code, transaction_date FROM ledger WHERE ledger_id = $ledger_id";
        $old_result = mysqli_query($conn, $old_sql);
        
        if ($old_result && mysqli_num_rows($old_result) > 0) {
            $old_entry = mysqli_fetch_assoc($old_result);
            $old_account_code = $old_entry['account_code'];
            
            // Calculate movement
            $movement = $debit_amount - $credit_amount;
            
            // Update the entry with all fields
            $update_sql = "UPDATE ledger SET
            transaction_date = '$transaction_date',
            class = '$class',
            account_code = '$account_code',
            account_name = '$account_name',
            particular = '$particular',
            voucher_number = '$voucher_number',
            narration = '$narration',
            debit_amount = $debit_amount,
            credit_amount = $credit_amount,
            movement = $movement,
            updated_at = NOW()
      WHERE ledger_id = $ledger_id";
            
            if (!mysqli_query($conn, $update_sql)) {
                throw new Exception("Failed to update entry: " . mysqli_error($conn));
            }
            
            // Recalculate balances for both old and new accounts (if account changed)
            recalculateAccountEndingBalances($conn, $old_account_code);
            
            if ($old_account_code !== $account_code) {
                recalculateAccountEndingBalances($conn, $account_code);
            }
            
            mysqli_commit($conn);
            
            $_SESSION['success_message'] = "Ledger entry updated successfully!";
            echo "<script>window.location.href='index.php?page=ledger_management';</script>";
            exit();
        } else {
            throw new Exception("Original entry not found!");
        }
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Update failed: " . $e->getMessage();
        header("Location: index.php?page=ledger_management&action=edit&id=$ledger_id");
        exit();
    }
}

// Handle bulk delete (delete entire voucher)
if (isset($_POST['bulk_delete_voucher'])) {
    $voucher_number = mysqli_real_escape_string($conn, $_POST['voucher_number']);
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get all accounts affected by this voucher
        $accounts_sql = "SELECT DISTINCT account_code FROM ledger WHERE voucher_number = '$voucher_number'";
        $accounts_result = mysqli_query($conn, $accounts_sql);
        $affected_accounts = [];
        
        while ($row = mysqli_fetch_assoc($accounts_result)) {
            $affected_accounts[] = $row['account_code'];
        }
        
        // Delete all entries with this voucher number
        $delete_sql = "DELETE FROM ledger WHERE voucher_number = '$voucher_number'";
        
        if (!mysqli_query($conn, $delete_sql)) {
            throw new Exception("Failed to delete voucher entries: " . mysqli_error($conn));
        }
        
        // Recalculate balances for all affected accounts
        foreach ($affected_accounts as $account_code) {
            recalculateAccountEndingBalances($conn, $account_code);
        }
        
        mysqli_commit($conn);
        
        $_SESSION['success_message'] = "Voucher '$voucher_number' and all its entries deleted successfully!";
        echo "<script>window.location.href='index.php?page=ledger_management';</script>";
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Bulk delete failed: " . $e->getMessage();
        header("Location: index.php?page=ledger_management");
        exit();
    }
}

// Check for messages from session
$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get search parameters
$search_date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$search_date_to = $_GET['date_to'] ?? date('Y-m-d');
$search_account = $_GET['account'] ?? '';
$search_voucher = $_GET['voucher'] ?? '';
$search_particular = $_GET['particular'] ?? '';

// Build WHERE clause for ledger query
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search_date_from) && !empty($search_date_to)) {
    $where_clauses[] = "transaction_date BETWEEN ? AND ?";
    $params[] = $search_date_from;
    $params[] = $search_date_to;
    $types .= 'ss';
}

if (!empty($search_account)) {
    $where_clauses[] = "(account_code LIKE ? OR account_name LIKE ?)";
    $params[] = "%" . $search_account . "%";
    $params[] = "%" . $search_account . "%";
    $types .= 'ss';
}

if (!empty($search_voucher)) {
    $where_clauses[] = "voucher_number LIKE ?";
    $params[] = "%" . $search_voucher . "%";
    $types .= 's';
}

if (!empty($search_particular)) {
    $where_clauses[] = "(particular LIKE ? OR narration LIKE ?)";
    $params[] = "%" . $search_particular . "%";
    $params[] = "%" . $search_particular . "%";
    $types .= 'ss';
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get ledger entries
$ledger_sql = "SELECT * FROM ledger $where_sql ORDER BY transaction_date DESC, ledger_id DESC LIMIT 500";
$ledger_stmt = mysqli_prepare($conn, $ledger_sql);

if ($ledger_stmt && !empty($params)) {
    mysqli_stmt_bind_param($ledger_stmt, $types, ...$params);
}

if ($ledger_stmt) {
    mysqli_stmt_execute($ledger_stmt);
    $ledger_result = mysqli_stmt_get_result($ledger_stmt);
} else {
    $ledger_result = mysqli_query($conn, $ledger_sql);
}

// Get distinct voucher numbers for filter
$vouchers_sql = "SELECT DISTINCT voucher_number FROM ledger ORDER BY voucher_number DESC LIMIT 100";
$vouchers_result = mysqli_query($conn, $vouchers_sql);

// Get accounts for dropdown
$accounts_sql = "SELECT DISTINCT account_code, account_name FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_code";
$accounts_result = mysqli_query($conn, $accounts_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ledger Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .voucher-group {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .voucher-header {
            background: #6c757d;
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .action-buttons .btn-sm {
            padding: 3px 8px;
            font-size: 0.8rem;
        }
        .balance-positive {
            color: #198754;
            font-weight: bold;
        }
        .balance-negative {
            color: #dc3545;
            font-weight: bold;
        }
        .account-code {
            font-family: monospace;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .debit-badge {
            background: #198754 !important;
        }
        .credit-badge {
            background: #dc3545 !important;
        }
        .search-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .bulk-delete-btn {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            color: white;
        }
        .bulk-delete-btn:hover {
            background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
            color: white;
        }
        .modal-danger .modal-header {
            background: #dc3545;
            color: white;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
        .total-row {
            background-color: #e9ecef !important;
            font-weight: bold;
        }
        .voucher-actions {
            position: sticky;
            top: 0;
            background: white;
            z-index: 100;
            padding: 10px 0;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 15px;
        }
        .date-badge {
            font-size: 0.8em;
            padding: 3px 8px;
        }
        .selected-row {
            background-color: rgba(0, 123, 255, 0.1) !important;
        }
        .select-all-checkbox {
            width: 30px;
        }
        .bulk-actions {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: none;
        }
        /* Fix for edit modal overlay */
        .modal-backdrop-custom {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1040;
        }
        .modal-custom {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1050;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2>Ledger Management</h2>
                    <div>
                        <a href="index.php?page=ledger" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Ledger
                        </a>
                    </div>
                </div>
                <p class="text-muted">View, edit, and delete ledger entries. All changes will recalculate account balances automatically.</p>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bulk Actions Bar -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="bulk-actions" id="bulkActions">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span id="selectedCount">0</span> items selected
                        </div>
                        <div>
                            <button type="button" class="btn btn-danger btn-sm" id="deleteSelectedBtn">
                                <i class="fas fa-trash me-1"></i>Delete Selected
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="clearSelectionBtn">
                                Clear Selection
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="search-card">
                    <h5><i class="fas fa-search me-2"></i>Search & Filter</h5>
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="page" value="ledger_management">
                        
                        <div class="col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="date_from" 
                                   value="<?php echo htmlspecialchars($search_date_from); ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="date_to" 
                                   value="<?php echo htmlspecialchars($search_date_to); ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Account</label>
                            <select class="form-select" name="account">
                                <option value="">All Accounts</option>
                                <?php if ($accounts_result && mysqli_num_rows($accounts_result) > 0): ?>
                                    <?php while($account = mysqli_fetch_assoc($accounts_result)): ?>
                                        <option value="<?php echo htmlspecialchars($account['account_code']); ?>" 
                                            <?php echo $search_account === $account['account_code'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                    <?php mysqli_data_seek($accounts_result, 0); ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Voucher Number</label>
                            <select class="form-select" name="voucher">
                                <option value="">All Vouchers</option>
                                <?php if ($vouchers_result && mysqli_num_rows($vouchers_result) > 0): ?>
                                    <?php while($voucher = mysqli_fetch_assoc($vouchers_result)): ?>
                                        <option value="<?php echo htmlspecialchars($voucher['voucher_number']); ?>" 
                                            <?php echo $search_voucher === $voucher['voucher_number'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($voucher['voucher_number']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Particular/Narration</label>
                            <input type="text" class="form-control" name="particular" 
                                   placeholder="Search in particulars or narration..." 
                                   value="<?php echo htmlspecialchars($search_particular); ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i>Apply Filter
                                </button>
                                <a href="index.php?page=ledger_management" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-1"></i>Reset
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#bulkDeleteModal">
                                    <i class="fas fa-trash-alt me-1"></i>Bulk Delete Voucher
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Form Modal (Inline editing without AJAX) -->
        <?php if ($is_editing && $edit_entry): ?>
        <div class="modal-backdrop-custom" id="editModalBackdrop"></div>
        <div class="modal-custom" style="width: 90%; max-width: 1000px;">
            <div class="modal-content border-0">
                <form method="POST" action="">
                    <div class="modal-header bg-primary text-white rounded-top">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Ledger Entry</h5>
                        <a href="index.php?page=ledger_management" class="btn-close btn-close-white"></a>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="ledger_id" value="<?php echo $edit_entry['ledger_id']; ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Date *</label>
                                <input type="date" class="form-control" name="transaction_date" 
                                       value="<?php echo htmlspecialchars($edit_entry['transaction_date']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Voucher Number *</label>
                                <input type="text" class="form-control" name="voucher_number" 
                                       value="<?php echo htmlspecialchars($edit_entry['voucher_number']); ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Account Code *</label>
                                <input type="text" class="form-control" name="account_code" 
                                       value="<?php echo htmlspecialchars($edit_entry['account_code']); ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Account Name *</label>
                                <input type="text" class="form-control" name="account_name" 
                                       value="<?php echo htmlspecialchars($edit_entry['account_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Class *</label>
                                <input type="text" class="form-control" name="class" 
                                       value="<?php echo htmlspecialchars($edit_entry['class']); ?>" required>
                            </div>
                            
                            <div class="col-md-12">
                                <label class="form-label">Particular</label>
                                <input type="text" class="form-control" name="particular" 
                                       value="<?php echo htmlspecialchars($edit_entry['particular']); ?>">
                            </div>
                            
                            <div class="col-md-12">
                                <label class="form-label">Narration</label>
                                <textarea class="form-control" name="narration" rows="2"><?php echo htmlspecialchars($edit_entry['narration']); ?></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Debit Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text"> </span>
                                    <input type="number" class="form-control" name="debit_amount" 
                                           step="0.01" min="0" 
                                           value="<?php echo formatForInput($edit_entry['debit_amount']); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Credit Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text"> </span>
                                    <input type="number" class="form-control" name="credit_amount" 
                                           step="0.01" min="0" 
                                           value="<?php echo formatForInput($edit_entry['credit_amount']); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <small>
                                        <i class="fas fa-info-circle me-1"></i>
                                        Current balance info for reference:<br>
                                        Beginning:  <?php echo formatMoney($edit_entry['beginning_balance']); ?> |
                                        Movement:  <?php echo formatMoney($edit_entry['movement']); ?> |
                                        Ending:  <?php echo formatMoney($edit_entry['ending_balance']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer rounded-bottom">
                        <a href="index.php?page=ledger_management" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_entry" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update Entry
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Ledger Entries -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-list me-2"></i>Ledger Entries</h4>
                            <small>
                                Showing <?php echo mysqli_num_rows($ledger_result); ?> entries
                                <?php if (!empty($search_date_from) || !empty($search_date_to)): ?>
                                    | Date: <?php echo date('d M Y', strtotime($search_date_from)); ?> 
                                    to <?php echo date('d M Y', strtotime($search_date_to)); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($ledger_result && mysqli_num_rows($ledger_result) > 0): 
                            // Group entries by voucher number
                            $entries_by_voucher = [];
                            $total_debits = 0;
                            $total_credits = 0;
                            
                            while($entry = mysqli_fetch_assoc($ledger_result)) {
                                $voucher = $entry['voucher_number'];
                                if (!isset($entries_by_voucher[$voucher])) {
                                    $entries_by_voucher[$voucher] = [];
                                }
                                $entries_by_voucher[$voucher][] = $entry;
                                
                                $total_debits += $entry['debit_amount'];
                                $total_credits += $entry['credit_amount'];
                            }
                        ?>
                        
                        <!-- Bulk Delete Form -->
                        <form method="POST" id="bulkDeleteForm">
                            <input type="hidden" name="delete_selected" value="1">
                            <input type="hidden" name="selected_ids" id="selectedIds">
                        </form>
                        
                        <?php foreach ($entries_by_voucher as $voucher_number => $voucher_entries):
                                $voucher_date = $voucher_entries[0]['transaction_date'];
                                $voucher_total_debit = 0;
                                $voucher_total_credit = 0;
                                
                                foreach ($voucher_entries as $entry) {
                                    $voucher_total_debit += $entry['debit_amount'];
                                    $voucher_total_credit += $entry['credit_amount'];
                                }
                        ?>
                        
                        <div class="voucher-group" id="voucher-<?php echo urlencode($voucher_number); ?>">
                            <div class="voucher-header d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($voucher_number); ?></strong>
                                    <span class="badge bg-light text-dark ms-2">
                                        <?php echo date('d M Y', strtotime($voucher_date)); ?>
                                    </span>
                                    <span class="badge bg-info ms-1">
                                        <?php echo count($voucher_entries); ?> entries
                                    </span>
                                </div>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteVoucherModal"
                                            data-voucher="<?php echo htmlspecialchars($voucher_number); ?>">
                                        <i class="fas fa-trash-alt"></i> Delete All
                                    </button>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th width="5%" class="select-all-checkbox">
                                                <input type="checkbox" class="form-check-input voucher-select-all" 
                                                       data-voucher="<?php echo htmlspecialchars($voucher_number); ?>">
                                            </th>
                                            <th width="5%">ID</th>
                                            <th width="10%">Date</th>
                                            <th width="15%">Account</th>
                                            <th width="20%">Particular</th>
                                            <th width="10%" class="text-end">Beginning</th>
                                            <th width="10%" class="text-end">Dr</th>
                                            <th width="10%" class="text-end">Cr</th>
                                            <th width="10%" class="text-end">Ending</th>
                                            <th width="10%" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($voucher_entries as $entry): 
                                            $entry['beginning_balance'] = roundAmount($entry['beginning_balance']);
                                            $entry['debit_amount'] = roundAmount($entry['debit_amount']);
                                            $entry['credit_amount'] = roundAmount($entry['credit_amount']);
                                            $entry['ending_balance'] = roundAmount($entry['ending_balance']);
                                        ?>
                                        <tr data-entry-id="<?php echo $entry['ledger_id']; ?>">
                                            <td>
                                                <input type="checkbox" class="form-check-input entry-checkbox" 
                                                       value="<?php echo $entry['ledger_id']; ?>">
                                            </td>
                                            <td><small>#<?php echo $entry['ledger_id']; ?></small></td>
                                            <td>
                                                <small><?php echo date('d/m/y', strtotime($entry['transaction_date'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="account-code"><?php echo htmlspecialchars($entry['account_code']); ?></div>
                                                <small><?php echo htmlspecialchars($entry['account_name']); ?></small>
                                            </td>
                                            <td>
                                                <small title="<?php echo htmlspecialchars($entry['narration']); ?>">
                                                    <?php echo htmlspecialchars(substr($entry['particular'], 0, 30)); ?>
                                                    <?php if (strlen($entry['particular']) > 30): ?>...<?php endif; ?>
                                                </small>
                                            </td>
                                            <td class="text-end <?php echo $entry['beginning_balance'] >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                                                <?php echo formatMoney($entry['beginning_balance']); ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if($entry['debit_amount'] > 0): ?>
                                                    <span class="badge debit-badge"><?php echo formatMoney($entry['debit_amount']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if($entry['credit_amount'] > 0): ?>
                                                    <span class="badge credit-badge"><?php echo formatMoney($entry['credit_amount']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end <?php echo $entry['ending_balance'] >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                                                <?php echo formatMoney($entry['ending_balance']); ?>
                                            </td>
                                            <td class="text-center action-buttons">
                                                <a href="index.php?page=ledger_management&action=edit&id=<?php echo $entry['ledger_id']; ?>" 
                                                   class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal"
                                                        data-id="<?php echo $entry['ledger_id']; ?>"
                                                        data-voucher="<?php echo htmlspecialchars($entry['voucher_number']); ?>"
                                                        data-account="<?php echo htmlspecialchars($entry['account_code'] . ' - ' . $entry['account_name']); ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <!-- Voucher totals -->
                                        <tr class="total-row">
                                            <td colspan="6" class="text-end"><strong>Voucher Totals:</strong></td>
                                            <td class="text-end"><strong class="text-success"><?php echo formatMoney($voucher_total_debit); ?></strong></td>
                                            <td class="text-end"><strong class="text-danger"><?php echo formatMoney($voucher_total_credit); ?></strong></td>
                                            <td class="text-end"></td>
                                            <td class="text-center"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Overall totals -->
                        <div class="alert alert-info mt-4">
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Total Entries Displayed:</strong> <?php echo count($entries_by_voucher); ?> vouchers
                                </div>
                                <div class="col-md-4 text-center">
                                    <strong>Total Debits:</strong>  <?php echo formatMoney($total_debits); ?>
                                </div>
                                <div class="col-md-4 text-end">
                                    <strong>Total Credits:</strong>  <?php echo formatMoney($total_credits); ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-database fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No ledger entries found</h5>
                            <p class="text-muted">Try adjusting your search filters</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal (Single Entry) -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this ledger entry?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Warning:</strong> This action will:<br>
                        1. Delete the ledger entry permanently<br>
                        2. Recalculate all ending balances for the affected account<br>
                        3. This action cannot be undone
                    </div>
                    <p><strong>Voucher:</strong> <span id="deleteVoucher"></span></p>
                    <p><strong>Account:</strong> <span id="deleteAccount"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="deleteConfirmBtn" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Delete Entry
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Selected Modal -->
    <div class="modal fade" id="deleteSelectedModal" tabindex="-1" aria-labelledby="deleteSelectedModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Selected Entries</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the selected <span id="selectedCountModal">0</span> entries?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Warning:</strong> This action will:<br>
                        1. Delete all selected ledger entries permanently<br>
                        2. Recalculate ending balances for all affected accounts<br>
                        3. This action cannot be undone
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="bulkDeleteForm" class="btn btn-danger" id="confirmBulkDelete">
                        <i class="fas fa-trash me-1"></i>Delete Selected
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Voucher Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content modal-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Bulk Delete Voucher</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <i class="fas fa-radiation me-2"></i>
                            <strong>EXTREME WARNING:</strong><br>
                            This will delete ALL entries with the specified voucher number.<br>
                            This action cannot be undone!
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Enter Voucher Number to Delete</label>
                            <input type="text" class="form-control" name="voucher_number" 
                                   placeholder="e.g., RV-20240123-001" required>
                            <small class="text-muted">Double-check the voucher number before proceeding</small>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirmDelete" required>
                            <label class="form-check-label" for="confirmDelete">
                                I understand this will permanently delete all ledger entries with this voucher number
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="bulk_delete_voucher" class="btn btn-danger">
                            <i class="fas fa-bomb me-1"></i>Delete Entire Voucher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Voucher Modal (From Voucher Header) -->
    <div class="modal fade" id="deleteVoucherModal" tabindex="-1" aria-labelledby="deleteVoucherModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content modal-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Entire Voucher</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-radiation me-2"></i>
                        <strong>EXTREME WARNING:</strong><br>
                        This will delete ALL entries for voucher: <strong id="voucherToDelete"></strong><br>
                        This action cannot be undone!
                    </div>
                    
                    <p>This will affect the following accounts:</p>
                    <ul id="affectedAccounts" class="mb-3"></ul>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmVoucherDelete" required>
                        <label class="form-check-label" for="confirmVoucherDelete">
                            I understand this will permanently delete the entire voucher
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="voucher_number" id="voucherNumberInput">
                        <button type="submit" name="bulk_delete_voucher" class="btn btn-danger" id="voucherDeleteBtn" disabled>
                            <i class="fas fa-bomb me-1"></i>Delete Entire Voucher
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Single entry delete modal
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const entryId = button.getAttribute('data-id');
                const voucher = button.getAttribute('data-voucher');
                const account = button.getAttribute('data-account');
                
                document.getElementById('deleteVoucher').textContent = voucher;
                document.getElementById('deleteAccount').textContent = account;
                
                const deleteUrl = `index.php?page=ledger_management&action=delete&id=${entryId}`;
                document.getElementById('deleteConfirmBtn').href = deleteUrl;
            });
        }
        
        // Voucher delete modal
        const deleteVoucherModal = document.getElementById('deleteVoucherModal');
        if (deleteVoucherModal) {
            deleteVoucherModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const voucher = button.getAttribute('data-voucher');
                
                document.getElementById('voucherToDelete').textContent = voucher;
                document.getElementById('voucherNumberInput').value = voucher;
                
                // Get accounts affected by this voucher from the table
                const voucherElement = document.getElementById(`voucher-${encodeURIComponent(voucher)}`);
                if (voucherElement) {
                    const accounts = new Set();
                    const rows = voucherElement.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const accountCell = row.querySelector('.account-code');
                        if (accountCell) {
                            accounts.add(accountCell.textContent.trim());
                        }
                    });
                    
                    const accountsList = document.getElementById('affectedAccounts');
                    accountsList.innerHTML = '';
                    accounts.forEach(account => {
                        const li = document.createElement('li');
                        li.textContent = account;
                        accountsList.appendChild(li);
                    });
                }
            });
        }
        
        // Enable/disable voucher delete button based on checkbox
        const confirmCheckbox = document.getElementById('confirmVoucherDelete');
        const voucherDeleteBtn = document.getElementById('voucherDeleteBtn');
        
        if (confirmCheckbox && voucherDeleteBtn) {
            confirmCheckbox.addEventListener('change', function() {
                voucherDeleteBtn.disabled = !this.checked;
            });
        }
        
        // Enable/disable bulk delete button based on checkbox
        const confirmBulkCheckbox = document.getElementById('confirmDelete');
        const bulkDeleteBtn = document.querySelector('[name="bulk_delete_voucher"]');
        
        if (confirmBulkCheckbox && bulkDeleteBtn) {
            confirmBulkCheckbox.addEventListener('change', function() {
                bulkDeleteBtn.disabled = !this.checked;
            });
        }
        
        // Selection management
        const entryCheckboxes = document.querySelectorAll('.entry-checkbox');
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');
        const selectedCountModal = document.getElementById('selectedCountModal');
        const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
        const clearSelectionBtn = document.getElementById('clearSelectionBtn');
        const selectedIdsInput = document.getElementById('selectedIds');
        
        // Voucher select all
        document.querySelectorAll('.voucher-select-all').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const voucher = this.dataset.voucher;
                const voucherGroup = document.getElementById(`voucher-${encodeURIComponent(voucher)}`);
                if (voucherGroup) {
                    const entryCheckboxes = voucherGroup.querySelectorAll('.entry-checkbox');
                    
                    entryCheckboxes.forEach(cb => {
                        cb.checked = this.checked;
                        cb.closest('tr').classList.toggle('selected-row', this.checked);
                    });
                    
                    updateSelection();
                }
            });
        });
        
        // Individual entry checkbox
        entryCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                this.closest('tr').classList.toggle('selected-row', this.checked);
                updateSelection();
                
                // Update voucher select-all checkbox
                const voucherGroup = this.closest('.voucher-group');
                if (voucherGroup) {
                    const voucherSelectAll = voucherGroup.querySelector('.voucher-select-all');
                    const voucherCheckboxes = voucherGroup.querySelectorAll('.entry-checkbox');
                    const allChecked = Array.from(voucherCheckboxes).every(cb => cb.checked);
                    const anyChecked = Array.from(voucherCheckboxes).some(cb => cb.checked);
                    
                    if (voucherSelectAll) {
                        voucherSelectAll.checked = allChecked;
                        voucherSelectAll.indeterminate = anyChecked && !allChecked;
                    }
                }
            });
        });
        
        function updateSelection() {
            const selected = document.querySelectorAll('.entry-checkbox:checked');
            const count = selected.length;
            
            selectedCount.textContent = count;
            selectedCountModal.textContent = count;
            
            if (count > 0) {
                bulkActions.style.display = 'block';
            } else {
                bulkActions.style.display = 'none';
            }
            
            // Update selected IDs in hidden field
            const selectedIds = Array.from(selected).map(cb => cb.value);
            selectedIdsInput.value = JSON.stringify(selectedIds);
        }
        
        // Clear selection
        if (clearSelectionBtn) {
            clearSelectionBtn.addEventListener('click', function() {
                entryCheckboxes.forEach(cb => {
                    cb.checked = false;
                    cb.closest('tr').classList.remove('selected-row');
                });
                
                document.querySelectorAll('.voucher-select-all').forEach(cb => {
                    cb.checked = false;
                    cb.indeterminate = false;
                });
                
                updateSelection();
            });
        }
        
        // Delete selected button
        if (deleteSelectedBtn) {
            deleteSelectedBtn.addEventListener('click', function() {
                const selectedCount = document.querySelectorAll('.entry-checkbox:checked').length;
                if (selectedCount > 0) {
                    // Update the modal count
                    selectedCountModal.textContent = selectedCount;
                    
                    // Show the modal
                    const deleteSelectedModal = new bootstrap.Modal(document.getElementById('deleteSelectedModal'));
                    deleteSelectedModal.show();
                } else {
                    alert('Please select at least one entry to delete.');
                }
            });
        }
        
        // Auto-toggle row selection when clicking anywhere on the row
        document.querySelectorAll('tbody tr').forEach(row => {
            // Skip total rows and rows without checkboxes
            if (!row.classList.contains('total-row') && row.querySelector('.entry-checkbox')) {
                row.addEventListener('click', function(e) {
                    // Don't trigger if clicking on action buttons or links
                    if (!e.target.closest('.action-buttons') && 
                        !e.target.closest('a') && 
                        !e.target.closest('button') &&
                        !e.target.closest('.entry-checkbox')) {
                        const checkbox = row.querySelector('.entry-checkbox');
                        if (checkbox) {
                            checkbox.checked = !checkbox.checked;
                            const event = new Event('change');
                            checkbox.dispatchEvent(event);
                        }
                    }
                });
            }
        });
        
        // Close edit modal if clicking outside
        const editModalBackdrop = document.getElementById('editModalBackdrop');
        if (editModalBackdrop) {
            editModalBackdrop.addEventListener('click', function() {
                window.location.href = 'index.php?page=ledger_management';
            });
        }
    });
    
    // Confirm before deleting single entry
    function confirmDelete(entryId, voucher, account) {
        if (confirm(`Are you sure you want to delete this ledger entry?\n\nVoucher: ${voucher}\nAccount: ${account}\n\nThis will recalculate account balances and cannot be undone!`)) {
            window.location.href = `index.php?page=ledger_management&action=delete&id=${entryId}`;
        }
    }
    </script>
</body>
</html>

<?php 
// Close connections
if (isset($ledger_result)) mysqli_free_result($ledger_result);
if (isset($accounts_result)) mysqli_free_result($accounts_result);
if (isset($vouchers_result)) mysqli_free_result($vouchers_result);
if (isset($conn)) mysqli_close($conn);
?>
