<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('client');

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ── FETCH LOGGED-IN USER PROFILE ──────────────────────────────
$ustmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ? LIMIT 1");
$ustmt->bind_param('i', $user_id);
$ustmt->execute();
$profile = $ustmt->get_result()->fetch_assoc();
$p_name  = $profile['name']  ?? $user_name;
$p_email = $profile['email'] ?? '';
$p_phone = $profile['phone'] ?? '';

// ── FETCH SERVICES & FEES ─────────────────────────────────────
$services = $conn->query("
    SELECT id, name, icon FROM services
    WHERE status = 'active'
    ORDER BY sort_order ASC, id ASC
")->fetch_all(MYSQLI_ASSOC);

$fees_raw = $conn->query("SELECT * FROM service_fees")->fetch_all(MYSQLI_ASSOC);
$fees = [];
foreach ($fees_raw as $f) {
    $fees[$f['service_id']][$f['fee_key']] = $f['amount'];
}

// ── BOOKING HANDLER ───────────────────────────────────────────
$booking_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wizard_submit'])) {
    verifyCSRF();

    $selected_ids          = array_map('intval', $_POST['service_ids'] ?? []);
    $selected_services_str = implode(',', $selected_ids);

    $rooms         = (int)($_POST['rooms']        ?? 0);
    $outdoor_areas = isset($_POST['outdoor_areas']) ? 1 : 0;
    $has_car       = isset($_POST['has_car'])       ? 1 : 0;
    $has_pool      = isset($_POST['has_pool'])      ? 1 : 0;
    $dish_washing  = isset($_POST['dish_washing'])  ? 1 : 0;
    $has_pets      = isset($_POST['has_pets'])      ? 1 : 0;
    $laundry       = isset($_POST['laundry'])       ? 1 : 0;
    $chef_type     = sanitize($_POST['chef_type']   ?? '');
    $num_people    = (int)($_POST['num_people']     ?? 0);
    $num_adults    = (int)($_POST['num_adults']     ?? 0);
    $needs_menu    = (int)($_POST['needs_menu']     ?? 0);
    $frequency     = sanitize($_POST['frequency']   ?? '');
    $duration      = sanitize($_POST['duration']    ?? '');
    $languages     = implode(',', $_POST['languages'] ?? []);
    $live_in       = isset($_POST['live_in'])  ? 1 : 0;
    $live_out      = isset($_POST['live_out']) ? 1 : 0;
    $living_area   = sanitize($_POST['living_area'] ?? '');
    $neighbourhood = sanitize($_POST['neighbourhood'] ?? '');
    $location      = $neighbourhood ?: 'Not specified';
    $district      = 'Not specified';
    $start_date    = date('Y-m-d');
    $budget        = '';
    $name          = $p_name;
    $phone         = $p_phone;
    $email         = $p_email;

    if (empty($selected_ids)) {
        $booking_error = 'Please select at least one service.';
    } elseif (!$frequency) {
        $booking_error = 'Please select how often you need the service.';
    } else {
        // ── CALCULATE PRICE ───────────────────────────────────
        $calculated_price = 0.0;
        foreach ($selected_ids as $sid) {
            $f = $fees[$sid] ?? [];
            if ($sid == 6 || $sid == 7) {
                $calculated_price += (float)($f['base']     ?? 0);
                $calculated_price += (float)($f['per_room'] ?? 0) * max(1, $rooms);
                if ($outdoor_areas) $calculated_price += (float)($f['outdoor'] ?? 0);
                if ($has_car)       $calculated_price += (float)($f['car']     ?? 0);
                if ($has_pool)      $calculated_price += (float)($f['pool']    ?? 0);
                if ($dish_washing)  $calculated_price += (float)($f['dishes']  ?? 0);
                if ($has_pets)      $calculated_price += (float)($f['pets']    ?? 0);
                if ($sid == 7 && $laundry) $calculated_price += (float)($f['laundry'] ?? 0);
            }
            if ($sid == 9) {
                if ($chef_type === 'events') {
                    $calculated_price += (float)($f['event_base'] ?? 0);
                    $calculated_price += (float)($f['per_person'] ?? 0) * max(1, $num_people);
                    if ($needs_menu) $calculated_price += (float)($f['menu'] ?? 0);
                } else {
                    $calculated_price += (float)($f['home_base']       ?? 0);
                    $calculated_price += (float)($f['home_per_person'] ?? 0) * max(1, $num_people);
                }
            }
        }

        // ── SERVICE NAMES ─────────────────────────────────────
        $svc_names = [];
        foreach ($services as $sv) {
            if (in_array($sv['id'], $selected_ids)) $svc_names[] = $sv['name'];
        }
        $service_name = implode(' + ', $svc_names);

        // ── INSERT BOOKINGS ───────────────────────────────────
        $insert_ok = true;
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
                $booking_error = 'Booking save failed: ' . $bstmt->error;
                $insert_ok = false;
                break;
            }
        }

        if ($insert_ok) {
            // ── NOTIFICATIONS ─────────────────────────────────
            $admin = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch_assoc();
            if ($admin) {
                logNotification($admin['id'], 'new_booking',
                    "New booking from {$name} for {$service_name}.");
            }
            logNotification($user_id, 'booking_submitted',
                'Your booking has been submitted! Our team will review it shortly.');

            // ── EMAIL NOTIFICATION ────────────────────────────
            require_once '../includes/mailer.php';
            $email_data = [
                'service_name'     => $service_name,
                'user_name'        => $name,
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
            sendBookingEmail('homehelpservicerw@gmail.com',
                '🏠 New Booking — Home Help Rwanda',
                buildBookingEmailHTML($email_data));

            // ── REDIRECT WITH SUCCESS ─────────────────────────
            $_SESSION['booking_success'] = true;
            redirect(BASE_URL . '/dashboard');
        }
    }
}

