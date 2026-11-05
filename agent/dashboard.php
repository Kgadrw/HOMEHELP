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

// ── SHELL ─────────────────────────────────────────────────────
$page_title    = 'Team Dashboard';
$page_heading  = 'Team Dashboard';
$dash_role     = 'agent';
$dash_page     = 'dashboard';
$badge_bookings = $pending_bookings;
$badge_apps     = $pending_apps;
include '../includes/dash-head.php';
?>
</head>
<body>
<?php include '../includes/dash-sidebar.php'; ?>

<?php if($flash): ?>
  <div class="flash flash-<?= e($flash['type']) ?>">
    <i data-lucide="<?= $flash['type']==='success' ? 'check-circle-2' : 'alert-triangle' ?>"></i>
    <span><?= e($flash['message']) ?></span>
  </div>
<?php endif; ?>

<div class="welcome-banner has-actions">
  <div>
    <h2>Welcome, <?= e(explode(' ',$user_name)[0]) ?></h2>
    <p>Your assigned tasks for today — <?= date('l, d F Y') ?></p>
  </div>
  <div class="banner-actions">
    <a href="<?= BASE_URL ?>/agent/bookings" class="btn btn-light"><i data-lucide="calendar-check"></i> Review Bookings</a>
    <a href="<?= BASE_URL ?>/agent/applications" class="btn btn-ghost-light"><i data-lucide="file-text"></i> Applications</a>
  </div>
</div>

<div class="stats-grid cols-3">
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-blue"><i data-lucide="calendar-check"></i></div></div>
    <div class="stat-num"><?= $total_bookings ?></div>
    <div class="stat-label">Total Bookings</div>
    <div class="stat-sub <?= $pending_bookings > 0 ? 'warn':'up' ?>">
      <?= $pending_bookings > 0 ? $pending_bookings.' pending review':'All reviewed!' ?>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-green"><i data-lucide="check-circle-2"></i></div></div>
    <div class="stat-num"><?= $approved_bookings ?></div>
    <div class="stat-label">Approved Bookings</div>
    <div class="stat-sub up"><?= $completed_bookings ?> completed</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-amber"><i data-lucide="briefcase"></i></div></div>
    <div class="stat-num"><?= $total_apps ?></div>
    <div class="stat-label">Job Applications</div>
    <div class="stat-sub <?= $pending_apps > 0 ? 'warn':'up' ?>">
      <?= $pending_apps > 0 ? $pending_apps.' pending':'All reviewed!' ?>
    </div>
  </div>
</div>

<div class="two-col eq">
  <div class="card">
    <div class="card-header">
      <span class="card-title">Recent Bookings</span>
      <a href="<?= BASE_URL ?>/agent/bookings" class="card-link">View all →</a>
    </div>
    <div class="item-list">
      <?php if(empty($recent_bookings)): ?>
        <div class="empty-list"><i data-lucide="calendar-check"></i> No bookings assigned yet</div>
      <?php else: ?>
        <?php foreach($recent_bookings as $b):
          $sc_map = ['pending'=>'s-pending','under_review'=>'s-under_review','approved'=>'s-approved','completed'=>'s-completed','rejected'=>'s-rejected'];
          $sc = $sc_map[$b['status']] ?? 's-pending';
        ?>
          <div class="list-item">
            <div class="item-icon"><?php if($b['service_icon']): ?><?= e($b['service_icon']) ?><?php else: ?><i data-lucide="home"></i><?php endif; ?></div>
            <div class="item-info">
              <div class="item-name"><?= e($b['service_name']) ?> — <?= e($b['client_name']) ?></div>
              <div class="item-sub"><i data-lucide="map-pin"></i> <?= e($b['location']) ?> · <?= date('d M Y', strtotime($b['start_date'])) ?></div>
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
        <div class="empty-list"><i data-lucide="file-text"></i> No applications assigned yet</div>
      <?php else: ?>
        <?php foreach($recent_apps as $ap):
          $sc_map = ['pending'=>'s-pending','under_review'=>'s-under_review','approved'=>'s-approved','rejected'=>'s-rejected'];
          $sc = $sc_map[$ap['status']] ?? 's-pending';
        ?>
          <div class="list-item">
            <div class="item-icon"><?php if($ap['service_icon']): ?><?= e($ap['service_icon']) ?><?php else: ?><i data-lucide="briefcase"></i><?php endif; ?></div>
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

<?php include '../includes/dash-end.php'; ?>
