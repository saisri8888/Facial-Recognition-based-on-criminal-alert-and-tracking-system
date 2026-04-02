<?php
// php/dashboard/index.php
require_once __DIR__ . '/../auth/middleware.php';

$db = getDB();

// Get stats
$totalCriminals = $db->query("SELECT COUNT(*) FROM criminals WHERE is_active = 1")->fetchColumn();
$wantedCriminals = $db->query("SELECT COUNT(*) FROM criminals WHERE status = 'wanted' AND is_active = 1")->fetchColumn();
$totalAlerts = $db->query("SELECT COUNT(*) FROM detection_alerts")->fetchColumn();
$newAlerts = $db->query("SELECT COUNT(*) FROM detection_alerts WHERE alert_status = 'new'")->fetchColumn();
$totalPhotos = $db->query("SELECT COUNT(*) FROM criminal_photos")->fetchColumn();
$totalEncodings = $db->query("SELECT COUNT(*) FROM face_encodings")->fetchColumn();

// Recent alerts
$recentAlerts = $db->query("
    SELECT da.*, CONCAT(c.first_name, ' ', c.last_name) as criminal_name, 
           c.criminal_code, c.danger_level, c.crime_type,
           u.full_name as detected_by_name,
           COALESCE(da.detection_location, 'Unknown') as location
    FROM detection_alerts da
    JOIN criminals c ON da.criminal_id = c.id
    JOIN users u ON da.detected_by_user = u.id
    ORDER BY da.detected_at DESC LIMIT 10
")->fetchAll();

// Recent criminals
$recentCriminals = $db->query("
    SELECT c.*, cp.photo_path,
           (SELECT COUNT(*) FROM criminal_photos WHERE criminal_id = c.id) as photo_count,
           (SELECT COUNT(*) FROM face_encodings WHERE criminal_id = c.id) as encoding_count
    FROM criminals c
    LEFT JOIN criminal_photos cp ON c.id = cp.criminal_id AND cp.is_primary = 1
    WHERE c.is_active = 1
    ORDER BY c.created_at DESC LIMIT 6
")->fetchAll();

// Danger level distribution
$dangerStats = $db->query("
    SELECT danger_level, COUNT(*) as count FROM criminals WHERE is_active = 1 GROUP BY danger_level
")->fetchAll(PDO::FETCH_KEY_PAIR);

include __DIR__ . '/../../includes/header.php';
?>

<div class="main-content">
    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-2 col-md-4">
            <div class="stat-card stat-criminals">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?= $totalCriminals ?></div>
                <div class="stat-label">Total Records</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="stat-card stat-wanted">
                <div class="stat-icon"><i class="fas fa-crosshairs"></i></div>
                <div class="stat-value"><?= $wantedCriminals ?></div>
                <div class="stat-label">Wanted</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="stat-card stat-alerts">
                <div class="stat-icon"><i class="fas fa-bell"></i></div>
                <div class="stat-value"><?= $newAlerts ?></div>
                <div class="stat-label">New Alerts</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="stat-card stat-total-alerts">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-value"><?= $totalAlerts ?></div>
                <div class="stat-label">Total Detections</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="stat-card stat-photos">
                <div class="stat-icon"><i class="fas fa-camera"></i></div>
                <div class="stat-value"><?= $totalPhotos ?></div>
                <div class="stat-label">Photos</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="stat-card stat-encodings">
                <div class="stat-icon"><i class="fas fa-brain"></i></div>
                <div class="stat-value"><?= $totalEncodings ?></div>
                <div class="stat-label">Face Encodings</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Quick Actions -->
        <div class="col-xl-8">
            <div class="card dark-card">
                <div class="card-header">
                    <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="<?= BASE_URL ?>php/detection/live.php" class="action-btn action-detect">
                                <i class="fas fa-video"></i>
                                <span>Live Detection</span>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?= BASE_URL ?>php/criminals/add.php" class="action-btn action-add">
                                <i class="fas fa-user-plus"></i>
                                <span>Add Criminal</span>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="#" onclick="trainModel()" class="action-btn action-train">
                                <i class="fas fa-brain"></i>
                                <span>Train Model</span>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?= BASE_URL ?>php/alerts/list.php" class="action-btn action-alerts">
                                <i class="fas fa-shield-alt"></i>
                                <span>View Alerts</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Alerts -->
            <div class="card dark-card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-bell me-2"></i>Recent Alerts</h5>
                    <a href="<?= BASE_URL ?>php/alerts/list.php" class="btn btn-sm btn-outline-info">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Criminal</th>
                                    <th>Crime</th>
                                    <th>Location</th>
                                    <th>Confidence</th>
                                    <th>Danger</th>
                                    <th>Status</th>
                                    <th>Detected At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentAlerts)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">No alerts yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentAlerts as $alert): ?>
                                    <tr>
                                        <td>
                                            <strong><?= sanitize($alert['criminal_name']) ?></strong><br>
                                            <small class="text-muted"><?= $alert['criminal_code'] ?></small>
                                        </td>
                                        <td><?= sanitize($alert['crime_type']) ?></td>
                                        <td>
                                            <small style="color:#17a2b8;"><i class="fas fa-map-marker-alt"></i> <?= sanitize($alert['location']) ?></small>
                                        </td>
                                        <td>
                                            <div class="progress" style="height:20px;background:rgba(255,255,255,0.1);">
                                                <div class="progress-bar <?= $alert['confidence_score'] > 80 ? 'bg-danger' : 'bg-warning' ?>" 
                                                     style="width:<?= $alert['confidence_score'] ?>%">
                                                    <?= number_format($alert['confidence_score'], 1) ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= getDangerBadge($alert['danger_level']) ?></td>
                                        <td><?= getAlertStatusBadge($alert['alert_status']) ?></td>
                                        <td><?= formatDateTime($alert['detected_at']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Info -->
        <div class="col-xl-4">
            <!-- Model Status -->
            <div class="card dark-card">
                <div class="card-header"><h5><i class="fas fa-cog me-2"></i>Model Status</h5></div>
                <div class="card-body">
                    <div id="modelStatus">
                        <div class="text-center py-3">
                            <div class="spinner-border text-info spinner-border-sm"></div>
                            <span class="ms-2">Checking...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Criminals -->
            <div class="card dark-card mt-4">
                <div class="card-header"><h5><i class="fas fa-user-secret me-2"></i>Recent Records</h5></div>
                <div class="card-body">
                    <?php foreach ($recentCriminals as $criminal): ?>
                    <div class="criminal-mini-card mb-2">
                        <div class="d-flex align-items-center">
                            <img src="<?= BASE_URL . ($criminal['photo_path'] ?? 'assets/images/no-photo.png') ?>" 
                                 class="rounded-circle me-3" width="45" height="45" 
                                 style="object-fit:cover;border:2px solid rgba(0,212,255,0.3);">
                            <div class="flex-grow-1">
                                <strong class="text-white"><?= sanitize($criminal['first_name'] . ' ' . $criminal['last_name']) ?></strong>
                                <br><small class="text-muted"><?= $criminal['crime_type'] ?></small>
                            </div>
                            <?= getDangerBadge($criminal['danger_level']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Check model status
fetch('<?= PYTHON_API_URL ?>/api/status')
    .then(r => r.json())
    .then(data => {
        document.getElementById('modelStatus').innerHTML = `
            <div class="d-flex justify-content-between mb-2">
                <span>Status</span>
                <span class="badge ${data.model_loaded ? 'bg-success' : 'bg-danger'}">${data.model_loaded ? 'Loaded' : 'Not Loaded'}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>Encodings</span>
                <span class="text-info">${data.total_encodings || 0}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span>Criminals</span>
                <span class="text-info">${data.total_criminals || 0}</span>
            </div>
        `;
    })
    .catch(() => {
        document.getElementById('modelStatus').innerHTML = 
            '<div class="text-danger text-center"><i class="fas fa-exclamation-triangle"></i> Python service offline</div>';
    });

function trainModel() {
    if (!confirm('Start model training? This will re-encode all criminal photos.')) return;
    
    const btn = event.target.closest('.action-btn');
    btn.innerHTML = '<div class="spinner-border spinner-border-sm"></div><span>Training...</span>';
    btn.style.pointerEvents = 'none';
    
    // Get CSRF token from session or page
    fetch('<?= BASE_URL ?>php/api/bridge.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'train_model',
            csrf_token: '<?= getCSRFToken() ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(`Training complete!\nEncodings: ${data.total_encodings}\nCriminals: ${data.total_criminals}`);
        } else {
            alert('Training failed: ' + (data.error || 'Unknown error'));
        }
        location.reload();
    })
    .catch(e => alert('Error: ' + e.message))
    .finally(() => {
        btn.innerHTML = '<i class="fas fa-brain"></i><span>Train Model</span>';
        btn.style.pointerEvents = 'auto';
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>