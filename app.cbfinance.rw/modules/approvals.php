<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/approval_helper.php';

$conn = getConnection();
$user_role = $_SESSION['role'] ?? '';
$username  = $_SESSION['username'] ?? 'unknown';

$success_message = '';
$error_message   = '';

// ── Handle Approve / Reject ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && canApprove()) {
    $approval_id  = (int)($_POST['approval_id'] ?? 0);
    $action       = $_POST['approval_action'] ?? ''; // 'approve' or 'reject'
    $review_notes = trim($_POST['review_notes'] ?? '');

    if ($approval_id > 0 && in_array($action, ['approve', 'reject'])) {
        // Fetch the pending record
        $fetch = $conn->prepare("SELECT * FROM pending_approvals WHERE approval_id = ? AND status = 'pending'");
        $fetch->bind_param('i', $approval_id);
        $fetch->execute();
        $approval = $fetch->get_result()->fetch_assoc();
        $fetch->close();

        if ($approval) {
            $conn->begin_transaction();
            try {
                if ($action === 'approve') {
                    // Execute the actual database operation
                    executeApproval($conn, $approval);
                    $new_status = 'approved';
                    $success_message = "✅ Action approved and executed successfully!";
                } else {
                    $new_status = 'rejected';
                    $success_message = "❌ Action rejected.";
                }

                // Update the approval record
                $upd = $conn->prepare("UPDATE pending_approvals SET status=?, reviewed_by=?, reviewed_at=NOW(), review_notes=? WHERE approval_id=?");
                $upd->bind_param('sssi', $new_status, $username, $review_notes, $approval_id);
                $upd->execute();
                $upd->close();

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Operation failed: " . $e->getMessage();
            }
        } else {
            $error_message = "Approval not found or already processed.";
        }
    }
}

// ── Fetch pending approvals ────────────────────────────────────────────────────
$filter     = $_GET['filter'] ?? 'pending';
$valid_filters = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $valid_filters)) $filter = 'pending';

$where = $filter !== 'all' ? "WHERE status = '$filter'" : '';
$approvals = $conn->query("SELECT * FROM pending_approvals $where ORDER BY submitted_at DESC LIMIT 100");

// Count badges
$counts = [];
foreach (['pending', 'approved', 'rejected'] as $s) {
    $r = $conn->query("SELECT COUNT(*) as cnt FROM pending_approvals WHERE status = '$s'");
    $counts[$s] = $r ? (int)$r->fetch_assoc()['cnt'] : 0;
}
?>

