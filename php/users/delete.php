<?php
// php/users/delete.php
require_once __DIR__ . '/../auth/middleware.php';

// Only admins can delete users
requireAdmin();

if (!isset($_GET['id'])) {
    redirect('php/users/list.php');
}

$userId = $_GET['id'];

// Prevent deleting self
if ($userId == $_SESSION['user_id']) {
    $_SESSION['error'] = 'You cannot delete your own account!';
    redirect('php/users/list.php');
}

$db = getDB();
if ($db) {
    try {
        // Get user info before deletion
        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Delete user
            $delStmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $delStmt->execute([$userId]);
            
            $_SESSION['success'] = 'User deleted successfully, username was: ' . htmlspecialchars($user['username']);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error deleting user: ' . $e->getMessage();
    }
}

redirect('php/users/list.php');
?>
