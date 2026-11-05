<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('admin');

// Prevent browser caching so new bookings always show immediately
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$admin_name = $_SESSION['user_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $action     = $_POST['action']     ?? '';
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $notes      = sanitize($_POST['notes']   ?? '');
    $agent_id   = (int)($_POST['agent_id']   ?? 0);

    if ($booking_id) {
        // ── LOAD MAILER ───────────────────────────────────────
        if (!function_exists('sendBookingEmail')) {
            require_once '../includes/mailer.php';
        }
        // ── FETCH BOOKING + CLIENT INFO FOR ALL ACTIONS ───────
        $info = $conn->query("
            SELECT b.selected_services,
                   u.name AS client_name, u.email AS client_email,
                   s.name AS service_name
            FROM bookings b
            JOIN users u    ON b.client_id  = u.id
            JOIN services s ON b.service_id = s.id
            WHERE b.id = $booking_id LIMIT 1
        ")->fetch_assoc();
        $service_label = '';
        if ($info) {
            $svc_names = [];
            if (!empty($info['selected_services'])) {
                $svc_ids = array_filter(array_map('intval', explode(',', $info['selected_services'])));
                if ($svc_ids) {
                    $sv_res = $conn->query("SELECT name FROM services WHERE id IN (".implode(',', $svc_ids).")");
                    while ($sv = $sv_res->fetch_assoc()) $svc_names[] = $sv['name'];
                }
            }
            $service_label = $svc_names ? implode(' + ', $svc_names) : ($info['service_name'] ?? '');
        }

        // ── PENDING → REVIEW (triggered automatically when Admin opens View) ──
        if ($action === 'under_review' || $action === 'view_open') {
            // Only transition if currently pending
            $cur = $conn->query("SELECT status, client_id FROM bookings WHERE id=$booking_id")->fetch_assoc();
            if ($cur && $cur['status'] === 'pending') {
                $stmt = $conn->prepare("UPDATE bookings SET status='under_review' WHERE id=?");
                $stmt->bind_param('i', $booking_id); $stmt->execute();
                logNotification($cur['client_id'], 'booking_review', 'Your booking is under review. Our team will contact you shortly.');
                if ($info && !empty($info['client_email'])) {
                    sendBookingEmail(
                        $info['client_email'],
                        'Your HomeHelp Rwanda Request is Under Review',
                        buildReviewEmailHTML(['client_name'=>$info['client_name'],'service_name'=>$service_label,'booking_id'=>$booking_id])
                    );
                }
            }
            if ($action === 'view_open') {
                // Return JSON for AJAX call from View button
                header('Content-Type: application/json');
                echo json_encode(['ok' => true]);
                exit;
            }
            flashSet('success', "Booking #$booking_id is now Under Review. Client notified by email.");

        // ── REVIEW → COMPLETED ────────────────────────────────
        } elseif ($action === 'complete') {
            $stmt = $conn->prepare("UPDATE bookings SET status='completed', agent_notes=? WHERE id=?");
            $stmt->bind_param('si', $notes, $booking_id); $stmt->execute();
            $b = $conn->query("SELECT client_id FROM bookings WHERE id=$booking_id")->fetch_assoc();
            if ($b) logNotification($b['client_id'], 'booking_completed', 'Great news! A HomeHelp worker has been assigned to your request. We will be in contact shortly.');
            if ($info && !empty($info['client_email'])) {
                sendBookingEmail(
                    $info['client_email'],
                    '✅ Great News! A HomeHelp Worker Has Been Assigned — Home Help Rwanda',
                    buildCompletedEmailHTML(['client_name'=>$info['client_name'],'service_name'=>$service_label,'booking_id'=>$booking_id,'agent_note'=>$notes])
                );
            }
            flashSet('success', "Booking #$booking_id marked as Completed. Client notified by email.");

        // ── REVIEW → HOLD ─────────────────────────────────────
        } elseif ($action === 'hold') {
            $stmt = $conn->prepare("UPDATE bookings SET status='hold', agent_notes=? WHERE id=?");
            $stmt->bind_param('si', $notes, $booking_id); $stmt->execute();
            $b = $conn->query("SELECT client_id FROM bookings WHERE id=$booking_id")->fetch_assoc();
            if ($b) logNotification($b['client_id'], 'booking_hold', 'Unfortunately your request could not proceed at this time. Please contact us for more information.');
            if ($info && !empty($info['client_email'])) {
                sendBookingEmail(
                    $info['client_email'],
                    'Regarding Your HomeHelp Rwanda Request — Important Update',
                    buildHoldEmailHTML(['client_name'=>$info['client_name'],'service_name'=>$service_label,'booking_id'=>$booking_id,'agent_note'=>$notes])
                );
            }
            flashSet('success', "Booking #$booking_id placed on Hold. Client notified by email.");

        // ── APPROVE (kept for compatibility) ──────────────────
        } elseif ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE bookings SET status='approved', agent_notes=? WHERE id=?");
            $stmt->bind_param('si', $notes, $booking_id); $stmt->execute();
            $b = $conn->query("SELECT client_id FROM bookings WHERE id=$booking_id")->fetch_assoc();
            if ($b) logNotification($b['client_id'], 'booking_approved', 'Your booking has been approved!');
            if ($info && !empty($info['client_email'])) {
                sendBookingEmail(
                    $info['client_email'],
                    '✅ Your Booking Has Been Approved — Home Help Rwanda',
                    buildApprovalEmailHTML(['client_name'=>$info['client_name'],'service_name'=>$service_label,'booking_id'=>$booking_id,'agent_note'=>$notes])
                );
            }
            flashSet('success', "Booking #$booking_id approved. Client notified by email.");

        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE bookings SET status='rejected', agent_notes=? WHERE id=?");
            $stmt->bind_param('si', $notes, $booking_id); $stmt->execute();
            $b = $conn->query("SELECT client_id FROM bookings WHERE id=$booking_id")->fetch_assoc();
            if ($b) logNotification($b['client_id'], 'booking_rejected', 'Your booking was not approved. Please contact us for more information.');
            flashSet('success', "Booking #$booking_id rejected.");

        } elseif ($action === 'assign_agent' && $agent_id) {
            $stmt = $conn->prepare("UPDATE bookings SET agent_id=? WHERE id=?");
            $stmt->bind_param('ii', $agent_id, $booking_id); $stmt->execute();
            flashSet('success', "Team member assigned to Booking #$booking_id.");
        }
    }
    redirect(BASE_URL . '/admin/bookings');
}

