<?php
// Run this ONCE then DELETE it immediately!
require_once 'config/db.php';

$new_password = 'AdminHome2026!';
$hash = password_hash($new_password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = 'admin@homehelp.rw'");
$stmt->bind_param('s', $hash);
$stmt->execute();

echo "✅ Password updated successfully!<br>";
echo "Email: admin@homehelp.rw<br>";
echo "Password: AdminHome2026!<br>";
echo "<br><strong>⚠️ DELETE this file immediately after use!</strong>";
?>