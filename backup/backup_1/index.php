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

// Filter to only 3 services for homepage display (Cleaning, Housekeeping, Chef)
$homepage_services = array_slice($db_services, 0, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Home Help Rwanda | Trusted Domestic Services — Nannies, Housekeepers & Chefs</title>
<meta name="description" content="Home Help connects Rwandan households with verified nannies, housekeepers, chefs, caregivers and more. Book a trusted domestic worker in Kigali and across Rwanda. Free to register."/>
<meta name="keywords" content="domestic services Rwanda, nanny Kigali, housekeeper Rwanda, home chef Kigali, caregiver Rwanda, domestic worker Rwanda, house help Rwanda, babysitter Kigali, cleaning services Rwanda, homehelp.rw"/>
<meta name="author" content="Brilliant Elite Ltd"/>
<meta name="robots" content="index, follow"/>
<link rel="canonical" href="https://homehelp.rw/"/>
<meta property="og:type"        content="website"/>
<meta property="og:url"         content="https://homehelp.rw/"/>
<meta property="og:title"       content="Home Help Rwanda | Trusted Domestic Services"/>
<meta property="og:description" content="Connect with verified nannies, housekeepers, chefs and caregivers across Rwanda. Book a domestic worker today — free to register!"/>
<meta property="og:image"       content="https://homehelp.rw/Image/og-image.jpg"/>
<meta property="og:image:width"  content="1200"/>
<meta property="og:image:height" content="630"/>
<meta property="og:site_name"   content="Home Help Rwanda"/>
<meta property="og:locale"      content="en_RW"/>
<meta name="twitter:card"        content="summary_large_image"/>
<meta name="twitter:title"       content="Home Help Rwanda | Trusted Domestic Services"/>
<meta name="twitter:description" content="Connect with verified domestic workers across Rwanda. Nannies, housekeepers, chefs and more."/>
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
  "logo": "https://homehelp.rw/Image/icon/homehelp_logo_transparent_dark.svg",
  "image": "https://homehelp.rw/Image/og-image.jpg",
  "telephone": "+250790533144",
  "email": "info@homehelp.rw",
  "address": {"@type":"PostalAddress","addressLocality":"Kigali","addressCountry":"RW"},
  "geo": {"@type":"GeoCoordinates","latitude":-1.9441,"longitude":30.0619},
  "openingHours": "Mo-Sa 07:00-19:00",
  "priceRange": "Free to register",
  "areaServed": "Rwanda",
  "serviceType": ["Nanny","Housekeeper","Home Chef","Caregiver","Pet Groomer"]
}
</script>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>

<style>
:root{
  --green-deep:#0D1B4B;--green-mid:#152268;--green-light:#2E5FC2;--green-soft:#D4DCF5;
  --amber:#2E5FC2;--amber-light:#D4DCF5;--cream:#FDFAF5;--cream-dark:#F2EDE3;
  --brown:#0D1B4B;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --white:#FFFFFF;
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
.container{width:90%;max-width:1200px;margin:0 auto}
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
.navbar{position:fixed;top:0;left:0;right:0;z-index:1000;padding:14px 0;transition:all 0.4s ease;background:transparent}
.navbar.scrolled{background:var(--white);padding:10px 0;box-shadow:var(--shadow-sm)}
.navbar-inner{display:flex;align-items:center;justify-content:space-between;gap:16px}
.nav-logo{display:flex;align-items:center;gap:10px;flex-shrink:0}
.nav-logo img{width:44px;height:44px;object-fit:contain}
.nav-logo-text{font-family:var(--font-display);font-size:1.45rem;font-weight:700;color:var(--white);transition:color 0.3s}
.navbar.scrolled .nav-logo-text{color:var(--green-deep)}
.nav-links{display:flex;align-items:center;gap:28px;list-style:none}
.nav-links a{font-size:0.88rem;font-weight:500;color:rgba(255,255,255,0.85);transition:color 0.2s;position:relative;text-decoration:none}
.navbar.scrolled .nav-links a{color:var(--text-mid)}
.nav-links a::after{content:'';position:absolute;bottom:-3px;left:0;width:0;height:2px;background:var(--green-light);border-radius:2px;transition:width 0.3s ease}
.nav-links a:hover::after{width:100%}
.nav-links a:hover{color:var(--white)}
.navbar.scrolled .nav-links a:hover{color:var(--green-deep)}
.nav-cta{display:flex;align-items:center;gap:10px;flex-shrink:0}
.nav-login{font-size:0.88rem;font-weight:600;color:rgba(255,255,255,0.92);transition:color 0.3s;text-decoration:none}
.navbar.scrolled .nav-login{color:var(--text-mid)}
.btn-find-worker{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:99px;background:var(--amber);color:var(--white);font-size:0.82rem;font-weight:700;border:none;cursor:pointer;transition:all 0.3s;white-space:nowrap;text-decoration:none}
.btn-find-worker:hover{background:var(--green-mid);transform:translateY(-2px);box-shadow:0 6px 20px rgba(46,95,194,0.4)}
.btn-find-worker svg{width:15px;height:15px;flex-shrink:0}
.btn-get-started{display:inline-flex;align-items:center;padding:10px 20px;border-radius:99px;font-family:var(--font-body);font-size:0.85rem;font-weight:600;cursor:pointer;transition:all 0.3s ease;text-decoration:none;white-space:nowrap;background:rgba(255,255,255,0.15);color:var(--white);border:1.5px solid rgba(255,255,255,0.4);backdrop-filter:blur(6px)}
.btn-get-started:hover{background:rgba(255,255,255,0.28);transform:translateY(-2px)}
.navbar.scrolled .btn-get-started{background:var(--green-deep);color:var(--white);border-color:var(--green-deep);backdrop-filter:none}
.navbar.scrolled .btn-get-started:hover{background:var(--green-mid);transform:translateY(-2px);box-shadow:var(--shadow-md)}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:4px;background:none;border:none}
.hamburger span{display:block;width:24px;height:2px;background:var(--white);border-radius:2px;transition:all 0.3s}
.navbar.scrolled .hamburger span{background:var(--green-deep)}
.hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg)}
.hamburger.open span:nth-child(2){opacity:0}
.hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}

