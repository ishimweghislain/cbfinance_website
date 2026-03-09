<?php

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
$asset_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if asset exists
$asset = null;
if ($asset_id > 0) {
    $result = $conn->query("SELECT * FROM assets WHERE asset_id = $asset_id");
    if ($result && $result->num_rows > 0) {
        $asset = $result->fetch_assoc();
    } else {
        $error_message = "Asset not found.";
        $asset_id = 0;
    }
}

// ─── Same dropdown options as assets.php ────────────────────────────────────
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

// ─── Same depreciation calculation function ─────────────────────────────────
function calculateDepreciation($totalValue, $lifespanYears, $ratePercent, $acqDate, $reportDate) {
    $acq  = new DateTime($acqDate);
    $rep  = new DateTime($reportDate);
    $diff = $acq->diff($rep);
    
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

// ─── UPDATE ASSET ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_asset'])) {
    if ($asset_id === 0) {
        $error_message = "Invalid asset ID.";
    } else {
        // Sanitize and validate input
        $category         = $conn->real_escape_string(trim($_POST['category'] ?? ''));
        $item_name        = $conn->real_escape_string(trim($_POST['item_name'] ?? ''));
        $description      = $conn->real_escape_string(trim($_POST['description'] ?? ''));
        $serial_number    = $conn->real_escape_string(trim($_POST['serial_number'] ?? ''));
        $location         = $conn->real_escape_string(trim($_POST['location'] ?? ''));
        $assigned_user    = $conn->real_escape_string(trim($_POST['assigned_user'] ?? ''));
        $acquisition_date = $conn->real_escape_string(trim($_POST['acquisition_date'] ?? ''));
        $acquisition_value= floatval(str_replace(',', '', $_POST['acquisition_value'] ?? 0));
        $supplier         = $conn->real_escape_string(trim($_POST['supplier'] ?? ''));
        $additions        = floatval(str_replace(',', '', $_POST['additions'] ?? 0));
        $lifespan_years   = (int)($_POST['lifespan_years'] ?? 0);
        $depreciation_rate= floatval($_POST['depreciation_rate'] ?? 0);
        $condition        = $conn->real_escape_string(trim($_POST['condition'] ?? 'GOOD'));
        $reporting_date   = $conn->real_escape_string(trim($_POST['reporting_date'] ?? date('Y-m-d')));
        
        // Disposal fields
        $disposal_date    = !empty($_POST['disposal_date']) ? "'" . $conn->real_escape_string(trim($_POST['disposal_date'])) . "'" : "NULL";
        $disposal_value   = !empty($_POST['disposal_value']) ? floatval(str_replace(',', '', $_POST['disposal_value'])) : "NULL";
        $disposal_reason  = !empty($_POST['disposal_reason']) ? "'" . $conn->real_escape_string(trim($_POST['disposal_reason'])) . "'" : "NULL";

        // Validate required fields
        if (empty($item_name) || empty($acquisition_date) || $acquisition_value <= 0) {
            $error_message = "Item Name, Acquisition Date and Value > 0 are required.";
        } elseif ($lifespan_years < 1 || $lifespan_years > 50 || $depreciation_rate <= 0 || $depreciation_rate > 100) {
            $error_message = "Invalid lifespan (1-50 years) or depreciation rate (0.01-100%).";
        } else {
            $total_value = $acquisition_value + $additions;
            
            // Only recalculate depreciation if relevant fields changed
            $needs_recalc = (
                $acquisition_date != $asset['acquisition_date'] ||
                $acquisition_value != $asset['acquisition_value'] ||
                $additions != $asset['additions'] ||
                $lifespan_years != $asset['lifespan_years'] ||
                $depreciation_rate != $asset['depreciation_rate']
            );
            
            if ($needs_recalc) {
                $dep = calculateDepreciation(
                    $total_value,
                    $lifespan_years,
                    $depreciation_rate,
                    $acquisition_date,
                    $reporting_date
                );
            } else {
                // Keep existing depreciation values
                $dep = [
                    'monthly_depreciation'     => $asset['monthly_depreciation'],
                    'daily_depreciation'       => $asset['daily_depreciation'],
                    'accumulated_depreciation' => $asset['accumulated_depreciation']
                ];
            }
            
            // Handle disposal - if disposal date is set, update condition to SCRAP
            if (!empty($_POST['disposal_date'])) {
                $condition = 'SCRAP';
            }
            
            // Format disposal_value if it's not NULL
            if ($disposal_value !== "NULL") {
                $disposal_value = number_format($disposal_value, 2, '.', '');
            }
            
            // Build SQL query
            $sql = "UPDATE assets SET 
                    category = '$category',
                    item_name = '$item_name',
                    description = '$description',
                    serial_number = '$serial_number',
                    location = '$location',
                    assigned_user = '$assigned_user',
                    acquisition_date = '$acquisition_date',
                    acquisition_value = $acquisition_value,
                    supplier = '$supplier',
                    additions = $additions,
                    lifespan_years = $lifespan_years,
                    depreciation_rate = $depreciation_rate,
                    asset_condition = '$condition',
                    reporting_date = '$reporting_date',
                    disposal_date = $disposal_date,
                    disposal_value = $disposal_value,
                    disposal_reason = $disposal_reason,
                    monthly_depreciation = " . $dep['monthly_depreciation'] . ",
                    daily_depreciation = " . $dep['daily_depreciation'] . ",
                    accumulated_depreciation = " . $dep['accumulated_depreciation'] . "
                WHERE asset_id = $asset_id";
            
            if ($conn->query($sql)) {
                $success_message = "Asset updated successfully.";
                // Refresh asset data
                $result = $conn->query("SELECT * FROM assets WHERE asset_id = $asset_id");
                if ($result && $result->num_rows > 0) {
                    $asset = $result->fetch_assoc();
                }
            } else {
                $error_message = "Update failed: " . $conn->error;
            }
        }
    }
}

