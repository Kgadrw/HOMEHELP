<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('agent');

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Stats
$stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id=?");
$stmt->bind_param('i',$user_id); $stmt->execute();
$total_bookings = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id=? AND status='pending'");
$stmt->bind_param('i',$user_id); $stmt->execute();
$pending_bookings = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id=? AND status='approved'");
$stmt->bind_param('i',$user_id); $stmt->execute();
$approved_bookings = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id=? AND status='completed'");
$stmt->bind_param('i',$user_id); $stmt->execute();
$completed_bookings = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) FROM job_applications WHERE agent_id=?");
$stmt->bind_param('i',$user_id); $stmt->execute();
$total_apps = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) FROM job_applications WHERE agent_id=? AND status='pending'");
$stmt->bind_param('i',$user_id); $stmt->execute();
$pending_apps = $stmt->get_result()->fetch_row()[0];

// Recent bookings
$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name, s.icon AS service_icon,
           u.name AS client_name
    FROM bookings b
    JOIN services s ON b.service_id=s.id
    JOIN users u ON b.client_id=u.id
    WHERE b.agent_id=?
    ORDER BY b.created_at DESC LIMIT 5
");
$stmt->bind_param('i',$user_id); $stmt->execute();
$recent_bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recent applications
$stmt = $conn->prepare("
    SELECT ja.*, s.name AS service_name, s.icon AS service_icon,
           u.name AS provider_name
    FROM job_applications ja
    JOIN services s ON ja.service_id=s.id
    JOIN users u ON ja.provider_id=u.id
    WHERE ja.agent_id=?
    ORDER BY ja.created_at DESC LIMIT 5
");
$stmt->bind_param('i',$user_id); $stmt->execute();
$recent_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$flash = flashGet();
$notif_count = unreadNotifCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Team Dashboard | Home Help</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --teal-deep:#0F2D3D;
  --teal-mid:#164E63;
  --teal-light:#0891B2;
  --teal-soft:#CFFAFE;
  --teal-sidebar:#0A2233;
  --amber:#E8A020;--amber-light:#FFF0CC;
  --cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --danger:#EF4444;--danger-bg:#FEF2F2;
  --success:#10B981;--success-bg:#ECFDF5;
  --warning:#F59E0B;--warning-bg:#FFFBEB;
  --info:#3B82F6;--info-bg:#EFF6FF;
  --shadow-sm:0 2px 12px rgba(15,45,61,0.08);
  --shadow-md:0 6px 28px rgba(15,45,61,0.13);
  --radius:14px;--radius-sm:8px;--sidebar:260px;
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden}
a{text-decoration:none;color:inherit}
.dash-wrap{display:flex;min-height:100vh}

/* SIDEBAR — dark teal */
.sidebar{width:var(--sidebar);flex-shrink:0;background:var(--teal-sidebar);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;transition:transform 0.3s ease;overflow-y:auto}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:22px 20px 18px;border-bottom:1px solid rgba(255,255,255,0.07)}
.sidebar-logo img{width:34px;height:34px;object-fit:contain}
.sidebar-logo-text{font-family:var(--font-display);font-size:1.25rem;font-weight:700;color:var(--white)}
.agent-badge{display:inline-block;padding:2px 8px;border-radius:99px;background:rgba(8,145,178,0.25);border:1px solid rgba(8,145,178,0.45);color:#67E8F9;font-size:0.62rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;margin-left:auto;flex-shrink:0}
.sidebar-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;gap:12px}
.user-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--teal-light),var(--amber));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--white);flex-shrink:0}
.user-info strong{display:block;font-size:0.86rem;font-weight:700;color:var(--white)}
.user-info span{font-size:0.71rem;color:#67E8F9;font-weight:600}
.sidebar-nav{padding:14px 0;flex:1}
.nav-section-label{font-size:0.64rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.25);padding:12px 20px 5px}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 20px;font-size:0.86rem;font-weight:500;color:rgba(255,255,255,0.6);cursor:pointer;transition:all 0.2s;position:relative;text-decoration:none}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,0.06)}
.nav-item.active{color:var(--white);background:rgba(8,145,178,0.18);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--amber);border-radius:0 3px 3px 0}
.nav-icon{font-size:1rem;width:20px;text-align:center;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--danger);color:var(--white);font-size:0.63rem;font-weight:700;padding:2px 6px;border-radius:99px}
.sidebar-footer{padding:14px 20px;border-top:1px solid rgba(255,255,255,0.07)}
.btn-logout{display:flex;align-items:center;gap:8px;width:100%;padding:9px 14px;border-radius:var(--radius-sm);background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.18);color:rgba(255,100,100,0.85);font-size:0.83rem;font-weight:600;cursor:pointer;transition:all 0.2s;text-decoration:none;font-family:var(--font-body)}
.btn-logout:hover{background:rgba(239,68,68,0.2)}

