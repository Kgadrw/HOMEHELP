<?php
// ============================================================
//  Home Help — App Config & Helper Functions
//  File: config/config.php
//  DevGuide §1.4, §3.1, §5.2, §6.1, §10.2
//  Brilliant Elite Ltd · Rwanda 🇷🇼
// ============================================================

// ── APP ─────────────────────────────────────────────────────
define('BASE_URL', 'https://homehelp.rw');
define('SITE_NAME',  'Home Help');

// ── WHATSAPP (Meta Cloud API) ────────────────────────────────
define('WA_TOKEN',       '');            // ← Paste your permanent token
define('WA_PHONE_ID',    '');            // ← Your Phone Number ID from Meta
define('ADMIN_WHATSAPP', '250790533144');// ← Admin WhatsApp (no + or spaces)

// ── SECRETS (optional — uses project config/secrets.php) ──
$__secrets = __DIR__ . '/../../../config/secrets.php';
if (is_file($__secrets)) {
    require_once $__secrets;
}

// ── GOOGLE OAUTH ─────────────────────────────────────────────
if (!defined('GOOGLE_CLIENT_ID'))     define('GOOGLE_CLIENT_ID', '');
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', '');
if (!defined('GOOGLE_REDIRECT_URI'))  define('GOOGLE_REDIRECT_URI', BASE_URL . '/auth/google-callback.php');

// ── FILE UPLOADS ─────────────────────────────────────────────
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB

// ── SESSION ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── TIMEZONE ─────────────────────────────────────────────────
date_default_timezone_set('Africa/Kigali');


// ============================================================
//  HELPER FUNCTIONS  (DevGuide §3.1, §5.2, §6.1, §10.2)
// ============================================================

/**
 * Check if user is logged in.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Require login — redirect to login page if not authenticated.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Require a specific role. Accepts string or array of roles.
 * Usage: requireRole('agent')  or  requireRole(['admin','agent'])
 */
function requireRole($role) {
    requireLogin();
    $allowed = is_array($role) ? $role : [$role];
    if (!in_array($_SESSION['user_role'] ?? '', $allowed, true)) {
        http_response_code(403);
        die('<h2 style="font-family:sans-serif;padding:40px;text-align:center;">
            403 — Access Denied
        </h2>');
    }
}

/**
 * Sanitize user input for safe output/storage.
 * DevGuide §3.1
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim((string)$input)));
}

/**
 * Safe HTML output escape.
 */
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect and stop execution.
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Generate a secure random token.
 * DevGuide §3.1
 */
function generateToken() {
    return bin2hex(random_bytes(32));
}

// ── CSRF PROTECTION  (DevGuide §10.2) ────────────────────────
/**
 * Generate CSRF token (stored in session, one per session).
 * Add to every form: <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token on POST.
 * Call at top of every POST handler: verifyCSRF();
 */
function verifyCSRF() {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('CSRF validation failed. Please go back and try again.');
    }
}

// ── BRUTE-FORCE LOGIN PROTECTION  (DevGuide §10.1) ───────────
/**
 * Check if an email/IP has exceeded 5 failed login attempts in 15 minutes.
 */
function isLoginBlocked($email, $ip) {
    global $conn;
    $limit  = 5;
    $window = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as cnt FROM login_attempts
         WHERE (email = ? OR ip_address = ?) AND attempted_at > ?"
    );
    $stmt->bind_param('sss', $email, $ip, $window);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)$row['cnt'] >= $limit;
}

/**
 * Log a failed login attempt.
 */
function logLoginAttempt($email, $ip) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?,?)");
    $stmt->bind_param('ss', $email, $ip);
    $stmt->execute();
}

/**
 * Clear login attempts after successful login.
 */
function clearLoginAttempts($email) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
}

// ── FILE UPLOAD  (DevGuide §5.2) ─────────────────────────────
/**
 * Safely upload a file. Returns stored relative path or throws Exception.
 * $file      = $_FILES['fieldname']
 * $targetDir = 'uploads/cv/'  or  'uploads/ids/'
 */
function uploadFile($file, $targetDir) {
    $allowedExt  = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    $allowedMime = ['application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'image/jpeg', 'image/png'];

    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = mime_content_type($file['tmp_name']);

    if (!in_array($ext, $allowedExt)) {
        throw new Exception('File type not allowed. Accepted: PDF, DOC, DOCX, JPG, PNG.');
    }
    if (!in_array($mime, $allowedMime)) {
        throw new Exception('Invalid file format detected.');
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new Exception('File too large. Maximum size is 5 MB.');
    }

    $filename   = uniqid('hh_', true) . '.' . $ext;
    $targetPath = '../assets/' . $targetDir;

    if (!is_dir($targetPath)) {
        mkdir($targetPath, 0755, true);
    }

    $fullPath = $targetPath . $filename;
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        throw new Exception('Upload failed. Please try again.');
    }

    return 'assets/' . $targetDir . $filename;
}

// ── WHATSAPP  (DevGuide §6.1) ────────────────────────────────
/**
 * Send a WhatsApp template message via Meta Cloud API.
 *
 * @param string $to          Recipient phone, digits only e.g. '250781234567'
 * @param string $templateName Approved Meta template name
 * @param array  $components  Template parameter components array
 * @return array              API response as array
 */
