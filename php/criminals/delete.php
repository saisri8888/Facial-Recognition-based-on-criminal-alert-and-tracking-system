<?php
// php/criminals/delete.php
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

$db = getDB();

// Soft delete
$stmt = $db->prepare("UPDATE criminals SET is_active = 0 WHERE id = ?");
$stmt->execute([$id]);

// Remove encodings from Python model
callPythonAPI('/api/remove_criminal', ['criminal_id' => $id]);

logAction('delete_criminal', 'criminals', "Deleted criminal ID: $id");

echo json_encode(['success' => true, 'message' => 'Criminal record deleted']);