<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('provider');

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ── STATS ─────────────────────────────────────────────────────
$total_apps = $conn->prepare("SELECT COUNT(*) FROM job_applications WHERE provider_id=?");
$total_apps->bind_param('i',$user_id); $total_apps->execute();
$stats['total'] = $total_apps->get_result()->fetch_row()[0];

$approved = $conn->prepare("SELECT COUNT(*) FROM job_applications WHERE provider_id=? AND status='approved'");
$approved->bind_param('i',$user_id); $approved->execute();
$stats['approved'] = $approved->get_result()->fetch_row()[0];

$pending = $conn->prepare("SELECT COUNT(*) FROM job_applications WHERE provider_id=? AND status='pending'");
$pending->bind_param('i',$user_id); $pending->execute();
$stats['pending'] = $pending->get_result()->fetch_row()[0];

// Latest application
$latest = $conn->prepare("
    SELECT ja.*, s.name AS service_name, s.icon AS service_icon
    FROM job_applications ja
    JOIN services s ON ja.service_id = s.id
    WHERE ja.provider_id = ?
    ORDER BY ja.created_at DESC LIMIT 1
");
$latest->bind_param('i',$user_id); $latest->execute();
$latest_app = $latest->get_result()->fetch_assoc();

$flash = flashGet();
$notif_count = unreadNotifCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Provider Dashboard | Home Help</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --navy-deep:#0D1B4B;--navy-mid:#152268;--navy-light:#2E5FC2;--navy-soft:#D4DCF5;
  --amber:#E8A020;--amber-light:#FFF0CC;
  --cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --danger:#EF4444;--danger-bg:#FEF2F2;
  --success:#10B981;--success-bg:#ECFDF5;
  --shadow-sm:0 2px 12px rgba(13,27,75,0.07);
  --shadow-md:0 6px 28px rgba(13,27,75,0.11);
  --radius:14px;--radius-sm:8px;--sidebar:260px;
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden}
a{text-decoration:none;color:inherit}
.dash-wrap{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar);flex-shrink:0;background:var(--navy-deep);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;transition:transform 0.3s ease;overflow-y:auto}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:24px 20px 20px;border-bottom:1px solid rgba(255,255,255,0.08)}
.sidebar-logo img{width:36px;height:36px;object-fit:contain}
.sidebar-logo-text{font-family:var(--font-display);font-size:1.3rem;font-weight:700;color:var(--white)}
.sidebar-user{padding:20px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;gap:12px}
.user-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--amber),#c47d0a);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--white);flex-shrink:0}
.user-info strong{display:block;font-size:0.88rem;font-weight:700;color:var(--white)}
.user-info span{font-size:0.73rem;color:rgba(255,255,255,0.5)}
.sidebar-nav{padding:16px 0;flex:1}
.nav-section-label{font-size:0.66rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.3);padding:12px 20px 6px}
.nav-item{display:flex;align-items:center;gap:12px;padding:11px 20px;font-size:0.87rem;font-weight:500;color:rgba(255,255,255,0.65);cursor:pointer;transition:all 0.2s;position:relative;text-decoration:none}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,0.06)}
.nav-item.active{color:var(--white);background:rgba(255,255,255,0.1);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--amber);border-radius:0 3px 3px 0}
.nav-icon{font-size:1.05rem;width:20px;text-align:center;flex-shrink:0}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,0.08)}
.btn-logout{display:flex;align-items:center;gap:8px;width:100%;padding:10px 14px;border-radius:var(--radius-sm);background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.2);color:rgba(255,100,100,0.9);font-size:0.84rem;font-weight:600;cursor:pointer;transition:all 0.2s;text-decoration:none;font-family:var(--font-body)}
.btn-logout:hover{background:rgba(239,68,68,0.2)}
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--white);border-bottom:1px solid var(--cream-dark);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar-left{display:flex;align-items:center;gap:16px}
.burger{display:none;background:none;border:none;cursor:pointer;padding:6px;flex-direction:column;gap:4px}
.burger span{display:block;width:20px;height:2px;background:var(--text-dark);border-radius:2px}
.topbar-title{font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--navy-deep)}
.content{padding:28px;flex:1}

