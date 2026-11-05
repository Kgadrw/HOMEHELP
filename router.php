<?php
/**
 * PHP built-in server router
 * Usage: php -S localhost:8000 router.php
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rawurldecode($uri);
$uri = rtrim($uri, '/') ?: '/';

$routes = [
    '/'                => 'index.php',
    '/find-worker'     => 'find-worker.php',
    '/career'          => 'career.php',
    '/login'           => 'auth/login.php',
    '/client-login-api'=> 'auth/client-login-api.php',
    '/register'        => 'auth/register.php',
    '/register-account'=> 'auth/register-account.php',
    '/book-request'    => 'auth/book-request.php',
    '/worker-login'    => 'auth/workerlogin.php',
    '/worker-login-api'=> 'auth/worker-login-api.php',
    '/worker-register' => 'auth/workerregister.php',
    '/staff-login'     => 'auth/adminlogin.php',
    '/admin-login'     => 'auth/adminlogin.php',
    '/admin'               => 'admin/dashboard.php',
    '/admin/bookings'      => 'admin/bookings.php',
    '/admin/applications'  => 'admin/applications.php',
    '/admin/users'         => 'admin/users.php',
    '/admin/services'      => 'admin/services.php',
    '/admin/team'          => 'admin/team.php',
    '/admin/reports'       => 'admin/reports.php',
    '/agent'               => 'agent/dashboard.php',
    '/agent/bookings'      => 'agent/bookings.php',
    '/agent/applications'  => 'agent/applications.php',
    '/agent/reports'       => 'agent/reports.php',
    '/dashboard'           => 'client/dashboard.php',
    '/book'                => 'client/book-service.php',
    '/my-bookings'         => 'client/my-bookings.php',
    '/profile'             => 'client/profile.php',
    '/settings'            => 'settings.php',
    '/provider-dashboard'  => 'provider/dashboard.php',
    '/apply'               => 'provider/apply.php',
    '/my-applications'     => 'provider/my-applications.php',
    '/reset-password'      => 'auth/resetpassword.php',
    '/logout'              => 'auth/logout.php',
    '/google-login'        => 'auth/google-callback.php',
    '/privacy'         => 'privacy.php',
    '/terms'           => 'terms.php',
    '/home-cleaning'   => 'home-cleaning.php',
    '/housekeeping'    => 'housekeeping.php',
    '/home-chef'       => 'home-chef.php',
    '/nanny'           => 'nanny.php',
    '/caregiver'       => 'caregiver.php',
    '/laundry'         => 'laundry.php',
    '/office-cleaning' => 'office-cleaning.php',
];

if (isset($routes[$uri])) {
    $target = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $routes[$uri]);
    chdir(dirname($target));
    require $target;
    return true;
}

$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $uri);
if ($uri !== '/' && is_file($file)) {
    return false;
}

if (is_file($file . '.php')) {
    chdir(dirname($file . '.php'));
    require $file . '.php';
    return true;
}

http_response_code(404);
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404</title></head><body style="font-family:sans-serif;padding:60px;text-align:center"><h1>404</h1><p>Page not found.</p><p><a href="/">Back home</a></p></body></html>';
return true;
