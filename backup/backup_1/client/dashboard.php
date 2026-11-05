<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('client');

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ?");
$stmt->bind_param('i', $user_id); $stmt->execute();
$total_bookings = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ? AND status = 'pending'");
$stmt->bind_param('i', $user_id); $stmt->execute();
$pending = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ? AND status = 'approved'");
$stmt->bind_param('i', $user_id); $stmt->execute();
$approved = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ? AND status = 'completed'");
$stmt->bind_param('i', $user_id); $stmt->execute();
$completed = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name, s.icon AS service_icon
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    WHERE b.client_id = ?
    ORDER BY b.created_at DESC
    LIMIT 5
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$recent_bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$notif_count = unreadNotifCount();
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$mark = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$mark->bind_param('i', $user_id);
$mark->execute();

$flash = flashGet();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Dashboard | Home Help</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --navy-deep:#0D1B4B;
  --navy-mid:#152268;
  --navy-light:#2E5FC2;
  --navy-soft:#D4DCF5;
  --amber:#E8A020;--amber-light:#FFF0CC;
  --cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --danger:#EF4444;--danger-bg:#FEF2F2;
  --success:#10B981;--success-bg:#ECFDF5;
  --warning:#F59E0B;--warning-bg:#FFFBEB;
  --info:#3B82F6;--info-bg:#EFF6FF;
  --shadow-sm:0 2px 12px rgba(13,27,75,0.07);
  --shadow-md:0 6px 28px rgba(13,27,75,0.11);
  --radius:14px;--radius-sm:8px;
  --sidebar:260px;
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden}
a{text-decoration:none;color:inherit}
img{max-width:100%;display:block}

.dash-wrap{display:flex;min-height:100vh}

/* ── SIDEBAR ── */
.sidebar{
  width:var(--sidebar);flex-shrink:0;
  background:var(--navy-deep);
  display:flex;flex-direction:column;
  position:fixed;top:0;left:0;bottom:0;
  z-index:200;transition:transform 0.3s ease;overflow-y:auto;
}
.sidebar-logo{
  display:flex;align-items:center;gap:10px;
  padding:24px 20px 20px;
  border-bottom:1px solid rgba(255,255,255,0.08);
}
.sidebar-logo img{width:36px;height:36px;object-fit:contain}
.sidebar-logo-text{font-family:var(--font-display);font-size:1.3rem;font-weight:700;color:var(--white)}
.sidebar-user{
  padding:20px;border-bottom:1px solid rgba(255,255,255,0.08);
  display:flex;align-items:center;gap:12px;
}
.user-avatar{
  width:42px;height:42px;border-radius:50%;
  background:linear-gradient(135deg,var(--navy-light),var(--amber));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-display);font-size:1.1rem;font-weight:700;
  color:var(--white);flex-shrink:0;
}
.user-info{overflow:hidden}
.user-info strong{display:block;font-size:0.88rem;font-weight:700;color:var(--white);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-info span{font-size:0.73rem;color:rgba(255,255,255,0.5);text-transform:capitalize}
.sidebar-nav{padding:16px 0;flex:1}
.nav-section-label{
  font-size:0.66rem;font-weight:700;letter-spacing:0.12em;
  text-transform:uppercase;color:rgba(255,255,255,0.3);padding:12px 20px 6px;
}
.nav-item{
  display:flex;align-items:center;gap:12px;padding:11px 20px;
  font-size:0.87rem;font-weight:500;color:rgba(255,255,255,0.65);
  cursor:pointer;transition:all 0.2s;position:relative;text-decoration:none;
}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,0.06)}
.nav-item.active{color:var(--white);background:rgba(255,255,255,0.1);font-weight:600}
.nav-item.active::before{
  content:'';position:absolute;left:0;top:0;bottom:0;
  width:3px;background:var(--amber);border-radius:0 3px 3px 0;
}
.nav-icon{font-size:1.05rem;width:20px;text-align:center;flex-shrink:0}
.nav-badge{
  margin-left:auto;background:var(--amber);color:var(--white);
  font-size:0.65rem;font-weight:700;padding:2px 6px;border-radius:99px;
}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,0.08)}
.btn-logout{
  display:flex;align-items:center;gap:8px;width:100%;padding:10px 14px;
  border-radius:var(--radius-sm);background:rgba(239,68,68,0.12);
  border:1px solid rgba(239,68,68,0.2);color:rgba(255,100,100,0.9);
  font-size:0.84rem;font-weight:600;cursor:pointer;transition:all 0.2s;
  text-decoration:none;font-family:var(--font-body);
}
.btn-logout:hover{background:rgba(239,68,68,0.2);color:#ff6b6b}

/* ── MAIN ── */
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}

