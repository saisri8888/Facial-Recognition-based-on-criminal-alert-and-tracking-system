<?php
// php/auth/logout.php
require_once __DIR__ . '/../../includes/functions.php';
logAction('logout', 'auth', 'User logged out');
session_destroy();
redirect('php/auth/login.php');