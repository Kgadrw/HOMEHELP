<?php
/**
 * Client account registration (JSON). Used by the in-page register modal.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (isLoggedIn()) {
    echo json_encode(['ok' => true, 'redirect' => BASE_URL . clean_redirect($_SESSION['user_role'] ?? 'client')]);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired. Refresh the page and try again.']);
    exit;
}

$name     = sanitize($_POST['name'] ?? '');
$email    = sanitize($_POST['email'] ?? '');
$phone    = sanitize($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';
$terms    = $_POST['terms'] ?? '';

if (!$name || !$email || !$phone || !$password || !$confirm) {
    echo json_encode(['ok' => false, 'error' => 'Please fill in all required fields.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}
if (strlen($password) < 8) {
    echo json_encode(['ok' => false, 'error' => 'Password must be at least 8 characters.']);
    exit;
}
if ($password !== $confirm) {
    echo json_encode(['ok' => false, 'error' => 'Passwords do not match.']);
    exit;
}
if (!$terms) {
    echo json_encode(['ok' => false, 'error' => 'Please accept the Terms of Service.']);
    exit;
}

$chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$chk->bind_param('s', $email);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
    echo json_encode(['ok' => false, 'error' => 'This email is already registered. Sign in instead.']);
    exit;
}

$role   = 'client';
$hashed = password_hash($password, PASSWORD_BCRYPT);
$ins    = $conn->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?,?,?,?,?,'active')");
$ins->bind_param('sssss', $name, $email, $phone, $hashed, $role);

if (!$ins->execute()) {
    echo json_encode(['ok' => false, 'error' => 'Could not create account. Please try again.']);
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id']   = (int)$conn->insert_id;
$_SESSION['user_name'] = $name;
$_SESSION['user_role'] = $role;

echo json_encode(['ok' => true, 'redirect' => BASE_URL . '/dashboard']);
