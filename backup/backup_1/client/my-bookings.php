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
$allowed_filters = ['all','pending','under_review','approved','completed','rejected','cancelled'];
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

$notif_count = unreadNotifCount();
$flash       = flashGet();

$status_config = [
    'pending'      => ['label'=>'Pending',      'color'=>'#92400E','bg'=>'#FFFBEB','icon'=>'⏳'],
    'under_review' => ['label'=>'Under Review', 'color'=>'#1E40AF','bg'=>'#EFF6FF','icon'=>'🔍'],
    'approved'     => ['label'=>'Approved',     'color'=>'#065F46','bg'=>'#ECFDF5','icon'=>'✅'],
    'completed'    => ['label'=>'Completed',    'color'=>'#0D1B4B','bg'=>'#D4DCF5','icon'=>'🏆'],
    'rejected'     => ['label'=>'Rejected',     'color'=>'#991B1B','bg'=>'#FEF2F2','icon'=>'❌'],
    'cancelled'    => ['label'=>'Cancelled',    'color'=>'#6B7280','bg'=>'#F3F4F6','icon'=>'🚫'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>My Bookings | Home Help</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --navy-deep:#0D1B4B;--navy-mid:#152268;--navy-light:#2E5FC2;--navy-soft:#D4DCF5;
  --amber:#E8A020;--amber-light:#FFF0CC;--cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --danger:#EF4444;--danger-bg:#FEF2F2;--success:#10B981;--success-bg:#ECFDF5;
  --shadow-sm:0 2px 12px rgba(13,27,75,0.07);--shadow-md:0 6px 28px rgba(13,27,75,0.11);
  --radius:14px;--radius-sm:8px;--sidebar:260px;
  --font-display:'Cormorant Garamond',Georgia,serif;--font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden}
a{text-decoration:none;color:inherit}
.dash-wrap{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar);flex-shrink:0;background:var(--navy-deep);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;transition:transform 0.3s ease;overflow-y:auto}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:24px 20px 20px;border-bottom:1px solid rgba(255,255,255,0.08)}
.sidebar-logo img{width:36px;height:36px;object-fit:contain}
.sidebar-logo-text{font-family:var(--font-display);font-size:1.3rem;font-weight:700;color:var(--white)}
.sidebar-user{padding:20px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;gap:12px}
.user-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--navy-light),var(--amber));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--white);flex-shrink:0}
.user-info strong{display:block;font-size:0.88rem;font-weight:700;color:var(--white);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-info span{font-size:0.73rem;color:rgba(255,255,255,0.5)}
.sidebar-nav{padding:16px 0;flex:1}
.nav-section-label{font-size:0.66rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.3);padding:12px 20px 6px}
.nav-item{display:flex;align-items:center;gap:12px;padding:11px 20px;font-size:0.87rem;font-weight:500;color:rgba(255,255,255,0.65);cursor:pointer;transition:all 0.2s;position:relative;text-decoration:none}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,0.06)}
.nav-item.active{color:var(--white);background:rgba(255,255,255,0.1);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--amber);border-radius:0 3px 3px 0}
.nav-icon{font-size:1.05rem;width:20px;text-align:center;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--amber);color:var(--white);font-size:0.65rem;font-weight:700;padding:2px 6px;border-radius:99px}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,0.08)}
.btn-logout{display:flex;align-items:center;gap:8px;width:100%;padding:10px 14px;border-radius:var(--radius-sm);background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.2);color:rgba(255,100,100,0.9);font-size:0.84rem;font-weight:600;cursor:pointer;transition:all 0.2s;text-decoration:none;font-family:var(--font-body)}
.btn-logout:hover{background:rgba(239,68,68,0.2)}
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--white);border-bottom:1px solid var(--cream-dark);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar-left{display:flex;align-items:center;gap:16px}
.burger{display:none;background:none;border:none;cursor:pointer;padding:6px;flex-direction:column;gap:4px}
.burger span{display:block;width:20px;height:2px;background:var(--text-dark);border-radius:2px}
.topbar-title{font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--navy-deep)}
.topbar-right{display:flex;align-items:center;gap:12px}
.topbar-notif{position:relative;width:38px;height:38px;border-radius:var(--radius-sm);background:var(--cream);border:1.5px solid var(--cream-dark);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.1rem;transition:all 0.2s;text-decoration:none}
.topbar-notif:hover{border-color:var(--navy-light);background:var(--navy-soft)}
.notif-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:var(--danger);border:2px solid var(--white)}
.btn-book-now{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:99px;background:var(--amber);color:var(--white);font-size:0.83rem;font-weight:700;border:none;cursor:pointer;transition:all 0.3s;text-decoration:none}
.btn-book-now:hover{background:#d4911a;transform:translateY(-1px)}
.content{padding:28px;flex:1}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:24px}
.page-header-left h1{font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--navy-deep);margin-bottom:4px}
.page-header-left h1 em{font-style:italic;color:var(--navy-light)}
.page-header-left p{font-size:0.88rem;color:var(--text-soft)}
.alert{padding:13px 16px;border-radius:var(--radius-sm);font-size:0.87rem;font-weight:500;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px}
.alert-error{background:var(--danger-bg);border:1.5px solid #FECACA;color:#B91C1C}
.alert-success{background:var(--success-bg);border:1.5px solid #BBF7D0;color:#065F46}
.filter-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px}
.filter-tab{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:99px;font-size:0.82rem;font-weight:600;border:1.5px solid var(--cream-dark);background:var(--white);color:var(--text-mid);cursor:pointer;transition:all 0.2s;text-decoration:none}
.filter-tab:hover{border-color:var(--navy-light);color:var(--navy-deep)}
.filter-tab.active{background:var(--navy-deep);border-color:var(--navy-deep);color:var(--white)}
.filter-count{background:rgba(255,255,255,0.25);color:inherit;font-size:0.7rem;font-weight:700;padding:1px 6px;border-radius:99px}
.filter-tab:not(.active) .filter-count{background:var(--cream-dark);color:var(--text-soft)}
.bookings-list{display:flex;flex-direction:column;gap:14px}
.booking-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden;transition:all 0.3s}
.booking-card:hover{box-shadow:var(--shadow-md);border-color:var(--navy-soft)}
.booking-card-header{padding:18px 22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;border-bottom:1px solid var(--cream-dark)}
.booking-service{display:flex;align-items:center;gap:12px}
.booking-icon{width:44px;height:44px;border-radius:12px;background:var(--navy-soft);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
.booking-service-name{font-weight:700;font-size:0.98rem;color:var(--navy-deep)}
.booking-ref{font-size:0.74rem;color:var(--text-soft);margin-top:2px}
.status-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:99px;font-size:0.76rem;font-weight:700;white-space:nowrap}
.booking-card-body{padding:18px 22px;display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.booking-detail{display:flex;flex-direction:column;gap:3px}
.booking-detail-label{font-size:0.71rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-soft)}
.booking-detail-value{font-size:0.87rem;font-weight:600;color:var(--text-dark)}
.booking-card-footer{padding:14px 22px;background:var(--cream);border-top:1px solid var(--cream-dark);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.booking-notes{font-size:0.82rem;color:var(--text-mid);font-style:italic;max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.booking-actions{display:flex;gap:8px;flex-shrink:0;align-items:center}
.btn-view{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:var(--radius-sm);background:var(--navy-deep);color:var(--white);font-size:0.78rem;font-weight:700;border:none;cursor:pointer;transition:all 0.2s;font-family:var(--font-body)}
.btn-view:hover{background:var(--navy-mid);transform:translateY(-1px)}
.btn-cancel-booking{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:var(--radius-sm);background:transparent;border:1.5px solid #FECACA;color:#B91C1C;font-size:0.78rem;font-weight:600;cursor:pointer;transition:all 0.2s;font-family:var(--font-body)}
.btn-cancel-booking:hover{background:var(--danger-bg)}
.progress-tracker{display:flex;align-items:center;gap:0;padding:14px 22px;border-top:1px solid var(--cream-dark);overflow-x:auto}
.progress-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;flex-shrink:0;border:2px solid var(--cream-dark);background:var(--white);color:var(--text-soft)}
.progress-dot.done{background:var(--navy-deep);border-color:var(--navy-deep);color:var(--white)}
.progress-dot.current{background:var(--amber);border-color:var(--amber);color:var(--white)}
.progress-line{width:40px;height:2px;background:var(--cream-dark);flex-shrink:0}
.progress-line.done{background:var(--navy-deep)}
.progress-item{display:flex;flex-direction:column;align-items:center;flex-shrink:0}
.progress-label{font-size:0.68rem;color:var(--text-soft);text-align:center;margin-top:4px;white-space:nowrap}
.progress-label.done{color:var(--navy-deep);font-weight:600}
.progress-label.current{color:var(--amber);font-weight:700}
.empty-state{text-align:center;padding:64px 20px;background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius)}
.empty-icon{font-size:3rem;margin-bottom:16px}
.empty-state h3{font-family:var(--font-display);font-size:1.4rem;color:var(--navy-deep);margin-bottom:8px}
.empty-state p{font-size:0.9rem;color:var(--text-soft);margin-bottom:24px}

/* ── CANCEL MODAL ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:500;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:var(--white);border-radius:var(--radius);padding:32px;max-width:420px;width:100%;box-shadow:var(--shadow-md)}
.modal h3{font-family:var(--font-display);font-size:1.4rem;color:var(--navy-deep);margin-bottom:10px}
.modal p{font-size:0.9rem;color:var(--text-mid);line-height:1.65;margin-bottom:24px}
.modal-actions{display:flex;gap:10px;justify-content:flex-end}
.btn-modal-cancel{padding:10px 20px;border-radius:var(--radius-sm);border:1.5px solid var(--cream-dark);background:transparent;color:var(--text-mid);font-size:0.88rem;font-weight:600;cursor:pointer;font-family:var(--font-body)}
.btn-modal-confirm{padding:10px 20px;border-radius:var(--radius-sm);background:var(--danger);border:none;color:var(--white);font-size:0.88rem;font-weight:700;cursor:pointer;font-family:var(--font-body)}

/* ── VIEW DETAILS MODAL ── */
.detail-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:600;align-items:center;justify-content:center;padding:20px}
.detail-overlay.open{display:flex}
.detail-box{background:var(--white);border-radius:var(--radius);max-width:640px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.2);overflow:hidden;max-height:90vh;display:flex;flex-direction:column}
.detail-head{background:linear-gradient(135deg,var(--navy-deep),var(--navy-mid));padding:22px 26px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.detail-head-left{display:flex;align-items:center;gap:14px}
.detail-svc-icon{width:50px;height:50px;border-radius:14px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0}
.detail-svc-name{font-family:var(--font-display);font-size:1.2rem;font-weight:700;color:var(--white)}
.detail-ref{font-size:0.75rem;color:rgba(255,255,255,0.6);margin-top:2px}
.detail-close{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.15);border:none;color:var(--white);font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.2s;flex-shrink:0}
.detail-close:hover{background:rgba(255,255,255,0.25)}

