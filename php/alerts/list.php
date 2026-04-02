<?php
// php/alerts/list.php
require_once __DIR__ . '/../auth/middleware.php';
$pageTitle = 'Detection Alerts';

$db = getDB();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $alertId = intval($_POST['alert_id']);
    $newStatus = sanitize($_POST['new_status']);
    $notes = sanitize($_POST['notes'] ?? '');
    
    $stmt = $db->prepare("UPDATE detection_alerts SET alert_status = ?, acknowledged_by = ?, 
                          acknowledged_at = NOW(), notes = CONCAT(IFNULL(notes,''), '\n', ?) WHERE id = ?");
    $stmt->execute([$newStatus, $_SESSION['user_id'], $notes, $alertId]);
    logAction('update_alert', 'alerts', "Alert #$alertId status changed to $newStatus");
}

// Get alerts with filters
$status_filter = $_GET['status'] ?? '';
$location_filter = $_GET['location'] ?? '';
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "da.alert_status = ?";
    $params[] = $status_filter;
}

if ($location_filter) {
    $where_conditions[] = "COALESCE(da.detection_location, 'Unknown Location') = ?";
    $params[] = $location_filter;
}

$where = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all unique locations for filter dropdown
$locations_stmt = $db->query("
    SELECT DISTINCT COALESCE(detection_location, 'Unknown Location') as location
    FROM detection_alerts
    ORDER BY location ASC
");
$locations = $locations_stmt->fetchAll(PDO::FETCH_COLUMN);

$alerts = $db->prepare("
    SELECT da.*, 
           CONCAT(c.first_name, ' ', c.last_name) as criminal_name,
           c.criminal_code, c.crime_type, c.danger_level, c.status as criminal_status,
           cp.photo_path,
           u.full_name as detected_by_name,
           u2.full_name as acknowledged_by_name,
           COALESCE(da.detection_location, 'Unknown Location') as location
    FROM detection_alerts da
    JOIN criminals c ON da.criminal_id = c.id
    LEFT JOIN criminal_photos cp ON c.id = cp.criminal_id AND cp.is_primary = 1
    JOIN users u ON da.detected_by_user = u.id
    LEFT JOIN users u2 ON da.acknowledged_by = u2.id
    $where
    ORDER BY da.detected_at DESC
");
$alerts->execute($params);
$alerts = $alerts->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .location-badge {
        display: inline-block;
        background: rgba(23, 162, 184, 0.1);
        border: 1px solid #17a2b8;
        color: #17a2b8;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-bell me-2"></i>Detection Alerts</h2>
        <div class="d-flex gap-2 flex-wrap">
            <div class="btn-group btn-group-sm">
                <a href="?status=&location=<?= urlencode($location_filter) ?>" class="btn btn-sm <?= !$status_filter ? 'btn-info' : 'btn-outline-info' ?>">All Status</a>
                <a href="?status=new&location=<?= urlencode($location_filter) ?>" class="btn btn-sm <?= $status_filter==='new' ? 'btn-danger' : 'btn-outline-danger' ?>">New</a>
                <a href="?status=acknowledged&location=<?= urlencode($location_filter) ?>" class="btn btn-sm <?= $status_filter==='acknowledged' ? 'btn-primary' : 'btn-outline-primary' ?>">Acknowledged</a>
                <a href="?status=investigating&location=<?= urlencode($location_filter) ?>" class="btn btn-sm <?= $status_filter==='investigating' ? 'btn-warning' : 'btn-outline-warning' ?>">Investigating</a>
                <a href="?status=resolved&location=<?= urlencode($location_filter) ?>" class="btn btn-sm <?= $status_filter==='resolved' ? 'btn-success' : 'btn-outline-success' ?>">Resolved</a>
            </div>
            <select class="form-select form-select-sm" style="max-width:300px;" onchange="filterByLocation(this.value)">
                <option value="">📍 All Locations</option>
                <?php foreach ($locations as $loc): ?>
                <option value="<?= urlencode($loc) ?>" <?= $location_filter === urlencode($loc) ? 'selected' : '' ?>>
                    <i class="fas fa-map-marker-alt"></i> <?= sanitize($loc) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="card dark-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0" id="alertsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Criminal</th>
                            <th>Crime</th>
                            <th>Location</th>
                            <th>Confidence</th>
                            <th>Danger</th>
                            <th>Alert Status</th>
                            <th>Detected By</th>
                            <th>Detected At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $a): ?>
                        <tr>
                            <td><?= $a['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= BASE_URL . ($a['photo_path'] ?? 'assets/images/no-photo.png') ?>" 
                                         class="rounded-circle" width="40" height="40" style="object-fit:cover;">
                                    <div>
                                        <strong><?= sanitize($a['criminal_name']) ?></strong><br>
                                        <code class="small"><?= $a['criminal_code'] ?></code>
                                    </div>
                                </div>
                            </td>
                            <td><?= sanitize($a['crime_type']) ?></td>
                            <td>
                                <i class="fas fa-map-marker-alt" style="color:#17a2b8; margin-right:5px;"></i>
                                <small><?= sanitize($a['location']) ?></small>
                            </td>
                            <td>
                                <div class="progress" style="height:22px;min-width:80px;background:rgba(255,255,255,0.1);">
                                    <div class="progress-bar <?= $a['confidence_score'] > 80 ? 'bg-danger' : ($a['confidence_score'] > 60 ? 'bg-warning' : 'bg-info') ?>" 
                                         style="width:<?= $a['confidence_score'] ?>%">
                                        <?= number_format($a['confidence_score'], 1) ?>%
                                    </div>
                                </div>
                            </td>
                            <td><?= getDangerBadge($a['danger_level']) ?></td>
                            <td><?= getAlertStatusBadge($a['alert_status']) ?></td>
                            <td><?= sanitize($a['detected_by_name']) ?></td>
                            <td><?= formatDateTime($a['detected_at']) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($a['detection_screenshot']): ?>
                                    <button class="btn btn-outline-info" onclick="viewScreenshot('<?= BASE_URL . $a['detection_screenshot'] ?>')">
                                        <i class="fas fa-image"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-warning" onclick="updateAlertStatus(<?= $a['id'] ?>, '<?= $a['alert_status'] ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="<?= BASE_URL ?>php/criminals/detail.php?id=<?= $a['criminal_id'] ?>" 
                                       class="btn btn-outline-success"><i class="fas fa-user"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Screenshot Modal -->
<div class="modal fade" id="screenshotModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-body p-0">
                <img id="screenshotImg" src="" class="w-100 rounded">
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#alertsTable').DataTable({ pageLength: 25, order: [[0, 'desc']] });
});

function filterByLocation(location) {
    const status = new URLSearchParams(window.location.search).get('status') || '';
    window.location.href = `?status=${encodeURIComponent(status)}&location=${encodeURIComponent(location)}`;
}

function viewScreenshot(url) {
    document.getElementById('screenshotImg').src = url;
    new bootstrap.Modal(document.getElementById('screenshotModal')).show();
}

function updateAlertStatus(alertId, currentStatus) {
    Swal.fire({
        title: 'Update Alert Status',
        html: `
            <select id="swalStatus" class="swal2-select">
                <option value="new" ${currentStatus === 'new' ? 'selected' : ''}>New</option>
                <option value="acknowledged" ${currentStatus === 'acknowledged' ? 'selected' : ''}>Acknowledged</option>
                <option value="investigating" ${currentStatus === 'investigating' ? 'selected' : ''}>Investigating</option>
                <option value="resolved" ${currentStatus === 'resolved' ? 'selected' : ''}>Resolved</option>
                <option value="false_alarm" ${currentStatus === 'false_alarm' ? 'selected' : ''}>False Alarm</option>
            </select>
            <textarea id="swalNotes" class="swal2-textarea" placeholder="Add notes..."></textarea>
        `,
        showCancelButton: true,
        confirmButtonText: 'Update',
        preConfirm: () => {
            return {
                status: document.getElementById('swalStatus').value,
                notes: document.getElementById('swalNotes').value
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="alert_id" value="${alertId}">
                <input type="hidden" name="new_status" value="${result.value.status}">
                <input type="hidden" name="notes" value="${result.value.notes}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>