$filter  = $_GET['status'] ?? 'all';
$search  = sanitize($_GET['search'] ?? '');
$allowed = ['all','pending','under_review','completed','hold','approved','rejected','cancelled'];
if (!in_array($filter, $allowed)) $filter = 'all';

$cnt_stmt = $conn->query("SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status");
$counts = ['all' => 0];
foreach ($cnt_stmt->fetch_all(MYSQLI_ASSOC) as $r) {
    $counts[$r['status']] = (int)$r['cnt']; $counts['all'] += (int)$r['cnt'];
}

$where = "WHERE 1=1"; $params = []; $types = '';
if ($filter !== 'all') { $where .= " AND b.status = ?"; $params[] = $filter; $types .= 's'; }
if ($search) {
    $like = "%$search%";
    $where .= " AND (u.name LIKE ? OR s.name LIKE ? OR b.location LIKE ? OR b.neighbourhood LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'ssss';
}

// ── FULL SELECT including all wizard columns ──────────────────
$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name, s.icon AS service_icon,
           u.name AS client_name, u.phone AS client_phone, u.email AS client_email,
           a.name AS agent_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    JOIN users u    ON b.client_id  = u.id
    LEFT JOIN users a ON b.agent_id = a.id
    $where ORDER BY b.created_at DESC
");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$agents_list = $conn->query("SELECT u.id, u.name FROM users u WHERE u.role='agent' AND u.status='active' ORDER BY u.name")->fetch_all(MYSQLI_ASSOC);

$flash = flashGet();
$notif_count = unreadNotifCount();

// [label, css class, lucide icon]
$status_config = [
    'pending'      => ['Pending',      's-pending',      'clock'],
    'under_review' => ['Under Review', 's-under_review', 'search'],
    'completed'    => ['Completed',    's-completed',    'award'],
    'hold'         => ['On Hold',      's-hold',         'pause-circle'],
    'approved'     => ['Approved',     's-approved',     'check-circle-2'],
    'rejected'     => ['Rejected',     's-rejected',     'x-circle'],
    'cancelled'    => ['Cancelled',    's-cancelled',    'ban'],
];

