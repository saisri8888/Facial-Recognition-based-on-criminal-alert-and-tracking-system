<?php
// php/api/bridge.php

require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($input['csrf_token']) || !verifyCSRFToken($input['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

$db = getDB();

switch ($action) {
    case 'train_model':
        // Get all criminal photos with full paths
        $photos = $db->query("
            SELECT cp.id as photo_id, cp.photo_path, cp.criminal_id,
                   c.criminal_code, CONCAT(c.first_name, ' ', c.last_name) as name
            FROM criminal_photos cp
            JOIN criminals c ON cp.criminal_id = c.id
            WHERE c.is_active = 1
        ")->fetchAll();

        $trainingData = [];
        foreach ($photos as $photo) {
            $fullPath = ROOT_PATH . $photo['photo_path'];
            if (file_exists($fullPath)) {
                $trainingData[] = [
                    'criminal_id' => $photo['criminal_id'],
                    'photo_id' => $photo['photo_id'],
                    'photo_path' => $fullPath,
                    'criminal_code' => $photo['criminal_code'],
                    'name' => $photo['name']
                ];
            }
        }

        // Log training start
        $db->prepare("INSERT INTO training_history (trained_by, total_photos, status) VALUES (?, ?, 'started')")
            ->execute([$_SESSION['user_id'], count($trainingData)]);
        $trainingId = $db->lastInsertId();

        // Call Python
        $result = callPythonAPI('/api/train', ['photos' => $trainingData]);

        if (!empty($result['success'])) {
            // Update training record
            $db->prepare("UPDATE training_history SET status = 'completed', total_criminals = ?, total_encodings = ?, 
                          training_duration = ? WHERE id = ?")
                ->execute([$result['total_criminals'], $result['total_encodings'], $result['duration'] ?? 0, $trainingId]);

            // Clear old encodings and insert new
            $db->exec("DELETE FROM face_encodings");
            
            if (!empty($result['encodings'])) {
                $stmt = $db->prepare("INSERT INTO face_encodings (criminal_id, photo_id, encoding_data) VALUES (?, ?, ?)");
                foreach ($result['encodings'] as $enc) {
                    $stmt->execute([$enc['criminal_id'], $enc['photo_id'], base64_decode($enc['encoding_base64'])]);
                }
            }

            // Update photo encoding status
            $db->exec("UPDATE criminal_photos SET face_encoding_stored = 0");
            if (!empty($result['encoded_photo_ids'])) {
                $ids = array_map('intval', $result['encoded_photo_ids']);
                if (!empty($ids)) {
                    // Use parameterized query instead of string interpolation
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $db->prepare("UPDATE criminal_photos SET face_encoding_stored = 1 WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                }
            }

            logAction('train_model', 'detection', "Trained with {$result['total_encodings']} encodings");
        } else {
            $db->prepare("UPDATE training_history SET status = 'failed', error_message = ? WHERE id = ?")
                ->execute([$result['error'] ?? 'Unknown error', $trainingId]);
        }

        echo json_encode($result);
        break;

    case 'save_alert':
        $criminalId = intval($input['criminal_id']);
        $confidence = floatval($input['confidence_score']);
        $location = isset($input['detection_location']) ? trim($input['detection_location']) : 'Unknown Location';
        
        // Save screenshot
        $screenshotPath = null;
        if (!empty($input['frame_data'])) {
            $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $input['frame_data']));
            $screenshotDir = ROOT_PATH . 'uploads/screenshots/';
            if (!is_dir($screenshotDir)) mkdir($screenshotDir, 0755, true);
            $screenshotPath = 'uploads/screenshots/' . uniqid('detect_') . '.jpg';
            file_put_contents(ROOT_PATH . $screenshotPath, $imgData);
        }

        $stmt = $db->prepare("INSERT INTO detection_alerts 
            (criminal_id, detected_by_user, confidence_score, detection_screenshot, detection_location, camera_source)
            VALUES (?, ?, ?, ?, ?, 'webcam')");
        $stmt->execute([$criminalId, $_SESSION['user_id'], $confidence, $screenshotPath, $location]);

        logAction('criminal_detected', 'detection', "Criminal ID $criminalId detected at $location with $confidence% confidence");

        echo json_encode(['success' => true, 'alert_id' => $db->lastInsertId()]);
        break;

    case 'end_session':
        $stmt = $db->prepare("UPDATE detection_sessions SET 
            end_time = NOW(), status = 'ended',
            frames_processed = ?, faces_detected = ?, matches_found = ?
            WHERE session_token = ?");
        $stmt->execute([
            intval($input['frames_processed']),
            intval($input['faces_detected']),
            intval($input['matches_found']),
            $input['session_token']
        ]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}