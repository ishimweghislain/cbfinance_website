<?php
// edit_chart_of_account.php
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

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ?page=chart_of_accounts");
    exit();
}

$account_id = (int)$_GET['id'];

// Fetch account data
$account_query = "SELECT * FROM chart_of_accounts WHERE account_id = $account_id";
$account_result = $conn->query($account_query);

if (!$account_result || $account_result->num_rows == 0) {
    $error_message = "Account not found!";
    $account = null;
} else {
    $account = $account_result->fetch_assoc();
}

// Define account classes and types for dropdowns
$account_classes = [
    'Balance Sheet' => 'Balance Sheet',
    'Income Statement' => 'Income Statement'
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

// Handle form submission for UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_account'])) {
    $class = mysqli_real_escape_string($conn, trim($_POST['class']));
    $account_code = mysqli_real_escape_string($conn, trim($_POST['account_code']));
    $account_name = mysqli_real_escape_string($conn, trim($_POST['account_name']));
    $account_type = mysqli_real_escape_string($conn, trim($_POST['account_type']));
    $sub_type = mysqli_real_escape_string($conn, trim($_POST['sub_type']));
    $normal_balance = mysqli_real_escape_string($conn, trim($_POST['normal_balance']));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($account_code) || empty($account_name) || empty($account_type)) {
        $error_message = "Required fields: Account Code, Account Name, and Account Type";
    } elseif (!preg_match('/^[0-9]{1,6}$/', $account_code)) {
        $error_message = "Account Code must be numeric (1-6 digits)";
    } else {
        // Check if account code exists for other accounts
        $check_query = "SELECT account_id FROM chart_of_accounts WHERE account_code = '$account_code' AND account_id != $account_id";
        $check_result = $conn->query($check_query);
        
        if ($check_result && $check_result->num_rows > 0) {
            $error_message = "Account Code already used by another account!";
        } else {
            // Update account
            $update_query = "UPDATE chart_of_accounts SET 
                class = '$class', 
                account_code = '$account_code', 
                account_name = '$account_name', 
                account_type = '$account_type', 
                sub_type = '$sub_type', 
                normal_balance = '$normal_balance', 
                is_active = $is_active,
                updated_at = NOW()
                WHERE account_id = $account_id";
            
            if ($conn->query($update_query)) {
                $success_message = "Account updated successfully!";
                echo "<script>window.location.href='?page=chart_of_accounts' </script>";
                $account_result = $conn->query($account_query);
                $account = $account_result->fetch_assoc();
            } else {
                $error_message = "Failed to update account: " . $conn->error;
            }
        }
    }
}

// Handle DELETE action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_account'])) {
    // Check if account has transactions
    $check_query = "SELECT COUNT(*) as count FROM journal_entries WHERE account_code = '" . mysqli_real_escape_string($conn, $account['account_code']) . "'";
    $check_result = $conn->query($check_query);
    
    if ($check_result) {
        $row = $check_result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $error_message = "Cannot delete account. It has associated transactions.";
        } else {
            // Delete the account
            $delete_query = "DELETE FROM chart_of_accounts WHERE account_id = $account_id";
            if ($conn->query($delete_query)) {
                $conn->close();
                header("Location: ?page=chart_of_accounts?success=Account+deleted+successfully");
                exit();
            } else {
                $error_message = "Failed to delete account: " . $conn->error;
            }
        }
    }
}
?>

