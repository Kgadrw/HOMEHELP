<?php
require_once 'config/db.php';
require_once 'config/config.php';

// Fetch active services for filter
$services = $conn->query("
    SELECT id, name, icon FROM services WHERE status='active' ORDER BY sort_order ASC, id ASC
")->fetch_all(MYSQLI_ASSOC);

// Fetch approved providers with optional filters
$where   = ["u.role = 'provider'", "u.status = 'active'"];
$params  = [];
$types   = '';

$filter_service  = (int)($_GET['service']  ?? 0);
$filter_district = sanitize($_GET['district'] ?? '');
$filter_search   = sanitize($_GET['q']        ?? '');

if ($filter_service) {
    $where[]  = "ja.service_id = ?";
    $params[] = $filter_service;
    $types   .= 'i';
}
if ($filter_district) {
    $where[]  = "u.phone LIKE ?"; // using phone as proxy; adapt if you have a district column
}
if ($filter_search) {
    $where[]  = "u.name LIKE ?";
    $params[] = '%' . $filter_search . '%';
    $types   .= 's';
}

$where_sql = implode(' AND ', $where);

// Get workers — join latest approved application for service info
$sql = "
    SELECT DISTINCT u.id, u.name, u.phone, u.created_at,
           s.name AS service_name, s.icon AS service_icon,
           ja.status AS app_status
    FROM users u
    LEFT JOIN job_applications ja ON ja.provider_id = u.id AND ja.status = 'approved'
    LEFT JOIN services s ON s.id = ja.service_id
    WHERE $where_sql
    ORDER BY u.created_at DESC
    LIMIT 24
";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $workers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $workers = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$total = count($workers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="description" content="Find trusted domestic workers in Rwanda. Browse verified nannies, housekeepers, chefs and caregivers on Home Help."/>
<title>Find a Worker | Home Help Rwanda</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --navy:#0D1B4B;--navy-mid:#152268;--navy-light:#2E5FC2;--navy-soft:#D4DCF5;
  --cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --success:#10B981;--success-bg:#ECFDF5;
  --shadow-sm:0 2px 12px rgba(13,27,75,0.08);
  --shadow-md:0 8px 32px rgba(13,27,75,0.12);
  --radius:16px;--radius-sm:8px;
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden;line-height:1.65}
a{text-decoration:none;color:inherit}
img{max-width:100%;height:auto;display:block}
::-webkit-scrollbar{width:6px}
::-webkit-scrollbar-thumb{background:var(--navy-light);border-radius:3px}
.container{width:90%;max-width:1200px;margin:0 auto}

/* ── NAVBAR ── */
.navbar{position:fixed;top:0;left:0;right:0;z-index:1000;padding:14px 0;transition:all 0.4s ease;background:transparent}
.navbar.scrolled{background:var(--white);padding:10px 0;box-shadow:var(--shadow-sm)}
.navbar-inner{display:flex;align-items:center;justify-content:space-between;gap:16px}
.nav-logo{display:flex;align-items:center;gap:10px;flex-shrink:0;text-decoration:none}
.nav-logo img{width:44px;height:44px;object-fit:contain}
.nav-logo-text{font-family:var(--font-display);font-size:1.45rem;font-weight:700;color:var(--white);transition:color 0.3s}
.navbar.scrolled .nav-logo-text{color:var(--navy)}
.nav-links{display:flex;align-items:center;gap:28px;list-style:none}
.nav-links a{font-size:0.88rem;font-weight:500;color:rgba(255,255,255,0.85);transition:color 0.2s;position:relative;text-decoration:none}
.navbar.scrolled .nav-links a{color:var(--text-mid)}
.nav-links a::after{content:'';position:absolute;bottom:-3px;left:0;width:0;height:2px;background:var(--navy-light);border-radius:2px;transition:width 0.3s}
.nav-links a:hover::after,.nav-links a.active::after{width:100%}
.nav-links a.active{font-weight:600}
.nav-cta{display:flex;align-items:center;gap:10px;flex-shrink:0}
.nav-login{font-size:0.88rem;font-weight:600;color:rgba(255,255,255,0.92);transition:color 0.3s;text-decoration:none}
.navbar.scrolled .nav-login{color:var(--text-mid)}
.btn-nav-cta{display:inline-flex;align-items:center;padding:9px 18px;border-radius:99px;font-family:var(--font-body);font-size:0.84rem;font-weight:600;cursor:pointer;transition:all 0.3s;text-decoration:none;background:var(--navy-light);color:var(--white);border:none}
.btn-nav-cta:hover{background:var(--navy-mid);transform:translateY(-1px)}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:4px;background:none;border:none}
.hamburger span{display:block;width:24px;height:2px;background:var(--white);border-radius:2px;transition:all 0.3s}
.navbar.scrolled .hamburger span{background:var(--navy)}
.hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg)}
.hamburger.open span:nth-child(2){opacity:0}
.hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}
.mobile-menu{display:none;position:fixed;inset:0;background:var(--navy);z-index:999;flex-direction:column;align-items:center;justify-content:flex-start;padding:90px 20px 40px;opacity:0;pointer-events:none;transition:opacity 0.3s;overflow-y:auto}
.mobile-menu.open{opacity:1;pointer-events:all;display:flex}
.mob-link{font-family:var(--font-display);font-size:1.9rem;font-weight:700;color:var(--white);padding:10px 0;width:100%;text-align:center;border-bottom:1px solid rgba(255,255,255,0.06);text-decoration:none}
.mob-link:hover{opacity:0.7}
.mobile-footer{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;padding:24px 0 0;width:100%;border-top:1px solid rgba(255,255,255,0.1);margin-top:8px}