/* Price strip */
.detail-price-strip{background:linear-gradient(135deg,var(--navy-deep),var(--navy-mid));padding:0 26px 18px;flex-shrink:0}
.detail-price-inner{background:rgba(255,255,255,0.1);border-radius:10px;padding:12px 18px;display:flex;align-items:center;justify-content:space-between}
.detail-price-label{font-size:0.76rem;color:rgba(255,255,255,0.65)}
.detail-price-amount{font-family:var(--font-display);font-size:1.5rem;font-weight:700;color:var(--white)}

/* Status bar */
.detail-status-bar{padding:12px 26px;background:var(--cream);border-bottom:1px solid var(--cream-dark);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.detail-status-label{font-size:0.78rem;font-weight:600;color:var(--text-soft)}
.detail-status-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:99px;font-size:0.76rem;font-weight:700}

/* Body */
.detail-body{padding:22px 26px;overflow-y:auto;flex:1}
.detail-section{margin-bottom:22px}
.detail-section:last-child{margin-bottom:0}
.detail-section-title{font-size:0.68rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-soft);margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid var(--cream-dark);display:flex;align-items:center;gap:6px}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.detail-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.detail-field{display:flex;flex-direction:column;gap:3px}
.detail-field-label{font-size:0.7rem;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.05em}
.detail-field-value{font-size:0.87rem;font-weight:600;color:var(--text-dark)}
.detail-field-value.empty{color:var(--text-soft);font-weight:400;font-style:italic}