/* MOBILE MENU */
.mobile-menu{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:var(--green-deep);z-index:999;flex-direction:column;align-items:center;justify-content:flex-start;padding:90px 20px 40px;gap:0;opacity:0;pointer-events:none;transition:opacity 0.3s ease;overflow-y:auto}
.mobile-menu.open{opacity:1;pointer-events:all;display:flex}
.mobile-menu .mob-link{font-family:var(--font-display);font-size:1.9rem;font-weight:700;color:var(--white);transition:color 0.2s;text-decoration:none;padding:10px 0;width:100%;text-align:center;border-bottom:1px solid rgba(255,255,255,0.06)}
.mobile-menu .mob-link:last-of-type{border-bottom:none}
.mobile-menu .mob-link:hover{color:rgba(255,255,255,0.7)}
.mobile-menu-footer{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;padding:24px 0 0;width:100%;margin-top:8px;border-top:1px solid rgba(255,255,255,0.1)}

/* HERO SLIDER */
.hero-slider{position:relative;height:100vh;min-height:620px;overflow:hidden}
.slide{position:absolute;inset:0;z-index:0;transition:opacity 1s ease,transform 1.2s ease;opacity:0;transform:scale(1.05)}
.slide.active{opacity:1;transform:scale(1);z-index:1}
.slide-bg{position:absolute;inset:0;background-size:cover;background-position:center}
.slide-overlay{position:absolute;inset:0;background:linear-gradient(135deg,rgba(13,27,75,0.82) 0%,rgba(13,27,75,0.48) 55%,rgba(0,0,0,0.22) 100%)}
.slide-content{position:absolute;inset:0;display:flex;align-items:center;z-index:2}
.slide-inner{width:90%;max-width:1200px;margin:0 auto;padding-top:80px}
.slide-tag{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.5);color:var(--white);font-size:0.75rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;padding:6px 16px;border-radius:99px;margin-bottom:20px;backdrop-filter:blur(4px);transform:translateY(30px);opacity:0;transition:all 0.7s ease 0.1s}
.slide.active .slide-tag{transform:translateY(0);opacity:1}
.slide-title{font-family:var(--font-display);font-size:clamp(2.8rem,6vw,5.8rem);font-weight:700;color:var(--white);line-height:1.05;margin-bottom:20px;transform:translateY(40px);opacity:0;transition:all 0.7s ease 0.25s}
.slide.active .slide-title{transform:translateY(0);opacity:1}
.slide-title em{font-style:italic;color:var(--white);display:block}
.slide-desc{font-size:1.05rem;color:rgba(255,255,255,0.8);line-height:1.75;max-width:500px;margin-bottom:36px;transform:translateY(30px);opacity:0;transition:all 0.7s ease 0.4s}
.slide.active .slide-desc{transform:translateY(0);opacity:1}
.slide-actions{display:flex;gap:14px;flex-wrap:wrap;transform:translateY(20px);opacity:0;transition:all 0.7s ease 0.55s}
.slide.active .slide-actions{transform:translateY(0);opacity:1}
.slider-arrow{position:absolute;top:50%;transform:translateY(-50%);z-index:10;width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,0.12);border:1.5px solid rgba(255,255,255,0.3);color:var(--white);font-size:1.2rem;cursor:pointer;transition:all 0.3s;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
.slider-arrow:hover{background:var(--green-light);border-color:var(--green-light)}
.slider-arrow-prev{left:24px}
.slider-arrow-next{right:24px}
.slider-nav{position:absolute;bottom:36px;left:50%;transform:translateX(-50%);z-index:10;display:flex;gap:8px}
.slider-dot{width:10px;height:10px;border-radius:50%;background:rgba(255,255,255,0.35);border:none;cursor:pointer;transition:all 0.3s;padding:0}
.slider-dot.active{background:var(--white);width:28px;border-radius:99px}
.slider-progress{position:absolute;bottom:0;left:0;height:3px;background:var(--white);z-index:10;transition:width 0.1s linear;border-radius:0 2px 2px 0}

/* ── SERVICES — two-column layout (A.II) ── */
.services{padding:100px 0;background:var(--white)}
.services-two-col{display:grid;grid-template-columns:2fr 3fr;gap:64px;align-items:center}
/* Left: Why Choosing Us */
.why-choose{padding-right:16px}
.why-choose .section-tag{margin-bottom:14px}
.why-choose-title{font-family:var(--font-display);font-size:clamp(1.6rem,2.5vw,2.2rem);font-weight:700;color:var(--green-deep);line-height:1.2;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.01em}
.why-choose-point{font-size:0.82rem;font-weight:700;color:var(--green-deep);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px;flex-direction:row}
.why-choose-point::before{content:'✔';width:22px;height:22px;border-radius:50%;background:var(--green-soft);color:var(--green-deep);font-size:0.7rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.why-choose-point-body{display:flex;flex-direction:column;gap:6px}
.why-choose-quote{font-style:italic;font-size:0.84rem;color:var(--text-soft);padding-left:12px;border-left:3px solid var(--green-light);font-weight:400;text-transform:none;letter-spacing:0}

/* Right: 3 service cards */
.services-grid-small{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.service-card{background:var(--cream);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:32px 20px;text-align:center;cursor:pointer;transition:all 0.35s ease;position:relative;overflow:hidden}
.service-card::before{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--green-light),var(--green-mid));transform:scaleX(0);transition:transform 0.35s ease}
.service-card:hover{transform:translateY(-8px);border-color:var(--green-light);box-shadow:var(--shadow-md);background:var(--white)}
.service-card:hover::before{transform:scaleX(1)}
.service-emoji{font-size:2.4rem;margin-bottom:14px;display:block;transition:transform 0.3s ease}
.service-card:hover .service-emoji{transform:scale(1.15) rotate(-4deg)}
.service-name{font-family:var(--font-display);font-size:1.15rem;font-weight:700;color:var(--green-deep);margin-bottom:0}
.service-tag{display:inline-block;margin-top:12px;background:var(--green-soft);color:var(--green-mid);font-size:0.7rem;font-weight:700;padding:3px 10px;border-radius:99px;letter-spacing:0.05em}

