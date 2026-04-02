<?php
// php/api/location_search.php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth/middleware.php';

try {
    $query = $_GET['q'] ?? '';
    
    if (strlen($query) < 2) {
        echo json_encode(['results' => []]);
        exit;
    }
    
    // Mock location database
    $locations = [
        ['name' => 'MG Road, Bangalore', 'lat' => 12.9352, 'lng' => 77.6149, 'type' => 'street'],
        ['name' => 'Indiranagar, Bangalore', 'lat' => 12.9716, 'lng' => 77.6412, 'type' => 'area'],
        ['name' => 'Whitefield, Bangalore', 'lat' => 12.9698, 'lng' => 77.7499, 'type' => 'area'],
        ['name' => 'Koramangala, Bangalore', 'lat' => 12.9352, 'lng' => 77.6245, 'type' => 'area'],
        ['name' => 'City Center, Bangalore', 'lat' => 12.9716, 'lng' => 77.5946, 'type' => 'landmark'],
        ['name' => 'Bus Station, Bangalore', 'lat' => 12.8396, 'lng' => 77.6245, 'type' => 'landmark'],
        ['name' => 'Railway Station, Bangalore', 'lat' => 12.9277, 'lng' => 77.5903, 'type' => 'landmark'],
        ['name' => 'Airport, Bangalore', 'lat' => 13.1979, 'lng' => 77.7064, 'type' => 'landmark']
    ];
    
    $results = array_filter($locations, function($loc) use ($query) {
        return stripos($loc['name'], $query) !== false;
    });
    
    echo json_encode(['results' => array_values($results)]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
