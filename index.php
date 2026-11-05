<?php
require_once 'config/db.php';
require_once 'config/config.php';

// ── CONTACT FORM HANDLER ──────────────────────────────────────
$contact_success = false;
$contact_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $first_name = trim(strip_tags($_POST['first_name'] ?? ''));
    $last_name  = trim(strip_tags($_POST['last_name']  ?? ''));
    $email      = trim(strip_tags($_POST['email']      ?? ''));
    $topic      = trim(strip_tags($_POST['topic']      ?? ''));
    $message    = trim(strip_tags($_POST['message']    ?? ''));
    $ip         = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!$first_name || !$last_name || !$email || !$message) {
        $contact_error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contact_error = 'Please enter a valid email address.';
    } elseif (strlen($message) < 10) {
        $contact_error = 'Your message is too short. Please write at least 10 characters.';
    } else {
        $limit_check = $conn->prepare("
            SELECT COUNT(*) FROM contact_messages
            WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $limit_check->bind_param('s', $ip);
        $limit_check->execute();
        $recent_count = $limit_check->get_result()->fetch_row()[0];

        if ($recent_count >= 3) {
            $contact_error = 'Too many messages sent. Please try again later.';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO contact_messages (first_name, last_name, email, topic, message, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('ssssss', $first_name, $last_name, $email, $topic, $message, $ip);
            if ($stmt->execute()) {
                $contact_success = true;
            } else {
                $contact_error = 'Something went wrong. Please try again.';
            }
        }
    }
}

// ── FETCH ACTIVE SERVICES ─────────────────────────────────────
$db_services = $conn->query("
    SELECT id, name, description, icon, status
    FROM services
    WHERE status = 'active'
    ORDER BY sort_order ASC, id ASC
")->fetch_all(MYSQLI_ASSOC);

$homepage_services = array_slice($db_services, 0, 3);

// ── FEATURED WORKERS (same source as find-worker) ─────────────
$featured_workers_raw = $conn->query("
    SELECT u.id, u.name, u.created_at, u.photo, u.profile_photo,
           s.name AS service_name,
           ja.full_name, ja.age, ja.location, ja.district, ja.experience,
           ja.bio, ja.availability, ja.expected_pay, ja.languages, ja.status AS app_status
    FROM users u
    LEFT JOIN job_applications ja ON ja.provider_id = u.id AND ja.status = 'approved'
    LEFT JOIN services s ON s.id = ja.service_id
    WHERE u.role = 'provider' AND u.status = 'active'
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

function hh_worker_photo_url(array $w): string {
    $file = trim((string)($w['photo'] ?: ($w['profile_photo'] ?? '')));
    if ($file === '') return 'Image/profile-placeholder.png';
    if (preg_match('#^https?://#i', $file)) return $file;
    if (strpos($file, 'uploads/') === 0 || strpos($file, 'Image/') === 0) return $file;
    return 'uploads/avatars/' . ltrim($file, '/');
}
function hh_worker_parse_bio(?string $bio): array {
    $raw = html_entity_decode((string)$bio, ENT_QUOTES, 'UTF-8');
    $education = '';
    $about = '';
    if (preg_match('/EDUCATION:\s*([^\n\r]+)/i', $raw, $m)) $education = trim($m[1]);
    if (preg_match('/ABOUT:\s*(.+)$/is', $raw, $m)) {
        $about = trim($m[1]);
    } else {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $keep = [];
        foreach ($lines as $line) {
            if (preg_match('/^(FULL NAME|PHONE|EMAIL|DOB|DOCS|EDUCATION|ABOUT)\s*:/i', $line)) continue;
            $keep[] = $line;
        }
        $about = trim(implode("\n", $keep));
    }
    return ['education' => $education, 'about' => $about];
}

$featured_workers = [];
foreach ($featured_workers_raw as $w) {
    $exp = trim((string)($w['experience'] ?? ''));
    if ($exp === '') {
        $exp_label = 'Experience on request';
    } elseif (stripos($exp, 'year') !== false) {
        $exp_label = $exp . (stripos($exp, 'experience') === false ? ' experience' : '');
    } else {
        $exp_label = $exp . ' years experience';
    }
    $parsed = hh_worker_parse_bio($w['bio'] ?? '');
    $place_parts = array_values(array_unique(array_filter([
        trim((string)($w['location'] ?? '')),
        trim((string)($w['district'] ?? '')),
    ])));
    $featured_workers[] = [
        'name'         => trim((string)($w['full_name'] ?? '')) ?: $w['name'],
        'role'         => $w['service_name'] ?: 'Domestic Worker',
        'exp'          => $exp_label,
        'age'          => $w['age'] ?? null,
        'place'        => implode(' · ', $place_parts),
        'availability' => trim((string)($w['availability'] ?? '')),
        'languages'    => trim((string)($w['languages'] ?? '')),
        'education'    => $parsed['education'],
        'pay'          => trim((string)($w['expected_pay'] ?? '')),
        'about'        => $parsed['about'],
        'verified'     => ($w['app_status'] ?? '') === 'approved',
        'img'          => hh_worker_photo_url($w),
    ];
}

$workers_available_count = (int)($conn->query("
    SELECT COUNT(*) FROM users WHERE role = 'provider' AND status = 'active'
")->fetch_row()[0] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Home Help Rwanda | Home Cleaning Kigali, Housekeeping, Nanny &amp; Domestic Services</title>
<meta name="description" content="Home Help is the best domestic services platform in Kigali, Rwanda. Book trusted home cleaners, housekeepers, nannies, home chefs and caregivers. Professional, affordable, and same-day service available. Free to register at homehelp.rw."/>
<meta name="author" content="Brilliant Elite Ltd"/>
<meta name="robots" content="index, follow"/>
<link rel="canonical" href="https://homehelp.rw/"/>
<?php include __DIR__ . '/includes/seo-meta.php'; ?>

<meta property="og:type"        content="website"/>
<meta property="og:url"         content="https://homehelp.rw/"/>
<meta property="og:title"       content="Home Help Rwanda | Home Cleaning Kigali &amp; Domestic Services"/>
<meta property="og:description" content="Book trusted home cleaners, housekeepers, nannies, home chefs and caregivers in Kigali and across Rwanda. Professional domestic services at affordable rates. Free to register at homehelp.rw."/>
<meta property="og:image"       content="https://homehelp.rw/Image/og-image.jpg"/>
<meta property="og:image:width"  content="1200"/>
<meta property="og:image:height" content="630"/>
<meta property="og:site_name"   content="Home Help Rwanda"/>
<meta property="og:locale"      content="en_RW"/>
<meta name="twitter:card"        content="summary_large_image"/>
<meta name="twitter:title"       content="Home Help Rwanda | Home Cleaning Kigali &amp; Domestic Services"/>
<meta name="twitter:description" content="Book home cleaners, housekeepers, nannies, home chefs and caregivers in Kigali Rwanda. Affordable, trusted domestic services. Register free at homehelp.rw."/>
<meta name="twitter:image"       content="https://homehelp.rw/Image/og-image.jpg"/>
<meta name="geo.region"      content="RW"/>
<meta name="geo.placename"   content="Kigali, Rwanda"/>
<meta name="geo.position"    content="-1.9441;30.0619"/>
<meta name="ICBM"            content="-1.9441, 30.0619"/>
<meta name="language"        content="English"/>
<meta name="google-site-verification" content="google05f1cf591a341011"/>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "Home Help Rwanda",
  "description": "Rwanda's trusted marketplace for domestic services connecting households with verified nannies, housekeepers, chefs and caregivers.",
  "url": "https://homehelp.rw",
  "logo": "https://homehelp.rw/Image/homehelp-logo.png",

  "image": "https://homehelp.rw/Image/og-image.jpg",
  "telephone": "+250790533144",
  "email": "info@homehelp.rw",
  "address": {"@type":"PostalAddress","addressLocality":"Kigali","addressCountry":"RW"},
  "geo": {"@type":"GeoCoordinates","latitude":-1.9441,"longitude":30.0619},
  "openingHours": "Mo-Sa 07:00-19:00",
  "priceRange": "Free to register",
  "areaServed": "Rwanda",
  "serviceType": ["Home Cleaning Kigali","Housekeeping Services Kigali","Home Chef Kigali","Nanny Services Kigali","Caregiver Services Rwanda","Office Cleaning Rwanda","Maid Services Kigali","Domestic Workers Rwanda","Babysitter Kigali","Live-in Maid Rwanda"]
}
</script>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>

<style>
:root{
  --green-deep:#0A1F6B;--green-mid:#152268;--green-light:#2E5FC2;--green-soft:#D4DCF5;
  --amber:#0A1F6B;--amber-light:#D4DCF5;--cream:#FDFAF5;--cream-dark:#F2EDE3;
  --brown:#0A1F6B;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --white:#FFFFFF;--field:#EEF2F8;--border:#E6EAF2;
  --shadow-sm:0 2px 12px rgba(13,27,75,0.08);
  --shadow-md:0 8px 32px rgba(13,27,75,0.12);
  --shadow-lg:0 20px 60px rgba(13,27,75,0.16);
  --radius:16px;--radius-sm:8px;
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden;line-height:1.65}
img{max-width:100%;height:auto;display:block}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:6px}
::-webkit-scrollbar-track{background:var(--cream)}
::-webkit-scrollbar-thumb{background:var(--green-light);border-radius:3px}
.container{width:min(96%,1400px);margin:0 auto;padding:0 16px}
.section-tag{display:inline-block;background:var(--amber-light);color:var(--brown);font-size:0.72rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;padding:6px 16px;border-radius:99px;margin-bottom:16px}
.section-title{font-family:var(--font-display);font-size:clamp(2rem,4vw,3.2rem);font-weight:700;color:var(--green-deep);line-height:1.2;margin-bottom:16px}
.section-title em{font-style:italic;color:var(--green-light)}
.section-sub{font-size:1rem;color:var(--text-mid);max-width:520px;line-height:1.7}
.btn{display:inline-flex;align-items:center;gap:8px;padding:14px 28px;border-radius:99px;font-family:var(--font-body);font-size:0.9rem;font-weight:600;cursor:pointer;border:none;transition:all 0.3s ease;white-space:nowrap;text-decoration:none}
.btn-primary{background:var(--green-deep);color:var(--white)}
.btn-primary:hover{background:var(--green-mid);transform:translateY(-2px);box-shadow:var(--shadow-md)}
.btn-amber{background:var(--amber);color:var(--white)}
.btn-amber:hover{background:var(--green-mid);transform:translateY(-2px);box-shadow:0 8px 24px rgba(46,95,194,0.35)}
.btn-outline{background:transparent;border:2px solid var(--green-deep);color:var(--green-deep)}
.btn-outline:hover{background:var(--green-deep);color:var(--white);transform:translateY(-2px)}
.btn-hero-ghost{background:rgba(255,255,255,0.15);color:var(--white);border:1.5px solid rgba(255,255,255,0.4);backdrop-filter:blur(6px);display:inline-flex;align-items:center;gap:8px;padding:13px 26px;border-radius:99px;font-family:var(--font-body);font-size:0.9rem;font-weight:600;cursor:pointer;transition:all 0.3s;text-decoration:none}
.btn-hero-ghost:hover{background:rgba(255,255,255,0.28);transform:translateY(-2px)}

/* NAVBAR */
.navbar{position:fixed;top:0;left:0;right:0;z-index:1000;padding:16px 0;transition:all 0.35s ease;background:rgba(255,255,255,0.92);backdrop-filter:blur(12px);border-bottom:1px solid transparent;border-radius:0}
.navbar.scrolled{top:12px;left:50%;right:auto;width:max-content;max-width:calc(100% - 24px);transform:translateX(-50%);padding:8px 18px;border-radius:999px;background:rgba(255,255,255,0.96);box-shadow:0 10px 36px rgba(7,35,24,0.12);border:1.5px solid rgba(7,35,24,0.08);border-bottom-color:rgba(7,35,24,0.08)}
.navbar.scrolled .container{width:auto;max-width:none;padding:0 10px}
.navbar.scrolled .navbar-inner{gap:28px}
.navbar-inner{display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:nowrap}
.nav-logo{display:flex;align-items:center;gap:10px;flex-shrink:0}
.nav-logo img{width:40px;height:40px;object-fit:contain}
.nav-logo-text{font-family:var(--font-body);font-size:1.2rem;font-weight:800;color:var(--green-deep);letter-spacing:-0.02em}
.nav-links{display:flex;align-items:center;gap:20px;list-style:none;flex-wrap:nowrap;justify-content:center}
.nav-links a{font-size:0.9rem;font-weight:500;color:#6B7280;transition:color 0.2s;position:relative;text-decoration:none;white-space:nowrap}
.nav-links a:hover{color:var(--green-deep)}
.nav-links a.active{color:var(--green-deep);font-weight:700}
.nav-cta{display:flex;align-items:center;gap:16px;flex-shrink:0}
.nav-login{font-size:0.9rem;font-weight:600;color:#6B7280;transition:color 0.2s;text-decoration:none}
.nav-login:hover{color:var(--green-deep)}
.nav-register{font-size:0.9rem;font-weight:600;color:var(--green-deep);text-decoration:none}
.nav-register:hover{opacity:0.8}
.btn-find-worker{display:inline-flex;align-items:center;gap:7px;padding:11px 22px;border-radius:999px;background:var(--green-deep);color:var(--white);font-size:0.88rem;font-weight:700;border:none;cursor:pointer;transition:all 0.3s;white-space:nowrap;text-decoration:none}
.btn-find-worker:hover{background:var(--green-mid);transform:translateY(-1px);box-shadow:0 8px 20px rgba(10,31,107,0.25)}
.btn-find-worker svg{width:15px;height:15px;flex-shrink:0}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:4px;background:none;border:none}
.hamburger span{display:block;width:24px;height:2px;background:var(--green-deep);border-radius:2px;transition:all 0.3s}
.hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg)}
.hamburger.open span:nth-child(2){opacity:0}
.hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}

/* MOBILE MENU */
.mobile-menu{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:var(--green-deep);z-index:999;flex-direction:column;align-items:center;justify-content:flex-start;padding:90px 20px 40px;gap:0;opacity:0;pointer-events:none;transition:opacity 0.3s ease;overflow-y:auto}
.mobile-menu.open{opacity:1;pointer-events:all;display:flex}
.mobile-menu .mob-link{font-family:var(--font-body);font-size:1.5rem;font-weight:700;color:var(--white);transition:color 0.2s;text-decoration:none;padding:14px 0;width:100%;text-align:center;border-bottom:1px solid rgba(255,255,255,0.06)}
.mobile-menu .mob-link:last-of-type{border-bottom:none}
.mobile-menu .mob-link:hover{color:rgba(255,255,255,0.7)}
.mobile-menu-footer{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;padding:24px 0 0;width:100%;margin-top:8px;border-top:1px solid rgba(255,255,255,0.1)}

/* HERO */
.hero{background:var(--white);padding:120px 0 72px;min-height:100vh;display:flex;align-items:center}
.hero-grid{display:grid;grid-template-columns:1.05fr 0.95fr;gap:56px;align-items:center}
.hero-copy{max-width:560px}
.hero-lead{font-size:1.02rem;color:#6B7280;line-height:1.65;max-width:460px;margin:-12px 0 26px}
.hero-title{font-family:var(--font-body);font-size:clamp(2.4rem,4.6vw,3.6rem);font-weight:800;color:var(--green-deep);line-height:1.12;letter-spacing:-0.03em;margin-bottom:18px}
.hero-search{background:var(--white);border:1px solid var(--border);border-radius:20px;padding:24px;box-shadow:0 12px 40px rgba(10,31,107,0.06)}
.hero-search-title{font-size:1.05rem;font-weight:700;color:var(--green-deep);margin-bottom:4px}
.hero-search-sub{font-size:0.84rem;color:#8B93A7;margin-bottom:18px;line-height:1.5}
.hero-search-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px}
.hero-field label{display:block;font-size:0.72rem;font-weight:600;color:#8B93A7;margin-bottom:6px}
.hero-field select{width:100%;appearance:none;-webkit-appearance:none;background:var(--field) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%238B93A7' d='M1 1l5 5 5-5'/%3E%3C/svg%3E") no-repeat right 14px center;border:none;border-radius:12px;padding:13px 36px 13px 14px;font-family:var(--font-body);font-size:0.88rem;font-weight:500;color:var(--green-deep);outline:none;cursor:pointer;transition:box-shadow 0.2s}
.hero-field select:focus{box-shadow:0 0 0 3px rgba(46,95,194,0.15)}
.btn-search-workers{width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:15px 22px;border:none;border-radius:999px;background:var(--green-deep);color:var(--white);font-family:var(--font-body);font-size:0.95rem;font-weight:700;cursor:pointer;transition:all 0.25s}
.btn-search-workers:hover{background:var(--green-mid);transform:translateY(-1px);box-shadow:0 10px 24px rgba(10,31,107,0.22)}
.hero-trust{display:flex;align-items:center;gap:14px;margin-top:28px}
.hero-avatars{display:flex;align-items:center}
.hero-avatars img{width:36px;height:36px;border-radius:50%;object-fit:cover;border:2.5px solid var(--white);margin-left:-10px;background:#ddd}
.hero-avatars img:first-child{margin-left:0}
.hero-trust-meta{display:flex;flex-direction:column;gap:2px}
.hero-stars{color:#F5B942;font-size:0.85rem;letter-spacing:1px;line-height:1}
.hero-trust-text{font-size:0.82rem;color:#6B7280;font-weight:500}
.hero-visual{position:relative}
.hero-photo{width:100%;aspect-ratio:4/5;object-fit:cover;border-radius:28px;display:block;box-shadow:0 24px 60px rgba(10,31,107,0.12)}
.demo-card{position:absolute;display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.96);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.8);border-radius:14px;padding:8px 12px 8px 8px;box-shadow:0 10px 30px rgba(10,31,107,0.14);animation:floatCard 4.5s ease-in-out infinite}
.demo-card img{width:40px;height:40px;border-radius:10px;object-fit:cover;flex-shrink:0}
.demo-card-info{display:flex;flex-direction:column;gap:2px;min-width:0}
.demo-card-label{font-size:0.62rem;font-weight:600;color:#9AA3B5;text-transform:uppercase;letter-spacing:0.04em}
.demo-card-name{font-size:0.82rem;font-weight:700;color:var(--green-deep);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px}
.demo-card-star{color:#F5B942;font-size:0.9rem;margin-left:4px}
.demo-card-top{top:14%;left:-18px;animation-delay:0s}
.demo-card-bottom{bottom:16%;right:-12px;animation-delay:1.2s}
@keyframes floatCard{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}

/* WHY US / SERVICES BAND */
.why-section{background:var(--white)}
.why-band{position:relative;overflow:hidden;background:#07164F;border-radius:0;isolation:isolate}
.why-band::after{content:'';position:absolute;inset:-30%;pointer-events:none;z-index:0;background:radial-gradient(ellipse 60% 50% at 15% 75%,#3E6EDC 0%,transparent 68%),radial-gradient(ellipse 55% 45% at 85% 55%,#5A8CFF 0%,transparent 68%),radial-gradient(ellipse 50% 40% at 50% 100%,#2846A0 0%,transparent 70%),linear-gradient(160deg,#07164F 0%,#0A1F6B 50%,#061240 100%);filter:blur(90px)}
.why-band::before{content:'';position:absolute;inset:0;pointer-events:none;z-index:1;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");background-size:140px 140px;opacity:0.45;mix-blend-mode:soft-light}
.why-tiles{position:relative;z-index:2;height:clamp(340px,46vw,540px);overflow:hidden}
.why-tiles-track{display:grid;grid-template-columns:repeat(5,1fr);height:100%}
.why-tile{background-size:cover;background-position:center;filter:saturate(0.92)}
.why-panel{position:relative;z-index:2;padding:56px 0 88px}
.why-panel-inner{display:grid;grid-template-columns:minmax(240px,0.85fr) 1.4fr;gap:56px;align-items:start}
.why-intro-title{font-family:var(--font-body);font-size:clamp(2rem,3.4vw,2.85rem);font-weight:800;color:var(--white);line-height:1.15;letter-spacing:-0.03em;margin-bottom:18px}
.why-intro-text{font-size:0.95rem;color:rgba(255,255,255,0.72);line-height:1.75;max-width:360px}
.why-features{display:grid;grid-template-columns:repeat(3,1fr);gap:28px 32px}
.why-feature{display:flex;flex-direction:column;gap:10px;min-height:100%}
.why-feature-head{display:flex;align-items:center;gap:12px}
.why-feature-icon{width:40px;height:40px;border-radius:50%;border:1.5px solid rgba(255,255,255,0.4);display:inline-flex;align-items:center;justify-content:center;color:var(--white);background:rgba(255,255,255,0.04);flex-shrink:0}
.why-feature-icon svg{width:18px;height:18px}
.why-feature h3{font-size:0.98rem;font-weight:700;color:var(--white);line-height:1.3;margin:0}
.why-feature p{font-size:0.84rem;color:rgba(255,255,255,0.62);line-height:1.65;flex:1}
.why-feature-link{display:inline-flex;align-items:center;gap:6px;font-size:0.82rem;font-weight:600;color:rgba(255,255,255,0.9);text-decoration:none;margin-top:4px;transition:gap 0.2s,color 0.2s}
.why-feature-link:hover{gap:10px;color:var(--white)}
.why-feature-link svg{width:14px;height:14px}
.why-feature-tag{display:inline-block;margin-top:2px;font-size:0.68rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#9DB4FF;background:rgba(255,255,255,0.08);padding:3px 8px;border-radius:99px;width:fit-content}

.hiw-head{max-width:520px;margin:0 0 28px}
.hiw-title{
  font-family:var(--font-body);font-size:clamp(1.7rem,3vw,2.35rem);font-weight:800;
  color:var(--green-deep);letter-spacing:-0.03em;line-height:1.15;margin:0 0 10px;
}
.hiw-sub{font-size:0.95rem;color:#8B93A7;line-height:1.6;margin:0}

/* HOME SERVICES — CAROUSEL */
.needs-section{
  position:relative;padding:96px 0 40px;overflow:hidden;
  background:var(--white);
}
.needs-head{
  text-align:center;max-width:640px;margin:0 auto 44px;
}
.needs-title{
  font-family:var(--font-body);font-size:clamp(1.9rem,3.6vw,2.85rem);font-weight:800;
  color:var(--green-deep);letter-spacing:-0.035em;line-height:1.15;margin:0 0 12px;
}
.needs-sub{
  margin:0 auto;font-size:1.02rem;line-height:1.55;color:#6B7280;max-width:42ch;
}
.needs-carousel{position:relative;width:100%}
.needs-track{
  display:flex;gap:22px;overflow-x:auto;scroll-snap-type:x mandatory;
  scroll-behavior:smooth;-webkit-overflow-scrolling:touch;
  padding:4px 2px 8px;scrollbar-width:none;
}
.needs-track::-webkit-scrollbar{display:none}
.needs-card{
  flex:0 0 calc((100% - 66px) / 4);scroll-snap-align:start;
  display:flex;flex-direction:column;text-decoration:none;color:inherit;
  min-width:0;
}
.needs-card-media{
  position:relative;border-radius:22px;overflow:hidden;aspect-ratio:4/3;
  background:#E8E4DC;
}
.needs-card-media img{
  width:100%;height:100%;object-fit:cover;display:block;
  transition:transform .45s ease;
}
.needs-card:hover .needs-card-media img{transform:scale(1.04)}
.needs-book{
  display:inline-flex;align-items:center;justify-content:center;
  margin-top:14px;padding:11px 18px;border-radius:999px;
  background:var(--green-deep);color:var(--white);
  font-size:.82rem;font-weight:750;text-decoration:none;border:0;line-height:1;
  box-shadow:none;transition:background .2s ease;align-self:flex-start;
}
.needs-book:hover{background:var(--green-mid)}
.needs-card-body{padding:18px 4px 0;display:flex;flex-direction:column}
.needs-card-body h3{
  margin:0 0 8px;font-size:1.15rem;font-weight:800;color:#111827;
  letter-spacing:-.02em;line-height:1.25;
}
.needs-card-body p{
  margin:0;font-size:.92rem;line-height:1.55;color:#6B7280;max-width:34ch;
}
.needs-nav{
  display:flex;align-items:center;justify-content:flex-end;gap:20px;
  margin-top:28px;
}
.needs-arrows{display:flex;gap:10px;flex-shrink:0}
.needs-arrow{
  width:44px;height:44px;border-radius:50%;border:1.5px solid var(--cream-dark);
  background:transparent;color:var(--green-deep);cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;
  transition:border-color .2s,background .2s,opacity .2s;
}
.needs-arrow:hover{border-color:var(--green-deep);background:var(--white)}
.needs-arrow:disabled{opacity:.35;cursor:default}
.needs-arrow svg{width:18px;height:18px;stroke-width:2.25}

/* FEATURED WORKERS */
.featured-section{background:var(--white);padding:72px 0}
.featured-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:32px}
.featured-title{font-family:var(--font-body);font-size:clamp(1.7rem,3vw,2.35rem);font-weight:800;color:var(--green-deep);letter-spacing:-0.03em;line-height:1.15;margin-bottom:8px}
.featured-sub{font-size:0.95rem;color:#8B93A7;max-width:420px;line-height:1.55}
.featured-browse{display:inline-flex;align-items:center;justify-content:center;padding:12px 22px;border-radius:999px;border:1.5px solid #D5DBE7;background:var(--white);color:var(--green-deep);font-size:0.9rem;font-weight:700;text-decoration:none;white-space:nowrap;transition:all 0.25s}
.featured-browse:hover{border-color:var(--green-deep);background:#F8FAFF}
.featured-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
.featured-card{display:flex;flex-direction:column;border-radius:22px;overflow:hidden;background:var(--white);border:1px solid #E6EAF2;isolation:isolate}
.featured-card-media{position:relative;aspect-ratio:1/1;background:#E8ECF4;overflow:hidden}
.featured-card-media img{width:100%;height:100%;object-fit:cover;object-position:center top;display:block}
.featured-card-body{padding:16px 16px 18px;display:flex;flex-direction:column;gap:4px;background:var(--white)}
.featured-card-name{font-size:1.02rem;font-weight:700;color:var(--green-deep);line-height:1.25}
.featured-card-role{font-size:0.86rem;color:#4B5568;font-weight:600}
.featured-card-meta{font-size:0.78rem;color:#8B93A7;margin-bottom:10px}
.featured-card-btn{display:inline-flex;align-items:center;justify-content:center;width:100%;padding:11px 16px;border-radius:999px;background:var(--green-deep);color:var(--white);font-size:0.88rem;font-weight:700;text-decoration:none;transition:background 0.25s}
.featured-card-btn:hover{background:var(--green-mid)}

/* CAREER */
.career{padding:100px 0;background:var(--white)}
.career-inner{display:grid;grid-template-columns:1fr 1fr;gap:80px;align-items:center}
.career-jobs{margin-top:36px;display:flex;flex-direction:column;gap:12px}
.career-job{background:var(--cream);border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);padding:18px 20px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;transition:all 0.3s;text-decoration:none;color:inherit}
.career-job:hover{border-color:var(--green-light);background:var(--green-soft);transform:translateX(6px)}
.career-job-info{display:flex;align-items:center;gap:14px}
.career-job-icon{width:40px;height:40px;border-radius:10px;background:var(--green-soft);display:flex;align-items:center;justify-content:center;font-size:1.1rem}
.career-job-info h4{font-weight:700;font-size:0.95rem;color:var(--green-deep);margin-bottom:2px}
.career-job-info p{font-size:0.78rem;color:var(--text-soft)}
.career-arrow{color:var(--green-light);font-size:1.1rem;transition:transform 0.3s}
.career-job:hover .career-arrow{transform:translateX(4px)}
.career-right{background:linear-gradient(145deg,var(--green-light),var(--green-mid));border-radius:24px;padding:48px 40px;color:var(--white);position:relative;overflow:hidden}
.career-right::before{content:'';position:absolute;top:-30%;right:-20%;width:70%;height:70%;background:radial-gradient(circle,rgba(255,255,255,0.15) 0%,transparent 70%);border-radius:50%}
.career-right-inner{position:relative;z-index:1}
.career-right h3{font-family:var(--font-display);font-size:2.2rem;font-weight:700;line-height:1.2;margin-bottom:16px}
.career-right p{font-size:0.95rem;color:rgba(255,255,255,0.82);line-height:1.7;margin-bottom:28px}
.career-benefits{display:flex;flex-direction:column;gap:10px;margin-bottom:32px}
.career-benefit{display:flex;align-items:center;gap:10px;font-size:0.88rem;color:rgba(255,255,255,0.9)}
.career-benefit-icon{width:22px;height:22px;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.65rem;flex-shrink:0}

/* TESTIMONIALS */
.testimonials{padding:88px 0;background:var(--white)}
.testimonials-title{font-family:var(--font-body);font-size:clamp(1.8rem,3.2vw,2.5rem);font-weight:800;color:var(--green-deep);text-align:center;letter-spacing:-0.03em;margin-bottom:40px}
.testimonials-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
.testimonial-card{background:var(--white);border-radius:20px;padding:28px 26px;box-shadow:0 10px 30px rgba(13,27,75,0.06);display:flex;flex-direction:column;min-height:100%}
.testimonial-text{font-size:0.95rem;color:#8B93A7;line-height:1.7;margin-bottom:28px;flex:1}
.testimonial-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:auto}
.testimonial-author{display:flex;align-items:center;gap:12px;min-width:0}
.author-avatar{width:42px;height:42px;border-radius:50%;object-fit:cover;flex-shrink:0;background:#E8EDF5}
.author-info h5{font-size:0.92rem;font-weight:700;color:var(--green-deep);line-height:1.2}
.author-info p{font-size:0.8rem;color:#8B93A7;margin-top:2px}
.testimonial-stars{display:flex;gap:2px;color:#F5B942;font-size:0.85rem;letter-spacing:1px;flex-shrink:0}

/* FAQ */
.faq{padding:88px 0 100px;background:var(--white)}
.faq-title{font-family:var(--font-body);font-size:clamp(2rem,4vw,2.8rem);font-weight:800;color:var(--green-deep);text-align:center;letter-spacing:-0.03em;line-height:1.15;margin-bottom:40px}
.faq-items{max-width:760px;margin:0 auto;border-top:1px solid #E6EAF2}
.faq-item{border-bottom:1px solid #E6EAF2}
.faq-question{width:100%;background:none;border:none;padding:22px 4px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;font-family:var(--font-body);font-size:1.02rem;font-weight:600;color:#0B1220;text-align:left;gap:20px}
.faq-question:hover{color:var(--green-deep)}
.faq-icon{width:20px;height:20px;color:#9AA3B5;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;transition:transform 0.3s,color 0.2s}
.faq-icon svg{width:18px;height:18px}
.faq-item.open .faq-icon{transform:rotate(180deg);color:var(--green-deep)}
.faq-answer{max-height:0;overflow:hidden;transition:max-height 0.35s ease}
.faq-answer.open{max-height:240px}
.faq-answer-inner{padding:0 4px 22px;font-size:0.95rem;color:#6B7280;line-height:1.7;max-width:640px}

/* HOW IT WORKS */
.hiw-section{padding:40px 0 88px;background:var(--white)}
.hiw-layout{
  display:grid;grid-template-columns:0.95fr 1.05fr;gap:28px;align-items:stretch;width:100%;
}
.hiw-media{
  position:relative;border-radius:28px;overflow:hidden;height:100%;min-height:100%;
  background:var(--green-soft);align-self:stretch;
}
.hiw-media img{
  position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;object-position:center
}
.hiw-steps{display:flex;flex-direction:column;gap:14px;height:100%}
.hiw-stat{
  position:absolute;left:20px;bottom:20px;z-index:2;
  display:flex;align-items:center;gap:14px;padding:14px 16px;
  background:var(--white);border-radius:18px;min-width:210px;
}
.hiw-stat-copy strong{
  display:block;font-size:1.55rem;font-weight:800;color:#111827;line-height:1;letter-spacing:-.03em
}
.hiw-stat-copy span{display:block;margin-top:4px;font-size:.78rem;font-weight:600;color:#6B7280}
.hiw-stat-icon{
  width:44px;height:44px;border-radius:50%;background:var(--green-deep);color:var(--white);
  display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;
}
.hiw-stat-icon svg{width:20px;height:20px;stroke-width:2}
.hiw-step{
  background:var(--white);border:1px solid #E6EAF2;border-radius:18px;
  padding:22px 24px;transition:border-color .2s ease;
}
.hiw-step:hover{border-color:#C9D4EE}
.hiw-step-label{
  display:block;margin:0 0 6px;font-size:.78rem;font-weight:700;color:#6B7280;
}
.hiw-step h3{
  margin:0 0 8px;font-size:1.15rem;font-weight:800;color:#111827;letter-spacing:-.02em;line-height:1.25
}
.hiw-step p{margin:0;font-size:.92rem;line-height:1.55;color:#6B7280}
.hiw-actions{margin-top:28px}
.hiw-btn{
  display:inline-flex;align-items:center;justify-content:center;
  padding:13px 22px;border-radius:999px;border:0;
  background:var(--green-deep);color:var(--white);
  font-size:.9rem;font-weight:750;text-decoration:none;box-shadow:none;
  transition:background .2s ease;
}
.hiw-btn:hover{background:var(--green-mid)}

/* CONTACT */
.contact{padding:88px 0;background:#F4F6FA}
.contact-head{margin-bottom:40px;max-width:560px}
.contact-eyebrow{display:block;font-size:0.88rem;font-weight:500;color:#8B93A7;margin-bottom:10px}
.contact-title{font-family:var(--font-body);font-size:clamp(1.8rem,3.2vw,2.5rem);font-weight:800;color:var(--green-deep);letter-spacing:-0.03em;line-height:1.15;margin-bottom:12px}
.contact-sub{font-size:0.98rem;color:#6B7280;line-height:1.7}
.contact-inner{display:grid;grid-template-columns:0.9fr 1.1fr;gap:28px;align-items:stretch}
.contact-info-card,.contact-form{
  background:var(--white);border:1px solid #E6EAF2;border-radius:24px;padding:32px;
  box-shadow:0 10px 30px rgba(13,27,75,0.04);height:100%;
  display:flex;flex-direction:column;
}
.contact-items{display:flex;flex-direction:column;gap:18px}
.contact-item{display:flex;gap:14px;align-items:flex-start}
.contact-item-icon{width:42px;height:42px;border-radius:50%;border:1.5px solid #D5DBE7;display:inline-flex;align-items:center;justify-content:center;color:var(--green-deep);background:#F8FAFF;flex-shrink:0}
.contact-item-icon svg{width:18px;height:18px}
.contact-item-body h5{font-weight:700;font-size:0.9rem;color:var(--green-deep);margin-bottom:3px}
.contact-item-body p,.contact-item-body a{font-size:0.88rem;color:#6B7280;text-decoration:none}
.contact-item-body a:hover{color:var(--green-deep)}
.contact-info-media{
  margin-top:24px;border-radius:16px;overflow:hidden;background:#E8ECF4;
  flex:1;min-height:180px;position:relative;
}
.contact-info-media img{
  position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block
}
.contact-form-title{font-family:var(--font-body);font-size:1.25rem;font-weight:800;color:var(--green-deep);letter-spacing:-0.02em;margin-bottom:22px}
.contact-form form{display:flex;flex-direction:column;flex:1}
.contact-form .contact-submit{margin-top:auto}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.form-group label{font-size:0.78rem;font-weight:600;color:#8B93A7}
.form-group input,.form-group select,.form-group textarea{padding:13px 14px;border:none;border-radius:12px;font-family:var(--font-body);font-size:0.9rem;color:var(--green-deep);background:var(--field);outline:none;transition:box-shadow 0.2s}
.form-group select{appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%238B93A7' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{box-shadow:0 0 0 3px rgba(46,95,194,0.15)}
.form-group textarea{resize:vertical;min-height:110px}
.contact-submit{width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:14px 22px;border:none;border-radius:999px;background:var(--green-deep);color:var(--white);font-family:var(--font-body);font-size:0.92rem;font-weight:700;cursor:pointer;transition:all 0.25s}
.contact-submit:hover{background:var(--green-mid);transform:translateY(-1px)}
.contact-submit svg{width:16px;height:16px}
.contact-alert{border-radius:12px;padding:14px 16px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px;font-size:0.88rem}
.contact-alert svg{width:18px;height:18px;flex-shrink:0;margin-top:1px}
.contact-alert.success{background:#ECFDF5;color:#065F46}
.contact-alert.error{background:#FEF2F2;color:#B91C1C;font-weight:600}

/* FOOTER */
.footer{background:#07164F;color:rgba(255,255,255,0.7);padding:72px 0 0;position:relative;isolation:isolate}
.footer::before{content:'';position:absolute;inset:0;pointer-events:none;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");background-size:140px 140px;opacity:0.2;mix-blend-mode:soft-light}
.footer .container{position:relative;z-index:1}
.footer-grid{display:grid;grid-template-columns:1.8fr 1fr 1fr 1fr;gap:40px;margin-bottom:48px}
.footer-brand{display:flex;align-items:center;gap:10px;margin-bottom:14px;text-decoration:none}
.footer-brand img{width:40px;height:40px;object-fit:contain}
.footer-brand span{font-family:var(--font-body);font-size:1.2rem;font-weight:800;color:var(--white);letter-spacing:-0.02em}
.footer-desc{font-size:0.88rem;line-height:1.75;color:rgba(255,255,255,0.58);margin-bottom:20px;max-width:320px}
.social-links{display:flex;gap:10px}
.social-link{width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.18);display:inline-flex;align-items:center;justify-content:center;color:var(--white);transition:all 0.25s;text-decoration:none}
.social-link svg{width:17px;height:17px;display:block}
.social-link:hover{background:rgba(255,255,255,0.14);border-color:rgba(255,255,255,0.35)}
.footer-col h5{font-family:var(--font-body);font-size:0.92rem;font-weight:700;color:var(--white);margin-bottom:16px}
.footer-links{display:flex;flex-direction:column;gap:10px;list-style:none}
.footer-links a{font-size:0.88rem;color:rgba(255,255,255,0.55);transition:color 0.2s;text-decoration:none}
.footer-links a:hover{color:var(--white)}
.footer-bottom{border-top:1px solid rgba(255,255,255,0.1);padding:22px 0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.footer-bottom p{font-size:0.82rem;color:rgba(255,255,255,0.42)}
.footer-bottom-links{display:flex;gap:20px}
.footer-bottom a{font-size:0.82rem;color:rgba(255,255,255,0.42);transition:color 0.2s;text-decoration:none}
.footer-bottom a:hover{color:var(--white)}
.footer-credit{position:relative;z-index:1;text-align:center;padding:14px 0;border-top:1px solid rgba(255,255,255,0.08);font-size:0.76rem;color:rgba(255,255,255,0.32)}
.footer-credit a{color:rgba(255,255,255,0.5);text-decoration:none}
.footer-credit a:hover{color:var(--white)}

/* REVEAL */
.reveal{opacity:0;transform:translateY(30px);transition:opacity 0.7s ease,transform 0.7s ease}
.reveal.visible{opacity:1;transform:translateY(0)}
.reveal-delay-1{transition-delay:0.1s}
.reveal-delay-2{transition-delay:0.2s}
.reveal-delay-3{transition-delay:0.3s}

/* RESPONSIVE */
@media(max-width:1024px){
  .career-inner,.contact-inner{grid-template-columns:1fr;gap:48px}
  .contact-info-card,.contact-form{height:auto}
  .contact-info-media{position:relative;min-height:220px;aspect-ratio:16/10;flex:none}
  .contact-info-media img{position:absolute}
  .footer-grid{grid-template-columns:1fr 1fr;gap:32px}
  .hero-grid{grid-template-columns:1fr;gap:40px}
  .hero{min-height:auto;padding:110px 0 56px}
  .hero-copy{max-width:none}
  .hero-photo{aspect-ratio:5/4;max-height:480px}
  .demo-card-top{left:12px}
  .demo-card-bottom{right:12px}
  .why-panel-inner{grid-template-columns:1fr;gap:40px}
  .why-intro-text{max-width:none}
  .why-features{grid-template-columns:repeat(2,1fr);gap:24px}
  .needs-card{flex-basis:calc((100% - 44px) / 3)}
  .hiw-layout{grid-template-columns:1fr;gap:24px}
  .hiw-media{min-height:360px;height:auto;aspect-ratio:4/3}
  .hiw-media img{position:relative}
  .featured-grid{grid-template-columns:repeat(2,1fr);gap:14px}
  .featured-head{flex-direction:column;align-items:flex-start}
  .testimonials-grid{grid-template-columns:1fr;max-width:520px;margin:0 auto}
}
@media(max-width:768px){
  .nav-links,.nav-cta{display:none}.hamburger{display:flex}
  .hero-search-grid{grid-template-columns:1fr}
  .hero-title{font-size:2.2rem}
  .hero-trust{flex-wrap:wrap}
  .why-tiles-track{grid-template-columns:repeat(3,1fr)}
  .why-tiles-track .why-tile:nth-child(n+4){display:none}
  .why-panel{padding:56px 0 64px}
  .why-features{grid-template-columns:1fr}
  .needs-section{padding:64px 0 48px}
  .needs-card{flex-basis:calc((100% - 22px) / 2)}
  .needs-card-body h3{font-size:1.05rem}
  .featured-grid{grid-template-columns:1fr}
  .featured-section{padding:48px 0}
  .form-row{grid-template-columns:1fr}.footer-grid{grid-template-columns:1fr;gap:28px}
  .footer-bottom{flex-direction:column;text-align:center}
  .career-right{padding:32px 24px}
  .contact-form,.contact-info-card{padding:24px 20px}
  .testimonials-grid{grid-template-columns:1fr}
  .hiw-section{padding:64px 0 56px}
  .hiw-media{min-height:280px;aspect-ratio:5/4}
}
@media(max-width:560px){
  .needs-card{flex-basis:86%}
}
@media(max-width:480px){
  .demo-card{padding:6px 10px 6px 6px}
  .demo-card-name{max-width:90px}
}
</style>
</head>
<body>

<?php $nav_active = 'home'; include __DIR__ . '/includes/public-header.php'; ?>

<!-- HERO -->
<section class="hero" id="home">
  <div class="container">
    <div class="hero-grid">
      <div class="hero-copy">
        <h1 class="hero-title">Reliable Home Help,<br/>Right at your doorstep.</h1>
        <p class="hero-lead">From busy mornings to quiet evenings — find trusted help for your home across Rwanda.</p>

        <form class="hero-search" method="GET" action="/find-worker">
          <div class="hero-search-title">Quick worker search</div>
          <p class="hero-search-sub">Search real discoverable worker profiles on HomeHelp.</p>

          <div class="hero-search-grid">
            <div class="hero-field">
              <label for="hero-service">Job category</label>
              <select id="hero-service" name="service">
                <option value="">All categories</option>
                <?php foreach ($db_services as $svc): ?>
                  <option value="<?= (int)$svc['id'] ?>"><?= htmlspecialchars($svc['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="hero-field">
              <label for="hero-district">District or location</label>
              <select id="hero-district" name="district">
                <option value="">All districts</option>
                <?php
                $districts = ['Gasabo','Kicukiro','Nyarugenge','Bugesera','Gatsibo','Kayonza','Kirehe','Ngoma','Nyagatare','Rwamagana','Burera','Gakenke','Gicumbi','Musanze','Rulindo','Gisagara','Huye','Kamonyi','Muhanga','Nyamagabe','Nyanza','Nyaruguru','Ruhango','Karongi','Ngororero','Nyabihu','Nyamasheke','Rubavu','Rusizi','Rutsiro'];
                sort($districts);
                foreach ($districts as $d): ?>
                  <option value="<?= $d ?>"><?= $d ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="hero-field">
              <label for="hero-experience">Experience level</label>
              <select id="hero-experience" name="experience">
                <option value="">Any experience</option>
                <option value="0-1">0–1 years</option>
                <option value="1-3">1–3 years</option>
                <option value="3-5">3–5 years</option>
                <option value="5+">5+ years</option>
              </select>
            </div>
            <div class="hero-field">
              <label for="hero-availability">Availability</label>
              <select id="hero-availability" name="availability">
                <option value="">Any availability</option>
                <option value="full-time">Full-time</option>
                <option value="part-time">Part-time</option>
                <option value="live-in">Live-in</option>
                <option value="weekends">Weekends</option>
              </select>
            </div>
          </div>

          <button type="submit" class="btn-search-workers">Search Workers</button>
        </form>

        <div class="hero-trust">
          <div class="hero-avatars">
            <img src="Image/profile-placeholder.png" alt=""/>
            <img src="Image/profile-placeholder.png" alt=""/>
            <img src="Image/profile-placeholder.png" alt=""/>
            <img src="Image/profile-placeholder.png" alt=""/>
            <img src="Image/profile-placeholder.png" alt=""/>
          </div>
          <div class="hero-trust-meta">
            <div class="hero-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
            <div class="hero-trust-text">4.9/5 Trusted by households across Rwanda</div>
          </div>
        </div>
      </div>

      <div class="hero-visual">
        <img class="hero-photo" src="Image/slider/Slider_3.jpeg" alt="Home Help worker in a kitchen"/>
        <div class="demo-card demo-card-top">
          <img src="Image/profile-placeholder.png" alt=""/>
          <div class="demo-card-info">
            <span class="demo-card-label">HomeHelp</span>
            <span class="demo-card-name">Workers available</span>
          </div>
          <span class="demo-card-star">&#9733;</span>
        </div>
        <div class="demo-card demo-card-bottom">
          <img src="Image/profile-placeholder.png" alt=""/>
          <div class="demo-card-info">
            <span class="demo-card-label"><?= $workers_available_count > 0 ? (int)$workers_available_count . ' ready' : 'Ready to hire' ?></span>
            <span class="demo-card-name">Workers available</span>
          </div>
          <span class="demo-card-star">&#9733;</span>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
$needs_find_id = function ($needles) use ($db_services) {
  foreach ($db_services as $svc) {
    $name = strtolower($svc['name']);
    foreach ((array)$needles as $needle) {
      if (strpos($name, strtolower($needle)) !== false) return (int)$svc['id'];
    }
  }
  return 0;
};
$needs_services = [
  [
    'title' => 'Housemaids & Housekeepers',
    'blurb' => 'Keep your home running smoothly with reliable daily help and household care.',
    'img' => 'Image/service-housekeeping.jpg',
    'id' => $needs_find_id(['housekeep', 'housemaid', 'maid']),
  ],
  [
    'title' => 'Home Cleaners',
    'blurb' => 'Keep your rooms spotless with routine dusting, mopping, and organized care.',
    'img' => 'Image/service-cleaning.jpg',
    'id' => $needs_find_id(['clean']),
  ],
  [
    'title' => 'Nannies & Childcare',
    'blurb' => 'Trusted childcare support for full-time, part-time, or flexible schedules.',
    'img' => 'Image/service-nanny.jpg',
    'id' => $needs_find_id(['nanny', 'child', 'babysit']),
  ],
  [
    'title' => 'Cooks & Home Chefs',
    'blurb' => 'Home-cooked meals prepared to your taste for daily cooking or special occasions.',
    'img' => 'Image/service-chef.jpg',
    'id' => $needs_find_id(['chef', 'cook']),
  ],
  [
    'title' => 'Laundry & Ironing',
    'blurb' => 'Fresh clothes, washed, ironed, and folded — ready to wear every day.',
    'img' => 'Image/service-laundry.jpg',
    'id' => $needs_find_id(['laundry', 'iron']),
  ],
];
?>
<!-- HOME SERVICES -->
<section class="needs-section" id="home-services">
  <div class="container">
    <div class="needs-head reveal">
      <h2 class="needs-title">Home services built around your needs</h2>
      <p class="needs-sub">Choose a category and browse verified workers ready to help at home.</p>
    </div>

    <div class="needs-carousel reveal" id="needsCarousel">
      <div class="needs-track" id="needsTrack">
        <?php foreach ($needs_services as $i => $svc):
          $book_attrs = 'data-open-book';
          if (!empty($svc['id'])) $book_attrs .= ' data-book-service="' . (int)$svc['id'] . '"';
        ?>
        <a class="needs-card<?= $i === 0 ? ' is-active' : '' ?>" href="/register" <?= $book_attrs ?> data-needs-card>
          <div class="needs-card-media">
            <img src="<?= htmlspecialchars($svc['img']) ?>" alt="<?= htmlspecialchars($svc['title']) ?>"/>
          </div>
          <div class="needs-card-body">
            <h3><?= htmlspecialchars($svc['title']) ?></h3>
            <p><?= htmlspecialchars($svc['blurb']) ?></p>
            <span class="needs-book">Book Service</span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <div class="needs-nav">
        <div class="needs-arrows">
          <button type="button" class="needs-arrow" id="needsPrev" aria-label="Previous services">
            <i data-lucide="chevron-left"></i>
          </button>
          <button type="button" class="needs-arrow" id="needsNext" aria-label="Next services">
            <i data-lucide="chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="hiw-section" id="how-it-works">
  <div class="container">
    <div class="hiw-head reveal">
      <h2 class="hiw-title">How it works</h2>
      <p class="hiw-sub">Three simple steps — from your first request to help arriving at your door.</p>
    </div>
    <div class="hiw-layout reveal">
      <div class="hiw-media">
        <img src="Image/how-it-works-hero.jpg" alt="Home Help workers supporting a household in Rwanda"/>
        <div class="hiw-stat">
          <div class="hiw-stat-copy">
            <strong>98%</strong>
            <span>Customer Satisfaction</span>
          </div>
          <div class="hiw-stat-icon" aria-hidden="true"><i data-lucide="badge-check"></i></div>
        </div>
      </div>
      <div class="hiw-steps">
        <article class="hiw-step">
          <span class="hiw-step-label">Step 1</span>
          <h3>Share what your home needs</h3>
          <p>Tell us the service, your schedule, and any preferences. Registration is free and only takes a few minutes.</p>
        </article>
        <article class="hiw-step">
          <span class="hiw-step-label">Step 2</span>
          <h3>Meet verified workers who fit</h3>
          <p>We review your request and connect you with trusted, vetted workers matched to your household.</p>
        </article>
        <article class="hiw-step">
          <span class="hiw-step-label">Step 3</span>
          <h3>Settle in while help arrives</h3>
          <p>Approve your match, stay updated by email, and enjoy reliable support at home with peace of mind.</p>
        </article>
        <div class="hiw-actions">
          <a href="/register" class="hiw-btn" data-open-book>Start Your Booking</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FEATURED WORKERS -->
<section class="featured-section" id="featured-workers">
  <div class="container">
    <div class="featured-head reveal">
      <div>
        <h2 class="featured-title">Featured workers</h2>
        <p class="featured-sub">Discoverable profiles from workers available on HomeHelp.</p>
      </div>
      <a href="/find-worker" class="featured-browse">Browse all workers</a>
    </div>
    <?php if (empty($featured_workers)): ?>
      <p class="featured-sub reveal">No workers available yet. <a href="/find-worker" style="color:var(--green-deep);font-weight:700">Browse workers</a></p>
    <?php else: ?>
    <div class="featured-grid">
      <?php foreach ($featured_workers as $i => $w):
        $meta_bits = [$w['exp']];
        if (!empty($w['age'])) $meta_bits[] = (int)$w['age'] . ' yrs';
        if ($w['place'] !== '') $meta_bits[] = $w['place'];
        if ($w['availability'] !== '') $meta_bits[] = $w['availability'];
        if ($w['languages'] !== '') $meta_bits[] = $w['languages'];
        if ($w['education'] !== '') $meta_bits[] = $w['education'];
        if ($w['pay'] !== '') $meta_bits[] = $w['pay'];
        $meta_line = implode(' · ', $meta_bits);
      ?>
      <article class="featured-card reveal<?= $i % 4 === 0 ? '' : ' reveal-delay-' . min(3, $i % 4) ?>">
        <div class="featured-card-media">
          <img src="<?= htmlspecialchars($w['img']) ?>" alt="<?= htmlspecialchars($w['name']) ?>"/>
        </div>
        <div class="featured-card-body">
          <div class="featured-card-name"><?= htmlspecialchars($w['name']) ?></div>
          <div class="featured-card-role"><?= htmlspecialchars($w['role']) ?></div>
          <div class="featured-card-meta" title="<?= htmlspecialchars($w['about'] !== '' ? $w['about'] : $meta_line) ?>"><?= htmlspecialchars($meta_line) ?></div>
          <a href="/find-worker" class="featured-card-btn">View Profile</a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php
$why_service_lucide = function ($name) {
  $n = strtolower($name);
  if (strpos($n, 'clean') !== false) return 'sparkles';
  if (strpos($n, 'chef') !== false || strpos($n, 'cook') !== false) return 'utensils-crossed';
  if (strpos($n, 'house') !== false || strpos($n, 'maid') !== false) return 'home';
  if (strpos($n, 'nanny') !== false || strpos($n, 'care') !== false) return 'heart-handshake';
  return 'badge-check';
};
$why_services = [
  ['name' => 'Cleaning', 'tag' => 'Most Requested'],
  ['name' => 'Housekeeping', 'tag' => 'Available'],
  ['name' => 'Chefs', 'tag' => 'Available'],
];
if (!empty($homepage_services)) {
  $tags = ['Most Requested', 'Available', 'Available'];
  $why_services = [];
  foreach (array_slice($homepage_services, 0, 3) as $i => $svc) {
    $why_services[] = [
      'name' => $svc['name'],
      'tag'  => $tags[$i] ?? 'Available',
    ];
  }
}
?>
<!-- WHY CHOOSING US -->
<section class="why-section" id="services">
<div class="why-band">
  <div class="why-tiles">
    <div class="why-tiles-track">
      <div class="why-tile" style="background-image:url('Image/slider/Slider_1.jpeg')"></div>
      <div class="why-tile" style="background-image:url('Image/slider/Slider_2.jpeg')"></div>
      <div class="why-tile" style="background-image:url('Image/slider/Slider_3.jpeg')"></div>
      <div class="why-tile" style="background-image:url('Image/slider/Slider_1.jpeg');background-position:70% center"></div>
      <div class="why-tile" style="background-image:url('Image/slider/Slider_2.jpeg');background-position:30% center"></div>
    </div>
  </div>

  <div class="why-panel">
    <div class="container">
      <div class="why-panel-inner reveal">
        <div class="why-intro">
          <h2 class="why-intro-title">Why Choosing Us</h2>
          <p class="why-intro-text">We carefully select and vet our workers, then connect you with the right people for your home. Get ongoing support and access to trusted professionals whenever you need them.</p>
        </div>

        <div class="why-features">
          <article class="why-feature">
            <div class="why-feature-head">
              <div class="why-feature-icon" aria-hidden="true"><i data-lucide="shield-check"></i></div>
              <h3>Trusted and reliable workers</h3>
            </div>
            <p>We carefully select and vet our workers.</p>
            <a class="why-feature-link" href="/find-worker">Learn More <i data-lucide="arrow-right"></i></a>
          </article>

          <article class="why-feature">
            <div class="why-feature-head">
              <div class="why-feature-icon" aria-hidden="true"><i data-lucide="crosshair"></i></div>
              <h3>Matched to your needs</h3>
            </div>
            <p>We connect you with the right workers based on your specific needs.</p>
            <a class="why-feature-link" href="/find-worker">Learn More <i data-lucide="arrow-right"></i></a>
          </article>

          <article class="why-feature">
            <div class="why-feature-head">
              <div class="why-feature-icon" aria-hidden="true"><i data-lucide="badge-check"></i></div>
              <h3>Professional quality services</h3>
            </div>
            <p>Trained help that keeps standards high, visit after visit.</p>
            <a class="why-feature-link" href="#home-services">Learn More <i data-lucide="arrow-right"></i></a>
          </article>

          <?php foreach ($why_services as $svc): ?>
          <article class="why-feature">
            <div class="why-feature-head">
              <div class="why-feature-icon" aria-hidden="true"><i data-lucide="<?= htmlspecialchars($why_service_lucide($svc['name'])) ?>"></i></div>
              <h3><?= htmlspecialchars($svc['name']) ?></h3>
            </div>
            <span class="why-feature-tag"><?= htmlspecialchars($svc['tag']) ?></span>
            <p>Book verified <?= htmlspecialchars(strtolower($svc['name'])) ?> professionals through HomeHelp.</p>
            <a class="why-feature-link" href="/find-worker">Learn More <i data-lucide="arrow-right"></i></a>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</section>

<?php
$testimonials = [
  [
    'quote' => 'I found a reliable housekeeper for my family within a week. Comparing experience was much easier — our home finally felt settled again.',
    'name'  => 'Jeanine Uwase',
    'place' => 'Kigali',
    'img'   => 'Image/profile-placeholder.png',
  ],
  [
    'quote' => 'Creating my worker profile helped me present my skills professionally. I received a hiring request soon after and felt proud of my work.',
    'name'  => 'Claudine Mukamana',
    'place' => 'Musanze',
    'img'   => 'Image/profile-placeholder.png',
  ],
  [
    'quote' => 'Clear filters by district and category saved time when we needed a caregiver. The process felt calm, not complicated.',
    'name'  => 'Patrick Niyonsenga',
    'place' => 'Huye',
    'img'   => 'Image/profile-placeholder.png',
  ],
];
?>
<!-- TESTIMONIALS -->
<section class="testimonials" id="testimonials">
  <div class="container">
    <h2 class="testimonials-title reveal">Testimonials</h2>
    <div class="testimonials-grid">
      <?php foreach ($testimonials as $i => $t): ?>
      <article class="testimonial-card reveal reveal-delay-<?= $i + 1 ?>">
        <p class="testimonial-text">&ldquo;<?= htmlspecialchars($t['quote']) ?>&rdquo;</p>
        <div class="testimonial-footer">
          <div class="testimonial-author">
            <img class="author-avatar" src="<?= htmlspecialchars($t['img']) ?>" alt="<?= htmlspecialchars($t['name']) ?>"/>
            <div class="author-info">
              <h5><?= htmlspecialchars($t['name']) ?></h5>
              <p><?= htmlspecialchars($t['place']) ?></p>
            </div>
          </div>
          <div class="testimonial-stars" aria-label="5 out of 5 stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php
$faq_items = [
  ['q' => 'How do I register as a worker?', 'a' => 'Create a free Service Provider account, complete your profile, then apply for a job category under Career. Upload your ID and CV so our team can review your application.'],
  ['q' => 'How can I find a domestic worker?', 'a' => 'Use Find a Worker to browse verified profiles, filter by service and district, then send a hiring request from your client dashboard.'],
  ['q' => 'Is registration free?', 'a' => 'Yes. Registration is free for both households and workers. You only pay for the domestic services you book.'],
  ['q' => 'How are workers verified?', 'a' => 'Applicants submit national ID, CV, and references. Our team manually screens each application before a worker can be approved and listed.'],
  ['q' => 'How can I update my profile?', 'a' => 'Log in to your dashboard, open Profile, update your details or documents, then save. Changes appear after review when required.'],
  ['q' => 'How do hiring requests work?', 'a' => 'Clients submit a booking request with service type, location, and start date. Our team reviews it and updates you as the request moves through Pending, Under Review, and Approved.'],
  ['q' => 'How do I report a suspicious account?', 'a' => 'Contact us through the Contact form or WhatsApp support with the account details. We review reports quickly and take action when needed.'],
  ['q' => 'How does HomeHelp protect personal information?', 'a' => 'We store account data securely, limit access to authorized team members, and never sell personal information. See our Privacy Policy for full details.'],
];
?>
<!-- FAQ -->
<section class="faq" id="faq">
  <div class="container">
    <h2 class="faq-title reveal">Questions? Answers.</h2>
    <div class="faq-items reveal">
      <?php foreach ($faq_items as $i => $item): ?>
      <div class="faq-item<?= $i === 0 ? ' open' : '' ?>">
        <button class="faq-question" type="button" onclick="toggleFAQ(this)">
          <span><?= htmlspecialchars($item['q']) ?></span>
          <span class="faq-icon" aria-hidden="true"><i data-lucide="chevron-down"></i></span>
        </button>
        <div class="faq-answer<?= $i === 0 ? ' open' : '' ?>">
          <div class="faq-answer-inner"><?= htmlspecialchars($item['a']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CONTACT -->
<section class="contact" id="contact">
  <div class="container">
    <div class="contact-head reveal">
      <span class="contact-eyebrow">Get In Touch</span>
      <h2 class="contact-title">We&#8217;re Here to Help</h2>
      <p class="contact-sub">Have a question or need support? Reach out and our team will respond promptly.</p>
    </div>
    <div class="contact-inner">
      <div class="contact-info-card reveal">
        <div class="contact-items">
          <div class="contact-item">
            <div class="contact-item-icon" aria-hidden="true"><i data-lucide="map-pin"></i></div>
            <div class="contact-item-body"><h5>Location</h5><p>Kigali, Rwanda</p></div>
          </div>
          <div class="contact-item">
            <div class="contact-item-icon" aria-hidden="true"><i data-lucide="message-circle"></i></div>
            <div class="contact-item-body"><h5>WhatsApp</h5><p><a href="https://wa.me/250790533144">+250 790 533 144</a></p></div>
          </div>
          <div class="contact-item">
            <div class="contact-item-icon" aria-hidden="true"><i data-lucide="mail"></i></div>
            <div class="contact-item-body"><h5>Email</h5><p><a href="mailto:info@homehelp.rw">info@homehelp.rw</a></p></div>
          </div>
          <div class="contact-item">
            <div class="contact-item-icon" aria-hidden="true"><i data-lucide="clock"></i></div>
            <div class="contact-item-body"><h5>Working Hours</h5><p>Mon &ndash; Sat: 7:00 AM &ndash; 7:00 PM</p></div>
          </div>
        </div>
        <div class="contact-info-media">
          <img src="Image/contact-kigali.jpg" alt="Kigali, Rwanda"/>
        </div>
      </div>
      <div class="contact-form reveal reveal-delay-2">
        <h3 class="contact-form-title">Send Us a Message</h3>

        <?php if($contact_success): ?>
          <div class="contact-alert success">
            <i data-lucide="circle-check"></i>
            <div>
              <strong>Message sent successfully!</strong>
              <div style="margin-top:3px;font-weight:500">Thank you! We will get back to you shortly.</div>
            </div>
          </div>
        <?php endif; ?>

        <?php if($contact_error): ?>
          <div class="contact-alert error">
            <i data-lucide="circle-alert"></i>
            <span><?= htmlspecialchars($contact_error) ?></span>
          </div>
        <?php endif; ?>

        <?php if(!$contact_success): ?>
        <form method="POST" action="#contact" id="contactForm">
          <div class="form-row">
            <div class="form-group">
              <label>First Name *</label>
              <input type="text" name="first_name" placeholder="Amina" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required/>
            </div>
            <div class="form-group">
              <label>Last Name *</label>
              <input type="text" name="last_name" placeholder="Uwase" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required/>
            </div>
          </div>
          <div class="form-group">
            <label>Email Address *</label>
            <input type="email" name="email" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
          </div>
          <div class="form-group">
            <label>Topic</label>
            <select name="topic">
              <option value="">Select a topic...</option>
              <?php
              $topics = ['Book a Service','Job Application Help','Account Issue','Partnership','Other'];
              foreach($topics as $t):
                $sel = (($_POST['topic'] ?? '') === $t) ? 'selected' : '';
              ?>
                <option value="<?= $t ?>" <?= $sel ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Message *</label>
            <textarea name="message" placeholder="How can we help you?" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
          </div>
          <input type="hidden" name="contact_submit" value="1"/>
          <button type="submit" class="contact-submit">Send Message <i data-lucide="arrow-right"></i></button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <a href="#home" class="footer-brand">
          <img src="Image/icon/homehelp_logo_transparent_white.svg" alt="Home Help"/>
          <span>HomeHelp</span>
        </a>
        <p class="footer-desc">Rwanda&#8217;s trusted marketplace for domestic services, connecting households with verified workers across the country.</p>
        <div class="social-links">
          <a class="social-link" href="https://www.facebook.com/" target="_blank" rel="noopener" aria-label="Facebook">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h1.5V2.14C17.026 2.087 15.824 2 14.647 2 11.928 2 10 3.657 10 6.7v2.8H7v4h3V22h4z"/></svg>
          </a>
          <a class="social-link" href="https://www.instagram.com/" target="_blank" rel="noopener" aria-label="Instagram">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5M12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10m0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
          </a>
          <a class="social-link" href="https://x.com/" target="_blank" rel="noopener" aria-label="X">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.743l7.727-8.835L1.254 2.25H8.08l4.264 5.686L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z"/></svg>
          </a>
          <a class="social-link" href="https://wa.me/250790533144" target="_blank" rel="noopener" aria-label="WhatsApp">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
          </a>
        </div>
      </div>
      <div class="footer-col">
        <h5>Services</h5>
        <ul class="footer-links">
          <li><a href="/home-cleaning">Home Cleaning Kigali</a></li>
          <li><a href="/housekeeping">Housekeeping</a></li>
          <li><a href="/home-chef">Home Chef</a></li>
          <li><a href="/nanny">Nanny &amp; Caregiver</a></li>
          <li><a href="/office-cleaning">Office Cleaning</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Platform</h5>
        <ul class="footer-links">
          <li><a href="#home-services">Services</a></li>
          <li><a href="#how-it-works">How It Works</a></li>
          <li><a href="#testimonials">Stories</a></li>
          <li><a href="/career">Career</a></li>
          <li><a href="#contact">Contact</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Account</h5>
        <ul class="footer-links">
          <li><a href="/login" data-open-login>Login</a></li>
          <li><a href="/register" data-open-register>Register</a></li>
          <li><a href="/worker-login" data-open-worker-login>Job Seeker Login</a></li>
          <li><a href="/worker-register">Job Seeker Register</a></li>
          <li><a href="/admin-login">Admin Login</a></li>
          <li><a href="/staff-login">Team Login</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&#169; <?php echo date('Y'); ?> Home Help &middot; Rwanda</p>
      <div class="footer-bottom-links"><a href="/privacy">Privacy Policy</a><a href="/terms">Terms of Service</a></div>
    </div>
  </div>
</footer>

<script>
var observer=new IntersectionObserver(function(entries){entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add('visible');observer.unobserve(e.target);}});},{threshold:0.12});
document.querySelectorAll('.reveal').forEach(function(el){observer.observe(el);});
function toggleFAQ(btn){var item=btn.parentElement;var answer=item.querySelector('.faq-answer');var isOpen=item.classList.contains('open');document.querySelectorAll('.faq-item').forEach(function(i){i.classList.remove('open');i.querySelector('.faq-answer').classList.remove('open');});if(!isOpen){item.classList.add('open');answer.classList.add('open');}}
document.querySelectorAll('a[href^="#"]').forEach(function(link){link.addEventListener('click',function(e){var href=link.getAttribute('href');if(!href||href==='#')return;var target=document.querySelector(href);if(target){e.preventDefault();target.scrollIntoView({behavior:'smooth',block:'start'});}});});
if(window.lucide){lucide.createIcons();}

(function(){
  var track=document.getElementById('needsTrack');
  var prev=document.getElementById('needsPrev');
  var next=document.getElementById('needsNext');
  if(!track||!prev||!next) return;
  var cards=[].slice.call(track.querySelectorAll('[data-needs-card]'));

  function cardStep(){
    if(!cards.length) return 0;
    var style=window.getComputedStyle(track);
    var gap=parseFloat(style.columnGap||style.gap)||0;
    return cards[0].getBoundingClientRect().width+gap;
  }
  function maxScroll(){
    return Math.max(0, track.scrollWidth - track.clientWidth);
  }
  function update(){
    var max=maxScroll();
    prev.disabled=track.scrollLeft<=4;
    next.disabled=track.scrollLeft>=max-4;
    var activeIdx=Math.round(track.scrollLeft / Math.max(1, cardStep()));
    cards.forEach(function(c,i){ c.classList.toggle('is-active', i===activeIdx); });
  }
  function scrollByDir(dir){
    track.scrollBy({left:dir*cardStep(), behavior:'smooth'});
  }
  prev.addEventListener('click', function(){ scrollByDir(-1); });
  next.addEventListener('click', function(){ scrollByDir(1); });
  track.addEventListener('scroll', function(){ window.requestAnimationFrame(update); }, {passive:true});
  window.addEventListener('resize', update);
  update();
})();
</script>
<?php
include __DIR__ . '/includes/worker-login-modal.php';
include __DIR__ . '/includes/client-login-modal.php';
include __DIR__ . '/includes/register-modal.php';
include __DIR__ . '/includes/book-modal.php';
?>
</body>
</html>