/* ── HERO ── */
.hero{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 60%,#1a3580 100%);padding:120px 0 64px;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:48px 48px}
.hero::after{content:'';position:absolute;top:-30%;right:-10%;width:55%;height:160%;background:radial-gradient(ellipse,rgba(46,95,194,0.22) 0%,transparent 60%);border-radius:50%}
.hero-inner{position:relative;z-index:1;text-align:center}
.hero-tag{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:rgba(255,255,255,0.9);font-size:0.75rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;padding:6px 16px;border-radius:99px;margin-bottom:20px}
.hero-title{font-family:var(--font-display);font-size:clamp(2.4rem,5vw,4.2rem);font-weight:700;color:var(--white);line-height:1.1;margin-bottom:16px}
.hero-title em{font-style:italic;color:var(--navy-soft)}
.hero-desc{font-size:1rem;color:rgba(255,255,255,0.65);max-width:520px;margin:0 auto 40px;line-height:1.8}

/* ── SEARCH BAR ── */
.search-bar{background:var(--white);border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow-md);display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end;max-width:900px;margin:0 auto}
.search-field{display:flex;flex-direction:column;gap:5px}
.search-field label{font-size:0.72rem;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.06em}
.search-field input,.search-field select{padding:10px 14px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.88rem;color:var(--text-dark);background:var(--cream);outline:none;transition:border-color 0.2s;width:100%}
.search-field input:focus,.search-field select:focus{border-color:var(--navy-light);background:var(--white)}
.search-field select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;background-color:var(--cream)}
.btn-search{padding:11px 24px;background:var(--navy);color:var(--white);border:none;border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.9rem;font-weight:700;cursor:pointer;transition:all 0.25s;white-space:nowrap;display:flex;align-items:center;gap:8px}
.btn-search:hover{background:var(--navy-mid);transform:translateY(-1px)}

/* ── MAIN CONTENT ── */
.main{padding:64px 0 100px}
.results-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:32px}
.results-count{font-size:0.9rem;color:var(--text-soft)}
.results-count strong{color:var(--navy);font-size:1rem}
.section-title{font-family:var(--font-display);font-size:1.8rem;font-weight:700;color:var(--navy)}
.section-title em{font-style:italic;color:var(--navy-light)}

/* ── SERVICE FILTERS ── */
.filter-chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:32px}
.chip{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:99px;border:1.5px solid var(--cream-dark);background:var(--white);font-size:0.8rem;font-weight:600;color:var(--text-mid);cursor:pointer;transition:all 0.2s;text-decoration:none}
.chip:hover,.chip.active{background:var(--navy);border-color:var(--navy);color:var(--white)}
.chip-icon{font-size:0.9rem}

