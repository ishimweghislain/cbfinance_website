<?php
// assets.php
// ────────────────────────────────────────────────
// FIXED version – movement column type corrected
// ────────────────────────────────────────────────

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

$error_message   = '';
$success_message = '';

// ─── 1. Ensure accumulated_depreciation column exists ────────────────────────
$check = $conn->query("SHOW COLUMNS FROM assets LIKE 'accumulated_depreciation'");
if ($check && $check->num_rows == 0) {
    $conn->query("ALTER TABLE assets ADD COLUMN accumulated_depreciation DECIMAL(15,2) DEFAULT 0.00 AFTER daily_depreciation");
    if ($conn->error) {
        $error_message .= "Failed to add accumulated_depreciation column: " . $conn->error . " ";
    } else {
        $success_message .= "Added accumulated_depreciation column. ";
    }
}

// ─── 2. Handle DELETE (with confirmation already in JS) ──────────────────────
if (isset($_GET['delete'])) {
    $asset_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM assets WHERE asset_id = ?");
    $stmt->bind_param("i", $asset_id);
    if ($stmt->execute()) {
        $success_message = "Asset deleted successfully.";
    } else {
        $error_message = "Delete failed: " . $stmt->error;
    }
    $stmt->close();
    echo "<script>window.location.href='?page=assets' </script>";
    exit();
}

// ─── Dropdown options ────────────────────────────────────────────────────────
$asset_categories = [
    'EQUIPMENT'       => 'Equipment',
    'FURNITURE'       => 'Furniture',
    'COMPUTER'        => 'Computer Equipment',
    'VEHICLE'         => 'Vehicle',
    'BUILDING'        => 'Building',
    'LAND'            => 'Land',
    'MACHINERY'       => 'Machinery',
    'OTHER'           => 'Other'
];

$asset_conditions = [
    'EXCELLENT' => 'Excellent',
    'GOOD'      => 'Good',
    'FAIR'      => 'Fair',
    'POOR'      => 'Poor',
    'SCRAP'     => 'Scrap'
];

$locations = [
    'OFFICE'    => 'Office',
    'WAREHOUSE' => 'Warehouse',
    'FACTORY'   => 'Factory',
    'SHOWROOM'  => 'Showroom',
    'STOREROOM' => 'Storeroom'
];

// ─── Depreciation calculation function ───────────────────────────────────────
function calculateDepreciation($totalValue, $lifespanYears, $ratePercent, $acqDate, $reportDate) {
    $acq  = new DateTime($acqDate);
    $rep  = new DateTime($reportDate);
    $diff = $acq->diff($rep);
    $yearsPassed = $diff->y + ($diff->m / 12) + ($diff->d / 365);

    $annual   = $totalValue * ($ratePercent / 100);
    $monthly  = $annual / 12;
    $daily    = $annual / 365;

    $monthsPassed = ($diff->y * 12) + $diff->m;
    $accum    = $monthly * $monthsPassed;
    $accum    = min($accum, $totalValue); // never exceed cost

    $nbv      = max(0, $totalValue - $accum);

    return [
        'monthly_depreciation'     => round($monthly, 2),
        'daily_depreciation'       => round($daily, 2),
        'accumulated_depreciation' => round($accum, 2),
        'net_book_value'           => round($nbv, 2),
        'total_months'             => $monthsPassed
    ];
}

// ─── Generate next asset number (NUHFxxxx) ───────────────────────────────────
function generateAssetNumber($conn) {
    $result = $conn->query("SELECT MAX(asset_number) AS last FROM assets WHERE asset_number LIKE 'NUHF%'");
    if ($row = $result->fetch_assoc()) {
        if ($row['last']) {
            $num = (int)substr($row['last'], 4) + 1;
            return 'NUHF' . str_pad($num, 4, '0', STR_PAD_LEFT);
        }
    }
    return 'NUHF0001';
}

