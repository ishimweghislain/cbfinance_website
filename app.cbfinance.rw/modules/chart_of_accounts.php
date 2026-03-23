<?php
// chart_of_accounts.php
// Start session
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

$conn->set_charset("utf8mb4");

$error_message = '';
$success_message = '';

// Handle delete action with CASCADE
if (isset($_GET['delete'])) {
    $account_id = (int)$_GET['delete'];
    
    // First, get the account_code for this account_id
    $get_code_query = "SELECT account_code, account_name FROM chart_of_accounts WHERE account_id = ?";
    $stmt = $conn->prepare($get_code_query);
    
    if ($stmt === false) {
        $_SESSION['error_message'] = "Database error: " . $conn->error;
        header("Location: ?page=chart_of_accounts");
        exit();
    }
    
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $account = $result->fetch_assoc();
        $account_code = $account['account_code'];
        $account_name = $account['account_name'];
        $stmt->close();
        
        // Begin transaction for cascade delete
        $conn->begin_transaction();
        
        try {
            $deleted_items = [];
            
            // Delete from journal_entries table
            $delete_journal_query = "DELETE FROM journal_entries WHERE account_code = ?";
            $delete_journal_stmt = $conn->prepare($delete_journal_query);
            
            if ($delete_journal_stmt !== false) {
                $delete_journal_stmt->bind_param("s", $account_code);
                $delete_journal_stmt->execute();
                $journal_deleted = $delete_journal_stmt->affected_rows;
                if ($journal_deleted > 0) {
                    $deleted_items[] = "$journal_deleted journal entries";
                }
                $delete_journal_stmt->close();
            }
            
            // Delete from ledger table
            $delete_ledger_query = "DELETE FROM ledger WHERE account_code = ?";
            $delete_ledger_stmt = $conn->prepare($delete_ledger_query);
            
            if ($delete_ledger_stmt !== false) {
                $delete_ledger_stmt->bind_param("s", $account_code);
                $delete_ledger_stmt->execute();
                $ledger_deleted = $delete_ledger_stmt->affected_rows;
                if ($ledger_deleted > 0) {
                    $deleted_items[] = "$ledger_deleted ledger entries";
                }
                $delete_ledger_stmt->close();
            }
            
            // Delete the account itself
            $delete_account_query = "DELETE FROM chart_of_accounts WHERE account_id = ?";
            $delete_account_stmt = $conn->prepare($delete_account_query);
            
            if ($delete_account_stmt === false) {
                throw new Exception("Failed to prepare delete statement: " . $conn->error);
            }
            
            $delete_account_stmt->bind_param("i", $account_id);
            
            if (!$delete_account_stmt->execute()) {
                throw new Exception("Failed to delete account: " . $conn->error);
            }
            
            $delete_account_stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            // Build success message
            $success_msg = "Account '$account_code - $account_name' deleted successfully!";
            if (!empty($deleted_items)) {
                $success_msg .= " Also deleted: " . implode(", ", $deleted_items) . ".";
            }
            $_SESSION['success_message'] = $success_msg;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error_message'] = "Failed to delete account: " . $e->getMessage();
        }
        
    } else {
        $_SESSION['error_message'] = "Account not found.";
        if ($stmt) $stmt->close();
    }
    
    // Redirect to clear the delete parameter
    echo "<script>window.location.href='?page=chart_of_accounts';</script>";
    exit();
}

// Retrieve session messages and clear them
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Define account classes and types for dropdowns
$account_classes = [
    'Balance Sheet' => 'Balance Sheet',
    'Income Statement' => 'Income Statement'
];

// Mapping Classes to their allowed Account Types
$class_to_types = [
    'Balance Sheet' => ['Asset', 'Liability', 'Equity'],
    'Income Statement' => ['Revenue', 'Expense', 'Gain', 'Loss']
];

$account_types = [
    'Asset' => 'Asset',
    'Liability' => 'Liability', 
    'Equity' => 'Equity',
    'Revenue' => 'Revenue',
    'Expense' => 'Expense',
    'Gain' => 'Gain',
    'Loss' => 'Loss'
];

