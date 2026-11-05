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

$stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ? AND status = 'under_review'");
$stmt->bind_param('i', $user_id); $stmt->execute();
$under_review = $stmt->get_result()->fetch_row()[0];

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

// Map a service name to a Lucide icon (display only)
if (!function_exists('svc_lucide_icon')) {
    function svc_lucide_icon($name) {
        $n = strtolower((string)$name);
        $map = [
            'chef' => 'chef-hat', 'cook' => 'chef-hat',
            'nanny' => 'baby', 'child' => 'baby',
            'care' => 'heart-handshake',
            'office' => 'building-2',
            'garden' => 'flower-2',
            'clean' => 'sparkles',
            'house' => 'house', 'home' => 'house',
        ];
        foreach ($map as $k => $icon) {
            if (strpos($n, $k) !== false) return $icon;
        }
        return 'briefcase';
    }
}

$page_title    = 'Dashboard';
include '../includes/dash-head.php';
?>
<style>
.success-banner{background:var(--success-bg);border:1px solid #A7F3D0;border-radius:var(--radius);padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:14px;animation:slideDown .35s ease both}
.success-banner-icon{width:38px;height:38px;border-radius:50%;background:#D1FAE5;color:#047857;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.success-banner-icon i{width:18px;height:18px}
.success-banner-body{flex:1;min-width:0}
.success-banner-body strong{display:block;font-size:.9rem;font-weight:700;color:#047857;margin-bottom:2px}
.success-banner-body span{font-size:.82rem;color:#047857}
.success-banner-close{background:none;border:none;cursor:pointer;color:#047857;padding:4px;display:flex;opacity:.7}
.success-banner-close:hover{opacity:1}
.success-banner-close i{width:18px;height:18px}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.service-cell{display:flex;align-items:center;gap:10px}
.service-badge{width:34px;height:34px;border-radius:9px;background:var(--navy-soft);color:var(--navy);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.service-badge i{width:16px;height:16px}
.service-name{font-weight:600;font-size:.86rem;color:var(--text)}
.service-date{font-size:.75rem;color:var(--soft);margin-top:1px}
.bookings-table td.date-cell{font-size:.83rem;color:var(--muted);white-space:nowrap}
.status-pill.cancelled{background:#F3F4F6;color:#6B7280}
.status-pill.hold{background:var(--warning-bg);color:#B45309}
.count-chip{font-size:.72rem;background:var(--navy-soft);color:var(--navy);padding:3px 10px;border-radius:99px;font-weight:700}
.notif-list{display:flex;flex-direction:column;margin:-16px -18px}
.notif-item{padding:14px 18px;border-bottom:1px solid var(--line);display:flex;gap:12px;align-items:flex-start}
.notif-item:last-child{border-bottom:none}
.notif-item.unread{background:var(--info-bg)}
.notif-pip{width:8px;height:8px;border-radius:50%;background:var(--navy-light);flex-shrink:0;margin-top:6px}
.notif-pip.read{background:var(--line)}
.notif-msg{font-size:.83rem;color:var(--text);line-height:1.5;margin-bottom:3px}
.notif-time{font-size:.72rem;color:var(--soft)}
.notif-empty{text-align:center;padding:24px 8px;font-size:.85rem;color:var(--muted)}
.notif-empty i{display:block;width:28px;height:28px;margin:0 auto 8px;color:var(--soft)}
.btn-inline{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:10px;background:var(--navy);color:#fff;font-size:.86rem;font-weight:700}
.btn-inline:hover{background:var(--navy-mid)}
.btn-inline i{width:15px;height:15px}
.two-col{margin-bottom:0}
</style>
<?php
echo '</head><body>';
$dash_role     = 'client';
$dash_page     = 'dashboard';
$page_heading  = 'Dashboard';
$user_name     = $_SESSION['user_name'];
$badge_pending = (int)$pending;
include '../includes/dash-sidebar.php';
?>

      <?php if($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>">
          <i data-lucide="<?= $flash['type']==='success' ? 'circle-check' : 'triangle-alert' ?>"></i>
          <span><?= e($flash['message']) ?></span>
        </div>
      <?php endif; ?>

      <!-- BOOKING SUCCESS BANNER -->
      <?php if (!empty($_SESSION['booking_success'])): ?>
        <?php unset($_SESSION['booking_success']); ?>
        <div class="success-banner" id="bookingSuccessBanner">
          <div class="success-banner-icon"><i data-lucide="check"></i></div>
          <div class="success-banner-body">
            <strong>Booking submitted successfully</strong>
            <span>Our team will review your request and contact you shortly via phone or email.</span>
          </div>
          <button type="button" class="success-banner-close" onclick="dismissSuccess()" title="Dismiss" aria-label="Dismiss"><i data-lucide="x"></i></button>
        </div>
      <?php endif; ?>

      <!-- WELCOME -->
      <div class="welcome-banner">
        <h2>Welcome back, <?= e(explode(' ', $user_name)[0]) ?></h2>
        <p>Here's what's happening with your bookings today.</p>
      </div>

      <!-- STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon"><i data-lucide="layers"></i></div></div>
          <div class="stat-num"><?= $total_bookings ?></div>
          <div class="stat-label">Total Bookings</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon"><i data-lucide="clock"></i></div></div>
          <div class="stat-num"><?= $pending ?></div>
          <div class="stat-label">Pending Review</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon"><i data-lucide="search"></i></div></div>
          <div class="stat-num"><?= $under_review ?></div>
          <div class="stat-label">Under Review</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon"><i data-lucide="circle-check"></i></div></div>
          <div class="stat-num"><?= $completed ?></div>
          <div class="stat-label">Completed</div>
        </div>
      </div>

      <!-- QUICK ACTIONS -->
      <div class="quick-actions">
        <a href="<?= BASE_URL ?>/book" class="quick-btn">
          <span class="quick-btn-icon"><i data-lucide="clipboard-list"></i></span>
          <span class="quick-btn-label">Book a Service</span>
        </a>
        <a href="<?= BASE_URL ?>/my-bookings" class="quick-btn">
          <span class="quick-btn-icon"><i data-lucide="folder"></i></span>
          <span class="quick-btn-label">My Bookings</span>
        </a>
        <a href="<?= BASE_URL ?>/profile" class="quick-btn">
          <span class="quick-btn-icon"><i data-lucide="user"></i></span>
          <span class="quick-btn-label">My Profile</span>
        </a>
      </div>

      <!-- RECENT BOOKINGS + NOTIFICATIONS -->
      <div class="two-col">

        <div class="card">
          <div class="card-header">
            <span class="card-title">Recent Bookings</span>
            <a href="<?= BASE_URL ?>/my-bookings" class="card-link">View all</a>
          </div>
          <div class="card-body">
            <?php if(empty($recent_bookings)): ?>
              <div class="empty-state">
                <i data-lucide="clipboard-list"></i>
                <h4>No bookings yet</h4>
                <p>You haven't made any service bookings yet.</p>
                <a href="<?= BASE_URL ?>/book" class="btn-inline"><i data-lucide="plus"></i> Book Your First Service</a>
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
                      'completed'=>['Completed','completed'],'hold'=>['On Hold','hold'],
                      'approved'=>['Approved','approved'],
                      'rejected'=>['Rejected','rejected'],'cancelled'=>['Cancelled','cancelled'],
                    ];
                    $l = $labels[$b['status']] ?? [$b['status'],'pending'];
                  ?>
                    <tr>
                      <td>
                        <div class="service-cell">
                          <div class="service-badge"><i data-lucide="<?= svc_lucide_icon($b['service_name']) ?>"></i></div>
                          <div>
                            <div class="service-name"><?= e($b['service_name']) ?></div>
                            <div class="service-date"><?= e($b['location']) ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="date-cell"><?= date('d M Y', strtotime($b['start_date'])) ?></td>
                      <td><span class="status-pill <?= e($l[1]) ?>"><?= e($l[0]) ?></span></td>
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
              <span class="count-chip"><?= $notif_count ?> new</span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if(empty($notifications)): ?>
              <div class="notif-empty"><i data-lucide="bell"></i>No notifications yet</div>
            <?php else: ?>
              <div class="notif-list">
                <?php foreach($notifications as $n): ?>
                  <div class="notif-item <?= $n['is_read']?'':'unread' ?>">
                    <div class="notif-pip <?= $n['is_read']?'read':'' ?>"></div>
                    <div>
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

<script>
function dismissSuccess(){
  var el=document.getElementById('bookingSuccessBanner');
  if(el){
    el.style.transition='opacity .3s ease, transform .3s ease';
    el.style.opacity='0';
    el.style.transform='translateY(-8px)';
    setTimeout(function(){el.remove();},300);
  }
}
(function(){
  var el=document.getElementById('bookingSuccessBanner');
  if(el) setTimeout(dismissSuccess, 8000);
})();
</script>
<?php include '../includes/dash-end.php'; ?>