/* ── HOW IT WORKS — two-column redesign (A.III) ── */
.how-it-works{padding:100px 0;background:var(--cream)}
.how-two-col{display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center}
/* Left: Illustration placeholder */
.how-illustration{background:linear-gradient(145deg,var(--green-soft),var(--amber-light));border-radius:24px;min-height:380px;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px;text-align:center;position:relative;overflow:hidden}
.how-illustration::before{content:'';position:absolute;top:-20%;right:-20%;width:70%;height:70%;background:radial-gradient(circle,rgba(46,95,194,0.15) 0%,transparent 70%);border-radius:50%}
.how-illustration-icon{font-size:5rem;margin-bottom:20px;position:relative;z-index:1}
.how-illustration-label{font-size:0.78rem;font-weight:700;color:var(--green-mid);text-transform:uppercase;letter-spacing:0.1em;position:relative;z-index:1;opacity:0.7}
/* Right: Card */
.how-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:40px 36px;box-shadow:var(--shadow-sm)}
.how-card-title{font-family:var(--font-display);font-size:clamp(1.4rem,2vw,1.9rem);font-weight:700;color:var(--green-deep);text-transform:uppercase;letter-spacing:0.02em;margin-bottom:28px;line-height:1.2}
.how-bullet{display:flex;align-items:flex-start;gap:14px;margin-bottom:20px}
.how-bullet:last-child{margin-bottom:0}
.how-bullet-check{width:28px;height:28px;border-radius:8px;background:var(--green-deep);color:var(--white);font-size:0.75rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px}
.how-bullet-text{font-size:0.92rem;color:var(--text-mid);line-height:1.6}

/* ABOUT */
.about{padding:100px 0;background:var(--green-deep);position:relative;overflow:hidden}
.about::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:48px 48px}
.about-inner{position:relative;z-index:1;display:grid;grid-template-columns:1fr 1fr;gap:80px;align-items:center}
.about-left .section-tag{background:rgba(255,255,255,0.12);color:var(--white)}
.about-left .section-title{color:var(--white)}
.about-left .section-sub{color:rgba(255,255,255,0.65)}
.about-pillars{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:36px}
.pillar{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:var(--radius-sm);padding:20px;transition:all 0.3s}
.pillar:hover{background:rgba(255,255,255,0.1);transform:translateY(-3px)}
.pillar-icon{font-size:1.5rem;margin-bottom:10px}
.pillar h4{font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--white);margin-bottom:6px}
.pillar p{font-size:0.8rem;color:rgba(255,255,255,0.55);line-height:1.6}
.about-stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.about-stat{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:var(--radius);padding:28px;text-align:center;transition:all 0.3s}
.about-stat:hover{background:rgba(46,95,194,0.15);border-color:rgba(46,95,194,0.3);transform:translateY(-4px)}
.about-stat-num{font-family:var(--font-display);font-size:2.8rem;font-weight:700;color:var(--white);line-height:1}
.about-stat-label{font-size:0.82rem;color:rgba(255,255,255,0.6);margin-top:8px}
.about-quote{grid-column:1/-1;background:rgba(46,95,194,0.12);border:1px solid rgba(255,255,255,0.2);border-left:4px solid var(--green-light);padding:24px;border-radius:var(--radius-sm)}
.about-quote p{font-family:var(--font-display);font-size:1.05rem;font-style:italic;color:rgba(255,255,255,0.85);line-height:1.7}
.about-quote cite{display:block;font-size:0.78rem;color:var(--green-soft);margin-top:8px;font-style:normal;font-weight:600}

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
.testimonials{padding:100px 0;background:var(--cream)}
.testimonials-header{text-align:center;margin-bottom:56px}
.testimonials-header .section-sub{margin:0 auto}
.testimonials-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px}
.testimonial-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:28px;transition:all 0.35s ease;position:relative}
.testimonial-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-md);border-color:var(--green-soft)}
.testimonial-card::before{content:'"';position:absolute;top:16px;right:24px;font-family:var(--font-display);font-size:4rem;color:var(--green-soft);line-height:1}
.stars{display:flex;gap:3px;margin-bottom:14px}
.star{color:var(--green-light);font-size:0.9rem}
.testimonial-text{font-size:0.92rem;color:var(--text-mid);line-height:1.75;margin-bottom:20px;font-style:italic}
.testimonial-author{display:flex;align-items:center;gap:12px}
.author-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(145deg,var(--green-light),var(--green-deep));display:flex;align-items:center;justify-content:center;color:var(--white);font-family:var(--font-display);font-size:1rem;font-weight:700;flex-shrink:0}
.author-info h5{font-size:0.9rem;font-weight:700;color:var(--green-deep)}
.author-info p{font-size:0.76rem;color:var(--text-soft)}

/* FAQ */
.faq{padding:100px 0;background:var(--white)}
.faq-inner{display:grid;grid-template-columns:1fr 1.6fr;gap:80px;align-items:start}
.faq-items{display:flex;flex-direction:column;gap:12px}
.faq-item{border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);overflow:hidden;transition:border-color 0.3s}
.faq-item.open{border-color:var(--green-light)}
.faq-question{width:100%;background:none;border:none;padding:18px 20px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;font-family:var(--font-body);font-size:0.92rem;font-weight:600;color:var(--text-dark);text-align:left;gap:12px;transition:color 0.2s}
.faq-item.open .faq-question{color:var(--green-deep)}
.faq-icon{width:28px;height:28px;border-radius:50%;background:var(--cream);border:1.5px solid var(--cream-dark);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1rem;line-height:1;transition:all 0.3s}
.faq-item.open .faq-icon{background:var(--green-light);border-color:var(--green-light);color:var(--white);transform:rotate(45deg)}
.faq-answer{max-height:0;overflow:hidden;transition:max-height 0.4s ease}
.faq-answer.open{max-height:300px}
.faq-answer-inner{padding:0 20px 18px;font-size:0.88rem;color:var(--text-mid);line-height:1.75}