$sub_types = [
    'Asset' => [
        'Current Asset' => 'Current Asset',
        'Fixed Asset' => 'Fixed Asset',
        'Intangible Asset' => 'Intangible Asset',
        'Investment' => 'Investment',
        'Other Asset' => 'Other Asset'
    ],
    'Liability' => [
        'Current Liability' => 'Current Liability',
        'Long-term Liability' => 'Long-term Liability',
        'Contingent Liability' => 'Contingent Liability',
        'Other Liability' => 'Other Liability'
    ],
    'Equity' => [
        'Capital Stock' => 'Capital Stock',
        'Retained Earnings' => 'Retained Earnings',
        'Treasury Stock' => 'Treasury Stock',
        'Other Equity' => 'Other Equity'
    ],
    'Revenue' => [
        'Operating Revenue' => 'Operating Revenue',
        'Non-operating Revenue' => 'Non-operating Revenue'
    ],
    'Expense' => [
        'Operating Expense' => 'Operating Expense',
        'Non-operating Expense' => 'Non-operating Expense',
        'Cost of Goods Sold' => 'Cost of Goods Sold',
        'Administrative Expense' => 'Administrative Expense'
    ],
    'Gain' => [
        'Capital Gain' => 'Capital Gain',
        'Operating Gain' => 'Operating Gain'
    ],
    'Loss' => [
        'Capital Loss' => 'Capital Loss',
        'Operating Loss' => 'Operating Loss'
    ]
];

$normal_balances = [
    'Debit' => 'Debit',
    'Credit' => 'Credit'
];

