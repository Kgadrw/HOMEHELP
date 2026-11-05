<?php
require_once 'config/db.php';
require_once 'config/config.php';

$services = $conn->query("
    SELECT id, name, icon FROM services WHERE status='active' ORDER BY sort_order ASC, id ASC
")->fetch_all(MYSQLI_ASSOC);

$where   = ["u.role = 'provider'", "u.status = 'active'"];
$params  = [];
$types   = '';

$filter_service      = (int)($_GET['service']  ?? 0);
$filter_district     = sanitize($_GET['district'] ?? '');
$filter_search       = sanitize($_GET['q'] ?? '');
$filter_experience   = sanitize($_GET['experience'] ?? '');
$filter_availability = sanitize($_GET['availability'] ?? '');

if ($filter_service) {
    $where[]  = "ja.service_id = ?";
    $params[] = $filter_service;
    $types   .= 'i';
}
if ($filter_district) {
    $where[]  = "ja.district = ?";
    $params[] = $filter_district;
    $types   .= 's';
}
if ($filter_search) {
    $where[]  = "(u.name LIKE ? OR ja.full_name LIKE ? OR ja.bio LIKE ?)";
    $like = '%' . $filter_search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}
if ($filter_availability) {
    $where[]  = "ja.availability LIKE ?";
    $params[] = '%' . str_replace('-', ' ', $filter_availability) . '%';
    $types   .= 's';
}
if ($filter_experience) {
    $exp_map = [
        '0-1' => ['Less than 1 year', '0', '1 year'],
        '1-3' => ['1-2 years', '1-2', '2 year'],
        '3-5' => ['3-5 years', '3-5', '3 year', '4 year'],
        '5+'  => ['5+ years', '5+', '5 year', '6 year', '7 year', '8 year', '9 year', '10'],
    ];
    if (!empty($exp_map[$filter_experience])) {
        $ors = [];
        foreach ($exp_map[$filter_experience] as $piece) {
            $ors[] = 'ja.experience LIKE ?';
            $params[] = '%' . $piece . '%';
            $types   .= 's';
        }
        $where[] = '(' . implode(' OR ', $ors) . ')';
    }
}

$where_sql = implode(' AND ', $where);

$sql = "
    SELECT u.id, u.name, u.created_at, u.photo, u.profile_photo,
           s.name AS service_name, s.icon AS service_icon,
           ja.status AS app_status, ja.full_name, ja.age, ja.location, ja.district,
           ja.experience, ja.bio, ja.availability, ja.expected_pay, ja.languages,
           ja.created_at AS applied_at
    FROM users u
    LEFT JOIN job_applications ja ON ja.provider_id = u.id AND ja.status = 'approved'
    LEFT JOIN services s ON s.id = ja.service_id
    WHERE $where_sql
    GROUP BY u.id
    ORDER BY u.created_at DESC
";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $workers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $workers = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function worker_photo_url(array $w): string {
    $file = trim((string)($w['photo'] ?: ($w['profile_photo'] ?? '')));
    if ($file === '') return 'Image/profile-placeholder.png';
    if (preg_match('#^https?://#i', $file)) return $file;
    if (strpos($file, 'uploads/') === 0 || strpos($file, 'Image/') === 0) return $file;
    return 'uploads/avatars/' . ltrim($file, '/');
}

