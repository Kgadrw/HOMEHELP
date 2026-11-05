<?php
// DIAGNOSTIC FILE — delete after use
// Upload to public_html/test_login.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<h2>Testing Login Dependencies</h2>';

// Step 1: db.php
echo '<h3>1. db.php</h3>';
try {
    require_once __DIR__ . '/config/db.php';
    echo '<p style="color:green">✅ db.php OK</p>';
    echo '<p>DB connection: ' . (isset($conn) ? 'YES' : 'NO') . '</p>';
} catch (Throwable $e) {
    echo '<p style="color:red">❌ ' . htmlspecialchars($e->getMessage()) . ' on line ' . $e->getLine() . '</p>';
}

// Step 2: config.php
echo '<h3>2. config.php</h3>';
try {
    require_once __DIR__ . '/config/config.php';
    echo '<p style="color:green">✅ config.php OK</p>';
    echo '<p>BASE_URL defined: ' . (defined("BASE_URL") ? BASE_URL : "NO") . '</p>';
} catch (Throwable $e) {
    echo '<p style="color:red">❌ ' . htmlspecialchars($e->getMessage()) . ' on line ' . $e->getLine() . '</p>';
}

// Step 3: Check if login.php itself has syntax error
echo '<h3>3. auth/login.php syntax</h3>';
$login_file = __DIR__ . '/auth/login.php';
if (file_exists($login_file)) {
    $size = filesize($login_file);
    $modified = date('Y-m-d H:i:s', filemtime($login_file));
    echo "<p>File exists: YES</p>";
    echo "<p>Size: {$size} bytes</p>";
    echo "<p>Last modified: {$modified}</p>";
    
    // Show first 5 lines
    $lines = file($login_file);
    echo "<p>Total lines: " . count($lines) . "</p>";
    echo "<h4>First 10 lines:</h4><pre>";
    for($i=0; $i<min(10,count($lines)); $i++){
        echo ($i+1) . ": " . htmlspecialchars($lines[$i]);
    }
    echo "</pre>";
} else {
    echo '<p style="color:red">❌ auth/login.php NOT FOUND</p>';
}

// Step 4: Check footer.php
echo '<h3>4. includes/footer.php</h3>';
$footer = __DIR__ . '/includes/footer.php';
if (file_exists($footer)) {
    $size = filesize($footer);
    echo "<p>Exists: YES, Size: {$size} bytes</p>";
} else {
    echo '<p style="color:red">❌ footer.php NOT FOUND</p>';
}

// Step 5: Check .htaccess routing
echo '<h3>5. .htaccess / routing</h3>';
$htaccess = __DIR__ . '/.htaccess';
if (file_exists($htaccess)) {
    echo "<pre>" . htmlspecialchars(file_get_contents($htaccess)) . "</pre>";
} else {
    echo '<p>No .htaccess found</p>';
}

echo '<hr><p><strong>Delete test_login.php after reading!</strong></p>';