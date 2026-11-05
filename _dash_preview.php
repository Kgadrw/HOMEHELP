<?php
/**
 * Local-only dashboard preview helper. Delete after use.
 * Usage: /_dash_preview.php?role=client|provider|agent|admin
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/config.php';

$host = $_SERVER['HTTP_HOST'] ?? '';
if (!str_starts_with($host, 'localhost') && !str_starts_with($host, '127.0.0.1')) {
    http_response_code(403);
    exit('Forbidden');
}

$role = $_GET['role'] ?? 'client';
$allowed = ['client', 'provider', 'agent', 'admin'];
if (!in_array($role, $allowed, true)) {
    exit('Invalid role');
}

$stmt = $conn->prepare("SELECT id, name, role FROM users WHERE role = ? AND status = 'active' ORDER BY id ASC LIMIT 1");
$stmt->bind_param('s', $role);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    // Create a throwaway preview user so the UI can still render
    $name  = ucfirst($role) . ' Preview';
    $email = $role . '.preview@homehelp.local';
    $pass  = password_hash('preview', PASSWORD_BCRYPT);
    $ins = $conn->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?,?,?,?,?,'active')");
    $phone = '+250700000000';
    $ins->bind_param('sssss', $name, $email, $phone, $pass, $role);
    $ins->execute();
    $user = ['id' => $conn->insert_id, 'name' => $name, 'role' => $role];
}

session_regenerate_id(true);
$_SESSION['user_id']   = (int)$user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'];

redirect(BASE_URL . clean_redirect($role));
