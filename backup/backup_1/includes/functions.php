<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

// ─── AUTH HELPERS ───────────────────────────────────────────────

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['user_role'] !== $role) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function getRedirectByRole($role) {
    $redirects = [
        'admin'    => '/admin/dashboard.php',
        'agent'    => '/agent/dashboard.php',
        'client'   => '/client/dashboard.php',
        'provider' => '/provider/dashboard.php',
    ];
    return BASE_URL . ($redirects[$role] ?? '/index.php');
}

// ─── INPUT / OUTPUT ─────────────────────────────────────────────

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// ─── SECURITY ────────────────────────────────────────────────────

function generateToken() {
    return bin2hex(random_bytes(32));
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}

// ─── FILE UPLOAD ─────────────────────────────────────────────────

function uploadFile($file, $targetDir) {
    $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        throw new Exception('File type not allowed.');
    }
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        throw new Exception('File too large. Maximum size is 5MB.');
    }

    $filename   = uniqid() . '.' . $ext;
    $targetPath = __DIR__ . '/../assets/' . $targetDir . $filename;

    if (!is_dir(dirname($targetPath))) {
        mkdir(dirname($targetPath), 0755, true);
    }

    move_uploaded_file($file['tmp_name'], $targetPath);
    return 'assets/' . $targetDir . $filename;
}

// ─── WHATSAPP ────────────────────────────────────────────────────

function sendWhatsAppMessage($to, $templateName, $components = []) {
    $url  = 'https://graph.facebook.com/v19.0/' . WA_PHONE_ID . '/messages';
    $body = [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'template',
        'template'          => [
            'name'       => $templateName,
            'language'   => ['code' => 'en'],
            'components' => $components,
        ],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST,          true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,    json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER,    [
        'Content-Type: application/json',
        'Authorization: Bearer ' . WA_TOKEN,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function sendBookingNotification($booking_id, $agent_id) {
    global $conn;

    $stmt = $conn->prepare(
        'SELECT b.*, s.name AS service_name, u.name AS client_name, u.phone
         FROM bookings b
         JOIN services s ON b.service_id = s.id
         JOIN users u    ON b.client_id  = u.id
         WHERE b.id = ?'
    );
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

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
        $astmt = $conn->prepare('SELECT whatsapp_number FROM agents WHERE user_id = ?');
        $astmt->bind_param('i', $agent_id);
        $astmt->execute();
        $agent = $astmt->get_result()->fetch_assoc();
        if ($agent) {
            sendWhatsAppMessage($agent['whatsapp_number'], 'new_booking_request', $components);
        }
    }

    // Always notify admin
    sendWhatsAppMessage(ADMIN_WHATSAPP, 'new_booking_request', $components);
}

function notifyClientBookingStatus($booking_id, $status) {
    global $conn;

    $stmt = $conn->prepare(
        'SELECT b.*, u.phone, u.name AS client_name
         FROM bookings b
         JOIN users u ON b.client_id = u.id
         WHERE b.id = ?'
    );
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if ($booking && $booking['phone']) {
        $templateName = ($status === 'approved') ? 'booking_approved' : 'booking_rejected';
        $components   = [[
            'type'       => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => $booking['client_name']],
            ],
        ]];
        sendWhatsAppMessage($booking['phone'], $templateName, $components);
    }
}

// ─── NOTIFICATIONS (in-app) ──────────────────────────────────────

function addNotification($user_id, $type, $message) {
    global $conn;
    $stmt = $conn->prepare(
        'INSERT INTO notifications (user_id, type, message) VALUES (?,?,?)'
    );
    $stmt->bind_param('iss', $user_id, $type, $message);
    $stmt->execute();
}

function getUnreadCount($user_id) {
    global $conn;
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0'
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'];
}
?>