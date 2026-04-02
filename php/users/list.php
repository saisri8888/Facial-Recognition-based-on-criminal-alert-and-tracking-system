<?php
// php/users/list.php
require_once __DIR__ . '/../auth/middleware.php';

// Only admins can manage users
requireAdmin();

$pageTitle = 'Manage System Users';

$db = getDB();
$users = [];

if ($db) {
    try {
        $stmt = $db->prepare("SELECT id, username, full_name, email, role, is_active, created_at, last_login FROM users ORDER BY created_at DESC");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error fetching users: ' . $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-users"></i> System Users</h1>
    <p class="text-muted">Manage user accounts and permissions</p>
</div>

<div class="container-fluid">
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="usersTable">
                    <thead class="table-dark">
                        <tr>
                            <th width="5%">#</th>
                            <th width="20%">Full Name</th>
                            <th width="15%">Username</th>
                            <th width="20%">Email</th>
                            <th width="12%">Role</th>
                            <th width="12%">Status</th>
                            <th width="15%">Last Login</th>
                            <th width="15%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fas fa-user-slash fa-2x mb-2"></i>
                                    <p>No users found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><strong><?= $user['id'] ?></strong></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-2">
                                                <i class="fas fa-user-circle"></i>
                                            </div>
                                            <span><?= htmlspecialchars($user['full_name']) ?></span>
                                        </div>
                                    </td>
                                    <td><code><?= htmlspecialchars($user['username']) ?></code></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <?php
                                        $roleBadges = [
                                            'admin' => 'danger',
                                            'investigator' => 'warning',
                                            'officer' => 'info',
                                            'viewer' => 'secondary'
                                        ];
                                        $badgeColor = $roleBadges[$user['role']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badgeColor ?>"><?= ucfirst($user['role']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColor = $user['is_active'] ? 'success' : 'secondary';
                                        $statusText = $user['is_active'] ? 'Active' : 'Inactive';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>"><?= $statusText ?></span>
                                    </td>
                                    <td>
                                        <?php if ($user['last_login']): ?>
                                            <small><?= date('M d, Y H:i', strtotime($user['last_login'])) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Never</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= BASE_URL ?>php/users/edit.php?id=<?= $user['id'] ?>" 
                                               class="btn btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" title="Delete"
                                                    onclick="if(confirm('Delete this user?')) window.location='<?= BASE_URL ?>php/users/delete.php?id=<?= $user['id'] ?>';">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        "paging": true,
        "pageLength": 10,
        "searching": true,
        "ordering": true,
        "info": true,
        "responsive": true,
        "language": {
            "searchPlaceholder": "Search users..."
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
