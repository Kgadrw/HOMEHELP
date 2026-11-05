<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('agent');

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $action     = $_POST['action']     ?? '';
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $notes      = sanitize($_POST['notes'] ?? '');

    if ($booking_id) {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE bookings SET status='approved', agent_notes=? WHERE id=? AND agent_id=?");
            $stmt->bind_param('sii', $notes, $booking_id, $user_id); $stmt->execute();
            $b = $conn->query("SELECT client_id FROM bookings WHERE id=$booking_id")->fetch_assoc();
            if ($b) logNotification($b['client_id'], 'booking_approved', 'Your booking has been approved! Our team will be in touch shortly.');
            flashSet('success', "Booking #$booking_id approved.");
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE bookings SET status='rejected', agent_notes=? WHERE id=? AND agent_id=?");
            $stmt->bind_param('sii', $notes, $booking_id, $user_id); $stmt->execute();
            $b = $conn->query("SELECT client_id FROM bookings WHERE id=$booking_id")->fetch_assoc();
            if ($b) logNotification($b['client_id'], 'booking_rejected', 'Your booking has been reviewed. Unfortunately it was not approved at this time.');
            flashSet('success', "Booking #$booking_id rejected.");
        } elseif ($action === 'under_review') {
            $stmt = $conn->prepare("UPDATE bookings SET status='under_review' WHERE id=? AND agent_id=?");
            $stmt->bind_param('ii', $booking_id, $user_id); $stmt->execute();
            flashSet('success', "Booking #$booking_id marked as Under Review.");
        } elseif ($action === 'complete') {
            $stmt = $conn->prepare("UPDATE bookings SET status='completed' WHERE id=? AND agent_id=?");
            $stmt->bind_param('ii', $booking_id, $user_id); $stmt->execute();
            flashSet('success', "Booking #$booking_id marked as Completed.");
        }
    }
    redirect(BASE_URL . '/agent/bookings');
}

$filter  = $_GET['status'] ?? 'all';
$search  = sanitize($_GET['search'] ?? '');
$allowed = ['all','pending','under_review','approved','completed','rejected','cancelled'];
if (!in_array($filter,$allowed)) $filter = 'all';

$cnt_raw = $conn->prepare("SELECT status, COUNT(*) as cnt FROM bookings WHERE agent_id=? GROUP BY status");
$cnt_raw->bind_param('i',$user_id); $cnt_raw->execute();
$counts = ['all'=>0];
foreach ($cnt_raw->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $counts[$r['status']] = (int)$r['cnt']; $counts['all'] += (int)$r['cnt'];
}

$where = "WHERE b.agent_id=?"; $params = [$user_id]; $types = 'i';
if ($filter !== 'all') { $where .= " AND b.status=?"; $params[]=$filter; $types.='s'; }
if ($search) {
    $like = "%$search%";
    $where .= " AND (u.name LIKE ? OR s.name LIKE ? OR b.location LIKE ?)";
    $params[]=$like; $params[]=$like; $params[]=$like; $types.='sss';
}

$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name, s.icon AS service_icon,
           u.name AS client_name, u.phone AS client_phone
    FROM bookings b
    JOIN services s ON b.service_id=s.id
    JOIN users u    ON b.client_id=u.id
    $where ORDER BY b.created_at DESC
");
$stmt->bind_param($types,...$params); $stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$flash = flashGet();
$status_config = [
    'pending'      => ['Pending',      's-pending',      'clock'],
    'under_review' => ['Under Review', 's-under_review', 'search'],
    'approved'     => ['Approved',     's-approved',     'check-circle-2'],
    'completed'    => ['Completed',    's-completed',    'award'],
    'rejected'     => ['Rejected',     's-rejected',     'x-circle'],
    'cancelled'    => ['Cancelled',    's-cancelled',    'ban'],
];

// ── SHELL ─────────────────────────────────────────────────────
$page_title     = 'Bookings';
$page_heading   = 'My Bookings';
$dash_role      = 'agent';
$dash_page      = 'bookings';
$badge_bookings = $counts['pending'] ?? 0;
include '../includes/dash-head.php';
?>
</head>
<body>

<!-- ACTION MODAL -->
<div class="modal-overlay" id="actionModal">
  <div class="modal">
    <h3 id="modalTitle">Confirm Action</h3>
    <p id="modalDesc">Add a note for the client (optional).</p>
    <textarea id="modalNotes" placeholder="Optional note to client..."></textarea>
    <div class="modal-actions">
      <button class="btn btn-outline" onclick="closeActionModal()">Cancel</button>
      <button id="modalConfirmBtn" class="btn btn-navy" onclick="submitAction()">Confirm</button>
    </div>
  </div>
</div>