// ─── Get last balance before given date ──────────────────────────────────────
function getAccountBalanceBefore($conn, $account_code, $date) {
    $stmt = $conn->prepare("
        SELECT ending_balance 
        FROM ledger 
        WHERE account_code = ? 
          AND transaction_date < ? 
        ORDER BY transaction_date DESC, ledger_id DESC 
        LIMIT 1
    ");
    $stmt->bind_param("ss", $account_code, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return (float)$row['ending_balance'];
    }
    return 0.0;
}

// ─── Voucher number (e.g. DEP-2025-01-0001) ──────────────────────────────────
function generateVoucherNumber($conn, $prefix = 'DEP') {
    $year  = date('Y');
    $month = date('m');
    $pattern = "$prefix-$year-$month-%";

    $stmt = $conn->prepare("SELECT MAX(voucher_number) AS last FROM ledger WHERE voucher_number LIKE ?");
    $stmt->bind_param("s", $pattern);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['last']) {
            $parts = explode('-', $row['last']);
            $seq   = (int)end($parts) + 1;
            return "$prefix-$year-$month-" . str_pad($seq, 4, '0', STR_PAD_LEFT);
        }
    }
    return "$prefix-$year-$month-0001";
}

// ─── Record monthly depreciation (main logic) ────────────────────────────────
function recordMonthlyDepreciation($conn, $month, $year, $created_by = 'system') {
    $conn->begin_transaction();

    try {
        $ref = "DEP-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-$year";
        $voucher = generateVoucherNumber($conn, 'DEP');
        $trans_date = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year)); // last day

        // Prevent double run
        $stmt = $conn->prepare("SELECT 1 FROM ledger WHERE reference_type = 'DEPRECIATION' AND reference_id = ? LIMIT 1");
        $stmt->bind_param("s", $ref);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Depreciation already recorded for " . date('F Y', mktime(0,0,0,$month,1,$year)));
        }

        $start = date('Y-m-01', mktime(0,0,0,$month,1,$year));
        $end   = $trans_date;

        $query = "
            SELECT a.*,
                   (a.acquisition_value + a.additions) AS total_value,
                   DATEDIFF(
                       LEAST(?, COALESCE(a.disposal_date, ?)),
                       GREATEST(?, a.acquisition_date)
                   ) AS days_in_month
            FROM assets a
            WHERE a.acquisition_date <= ?
              AND (a.disposal_date IS NULL OR a.disposal_date > ?)
              AND a.monthly_depreciation > 0
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssss", $end, $end, $start, $end, $start);
        $stmt->execute();
        $assets = $stmt->get_result();

        $dep_2y = $dep_4y = $dep_other = 0;
        $details = [];

        while ($asset = $assets->fetch_assoc()) {
            $days = (int)$asset['days_in_month'];
            if ($days <= 0) continue;

            $actual_dep = round(($asset['monthly_depreciation'] / 30) * $days, 2);

            if ($asset['lifespan_years'] == 2)      $dep_2y    += $actual_dep;
            elseif ($asset['lifespan_years'] == 4)  $dep_4y    += $actual_dep;
            else                                    $dep_other += $actual_dep;

            $details[] = [
                'asset_id'          => $asset['asset_id'],
                'depreciation_amount' => $actual_dep
            ];
        }

        $total_dep = $dep_2y + $dep_4y + $dep_other;
        if ($total_dep <= 0) {
            throw new Exception("No depreciation to record for " . date('F Y', mktime(0,0,0,$month,1,$year)));
        }

        $now = date('Y-m-d H:i:s');
        $desc = "Monthly Depreciation – " . date('F Y', mktime(0,0,0,$month,1,$year));

        // ── Expense debits ───────────────────────────────────────
        $accounts = [
            ['code' => '5401', 'name' => 'Depreciation - 2 Year Assets', 'amt' => $dep_2y],
            ['code' => '5402', 'name' => 'Depreciation - 4 Year Assets', 'amt' => $dep_4y],
            ['code' => '5403', 'name' => 'Depreciation - Other Assets',  'amt' => $dep_other],
        ];

        foreach ($accounts as $acc) {
            if ($acc['amt'] <= 0) continue;
            
            $bal_before = getAccountBalanceBefore($conn, $acc['code'], $trans_date);
            $bal_after  = $bal_before + $acc['amt'];
            
            // FIXED: Declare all variables BEFORE using them in prepared statement
            $class = 'EXPENSE';
            $code = $acc['code'];
            $name = $acc['name'];
            $particular = $acc['name'];
            $zero = 0.0;
            $amount = $acc['amt'];
            $movement = 1; // FIXED: 1 for DR, -1 for CR (numeric value)
            $ref_type = 'DEPRECIATION';
            
            $stmt = $conn->prepare("
                INSERT INTO ledger (
                    transaction_date, class, account_code, account_name, particular,
                    voucher_number, narration, beginning_balance, debit_amount, credit_amount,
                    movement, ending_balance, reference_type, reference_id, created_by, created_at
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            
            $stmt->bind_param(
                "sssssssdddidssss",
                $trans_date, $class, $code, $name, $particular,
                $voucher, $desc, $bal_before, $amount, $zero,
                $movement, $bal_after, $ref_type, $ref, $created_by, $now
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert expense entry: " . $stmt->error);
            }
        }

        // ── Credit Accumulated Depreciation (1405) ───────────────
        $bal_before_1405 = getAccountBalanceBefore($conn, '1405', $trans_date);
        $bal_after_1405  = $bal_before_1405 - $total_dep;

        // FIXED: Declare all variables BEFORE bind_param
        $class = 'ASSET';
        $code = '1405';
        $name = 'Accumulated Depreciation';
        $particular = 'Monthly Depreciation';
        $movement = -1; // FIXED: -1 for CR (numeric value)
        $zero = 0.0;
        $ref_type = 'DEPRECIATION';

        $stmt = $conn->prepare("
            INSERT INTO ledger (
                transaction_date, class, account_code, account_name, particular,
                voucher_number, narration, beginning_balance, debit_amount, credit_amount,
                movement, ending_balance, reference_type, reference_id, created_by, created_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        
        $stmt->bind_param(
            "sssssssdddidssss",
            $trans_date, $class, $code, $name, $particular,
            $voucher, $desc, $bal_before_1405, $zero, $total_dep,
            $movement, $bal_after_1405, $ref_type, $ref, $created_by, $now
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert accumulated depreciation entry: " . $stmt->error);
        }

        // ── Update assets table (with cap) ───────────────────────
        foreach ($details as $d) {
            $stmt = $conn->prepare("
                UPDATE assets
                SET accumulated_depreciation = LEAST(
                    accumulated_depreciation + ?,
                    acquisition_value + additions
                )
                WHERE asset_id = ?
            ");
            $stmt->bind_param("di", $d['depreciation_amount'], $d['asset_id']);
            $stmt->execute();
        }

        $conn->commit();

        return "Depreciation recorded successfully.<br>" .
               "Voucher: <strong>$voucher</strong><br>" .
               "Total: <strong> " . number_format($total_dep, 2) . "</strong>";

    } catch (Exception $e) {
        $conn->rollback();
        return "Error: " . $e->getMessage();
    }
}

// ─── ADD NEW ASSET ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_asset'])) {
    $data = [
        'category'         => trim($_POST['category'] ?? ''),
        'item_name'        => trim($_POST['item_name'] ?? ''),
        'description'      => trim($_POST['description'] ?? ''),
        'serial_number'    => trim($_POST['serial_number'] ?? ''),
        'location'         => trim($_POST['location'] ?? ''),
        'assigned_user'    => trim($_POST['assigned_user'] ?? ''),
        'acquisition_date' => trim($_POST['acquisition_date'] ?? ''),
        'acquisition_value'=> floatval(str_replace(',', '', $_POST['acquisition_value'] ?? 0)),
        'supplier'         => trim($_POST['supplier'] ?? ''),
        'additions'        => floatval(str_replace(',', '', $_POST['additions'] ?? 0)),
        'lifespan_years'   => (int)($_POST['lifespan_years'] ?? 0),
        'depreciation_rate'=> floatval($_POST['depreciation_rate'] ?? 0),
        'condition'        => trim($_POST['condition'] ?? 'GOOD'),
        'reporting_date'   => trim($_POST['reporting_date'] ?? date('Y-m-d'))
    ];

    if (empty($data['item_name']) || empty($data['acquisition_date']) || $data['acquisition_value'] <= 0) {
        $error_message = "Item Name, Acquisition Date and Value > 0 are required.";
    } elseif ($data['lifespan_years'] < 1 || $data['depreciation_rate'] <= 0 || $data['depreciation_rate'] > 100) {
        $error_message = "Invalid lifespan or depreciation rate.";
    } else {
        $asset_number = generateAssetNumber($conn);
        $total_value  = $data['acquisition_value'] + $data['additions'];

        $dep = calculateDepreciation(
            $total_value,
            $data['lifespan_years'],
            $data['depreciation_rate'],
            $data['acquisition_date'],
            $data['reporting_date']
        );

        $stmt = $conn->prepare("
            INSERT INTO assets (
                asset_number, category, item_name, description, serial_number,
                location, assigned_user, acquisition_date, acquisition_value,
                supplier, additions, lifespan_years, depreciation_rate, asset_condition,
                monthly_depreciation, daily_depreciation, accumulated_depreciation, reporting_date
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "ssssssssdsdiddddds",
            $asset_number, $data['category'], $data['item_name'], $data['description'], $data['serial_number'],
            $data['location'], $data['assigned_user'], $data['acquisition_date'], $data['acquisition_value'],
            $data['supplier'], $data['additions'], $data['lifespan_years'], $data['depreciation_rate'], $data['condition'],
            $dep['monthly_depreciation'], $dep['daily_depreciation'], $dep['accumulated_depreciation'], $data['reporting_date']
        );

        if ($stmt->execute()) {
            $success_message = "Asset added successfully: $asset_number";
            echo "<script>window.location.href='?page=assets' </script>";
            exit();
        } else {
            $error_message = "Insert failed: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ─── DISPOSE ASSET ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispose_asset'])) {
    $asset_number   = trim($_POST['asset_number'] ?? '');
    $disposal_date  = trim($_POST['disposal_date'] ?? '');
    $disposal_value = floatval(str_replace(',', '', $_POST['disposal_value'] ?? 0));
    $reason         = trim($_POST['disposal_reason'] ?? '');

    if (empty($asset_number) || empty($disposal_date)) {
        $error_message = "Asset number and disposal date are required.";
    } else {
        $stmt = $conn->prepare("
            UPDATE assets 
            SET disposal_date = ?, 
                disposal_value = ?, 
                disposal_reason = ?, 
                asset_condition = 'SCRAP'
            WHERE asset_number = ?
        ");
        $stmt->bind_param("sdss", $disposal_date, $disposal_value, $reason, $asset_number);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success_message = "Asset $asset_number disposed successfully.";
        } else {
            $error_message = "Disposal failed: Asset not found or already disposed.";
        }
        $stmt->close();
    }
}

// ─── RECORD MONTHLY DEPRECIATION FORM ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_monthly_depreciation'])) {
    $month = (int)($_POST['depreciation_month'] ?? 0);
    $year  = (int)($_POST['depreciation_year'] ?? 0);
    $by    = $_SESSION['user_id'] ?? $_SESSION['username'] ?? 'system';

    if ($month >= 1 && $month <= 12 && $year >= 2000) {
        $result = recordMonthlyDepreciation($conn, $month, $year, $by);
        if (strpos($result, 'Error') === 0) {
            $error_message = $result;
        } else {
            $success_message = $result;
        }
    } else {
        $error_message = "Invalid month/year selected.";
    }
}

// ─── Fetch data for display ──────────────────────────────────────────────────
$assets_result = $conn->query("SELECT * FROM assets ORDER BY asset_number ASC");

// Statistics
$stats_result = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN disposal_date IS NULL THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN disposal_date IS NOT NULL THEN 1 ELSE 0 END) AS disposed,
        SUM(acquisition_value + additions) AS total_value,
        AVG(depreciation_rate) AS avg_dep_rate,
        SUM(COALESCE(accumulated_depreciation, 0)) AS total_accum_dep
    FROM assets
");
$stats = $stats_result ? $stats_result->fetch_assoc() : [];

// Total current book value
$book_result = $conn->query("
    SELECT SUM(GREATEST(0, (acquisition_value + additions) - COALESCE(accumulated_depreciation, 0))) AS total_book
    FROM assets 
    WHERE disposal_date IS NULL
");
$book_row = $book_result ? $book_result->fetch_assoc() : [];
$total_book_value = $book_row['total_book'] ?? 0;

// Last depreciation
$last_dep_result = $conn->query("
    SELECT MAX(transaction_date) AS last 
    FROM ledger 
    WHERE reference_type = 'DEPRECIATION' 
      AND account_code IN ('5401','5402','5403')
");
$last_dep = $last_dep_result ? $last_dep_result->fetch_assoc()['last'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fixed Assets Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sticky-top { position: sticky; top: 0; z-index: 10; }
        .table-responsive { overflow-x: auto; }
        .money-input { text-align: right; }
    </style>
</head>
<body>

<div class="container-fluid px-0">
    <!-- Messages -->
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show mx-3 mt-3">
        <?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show mx-3 mt-3">
        <?= $success_message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="row mx-3 mt-3 mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 fw-bold text-primary mb-0">
                <i class="fas fa-boxes me-2"></i> Fixed Assets Register
            </h2>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                    <i class="fas fa-plus-circle me-2"></i>Add Asset
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#recordDepModal">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Record Monthly Dep.
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics cards -->
    <div class="row mx-3 mb-4">
        <!-- Total Assets -->
        <div class="col-xl-3 col-lg-6 mb-3">
            <div class="card border-start border-primary border-4 h-100">
                <div class="card-body">
                    <h6 class="text-muted">Total Assets</h6>
                    <h3 class="text-primary"><?= $stats['total'] ?? 0 ?></h3>
                    <span class="badge bg-success">Active: <?= $stats['active'] ?? 0 ?></span>
                    <span class="badge bg-danger ms-1">Disposed: <?= $stats['disposed'] ?? 0 ?></span>
                </div>
            </div>
        </div>
        <!-- Total Value -->
        <div class="col-xl-3 col-lg-6 mb-3">
            <div class="card border-start border-success border-4 h-100">
                <div class="card-body">
                    <h6 class="text-muted">Total Acquisition Value</h6>
                    <h3 class="text-success"> <?= number_format($stats['total_value'] ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
        <!-- Book Value -->
        <div class="col-xl-3 col-lg-6 mb-3">
            <div class="card border-start border-info border-4 h-100">
                <div class="card-body">
                    <h6 class="text-muted">Current Book Value</h6>
                    <h3 class="text-info"> <?= number_format($total_book_value, 2) ?></h3>
                </div>
            </div>
        </div>
        <!-- Accumulated Dep -->
        <div class="col-xl-3 col-lg-6 mb-3">
            <div class="card border-start border-warning border-4 h-100">
                <div class="card-body">
                    <h6 class="text-muted">Accumulated Depreciation</h6>
                    <h3 class="text-warning"> <?= number_format($stats['total_accum_dep'] ?? 0, 2) ?></h3>
                    <?php if ($last_dep): ?>
                    <small class="text-muted">Last: <?= date('d/m/Y', strtotime($last_dep)) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="row mx-3 mb-3">
        <div class="col-md-6">
            <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search assets...">
        </div>
    </div>

    <!-- Assets Table -->
    <div class="row mx-0">
        <div class="col-12 px-0">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Assets Register</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 65vh;">
                        <table class="table table-hover table-striped mb-0" id="assetsTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Asset No</th>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Location</th>
                                    <th>Acq. Date</th>
                                    <th>Value</th>
                                    <th>Accum. Dep</th>
                                    <th>Book Value</th>
                                    <th>Condition</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($assets_result && $assets_result->num_rows > 0): ?>
                                    <?php while ($asset = $assets_result->fetch_assoc()): 
                                        $total_val = $asset['acquisition_value'] + $asset['additions'];
                                        $book_val  = max(0, $total_val - ($asset['accumulated_depreciation'] ?? 0));
                                        $disposed  = !empty($asset['disposal_date']);
                                    ?>
                                    <tr class="<?= $disposed ? 'table-secondary' : '' ?>">
                                        <td><code><?= htmlspecialchars($asset['asset_number']) ?></code></td>
                                        <td><?= htmlspecialchars($asset['item_name']) ?></td>
                                        <td><?= htmlspecialchars($asset['category']) ?></td>
                                        <td><?= htmlspecialchars($asset['location']) ?></td>
                                        <td><?= $asset['acquisition_date'] ? date('d/m/Y', strtotime($asset['acquisition_date'])) : '-' ?></td>
                                        <td> <?= number_format($total_val, 2) ?></td>
                                        <td class="text-danger"> <?= number_format($asset['accumulated_depreciation'] ?? 0, 2) ?></td>
                                        <td class="text-success fw-bold"> <?= number_format($book_val, 2) ?></td>
                                        <td>
                                            <?= htmlspecialchars($asset['asset_condition'] ?? 'Unknown') ?>
                                        </td>
                                        <td class="text-center">
                                            
                                            <a href="?page=edit_asset&id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-outline-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?page=assets&delete=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Permanently delete <?= htmlspecialchars($asset['asset_number']) ?>?')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>

                                    <!-- Dispose Modal per asset -->
                                    <div class="modal fade" id="disposeModal<?= $asset['asset_id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="POST" class="modal-content">
                                                <div class="modal-header bg-warning">
                                                    <h5 class="modal-title">Dispose <?= htmlspecialchars($asset['asset_number']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="asset_number" value="<?= htmlspecialchars($asset['asset_number']) ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Disposal Date *</label>
                                                        <input type="date" name="disposal_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Disposal Value</label>
                                                        <input type="text" name="disposal_value" class="form-control money-input" placeholder="0.00">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Reason *</label>
                                                        <select name="disposal_reason" class="form-select" required>
                                                            <option value="">Select...</option>
                                                            <option value="SOLD">Sold</option>
                                                            <option value="SCRAPPED">Scrapped</option>
                                                            <option value="LOST">Lost</option>
                                                            <option value="DAMAGED">Damaged</option>
                                                            <option value="OBSOLETE">Obsolete</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="dispose_asset" class="btn btn-danger">Dispose Asset</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="10" class="text-center py-5 text-muted">No assets found</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Asset Modal -->
<div class="modal fade" id="addAssetModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form method="POST" class="modal-content" id="addAssetForm">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add New Asset</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Item Name *</label>
                        <input type="text" name="item_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($asset_categories as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Serial Number</label>
                        <input type="text" name="serial_number" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Location *</label>
                        <select name="location" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($locations as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Assigned User</label>
                        <input type="text" name="assigned_user" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Supplier</label>
                        <input type="text" name="supplier" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Acquisition Date *</label>
                        <input type="date" name="acquisition_date" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Acquisition Value  *</label>
                        <input type="text" name="acquisition_value" class="form-control money-input" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Additions</label>
                        <input type="text" name="additions" class="form-control money-input" value="0">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Condition *</label>
                        <select name="condition" class="form-select" required>
                            <?php foreach ($asset_conditions as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $k === 'GOOD' ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Lifespan (Years) *</label>
                        <input type="number" name="lifespan_years" class="form-control" min="1" max="50" value="4" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Depreciation Rate (%) *</label>
                        <input type="number" name="depreciation_rate" class="form-control" min="0.01" max="100" step="0.01" value="25" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Reporting Date *</label>
                        <input type="date" name="reporting_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_asset" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Asset
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Record Monthly Depreciation Modal -->
<div class="modal fade" id="recordDepModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Record Monthly Depreciation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    This will record depreciation for all active assets for the selected month.
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Month *</label>
                        <select name="depreciation_month" class="form-select" required>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= date('n') == $m ? 'selected' : '' ?>>
                                <?= date('F', mktime(0,0,0,$m,1)) ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Year *</label>
                        <select name="depreciation_year" class="form-select" required>
                            <?php for ($y = date('Y') - 3; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?= $y ?>" <?= date('Y') == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="record_monthly_depreciation" class="btn btn-success">
                    <i class="fas fa-calculator me-2"></i>Record Depreciation
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Money input auto-format
document.querySelectorAll('.money-input').forEach(el => {
    el.addEventListener('blur', function() {
        let val = this.value.replace(/[^\d.]/g, '');
        if (val && !isNaN(val)) {
            this.value = parseFloat(val).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    });
    el.addEventListener('focus', function() {
        this.value = this.value.replace(/[^\d.]/g, '');
    });
});

// Table search
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        const rows = document.querySelectorAll('#assetsTable tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });
}

// Form validation
document.getElementById('addAssetForm')?.addEventListener('submit', function(e) {
    const acqValue = parseFloat(document.querySelector('[name="acquisition_value"]').value.replace(/[^\d.]/g, ''));
    const lifespan = parseInt(document.querySelector('[name="lifespan_years"]').value);
    const depRate = parseFloat(document.querySelector('[name="depreciation_rate"]').value);
    
    if (acqValue <= 0) {
        e.preventDefault();
        alert('Acquisition value must be greater than 0');
        return false;
    }
    
    if (lifespan < 1 || lifespan > 50) {
        e.preventDefault();
        alert('Lifespan must be between 1 and 50 years');
        return false;
    }
    
    if (depRate <= 0 || depRate > 100) {
        e.preventDefault();
        alert('Depreciation rate must be between 0.01% and 100%');
        return false;
    }
});

// Auto-dismiss alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

</body>
</html>

<?php $conn->close(); ?>