/* MAIN */
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--white);border-bottom:1px solid var(--cream-dark);padding:0 28px;height:62px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar-left{display:flex;align-items:center;gap:14px}
.burger{display:none;background:none;border:none;cursor:pointer;padding:6px;flex-direction:column;gap:4px}
.burger span{display:block;width:20px;height:2px;background:var(--text-dark);border-radius:2px}
.topbar-title{font-family:var(--font-display);font-size:1.35rem;font-weight:700;color:var(--teal-deep)}
.topbar-right{display:flex;align-items:center;gap:10px}
.topbar-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:99px;background:var(--teal-soft);color:var(--teal-deep);font-size:0.78rem;font-weight:700}
.topbar-notif{position:relative;width:36px;height:36px;border-radius:var(--radius-sm);background:var(--cream);border:1.5px solid var(--cream-dark);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1rem;transition:all 0.2s;text-decoration:none}
.topbar-notif:hover{border-color:var(--teal-light);background:var(--teal-soft)}
.notif-dot{position:absolute;top:5px;right:5px;width:7px;height:7px;border-radius:50%;background:var(--danger);border:2px solid var(--white)}

.content{padding:24px 28px;flex:1}
.flash{padding:12px 16px;border-radius:var(--radius-sm);font-size:0.86rem;font-weight:500;margin-bottom:18px;display:flex;align-items:center;gap:10px}
.flash-success{background:var(--success-bg);border:1.5px solid #BBF7D0;color:#065F46}
.flash-error{background:var(--danger-bg);border:1.5px solid #FECACA;color:#B91C1C}

/* WELCOME BANNER */
.welcome-banner{
  background:linear-gradient(135deg,var(--teal-sidebar) 0%,var(--teal-mid) 100%);
  border-radius:var(--radius);padding:24px 28px;
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:22px;position:relative;overflow:hidden;
}
.welcome-banner::before{content:'';position:absolute;top:-40%;right:-8%;width:40%;height:200%;background:radial-gradient(ellipse,rgba(232,160,32,0.2) 0%,transparent 65%);border-radius:50%}
.welcome-banner::after{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px}
.welcome-text{position:relative;z-index:1}
.welcome-text h2{font-family:var(--font-display);font-size:1.7rem;font-weight:700;color:var(--white);margin-bottom:4px}
.welcome-text h2 em{font-style:italic;color:var(--amber)}
.welcome-text p{font-size:0.85rem;color:rgba(255,255,255,0.55)}
.welcome-right{position:relative;z-index:1;display:flex;gap:10px;flex-wrap:wrap}
.btn-welcome{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:99px;font-size:0.8rem;font-weight:700;cursor:pointer;transition:all 0.25s;text-decoration:none;border:none;font-family:var(--font-body)}
.btn-amber{background:var(--amber);color:var(--white)}.btn-amber:hover{background:#d4911a;transform:translateY(-1px)}
.btn-ghost{background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.85);border:1px solid rgba(255,255,255,0.2)}.btn-ghost:hover{background:rgba(255,255,255,0.18)}

/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}
.stat-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:18px 20px;transition:all 0.3s}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);border-color:var(--teal-soft)}
.stat-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px}
.stat-icon{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.15rem}
.stat-num{font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--teal-deep);line-height:1}
.stat-label{font-size:0.78rem;color:var(--text-soft);margin-top:3px;font-weight:500}
.stat-sub{font-size:0.72rem;margin-top:5px;font-weight:600}
.sub-warn{color:var(--warning)}.sub-up{color:var(--success)}.sub-neutral{color:var(--text-soft)}

