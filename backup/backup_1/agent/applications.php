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
    'pending'      => ['Pending',      's-pending',      '⏳'],
    'under_review' => ['Under Review', 's-under_review', '🔍'],
    'approved'     => ['Approved',     's-approved',     '✅'],
    'rejected'     => ['Rejected',     's-rejected',     '❌'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Applications | Team — Home Help</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --teal-deep:#0F2D3D;--teal-mid:#164E63;--teal-light:#0891B2;--teal-soft:#CFFAFE;--teal-sidebar:#0A2233;
  --amber:#E8A020;--amber-light:#FFF0CC;--cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --danger:#EF4444;--danger-bg:#FEF2F2;--success:#10B981;--success-bg:#ECFDF5;
  --warning:#F59E0B;--info:#3B82F6;--info-bg:#EFF6FF;
  --shadow-sm:0 2px 12px rgba(15,45,61,0.08);--shadow-md:0 6px 28px rgba(15,45,61,0.13);
  --radius:14px;--radius-sm:8px;--sidebar:260px;
  --font-display:'Cormorant Garamond',Georgia,serif;--font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden}
a{text-decoration:none;color:inherit}
.dash-wrap{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar);flex-shrink:0;background:var(--teal-sidebar);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;transition:transform 0.3s ease;overflow-y:auto}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:22px 20px 18px;border-bottom:1px solid rgba(255,255,255,0.07)}
.sidebar-logo img{width:34px;height:34px;object-fit:contain}
.sidebar-logo-text{font-family:var(--font-display);font-size:1.25rem;font-weight:700;color:var(--white)}
.agent-badge{display:inline-block;padding:2px 8px;border-radius:99px;background:rgba(8,145,178,0.25);border:1px solid rgba(8,145,178,0.45);color:#67E8F9;font-size:0.62rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;margin-left:auto;flex-shrink:0}
.sidebar-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;gap:12px}
.user-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--teal-light),var(--amber));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--white);flex-shrink:0}
.user-info strong{display:block;font-size:0.86rem;font-weight:700;color:var(--white)}
.user-info span{font-size:0.71rem;color:#67E8F9;font-weight:600}
.sidebar-nav{padding:14px 0;flex:1}
.nav-section-label{font-size:0.64rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.25);padding:12px 20px 5px}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 20px;font-size:0.86rem;font-weight:500;color:rgba(255,255,255,0.6);cursor:pointer;transition:all 0.2s;position:relative;text-decoration:none}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,0.06)}
.nav-item.active{color:var(--white);background:rgba(8,145,178,0.18);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--amber);border-radius:0 3px 3px 0}
.nav-icon{font-size:1rem;width:20px;text-align:center;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--danger);color:var(--white);font-size:0.63rem;font-weight:700;padding:2px 6px;border-radius:99px}
.sidebar-footer{padding:14px 20px;border-top:1px solid rgba(255,255,255,0.07)}
.btn-logout{display:flex;align-items:center;gap:8px;width:100%;padding:9px 14px;border-radius:var(--radius-sm);background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.18);color:rgba(255,100,100,0.85);font-size:0.83rem;font-weight:600;cursor:pointer;transition:all 0.2s;text-decoration:none;font-family:var(--font-body)}
.btn-logout:hover{background:rgba(239,68,68,0.2)}
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--white);border-bottom:1px solid var(--cream-dark);padding:0 28px;height:62px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar-left{display:flex;align-items:center;gap:14px}
.burger{display:none;background:none;border:none;cursor:pointer;padding:6px;flex-direction:column;gap:4px}
.burger span{display:block;width:20px;height:2px;background:var(--text-dark);border-radius:2px}
.topbar-title{font-family:var(--font-display);font-size:1.35rem;font-weight:700;color:var(--teal-deep)}
.topbar-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:99px;background:var(--teal-soft);color:var(--teal-deep);font-size:0.78rem;font-weight:700}
.content{padding:24px 28px;flex:1}
.flash{padding:12px 16px;border-radius:var(--radius-sm);font-size:0.86rem;font-weight:500;margin-bottom:18px;display:flex;align-items:center;gap:10px}
.flash-success{background:var(--success-bg);border:1.5px solid #BBF7D0;color:#065F46}
.flash-error{background:var(--danger-bg);border:1.5px solid #FECACA;color:#B91C1C}
.page-header{margin-bottom:20px}
.page-header h1{font-family:var(--font-display);font-size:1.9rem;font-weight:700;color:var(--teal-deep)}
.page-header h1 em{font-style:italic;color:var(--amber)}
.filter-bar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px}
.filter-tabs{display:flex;gap:6px;flex-wrap:wrap}
.filter-tab{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:99px;font-size:0.8rem;font-weight:600;border:1.5px solid var(--cream-dark);background:var(--white);color:var(--text-mid);cursor:pointer;transition:all 0.2s;text-decoration:none}
.filter-tab:hover{border-color:var(--teal-light);color:var(--teal-deep)}
.filter-tab.active{background:var(--teal-sidebar);border-color:var(--teal-sidebar);color:var(--white)}
.filter-count{background:rgba(255,255,255,0.2);font-size:0.68rem;font-weight:700;padding:1px 5px;border-radius:99px}
.filter-tab:not(.active) .filter-count{background:var(--cream-dark);color:var(--text-soft)}
.search-wrap{position:relative}
.search-wrap input{padding:8px 14px 8px 36px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.84rem;background:var(--white);outline:none;width:220px;transition:all 0.2s}
.search-wrap input:focus{border-color:var(--teal-light);box-shadow:0 0 0 3px rgba(8,145,178,0.1)}
.search-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:0.85rem;pointer-events:none}
.card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden}
.table-wrap{overflow-x:auto}
.admin-table{width:100%;border-collapse:collapse;min-width:860px}
.admin-table th{font-size:0.69rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:var(--text-soft);padding:11px 16px;text-align:left;background:var(--cream);border-bottom:1px solid var(--cream-dark);white-space:nowrap}
.admin-table td{padding:13px 16px;font-size:0.83rem;border-bottom:1px solid var(--cream-dark);vertical-align:middle}
.admin-table tr:last-child td{border-bottom:none}
.admin-table tr:hover td{background:#f7fffe}
.prov-cell{display:flex;align-items:center;gap:8px}
.prov-av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--amber),#c47d0a);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:0.82rem;font-weight:700;color:var(--white);flex-shrink:0}
.cell-name{font-weight:600;font-size:0.84rem;color:var(--text-dark)}
.cell-sub{font-size:0.73rem;color:var(--text-soft);margin-top:1px}
.service-chip{display:inline-flex;align-items:center;gap:4px;font-size:0.8rem;color:var(--text-mid)}
.status-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;font-size:0.7rem;font-weight:700;white-space:nowrap}
.s-pending{background:#FFFBEB;color:#92400E}.s-under_review{background:#EFF6FF;color:#1E40AF}
.s-approved{background:#ECFDF5;color:#065F46}.s-rejected{background:#FEF2F2;color:#991B1B}
.actions-cell{display:flex;gap:5px;flex-wrap:wrap}
.act-btn{display:inline-flex;align-items:center;gap:3px;padding:4px 9px;border-radius:var(--radius-sm);font-size:0.73rem;font-weight:600;cursor:pointer;border:1.5px solid;transition:all 0.2s;background:var(--white);font-family:var(--font-body);white-space:nowrap}
.act-approve{border-color:#BBF7D0;color:#065F46}.act-approve:hover{background:var(--success-bg)}
.act-reject{border-color:#FECACA;color:#B91C1C}.act-reject:hover{background:var(--danger-bg)}
.act-review{border-color:#BFDBFE;color:#1E40AF}.act-review:hover{background:var(--info-bg)}
.act-view{border-color:var(--amber);color:#92400E;background:var(--amber-light)}.act-view:hover{background:#FDE68A}
.empty-state{text-align:center;padding:48px 20px;color:var(--text-soft)}

/* ACTION MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:500;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:var(--white);border-radius:var(--radius);padding:28px;max-width:440px;width:100%;box-shadow:var(--shadow-md)}
.modal h3{font-family:var(--font-display);font-size:1.35rem;color:var(--teal-deep);margin-bottom:6px}
.modal p{font-size:0.86rem;color:var(--text-mid);margin-bottom:14px}
.modal textarea{width:100%;padding:10px 14px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.86rem;resize:vertical;min-height:80px;outline:none;background:var(--cream);margin-bottom:16px}
.modal textarea:focus{border-color:var(--teal-light)}
.modal-actions{display:flex;gap:10px;justify-content:flex-end}
.btn-modal-cancel{padding:9px 18px;border-radius:var(--radius-sm);border:1.5px solid var(--cream-dark);background:transparent;color:var(--text-mid);font-size:0.86rem;font-weight:600;cursor:pointer;font-family:var(--font-body)}
.btn-modal-approve{padding:9px 18px;border-radius:var(--radius-sm);background:var(--teal-deep);border:none;color:var(--white);font-size:0.86rem;font-weight:700;cursor:pointer;font-family:var(--font-body)}
.btn-modal-approve:hover{background:var(--teal-mid)}
.btn-modal-reject{padding:9px 18px;border-radius:var(--radius-sm);background:var(--danger);border:none;color:var(--white);font-size:0.86rem;font-weight:700;cursor:pointer;font-family:var(--font-body)}

/* DETAILS MODAL */
.detail-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:600;align-items:center;justify-content:center;padding:20px}
.detail-overlay.open{display:flex}
.detail-box{background:var(--white);border-radius:var(--radius);max-width:600px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.2);overflow:hidden;max-height:90vh;display:flex;flex-direction:column}
.detail-head{background:linear-gradient(135deg,var(--teal-sidebar),var(--teal-mid));padding:22px 26px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.detail-head-left{display:flex;align-items:center;gap:14px}
.detail-svc-icon{width:50px;height:50px;border-radius:14px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0}
.detail-svc-name{font-family:var(--font-display);font-size:1.2rem;font-weight:700;color:var(--white)}
.detail-app-ref{font-size:0.75rem;color:rgba(255,255,255,0.6);margin-top:2px}
.detail-close{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.15);border:none;color:var(--white);font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.2s;flex-shrink:0}
.detail-close:hover{background:rgba(255,255,255,0.25)}
.applicant-strip{background:var(--cream);padding:14px 26px;display:flex;align-items:center;gap:14px;border-bottom:1px solid var(--cream-dark);flex-shrink:0}
.applicant-av{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--amber),#c47d0a);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--white);flex-shrink:0}
.applicant-name{font-weight:700;font-size:0.96rem;color:var(--text-dark)}
.applicant-contact{font-size:0.78rem;color:var(--text-soft);margin-top:2px}
.detail-status-pill{margin-left:auto;display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:99px;font-size:0.76rem;font-weight:700;flex-shrink:0}
.detail-body{padding:22px 26px;overflow-y:auto;flex:1}
.detail-section{margin-bottom:20px}
.detail-section-title{font-size:0.68rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-soft);margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid var(--cream-dark)}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.detail-field{display:flex;flex-direction:column;gap:3px}
.detail-field-label{font-size:0.7rem;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.05em}
.detail-field-value{font-size:0.87rem;font-weight:600;color:var(--text-dark)}
.detail-text-block{font-size:0.86rem;color:var(--text-mid);line-height:1.65;background:var(--cream);border-radius:var(--radius-sm);padding:12px 14px}
.detail-note-block{background:var(--amber-light);border:1.5px solid #FCD34D;border-radius:var(--radius-sm);padding:12px 14px;font-size:0.85rem;color:#92400E}
.detail-foot{padding:16px 26px;border-top:1px solid var(--cream-dark);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-shrink:0;flex-wrap:wrap}
.detail-foot-actions{display:flex;gap:8px}
.btn-detail-approve{padding:9px 18px;border-radius:99px;background:var(--teal-deep);border:none;color:var(--white);font-size:0.85rem;font-weight:700;cursor:pointer;font-family:var(--font-body)}
.btn-detail-approve:hover{background:var(--teal-mid)}
.btn-detail-reject{padding:9px 18px;border-radius:99px;background:var(--danger);border:none;color:var(--white);font-size:0.85rem;font-weight:700;cursor:pointer;font-family:var(--font-body)}
.btn-detail-close-foot{padding:9px 18px;border-radius:99px;border:1.5px solid var(--cream-dark);background:transparent;color:var(--text-mid);font-size:0.85rem;font-weight:600;cursor:pointer;font-family:var(--font-body)}

.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:199}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .content{padding:16px}.topbar{padding:0 16px}.detail-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- ACTION MODAL -->
<div class="modal-overlay" id="actionModal">
  <div class="modal">
    <h3 id="modalTitle">Confirm Action</h3>
    <p id="modalDesc">Add a note for the applicant (optional).</p>
    <textarea id="modalNotes" placeholder="Optional note..."></textarea>
    <div class="modal-actions">
      <button class="btn-modal-cancel" onclick="closeActionModal()">Cancel</button>
      <button id="modalConfirmBtn" class="btn-modal-approve" onclick="submitAction()">Confirm</button>
    </div>
  </div>
</div>

<!-- DETAILS MODAL -->
<div class="detail-overlay" id="detailModal" onclick="handleDetailOverlay(event)">
  <div class="detail-box">
    <div class="detail-head">
      <div class="detail-head-left">
        <div class="detail-svc-icon" id="dm_icon">💼</div>
        <div>
          <div class="detail-svc-name" id="dm_service">Service</div>
          <div class="detail-app-ref"  id="dm_ref">Application #</div>
        </div>
      </div>
      <button class="detail-close" onclick="closeDetailModal()">✕</button>
    </div>
    <div class="applicant-strip">
      <div class="applicant-av" id="dm_av">A</div>
      <div>
        <div class="applicant-name"    id="dm_name">—</div>
        <div class="applicant-contact" id="dm_contact">—</div>
      </div>
      <span class="detail-status-pill" id="dm_status_pill">⏳ Pending</span>
    </div>
    <div class="detail-body">
      <div class="detail-section">
        <div class="detail-section-title">📋 Application Details</div>
        <div class="detail-grid">
          <div class="detail-field"><span class="detail-field-label">📍 Location</span><span class="detail-field-value" id="dm_location">—</span></div>
          <div class="detail-field"><span class="detail-field-label">🗺️ District</span><span class="detail-field-value" id="dm_district">—</span></div>
          <div class="detail-field"><span class="detail-field-label">⏰ Availability</span><span class="detail-field-value" id="dm_availability">—</span></div>
          <div class="detail-field"><span class="detail-field-label">💰 Expected Pay</span><span class="detail-field-value" id="dm_pay">—</span></div>
          <div class="detail-field"><span class="detail-field-label">🗣️ Languages</span><span class="detail-field-value" id="dm_languages">—</span></div>
          <div class="detail-field"><span class="detail-field-label">📅 Applied On</span><span class="detail-field-value" id="dm_date">—</span></div>
        </div>
      </div>
      <div class="detail-section">
        <div class="detail-section-title">💼 Work Experience</div>
        <div class="detail-text-block" id="dm_experience">—</div>
      </div>
      <div class="detail-section" id="dm_bio_section">
        <div class="detail-section-title">🙋 About the Applicant</div>
        <div class="detail-text-block" id="dm_bio">—</div>
      </div>
      <div class="detail-section" id="dm_note_section" style="display:none">
        <div class="detail-section-title">💬 Your Note</div>
        <div class="detail-note-block" id="dm_note">—</div>
      </div>
    </div>
    <div class="detail-foot">
      <button class="btn-detail-close-foot" onclick="closeDetailModal()">Close</button>
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

<div class="dash-wrap">
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="../Image/icon/homehelp_logo_transparent_white.svg" alt="Home Help" onerror="this.style.display='none'"/>
      <span class="sidebar-logo-text">Home Help</span>
      <span class="agent-badge">Team</span>
    </div>
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($user_name,0,1)) ?></div>
      <div class="user-info"><strong><?= e($user_name) ?></strong><span>Team Member</span></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="<?= BASE_URL ?>/agent"              class="nav-item"><span class="nav-icon">📊</span> Dashboard</a>
      <div class="nav-section-label">My Work</div>
      <a href="<?= BASE_URL ?>/agent/bookings"     class="nav-item"><span class="nav-icon">📋</span> Bookings</a>
      <a href="<?= BASE_URL ?>/agent/applications" class="nav-item active">
        <span class="nav-icon">💼</span> Applications
        <?php if(($counts['pending']??0)>0): ?><span class="nav-badge"><?= $counts['pending'] ?></span><?php endif; ?>
      </a>
      <div class="nav-section-label">Reports</div>
      <a href="<?= BASE_URL ?>/agent/reports"      class="nav-item"><span class="nav-icon">📈</span> My Reports</a>
      <div class="nav-section-label">View As</div>
      <a href="<?= BASE_URL ?>"                    class="nav-item"><span class="nav-icon">🌐</span> View Website</a>
    </nav>
    <div class="sidebar-footer">
      <a href="<?= BASE_URL ?>/logout" class="btn-logout"><span>🚪</span> Sign Out</a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="burger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <span class="topbar-title">Job Applications</span>
      </div>
      <div class="topbar-right">
        <span class="topbar-chip">🎯 Team Panel</span>
      </div>
    </header>

    <div class="content">
      <?php if($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>">
          <?= $flash['type']==='success'?'✅':'⚠️' ?> <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="page-header">
        <h1>Job <em>Applications</em></h1>
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
          <span class="search-icon">🔍</span>
          <?php if($filter!=='all'): ?><input type="hidden" name="status" value="<?= e($filter) ?>"/><?php endif; ?>
          <input type="text" name="search" placeholder="Search name, service..." value="<?= e($search) ?>" autocomplete="off"/>
        </form>
      </div>

      <div class="card">
        <div class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr><th>#</th><th>Applicant</th><th>Service</th><th>Location</th><th>Availability</th><th>Status</th><th>Applied</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if(empty($applications)): ?>
                <tr><td colspan="8"><div class="empty-state">💼 No applications assigned yet.</div></td></tr>
              <?php else: ?>
                <?php foreach($applications as $ap):
                  $sc=$status_config[$ap['status']]??['Unknown','s-pending','❓'];
                  $modal_data=json_encode([
                    'id'=>$ap['id'],'service_name'=>$ap['service_name'],'service_icon'=>$ap['service_icon']?:'💼',
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
                    <td style="font-size:0.74rem;color:var(--text-soft);font-weight:700">#<?= $ap['id'] ?></td>
                    <td>
                      <div class="prov-cell">
                        <div class="prov-av"><?= strtoupper(substr($ap['provider_name'],0,1)) ?></div>
                        <div>
                          <div class="cell-name"><?= e($ap['provider_name']) ?></div>
                          <div class="cell-sub"><?= e($ap['provider_phone']??$ap['provider_email']??'') ?></div>
                        </div>
                      </div>
                    </td>
                    <td><span class="service-chip"><?= $ap['service_icon']?:'💼' ?> <?= e($ap['service_name']) ?></span></td>
                    <td style="font-size:0.8rem;color:var(--text-mid);max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($ap['location']?:'—') ?></td>
                    <td style="font-size:0.8rem;color:var(--text-mid)"><?= e($ap['availability']?:'—') ?></td>
                    <td><span class="status-pill <?= $sc[1] ?>"><?= $sc[2] ?> <?= $sc[0] ?></span></td>
                    <td style="font-size:0.77rem;color:var(--text-soft);white-space:nowrap"><?= date('d M Y',strtotime($ap['created_at'])) ?></td>
                    <td>
                      <div class="actions-cell">
                        <button class="act-btn act-view" onclick="openDetailModal(<?= htmlspecialchars($modal_data,ENT_QUOTES) ?>)">👁 View</button>
                        <?php if(in_array($ap['status'],['pending','under_review'])): ?>
                          <button class="act-btn act-approve" onclick="openActionModal('approve',<?= $ap['id'] ?>,'Approve Application #<?= $ap['id'] ?>','Optional note for the applicant.')">✅</button>
                          <button class="act-btn act-reject"  onclick="openActionModal('reject', <?= $ap['id'] ?>,'Reject Application #<?= $ap['id'] ?>','Please provide a reason.')">❌</button>
                        <?php endif; ?>
                        <?php if($ap['status']==='pending'): ?>
                          <button class="act-btn act-review" onclick="quickAction('under_review',<?= $ap['id'] ?>)">🔍</button>
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
    </div>
    <?php include_once '../includes/footer.php'; ?>
  </div>
</div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');document.body.style.overflow=document.getElementById('sidebar').classList.contains('open')?'hidden':'';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
window.addEventListener('resize',function(){if(window.innerWidth>768)closeSidebar();});
var _ca='',_ci=0;
function openActionModal(action,id,title,desc){
  _ca=action;_ci=id;
  document.getElementById('modalTitle').textContent=title;
  document.getElementById('modalDesc').textContent=desc;
  document.getElementById('modalNotes').value='';
  var btn=document.getElementById('modalConfirmBtn');
  if(action==='approve'){btn.className='btn-modal-approve';btn.textContent='✅ Approve';}
  else{btn.className='btn-modal-reject';btn.textContent='❌ Reject';}
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
  document.getElementById('dm_icon').textContent=d.service_icon;
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
  pill.textContent=d.status_icon+' '+d.status_label;
  pill.className='detail-status-pill '+d.status_class;
  if(d.bio){document.getElementById('dm_bio').textContent=d.bio;document.getElementById('dm_bio_section').style.display='block';}
  else{document.getElementById('dm_bio_section').style.display='none';}
  if(d.agent_notes){document.getElementById('dm_note').textContent=d.agent_notes;document.getElementById('dm_note_section').style.display='block';}
  else{document.getElementById('dm_note_section').style.display='none';}
  var acts=document.getElementById('dm_actions');acts.innerHTML='';
  if(d.status==='pending'||d.status==='under_review'){
    acts.innerHTML='<button class="btn-detail-approve" onclick="closeDetailModal();openActionModal(\'approve\','+d.id+',\'Approve Application #'+d.id+'\',\'Optional note.\')">✅ Approve</button>'+
    '<button class="btn-detail-reject" onclick="closeDetailModal();openActionModal(\'reject\','+d.id+',\'Reject Application #'+d.id+'\',\'Please provide a reason.\')">❌ Reject</button>';
  }
  document.getElementById('detailModal').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeDetailModal(){document.getElementById('detailModal').classList.remove('open');document.body.style.overflow='';}
function handleDetailOverlay(e){if(e.target===document.getElementById('detailModal'))closeDetailModal();}
</script>
</body>
</html>