<div class="container-fluid">
    <!-- Messages -->
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h4 fw-bold text-primary">
                        <i class="fas fa-edit me-2"></i>
                        Edit Account
                    </h2>
                    <p class="text-muted mb-0">
                        Edit account details for: 
                        <strong><?php echo htmlspecialchars($account['account_name'] ?? ''); ?></strong>
                        (<code><?php echo $account['account_code'] ?? ''; ?></code>)
                    </p>
                </div>
                <a href="?page=chart_of_accounts" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Accounts
                </a>
            </div>
        </div>
    </div>

    <?php if ($account): ?>
    <div class="row">
        <!-- Account Form -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Edit Account Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="editAccountForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Code <span class="text-danger">*</span></label>
                                <input type="text" name="account_code" class="form-control" 
                                    value="<?php echo htmlspecialchars($account['account_code']); ?>" 
                                    pattern="[0-9]{1,6}" title="1-6 digit numeric code" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Name <span class="text-danger">*</span></label>
                                <input type="text" name="account_name" class="form-control" 
                                    value="<?php echo htmlspecialchars($account['account_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class <span class="text-danger">*</span></label>
                                <select name="class" class="form-select" required id="classSelect">
                                    <option value="">-- Select Class --</option>
                                    <?php foreach ($account_classes as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"
                                            <?php echo ($account['class'] == $key) ? 'selected' : ''; ?>>
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
                                        <option value="<?php echo $key; ?>"
                                            <?php echo ($account['account_type'] == $key) ? 'selected' : ''; ?>
                                            data-normal-balance="<?php echo in_array($key, ['Asset', 'Expense', 'Loss']) ? 'Debit' : 'Credit'; ?>">
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
                                    <?php foreach ($sub_types[$account['account_type']] ?? [] as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"
                                            <?php echo ($account['sub_type'] == $key) ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Normal Balance <span class="text-danger">*</span></label>
                                <select name="normal_balance" class="form-select" required id="normalBalanceSelect">
                                    <option value="">-- Select Balance --</option>
                                    <?php foreach ($normal_balances as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"
                                            <?php echo ($account['normal_balance'] == $key) ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="balanceHint" class="text-muted">
                                    <?php echo $account['account_type']; ?> accounts normally have a <?php echo $account['normal_balance']; ?> balance
                                </small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="isActive" 
                                    <?php echo ($account['is_active'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="isActive">
                                    Account is Active
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" name="update_account" class="btn btn-warning me-2">
                                <i class="fas fa-save me-2"></i>Update Account
                            </button>
                            <a href="?page=chart_of_accounts" class="btn btn-secondary me-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Information Sidebar -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-muted">Created</h6>
                        <p class="mb-0">
                            <i class="fas fa-calendar-plus me-2 text-muted"></i>
                            <?php echo date('F j, Y', strtotime($account['created_at'])); ?>
                        </p>
                        <small class="text-muted">
                            <?php echo date('h:i A', strtotime($account['created_at'])); ?>
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-muted">Last Updated</h6>
                        <p class="mb-0">
                            <i class="fas fa-calendar-check me-2 text-muted"></i>
                            <?php echo date('F j, Y', strtotime($account['updated_at'])); ?>
                        </p>
                        <small class="text-muted">
                            <?php echo date('h:i A', strtotime($account['updated_at'])); ?>
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-muted">Current Status</h6>
                        <p class="mb-0">
                            <?php if ($account['is_active'] == 1): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle me-1"></i>Active
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger">
                                    <i class="fas fa-times-circle me-1"></i>Inactive
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-muted">Account Type</h6>
                        <p class="mb-0">
                            <span class="badge bg-<?php 
                                echo $account['account_type'] == 'Asset' ? 'primary' : 
                                      ($account['account_type'] == 'Liability' ? 'warning' : 
                                      ($account['account_type'] == 'Equity' ? 'info' : 
                                      ($account['account_type'] == 'Revenue' ? 'success' : 'danger'))); 
                            ?>">
                                <?php echo $account['account_type']; ?>
                            </span>
                        </p>
                    </div>
                    
                    <div>
                        <h6 class="text-muted">Normal Balance</h6>
                        <p class="mb-0">
                            <span class="badge bg-light text-dark border">
                                <i class="fas fa-balance-scale me-1"></i>
                                <?php echo $account['normal_balance']; ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Warning</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        <i class="fas fa-info-circle text-warning me-2"></i>
                        Changing account details may affect existing transactions and reports.
                    </p>
                    <p class="small text-muted">
                        <i class="fas fa-info-circle text-warning me-2"></i>
                        Account code should remain unique across all accounts.
                    </p>
                    <p class="small text-muted">
                        <i class="fas fa-info-circle text-warning me-2"></i>
                        Deleting an account will permanently remove it from the system.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this account?</p>
                    <div class="alert alert-warning">
                        <h6 class="alert-heading">Account Details:</h6>
                        <p class="mb-1"><strong>Code:</strong> <?php echo $account['account_code']; ?></p>
                        <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($account['account_name']); ?></p>
                        <p class="mb-0"><strong>Type:</strong> <?php echo $account['account_type']; ?> (<?php echo $account['sub_type']; ?>)</p>
                    </div>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        This action cannot be undone!
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="delete_account" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-exclamation-circle fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">Account Not Found</h4>
            <p>The account you're trying to edit does not exist or has been deleted.</p>
            <a href="?page=chart_of_accounts" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Accounts
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const accountTypeSelect = document.getElementById('accountTypeSelect');
    const subTypeSelect = document.getElementById('subTypeSelect');
    const normalBalanceSelect = document.getElementById('normalBalanceSelect');
    const balanceHint = document.getElementById('balanceHint');
    
    // Define sub-types for each account type
    const subTypes = <?php echo json_encode($sub_types); ?>;
    
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
    const form = document.getElementById('editAccountForm');
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
    
    // Initialize delete modal
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            // Optional: Do something when modal shows
        });
    }
});
</script>

<?php 
$conn->close();
?>
