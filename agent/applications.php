<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('agent');

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $action = $_POST['action'] ?? '';
    $app_id = (int)($_POST['app_id'] ?? 0);
    $notes  = sanitize($_POST['notes'] ?? '');

    if ($app_id) {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE job_applications SET status='approved', agent_notes=? WHERE id=? AND agent_id=?");
            $stmt->bind_param('sii',$notes,$app_id,$user_id); $stmt->execute();
            $r = $conn->query("SELECT provider_id FROM job_applications WHERE id=$app_id")->fetch_assoc();
            if ($r) logNotification($r['provider_id'],'application_approved','Congratulations! Your job application has been approved.');

            // ── EMAIL THE APPLICANT ────────────────────────────
            require_once '../includes/mailer.php';
            $info = $conn->query("
                SELECT ja.id, s.name AS service_name,
                       u.name AS applicant_name, u.email AS applicant_email
                FROM job_applications ja
                JOIN services s ON ja.service_id  = s.id
                JOIN users u    ON ja.provider_id = u.id
                WHERE ja.id = $app_id LIMIT 1
            ")->fetch_assoc();
            if ($info && !empty($info['applicant_email'])) {
                sendBookingEmail(
                    $info['applicant_email'],
                    '🎉 Your Job Application Has Been Approved — Home Help Rwanda',
                    buildApplicationApprovalEmailHTML([
                        'applicant_name' => $info['applicant_name'],
                        'service_name'   => $info['service_name'],
                        'agent_note'     => $notes,
                        'app_id'         => $app_id,
                    ])
                );
            }

            flashSet('success',"Application #$app_id approved.");
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE job_applications SET status='rejected', agent_notes=? WHERE id=? AND agent_id=?");
            $stmt->bind_param('sii',$notes,$app_id,$user_id); $stmt->execute();
            $r = $conn->query("SELECT provider_id FROM job_applications WHERE id=$app_id")->fetch_assoc();
            if ($r) logNotification($r['provider_id'],'application_rejected','Your job application was not approved at this time.');
            flashSet('success',"Application #$app_id rejected.");
        } elseif ($action === 'under_review') {
            $stmt = $conn->prepare("UPDATE job_applications SET status='under_review' WHERE id=? AND agent_id=?");
            $stmt->bind_param('ii',$app_id,$user_id); $stmt->execute();
            flashSet('success',"Application #$app_id marked as Under Review.");
        }
    }
    redirect(BASE_URL . '/agent/applications');
}

$filter  = $_GET['status'] ?? 'all';
$search  = sanitize($_GET['search'] ?? '');
$allowed = ['all','pending','under_review','approved','rejected'];
if (!in_array($filter,$allowed)) $filter='all';

$cnt_raw = $conn->prepare("SELECT status, COUNT(*) as cnt FROM job_applications WHERE agent_id=? GROUP BY status");
$cnt_raw->bind_param('i',$user_id); $cnt_raw->execute();
$counts = ['all'=>0];
foreach ($cnt_raw->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $counts[$r['status']]=(int)$r['cnt']; $counts['all']+=(int)$r['cnt'];
}

$where="WHERE ja.agent_id=?"; $params=[$user_id]; $types='i';
if ($filter!=='all') { $where.=" AND ja.status=?"; $params[]=$filter; $types.='s'; }
if ($search) {
    $like="%$search%";
    $where.=" AND (u.name LIKE ? OR s.name LIKE ? OR ja.location LIKE ?)";
    $params[]=$like; $params[]=$like; $params[]=$like; $types.='sss';
}

