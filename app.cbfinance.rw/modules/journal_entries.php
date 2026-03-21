<?php
require_once __DIR__ . '/../config/database.php';
$conn = getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_journal_entry'])) {
        $entry_date = $_POST['entry_date'];
        $voucher_number = $_POST['voucher_number'];
        $voucher_type_id = $_POST['voucher_type_id'];
        $reference_number = $_POST['reference_number'];
        $narration = $_POST['narration'];
        $loan_number = $_POST['loan_number'] ?? '';
        $created_by = $_SESSION['user_name'];

        // Get accounts data from form
        $account_codes = $_POST['account_code'];
        $account_names = $_POST['account_name'];
        $debits = $_POST['debit_amount'];
        $credits = $_POST['credit_amount'];
        $line_narrations = $_POST['line_narration'];

        // Calculate totals
        $total_debit = array_sum($debits);
        $total_credit = array_sum($credits);

        if (abs($total_debit - $total_credit) < 0.01) { // Allow small rounding differences
            // Insert journal entry
            $stmt = $conn->prepare("INSERT INTO journal_entries (entry_date, voucher_number, voucher_type_id, reference_number, loan_number, narration, total_debit, total_credit, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssisssdds", $entry_date, $voucher_number, $voucher_type_id, $reference_number, $loan_number, $narration, $total_debit, $total_credit, $created_by);
            $stmt->execute();

            $entry_id = $stmt->insert_id;
            $stmt->close();

            // Insert journal entry lines
            for ($i = 0; $i < count($account_codes); $i++) {
                if (!empty($account_codes[$i]) && ($debits[$i] > 0 || $credits[$i] > 0)) {
                    // Get account_id from account_code
                    $account_stmt = $conn->prepare("SELECT account_id FROM chart_of_accounts WHERE account_code = ?");
                    $account_stmt->bind_param("s", $account_codes[$i]);
                    $account_stmt->execute();
                    $account_result = $account_stmt->get_result();

                    if ($account_row = $account_result->fetch_assoc()) {
                        $account_id = $account_row['account_id'];

                        $stmt = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, line_number, account_id, account_code, account_name, debit_amount, credit_amount, line_narration) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $line_num = $i + 1;
                        $stmt->bind_param("iiissdds", $entry_id, $line_num, $account_id, $account_codes[$i], $account_names[$i], $debits[$i], $credits[$i], $line_narrations[$i]);
                        $stmt->execute();
                        $stmt->close();
                    }
                    $account_stmt->close();
                }
            }

            // Also insert into accounting_entries table for loan tracking
            if (!empty($loan_number)) {
                for ($i = 0; $i < count($account_codes); $i++) {
                    if (!empty($account_codes[$i]) && ($debits[$i] > 0 || $credits[$i] > 0)) {
                        $accounting_stmt = $conn->prepare("INSERT INTO accounting_entries (loan_number, account_code, transaction_type, debit, credit, description, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        $transaction_type = $debits[$i] > 0 ? 'Debit' : 'Credit';
                        $description = $line_narrations[$i] ?: $narration;
                        $accounting_stmt->bind_param("sssdds", $loan_number, $account_codes[$i], $transaction_type, $debits[$i], $credits[$i], $description);
                        $accounting_stmt->execute();
                        $accounting_stmt->close();
                    }
                }
            }

            $success_message = "Journal entry created successfully! Entry ID: $entry_id";
        }
        else {
            $error_message = "Journal entry is not balanced! Debit: " . number_format($total_debit, 2) . ", Credit: " . number_format($total_credit, 2);
        }
    }

    if (isset($_POST['post_entry'])) {
        $entry_id = $_POST['entry_id'];

        // Update journal entry status
        $conn->query("UPDATE journal_entries SET is_posted = TRUE, posted_date = NOW(), approved_by = '{$_SESSION['user_name']}' WHERE entry_id = $entry_id");

        // Update account balances
        $result = $conn->query("SELECT account_id, SUM(debit_amount) as total_debit, SUM(credit_amount) as total_credit FROM journal_entry_lines WHERE entry_id = $entry_id GROUP BY account_id");
        while ($row = $result->fetch_assoc()) {
            $balance_change = $row['total_debit'] - $row['total_credit'];
            $conn->query("UPDATE chart_of_accounts SET current_balance = current_balance + $balance_change WHERE account_id = {$row['account_id']}");
        }

        $success_message = "Journal entry posted successfully!";
    }
}

// Get voucher types
$voucher_types = $conn->query("SELECT * FROM voucher_types WHERE is_active = TRUE");

