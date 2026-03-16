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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Loan ID is required";
    echo "<script>window.location.href='?page=loans'</script>";
    exit();
}

$loan_id = intval($_GET['id']);

// ── Check tables exist ───────────────────────────────────────────────────────
$check_loan_table = $conn->query("SHOW TABLES LIKE 'loan_portfolio'");
if (!$check_loan_table || $check_loan_table->num_rows === 0) {
    die("Error: 'loan_portfolio' table does not exist.");
}
$check_customer_table = $conn->query("SHOW TABLES LIKE 'customers'");
if (!$check_customer_table || $check_customer_table->num_rows === 0) {
    die("Error: 'customers' table does not exist.");
}

$check_instalments_table = $conn->query("SHOW TABLES LIKE 'loan_instalments'");
$check_payments_table    = $conn->query("SHOW TABLES LIKE 'loan_payments'");
$check_accounting_table  = $conn->query("SHOW TABLES LIKE 'accounting_entries'");

// ── Dynamic customer columns ─────────────────────────────────────────────────
$check_columns = $conn->query("SHOW COLUMNS FROM customers");
$columns = array();
while ($row = $check_columns->fetch_assoc()) {
    $columns[] = $row['Field'];
}

$select_fields = "lp.*, c.customer_name, c.customer_code";
if (in_array('phone_number', $columns))      $select_fields .= ", c.phone_number";
elseif (in_array('phone', $columns))         $select_fields .= ", c.phone as phone_number";
elseif (in_array('contact_number', $columns))$select_fields .= ", c.contact_number as phone_number";
elseif (in_array('mobile_number', $columns)) $select_fields .= ", c.mobile_number as phone_number";

if (in_array('email', $columns))             $select_fields .= ", c.email";
elseif (in_array('email_address', $columns)) $select_fields .= ", c.email_address as email";

if (in_array('address', $columns))            $select_fields .= ", c.address";
elseif (in_array('physical_address',$columns))$select_fields .= ", c.physical_address as address";
elseif (in_array('location', $columns))       $select_fields .= ", c.location as address";

// ── Fetch main loan ──────────────────────────────────────────────────────────
$loan_stmt = $conn->prepare(
    "SELECT $select_fields FROM loan_portfolio lp
     LEFT JOIN customers c ON lp.customer_id = c.customer_id
     WHERE lp.loan_id = ?"
);
if ($loan_stmt === false) {
    die("Prepare error: " . htmlspecialchars($conn->error));
}
$loan_stmt->bind_param("i", $loan_id);
$loan_stmt->execute();
$loan_result = $loan_stmt->get_result();
if ($loan_result->num_rows === 0) {
    $_SESSION['error_message'] = "Loan not found";
    header("Location: index.php?page=loans");
    exit();
}
$loan = $loan_result->fetch_assoc();
$loan_stmt->close();

// ── Fetch instalments (current loan) ────────────────────────────────────────
$instalments = array();
if ($check_instalments_table && $check_instalments_table->num_rows > 0) {
    $s = $conn->prepare(
        "SELECT instalment_id, loan_id, instalment_number, due_date,
                principal_amount, interest_amount, management_fee as fees_amount, total_payment as total_amount,
                paid_amount as amount_paid, balance_remaining as balance_due, balance_remaining,
                penalty_amount, penalty_paid,
                status as payment_status, payment_date as paid_date,
                days_overdue, created_at, updated_at
         FROM loan_instalments WHERE loan_id = ? ORDER BY instalment_number ASC"
    );
    if ($s) {
        $s->bind_param("i", $loan_id);
        $s->execute();
        $instalments = $s->get_result()->fetch_all(MYSQLI_ASSOC);
        $s->close();
    }
}

// ── Fetch accounting entries ─────────────────────────────────────────────────
$accounting_entries = array();
if ($check_accounting_table && $check_accounting_table->num_rows > 0) {
    $chk = $conn->query("SHOW COLUMNS FROM accounting_entries LIKE 'loan_number'");
    if ($chk && $chk->num_rows > 0 && !empty($loan['loan_number'])) {
        $s = $conn->prepare("SELECT * FROM accounting_entries WHERE loan_number = ? ORDER BY created_at DESC");
        if ($s) {
            $s->bind_param("s", $loan['loan_number']);
            $s->execute();
            $accounting_entries = $s->get_result()->fetch_all(MYSQLI_ASSOC);
            $s->close();
        }
    }
}