function worker_parse_bio(?string $bio): array {
    $raw = html_entity_decode((string)$bio, ENT_QUOTES, 'UTF-8');
    $education = '';
    $about = '';
    if (preg_match('/EDUCATION:\s*([^\n\r]+)/i', $raw, $m)) {
        $education = trim($m[1]);
    }
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

function worker_exp_label(?string $exp): string {
    $exp = trim((string)$exp);
    if ($exp === '') return 'Experience on request';
    if (stripos($exp, 'year') !== false) {
        return $exp . (stripos($exp, 'experience') === false ? ' experience' : '');
    }
    return $exp . ' years experience';
}

$total = count($workers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<?php $seo_keywords = 'find a worker Rwanda, hire nanny Kigali, hire housekeeper Rwanda, hire home chef Kigali, verified domestic workers Rwanda, Home Help find worker'; ?>
<?php include __DIR__ . '/includes/seo-meta.php'; ?>
<meta name="description" content="Find trusted domestic workers in Rwanda. Browse verified nannies, housekeepers, chefs and caregivers on Home Help."/>
<title>Find a Worker | Home Help Rwanda</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<style>
:root{
  --green-deep:#0A1F6B;--green-mid:#152268;--green-light:#2E5FC2;
  --white:#FFFFFF;--field:#EEF2F8;--border:#E6EAF2;
  --text-dark:#0B1220;--text-mid:#6B7280;--text-soft:#8B93A7;
  --shadow-sm:0 2px 12px rgba(13,27,75,0.08);
  --font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--font-body);background:var(--white);color:var(--text-dark);overflow-x:hidden;line-height:1.65}
a{text-decoration:none;color:inherit}
img{max-width:100%;height:auto;display:block}
.container{width:min(96%,1400px);margin:0 auto;padding:0 16px}

.navbar{position:fixed;top:0;left:0;right:0;z-index:1000;padding:16px 0;transition:all 0.35s ease;background:rgba(255,255,255,0.94);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);border-radius:0}
.navbar.scrolled{top:12px;left:50%;right:auto;width:max-content;max-width:calc(100% - 24px);transform:translateX(-50%);padding:8px 18px;border-radius:999px;background:rgba(255,255,255,0.96);box-shadow:0 10px 36px rgba(7,35,24,0.12);border:1.5px solid rgba(7,35,24,0.08)}
.navbar.scrolled .container{width:auto;max-width:none;padding:0 10px}
.navbar.scrolled .navbar-inner{gap:28px}
.navbar-inner{display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:nowrap}
.nav-logo{display:flex;align-items:center;gap:10px}
.nav-logo img{width:40px;height:40px;object-fit:contain}
.nav-logo-text{font-size:1.2rem;font-weight:800;color:var(--green-deep);letter-spacing:-0.02em}
.nav-links{display:flex;align-items:center;gap:20px;list-style:none;flex-wrap:nowrap;justify-content:center}
.nav-links a{font-size:0.9rem;font-weight:500;color:#6B7280;transition:color 0.2s;text-decoration:none;white-space:nowrap}
.nav-links a:hover{color:var(--green-deep)}
.nav-links a.active{color:var(--green-deep);font-weight:700}
.nav-cta{display:flex;align-items:center;gap:16px}
.nav-login{font-size:0.9rem;font-weight:600;color:var(--text-mid)}
.nav-login:hover{color:var(--green-deep)}
.nav-register{font-size:0.9rem;font-weight:600;color:var(--green-deep)}
.btn-find-worker{display:inline-flex;align-items:center;gap:7px;padding:11px 22px;border-radius:999px;background:var(--green-deep);color:var(--white);font-size:0.88rem;font-weight:700}
.btn-find-worker:hover{background:var(--green-mid)}
.btn-find-worker svg{width:15px;height:15px}
.hamburger{display:none;flex-direction:column;gap:5px;background:none;border:none;cursor:pointer;padding:4px}
.hamburger span{display:block;width:24px;height:2px;background:var(--green-deep);border-radius:2px;transition:all 0.3s}
.hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg)}
.hamburger.open span:nth-child(2){opacity:0}
.hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}
.mobile-menu{display:none;position:fixed;inset:0;background:var(--green-deep);z-index:999;flex-direction:column;align-items:center;padding:90px 20px 40px;opacity:0;pointer-events:none;transition:opacity 0.3s;overflow-y:auto}
.mobile-menu.open{display:flex;opacity:1;pointer-events:all}
.mob-link{font-size:1.4rem;font-weight:700;color:var(--white);padding:14px 0;width:100%;text-align:center;border-bottom:1px solid rgba(255,255,255,0.08);text-decoration:none}
.mob-link.active{font-weight:800}
.mobile-menu-footer{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;padding:24px 0 0;width:100%;margin-top:8px;border-top:1px solid rgba(255,255,255,0.1)}