/* CTA BANNER */
.cta-banner{padding:80px 0;background:linear-gradient(135deg,var(--green-deep) 0%,var(--green-mid) 100%);position:relative;overflow:hidden}
.cta-banner::before{content:'';position:absolute;top:-50%;left:-10%;width:50%;height:200%;background:radial-gradient(ellipse,rgba(46,95,194,0.15) 0%,transparent 60%);border-radius:50%}
.cta-inner{position:relative;z-index:1;text-align:center}
.cta-inner h2{font-family:var(--font-display);font-size:clamp(2rem,4vw,3.2rem);font-weight:700;color:var(--white);margin-bottom:16px}
.cta-inner h2 em{font-style:italic;color:var(--white)}
.cta-inner p{font-size:1rem;color:rgba(255,255,255,0.7);max-width:500px;margin:0 auto 36px;line-height:1.7}
.cta-btns{display:flex;justify-content:center;flex-wrap:wrap;gap:14px}

/* CONTACT */
.contact{padding:100px 0;background:var(--cream)}
.contact-inner{display:grid;grid-template-columns:1fr 1.2fr;gap:80px;align-items:start}
.contact-items{margin-top:32px;display:flex;flex-direction:column;gap:20px}
.contact-item{display:flex;gap:16px;align-items:flex-start}
.contact-item-icon{width:44px;height:44px;border-radius:12px;background:var(--green-soft);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.contact-item-body h5{font-weight:700;font-size:0.9rem;color:var(--green-deep);margin-bottom:3px}
.contact-item-body p{font-size:0.85rem;color:var(--text-mid)}
.contact-form{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:36px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.form-group label{font-size:0.82rem;font-weight:600;color:var(--text-dark)}
.form-group input,.form-group select,.form-group textarea{padding:12px 16px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.88rem;color:var(--text-dark);background:var(--cream);transition:border-color 0.2s;outline:none}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--green-light);box-shadow:0 0 0 3px rgba(46,95,194,0.1)}
.form-group textarea{resize:vertical;min-height:100px}

