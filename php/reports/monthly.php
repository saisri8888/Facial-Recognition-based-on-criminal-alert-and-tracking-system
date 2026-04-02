<?php
// php/reports/monthly.php
require_once __DIR__ . '/../auth/middleware.php';
requireInvestigator();

$pageTitle = 'Monthly Crime Detection Report';
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$db = getDB();
$stats = ['total' => 0, 'criminals' => 0, 'avg_confidence' => 0];
$topCrimes = [];
$topLocations = [];
$dailyTrend = [];

if ($db) {
    try {
        // Total detections this month
        $stmt = $db->prepare("
            SELECT COUNT(*) as total, AVG(confidence_score) as avg_conf
            FROM detection_alerts 
            WHERE MONTH(detected_at) = ? AND YEAR(detected_at) = ?
        ");
        $stmt->execute([$month, $year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total'] = $result['total'] ?? 0;
        $stats['avg_confidence'] = round($result['avg_conf'] ?? 0, 2);
        
        // Unique criminals
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT criminal_id) as count
            FROM detection_alerts 
            WHERE MONTH(detected_at) = ? AND YEAR(detected_at) = ?
        ");
        $stmt->execute([$month, $year]);
        $stats['criminals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Top crime types
        $stmt = $db->prepare("
            SELECT c.crime_type, COUNT(*) as count
            FROM detection_alerts a
            JOIN criminals c ON a.criminal_id = c.id
            WHERE MONTH(a.detected_at) = ? AND YEAR(a.detected_at) = ?
            GROUP BY c.crime_type
            ORDER BY count DESC
            LIMIT 5
        ");
        $stmt->execute([$month, $year]);
        $topCrimes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Daily trend
        $stmt = $db->prepare("
            SELECT DATE(detected_at) as date, COUNT(*) as count
            FROM detection_alerts 
            WHERE MONTH(detected_at) = ? AND YEAR(detected_at) = ?
            GROUP BY DATE(detected_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$month, $year]);
        $dailyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log('Monthly Report Error: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-calendar-alt"></i> Monthly Crime Detection Report</h1>
    <p class="text-muted">Analyze crime statistics and trends</p>
</div>

<div class="container-fluid">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label for="reportMonth" class="form-label"><strong>Month</strong></label>
                    <select id="reportMonth" class="form-select form-select-lg">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="reportYear" class="form-label"><strong>Year</strong></label>
                    <select id="reportYear" class="form-select form-select-lg">
                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                            <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <button onclick="generateMonthlyReport()" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-refresh"></i> Generate Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-6 col-lg-3 mb-3">
            <div class="card shadow-sm border-primary">
                <div class="card-body text-center">
                    <h3 class="text-primary"><?= $stats['total'] ?></h3>
                    <p class="text-muted mb-0">Total Detections</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3">
            <div class="card shadow-sm border-success">
                <div class="card-body text-center">
                    <h3 class="text-success"><?= $stats['criminals'] ?></h3>
                    <p class="text-muted mb-0">Unique Criminals</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3">
            <div class="card shadow-sm border-warning">
                <div class="card-body text-center">
                    <h3 class="text-warning"><?= $stats['avg_confidence'] ?>%</h3>
                    <p class="text-muted mb-0">Avg Confidence</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3">
            <div class="card shadow-sm border-info">
                <div class="card-body text-center">
                    <h3 class="text-info"><?= $stats['total'] > 0 ? round($stats['total'] / 30, 1) : 0 ?></h3>
                    <p class="text-muted mb-0">Avg Per Day</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Daily Trend Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary">
                    <h5 class="text-white mb-0"><i class="fas fa-chart-line"></i> Daily Detection Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="trendChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Crime Types -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-danger">
                    <h5 class="text-white mb-0"><i class="fas fa-list"></i> Top Crime Types</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($topCrimes)): ?>
                        <p class="text-muted text-center">No data available</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($topCrimes as $crime): ?>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span><?= htmlspecialchars($crime['crime_type']) ?></span>
                                    <span class="badge bg-danger"><?= $crime['count'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function generateMonthlyReport() {
    const month = document.getElementById('reportMonth').value;
    const year = document.getElementById('reportYear').value;
    window.location.href = '<?= BASE_URL ?>php/reports/monthly.php?month=' + month + '&year=' + year;
}

// Chart.js Configuration
const trendData = <?= json_encode($dailyTrend) ?>;
const dates = trendData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
const counts = trendData.map(d => d.count);

new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: dates,
        datasets: [{
            label: 'Daily Detections',
            data: counts,
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
