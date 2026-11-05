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

// ── SHELL ─────────────────────────────────────────────────────
$page_title   = 'Reports';
$page_heading = 'Reports';
$dash_role    = 'admin';
$dash_page    = 'reports';
$user_name    = $admin_name;
include '../includes/dash-head.php';
?>
</head>
<body>
<?php include '../includes/dash-sidebar.php'; ?>

<div class="page-head">
  <div>
    <h1>Platform Reports</h1>
    <p>Overview of all activity on Home Help — generated <?= date('d F Y, H:i') ?></p>
  </div>
  <span class="badge-soft"><i data-lucide="calendar"></i> <?= date('F Y') ?></span>
</div>

<!-- SUMMARY STATS -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-blue"><i data-lucide="users"></i></div></div>
    <div class="stat-num"><?= $total_users ?></div>
    <div class="stat-label">Total Platform Users</div>
    <div class="stat-sub"><?= $total_clients ?> clients · <?= $total_providers ?> job seekers · <?= $total_agents ?> team members</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon"><i data-lucide="calendar-check"></i></div></div>
    <div class="stat-num"><?= $total_bookings ?></div>
    <div class="stat-label">Total Service Bookings</div>
    <div class="stat-sub <?= $growth >= 0 ? 'up' : 'down' ?>">
      <?= $growth >= 0 ? '↑' : '↓' ?> <?= abs($growth) ?>% vs last month
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-amber"><i data-lucide="briefcase"></i></div></div>
    <div class="stat-num"><?= $total_apps ?></div>
    <div class="stat-label">Job Applications</div>
    <div class="stat-sub up"><?= $approved_apps ?> approved</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-green"><i data-lucide="award"></i></div></div>
    <div class="stat-num"><?= $completed_books ?></div>
    <div class="stat-label">Completed Services</div>
    <div class="stat-sub up">of <?= $total_bookings ?> total bookings</div>
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
<div class="two-col eq" style="margin-bottom:16px">

  <!-- MONTHLY BOOKINGS CHART -->
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
            $h = $max_monthly > 0 ? round(($m['total'] / $max_monthly) * 120) : 4;
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
          <span style="font-weight:700;color:<?= $growth >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
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
          $icon = serviceLucideIcon($bs['icon'] ?? '', $bs['name'] ?? '');
        ?>
          <div class="bar-row">
            <div class="bar-label"><i data-lucide="<?= e($icon) ?>"></i> <?= e($bs['name']) ?></div>
            <div class="bar-track">
              <div class="bar-fill" style="width:<?= $pct ?>%"></div>
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
  <div class="table-wrap">
    <table class="data-table">
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
          <tr><td colspan="6" class="empty-row"><i data-lucide="user-cog"></i> No team members yet.</td></tr>
        <?php else: ?>
          <?php foreach($top_agents as $i => $ag):
            $rank_class = ['rank-1','rank-2','rank-3'][$i] ?? 'rank-other';
            $perf = $ag['total'] > 0 ? round(($ag['completed'] / $ag['total']) * 100) : 0;
            $max_total = max(array_column($top_agents, 'total') ?: [1]);
          ?>
            <tr>
              <td><div class="rank <?= $rank_class ?>"><?= $i+1 ?></div></td>
              <td class="cell-name"><?= e($ag['name']) ?></td>
              <td class="num-strong text-navy"><?= $ag['total'] ?></td>
              <td class="num-strong text-success"><?= $ag['completed'] ?></td>
              <td class="num-strong text-warning"><?= $ag['pending'] ?></td>
              <td>
                <div class="perf">
                  <div class="perf-bar"><div class="perf-fill" style="width:<?= $perf ?>%"></div></div>
                  <span class="num-strong text-navy" style="font-size:.78rem"><?= $perf ?>%</span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../includes/dash-end.php'; ?>