/* ── TOPBAR ── */
.topbar{
  background:var(--white);border-bottom:1px solid var(--cream-dark);
  padding:0 28px;height:64px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:100;
}
.topbar-left{display:flex;align-items:center;gap:16px}
.burger{display:none;background:none;border:none;cursor:pointer;padding:6px;flex-direction:column;gap:4px}
.burger span{display:block;width:20px;height:2px;background:var(--text-dark);border-radius:2px;transition:all 0.3s}
.topbar-title{font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--navy-deep)}
.topbar-right{display:flex;align-items:center;gap:12px}
.topbar-notif{
  position:relative;width:38px;height:38px;border-radius:var(--radius-sm);
  background:var(--cream);border:1.5px solid var(--cream-dark);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:1.1rem;transition:all 0.2s;text-decoration:none;
}
.topbar-notif:hover{border-color:var(--navy-light);background:var(--navy-soft)}
.notif-dot{
  position:absolute;top:6px;right:6px;width:8px;height:8px;
  border-radius:50%;background:var(--danger);border:2px solid var(--white);
}
.btn-book-now{
  display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
  border-radius:99px;background:var(--amber);color:var(--white);
  font-size:0.83rem;font-weight:700;border:none;cursor:pointer;
  transition:all 0.3s;text-decoration:none;
}
.btn-book-now:hover{background:#d4911a;transform:translateY(-1px);box-shadow:0 4px 16px rgba(232,160,32,0.35)}

/* ── CONTENT ── */
.content{padding:28px;flex:1}

/* ── WELCOME BANNER ── */
.welcome-banner{
  background:linear-gradient(135deg,var(--navy-deep) 0%,var(--navy-mid) 100%);
  border-radius:var(--radius);padding:28px 32px;
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:24px;position:relative;overflow:hidden;
}
.welcome-banner::before{
  content:'';position:absolute;top:-40%;right:-10%;width:50%;height:200%;
  background:radial-gradient(ellipse,rgba(232,160,32,0.18) 0%,transparent 65%);
  border-radius:50%;
}
.welcome-text{position:relative;z-index:1}
.welcome-text h2{
  font-family:var(--font-display);font-size:1.8rem;font-weight:700;
  color:var(--white);line-height:1.2;margin-bottom:6px;
}
.welcome-text h2 em{font-style:italic;color:var(--amber)}
.welcome-text p{font-size:0.88rem;color:rgba(255,255,255,0.65)}
.welcome-emoji{font-size:3.5rem;position:relative;z-index:1}

/* ── FLASH ── */
.flash{padding:13px 16px;border-radius:var(--radius-sm);font-size:0.87rem;font-weight:500;margin-bottom:20px;display:flex;align-items:center;gap:10px}
.flash-success{background:var(--success-bg);border:1.5px solid #BBF7D0;color:#065F46}
.flash-error{background:var(--danger-bg);border:1.5px solid #FECACA;color:#B91C1C}

/* ── BOOKING SUCCESS BANNER ── */
.booking-success-banner{
  background:var(--success-bg);
  border:1.5px solid #6EE7B7;
  border-radius:var(--radius);
  padding:16px 22px;
  margin-bottom:20px;
  display:flex;
  align-items:center;
  gap:14px;
  animation:slideDown 0.4s ease both;
}
.booking-success-icon{
  width:40px;height:40px;border-radius:50%;
  background:#D1FAE5;
  display:flex;align-items:center;justify-content:center;
  font-size:1.2rem;flex-shrink:0;
}
.booking-success-body{flex:1}
.booking-success-body strong{
  display:block;font-size:0.92rem;font-weight:700;
  color:#065F46;margin-bottom:2px;
}
.booking-success-body span{
  font-size:0.82rem;color:#047857;
}
.booking-success-close{
  background:none;border:none;cursor:pointer;
  font-size:1rem;color:#6EE7B7;padding:4px;
  transition:color 0.2s;line-height:1;
}
.booking-success-close:hover{color:#065F46;}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

/* ── STATS ── */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{
  background:var(--white);border:1.5px solid var(--cream-dark);
  border-radius:var(--radius);padding:20px 22px;transition:all 0.3s;cursor:default;
}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);border-color:var(--navy-soft)}
.stat-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px}
.stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem}
.stat-icon.total{background:#EFF6FF;color:var(--info)}
.stat-icon.pending{background:var(--warning-bg);color:var(--warning)}
.stat-icon.approved{background:var(--success-bg);color:var(--success)}
.stat-icon.completed{background:var(--navy-soft);color:var(--navy-deep)}
.stat-num{font-family:var(--font-display);font-size:2.2rem;font-weight:700;color:var(--navy-deep);line-height:1}
.stat-label{font-size:0.8rem;color:var(--text-soft);margin-top:4px;font-weight:500}
.stat-trend{font-size:0.72rem;font-weight:600;padding:2px 8px;border-radius:99px}
.trend-up{background:var(--success-bg);color:var(--success)}
.trend-neutral{background:var(--cream-dark);color:var(--text-soft)}

/* ── TWO COL ── */
.two-col{display:grid;grid-template-columns:1fr 360px;gap:20px;margin-bottom:24px}

/* ── CARD ── */
.card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden}
.card-header{
  padding:18px 22px;border-bottom:1px solid var(--cream-dark);
  display:flex;align-items:center;justify-content:space-between;
}
.card-title{font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--navy-deep)}
.card-link{font-size:0.8rem;color:var(--navy-light);font-weight:600;text-decoration:none}
.card-link:hover{color:var(--amber)}
.card-body{padding:0}