$fees_json     = json_encode($fees);
$services_json = json_encode(array_column($services, 'name', 'id'));
$notif_count   = unreadNotifCount();

// Total steps for logged-in user = 5 (no account creation step)
$TOTAL_STEPS = 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Book a Service | Home Help Rwanda</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --navy:#0D1B4B;--navy-mid:#152268;--navy-light:#2E5FC2;--navy-soft:#D4DCF5;
  --amber:#E8A020;--amber-light:#FFF0CC;
  --cream:#F4F6FB;--cream-dark:#E8ECF4;--white:#FFFFFF;
  --text-dark:#1A1A2E;--text-mid:#4A5568;--text-soft:#8A95A3;
  --danger:#EF4444;--danger-bg:#FEF2F2;
  --success:#10B981;--success-bg:#ECFDF5;
  --shadow-card:0 4px 24px rgba(13,27,75,0.10);
  --shadow-lg:0 16px 48px rgba(13,27,75,0.14);
  --radius:14px;--radius-sm:8px;--sidebar:260px;
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden}
a{text-decoration:none;color:inherit}

/* ── LAYOUT ── */
.dash-wrap{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar);flex-shrink:0;background:var(--navy);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;transition:transform 0.3s ease;overflow-y:auto}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:24px 20px 20px;border-bottom:1px solid rgba(255,255,255,0.08)}
.sidebar-logo img{width:36px;height:36px;object-fit:contain}
.sidebar-logo-text{font-family:var(--font-display);font-size:1.3rem;font-weight:700;color:var(--white)}
.sidebar-user{padding:20px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;gap:12px}
.user-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--navy-light),var(--amber));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--white);flex-shrink:0}
.user-info strong{display:block;font-size:0.88rem;font-weight:700;color:var(--white);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-info span{font-size:0.73rem;color:rgba(255,255,255,0.5)}
.sidebar-nav{padding:16px 0;flex:1}
.nav-section-label{font-size:0.66rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.3);padding:12px 20px 6px}
.nav-item{display:flex;align-items:center;gap:12px;padding:11px 20px;font-size:0.87rem;font-weight:500;color:rgba(255,255,255,0.65);cursor:pointer;transition:all 0.2s;position:relative;text-decoration:none}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,0.06)}
.nav-item.active{color:var(--white);background:rgba(255,255,255,0.1);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--amber);border-radius:0 3px 3px 0}
.nav-icon{font-size:1.05rem;width:20px;text-align:center;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--amber);color:var(--white);font-size:0.65rem;font-weight:700;padding:2px 6px;border-radius:99px}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,0.08)}
.btn-logout{display:flex;align-items:center;gap:8px;width:100%;padding:10px 14px;border-radius:var(--radius-sm);background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.2);color:rgba(255,100,100,0.9);font-size:0.84rem;font-weight:600;cursor:pointer;transition:all 0.2s;text-decoration:none;font-family:var(--font-body)}
.btn-logout:hover{background:rgba(239,68,68,0.2)}
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--white);border-bottom:1px solid var(--cream-dark);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar-left{display:flex;align-items:center;gap:16px}
.burger{display:none;background:none;border:none;cursor:pointer;padding:6px;flex-direction:column;gap:4px}
.burger span{display:block;width:20px;height:2px;background:var(--text-dark);border-radius:2px;transition:all 0.3s}
.topbar-title{font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--navy)}
.topbar-right{display:flex;align-items:center;gap:12px}
.topbar-notif{position:relative;width:38px;height:38px;border-radius:var(--radius-sm);background:var(--cream);border:1.5px solid var(--cream-dark);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.1rem;transition:all 0.2s;text-decoration:none}
.topbar-notif:hover{border-color:var(--navy-light);background:var(--navy-soft)}
.notif-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:var(--danger);border:2px solid var(--white)}
.btn-back-top{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:99px;background:var(--cream);border:1.5px solid var(--cream-dark);color:var(--text-mid);font-size:0.83rem;font-weight:600;transition:all 0.25s;text-decoration:none}
.btn-back-top:hover{border-color:var(--navy-light);color:var(--navy)}
.content{padding:28px;flex:1}