/* ── WORKER CARDS ── */
.workers-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px}
.worker-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden;transition:all 0.35s;position:relative}
.worker-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-md);border-color:var(--navy-soft)}
.worker-card-top{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 100%);padding:28px 20px 20px;text-align:center;position:relative}
.worker-card-top::after{content:'';position:absolute;bottom:-1px;left:0;right:0;height:20px;background:var(--white);border-radius:50% 50% 0 0 / 20px 20px 0 0}
.worker-avatar{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--navy-light),var(--navy));border:3px solid rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1.6rem;font-weight:700;color:var(--white);margin:0 auto 10px}
.worker-verified{display:inline-flex;align-items:center;gap:4px;background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#065F46;font-size:0.68rem;font-weight:700;padding:3px 8px;border-radius:99px}
.worker-card-body{padding:16px 20px 20px}
.worker-name{font-family:var(--font-display);font-size:1.15rem;font-weight:700;color:var(--navy);text-align:center;margin-bottom:4px}
.worker-service{display:flex;align-items:center;justify-content:center;gap:6px;font-size:0.82rem;color:var(--text-soft);margin-bottom:14px}
.worker-service-badge{display:inline-flex;align-items:center;gap:5px;background:var(--navy-soft);color:var(--navy);font-size:0.75rem;font-weight:700;padding:4px 10px;border-radius:99px}
.worker-details{display:flex;flex-direction:column;gap:7px;margin-bottom:16px}
.worker-detail{display:flex;align-items:center;gap:8px;font-size:0.82rem;color:var(--text-mid)}
.worker-detail-icon{width:26px;height:26px;border-radius:7px;background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:0.75rem;flex-shrink:0}
.btn-hire{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:11px;border-radius:var(--radius-sm);background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:0.86rem;font-weight:700;border:none;cursor:pointer;transition:all 0.3s;text-decoration:none}
.btn-hire:hover{background:var(--navy-mid);transform:translateY(-1px);box-shadow:0 4px 16px rgba(13,27,75,0.25)}

/* ── EMPTY STATE ── */
.empty-state{grid-column:1/-1;text-align:center;padding:64px 20px}
.empty-icon{font-size:3.5rem;margin-bottom:16px}
.empty-title{font-family:var(--font-display);font-size:1.6rem;font-weight:700;color:var(--navy);margin-bottom:8px}
.empty-desc{font-size:0.9rem;color:var(--text-soft);max-width:400px;margin:0 auto 24px}

/* ── CTA ── */
.cta-section{margin-top:64px;background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 100%);border-radius:var(--radius);padding:48px;text-align:center;position:relative;overflow:hidden}
.cta-section::before{content:'';position:absolute;top:-50%;right:-5%;width:40%;height:200%;background:radial-gradient(ellipse,rgba(46,95,194,0.3) 0%,transparent 60%);border-radius:50%}
.cta-section h2{font-family:var(--font-display);font-size:clamp(1.8rem,3vw,2.6rem);font-weight:700;color:var(--white);margin-bottom:10px;position:relative;z-index:1}
.cta-section h2 em{font-style:italic;color:var(--navy-soft)}
.cta-section p{font-size:0.95rem;color:rgba(255,255,255,0.65);max-width:480px;margin:0 auto 28px;line-height:1.7;position:relative;z-index:1}
.cta-btns{display:flex;justify-content:center;gap:12px;flex-wrap:wrap;position:relative;z-index:1}
.btn-white{background:var(--white);color:var(--navy);padding:13px 28px;border-radius:99px;font-family:var(--font-body);font-size:0.9rem;font-weight:700;border:none;cursor:pointer;transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
.btn-white:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.2)}
.btn-ghost{background:rgba(255,255,255,0.12);color:var(--white);border:1.5px solid rgba(255,255,255,0.3);padding:12px 26px;border-radius:99px;font-family:var(--font-body);font-size:0.9rem;font-weight:600;cursor:pointer;transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center}
.btn-ghost:hover{background:rgba(255,255,255,0.22)}