/* Tags (languages, services) */
.detail-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:2px}
.detail-tag{display:inline-block;padding:3px 10px;border-radius:99px;font-size:0.76rem;font-weight:600;background:var(--navy-soft);color:var(--navy-deep)}

/* Checklist */
.detail-checklist{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:4px}
.detail-check{display:flex;align-items:center;gap:7px;font-size:0.83rem;color:var(--text-mid)}
.detail-check .icon-yes{color:var(--success);font-size:0.85rem}
.detail-check .icon-no{color:var(--text-soft);font-size:0.85rem}

/* Text blocks */
.detail-text-block{font-size:0.86rem;color:var(--text-mid);line-height:1.65;background:var(--cream);border-radius:var(--radius-sm);padding:12px 14px}
.detail-team-note{background:var(--navy-soft);border-radius:var(--radius-sm);padding:12px 14px;font-size:0.85rem;color:var(--navy-deep);font-style:italic}

.detail-foot{padding:16px 26px;border-top:1px solid var(--cream-dark);display:flex;justify-content:flex-end;flex-shrink:0}
.btn-detail-close{padding:9px 24px;border-radius:99px;background:var(--navy-deep);color:var(--white);font-family:var(--font-body);font-size:0.87rem;font-weight:700;border:none;cursor:pointer}
.btn-detail-close:hover{background:var(--navy-mid)}