/* ── BOOKINGS TABLE ── */
.bookings-table{width:100%;border-collapse:collapse}
.bookings-table th{
  font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;
  color:var(--text-soft);padding:12px 22px;text-align:left;
  background:var(--cream);border-bottom:1px solid var(--cream-dark);
}
.bookings-table td{padding:14px 22px;font-size:0.86rem;border-bottom:1px solid var(--cream-dark);vertical-align:middle}
.bookings-table tr:last-child td{border-bottom:none}
.bookings-table tr:hover td{background:var(--cream)}
.service-cell{display:flex;align-items:center;gap:10px}
.service-badge{
  width:34px;height:34px;border-radius:9px;
  background:var(--navy-soft);
  display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;
}
.service-name{font-weight:600;color:var(--text-dark);font-size:0.86rem}
.service-date{font-size:0.75rem;color:var(--text-soft);margin-top:1px}
.status-pill{display:inline-block;padding:3px 10px;border-radius:99px;font-size:0.72rem;font-weight:700;white-space:nowrap}
.status-pending{background:var(--warning-bg);color:#92400E}
.status-under_review{background:var(--info-bg);color:#1E40AF}
.status-approved{background:var(--success-bg);color:#065F46}
.status-completed{background:var(--navy-soft);color:var(--navy-deep)}
.status-rejected{background:var(--danger-bg);color:#991B1B}
.status-cancelled{background:var(--cream-dark);color:var(--text-soft)}
.empty-state{text-align:center;padding:48px 20px}
.empty-state .empty-icon{font-size:2.5rem;margin-bottom:12px}
.empty-state h4{font-family:var(--font-display);font-size:1.1rem;color:var(--navy-deep);margin-bottom:6px}
.empty-state p{font-size:0.85rem;color:var(--text-soft);margin-bottom:20px}

/* ── NOTIFICATIONS ── */
.notif-list{display:flex;flex-direction:column}
.notif-item{
  padding:14px 20px;border-bottom:1px solid var(--cream-dark);
  display:flex;gap:12px;align-items:flex-start;transition:background 0.2s;
}
.notif-item:last-child{border-bottom:none}
.notif-item:hover{background:var(--cream)}
.notif-item.unread{background:#FFFBEB}
.notif-dot-indicator{width:8px;height:8px;border-radius:50%;background:var(--amber);flex-shrink:0;margin-top:6px}
.notif-dot-read{background:var(--cream-dark)}
.notif-body{flex:1;min-width:0}
.notif-msg{font-size:0.83rem;color:var(--text-dark);line-height:1.5;margin-bottom:3px}
.notif-time{font-size:0.72rem;color:var(--text-soft)}
.notif-empty{padding:32px 20px;text-align:center;font-size:0.85rem;color:var(--text-soft)}

/* ── QUICK ACTIONS ── */
.quick-actions{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px}
.quick-btn{
  background:var(--white);border:1.5px solid var(--cream-dark);
  border-radius:var(--radius);padding:18px 16px;
  text-align:center;cursor:pointer;transition:all 0.3s;text-decoration:none;
  display:flex;flex-direction:column;align-items:center;gap:8px;
}
.quick-btn:hover{border-color:var(--navy-light);background:var(--navy-soft);transform:translateY(-3px);box-shadow:var(--shadow-sm)}
.quick-btn-icon{font-size:1.6rem}
.quick-btn-label{font-size:0.82rem;font-weight:600;color:var(--navy-deep)}

/* ── MOBILE ── */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:199}
@media(max-width:1100px){.two-col{grid-template-columns:1fr}.stats-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .stats-grid{grid-template-columns:repeat(2,1fr)}.content{padding:18px}
  .welcome-emoji{display:none}.topbar{padding:0 16px}
}
@media(max-width:480px){.stats-grid{grid-template-columns:repeat(2,1fr)}.quick-actions{grid-template-columns:repeat(3,1fr)}.welcome-banner{padding:20px}}
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
      <a href="<?= BASE_URL ?>/dashboard"   class="nav-item active"><span class="nav-icon">🏠</span> Dashboard</a>
      <a href="<?= BASE_URL ?>/book"         class="nav-item"><span class="nav-icon">📋</span> Book a Service</a>
      <a href="<?= BASE_URL ?>/my-bookings"  class="nav-item">
        <span class="nav-icon">📂</span> My Bookings
        <?php if($pending > 0): ?><span class="nav-badge"><?= $pending ?></span><?php endif; ?>
      </a>
      <div class="nav-section-label">Account</div>
      <a href="<?= BASE_URL ?>/profile"              class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
      <a href="#notifications"                        class="nav-item">
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

  <div class="main">
    <!-- TOPBAR -->
    <header class="topbar">
      <div class="topbar-left">
        <button class="burger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <span class="topbar-title">Dashboard</span>
      </div>
      <div class="topbar-right">
        <a href="#notifications" class="topbar-notif" title="Notifications">
          🔔<?php if($notif_count > 0): ?><span class="notif-dot"></span><?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/book" class="btn-book-now">＋ Book Service</a>
      </div>
    </header>

    <div class="content">

      <?php if($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>">
          <?= $flash['type']==='success' ? '✅' : '⚠️' ?> <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- ── BOOKING SUCCESS BANNER ── -->
      <?php if (!empty($_SESSION['booking_success'])): ?>
        <?php unset($_SESSION['booking_success']); ?>
        <div class="booking-success-banner" id="bookingSuccessBanner">
          <div class="booking-success-icon">✅</div>
          <div class="booking-success-body">
            <strong>Booking submitted successfully!</strong>
            <span>Our team will review your request and contact you shortly via phone or Email.</span>
          </div>
          <button class="booking-success-close" onclick="dismissSuccess()" title="Dismiss">✕</button>
        </div>
      <?php endif; ?>

      <!-- WELCOME BANNER -->
      <div class="welcome-banner">
        <div class="welcome-text">
          <h2>Welcome back, <em><?= e(explode(' ',$user_name)[0]) ?></em> 👋</h2>
          <p>Here's what's happening with your bookings today.</p>
        </div>
        <div class="welcome-emoji">🏠</div>
      </div>

      <!-- STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon total">📊</div><span class="stat-trend trend-neutral">All</span></div>
          <div class="stat-num"><?= $total_bookings ?></div>
          <div class="stat-label">Total Bookings</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon pending">⏳</div><span class="stat-trend trend-neutral">Active</span></div>
          <div class="stat-num"><?= $pending ?></div>
          <div class="stat-label">Pending Review</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon approved">✅</div><span class="stat-trend trend-up">Good</span></div>
          <div class="stat-num"><?= $approved ?></div>
          <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon completed">🏆</div><span class="stat-trend trend-up">Done</span></div>
          <div class="stat-num"><?= $completed ?></div>
          <div class="stat-label">Completed</div>
        </div>
      </div>

      <!-- QUICK ACTIONS -->
      <div class="quick-actions">
        <a href="<?= BASE_URL ?>/book" class="quick-btn">
          <span class="quick-btn-icon">📋</span><span class="quick-btn-label">Book a Service</span>
        </a>
        <a href="<?= BASE_URL ?>/my-bookings" class="quick-btn">
          <span class="quick-btn-icon">📂</span><span class="quick-btn-label">My Bookings</span>
        </a>
        <a href="<?= BASE_URL ?>/profile" class="quick-btn">
          <span class="quick-btn-icon">👤</span><span class="quick-btn-label">My Profile</span>
        </a>
      </div>

      <!-- RECENT BOOKINGS + NOTIFICATIONS -->
      <div class="two-col">

        <div class="card">
          <div class="card-header">
            <span class="card-title">Recent Bookings</span>
            <a href="<?= BASE_URL ?>/my-bookings" class="card-link">View all →</a>
          </div>
          <div class="card-body">
            <?php if(empty($recent_bookings)): ?>
              <div class="empty-state">
                <div class="empty-icon">📋</div>
                <h4>No bookings yet</h4>
                <p>You haven't made any service bookings yet.</p>
                <a href="<?= BASE_URL ?>/book" class="btn-book-now">Book Your First Service →</a>
              </div>
            <?php else: ?>
              <table class="bookings-table">
                <thead>
                  <tr><th>Service</th><th>Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                  <?php foreach($recent_bookings as $b):
                    $labels = [
                      'pending'=>['Pending','pending'],'under_review'=>['Under Review','under_review'],
                      'approved'=>['Approved','approved'],'completed'=>['Completed','completed'],
                      'rejected'=>['Rejected','rejected'],'cancelled'=>['Cancelled','cancelled'],
                    ];
                    $l = $labels[$b['status']] ?? [$b['status'],'pending'];
                  ?>
                    <tr>
                      <td>
                        <div class="service-cell">
                          <div class="service-badge"><?= $b['service_icon']?:'🏠' ?></div>
                          <div>
                            <div class="service-name"><?= e($b['service_name']) ?></div>
                            <div class="service-date"><?= e($b['location']) ?></div>
                          </div>
                        </div>
                      </td>
                      <td style="font-size:0.83rem;color:var(--text-mid)"><?= date('d M Y', strtotime($b['start_date'])) ?></td>
                      <td><span class="status-pill status-<?= $l[1] ?>"><?= $l[0] ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>

        <div class="card" id="notifications">
          <div class="card-header">
            <span class="card-title">Notifications</span>
            <?php if($notif_count > 0): ?>
              <span style="font-size:0.75rem;background:var(--amber-light);color:#92400E;padding:2px 8px;border-radius:99px;font-weight:700">
                <?= $notif_count ?> new
              </span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if(empty($notifications)): ?>
              <div class="notif-empty">🔔 No notifications yet</div>
            <?php else: ?>
              <div class="notif-list">
                <?php foreach($notifications as $n): ?>
                  <div class="notif-item <?= $n['is_read']?'':'unread' ?>">
                    <div class="notif-dot-indicator <?= $n['is_read']?'notif-dot-read':'' ?>"></div>
                    <div class="notif-body">
                      <div class="notif-msg"><?= e($n['message']) ?></div>
                      <div class="notif-time"><?= date('d M, H:i', strtotime($n['created_at'])) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
  </div>
</div>

<script>
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

function dismissSuccess(){
  var el=document.getElementById('bookingSuccessBanner');
  if(el){
    el.style.transition='opacity 0.3s ease, transform 0.3s ease';
    el.style.opacity='0';
    el.style.transform='translateY(-8px)';
    setTimeout(function(){el.remove();},300);
  }
}

// Auto-dismiss after 8 seconds
(function(){
  var el=document.getElementById('bookingSuccessBanner');
  if(el) setTimeout(dismissSuccess, 8000);
})();
</script>
</body>
</html>