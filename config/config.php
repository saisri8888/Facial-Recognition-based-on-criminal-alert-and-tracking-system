<?php
// config/config.php

// Load environment variables from .env file
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        return;
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!empty($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(dirname(__DIR__) . '/.env');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'criminal_detection_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');

// API Configuration
define('PYTHON_API_URL', getenv('PYTHON_API_URL') ?: 'http://localhost:5001');
define('PYTHON_API_KEY', getenv('PYTHON_API_KEY') ?: 'default-key-change-this');

// Application
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/criminal/');
define('ROOT_PATH', dirname(__DIR__) . '/');
define('UPLOAD_PATH', ROOT_PATH . 'uploads/criminals/');
define('SITE_NAME', getenv('SITE_NAME') ?: 'Criminal Detection System');

// Upload Settings
define('MAX_UPLOAD_SIZE', (int)getenv('MAX_UPLOAD_SIZE') ?: (10 * 1024 * 1024));
$allowedExts = explode(',', getenv('ALLOWED_EXTENSIONS') ?: 'jpg,jpeg,png,webp');
define('ALLOWED_EXTENSIONS', array_map('trim', $allowedExts));

// Detection Settings
define('DETECTION_CONFIDENCE_THRESHOLD', (float)getenv('DETECTION_CONFIDENCE_THRESHOLD') ?: 60.0);
define('FRAMES_PER_SECOND', (int)getenv('FRAMES_PER_SECOND') ?: 2);

// Session config
$sessionSecure = (bool)getenv('SESSION_SECURE') ?: false;
$sessionHttpOnly = (bool)getenv('SESSION_HTTPONLY') ?: true;

ini_set('session.cookie_httponly', $sessionHttpOnly ? 1 : 0);
ini_set('session.cookie_secure', $sessionSecure ? 1 : 0);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);

session_start();