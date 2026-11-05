<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('admin');

$admin_name = $_SESSION['user_name'];

// ── HANDLE MARK AS READ / DELETE ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['msg_action'])) {
    verifyCSRF();
    $msg_id = (int)($_POST['msg_id'] ?? 0);
    if ($msg_id) {
        if ($_POST['msg_action'] === 'read') {
            $conn->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->bind_param('i',$msg_id) || true;
            $s = $conn->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?");
            $s->bind_param('i',$msg_id); $s->execute();
        } elseif ($_POST['msg_action'] === 'delete') {
            $s = $conn->prepare("DELETE FROM contact_messages WHERE id=?");
            $s->bind_param('i',$msg_id); $s->execute();
            flashSet('success','Message deleted.');
        } elseif ($_POST['msg_action'] === 'read_all') {
            $conn->query("UPDATE contact_messages SET is_read=1");
            flashSet('success','All messages marked as read.');
        }
    }
    redirect(BASE_URL . '/admin');
}

// ── STATS ─────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
$stmt->execute();
$roles_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$role_counts = [];
foreach ($roles_raw as $r) $role_counts[$r['role']] = $r['cnt'];

$stats['total_clients']   = $role_counts['client']   ?? 0;
$stats['total_providers'] = $role_counts['provider']  ?? 0;
$stats['total_agents']    = $role_counts['agent']     ?? 0;
$stats['total_users']     = array_sum($role_counts);

$stmt = $conn->query("SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status");
$bookings_raw = $stmt->fetch_all(MYSQLI_ASSOC);
$booking_counts = [];
foreach ($bookings_raw as $b) $booking_counts[$b['status']] = $b['cnt'];

$stats['total_bookings']       = array_sum($booking_counts);
$stats['pending_bookings']     = $booking_counts['pending']      ?? 0;
$stats['under_review_bookings']= $booking_counts['under_review'] ?? 0;
$stats['completed_bookings']   = $booking_counts['completed']    ?? 0;

$stmt = $conn->query("SELECT COUNT(*) FROM services WHERE status = 'active'");
$stats['active_services'] = $stmt->fetch_row()[0];

