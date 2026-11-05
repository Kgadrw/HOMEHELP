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

// Map a service name to a Lucide icon
if (!function_exists('svc_icon')) {
    function svc_icon($name) {
        $n = strtolower((string)$name);
        if (strpos($n,'chef') !== false || strpos($n,'cook') !== false) return 'chef-hat';
        if (strpos($n,'clean') !== false)                               return 'sparkles';
        if (strpos($n,'house') !== false)                               return 'house';
        if (strpos($n,'laundry') !== false)                             return 'shirt';
        if (strpos($n,'garden') !== false)                              return 'flower-2';
        if (strpos($n,'nanny') !== false || strpos($n,'child') !== false) return 'baby';
        return 'briefcase';
    }
}

$sc_map = [
    'pending'      => ['sp-pending',  'clock'],
    'under_review' => ['sp-review',   'search'],
    'approved'     => ['sp-approved', 'circle-check'],
    'rejected'     => ['sp-rejected', 'circle-x'],
];

// ── SHELL VARS ────────────────────────────────────────────────
$page_title    = 'Provider Dashboard';
$dash_role     = 'provider';
$dash_page     = 'dashboard';
$page_heading  = 'My Dashboard';
$badge_pending = (int)$stats['pending'];
?>
<?php include '../includes/dash-head.php'; ?>
<style>
.stats-grid.cols-3{grid-template-columns:repeat(3,1fr)}
.quick-actions.cols-2{grid-template-columns:repeat(2,1fr)}
.welcome-banner{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px}
.welcome-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:10px;background:#fff;color:var(--navy);font-size:.84rem;font-weight:700}
.welcome-btn:hover{background:var(--navy-soft)}
.welcome-btn i{width:16px;height:16px}
.section-title{font-size:1rem;font-weight:800;color:var(--navy);letter-spacing:-.01em;margin-bottom:12px}
.latest-card{margin-bottom:20px}
.latest-header{display:flex;align-items:center;gap:12px;padding:16px 18px;border-bottom:1px solid var(--line)}
.latest-icon{width:42px;height:42px;border-radius:10px;background:var(--navy-soft);color:var(--navy);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.latest-icon i{width:20px;height:20px}
.latest-service{font-weight:700;font-size:.95rem;color:var(--navy)}
.latest-date{font-size:.76rem;color:var(--muted);margin-top:2px}
.latest-header .status-pill{margin-left:auto}
.status-pill i{width:13px;height:13px}
.latest-body{padding:16px 18px;display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.dl{font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--soft)}
.dv{font-size:.88rem;font-weight:600;color:var(--text);margin-top:3px}
@media(max-width:768px){
  .stats-grid.cols-3,.quick-actions.cols-2{grid-template-columns:1fr}
  .latest-body{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>
<?php include '../includes/dash-sidebar.php'; ?>

<?php if($flash): ?>
  <div class="flash flash-<?= ($flash['type'] ?? 'success') === 'error' ? 'error' : 'success' ?>">
    <i data-lucide="<?= ($flash['type'] ?? 'success') === 'error' ? 'triangle-alert' : 'circle-check' ?>"></i>
    <span><?= e($flash['message']) ?></span>
  </div>
<?php endif; ?>

<!-- WELCOME -->
<div class="welcome-banner">
  <div>
    <h2>Welcome back, <?= e(explode(' ',$user_name)[0]) ?></h2>
    <p>Ready to find your next opportunity? — <?= date('l, d F Y') ?></p>
  </div>
  <a href="<?= BASE_URL ?>/apply" class="welcome-btn"><i data-lucide="file-plus"></i> Apply for a Job</a>
</div>

<!-- STATS -->
<div class="stats-grid cols-3">
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon"><i data-lucide="folder"></i></div></div>
    <div class="stat-num"><?= $stats['total'] ?></div>
    <div class="stat-label">Total Applications</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon"><i data-lucide="circle-check"></i></div></div>
    <div class="stat-num"><?= $stats['approved'] ?></div>
    <div class="stat-label">Approved</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon"><i data-lucide="clock"></i></div></div>
    <div class="stat-num"><?= $stats['pending'] ?></div>
    <div class="stat-label">Pending Review</div>
  </div>
</div>

<!-- LATEST APPLICATION -->
<?php if($latest_app): ?>
  <div class="section-title">Latest Application</div>
  <?php $sc = $sc_map[$latest_app['status']] ?? $sc_map['pending']; ?>
  <div class="card latest-card">
    <div class="latest-header">
      <div class="latest-icon"><i data-lucide="<?= svc_icon($latest_app['service_name']) ?>"></i></div>
      <div>
        <div class="latest-service"><?= e($latest_app['service_name']) ?></div>
        <div class="latest-date">Submitted <?= date('d M Y', strtotime($latest_app['created_at'])) ?></div>
      </div>
      <span class="status-pill <?= $sc[0] ?>"><i data-lucide="<?= $sc[1] ?>"></i> <?= ucfirst(str_replace('_',' ',$latest_app['status'])) ?></span>
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
<div class="quick-actions cols-2">
  <a href="<?= BASE_URL ?>/apply" class="quick-btn">
    <div class="quick-btn-icon"><i data-lucide="file-plus"></i></div>
    <div>
      <div class="quick-btn-label">Apply for a Job</div>
      <div class="latest-date">Submit a new job application</div>
    </div>
  </a>
  <a href="<?= BASE_URL ?>/my-applications" class="quick-btn">
    <div class="quick-btn-icon"><i data-lucide="folder"></i></div>
    <div>
      <div class="quick-btn-label">My Applications</div>
      <div class="latest-date">Track all your applications</div>
    </div>
  </a>
</div>

<?php include '../includes/dash-end.php'; ?>
