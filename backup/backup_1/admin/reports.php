<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('admin');

$admin_name = $_SESSION['user_name'];

// ── SUMMARY STATS ─────────────────────────────────────────────
$total_users     = $conn->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetch_row()[0];
$total_clients   = $conn->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetch_row()[0];
$total_providers = $conn->query("SELECT COUNT(*) FROM users WHERE role='provider'")->fetch_row()[0];
$total_agents    = $conn->query("SELECT COUNT(*) FROM users WHERE role='agent'")->fetch_row()[0];
$total_bookings  = $conn->query("SELECT COUNT(*) FROM bookings")->fetch_row()[0];
$total_apps      = $conn->query("SELECT COUNT(*) FROM job_applications")->fetch_row()[0];
$approved_books  = $conn->query("SELECT COUNT(*) FROM bookings WHERE status='approved'")->fetch_row()[0];
$completed_books = $conn->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetch_row()[0];
$approved_apps   = $conn->query("SELECT COUNT(*) FROM job_applications WHERE status='approved'")->fetch_row()[0];

// ── MONTHLY BOOKINGS (last 6 months) ──────────────────────────
$monthly = $conn->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month,
           DATE_FORMAT(created_at,'%Y-%m') AS month_key,
           COUNT(*) AS total,
           SUM(status='approved') AS approved,
           SUM(status='completed') AS completed,
           SUM(status='rejected') AS rejected
    FROM bookings
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_key, month
    ORDER BY month_key ASC
")->fetch_all(MYSQLI_ASSOC);

// ── BOOKINGS BY SERVICE ────────────────────────────────────────
$by_service = $conn->query("
    SELECT s.name, s.icon, COUNT(b.id) AS total,
           SUM(b.status='completed') AS completed,
           SUM(b.status='approved')  AS approved
    FROM services s
    LEFT JOIN bookings b ON b.service_id = s.id
    GROUP BY s.id, s.name, s.icon
    ORDER BY total DESC
")->fetch_all(MYSQLI_ASSOC);

// ── TOP TEAM MEMBERS ──────────────────────────────────────────
$top_agents = $conn->query("
    SELECT u.name,
           COUNT(b.id) AS total,
           SUM(b.status='completed') AS completed,
           SUM(b.status='approved')  AS approved,
           SUM(b.status='pending')   AS pending
    FROM users u
    LEFT JOIN bookings b ON b.agent_id = u.id
    WHERE u.role = 'agent'
    GROUP BY u.id, u.name
    ORDER BY total DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// ── RECENT MONTH COMPARISON ───────────────────────────────────
$this_month = $conn->query("SELECT COUNT(*) FROM bookings WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetch_row()[0];
$last_month = $conn->query("SELECT COUNT(*) FROM bookings WHERE MONTH(created_at)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND YEAR(created_at)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))")->fetch_row()[0];
$growth = $last_month > 0 ? round((($this_month - $last_month) / $last_month) * 100) : 0;

$max_monthly = max(array_column($monthly, 'total') ?: [1]);
$max_service = max(array_column($by_service, 'total') ?: [1]);

$flash = flashGet();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Reports | Admin — Home Help</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --admin-deep:#13103A;--admin-mid:#2D2A6E;--admin-light:#4F46E5;--admin-soft:#E0E7FF;
  --amber:#E8A020;--amber-light:#FFF0CC;--cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --danger:#EF4444;--danger-bg:#FEF2F2;--success:#10B981;--success-bg:#ECFDF5;
  --warning:#F59E0B;--info:#3B82F6;
  --shadow-sm:0 2px 12px rgba(19,16,58,0.07);--shadow-md:0 6px 28px rgba(19,16,58,0.11);
  --radius:14px;--radius-sm:8px;--admin-sidebar:#0E0C2E;--sidebar:260px;
  --font-display:'Cormorant Garamond',Georgia,serif;--font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden}
a{text-decoration:none;color:inherit}
.dash-wrap{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar);flex-shrink:0;background:var(--admin-sidebar);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;transition:transform 0.3s ease;overflow-y:auto}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:22px 20px 18px;border-bottom:1px solid rgba(255,255,255,0.07)}
.sidebar-logo img{width:34px;height:34px;object-fit:contain}
.sidebar-logo-text{font-family:var(--font-display);font-size:1.25rem;font-weight:700;color:var(--white)}
.admin-badge{display:inline-block;padding:2px 8px;border-radius:99px;background:rgba(232,160,32,0.2);border:1px solid rgba(232,160,32,0.35);color:var(--amber);font-size:0.62rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;margin-left:auto;flex-shrink:0}
.sidebar-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;gap:12px}
.user-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--amber),#c47d0a);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--white);flex-shrink:0}
.user-info strong{display:block;font-size:0.86rem;font-weight:700;color:var(--white)}
.user-info span{font-size:0.71rem;color:var(--amber);font-weight:600}
.sidebar-nav{padding:14px 0;flex:1}
.nav-section-label{font-size:0.64rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.25);padding:12px 20px 5px}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 20px;font-size:0.86rem;font-weight:500;color:rgba(255,255,255,0.6);cursor:pointer;transition:all 0.2s;position:relative;text-decoration:none}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,0.05)}
.nav-item.active{color:var(--white);background:rgba(79,70,229,0.18);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--amber);border-radius:0 3px 3px 0}
.nav-icon{font-size:1rem;width:20px;text-align:center;flex-shrink:0}
.sidebar-footer{padding:14px 20px;border-top:1px solid rgba(255,255,255,0.07)}
.btn-logout{display:flex;align-items:center;gap:8px;width:100%;padding:9px 14px;border-radius:var(--radius-sm);background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.18);color:rgba(255,100,100,0.85);font-size:0.83rem;font-weight:600;cursor:pointer;transition:all 0.2s;text-decoration:none;font-family:var(--font-body)}
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--white);border-bottom:1px solid var(--cream-dark);padding:0 28px;height:62px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar-left{display:flex;align-items:center;gap:14px}
.burger{display:none;background:none;border:none;cursor:pointer;padding:6px;flex-direction:column;gap:4px}
.burger span{display:block;width:20px;height:2px;background:var(--text-dark);border-radius:2px}
.topbar-title{font-family:var(--font-display);font-size:1.35rem;font-weight:700;color:var(--admin-deep)}
.topbar-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:99px;background:var(--amber-light);color:#92400E;font-size:0.78rem;font-weight:700}
.content{padding:24px 28px;flex:1}
.page-header{margin-bottom:22px}
.page-header h1{font-family:var(--font-display);font-size:1.9rem;font-weight:700;color:var(--admin-deep)}
.page-header h1 em{font-style:italic;color:var(--admin-light)}
.page-header p{font-size:0.86rem;color:var(--text-soft);margin-top:4px}