// Handle ADD NEW ACCOUNT form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_account'])) {
    $class = trim($_POST['class']);
    $account_code = trim($_POST['account_code']);
    $account_name = trim($_POST['account_name']);
    $account_type = trim($_POST['account_type']);
    $sub_type = trim($_POST['sub_type']);
    $normal_balance = trim($_POST['normal_balance']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($account_code) || empty($account_name) || empty($account_type)) {
        $error_message = "Required fields: Account Code, Account Name, and Account Type";
    } elseif (!preg_match('/^[0-9]{1,6}$/', $account_code)) {
        $error_message = "Account Code must be numeric (1-6 digits)";
    } else {
        // Check if account code already exists using prepared statement
        $check_query = "SELECT account_id FROM chart_of_accounts WHERE account_code = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $account_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result && $check_result->num_rows > 0) {
            $error_message = "Account Code already exists!";
        } else {
            // Insert new account using prepared statement
            $insert_query = "INSERT INTO chart_of_accounts 
                (class, account_code, account_name, account_type, sub_type, normal_balance, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("ssssssi", $class, $account_code, $account_name, $account_type, $sub_type, $normal_balance, $is_active);
            
            if ($insert_stmt->execute()) {
                $success_message = "Account added successfully: $account_code - $account_name";
                // Clear form
                $_POST = array();
            } else {
                $error_message = "Failed to add account: " . $conn->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Get all accounts for display
$accounts_query = "
    SELECT * FROM chart_of_accounts 
    ORDER BY 
        CASE class 
            WHEN 'Balance Sheet' THEN 1 
            WHEN 'Income Statement' THEN 2 
            ELSE 3 
        END,
        account_code ASC
";
$accounts_result = $conn->query($accounts_query);

// Count active/inactive accounts
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM chart_of_accounts
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get recent changes
$recent_query = "
    SELECT * FROM chart_of_accounts 
    ORDER BY updated_at DESC 
    LIMIT 5
";
$recent_result = $conn->query($recent_query);
?>

<div class="container-fluid">
    <!-- Messages -->
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chart of Accounts Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="h4 fw-bold text-primary">Chart of Accounts Management</h2>
            <p class="text-muted">Manage your accounting chart of accounts</p>
        </div>
    </div>

    <div class="row">
        <!-- LEFT: Add New Account Form -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-plus-circle me-2"></i>
                        Add New Account
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="accountForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Code <span class="text-danger">*</span></label>
                                <input type="text" name="account_code" class="form-control" 
                                    value="<?php echo isset($_POST['account_code']) ? htmlspecialchars($_POST['account_code']) : ''; ?>" 
                                    pattern="[0-9]{1,6}" title="1-6 digit numeric code" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Name <span class="text-danger">*</span></label>
                                <input type="text" name="account_name" class="form-control" 
                                    value="<?php echo isset($_POST['account_name']) ? htmlspecialchars($_POST['account_name']) : ''; ?>" 
                                    required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class <span class="text-danger">*</span></label>
                                <select name="class" class="form-select" required id="classSelect">
                                    <option value="">-- Select Class --</option>
                                    <?php foreach ($account_classes as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"
                                            <?php echo (isset($_POST['class']) && $_POST['class'] == $key) ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Type <span class="text-danger">*</span></label>
                                <select name="account_type" class="form-select" required id="accountTypeSelect">
                                    <option value="">-- Select Type --</option>
                                    <?php foreach ($account_types as $key => $value): ?>
                                        <?php 
                                            // Determine which class this type belongs to
                                            $belongs_to = '';
                                            foreach($class_to_types as $c => $ts) {
                                                if(in_array($key, $ts)) { $belongs_to = $c; break; }
                                            }
                                        ?>
                                        <option value="<?php echo $key; ?>"
                                            class="type-option"
                                            data-class="<?php echo $belongs_to; ?>"
                                            <?php echo (isset($_POST['account_type']) && $_POST['account_type'] == $key) ? 'selected' : ''; ?>
                                            data-normal-balance="<?php echo in_array($key, ['Asset', 'Expense', 'Loss']) ? 'Debit' : 'Credit'; ?>"
                                            style="display:none;">
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sub Type <span class="text-danger">*</span></label>
                                <select name="sub_type" class="form-select" required id="subTypeSelect">
                                    <option value="">-- Select Sub Type --</option>
                                    <?php if (isset($_POST['account_type']) && !empty($_POST['account_type'])): ?>
                                        <?php foreach ($sub_types[$_POST['account_type']] ?? [] as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"
                                                <?php echo (isset($_POST['sub_type']) && $_POST['sub_type'] == $key) ? 'selected' : ''; ?>>
                                                <?php echo $value; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Normal Balance <span class="text-danger">*</span></label>
                                <select name="normal_balance" class="form-select" required id="normalBalanceSelect">
                                    <option value="">-- Select Balance --</option>
                                    <?php foreach ($normal_balances as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"
                                            <?php echo (isset($_POST['normal_balance']) && $_POST['normal_balance'] == $key) ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="balanceHint" class="text-muted"></small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="isActive" checked>
                                <label class="form-check-label" for="isActive">
                                    Account is Active
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_account" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i>Add Account
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Account Statistics -->
            <div class="card mt-3">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Account Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <h3 class="text-primary"><?php echo $stats['total']; ?></h3>
                            <small class="text-muted">Total Accounts</small>
                        </div>
                        <div class="col-4">
                            <h3 class="text-success"><?php echo $stats['active']; ?></h3>
                            <small class="text-muted">Active</small>
                        </div>
                        <div class="col-4">
                            <h3 class="text-danger"><?php echo $stats['inactive']; ?></h3>
                            <small class="text-muted">Inactive</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Accounts List and Recent Changes -->
        <div class="col-md-1/2 mb-4">
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Accounts List</h5>
                        <span class="badge bg-light text-dark"><?php echo $accounts_result->num_rows; ?> accounts</span>
                    </div>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if ($accounts_result && $accounts_result->num_rows > 0): ?>
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Account Name</th>
                                    <th>Type</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($account = $accounts_result->fetch_assoc()): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($account['account_code']); ?></code></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($account['account_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($account['sub_type']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $account['account_type'] == 'Asset' ? 'primary' : 
                                                  ($account['account_type'] == 'Liability' ? 'warning' : 
                                                  ($account['account_type'] == 'Equity' ? 'info' : 
                                                  ($account['account_type'] == 'Revenue' ? 'success' : 'danger'))); 
                                        ?>">
                                            <?php echo htmlspecialchars($account['account_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo htmlspecialchars($account['normal_balance']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($account['is_active'] == 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?page=edit_chart_of_account&id=<?php echo $account['account_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Edit">
                                           <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?page=chart_of_accounts&delete=<?php echo $account['account_id']; ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete account <?php echo htmlspecialchars($account['account_code']); ?>? This action cannot be undone.');">
                                           <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">No accounts found. Add your first account.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const classSelect = document.getElementById('classSelect');
    const accountTypeSelect = document.getElementById('accountTypeSelect');
    const subTypeSelect = document.getElementById('subTypeSelect');
    const normalBalanceSelect = document.getElementById('normalBalanceSelect');
    const balanceHint = document.getElementById('balanceHint');
    
    // Define sub-types for each account type
    const subTypes = <?php echo json_encode($sub_types); ?>;
    
    // Filter Account Types based on Class Selection
    classSelect.addEventListener('change', function() {
        const selectedClass = this.value;
        const options = accountTypeSelect.querySelectorAll('.type-option');
        
        // Reset type selection
        accountTypeSelect.selectedIndex = 0;
        subTypeSelect.innerHTML = '<option value="">-- Select Sub Type --</option>';
        normalBalanceSelect.selectedIndex = 0;
        balanceHint.textContent = '';
        
        options.forEach(opt => {
            if (opt.getAttribute('data-class') === selectedClass || selectedClass === "") {
                opt.style.display = 'block';
                opt.disabled = false;
            } else {
                opt.style.display = 'none';
                opt.disabled = true;
            }
        });
    });

    // Update sub-types when account type changes
    accountTypeSelect.addEventListener('change', function() {
        const selectedType = this.value;
        const subtypes = subTypes[selectedType] || {};
        
        // Clear and repopulate sub-type dropdown
        subTypeSelect.innerHTML = '<option value="">-- Select Sub Type --</option>';
        
        for (const [key, value] of Object.entries(subtypes)) {
            const option = document.createElement('option');
            option.value = key;
            option.textContent = value;
            subTypeSelect.appendChild(option);
        }
        
        // Set normal balance based on account type
        const selectedOption = this.options[this.selectedIndex];
        const normalBalance = selectedOption.getAttribute('data-normal-balance');
        
        if (normalBalance) {
            // Set normal balance
            for (let i = 0; i < normalBalanceSelect.options.length; i++) {
                if (normalBalanceSelect.options[i].value === normalBalance) {
                    normalBalanceSelect.selectedIndex = i;
                    break;
                }
            }
            
            // Show hint
            balanceHint.textContent = selectedType + ' accounts normally have a ' + normalBalance + ' balance';
            balanceHint.className = 'text-muted';
            
            if (selectedType === 'Asset' || selectedType === 'Expense' || selectedType === 'Loss') {
                balanceHint.className = 'text-primary';
            } else {
                balanceHint.className = 'text-success';
            }
        }
    });
    
    // Form validation
    const form = document.getElementById('accountForm');
    form.addEventListener('submit', function(e) {
        const accountCode = form.querySelector('input[name="account_code"]');
        const accountName = form.querySelector('input[name="account_name"]');
        const accountType = form.querySelector('select[name="account_type"]');
        
        let valid = true;
        
        // Validate account code format
        if (!/^[0-9]{1,6}$/.test(accountCode.value)) {
            accountCode.classList.add('is-invalid');
            valid = false;
        } else {
            accountCode.classList.remove('is-invalid');
        }
        
        // Validate required fields
        [accountName, accountType].forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                valid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        if (!valid) {
            e.preventDefault();
            alert('Please fill all required fields correctly.');
        }
    });
});
</script>

</body>
</html>
<?php 
$conn->close();
?>