.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:199}
@media(max-width:1100px){.booking-card-body{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .content{padding:18px}.topbar{padding:0 16px}
  .booking-card-body{grid-template-columns:1fr 1fr}
  .booking-card-header{flex-direction:column;align-items:flex-start}
  .detail-grid{grid-template-columns:1fr}.detail-grid-3{grid-template-columns:1fr 1fr}
  .detail-checklist{grid-template-columns:1fr}
}
@media(max-width:480px){.booking-card-body{grid-template-columns:1fr}.filter-tabs{gap:6px}.filter-tab{padding:6px 12px;font-size:0.78rem}}
</style>
</head>
<body>

<!-- ── CANCEL MODAL ── -->
<div class="modal-overlay" id="cancelModal">
  <div class="modal">
    <h3>Cancel Booking?</h3>
    <p>Are you sure you want to cancel this booking? This action cannot be undone.</p>
    <div class="modal-actions">
      <button class="btn-modal-cancel" onclick="closeCancelModal()">Keep Booking</button>
      <button class="btn-modal-confirm" onclick="confirmCancel()">Yes, Cancel It</button>
    </div>
  </div>
</div>
<form method="POST" id="cancelForm" style="display:none">
  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
  <input type="hidden" name="cancel_booking" value="1"/>
  <input type="hidden" name="booking_id" id="cancelBookingId" value=""/>
</form>

<!-- ── VIEW DETAILS MODAL ── -->
<div class="detail-overlay" id="detailModal" onclick="handleOverlay(event)">
  <div class="detail-box">

    <!-- Header -->
    <div class="detail-head">
      <div class="detail-head-left">
        <div class="detail-svc-icon" id="dm_icon">🏠</div>
        <div>
          <div class="detail-svc-name" id="dm_service">Service</div>
          <div class="detail-ref" id="dm_ref">Booking #</div>
        </div>
      </div>
      <button class="detail-close" onclick="closeDetailModal()">✕</button>
    </div>

    <!-- Price strip -->
    <div class="detail-price-strip">
      <div class="detail-price-inner">
        <div>
          <div class="detail-price-label">Estimated Monthly Fee</div>
          <div style="font-size:0.72rem;color:rgba(255,255,255,0.5)">Based on selected services & details</div>
        </div>
        <div class="detail-price-amount" id="dm_price">0 RWF</div>
      </div>
    </div>

    <!-- Status bar -->
    <div class="detail-status-bar">
      <span class="detail-status-label">Current Status</span>
      <span class="detail-status-pill" id="dm_status_pill">⏳ Pending</span>
    </div>

    <!-- Body -->
    <div class="detail-body">

      <!-- Services -->
      <div class="detail-section">
        <div class="detail-section-title">🧹 Services Requested</div>
        <div id="dm_services_tags" class="detail-tags"></div>
      </div>

      <!-- Booking basics -->
      <div class="detail-section">
        <div class="detail-section-title">📋 Booking Details</div>
        <div class="detail-grid">
          <div class="detail-field"><span class="detail-field-label">🔄 Frequency</span><span class="detail-field-value" id="dm_frequency">—</span></div>
          <div class="detail-field"><span class="detail-field-label">⏰ Duration</span><span class="detail-field-value" id="dm_duration">—</span></div>
          <div class="detail-field"><span class="detail-field-label">📍 Neighbourhood</span><span class="detail-field-value" id="dm_neighbourhood">—</span></div>
          <div class="detail-field"><span class="detail-field-label">📅 Submitted</span><span class="detail-field-value" id="dm_submitted">—</span></div>
          <div class="detail-field"><span class="detail-field-label">🏠 Accommodation</span><span class="detail-field-value" id="dm_accomm">—</span></div>
          <div class="detail-field"><span class="detail-field-label">👤 Assigned Team</span><span class="detail-field-value" id="dm_team">—</span></div>
        </div>
      </div>

      <!-- Service specifics (cleaning/housekeeping) -->
      <div class="detail-section" id="dm_cleaning_section">
        <div class="detail-section-title">🚪 Property Details</div>
        <div class="detail-grid-3" style="margin-bottom:12px">
          <div class="detail-field"><span class="detail-field-label">Rooms</span><span class="detail-field-value" id="dm_rooms">—</span></div>
        </div>
        <div class="detail-checklist" id="dm_checklist"></div>
      </div>

      <!-- Chef details -->
      <div class="detail-section" id="dm_chef_section">
        <div class="detail-section-title">👨‍🍳 Chef Details</div>
        <div class="detail-grid">
          <div class="detail-field"><span class="detail-field-label">Chef Type</span><span class="detail-field-value" id="dm_chef_type">—</span></div>
          <div class="detail-field"><span class="detail-field-label">Number of People</span><span class="detail-field-value" id="dm_people">—</span></div>
          <div class="detail-field"><span class="detail-field-label">Number of Adults</span><span class="detail-field-value" id="dm_adults">—</span></div>
          <div class="detail-field"><span class="detail-field-label">Menu Needed</span><span class="detail-field-value" id="dm_menu">—</span></div>
        </div>
      </div>

      <!-- Languages -->
      <div class="detail-section">
        <div class="detail-section-title">🗣️ Language Preference</div>
        <div id="dm_lang_tags" class="detail-tags"></div>
      </div>

      <!-- Team note -->
      <div class="detail-section" id="dm_note_section" style="display:none">
        <div class="detail-section-title">💬 Team Note</div>
        <div class="detail-team-note" id="dm_note">—</div>
      </div>

    </div><!-- /detail-body -->

    <div class="detail-foot">
      <button class="btn-detail-close" onclick="closeDetailModal()">Close</button>
    </div>

  </div>
</div>

<div class="dash-wrap">
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="../Image/icon/homehelp_logo_transparent_white.svg" alt="Home Help" onerror="this.style.display='none'"/>
      <span class="sidebar-logo-text">Home Help</span>
    </div>
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($user_name,0,1)) ?></div>
      <div class="user-info"><strong><?= e($user_name) ?></strong><span>Client</span></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Main</div>
      <a href="<?= BASE_URL ?>/dashboard"    class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
      <a href="<?= BASE_URL ?>/book"          class="nav-item"><span class="nav-icon">📋</span> Book a Service</a>
      <a href="<?= BASE_URL ?>/my-bookings"   class="nav-item active">
        <span class="nav-icon">📂</span> My Bookings
        <?php if(($counts['pending']??0) > 0): ?><span class="nav-badge"><?= $counts['pending'] ?></span><?php endif; ?>
      </a>
      <div class="nav-section-label">Account</div>
      <a href="<?= BASE_URL ?>/profile"      class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
      <a href="<?= BASE_URL ?>/dashboard#notifications" class="nav-item">
        <span class="nav-icon">🔔</span> Notifications
        <?php if($notif_count > 0): ?><span class="nav-badge"><?= $notif_count ?></span><?php endif; ?>
      </a>
      <div class="nav-section-label">Help</div>
      <a href="<?= BASE_URL ?>/#faq"     class="nav-item"><span class="nav-icon">❓</span> FAQ</a>
      <a href="<?= BASE_URL ?>/#contact" class="nav-item"><span class="nav-icon">💬</span> Contact Support</a>
    </nav>
    <div class="sidebar-footer">
      <a href="<?= BASE_URL ?>/logout" class="btn-logout"><span>🚪</span> Sign Out</a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="burger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <span class="topbar-title">My Bookings</span>
      </div>
      <div class="topbar-right">
        <a href="<?= BASE_URL ?>/dashboard#notifications" class="topbar-notif">
          🔔<?php if($notif_count > 0): ?><span class="notif-dot"></span><?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/book" class="btn-book-now">＋ New Booking</a>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div class="page-header-left">
          <h1>My <em>Bookings</em></h1>
          <p>Track all your service requests and their current status.</p>
        </div>
      </div>

      <?php if($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
          <?= $flash['type']==='success' ? '✅' : '⚠️' ?> <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- FILTER TABS -->
      <div class="filter-tabs">
        <?php
        $tab_labels = ['all'=>'All','pending'=>'Pending','under_review'=>'Under Review','approved'=>'Approved','completed'=>'Completed','rejected'=>'Rejected','cancelled'=>'Cancelled'];
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
        <div class="empty-state">
          <div class="empty-icon">📋</div>
          <h3>No bookings <?= $filter !== 'all' ? 'with status "'.$tab_labels[$filter].'"' : 'yet' ?></h3>
          <p><?= $filter !== 'all' ? 'Try a different filter.' : "You haven't made any bookings yet." ?></p>
          <?php if($filter === 'all'): ?>
            <a href="<?= BASE_URL ?>/book" class="btn-book-now">Book Your First Service →</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="bookings-list">
          <?php foreach($bookings as $b):
            $sc       = $status_config[$b['status']] ?? $status_config['pending'];
            $rejected = in_array($b['status'], ['rejected','cancelled']);

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

            // Build modal data
            $modal = json_encode([
              'id'                => $b['id'],
              'service_name'      => implode(' + ', $svc_names),
              'service_icon'      => $b['service_icon'] ?: '🏠',
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
              'calculated_price'  => number_format((float)($b['calculated_price'] ?? 0)),
              'created_at'        => date('d M Y', strtotime($b['created_at'])),
              'has_cleaning'      => (in_array(6, array_map('intval', explode(',', $b['selected_services'] ?: '0'))) || in_array(7, array_map('intval', explode(',', $b['selected_services'] ?: '0')))),
              'has_chef'          => in_array(9, array_map('intval', explode(',', $b['selected_services'] ?: '0'))),
            ], JSON_HEX_QUOT | JSON_HEX_TAG);
          ?>

            <div class="booking-card">
              <!-- Header -->
              <div class="booking-card-header">
                <div class="booking-service">
                  <div class="booking-icon"><?= $b['service_icon']?:'🏠' ?></div>
                  <div>
                    <div class="booking-service-name"><?= e(implode(' + ', $svc_names)) ?></div>
                    <div class="booking-ref">Booking #<?= $b['id'] ?> · <?= date('d M Y', strtotime($b['created_at'])) ?></div>
                  </div>
                </div>
                <span class="status-pill" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>">
                  <?= $sc['icon'] ?> <?= $sc['label'] ?>
                </span>
              </div>

              <!-- Progress tracker -->
              <?php if(!$rejected): ?>
              <div class="progress-tracker">
                <?php
                $track_steps = ['pending'=>'Submitted','under_review'=>'Reviewing','approved'=>'Approved','completed'=>'Done'];
                $keys = array_keys($track_steps);
                $cur  = array_search($b['status'], $keys);
                $tot  = count($keys);
                foreach($track_steps as $sk => $slabel):
                  $idx        = array_search($sk,$keys);
                  $is_done    = $cur !== false && $idx < $cur;
                  $is_current = $b['status'] === $sk;
                ?>
                  <div class="progress-item">
                    <div class="progress-dot <?= $is_done?'done':($is_current?'current':'') ?>"><?= $is_done?'✓':($idx+1) ?></div>
                    <div class="progress-label <?= $is_done?'done':($is_current?'current':'') ?>"><?= $slabel ?></div>
                  </div>
                  <?php if($idx < $tot-1): ?><div class="progress-line <?= $is_done?'done':'' ?>"></div><?php endif; ?>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

              <!-- Body -->
              <div class="booking-card-body">
                <div class="booking-detail"><span class="booking-detail-label">🔄 Frequency</span><span class="booking-detail-value"><?= e($b['frequency']?:'—') ?></span></div>
                <div class="booking-detail"><span class="booking-detail-label">📍 Neighbourhood</span><span class="booking-detail-value"><?= e($b['neighbourhood']?:'—') ?></span></div>
                <div class="booking-detail"><span class="booking-detail-label">💰 Est. Price</span><span class="booking-detail-value"><?= number_format((float)($b['calculated_price']??0)) ?> RWF</span></div>
                <div class="booking-detail"><span class="booking-detail-label">👤 Team</span><span class="booking-detail-value"><?= $b['team_name'] ? e($b['team_name']) : 'Pending' ?></span></div>
              </div>

              <!-- Footer -->
              <div class="booking-card-footer">
                <div class="booking-notes">
                  <?php if(!empty($b['agent_notes'])): ?>
                    💬 <?= e(mb_substr($b['agent_notes'],0,60)) ?><?= mb_strlen($b['agent_notes'])>60?'...':'' ?>
                  <?php elseif(!empty($b['languages'])): ?>
                    🗣️ <?= e($b['languages']) ?>
                  <?php else: ?>
                    <span style="color:var(--text-soft)">Tap View Details for full info</span>
                  <?php endif; ?>
                </div>
                <div class="booking-actions">
                  <button class="btn-view" onclick='openDetailModal(<?= $modal ?>)'>👁 View Details</button>
                  <?php if($b['status']==='pending'): ?>
                    <button class="btn-cancel-booking" onclick="askCancel(<?= $b['id'] ?>)">🚫 Cancel</button>
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
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');document.body.style.overflow=document.getElementById('sidebar').classList.contains('open')?'hidden':'';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
window.addEventListener('resize',function(){if(window.innerWidth>768)closeSidebar();});

// ── CANCEL MODAL ──────────────────────────────────────────────
function askCancel(id){document.getElementById('cancelBookingId').value=id;document.getElementById('cancelModal').classList.add('open');document.body.style.overflow='hidden';}
function closeCancelModal(){document.getElementById('cancelModal').classList.remove('open');document.body.style.overflow='';}
function confirmCancel(){document.getElementById('cancelForm').submit();}
document.getElementById('cancelModal').addEventListener('click',function(e){if(e.target===this)closeCancelModal();});

// ── VIEW DETAILS MODAL ────────────────────────────────────────
function openDetailModal(d) {
  // Header
  document.getElementById('dm_icon').textContent    = d.service_icon;
  document.getElementById('dm_service').textContent = d.service_name;
  document.getElementById('dm_ref').textContent     = 'Booking #' + d.id + ' · Submitted ' + d.created_at;

  // Price
  document.getElementById('dm_price').textContent = d.calculated_price + ' RWF';

  // Status
  var pill = document.getElementById('dm_status_pill');
  pill.textContent   = d.status_icon + ' ' + d.status_label;
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
  document.getElementById('dm_team').textContent        = d.team_name;

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
      div.innerHTML = '<span class="icon-yes">✅</span> ' + item[1];
      cl.appendChild(div);
    });
    if (!d.checklist || !d.checklist.length) {
      var div = document.createElement('div');
      div.className = 'detail-check';
      div.innerHTML = '<span class="icon-no">—</span> No extras selected';
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
    ltags.innerHTML = '<span style="font-size:0.84rem;color:var(--text-soft);font-style:italic">No preference specified</span>';
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
}

function closeDetailModal(){
  document.getElementById('detailModal').classList.remove('open');
  document.body.style.overflow = '';
}
function handleOverlay(e){
  if(e.target===document.getElementById('detailModal')) closeDetailModal();
}
</script>
</body>
</html>