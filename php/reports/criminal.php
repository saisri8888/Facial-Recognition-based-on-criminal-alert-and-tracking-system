<?php
// php/reports/criminal.php
require_once __DIR__ . '/../auth/middleware.php';
requireInvestigator();

$pageTitle = 'Criminal Activity Report';
$criminals = [];
$selectedCriminalId = isset($_GET['criminal_id']) ? intval($_GET['criminal_id']) : null;
$activitiesDetailing = [];

$db = getDB();

// Get all criminals for dropdown - ONLY those with detections
if ($db) {
    try {
        // Get criminals that actually have detection history
        $stmt = $db->query("
            SELECT DISTINCT c.id, c.criminal_code, c.first_name, c.last_name
            FROM criminals c
            INNER JOIN detection_alerts a ON c.id = a.criminal_id
            ORDER BY c.first_name, c.last_name
        ");
        $criminals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If criminal selected, get activity history
        if ($selectedCriminalId) {
            $stmt = $db->prepare("
                SELECT a.id,
                       a.criminal_id,
                       a.detected_at as created_at,
                       a.confidence_score as confidence,
                       COALESCE(a.detection_location, 'Unknown') as location,
                       a.detected_by_user,
                       a.notes,
                       c.first_name, c.last_name, c.criminal_code, c.crime_type, c.danger_level, c.status,
                       cp.photo_path
                FROM detection_alerts a
                JOIN criminals c ON a.criminal_id = c.id
                LEFT JOIN criminal_photos cp ON c.id = cp.criminal_id AND cp.is_primary = 1
                WHERE a.criminal_id = ?
                ORDER BY a.detected_at DESC
            ");
            $stmt->execute([$selectedCriminalId]);
            $activitiesDetailing = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log('Criminal Report Error: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-user-secret"></i> Criminal Activity Report</h1>
    <p class="text-muted">Track detection history of specific criminals</p>
</div>

<div class="container-fluid">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-8">
                    <label for="criminalSelect" class="form-label"><strong>Select Criminal</strong></label>
                    <select id="criminalSelect" class="form-select form-select-lg">
                        <option value="">-- Select a Criminal --</option>
                        <?php foreach ($criminals as $criminal): ?>
                            <option value="<?= $criminal['id'] ?>" <?= $criminal['id'] == $selectedCriminalId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($criminal['first_name'] . ' ' . $criminal['last_name']) ?> 
                                (<?= htmlspecialchars($criminal['criminal_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button onclick="viewCriminalReport()" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-search"></i> View Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($selectedCriminalId && !empty($activitiesDetailing)): ?>
        <?php $criminal = $activitiesDetailing[0]; ?>
        
        <!-- Criminal Details Banner -->
        <div class="card shadow-sm mb-4 border-danger">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <?php if ($criminal['photo_path']): ?>
                            <img src="<?= BASE_URL . $criminal['photo_path'] ?>" class="img-fluid rounded" alt="Criminal">
                        <?php else: ?>
                            <div class="bg-light p-4 rounded text-center">
                                <i class="fas fa-user-slash fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-9">
                        <h3 class="text-danger"><?= htmlspecialchars($criminal['first_name'] . ' ' . $criminal['last_name']) ?></h3>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Criminal ID:</strong></td>
                                <td><code><?= $criminal['criminal_id'] ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>Criminal Code:</strong></td>
                                <td><code><?= htmlspecialchars($criminal['criminal_code']) ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>Crime Type:</strong></td>
                                <td><span class="badge bg-danger"><?= htmlspecialchars($criminal['crime_type']) ?></span></td>
                            </tr>
                            <tr>
                                <td><strong>Danger Level:</strong></td>
                                <td>
                                    <span class="badge bg-<?= $criminal['danger_level'] === 'High' ? 'danger' : ($criminal['danger_level'] === 'Medium' ? 'warning' : 'info') ?>">
                                        <?= htmlspecialchars($criminal['danger_level']) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-<?= $criminal['status'] === 'wanted' ? 'danger' : ($criminal['status'] === 'arrested' ? 'secondary' : 'success') ?>">
                                        <?= ucfirst(htmlspecialchars($criminal['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detection History -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary d-flex justify-content-between">
                <h5 class="text-white mb-0"><i class="fas fa-history"></i> Detection History (<?= count($activitiesDetailing) ?> records)</h5>
                <button onclick="exportCriminalReport()" class="btn btn-sm btn-light">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="activityTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="12%">Date</th>
                                <th width="12%">Time</th>
                                <th width="12%">Confidence</th>
                                <th width="15%">Location</th>
                                <th width="15%">Detected By</th>
                                <th width="20%">Notes</th>
                                <th width="14%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activitiesDetailing as $activity): ?>
                                <tr>
                                    <td><?= date('M d, Y', strtotime($activity['created_at'])) ?></td>
                                    <td><?= date('H:i:s', strtotime($activity['created_at'])) ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" style="width: <?= $activity['confidence'] ?>%">
                                                <?= round($activity['confidence'], 1) ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><small style="color:#17a2b8;"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($activity['location']) ?></small></td>
                                    <td><small><?= htmlspecialchars($activity['detected_by_user'] ?? 'System') ?></small></td>
                                    <td><small><?= htmlspecialchars(substr($activity['notes'] ?? '', 0, 30)) . (strlen($activity['notes'] ?? '') > 30 ? '...' : '') ?></small></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>php/alerts/detail.php?id=<?= $activity['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Summary Statistics -->
                <div class="mt-4 p-3 bg-light rounded">
                    <h6><i class="fas fa-bar-chart"></i> Detection Summary</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Total Detections:</strong> <?= count($activitiesDetailing) ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Average Confidence:</strong> <?= round(array_sum(array_column($activitiesDetailing, 'confidence')) / count($activitiesDetailing), 2) ?>%
                        </div>
                        <div class="col-md-3">
                            <strong>First Detection:</strong> <?= date('M d, Y', strtotime(end($activitiesDetailing)['created_at'])) ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Last Detection:</strong> <?= date('M d, Y', strtotime($activitiesDetailing[0]['created_at'])) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($selectedCriminalId): ?>
        <div class="alert alert-info text-center py-5">
            <i class="fas fa-info-circle fa-2x mb-3"></i>
            <p>No detection history found for this criminal</p>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary text-center py-5">
            <i class="fas fa-user-secret fa-2x mb-3"></i>
            <p>Select a criminal from the dropdown above to view their detection history</p>
        </div>
    <?php endif; ?>
</div>

<script>
function viewCriminalReport() {
    const criminalId = document.getElementById('criminalSelect').value;
    if (!criminalId) {
        alert('Please select a criminal');
        return;
    }
    window.location.href = '<?= BASE_URL ?>php/reports/criminal.php?criminal_id=' + criminalId;
}

function exportCriminalReport() {
    const table = document.getElementById('activityTable');
    let csv = 'Date,Time,Confidence,Location,Detected By,Notes\n';
    
    table.querySelectorAll('tbody tr').forEach(row => {
        const cells = row.querySelectorAll('td');
        const date = cells[0].textContent.trim();
        const time = cells[1].textContent.trim();
        const confidence = cells[2].textContent.trim();
        const location = cells[3].textContent.trim();
        const detectedBy = cells[4].textContent.trim();
        const notes = cells[5].textContent.trim();
        
        csv += `"${date}","${time}","${confidence}","${location}","${detectedBy}","${notes}"\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'criminal-report.csv';
    a.click();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
