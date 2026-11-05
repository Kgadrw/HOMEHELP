<?php
require_once '../config/db.php';
require_once '../config/config.php';

// Check role BEFORE destroying session
$role = $_SESSION['user_role'] ?? 'client';

// Destroy session completely
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

// Redirect based on role
if ($role === 'provider') {
    redirect(BASE_URL . '/worker-login');
} elseif ($role === 'admin' || $role === 'agent') {
    redirect(BASE_URL . '/staff-login');
} else {
    redirect(BASE_URL . '/login');
}