<?php
/**
 * Job seeker (provider) login — JSON for in-page modal.
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
    echo json_encode(['ok' => true, 'redirect' => BASE_URL . clean_redirect($_SESSION['user_role'] ?? 'provider')]);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired. Refresh the page and try again.']);
    exit;
}

$email = sanitize($_POST['email'] ?? '');
$pass  = $_POST['password'] ?? '';
$ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$next  = $_POST['next'] ?? $_GET['next'] ?? '';

if (!$email || !$pass) {
    echo json_encode(['ok' => false, 'error' => 'Please fill in all fields.']);
    exit;
}
if (isLoginBlocked($email, $ip)) {
    echo json_encode(['ok' => false, 'error' => 'Too many failed attempts. Please wait 15 minutes.']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = 'provider' AND status = 'active' LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !password_verify($pass, $user['password'])) {
    logLoginAttempt($email, $ip);
    echo json_encode(['ok' => false, 'error' => 'Invalid email or password. Use a Job Seeker account.']);
    exit;
}

session_regenerate_id(true);
clearLoginAttempts($email);
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'];

$redirect = BASE_URL . '/provider-dashboard';
if (is_string($next) && $next !== '' && str_starts_with($next, '/') && !str_starts_with($next, '//')) {
    $path = parse_url($next, PHP_URL_PATH) ?: '';
    if (preg_match('#^/(provider-dashboard|apply|my-applications|settings)(/|$)#', $path)) {
        $redirect = BASE_URL . $next;
    }
}

echo json_encode(['ok' => true, 'redirect' => $redirect]);