// ── Fetch payments (current loan) ───────────────────────────────────────────
$payments = array();
if ($check_payments_table && $check_payments_table->num_rows > 0) {
    $s = $conn->prepare(
        "SELECT payment_id, loan_id, payment_amount, payment_date,
                payment_method, reference_number,
                interest_amount, principal_amount, monitoring_fee, penalties,
                created_at, payment_evidence
         FROM loan_payments WHERE loan_id = ? ORDER BY payment_date ASC"
    );
    if ($s) {
        $s->bind_param("i", $loan_id);
        $s->execute();
        $payments = $s->get_result()->fetch_all(MYSQLI_ASSOC);
        $s->close();
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS — PHP 7 compatible
// ═══════════════════════════════════════════════════════════════════════════════

function fmtDate($d) {
    return (!empty($d) && $d !== '0000-00-00') ? date('d M Y', strtotime($d)) : '—';
}

function scoreColor($v) {
    $v = floatval($v);
    if ($v >= 75) return 'success';
    if ($v >= 50) return 'warning';
    return 'danger';
}

function gradeColor($g) {
    if ($g === 'A+' || $g === 'A') return 'success';
    if ($g === 'B') return 'info';
    if ($g === 'C') return 'warning';
    return 'danger';
}

function loanStatusColor($s) {
    $s = strtolower(trim($s));
    if ($s === 'active')   return 'success';
    if ($s === 'paid' || $s === 'closed' || $s === 'completed') return 'primary';
    if ($s === 'defaulted') return 'danger';
    if ($s === 'pending')  return 'warning';
    return 'secondary';
}

/**
 * Classify one instalment — PHP 7 compatible, no match()
 * Returns: 'on_time' | 'late' | 'missed' | 'pending'
 */
function classifyInstalment($inst) {
    $status    = strtolower(trim(isset($inst['payment_status']) ? $inst['payment_status'] : ''));
    $paid      = floatval(isset($inst['amount_paid'])  ? $inst['amount_paid']  : 0);
    $due_total = floatval(isset($inst['total_amount']) ? $inst['total_amount'] : 0);
    $balance   = floatval(isset($inst['balance_due'])  ? $inst['balance_due']  : 0);
    $due_date  = isset($inst['due_date'])  ? $inst['due_date']  : '';
    $paid_date = isset($inst['paid_date']) ? $inst['paid_date'] : '';
    $overdue   = intval(isset($inst['days_overdue'])   ? $inst['days_overdue'] : 0);

    // Fully paid
    if ($status === 'paid' || ($balance <= 0.01 && $paid > 0 && $due_total > 0)) {
        if (!empty($paid_date) && !empty($due_date)) {
            return (strtotime($paid_date) <= strtotime($due_date)) ? 'on_time' : 'late';
        }
        return ($overdue <= 0) ? 'on_time' : 'late';
    }
    // Partial — paid something but not cleared
    if ($status === 'partial' || ($paid > 0 && $paid < $due_total)) {
        return 'late';
    }
    // Overdue / missed
    if ($status === 'overdue') return 'missed';
    if (!empty($due_date) && strtotime($due_date) < time() && $paid == 0) return 'missed';
    // Future pending
    return 'pending';
}

// ═══════════════════════════════════════════════════════════════════════════════
// PREVIOUS LOAN HISTORY  +  CREDIT SCORE
// ═══════════════════════════════════════════════════════════════════════════════
$previous_loans    = array();
$customer_id       = intval(isset($loan['customer_id']) ? $loan['customer_id'] : 0);
$current_disb_date = isset($loan['disbursement_date']) ? $loan['disbursement_date'] : null;

$cs = array(
    'total_previous_loans' => 0, 'fully_paid_loans' => 0, 'defaulted_loans' => 0,
    'active_loans' => 0, 'total_disbursed' => 0, 'total_expected' => 0,
    'total_repaid' => 0, 'on_time_payments' => 0, 'late_payments' => 0,
    'missed_payments' => 0, 'pending_payments' => 0,
    'credit_score' => 0, 'credit_grade' => 'N/A',
    'payment_rate' => 0, 'ontime_rate' => 0,
    'repayment_rate' => 0, 'completion_rate' => 0,
);

if ($customer_id > 0 && !empty($current_disb_date)) {

    $ps = $conn->prepare(
        "SELECT * FROM loan_portfolio
         WHERE customer_id = ? AND loan_id != ? AND disbursement_date < ?
         ORDER BY disbursement_date DESC"
    );
    if ($ps) {
        $ps->bind_param("iis", $customer_id, $loan_id, $current_disb_date);
        $ps->execute();
        $previous_loans = $ps->get_result()->fetch_all(MYSQLI_ASSOC);
        $ps->close();
    }

    foreach ($previous_loans as $pli => $pl_row) {
        $plid = intval($pl_row['loan_id']);
        $previous_loans[$pli]['instalments'] = array();
        $previous_loans[$pli]['payments']    = array();

        // Fetch instalments for this previous loan
        if ($check_instalments_table && $check_instalments_table->num_rows > 0) {
            $s = $conn->prepare(
                "SELECT instalment_number, due_date, principal_amount, interest_amount,
                        management_fee as fees_amount, total_payment as total_amount, paid_amount as amount_paid, 
                        balance_remaining as balance_due, balance_remaining,
                        penalty_amount, penalty_paid,
                        status as payment_status, payment_date as paid_date, days_overdue
                 FROM loan_instalments WHERE loan_id = ? ORDER BY instalment_number ASC"
            );
            if ($s) {
                $s->bind_param("i", $plid);
                $s->execute();
                $previous_loans[$pli]['instalments'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
                $s->close();
            }
        }

        // Fetch ALL payments for this previous loan
        if ($check_payments_table && $check_payments_table->num_rows > 0) {
            $s = $conn->prepare(
                "SELECT payment_id, payment_amount, payment_date, payment_method,
                        reference_number, created_at
                 FROM loan_payments WHERE loan_id = ? ORDER BY payment_date ASC"
            );
            if ($s) {
                $s->bind_param("i", $plid);
                $s->execute();
                $previous_loans[$pli]['payments'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
                $s->close();
            }
        }

        // Accurate total paid — all records in loan_payments are confirmed
        $paid_from_pmts = 0;
        foreach ($previous_loans[$pli]['payments'] as $p) {
            $paid_from_pmts += floatval($p['payment_amount']);
        }
        $paid_from_inst = 0;
        foreach ($previous_loans[$pli]['instalments'] as $i2) {
            $paid_from_inst += floatval(isset($i2['amount_paid']) ? $i2['amount_paid'] : 0);
        }
        $previous_loans[$pli]['total_paid'] = $paid_from_pmts > 0 ? $paid_from_pmts : $paid_from_inst;

        // Total expected from instalment rows
        $tot_exp = 0;
        foreach ($previous_loans[$pli]['instalments'] as $i2) {
            $tot_exp += floatval(isset($i2['total_amount']) ? $i2['total_amount'] : 0);
        }
        if ($tot_exp == 0) {
            $tot_exp = floatval(isset($pl_row['instalment_amount']) ? $pl_row['instalment_amount'] : 0)
                     * intval(isset($pl_row['number_of_instalments']) ? $pl_row['number_of_instalments'] : 0);
        }
        $previous_loans[$pli]['total_expected'] = $tot_exp;

        // Classify every instalment
        $on_time = $late = $missed = $pending_c = 0;
        foreach ($previous_loans[$pli]['instalments'] as $ii => $inst_row) {
            $cls = classifyInstalment($inst_row);
            $previous_loans[$pli]['instalments'][$ii]['_class'] = $cls;
            if ($cls === 'on_time') $on_time++;
            elseif ($cls === 'late') $late++;
            elseif ($cls === 'missed') $missed++;
            else $pending_c++;
        }
        $previous_loans[$pli]['on_time']  = $on_time;
        $previous_loans[$pli]['late']     = $late;
        $previous_loans[$pli]['missed']   = $missed;
        $previous_loans[$pli]['pending']  = $pending_c;

        $total_inst = count($previous_loans[$pli]['instalments']);
        $previous_loans[$pli]['payment_rate']  = ($total_inst > 0)
            ? round((($on_time + $late) / $total_inst) * 100, 1) : 0;
        $previous_loans[$pli]['ontime_rate']   = ($total_inst > 0)
            ? round(($on_time / $total_inst) * 100, 1) : 0;
        $previous_loans[$pli]['repayment_rate']= ($tot_exp > 0)
            ? round(min(100, ($previous_loans[$pli]['total_paid'] / $tot_exp) * 100), 1) : 0;

        // Accumulate credit score data
        $cs['total_previous_loans']++;
        $cs['total_disbursed']  += floatval(isset($pl_row['disbursement_amount']) ? $pl_row['disbursement_amount'] : 0);
        $cs['total_expected']   += $tot_exp;
        $cs['total_repaid']     += $previous_loans[$pli]['total_paid'];
        $cs['on_time_payments'] += $on_time;
        $cs['late_payments']    += $late;
        $cs['missed_payments']  += $missed;
        $cs['pending_payments'] += $pending_c;

        $sl = strtolower(isset($pl_row['loan_status']) ? $pl_row['loan_status'] : '');
        if ($sl === 'paid' || $sl === 'closed' || $sl === 'completed') {
            $cs['fully_paid_loans']++;
        } elseif ($sl === 'defaulted') {
            $cs['defaulted_loans']++;
        } else {
            $cs['active_loans']++;
        }
    }

    // ── Credit score calculation (PHP 7 compatible) ──────────────────────────
    if ($cs['total_previous_loans'] > 0) {
        $closed = $cs['on_time_payments'] + $cs['late_payments'] + $cs['missed_payments'];

        $ontime_rate    = ($closed > 0) ? ($cs['on_time_payments'] / $closed) * 100 : 0;
        $repayment_rate = ($cs['total_expected'] > 0)
                        ? min(100, ($cs['total_repaid'] / $cs['total_expected']) * 100) : 0;
        $completion_rate= ($cs['fully_paid_loans'] / $cs['total_previous_loans']) * 100;
        $attempt_rate   = ($closed > 0)
                        ? (($cs['on_time_payments'] + $cs['late_payments']) / $closed) * 100 : 0;

        $default_penalty = $cs['defaulted_loans'] * 15;

        $score = ($ontime_rate    * 0.40)
               + ($repayment_rate * 0.30)
               + ($completion_rate* 0.20)
               + ($attempt_rate   * 0.10)
               - $default_penalty;

        $score = max(0, min(100, round($score, 1)));

        $cs['credit_score']    = $score;
        $cs['payment_rate']    = round($attempt_rate, 1);
        $cs['ontime_rate']     = round($ontime_rate, 1);
        $cs['repayment_rate']  = round($repayment_rate, 1);
        $cs['completion_rate'] = round($completion_rate, 1);

        if ($score >= 85)     $cs['credit_grade'] = 'A+';
        elseif ($score >= 75) $cs['credit_grade'] = 'A';
        elseif ($score >= 65) $cs['credit_grade'] = 'B';
        elseif ($score >= 50) $cs['credit_grade'] = 'C';
        elseif ($score >= 35) $cs['credit_grade'] = 'D';
        else                  $cs['credit_grade'] = 'F';
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// CURRENT LOAN STATISTICS
// ═══════════════════════════════════════════════════════════════════════════════
$disbursement_amount   = floatval(isset($loan['total_disbursed']) ? $loan['total_disbursed'] : (isset($loan['loan_amount']) ? $loan['loan_amount'] : 0));
$total_outstanding     = floatval(isset($loan['total_outstanding'])     ? $loan['total_outstanding']     : 0);
$number_of_instalments = intval(isset($loan['number_of_instalments'])   ? $loan['number_of_instalments'] : 0);
$instalment_amount     = floatval(isset($loan['instalment_amount'])     ? $loan['instalment_amount']     : (isset($loan['monthly_payment']) ? $loan['monthly_payment'] : 0));

// loan_payments has no payment_status column — every record is a confirmed payment
$total_paid_from_pmts = 0;
foreach ($payments as $p) {
    $total_paid_from_pmts += floatval($p['payment_amount'] ?? 0);
}
$total_paid_from_inst = 0;
foreach ($instalments as $i2) {
    $total_paid_from_inst += floatval(isset($i2['amount_paid']) ? $i2['amount_paid'] : 0);
}
// Use payment records if they exist (more accurate), otherwise fall back to instalment paid_amount sum
$total_paid = ($total_paid_from_pmts > 0) ? $total_paid_from_pmts : $total_paid_from_inst;
// Ensure we never show a negative paid amount
$total_paid = max(0, $total_paid);

$paid_inst = $pending_inst = $overdue_inst = $partial_inst = 0;
foreach ($instalments as $i2) {
    $st  = isset($i2['payment_status']) ? $i2['payment_status'] : '';
    $bal = floatval(isset($i2['balance_due'])   ? $i2['balance_due']   : 0);
    $amt = floatval(isset($i2['amount_paid'])   ? $i2['amount_paid']   : 0);
    $tot = floatval(isset($i2['total_amount'])  ? $i2['total_amount']  : 0);
    if (empty($st)) {
        if ($bal <= 0 && $amt > 0)        $st = 'Paid';
        elseif ($amt > 0 && $amt < $tot)  $st = 'Partial';
        elseif (!empty($i2['due_date']) && strtotime($i2['due_date']) < time()) $st = 'Overdue';
        else $st = 'Pending';
    }
    if ($st === 'Paid')    $paid_inst++;
    elseif ($st === 'Pending')  $pending_inst++;
    elseif ($st === 'Overdue')  $overdue_inst++;
    elseif ($st === 'Partial')  $partial_inst++;
}

$total_exp_inst = 0;
foreach ($instalments as $i2) {
    $total_exp_inst += floatval(isset($i2['total_amount']) ? $i2['total_amount'] : 0);
}
if ($total_exp_inst == 0 && $instalment_amount > 0 && $number_of_instalments > 0) {
    $total_exp_inst = $instalment_amount * $number_of_instalments;
}

$application_fees      = floatval(isset($loan['application_fees'])      ? $loan['application_fees']      : 0);
$disbursement_fees     = floatval(isset($loan['disbursement_fees'])     ? $loan['disbursement_fees']     : 0);
$disbursement_fees_vat = floatval(isset($loan['disbursement_fees_vat']) ? $loan['disbursement_fees_vat'] : 0);
$monitoring_fees       = floatval(isset($loan['monitoring_fees'])       ? $loan['monitoring_fees']       : 0);
$monitoring_fees_vat   = floatval(isset($loan['monitoring_fees_vat'])   ? $loan['monitoring_fees_vat']   : 0);
$penalties             = floatval(isset($loan['penalties'])             ? $loan['penalties']             : 0);
$total_fees = $application_fees + $disbursement_fees + $disbursement_fees_vat
            + $monitoring_fees  + $monitoring_fees_vat + $penalties;

$loan_status  = isset($loan['loan_status']) ? $loan['loan_status'] : 'Unknown';
$status_color = loanStatusColor($loan_status);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Loan Details — <?php echo htmlspecialchars(isset($loan['loan_number']) ? $loan['loan_number'] : 'N/A'); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { font-size: 12px !important; background: #f4f6fb; }
.card { margin-bottom:.75rem!important; border:none; box-shadow:0 1px 8px rgba(0,0,0,.09); border-radius:8px!important; }
.card-header { padding:.55rem 1rem!important; border-radius:8px 8px 0 0!important; }
.card-body { padding:.75rem!important; }
.card-header h5 { font-size:.92rem!important; margin-bottom:0!important; }
.table { font-size:.8rem!important; }
.table th,.table td { padding:.3rem .5rem!important; }
.badge { font-size:.68rem!important; padding:.18rem .38rem!important; }
.btn { font-size:.8rem!important; padding:.25rem .55rem!important; }
.btn-sm { font-size:.72rem!important; padding:.18rem .4rem!important; }
.text-muted { font-size:.75rem!important; }
.container-fluid { padding:.75rem!important; }
@media print { .no-print { display:none!important; } .card { page-break-inside:avoid; } }

.info-label { font-weight:600; color:#6c757d; }

/* Previous loan table */
.prev-loan-row { cursor:pointer; transition:background .15s; }
.prev-loan-row:hover > td { background:#e8f0fe!important; }
.prev-loan-row > td { vertical-align:middle; }
.rate-wrap { display:flex; align-items:center; gap:5px; min-width:90px; }
.rate-wrap .progress { flex:1; height:7px; border-radius:4px; }

/* Credit score */
.cs-circle {
    width:120px; height:120px; border-radius:50%;
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    color:#fff; margin:0 auto; box-shadow:0 4px 18px rgba(0,0,0,.22);
}
.cs-circle .csn { font-size:2rem; font-weight:900; line-height:1; }
.cs-circle .csl { font-size:.58rem; opacity:.82; }
.grade-badge {
    width:60px; height:60px; border-radius:50%;
    display:inline-flex; align-items:center; justify-content:center;
    font-size:1.5rem; font-weight:900; color:#fff; box-shadow:0 2px 8px rgba(0,0,0,.2);
}
.metric-box { text-align:center; padding:.45rem .3rem; border-radius:8px; background:#f8f9fa; }
.metric-box .mv { font-size:1.1rem; font-weight:800; }
.metric-box .ml { font-size:.62rem; color:#6c757d; }
.formula-line { font-size:.7rem; padding:.2rem 0; border-bottom:1px dashed #dee2e6; }
.formula-line:last-child { border:none; }
.mini-prog { height:9px; border-radius:5px; }

/* Modal */
.modal-header-grad { background:linear-gradient(135deg,#1565c0,#42a5f5); color:#fff; }
.modal-dialog { max-width:94%!important; }
.tab-stat { text-align:center; padding:.5rem .4rem; border-radius:8px; background:#f8f9fa; }
.tab-stat .tsv { font-size:1.1rem; font-weight:800; }
.tab-stat .tsl { font-size:.62rem; color:#6c757d; }

/* Instalment row colours */
.inst-ontime  > td { background:#e8f5e9!important; }
.inst-late    > td { background:#fff8e1!important; }
.inst-missed  > td { background:#ffebee!important; }
.inst-pending > td { background:#f3f4f6!important; }

/* Payment row colours */
.pmt-completed > td { background:#f0fff4!important; }
.pmt-pending   > td { background:#fffde7!important; }
.pmt-failed    > td { background:#fce4ec!important; }
</style>
</head>
<body>
<div class="container-fluid py-3">

<!-- Header -->
<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="h4 fw-bold text-primary mb-0">Loan Details</h2>
            <p class="text-muted mb-0">Loan #<?php echo htmlspecialchars(isset($loan['loan_number']) ? $loan['loan_number'] : 'N/A'); ?></p>
        </div>
        <div class="no-print">
            <a href="index.php?page=loans" class="btn btn-secondary me-1"><i class="fas fa-arrow-left me-1"></i>Back</a>
            <a href="index.php?page=editloan&id=<?php echo $loan_id; ?>" class="btn btn-warning"><i class="fas fa-edit me-1"></i>Edit</a>
        </div>
    </div>
</div>

<!-- Status -->
<div class="card border-<?php echo $status_color; ?> mb-3">
    <div class="card-body py-2 d-flex justify-content-between align-items-center">
        <div>
            <p class="text-muted mb-0 small">Loan Status</p>
            <span class="badge bg-<?php echo $status_color; ?> mt-1" style="font-size:.82rem;padding:.3rem .7rem;">
                <?php echo htmlspecialchars($loan_status); ?>
            </span>
        </div>
        <i class="fas fa-info-circle fa-2x text-<?php echo $status_color; ?>"></i>
    </div>
</div>

<!-- Customer + Loan info -->
<div class="row">
<div class="col-lg-6">
    <div class="card mb-3">
        <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-user me-2"></i>Customer Information</h5></div>
        <div class="card-body">
            <table class="table table-borderless mb-0">
                <tr><td class="info-label" width="42%">Name:</td><td class="fw-bold"><?php echo htmlspecialchars(isset($loan['customer_name']) ? $loan['customer_name'] : 'N/A'); ?></td></tr>
                <tr><td class="info-label">Code:</td><td class="fw-bold"><?php echo htmlspecialchars(isset($loan['customer_code']) ? $loan['customer_code'] : 'N/A'); ?></td></tr>
                <?php if (!empty($loan['phone_number'])): ?><tr><td class="info-label">Phone:</td><td><a href="tel:<?php echo htmlspecialchars($loan['phone_number']); ?>"><?php echo htmlspecialchars($loan['phone_number']); ?></a></td></tr><?php endif; ?>
                <?php if (!empty($loan['email'])): ?><tr><td class="info-label">Email:</td><td><a href="mailto:<?php echo htmlspecialchars($loan['email']); ?>"><?php echo htmlspecialchars($loan['email']); ?></a></td></tr><?php endif; ?>
                <?php if (!empty($loan['address'])): ?><tr><td class="info-label">Address:</td><td><?php echo htmlspecialchars($loan['address']); ?></td></tr><?php endif; ?>
            </table>
        </div>
    </div>
</div>
<div class="col-lg-6">
    <div class="card mb-3">
        <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Loan Information</h5></div>
        <div class="card-body">
            <?php
            // Calculate penalty-inclusive stats from installments
            $sum_penalties_due  = 0;
            $sum_penalties_paid = 0;
            $sum_schedule_bal   = 0;
            if (isset($instalments) && is_array($instalments)) {
                foreach ($instalments as $inst_row) {
                    $sum_penalties_due  += floatval($inst_row['penalty_amount'] ?? 0);
                    $sum_penalties_paid += floatval($inst_row['penalty_paid'] ?? 0);
                    $sum_schedule_bal   += floatval($inst_row['balance_due'] ?? $inst_row['balance_remaining'] ?? 0);
                }
            }
            $remaining_penalties = max(0, $sum_penalties_due - $sum_penalties_paid);
            $final_outstanding = $sum_schedule_bal + $remaining_penalties;
            ?>
            <table class="table table-borderless mb-0">
                <tr><td class="info-label" width="45%">Loan Number:</td><td class="fw-bold"><?php echo htmlspecialchars(isset($loan['loan_number']) ? $loan['loan_number'] : 'N/A'); ?></td></tr>
                <tr><td class="info-label">Disbursement Amount:</td><td class="fw-bold text-primary">FRW <?php echo number_format($disbursement_amount > 0 ? $disbursement_amount : array_sum(array_column($instalments, 'principal_amount')), 2); ?></td></tr>
                <tr class="border-top"><td class="info-label">Total Expected (Schedule):</td><td class="fw-bold">FRW <?php echo number_format($total_exp_inst > 0 ? $total_exp_inst : array_sum(array_column($instalments, 'total_amount')), 2); ?></td></tr>
                <tr><td class="info-label">Already Paid:</td><td class="fw-bold text-success">FRW <?php echo number_format($total_paid, 2); ?></td></tr>
                <tr><td class="info-label">Penalties Paid:</td><td class="text-danger">FRW <?php echo number_format($sum_penalties_paid,2); ?></td></tr>
                <tr class="bg-light shadow-sm"><td class="info-label fw-bold">Outstanding Balance:</td><td class="fw-bold text-danger" style="font-size:1.15rem;">FRW <?php echo number_format($final_outstanding,2); ?></td></tr>
                <?php if ($remaining_penalties > 0): ?>
                <tr><td class="info-label small text-muted">Includes FRW <?php echo number_format($remaining_penalties,2); ?> remaining penalties</td><td></td></tr>
                <?php endif; ?>
                <?php if (!empty($loan['disbursement_date'])): ?><tr><td class="info-label">Disbursement Date:</td><td><?php echo fmtDate($loan['disbursement_date']); ?></td></tr><?php endif; ?>
            </table>
        </div>
    </div>
</div>
</div>

<!-- Fees -->
<?php if ($total_fees > 0): ?>
<div class="card mb-3">
    <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Fees &amp; Charges</h5></div>
    <div class="card-body">
        <table class="table table-borderless mb-0">
            <?php if ($application_fees > 0): ?><tr><td class="info-label" width="35%">Application Fees:</td><td>FRW <?php echo number_format($application_fees,2); ?></td></tr><?php endif; ?>
            <?php if ($disbursement_fees > 0): ?><tr><td class="info-label">Disbursement Fees:</td><td>FRW <?php echo number_format($disbursement_fees,2); ?></td></tr><?php endif; ?>
            <?php if ($monitoring_fees > 0): ?><tr><td class="info-label">Monitoring Fees:</td><td>FRW <?php echo number_format($monitoring_fees,2); ?></td></tr><?php endif; ?>
            <?php if ($penalties > 0): ?><tr><td class="info-label">Penalties:</td><td class="text-danger fw-bold">FRW <?php echo number_format($penalties,2); ?></td></tr><?php endif; ?>
            <tr class="border-top"><td class="info-label fw-bold">Total Fees:</td><td class="fw-bold">FRW <?php echo number_format($total_fees,2); ?></td></tr>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Current loan payment history with delete buttons -->
<?php if (!empty($payments)): ?>
<div class="card mb-3">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Payment History</h5>
        <span class="badge bg-light text-dark"><?php echo count($payments); ?> payment(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" style="font-size:.8rem;">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Principal</th>
                        <th class="text-end">Interest</th>
                        <th class="text-end">Fee</th>
                        <th class="text-end">Penalty</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th class="text-center">Evidence</th>
                        <th class="text-center no-print">Delete</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $pi => $p): ?>
                <tr>
                    <td class="text-muted"><?php echo $pi + 1; ?></td>
                    <td><strong><?php echo fmtDate($p['payment_date'] ?? ''); ?></strong></td>
                    <td class="text-end fw-bold text-success"><?php echo number_format($p['payment_amount'] ?? 0, 0); ?></td>
                    <td class="text-end text-muted"><?php echo number_format($p['principal_amount'] ?? 0, 0); ?></td>
                    <td class="text-end text-muted"><?php echo number_format($p['interest_amount'] ?? 0, 0); ?></td>
                    <td class="text-end text-muted"><?php echo number_format($p['monitoring_fee'] ?? 0, 0); ?></td>
                    <td class="text-end text-danger"><?php echo number_format($p['penalties'] ?? 0, 0); ?></td>
                    <td><?php echo !empty($p['payment_method']) ? '<span class="badge bg-info text-dark">'.htmlspecialchars($p['payment_method']).'</span>' : '—'; ?></td>
                    <td><?php echo !empty($p['reference_number']) ? '<code>'.htmlspecialchars($p['reference_number']).'</code>' : '—'; ?></td>
                    <td class="text-center">
                        <?php if (!empty($p['payment_evidence'])): ?>
                            <a href="uploads/payments/<?php echo $p['payment_evidence']; ?>" 
                               target="_blank" class="btn btn-sm btn-outline-primary" style="font-size: 0.75rem; padding: 2px 6px;">
                                <i class="fas fa-eye me-1"></i>View Slip
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">No photo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center no-print">
                        <?php $del_amt = number_format($p['payment_amount'] ?? 0, 0); $del_pid = intval($p['payment_id'] ?? 0); ?>
                        <button class="btn btn-outline-danger btn-sm px-2 py-0"
                                title="Delete and reverse this payment"
                                onclick="if(confirm('Delete FRW <?php echo $del_amt; ?>? This REVERSES the balance. Cannot be undone.')) window.location.href='?page=delete_payment&payment_id=<?php echo $del_pid; ?>&loan_id=<?php echo $loan_id; ?>';">
                            <i class="fas fa-trash-alt"></i> Del
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td colspan="2">TOTAL</td>
                        <td class="text-end text-success"><?php echo number_format(array_sum(array_column($payments, 'payment_amount')), 0); ?></td>
                        <td class="text-end"><?php echo number_format(array_sum(array_column($payments, 'principal_amount')), 0); ?></td>
                        <td class="text-end"><?php echo number_format(array_sum(array_column($payments, 'interest_amount')), 0); ?></td>
                        <td class="text-end"><?php echo number_format(array_sum(array_column($payments, 'monitoring_fee')), 0); ?></td>
                        <td class="text-end text-danger"><?php echo number_format(array_sum(array_column($payments, 'penalties')), 0); ?></td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Accounting entries -->
<?php if (!empty($accounting_entries)): ?>
<div class="card mb-3">
    <div class="card-header bg-secondary text-white d-flex justify-content-between">
        <h5 class="mb-0"><i class="fas fa-book-open me-2"></i>Accounting Entries</h5>
        <span class="badge bg-light text-dark"><?php echo count($accounting_entries); ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-dark"><tr><th>Date</th><th>Account Code</th><th>Type</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                <tbody>
                <?php foreach ($accounting_entries as $e): ?>
                <tr>
                    <td><?php echo fmtDate(isset($e['created_at']) ? $e['created_at'] : ''); ?></td>
                    <td><code><?php echo htmlspecialchars(isset($e['account_code']) ? $e['account_code'] : ''); ?></code></td>
                    <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars(isset($e['transaction_type']) ? $e['transaction_type'] : ''); ?></span></td>
                    <td class="text-end"><?php echo (isset($e['debit']) && $e['debit'] > 0) ? '<span class="text-danger fw-bold">'.number_format($e['debit'],2).'</span>' : '<span class="text-muted">—</span>'; ?></td>
                    <td class="text-end"><?php echo (isset($e['credit']) && $e['credit'] > 0) ? '<span class="text-success fw-bold">'.number_format($e['credit'],2).'</span>' : '<span class="text-muted">—</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Quick actions -->
<div class="card no-print bg-light border-0 mb-4" style="box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
        <div>
            <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
            <p class="text-muted small mb-0">Manage and process this loan immediately</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="index.php?page=recordpayment&loan_id=<?php echo $loan_id; ?>" class="btn btn-success px-3 py-2 fw-bold shadow-sm">
                <i class="fas fa-money-bill-wave me-2"></i>Record Payment
            </a>
            <a href="index.php?page=editloan&id=<?php echo $loan_id; ?>" class="btn btn-warning px-3 py-2 fw-bold shadow-sm">
                <i class="fas fa-edit me-2"></i>Edit Loan Details
            </a>
            <button onclick="window.print()" class="btn btn-primary px-3 py-2 fw-bold shadow-sm">
                <i class="fas fa-print me-2"></i>Print Statement
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     PREVIOUS LOAN HISTORY — click row opens modal
══════════════════════════════════════════════════════════════════ -->
<div class="card mb-3 border-0" style="box-shadow:0 2px 12px rgba(0,0,0,.13);">
    <div class="card-header text-white d-flex justify-content-between align-items-center"
         style="background:linear-gradient(135deg,#263238,#455a64);">
        <h5 class="mb-0">
            <i class="fas fa-history me-2"></i>Previous Loan History
            <small class="ms-2 opacity-75" style="font-size:.65rem;">
                — disbursed before <?php echo (!empty($current_disb_date) && $current_disb_date !== '0000-00-00') ? fmtDate($current_disb_date) : 'this loan'; ?>
            </small>
        </h5>
        <span class="badge bg-light text-dark"><?php echo count($previous_loans); ?> loan(s)</span>
    </div>
    <div class="card-body p-0">
    <?php if (empty($previous_loans)): ?>
        <div class="p-4 text-center text-muted">
            <i class="fas fa-folder-open fa-2x d-block mb-2"></i>
            No previous loans found for this customer before this loan's disbursement date.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Loan #</th>
                        <th>Disbursed</th>
                        <th>Maturity</th>
                        <th class="text-end">Amount (FRW)</th>
                        <th class="text-end">Total Paid</th>
                        <th class="text-center">Instal.</th>
                        <th class="text-center">On-Time</th>
                        <th class="text-center">Late</th>
                        <th class="text-center">Missed</th>
                        <th class="text-center">Pay Rate</th>
                        <th class="text-center">Status</th>
                        <th class="text-center no-print">Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($previous_loans as $idx => $pl):
                    $pl_sc = loanStatusColor(isset($pl['loan_status']) ? $pl['loan_status'] : '');
                    $pl_pr = floatval($pl['payment_rate']);
                    $rc    = ($pl_pr >= 80) ? 'success' : (($pl_pr >= 50) ? 'warning' : 'danger');
                ?>
                <tr class="prev-loan-row"
                    onclick="openModal(<?php echo intval($pl['loan_id']); ?>)"
                    title="Click to view full payment history">
                    <td class="text-muted fw-bold"><?php echo $idx+1; ?></td>
                    <td><span class="fw-bold text-primary"><?php echo htmlspecialchars(isset($pl['loan_number']) ? $pl['loan_number'] : 'N/A'); ?></span>
                        <i class="fas fa-search ms-1 text-muted" style="font-size:.55rem;"></i></td>
                    <td><?php echo fmtDate(isset($pl['disbursement_date']) ? $pl['disbursement_date'] : ''); ?></td>
                    <td><?php echo fmtDate(isset($pl['maturity_date']) ? $pl['maturity_date'] : ''); ?></td>
                    <td class="text-end fw-bold"><?php echo number_format(isset($pl['disbursement_amount']) ? $pl['disbursement_amount'] : 0,2); ?></td>
                    <td class="text-end fw-bold text-success"><?php echo number_format($pl['total_paid'],2); ?></td>
                    <td class="text-center"><?php echo count($pl['instalments']); ?></td>
                    <td class="text-center"><span class="badge bg-success"><?php echo $pl['on_time']; ?></span></td>
                    <td class="text-center"><span class="badge bg-warning text-dark"><?php echo $pl['late']; ?></span></td>
                    <td class="text-center"><span class="badge bg-danger"><?php echo $pl['missed']; ?></span></td>
                    <td>
                        <div class="rate-wrap">
                            <div class="progress">
                                <div class="progress-bar bg-<?php echo $rc; ?>" data-w="<?php echo $pl_pr; ?>" style="width:<?php echo $pl_pr; ?>%"></div>
                            </div>
                            <small class="fw-bold text-<?php echo $rc; ?>"><?php echo $pl_pr; ?>%</small>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-<?php echo $pl_sc; ?>"><?php echo htmlspecialchars(isset($pl['loan_status']) ? $pl['loan_status'] : ''); ?></span>
                    </td>
                    <td class="text-center no-print">
                        <button class="btn btn-sm btn-outline-primary"
                                onclick="openModal(<?php echo intval($pl['loan_id']); ?>); event.stopPropagation();">
                            <i class="fas fa-eye me-1"></i>View
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small p-2 mb-0">
            <i class="fas fa-info-circle me-1"></i>
            Click any row or <strong>View</strong> to open full payment history &amp; instalment schedule.
        </p>
    <?php endif; ?>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     CREDIT SCORE SECTION
══════════════════════════════════════════════════════════════════ -->
<div class="card mb-3 border-0" style="box-shadow:0 2px 12px rgba(0,0,0,.13);">
    <div class="card-header text-white d-flex justify-content-between align-items-center"
         style="background:linear-gradient(135deg,#1a237e,#3949ab);">
        <h5 class="mb-0"><i class="fas fa-star-half-alt me-2"></i>Customer Credit Score &amp; Payment Rate</h5>
        <span class="badge bg-light text-dark">Based on <?php echo $cs['total_previous_loans']; ?> previous loan(s)</span>
    </div>
    <div class="card-body">
    <?php if ($cs['total_previous_loans'] === 0): ?>
        <div class="text-center text-muted py-3">
            <i class="fas fa-chart-bar fa-2x d-block mb-2"></i>
            No previous loan data — credit score will appear once historical loans exist.
        </div>
    <?php else:
        $cs_c = scoreColor($cs['credit_score']);
        $gr_c = gradeColor($cs['credit_grade']);
    ?>
    <div class="row align-items-start gy-3">

        <!-- Score + Grade -->
        <div class="col-md-3 text-center">
            <div class="cs-circle bg-<?php echo $cs_c; ?>">
                <span class="csn"><?php echo $cs['credit_score']; ?></span>
                <span class="csl">out of 100</span>
            </div>
            <p class="text-muted mt-2 mb-2" style="font-size:.68rem;">Credit Score</p>
            <div class="grade-badge bg-<?php echo $gr_c; ?>"><?php echo $cs['credit_grade']; ?></div>
            <p class="text-muted mt-1 mb-0" style="font-size:.68rem;">Grade</p>
        </div>

        <!-- Metric boxes -->
        <div class="col-md-4">
            <div class="row g-2">
                <div class="col-6"><div class="metric-box"><div class="mv text-primary"><?php echo $cs['total_previous_loans']; ?></div><div class="ml">Total Prev. Loans</div></div></div>
                <div class="col-6"><div class="metric-box"><div class="mv text-success"><?php echo $cs['fully_paid_loans']; ?></div><div class="ml">Fully Paid</div></div></div>
                <div class="col-6"><div class="metric-box"><div class="mv text-danger"><?php echo $cs['defaulted_loans']; ?></div><div class="ml">Defaulted</div></div></div>
                <div class="col-6"><div class="metric-box"><div class="mv text-info"><?php echo $cs['active_loans']; ?></div><div class="ml">Active/Open</div></div></div>
                <div class="col-4"><div class="metric-box"><div class="mv text-success"><?php echo $cs['on_time_payments']; ?></div><div class="ml">On-Time</div></div></div>
                <div class="col-4"><div class="metric-box"><div class="mv text-warning"><?php echo $cs['late_payments']; ?></div><div class="ml">Late</div></div></div>
                <div class="col-4"><div class="metric-box"><div class="mv text-danger"><?php echo $cs['missed_payments']; ?></div><div class="ml">Missed</div></div></div>
            </div>
        </div>

        <!-- Progress bars + formula -->
        <div class="col-md-5">
            <?php
            $bars = array(
                array('On-Time Rate (A · 40%)',         $cs['ontime_rate']),
                array('Amount Repayment Rate (B · 30%)', $cs['repayment_rate']),
                array('Loan Completion Rate (C · 20%)',  $cs['completion_rate']),
                array('Payment Attempt Rate (D · 10%)',  $cs['payment_rate']),
            );
            foreach ($bars as $bar):
                $bl = $bar[0]; $bv = $bar[1]; $bc2 = scoreColor($bv);
            ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between mb-1">
                    <small class="fw-bold"><?php echo $bl; ?></small>
                    <small class="text-<?php echo $bc2; ?> fw-bold"><?php echo $bv; ?>%</small>
                </div>
                <div class="progress mini-prog">
                    <div class="progress-bar bg-<?php echo $bc2; ?>" data-w="<?php echo $bv; ?>" style="width:<?php echo $bv; ?>%"></div>
                </div>
                <?php if ($bl === 'Amount Repayment Rate (B · 30%)'): ?>
                <small class="text-muted">FRW <?php echo number_format($cs['total_repaid'],0); ?> / FRW <?php echo number_format($cs['total_expected'],0); ?></small>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <!-- Calculation breakdown -->
            <div class="border rounded p-2 bg-light mt-2">
                <p class="fw-bold mb-1" style="font-size:.72rem;"><i class="fas fa-calculator me-1"></i>Score Breakdown</p>
                <div class="formula-line d-flex justify-content-between">
                    <span>A: <?php echo $cs['ontime_rate']; ?>% × 40%</span>
                    <strong><?php echo round($cs['ontime_rate'] * 0.40, 2); ?> pts</strong>
                </div>
                <div class="formula-line d-flex justify-content-between">
                    <span>B: <?php echo $cs['repayment_rate']; ?>% × 30%</span>
                    <strong><?php echo round($cs['repayment_rate'] * 0.30, 2); ?> pts</strong>
                </div>
                <div class="formula-line d-flex justify-content-between">
                    <span>C: <?php echo $cs['completion_rate']; ?>% × 20%</span>
                    <strong><?php echo round($cs['completion_rate'] * 0.20, 2); ?> pts</strong>
                </div>
                <div class="formula-line d-flex justify-content-between">
                    <span>D: <?php echo $cs['payment_rate']; ?>% × 10%</span>
                    <strong><?php echo round($cs['payment_rate'] * 0.10, 2); ?> pts</strong>
                </div>
                <?php if ($cs['defaulted_loans'] > 0): ?>
                <div class="formula-line d-flex justify-content-between text-danger">
                    <span>Penalty: <?php echo $cs['defaulted_loans']; ?> default(s) × −15</span>
                    <strong>−<?php echo ($cs['defaulted_loans'] * 15); ?> pts</strong>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between pt-1 mt-1 border-top fw-bold">
                    <span>Final Score</span>
                    <span class="text-<?php echo $cs_c; ?>"><?php echo $cs['credit_score']; ?> / 100 &nbsp; (<?php echo $cs['credit_grade']; ?>)</span>
                </div>
            </div>
        </div>

    </div>

    <!-- Grade legend -->
    <div class="mt-3 pt-2 border-top d-flex flex-wrap gap-1 align-items-center">
        <small class="fw-bold me-1">Grade:</small>
        <?php
        $grade_legend = array(
            array('A+','85–100','Excellent','success'),
            array('A', '75–84', 'Very Good','success'),
            array('B', '65–74', 'Good',     'info'),
            array('C', '50–64', 'Fair',     'warning'),
            array('D', '35–49', 'Poor',     'danger'),
            array('F', '0–34',  'Very Poor','danger'),
        );
        foreach ($grade_legend as $gl): ?>
        <span class="badge bg-<?php echo $gl[3]; ?>" style="font-size:.62rem;"><?php echo $gl[0]; ?> (<?php echo $gl[1]; ?>) <?php echo $gl[2]; ?></span>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
    </div>
</div>


</div><!-- /container-fluid -->

<!-- ══════════════════════════════════════════════════════════════
     MODALS — one per previous loan
══════════════════════════════════════════════════════════════════ -->
<?php foreach ($previous_loans as $pl):
    $plid   = intval($pl['loan_id']);
    $pl_sc  = loanStatusColor(isset($pl['loan_status']) ? $pl['loan_status'] : '');
    $pl_pr  = floatval($pl['payment_rate']);
    $pl_rr  = floatval($pl['repayment_rate']);
    $pl_ot  = floatval($pl['ontime_rate']);
    $rc     = ($pl_pr >= 80) ? 'success' : (($pl_pr >= 50) ? 'warning' : 'danger');
    $rr_c   = scoreColor($pl_rr);
    $ot_c   = scoreColor($pl_ot);
    $outstanding = max(0, $pl['total_expected'] - $pl['total_paid']);

    $pmts_completed_total = 0;
    foreach ($pl['payments'] as $p) {
        if (strtolower(isset($p['payment_status']) ? $p['payment_status'] : '') === 'completed') {
            $pmts_completed_total += floatval($p['payment_amount']);
        }
    }
    $completed_count = 0;
    foreach ($pl['payments'] as $p) {
        if (strtolower(isset($p['payment_status']) ? $p['payment_status'] : '') === 'completed') $completed_count++;
    }
?>
<div class="modal fade" id="loanModal_<?php echo $plid; ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width:94%;">
   <div class="modal-content">

    <div class="modal-header modal-header-grad py-2">
        <div>
            <h5 class="modal-title mb-0">
                <i class="fas fa-history me-2"></i>
                Loan History: <strong><?php echo htmlspecialchars(isset($pl['loan_number']) ? $pl['loan_number'] : 'N/A'); ?></strong>
            </h5>
            <small class="opacity-75">
                <?php echo fmtDate(isset($pl['disbursement_date']) ? $pl['disbursement_date'] : ''); ?>
                &rarr; <?php echo fmtDate(isset($pl['maturity_date']) ? $pl['maturity_date'] : ''); ?>
                &nbsp;|&nbsp;
                <span class="badge bg-<?php echo $pl_sc; ?>"><?php echo htmlspecialchars(isset($pl['loan_status']) ? $pl['loan_status'] : ''); ?></span>
                &nbsp;|&nbsp;
                <?php echo number_format(isset($pl['interest_rate']) ? $pl['interest_rate'] : 0,2); ?>% interest
                &nbsp;|&nbsp;
                <?php echo intval(isset($pl['number_of_instalments']) ? $pl['number_of_instalments'] : 0); ?> months
                &nbsp;|&nbsp;
                FRW <?php echo number_format(isset($pl['instalment_amount']) ? $pl['instalment_amount'] : 0,2); ?>/mo
            </small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>

    <div class="modal-body p-3">

        <!-- KPI row -->
        <div class="row g-2 mb-3">
            <div class="col-6 col-md-2"><div class="tab-stat"><div class="tsv text-primary">FRW <?php echo number_format(isset($pl['disbursement_amount']) ? $pl['disbursement_amount'] : 0,0); ?></div><div class="tsl">Disbursed</div></div></div>
            <div class="col-6 col-md-2"><div class="tab-stat"><div class="tsv text-success">FRW <?php echo number_format($pl['total_paid'],0); ?></div><div class="tsl">Total Paid</div></div></div>
            <div class="col-6 col-md-2"><div class="tab-stat"><div class="tsv text-danger">FRW <?php echo number_format($outstanding,0); ?></div><div class="tsl">Outstanding</div></div></div>
            <div class="col-6 col-md-2"><div class="tab-stat"><div class="tsv text-<?php echo $rc; ?>"><?php echo $pl_pr; ?>%</div><div class="tsl">Payment Rate</div></div></div>
            <div class="col-6 col-md-2"><div class="tab-stat"><div class="tsv text-<?php echo $ot_c; ?>"><?php echo $pl_ot; ?>%</div><div class="tsl">On-Time Rate</div></div></div>
            <div class="col-6 col-md-2"><div class="tab-stat"><div class="tsv text-<?php echo $rr_c; ?>"><?php echo $pl_rr; ?>%</div><div class="tsl">Repaid %</div></div></div>
        </div>

        <!-- Badge summary -->
        <div class="d-flex gap-2 flex-wrap mb-3">
            <span class="badge bg-secondary"><?php echo count($pl['instalments']); ?> instalments</span>
            <span class="badge bg-success">&#10003; <?php echo $pl['on_time']; ?> on-time</span>
            <span class="badge bg-warning text-dark">&#9888; <?php echo $pl['late']; ?> late/partial</span>
            <span class="badge bg-danger">&#10007; <?php echo $pl['missed']; ?> missed</span>
            <span class="badge bg-light text-dark border"><?php echo $pl['pending']; ?> pending</span>
            <span class="badge bg-primary"><?php echo count($pl['payments']); ?> payment records</span>
        </div>

        <!-- Repayment progress -->
        <div class="mb-3">
            <div class="d-flex justify-content-between mb-1">
                <small class="fw-bold">Repayment Progress</small>
                <small class="fw-bold text-<?php echo $rr_c; ?>"><?php echo $pl_rr; ?>%</small>
            </div>
            <div class="progress" style="height:14px;border-radius:7px;">
                <div class="progress-bar bg-<?php echo $rr_c; ?>"
                     data-w="<?php echo $pl_rr; ?>"
                     style="width:<?php echo $pl_rr; ?>%"></div>
            </div>
            <small class="text-muted">
                FRW <?php echo number_format($pl['total_paid'],2); ?> paid of
                FRW <?php echo number_format($pl['total_expected'],2); ?> expected
                (outstanding: FRW <?php echo number_format($outstanding,2); ?>)
            </small>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab"
                        data-bs-target="#pmt-<?php echo $plid; ?>" type="button">
                    <i class="fas fa-money-bill-wave me-1"></i>Payment History
                    <span class="badge bg-success ms-1"><?php echo count($pl['payments']); ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab"
                        data-bs-target="#inst-<?php echo $plid; ?>" type="button">
                    <i class="fas fa-list me-1"></i>Instalment Schedule
                    <span class="badge bg-secondary ms-1"><?php echo count($pl['instalments']); ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content border border-top-0 rounded-bottom">

            <!-- TAB 1: Payment History -->
            <div class="tab-pane fade show active p-2" id="pmt-<?php echo $plid; ?>">
            <?php if (empty($pl['payments'])): ?>
                <div class="text-center text-muted py-3">
                    <i class="fas fa-receipt fa-2x d-block mb-2"></i>
                    No payment records found in the payments table for this loan.<br>
                    <small>Amount from instalment records: FRW <?php echo number_format($paid_from_inst ?? 0,2); ?></small>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th class="text-end">Total (FRW)</th>
                                <th class="text-end small">Principal</th>
                                <th class="text-end small">Interest</th>
                                <th class="text-end small">Fees</th>
                                <th class="text-end small">Penalties</th>
                                <th>Method</th>
                                <th>Ref #</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $running_total = 0;
                        foreach ($pl['payments'] as $pi => $p):
                            $p_status   = strtolower(isset($p['payment_status']) ? $p['payment_status'] : 'completed');
                            $p_done     = ($p_status === 'completed' || $p_status === 'paid' || $p_status === 'success');
                            $p_failed   = (strpos($p_status, 'fail') !== false || strpos($p_status, 'reject') !== false || strpos($p_status, 'cancel') !== false);
                            $prow_class = $p_done ? 'pmt-completed' : ($p_failed ? 'pmt-failed' : 'pmt-pending');
                            $pamt       = floatval(isset($p['payment_amount']) ? $p['payment_amount'] : 0);
                            if ($p_done) $running_total += $pamt;
                        ?>
                         <tr class="<?php echo $prow_class; ?>">
                            <td class="text-muted"><?php echo $pi+1; ?></td>
                            <td><strong><?php echo fmtDate(isset($p['payment_date']) ? $p['payment_date'] : ''); ?></strong></td>
                            <td class="text-end fw-bold <?php echo $p_done ? 'text-success' : 'text-muted'; ?>">
                                <?php echo number_format($pamt,2); ?>
                            </td>
                            <td class="text-end text-muted small"><?php echo number_format($p['principal_amount'] ?? 0, 2); ?></td>
                            <td class="text-end text-muted small"><?php echo number_format($p['interest_amount'] ?? 0, 2); ?></td>
                            <td class="text-end text-muted small"><?php echo number_format($p['monitoring_fee'] ?? 0, 2); ?></td>
                            <td class="text-end text-danger small"><?php echo number_format($p['penalties'] ?? 0, 2); ?></td>
                            <td><?php echo !empty($p['payment_method']) ? htmlspecialchars($p['payment_method']) : '—'; ?></td>
                            <td><code><?php echo htmlspecialchars($p['reference_number'] ?? '—'); ?></code></td>
                            <td class="text-center">
                                <?php if ($p_done): ?>
                                <button class="btn btn-outline-danger btn-sm py-1" 
                                        onclick="if(confirm('🚨 WARNING: Delete this payment? \n\nThis reverses the balance and ledger.')) window.location.href='?page=delete_payment&payment_id=<?php echo $p['payment_id'] ?? 0; ?>&loan_id=<?php echo $plid; ?>';">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <td colspan="2" class="fw-bold">TOTAL COMPLETED</td>
                                <td class="text-end fw-bold text-success">
                                    FRW <?php echo number_format($pmts_completed_total,2); ?>
                                </td>
                                <td colspan="5" class="text-muted" style="font-size:.68rem;">
                                    (<?php echo $completed_count; ?> completed payment(s))
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
            </div>

            <!-- TAB 2: Instalment Schedule -->
            <div class="tab-pane fade p-2" id="inst-<?php echo $plid; ?>">
            <?php if (empty($pl['instalments'])): ?>
                <div class="text-center text-muted py-3">
                    <i class="fas fa-list fa-2x d-block mb-2"></i>
                    No instalment records found for this loan.
                </div>
            <?php else:
                $inst_total_due = $inst_total_paid = $inst_total_bal = 0;
            ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Due Date</th>
                                <th class="text-end">Principal</th>
                                <th class="text-end">Interest</th>
                                <th class="text-end">Fees</th>
                                <th class="text-end">Total Due</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Balance</th>
                                <th>Paid Date</th>
                                <th class="text-center">Days Late</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Classification</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pl['instalments'] as $inst):
                            $inst_cls   = isset($inst['_class']) ? $inst['_class'] : 'pending';
                            if ($inst_cls === 'on_time')     $inst_row_class = 'inst-ontime';
                            elseif ($inst_cls === 'late')    $inst_row_class = 'inst-late';
                            elseif ($inst_cls === 'missed')  $inst_row_class = 'inst-missed';
                            else                             $inst_row_class = 'inst-pending';

                            $ist = strtolower(isset($inst['payment_status']) ? $inst['payment_status'] : '');
                            if ($ist === 'paid')          $ibc = 'success';
                            elseif ($ist === 'overdue')   $ibc = 'danger';
                            elseif ($ist === 'partial')   $ibc = 'warning';
                            elseif ($ist === 'pending')   $ibc = 'info';
                            else                          $ibc = 'secondary';
                            $ibc_text = ($ibc === 'warning') ? 'text-dark' : '';

                            // Accurate days late
                            $days_late = 0;
                            $i_paid_date = isset($inst['paid_date']) ? $inst['paid_date'] : '';
                            $i_due_date  = isset($inst['due_date'])  ? $inst['due_date']  : '';
                            if (!empty($i_paid_date) && !empty($i_due_date) && $i_paid_date !== '0000-00-00' && $i_due_date !== '0000-00-00') {
                                $diff = strtotime($i_paid_date) - strtotime($i_due_date);
                                $days_late = max(0, intval($diff / 86400));
                            } elseif (intval(isset($inst['days_overdue']) ? $inst['days_overdue'] : 0) > 0) {
                                $days_late = intval($inst['days_overdue']);
                            }

                            $i_total = floatval(isset($inst['total_amount']) ? $inst['total_amount'] : 0);
                            $i_paid  = floatval(isset($inst['amount_paid'])  ? $inst['amount_paid']  : 0);
                            $i_bal   = floatval(isset($inst['balance_due'])  ? $inst['balance_due']  : 0);
                            $inst_total_due  += $i_total;
                            $inst_total_paid += $i_paid;
                            $inst_total_bal  += $i_bal;

                            // Classification badge html
                            if ($inst_cls === 'on_time') $cls_html = '<span class="badge bg-success">On-Time</span>';
                            elseif ($inst_cls === 'late')$cls_html = '<span class="badge bg-warning text-dark">Late</span>';
                            elseif ($inst_cls === 'missed')$cls_html= '<span class="badge bg-danger">Missed</span>';
                            else                          $cls_html = '<span class="badge bg-light text-dark border">Pending</span>';
                        ?>
                        <tr class="<?php echo $inst_row_class; ?>">
                            <td class="fw-bold"><?php echo isset($inst['instalment_number']) ? $inst['instalment_number'] : ''; ?></td>
                            <td><strong><?php echo fmtDate($i_due_date); ?></strong></td>
                            <td class="text-end"><?php echo number_format(isset($inst['principal_amount']) ? $inst['principal_amount'] : 0,2); ?></td>
                            <td class="text-end"><?php echo number_format(isset($inst['interest_amount']) ? $inst['interest_amount'] : 0,2); ?></td>
                            <td class="text-end"><?php echo number_format(isset($inst['fees_amount']) ? $inst['fees_amount'] : 0,2); ?></td>
                            <td class="text-end fw-bold"><?php echo number_format($i_total,2); ?></td>
                            <td class="text-end fw-bold text-success"><?php echo number_format($i_paid,2); ?></td>
                            <td class="text-end <?php echo ($i_bal > 0.01) ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                <?php echo number_format($i_bal,2); ?>
                            </td>
                            <td><?php echo fmtDate($i_paid_date); ?></td>
                            <td class="text-center">
                                <?php if ($days_late > 0): ?>
                                    <span class="badge bg-danger"><?php echo $days_late; ?> d</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $ibc; ?> <?php echo $ibc_text; ?>">
                                    <?php echo ucfirst(isset($inst['payment_status']) ? $inst['payment_status'] : ''); ?>
                                </span>
                            </td>
                            <td class="text-center"><?php echo $cls_html; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-secondary fw-bold">
                            <tr>
                                <td colspan="5">TOTALS</td>
                                <td class="text-end"><?php echo number_format($inst_total_due,2); ?></td>
                                <td class="text-end text-success"><?php echo number_format($inst_total_paid,2); ?></td>
                                <td class="text-end text-danger"><?php echo number_format($inst_total_bal,2); ?></td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <!-- Colour key -->
                <div class="d-flex gap-2 flex-wrap mt-2 align-items-center">
                    <small class="text-muted fw-bold">Row colours:</small>
                    <span style="background:#e8f5e9;padding:2px 8px;border-radius:4px;font-size:.67rem;">On-Time (paid &le; due date)</span>
                    <span style="background:#fff8e1;padding:2px 8px;border-radius:4px;font-size:.67rem;">Late (paid after due / partial)</span>
                    <span style="background:#ffebee;padding:2px 8px;border-radius:4px;font-size:.67rem;">Missed (unpaid &amp; past due)</span>
                    <span style="background:#f3f4f6;padding:2px 8px;border-radius:4px;font-size:.67rem;">Pending</span>
                </div>
            <?php endif; ?>
            </div>

        </div><!-- /tab-content -->
    </div><!-- /modal-body -->

    <div class="modal-footer py-2">
        <small class="text-muted me-auto">
            On-time: <strong class="text-<?php echo $ot_c; ?>"><?php echo $pl_ot; ?>%</strong>
            &nbsp;|&nbsp; Repaid: <strong class="text-<?php echo $rr_c; ?>"><?php echo $pl_rr; ?>%</strong>
            &nbsp;|&nbsp; Payment rate: <strong class="text-<?php echo $rc; ?>"><?php echo $pl_pr; ?>%</strong>
        </small>
        <a href="index.php?page=viewloan&id=<?php echo $plid; ?>" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-external-link-alt me-1"></i>Open Full Loan
        </a>
        <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
    </div>

   </div>
  </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
var _modals = {};
function openModal(id) {
    var el = document.getElementById('loanModal_' + id);
    if (!el) return;
    if (!_modals[id]) { _modals[id] = new bootstrap.Modal(el); }
    _modals[id].show();
}

function animateBars(root) {
    var bars = (root || document).querySelectorAll('.progress-bar[data-w]');
    bars.forEach(function(b) {
        var w = b.getAttribute('data-w') + '%';
        b.style.width = '0%';
        setTimeout(function() { b.style.width = w; }, 100);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    animateBars(document);
    document.querySelectorAll('.modal').forEach(function(m) {
        m.addEventListener('shown.bs.modal', function() { animateBars(m); });
    });
});
</script>
</body>
</html>
<?php $conn->close(); ?>
