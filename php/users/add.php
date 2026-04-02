<?php
// php/users/add.php
require_once __DIR__ . '/../auth/middleware.php';

// Only admins can add users
requireAdmin();

$pageTitle = 'Add System User';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token validation failed. Please try again.';
    } else {
        $db = getDB();
        
        if ($db) {
            try {
                // Check if user already exists
                $existingUser = $db->prepare("SELECT id FROM users WHERE username = ?");
                $existingUser->execute([sanitize($_POST['username'])]);
                
                if ($existingUser->rowCount() > 0) {
                    $error = 'Username already exists. Please choose a different username.';
                } else {
                    // Hash the password
                    $hashedPassword = password_hash($_POST['password'], PASSWORD_BCRYPT);
                    
                    $stmt = $db->prepare("INSERT INTO users 
                        (username, full_name, email, password, role, is_active)
                        VALUES (?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        sanitize($_POST['username']),
                        sanitize($_POST['full_name']),
                        sanitize($_POST['email']),
                        $hashedPassword,
                        $_POST['role'] ?? 'officer',
                        ($_POST['is_active'] ?? 1) ? 1 : 0
                    ]);
                    
                    logAction('add_user', 'users', "Added user: {$_POST['username']} ({$_POST['full_name']})");
                    
                    $success = "User account created successfully! Username: " . htmlspecialchars($_POST['username']);
                }
            } catch (Exception $e) {
                $error = 'Error creating user: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-user-plus"></i> Add System User</h1>
    <p class="text-muted">Create a new user account for system access</p>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="addUserForm">
                        <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required 
                                   pattern="[a-zA-Z0-9_]{3,}" title="3+ characters (letters, numbers, underscore)">
                            <small class="form-text text-muted">3+ characters, alphanumeric and underscore only</small>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required 
                                   minlength="6" title="Minimum 6 characters">
                            <small class="form-text text-muted">Minimum 6 characters</small>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <div class="invalid-feedback" id="passwordError">Passwords do not match</div>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">User Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">-- Select Role --</option>
                                <option value="admin">Administrator</option>
                                <option value="investigator">Investigator</option>
                                <option value="officer">Police Officer</option>
                                <option value="viewer">Viewer (Read Only)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="is_active" class="form-label">Account Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="is_active" name="is_active" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-check"></i> Create User Account
                            </button>
                            <a href="<?= BASE_URL ?>php/users/list.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-arrow-left"></i> Back to Users
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm bg-light">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-info-circle"></i> User Roles</h5>
                    
                    <div class="role-info">
                        <h6 class="badge bg-danger mb-2">Administrator</h6>
                        <p class="small">Full system access. Can manage users, criminals, and view all alerts.</p>
                    </div>

                    <div class="role-info">
                        <h6 class="badge bg-warning mb-2">Investigator</h6>
                        <p class="small">Can add/edit criminals, run detection, and manage alerts.</p>
                    </div>

                    <div class="role-info">
                        <h6 class="badge bg-info mb-2">Police Officer</h6>
                        <p class="small">Can run detection and view criminal records, limited edit rights.</p>
                    </div>

                    <div class="role-info">
                        <h6 class="badge bg-secondary mb-2">Viewer</h6>
                        <p class="small">Read-only access. Can view records and alerts only.</p>
                    </div>

                    <hr>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('addUserForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        document.getElementById('passwordError').style.display = 'block';
        document.getElementById('confirm_password').classList.add('is-invalid');
    } else {
        document.getElementById('confirm_password').classList.remove('is-invalid');
        document.getElementById('passwordError').style.display = 'none';
    }
});

// Real-time password match validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password === confirmPassword && confirmPassword !== '') {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    } else if (confirmPassword !== '') {
        this.classList.add('is-invalid');
        this.classList.remove('is-valid');
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