.page-hero{padding:120px 0 40px;background:var(--white)}
.page-eyebrow{font-size:0.88rem;font-weight:500;color:var(--text-soft);margin-bottom:10px}
.page-title{font-size:clamp(2rem,4vw,3rem);font-weight:800;color:var(--green-deep);letter-spacing:-0.03em;line-height:1.12;margin-bottom:12px}
.page-sub{font-size:1rem;color:var(--text-mid);max-width:560px;line-height:1.7}

.main{padding:24px 0 88px;background:#F4F6FA}
.filter-chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px}
.chip{display:inline-flex;align-items:center;gap:6px;padding:9px 14px;border-radius:99px;border:1.5px solid var(--border);background:var(--white);font-size:0.82rem;font-weight:600;color:var(--text-mid);transition:all 0.2s}
.chip:hover,.chip.active{background:var(--green-deep);border-color:var(--green-deep);color:var(--white)}
.results-header{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap}
.results-title{font-size:clamp(1.4rem,2.5vw,1.9rem);font-weight:800;color:var(--green-deep);letter-spacing:-0.02em}
.results-count{font-size:0.9rem;color:var(--text-soft);margin-top:4px}
.results-count strong{color:var(--green-deep)}
.clear-filters{font-size:0.85rem;font-weight:600;color:var(--green-light)}

