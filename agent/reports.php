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

// ── SHELL ─────────────────────────────────────────────────────
$page_title   = 'My Reports';
$page_heading = 'My Reports';
$dash_role    = 'agent';
$dash_page    = 'reports';
include '../includes/dash-head.php';
?>
</head>
<body>
<?php include '../includes/dash-sidebar.php'; ?>

<div class="page-head">
  <div>
    <h1>My Reports</h1>
    <p>Your personal performance overview — generated <?= date('d F Y, H:i') ?></p>
  </div>
  <span class="badge-soft"><i data-lucide="calendar"></i> <?= date('F Y') ?></span>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-blue"><i data-lucide="calendar-check"></i></div></div>
    <div class="stat-num"><?= $total_bookings ?></div><div class="stat-label">Total Bookings</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-green"><i data-lucide="check-circle-2"></i></div></div>
    <div class="stat-num"><?= $approved_books ?></div><div class="stat-label">Approved</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon"><i data-lucide="award"></i></div></div>
    <div class="stat-num"><?= $completed_books ?></div><div class="stat-label">Completed</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-amber"><i data-lucide="briefcase"></i></div></div>
    <div class="stat-num"><?= $total_apps ?></div><div class="stat-label">Applications</div>
  </div>
</div>

<div class="rates-row">
  <div class="rate-card">
    <?php $approval_rate = $total_bookings > 0 ? round(($approved_books/$total_bookings)*100) : 0; ?>
    <div class="rate-pct rate-navy"><?= $approval_rate ?>%</div>
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

<div class="two-col eq">
  <div class="card">
    <div class="card-header">
      <span class="card-title">Monthly Bookings (last 6 months)</span>
    </div>
    <div class="card-body">
      <?php if(empty($monthly)): ?>
        <div class="empty-list"><i data-lucide="bar-chart-3"></i> No booking data yet.</div>
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
              <div class="month-label"><?= e($m['month']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="chart-foot">
          <span>This month: <strong><?= $this_month ?></strong></span>
          <span>Last month: <strong><?= $last_month ?></strong></span>
          <span style="font-weight:700;color:<?= $growth>=0?'var(--success)':'var(--danger)' ?>">
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
        <div class="empty-list"><i data-lucide="layers"></i> No data yet.</div>
      <?php else: ?>
        <div class="bar-chart">
          <?php foreach($by_service as $bs):
            $pct = $max_svc > 0 ? round(($bs['total']/$max_svc)*100) : 0;
          ?>
            <div class="bar-row">
              <div class="bar-label"><?php if($bs['icon']): ?><?= e($bs['icon']) ?> <?php else: ?><i data-lucide="home"></i> <?php endif; ?><?= e($bs['name']) ?></div>
              <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
              <div class="bar-num"><?= $bs['total'] ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include '../includes/dash-end.php'; ?>
