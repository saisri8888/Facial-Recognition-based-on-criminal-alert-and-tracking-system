<?php
// php/api/routes.php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth/middleware.php';

try {
    $db = getDB();
    $criminalId = $_GET['criminal_id'] ?? null;
    $response = [];
    
    if (!$criminalId) {
        echo json_encode(['error' => 'Criminal ID required']);
        exit;
    }
    
    // Get detection history for a criminal
    $stmt = $db->prepare("
        SELECT 
            da.id,
            da.detected_at,
            da.detection_location as location,
            da.confidence_score,
            c.first_name,
            c.last_name
        FROM detection_alerts da
        JOIN criminals c ON da.criminal_id = c.id
        WHERE da.criminal_id = ?
        ORDER BY da.detected_at ASC
    ");
    $stmt->execute([$criminalId]);
    $detections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create route from detections
    $route = [];
    foreach ($detections as $detection) {
        $route[] = [
            'id' => $detection['id'],
            'location' => $detection['location'] ?? 'Unknown',
            'time' => $detection['detected_at'],
            'confidence' => $detection['confidence_score']
        ];
    }
    
    $response['route'] = $route;
    $response['criminal_name'] = !empty($detections) ? $detections[0]['first_name'] . ' ' . $detections[0]['last_name'] : 'Unknown';
    $response['total_detections'] = count($detections);
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