// Get accounts for dropdown
$accounts = $conn->query("SELECT account_id, account_code, account_name, account_type, current_balance FROM chart_of_accounts WHERE is_active = TRUE ORDER BY account_code");

// Get active loans for dropdown
$loans = $conn->query("SELECT loan_number, customer_name FROM loan_portfolio lp LEFT JOIN customers c ON lp.customer_id = c.customer_id WHERE loan_status = 'Active' ORDER BY loan_number");

// Get common loan-related accounts
$loan_accounts = $conn->query("SELECT account_code, account_name FROM chart_of_accounts WHERE is_active = TRUE AND account_type = 'Assets' AND (account_name LIKE '%Loan%' OR account_name LIKE '%Interest%' OR account_name LIKE '%Fee%') ORDER BY account_code");

?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="h4 fw-bold text-primary">Journal Entries</h2>
        <p class="text-muted">Record and manage accounting transactions</p>
    </div>
</div>

<style>
    /* Add these styles to the existing style section */
body {
    font-size: 12px !important;
}

.card {
    margin-bottom: 0.5rem !important;
}

.card-header {
    padding: 0.5rem 1rem !important;
}

.card-body {
    padding: 0.75rem !important;
}

.card-title, .card-header h5, .card-header h6 {
    font-size: 0.9rem !important;
    margin-bottom: 0.25rem !important;
}

.table {
    font-size: 0.8rem !important;
}

.table th, .table td {
    padding: 0.3rem 0.5rem !important;
}

.table-sm th, .table-sm td {
    padding: 0.2rem 0.4rem !important;
}

.form-control, .form-select {
    font-size: 0.85rem !important;
    padding: 0.25rem 0.5rem !important;
    height: calc(1.5em + 0.5rem) !important;
}

.btn {
    font-size: 0.8rem !important;
    padding: 0.25rem 0.5rem !important;
}

.btn-sm {
    font-size: 0.75rem !important;
    padding: 0.2rem 0.4rem !important;
}

.badge {
    font-size: 0.7rem !important;
    padding: 0.15rem 0.35rem !important;
}

.alert {
    font-size: 0.8rem !important;
    padding: 0.5rem 1rem !important;
    margin-bottom: 0.5rem !important;
}

h1, .h1 { font-size: 1.5rem !important; }
h2, .h2 { font-size: 1.3rem !important; }
h3, .h3 { font-size: 1.2rem !important; }
h4, .h4 { font-size: 1.1rem !important; }
h5, .h5 { font-size: 1rem !important; }
h6, .h6 { font-size: 0.9rem !important; }

.mb-1 { margin-bottom: 0.25rem !important; }
.mb-2 { margin-bottom: 0.5rem !important; }
.mb-3 { margin-bottom: 0.75rem !important; }
.mb-4 { margin-bottom: 1rem !important; }
.mb-5 { margin-bottom: 1.25rem !important; }

.mt-1 { margin-top: 0.25rem !important; }
.mt-2 { margin-top: 0.5rem !important; }
.mt-3 { margin-top: 0.75rem !important; }
.mt-4 { margin-top: 1rem !important; }
.mt-5 { margin-top: 1.25rem !important; }

.py-1 { padding-top: 0.25rem !important; padding-bottom: 0.25rem !important; }
.py-2 { padding-top: 0.5rem !important; padding-bottom: 0.5rem !important; }
.py-3 { padding-top: 0.75rem !important; padding-bottom: 0.75rem !important; }
.py-4 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
.py-5 { padding-top: 1.25rem !important; padding-bottom: 1.25rem !important; }

