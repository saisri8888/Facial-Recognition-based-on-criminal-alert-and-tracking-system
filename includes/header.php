<?php
// includes/header.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-shield-halved"></i>
            <span>CrimeDetect</span>
        </div>
        <ul class="sidebar-menu">
            <li><a href="<?= BASE_URL ?>php/dashboard/index.php" class="<?= strpos($_SERVER['PHP_SELF'],'dashboard')!==false?'active':'' ?>">
                <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
            </a></li>
            <li><a href="<?= BASE_URL ?>php/detection/live.php" class="<?= strpos($_SERVER['PHP_SELF'],'detection')!==false?'active':'' ?>">
                <i class="fas fa-video"></i><span>Live Detection</span>
                <?php if ($newAlerts ?? 0): ?><span class="menu-badge"><?= $newAlerts ?></span><?php endif; ?>
            </a></li>
            <li><a href="<?= BASE_URL ?>php/detection/static.php" class="<?= strpos($_SERVER['PHP_SELF'],'static')!==false?'active':'' ?>">
                <i class="fas fa-images"></i><span>Static Detection</span>
            </a></li>
            <li><a href="<?= BASE_URL ?>php/criminals/list.php" class="<?= strpos($_SERVER['PHP_SELF'],'criminal')!==false?'active':'' ?>">
                <i class="fas fa-user-secret"></i><span>Criminal Records</span>
            </a></li>
            <?php if (isInvestigator()): ?>
            <li><a href="<?= BASE_URL ?>php/criminals/add.php">
                <i class="fas fa-user-plus"></i><span>Add Criminal</span>
            </a></li>
            <?php endif; ?>
            <li><a href="<?= BASE_URL ?>php/alerts/list.php" class="<?= strpos($_SERVER['PHP_SELF'],'alert')!==false?'active':'' ?>">
                <i class="fas fa-bell"></i><span>Alerts</span>
            </a></li>
            <?php if (isInvestigator()): ?>
            <li><a href="<?= BASE_URL ?>php/reports/index.php" class="<?= strpos($_SERVER['PHP_SELF'],'reports')!==false?'active':'' ?>">
                <i class="fas fa-chart-line"></i><span>Reports</span>
            </a></li>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
            <li><a href="<?= BASE_URL ?>php/users/list.php" class="<?= strpos($_SERVER['PHP_SELF'],'users')!==false?'active':'' ?>">
                <i class="fas fa-users"></i><span>System Users</span>
            </a></li>
            <li><a href="<?= BASE_URL ?>php/users/add.php">
                <i class="fas fa-user-plus"></i><span>Add User</span>
            </a></li>
            <?php endif; ?>
        </ul>
        <div class="sidebar-footer">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <div>
                    <strong><?= $_SESSION['full_name'] ?? 'User' ?></strong>
                    <small><?= ucfirst($_SESSION['role'] ?? 'officer') ?></small>
                </div>
            </div>
            <a href="<?= BASE_URL ?>php/auth/logout.php" class="btn btn-sm btn-outline-danger w-100 mt-2">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Main Content Wrapper -->
    <div class="content-wrapper">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <button class="btn btn-link text-white" onclick="document.getElementById('sidebar').classList.toggle('collapsed')">
                <i class="fas fa-bars"></i>
            </button>
            <div class="d-flex align-items-center gap-3">
                <div class="alert-indicator" id="alertIndicator" style="display:none;">
                    <i class="fas fa-exclamation-triangle text-danger blink"></i>
                    <span class="badge bg-danger" id="alertCount">0</span>
                </div>
                <span class="text-white-50"><?= date('M d, Y h:i A') ?></span>
            </div>
        </nav>