<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('provider');

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ── FETCH APPLICATIONS ────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT ja.*, s.name AS service_name, s.icon AS service_icon,
           u.name AS agent_name
    FROM job_applications ja
    JOIN services s ON ja.service_id = s.id
    LEFT JOIN users u ON ja.agent_id = u.id
    WHERE ja.provider_id = ?
    ORDER BY ja.created_at DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$flash = flashGet();
$notif_count = unreadNotifCount();

$status_config = [
    'pending'      => ['Pending',      'sp-pending',      '⏳', 'Your application is waiting to be reviewed.'],
    'under_review' => ['Under Review', 'sp-review',       '🔍', 'Our team is currently reviewing your application.'],
    'approved'     => ['Approved',     'sp-approved',     '✅', 'Congratulations! Your application has been approved.'],
    'rejected'     => ['Rejected',     'sp-rejected',     '❌', 'Unfortunately your application was not approved this time.'],
    'cancelled'    => ['Cancelled',    'sp-cancelled',    '🚫', 'This application was cancelled.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>My Applications | Home Help</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --navy-deep:#0D1B4B;--navy-mid:#152268;--navy-light:#2E5FC2;--navy-soft:#D4DCF5;
  --amber:#E8A020;--amber-light:#FFF0CC;
  --cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --danger:#EF4444;--danger-bg:#FEF2F2;
  --success:#10B981;--success-bg:#ECFDF5;
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
.dash-wrap{display:flex;min-height:100vh}

/* SIDEBAR */
.sidebar{width:var(--sidebar);flex-shrink:0;background:var(--navy-deep);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;transition:transform 0.3s ease;overflow-y:auto}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:24px 20px 20px;border-bottom:1px solid rgba(255,255,255,0.08)}
.sidebar-logo img{width:36px;height:36px;object-fit:contain}
.sidebar-logo-text{font-family:var(--font-display);font-size:1.3rem;font-weight:700;color:var(--white)}
.sidebar-user{padding:20px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;gap:12px}
.user-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--amber),#c47d0a);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--white);flex-shrink:0}
.user-info strong{display:block;font-size:0.88rem;font-weight:700;color:var(--white)}
.user-info span{font-size:0.73rem;color:rgba(255,255,255,0.5)}
.sidebar-nav{padding:16px 0;flex:1}
.nav-section-label{font-size:0.66rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.3);padding:12px 20px 6px}
.nav-item{display:flex;align-items:center;gap:12px;padding:11px 20px;font-size:0.87rem;font-weight:500;color:rgba(255,255,255,0.65);cursor:pointer;transition:all 0.2s;position:relative;text-decoration:none}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,0.06)}
.nav-item.active{color:var(--white);background:rgba(255,255,255,0.1);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--amber);border-radius:0 3px 3px 0}
.nav-icon{font-size:1.05rem;width:20px;text-align:center;flex-shrink:0}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,0.08)}
.btn-logout{display:flex;align-items:center;gap:8px;width:100%;padding:10px 14px;border-radius:var(--radius-sm);background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.2);color:rgba(255,100,100,0.9);font-size:0.84rem;font-weight:600;cursor:pointer;transition:all 0.2s;text-decoration:none;font-family:var(--font-body)}
.btn-logout:hover{background:rgba(239,68,68,0.2)}

/* MAIN */
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--white);border-bottom:1px solid var(--cream-dark);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar-left{display:flex;align-items:center;gap:16px}
.burger{display:none;background:none;border:none;cursor:pointer;padding:6px;flex-direction:column;gap:4px}
.burger span{display:block;width:20px;height:2px;background:var(--text-dark);border-radius:2px}
.topbar-title{font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--navy-deep)}
.topbar-right{display:flex;align-items:center;gap:12px}
.btn-apply-new{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:99px;background:var(--amber);color:var(--white);font-size:0.83rem;font-weight:700;border:none;cursor:pointer;transition:all 0.3s;text-decoration:none}
.btn-apply-new:hover{background:#d4911a;transform:translateY(-1px)}

/* CONTENT */
.content{padding:28px;flex:1}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:24px}
.page-header h1{font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--navy-deep)}
.page-header h1 em{font-style:italic;color:var(--amber)}
.page-header p{font-size:0.88rem;color:var(--text-soft);margin-top:4px}

