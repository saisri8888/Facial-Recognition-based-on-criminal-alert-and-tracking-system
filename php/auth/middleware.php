<?php
// php/auth/middleware.php

require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }
    redirect('php/auth/login.php');
}