/* ── FOOTER ── */
.footer{background:var(--navy);color:rgba(255,255,255,0.7);padding:48px 0 0}
.footer-bottom{border-top:1px solid rgba(255,255,255,0.08);padding:20px 0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.footer-bottom p{font-size:0.82rem;color:rgba(255,255,255,0.4)}
.footer-bottom a{font-size:0.82rem;color:rgba(255,255,255,0.4);transition:color 0.2s;text-decoration:none}
.footer-bottom a:hover{color:var(--white)}
.footer-credit{text-align:center;padding:12px 0;border-top:1px solid rgba(255,255,255,0.06);font-size:0.75rem;color:rgba(255,255,255,0.3)}
.footer-credit a{color:rgba(255,255,255,0.4);text-decoration:none}
.footer-credit a:hover{color:var(--white)}

/* ── REVEAL ── */
.reveal{opacity:0;transform:translateY(20px);transition:opacity 0.6s ease,transform 0.6s ease}
.reveal.visible{opacity:1;transform:translateY(0)}

@media(max-width:900px){.search-bar{grid-template-columns:1fr 1fr;gap:10px}.btn-search{grid-column:1/-1}}
@media(max-width:768px){.nav-links,.nav-cta{display:none}.hamburger{display:flex}.search-bar{grid-template-columns:1fr}.workers-grid{grid-template-columns:repeat(auto-fill,minmax(220px,1fr))}}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
  <div class="container">
    <div class="navbar-inner">
      <a href="/" class="nav-logo">
        <img src="Image/icon/homehelp_logo_transparent_white.svg" id="navLogo" alt="Home Help"/>
        <span class="nav-logo-text">Home Help</span>
      </a>
      <ul class="nav-links">
        <li><a href="/">Home</a></li>
        <li><a href="/#services">Services</a></li>
        <li><a href="/#about">About</a></li>
        <li><a href="/career">Career</a></li>
        <li><a href="/#faq">FAQ</a></li>
        <li><a href="/#contact">Contact</a></li>
      </ul>
      <div class="nav-cta">
        <a href="/login" class="nav-login">Login</a>
        <a href="/find-worker" class="btn-nav-cta active">🔍 Find a Worker</a>
      </div>
      <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
    </div>
  </div>
</nav>

<!-- MOBILE MENU -->
<div class="mobile-menu" id="mobileMenu">
  <a href="/"         class="mob-link">Home</a>
  <a href="/#services"class="mob-link">Services</a>
  <a href="/#about"   class="mob-link">About</a>
  <a href="/career"   class="mob-link">Career</a>
  <a href="/#faq"     class="mob-link">FAQ</a>
  <a href="/#contact" class="mob-link">Contact</a>
  <a href="/login"    class="mob-link">Login</a>
  <div class="mobile-footer">
    <a href="/register" style="background:var(--navy-light);color:var(--white);padding:10px 20px;border-radius:99px;font-weight:700;font-size:0.88rem">Get Started</a>
  </div>
</div>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <div class="hero-inner">
      <div class="hero-tag">🔍 <?= $total ?> worker<?= $total!=1?'s':'' ?> available</div>
      <h1 class="hero-title">Find Trusted <em>Domestic Workers</em></h1>
      <p class="hero-desc">Browse verified nannies, housekeepers, chefs, and caregivers available in Rwanda. Book directly through your dashboard.</p>

      <!-- SEARCH FORM -->
      <form method="GET" action="/find-worker" class="search-bar">
        <div class="search-field">
          <label>Search by Name</label>
          <input type="text" name="q" placeholder="e.g. Jean Claude..." value="<?= e($filter_search) ?>"/>
        </div>
        <div class="search-field">
          <label>Service Category</label>
          <select name="service">
            <option value="">All Categories</option>
            <?php foreach($services as $svc): ?>
              <option value="<?= $svc['id'] ?>" <?= $filter_service==$svc['id']?'selected':'' ?>>
                <?= $svc['icon'] ?> <?= htmlspecialchars($svc['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="search-field">
          <label>District</label>
          <select name="district">
            <option value="">All Districts</option>
            <?php
            $districts=['Gasabo','Kicukiro','Nyarugenge','Bugesera','Gatsibo','Kayonza','Kirehe','Ngoma','Nyagatare','Rwamagana','Burera','Gakenke','Gicumbi','Musanze','Rulindo','Gisagara','Huye','Kamonyi','Muhanga','Nyamagabe','Nyanza','Nyaruguru','Ruhango','Karongi','Ngororero','Nyabihu','Nyamasheke','Rubavu','Rusizi','Rutsiro'];
            sort($districts);
            foreach($districts as $d): ?>
              <option value="<?= $d ?>" <?= $filter_district===$d?'selected':'' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn-search">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          Search
        </button>
      </form>
    </div>
  </div>
</section>

<!-- MAIN -->
<section class="main">
  <div class="container">

    <!-- Filter chips by service -->
    <div class="filter-chips">
      <a href="/find-worker" class="chip <?= !$filter_service?'active':'' ?>">All Workers</a>
      <?php foreach($services as $svc): ?>
        <a href="/find-worker?service=<?= $svc['id'] ?>" class="chip <?= $filter_service==$svc['id']?'active':'' ?>">
          <span class="chip-icon"><?= $svc['icon'] ?></span>
          <?= htmlspecialchars($svc['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Results header -->
    <div class="results-header">
      <div>
        <h2 class="section-title">Available <em>Workers</em></h2>
        <p class="results-count">Showing <strong><?= $total ?></strong> verified worker<?= $total!=1?'s':'' ?>
          <?= $filter_search ? ' for "'.e($filter_search).'"' : '' ?>
          <?= $filter_service ? ' in selected category' : '' ?>
        </p>
      </div>
      <?php if($filter_search || $filter_service || $filter_district): ?>
        <a href="/find-worker" style="font-size:0.82rem;color:var(--navy-light);font-weight:600;text-decoration:none">✕ Clear filters</a>
      <?php endif; ?>
    </div>

    <!-- Workers grid -->
    <div class="workers-grid">
      <?php if(empty($workers)): ?>
        <div class="empty-state">
          <div class="empty-icon">🔍</div>
          <h3 class="empty-title">No Workers Found</h3>
          <p class="empty-desc">No workers match your search right now. Try different filters or check back soon — new workers register every day.</p>
          <a href="/find-worker" class="btn-hire" style="width:auto;display:inline-flex;padding:12px 28px">Clear Filters</a>
        </div>
      <?php else: ?>
        <?php foreach($workers as $w):
          $initials = strtoupper(substr($w['name'], 0, 2));
          $joined   = date('M Y', strtotime($w['created_at']));
          $svc_name = $w['service_name'] ?: 'Domestic Worker';
          $svc_icon = $w['service_icon'] ?: '💼';
        ?>
          <div class="worker-card reveal">
            <div class="worker-card-top">
              <div class="worker-avatar"><?= $initials ?></div>
              <div class="worker-verified">✓ Verified</div>
            </div>
            <div class="worker-card-body">
              <div class="worker-name"><?= e($w['name']) ?></div>
              <div class="worker-service">
                <span class="worker-service-badge"><?= $svc_icon ?> <?= e($svc_name) ?></span>
              </div>
              <div class="worker-details">
                <div class="worker-detail">
                  <div class="worker-detail-icon">📍</div>
                  Rwanda
                </div>
                <div class="worker-detail">
                  <div class="worker-detail-icon">📅</div>
                  Member since <?= $joined ?>
                </div>
                <div class="worker-detail">
                  <div class="worker-detail-icon">✅</div>
                  Background checked
                </div>
              </div>
              <a href="/register" class="btn-hire">Hire Now →</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- CTA -->
    <div class="cta-section reveal">
      <h2>Can't Find the Right <em>Worker?</em></h2>
      <p>Submit a booking request and our agents will find the perfect match for your specific needs across Rwanda.</p>
      <div class="cta-btns">
        <a href="/register" class="btn-white">📋 Post a Request →</a>
        <a href="/#contact"  class="btn-ghost">Contact Our Team</a>
      </div>
    </div>

  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="container">
    <div class="footer-bottom">
      <p>© <?= date('Y') ?> Home Help · Brilliant Elite Ltd · Rwanda 🇷🇼</p>
      <div style="display:flex;gap:20px">
        <a href="/career">For Job Seekers</a>
        <a href="/#contact">Contact</a>
        <a href="#">Privacy</a>
      </div>
    </div>
  </div>
  <div class="footer-credit">
    Built by <a href="https://bibhq.com" target="_blank">Brilliant Elite Ltd</a> · Kigali, Rwanda 🇷🇼
  </div>
</footer>

<script>
// Navbar scroll
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', function(){
  const scrolled = window.scrollY > 60;
  navbar.classList.toggle('scrolled', scrolled);
  const logo = document.getElementById('navLogo');
  if(logo) logo.src = scrolled
    ? 'Image/icon/homehelp_logo_transparent_dark.svg'
    : 'Image/icon/homehelp_logo_transparent_white.svg';
});
// Mobile menu
const hamburger = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobileMenu');
hamburger.addEventListener('click', function(){
  hamburger.classList.toggle('open');
  mobileMenu.classList.toggle('open');
  document.body.style.overflow = mobileMenu.classList.contains('open') ? 'hidden' : '';
});
document.querySelectorAll('.mob-link').forEach(function(l){
  l.addEventListener('click',function(){
    hamburger.classList.remove('open');
    mobileMenu.classList.remove('open');
    document.body.style.overflow = '';
  });
});
// Reveal
const observer = new IntersectionObserver(function(entries){
  entries.forEach(function(e){
    if(e.isIntersecting){e.target.classList.add('visible');observer.unobserve(e.target);}
  });
}, {threshold:0.1});
document.querySelectorAll('.reveal').forEach(function(el){observer.observe(el);});
</script>
</body>
</html>