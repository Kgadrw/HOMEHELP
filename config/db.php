<?php
// ============================================================
//  Home Help — Database Connection
//  File: config/db.php
//  DevGuide §1.3 — using mysqli as specified
//  Brilliant Elite Ltd · Rwanda 🇷🇼
// ============================================================

// Use 127.0.0.1 (not "localhost") so mysqli uses TCP and respects the port.
// Portable MariaDB runs on 3307 because Windows MySQL80 already uses 3306.
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3307);
define('DB_USER', 'homehelp_user_help');
define('DB_PASS', 'H@meHe!p3r');
define('DB_NAME', 'homehelp_dhomehelpb');

mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    error_log('DB Connection failed: ' . $conn->connect_error . ' @ ' . DB_HOST . ':' . DB_PORT);
    http_response_code(500);
    die('<p style="font-family:sans-serif;text-align:center;padding:40px;">
        Database is not reachable on ' . htmlspecialchars(DB_HOST) . ':' . (int)DB_PORT . '.<br>
        Start portable MariaDB, then refresh.
    </p>');
}

$conn->set_charset('utf8mb4');