$stmt = $conn->query("SELECT COUNT(*) FROM users WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
$stats['new_users_month'] = $stmt->fetch_row()[0];

$stmt = $conn->query("SELECT COUNT(*) FROM bookings WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
$stats['new_bookings_month'] = $stmt->fetch_row()[0];

$stmt = $conn->query("SELECT COUNT(*) FROM job_applications WHERE status='pending'");
$stats['pending_applications'] = $stmt->fetch_row()[0];

// ── CONTACT MESSAGES ─────────────────────────────────────────
$stats['unread_messages'] = 0;
$contact_messages = [];
$table_check = $conn->query("SHOW TABLES LIKE 'contact_messages'");
if ($table_check && $table_check->num_rows > 0) {
    $stmt = $conn->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0");
    $stats['unread_messages'] = $stmt->fetch_row()[0];

    $stmt = $conn->query("
        SELECT * FROM contact_messages
        ORDER BY is_read ASC, created_at DESC
        LIMIT 20
    ");
    $contact_messages = $stmt->fetch_all(MYSQLI_ASSOC);
}

// ── RECENT BOOKINGS ───────────────────────────────────────────
$stmt = $conn->query("
    SELECT b.id, b.status, b.created_at, b.start_date, b.location,
           s.name AS service_name, s.icon AS service_icon,
           u.name AS client_name, a.name AS agent_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    JOIN users u    ON b.client_id  = u.id
    LEFT JOIN users a ON b.agent_id = a.id
    ORDER BY b.created_at DESC LIMIT 8
");
$recent_bookings = $stmt->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 6");
$recent_users = $stmt->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->query("
    SELECT s.name, s.icon, COUNT(b.id) as cnt
    FROM services s
    LEFT JOIN bookings b ON b.service_id = s.id
    WHERE s.status = 'active'
    GROUP BY s.id, s.name, s.icon
    ORDER BY cnt DESC
");
$service_stats = $stmt->fetch_all(MYSQLI_ASSOC);
$max_service_cnt = max(array_column($service_stats, 'cnt') ?: [1]);

$flash = flashGet();
$notif_count = unreadNotifCount();

// ── SHELL ─────────────────────────────────────────────────────
$page_title   = 'Admin Dashboard';
$page_heading = 'Admin Dashboard';
$dash_role    = 'admin';
$dash_page    = 'dashboard';
$user_name    = $admin_name;
include '../includes/dash-head.php';
?>
</head>
<body>
<?php include '../includes/dash-sidebar.php'; ?>

<!-- Hidden form for message actions -->
<form method="POST" id="msgActionForm" style="display:none">
  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
  <input type="hidden" name="msg_action" id="msgActionInput" value=""/>
  <input type="hidden" name="msg_id"     id="msgIdInput"     value=""/>
</form>

<?php if($flash): ?>
  <div class="flash flash-<?= e($flash['type']) ?>">
    <i data-lucide="<?= $flash['type']==='success' ? 'check-circle-2' : 'alert-triangle' ?>"></i>
    <span><?= e($flash['message']) ?></span>
  </div>
<?php endif; ?>

<!-- WELCOME BANNER -->
<div class="welcome-banner has-actions">
  <div>
    <h2>Welcome, <?= e(explode(' ',$admin_name)[0]) ?></h2>
    <p>Here's your platform overview for today — <?= date('l, d F Y') ?></p>
  </div>
  <div class="banner-actions">
    <a href="<?= BASE_URL ?>/admin/bookings" class="btn btn-light"><i data-lucide="calendar-check"></i> View Bookings</a>
    <a href="<?= BASE_URL ?>/admin/applications" class="btn btn-ghost-light"><i data-lucide="file-text"></i> Applications</a>
  </div>
</div>

<!-- STATS ROW 1 -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-blue"><i data-lucide="users"></i></div></div>
    <div class="stat-num"><?= $stats['total_users'] ?></div>
    <div class="stat-label">Total Users</div>
    <div class="stat-sub up">+<?= $stats['new_users_month'] ?> this month</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-amber"><i data-lucide="calendar-check"></i></div></div>
    <div class="stat-num"><?= $stats['total_bookings'] ?></div>
    <div class="stat-label">Total Bookings</div>
    <div class="stat-sub up">+<?= $stats['new_bookings_month'] ?> this month</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-green"><i data-lucide="layers"></i></div></div>
    <div class="stat-num"><?= $stats['active_services'] ?></div>
    <div class="stat-label">Active Services</div>
    <div class="stat-sub up"><?= $stats['total_agents'] ?> team members assigned</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-red"><i data-lucide="clock"></i></div></div>
    <div class="stat-num"><?= $stats['pending_bookings'] ?></div>
    <div class="stat-label">Pending Bookings</div>
    <div class="stat-sub <?= $stats['pending_bookings'] > 0 ? 'warn':'up' ?>">
      <?= $stats['pending_bookings'] > 0 ? 'Needs review':'All clear' ?>
    </div>
  </div>
</div>

<!-- STATS ROW 2 -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon"><i data-lucide="home"></i></div></div>
    <div class="stat-num"><?= $stats['total_clients'] ?></div>
    <div class="stat-label">Clients</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-amber"><i data-lucide="briefcase"></i></div></div>
    <div class="stat-num"><?= $stats['total_providers'] ?></div>
    <div class="stat-label">Job Seekers</div>
    <div class="stat-sub <?= $stats['pending_applications'] > 0 ? 'warn':'up' ?>">
      <?= $stats['pending_applications'] > 0 ? $stats['pending_applications'].' pending applications':'No pending' ?>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-green"><i data-lucide="search"></i></div></div>
    <div class="stat-num"><?= $stats['under_review_bookings'] ?></div>
    <div class="stat-label">Under Review Bookings</div>
    <div class="stat-sub <?= $stats['under_review_bookings'] > 0 ? 'warn':'up' ?>">
      <?= $stats['under_review_bookings'] > 0 ? 'Awaiting action':'All caught up' ?>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><div class="stat-icon ic-violet"><i data-lucide="mail"></i></div></div>
    <div class="stat-num"><?= $stats['unread_messages'] ?></div>
    <div class="stat-label">Unread Messages</div>
    <div class="stat-sub <?= $stats['unread_messages'] > 0 ? 'warn':'up' ?>">
      <?= $stats['unread_messages'] > 0 ? 'New messages' : 'All caught up' ?>
    </div>
  </div>
</div>

<!-- BOOKINGS BY SERVICE -->
<section class="bbs-panel mb" id="bookings-by-service">
  <div class="bbs-head">
    <div>
      <p class="bbs-eyebrow"><i data-lucide="bar-chart-3"></i> Demand overview</p>
      <h2 class="bbs-title">Bookings by Service</h2>
      <p class="bbs-sub">Which services clients request most — <?= (int)$stats['total_bookings'] ?> bookings total</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/reports" class="btn btn-navy"><i data-lucide="file-bar-chart-2"></i> Full report</a>
  </div>

  <?php if (empty($service_stats)): ?>
    <div class="card"><div class="empty-state"><i data-lucide="layers"></i><h4>No active services</h4><p>Add services to see booking demand here.</p></div></div>
  <?php else: ?>
    <div class="bbs-grid">
      <?php foreach ($service_stats as $i => $ss):
        $pct = $max_service_cnt > 0 ? round(($ss['cnt'] / $max_service_cnt) * 100) : 0;
        $share = $stats['total_bookings'] > 0 ? round(($ss['cnt'] / $stats['total_bookings']) * 100) : 0;
        $icon = serviceLucideIcon($ss['icon'] ?? '', $ss['name'] ?? '');
        $tone = ['tone-a','tone-b','tone-c','tone-d'][$i % 4];
      ?>
        <article class="bbs-card <?= $tone ?>">
          <div class="bbs-card-top">
            <div class="bbs-icon"><i data-lucide="<?= e($icon) ?>"></i></div>
            <div class="bbs-rank">#<?= $i + 1 ?></div>
          </div>
          <div class="bbs-name"><?= e($ss['name']) ?></div>
          <div class="bbs-count"><?= (int)$ss['cnt'] ?></div>
          <div class="bbs-meta"><?= $share ?>% of all bookings</div>
          <div class="bbs-track" aria-hidden="true">
            <div class="bbs-fill" style="width:<?= max($pct, $ss['cnt'] > 0 ? 8 : 0) ?>%"></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- RECENT BOOKINGS + RECENT USERS -->
<div class="two-col side mb">
  <div class="card">
    <div class="card-header">
      <span class="card-title">Recent Bookings</span>
      <a href="<?= BASE_URL ?>/admin/bookings" class="card-link">View all</a>
    </div>
    <div class="table-wrap">
      <table class="data-table" style="min-width:560px">
        <thead>
          <tr><th>Client</th><th>Service</th><th>Date</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php if(empty($recent_bookings)): ?>
            <tr><td colspan="5" class="empty-row">No bookings yet</td></tr>
          <?php else: ?>
            <?php foreach($recent_bookings as $b):
              $sc_map = ['pending'=>'s-pending','under_review'=>'s-under_review','completed'=>'s-completed','hold'=>'s-hold','approved'=>'s-approved','rejected'=>'s-rejected','cancelled'=>'s-cancelled'];
              $sc = $sc_map[$b['status']] ?? 's-pending';
              $label = ucfirst(str_replace('_',' ',$b['status']));
            ?>
              <tr>
                <td><div class="cell-user"><div class="cell-av"><?= e(strtoupper(substr($b['client_name'],0,1))) ?></div><span class="cell-name"><?= e(explode(' ',$b['client_name'])[0]) ?></span></div></td>
                <td><span class="service-chip"><i data-lucide="<?= e(serviceLucideIcon($b['service_icon'] ?? '', $b['service_name'] ?? '')) ?>"></i> <?= e($b['service_name']) ?></span></td>
                <td class="date-cell"><?= date('d M', strtotime($b['start_date'])) ?></td>
                <td><span class="status-pill <?= $sc ?>"><?= $label ?></span></td>
                <td><a href="<?= BASE_URL ?>/admin/bookings" class="act-btn">View</a></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">Recent Users</span>
      <a href="<?= BASE_URL ?>/admin/users" class="card-link">View all</a>
    </div>
    <div>
      <?php if(empty($recent_users)): ?>
        <div class="empty-list">No users yet</div>
      <?php else: ?>
        <?php foreach($recent_users as $u): ?>
          <div class="user-row">
            <div class="cell-av av-<?= e($u['role']) ?>"><?= e(strtoupper(substr($u['name'],0,1))) ?></div>
            <div class="user-row-info">
              <div class="user-row-name"><?= e($u['name']) ?></div>
              <div class="user-row-email"><?= e($u['email']) ?></div>
            </div>
            <span class="role-badge rb-<?= e($u['role']) ?>"><?= $u['role']==='agent' ? 'Team' : ucfirst($u['role']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- CONTACT MESSAGES INBOX -->
<section class="inbox-panel" id="messages-section">
  <div class="inbox-head">
    <div>
      <h2 class="inbox-title">Messages</h2>
      <p class="inbox-sub">
        <?= count($contact_messages) ?> message<?= count($contact_messages) !== 1 ? 's' : '' ?>
        <?php if ($stats['unread_messages'] > 0): ?>
          · <strong><?= (int)$stats['unread_messages'] ?> unread</strong>
        <?php endif; ?>
      </p>
    </div>
    <?php if ($stats['unread_messages'] > 0): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
        <input type="hidden" name="msg_action" value="read_all"/>
        <input type="hidden" name="msg_id" value="0"/>
        <button type="submit" class="btn btn-outline"><i data-lucide="check-check"></i> Mark all as read</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (empty($contact_messages)): ?>
    <div class="inbox-empty">
      <i data-lucide="mail-open"></i>
      <h4>No messages yet</h4>
      <p>Messages from the homepage contact form will appear here.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table inbox-table" style="min-width:820px">
        <thead>
          <tr>
            <th>From</th>
            <th>Topic</th>
            <th>Message</th>
            <th>Received</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contact_messages as $msg):
            $is_unread = !$msg['is_read'];
            $initials  = strtoupper(substr($msg['first_name'], 0, 1) . substr($msg['last_name'], 0, 1));
            $full_name = trim($msg['first_name'] . ' ' . $msg['last_name']);
            $preview   = strlen($msg['message']) > 110 ? substr($msg['message'], 0, 110) . '…' : $msg['message'];
          ?>
            <tr class="<?= $is_unread ? 'is-unread' : '' ?>" id="msgRow<?= $msg['id'] ?>">
              <td>
                <div class="cell-user">
                  <div class="cell-av"><?= e($initials) ?></div>
                  <div>
                    <div class="cell-name"><?= e($full_name) ?></div>
                    <div class="cell-sub">
                      <a class="inbox-mail-link" href="mailto:<?= e($msg['email']) ?>"><?= e($msg['email']) ?></a>
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <span class="msg-topic"><?= e($msg['topic'] ?: 'General') ?></span>
                <?php if ($is_unread): ?>
                  <div class="inbox-new-label">New</div>
                <?php endif; ?>
              </td>
              <td class="inbox-msg-cell">
                <p class="msg-preview"><?= e($preview) ?></p>
              </td>
              <td class="date-cell">
                <?= date('d M Y', strtotime($msg['created_at'])) ?>
                <div class="cell-sub"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
              </td>
              <td>
                <div class="actions-cell">
                  <button type="button" class="act-btn act-view"
                    onclick='openMsgModal(<?= json_encode([
                      'id' => (int)$msg['id'],
                      'name' => $full_name,
                      'email' => $msg['email'],
                      'topic' => $msg['topic'] ?: 'General',
                      'message' => $msg['message'],
                      'date' => date('d M Y · H:i', strtotime($msg['created_at'])),
                      'unread' => (bool)$is_unread,
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)'>
                    <i data-lucide="eye"></i> Open
                  </button>
                  <?php if ($is_unread): ?>
                    <button type="button" class="act-btn act-approve" onclick="msgAction('read',<?= (int)$msg['id'] ?>)"><i data-lucide="check"></i></button>
                  <?php endif; ?>
                  <button type="button" class="act-btn act-delete" onclick="if(confirm('Delete this message?'))msgAction('delete',<?= (int)$msg['id'] ?>)"><i data-lucide="trash-2"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- Message detail modal -->
<div class="modal-overlay" id="msgModal" onclick="if(event.target===this)closeMsgModal()">
  <div class="modal msg-modal">
    <div class="msg-modal-top">
      <div>
        <div class="msg-modal-topic" id="mmTopic">—</div>
        <h3 id="mmName">—</h3>
        <a class="msg-modal-email" id="mmEmail" href="#">—</a>
      </div>
      <button type="button" class="detail-close" onclick="closeMsgModal()" aria-label="Close"><i data-lucide="x"></i></button>
    </div>
    <div class="msg-modal-meta" id="mmDate">—</div>
    <div class="msg-modal-body" id="mmBody"></div>
    <div class="modal-actions">
      <button type="button" class="btn btn-outline" onclick="closeMsgModal()">Close</button>
      <a class="btn btn-navy" id="mmReply" href="#"><i data-lucide="reply"></i> Reply by email</a>
      <button type="button" class="btn btn-outline" id="mmMarkRead" style="display:none" onclick="msgAction('read', window._openMsgId)"><i data-lucide="check"></i> Mark as read</button>
    </div>
  </div>
</div>

<script>
function msgAction(action, id){
  document.getElementById('msgActionInput').value = action;
  document.getElementById('msgIdInput').value     = id;
  document.getElementById('msgActionForm').submit();
}
function openMsgModal(d){
  window._openMsgId = d.id;
  document.getElementById('mmTopic').textContent = d.topic || 'General';
  document.getElementById('mmName').textContent = d.name || '—';
  var mail = document.getElementById('mmEmail');
  mail.textContent = d.email || '—';
  mail.href = d.email ? ('mailto:' + d.email) : '#';
  document.getElementById('mmDate').textContent = d.date || '';
  document.getElementById('mmBody').textContent = d.message || '';
  document.getElementById('mmReply').href = d.email ? ('mailto:' + d.email + '?subject=' + encodeURIComponent('Re: ' + (d.topic || 'Your HomeHelp message'))) : '#';
  document.getElementById('mmMarkRead').style.display = d.unread ? '' : 'none';
  document.getElementById('msgModal').classList.add('open');
  document.body.style.overflow = 'hidden';
  if (window.lucide) lucide.createIcons();
}
function closeMsgModal(){
  document.getElementById('msgModal').classList.remove('open');
  document.body.style.overflow = '';
}
</script>
<?php include '../includes/dash-end.php'; ?>