// ─── CALCULATE DEPRECIATION PREVIEW ─────────────────────────────────────────
$dep_preview = null;
if ($asset) {
    $total_value = $asset['acquisition_value'] + $asset['additions'];
    $dep_preview = calculateDepreciation(
        $total_value,
        $asset['lifespan_years'],
        $asset['depreciation_rate'],
        $asset['acquisition_date'],
        $asset['reporting_date']
    );
}

// ─── GET ASSET HISTORY ──────────────────────────────────────────────────────
$history = [];
if ($asset_id > 0 && $asset) {
    $search_pattern = '%' . $asset['asset_number'] . '%';
    $search_pattern_escaped = $conn->real_escape_string($search_pattern);
    
    $history_result = $conn->query("
        SELECT * FROM ledger 
        WHERE reference_type = 'DEPRECIATION' 
        AND (particular LIKE '$search_pattern_escaped' OR reference_id LIKE '$search_pattern_escaped')
        ORDER BY transaction_date DESC, ledger_id DESC
        LIMIT 20
    ");
    
    if ($history_result) {
        while ($row = $history_result->fetch_assoc()) {
            $history[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Asset - Fixed Assets Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-header { background-color: #f8f9fa; }
        .form-label { font-weight: 500; }
        .money-input { text-align: right; }
        .nav-tabs .nav-link.active { font-weight: 600; }
        .depreciation-card { border-left: 4px solid #0d6efd; }
        .history-table { font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="container-fluid px-4 py-3">
    <!-- Back button -->
    <div class="mb-4">
        <a href="?page=assets" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Assets
        </a>
    </div>

    <!-- Messages -->
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>
        <?= $success_message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!$asset): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Asset not found or invalid ID specified.
        </div>
    <?php else: ?>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h4 fw-bold text-primary">
                            <i class="fas fa-edit me-2"></i>Edit Asset
                        </h2>
                        <p class="text-muted mb-0">Asset Number: <code><?= htmlspecialchars($asset['asset_number']) ?></code></p>
                    </div>
                    <div class="badge bg-<?= !empty($asset['disposal_date']) ? 'danger' : 'success' ?> fs-6 p-3">
                        <?= !empty($asset['disposal_date']) ? 'DISPOSED' : 'ACTIVE' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4" id="assetTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit" type="button" role="tab">
                    <i class="fas fa-pencil-alt me-2"></i>Edit Details
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="depreciation-tab" data-bs-toggle="tab" data-bs-target="#depreciation" type="button" role="tab">
                    <i class="fas fa-calculator me-2"></i>Depreciation
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                    <i class="fas fa-history me-2"></i>Transaction History
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="assetTabsContent">
            <!-- EDIT DETAILS TAB -->
            <div class="tab-pane fade show active" id="edit" role="tabpanel">
                <form method="POST" id="updateAssetForm">
                    <input type="hidden" name="update_asset" value="1">
                    
                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Basic Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Asset Number</label>
                                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($asset['asset_number']) ?>" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Item Name *</label>
                                        <input type="text" name="item_name" class="form-control" value="<?= htmlspecialchars($asset['item_name']) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Category *</label>
                                        <select name="category" class="form-select" required>
                                            <option value="">— Select —</option>
                                            <?php foreach ($asset_categories as $k => $v): ?>
                                            <option value="<?= $k ?>" <?= $asset['category'] == $k ? 'selected' : '' ?>>
                                                <?= $v ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($asset['description']) ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Serial Number</label>
                                        <input type="text" name="serial_number" class="form-control" value="<?= htmlspecialchars($asset['serial_number']) ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Location *</label>
                                        <select name="location" class="form-select" required>
                                            <option value="">— Select —</option>
                                            <?php foreach ($locations as $k => $v): ?>
                                            <option value="<?= $k ?>" <?= $asset['location'] == $k ? 'selected' : '' ?>>
                                                <?= $v ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Assigned User</label>
                                        <input type="text" name="assigned_user" class="form-control" value="<?= htmlspecialchars($asset['assigned_user']) ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Condition *</label>
                                        <select name="condition" class="form-select" required>
                                            <?php foreach ($asset_conditions as $k => $v): ?>
                                            <option value="<?= $k ?>" <?= $asset['asset_condition'] == $k ? 'selected' : '' ?>>
                                                <?= $v ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="col-lg-6">
                            <!-- Acquisition Info Card -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Acquisition Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Acquisition Date *</label>
                                        <input type="date" name="acquisition_date" class="form-control" 
                                               value="<?= htmlspecialchars($asset['acquisition_date']) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Acquisition Value *</label>
                                        <input type="text" name="acquisition_value" class="form-control money-input" 
                                               value="<?= number_format($asset['acquisition_value'], 2) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Additions </label>
                                        <input type="text" name="additions" class="form-control money-input" 
                                               value="<?= number_format($asset['additions'], 2) ?>">
                                        <small class="text-muted">Additional costs after acquisition</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Supplier</label>
                                        <input type="text" name="supplier" class="form-control" 
                                               value="<?= htmlspecialchars($asset['supplier']) ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Depreciation Settings Card -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Depreciation Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Lifespan (Years) *</label>
                                        <input type="number" name="lifespan_years" class="form-control" 
                                               min="1" max="50" value="<?= $asset['lifespan_years'] ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Depreciation Rate (%) *</label>
                                        <input type="number" name="depreciation_rate" class="form-control" 
                                               min="0.01" max="100" step="0.01" 
                                               value="<?= $asset['depreciation_rate'] ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Reporting Date *</label>
                                        <input type="date" name="reporting_date" class="form-control" 
                                               value="<?= htmlspecialchars($asset['reporting_date']) ?>" required>
                                        <small class="text-muted">Date for depreciation calculation</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Disposal Information Card -->
                            <div class="card mb-4 border-warning">
                                <div class="card-header bg-warning bg-opacity-25">
                                    <h5 class="mb-0"><i class="fas fa-trash-alt me-2"></i>Disposal Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Disposal Date</label>
                                        <input type="date" name="disposal_date" class="form-control" 
                                               value="<?= htmlspecialchars($asset['disposal_date'] ?? '') ?>">
                                        <small class="text-muted">Leave empty if asset is still active</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Disposal Value </label>
                                        <input type="text" name="disposal_value" class="form-control money-input" 
                                               value="<?= !empty($asset['disposal_value']) ? number_format($asset['disposal_value'], 2) : '' ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Disposal Reason</label>
                                        <select name="disposal_reason" class="form-select">
                                            <option value="">— Select —</option>
                                            <option value="SOLD" <?= ($asset['disposal_reason'] ?? '') == 'SOLD' ? 'selected' : '' ?>>Sold</option>
                                            <option value="SCRAPPED" <?= ($asset['disposal_reason'] ?? '') == 'SCRAPPED' ? 'selected' : '' ?>>Scrapped</option>
                                            <option value="LOST" <?= ($asset['disposal_reason'] ?? '') == 'LOST' ? 'selected' : '' ?>>Lost</option>
                                            <option value="DAMAGED" <?= ($asset['disposal_reason'] ?? '') == 'DAMAGED' ? 'selected' : '' ?>>Damaged</option>
                                            <option value="OBSOLETE" <?= ($asset['disposal_reason'] ?? '') == 'OBSOLETE' ? 'selected' : '' ?>>Obsolete</option>
                                            <option value="OTHER" <?= ($asset['disposal_reason'] ?? '') == 'OTHER' ? 'selected' : '' ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Buttons -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center">
                                    <button type="submit" class="btn btn-primary px-5">
                                        <i class="fas fa-save me-2"></i>Update Asset
                                    </button>
                                    <a href="?page=assets" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                    <button type="button" class="btn btn-outline-info ms-2" onclick="recalculateDepreciation()">
                                        <i class="fas fa-calculator me-2"></i>Recalculate Depreciation
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- DEPRECIATION TAB -->
            <div class="tab-pane fade" id="depreciation" role="tabpanel">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card depreciation-card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Depreciation Calculation</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($dep_preview): 
                                    $total_value = $asset['acquisition_value'] + $asset['additions'];
                                    $book_value = $total_value - $dep_preview['accumulated_depreciation'];
                                ?>
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <table class="table table-bordered">
                                            <tbody>
                                                <tr>
                                                    <th>Total Asset Value</th>
                                                    <td class="text-end fw-bold"><?= number_format($total_value, 2) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Annual Depreciation</th>
                                                    <td class="text-end"><?= number_format($total_value * ($asset['depreciation_rate'] / 100), 2) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Monthly Depreciation</th>
                                                    <td class="text-end"> <?= number_format($dep_preview['monthly_depreciation'], 2) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Daily Depreciation</th>
                                                    <td class="text-end"> <?= number_format($dep_preview['daily_depreciation'], 2) ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-bordered">
                                            <tbody>
                                                <tr>
                                                    <th>Years in Service</th>
                                                    <td class="text-end"><?= floor($dep_preview['total_months'] / 12) ?> years, <?= $dep_preview['total_months'] % 12 ?> months</td>
                                                </tr>
                                                <tr>
                                                    <th>Accumulated Depreciation</th>
                                                    <td class="text-end text-danger fw-bold"> <?= number_format($dep_preview['accumulated_depreciation'], 2) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Remaining Lifespan</th>
                                                    <td class="text-end"><?= max(0, $asset['lifespan_years'] - floor($dep_preview['total_months'] / 12)) ?> years</td>
                                                </tr>
                                                <tr>
                                                    <th>Current Book Value</th>
                                                    <td class="text-end text-success fw-bold"> <?= number_format($book_value, 2) ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Depreciation Schedule Preview -->
                                <div class="mb-4">
                                    <h6 class="mb-3"><i class="fas fa-calendar-alt me-2"></i>Depreciation Schedule (Next 12 Months)</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Month</th>
                                                    <th>Monthly Dep</th>
                                                    <th>Cumulative Dep</th>
                                                    <th>Book Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $cumulative = $dep_preview['accumulated_depreciation'];
                                                $current_date = new DateTime($asset['reporting_date']);
                                                for ($i = 1; $i <= 12; $i++):
                                                    $cumulative += $dep_preview['monthly_depreciation'];
                                                    $cumulative = min($cumulative, $total_value);
                                                    $book_val = max(0, $total_value - $cumulative);
                                                    $current_date->modify('+1 month');
                                                ?>
                                                <tr>
                                                    <td><?= $current_date->format('M Y') ?></td>
                                                    <td> <?= number_format($dep_preview['monthly_depreciation'], 2) ?></td>
                                                    <td> <?= number_format($cumulative, 2) ?></td>
                                                    <td> <?= number_format($book_val, 2) ?></td>
                                                </tr>
                                                <?php endfor; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Depreciation Notes</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <small>Depreciation is calculated from acquisition date to reporting date</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <small>Monthly depreciation = (Total Value × Rate%) ÷ 12</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <small>Accumulated depreciation cannot exceed total asset value</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <small>Book Value = Total Value - Accumulated Depreciation</small>
                                    </li>
                                    <li>
                                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                        <small>Changing acquisition date/value or rate will recalculate depreciation</small>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <button class="btn btn-outline-primary w-100 mb-2" onclick="recalculateDepreciation()">
                                    <i class="fas fa-redo me-2"></i>Recalculate Now
                                </button>
                                <a href="?page=assets&delete=<?= $asset_id ?>" 
                                   class="btn btn-outline-danger w-100"
                                   onclick="return confirm('Permanently delete <?= htmlspecialchars($asset['asset_number']) ?>? This cannot be undone!')">
                                    <i class="fas fa-trash me-2"></i>Delete Asset
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- HISTORY TAB -->
            <div class="tab-pane fade" id="history" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Transaction History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($history)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm history-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Voucher</th>
                                        <th>Account</th>
                                        <th>Description</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history as $entry): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($entry['transaction_date'])) ?></td>
                                        <td><code><?= htmlspecialchars($entry['voucher_number']) ?></code></td>
                                        <td><?= htmlspecialchars($entry['account_code']) ?> - <?= htmlspecialchars($entry['account_name']) ?></td>
                                        <td><?= htmlspecialchars($entry['narration']) ?></td>
                                        <td class="text-end"><?= $entry['debit_amount'] > 0 ? ' ' . number_format($entry['debit_amount'], 2) : '-' ?></td>
                                        <td class="text-end"><?= $entry['credit_amount'] > 0 ? ' ' . number_format($entry['credit_amount'], 2) : '-' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>No transaction history found for this asset</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
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

// Form validation
document.getElementById('updateAssetForm')?.addEventListener('submit', function(e) {
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

// Recalculate depreciation function
function recalculateDepreciation() {
    const acqDate = document.querySelector('[name="acquisition_date"]').value;
    const acqValue = parseFloat(document.querySelector('[name="acquisition_value"]').value.replace(/[^\d.]/g, ''));
    const additions = parseFloat(document.querySelector('[name="additions"]').value.replace(/[^\d.]/g, '') || 0);
    const lifespan = parseInt(document.querySelector('[name="lifespan_years"]').value);
    const depRate = parseFloat(document.querySelector('[name="depreciation_rate"]').value);
    const reportDate = document.querySelector('[name="reporting_date"]').value;
    
    if (!acqDate || acqValue <= 0 || lifespan < 1 || depRate <= 0) {
        alert('Please fill all required fields with valid values first');
        return;
    }
    
    // Show confirmation
    if (confirm('Recalculate depreciation based on current values? This will update the depreciation fields.')) {
        // In a real implementation, you would make an AJAX call to recalculate
        // For now, we'll just submit the form
        document.getElementById('updateAssetForm').submit();
    }
}

// Auto-dismiss alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>
</body>
</html>

<?php $conn->close(); ?>
