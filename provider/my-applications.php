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

// Status => [label, css class, Lucide icon, message]
$status_config = [
    'pending'      => ['Pending',      'sp-pending',      'clock',        'Your application is waiting to be reviewed.'],
    'under_review' => ['Under Review', 'sp-review',       'search',       'Our team is currently reviewing your application.'],
    'approved'     => ['Approved',     'sp-approved',     'circle-check', 'Congratulations! Your application has been approved.'],
    'rejected'     => ['Rejected',     'sp-rejected',     'circle-x',     'Unfortunately your application was not approved this time.'],
    'cancelled'    => ['Cancelled',    'sp-cancelled',    'ban',          'This application was cancelled.'],
];

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

// ── SHELL VARS ────────────────────────────────────────────────
$page_title          = 'My Applications';
$dash_role           = 'provider';
$dash_page           = 'applications';
$page_heading        = 'My Applications';
$topbar_action_href  = BASE_URL . '/apply';
$topbar_action_label = 'New Application';
$topbar_action_icon  = 'plus';
?>
<?php include '../includes/dash-head.php'; ?>
<style>
.page-intro{margin-bottom:20px}
.page-intro h1{font-size:1.5rem;font-weight:800;color:var(--navy);letter-spacing:-.02em;margin-bottom:4px}
.page-intro p{font-size:.9rem;color:var(--muted)}
.sp-cancelled{background:#F3F4F6;color:#6B7280}
.status-pill{gap:5px}
.status-pill i{width:13px;height:13px}

.apps-list{display:flex;flex-direction:column;gap:14px}
.app-card:hover{box-shadow:var(--shadow)}
.app-card-header{padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;border-bottom:1px solid var(--line)}
.app-service{display:flex;align-items:center;gap:12px}
.app-icon{width:42px;height:42px;border-radius:10px;background:var(--navy-soft);color:var(--navy);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.app-icon i{width:20px;height:20px}
.app-service-name{font-weight:700;font-size:.97rem;color:var(--navy)}
.app-ref{font-size:.76rem;color:var(--muted);margin-top:2px}

.progress-tracker{padding:16px 20px;display:flex;align-items:flex-start;overflow-x:auto;border-bottom:1px solid var(--line)}
.progress-item{display:flex;flex-direction:column;align-items:center;flex-shrink:0}
.progress-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;border:2px solid var(--line);background:var(--white);color:var(--soft)}
.progress-dot i{width:14px;height:14px;stroke-width:3}
.progress-dot.done{background:var(--navy);border-color:var(--navy);color:#fff}
.progress-dot.current{background:var(--navy-light);border-color:var(--navy-light);color:#fff}
.progress-lbl{font-size:.68rem;color:var(--soft);margin-top:4px;white-space:nowrap}
.progress-lbl.done{color:var(--navy);font-weight:600}
.progress-lbl.current{color:var(--navy-light);font-weight:700}
.progress-line{width:50px;height:2px;background:var(--line);flex-shrink:0;margin-top:14px}
.progress-line.done{background:var(--navy)}

.app-card-body{padding:16px 20px;display:grid;grid-template-columns:repeat(3,1fr);gap:14px;border-bottom:1px solid var(--line)}
.detail-item{display:flex;flex-direction:column;gap:3px}
.detail-label{display:inline-flex;align-items:center;gap:5px;font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--soft)}
.detail-label i{width:12px;height:12px}
.detail-value{font-size:.88rem;font-weight:600;color:var(--text)}

.app-card-footer{padding:12px 20px;background:var(--bg);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.agent-note{display:flex;align-items:center;gap:6px;font-size:.82rem;color:#374151;font-style:italic;max-width:480px}
.agent-note i{width:14px;height:14px;flex-shrink:0}
.status-msg{font-size:.8rem;font-weight:600;font-style:normal}
.status-msg.success{color:var(--success)}
.status-msg.warn{color:var(--warning)}
.status-msg.danger{color:var(--danger)}
.footer-actions{display:flex;gap:10px;align-items:center;flex-shrink:0}
.btn-view-details{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--radius-sm);background:var(--navy);color:#fff;font-size:.78rem;font-weight:700;border:none;cursor:pointer;font-family:var(--font)}
.btn-view-details:hover{background:var(--navy-mid)}
.btn-view-details i{width:14px;height:14px}
.apply-again{display:inline-flex;align-items:center;gap:4px;font-size:.8rem;font-weight:700;color:var(--navy-light)}
.apply-again i{width:14px;height:14px}

.empty-state{background:var(--white);border:1px solid var(--line);border-radius:var(--radius);padding:56px 20px}
.empty-state i{width:40px;height:40px}
.btn-apply{display:inline-flex;align-items:center;gap:7px;padding:11px 20px;border-radius:var(--radius-sm);background:var(--navy);color:#fff;font-size:.88rem;font-weight:700}
.btn-apply:hover{background:var(--navy-mid)}
.btn-apply i{width:16px;height:16px;margin:0;color:#fff}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(10,18,40,.55);z-index:600;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal-box{background:var(--white);border-radius:var(--radius);max-width:560px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden;max-height:90vh;display:flex;flex-direction:column}
.modal-head{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 100%);padding:20px 22px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.modal-head-left{display:flex;align-items:center;gap:14px}
.modal-service-icon{width:46px;height:46px;border-radius:12px;background:rgba(255,255,255,.15);color:#fff;display:flex;align-items:center;justify-content:center}
.modal-service-icon i{width:22px;height:22px}
.modal-service-name{font-size:1.05rem;font-weight:800;color:#fff;letter-spacing:-.01em}
.modal-app-ref{font-size:.76rem;color:rgba(255,255,255,.65);margin-top:2px}
.modal-close{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.15);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.modal-close:hover{background:rgba(255,255,255,.25)}
.modal-close i{width:16px;height:16px}
.modal-body{padding:20px 22px;overflow-y:auto;flex:1}
.modal-status-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid var(--line)}
.modal-status-label{font-size:.78rem;font-weight:600;color:var(--muted)}
.modal-section{margin-bottom:18px}
.modal-section-title{display:flex;align-items:center;gap:6px;font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--soft);margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid var(--line)}
.modal-section-title i{width:13px;height:13px}
.modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.modal-field{display:flex;flex-direction:column;gap:3px}
.modal-field-label{display:inline-flex;align-items:center;gap:5px;font-size:.7rem;font-weight:700;color:var(--soft);text-transform:uppercase;letter-spacing:.05em}
.modal-field-label i{width:12px;height:12px}
.modal-field-value{font-size:.88rem;font-weight:600;color:var(--text)}
.modal-text-block{font-size:.86rem;color:#374151;line-height:1.65;background:var(--bg);border-radius:var(--radius-sm);padding:12px 14px;white-space:pre-line}
.modal-agent-note{background:var(--navy-soft);border-radius:var(--radius-sm);padding:12px 14px;font-size:.85rem;color:var(--navy);font-style:italic}
.modal-foot{padding:14px 22px;border-top:1px solid var(--line);display:flex;justify-content:flex-end;flex-shrink:0}

@media(max-width:768px){
  .app-card-body{grid-template-columns:1fr 1fr}
  .app-card-header{flex-direction:column;align-items:flex-start}
}
@media(max-width:480px){
  .app-card-body,.modal-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>
<?php include '../includes/dash-sidebar.php'; ?>

<div class="page-intro">
  <h1>My Applications</h1>
  <p>Track all your job applications and their current status.</p>
</div>

<?php if($flash): ?>
  <div class="flash flash-<?= $flash['type']==='success' ? 'success' : 'error' ?>">
    <i data-lucide="<?= $flash['type']==='success' ? 'circle-check' : 'triangle-alert' ?>"></i>
    <span><?= e($flash['message']) ?></span>
  </div>
<?php endif; ?>

<?php if(empty($applications)): ?>
  <div class="empty-state">
    <i data-lucide="folder-open"></i>
    <h4>No applications yet</h4>
    <p>You haven't applied for any jobs yet. Get started and find your first opportunity!</p>
    <a href="<?= BASE_URL ?>/apply" class="btn-apply">Apply for a Job <i data-lucide="arrow-right"></i></a>
  </div>
<?php else: ?>
  <div class="apps-list">
    <?php foreach($applications as $ap):
      $sc = $status_config[$ap['status']] ?? $status_config['pending'];
      $steps = ['pending','under_review','approved'];
      $rejected = in_array($ap['status'], ['rejected','cancelled']);
    ?>
      <div class="card app-card">

        <!-- HEADER -->
        <div class="app-card-header">
          <div class="app-service">
            <div class="app-icon"><i data-lucide="<?= svc_icon($ap['service_name']) ?>"></i></div>
            <div>
              <div class="app-service-name"><?= e($ap['service_name']) ?></div>
              <div class="app-ref">Application #<?= $ap['id'] ?> · Submitted <?= date('d M Y', strtotime($ap['created_at'])) ?></div>
            </div>
          </div>
          <span class="status-pill <?= $sc[1] ?>"><i data-lucide="<?= $sc[2] ?>"></i> <?= $sc[0] ?></span>
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
                  <?php if($is_done): ?><i data-lucide="check"></i><?php else: ?><?= ($idx+1) ?><?php endif; ?>
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
            <span class="detail-label"><i data-lucide="map-pin"></i> Location</span>
            <span class="detail-value"><?= e($ap['location']) ?>, <?= e($ap['district']??'') ?></span>
          </div>
          <div class="detail-item">
            <span class="detail-label"><i data-lucide="clock"></i> Availability</span>
            <span class="detail-value"><?= e($ap['availability']?:'—') ?></span>
          </div>
          <div class="detail-item">
            <span class="detail-label"><i data-lucide="user"></i> Assigned Agent</span>
            <span class="detail-value"><?= $ap['agent_name'] ? e($ap['agent_name']) : 'Pending' ?></span>
          </div>
        </div>

        <!-- FOOTER -->
        <div class="app-card-footer">
          <div class="agent-note">
            <?php if($ap['agent_notes']): ?>
              <i data-lucide="message-square"></i> <span>Agent: <?= e($ap['agent_notes']) ?></span>
            <?php else: ?>
              <span class="status-msg <?= $ap['status']==='approved'?'success':($ap['status']==='rejected'?'danger':'warn') ?>">
                <?= $sc[3] ?>
              </span>
            <?php endif; ?>
          </div>
          <div class="footer-actions">
            <?php
            $modal_data = json_encode([
              'id'           => $ap['id'],
              'service_name' => $ap['service_name'],
              'service_icon' => svc_icon($ap['service_name']),
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
            <button type="button" class="btn-view-details" onclick="openModal(<?= htmlspecialchars($modal_data, ENT_QUOTES) ?>)">
              <i data-lucide="eye"></i> View Details
            </button>
            <?php if($ap['status']==='rejected'): ?>
              <a href="<?= BASE_URL ?>/apply" class="apply-again">Apply Again <i data-lucide="arrow-right"></i></a>
            <?php endif; ?>
          </div>
        </div>

      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- APPLICATION DETAILS MODAL -->
<div class="modal-overlay" id="detailModal" onclick="handleOverlayClick(event)">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-head-left">
        <div class="modal-service-icon" id="mIcon"><i data-lucide="briefcase"></i></div>
        <div>
          <div class="modal-service-name" id="mService">Service</div>
          <div class="modal-app-ref" id="mRef">Application #</div>
        </div>
      </div>
      <button type="button" class="modal-close" onclick="closeModal()" aria-label="Close"><i data-lucide="x"></i></button>
    </div>
    <div class="modal-body">
      <!-- STATUS -->
      <div class="modal-status-row">
        <span class="modal-status-label">Current Status</span>
        <span class="status-pill" id="mStatusPill"></span>
      </div>
      <!-- DETAILS -->
      <div class="modal-section">
        <div class="modal-section-title"><i data-lucide="clipboard-list"></i> Application Details</div>
        <div class="modal-grid">
          <div class="modal-field">
            <span class="modal-field-label"><i data-lucide="map-pin"></i> Location</span>
            <span class="modal-field-value" id="mLocation">—</span>
          </div>
          <div class="modal-field">
            <span class="modal-field-label"><i data-lucide="map"></i> District</span>
            <span class="modal-field-value" id="mDistrict">—</span>
          </div>
          <div class="modal-field">
            <span class="modal-field-label"><i data-lucide="clock"></i> Availability</span>
            <span class="modal-field-value" id="mAvailability">—</span>
          </div>
          <div class="modal-field">
            <span class="modal-field-label"><i data-lucide="wallet"></i> Expected Pay</span>
            <span class="modal-field-value" id="mPay">—</span>
          </div>
          <div class="modal-field">
            <span class="modal-field-label"><i data-lucide="languages"></i> Languages</span>
            <span class="modal-field-value" id="mLanguages">—</span>
          </div>
          <div class="modal-field">
            <span class="modal-field-label"><i data-lucide="user"></i> Assigned Agent</span>
            <span class="modal-field-value" id="mAgent">Pending</span>
          </div>
          <div class="modal-field">
            <span class="modal-field-label"><i data-lucide="calendar"></i> Submitted On</span>
            <span class="modal-field-value" id="mDate">—</span>
          </div>
        </div>
      </div>
      <!-- EXPERIENCE -->
      <div class="modal-section">
        <div class="modal-section-title"><i data-lucide="briefcase"></i> Work Experience</div>
        <div class="modal-text-block" id="mExperience">—</div>
      </div>
      <!-- BIO -->
      <div class="modal-section" id="mBioSection">
        <div class="modal-section-title"><i data-lucide="user-round"></i> About Me</div>
        <div class="modal-text-block" id="mBio">—</div>
      </div>
      <!-- AGENT NOTE -->
      <div class="modal-section" id="mNoteSection" style="display:none">
        <div class="modal-section-title"><i data-lucide="message-square"></i> Agent Note</div>
        <div class="modal-agent-note" id="mNote"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn-submit" onclick="closeModal()">Close</button>
    </div>
  </div>
</div>

<script>
function openModal(data){
  document.getElementById('mIcon').innerHTML         = '<i data-lucide="' + data.service_icon + '"></i>';
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
  pill.className = 'status-pill ' + data.status_class;
  pill.innerHTML = '<i data-lucide="' + data.status_icon + '"></i> ';
  pill.appendChild(document.createTextNode(data.status_label));
  if(window.lucide) lucide.createIcons();
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
document.addEventListener('keydown',function(e){ if(e.key==='Escape') closeModal(); });
</script>

<?php include '../includes/dash-end.php'; ?>