.px-1 { padding-left: 0.25rem !important; padding-right: 0.25rem !important; }
.px-2 { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
.px-3 { padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
.px-4 { padding-left: 1rem !important; padding-right: 1rem !important; }
.px-5 { padding-left: 1.25rem !important; padding-right: 1.25rem !important; }

.input-group-sm > .form-control,
.input-group-sm > .form-select {
    font-size: 0.75rem !important;
    padding: 0.2rem 0.4rem !important;
    height: calc(1.5em + 0.4rem) !important;
}

.btn-group-sm > .btn {
    font-size: 0.7rem !important;
    padding: 0.15rem 0.3rem !important;
}

/* Reduce spacing in flex gaps */
.gap-1 { gap: 0.25rem !important; }
.gap-2 { gap: 0.5rem !important; }
.gap-3 { gap: 0.75rem !important; }

/* Make text-muted even smaller */
.text-muted {
    font-size: 0.75rem !important;
}

/* Reduce line heights */
h1, h2, h3, h4, h5, h6 {
    line-height: 1.2 !important;
}

p, .form-label, .small {
    line-height: 1.3 !important;
}

/* Compact table rows */
.table-hover tbody tr {
    line-height: 1.2 !important;
}

/* Make icons smaller if needed */
.bi {
    font-size: 0.9em !important;
}

/* Report header adjustments */
.report-header h4 { font-size: 1.1rem !important; }
.report-header h5 { font-size: 1rem !important; }
.report-header p { font-size: 0.8rem !important; }

/* Form labels smaller */
.form-label {
    font-size: 0.8rem !important;
    margin-bottom: 0.1rem !important;
}

.form-check-label {
    font-size: 0.8rem !important;
}

/* Reduce spacing in rows */
.row {
    margin-bottom: 0.25rem !important;
}

/* Make the entire UI more compact */
.container-fluid {
    padding: 0.5rem !important;
}

/* Adjust breadcrumb if present */
.breadcrumb {
    padding: 0.25rem 0.5rem !important;
    font-size: 0.8rem !important;
    margin-bottom: 0.5rem !important;
}
</style>
<?php if (isset($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php
endif; ?>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php
endif; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addJournalEntryModal">
            <i class="fas fa-plus-circle me-2"></i> New Journal Entry
        </button>
    </div>
    <div class="col-md-6 text-end">
        <span class="text-muted">Showing last 50 entries</span>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Journal Entries</h5>
                <div>
                    <span class="badge bg-success">Posted: <?php echo $conn->query("SELECT COUNT(*) FROM journal_entries WHERE is_posted = TRUE")->fetch_row()[0]; ?></span>
                    <span class="badge bg-warning ms-2">Pending: <?php echo $conn->query("SELECT COUNT(*) FROM journal_entries WHERE is_posted = FALSE")->fetch_row()[0]; ?></span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Voucher #</th>
                                <th>Type</th>
                                <th>Loan #</th>
                                <th>Narration</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($entry = $entries->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($entry['entry_date'])); ?></td>
                                <td><code><?php echo htmlspecialchars($entry['voucher_number']); ?></code></td>
                                <td><?php echo htmlspecialchars($entry['voucher_type_name']); ?></td>
                                <td>
                                    <?php if (!empty($entry['loan_number'])): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($entry['loan_number']); ?></span>
                                    <?php
    else: ?>
                                        <span class="text-muted">-</span>
                                    <?php
    endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(substr($entry['narration'], 0, 40)) . (strlen($entry['narration']) > 40 ? '...' : ''); ?></td>
                                <td class="text-success fw-bold">FRW <?php echo number_format($entry['total_debit'], 2); ?></td>
                                <td class="text-danger fw-bold">FRW <?php echo number_format($entry['total_credit'], 2); ?></td>
                                <td>
                                    <?php if ($entry['is_posted']): ?>
                                    <span class="badge bg-success">Posted</span>
                                    <?php if ($entry['is_reversed']): ?>
                                    <span class="badge bg-danger ms-1">Reversed</span>
                                    <?php
        endif; ?>
                                    <?php
    else: ?>
                                    <span class="badge bg-warning">Pending</span>
                                    <?php
    endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewEntryModal<?php echo $entry['entry_id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (!$entry['is_posted']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="entry_id" value="<?php echo $entry['entry_id']; ?>">
                                            <button type="submit" name="post_entry" class="btn btn-outline-success" onclick="return confirm('Post this entry?')">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                        </form>
                                        <?php
    endif; ?>
                                    </div>
                                    
                                    <!-- View Entry Modal -->
                                    <div class="modal fade" id="viewEntryModal<?php echo $entry['entry_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Journal Entry Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Voucher #:</strong> <?php echo htmlspecialchars($entry['voucher_number']); ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Date:</strong> <?php echo date('M d, Y', strtotime($entry['entry_date'])); ?>
                                                        </div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Loan #:</strong> <?php echo !empty($entry['loan_number']) ? htmlspecialchars($entry['loan_number']) : '-'; ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Reference:</strong> <?php echo !empty($entry['reference_number']) ? htmlspecialchars($entry['reference_number']) : '-'; ?>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Narration:</strong> <?php echo htmlspecialchars($entry['narration']); ?>
                                                    </div>
                                                    
                                                    <h6 class="mb-3">Entry Lines</h6>
                                                    <?php
    $lines_result = $conn->query("SELECT * FROM journal_entry_lines WHERE entry_id = {$entry['entry_id']} ORDER BY line_number");
    if ($lines_result->num_rows > 0): ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th>Account Code</th>
                                                                    <th>Account Name</th>
                                                                    <th class="text-end">Debit</th>
                                                                    <th class="text-end">Credit</th>
                                                                    <th>Narration</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php while ($line = $lines_result->fetch_assoc()): ?>
                                                                <tr>
                                                                    <td><code><?php echo htmlspecialchars($line['account_code']); ?></code></td>
                                                                    <td><?php echo htmlspecialchars($line['account_name']); ?></td>
                                                                    <td class="text-end"><?php echo $line['debit_amount'] > 0 ? 'FRW ' . number_format($line['debit_amount'], 2) : '-'; ?></td>
                                                                    <td class="text-end"><?php echo $line['credit_amount'] > 0 ? 'FRW ' . number_format($line['credit_amount'], 2) : '-'; ?></td>
                                                                    <td><?php echo htmlspecialchars($line['line_narration']); ?></td>
                                                                </tr>
                                                                <?php
        endwhile; ?>
                                                            </tbody>
                                                            <tfoot>
                                                                <tr class="table-light">
                                                                    <td colspan="2" class="fw-bold text-end">Totals:</td>
                                                                    <td class="fw-bold text-end text-success">FRW <?php echo number_format($entry['total_debit'], 2); ?></td>
                                                                    <td class="fw-bold text-end text-danger">FRW <?php echo number_format($entry['total_credit'], 2); ?></td>
                                                                    <td></td>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>
                                                    <?php
    endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php
endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Journal Entry Modal -->
<div class="modal fade" id="addJournalEntryModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Journal Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <label class="form-label">Entry Date *</label>
                            <input type="date" class="form-control" name="entry_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Voucher Number *</label>
                            <input type="text" class="form-control" name="voucher_number" value="JV<?php echo date('YmdHis'); ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Voucher Type *</label>
                            <select class="form-select" name="voucher_type_id" required>
                                <option value="">Select Type</option>
                                <?php $voucher_types->data_seek(0);
while ($type = $voucher_types->fetch_assoc()): ?>
                                <option value="<?php echo $type['voucher_type_id']; ?>">
                                    <?php echo htmlspecialchars($type['voucher_type_name']); ?>
                                </option>
                                <?php
endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Loan Number</label>
                            <select class="form-select" name="loan_number" id="loan_number_select">
                                <option value="">Select Loan</option>
                                <?php while ($loan = $loans->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($loan['loan_number']); ?>">
                                    <?php echo htmlspecialchars($loan['loan_number'] . ' - ' . $loan['customer_name']); ?>
                                </option>
                                <?php
endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Reference Number</label>
                            <input type="text" class="form-control" name="reference_number">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Auto-fill from template:</label>
                            <select class="form-select" id="template_select" onchange="applyTemplate()">
                                <option value="">Select Template</option>
                                <option value="loan_payment">Loan Payment</option>
                                <option value="bad_debt">Bad Debt Payment</option>
                                <option value="loan_disbursement">Loan Disbursement</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Narration *</label>
                        <textarea class="form-control" name="narration" rows="2" required placeholder="e.g., First Instalment Payment by Customer Name"></textarea>
                    </div>
                    
                    <h6 class="mb-3">Journal Lines</h6>
                    <div id="journalLines">
                        <!-- Default 2 lines -->
                        <?php for ($i = 0; $i < 2; $i++): ?>
                        <div class="row mb-3 journal-line">
                            <div class="col-md-2">
                                <label class="form-label">Account Code *</label>
                                <input type="text" class="form-control account-code" name="account_code[]" list="accountCodes" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Account Name *</label>
                                <input type="text" class="form-control account-name" name="account_name[]" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Debit Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">FRW</span>
                                    <input type="number" class="form-control debit-amount" name="debit_amount[]" value="0.00" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Credit Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">FRW</span>
                                    <input type="number" class="form-control credit-amount" name="credit_amount[]" value="0.00" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Line Narration</label>
                                <input type="text" class="form-control line-narration" name="line_narration[]" placeholder="e.g., Principal, Interest, Fees, etc.">
                            </div>
                        </div>
                        <?php
endfor; ?>
                    </div>
                    
                    <datalist id="accountCodes">
                        <?php $accounts->data_seek(0);
while ($account = $accounts->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($account['account_code']); ?>">
                            <?php echo htmlspecialchars($account['account_name']); ?>
                        </option>
                        <?php
endwhile; ?>
                    </datalist>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addJournalLine">
                            <i class="fas fa-plus me-1"></i> Add Line
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" id="addLoanPaymentLines">
                            <i class="fas fa-hand-holding-usd me-1"></i> Add Loan Payment Lines
                        </button>
                    </div>
                    
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Total Debit:</strong> FRW <span id="totalDebit">0.00</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Total Credit:</strong> FRW <span id="totalCredit">0.00</span>
                            </div>
                        </div>
                        <div class="mt-2">
                            <strong>Balance:</strong> FRW <span id="balance">0.00</span>
                            <span id="balanceStatus" class="ms-2"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_journal_entry" class="btn btn-primary" id="submitEntry" disabled>
                        <i class="fas fa-save me-2"></i>Save Journal Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $conn->close(); ?>

<script>
$(document).ready(function() {
    let lineCounter = 2;
    
    // Add new journal line
    $('#addJournalLine').click(function() {
        const newLine = `
        <div class="row mb-3 journal-line">
            <div class="col-md-2">
                <input type="text" class="form-control account-code" name="account_code[]" list="accountCodes" required>
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control account-name" name="account_name[]" required>
            </div>
            <div class="col-md-2">
                <div class="input-group">
                    <span class="input-group-text">FRW</span>
                    <input type="number" class="form-control debit-amount" name="debit_amount[]" value="0.00" step="0.01" min="0">
                </div>
            </div>
            <div class="col-md-2">
                <div class="input-group">
                    <span class="input-group-text">FRW</span>
                    <input type="number" class="form-control credit-amount" name="credit_amount[]" value="0.00" step="0.01" min="0">
                </div>
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control line-narration" name="line_narration[]" placeholder="e.g., Principal, Interest, Fees, etc.">
                <button type="button" class="btn btn-danger btn-sm mt-1 remove-line">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="col-md-1 d-flex align-items-center">
                <button type="button" class="btn btn-outline-secondary btn-sm fill-account" title="Fill from account code">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>`;
        $('#journalLines').append(newLine);
        lineCounter++;
    });
    
    // Remove journal line
    $(document).on('click', '.remove-line', function() {
        if ($('.journal-line').length > 2) {
            $(this).closest('.journal-line').remove();
            calculateTotals();
        }
    });
    
    // Fill account name from account code
    $(document).on('click', '.fill-account', function() {
        const line = $(this).closest('.journal-line');
        const accountCode = line.find('.account-code').val();
        
        if (accountCode) {
            // This would typically be an AJAX call to get account name from database
            // For now, we'll use a simple mapping
            const accountMap = {
                '1102': 'Bank Account',
                '1201': 'Loan Portfolio - Performing',
                '1203': 'Interest Receivable',
                '1204': 'Monitoring Fees Receivable',
                '1206': 'VAT Receivable',
                '1209': 'Suspended Interest Receivable',
                '1210': 'Suspended Monitoring Fee Receivable',
                '1212': 'Suspended VAT Receivable',
                '4203': 'Penalty Charges'
            };
            
            if (accountMap[accountCode]) {
                line.find('.account-name').val(accountMap[accountCode]);
            }
        }
    });
    
    // Add loan payment lines (from your example)
    $('#addLoanPaymentLines').click(function() {
        // Clear existing lines except first 2
        $('.journal-line:gt(1)').remove();
        
        // Add standard loan payment lines (Bank/Cash debit, Loan/Interest/Fees/VAT credits)
        const loanPaymentLines = [
            ['1102', 'Bank Account', '', '319,221', 'Bank debit line'],
            ['1201', 'Loan Portfolio - Performing', '233,537', '', 'Principal'],
            ['1203', 'Interest Receivable', '79,425', '', 'Interest'],
            ['1204', 'Monitoring Fees Receivable', '5,304', '', 'Monitoring Fees'],
            ['1206', 'VAT Receivable', '955', '', 'VAT']
        ];
        
        loanPaymentLines.forEach((line, index) => {
            if (index >= 2) { // Add new lines for lines 3-5
                const newLine = `
                <div class="row mb-3 journal-line">
                    <div class="col-md-2">
                        <input type="text" class="form-control account-code" name="account_code[]" value="${line[0]}" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control account-name" name="account_name[]" value="${line[1]}" required>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text">FRW</span>
                            <input type="number" class="form-control credit-amount" name="credit_amount[]" value="${line[2].replace(',', '') || 0}" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text">FRW</span>
                            <input type="number" class="form-control debit-amount" name="debit_amount[]" value="${line[3].replace(',', '') || 0}" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <input type="text" class="form-control line-narration" name="line_narration[]" value="${line[4]}">
                        <button type="button" class="btn btn-danger btn-sm mt-1 remove-line">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm fill-account" title="Fill from account code">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>`;
                $('#journalLines').append(newLine);
            } else { // Update first 2 lines
                const existingLine = $('.journal-line').eq(index);
                existingLine.find('.account-code').val(line[0]);
                existingLine.find('.account-name').val(line[1]);
                if (line[2]) {
                    existingLine.find('.credit-amount').val(line[2].replace(',', ''));
                    existingLine.find('.debit-amount').val('0.00');
                }
                if (line[3]) {
                    existingLine.find('.debit-amount').val(line[3].replace(',', ''));
                    existingLine.find('.credit-amount').val('0.00');
                }
                existingLine.find('.line-narration').val(line[4]);
            }
        });
        
        calculateTotals();
    });
    
    // Calculate totals when amounts change
    $(document).on('input', '.debit-amount, .credit-amount', calculateTotals);
    
    // Ensure only debit or credit is entered
    $(document).on('input', '.debit-amount', function() {
        if ($(this).val() > 0) {
            $(this).closest('.journal-line').find('.credit-amount').val('0.00');
        }
    });
    
    $(document).on('input', '.credit-amount', function() {
        if ($(this).val() > 0) {
            $(this).closest('.journal-line').find('.debit-amount').val('0.00');
        }
    });
    
    function calculateTotals() {
        let totalDebit = 0;
        let totalCredit = 0;
        
        $('.debit-amount').each(function() {
            totalDebit += parseFloat($(this).val()) || 0;
        });
        
        $('.credit-amount').each(function() {
            totalCredit += parseFloat($(this).val()) || 0;
        });
        
        $('#totalDebit').text(totalDebit.toFixed(2));
        $('#totalCredit').text(totalCredit.toFixed(2));
        
        const balance = totalDebit - totalCredit;
        $('#balance').text(Math.abs(balance).toFixed(2));
        
        if (Math.abs(balance) < 0.01) {
            $('#balanceStatus').html('<span class="badge bg-success">Balanced</span>');
            $('#submitEntry').prop('disabled', false);
        } else {
            const status = balance > 0 ? 'Debit > Credit' : 'Credit > Debit';
            $('#balanceStatus').html('<span class="badge bg-danger">' + status + '</span>');
            $('#submitEntry').prop('disabled', true);
        }
    }
    
    // Template application
    function applyTemplate() {
        const template = $('#template_select').val();
        const loanNumber = $('#loan_number_select').val();
        
        switch(template) {
            case 'loan_payment':
                if (loanNumber) {
                    $('textarea[name="narration"]').val('Loan Payment for ' + loanNumber);
                    $('#addLoanPaymentLines').click();
                }
                break;
            case 'bad_debt':
                if (loanNumber) {
                    $('textarea[name="narration"]').val('Bad Debt Payment for ' + loanNumber);
                    // Clear and add bad debt lines
                    $('.journal-line:gt(1)').remove();
                    // Add bad debt payment lines
                    // ... similar to loan payment but with different accounts
                }
                break;
            case 'loan_disbursement':
                if (loanNumber) {
                    $('textarea[name="narration"]').val('Loan Disbursement for ' + loanNumber);
                    // Clear and add disbursement lines
                    $('.journal-line:gt(1)').remove();
                    // Add disbursement lines (opposite of payment)
                }
                break;
        }
    }
    
    // Auto-fill account name when account code is entered
    $(document).on('blur', '.account-code', function() {
        const accountCode = $(this).val();
        const line = $(this).closest('.journal-line');
        
        if (accountCode) {
            // This should be an AJAX call in production
            // For now, using a simple mapping
            const accountMap = {
                '1102': 'Bank Account',
                '1201': 'Loan Portfolio - Performing',
                '1203': 'Interest Receivable',
                '1204': 'Monitoring Fees Receivable',
                '1206': 'VAT Receivable',
                '1209': 'Suspended Interest Receivable',
                '1210': 'Suspended Monitoring Fee Receivable',
                '1212': 'Suspended VAT Receivable',
                '4203': 'Penalty Charges'
            };
            
            if (accountMap[accountCode]) {
                line.find('.account-name').val(accountMap[accountCode]);
            }
        }
    });
    
    // Initial calculation
    calculateTotals();
});
</script>