function sendWhatsAppMessage($to, $templateName, $components = []) {
    if (!WA_PHONE_ID || !WA_TOKEN) return [];

    $url  = 'https://graph.facebook.com/v21.0/' . WA_PHONE_ID . '/messages';
    $body = [
        'messaging_product' => 'whatsapp',
        'to'                => preg_replace('/\D/', '', $to),
        'type'              => 'template',
        'template'          => [
            'name'       => $templateName,
            'language'   => ['code' => 'en'],
            'components' => $components,
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . WA_TOKEN,
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}

/**
 * Notify agent + admin when a new booking is submitted.
 * DevGuide §6.2
 */
function sendBookingNotification($booking_id, $agent_id) {
    global $conn;

    $stmt = $conn->prepare(
        "SELECT b.*, s.name AS service_name, u.name AS client_name, u.phone AS client_phone
         FROM bookings b
         JOIN services s ON b.service_id = s.id
         JOIN users u    ON b.client_id  = u.id
         WHERE b.id = ?"
    );
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    if (!$booking) return;

    $components = [[
        'type'       => 'body',
        'parameters' => [
            ['type' => 'text', 'text' => $booking['client_name']],
            ['type' => 'text', 'text' => $booking['service_name']],
            ['type' => 'text', 'text' => $booking['location']],
            ['type' => 'text', 'text' => $booking['start_date']],
        ],
    ]];

    // Notify assigned agent
    if ($agent_id) {
        $agentStmt = $conn->prepare("SELECT whatsapp_number FROM agents WHERE user_id = ?");
        $agentStmt->bind_param('i', $agent_id);
        $agentStmt->execute();
        $agent = $agentStmt->get_result()->fetch_assoc();
        if ($agent && $agent['whatsapp_number']) {
            sendWhatsAppMessage($agent['whatsapp_number'], 'new_booking_request', $components);
        }
    }

    // Always notify admin
    sendWhatsAppMessage(ADMIN_WHATSAPP, 'new_booking_request', $components);

    // Log notification in DB
    logNotification($booking['client_id'] ?? null,
        'new_booking',
        "Your booking for {$booking['service_name']} on {$booking['start_date']} has been submitted and is under review."
    );
}

/**
 * Notify client when booking status changes.
 */
function notifyClientBookingStatus($booking_id, $new_status) {
    global $conn;

    $stmt = $conn->prepare(
        "SELECT b.*, s.name AS service_name, u.name AS client_name, u.phone AS client_phone, u.id AS client_id
         FROM bookings b
         JOIN services s ON b.service_id = s.id
         JOIN users u    ON b.client_id  = u.id
         WHERE b.id = ?"
    );
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    if (!$booking) return;

    $statusLabel = ucfirst(str_replace('_', ' ', $new_status));
    $message = "Your booking for {$booking['service_name']} has been {$statusLabel}.";

    if ($booking['client_phone']) {
        $components = [[
            'type'       => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => $booking['client_name']],
                ['type' => 'text', 'text' => $booking['service_name']],
                ['type' => 'text', 'text' => $statusLabel],
            ],
        ]];
        sendWhatsAppMessage($booking['client_phone'], 'booking_status_update', $components);
    }

    logNotification($booking['client_id'], 'booking_' . $new_status, $message);
}

/**
 * Log notification to notifications table.
 */
function logNotification($user_id, $type, $message) {
    global $conn;
    if (!$user_id) return;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?,?,?)");
    $stmt->bind_param('iss', $user_id, $type, $message);
    $stmt->execute();
}

// ── DISPLAY HELPERS ──────────────────────────────────────────
/**
 * Get unread notification count for current user.
 */
function unreadNotifCount() {
    global $conn;
    if (!isLoggedIn()) return 0;
    $id   = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_row()[0];
}

/**
 * Render a status badge as HTML span.
 */
function statusBadge($status) {
    $map = [
        'pending'      => ['Pending',      '#F59E0B', '#FFF7ED'],
        'under_review' => ['Under Review', '#3B82F6', '#EFF6FF'],
        'approved'     => ['Approved',     '#10B981', '#ECFDF5'],
        'completed'    => ['Completed',    '#0D3B2E', '#D6EDE4'],
        'rejected'     => ['Rejected',     '#EF4444', '#FEF2F2'],
        'active'       => ['Active',       '#2E8B5F', '#D6EDE4'],
        'blocked'      => ['Blocked',      '#EF4444', '#FEF2F2'],
        'inactive'     => ['Inactive',     '#9CA3AF', '#F3F4F6'],
    ];
    $s = $map[$status] ?? [ucfirst($status), '#888', '#f3f4f6'];
    return '<span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:0.76rem;'
         . 'font-weight:700;background:' . $s[2] . ';color:' . $s[1] . ';">'
         . e($s[0]) . '</span>';
}

/**
 * Format currency in RWF.
 */
function formatRWF($amount) {
    if ($amount === null || $amount === '') return '—';
    return number_format((float)$amount, 0) . ' RWF';
}

/**
 * Format date for display.
 */
function formatDate($date, $format = 'D, d M Y') {
    if (!$date) return '—';
    return date($format, strtotime($date));
}

/**
 * Flash message — set once, shown once.
 */
function flashSet($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function flashGet() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}