// ── SHELL ─────────────────────────────────────────────────────
$page_title   = 'Bookings';
$page_heading = 'Bookings';
$dash_role    = 'admin';
$dash_page    = 'bookings';
$user_name    = $admin_name;
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

    <div class="detail-quick">
      <div class="detail-quick-inner">
        <div>
          <div class="detail-quick-label">Quick Contact</div>
          <div style="font-size:.72rem;color:rgba(255,255,255,.55)">Reach out directly about this booking</div>
        </div>
        <a href="#" id="dm_call_link"><i data-lucide="phone"></i> <span id="dm_call_text">—</span></a>
      </div>
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
        <div class="detail-section-title"><i data-lucide="sparkles"></i> Services Requested</div>
        <div id="dm_services_tags" class="detail-tags"></div>
      </div>

      <div class="detail-section">
        <div class="detail-section-title"><i data-lucide="clipboard-list"></i> Booking Details</div>
        <div class="detail-grid">
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="repeat"></i> Frequency</span><span class="detail-field-value" id="dm_frequency">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="clock"></i> Duration</span><span class="detail-field-value" id="dm_duration">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="map-pin"></i> Neighbourhood</span><span class="detail-field-value" id="dm_neighbourhood">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="map"></i> District</span><span class="detail-field-value" id="dm_district">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="home"></i> Accommodation</span><span class="detail-field-value" id="dm_accomm">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="wallet"></i> Budget</span><span class="detail-field-value" id="dm_budget">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="user"></i> Assigned Team</span><span class="detail-field-value" id="dm_agent">—</span></div>
          <div class="detail-field"><span class="detail-field-label"><i data-lucide="calendar"></i> Submitted On</span><span class="detail-field-value" id="dm_submitted">—</span></div>
        </div>
      </div>

      <div class="detail-section" id="dm_cleaning_section">
        <div class="detail-section-title"><i data-lucide="door-open"></i> Property Details</div>
        <div class="detail-grid-3" style="margin-bottom:12px">
          <div class="detail-field"><span class="detail-field-label">Rooms</span><span class="detail-field-value" id="dm_rooms">—</span></div>
        </div>
        <div class="detail-checklist" id="dm_checklist"></div>
      </div>

      <div class="detail-section" id="dm_chef_section">
        <div class="detail-section-title"><i data-lucide="chef-hat"></i> Chef Details</div>
        <div class="detail-grid">
          <div class="detail-field"><span class="detail-field-label">Chef Type</span><span class="detail-field-value" id="dm_chef_type">—</span></div>
          <div class="detail-field"><span class="detail-field-label">Number of People</span><span class="detail-field-value" id="dm_people">—</span></div>
          <div class="detail-field"><span class="detail-field-label">Number of Adults</span><span class="detail-field-value" id="dm_adults">—</span></div>
          <div class="detail-field"><span class="detail-field-label">Menu Needed</span><span class="detail-field-value" id="dm_menu">—</span></div>
        </div>
      </div>

      <div class="detail-section">
        <div class="detail-section-title"><i data-lucide="languages"></i> Language Preference</div>
        <div id="dm_lang_tags" class="detail-tags"></div>
      </div>

      <div class="detail-section" id="dm_req_section">
        <div class="detail-section-title"><i data-lucide="file-text"></i> Special Requirements</div>
        <div class="detail-text-block" id="dm_requirements">—</div>
      </div>

      <div class="detail-section" id="dm_note_section" style="display:none">
        <div class="detail-section-title"><i data-lucide="message-square"></i> Team Note to Client</div>
        <div class="detail-note-block" id="dm_note">—</div>
      </div>

    </div>

    <div class="detail-foot">
      <button class="btn btn-outline" onclick="closeDetailModal()">Close</button>
      <div class="detail-foot-actions" id="dm_actions"></div>
    </div>
  </div>
</div>

<!-- Hidden forms -->
<form method="POST" id="actionForm" style="display:none">
  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
  <input type="hidden" name="action"     id="formAction"    value=""/>
  <input type="hidden" name="booking_id" id="formBookingId" value=""/>
  <input type="hidden" name="notes"      id="formNotes"     value=""/>
</form>