$stmt = $conn->prepare("
    SELECT ja.*, s.name AS service_name, s.icon AS service_icon,
           u.name AS provider_name, u.phone AS provider_phone, u.email AS provider_email
    FROM job_applications ja
    JOIN services s ON ja.service_id=s.id
    JOIN users u ON ja.provider_id=u.id
    $where ORDER BY ja.created_at DESC
");
$stmt->bind_param($types,...$params); $stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$flash = flashGet();
$status_config = [
    'pending'      => ['Pending',      's-pending',      'clock'],
    'under_review' => ['Under Review', 's-under_review', 'search'],
    'approved'     => ['Approved',     's-approved',     'check-circle-2'],
    'rejected'     => ['Rejected',     's-rejected',     'x-circle'],
];

// ── SHELL ─────────────────────────────────────────────────────
$page_title   = 'Applications';
$page_heading = 'Job Applications';
$dash_role    = 'agent';
$dash_page    = 'applications';
$badge_apps   = $counts['pending'] ?? 0;
include '../includes/dash-head.php';
?>
</head>
<body>

<!-- ACTION MODAL -->
<div class="modal-overlay" id="actionModal">
  <div class="modal">
    <h3 id="modalTitle">Confirm Action</h3>
    <p id="modalDesc">Add a note for the applicant (optional).</p>
    <textarea id="modalNotes" placeholder="Optional note..."></textarea>
    <div class="modal-actions">
      <button class="btn btn-outline" onclick="closeActionModal()">Cancel</button>
      <button id="modalConfirmBtn" class="btn btn-navy" onclick="submitAction()">Confirm</button>
    </div>
  </div>
</div>

<!-- DETAILS MODAL -->
<div class="detail-overlay" id="detailModal" onclick="handleDetailOverlay(event)">
  <div class="detail-box">
    <div class="detail-head">
      <div class="detail-head-left">
        <div class="detail-svc-icon" id="dm_icon"></div>
        <div>
          <div class="detail-svc-name" id="dm_service">Service</div>
          <div class="detail-ref"      id="dm_ref">Application #</div>
        </div>
      </div>
      <button class="detail-close" onclick="closeDetailModal()" aria-label="Close"><i data-lucide="x"></i></button>
    </div>
    <div class="detail-strip">
      <div class="detail-strip-av" id="dm_av">A</div>
      <div>
        <div class="detail-strip-name"    id="dm_name">—</div>
        <div class="detail-strip-contact" id="dm_contact">—</div>
      </div>
      <span class="detail-status-pill" id="dm_status_pill">Pending</span>
    </div>
    <div class="detail-body">
      <div class="detail-section">
        <div class="detail-section-title"><i data-lucide="clipboard-list"></i> Application Details</div>
        <div class="detail-grid">
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="map-pin"></i> Location</span><span class="detail-field-value" id="dm_location">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="map"></i> District</span><span class="detail-field-value" id="dm_district">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="clock"></i> Availability</span><span class="detail-field-value" id="dm_availability">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="wallet"></i> Expected Pay</span><span class="detail-field-value" id="dm_pay">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="languages"></i> Languages</span><span class="detail-field-value" id="dm_languages">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="calendar"></i> Applied On</span><span class="detail-field-value" id="dm_date">—</span></div>
        </div>
      </div>
      <div class="detail-section">
        <div class="detail-section-title"><i data-lucide="briefcase"></i> Work Experience</div>
        <div class="detail-text-block" id="dm_experience">—</div>
      </div>
      <div class="detail-section" id="dm_bio_section">
        <div class="detail-section-title"><i data-lucide="user"></i> About the Applicant</div>
        <div class="detail-text-block" id="dm_bio">—</div>
      </div>
      <div class="detail-section" id="dm_note_section" style="display:none">
        <div class="detail-section-title"><i data-lucide="message-square"></i> Your Note</div>
        <div class="detail-note-block" id="dm_note">—</div>
      </div>
    </div>
    <div class="detail-foot">
      <button class="btn btn-outline" onclick="closeDetailModal()">Close</button>
      <div class="detail-foot-actions" id="dm_actions"></div>
    </div>
  </div>
</div>

<form method="POST" id="actionForm" style="display:none">
  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
  <input type="hidden" name="action"  id="formAction" value=""/>
  <input type="hidden" name="app_id"  id="formAppId"  value=""/>
  <input type="hidden" name="notes"   id="formNotes"  value=""/>
</form>

<?php include '../includes/dash-sidebar.php'; ?>

<?php if($flash): ?>
  <div class="flash flash-<?= e($flash['type']) ?>">
    <i data-lucide="<?= $flash['type']==='success' ? 'check-circle-2' : 'alert-triangle' ?>"></i>
    <span><?= e($flash['message']) ?></span>
  </div>
<?php endif; ?>

<div class="page-head">
  <div>
    <h1>Job Applications</h1>
    <p>Review applicants assigned to you and share your decision.</p>
  </div>
</div>

<div class="filter-bar">
  <div class="filter-tabs">
    <?php
    $tabs = ['all'=>'All','pending'=>'Pending','under_review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected'];
    foreach($tabs as $k=>$lbl):
      $cnt=$counts[$k]??0;
      if($k!=='all'&&$cnt===0) continue;
    ?>
      <a href="?status=<?= $k ?><?= $search?'&search='.urlencode($search):'' ?>"
         class="filter-tab <?= $filter===$k?'active':'' ?>">
        <?= $lbl ?> <span class="filter-count"><?= $cnt ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <form method="GET" class="search-wrap">
    <i data-lucide="search"></i>
    <?php if($filter!=='all'): ?><input type="hidden" name="status" value="<?= e($filter) ?>"/><?php endif; ?>
    <input type="text" name="search" placeholder="Search name, service..." value="<?= e($search) ?>" autocomplete="off"/>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="data-table" style="min-width:860px">
      <thead>
        <tr><th>#</th><th>Applicant</th><th>Service</th><th>Location</th><th>Availability</th><th>Status</th><th>Applied</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if(empty($applications)): ?>
          <tr><td colspan="8" class="empty-row"><i data-lucide="file-text"></i> No applications assigned yet.</td></tr>
        <?php else: ?>
          <?php foreach($applications as $ap):
            $sc=$status_config[$ap['status']]??['Unknown','s-pending','help-circle'];
            $modal_data=json_encode([
              'id'=>$ap['id'],'service_name'=>$ap['service_name'],'service_icon'=>$ap['service_icon']?:'',
              'status'=>$ap['status'],'status_label'=>$sc[0],'status_icon'=>$sc[2],'status_class'=>$sc[1],
              'provider_name'=>$ap['provider_name'],'provider_phone'=>$ap['provider_phone']??'',
              'provider_email'=>$ap['provider_email']??'',
              'location'=>$ap['location']??'—','district'=>$ap['district']??'—',
              'availability'=>$ap['availability']??'—','expected_pay'=>$ap['expected_pay']??'—',
              'languages'=>$ap['languages']??'—','experience'=>$ap['experience']??'—',
              'bio'=>$ap['bio']??'','agent_notes'=>$ap['agent_notes']??'',
              'created_at'=>date('d M Y',strtotime($ap['created_at'])),
            ],JSON_HEX_QUOT|JSON_HEX_TAG);
          ?>
            <tr>
              <td class="id-cell">#<?= $ap['id'] ?></td>
              <td>
                <div class="cell-user">
                  <div class="cell-av"><?= e(strtoupper(substr($ap['provider_name'],0,1))) ?></div>
                  <div>
                    <div class="cell-name"><?= e($ap['provider_name']) ?></div>
                    <div class="cell-sub"><?= e($ap['provider_phone']??$ap['provider_email']??'') ?></div>
                  </div>
                </div>
              </td>
              <td><span class="meta-inline" style="font-size:.8rem"><?php if($ap['service_icon']): ?><?= e($ap['service_icon']) ?><?php else: ?><i data-lucide="briefcase"></i><?php endif; ?> <?= e($ap['service_name']) ?></span></td>
              <td class="muted-cell" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($ap['location']?:'—') ?></td>
              <td class="muted-cell"><?= e($ap['availability']?:'—') ?></td>
              <td><span class="status-pill <?= $sc[1] ?>"><i data-lucide="<?= $sc[2] ?>"></i> <?= $sc[0] ?></span></td>
              <td class="date-cell"><?= date('d M Y',strtotime($ap['created_at'])) ?></td>
              <td>
                <div class="actions-cell">
                  <button class="act-btn act-view" onclick="openDetailModal(<?= htmlspecialchars($modal_data,ENT_QUOTES) ?>)"><i data-lucide="eye"></i> View</button>
                  <?php if(in_array($ap['status'],['pending','under_review'])): ?>
                    <button class="act-btn act-approve" title="Approve" onclick="openActionModal('approve',<?= $ap['id'] ?>,'Approve Application #<?= $ap['id'] ?>','Optional note for the applicant.')"><i data-lucide="check-circle-2"></i></button>
                    <button class="act-btn act-reject"  title="Reject"  onclick="openActionModal('reject', <?= $ap['id'] ?>,'Reject Application #<?= $ap['id'] ?>','Please provide a reason.')"><i data-lucide="x-circle"></i></button>
                  <?php endif; ?>
                  <?php if($ap['status']==='pending'): ?>
                    <button class="act-btn act-review" title="Mark Under Review" onclick="quickAction('under_review',<?= $ap['id'] ?>)"><i data-lucide="search"></i></button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function refreshIcons(){ if (window.lucide) lucide.createIcons(); }
function icon(name){ return '<i data-lucide="' + name + '"></i>'; }

var _ca='',_ci=0;
function openActionModal(action,id,title,desc){
  _ca=action;_ci=id;
  document.getElementById('modalTitle').textContent=title;
  document.getElementById('modalDesc').textContent=desc;
  document.getElementById('modalNotes').value='';
  var btn=document.getElementById('modalConfirmBtn');
  if(action==='approve'){btn.className='btn btn-navy';btn.innerHTML=icon('check-circle-2')+' Approve';}
  else{btn.className='btn btn-danger';btn.innerHTML=icon('x-circle')+' Reject';}
  refreshIcons();
  document.getElementById('actionModal').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeActionModal(){document.getElementById('actionModal').classList.remove('open');document.body.style.overflow='';}
function submitAction(){
  document.getElementById('formAction').value=_ca;
  document.getElementById('formAppId').value=_ci;
  document.getElementById('formNotes').value=document.getElementById('modalNotes').value;
  document.getElementById('actionForm').submit();
}
function quickAction(action,id){
  document.getElementById('formAction').value=action;
  document.getElementById('formAppId').value=id;
  document.getElementById('formNotes').value='';
  document.getElementById('actionForm').submit();
}
document.getElementById('actionModal').addEventListener('click',function(e){if(e.target===this)closeActionModal();});

function openDetailModal(d){
  var ic=document.getElementById('dm_icon');
  if(d.service_icon){ic.textContent=d.service_icon;}else{ic.innerHTML=icon('briefcase');}
  document.getElementById('dm_service').textContent=d.service_name;
  document.getElementById('dm_ref').textContent='Application #'+d.id+' · '+d.created_at;
  document.getElementById('dm_av').textContent=d.provider_name.charAt(0).toUpperCase();
  document.getElementById('dm_name').textContent=d.provider_name;
  document.getElementById('dm_contact').textContent=d.provider_phone||d.provider_email||'—';
  document.getElementById('dm_location').textContent=d.location;
  document.getElementById('dm_district').textContent=d.district;
  document.getElementById('dm_availability').textContent=d.availability;
  document.getElementById('dm_pay').textContent=d.expected_pay||'—';
  document.getElementById('dm_languages').textContent=d.languages||'—';
  document.getElementById('dm_date').textContent=d.created_at;
  document.getElementById('dm_experience').textContent=d.experience;
  var pill=document.getElementById('dm_status_pill');
  pill.innerHTML=icon(d.status_icon)+' '+d.status_label;
  pill.className='detail-status-pill '+d.status_class;
  if(d.bio){document.getElementById('dm_bio').textContent=d.bio;document.getElementById('dm_bio_section').style.display='block';}
  else{document.getElementById('dm_bio_section').style.display='none';}
  if(d.agent_notes){document.getElementById('dm_note').textContent=d.agent_notes;document.getElementById('dm_note_section').style.display='block';}
  else{document.getElementById('dm_note_section').style.display='none';}
  var acts=document.getElementById('dm_actions');acts.innerHTML='';
  if(d.status==='pending'||d.status==='under_review'){
    acts.innerHTML='<button class="btn btn-navy" onclick="closeDetailModal();openActionModal(\'approve\','+d.id+',\'Approve Application #'+d.id+'\',\'Optional note.\')">'+icon('check-circle-2')+' Approve</button>'+
    '<button class="btn btn-danger" onclick="closeDetailModal();openActionModal(\'reject\','+d.id+',\'Reject Application #'+d.id+'\',\'Please provide a reason.\')">'+icon('x-circle')+' Reject</button>';
  }
  refreshIcons();
  document.getElementById('detailModal').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeDetailModal(){document.getElementById('detailModal').classList.remove('open');document.body.style.overflow='';}
function handleDetailOverlay(e){if(e.target===document.getElementById('detailModal'))closeDetailModal();}
</script>
<?php include '../includes/dash-end.php'; ?>
