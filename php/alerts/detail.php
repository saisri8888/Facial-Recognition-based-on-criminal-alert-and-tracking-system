<?php
require_once __DIR__ . '/../auth/middleware.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    redirect('php/alerts/list.php');
}

$db = getDB();

$stmt = $db->prepare("
    SELECT da.*, 
           CONCAT(c.first_name, ' ', c.last_name) as criminal_name,
           c.criminal_code, c.crime_type, c.danger_level, c.status as criminal_status,
           c.id as criminal_id,
           cp.photo_path,
           u.full_name as detected_by_name,
           u2.full_name as acknowledged_by_name,
           COALESCE(da.detection_location, 'Unknown Location') as location
    FROM detection_alerts da
    JOIN criminals c ON da.criminal_id = c.id
    LEFT JOIN criminal_photos cp ON c.id = cp.criminal_id AND cp.is_primary = 1
    JOIN users u ON da.detected_by_user = u.id
    LEFT JOIN users u2 ON da.acknowledged_by = u2.id
    WHERE da.id = ?
");
$stmt->execute([$id]);
$alert = $stmt->fetch();

if (!$alert) {
    $_SESSION['error'] = 'Alert not found.';
    redirect('php/alerts/list.php');
}

$pageTitle = 'Alert #' . $id;
include __DIR__ . '/../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-bell me-2 text-danger"></i>Alert #<?= $id ?></h2>
        <a href="<?= BASE_URL ?>php/alerts/list.php" class="btn btn-outline-info">
            <i class="fas fa-arrow-left me-1"></i> Back to Alerts
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <!-- Criminal Info -->
            <div class="card dark-card">
                <div class="card-body text-center">
                    <img src="<?= BASE_URL . ($alert['photo_path'] ?? 'assets/images/no-photo.png') ?>" 
                         class="rounded-circle mb-3" width="150" height="150" style="object-fit:cover;border:4px solid #ff4757;">
                    <h4 class="text-white"><?= sanitize($alert['criminal_name']) ?></h4>
                    <code><?= $alert['criminal_code'] ?></code>
                    <div class="mt-2">
                        <?= getDangerBadge($alert['danger_level']) ?>
                        <?= getStatusBadge($alert['criminal_status']) ?>
                    </div>
                    <p class="mt-2 text-muted"><?= sanitize($alert['crime_type']) ?></p>
                    <a href="<?= BASE_URL ?>php/criminals/detail.php?id=<?= $alert['criminal_id'] ?>" 
                       class="btn btn-outline-info btn-sm mt-2">
                        <i class="fas fa-user me-1"></i> View Full Profile
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <!-- Alert Details -->
            <div class="card dark-card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>Detection Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Confidence Score</label>
                            <div class="progress mt-1" style="height:30px;background:rgba(255,255,255,0.1);">
                                <div class="progress-bar <?= $alert['confidence_score'] > 80 ? 'bg-danger' : 'bg-warning' ?>" 
                                     style="width:<?= $alert['confidence_score'] ?>%;font-size:16px;">
                                    <?= number_format($alert['confidence_score'], 1) ?>%
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Alert Status</label>
                            <p class="mt-1"><?= getAlertStatusBadge($alert['alert_status']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Detected By</label>
                            <p class="text-white mb-0"><?= sanitize($alert['detected_by_name']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Camera Source</label>
                            <p class="text-white mb-0"><?= $alert['camera_source'] ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small"><i class="fas fa-map-marker-alt" style="color:#17a2b8; margin-right:5px;"></i>Detection Location</label>
                            <p class="text-white mb-0" style="font-weight:600;font-size:16px;color:#17a2b8;"><?= sanitize($alert['location']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Detected At</label>
                            <p class="text-white mb-0"><?= formatDateTime($alert['detected_at']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Acknowledged By</label>
                            <p class="text-white mb-0"><?= sanitize($alert['acknowledged_by_name'] ?? 'Not yet') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detection Screenshot -->
            <?php if ($alert['detection_screenshot']): ?>
            <div class="card dark-card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-camera me-2"></i>Detection Screenshot</h5>
                </div>
                <div class="card-body text-center">
                    <img src="<?= BASE_URL . $alert['detection_screenshot'] ?>" 
                         class="img-fluid rounded" style="max-height:500px;">
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes -->
            <?php if ($alert['notes']): ?>
            <div class="card dark-card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-sticky-note me-2"></i>Notes</h5>
                </div>
                <div class="card-body">
                    <p class="text-white"><?= nl2br(sanitize($alert['notes'])) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>