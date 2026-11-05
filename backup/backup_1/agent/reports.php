<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('agent');

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Stats
$total_bookings   = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id=?"); $total_bookings->bind_param('i',$user_id); $total_bookings->execute(); $total_bookings=$total_bookings->get_result()->fetch_row()[0];
$completed_books  = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id=? AND status='completed'"); $completed_books->bind_param('i',$user_id); $completed_books->execute(); $completed_books=$completed_books->get_result()->fetch_row()[0];
$approved_books   = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id=? AND status='approved'"); $approved_books->bind_param('i',$user_id); $approved_books->execute(); $approved_books=$approved_books->get_result()->fetch_row()[0];
$rejected_books   = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id=? AND status='rejected'"); $rejected_books->bind_param('i',$user_id); $rejected_books->execute(); $rejected_books=$rejected_books->get_result()->fetch_row()[0];
$total_apps       = $conn->prepare("SELECT COUNT(*) FROM job_applications WHERE agent_id=?"); $total_apps->bind_param('i',$user_id); $total_apps->execute(); $total_apps=$total_apps->get_result()->fetch_row()[0];
$approved_apps    = $conn->prepare("SELECT COUNT(*) FROM job_applications WHERE agent_id=? AND status='approved'"); $approved_apps->bind_param('i',$user_id); $approved_apps->execute(); $approved_apps=$approved_apps->get_result()->fetch_row()[0];

// Monthly bookings
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month, DATE_FORMAT(created_at,'%Y-%m') AS month_key,
           COUNT(*) AS total, SUM(status='completed') AS completed, SUM(status='approved') AS approved
    FROM bookings WHERE agent_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_key, month ORDER BY month_key ASC
");
$stmt->bind_param('i',$user_id); $stmt->execute();
$monthly = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$max_monthly = max(array_column($monthly,'total') ?: [1]);

// Bookings by service
$stmt = $conn->prepare("
    SELECT s.name, s.icon, COUNT(b.id) AS total
    FROM bookings b JOIN services s ON b.service_id=s.id
    WHERE b.agent_id=? GROUP BY s.id,s.name,s.icon ORDER BY total DESC
");
$stmt->bind_param('i',$user_id); $stmt->execute();
$by_service = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$max_svc = max(array_column($by_service,'total') ?: [1]);

$this_month = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id=? AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
$this_month->bind_param('i',$user_id); $this_month->execute(); $this_month=$this_month->get_result()->fetch_row()[0];
$last_month = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id=? AND MONTH(created_at)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND YEAR(created_at)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))");
$last_month->bind_param('i',$user_id); $last_month->execute(); $last_month=$last_month->get_result()->fetch_row()[0];
$growth = $last_month > 0 ? round((($this_month-$last_month)/$last_month)*100) : 0;

$notif_count = unreadNotifCount();
$flash = flashGet();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>My Reports | Team — Home Help</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --teal-deep:#0F2D3D;--teal-mid:#164E63;--teal-light:#0891B2;--teal-soft:#CFFAFE;--teal-sidebar:#0A2233;
  --amber:#E8A020;--amber-light:#FFF0CC;--cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --danger:#EF4444;--danger-bg:#FEF2F2;--success:#10B981;--success-bg:#ECFDF5;
  --warning:#F59E0B;--info:#3B82F6;
  --shadow-sm:0 2px 12px rgba(15,45,61,0.08);--shadow-md:0 6px 28px rgba(15,45,61,0.13);
  --radius:14px;--radius-sm:8px;--sidebar:260px;
  --font-display:'Cormorant Garamond',Georgia,serif;--font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden}
