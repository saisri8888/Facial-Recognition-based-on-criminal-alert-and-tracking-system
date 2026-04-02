<?php
// php/reports/daily.php
require_once __DIR__ . '/../auth/middleware.php';
requireInvestigator();

$pageTitle = 'Daily Detection Report';
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$detections = [];
$db = getDB();

if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT a.id, a.criminal_id, a.confidence_score as confidence, a.detected_at as created_at,
                   c.first_name, c.last_name, c.criminal_code, c.crime_type,
                   cp.photo_path,
                   COALESCE(a.detection_location, 'Unknown') as location
            FROM detection_alerts a
            JOIN criminals c ON a.criminal_id = c.id
            LEFT JOIN criminal_photos cp ON c.id = cp.criminal_id AND cp.is_primary = 1
            WHERE DATE(a.detected_at) = ?
            ORDER BY a.detected_at DESC
        ");
        $stmt->execute([$selectedDate]);
        $detections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Daily Report Error: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-calendar-day"></i> Daily Detection Report</h1>
    <p class="text-muted">View all criminal detections for a specific day</p>
</div>

<div class="container-fluid">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <label for="reportDate" class="form-label"><strong>Select Date</strong></label>
                    <input type="date" id="reportDate" class="form-control form-control-lg" value="<?= $selectedDate ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-6">
                    <button onclick="generateReport()" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-refresh"></i> Generate Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary d-flex justify-content-between align-items-center">
            <h5 class="text-white mb-0">
                <i class="fas fa-list"></i> Detections for <?= date('M d, Y', strtotime($selectedDate)) ?>
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($detections)): ?>
                <div class="alert alert-info text-center py-5">
                    <i class="fas fa-search fa-2x mb-3"></i>
                    <p>No detections found for this date</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="detectionsTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="5%">#</th>
                                <th width="15%">Photo</th>
                                <th width="20%">Criminal Name</th>
                                <th width="15%">Criminal ID</th>
                                <th width="15%">Crime Type</th>
                                <th width="15%">Location</th>
                                <th width="12%">Confidence</th>
                                <th width="18%">Detection Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detections as $idx => $detection): ?>
                                <tr>
                                    <td><strong><?= $idx + 1 ?></strong></td>
                                    <td>
                                        <?php if ($detection['photo_path']): ?>
                                            <img src="<?= BASE_URL . $detection['photo_path'] ?>" class="img-thumbnail" style="max-height: 50px;">
                                        <?php else: ?>
                                            <span class="text-muted small">No photo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($detection['first_name'] . ' ' . $detection['last_name']) ?></strong><br>
                                        <code><?= htmlspecialchars($detection['criminal_code']) ?></code>
                                    </td>
                                    <td><code><?= htmlspecialchars($detection['criminal_id']) ?></code></td>
                                    <td><span class="badge bg-danger"><?= htmlspecialchars($detection['crime_type']) ?></span></td>
                                    <td>
                                        <small style="color:#17a2b8;"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($detection['location']) ?></small>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" style="width: <?= $detection['confidence'] ?>%">
                                                <?= round($detection['confidence'], 1) ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= date('H:i:s', strtotime($detection['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 p-3 bg-light rounded">
                    <h6><i class="fas fa-bar-chart"></i> Summary</h6>
                    <ul class="list-unstyled">
                        <li><strong>Total Detections:</strong> <?= count($detections) ?></li>
                        <li><strong>Unique Criminals:</strong> <?= count(array_unique(array_column($detections, 'criminal_id'))) ?></li>
                        <li><strong>Average Confidence:</strong> <?= round(array_sum(array_column($detections, 'confidence')) / count($detections), 2) ?>%</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function generateReport() {
    const date = document.getElementById('reportDate').value;
    window.location.href = '<?= BASE_URL ?>php/reports/daily.php?date=' + date;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
