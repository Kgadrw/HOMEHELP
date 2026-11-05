<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('client');

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ── CANCEL BOOKING ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    verifyCSRF();
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND client_id = ? AND status = 'pending'");
    $stmt->bind_param('ii', $booking_id, $user_id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        flashSet('success', 'Booking cancelled successfully.');
    } else {
        flashSet('error', 'This booking cannot be cancelled. Only pending bookings can be cancelled.');
    }
    redirect(BASE_URL . '/my-bookings');
}

// ── FILTER ────────────────────────────────────────────────────
$filter = $_GET['status'] ?? 'all';
$allowed_filters = ['all','pending','under_review','completed','hold','approved','rejected','cancelled'];
if (!in_array($filter, $allowed_filters)) $filter = 'all';

// ── FETCH BOOKINGS (all wizard columns) ───────────────────────
$where  = "WHERE b.client_id = ?";
$params = [$user_id];
$types  = 'i';
if ($filter !== 'all') { $where .= " AND b.status = ?"; $params[] = $filter; $types .= 's'; }

$stmt = $conn->prepare("
    SELECT b.*,
           s.name AS service_name,
           s.icon AS service_icon,
           u.name AS team_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    LEFT JOIN users u ON b.agent_id = u.id
    $where
    ORDER BY b.created_at DESC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── COUNT PER STATUS ──────────────────────────────────────────
$count_stmt = $conn->prepare("SELECT status, COUNT(*) as cnt FROM bookings WHERE client_id = ? GROUP BY status");
$count_stmt->bind_param('i', $user_id);
$count_stmt->execute();
$counts_raw = $count_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$counts = ['all' => 0];
foreach ($counts_raw as $row) {
    $counts[$row['status']] = (int)$row['cnt'];
    $counts['all'] += (int)$row['cnt'];
}

$flash       = flashGet();

// Icons are Lucide names (rendered via <i data-lucide>)
$status_config = [
    'pending'      => ['label'=>'Pending',      'color'=>'#B45309','bg'=>'#FFFBEB','icon'=>'clock'],
    'under_review' => ['label'=>'Under Review', 'color'=>'#1D4ED8','bg'=>'#EFF6FF','icon'=>'search'],
    'completed'    => ['label'=>'Completed',    'color'=>'#0A1F6B','bg'=>'#D4DCF5','icon'=>'trophy'],
    'hold'         => ['label'=>'On Hold',      'color'=>'#B45309','bg'=>'#FFFBEB','icon'=>'circle-pause'],
    'approved'     => ['label'=>'Approved',     'color'=>'#047857','bg'=>'#ECFDF5','icon'=>'circle-check'],
    'rejected'     => ['label'=>'Rejected',     'color'=>'#B91C1C','bg'=>'#FEF2F2','icon'=>'circle-x'],
    'cancelled'    => ['label'=>'Cancelled',    'color'=>'#6B7280','bg'=>'#F3F4F6','icon'=>'ban'],
];

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

$page_title = 'My Bookings';
include '../includes/dash-head.php';
?>
<style>
.page-intro{margin-bottom:20px}
.page-intro h1{font-size:1.35rem;font-weight:800;color:var(--navy);letter-spacing:-.02em;margin-bottom:3px}
.page-intro p{font-size:.88rem;color:var(--muted)}
.filter-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px}
.filter-tab{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:99px;font-size:.82rem;font-weight:600;border:1px solid var(--line);background:var(--white);color:var(--muted);transition:all .15s}
.filter-tab:hover{border-color:var(--navy-light);color:var(--navy)}
.filter-tab.active{background:var(--navy);border-color:var(--navy);color:#fff}
.filter-count{background:rgba(255,255,255,.22);color:inherit;font-size:.7rem;font-weight:700;padding:1px 7px;border-radius:99px}
.filter-tab:not(.active) .filter-count{background:var(--bg);color:var(--soft)}

.bookings-list{display:flex;flex-direction:column;gap:14px}
.booking-card{background:var(--white);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;transition:border-color .15s,box-shadow .15s}
.booking-card:hover{border-color:var(--navy-soft);box-shadow:var(--shadow)}
.booking-card-header{padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;border-bottom:1px solid var(--line)}
.booking-service{display:flex;align-items:center;gap:12px;min-width:0}
.booking-icon{width:42px;height:42px;border-radius:11px;background:var(--navy-soft);color:var(--navy);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.booking-icon i{width:20px;height:20px}
.booking-service-name{font-weight:700;font-size:.95rem;color:var(--navy)}
.booking-ref{font-size:.75rem;color:var(--soft);margin-top:2px}
.pill{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:99px;font-size:.75rem;font-weight:700;white-space:nowrap}
.pill i{width:13px;height:13px}
.booking-card-body{padding:16px 20px;display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.booking-detail{display:flex;flex-direction:column;gap:4px}
.booking-detail-label{display:flex;align-items:center;gap:5px;font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--soft)}
.booking-detail-label i{width:13px;height:13px}
.booking-detail-value{font-size:.87rem;font-weight:600;color:var(--text)}
.booking-card-footer{padding:12px 20px;background:var(--bg);border-top:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.booking-notes{display:flex;align-items:center;gap:7px;font-size:.82rem;color:var(--muted);max-width:420px;min-width:0}
.booking-notes i{width:14px;height:14px;flex-shrink:0}
.booking-notes span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.booking-actions{display:flex;gap:8px;flex-shrink:0;align-items:center}
.btn-view{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--radius-sm);background:var(--navy);color:#fff;font-size:.78rem;font-weight:700;border:none;cursor:pointer;font-family:var(--font)}
.btn-view:hover{background:var(--navy-mid)}
.btn-view i{width:14px;height:14px}
.btn-cancel-booking{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--radius-sm);background:transparent;border:1px solid #FECACA;color:#B91C1C;font-size:.78rem;font-weight:600;cursor:pointer;font-family:var(--font)}
.btn-cancel-booking:hover{background:var(--danger-bg)}
.btn-cancel-booking i{width:14px;height:14px}

.progress-tracker{display:flex;align-items:flex-start;padding:14px 20px;border-bottom:1px solid var(--line);overflow-x:auto}
.progress-item{display:flex;flex-direction:column;align-items:center;flex-shrink:0}
.progress-dot{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;border:2px solid var(--line);background:var(--white);color:var(--soft)}
.progress-dot i{width:13px;height:13px;stroke-width:3}
.progress-dot.done{background:var(--navy);border-color:var(--navy);color:#fff}
.progress-dot.current{background:var(--navy-light);border-color:var(--navy-light);color:#fff}
.progress-line{width:44px;height:2px;background:var(--line);flex-shrink:0;margin-top:12px}
.progress-line.done{background:var(--navy)}
.progress-label{font-size:.68rem;color:var(--soft);text-align:center;margin-top:4px;white-space:nowrap}
.progress-label.done{color:var(--navy);font-weight:600}
.progress-label.current{color:var(--navy-light);font-weight:700}

.btn-inline{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:10px;background:var(--navy);color:#fff;font-size:.86rem;font-weight:700}
.btn-inline:hover{background:var(--navy-mid)}
.btn-inline i{width:15px;height:15px}
.empty-card{background:var(--white);border:1px solid var(--line);border-radius:var(--radius)}

/* Modals */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(10,31,107,.45);z-index:500;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:var(--white);border-radius:var(--radius);padding:28px;max-width:420px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,.2)}
.modal-icon{width:44px;height:44px;border-radius:12px;background:var(--danger-bg);color:var(--danger);display:flex;align-items:center;justify-content:center;margin-bottom:14px}
.modal-icon i{width:20px;height:20px}
.modal h3{font-size:1.15rem;font-weight:800;color:var(--navy);margin-bottom:8px;letter-spacing:-.01em}
.modal p{font-size:.9rem;color:var(--muted);line-height:1.65;margin-bottom:22px}
.modal-actions{display:flex;gap:10px;justify-content:flex-end}
.btn-modal-cancel{padding:10px 18px;border-radius:var(--radius-sm);border:1px solid var(--line);background:transparent;color:var(--muted);font-size:.88rem;font-weight:600;cursor:pointer;font-family:var(--font)}
.btn-modal-cancel:hover{border-color:var(--navy-light);color:var(--navy)}
.btn-modal-confirm{padding:10px 18px;border-radius:var(--radius-sm);background:var(--danger);border:none;color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;font-family:var(--font)}

.detail-overlay{display:none;position:fixed;inset:0;background:rgba(10,31,107,.5);z-index:600;align-items:center;justify-content:center;padding:20px}
.detail-overlay.open{display:flex}
.detail-box{background:var(--white);border-radius:var(--radius);max-width:640px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden;max-height:90vh;display:flex;flex-direction:column}
.detail-head{background:linear-gradient(135deg,var(--navy),var(--navy-mid));padding:20px 24px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.detail-head-left{display:flex;align-items:center;gap:14px;min-width:0}
.detail-svc-icon{width:46px;height:46px;border-radius:12px;background:rgba(255,255,255,.15);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.detail-svc-icon i{width:22px;height:22px}
.detail-svc-name{font-size:1.05rem;font-weight:800;color:#fff;letter-spacing:-.01em}
.detail-ref{font-size:.75rem;color:rgba(255,255,255,.65);margin-top:2px}
.detail-close{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.15);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.detail-close:hover{background:rgba(255,255,255,.25)}
.detail-close i{width:16px;height:16px}
.detail-status-bar{padding:12px 24px;background:var(--bg);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.detail-status-label{font-size:.78rem;font-weight:600;color:var(--soft)}
.detail-body{padding:22px 24px;overflow-y:auto;flex:1}
.detail-section{margin-bottom:22px}
.detail-section:last-child{margin-bottom:0}
.detail-section-title{font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--soft);margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:6px}
.detail-section-title i{width:14px;height:14px}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.detail-field{display:flex;flex-direction:column;gap:3px}
.detail-field-label{display:flex;align-items:center;gap:5px;font-size:.7rem;font-weight:700;color:var(--soft);text-transform:uppercase;letter-spacing:.05em}
.detail-field-label i{width:12px;height:12px}
.detail-field-value{font-size:.87rem;font-weight:600;color:var(--text)}
.detail-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:2px}
.detail-tag{display:inline-block;padding:3px 10px;border-radius:99px;font-size:.76rem;font-weight:600;background:var(--navy-soft);color:var(--navy)}
.detail-checklist{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}
.detail-check{display:flex;align-items:center;gap:7px;font-size:.83rem;color:var(--muted)}
.detail-check i{width:15px;height:15px;color:var(--success)}
.detail-check i.muted{color:var(--soft)}
.detail-team-note{background:var(--navy-soft);border-radius:var(--radius-sm);padding:12px 14px;font-size:.85rem;color:var(--navy)}
.detail-empty{font-size:.84rem;color:var(--soft);font-style:italic}
.detail-foot{padding:14px 24px;border-top:1px solid var(--line);display:flex;justify-content:flex-end;flex-shrink:0}
.btn-detail-close{padding:9px 22px;border-radius:var(--radius-sm);background:var(--navy);color:#fff;font-family:var(--font);font-size:.87rem;font-weight:700;border:none;cursor:pointer}
.btn-detail-close:hover{background:var(--navy-mid)}

@media(max-width:1100px){.booking-card-body{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){
  .booking-card-header{flex-direction:column;align-items:flex-start}
  .detail-grid,.detail-checklist{grid-template-columns:1fr}
}
@media(max-width:480px){.booking-card-body{grid-template-columns:1fr}.filter-tab{padding:6px 12px;font-size:.78rem}}
</style>
<?php
echo '</head><body>';
$dash_role     = 'client';
$dash_page     = 'bookings';
$page_heading  = 'My Bookings';
$user_name     = $_SESSION['user_name'];
$badge_pending = (int)($counts['pending'] ?? 0);
$topbar_action_href  = BASE_URL . '/book';
$topbar_action_label = 'New Booking';
$topbar_action_icon  = 'plus';
include '../includes/dash-sidebar.php';
?>

      <!-- CANCEL MODAL -->
      <div class="modal-overlay" id="cancelModal">
        <div class="modal">
          <div class="modal-icon"><i data-lucide="triangle-alert"></i></div>
          <h3>Cancel Booking?</h3>
          <p>Are you sure you want to cancel this booking? This action cannot be undone.</p>
          <div class="modal-actions">
            <button type="button" class="btn-modal-cancel" onclick="closeCancelModal()">Keep Booking</button>
            <button type="button" class="btn-modal-confirm" onclick="confirmCancel()">Yes, Cancel It</button>
          </div>
        </div>
      </div>
      <form method="POST" id="cancelForm" style="display:none">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
        <input type="hidden" name="cancel_booking" value="1"/>
        <input type="hidden" name="booking_id" id="cancelBookingId" value=""/>
      </form>

      <!-- VIEW DETAILS MODAL -->
      <div class="detail-overlay" id="detailModal" onclick="handleOverlay(event)">
        <div class="detail-box">

          <div class="detail-head">
            <div class="detail-head-left">
              <div class="detail-svc-icon" id="dm_icon"><i data-lucide="briefcase"></i></div>
              <div>
                <div class="detail-svc-name" id="dm_service">Service</div>
                <div class="detail-ref" id="dm_ref">Booking #</div>
              </div>
            </div>
            <button type="button" class="detail-close" onclick="closeDetailModal()" aria-label="Close"><i data-lucide="x"></i></button>
          </div>

          <div class="detail-status-bar">
            <span class="detail-status-label">Current Status</span>
            <span class="pill" id="dm_status_pill">Pending</span>
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
                <div class="detail-field"><span class="detail-field-label"><i data-lucide="timer"></i> Duration</span><span class="detail-field-value" id="dm_duration">—</span></div>
                <div class="detail-field"><span class="detail-field-label"><i data-lucide="map-pin"></i> Neighbourhood</span><span class="detail-field-value" id="dm_neighbourhood">—</span></div>
                <div class="detail-field"><span class="detail-field-label"><i data-lucide="calendar"></i> Submitted</span><span class="detail-field-value" id="dm_submitted">—</span></div>
                <div class="detail-field"><span class="detail-field-label"><i data-lucide="house"></i> Accommodation</span><span class="detail-field-value" id="dm_accomm">—</span></div>
                <div class="detail-field"><span class="detail-field-label"><i data-lucide="user"></i> Assigned Team</span><span class="detail-field-value" id="dm_team">—</span></div>
              </div>
            </div>

            <div class="detail-section" id="dm_cleaning_section">
              <div class="detail-section-title"><i data-lucide="door-open"></i> Property Details</div>
              <div class="detail-field">
                <span class="detail-field-label">Rooms</span>
                <span class="detail-field-value" id="dm_rooms">—</span>
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

            <div class="detail-section" id="dm_note_section" style="display:none">
              <div class="detail-section-title"><i data-lucide="message-square"></i> Team Note</div>
              <div class="detail-team-note" id="dm_note">—</div>
            </div>

          </div>

          <div class="detail-foot">
            <button type="button" class="btn-detail-close" onclick="closeDetailModal()">Close</button>
          </div>

        </div>
      </div>

      <div class="page-intro">
        <h1>My Bookings</h1>
        <p>Track all your service requests and their current status.</p>
      </div>

      <?php if($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>">
          <i data-lucide="<?= $flash['type']==='success' ? 'circle-check' : 'triangle-alert' ?>"></i>
          <span><?= e($flash['message']) ?></span>
        </div>
      <?php endif; ?>

      <!-- FILTER TABS -->
      <div class="filter-tabs">
        <?php
        $tab_labels = ['all'=>'All','pending'=>'Pending','under_review'=>'Under Review','completed'=>'Completed','hold'=>'On Hold','approved'=>'Approved','rejected'=>'Rejected','cancelled'=>'Cancelled'];
        foreach($tab_labels as $key => $label):
          $cnt = $counts[$key] ?? 0;
          if ($key !== 'all' && $cnt === 0) continue;
        ?>
          <a href="?status=<?= $key ?>" class="filter-tab <?= $filter===$key ? 'active':'' ?>">
            <?= $label ?> <span class="filter-count"><?= $cnt ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- BOOKINGS LIST -->
      <?php if(empty($bookings)): ?>
        <div class="empty-card">
          <div class="empty-state">
            <i data-lucide="clipboard-list"></i>
            <h4>No bookings <?= $filter !== 'all' ? 'with status "'.$tab_labels[$filter].'"' : 'yet' ?></h4>
            <p><?= $filter !== 'all' ? 'Try a different filter.' : "You haven't made any bookings yet." ?></p>
            <?php if($filter === 'all'): ?>
              <a href="<?= BASE_URL ?>/book" class="btn-inline"><i data-lucide="plus"></i> Book Your First Service</a>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="bookings-list">
          <?php foreach($bookings as $b):
            $sc       = $status_config[$b['status']] ?? $status_config['pending'];
            $no_track = in_array($b['status'], ['rejected','cancelled','hold','approved']);

            // Build service names from selected_services IDs
            $svc_names = [];
            if (!empty($b['selected_services'])) {
                $svc_ids = explode(',', $b['selected_services']);
                $svc_result = $conn->query("SELECT name FROM services WHERE id IN (".implode(',', array_map('intval',$svc_ids)).")");
                while($sv = $svc_result->fetch_assoc()) $svc_names[] = $sv['name'];
            }
            if (empty($svc_names)) $svc_names[] = $b['service_name'];

            // Build checklist
            $checklist = [];
            if ($b['outdoor_areas']) $checklist[] = ['yes', 'Outdoor areas'];
            if ($b['has_car'])       $checklist[] = ['yes', 'Car cleaning'];
            if ($b['has_pool'])      $checklist[] = ['yes', 'Has pool'];
            if ($b['dish_washing'])  $checklist[] = ['yes', 'Dish washing'];
            if ($b['has_pets'])      $checklist[] = ['yes', 'Pets'];
            if ($b['laundry'])       $checklist[] = ['yes', 'Laundry & ironing'];

            // Accommodation
            $accomm = '—';
            if ($b['live_in'])  $accomm = 'Live In';
            if ($b['live_out']) $accomm = 'Live Out' . ($b['living_area'] ? ' — '.$b['living_area'] : '');

            $svc_icon = svc_lucide_icon($svc_names[0]);

            // Build modal data
            $modal = json_encode([
              'id'                => $b['id'],
              'service_name'      => implode(' + ', $svc_names),
              'service_icon'      => $svc_icon,
              'status'            => $b['status'],
              'status_label'      => $sc['label'],
              'status_icon'       => $sc['icon'],
              'status_color'      => $sc['color'],
              'status_bg'         => $sc['bg'],
              'services'          => $svc_names,
              'frequency'         => $b['frequency']    ?: '—',
              'duration'          => $b['duration']     ?: '—',
              'neighbourhood'     => $b['neighbourhood'] ?: '—',
              'accomm'            => $accomm,
              'team_name'        => $b['team_name']   ?: 'Pending assignment',
              'agent_notes'       => $b['agent_notes']  ?? '',
              'rooms'             => $b['rooms']         ?: '—',
              'checklist'         => $checklist,
              'chef_type'         => $b['chef_type']    ?: '',
              'num_people'        => $b['num_people']   ?: '—',
              'num_adults'        => $b['num_adults']   ?: '—',
              'needs_menu'        => $b['needs_menu']   ? 'Yes' : 'No',
              'languages'         => $b['languages']    ?: '',
              'created_at'        => date('d M Y', strtotime($b['created_at'])),
              'has_cleaning'      => (in_array(6, array_map('intval', explode(',', $b['selected_services'] ?: '0'))) || in_array(7, array_map('intval', explode(',', $b['selected_services'] ?: '0')))),
              'has_chef'          => in_array(9, array_map('intval', explode(',', $b['selected_services'] ?: '0'))),
            ], JSON_HEX_QUOT | JSON_HEX_TAG);
          ?>

            <div class="booking-card">
              <div class="booking-card-header">
                <div class="booking-service">
                  <div class="booking-icon"><i data-lucide="<?= $svc_icon ?>"></i></div>
                  <div>
                    <div class="booking-service-name"><?= e(implode(' + ', $svc_names)) ?></div>
                    <div class="booking-ref">Booking #<?= $b['id'] ?> · <?= date('d M Y', strtotime($b['created_at'])) ?></div>
                  </div>
                </div>
                <span class="pill" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>">
                  <i data-lucide="<?= $sc['icon'] ?>"></i> <?= $sc['label'] ?>
                </span>
              </div>

              <?php if(!$no_track): ?>
              <div class="progress-tracker">
                <?php
                $track_steps = ['pending'=>'Submitted','under_review'=>'Reviewing','completed'=>'Done'];
                $keys = array_keys($track_steps);
                $cur  = array_search($b['status'], $keys);
                $tot  = count($keys);
                foreach($track_steps as $sk => $slabel):
                  $idx        = array_search($sk,$keys);
                  $is_done    = $cur !== false && $idx < $cur;
                  $is_current = $b['status'] === $sk;
                ?>
                  <div class="progress-item">
                    <div class="progress-dot <?= $is_done?'done':($is_current?'current':'') ?>"><?php if($is_done): ?><i data-lucide="check"></i><?php else: ?><?= $idx+1 ?><?php endif; ?></div>
                    <div class="progress-label <?= $is_done?'done':($is_current?'current':'') ?>"><?= $slabel ?></div>
                  </div>
                  <?php if($idx < $tot-1): ?><div class="progress-line <?= $is_done?'done':'' ?>"></div><?php endif; ?>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

              <div class="booking-card-body">
                <div class="booking-detail"><span class="booking-detail-label"><i data-lucide="repeat"></i> Frequency</span><span class="booking-detail-value"><?= e($b['frequency']?:'—') ?></span></div>
                <div class="booking-detail"><span class="booking-detail-label"><i data-lucide="map-pin"></i> Neighbourhood</span><span class="booking-detail-value"><?= e($b['neighbourhood']?:'—') ?></span></div>
                <div class="booking-detail"><span class="booking-detail-label"><i data-lucide="calendar"></i> Submitted</span><span class="booking-detail-value"><?= date('d M Y', strtotime($b['created_at'])) ?></span></div>
                <div class="booking-detail"><span class="booking-detail-label"><i data-lucide="user"></i> Team</span><span class="booking-detail-value"><?= $b['team_name'] ? e($b['team_name']) : 'Pending' ?></span></div>
              </div>

              <div class="booking-card-footer">
                <div class="booking-notes">
                  <?php if(!empty($b['agent_notes'])): ?>
                    <i data-lucide="message-square"></i>
                    <span><?= e(mb_substr($b['agent_notes'],0,60)) ?><?= mb_strlen($b['agent_notes'])>60?'...':'' ?></span>
                  <?php elseif(!empty($b['languages'])): ?>
                    <i data-lucide="languages"></i>
                    <span><?= e($b['languages']) ?></span>
                  <?php else: ?>
                    <span>Tap View Details for full info</span>
                  <?php endif; ?>
                </div>
                <div class="booking-actions">
                  <button type="button" class="btn-view" onclick='openDetailModal(<?= $modal ?>)'><i data-lucide="eye"></i> View Details</button>
                  <?php if($b['status']==='pending'): ?>
                    <button type="button" class="btn-cancel-booking" onclick="askCancel(<?= $b['id'] ?>)"><i data-lucide="ban"></i> Cancel</button>
                  <?php endif; ?>
                </div>
              </div>
            </div>

          <?php endforeach; ?>
        </div>
      <?php endif; ?>

<script>
function refreshIcons(){ if(window.lucide) lucide.createIcons(); }

// ── CANCEL MODAL ──────────────────────────────────────────────
function askCancel(id){document.getElementById('cancelBookingId').value=id;document.getElementById('cancelModal').classList.add('open');document.body.style.overflow='hidden';}
function closeCancelModal(){document.getElementById('cancelModal').classList.remove('open');document.body.style.overflow='';}
function confirmCancel(){document.getElementById('cancelForm').submit();}
document.getElementById('cancelModal').addEventListener('click',function(e){if(e.target===this)closeCancelModal();});

function iconEl(name, cls){
  var i = document.createElement('i');
  i.setAttribute('data-lucide', name);
  if (cls) i.className = cls;
  return i;
}

// ── VIEW DETAILS MODAL ────────────────────────────────────────
function openDetailModal(d) {
  // Header
  var ic = document.getElementById('dm_icon');
  ic.innerHTML = '';
  ic.appendChild(iconEl(d.service_icon || 'briefcase'));
  document.getElementById('dm_service').textContent = d.service_name;
  document.getElementById('dm_ref').textContent     = 'Booking #' + d.id + ' · Submitted ' + d.created_at;

  // Status
  var pill = document.getElementById('dm_status_pill');
  pill.innerHTML = '';
  pill.appendChild(iconEl(d.status_icon));
  pill.appendChild(document.createTextNode(' ' + d.status_label));
  pill.style.background = d.status_bg;
  pill.style.color      = d.status_color;

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
  document.getElementById('dm_frequency').textContent    = d.frequency    || '—';
  document.getElementById('dm_duration').textContent     = d.duration     || '—';
  document.getElementById('dm_neighbourhood').textContent= d.neighbourhood|| '—';
  document.getElementById('dm_submitted').textContent    = d.created_at;
  document.getElementById('dm_accomm').textContent       = d.accomm       || '—';
  document.getElementById('dm_team').textContent         = d.team_name;

  // Cleaning/Housekeeping section
  var cleanSec = document.getElementById('dm_cleaning_section');
  if (d.has_cleaning) {
    cleanSec.style.display = 'block';
    document.getElementById('dm_rooms').textContent = d.rooms || '—';
    var cl = document.getElementById('dm_checklist');
    cl.innerHTML = '';
    (d.checklist || []).forEach(function(item){
      var div = document.createElement('div');
      div.className = 'detail-check';
      div.appendChild(iconEl('circle-check'));
      div.appendChild(document.createTextNode(item[1]));
      cl.appendChild(div);
    });
    if (!d.checklist || !d.checklist.length) {
      var div = document.createElement('div');
      div.className = 'detail-check';
      div.appendChild(iconEl('minus', 'muted'));
      div.appendChild(document.createTextNode('No extras selected'));
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
    document.getElementById('dm_people').textContent    = d.num_people || '—';
    document.getElementById('dm_adults').textContent    = d.num_adults || '—';
    document.getElementById('dm_menu').textContent      = d.needs_menu || '—';
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
    ltags.innerHTML = '<span class="detail-empty">No preference specified</span>';
  }

  // Team note
  if (d.agent_notes) {
    document.getElementById('dm_note').textContent = d.agent_notes;
    document.getElementById('dm_note_section').style.display = 'block';
  } else {
    document.getElementById('dm_note_section').style.display = 'none';
  }

  document.getElementById('detailModal').classList.add('open');
  document.body.style.overflow = 'hidden';
  refreshIcons();
}

function closeDetailModal(){
  document.getElementById('detailModal').classList.remove('open');
  document.body.style.overflow = '';
}
function handleOverlay(e){
  if(e.target===document.getElementById('detailModal')) closeDetailModal();
}
</script>
<?php include '../includes/dash-end.php'; ?>