/* WELCOME */
.welcome-banner{background:linear-gradient(135deg,var(--navy-deep),#1a2d7a);border-radius:var(--radius);padding:24px 28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:24px;position:relative;overflow:hidden}
.welcome-banner::after{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px}
.welcome-text{position:relative;z-index:1}
.welcome-text h2{font-family:var(--font-display);font-size:1.65rem;font-weight:700;color:var(--white);margin-bottom:4px}
.welcome-text h2 em{font-style:italic;color:var(--amber)}
.welcome-text p{font-size:0.85rem;color:rgba(255,255,255,0.55)}
.welcome-btn{position:relative;z-index:1;display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:99px;background:var(--amber);color:var(--white);font-size:0.84rem;font-weight:700;text-decoration:none;transition:all 0.25s;border:none;cursor:pointer;font-family:var(--font-body)}
.welcome-btn:hover{background:#d4911a;transform:translateY(-1px)}

/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:18px 20px;transition:all 0.3s}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md)}
.stat-icon{font-size:1.5rem;margin-bottom:10px}
.stat-num{font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--navy-deep);line-height:1}
.stat-label{font-size:0.78rem;color:var(--text-soft);margin-top:4px;font-weight:500}

/* LATEST APP CARD */
.section-title{font-family:var(--font-display);font-size:1.15rem;font-weight:700;color:var(--navy-deep);margin-bottom:14px}
.latest-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden;margin-bottom:22px}
.latest-header{padding:16px 20px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--cream-dark)}
.latest-icon{width:44px;height:44px;border-radius:12px;background:var(--navy-soft);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
.latest-service{font-weight:700;font-size:0.96rem;color:var(--navy-deep)}
.latest-date{font-size:0.74rem;color:var(--text-soft);margin-top:2px}
.status-pill{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:99px;font-size:0.73rem;font-weight:700;margin-left:auto}
.sp-pending{background:#FFFBEB;color:#92400E}
.sp-review{background:#EFF6FF;color:#1E40AF}
.sp-approved{background:var(--success-bg);color:#065F46}
.sp-rejected{background:var(--danger-bg);color:#991B1B}
.latest-body{padding:16px 20px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
.dl{font-size:0.7rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-soft)}
.dv{font-size:0.86rem;font-weight:600;color:var(--text-dark);margin-top:3px}

/* QUICK ACTIONS */
.quick-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
.quick-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:20px;display:flex;align-items:center;gap:14px;transition:all 0.25s;text-decoration:none}
.quick-card:hover{border-color:var(--navy-light);box-shadow:var(--shadow-sm);transform:translateY(-2px)}
.quick-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
.qi-green{background:var(--navy-soft)}
.qi-amber{background:var(--amber-light)}
.quick-text strong{display:block;font-size:0.9rem;font-weight:700;color:var(--navy-deep)}
.quick-text span{font-size:0.78rem;color:var(--text-soft);margin-top:2px}

/* MOBILE */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:199}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .content{padding:18px}.topbar{padding:0 16px}
  .stats-grid{grid-template-columns:repeat(3,1fr)}
  .latest-body{grid-template-columns:1fr 1fr}
  .quick-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>
<div class="dash-wrap">
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="../Image/icon/homehelp_logo_transparent_white.svg" alt="Home Help" onerror="this.style.display='none'"/>
      <span class="sidebar-logo-text">Home Help</span>
    </div>
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($user_name,0,1)) ?></div>
      <div class="user-info"><strong><?= e($user_name) ?></strong><span>Job Seeker</span></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Main</div>
      <a href="<?= BASE_URL ?>/provider-dashboard" class="nav-item active"><span class="nav-icon">🏠</span> Dashboard</a>
      <a href="<?= BASE_URL ?>/apply"              class="nav-item"><span class="nav-icon">📝</span> Apply for a Job</a>
      <a href="<?= BASE_URL ?>/my-applications"    class="nav-item"><span class="nav-icon">📂</span> My Applications</a>
      <div class="nav-section-label">Account</div>
      <a href="<?= BASE_URL ?>/profile"            class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
      <div class="nav-section-label">Help</div>
      <a href="<?= BASE_URL ?>/#faq"              class="nav-item"><span class="nav-icon">❓</span> FAQ</a>
      <a href="<?= BASE_URL ?>/#contact"          class="nav-item"><span class="nav-icon">💬</span> Contact Support</a>
    </nav>
    <div class="sidebar-footer">
      <a href="<?= BASE_URL ?>/logout" class="btn-logout"><span>🚪</span> Sign Out</a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="burger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <span class="topbar-title">My Dashboard</span>
      </div>
    </header>

    <div class="content">

      <?php if($flash): ?>
        <div style="padding:12px 16px;border-radius:8px;font-size:0.86rem;font-weight:500;margin-bottom:20px;background:var(--success-bg);border:1.5px solid #BBF7D0;color:#065F46;display:flex;align-items:center;gap:10px">
          ✅ <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- WELCOME -->
      <div class="welcome-banner">
        <div class="welcome-text">
          <h2>Welcome back, <em><?= e(explode(' ',$user_name)[0]) ?></em> 👋</h2>
          <p>Ready to find your next opportunity? — <?= date('l, d F Y') ?></p>
        </div>
        <a href="<?= BASE_URL ?>/apply" class="welcome-btn">📝 Apply for a Job</a>
      </div>

      <!-- STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">📂</div>
          <div class="stat-num"><?= $stats['total'] ?></div>
          <div class="stat-label">Total Applications</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">✅</div>
          <div class="stat-num"><?= $stats['approved'] ?></div>
          <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">⏳</div>
          <div class="stat-num"><?= $stats['pending'] ?></div>
          <div class="stat-label">Pending Review</div>
        </div>
      </div>

      <!-- LATEST APPLICATION -->
      <?php if($latest_app): ?>
        <div class="section-title">Latest Application</div>
        <?php
          $sc_map = ['pending'=>['sp-pending','⏳'],'under_review'=>['sp-review','🔍'],'approved'=>['sp-approved','✅'],'rejected'=>['sp-rejected','❌']];
          $sc = $sc_map[$latest_app['status']] ?? ['sp-pending','⏳'];
        ?>
        <div class="latest-card">
          <div class="latest-header">
            <div class="latest-icon"><?= $latest_app['service_icon']?:'💼' ?></div>
            <div>
              <div class="latest-service"><?= e($latest_app['service_name']) ?></div>
              <div class="latest-date">Submitted <?= date('d M Y', strtotime($latest_app['created_at'])) ?></div>
            </div>
            <span class="status-pill <?= $sc[0] ?>"><?= $sc[1] ?> <?= ucfirst(str_replace('_',' ',$latest_app['status'])) ?></span>
          </div>
          <div class="latest-body">
            <div><div class="dl">Location</div><div class="dv"><?= e($latest_app['location']) ?></div></div>
            <div><div class="dl">Availability</div><div class="dv"><?= e($latest_app['availability']?:'—') ?></div></div>
            <div><div class="dl">Application #</div><div class="dv"><?= $latest_app['id'] ?></div></div>
          </div>
        </div>
      <?php endif; ?>

      <!-- QUICK ACTIONS -->
      <div class="section-title">Quick Actions</div>
      <div class="quick-grid">
        <a href="<?= BASE_URL ?>/apply" class="quick-card">
          <div class="quick-icon qi-green">📝</div>
          <div class="quick-text">
            <strong>Apply for a Job</strong>
            <span>Submit a new job application</span>
          </div>
        </a>
        <a href="<?= BASE_URL ?>/my-applications" class="quick-card">
          <div class="quick-icon qi-amber">📂</div>
          <div class="quick-text">
            <strong>My Applications</strong>
            <span>Track all your applications</span>
          </div>
        </a>
      </div>

    </div>
    <?php include_once '../includes/footer.php'; ?>
  </div>
</div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');document.body.style.overflow=document.getElementById('sidebar').classList.contains('open')?'hidden':'';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
window.addEventListener('resize',function(){if(window.innerWidth>768)closeSidebar();});
</script>
</body>
</html>