<?php foreach($bookings as $b): ?>
<form method="POST" id="assignForm<?= $b['id'] ?>" style="display:none">
  <input type="hidden" name="csrf_token"  value="<?= generateCSRFToken() ?>"/>
  <input type="hidden" name="action"      value="assign_agent"/>
  <input type="hidden" name="booking_id"  value="<?= $b['id'] ?>"/>
  <input type="hidden" name="agent_id"    id="agentVal<?= $b['id'] ?>" value=""/>
</form>
<?php endforeach; ?>

<?php include '../includes/dash-sidebar.php'; ?>

<?php if($flash): ?>
  <div class="flash flash-<?= e($flash['type']) ?>">
    <i data-lucide="<?= $flash['type']==='success' ? 'check-circle-2' : 'alert-triangle' ?>"></i>
    <span><?= e($flash['message']) ?></span>
  </div>
<?php endif; ?>

<div class="page-head">
  <div>
    <h1>All Bookings</h1>
    <p>Review client requests, assign team members and track progress.</p>
  </div>
</div>

<div class="filter-bar">
  <div class="filter-tabs">
    <?php
    $tabs = ['all'=>'All','pending'=>'Pending','under_review'=>'Under Review','completed'=>'Completed','hold'=>'On Hold','approved'=>'Approved','rejected'=>'Rejected'];
    foreach($tabs as $k => $lbl):
      $cnt = $counts[$k] ?? 0;
      if($k !== 'all' && $cnt === 0) continue;
    ?>
      <a href="?status=<?= $k ?><?= $search ? '&search='.urlencode($search) : '' ?>"
         class="filter-tab <?= $filter===$k ? 'active':'' ?>">
        <?= $lbl ?> <span class="filter-count"><?= $cnt ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <form method="GET" class="search-wrap">
    <i data-lucide="search"></i>
    <?php if($filter !== 'all'): ?><input type="hidden" name="status" value="<?= e($filter) ?>"/><?php endif; ?>
    <input type="text" name="search" placeholder="Search client, service..."
      value="<?= e($search) ?>" autocomplete="off"/>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="data-table" style="min-width:900px">
      <thead>
        <tr>
          <th>#</th>
          <th>Client</th>
          <th>Service</th>
          <th>Location</th>
          <th>Date</th>
          <th>Team</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($bookings)): ?>
          <tr><td colspan="8" class="empty-row"><i data-lucide="calendar-x"></i> No bookings found<?= $search ? ' for "'.e($search).'"' : '' ?>.</td></tr>
        <?php else: ?>
          <?php foreach($bookings as $b):
            $sc = $status_config[$b['status']] ?? ['Unknown','s-pending','help-circle'];

            // Service names from selected_services IDs
            $svc_names = [];
            if (!empty($b['selected_services'])) {
                $svc_ids = array_filter(array_map('intval', explode(',', $b['selected_services'])));
                if ($svc_ids) {
                    $svc_result = $conn->query("SELECT name FROM services WHERE id IN (".implode(',', $svc_ids).")");
                    while($sv = $svc_result->fetch_assoc()) $svc_names[] = $sv['name'];
                }
            }
            if (empty($svc_names)) $svc_names[] = $b['service_name'];

            // Checklist
            $checklist = [];
            if (!empty($b['outdoor_areas'])) $checklist[] = 'Outdoor areas';
            if (!empty($b['has_car']))       $checklist[] = 'Car cleaning';
            if (!empty($b['has_pool']))      $checklist[] = 'Has pool';
            if (!empty($b['dish_washing']))  $checklist[] = 'Dish washing';
            if (!empty($b['has_pets']))      $checklist[] = 'Pets';
            if (!empty($b['laundry']))        $checklist[] = 'Laundry & ironing';

            // Accommodation
            $accomm = '—';
            if (!empty($b['live_in']))  $accomm = 'Live In';
            if (!empty($b['live_out'])) $accomm = 'Live Out' . (!empty($b['living_area']) ? ' — '.$b['living_area'] : '');

            $svc_ids_check = array_map('intval', explode(',', $b['selected_services'] ?: '0'));
            $has_cleaning  = (in_array(6, $svc_ids_check) || in_array(7, $svc_ids_check));
            $has_chef      = in_array(9, $svc_ids_check);

            $modal_data = json_encode([
              'id'                => $b['id'],
              'service_name'      => implode(' + ', $svc_names),
              'service_icon'      => serviceLucideIcon($b['service_icon'] ?? '', $b['service_name'] ?? ''),
              'status'            => $b['status'],
              'status_label'      => $sc[0],
              'status_icon'       => $sc[2],
              'status_class'      => $sc[1],
              'client_name'       => $b['client_name'],
              'client_phone'      => $b['client_phone'] ?? '',
              'client_email'      => $b['client_email'] ?? '',
              'services'          => $svc_names,
              'frequency'         => $b['frequency']     ?: '—',
              'duration'          => $b['duration']      ?: '—',
              'neighbourhood'     => $b['neighbourhood'] ?: '—',
              'district'          => $b['district']      ?: '—',
              'accomm'            => $accomm,
              'budget'            => $b['budget'] ?: '—',
              'agent_name'        => $b['agent_name'] ?? 'Not assigned yet',
              'agent_notes'       => $b['agent_notes']  ?? '',
              'rooms'             => $b['rooms']         ?: '—',
              'checklist'         => $checklist,
              'chef_type'         => $b['chef_type']    ?: '',
              'num_people'        => $b['num_people']   ?: '—',
              'num_adults'        => $b['num_adults']   ?: '—',
              'needs_menu'        => !empty($b['needs_menu']) ? 'Yes' : 'No',
              'languages'         => $b['languages']    ?: '',
              'requirements'      => $b['requirements'] ?? '',
              'created_at'        => date('d M Y', strtotime($b['created_at'])),
              'has_cleaning'      => $has_cleaning,
              'has_chef'          => $has_chef,
            ], JSON_HEX_QUOT | JSON_HEX_TAG);
          ?>
            <tr>
              <td class="id-cell">#<?= $b['id'] ?></td>
              <td>
                <div class="cell-user">
                  <div class="cell-av"><?= e(strtoupper(substr($b['client_name'],0,1))) ?></div>
                  <div>
                    <div class="cell-name"><?= e($b['client_name']) ?></div>
                    <div class="cell-sub"><?= e($b['client_phone'] ?? '') ?></div>
                  </div>
                </div>
              </td>
              <td><span class="service-chip"><i data-lucide="<?= e(serviceLucideIcon($b['service_icon'] ?? '', $b['service_name'] ?? '')) ?>"></i> <?= e(implode(' + ', $svc_names)) ?></span></td>
              <td><div class="cell-name trunc"><?= e($b['neighbourhood'] ?: $b['location'] ?: '—') ?></div></td>
              <td class="date-cell"><?= date('d M Y', strtotime($b['created_at'])) ?></td>
              <td>
                <?php if($b['agent_name']): ?>
                  <span class="cell-name" style="display:inline-flex;align-items:center;gap:6px"><i data-lucide="user"></i> <?= e($b['agent_name']) ?></span>
                <?php elseif(!empty($agents_list)): ?>
                  <div class="inline-assign">
                    <select class="mini-select" id="agentSel<?= $b['id'] ?>">
                      <option value="">Assign...</option>
                      <?php foreach($agents_list as $ag): ?>
                        <option value="<?= $ag['id'] ?>"><?= e($ag['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn-mini" onclick="assignAgent(<?= $b['id'] ?>)">Go</button>
                  </div>
                <?php else: ?>
                  <span class="muted-cell">No team members yet</span>
                <?php endif; ?>
              </td>
              <td><span class="status-pill <?= $sc[1] ?>"><i data-lucide="<?= $sc[2] ?>"></i> <?= $sc[0] ?></span></td>
              <td>
                <div class="actions-cell">
                  <button class="act-btn act-view" onclick='openDetailModal(<?= $modal_data ?>)'><i data-lucide="eye"></i> View</button>
                  <?php if($b['status']==='under_review'): ?>
                    <button class="act-btn act-complete" onclick="openActionModal('complete',<?= $b['id'] ?>,'Complete Booking #<?= $b['id'] ?>','Add a note for the client (optional).')" title="Mark as Completed"><i data-lucide="award"></i> Complete</button>
                    <button class="act-btn act-hold" onclick="openActionModal('hold',<?= $b['id'] ?>,'Put Booking #<?= $b['id'] ?> on Hold','Please explain why this request is being closed.')" title="Put on Hold"><i data-lucide="pause-circle"></i> Hold</button>
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

// ACTION MODAL
var _ca='',_ci=0;
function openActionModal(action,id,title,desc){
  _ca=action;_ci=id;
  document.getElementById('modalTitle').textContent=title;
  document.getElementById('modalDesc').textContent=desc;
  document.getElementById('modalNotes').value='';
  var btn=document.getElementById('modalConfirmBtn');
  if(action==='complete'){
    btn.className='btn btn-navy';
    btn.innerHTML=icon('award')+' Complete';
  } else if(action==='hold'){
    btn.className='btn btn-danger';
    btn.innerHTML=icon('pause-circle')+' Confirm Hold';
  } else if(action==='approve'){
    btn.className='btn btn-navy';
    btn.innerHTML=icon('check-circle-2')+' Approve';
  } else {
    btn.className='btn btn-navy';
    btn.textContent='Confirm';
  }
  refreshIcons();
  document.getElementById('actionModal').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeActionModal(){document.getElementById('actionModal').classList.remove('open');document.body.style.overflow='';}
function submitAction(){
  document.getElementById('formAction').value=_ca;
  document.getElementById('formBookingId').value=_ci;
  document.getElementById('formNotes').value=document.getElementById('modalNotes').value;
  _statusChanged = false; // form submit reloads page anyway
  document.getElementById('actionForm').submit();
}
function quickAction(action,id){
  document.getElementById('formAction').value=action;
  document.getElementById('formBookingId').value=id;
  document.getElementById('formNotes').value='';
  document.getElementById('actionForm').submit();
}
function assignAgent(id){
  var sel=document.getElementById('agentSel'+id);
  if(!sel.value){alert('Please select a team member first.');return;}
  document.getElementById('agentVal'+id).value=sel.value;
  document.getElementById('assignForm'+id).submit();
}
document.getElementById('actionModal').addEventListener('click',function(e){if(e.target===this)closeActionModal();});

// VIEW DETAILS MODAL
function footActions(d){
  return '<button class="btn btn-navy" onclick="closeDetailModal();openActionModal(\'complete\',' + d.id + ',\'Complete Booking #' + d.id + '\',\'You may add a note for the client (optional).\')">' + icon('award') + ' Complete</button>' +
         '<button class="btn btn-danger" onclick="closeDetailModal();openActionModal(\'hold\',' + d.id + ',\'Close Booking #' + d.id + '\',\'Please explain why this request cannot proceed.\')">' + icon('pause-circle') + ' Hold</button>';
}
function setPill(el, cls, iconName, label){
  el.className = 'detail-status-pill ' + cls;
  el.innerHTML = icon(iconName) + ' ' + label;
}
function openDetailModal(d){
  var ic = document.getElementById('dm_icon');
  ic.innerHTML = icon(d.service_icon || 'briefcase');
  document.getElementById('dm_service').textContent       = d.service_name;
  document.getElementById('dm_ref').textContent           = 'Booking #' + d.id + ' · ' + d.created_at;
  document.getElementById('dm_client_av').textContent     = d.client_name.charAt(0).toUpperCase();
  document.getElementById('dm_client_name').textContent   = d.client_name;
  document.getElementById('dm_client_contact').textContent= d.client_phone || d.client_email || '—';

  // Quick contact
  var callLink = document.getElementById('dm_call_link');
  if (d.client_phone) {
    callLink.href = 'tel:' + d.client_phone.replace(/\s+/g, '');
    document.getElementById('dm_call_text').textContent = d.client_phone;
  } else {
    callLink.removeAttribute('href');
    document.getElementById('dm_call_text').textContent = 'No phone on file';
  }

  // Status pill
  setPill(document.getElementById('dm_status_pill'), d.status_class, d.status_icon, d.status_label);

  // Services tags
  var stags = document.getElementById('dm_services_tags');
  stags.innerHTML = '';
  (d.services || [d.service_name]).forEach(function(s){
    var span = document.createElement('span');
    span.className = 'detail-tag';
    span.textContent = s;
    stags.appendChild(span);
  });

  // Booking basics
  document.getElementById('dm_frequency').textContent     = d.frequency;
  document.getElementById('dm_duration').textContent      = d.duration;
  document.getElementById('dm_neighbourhood').textContent = d.neighbourhood;
  document.getElementById('dm_district').textContent      = d.district;
  document.getElementById('dm_accomm').textContent        = d.accomm;
  document.getElementById('dm_budget').textContent        = d.budget;
  document.getElementById('dm_agent').textContent         = d.agent_name;
  document.getElementById('dm_submitted').textContent     = d.created_at;

  // Cleaning/Housekeeping section
  var cleanSec = document.getElementById('dm_cleaning_section');
  if (d.has_cleaning) {
    cleanSec.style.display = 'block';
    document.getElementById('dm_rooms').textContent = d.rooms;
    var cl = document.getElementById('dm_checklist');
    cl.innerHTML = '';
    (d.checklist || []).forEach(function(item){
      var div = document.createElement('div');
      div.className = 'detail-check';
      div.innerHTML = icon('check-circle-2') + ' ';
      div.appendChild(document.createTextNode(item));
      cl.appendChild(div);
    });
    if (!d.checklist || !d.checklist.length) {
      var div = document.createElement('div');
      div.className = 'detail-check';
      div.textContent = 'No extras selected';
      cl.appendChild(div);
    }
  } else {
    cleanSec.style.display = 'none';
  }

  // Chef section
  var chefSec = document.getElementById('dm_chef_section');
  if (d.has_chef && d.chef_type) {
    chefSec.style.display = 'block';
    document.getElementById('dm_chef_type').textContent = d.chef_type === 'events' ? 'Chef for Events' : 'Chef/Cook for Home';
    document.getElementById('dm_people').textContent    = d.num_people;
    document.getElementById('dm_adults').textContent    = d.num_adults;
    document.getElementById('dm_menu').textContent      = d.needs_menu;
  } else {
    chefSec.style.display = 'none';
  }

  // Languages
  var ltags = document.getElementById('dm_lang_tags');
  ltags.innerHTML = '';
  if (d.languages) {
    d.languages.split(',').forEach(function(l){
      l = l.trim();
      if (!l) return;
      var span = document.createElement('span');
      span.className = 'detail-tag';
      span.textContent = l;
      ltags.appendChild(span);
    });
  } else {
    ltags.innerHTML = '<span class="muted-cell" style="font-style:italic">No preference specified</span>';
  }

  // Requirements
  if(d.requirements){
    document.getElementById('dm_requirements').textContent = d.requirements;
    document.getElementById('dm_req_section').style.display = 'block';
  } else {
    document.getElementById('dm_req_section').style.display = 'none';
  }

  // Team note
  if(d.agent_notes){
    document.getElementById('dm_note').textContent = d.agent_notes;
    document.getElementById('dm_note_section').style.display = 'block';
  } else {
    document.getElementById('dm_note_section').style.display = 'none';
  }

  // Footer actions — strictly follow workflow
  var acts = document.getElementById('dm_actions');
  acts.innerHTML = '';
  if(d.status==='under_review'){
    // Review: show Complete and Hold buttons only
    acts.innerHTML = footActions(d);
  }
  // Pending, Completed, Hold, Approved, Rejected: no action buttons in modal footer

  // If opening a PENDING booking → auto-transition to under_review via AJAX
  if(d.status === 'pending'){
    var fd = new FormData();
    fd.append('action',     'view_open');
    fd.append('booking_id', d.id);
    fd.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
    fetch('', {method:'POST', body: fd})
      .then(function(r){ return r.json(); })
      .then(function(){
        // Mark that status changed so page reloads on modal close
        _statusChanged = true;
        // Update badge immediately in modal
        setPill(document.getElementById('dm_status_pill'), 's-under_review', 'search', 'Under Review');
        // Show Complete + Hold buttons
        acts.innerHTML = footActions(d);
        refreshIcons();
      })
      .catch(function(){});
  }

  refreshIcons();
  document.getElementById('detailModal').classList.add('open');
  document.body.style.overflow='hidden';
}
var _statusChanged = false;
function closeDetailModal(){
  document.getElementById('detailModal').classList.remove('open');
  document.body.style.overflow='';
  if(_statusChanged){
    _statusChanged = false;
    window.location.reload();
  }
}
function handleDetailOverlay(e){if(e.target===document.getElementById('detailModal'))closeDetailModal();}
</script>
<?php include '../includes/dash-end.php'; ?>