/* FLASH */
.flash{padding:12px 16px;border-radius:var(--radius-sm);font-size:0.86rem;font-weight:500;margin-bottom:20px;display:flex;align-items:center;gap:10px}
.flash-success{background:var(--success-bg);border:1.5px solid #BBF7D0;color:#065F46}
.flash-error{background:var(--danger-bg);border:1.5px solid #FECACA;color:#B91C1C}

/* APPLICATION CARDS */
.apps-list{display:flex;flex-direction:column;gap:16px}
.app-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden;transition:all 0.3s}
.app-card:hover{box-shadow:var(--shadow-md);border-color:var(--navy-soft)}

.app-card-header{padding:18px 22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;border-bottom:1px solid var(--cream-dark)}
.app-service{display:flex;align-items:center;gap:12px}
.app-icon{width:46px;height:46px;border-radius:12px;background:var(--navy-soft);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
.app-service-name{font-weight:700;font-size:1rem;color:var(--navy-deep)}
.app-ref{font-size:0.74rem;color:var(--text-soft);margin-top:2px}

/* STATUS PILLS */
.status-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:99px;font-size:0.76rem;font-weight:700}
.sp-pending{background:#FFFBEB;color:#92400E}
.sp-review{background:#EFF6FF;color:#1E40AF}
.sp-approved{background:var(--success-bg);color:#065F46}
.sp-rejected{background:var(--danger-bg);color:#991B1B}
.sp-cancelled{background:#F3F4F6;color:#6B7280}

/* PROGRESS TRACKER */
.progress-tracker{padding:16px 22px;display:flex;align-items:flex-start;overflow-x:auto;gap:0;border-bottom:1px solid var(--cream-dark)}
.progress-item{display:flex;flex-direction:column;align-items:center;flex-shrink:0}
.progress-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;border:2px solid var(--cream-dark);background:var(--white);color:var(--text-soft)}
.progress-dot.done{background:var(--navy-deep);border-color:var(--navy-deep);color:var(--white)}
.progress-dot.current{background:var(--amber);border-color:var(--amber);color:var(--white)}
.progress-dot.fail{background:var(--danger);border-color:var(--danger);color:var(--white)}
.progress-lbl{font-size:0.67rem;color:var(--text-soft);margin-top:4px;white-space:nowrap}
.progress-lbl.done{color:var(--navy-deep);font-weight:600}
.progress-lbl.current{color:var(--amber);font-weight:700}
.progress-line{width:50px;height:2px;background:var(--cream-dark);flex-shrink:0;margin-top:14px}
.progress-line.done{background:var(--navy-deep)}

/* CARD BODY */
.app-card-body{padding:16px 22px;display:grid;grid-template-columns:repeat(3,1fr);gap:14px;border-bottom:1px solid var(--cream-dark)}
.detail-item{display:flex;flex-direction:column;gap:3px}
.detail-label{font-size:0.7rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-soft)}
.detail-value{font-size:0.86rem;font-weight:600;color:var(--text-dark)}

/* CARD FOOTER */
.app-card-footer{padding:14px 22px;background:var(--cream);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.agent-note{font-size:0.82rem;color:var(--text-mid);font-style:italic;max-width:480px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.status-msg{font-size:0.8rem;font-weight:600}
.status-msg.success{color:var(--success)}
.status-msg.warn{color:var(--amber)}
.status-msg.danger{color:var(--danger)}

/* EMPTY STATE */
.empty-state{text-align:center;padding:64px 20px;background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius)}
.empty-icon{font-size:3rem;margin-bottom:16px}
.empty-state h3{font-family:var(--font-display);font-size:1.4rem;color:var(--navy-deep);margin-bottom:8px}
.empty-state p{font-size:0.9rem;color:var(--text-soft);margin-bottom:24px}
.btn-apply{display:inline-flex;align-items:center;gap:7px;padding:12px 24px;border-radius:99px;background:var(--navy-deep);color:var(--white);font-size:0.9rem;font-weight:700;text-decoration:none;transition:all 0.3s}
.btn-apply:hover{background:var(--navy-mid);transform:translateY(-2px)}

/* MOBILE */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:199}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .content{padding:18px}.topbar{padding:0 16px}
  .app-card-body{grid-template-columns:1fr 1fr}
  .app-card-header{flex-direction:column;align-items:flex-start}
}
@media(max-width:480px){.app-card-body{grid-template-columns:1fr}}

/* VIEW DETAILS BUTTON */
.btn-view-details{
  display:inline-flex;align-items:center;gap:5px;
  padding:6px 14px;border-radius:var(--radius-sm);
  background:var(--navy-deep);color:var(--white);
  font-size:0.78rem;font-weight:700;border:none;cursor:pointer;
  transition:all 0.2s;font-family:var(--font-body);
}
.btn-view-details:hover{background:var(--navy-mid);transform:translateY(-1px)}

/* MODAL OVERLAY */
.modal-overlay{
  display:none;position:fixed;inset:0;
  background:rgba(0,0,0,0.55);z-index:600;
  align-items:center;justify-content:center;padding:20px;
}
.modal-overlay.open{display:flex}

/* MODAL BOX */
.modal-box{
  background:var(--white);border-radius:var(--radius);
  max-width:560px;width:100%;
  box-shadow:0 20px 60px rgba(0,0,0,0.2);
  overflow:hidden;max-height:90vh;display:flex;flex-direction:column;
}
.modal-head{
  background:linear-gradient(135deg,var(--navy-deep),var(--navy-mid));
  padding:22px 24px;display:flex;align-items:center;justify-content:space-between;
  flex-shrink:0;
}
.modal-head-left{display:flex;align-items:center;gap:14px}
.modal-service-icon{
  width:48px;height:48px;border-radius:14px;
  background:rgba(255,255,255,0.15);
  display:flex;align-items:center;justify-content:center;font-size:1.4rem;
}
.modal-service-name{font-family:var(--font-display);font-size:1.2rem;font-weight:700;color:var(--white)}
.modal-app-ref{font-size:0.75rem;color:rgba(255,255,255,0.6);margin-top:2px}
.modal-close{
  width:32px;height:32px;border-radius:50%;
  background:rgba(255,255,255,0.15);border:none;
  color:var(--white);font-size:1.1rem;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:all 0.2s;flex-shrink:0;
}
.modal-close:hover{background:rgba(255,255,255,0.25)}
.modal-body{padding:22px 24px;overflow-y:auto;flex:1}
.modal-status-row{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:20px;padding-bottom:16px;
  border-bottom:1px solid var(--cream-dark);
}
.modal-status-label{font-size:0.78rem;font-weight:600;color:var(--text-soft)}
.modal-section{margin-bottom:18px}
.modal-section-title{
  font-size:0.68rem;font-weight:700;letter-spacing:0.1em;
  text-transform:uppercase;color:var(--text-soft);
  margin-bottom:10px;padding-bottom:6px;
  border-bottom:1px solid var(--cream-dark);
}
.modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.modal-field{display:flex;flex-direction:column;gap:3px}
.modal-field-label{font-size:0.7rem;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.05em}
.modal-field-value{font-size:0.87rem;font-weight:600;color:var(--text-dark)}
.modal-field-full{grid-column:1/-1}
.modal-text-block{
  font-size:0.86rem;color:var(--text-mid);line-height:1.65;
  background:var(--cream);border-radius:var(--radius-sm);
  padding:12px 14px;
}
.modal-agent-note{
  background:var(--navy-soft);border-radius:var(--radius-sm);
  padding:12px 14px;font-size:0.85rem;color:var(--navy-deep);
  font-style:italic;margin-top:6px;
}
.modal-foot{
  padding:16px 24px;border-top:1px solid var(--cream-dark);
  display:flex;justify-content:flex-end;gap:10px;flex-shrink:0;
}
.btn-modal-close-foot{
  padding:9px 22px;border-radius:99px;
  background:var(--navy-deep);color:var(--white);
  font-family:var(--font-body);font-size:0.87rem;font-weight:700;
  border:none;cursor:pointer;transition:all 0.2s;
}
.btn-modal-close-foot:hover{background:var(--navy-mid)}
@media(max-width:480px){.modal-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="dash-wrap">
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="../Image/icon/homehelp_logo_transparent_white.svg" alt="Home Help" onerror="this.style.display='none'"/>
      <span class="sidebar-logo-text">Home Help</span>
    </div>
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($user_name,0,1)) ?></div>
      <div class="user-info">
        <strong><?= e($user_name) ?></strong>
        <span>Job Seeker</span>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Main</div>
      <a href="<?= BASE_URL ?>/provider-dashboard" class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
      <a href="<?= BASE_URL ?>/apply"              class="nav-item"><span class="nav-icon">📝</span> Apply for a Job</a>
      <a href="<?= BASE_URL ?>/my-applications"    class="nav-item active"><span class="nav-icon">📂</span> My Applications</a>
      <div class="nav-section-label">Account</div>
      <a href="<?= BASE_URL ?>/profile"            class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
      <div class="nav-section-label">Help</div>
      <a href="<?= BASE_URL ?>/#faq"              class="nav-item"><span class="nav-icon">❓</span> FAQ</a>
      <a href="<?= BASE_URL ?>/#contact"          class="nav-item"><span class="nav-icon">💬</span> Contact Support</a>
    </nav>
    <div class="sidebar-footer">
      <a href="<?= BASE_URL ?>/logout" class="btn-logout"><span>🚪</span> Sign Out</a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="burger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <span class="topbar-title">My Applications</span>
      </div>
      <div class="topbar-right">
        <a href="<?= BASE_URL ?>/apply" class="btn-apply-new">＋ New Application</a>
      </div>
    </header>

    <div class="content">

      <div class="page-header">
        <div>
          <h1>My <em>Applications</em></h1>
          <p>Track all your job applications and their current status.</p>
        </div>
      </div>

      <?php if($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>">
          <?= $flash['type']==='success' ? '✅' : '⚠️' ?> <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <?php if(empty($applications)): ?>
        <div class="empty-state">
          <div class="empty-icon">📂</div>
          <h3>No applications yet</h3>
          <p>You haven't applied for any jobs yet. Get started and find your first opportunity!</p>
          <a href="<?= BASE_URL ?>/apply" class="btn-apply">Apply for a Job →</a>
        </div>
      <?php else: ?>
        <div class="apps-list">
          <?php foreach($applications as $ap):
            $sc = $status_config[$ap['status']] ?? $status_config['pending'];
            $steps = ['pending','under_review','approved'];
            $rejected = in_array($ap['status'], ['rejected','cancelled']);
          ?>
            <div class="app-card">

              <!-- HEADER -->
              <div class="app-card-header">
                <div class="app-service">
                  <div class="app-icon"><?= $ap['service_icon']?:'💼' ?></div>
                  <div>
                    <div class="app-service-name"><?= e($ap['service_name']) ?></div>
                    <div class="app-ref">Application #<?= $ap['id'] ?> · Submitted <?= date('d M Y', strtotime($ap['created_at'])) ?></div>
                  </div>
                </div>
                <span class="status-pill <?= $sc[1] ?>"><?= $sc[2] ?> <?= $sc[0] ?></span>
              </div>

              <!-- PROGRESS TRACKER -->
              <?php if(!$rejected): ?>
                <div class="progress-tracker">
                  <?php
                  $track = ['pending'=>'Submitted','under_review'=>'Reviewing','approved'=>'Approved'];
                  $keys  = array_keys($track);
                  $cur   = array_search($ap['status'], $keys);
                  $total = count($keys);
                  foreach($track as $sk => $slabel):
                    $idx = array_search($sk, $keys);
                    $is_done    = $cur !== false && $idx < $cur;
                    $is_current = $ap['status'] === $sk;
                  ?>
                    <div class="progress-item">
                      <div class="progress-dot <?= $is_done?'done':($is_current?'current':'') ?>">
                        <?= $is_done ? '✓' : ($idx+1) ?>
                      </div>
                      <div class="progress-lbl <?= $is_done?'done':($is_current?'current':'') ?>"><?= $slabel ?></div>
                    </div>
                    <?php if($idx < $total-1): ?>
                      <div class="progress-line <?= $is_done?'done':'' ?>"></div>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <!-- DETAILS -->
              <div class="app-card-body">
                <div class="detail-item">
                  <span class="detail-label">📍 Location</span>
                  <span class="detail-value"><?= e($ap['location']) ?>, <?= e($ap['district']??'') ?></span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">⏰ Availability</span>
                  <span class="detail-value"><?= e($ap['availability']?:'—') ?></span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">👤 Assigned Agent</span>
                  <span class="detail-value"><?= $ap['agent_name'] ? e($ap['agent_name']) : 'Pending' ?></span>
                </div>
              </div>

              <!-- FOOTER -->
              <div class="app-card-footer">
                <div class="agent-note">
                  <?php if($ap['agent_notes']): ?>
                    💬 Agent: <?= e($ap['agent_notes']) ?>
                  <?php else: ?>
                    <span class="status-msg <?= $ap['status']==='approved'?'success':($ap['status']==='rejected'?'danger':'warn') ?>">
                      <?= $sc[3] ?>
                    </span>
                  <?php endif; ?>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-shrink:0">
                  <?php
                  $modal_data = json_encode([
                    'id'           => $ap['id'],
                    'service_name' => $ap['service_name'],
                    'service_icon' => $ap['service_icon'] ?: '💼',
                    'status'       => $ap['status'],
                    'status_label' => $sc[0],
                    'status_icon'  => $sc[2],
                    'status_class' => $sc[1],
                    'location'     => $ap['location'],
                    'district'     => $ap['district'] ?? '',
                    'availability' => $ap['availability'] ?? '—',
                    'experience'   => $ap['experience'] ?? '—',
                    'bio'          => $ap['bio'] ?? '',
                    'languages'    => $ap['languages'] ?? '—',
                    'expected_pay' => $ap['expected_pay'] ?? '—',
                    'agent_name'   => $ap['agent_name'] ?? 'Pending',
                    'agent_notes'  => $ap['agent_notes'] ?? '',
                    'created_at'   => date('d M Y', strtotime($ap['created_at'])),
                  ], JSON_HEX_QUOT | JSON_HEX_TAG);
                  ?>
                  <button class="btn-view-details" onclick="openModal(<?= htmlspecialchars($modal_data, ENT_QUOTES) ?>)">
                    👁 View Details
                  </button>
                  <?php if($ap['status']==='rejected'): ?>
                    <a href="<?= BASE_URL ?>/apply" style="font-size:0.8rem;font-weight:700;color:var(--navy-light)">→ Apply Again</a>
                  <?php endif; ?>
                </div>
              </div>

            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
    <?php include_once '../includes/footer.php'; ?>
  </div>
</div>

<script>

function openModal(data){
  document.getElementById('mIcon').textContent       = data.service_icon;
  document.getElementById('mService').textContent    = data.service_name;
  document.getElementById('mRef').textContent        = 'Application #' + data.id + ' · ' + data.created_at;
  document.getElementById('mLocation').textContent   = data.location;
  document.getElementById('mDistrict').textContent   = data.district || '—';
  document.getElementById('mAvailability').textContent = data.availability;
  document.getElementById('mPay').textContent        = data.expected_pay || '—';
  document.getElementById('mLanguages').textContent  = data.languages || '—';
  document.getElementById('mAgent').textContent      = data.agent_name;
  document.getElementById('mDate').textContent       = data.created_at;
  document.getElementById('mExperience').textContent = data.experience;
  // Bio
  if(data.bio){
    document.getElementById('mBio').textContent = data.bio;
    document.getElementById('mBioSection').style.display = 'block';
  } else {
    document.getElementById('mBioSection').style.display = 'none';
  }
  // Agent note
  if(data.agent_notes){
    document.getElementById('mNote').textContent = data.agent_notes;
    document.getElementById('mNoteSection').style.display = 'block';
  } else {
    document.getElementById('mNoteSection').style.display = 'none';
  }
  // Status pill
  var pill = document.getElementById('mStatusPill');
  pill.textContent  = data.status_icon + ' ' + data.status_label;
  pill.className    = 'status-pill ' + data.status_class;
  // Show modal
  document.getElementById('detailModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal(){
  document.getElementById('detailModal').classList.remove('open');
  document.body.style.overflow = '';
}
function handleOverlayClick(e){
  if(e.target === document.getElementById('detailModal')) closeModal();
}

function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');document.body.style.overflow=document.getElementById('sidebar').classList.contains('open')?'hidden':'';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
window.addEventListener('resize',function(){if(window.innerWidth>768)closeSidebar();});
</script>

<!-- APPLICATION DETAILS MODAL -->
<div class="modal-overlay" id="detailModal" onclick="handleOverlayClick(event)">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-head-left">
        <div class="modal-service-icon" id="mIcon">💼</div>
        <div>
          <div class="modal-service-name" id="mService">Service</div>
          <div class="modal-app-ref" id="mRef">Application #</div>
        </div>
      </div>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <!-- STATUS -->
      <div class="modal-status-row">
        <span class="modal-status-label">Current Status</span>
        <span class="status-pill" id="mStatusPill">⏳ Pending</span>
      </div>
      <!-- DETAILS -->
      <div class="modal-section">
        <div class="modal-section-title">📋 Application Details</div>
        <div class="modal-grid">
          <div class="modal-field">
            <span class="modal-field-label">📍 Location</span>
            <span class="modal-field-value" id="mLocation">—</span>
          </div>
          <div class="modal-field">
            <span class="modal-field-label">🗺️ District</span>
            <span class="modal-field-value" id="mDistrict">—</span>
          </div>
          <div class="modal-field">
            <span class="modal-field-label">⏰ Availability</span>
            <span class="modal-field-value" id="mAvailability">—</span>
          </div>
          <div class="modal-field">
            <span class="modal-field-label">💰 Expected Pay</span>
            <span class="modal-field-value" id="mPay">—</span>
          </div>
          <div class="modal-field">
            <span class="modal-field-label">🗣️ Languages</span>
            <span class="modal-field-value" id="mLanguages">—</span>
          </div>
          <div class="modal-field">
            <span class="modal-field-label">👤 Assigned Agent</span>
            <span class="modal-field-value" id="mAgent">Pending</span>
          </div>
          <div class="modal-field">
            <span class="modal-field-label">📅 Submitted On</span>
            <span class="modal-field-value" id="mDate">—</span>
          </div>
        </div>
      </div>
      <!-- EXPERIENCE -->
      <div class="modal-section">
        <div class="modal-section-title">💼 Work Experience</div>
        <div class="modal-text-block" id="mExperience">—</div>
      </div>
      <!-- BIO -->
      <div class="modal-section" id="mBioSection">
        <div class="modal-section-title">🙋 About Me</div>
        <div class="modal-text-block" id="mBio">—</div>
      </div>
      <!-- AGENT NOTE -->
      <div class="modal-section" id="mNoteSection" style="display:none">
        <div class="modal-section-title">💬 Agent Note</div>
        <div class="modal-agent-note" id="mNote"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-modal-close-foot" onclick="closeModal()">Close</button>
    </div>
  </div>
</div>
</body>
</html>