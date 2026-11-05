<?php
// ============================================================
//  Home Help — Database Connection
//  File: config/db.php
//  DevGuide §1.3 — using mysqli as specified
//  Brilliant Elite Ltd · Rwanda 🇷🇼
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'homehelp_user_help');
define('DB_PASS', 'H@meHe!p3r');
define('DB_NAME', 'homehelp_dhomehelpb');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    // Log error — never expose DB details to users
    error_log('DB Connection failed: ' . $conn->connect_error);
    http_response_code(500);
    die('<p style="font-family:sans-serif;text-align:center;padding:40px;">
        Service temporarily unavailable. Please try again later.
    </p>');
}

$conn->set_charset('utf8mb4');