/* SUMMARY GRID */
.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
.sum-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:18px 20px}
.sum-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm)}
.sum-icon{font-size:1.6rem;margin-bottom:10px}
.sum-num{font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--admin-deep);line-height:1}
.sum-label{font-size:0.78rem;color:var(--text-soft);margin-top:4px;font-weight:500}
.sum-sub{font-size:0.73rem;font-weight:600;margin-top:5px}
.sub-up{color:var(--success)}.sub-down{color:var(--danger)}.sub-neutral{color:var(--text-soft)}

/* TWO COL */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}
.card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden}
.card-header{padding:16px 20px;border-bottom:1px solid var(--cream-dark);display:flex;align-items:center;justify-content:space-between}
.card-title{font-family:var(--font-display);font-size:1.05rem;font-weight:700;color:var(--admin-deep)}
.card-body{padding:20px}

/* BAR CHART */
.bar-chart{display:flex;flex-direction:column;gap:14px}
.bar-row{display:flex;align-items:center;gap:10px}
.bar-label{width:60px;font-size:0.78rem;font-weight:600;color:var(--text-mid);text-align:right;flex-shrink:0}
.bar-track{flex:1;background:var(--cream-dark);border-radius:99px;height:10px;overflow:hidden;position:relative}
.bar-fill{height:100%;border-radius:99px;transition:width 0.8s ease}
.bar-fill.green{background:linear-gradient(90deg,var(--admin-light),var(--admin-mid))}
.bar-fill.amber{background:linear-gradient(90deg,var(--amber),#c47d0a)}
.bar-fill.blue{background:linear-gradient(90deg,#60A5FA,#2563EB)}
.bar-num{font-size:0.78rem;font-weight:700;color:var(--text-mid);min-width:22px;text-align:right}

/* MONTHLY CHART */
.monthly-chart{display:flex;gap:8px;align-items:flex-end;height:140px;padding:0 4px}
.month-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px}
.month-bar-wrap{flex:1;display:flex;align-items:flex-end;width:100%}
.month-bar{width:100%;border-radius:4px 4px 0 0;transition:height 0.8s ease;min-height:3px;background:linear-gradient(180deg,var(--admin-light),var(--admin-mid))}
.month-label{font-size:0.68rem;color:var(--text-soft);font-weight:500;text-align:center;white-space:nowrap}
.month-count{font-size:0.72rem;font-weight:700;color:var(--admin-deep)}

/* TEAM TABLE */
.agents-table{width:100%;border-collapse:collapse}
.agents-table th{font-size:0.69rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:var(--text-soft);padding:10px 14px;text-align:left;background:var(--cream);border-bottom:1px solid var(--cream-dark)}
.agents-table td{padding:12px 14px;font-size:0.83rem;border-bottom:1px solid var(--cream-dark);vertical-align:middle}
.agents-table tr:last-child td{border-bottom:none}
.agent-rank{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.78rem;font-weight:700}
.rank-1{background:var(--amber);color:var(--white)}
.rank-2{background:#94A3B8;color:var(--white)}
.rank-3{background:#B87333;color:var(--white)}
.rank-other{background:var(--cream-dark);color:var(--text-soft)}
.perf-bar{height:6px;border-radius:99px;background:var(--cream-dark);overflow:hidden;width:80px}
.perf-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--admin-light),var(--admin-mid))}

/* RATE CARDS */
.rates-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px}
.rate-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:18px 20px;text-align:center}
.rate-pct{font-family:var(--font-display);font-size:2.2rem;font-weight:700;line-height:1}
.rate-label{font-size:0.78rem;color:var(--text-soft);margin-top:4px}
.rate-green{color:var(--success)}
.rate-amber{color:var(--amber)}
.rate-blue{color:var(--info)}

