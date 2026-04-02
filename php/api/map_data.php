<?php
// php/api/map_data.php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth/middleware.php';

try {
    $db = getDB();
    $action = $_GET['action'] ?? '';
    $response = [];

    if ($action === 'detections') {
        // Get all detection locations
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $criminalId = $_GET['criminal_id'] ?? null;
        $crimeType = $_GET['crime_type'] ?? null;

        $query = "
            SELECT 
                da.id,
                da.criminal_id,
                da.confidence_score,
                da.detected_at,
                da.detection_location as location,
                da.detection_screenshot,
                c.first_name,
                c.last_name,
                c.crime_type,
                c.danger_level,
                cp.photo_path,
                COALESCE(da.detection_location, 'Location Unknown') as display_location
            FROM detection_alerts da
            JOIN criminals c ON da.criminal_id = c.id
            LEFT JOIN criminal_photos cp ON c.id = cp.criminal_id AND cp.is_primary = 1
            WHERE 1=1
        ";

        $params = [];

        if ($dateFrom) {
            $query .= " AND DATE(da.detected_at) >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $query .= " AND DATE(da.detected_at) <= ?";
            $params[] = $dateTo;
        }

        if ($criminalId) {
            $query .= " AND da.criminal_id = ?";
            $params[] = intval($criminalId);
        }

        if ($crimeType) {
            $query .= " AND c.crime_type = ?";
            $params[] = $crimeType;
        }

        $query .= " ORDER BY da.detected_at DESC LIMIT 500";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $response['detections'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else if ($action === 'criminals') {
        // Get all criminals for filtering
        $stmt = $db->query("
            SELECT id, criminal_code, first_name, last_name, crime_type, danger_level
            FROM criminals
            ORDER BY first_name
        ");
        $response['criminals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else if ($action === 'crime_types') {
        // Get unique crime types
        $stmt = $db->query("
            SELECT DISTINCT crime_type
            FROM criminals
            WHERE crime_type IS NOT NULL AND crime_type != ''
            ORDER BY crime_type
        ");
        $response['crime_types'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } else if ($action === 'heatmap') {
        // Get heatmap data
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;

        $query = "
            SELECT 
                COALESCE(da.detection_location, 'City Center') as location,
                COUNT(*) as count,
                LATITUDE(da.detection_location) as lat,
                LONGITUDE(da.detection_location) as lng
            FROM detection_alerts da
            WHERE da.detection_location IS NOT NULL
        ";

        $params = [];

        if ($dateFrom) {
            $query .= " AND DATE(da.detected_at) >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $query .= " AND DATE(da.detected_at) <= ?";
            $params[] = $dateTo;
        }

        $query .= " GROUP BY da.detection_location ORDER BY count DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $response['heatmap_points'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else if ($action === 'statistics') {
        // Get crime statistics overlay
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $location = $_GET['location'] ?? null;

        $query = "
            SELECT 
                COALESCE(da.detection_location, 'City Center') as location,
                COUNT(*) as total_crimes,
                COUNT(DISTINCT da.criminal_id) as unique_criminals,
                COUNT(CASE WHEN c.danger_level = 'critical' THEN 1 END) as critical_crimes,
                COUNT(CASE WHEN c.danger_level = 'high' THEN 1 END) as high_crimes,
                COUNT(CASE WHEN c.danger_level = 'medium' THEN 1 END) as medium_crimes,
                COUNT(CASE WHEN c.danger_level = 'low' THEN 1 END) as low_crimes,
                (SELECT crime_type FROM criminals c2 
                 WHERE c2.id IN (
                     SELECT DISTINCT criminal_id FROM detection_alerts 
                     WHERE detection_location = COALESCE(da.detection_location, 'City Center')
                 )
                 GROUP BY crime_type ORDER BY COUNT(*) DESC LIMIT 1) as most_common_crime
            FROM detection_alerts da
            JOIN criminals c ON da.criminal_id = c.id
            WHERE 1=1
        ";

        $params = [];

        if ($dateFrom) {
            $query .= " AND DATE(da.detected_at) >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $query .= " AND DATE(da.detected_at) <= ?";
            $params[] = $dateTo;
        }

        if ($location) {
            $query .= " AND da.detection_location = ?";
            $params[] = $location;
        }

        $query .= " GROUP BY detection_location ORDER BY total_crimes DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $response['statistics'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
