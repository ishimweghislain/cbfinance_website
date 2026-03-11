<?php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Developer') {
    die("Access denied. Developer privileges required.");
}

$conn = getConnection();
require_once 'includes/activity_logger.php';

// Handle user actions (create, edit, delete, toggle active)
$action = $_POST['action'] ?? '';
$error = '';
$success = '';

if ($action) {
    if ($action === 'create' || $action === 'edit') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($action === 'create') {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, full_name, email, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $username, $hashed, $role, $full_name, $email, $is_active);
            if ($stmt->execute()) {
                $success = "User created successfully.";
                logActivity($conn, 'create', 'user', $conn->insert_id, "Developer {$_SESSION['username']} created user: $username");
            } else {
                $error = "Error: " . $stmt->error;
            }
        } else {
            $user_id = (int)$_POST['user_id'];
            if ($password) {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET username=?, password=?, role=?, full_name=?, email=?, is_active=? WHERE user_id=?");
                $stmt->bind_param("sssssii", $username, $hashed, $role, $full_name, $email, $is_active, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, role=?, full_name=?, email=?, is_active=? WHERE user_id=?");
                $stmt->bind_param("ssssii", $username, $role, $full_name, $email, $is_active, $user_id);
            }
            if ($stmt->execute()) {
                $success = "User updated successfully.";
                logActivity($conn, 'update', 'user', $user_id, "Developer {$_SESSION['username']} updated user: $username");
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
    } elseif ($action === 'delete') {
        $user_id = (int)$_POST['user_id'];
        $username = $_POST['username'];
        
        // Safety: check if target is a developer
        $chk = $conn->query("SELECT role FROM users WHERE user_id = $user_id");
        $target_role = ($chk && $row = $chk->fetch_assoc()) ? $row['role'] : '';

        if ($target_role !== 'Developer') {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $success = "User deleted successfully.";
                logActivity($conn, 'delete', 'user', $user_id, "Developer {$_SESSION['username']} deleted user: $username");
            } else {
                $error = "Error: " . $stmt->error;
            }
        } else {
            $error = "For system safety, Developer accounts cannot be deleted here.";
        }
    }
}

$users = $conn->query("SELECT * FROM users ORDER BY role DESC, username ASC");
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-2 text-gray-800"><i class="bi bi-people me-2"></i>User Management</h1>
            <p class="text-muted small mb-4">Manage system users, roles, and access.</p>
            
            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">System Users</h6>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal" onclick="setUserModal('create')">
                        <i class="bi bi-plus-lg me-1"></i> Add New User
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="px-3">Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th class="text-center">Active</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $users->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-3">
                                        <span class="fw-bold"><?= htmlspecialchars($row['username']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td><span class="badge bg-secondary"><?= strtoupper($row['role']) ?></span></td>
                                    <td class="text-center">
                                        <i class="bi bi-circle-fill <?= $row['is_active'] ? 'text-success' : 'text-danger' ?>" style="font-size:0.6rem;"></i>
                                        <small class="ms-1"><?= $row['is_active'] ? 'Yes' : 'No' ?></small>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-outline-primary btn-sm me-1" onclick='setUserModal("edit", <?= json_encode($row) ?>)' data-bs-toggle="modal" data-bs-target="#userModal">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                            <input type="hidden" name="username" value="<?= $row['username'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" <?= ($row['role'] === 'Developer') ? 'disabled title="Cannot delete developers"' : '' ?>>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="userForm" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="modalAction" value="create">
                <input type="hidden" name="user_id" id="modalUserId">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Username</label>
                        <input type="text" name="username" id="modalUsername" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Role</label>
                        <select name="role" id="modalRole" class="form-select" required>
                            <option value="Director">Director</option>
                            <option value="MD">MD</option>
                            <option value="Accountant">Accountant</option>
                            <option value="Secretary">Secretary</option>
                            <option value="Developer">Developer</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Full Name</label>
                        <input type="text" name="full_name" id="modalFullName" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Email</label>
                        <input type="email" name="email" id="modalEmail" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Password <small class="text-muted">(leave blank to keep current if editing)</small></label>
                        <input type="password" name="password" id="modalPassword" class="form-control">
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="modalIsActive" checked>
                            <label class="form-check-label small fw-bold" for="modalIsActive">Account Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save User</button>
            </div>
        </form>
    </div>
</div>

<script>
function setUserModal(action, data = null) {
    const title = document.getElementById('userModalTitle');
    const formAction = document.getElementById('modalAction');
    const userIdInput = document.getElementById('modalUserId');
    const usernameInput = document.getElementById('modalUsername');
    const fullNameInput = document.getElementById('modalFullName');
    const emailInput = document.getElementById('modalEmail');
    const roleInput = document.getElementById('modalRole');
    const isActiveInput = document.getElementById('modalIsActive');
    const passwordInput = document.getElementById('modalPassword');

    if (action === 'create') {
        title.innerText = 'Add New User';
        formAction.value = 'create';
        userIdInput.value = '';
        usernameInput.value = '';
        fullNameInput.value = '';
        emailInput.value = '';
        roleInput.value = 'Secretary';
        isActiveInput.checked = true;
        passwordInput.required = true;
    } else {
        title.innerText = 'Edit User: ' + data.username;
        formAction.value = 'edit';
        userIdInput.value = data.user_id;
        usernameInput.value = data.username;
        fullNameInput.value = data.full_name;
        emailInput.value = data.email;
        roleInput.value = data.role;
        isActiveInput.checked = parseInt(data.is_active) === 1;
        passwordInput.required = false;
        passwordInput.value = '';
    }
}
</script>