/* ── WIZARD CARD ── */
.step-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow-lg);overflow:hidden;max-width:780px;margin:0 auto}
.prog-track{height:3px;background:var(--cream-dark)}
.prog-fill{height:3px;background:linear-gradient(90deg,var(--navy-light),var(--navy));transition:width 0.45s ease;border-radius:0 2px 2px 0}
.step-head{display:flex;align-items:center;justify-content:space-between;padding:22px 32px 0}
.step-head-title{font-family:var(--font-display);font-size:1.25rem;font-weight:700;color:var(--navy)}
.step-head-count{font-size:0.76rem;color:var(--text-soft);font-weight:500}
.step-inds{display:flex;align-items:center;padding:16px 32px 0}
.sind{display:flex;flex-direction:column;align-items:center;flex:1;position:relative}
.sind:not(:last-child)::after{content:'';position:absolute;top:12px;left:calc(50% + 12px);right:calc(-50% + 12px);height:2px;background:var(--cream-dark);z-index:0;transition:background 0.3s}
.sind.done:not(:last-child)::after{background:var(--navy-light)}
.sind-circle{width:24px;height:24px;border-radius:50%;border:2px solid var(--cream-dark);background:var(--white);display:flex;align-items:center;justify-content:center;font-size:0.65rem;font-weight:700;color:var(--text-soft);position:relative;z-index:1;transition:all 0.3s}
.sind.active .sind-circle{border-color:var(--navy);background:var(--navy);color:var(--white)}
.sind.done .sind-circle{border-color:var(--navy-light);background:var(--navy-light);color:var(--white)}
.sind-label{font-size:0.58rem;font-weight:600;color:var(--text-soft);margin-top:4px;text-align:center}
.sind.active .sind-label{color:var(--navy);font-weight:700}
.sind.done .sind-label{color:var(--navy-light)}
.step-body{padding:22px 32px 28px}
.step-q{font-size:1rem;font-weight:700;color:var(--text-dark);margin-bottom:3px}
.step-hint{font-size:0.77rem;color:var(--text-soft);margin-bottom:18px}
.wp{display:none}
.wp.on{display:block;animation:fadeUp 0.3s ease both}

/* ── SERVICE CARDS ── */
.svc-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.svc-lbl{position:relative;cursor:pointer}
.svc-lbl input[type="checkbox"]{position:absolute;opacity:0;width:0;height:0}
.svc-box{border:2px solid var(--cream-dark);border-radius:10px;padding:16px 10px;text-align:center;background:var(--cream);transition:all 0.22s;position:relative}
.svc-lbl input:checked + .svc-box{border-color:var(--navy);background:var(--navy-soft);box-shadow:0 0 0 3px rgba(13,27,75,0.07)}
.svc-box:hover{border-color:var(--navy-light);background:var(--navy-soft)}
.svc-emo{font-size:1.8rem;display:block;margin-bottom:6px}
.svc-nm{font-size:0.73rem;font-weight:700;color:var(--navy)}
.svc-tick{position:absolute;top:6px;right:6px;width:16px;height:16px;border-radius:50%;background:var(--navy);color:var(--white);font-size:0.55rem;display:none;align-items:center;justify-content:center}
.svc-lbl input:checked + .svc-box .svc-tick{display:flex}

/* ── DYNAMIC STEP 2 ── */
.svc-section{border:1.5px solid var(--cream-dark);border-radius:10px;padding:18px 20px;margin-bottom:14px}
.svc-section:last-child{margin-bottom:0}
.svc-section-title{font-size:0.82rem;font-weight:700;color:var(--navy);margin-bottom:14px;display:flex;align-items:center;gap:8px}
.check-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px}
.check-item{display:flex;align-items:center;gap:8px;font-size:0.82rem;color:var(--text-mid);cursor:pointer;padding:8px 12px;border:1.5px solid var(--cream-dark);border-radius:8px;transition:all 0.2s}
.check-item:hover{border-color:var(--navy-light);background:var(--navy-soft)}
.check-item input[type="checkbox"]{width:15px;height:15px;accent-color:var(--navy);flex-shrink:0}
.check-item.checked{border-color:var(--navy);background:var(--navy-soft)}
.chef-type-opts{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px}
.chef-type-lbl{cursor:pointer}
.chef-type-lbl input[type="radio"]{display:none}
.chef-type-box{border:2px solid var(--cream-dark);border-radius:10px;padding:14px;text-align:center;background:var(--cream);transition:all 0.22s;font-size:0.84rem;font-weight:600;color:var(--text-mid)}
.chef-type-lbl input:checked + .chef-type-box{border-color:var(--navy);background:var(--navy-soft);color:var(--navy)}
.chef-fields{display:none}
.chef-fields.on{display:block;margin-top:12px}

/* ── FREQUENCY ── */
.freq-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
.freq-lbl{cursor:pointer}
.freq-lbl input[type="radio"]{display:none}
.freq-box{border:1.5px solid var(--cream-dark);border-radius:10px;padding:13px;text-align:center;background:var(--cream);font-size:0.84rem;font-weight:600;color:var(--text-mid);transition:all 0.22s}
.freq-lbl input:checked + .freq-box{border-color:var(--navy);background:var(--navy-soft);color:var(--navy)}
.freq-box:hover{border-color:var(--navy-light);background:var(--navy-soft)}