.workers-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
.worker-card{display:flex;flex-direction:column;border-radius:22px;overflow:hidden;background:var(--white);border:1px solid var(--border);isolation:isolate}
.worker-media{position:relative;aspect-ratio:1/1;background:#E8ECF4;overflow:hidden}
.worker-media img{width:100%;height:100%;object-fit:cover;object-position:center top;display:block}
.worker-body{padding:16px 16px 18px;display:flex;flex-direction:column;gap:4px;background:var(--white)}
.worker-name{font-size:1.02rem;font-weight:700;color:var(--green-deep)}
.worker-role{font-size:0.86rem;color:#4B5568;font-weight:600}
.worker-meta{font-size:0.78rem;color:var(--text-soft);margin-bottom:10px}
.worker-badge{display:inline-flex;align-items:center;gap:5px;width:fit-content;font-size:0.68rem;font-weight:700;color:var(--green-deep);background:#EEF2FF;padding:3px 8px;border-radius:99px;margin-bottom:6px}
.worker-btn{display:inline-flex;align-items:center;justify-content:center;width:100%;padding:11px 16px;border-radius:999px;background:var(--green-deep);color:var(--white);font-size:0.88rem;font-weight:700;transition:background 0.25s}
.worker-btn:hover{background:var(--green-mid)}

.empty-state{grid-column:1/-1;text-align:center;padding:64px 20px;background:var(--white);border-radius:24px;border:1px solid var(--border)}
.empty-state h3{font-size:1.4rem;font-weight:800;color:var(--green-deep);margin:12px 0 8px}
.empty-state p{color:var(--text-soft);max-width:420px;margin:0 auto 20px}
.empty-state a{display:inline-flex;padding:12px 22px;border-radius:999px;background:var(--green-deep);color:var(--white);font-weight:700}

.cta-wrap{margin-top:48px}
.cta-card{position:relative;overflow:hidden;background:#07164F;border-radius:28px;padding:48px;text-align:center;isolation:isolate}
.cta-card::before{content:'';position:absolute;inset:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");background-size:140px 140px;opacity:0.3;mix-blend-mode:soft-light;pointer-events:none}
.cta-inner{position:relative;z-index:1;max-width:620px;margin:0 auto}
.cta-inner h2{font-size:clamp(1.6rem,3vw,2.3rem);font-weight:800;color:var(--white);letter-spacing:-0.03em;margin-bottom:10px}
.cta-inner p{color:rgba(255,255,255,0.7);margin-bottom:24px;line-height:1.7}
.cta-btns{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}
.cta-btn-primary{display:inline-flex;align-items:center;gap:8px;padding:13px 22px;border-radius:999px;background:var(--white);color:var(--green-deep);font-weight:700}
.cta-btn-ghost{display:inline-flex;align-items:center;padding:12px 22px;border-radius:999px;border:1.5px solid rgba(255,255,255,0.35);color:var(--white);font-weight:700}

.footer{background:#07164F;color:rgba(255,255,255,0.7);padding:72px 0 0;position:relative;isolation:isolate}
.footer::before{content:'';position:absolute;inset:0;pointer-events:none;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");background-size:140px 140px;opacity:0.2;mix-blend-mode:soft-light}
.footer .container{position:relative;z-index:1}
.footer-grid{display:grid;grid-template-columns:1.8fr 1fr 1fr 1fr;gap:40px;margin-bottom:48px}
.footer-brand{display:flex;align-items:center;gap:10px;margin-bottom:14px;text-decoration:none}
.footer-brand img{width:40px;height:40px;object-fit:contain}
.footer-brand span{font-size:1.2rem;font-weight:800;color:var(--white);letter-spacing:-0.02em}
.footer-desc{font-size:0.88rem;line-height:1.75;color:rgba(255,255,255,0.58);margin-bottom:20px;max-width:320px}
.social-links{display:flex;gap:10px}
.social-link{width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.18);display:inline-flex;align-items:center;justify-content:center;color:var(--white);transition:all 0.25s}
.social-link svg{width:17px;height:17px;display:block}
.social-link:hover{background:rgba(255,255,255,0.14);border-color:rgba(255,255,255,0.35)}
.footer-col h5{font-size:0.92rem;font-weight:700;color:var(--white);margin-bottom:16px}
.footer-links{display:flex;flex-direction:column;gap:10px;list-style:none}
.footer-links a{font-size:0.88rem;color:rgba(255,255,255,0.55);transition:color 0.2s}
.footer-links a:hover{color:var(--white)}
.footer-bottom{border-top:1px solid rgba(255,255,255,0.1);padding:22px 0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.footer-bottom p{font-size:0.82rem;color:rgba(255,255,255,0.42)}
.footer-bottom-links{display:flex;gap:20px}
.footer-bottom a{font-size:0.82rem;color:rgba(255,255,255,0.42);transition:color 0.2s}
.footer-bottom a:hover{color:var(--white)}
.footer-credit{position:relative;z-index:1;text-align:center;padding:14px 0;border-top:1px solid rgba(255,255,255,0.08);font-size:0.76rem;color:rgba(255,255,255,0.32)}
.footer-credit a{color:rgba(255,255,255,0.5)}
@media(max-width:900px){.footer-grid{grid-template-columns:1fr 1fr;gap:32px}}
@media(max-width:600px){.footer-grid{grid-template-columns:1fr}}

.reveal{opacity:0;transform:translateY(20px);transition:opacity 0.6s ease,transform 0.6s ease}
.reveal.visible{opacity:1;transform:translateY(0)}

@media(max-width:1100px){.workers-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){
  .workers-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:768px){
  .nav-links,.nav-cta{display:none}.hamburger{display:flex}
  .workers-grid{grid-template-columns:1fr}
  .cta-card{padding:36px 22px}
}
</style>
</head>
<body>

<?php $nav_active = 'find-worker'; include __DIR__ . '/includes/public-header.php'; ?>

<section class="page-hero">
  <div class="container">
    <div class="page-eyebrow"><?= (int)$total ?> worker<?= $total!=1?'s':'' ?> available</div>
    <h1 class="page-title">Find Trusted Domestic Workers</h1>
    <p class="page-sub">Browse verified nannies, housekeepers, chefs, and caregivers available in Rwanda. Book directly through your dashboard.</p>
  </div>
</section>

<section class="main">
  <div class="container">
    <div class="filter-chips">
      <a href="/find-worker" class="chip <?= !$filter_service?'active':'' ?>">All Workers</a>
      <?php foreach ($services as $svc): ?>
        <a href="/find-worker?service=<?= (int)$svc['id'] ?>" class="chip <?= $filter_service==$svc['id']?'active':'' ?>">
          <?= htmlspecialchars($svc['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="results-header">
      <div>
        <h2 class="results-title">Featured workers</h2>
        <p class="results-count">Showing <strong><?= (int)$total ?></strong> verified worker<?= $total!=1?'s':'' ?>
          <?= $filter_search ? ' for "'.e($filter_search).'"' : '' ?>
          <?= $filter_service ? ' in selected category' : '' ?>
        </p>
      </div>
      <?php if ($filter_search || $filter_service || $filter_district): ?>
        <a href="/find-worker" class="clear-filters">Clear filters</a>
      <?php endif; ?>
    </div>

    <div class="workers-grid">
      <?php if (empty($workers)): ?>
        <div class="empty-state reveal">
          <i data-lucide="search-x" style="width:36px;height:36px;color:var(--green-deep)"></i>
          <h3>No Workers Found</h3>
          <p>No workers match your search right now. Try different filters or check back soon.</p>
          <a href="/find-worker">Clear Filters</a>
        </div>
      <?php else: ?>
        <?php foreach ($workers as $w):
          $display_name = trim((string)($w['full_name'] ?? '')) ?: $w['name'];
          $svc_name = $w['service_name'] ?: 'Domestic Worker';
          $exp_label = worker_exp_label($w['experience'] ?? '');
          $parsed = worker_parse_bio($w['bio'] ?? '');
          $photo = worker_photo_url($w);
          $place_parts = array_values(array_unique(array_filter([
            trim((string)($w['location'] ?? '')),
            trim((string)($w['district'] ?? '')),
          ])));
          $place = implode(' · ', $place_parts);
          $joined = !empty($w['created_at']) ? date('M Y', strtotime($w['created_at'])) : '';
        ?>
          <?php
            $meta_bits = [$exp_label];
            if (!empty($w['age'])) $meta_bits[] = (int)$w['age'] . ' yrs';
            if ($place !== '') $meta_bits[] = $place;
            if (!empty($w['availability'])) $meta_bits[] = $w['availability'];
            if (!empty($w['languages'])) $meta_bits[] = $w['languages'];
            if ($parsed['education'] !== '') $meta_bits[] = $parsed['education'];
            if (!empty($w['expected_pay'])) $meta_bits[] = $w['expected_pay'];
            if ($joined !== '') $meta_bits[] = 'Since ' . $joined;
            $meta_line = implode(' · ', $meta_bits);
          ?>
          <article class="worker-card reveal">
            <div class="worker-media">
              <img src="<?= e($photo) ?>" alt="<?= e($display_name) ?>"/>
            </div>
            <div class="worker-body">
              <span class="worker-badge">Verified</span>
              <div class="worker-name"><?= e($display_name) ?></div>
              <div class="worker-role"><?= e($svc_name) ?></div>
              <div class="worker-meta" title="<?= e($parsed['about'] !== '' ? $parsed['about'] : $meta_line) ?>"><?= e($meta_line) ?></div>
              <a href="/login" class="worker-btn" data-open-login>Hire Now</a>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="cta-wrap">
      <div class="cta-card reveal">
        <div class="cta-inner">
          <h2>Can't Find the Right Worker?</h2>
          <p>Submit a booking request and our agents will find the perfect match for your needs across Rwanda.</p>
          <div class="cta-btns">
            <a href="/register" class="cta-btn-primary" data-open-book>Post a Request <i data-lucide="arrow-right"></i></a>
            <a href="/#contact" class="cta-btn-ghost">Contact Our Team</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <a href="/" class="footer-brand">
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
          <li><a href="/#services">Why Choose Us</a></li>
          <li><a href="/#how-it-works">How It Works</a></li>
          <li><a href="/career">Career</a></li>
          <li><a href="/#faq">FAQ</a></li>
          <li><a href="/#contact">Contact</a></li>
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
      <p>&copy; <?= date('Y') ?> Home Help &middot; Rwanda</p>
      <div class="footer-bottom-links">
        <a href="/privacy">Privacy Policy</a>
        <a href="/terms">Terms of Service</a>
      </div>
    </div>
  </div>
</footer>

<script>
const observer=new IntersectionObserver(function(entries){
  entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add('visible');observer.unobserve(e.target);}});
},{threshold:0.1});
document.querySelectorAll('.reveal').forEach(function(el){observer.observe(el);});
if(window.lucide){lucide.createIcons();}
</script>
<?php
include __DIR__ . '/includes/worker-login-modal.php';
include __DIR__ . '/includes/client-login-modal.php';
include __DIR__ . '/includes/register-modal.php';
include __DIR__ . '/includes/book-modal.php';
?>
</body>
</html>
