<?php
require_once __DIR__ . '/../auth/middleware.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    redirect('php/criminals/list.php');
}

$db = getDB();

// Get criminal details
$stmt = $db->prepare("
    SELECT c.*, u.full_name as added_by_name
    FROM criminals c
    LEFT JOIN users u ON c.added_by = u.id
    WHERE c.id = ? AND c.is_active = 1
");
$stmt->execute([$id]);
$criminal = $stmt->fetch();

if (!$criminal) {
    $_SESSION['error'] = 'Criminal record not found.';
    redirect('php/criminals/list.php');
}

// Get photos
$photos = $db->prepare("
    SELECT cp.*, 
           (SELECT COUNT(*) FROM face_encodings fe WHERE fe.photo_id = cp.id) as has_encoding
    FROM criminal_photos cp 
    WHERE cp.criminal_id = ? 
    ORDER BY cp.is_primary DESC, cp.uploaded_at ASC
");
$photos->execute([$id]);
$photos = $photos->fetchAll();

// Get detection alerts for this criminal
$alerts = $db->prepare("
    SELECT da.*, u.full_name as detected_by_name
    FROM detection_alerts da
    JOIN users u ON da.detected_by_user = u.id
    WHERE da.criminal_id = ?
    ORDER BY da.detected_at DESC
    LIMIT 20
");
$alerts->execute([$id]);
$alerts = $alerts->fetchAll();

// Stats
$totalAlerts = $db->prepare("SELECT COUNT(*) FROM detection_alerts WHERE criminal_id = ?");
$totalAlerts->execute([$id]);
$totalAlerts = $totalAlerts->fetchColumn();

$totalPhotos = count($photos);
$totalEncodings = $db->prepare("SELECT COUNT(*) FROM face_encodings WHERE criminal_id = ?");
$totalEncodings->execute([$id]);
$totalEncodings = $totalEncodings->fetchColumn();

$pageTitle = $criminal['first_name'] . ' ' . $criminal['last_name'] . ' - Details';

include __DIR__ . '/../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h2>
            <i class="fas fa-user-secret me-2"></i>
            Criminal Record: <?= sanitize($criminal['first_name'] . ' ' . $criminal['last_name']) ?>
        </h2>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>php/criminals/edit.php?id=<?= $id ?>" class="btn btn-warning">
                <i class="fas fa-edit me-1"></i> Edit
            </a>
            <a href="<?= BASE_URL ?>php/criminals/list.php" class="btn btn-outline-info">
                <i class="fas fa-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: Photo & Basic Info -->
        <div class="col-lg-4">
            <!-- Primary Photo -->
            <div class="card dark-card">
                <div class="card-body text-center">
                    <?php
                    $primaryPhoto = null;
                    foreach ($photos as $p) {
                        if ($p['is_primary']) { $primaryPhoto = $p; break; }
                    }
                    if (!$primaryPhoto && !empty($photos)) {
                        $primaryPhoto = $photos[0];
                    }
                    ?>
                    <img src="<?= BASE_URL . ($primaryPhoto['photo_path'] ?? 'assets/images/no-photo.png') ?>" 
                         class="rounded-circle mb-3" 
                         width="180" height="180" 
                         style="object-fit:cover;border:4px solid <?= $criminal['danger_level'] === 'critical' ? '#ff0000' : ($criminal['danger_level'] === 'high' ? '#ff4757' : '#00d4ff') ?>;">
                    
                    <h3 class="text-white mb-1">
                        <?= sanitize($criminal['first_name'] . ' ' . $criminal['last_name']) ?>
                    </h3>
                    
                    <?php if ($criminal['alias_name']): ?>
                        <p class="text-muted mb-2">aka "<?= sanitize($criminal['alias_name']) ?>"</p>
                    <?php endif; ?>
                    
                    <p class="mb-2"><code class="fs-6"><?= $criminal['criminal_code'] ?></code></p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <?= getDangerBadge($criminal['danger_level']) ?>
                        <?= getStatusBadge($criminal['status']) ?>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mt-1">
                <div class="col-4">
                    <div class="card dark-card text-center py-3">
                        <div class="text-info fs-4 fw-bold"><?= $totalPhotos ?></div>
                        <small class="text-muted">Photos</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card dark-card text-center py-3">
                        <div class="text-success fs-4 fw-bold"><?= $totalEncodings ?></div>
                        <small class="text-muted">Encodings</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card dark-card text-center py-3">
                        <div class="text-danger fs-4 fw-bold"><?= $totalAlerts ?></div>
                        <small class="text-muted">Alerts</small>
                    </div>
                </div>
            </div>

            <!-- All Photos -->
            <div class="card dark-card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-images me-2"></i>All Photos (<?= $totalPhotos ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($photos)): ?>
                        <p class="text-muted text-center">No photos uploaded</p>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($photos as $photo): ?>
                                <div class="col-4">
                                    <div class="position-relative">
                                        <img src="<?= BASE_URL . $photo['photo_path'] ?>" 
                                             class="img-fluid rounded cursor-pointer"
                                             style="height:90px;width:100%;object-fit:cover;border:2px solid <?= $photo['has_encoding'] ? 'rgba(0,255,0,0.5)' : 'rgba(255,255,0,0.5)' ?>;"
                                             onclick="viewPhoto('<?= BASE_URL . $photo['photo_path'] ?>')"
                                             title="Click to enlarge">
                                        
                                        <?php if ($photo['is_primary']): ?>
                                            <span class="position-absolute top-0 start-0 badge bg-info" style="font-size:9px;">Primary</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($photo['has_encoding']): ?>
                                            <span class="position-absolute top-0 end-0 badge bg-success" style="font-size:9px;">
                                                <i class="fas fa-check"></i>
                                            </span>
                                        <?php else: ?>
                                            <span class="position-absolute top-0 end-0 badge bg-warning" style="font-size:9px;">
                                                <i class="fas fa-times"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-2">
                            <small class="text-muted">
                                <span style="color:rgba(0,255,0,0.7);">■</span> Face encoded &nbsp;
                                <span style="color:rgba(255,255,0,0.7);">■</span> Not encoded
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Details -->
        <div class="col-lg-8">
            <!-- Personal Information -->
            <div class="card dark-card">
                <div class="card-header">
                    <h5><i class="fas fa-id-card me-2"></i>Personal Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="text-muted small">First Name</label>
                            <p class="text-white mb-0 fw-bold"><?= sanitize($criminal['first_name']) ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Last Name</label>
                            <p class="text-white mb-0 fw-bold"><?= sanitize($criminal['last_name']) ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Alias</label>
                            <p class="text-white mb-0"><?= sanitize($criminal['alias_name'] ?: 'N/A') ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Date of Birth</label>
                            <p class="text-white mb-0"><?= $criminal['date_of_birth'] ? formatDate($criminal['date_of_birth']) : 'N/A' ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Gender</label>
                            <p class="text-white mb-0"><?= ucfirst($criminal['gender']) ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Nationality</label>
                            <p class="text-white mb-0"><?= sanitize($criminal['nationality'] ?: 'N/A') ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">ID Number</label>
                            <p class="text-white mb-0"><?= sanitize($criminal['id_number'] ?: 'N/A') ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Phone</label>
                            <p class="text-white mb-0"><?= sanitize($criminal['phone'] ?: 'N/A') ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">City / State</label>
                            <p class="text-white mb-0">
                                <?= sanitize(($criminal['city'] ?: '') . ($criminal['state'] ? ', ' . $criminal['state'] : '')) ?: 'N/A' ?>
                            </p>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small">Address</label>
                            <p class="text-white mb-0"><?= sanitize($criminal['address'] ?: 'N/A') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Crime Details -->
            <div class="card dark-card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-gavel me-2"></i>Crime Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="text-muted small">Crime Type</label>
                            <p class="text-white mb-0 fw-bold"><?= sanitize($criminal['crime_type']) ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Danger Level</label>
                            <p class="mb-0"><?= getDangerBadge($criminal['danger_level']) ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Status</label>
                            <p class="mb-0"><?= getStatusBadge($criminal['status']) ?></p>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small">Crime Description</label>
                            <p class="text-white mb-0"><?= nl2br(sanitize($criminal['crime_description'] ?: 'N/A')) ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Arrest Count</label>
                            <p class="text-white mb-0"><?= $criminal['arrest_count'] ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Last Seen Location</label>
                            <p class="text-white mb-0"><?= sanitize($criminal['last_seen_location'] ?: 'N/A') ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Last Seen Date</label>
                            <p class="text-white mb-0"><?= $criminal['last_seen_date'] ? formatDate($criminal['last_seen_date']) : 'N/A' ?></p>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small">Notes</label>
                            <p class="text-white mb-0"><?= nl2br(sanitize($criminal['notes'] ?: 'N/A')) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Record Info -->
            <div class="card dark-card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>Record Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="text-muted small">Added By</label>
                            <p class="text-white mb-0"><?= sanitize($criminal['added_by_name'] ?? 'System') ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Created At</label>
                            <p class="text-white mb-0"><?= formatDateTime($criminal['created_at']) ?></p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Last Updated</label>
                            <p class="text-white mb-0"><?= formatDateTime($criminal['updated_at']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detection Alerts History -->
            <div class="card dark-card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-bell me-2 text-danger"></i>Detection History (<?= $totalAlerts ?>)</h5>
                    <?php if ($totalAlerts > 0): ?>
                        <a href="<?= BASE_URL ?>php/alerts/list.php?criminal_id=<?= $id ?>" class="btn btn-sm btn-outline-info">View All</a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($alerts)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-shield-alt fa-2x mb-2"></i>
                            <p>No detections recorded for this criminal</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Confidence</th>
                                        <th>Status</th>
                                        <th>Detected By</th>
                                        <th>Camera</th>
                                        <th>Date & Time</th>
                                        <th>Screenshot</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($alerts as $alert): ?>
                                    <tr>
                                        <td>
                                            <div class="progress" style="height:20px;min-width:80px;background:rgba(255,255,255,0.1);">
                                                <div class="progress-bar <?= $alert['confidence_score'] > 80 ? 'bg-danger' : ($alert['confidence_score'] > 60 ? 'bg-warning' : 'bg-info') ?>" 
                                                     style="width:<?= $alert['confidence_score'] ?>%">
                                                    <?= number_format($alert['confidence_score'], 1) ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= getAlertStatusBadge($alert['alert_status']) ?></td>
                                        <td><?= sanitize($alert['detected_by_name']) ?></td>
                                        <td><span class="badge bg-secondary"><?= $alert['camera_source'] ?></span></td>
                                        <td><?= formatDateTime($alert['detected_at']) ?></td>
                                        <td>
                                            <?php if ($alert['detection_screenshot']): ?>
                                                <button class="btn btn-sm btn-outline-info" 
                                                        onclick="viewPhoto('<?= BASE_URL . $alert['detection_screenshot'] ?>')">
                                                    <i class="fas fa-image"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Photo Viewer Modal -->
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background:rgba(0,0,0,0.9);border:1px solid rgba(255,255,255,0.1);">
            <div class="modal-header border-0">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="modalPhoto" src="" class="img-fluid rounded" style="max-height:80vh;">
            </div>
        </div>
    </div>
</div>

<script>
function viewPhoto(url) {
    document.getElementById('modalPhoto').src = url;
    new bootstrap.Modal(document.getElementById('photoModal')).show();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>