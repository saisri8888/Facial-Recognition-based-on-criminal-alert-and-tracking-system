<?php
// includes/functions.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function getDB() {
    return Database::getInstance()->getConnection();
}

function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isInvestigator() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'investigator']);
}

function isOfficer() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'investigator', 'officer']);
}

function isViewer() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'investigator', 'officer', 'viewer']);
}

function hasRole($role) {
    $hierarchy = [
        'viewer' => 1,
        'officer' => 2,
        'investigator' => 3,
        'admin' => 4
    ];
    $userLevel = $hierarchy[$_SESSION['role']] ?? 0;
    $requiredLevel = $hierarchy[$role] ?? 0;
    return $userLevel >= $requiredLevel;
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('php/auth/login.php');
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['error'] = 'Access denied. Admin privileges required.';
        redirect('php/dashboard/index.php');
    }
}

function requireInvestigator() {
    requireLogin();
    if (!isInvestigator()) {
        $_SESSION['error'] = 'Access denied. Investigator or higher privileges required.';
        redirect('php/dashboard/index.php');
    }
}

function requireOfficer() {
    requireLogin();
    if (!isOfficer()) {
        $_SESSION['error'] = 'Access denied. Officer or higher privileges required.';
        redirect('php/dashboard/index.php');
    }
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// CSRF Token Functions
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(intval(getenv('CSRF_TOKEN_LENGTH') ?: 32) / 2));
    }
    return $_SESSION['csrf_token'];
}

function getCSRFToken() {
    return generateCSRFToken();
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

function getCSRFTokenInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCSRFToken()) . '">';
}

function generateCriminalCode() {
    $db = getDB();
    $stmt = $db->query("SELECT MAX(id) as max_id FROM criminals");
    $row = $stmt->fetch();
    $nextId = ($row['max_id'] ?? 0) + 1;
    return 'CRM-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
}

function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

function callPythonAPI($endpoint, $data = [], $method = 'POST', $files = []) {
    $url = PYTHON_API_URL . $endpoint;
    $ch = curl_init();
    
    // Get API key from config
    $apiKey = defined('PYTHON_API_KEY') ? PYTHON_API_KEY : getenv('PYTHON_API_KEY');

    if (!empty($files)) {
        // Multipart form data with files
        $postFields = $data;
        foreach ($files as $key => $filePath) {
            $postFields[$key] = new CURLFile($filePath);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        $headers = ['X-API-Key: ' . $apiKey];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    } else {
        $headers = [
            'Content-Type: application/json',
            'X-API-Key: ' . $apiKey
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    $decoded = json_decode($response, true);
    $decoded['http_code'] = $httpCode;
    return $decoded;
}

function logAction($action, $module, $description = '') {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO system_logs (user_id, action, module, description, ip_address, user_agent) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $action,
        $module,
        $description,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

function uploadCriminalPhoto($file, $criminalId) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'error' => 'File too large'];
    }

    $criminalDir = UPLOAD_PATH . $criminalId . '/';
    if (!is_dir($criminalDir)) {
        mkdir($criminalDir, 0755, true);
    }

    $filename = uniqid('photo_') . '.' . $ext;
    $filepath = $criminalDir . $filename;
    $relativePath = 'uploads/criminals/' . $criminalId . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => $relativePath, 'full_path' => $filepath];
    }

    return ['success' => false, 'error' => 'Upload failed'];
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M d, Y h:i A', strtotime($datetime));
}

function getDangerBadge($level) {
    $badges = [
        'low' => 'bg-success',
        'medium' => 'bg-warning',
        'high' => 'bg-danger',
        'critical' => 'bg-dark text-white'
    ];
    return '<span class="badge ' . ($badges[$level] ?? 'bg-secondary') . '">' . ucfirst($level) . '</span>';
}

function getStatusBadge($status) {
    $badges = [
        'wanted' => 'bg-danger',
        'arrested' => 'bg-success',
        'released' => 'bg-warning',
        'deceased' => 'bg-dark'
    ];
    return '<span class="badge ' . ($badges[$status] ?? 'bg-secondary') . '">' . ucfirst($status) . '</span>';
}

function getAlertStatusBadge($status) {
    $badges = [
        'new' => 'bg-danger blink',
        'acknowledged' => 'bg-info',
        'investigating' => 'bg-warning',
        'resolved' => 'bg-success',
        'false_alarm' => 'bg-secondary'
    ];
    return '<span class="badge ' . ($badges[$status] ?? 'bg-secondary') . '">' . ucfirst(str_replace('_',' ',$status)) . '</span>';
}