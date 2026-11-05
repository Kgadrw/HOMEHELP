<?php
/**
 * Hire / booking request (JSON). Used by the in-page book modal.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired. Refresh the page and try again.']);
    exit;
}

$services = $conn->query("
    SELECT id, name FROM services WHERE status = 'active' ORDER BY sort_order ASC, id ASC
")->fetch_all(MYSQLI_ASSOC);

$fees_raw = $conn->query("SELECT * FROM service_fees")->fetch_all(MYSQLI_ASSOC);
$fees = [];
foreach ($fees_raw as $f) {
    $fees[$f['service_id']][$f['fee_key']] = $f['amount'];
}

$selected_ids          = array_map('intval', $_POST['service_ids'] ?? []);
$selected_ids          = array_values(array_filter($selected_ids));
$selected_services_str = implode(',', $selected_ids);

$rooms         = (int)($_POST['rooms'] ?? 0);
$outdoor_areas = isset($_POST['outdoor_areas']) ? 1 : 0;
$has_car       = isset($_POST['has_car']) ? 1 : 0;
$has_pool      = isset($_POST['has_pool']) ? 1 : 0;
$dish_washing  = isset($_POST['dish_washing']) ? 1 : 0;
$has_pets      = isset($_POST['has_pets']) ? 1 : 0;
$laundry       = isset($_POST['laundry']) ? 1 : 0;
$chef_type     = sanitize($_POST['chef_type'] ?? '');
$num_people    = (int)($_POST['num_people'] ?? 0);
$num_adults    = (int)($_POST['num_adults'] ?? 0);
$needs_menu    = (int)($_POST['needs_menu'] ?? 0);

$frequency = sanitize($_POST['frequency'] ?? '');
$duration  = sanitize($_POST['duration'] ?? '');

$languages   = implode(',', array_map('sanitize', $_POST['languages'] ?? []));
$live_in     = isset($_POST['live_in']) ? 1 : 0;
$live_out    = isset($_POST['live_out']) ? 1 : 0;
$living_area = sanitize($_POST['living_area'] ?? '');

$name          = sanitize($_POST['name'] ?? '');
$phone         = sanitize($_POST['phone'] ?? '');
$email         = sanitize($_POST['email'] ?? '');
$neighbourhood = sanitize($_POST['neighbourhood'] ?? '');

$location   = $neighbourhood ?: 'Not specified';
$district   = 'Not specified';
$start_date = date('Y-m-d');
$budget     = '';

$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

if (empty($selected_ids)) {
    echo json_encode(['ok' => false, 'error' => 'Please select at least one service.']);
    exit;
}
if (!$frequency) {
    echo json_encode(['ok' => false, 'error' => 'Please choose how often you need help.']);
    exit;
}

$user_id   = null;
$user_name = $name;
$logged_in = isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'client');

if ($logged_in) {
    $user_id   = (int)$_SESSION['user_id'];
    $user_name = $_SESSION['user_name'] ?? $name;
    if (!$name) {
        $name = $user_name;
    }
} else {
    if (!$name || !$phone || !$email || !$password || !$confirm) {
        echo json_encode(['ok' => false, 'error' => 'Please fill in your contact details and password.']);
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
    $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $chk->bind_param('s', $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo json_encode(['ok' => false, 'error' => 'This email is already registered. Sign in, then book again.']);
        exit;
    }
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $role   = 'client';
    $ins = $conn->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?,?,?,?,?,'active')");
    $ins->bind_param('sssss', $name, $email, $phone, $hashed, $role);
    if (!$ins->execute()) {
        echo json_encode(['ok' => false, 'error' => 'Could not create account. Please try again.']);
        exit;
    }
    $user_id = (int)$conn->insert_id;
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user_id;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_role'] = 'client';
    $user_name = $name;
}

$calculated_price = 0.0;
foreach ($selected_ids as $sid) {
    $f = $fees[$sid] ?? [];
    if ($sid == 6 || $sid == 7) {
        $calculated_price += (float)($f['base'] ?? 0);
        $calculated_price += (float)($f['per_room'] ?? 0) * max(1, $rooms);
        if ($outdoor_areas) $calculated_price += (float)($f['outdoor'] ?? 0);
        if ($has_car)       $calculated_price += (float)($f['car'] ?? 0);
        if ($has_pool)      $calculated_price += (float)($f['pool'] ?? 0);
        if ($dish_washing)  $calculated_price += (float)($f['dishes'] ?? 0);
        if ($has_pets)      $calculated_price += (float)($f['pets'] ?? 0);
        if ($sid == 7 && $laundry) $calculated_price += (float)($f['laundry'] ?? 0);
    }
    if ($sid == 9) {
        if ($chef_type === 'events') {
            $calculated_price += (float)($f['event_base'] ?? 0);
            $calculated_price += (float)($f['per_person'] ?? 0) * max(1, $num_people);
            if ($needs_menu) $calculated_price += (float)($f['menu'] ?? 0);
        } else {
            $calculated_price += (float)($f['home_base'] ?? 0);
            $calculated_price += (float)($f['home_per_person'] ?? 0) * max(1, $num_people);
        }
    }
}

$svc_names = [];
foreach ($services as $sv) {
    if (in_array((int)$sv['id'], $selected_ids, true)) {
        $svc_names[] = $sv['name'];
    }
}
$service_name = implode(' + ', $svc_names);

foreach ($selected_ids as $sid) {
    $pending = 'pending';
    $reqs    = '';
    $bstmt = $conn->prepare("
        INSERT INTO bookings
            (client_id, service_id, location, district, neighbourhood,
             start_date, duration, frequency, requirements,
             rooms, outdoor_areas, has_car, has_pool, dish_washing,
             has_pets, laundry, chef_type, num_people, num_adults,
             needs_menu, languages, live_in, live_out, living_area,
             calculated_price, selected_services, budget, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $bstmt->bind_param(
        'iisssssssiiiiiiisiiisiisdsss',
        $user_id, $sid, $location, $district, $neighbourhood,
        $start_date, $duration, $frequency, $reqs,
        $rooms, $outdoor_areas, $has_car, $has_pool, $dish_washing,
        $has_pets, $laundry, $chef_type, $num_people, $num_adults,
        $needs_menu, $languages, $live_in, $live_out, $living_area,
        $calculated_price, $selected_services_str, $budget, $pending
    );
    if (!$bstmt->execute()) {
        echo json_encode(['ok' => false, 'error' => 'Could not save booking. Please try again.']);
        exit;
    }
}

$admin = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch_assoc();
if ($admin) {
    logNotification($admin['id'], 'new_booking', "New booking from {$user_name} for {$service_name}.");
}
logNotification($user_id, 'booking_submitted', 'Your booking has been submitted! Our team will review it shortly.');

try {
    require_once __DIR__ . '/../includes/mailer.php';
    $email_data = [
        'service_name'     => $service_name,
        'user_name'        => $user_name,
        'phone'            => $phone,
        'email'            => $email,
        'neighbourhood'    => $neighbourhood,
        'frequency'        => $frequency,
        'languages'        => $languages,
        'rooms'            => $rooms,
        'chef_type'        => $chef_type,
        'num_people'       => $num_people,
        'live_in'          => $live_in,
        'live_out'         => $live_out,
        'living_area'      => $living_area,
        'calculated_price' => $calculated_price,
    ];
    sendBookingEmail(
        ['bookings@homehelp.rw', 'homehelpservicerw@gmail.com'],
        'New Booking - Home Help Rwanda',
        buildBookingEmailHTML($email_data)
    );
} catch (Throwable $e) {
    error_log('[Booking] Email failed: ' . $e->getMessage());
}

$_SESSION['booking_success'] = true;
echo json_encode(['ok' => true, 'redirect' => BASE_URL . '/dashboard']);