<!-- VIEW DETAILS MODAL -->
<div class="detail-overlay" id="detailModal" onclick="handleDetailOverlay(event)">
  <div class="detail-box">
    <div class="detail-head">
      <div class="detail-head-left">
        <div class="detail-svc-icon" id="dm_icon"></div>
        <div>
          <div class="detail-svc-name" id="dm_service">Service</div>
          <div class="detail-ref"      id="dm_ref">Booking #</div>
        </div>
      </div>
      <button class="detail-close" onclick="closeDetailModal()" aria-label="Close"><i data-lucide="x"></i></button>
    </div>
    <div class="detail-strip">
      <div class="detail-strip-av" id="dm_client_av">C</div>
      <div>
        <div class="detail-strip-name"    id="dm_client_name">—</div>
        <div class="detail-strip-contact" id="dm_client_contact">—</div>
      </div>
      <span class="detail-status-pill" id="dm_status_pill">Pending</span>
    </div>
    <div class="detail-body">
      <div class="detail-section">
        <div class="detail-section-title"><i data-lucide="clipboard-list"></i> Booking Details</div>
        <div class="detail-grid">
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="map-pin"></i> Location</span><span class="detail-field-value" id="dm_location">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="map"></i> District</span><span class="detail-field-value" id="dm_district">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="calendar"></i> Start Date</span><span class="detail-field-value" id="dm_date">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="clock"></i> Duration</span><span class="detail-field-value" id="dm_duration">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="wallet"></i> Budget</span><span class="detail-field-value" id="dm_budget">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="calendar-plus"></i> Submitted On</span><span class="detail-field-value" id="dm_submitted">—</span></div>
        </div>
      </div>
      <div class="detail-section" id="dm_req_section">
        <div class="detail-section-title"><i data-lucide="file-text"></i> Special Requirements</div>
        <div class="detail-text-block" id="dm_requirements">—</div>
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
  <input type="hidden" name="action"     id="formAction"    value=""/>
  <input type="hidden" name="booking_id" id="formBookingId" value=""/>
  <input type="hidden" name="notes"      id="formNotes"     value=""/>
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
    <h1>My Bookings</h1>
    <p>Review requests assigned to you and keep clients updated.</p>
  </div>
</div>

<div class="filter-bar">
  <div class="filter-tabs">
    <?php
    $tabs = ['all'=>'All','pending'=>'Pending','under_review'=>'Under Review','approved'=>'Approved','completed'=>'Completed','rejected'=>'Rejected'];
    foreach($tabs as $k=>$lbl):
      $cnt = $counts[$k] ?? 0;
      if($k!=='all' && $cnt===0) continue;
    ?>
      <a href="?status=<?= $k ?><?= $search ? '&search='.urlencode($search):'' ?>"
         class="filter-tab <?= $filter===$k?'active':'' ?>">
        <?= $lbl ?> <span class="filter-count"><?= $cnt ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <form method="GET" class="search-wrap">
    <i data-lucide="search"></i>
    <?php if($filter!=='all'): ?><input type="hidden" name="status" value="<?= e($filter) ?>"/><?php endif; ?>
    <input type="text" name="search" placeholder="Search client, service..." value="<?= e($search) ?>" autocomplete="off"/>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="data-table" style="min-width:800px">
      <thead>
        <tr><th>#</th><th>Client</th><th>Service</th><th>Location</th><th>Date</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if(empty($bookings)): ?>
          <tr><td colspan="7" class="empty-row"><i data-lucide="calendar-check"></i> No bookings assigned to you yet.</td></tr>
        <?php else: ?>
          <?php foreach($bookings as $b):
            $sc = $status_config[$b['status']] ?? ['Unknown','s-pending','help-circle'];
            $modal_data = json_encode([
              'id'=>$b['id'],'service_name'=>$b['service_name'],'service_icon'=>$b['service_icon']?:'',
              'status'=>$b['status'],'status_label'=>$sc[0],'status_icon'=>$sc[2],'status_class'=>$sc[1],
              'client_name'=>$b['client_name'],'client_phone'=>$b['client_phone']??'',
              'location'=>$b['location']??'—','district'=>$b['district']??'—',
              'start_date'=>date('d M Y',strtotime($b['start_date'])),
              'duration'=>$b['duration']??'—','budget'=>$b['budget']??'—',
              'requirements'=>$b['requirements']??'','agent_notes'=>$b['agent_notes']??'',
              'created_at'=>date('d M Y',strtotime($b['created_at'])),
            ], JSON_HEX_QUOT|JSON_HEX_TAG);
          ?>
            <tr>
              <td class="id-cell">#<?= $b['id'] ?></td>
              <td>
                <div class="cell-user">
                  <div class="cell-av"><?= e(strtoupper(substr($b['client_name'],0,1))) ?></div>
                  <div>
                    <div class="cell-name"><?= e($b['client_name']) ?></div>
                    <div class="cell-sub"><?= e($b['client_phone']??'') ?></div>
                  </div>
                </div>
              </td>
              <td><span class="meta-inline" style="font-size:.8rem"><?php if($b['service_icon']): ?><?= e($b['service_icon']) ?><?php else: ?><i data-lucide="home"></i><?php endif; ?> <?= e($b['service_name']) ?></span></td>
              <td class="muted-cell" style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($b['location']) ?></td>
              <td class="date-cell"><?= date('d M Y',strtotime($b['start_date'])) ?></td>
              <td><span class="status-pill <?= $sc[1] ?>"><i data-lucide="<?= $sc[2] ?>"></i> <?= $sc[0] ?></span></td>
              <td>
                <div class="actions-cell">
                  <button class="act-btn act-view" onclick="openDetailModal(<?= htmlspecialchars($modal_data,ENT_QUOTES) ?>)"><i data-lucide="eye"></i> View</button>
                  <?php if($b['status']==='pending'||$b['status']==='under_review'): ?>
                    <button class="act-btn act-approve" title="Approve" onclick="openActionModal('approve',<?= $b['id'] ?>,'Approve Booking #<?= $b['id'] ?>','Optional note for the client.')"><i data-lucide="check-circle-2"></i></button>
                    <button class="act-btn act-reject"  title="Reject"  onclick="openActionModal('reject', <?= $b['id'] ?>,'Reject Booking #<?= $b['id'] ?>','Please provide a reason.')"><i data-lucide="x-circle"></i></button>
                  <?php endif; ?>
                  <?php if($b['status']==='pending'): ?>
                    <button class="act-btn act-review" title="Mark Under Review" onclick="quickAction('under_review',<?= $b['id'] ?>)"><i data-lucide="search"></i></button>
                  <?php endif; ?>
                  <?php if($b['status']==='approved'): ?>
                    <button class="act-btn act-complete" title="Mark Completed" onclick="quickAction('complete',<?= $b['id'] ?>)"><i data-lucide="award"></i></button>
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
  document.getElementById('formBookingId').value=_ci;
  document.getElementById('formNotes').value=document.getElementById('modalNotes').value;
  document.getElementById('actionForm').submit();
}
function quickAction(action,id){
  document.getElementById('formAction').value=action;
  document.getElementById('formBookingId').value=id;
  document.getElementById('formNotes').value='';
  document.getElementById('actionForm').submit();
}
document.getElementById('actionModal').addEventListener('click',function(e){if(e.target===this)closeActionModal();});

