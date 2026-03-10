<?php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Developer') {
    die("Access denied. Developer privileges required.");
}

$conn = getConnection();

// Handle Clear Logs if requested
if (isset($_POST['action']) && $_POST['action'] === 'clear_logs' && $_SESSION['username'] === 'developerghis') {
    $conn->query("TRUNCATE TABLE activity_logs");
    echo "<script>alert('Activity logs cleared.'); window.location.href='index.php?page=activity_logs';</script>";
}

// Pagination
$limit = 50;
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

$total_res = $conn->query("SELECT COUNT(*) as cnt FROM activity_logs");
$total_rows = $total_res->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $limit);

$logs = $conn->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-clock-history me-2"></i>System Activity Logs</h1>
            <p class="text-muted small mb-0">Track all sensitive actions in the system</p>
        </div>
        <?php if ($_SESSION['username'] === 'developerghis'): ?>
        <form method="POST" onsubmit="return confirm('Are you sure you want to clear all logs?');">
            <input type="hidden" name="action" value="clear_logs">
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash me-1"></i> Clear All Logs
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-3">Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $logs->fetch_assoc()): 
                            $badge = 'secondary';
                            switch($row['action_type']) {
                                case 'login': $badge = 'success'; break;
                                case 'logout': $badge = 'info'; break;
                                case 'delete': $badge = 'danger'; break;
                                case 'update': $badge = 'primary'; break;
                                case 'create': $badge = 'success'; break;
                                case 'approve': $badge = 'success'; break;
                                case 'reject': $badge = 'danger'; break;
                            }
                        ?>
                        <tr>
                            <td class="px-3 small text-muted">
                                <?= date('Y-m-d H:i:s', strtotime($row['created_at'])) ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-light rounded-circle me-2 d-flex align-items-center justify-content-center" style="width:30px;height:30px;">
                                        <i class="bi bi-person h6 mb-0"></i>
                                    </div>
                                    <span class="fw-bold"><?= htmlspecialchars($row['username']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?= $badge ?>"><?= strtoupper($row['action_type']) ?></span>
                            </td>
                            <td class="small">
                                <?php if ($row['entity_type']): ?>
                                    <span class="text-primary fw-bold text-uppercase"><?= $row['entity_type'] ?></span>
                                    <span class="text-muted ms-1">ID: <?= $row['entity_id'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-dark small"><?= htmlspecialchars($row['description']) ?></div>
                            </td>
                            <td class="small text-muted">
                                <code><?= $row['ip_address'] ?></code>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white py-3">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php for ($i=1; $i<=$total_pages; $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=activity_logs&p=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.table thead th {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
}
.badge {
    font-weight: 600;
    padding: 0.4em 0.8em;
}
</style>
