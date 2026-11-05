<?php
require_once '../config/db.php';
require_once '../config/config.php';

// ── GOOGLE OAUTH CONFIG ───────────────────────────────────────
$google_client_id     = GOOGLE_CLIENT_ID;
$google_client_secret = GOOGLE_CLIENT_SECRET;
$google_redirect_uri  = GOOGLE_REDIRECT_URI !== '' ? GOOGLE_REDIRECT_URI : 'https://homehelp.rw/google-login';

if ($google_client_id === '' || $google_client_secret === '') {
    http_response_code(500);
    exit('Google OAuth is not configured. Add credentials in config/secrets.php.');
}

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'client';
    redirect(BASE_URL . clean_redirect($role));
}

// ── STEP 1: No code yet — redirect to Google ─────────────────
if (!isset($_GET['code'])) {

    // Get role from query param — default to client
    $role = ($_GET['role'] ?? 'client') === 'provider' ? 'provider' : 'client';
    $_SESSION['google_role'] = $role;

    // Generate state token to prevent CSRF
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_state'] = $state;

    $params = http_build_query([
        'client_id'     => $google_client_id,
        'redirect_uri'  => $google_redirect_uri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ]);

    redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
}

// ── STEP 2: Google redirected back with code ──────────────────

// Verify state to prevent CSRF
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['google_state'] ?? '')) {
    flashSet('error', 'Invalid state. Please try again.');
    redirect(BASE_URL . '/login');
}
unset($_SESSION['google_state']);

// ── FIX: Read role from session (set before redirect to Google)
$role = (($_SESSION['google_role'] ?? 'client') === 'provider') ? 'provider' : 'client';
unset($_SESSION['google_role']);

// Exchange code for access token
$token_response = googlePost('https://oauth2.googleapis.com/token', [
    'code'          => $_GET['code'],
    'client_id'     => $google_client_id,
    'client_secret' => $google_client_secret,
    'redirect_uri'  => $google_redirect_uri,
    'grant_type'    => 'authorization_code',
]);

if (!isset($token_response['access_token'])) {
    flashSet('error', 'Google login failed. Please try again.');
    redirect(BASE_URL . '/login');
}

// Get user info from Google
$user_info = googleGet(
    'https://www.googleapis.com/oauth2/v2/userinfo',
    $token_response['access_token']
);

if (!isset($user_info['email'])) {
    flashSet('error', 'Could not retrieve your Google account info.');
    redirect(BASE_URL . '/login');
}

$google_id    = $user_info['id']             ?? '';
$email        = $user_info['email']          ?? '';
$name         = $user_info['name']           ?? 'User';
$google_photo = $user_info['picture']        ?? '';
$verified     = $user_info['verified_email'] ?? false;

if (!$verified) {
    flashSet('error', 'Your Google email is not verified. Please verify it and try again.');
    redirect(BASE_URL . '/login');
}

// ── STEP 3: Find or create user ───────────────────────────────

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    // User exists — log them in with their EXISTING role (never override)
    if ($user['status'] !== 'active') {
        flashSet('error', 'Your account has been suspended. Please contact support.');
        $login_page = ($role === 'provider') ? '/worker-login' : '/login';
        redirect(BASE_URL . $login_page);
    }

    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];

    $redirects = [
        'admin'    => '/admin',
        'agent'    => '/agent',
        'client'   => '/dashboard',
        'provider' => '/provider-dashboard',
    ];
    redirect(BASE_URL . ($redirects[$user['role']] ?? '/dashboard'));

} else {
    // New user — create with the role detected from session
    $random_pass = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $phone       = '';

    $stmt = $conn->prepare("
        INSERT INTO users (name, email, phone, password, role, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    $stmt->bind_param('sssss', $name, $email, $phone, $random_pass, $role);

    if ($stmt->execute()) {
        $new_id = $conn->insert_id;

        session_regenerate_id(true);
        $_SESSION['user_id']   = $new_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_role'] = $role;

        flashSet('success', 'Welcome to Home Help! Your account has been created.');

        // ── FIX: Redirect to correct dashboard based on role
        $redirects = [
            'provider' => '/provider-dashboard',
            'client'   => '/dashboard',
        ];
        redirect(BASE_URL . ($redirects[$role] ?? '/dashboard'));

    } else {
        flashSet('error', 'Failed to create your account. Please try again.');
        redirect(BASE_URL . '/login');
    }
}

// ── HELPER FUNCTIONS ──────────────────────────────────────────
function googlePost($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}

function googleGet($url, $access_token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$access_token}"]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}