function openDetailModal(d){
  var ic=document.getElementById('dm_icon');
  if(d.service_icon){ic.textContent=d.service_icon;}else{ic.innerHTML=icon('home');}
  document.getElementById('dm_service').textContent=d.service_name;
  document.getElementById('dm_ref').textContent='Booking #'+d.id+' · '+d.created_at;
  document.getElementById('dm_client_av').textContent=d.client_name.charAt(0).toUpperCase();
  document.getElementById('dm_client_name').textContent=d.client_name;
  document.getElementById('dm_client_contact').textContent=d.client_phone||'—';
  document.getElementById('dm_location').textContent=d.location;
  document.getElementById('dm_district').textContent=d.district;
  document.getElementById('dm_date').textContent=d.start_date;
  document.getElementById('dm_duration').textContent=d.duration;
  document.getElementById('dm_budget').textContent=d.budget||'—';
  document.getElementById('dm_submitted').textContent=d.created_at;
  var pill=document.getElementById('dm_status_pill');
  pill.innerHTML=icon(d.status_icon)+' '+d.status_label;
  pill.className='detail-status-pill '+d.status_class;
  if(d.requirements){document.getElementById('dm_requirements').textContent=d.requirements;document.getElementById('dm_req_section').style.display='block';}
  else{document.getElementById('dm_req_section').style.display='none';}
  if(d.agent_notes){document.getElementById('dm_note').textContent=d.agent_notes;document.getElementById('dm_note_section').style.display='block';}
  else{document.getElementById('dm_note_section').style.display='none';}
  var acts=document.getElementById('dm_actions');acts.innerHTML='';
  if(d.status==='pending'||d.status==='under_review'){
    acts.innerHTML='<button class="btn btn-navy" onclick="closeDetailModal();openActionModal(\'approve\','+d.id+',\'Approve Booking #'+d.id+'\',\'Optional note for the client.\')">'+icon('check-circle-2')+' Approve</button>'+
    '<button class="btn btn-danger" onclick="closeDetailModal();openActionModal(\'reject\','+d.id+',\'Reject Booking #'+d.id+'\',\'Please provide a reason.\')">'+icon('x-circle')+' Reject</button>';
  }
  if(d.status==='approved'){acts.innerHTML='<button class="btn btn-navy" onclick="closeDetailModal();quickAction(\'complete\','+d.id+')">'+icon('award')+' Complete</button>';}
  refreshIcons();
  document.getElementById('detailModal').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeDetailModal(){document.getElementById('detailModal').classList.remove('open');document.body.style.overflow='';}
function handleDetailOverlay(e){if(e.target===document.getElementById('detailModal'))closeDetailModal();}
</script>
<?php include '../includes/dash-end.php'; ?>