/* MOBILE */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:199}
@media(max-width:1100px){.summary-grid{grid-template-columns:repeat(2,1fr)}}
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
      <span class="admin-badge">Admin</span>
    </div>
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($admin_name,0,1)) ?></div>
      <div class="user-info"><strong><?= e($admin_name) ?></strong><span>Super Admin</span></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="<?= BASE_URL ?>/admin"              class="nav-item"><span class="nav-icon">📊</span> Dashboard</a>
      <div class="nav-section-label">Manage</div>
      <a href="<?= BASE_URL ?>/admin/bookings"     class="nav-item"><span class="nav-icon">📋</span> Bookings</a>
      <a href="<?= BASE_URL ?>/admin/applications" class="nav-item"><span class="nav-icon">💼</span> Job Applications</a>
      <a href="<?= BASE_URL ?>/admin/users"        class="nav-item"><span class="nav-icon">👥</span> Users</a>
      <a href="<?= BASE_URL ?>/admin/services"     class="nav-item"><span class="nav-icon">🛎️</span> Services</a>
      <a href="<?= BASE_URL ?>/admin/agents"       class="nav-item"><span class="nav-icon">🎯</span> Team</a>
      <div class="nav-section-label">Reports</div>
      <a href="<?= BASE_URL ?>/admin/reports"      class="nav-item active"><span class="nav-icon">📈</span> Reports</a>
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
        <span class="topbar-title">Reports</span>
      </div>
      <div class="topbar-right">
        <span class="topbar-chip">📅 <?= date('F Y') ?></span>
      </div>
    </header>

    <div class="content">

      <div class="page-header">
        <h1>Platform <em>Reports</em></h1>
        <p>Overview of all activity on Home Help — generated <?= date('d F Y, H:i') ?></p>
      </div>

      <!-- SUMMARY STATS -->
      <div class="summary-grid">
        <div class="sum-card">
          <div class="sum-icon">👥</div>
          <div class="sum-num"><?= $total_users ?></div>
          <div class="sum-label">Total Platform Users</div>
          <div class="sum-sub sub-neutral"><?= $total_clients ?> clients · <?= $total_providers ?> job seekers · <?= $total_agents ?> team members</div>
        </div>
        <div class="sum-card">
          <div class="sum-icon">📋</div>
          <div class="sum-num"><?= $total_bookings ?></div>
          <div class="sum-label">Total Service Bookings</div>
          <div class="sum-sub <?= $growth >= 0 ? 'sub-up' : 'sub-down' ?>">
            <?= $growth >= 0 ? '↑' : '↓' ?> <?= abs($growth) ?>% vs last month
          </div>
        </div>
        <div class="sum-card">
          <div class="sum-icon">💼</div>
          <div class="sum-num"><?= $total_apps ?></div>
          <div class="sum-label">Job Applications</div>
          <div class="sum-sub sub-up">✅ <?= $approved_apps ?> approved</div>
        </div>
        <div class="sum-card">
          <div class="sum-icon">🏆</div>
          <div class="sum-num"><?= $completed_books ?></div>
          <div class="sum-label">Completed Services</div>
          <div class="sum-sub sub-up">of <?= $total_bookings ?> total bookings</div>
        </div>
      </div>

      <!-- RATE CARDS -->
      <div class="rates-row">
        <div class="rate-card">
          <?php $approval_rate = $total_bookings > 0 ? round(($approved_books / $total_bookings) * 100) : 0; ?>
          <div class="rate-pct rate-green"><?= $approval_rate ?>%</div>
          <div class="rate-label">Booking Approval Rate</div>
        </div>
        <div class="rate-card">
          <?php $completion_rate = $approved_books > 0 ? round(($completed_books / $approved_books) * 100) : 0; ?>
          <div class="rate-pct rate-amber"><?= $completion_rate ?>%</div>
          <div class="rate-label">Service Completion Rate</div>
        </div>
        <div class="rate-card">
          <?php $app_rate = $total_apps > 0 ? round(($approved_apps / $total_apps) * 100) : 0; ?>
          <div class="rate-pct rate-blue"><?= $app_rate ?>%</div>
          <div class="rate-label">Application Approval Rate</div>
        </div>
      </div>

      <!-- MONTHLY CHART + SERVICE BREAKDOWN -->
      <div class="two-col">

        <!-- MONTHLY BOOKINGS CHART -->
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
                  $h = $max_monthly > 0 ? round(($m['total'] / $max_monthly) * 120) : 4;
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
              <div style="display:flex;gap:16px;margin-top:16px;justify-content:center">
                <span style="font-size:0.75rem;color:var(--text-soft)">
                  This month: <strong style="color:var(--admin-deep)"><?= $this_month ?></strong>
                </span>
                <span style="font-size:0.75rem;color:var(--text-soft)">
                  Last month: <strong style="color:var(--text-mid)"><?= $last_month ?></strong>
                </span>
                <span style="font-size:0.75rem;font-weight:700;color:<?= $growth >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                  <?= $growth >= 0 ? '↑' : '↓' ?> <?= abs($growth) ?>%
                </span>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- BOOKINGS BY SERVICE -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Bookings by Service</span>
          </div>
          <div class="card-body">
            <div class="bar-chart">
              <?php foreach($by_service as $bs):
                $pct = $max_service > 0 ? round(($bs['total'] / $max_service) * 100) : 0;
              ?>
                <div class="bar-row">
                  <div class="bar-label"><?= $bs['icon'] ?> <?= e($bs['name']) ?></div>
                  <div class="bar-track">
                    <div class="bar-fill green" style="width:<?= $pct ?>%"></div>
                  </div>
                  <div class="bar-num"><?= $bs['total'] ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

      </div>

      <!-- TOP TEAM MEMBERS -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Team Performance</span>
        </div>
        <div style="overflow-x:auto">
          <table class="agents-table">
            <thead>
              <tr>
                <th>Rank</th>
                <th>Team Member</th>
                <th>Total Assigned</th>
                <th>Completed</th>
                <th>Pending</th>
                <th>Performance</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($top_agents)): ?>
                <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-soft)">No team members yet.</td></tr>
              <?php else: ?>
                <?php foreach($top_agents as $i => $ag):
                  $rank_class = ['rank-1','rank-2','rank-3'][$i] ?? 'rank-other';
                  $perf = $ag['total'] > 0 ? round(($ag['completed'] / $ag['total']) * 100) : 0;
                  $max_total = max(array_column($top_agents, 'total') ?: [1]);
                ?>
                  <tr>
                    <td><div class="agent-rank <?= $rank_class ?>"><?= $i+1 ?></div></td>
                    <td style="font-weight:700;font-size:0.86rem"><?= e($ag['name']) ?></td>
                    <td style="font-weight:700;color:var(--admin-deep)"><?= $ag['total'] ?></td>
                    <td style="font-weight:700;color:var(--success)"><?= $ag['completed'] ?></td>
                    <td style="font-weight:700;color:var(--warning)"><?= $ag['pending'] ?></td>
                    <td>
                      <div style="display:flex;align-items:center;gap:8px">
                        <div class="perf-bar">
                          <div class="perf-fill" style="width:<?= $perf ?>%"></div>
                        </div>
                        <span style="font-size:0.78rem;font-weight:700;color:var(--admin-deep)"><?= $perf ?>%</span>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
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