<div class="container-fluid py-3">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 fw-bold text-primary mb-0">
                    <i class="bi bi-shield-check me-2"></i>Approval Center
                </h2>
                <p class="text-muted small mb-0">Review and approve or reject pending actions</p>
            </div>
            <?php if (!canApprove()): ?>
            <div class="alert alert-warning py-2 px-3 mb-0">
                <i class="bi bi-lock me-1"></i> View only — only Director or MD can approve actions.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= $success_message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error_message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-warning shadow-sm">
                <div class="card-body text-center py-2">
                    <h2 class="fw-bold text-warning mb-0"><?= $counts['pending'] ?></h2>
                    <small class="text-muted">Pending Review</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success shadow-sm">
                <div class="card-body text-center py-2">
                    <h2 class="fw-bold text-success mb-0"><?= $counts['approved'] ?></h2>
                    <small class="text-muted">Approved</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger shadow-sm">
                <div class="card-body text-center py-2">
                    <h2 class="fw-bold text-danger mb-0"><?= $counts['rejected'] ?></h2>
                    <small class="text-muted">Rejected</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-2">
            <ul class="nav nav-tabs card-header-tabs">
                <?php foreach (['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'all' => 'secondary'] as $f => $color): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === $f ? 'active' : '' ?>" href="?page=approvals&filter=<?= $f ?>">
                        <?= ucfirst($f) ?>
                        <?php if ($f !== 'all' && isset($counts[$f]) && $counts[$f] > 0): ?>
                            <span class="badge bg-<?= $color ?> ms-1"><?= $counts[$f] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Submitted By</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Reviewed By</th>
                            <?php if (canApprove() && $filter === 'pending'): ?>
                            <th class="text-center">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($approvals && $approvals->num_rows > 0):
                            while ($row = $approvals->fetch_assoc()):
                                $data = is_string($row['action_data']) ? json_decode($row['action_data'], true) : [];
                                if (!is_array($data)) $data = [];
                                $badge = 'secondary';
                                switch($row['status']) {
                                    case 'pending':  $badge = 'warning'; break;
                                    case 'approved': $badge = 'success'; break;
                                    case 'rejected': $badge = 'danger'; break;
                                }

                                $action_badge = '<span class="badge bg-secondary">'.strtoupper($row['action_type'] ?? '').'</span>';
                                switch($row['action_type']) {
                                    case 'add':    $action_badge = '<span class="badge bg-success">ADD</span>'; break;
                                    case 'edit':   $action_badge = '<span class="badge bg-primary">EDIT</span>'; break;
                                    case 'delete': $action_badge = '<span class="badge bg-danger">DELETE</span>'; break;
                                }
                        ?>
                        <tr>
                            <td><?= $row['approval_id'] ?></td>
                            <td><?= $action_badge ?> <span class="badge bg-light text-dark"><?= ucfirst($row['entity_type']) ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($row['description']) ?></strong>
                                <?php if ($row['review_notes']): ?>
                                    <br><small class="text-muted"><i class="bi bi-chat me-1"></i><?= htmlspecialchars($row['review_notes']) ?></small>
                                <?php endif; ?>
                                <br>
                                <button class="btn btn-link btn-sm p-0 text-info" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#details-<?= $row['approval_id'] ?>">
                                    <i class="bi bi-eye"></i> View data
                                </button>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($row['submitted_by']) ?></strong>
                                <br><small class="badge bg-secondary"><?= htmlspecialchars($row['submitted_by_role']) ?></small>
                            </td>
                            <td><small><?= date('d M Y H:i', strtotime($row['submitted_at'])) ?></small></td>
                            <td><span class="badge bg-<?= $badge ?>"><?= ucfirst($row['status']) ?></span></td>
                            <td>
                                <?php if ($row['reviewed_by']): ?>
                                    <small><?= htmlspecialchars($row['reviewed_by']) ?></small><br>
                                    <small class="text-muted"><?= date('d M H:i', strtotime($row['reviewed_at'])) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">—</small>
                                <?php endif; ?>
                            </td>

                            <?php if (canApprove() && $filter === 'pending'): ?>
                            <td class="text-center">
                                <button class="btn btn-success btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#reviewModal"
                                        data-approval-id="<?= $row['approval_id'] ?>"
                                        data-description="<?= htmlspecialchars($row['description']) ?>"
                                        data-action="approve">
                                    <i class="bi bi-check-lg"></i> Approve
                                </button>
                                <button class="btn btn-outline-danger btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#reviewModal"
                                        data-approval-id="<?= $row['approval_id'] ?>"
                                        data-description="<?= htmlspecialchars($row['description']) ?>"
                                        data-action="reject">
                                    <i class="bi bi-x-lg"></i> Reject
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <!-- Collapsible data row -->
                        <tr class="collapse" id="details-<?= $row['approval_id'] ?>">
                            <td colspan="8" class="bg-light">
                                <div class="p-2">
                                    <small class="text-muted fw-bold">Submitted Data Preview:</small>
                                    <div class="mt-1" style="max-height:200px;overflow-y:auto;">
                                        <?php
                                        $decoded_data = is_string($row['action_data']) ? json_decode($row['action_data'], true) : null;
                                        if (is_array($decoded_data) && count($decoded_data) > 0):
                                        ?>
                                        <table class="table table-sm table-bordered table-striped mb-0" style="font-size:11px;">
                                            <tbody>
                                            <?php foreach ($decoded_data as $k => $v): if (is_null($v) || $v === '') continue; ?>
                                                <tr>
                                                    <td class="fw-bold text-nowrap" style="width:160px"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $k))) ?></td>
                                                    <td><?= htmlspecialchars((string)$v) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <?php else: ?>
                                        <div class="text-muted small p-2">
                                            <i class="bi bi-info-circle me-1"></i>No detailed data available.
                                            <?php if (!empty($row['action_data'])): ?>
                                            <br><small class="text-secondary">Raw: <?= htmlspecialchars(substr($row['action_data'], 0, 200)) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile;
                        else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No <?= $filter ?> requests found.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<?php if (canApprove()): ?>
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reviewModalTitle">Review Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="approval_id"     id="modal_approval_id">
                    <input type="hidden" name="approval_action" id="modal_approval_action">
                    <p id="modal_description" class="fw-bold"></p>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea name="review_notes" class="form-control" rows="3" 
                                  placeholder="Add a comment about your decision..."></textarea>
                    </div>
                    <div id="modal_warning" class="alert alert-warning d-none">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        This will permanently execute the action in the database.
                    </div>
                    <div id="modal_reject_info" class="alert alert-danger d-none">
                        <i class="bi bi-x-circle me-1"></i>
                        This action will be rejected and will NOT be executed.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="modal_submit_btn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const reviewModal = document.getElementById('reviewModal');
    reviewModal.addEventListener('show.bs.modal', function (e) {
        const btn    = e.relatedTarget;
        const id     = btn.getAttribute('data-approval-id');
        const desc   = btn.getAttribute('data-description');
        const action = btn.getAttribute('data-action');

        document.getElementById('modal_approval_id').value     = id;
        document.getElementById('modal_approval_action').value = action;
        document.getElementById('modal_description').textContent = desc;

        const title  = document.getElementById('reviewModalTitle');
        const submit = document.getElementById('modal_submit_btn');
        const warn   = document.getElementById('modal_warning');
        const rej    = document.getElementById('modal_reject_info');

        if (action === 'approve') {
            title.textContent = '✅ Approve Action';
            submit.className  = 'btn btn-success';
            submit.textContent = 'Yes, Approve & Execute';
            warn.classList.remove('d-none');
            rej.classList.add('d-none');
        } else {
            title.textContent = '❌ Reject Action';
            submit.className  = 'btn btn-danger';
            submit.textContent = 'Yes, Reject';
            warn.classList.add('d-none');
            rej.classList.remove('d-none');
        }
    });
});
</script>
<?php endif; ?>

<?php if (isset($conn)) $conn->close(); ?>