/* ── LANGUAGE PILLS ── */
.lang-pills{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px}
.lang-lbl{cursor:pointer}
.lang-lbl input[type="checkbox"]{display:none}
.lang-pill{padding:9px 18px;border-radius:99px;border:1.5px solid var(--cream-dark);background:var(--cream);font-size:0.82rem;font-weight:600;color:var(--text-mid);transition:all 0.22s}
.lang-lbl input:checked + .lang-pill{border-color:var(--navy);background:var(--navy);color:var(--white)}
.lang-pill:hover{border-color:var(--navy-light);background:var(--navy-soft)}
.extra-details{border:1.5px solid var(--cream-dark);border-radius:10px;padding:16px 20px;margin-top:16px}
.extra-title{font-size:0.78rem;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:12px}
.live-opts{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.living-area-field{margin-top:12px;display:none}
.living-area-field.on{display:block}

/* ── STEP 4 — PRE-FILLED PROFILE ── */
.profile-banner{background:var(--success-bg);border:1.5px solid #BBF7D0;border-radius:10px;padding:14px 18px;display:flex;align-items:center;gap:12px;margin-bottom:20px;font-size:0.84rem;color:#065F46}
.profile-card{background:var(--cream);border:1.5px solid var(--cream-dark);border-radius:10px;padding:18px 20px;margin-bottom:16px}
.profile-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--cream-dark)}
.profile-row:last-child{border-bottom:none}
.profile-icon{font-size:1rem;width:24px;text-align:center;flex-shrink:0}
.profile-label{font-size:0.75rem;font-weight:700;color:var(--text-soft);min-width:80px}
.profile-value{font-size:0.88rem;font-weight:600;color:var(--text-dark)}
.form-group{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
.form-group label{font-size:0.8rem;font-weight:600;color:var(--text-dark)}
.iw{position:relative}
.iico{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:0.85rem;pointer-events:none;z-index:1}
.form-group input,.form-group select{width:100%;padding:11px 14px 11px 38px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.86rem;color:var(--text-dark);background:var(--cream);transition:all 0.2s;outline:none}
.form-group input:focus,.form-group select:focus{border-color:var(--navy-light);background:var(--white);box-shadow:0 0 0 3px rgba(46,95,194,0.1)}

/* ── PRICE BADGE ── */
.price-badge{background:linear-gradient(135deg,var(--navy),var(--navy-mid));border-radius:10px;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.price-badge-label{font-size:0.78rem;color:rgba(255,255,255,0.7);font-weight:500}
.price-badge-amount{font-family:var(--font-display);font-size:1.6rem;font-weight:700;color:var(--white)}
.price-badge-sub{font-size:0.7rem;color:rgba(255,255,255,0.5)}

/* ── SUMMARY ── */
.summ-box{background:var(--cream);border:1.5px solid var(--cream-dark);border-radius:10px;padding:16px 20px;margin-bottom:16px}
.summ-title{font-size:0.7rem;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px}
.summ-row{display:flex;gap:10px;font-size:0.82rem;color:var(--text-mid);margin-bottom:5px;align-items:flex-start}
.summ-row:last-child{margin-bottom:0}
.summ-row strong{color:var(--navy);font-weight:600;min-width:100px;flex-shrink:0}

/* ── NAV BUTTONS ── */
.wiz-nav{display:flex;gap:10px;margin-top:20px}
.btn-next{flex:1;padding:12px 20px;border-radius:var(--radius-sm);background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:0.88rem;font-weight:700;border:none;cursor:pointer;transition:all 0.3s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-next:hover{background:var(--navy-mid);transform:translateY(-2px);box-shadow:var(--shadow-card)}
.btn-next.loading{opacity:0.7;cursor:not-allowed;pointer-events:none}
.btn-back-w{padding:12px 18px;border-radius:var(--radius-sm);background:transparent;border:1.5px solid var(--cream-dark);color:var(--text-mid);font-family:var(--font-body);font-size:0.88rem;font-weight:600;cursor:pointer;transition:all 0.25s}
.btn-back-w:hover{border-color:var(--navy-light);color:var(--navy)}
.alert{padding:11px 14px;border-radius:var(--radius-sm);font-size:0.83rem;font-weight:500;margin-bottom:14px;display:flex;align-items:flex-start;gap:8px}
.alert-error{background:var(--danger-bg);border:1.5px solid #FECACA;color:#B91C1C}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:199}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .content{padding:18px}.topbar{padding:0 16px}
  .step-body{padding:18px 18px 22px}.step-head{padding:16px 18px 0}.step-inds{padding:12px 18px 0}
  .svc-grid{grid-template-columns:repeat(2,1fr)}.check-grid{grid-template-columns:1fr}
  .chef-type-opts{grid-template-columns:1fr}.live-opts{grid-template-columns:1fr}
  .freq-grid{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>
<div class="dash-wrap">
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="../Image/icon/homehelp_logo_transparent_white.svg" alt="Home Help" onerror="this.style.display='none'"/>
      <span class="sidebar-logo-text">Home Help</span>
    </div>
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($user_name,0,1)) ?></div>
      <div class="user-info">
        <strong><?= e($user_name) ?></strong>
        <span>Client</span>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Main</div>
      <a href="<?= BASE_URL ?>/dashboard"  class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
      <a href="<?= BASE_URL ?>/book"        class="nav-item active"><span class="nav-icon">📋</span> Book a Service</a>
      <a href="<?= BASE_URL ?>/my-bookings" class="nav-item"><span class="nav-icon">📂</span> My Bookings</a>
      <div class="nav-section-label">Account</div>
      <a href="<?= BASE_URL ?>/profile"    class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
      <a href="<?= BASE_URL ?>/dashboard#notifications" class="nav-item">
        <span class="nav-icon">🔔</span> Notifications
        <?php if($notif_count > 0): ?><span class="nav-badge"><?= $notif_count ?></span><?php endif; ?>
      </a>
      <div class="nav-section-label">Help</div>
      <a href="<?= BASE_URL ?>/#faq"     class="nav-item"><span class="nav-icon">❓</span> FAQ</a>
      <a href="<?= BASE_URL ?>/#contact" class="nav-item"><span class="nav-icon">💬</span> Contact Support</a>
    </nav>
    <div class="sidebar-footer">
      <a href="<?= BASE_URL ?>/logout" class="btn-logout"><span>🚪</span> Sign Out</a>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="burger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <span class="topbar-title">Book a Service</span>
      </div>
      <div class="topbar-right">
        <a href="<?= BASE_URL ?>/dashboard#notifications" class="topbar-notif">
          🔔<?php if($notif_count > 0): ?><span class="notif-dot"></span><?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/dashboard" class="btn-back-top">← Dashboard</a>
      </div>
    </header>

    <div class="content">

      <?php if($booking_error): ?>
        <div class="alert alert-error" style="max-width:780px;margin:0 auto 16px;">⚠️ <?= e($booking_error) ?></div>
      <?php endif; ?>

      <div class="step-card">
        <!-- Progress bar -->
        <div class="prog-track">
          <div class="prog-fill" id="progFill" style="width:<?= round(100/5) ?>%"></div>
        </div>

        <!-- Header -->
        <div class="step-head">
          <div class="step-head-title">New Service Request</div>
          <div class="step-head-count" id="stepCount">Step 1 of 5</div>
        </div>

        <!-- Step indicators -->
        <div class="step-inds">
          <?php foreach(['Service','Details','Frequency','Language','Review'] as $i=>$lbl): ?>
          <div class="sind <?= $i===0?'active':'' ?>" id="sind-<?= $i+1 ?>">
            <div class="sind-circle"><?= $i+1 ?></div>
            <div class="sind-label"><?= $lbl ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- FORM -->
        <div class="step-body">
          <form method="POST" id="wizForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
            <input type="hidden" name="wizard_submit" value="1"/>

            <!-- ── STEP 1: SERVICE SELECTION ── -->
            <div class="wp on" id="wp-1">
              <div class="step-q">Which services do you need?</div>
              <div class="step-hint">Select one or more services.</div>
              <div class="svc-grid">
                <?php foreach($services as $sv): ?>
                <label class="svc-lbl">
                  <input type="checkbox" name="service_ids[]" value="<?= $sv['id'] ?>" class="svc-chk"/>
                  <div class="svc-box">
                    <span class="svc-emo"><?= $sv['icon']?:'🏠' ?></span>
                    <span class="svc-nm"><?= e($sv['name']) ?></span>
                    <div class="svc-tick">✓</div>
                  </div>
                </label>
                <?php endforeach; ?>
              </div>
              <div class="wiz-nav">
                <button type="button" class="btn-next" onclick="wNext(1)">Next →</button>
              </div>
            </div>

            <!-- ── STEP 2: SERVICE DETAILS ── -->
            <div class="wp" id="wp-2">
              <div class="step-q">Service Details</div>
              <div class="step-hint">Tell us more about what you need.</div>
              <div id="dynamicStep2"></div>
              <div class="wiz-nav">
                <button type="button" class="btn-back-w" onclick="wBack(2)">← Back</button>
                <button type="button" class="btn-next" onclick="wNext(2)">Next →</button>
              </div>
            </div>

            <!-- ── STEP 3: FREQUENCY ── -->
            <div class="wp" id="wp-3">
              <div class="step-q">How often do you need the service?</div>
              <div class="step-hint">Select the frequency that works best for you.</div>
              <div class="freq-grid">
                <label class="freq-lbl"><input type="radio" name="frequency" value="3 days/week"/><div class="freq-box">3 days/week</div></label>
                <label class="freq-lbl"><input type="radio" name="frequency" value="5 days/week"/><div class="freq-box">5 days/week</div></label>
                <label class="freq-lbl"><input type="radio" name="frequency" value="6 days/week"/><div class="freq-box">6 days/week</div></label>
                <label class="freq-lbl"><input type="radio" name="frequency" value="Temporary Work"/><div class="freq-box">Temporary Work</div></label>
              </div>
              <div id="tempDaysWrap" style="display:none;margin-top:12px">
                <div class="form-group">
                  <label>Number of Days</label>
                  <div class="iw"><span class="iico">📆</span><input type="number" name="duration" placeholder="e.g. 5" min="1"/></div>
                </div>
              </div>
              <div class="wiz-nav">
                <button type="button" class="btn-back-w" onclick="wBack(3)">← Back</button>
                <button type="button" class="btn-next" onclick="wNext(3)">Next →</button>
              </div>
            </div>

            <!-- ── STEP 4: LANGUAGE + NEIGHBOURHOOD ── -->
            <div class="wp" id="wp-4">
              <div class="step-q">Language Preference</div>
              <div class="step-hint">Select the language(s) your worker should speak.</div>
              <div class="lang-pills">
                <label class="lang-lbl"><input type="checkbox" name="languages[]" value="English"/><div class="lang-pill">English</div></label>
                <label class="lang-lbl"><input type="checkbox" name="languages[]" value="French"/><div class="lang-pill">French</div></label>
                <label class="lang-lbl"><input type="checkbox" name="languages[]" value="Swahili"/><div class="lang-pill">Swahili</div></label>
                <label class="lang-lbl"><input type="checkbox" name="languages[]" value="Kinyarwanda"/><div class="lang-pill">Kinyarwanda</div></label>
              </div>
              <div class="extra-details">
                <div class="extra-title">Extra Details</div>
                <div class="live-opts">
                  <label class="check-item" id="liveOutItem">
                    <input type="checkbox" name="live_out" value="1" id="liveOutChk" onchange="toggleLivingArea()"/>
                    Live Out
                  </label>
                  <label class="check-item">
                    <input type="checkbox" name="live_in" value="1"/>
                    Live In
                  </label>
                </div>
                <div class="living-area-field" id="livingAreaField">
                  <div class="form-group" style="margin-top:12px">
                    <label>Living Area</label>
                    <div class="iw">
                      <span class="iico">📍</span>
                      <select name="living_area">
                        <option value="">Select area...</option>
                        <option value="Within Kigali">Within Kigali</option>
                        <option value="Outside Kigali">Outside Kigali</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="form-group" style="margin-top:14px">
                  <label>Neighbourhood <span style="color:var(--text-soft);font-size:0.72rem;font-weight:400">(optional)</span></label>
                  <div class="iw"><span class="iico">🏘️</span><input type="text" name="neighbourhood" placeholder="e.g. Kicukiro"/></div>
                </div>
              </div>
              <div class="wiz-nav">
                <button type="button" class="btn-back-w" onclick="wBack(4)">← Back</button>
                <button type="button" class="btn-next" onclick="wNext(4)">Next →</button>
              </div>
            </div>

            <!-- ── STEP 5: REVIEW & SUBMIT (no password) ── -->
            <div class="wp" id="wp-5">
              <div class="step-q">Review &amp; Submit</div>
              <div class="step-hint">Confirm your details and submit your request.</div>

              <!-- Price -->
              <div class="price-badge">
                <div>
                  <div class="price-badge-label">Estimated Monthly Fee</div>
                  <div class="price-badge-sub">Based on your selected services</div>
                </div>
                <div style="text-align:right">
                  <div class="price-badge-amount" id="priceBadge">0 RWF</div>
                </div>
              </div>

              <!-- Summary -->
              <div class="summ-box">
                <div class="summ-title">Request Summary</div>
                <div class="summ-row"><strong>Services:</strong><span id="s-svcs">—</span></div>
                <div class="summ-row"><strong>Frequency:</strong><span id="s-freq">—</span></div>
                <div class="summ-row"><strong>Languages:</strong><span id="s-lang">—</span></div>
                <div class="summ-row"><strong>Details:</strong><span id="s-details">—</span></div>
              </div>

              <!-- Pre-filled profile banner -->
              <div class="profile-banner">
                ✅ <span>Your account details are pre-filled automatically.</span>
              </div>

              <!-- Profile display (read-only, hidden inputs carry values) -->
              <div class="profile-card">
                <div class="profile-row">
                  <span class="profile-icon">👤</span>
                  <span class="profile-label">Full Name</span>
                  <span class="profile-value"><?= e($p_name) ?></span>
                </div>
                <div class="profile-row">
                  <span class="profile-icon">📱</span>
                  <span class="profile-label">Phone</span>
                  <span class="profile-value"><?= e($p_phone ?: 'Not set — update your profile') ?></span>
                </div>
                <div class="profile-row">
                  <span class="profile-icon">✉️</span>
                  <span class="profile-label">Email</span>
                  <span class="profile-value"><?= e($p_email) ?></span>
                </div>
              </div>

              <!-- Hidden fields carry profile data to POST -->
              <input type="hidden" name="name"  value="<?= e($p_name)  ?>"/>
              <input type="hidden" name="phone" value="<?= e($p_phone) ?>"/>
              <input type="hidden" name="email" value="<?= e($p_email) ?>"/>

              <p style="font-size:0.74rem;color:var(--text-soft);line-height:1.6;margin-bottom:4px;">
                By submitting you agree to our
                <a href="<?= BASE_URL ?>/terms"   target="_blank" style="color:var(--navy-light);font-weight:600;">Terms</a> and
                <a href="<?= BASE_URL ?>/privacy" target="_blank" style="color:var(--navy-light);font-weight:600;">Privacy Policy</a>.
              </p>

              <div class="wiz-nav">
                <button type="button" class="btn-back-w" onclick="wBack(5)">← Back</button>
                <button type="submit" class="btn-next" id="submitBtn">
                  <span id="btnTxt">Submit Request →</span>
                </button>
              </div>
            </div>

          </form>
        </div><!-- /step-body -->
      </div><!-- /step-card -->
    </div><!-- /content -->

    <?php include_once '../includes/footer.php'; ?>
  </div>
</div>

<script>
var FEES         = <?= $fees_json ?>;
var SVC_MAP      = <?= $services_json ?>;
var TOTAL_STEPS  = 5;
var SVC_CLEANING     = 6;
var SVC_HOUSEKEEPING = 7;
var SVC_CHEFS        = 9;
var curStep = 1;

<?php if($booking_error): ?>
document.addEventListener('DOMContentLoaded',function(){goStep(5);});
<?php endif; ?>

function goStep(n){
  document.querySelectorAll('.wp').forEach(function(p){p.classList.remove('on');});
  var p=document.getElementById('wp-'+n);if(p)p.classList.add('on');
  for(var i=1;i<=TOTAL_STEPS;i++){
    var el=document.getElementById('sind-'+i);if(!el)continue;
    el.classList.remove('active','done');
    if(i<n)el.classList.add('done');
    else if(i===n)el.classList.add('active');
  }
  document.getElementById('progFill').style.width=Math.round((n/TOTAL_STEPS)*100)+'%';
  document.getElementById('stepCount').textContent='Step '+n+' of '+TOTAL_STEPS;
  curStep=n;
  if(n===TOTAL_STEPS) updateSummary();
}

function wNext(from){
  if(from===1){
    if(!document.querySelectorAll('.svc-chk:checked').length){alert('Please select at least one service.');return;}
    buildDynamicStep2();
  }
  if(from===2){if(!validateStep2())return;}
  if(from===3){if(!document.querySelector('[name="frequency"]:checked')){alert('Please select how often you need the service.');return;}}
  goStep(from+1);
}
function wBack(from){goStep(from-1);}

function getSelectedServices(){
  return Array.from(document.querySelectorAll('.svc-chk:checked')).map(function(c){return parseInt(c.value);});
}

function buildDynamicStep2(){
  var selected=getSelectedServices();
  var html='';
  selected.forEach(function(sid){
    if(sid===SVC_CLEANING)          html+=buildCleaningSection(SVC_MAP[sid]||'Cleaning');
    else if(sid===SVC_HOUSEKEEPING) html+=buildHousekeepingSection(SVC_MAP[sid]||'Housekeeping');
    else if(sid===SVC_CHEFS)        html+=buildChefsSection(SVC_MAP[sid]||'Chefs');
  });
  document.getElementById('dynamicStep2').innerHTML=html;
  document.querySelectorAll('[name="chef_type"]').forEach(function(r){r.addEventListener('change',toggleChefFields);});
  document.querySelectorAll('.check-item input[type="checkbox"]').forEach(function(chk){
    chk.addEventListener('change',function(){this.closest('.check-item').classList.toggle('checked',this.checked);});
  });
}

function buildCleaningSection(name){
  return '<div class="svc-section"><div class="svc-section-title">🧹 '+name+' Details</div>'+
    '<div class="form-group"><label>Number of Rooms</label>'+
    '<div class="iw"><span class="iico">🚪</span><input type="number" name="rooms" placeholder="e.g. 3" min="1"/></div></div>'+
    '<div class="check-grid">'+
      '<label class="check-item"><input type="checkbox" name="outdoor_areas" value="1"/> Outdoor Areas</label>'+
      '<label class="check-item"><input type="checkbox" name="has_car" value="1"/> Car Cleaning</label>'+
      '<label class="check-item"><input type="checkbox" name="has_pool" value="1"/> Has Pool</label>'+
      '<label class="check-item"><input type="checkbox" name="dish_washing" value="1"/> Dish Washing</label>'+
      '<label class="check-item"><input type="checkbox" name="has_pets" value="1"/> Pets</label>'+
    '</div></div>';
}

function buildHousekeepingSection(name){
  return '<div class="svc-section"><div class="svc-section-title">🏠 '+name+' Details</div>'+
    '<div class="form-group"><label>Number of Rooms</label>'+
    '<div class="iw"><span class="iico">🚪</span><input type="number" name="rooms" placeholder="e.g. 3" min="1"/></div></div>'+
    '<div class="check-grid">'+
      '<label class="check-item"><input type="checkbox" name="outdoor_areas" value="1"/> Outdoor Areas</label>'+
      '<label class="check-item"><input type="checkbox" name="has_car" value="1"/> Car Cleaning</label>'+
      '<label class="check-item"><input type="checkbox" name="has_pool" value="1"/> Has Pool</label>'+
      '<label class="check-item"><input type="checkbox" name="dish_washing" value="1"/> Dish Washing</label>'+
      '<label class="check-item"><input type="checkbox" name="has_pets" value="1"/> Pets</label>'+
      '<label class="check-item"><input type="checkbox" name="laundry" value="1"/> Laundry &amp; Ironing</label>'+
    '</div></div>';
}

function buildChefsSection(name){
  return '<div class="svc-section"><div class="svc-section-title">👨‍🍳 '+name+' Details</div>'+
    '<div class="form-group"><label>What type of chef are you looking for?</label></div>'+
    '<div class="chef-type-opts">'+
      '<label class="chef-type-lbl"><input type="radio" name="chef_type" value="events"/><div class="chef-type-box">👨‍🍳 Chef for Events</div></label>'+
      '<label class="chef-type-lbl"><input type="radio" name="chef_type" value="home"/><div class="chef-type-box">🏠 Chef/Cook for Home</div></label>'+
    '</div>'+
    '<div class="chef-fields" id="chefEventsFields">'+
      '<div class="iw" style="margin-bottom:10px"><span class="iico">👥</span><input type="number" name="num_people" placeholder="Expected number of people" min="1" style="width:100%;padding:11px 14px 11px 38px;border:1.5px solid var(--cream-dark);border-radius:8px;font-family:var(--font-body);font-size:0.86rem;background:var(--cream);outline:none"/></div>'+
      '<div style="display:flex;gap:10px;margin-bottom:10px">'+
        '<label class="freq-lbl" style="flex:1"><input type="radio" name="needs_menu_choice" value="1" onchange="setMenuVal(1)"/><div class="freq-box">Need a Menu</div></label>'+
        '<label class="freq-lbl" style="flex:1"><input type="radio" name="needs_menu_choice" value="0" onchange="setMenuVal(0)"/><div class="freq-box">No Menu Needed</div></label>'+
      '</div><input type="hidden" name="needs_menu" id="needsMenuVal" value="0"/>'+
    '</div>'+
    '<div class="chef-fields" id="chefHomeFields">'+
      '<div class="iw" style="margin-bottom:10px"><span class="iico">👥</span><input type="number" name="num_people" placeholder="Number of people" min="1" style="width:100%;padding:11px 14px 11px 38px;border:1.5px solid var(--cream-dark);border-radius:8px;font-family:var(--font-body);font-size:0.86rem;background:var(--cream);outline:none"/></div>'+
      '<div class="iw"><span class="iico">🧑</span><input type="number" name="num_adults" placeholder="Number of adults" min="1" style="width:100%;padding:11px 14px 11px 38px;border:1.5px solid var(--cream-dark);border-radius:8px;font-family:var(--font-body);font-size:0.86rem;background:var(--cream);outline:none"/></div>'+
    '</div>'+
  '</div>';
}

function setMenuVal(v){var el=document.getElementById('needsMenuVal');if(el)el.value=v;}

function toggleChefFields(){
  var val=document.querySelector('[name="chef_type"]:checked');
  var evF=document.getElementById('chefEventsFields');
  var hmF=document.getElementById('chefHomeFields');
  if(!val||!evF||!hmF)return;
  evF.classList.toggle('on',val.value==='events');
  hmF.classList.toggle('on',val.value==='home');
}

function validateStep2(){
  var selected=getSelectedServices();
  var ok=true;
  selected.forEach(function(sid){
    if(sid===SVC_CHEFS&&!document.querySelector('[name="chef_type"]:checked')){
      alert('Please select the type of chef you need.');ok=false;
    }
  });
  return ok;
}

function toggleLivingArea(){
  var chk=document.getElementById('liveOutChk');
  var field=document.getElementById('livingAreaField');
  if(field)field.classList.toggle('on',chk.checked);
  document.getElementById('liveOutItem').classList.toggle('checked',chk.checked);
}

document.querySelectorAll('[name="frequency"]').forEach(function(r){
  r.addEventListener('change',function(){
    var w=document.getElementById('tempDaysWrap');
    if(w)w.style.display=this.value==='Temporary Work'?'block':'none';
  });
});

function calcPrice(){
  var selected=getSelectedServices();
  var total=0;
  selected.forEach(function(sid){
    var f=FEES[sid]||{};
    if(sid===SVC_CLEANING||sid===SVC_HOUSEKEEPING){
      total+=parseFloat(f['base']||0);
      var roomsEl=document.querySelector('[name="rooms"]');
      var rooms=roomsEl?parseInt(roomsEl.value)||1:1;
      total+=parseFloat(f['per_room']||0)*rooms;
      if(document.querySelector('[name="outdoor_areas"]:checked')) total+=parseFloat(f['outdoor']||0);
      if(document.querySelector('[name="has_car"]:checked'))       total+=parseFloat(f['car']||0);
      if(document.querySelector('[name="has_pool"]:checked'))      total+=parseFloat(f['pool']||0);
      if(document.querySelector('[name="dish_washing"]:checked'))  total+=parseFloat(f['dishes']||0);
      if(document.querySelector('[name="has_pets"]:checked'))      total+=parseFloat(f['pets']||0);
      if(sid===SVC_HOUSEKEEPING&&document.querySelector('[name="laundry"]:checked')) total+=parseFloat(f['laundry']||0);
    }
    if(sid===SVC_CHEFS){
      var ct=document.querySelector('[name="chef_type"]:checked');
      if(ct&&ct.value==='events'){
        total+=parseFloat(f['event_base']||0);
        var ppl=parseInt((document.querySelector('[name="num_people"]')||{}).value)||1;
        total+=parseFloat(f['per_person']||0)*ppl;
        var nm=document.getElementById('needsMenuVal');
        if(nm&&nm.value==='1') total+=parseFloat(f['menu']||0);
      } else if(ct&&ct.value==='home'){
        total+=parseFloat(f['home_base']||0);
        var ppl2=parseInt((document.querySelector('[name="num_people"]')||{}).value)||1;
        total+=parseFloat(f['home_per_person']||0)*ppl2;
      }
    }
  });
  return total;
}

function updateSummary(){
  var selected=getSelectedServices();
  var svcNames=selected.map(function(id){return SVC_MAP[id]||'#'+id;}).join(' + ');
  var freq=document.querySelector('[name="frequency"]:checked');
  var langs=Array.from(document.querySelectorAll('[name="languages[]"]:checked')).map(function(l){return l.value;}).join(', ');
  var details=[];
  var roomsEl=document.querySelector('[name="rooms"]');
  if(roomsEl&&roomsEl.value)details.push(roomsEl.value+' rooms');
  if(document.querySelector('[name="outdoor_areas"]:checked'))details.push('Outdoor areas');
  if(document.querySelector('[name="has_car"]:checked'))      details.push('Car cleaning');
  if(document.querySelector('[name="has_pool"]:checked'))     details.push('Pool');
  if(document.querySelector('[name="dish_washing"]:checked')) details.push('Dish washing');
  if(document.querySelector('[name="has_pets"]:checked'))     details.push('Pets');
  if(document.querySelector('[name="laundry"]:checked'))      details.push('Laundry & ironing');
  var ct=document.querySelector('[name="chef_type"]:checked');
  if(ct)details.push('Chef for '+ct.value);
  var pplEl=document.querySelector('[name="num_people"]');
  if(pplEl&&pplEl.value)details.push(pplEl.value+' people');
  var price=calcPrice();
  function set(id,val){var el=document.getElementById(id);if(el)el.textContent=val||'—';}
  set('s-svcs',svcNames);
  set('s-freq',freq?freq.value:'—');
  set('s-lang',langs||'No preference');
  set('s-details',details.join(', ')||'—');
  var pb=document.getElementById('priceBadge');
  if(pb)pb.textContent=price.toLocaleString()+' RWF';
}

document.getElementById('wizForm').addEventListener('submit',function(){
  var btn=document.getElementById('submitBtn');
  if(btn){btn.classList.add('loading');document.getElementById('btnTxt').textContent='Submitting...';}
});

function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('open');
  document.body.style.overflow=document.getElementById('sidebar').classList.contains('open')?'hidden':'';
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
  document.body.style.overflow='';
}
window.addEventListener('resize',function(){if(window.innerWidth>768)closeSidebar();});
</script>
</body>
</html>