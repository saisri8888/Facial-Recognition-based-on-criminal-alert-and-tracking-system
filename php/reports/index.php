<?php
// php/reports/index.php
require_once __DIR__ . '/../auth/middleware.php';

// Reports require Investigator or higher
requireInvestigator();

$pageTitle = 'Reports Center';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-chart-line"></i> Reports Center</h1>
    <p class="text-muted">Generate and analyze detection reports</p>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card shadow-sm h-100 border-primary">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-day fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Daily Detection Report</h5>
                    <p class="card-text text-muted small">View all criminal detections for a specific day</p>
                    <a href="<?= BASE_URL ?>php/reports/daily.php" class="btn btn-primary w-100">
                        <i class="fas fa-arrow-right"></i> View Report
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card shadow-sm h-100 border-success">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-alt fa-3x text-success mb-3"></i>
                    <h5 class="card-title">Monthly Crime Report</h5>
                    <p class="card-text text-muted small">Analyze crime statistics and trends by month</p>
                    <a href="<?= BASE_URL ?>php/reports/monthly.php" class="btn btn-success w-100">
                        <i class="fas fa-arrow-right"></i> View Report
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card shadow-sm h-100 border-warning">
                <div class="card-body text-center">
                    <i class="fas fa-user-secret fa-3x text-warning mb-3"></i>
                    <h5 class="card-title">Criminal Activity Report</h5>
                    <p class="card-text text-muted small">Track detection history of specific criminals</p>
                    <a href="<?= BASE_URL ?>php/reports/criminal.php" class="btn btn-warning w-100">
                        <i class="fas fa-arrow-right"></i> View Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <hr class="my-4">

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Quick Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <?php
                        $db = getDB();
                        $stats = [];
                        
                        if ($db) {
                            try {
                                // Total detections
                                $stmt = $db->query("SELECT COUNT(*) as count FROM detection_alerts");
                                $stats['total_detections'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                                
                                // Total criminals caught
                                $stmt = $db->query("SELECT COUNT(DISTINCT criminal_id) as count FROM detection_alerts");
                                $stats['total_criminals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                                
                                // Today's detections
                                $stmt = $db->query("SELECT COUNT(*) as count FROM detection_alerts WHERE DATE(detected_at) = CURDATE()");
                                $stats['today_detections'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                                
                                // This month detections
                                $stmt = $db->query("SELECT COUNT(*) as count FROM detection_alerts WHERE MONTH(detected_at) = MONTH(NOW()) AND YEAR(detected_at) = YEAR(NOW())");
                                $stats['month_detections'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                            } catch (Exception $e) {
                                error_log('Stats Error: ' . $e->getMessage());
                            }
                        }
                        ?>
                        
                        <div class="col-md-6 col-lg-3 mb-3">
                            <div class="stat-box">
                                <h3 class="text-primary"><?= $stats['total_detections'] ?? 0 ?></h3>
                                <p class="text-muted">Total Detections</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-3">
                            <div class="stat-box">
                                <h3 class="text-success"><?= $stats['total_criminals'] ?? 0 ?></h3>
                                <p class="text-muted">Criminals Caught</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-3">
                            <div class="stat-box">
                                <h3 class="text-warning"><?= $stats['today_detections'] ?? 0 ?></h3>
                                <p class="text-muted">Today's Detections</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-3">
                            <div class="stat-box">
                                <h3 class="text-info"><?= $stats['month_detections'] ?? 0 ?></h3>
                                <p class="text-muted">This Month</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stat-box {
    padding: 20px;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    border-radius: 8px;
    transition: transform 0.3s;
}

.stat-box:hover {
    transform: translateY(-5px);
}

.stat-box h3 {
    font-size: 2em;
    font-weight: bold;
    margin: 0;
}

.stat-box p {
    margin: 5px 0 0 0;
    font-size: 0.9em;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