a{text-decoration:none;color:inherit}
.dash-wrap{display:flex;min-height:100vh}
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
.sidebar-footer{padding:14px 20px;border-top:1px solid rgba(255,255,255,0.07)}
.btn-logout{display:flex;align-items:center;gap:8px;width:100%;padding:9px 14px;border-radius:var(--radius-sm);background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.18);color:rgba(255,100,100,0.85);font-size:0.83rem;font-weight:600;cursor:pointer;transition:all 0.2s;text-decoration:none;font-family:var(--font-body)}
.btn-logout:hover{background:rgba(239,68,68,0.2)}
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--white);border-bottom:1px solid var(--cream-dark);padding:0 28px;height:62px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar-left{display:flex;align-items:center;gap:14px}
.burger{display:none;background:none;border:none;cursor:pointer;padding:6px;flex-direction:column;gap:4px}
.burger span{display:block;width:20px;height:2px;background:var(--text-dark);border-radius:2px}
.topbar-title{font-family:var(--font-display);font-size:1.35rem;font-weight:700;color:var(--teal-deep)}
.topbar-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:99px;background:var(--teal-soft);color:var(--teal-deep);font-size:0.78rem;font-weight:700}
.content{padding:24px 28px;flex:1}
.page-header{margin-bottom:22px}
.page-header h1{font-family:var(--font-display);font-size:1.9rem;font-weight:700;color:var(--teal-deep)}
.page-header h1 em{font-style:italic;color:var(--amber)}
.page-header p{font-size:0.86rem;color:var(--text-soft);margin-top:4px}
/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
.stat-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:18px 20px;transition:all 0.3s}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm);border-color:var(--teal-soft)}
.stat-icon{font-size:1.6rem;margin-bottom:10px}
.stat-num{font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--teal-deep);line-height:1}
.stat-label{font-size:0.78rem;color:var(--text-soft);margin-top:4px;font-weight:500}
/* RATES */
.rates-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}
.rate-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:18px 20px;text-align:center}
.rate-pct{font-family:var(--font-display);font-size:2.2rem;font-weight:700;line-height:1}
.rate-label{font-size:0.78rem;color:var(--text-soft);margin-top:4px}
.rate-teal{color:var(--teal-light)}.rate-green{color:var(--success)}.rate-amber{color:var(--amber)}
/* TWO COL */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}
.card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden}
.card-header{padding:16px 20px;border-bottom:1px solid var(--cream-dark);display:flex;align-items:center;justify-content:space-between}
.card-title{font-family:var(--font-display);font-size:1.05rem;font-weight:700;color:var(--teal-deep)}
.card-body{padding:20px}
/* CHART */
.monthly-chart{display:flex;gap:8px;align-items:flex-end;height:120px;padding:0 4px}
.month-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px}
.month-bar-wrap{flex:1;display:flex;align-items:flex-end;width:100%}
.month-bar{width:100%;border-radius:4px 4px 0 0;min-height:3px;background:linear-gradient(180deg,var(--teal-light),var(--teal-mid))}
.month-label{font-size:0.68rem;color:var(--text-soft);text-align:center;white-space:nowrap}
.month-count{font-size:0.72rem;font-weight:700;color:var(--teal-deep)}
/* BAR */
.bar-chart{display:flex;flex-direction:column;gap:14px}
.bar-row{display:flex;align-items:center;gap:10px}
.bar-label{width:80px;font-size:0.78rem;font-weight:600;color:var(--text-mid);text-align:right;flex-shrink:0}
.bar-track{flex:1;background:var(--cream-dark);border-radius:99px;height:10px;overflow:hidden}
.bar-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--teal-light),var(--teal-mid))}
.bar-num{font-size:0.78rem;font-weight:700;color:var(--text-mid);min-width:22px;text-align:right}
/* MOBILE */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:199}
@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .content{padding:16px}.topbar{padding:0 16px}
  .two-col{grid-template-columns:1fr}.rates-row{grid-template-columns:1fr 1fr}
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
      <a href="<?= BASE_URL ?>/agent"              class="nav-item"><span class="nav-icon">📊</span> Dashboard</a>
      <div class="nav-section-label">My Work</div>
      <a href="<?= BASE_URL ?>/agent/bookings"     class="nav-item"><span class="nav-icon">📋</span> Bookings</a>
      <a href="<?= BASE_URL ?>/agent/applications" class="nav-item"><span class="nav-icon">💼</span> Applications</a>
      <div class="nav-section-label">Reports</div>
      <a href="<?= BASE_URL ?>/agent/reports"      class="nav-item active"><span class="nav-icon">📈</span> My Reports</a>
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
        <span class="topbar-title">My Reports</span>
      </div>
      <div class="topbar-right">
        <span class="topbar-chip">📅 <?= date('F Y') ?></span>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <h1>My <em>Reports</em></h1>
        <p>Your personal performance overview — generated <?= date('d F Y, H:i') ?></p>
      </div>

      <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon">📋</div><div class="stat-num"><?= $total_bookings ?></div><div class="stat-label">Total Bookings</div></div>
        <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-num"><?= $approved_books ?></div><div class="stat-label">Approved</div></div>
        <div class="stat-card"><div class="stat-icon">🏆</div><div class="stat-num"><?= $completed_books ?></div><div class="stat-label">Completed</div></div>
        <div class="stat-card"><div class="stat-icon">💼</div><div class="stat-num"><?= $total_apps ?></div><div class="stat-label">Applications</div></div>
      </div>

      <div class="rates-row">
        <div class="rate-card">
          <?php $approval_rate = $total_bookings > 0 ? round(($approved_books/$total_bookings)*100) : 0; ?>
          <div class="rate-pct rate-teal"><?= $approval_rate ?>%</div>
          <div class="rate-label">Booking Approval Rate</div>
        </div>
        <div class="rate-card">
          <?php $completion_rate = $approved_books > 0 ? round(($completed_books/$approved_books)*100) : 0; ?>
          <div class="rate-pct rate-green"><?= $completion_rate ?>%</div>
          <div class="rate-label">Completion Rate</div>
        </div>
        <div class="rate-card">
          <?php $app_rate = $total_apps > 0 ? round(($approved_apps/$total_apps)*100) : 0; ?>
          <div class="rate-pct rate-amber"><?= $app_rate ?>%</div>
          <div class="rate-label">Application Approval Rate</div>
        </div>
      </div>

      <div class="two-col">
        <div class="card">
          <div class="card-header">
            <span class="card-title">Monthly Bookings (last 6 months)</span>
          </div>
          <div class="card-body">
            <?php if(empty($monthly)): ?>
              <p style="color:var(--text-soft);font-size:0.86rem;text-align:center;padding:20px">No booking data yet.</p>
            <?php else: ?>
              <div class="monthly-chart">
                <?php foreach($monthly as $m):
                  $h = $max_monthly > 0 ? round(($m['total']/$max_monthly)*100) : 4;
                ?>
                  <div class="month-col">
                    <div class="month-count"><?= $m['total'] ?></div>
                    <div class="month-bar-wrap">
                      <div class="month-bar" style="height:<?= max($h,4) ?>px"></div>
                    </div>
                    <div class="month-label"><?= $m['month'] ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div style="display:flex;gap:16px;margin-top:14px;justify-content:center">
                <span style="font-size:0.75rem;color:var(--text-soft)">This month: <strong style="color:var(--teal-deep)"><?= $this_month ?></strong></span>
                <span style="font-size:0.75rem;color:var(--text-soft)">Last month: <strong style="color:var(--text-mid)"><?= $last_month ?></strong></span>
                <span style="font-size:0.75rem;font-weight:700;color:<?= $growth>=0?'var(--success)':'var(--danger)' ?>">
                  <?= $growth>=0?'↑':'↓' ?> <?= abs($growth) ?>%
                </span>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <span class="card-title">Bookings by Service</span>
          </div>
          <div class="card-body">
            <?php if(empty($by_service)): ?>
              <p style="color:var(--text-soft);font-size:0.86rem;text-align:center;padding:20px">No data yet.</p>
            <?php else: ?>
              <div class="bar-chart">
                <?php foreach($by_service as $bs):
                  $pct = $max_svc > 0 ? round(($bs['total']/$max_svc)*100) : 0;
                ?>
                  <div class="bar-row">
                    <div class="bar-label"><?= $bs['icon'] ?> <?= e($bs['name']) ?></div>
                    <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
                    <div class="bar-num"><?= $bs['total'] ?></div>
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
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');document.body.style.overflow=document.getElementById('sidebar').classList.contains('open')?'hidden':'';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
window.addEventListener('resize',function(){if(window.innerWidth>768)closeSidebar();});
</script>
</body>
</html>