/* TWO COL */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden}
.card-header{padding:16px 20px;border-bottom:1px solid var(--cream-dark);display:flex;align-items:center;justify-content:space-between}
.card-title{font-family:var(--font-display);font-size:1.05rem;font-weight:700;color:var(--teal-deep)}
.card-link{font-size:0.78rem;color:var(--teal-light);font-weight:600;text-decoration:none}
.card-link:hover{color:var(--amber)}

/* LISTS */
.item-list{display:flex;flex-direction:column}
.list-item{display:flex;align-items:center;gap:12px;padding:13px 18px;border-bottom:1px solid var(--cream-dark);transition:background 0.2s}
.list-item:last-child{border-bottom:none}
.list-item:hover{background:var(--cream)}
.item-icon{width:36px;height:36px;border-radius:10px;background:var(--teal-soft);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.item-info{flex:1;min-width:0}
.item-name{font-weight:700;font-size:0.84rem;color:var(--text-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.item-sub{font-size:0.73rem;color:var(--text-soft);margin-top:1px}
.status-pill{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;border-radius:99px;font-size:0.7rem;font-weight:700;white-space:nowrap;flex-shrink:0}
.s-pending{background:#FFFBEB;color:#92400E}
.s-under_review{background:#EFF6FF;color:#1E40AF}
.s-approved{background:#ECFDF5;color:#065F46}
.s-completed{background:var(--teal-soft);color:var(--teal-deep)}
.s-rejected{background:#FEF2F2;color:#991B1B}
.empty-list{padding:32px 20px;text-align:center;font-size:0.85rem;color:var(--text-soft)}

/* MOBILE */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:199}
@media(max-width:1000px){.two-col{grid-template-columns:1fr}.stats-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .content{padding:16px}.topbar{padding:0 16px}.welcome-right{display:none}
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
      <span class="agent-badge">Team</span>
    </div>
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($user_name,0,1)) ?></div>
      <div class="user-info"><strong><?= e($user_name) ?></strong><span>Team Member</span></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="<?= BASE_URL ?>/agent"              class="nav-item active"><span class="nav-icon">📊</span> Dashboard</a>
      <div class="nav-section-label">My Work</div>
      <a href="<?= BASE_URL ?>/agent/bookings"     class="nav-item">
        <span class="nav-icon">📋</span> Bookings
        <?php if($pending_bookings > 0): ?><span class="nav-badge"><?= $pending_bookings ?></span><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>/agent/applications" class="nav-item">
        <span class="nav-icon">💼</span> Applications
        <?php if($pending_apps > 0): ?><span class="nav-badge"><?= $pending_apps ?></span><?php endif; ?>
      </a>
      <div class="nav-section-label">Reports</div>
      <a href="<?= BASE_URL ?>/agent/reports"      class="nav-item"><span class="nav-icon">📈</span> My Reports</a>
      <div class="nav-section-label">View As</div>
      <a href="<?= BASE_URL ?>"                    class="nav-item"><span class="nav-icon">🌐</span> View Website</a>
    </nav>
    <div class="sidebar-footer">
      <a href="<?= BASE_URL ?>/logout" class="btn-logout"><span>🚪</span> Sign Out</a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="burger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <span class="topbar-title">Team Dashboard</span>
      </div>
      <div class="topbar-right">
        <span class="topbar-chip">🎯 Team Panel</span>
        <a href="<?= BASE_URL ?>/agent" class="topbar-notif" title="Notifications">
          🔔<?php if($notif_count > 0): ?><span class="notif-dot"></span><?php endif; ?>
        </a>
      </div>
    </header>

    <div class="content">
      <?php if($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>">
          <?= $flash['type']==='success' ? '✅' : '⚠️' ?> <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="welcome-banner">
        <div class="welcome-text">
          <h2>Welcome, <em><?= e(explode(' ',$user_name)[0]) ?></em> 👋</h2>
          <p>Your assigned tasks for today — <?= date('l, d F Y') ?></p>
        </div>
        <div class="welcome-right">
          <a href="<?= BASE_URL ?>/agent/bookings"     class="btn-welcome btn-amber">📋 Review Bookings</a>
          <a href="<?= BASE_URL ?>/agent/applications" class="btn-welcome btn-ghost">💼 Applications</a>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon" style="background:#EFF6FF">📋</div></div>
          <div class="stat-num"><?= $total_bookings ?></div>
          <div class="stat-label">Total Bookings</div>
          <div class="stat-sub <?= $pending_bookings > 0 ? 'sub-warn':'sub-up' ?>">
            <?= $pending_bookings > 0 ? $pending_bookings.' pending review':'All reviewed!' ?>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon" style="background:var(--success-bg)">✅</div></div>
          <div class="stat-num"><?= $approved_bookings ?></div>
          <div class="stat-label">Approved Bookings</div>
          <div class="stat-sub sub-up"><?= $completed_bookings ?> completed</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon" style="background:var(--amber-light)">💼</div></div>
          <div class="stat-num"><?= $total_apps ?></div>
          <div class="stat-label">Job Applications</div>
          <div class="stat-sub <?= $pending_apps > 0 ? 'sub-warn':'sub-up' ?>">
            <?= $pending_apps > 0 ? $pending_apps.' pending':'All reviewed!' ?>
          </div>
        </div>
      </div>

      <div class="two-col">
        <div class="card">
          <div class="card-header">
            <span class="card-title">Recent Bookings</span>
            <a href="<?= BASE_URL ?>/agent/bookings" class="card-link">View all →</a>
          </div>
          <div class="item-list">
            <?php if(empty($recent_bookings)): ?>
              <div class="empty-list">📋 No bookings assigned yet</div>
            <?php else: ?>
              <?php foreach($recent_bookings as $b):
                $sc_map = ['pending'=>'s-pending','under_review'=>'s-under_review','approved'=>'s-approved','completed'=>'s-completed','rejected'=>'s-rejected'];
                $sc = $sc_map[$b['status']] ?? 's-pending';
              ?>
                <div class="list-item">
                  <div class="item-icon"><?= $b['service_icon']?:'🏠' ?></div>
                  <div class="item-info">
                    <div class="item-name"><?= e($b['service_name']) ?> — <?= e($b['client_name']) ?></div>
                    <div class="item-sub">📍 <?= e($b['location']) ?> · <?= date('d M Y', strtotime($b['start_date'])) ?></div>
                  </div>
                  <span class="status-pill <?= $sc ?>"><?= ucfirst(str_replace('_',' ',$b['status'])) ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <span class="card-title">Recent Applications</span>
            <a href="<?= BASE_URL ?>/agent/applications" class="card-link">View all →</a>
          </div>
          <div class="item-list">
            <?php if(empty($recent_apps)): ?>
              <div class="empty-list">💼 No applications assigned yet</div>
            <?php else: ?>
              <?php foreach($recent_apps as $ap):
                $sc_map = ['pending'=>'s-pending','under_review'=>'s-under_review','approved'=>'s-approved','rejected'=>'s-rejected'];
                $sc = $sc_map[$ap['status']] ?? 's-pending';
              ?>
                <div class="list-item">
                  <div class="item-icon"><?= $ap['service_icon']?:'💼' ?></div>
                  <div class="item-info">
                    <div class="item-name"><?= e($ap['provider_name']) ?></div>
                    <div class="item-sub"><?= e($ap['service_name']) ?> · <?= e($ap['availability']?:'—') ?></div>
                  </div>
                  <span class="status-pill <?= $sc ?>"><?= ucfirst(str_replace('_',' ',$ap['status'])) ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
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