/* FOOTER */
.footer{background:var(--green-deep);color:rgba(255,255,255,0.7);padding:64px 0 0}
.footer-grid{display:grid;grid-template-columns:1.8fr 1fr 1fr 1fr;gap:48px;margin-bottom:48px}
.footer-brand{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.footer-brand img{width:44px;height:44px;object-fit:contain}
.footer-brand span{font-family:var(--font-display);font-size:1.5rem;font-weight:700;color:var(--white)}
.footer-desc{font-size:0.85rem;line-height:1.75;color:rgba(255,255,255,0.55);margin-bottom:20px}
.social-links{display:flex;gap:10px}
.social-link{width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;font-size:0.9rem;transition:all 0.3s;cursor:pointer}
.social-link:hover{background:var(--green-light);border-color:var(--green-light)}
.footer-col h5{font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--white);margin-bottom:16px}
.footer-links{display:flex;flex-direction:column;gap:10px;list-style:none}
.footer-links a{font-size:0.85rem;color:rgba(255,255,255,0.55);transition:color 0.2s;text-decoration:none}
.footer-links a:hover{color:var(--white)}
.footer-bottom{border-top:1px solid rgba(255,255,255,0.08);padding:24px 0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.footer-bottom p{font-size:0.82rem;color:rgba(255,255,255,0.4)}
.footer-bottom a{font-size:0.82rem;color:rgba(255,255,255,0.4);transition:color 0.2s;text-decoration:none}
.footer-bottom a:hover{color:var(--white)}
.footer-credit{text-align:center;padding:14px 0;border-top:1px solid rgba(255,255,255,0.06);font-size:0.76rem;color:rgba(255,255,255,0.3)}
.footer-credit a{color:rgba(255,255,255,0.45);text-decoration:none;transition:color 0.2s}
.footer-credit a:hover{color:var(--white)}

/* REVEAL */
.reveal{opacity:0;transform:translateY(30px);transition:opacity 0.7s ease,transform 0.7s ease}
.reveal.visible{opacity:1;transform:translateY(0)}
.reveal-delay-1{transition-delay:0.1s}
.reveal-delay-2{transition-delay:0.2s}
.reveal-delay-3{transition-delay:0.3s}

/* RESPONSIVE */
@media(max-width:1024px){
  .about-inner,.career-inner,.faq-inner,.contact-inner{grid-template-columns:1fr;gap:48px}
  .services-two-col{grid-template-columns:1fr;gap:48px}
  .how-two-col{grid-template-columns:1fr;gap:40px}
  .footer-grid{grid-template-columns:1fr 1fr;gap:32px}
}
@media(max-width:768px){
  .nav-links,.nav-cta{display:none}.hamburger{display:flex}
  .slider-arrow-prev{left:12px}.slider-arrow-next{right:12px}
  .services-grid-small{grid-template-columns:1fr}
  .about-pillars{grid-template-columns:1fr}.about-stat-grid{grid-template-columns:1fr 1fr}
  .form-row{grid-template-columns:1fr}.footer-grid{grid-template-columns:1fr;gap:28px}
  .footer-bottom{flex-direction:column;text-align:center}
  .testimonials-grid{grid-template-columns:1fr}.career-right{padding:32px 24px}.contact-form{padding:24px 20px}
  .how-card{padding:28px 20px}
}
@media(max-width:480px){
  .services-grid-small{grid-template-columns:1fr}
  .slide-title{font-size:2.4rem}
  .slide-actions{flex-direction:column;align-items:flex-start}
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
  <div class="container">
    <div class="navbar-inner">
      <a href="#home" class="nav-logo">
        <img src='Image/icon/homehelp_logo_transparent_white.svg' id="navLogo" alt="Home Help Logo"/>
        <span class="nav-logo-text">Home Help</span>
      </a>
      <ul class="nav-links">
        <li><a href="#home">Home</a></li>
        <li><a href="#services">Services</a></li>
        <li><a href="#about">About</a></li>
        <li><a href="/career">Career</a></li>
        <li><a href="#faq">FAQ</a></li>
        <li><a href="#contact">Contact</a></li>
      </ul>
      <div class="nav-cta">
        <a href="/login" class="nav-login">Login</a>
        <a href="/find-worker" class="btn-find-worker">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          Find a Worker
        </a>
        <a href="/register" class="btn-get-started">Get Started</a>
      </div>
      <button class="hamburger" id="hamburger" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</nav>

<!-- MOBILE MENU -->
<div class="mobile-menu" id="mobileMenu">
  <a href="#home"     class="mob-link">Home</a>
  <a href="#services" class="mob-link">Services</a>
  <a href="#about"    class="mob-link">About</a>
  <a href="/career"   class="mob-link">Career</a>
  <a href="#faq"      class="mob-link">FAQ</a>
  <a href="#contact"  class="mob-link">Contact</a>
  <a href="/login"    class="mob-link">Login</a>
  <div class="mobile-menu-footer">
    <a href="/find-worker" class="btn-find-worker">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      Find a Worker
    </a>
    <a href="/register" class="btn btn-primary">Get Started</a>
  </div>
</div>

<!-- HERO SLIDER — Change A.I: all buttons → "Book A HouseWorker" -->
<section class="hero-slider" id="home">
  <div class="slide active">
    <div class="slide-bg" style="background-image:url('Image/slider/Slider_1.jpeg')"></div>
    <div class="slide-overlay"></div>
    <div class="slide-content">
      <div class="slide-inner">
        <h1 class="slide-title">Care That Feels<br/><em>Like Family</em></h1>
        <p class="slide-desc">Connect with verified, loving nannies and caregivers who treat your children like their own &mdash; right here in Rwanda.</p>
        <div class="slide-actions">
          <a href="/register" class="btn btn-amber">Book A HouseWorker &rarr;</a>
          <a href="#how-it-works" class="btn-hero-ghost">How It Works</a>
        </div>
      </div>
    </div>
  </div>
  <div class="slide">
    <div class="slide-bg" style="background-image:url('Image/slider/Slider_2.jpeg')"></div>
    <div class="slide-overlay"></div>
    <div class="slide-content">
      <div class="slide-inner">
        <h1 class="slide-title">A Clean Home,<br/><em>Every Day</em></h1>
        <p class="slide-desc">Our trained housekeepers bring order, cleanliness, and comfort to your home &mdash; reliably and on your schedule.</p>
        <div class="slide-actions">
          <a href="/register" class="btn btn-amber">Book A HouseWorker &rarr;</a>
          <a href="#services" class="btn-hero-ghost">See All Services</a>
        </div>
      </div>
    </div>
  </div>
  <div class="slide">
    <div class="slide-bg" style="background-image:url('Image/slider/Slider_3.jpeg')"></div>
    <div class="slide-overlay"></div>
    <div class="slide-content">
      <div class="slide-inner">
        <h1 class="slide-title">Delicious Meals,<br/><em>Made at Home</em></h1>
        <p class="slide-desc">From daily cooking to special occasions, our talented home chefs prepare fresh, delicious meals tailored to your taste.</p>
        <div class="slide-actions">
          <a href="/register" class="btn btn-amber">Book A HouseWorker &rarr;</a>
          <a href="#career" class="btn-hero-ghost">Join as a Chef</a>
        </div>
      </div>
    </div>
  </div>
  <button class="slider-arrow slider-arrow-prev" onclick="changeSlide(-1)">&#8592;</button>
  <button class="slider-arrow slider-arrow-next" onclick="changeSlide(1)">&#8594;</button>
  <div class="slider-nav">
    <button class="slider-dot active" onclick="goToSlide(0)"></button>
    <button class="slider-dot" onclick="goToSlide(1)"></button>
    <button class="slider-dot" onclick="goToSlide(2)"></button>
  </div>
  <div class="slider-progress" id="sliderProgress"></div>
</section>

<!-- SERVICES — Change A.II: two-column layout -->
<section class="services" id="services">
  <div class="container">
    <div class="services-two-col reveal">

   <!-- LEFT: Why Choosing Us -->
      <div class="why-choose">
        <span class="section-tag">Why Choosing Us</span>

        <div class="why-choose-point">
          <div class="why-choose-point-body">
            TRUSTED AND RELIABLE WORKERS
            <div class="why-choose-quote">"We carefully select and vet our workers."</div>
          </div>
        </div>

        <div class="why-choose-point">
          <div class="why-choose-point-body">
            WE CONNECT YOU WITH THE RIGHT WORKERS BASED ON YOUR NEEDS
          </div>
        </div>

        <div class="why-choose-point">
          <div class="why-choose-point-body">
            PROFESSIONAL QUALITY SERVICES
            <div class="why-choose-quote">"Our team is trained to deliver high-standard services."</div>
          </div>
        </div>

        <div class="why-choose-point">
          <div class="why-choose-point-body">
            GET ONGOING SUPPORT AND ACCESS TO TRUSTED WORKERS.
          </div>
        </div>
      </div>

      <!-- RIGHT: 3 service cards -->
      <div>
        <div class="services-grid-small">
          <?php
          $fallback_services = [
            ['🧹','Cleaning'],
            ['🏠','Housekeeping'],
            ['👨‍🍳','Chef (Cooking)'],
          ];
          $display_services = !empty($homepage_services) ? $homepage_services : null;
          $tags = ['Most Requested','Available','Available'];
          if($display_services):
            foreach(array_slice($display_services,0,3) as $i => $svc):
          ?>
            <div class="service-card reveal reveal-delay-<?= $i+1 ?>">
              <span class="service-emoji"><?= htmlspecialchars($svc['icon'] ?: '🏠') ?></span>
              <h3 class="service-name"><?= htmlspecialchars($svc['name']) ?></h3>
              <span class="service-tag"><?= $tags[$i] ?? 'Available' ?></span>
            </div>
          <?php endforeach; else:
            foreach($fallback_services as $i => [$icon,$name]):
          ?>
            <div class="service-card reveal reveal-delay-<?= $i+1 ?>">
              <span class="service-emoji"><?= $icon ?></span>
              <h3 class="service-name"><?= $name ?></h3>
              <span class="service-tag"><?= $tags[$i] ?></span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ABOUT — Change C: new text + new pillars -->
<section class="about" id="about">
  <div class="container">
    <div class="about-inner">
      <div class="about-left reveal">
        <span class="section-tag">Who We Are</span>
        <h2 class="section-title">Transforming Domestic <em>Employment</em> in Rwanda</h2>
        <p class="section-sub">HOMEHELP IS A RELIABLE DIGITAL PLATFORM DESIGNED TO CONNECT HOUSEHOLDS WITH TRUSTED HOME SERVICE PROVIDERS QUICKLY AND EFFICIENTLY. WE ARE BUILT ON TRUST, RELIABILITY, CONVENIENCE, AND QUALITY SERVICE CORE VALUES.</p>
        <div class="about-pillars">
          <div class="pillar">
            <div class="pillar-icon">🔓</div>
            <h4>EASY ACCESS TO SERVICES</h4>
            <p>Users can quickly find services like cleaning, housekeeping, and cooks/chefs all in one place.</p>
          </div>
          <div class="pillar">
            <div class="pillar-icon">🛡️</div>
            <h4>VERIFIED PROFESSIONALS</h4>
            <p>We ensure service providers go through a vetting process to improve safety and reliability.</p>
          </div>
          <div class="pillar">
            <div class="pillar-icon">⏱️</div>
            <h4>TIME-SAVING BOOKING SYSTEM</h4>
            <p>Customers can book services online without long calls or extensive searching.</p>
          </div>
          <div class="pillar">
            <div class="pillar-icon">🌍</div>
            <h4>LOCAL EMPOWERMENT</h4>
            <p>We create opportunities for skilled workers by connecting them with more customers.</p>
          </div>
        </div>
      </div>
      <div class="about-right reveal reveal-delay-2">
        <div class="about-stat-grid">
          <div class="about-stat"><div class="about-stat-num">5</div><div class="about-stat-label">Service Categories</div></div>
          <div class="about-stat"><div class="about-stat-num">24h</div><div class="about-stat-label">Support Available</div></div>
          <div class="about-stat"><div class="about-stat-num">100%</div><div class="about-stat-label">Verified Workers</div></div>
          <div class="about-stat"><div class="about-stat-num">&#127479;&#127484;</div><div class="about-stat-label">Rwanda-Based</div></div>
          <div class="about-quote">
            <p>"We created Home Help to make domestic hiring safe, fair, and dignified for everyone involved."</p>
            <cite>&#8212; Brilliant Elite Ltd, Kigali</cite>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS — Change A.III: two-column redesign -->
<section class="how-it-works" id="how-it-works">
  <div class="container">
    <div class="how-two-col">

      <!-- LEFT: Illustration placeholder -->
      <!-- LEFT: Illustration -->
      <div class="how-illustration reveal" style="background:none;padding:0;">
        <img src="Image/how-illustration.png" alt="Person searching for home help" style="width:100%;border-radius:24px;"/>
      </div>

      <!-- RIGHT: Card -->
      <div class="reveal reveal-delay-2">
        <div class="how-card">
          <div class="how-card-title">EFFORTLESS HIRING WHENEVER YOU NEED IT</div>
          <div class="how-bullet">
            <div class="how-bullet-check">✓</div>
            <div class="how-bullet-text">Easily search and find trusted reliable workers</div>
          </div>
          <div class="how-bullet">
            <div class="how-bullet-check">✓</div>
            <div class="how-bullet-text">Set your preferences (service type, skills, time)</div>
          </div>
          <div class="how-bullet">
            <div class="how-bullet-check">✓</div>
            <div class="how-bullet-text">Receive worker suggestions and get the helper you need</div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonials" id="testimonials">
  <div class="container">
    <div class="testimonials-header reveal">
      <span class="section-tag">What People Say</span>
      <h2 class="section-title">Trusted by Families <em>Across Rwanda</em></h2>
      <p class="section-sub">Real feedback from clients and job seekers who have used Home Help.</p>
    </div>
    <div class="testimonials-grid">
      <div class="testimonial-card reveal reveal-delay-1">
        <div class="stars"><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span></div>
        <p class="testimonial-text">I found a wonderful nanny for my daughter within just 2 days. The team was professional, responsive, and the whole process was smooth from start to finish.</p>
        <div class="testimonial-author"><div class="author-avatar">A</div><div class="author-info"><h5>Amina Uwase</h5><p>Client &mdash; Kigali, Gasabo</p></div></div>
      </div>
      <div class="testimonial-card reveal reveal-delay-2">
        <div class="stars"><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span></div>
        <p class="testimonial-text">After months of struggling to find work, I applied on Home Help and got placed with a great family in less than a week. I highly recommend it.</p>
        <div class="testimonial-author"><div class="author-avatar">J</div><div class="author-info"><h5>Jean Claude Habimana</h5><p>Housekeeper &mdash; Musanze</p></div></div>
      </div>
      <div class="testimonial-card reveal reveal-delay-3">
        <div class="stars"><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span></div>
        <p class="testimonial-text">The WhatsApp notifications are very convenient. I got updates on every step of the process. The platform is easy to use even on a phone.</p>
        <div class="testimonial-author"><div class="author-avatar">M</div><div class="author-info"><h5>Marie Iradukunda</h5><p>Client &mdash; Butare</p></div></div>
      </div>
    </div>
  </div>
</section>

<!-- FAQ — Change D: "Agent" → "Team" in visible text -->
<section class="faq" id="faq">
  <div class="container">
    <div class="faq-inner">
      <div class="faq-left reveal">
        <span class="section-tag">Questions &amp; Answers</span>
        <h2 class="section-title">Frequently <em>Asked</em> Questions</h2>
        <p class="section-sub">Have a question? Our most common questions are answered here.</p>
        <br/>
        <a href="#contact" class="btn btn-outline">Contact Us &rarr;</a>
      </div>
      <div class="faq-items reveal reveal-delay-2">
        <div class="faq-item open"><button class="faq-question" onclick="toggleFAQ(this)">How do I book a domestic service?<div class="faq-icon">+</div></button><div class="faq-answer open"><div class="faq-answer-inner">Create a free account, log in as a Client, click "Book a Service", fill in the service type, location, start date, and requirements. Our team will review and notify you within hours.</div></div></div>
        <div class="faq-item"><button class="faq-question" onclick="toggleFAQ(this)">Is it free to register?<div class="faq-icon">+</div></button><div class="faq-answer"><div class="faq-answer-inner">Yes, registration is completely free for both clients and service providers. You only pay for the domestic services you receive.</div></div></div>
        <div class="faq-item"><button class="faq-question" onclick="toggleFAQ(this)">How are service providers verified?<div class="faq-icon">+</div></button><div class="faq-answer"><div class="faq-answer-inner">All job applicants submit their national ID, CV, and references. Our trained team reviews each application and conducts manual screening before any provider is approved.</div></div></div>
        <div class="faq-item"><button class="faq-question" onclick="toggleFAQ(this)">Can I track my booking status?<div class="faq-icon">+</div></button><div class="faq-answer"><div class="faq-answer-inner">Yes. Your dashboard shows real-time status &mdash; Pending, Under Review, Approved, or Rejected. You also receive WhatsApp messages at every stage automatically.</div></div></div>
        <div class="faq-item"><button class="faq-question" onclick="toggleFAQ(this)">What areas does Home Help serve?<div class="faq-icon">+</div></button><div class="faq-answer"><div class="faq-answer-inner">We currently serve clients across Rwanda including Kigali, Musanze, Rubavu, Butare, and other major cities. We are actively expanding our team network.</div></div></div>
        <div class="faq-item"><button class="faq-question" onclick="toggleFAQ(this)">How do I apply for a job?<div class="faq-icon">+</div></button><div class="faq-answer"><div class="faq-answer-inner">Register as a Service Provider, go to Career, select a job category, fill in your application form, upload your ID and CV, and submit. A team member will review and contact you via WhatsApp.</div></div></div>
      </div>
    </div>
  </div>
</section>

<!-- CTA BANNER -->
<section class="cta-banner">
  <div class="container">
    <div class="cta-inner reveal">
      <h2>Ready to Find the <em>Right Help?</em></h2>
      <p>Join hundreds of families and job seekers already using Home Help to connect, work, and thrive in Rwanda.</p>
      <div class="cta-btns">
        <a href="/register" class="btn btn-amber">Book a Service &rarr;</a>
        <a href="/worker-login" class="btn-hero-ghost">Apply for a Job</a>
      </div>
    </div>
  </div>
</section>

<!-- CONTACT -->
<section class="contact" id="contact">
  <div class="container">
    <div class="contact-inner">
      <div class="contact-info reveal">
        <span class="section-tag">Get In Touch</span>
        <h2 class="section-title">We&#8217;re <em>Here</em> to Help</h2>
        <p class="section-sub">Have a question or need support? Reach out and our team will respond promptly.</p>
        <div class="contact-items">
          <div class="contact-item"><div class="contact-item-icon">&#128205;</div><div class="contact-item-body"><h5>Location</h5><p>Kigali, Rwanda</p></div></div>
          <div class="contact-item"><div class="contact-item-icon">&#128242;</div><div class="contact-item-body"><h5>WhatsApp</h5><p>+250 790 533 144</p></div></div>
          <div class="contact-item"><div class="contact-item-icon">&#128231;</div><div class="contact-item-body"><h5>Email</h5><p>info@homehelp.rw</p></div></div>
          <div class="contact-item"><div class="contact-item-icon">&#128336;</div><div class="contact-item-body"><h5>Working Hours</h5><p>Mon &ndash; Sat: 7:00 AM &ndash; 7:00 PM</p></div></div>
        </div>
      </div>
      <div class="contact-form reveal reveal-delay-2">
        <h3 style="font-family:var(--font-display);font-size:1.5rem;color:var(--green-deep);margin-bottom:24px;">Send Us a Message</h3>

        <?php if($contact_success): ?>
          <div style="background:#ECFDF5;border:1.5px solid #BBF7D0;border-radius:10px;padding:18px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
            <span style="font-size:1.4rem">✅</span>
            <div>
              <div style="font-weight:700;color:#065F46;font-size:0.95rem;">Message sent successfully!</div>
              <div style="font-size:0.83rem;color:#047857;margin-top:3px;">Thank you! We will get back to you shortly.</div>
            </div>
          </div>
        <?php endif; ?>

        <?php if($contact_error): ?>
          <div style="background:#FEF2F2;border:1.5px solid #FECACA;border-radius:10px;padding:14px 18px;margin-bottom:18px;font-size:0.86rem;font-weight:600;color:#B91C1C;display:flex;align-items:center;gap:8px;">
            ⚠️ <?= htmlspecialchars($contact_error) ?>
          </div>
        <?php endif; ?>

        <?php if(!$contact_success): ?>
        <form method="POST" action="#contact" id="contactForm">
          <div class="form-row">
            <div class="form-group">
              <label>First Name <span style="color:#EF4444">*</span></label>
              <input type="text" name="first_name" placeholder="Amina" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required/>
            </div>
            <div class="form-group">
              <label>Last Name <span style="color:#EF4444">*</span></label>
              <input type="text" name="last_name" placeholder="Uwase" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required/>
            </div>
          </div>
          <div class="form-group">
            <label>Email Address <span style="color:#EF4444">*</span></label>
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
            <label>Message <span style="color:#EF4444">*</span></label>
            <textarea name="message" placeholder="How can we help you?" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
          </div>
          <input type="hidden" name="contact_submit" value="1"/>
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Send Message &rarr;</button>
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
        <div class="footer-brand">
          <img src="Image/icon/homehelp_logo_transparent_white.svg" alt="Home Help"/>
          <span>Home Help</span>
        </div>
        <p class="footer-desc">Rwanda&#8217;s trusted marketplace for domestic services, connecting households with verified workers across the country.</p>
        <div class="social-links">
          <div class="social-link">&#128280;</div>
          <div class="social-link">&#128247;</div>
          <div class="social-link">&#128038;</div>
          <div class="social-link">&#128172;</div>
        </div>
      </div>
      <div class="footer-col"><h5>Services</h5><ul class="footer-links"><li><a href="/register">Cleaning</a></li><li><a href="/register">Housekeeping</a></li><li><a href="/register">Chef</a></li><li><a href="/register">Caregiver</a></li><li><a href="/register">Pet Groomer</a></li></ul></div>
      <div class="footer-col"><h5>Platform</h5><ul class="footer-links"><li><a href="#about">About Us</a></li><li><a href="#how-it-works">How It Works</a></li><li><a href="/career">Career</a></li><li><a href="#faq">FAQ</a></li><li><a href="#contact">Contact</a></li></ul></div>
      <div class="footer-col"><h5>Account</h5><ul class="footer-links"><li><a href="/register">Register</a></li><li><a href="/login">Login</a></li><li><a href="/find-worker">Find a Worker</a></li><li><a href="/worker-login">Apply for Work</a></li><li><a href="/staff-login">Team Login</a></li></ul></div>
    </div>
    <div class="footer-bottom">
      <p>&#169; <?php echo date('Y'); ?> Home Help &middot; Brilliant Elite Ltd &middot; Rwanda &#127479;&#127484;</p>
      <div style="display:flex;gap:20px;"><a href="#">Privacy Policy</a><a href="#">Terms of Service</a></div>
    </div>
  </div>
  <div class="footer-credit">
    Built by <a href="https://bibhq.com" target="_blank">Brilliant Elite Ltd</a> &middot; Kigali, Rwanda &#127479;&#127484;
  </div>
</footer>

<script>
const navbar=document.getElementById('navbar');
window.addEventListener('scroll',function(){
  var scrolled=window.scrollY>60;
  navbar.classList.toggle('scrolled',scrolled);
  var logo=document.getElementById('navLogo');
  if(logo){logo.src=scrolled?'Image/icon/homehelp_logo_transparent_dark.svg':'Image/icon/homehelp_logo_transparent_white.svg';}
});
const hamburger=document.getElementById('hamburger');
const mobileMenu=document.getElementById('mobileMenu');
hamburger.addEventListener('click',function(){hamburger.classList.toggle('open');mobileMenu.classList.toggle('open');document.body.style.overflow=mobileMenu.classList.contains('open')?'hidden':'';});
document.querySelectorAll('.mob-link').forEach(function(l){l.addEventListener('click',function(){hamburger.classList.remove('open');mobileMenu.classList.remove('open');document.body.style.overflow='';});});
var SLIDE_DURATION=6000;var currentSlide=0;var sliderTimer=null;
var slides=document.querySelectorAll('.slide');var dots=document.querySelectorAll('.slider-dot');var progressBar=document.getElementById('sliderProgress');
function goToSlide(n){slides[currentSlide].classList.remove('active');dots[currentSlide].classList.remove('active');currentSlide=(n+slides.length)%slides.length;slides[currentSlide].classList.add('active');dots[currentSlide].classList.add('active');resetProgress();}
function changeSlide(dir){goToSlide(currentSlide+dir);}
function resetProgress(){clearTimeout(sliderTimer);progressBar.style.transition='none';progressBar.style.width='0%';setTimeout(function(){progressBar.style.transition='width '+SLIDE_DURATION+'ms linear';progressBar.style.width='100%';},50);sliderTimer=setTimeout(function(){goToSlide(currentSlide+1);},SLIDE_DURATION);}
var heroSlider=document.querySelector('.hero-slider');
heroSlider.addEventListener('mouseenter',function(){clearTimeout(sliderTimer);});
heroSlider.addEventListener('mouseleave',function(){resetProgress();});
var touchStartX=0;
heroSlider.addEventListener('touchstart',function(e){touchStartX=e.touches[0].clientX;},{passive:true});
heroSlider.addEventListener('touchend',function(e){var diff=touchStartX-e.changedTouches[0].clientX;if(Math.abs(diff)>50)changeSlide(diff>0?1:-1);});
resetProgress();
var observer=new IntersectionObserver(function(entries){entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add('visible');observer.unobserve(e.target);}});},{threshold:0.12});
document.querySelectorAll('.reveal').forEach(function(el){observer.observe(el);});
function toggleFAQ(btn){var item=btn.parentElement;var answer=item.querySelector('.faq-answer');var isOpen=item.classList.contains('open');document.querySelectorAll('.faq-item').forEach(function(i){i.classList.remove('open');i.querySelector('.faq-answer').classList.remove('open');});if(!isOpen){item.classList.add('open');answer.classList.add('open');}}
document.querySelectorAll('a[href^="#"]').forEach(function(link){link.addEventListener('click',function(e){var target=document.querySelector(link.getAttribute('href'));if(target){e.preventDefault();target.scrollIntoView({behavior:'smooth',block:'start'});}});});
</script>
</body>
</html>