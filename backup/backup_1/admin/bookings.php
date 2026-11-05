<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('admin');

$admin_name = $_SESSION['user_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $action     = $_POST['action']     ?? '';
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $notes      = sanitize($_POST['notes']   ?? '');
    $agent_id   = (int)($_POST['agent_id']   ?? 0);

    if ($booking_id) {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE bookings SET status='approved', agent_notes=? WHERE id=?");
            $stmt->bind_param('si', $notes, $booking_id); $stmt->execute();
            $b = $conn->query("SELECT client_id FROM bookings WHERE id=$booking_id")->fetch_assoc();
            if ($b) logNotification($b['client_id'], 'booking_approved', 'Your booking has been approved! Our team will be in touch shortly.');

            // ── EMAIL THE CLIENT ───────────────────────────────
            require_once '../includes/mailer.php';
            $info = $conn->query("
                SELECT b.calculated_price, b.selected_services,
                       u.name AS client_name, u.email AS client_email,
                       s.name AS service_name
                FROM bookings b
                JOIN users u    ON b.client_id  = u.id
                JOIN services s ON b.service_id = s.id
                WHERE b.id = $booking_id
                LIMIT 1
            ")->fetch_assoc();

            if ($info && !empty($info['client_email'])) {
                $svc_names = [];
                if (!empty($info['selected_services'])) {
                    $svc_ids = array_filter(array_map('intval', explode(',', $info['selected_services'])));
                    if ($svc_ids) {
                        $svc_result = $conn->query("SELECT name FROM services WHERE id IN (".implode(',', $svc_ids).")");
                        while ($sv = $svc_result->fetch_assoc()) $svc_names[] = $sv['name'];
                    }
                }
                $service_label = $svc_names ? implode(' + ', $svc_names) : $info['service_name'];

                $approval_data = [
                    'client_name'      => $info['client_name'],
                    'service_name'     => $service_label,
                    'agent_note'       => $notes,
                    'booking_id'       => $booking_id,
                    'calculated_price' => $info['calculated_price'],
                ];
                sendBookingEmail(
                    $info['client_email'],
                    '✅ Your Booking Has Been Approved — Home Help Rwanda',
                    buildApprovalEmailHTML($approval_data)
                );
            }

            flashSet('success', "Booking #$booking_id approved successfully.");
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE bookings SET status='rejected', agent_notes=? WHERE id=?");
            $stmt->bind_param('si', $notes, $booking_id); $stmt->execute();
            $b = $conn->query("SELECT client_id FROM bookings WHERE id=$booking_id")->fetch_assoc();
            if ($b) logNotification($b['client_id'], 'booking_rejected', 'Your booking has been reviewed. Unfortunately it was not approved at this time.');
            flashSet('success', "Booking #$booking_id rejected.");
        } elseif ($action === 'under_review') {
            $stmt = $conn->prepare("UPDATE bookings SET status='under_review' WHERE id=?");
            $stmt->bind_param('i', $booking_id); $stmt->execute();
            flashSet('success', "Booking #$booking_id marked as Under Review.");
        } elseif ($action === 'complete') {
            $stmt = $conn->prepare("UPDATE bookings SET status='completed' WHERE id=?");
            $stmt->bind_param('i', $booking_id); $stmt->execute();
            flashSet('success', "Booking #$booking_id marked as Completed.");
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
$allowed = ['all','pending','under_review','approved','completed','rejected','cancelled'];
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

$status_config = [
    'pending'      => ['Pending',      's-pending',      '⏳'],
    'under_review' => ['Under Review', 's-under_review', '🔍'],
    'approved'     => ['Approved',     's-approved',     '✅'],
    'completed'    => ['Completed',    's-completed',    '🏆'],
    'rejected'     => ['Rejected',     's-rejected',     '❌'],
    'cancelled'    => ['Cancelled',    's-cancelled',    '🚫'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Bookings | Admin — Home Help</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --admin-deep:#13103A;--admin-mid:#2D2A6E;--admin-light:#4F46E5;--admin-soft:#E0E7FF;
  --amber:#E8A020;--amber-light:#FFF0CC;--cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --danger:#EF4444;--danger-bg:#FEF2F2;--success:#10B981;--success-bg:#ECFDF5;
  --warning:#F59E0B;--warning-bg:#FFFBEB;--info:#3B82F6;--info-bg:#EFF6FF;
  --shadow-sm:0 2px 12px rgba(19,16,58,0.07);--shadow-md:0 6px 28px rgba(19,16,58,0.11);
  --radius:14px;--radius-sm:8px;--admin-sidebar:#0E0C2E;--sidebar:260px;
  --font-display:'Cormorant Garamond',Georgia,serif;--font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden}
a{text-decoration:none;color:inherit}
.dash-wrap{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar);flex-shrink:0;background:var(--admin-sidebar);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;transition:transform 0.3s ease;overflow-y:auto}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:22px 20px 18px;border-bottom:1px solid rgba(255,255,255,0.07)}
.sidebar-logo img{width:34px;height:34px;object-fit:contain}
.sidebar-logo-text{font-family:var(--font-display);font-size:1.25rem;font-weight:700;color:var(--white)}
.admin-badge{display:inline-block;padding:2px 8px;border-radius:99px;background:rgba(232,160,32,0.2);border:1px solid rgba(232,160,32,0.35);color:var(--amber);font-size:0.62rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;margin-left:auto;flex-shrink:0}
.sidebar-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;gap:12px}
.user-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--amber),#c47d0a);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--white);flex-shrink:0}
.user-info strong{display:block;font-size:0.86rem;font-weight:700;color:var(--white)}
.user-info span{font-size:0.71rem;color:var(--amber);font-weight:600}
.sidebar-nav{padding:14px 0;flex:1}
.nav-section-label{font-size:0.64rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.25);padding:12px 20px 5px}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 20px;font-size:0.86rem;font-weight:500;color:rgba(255,255,255,0.6);cursor:pointer;transition:all 0.2s;position:relative;text-decoration:none}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,0.05)}
.nav-item.active{color:var(--white);background:rgba(79,70,229,0.18);font-weight:600}
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
.topbar-title{font-family:var(--font-display);font-size:1.35rem;font-weight:700;color:var(--admin-deep)}
.topbar-right{display:flex;align-items:center;gap:10px}
.topbar-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:99px;background:var(--admin-soft);color:var(--admin-mid);font-size:0.78rem;font-weight:700}
.content{padding:24px 28px;flex:1}
.flash{padding:12px 16px;border-radius:var(--radius-sm);font-size:0.86rem;font-weight:500;margin-bottom:18px;display:flex;align-items:center;gap:10px}
.flash-success{background:var(--success-bg);border:1.5px solid #BBF7D0;color:#065F46}
.flash-error{background:var(--danger-bg);border:1.5px solid #FECACA;color:#B91C1C}
.page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:20px}
.page-header h1{font-family:var(--font-display);font-size:1.9rem;font-weight:700;color:var(--admin-deep)}
.page-header h1 em{font-style:italic;color:var(--admin-light)}
.filter-bar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px}
.filter-tabs{display:flex;gap:6px;flex-wrap:wrap}
.filter-tab{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:99px;font-size:0.8rem;font-weight:600;border:1.5px solid var(--cream-dark);background:var(--white);color:var(--text-mid);cursor:pointer;transition:all 0.2s;text-decoration:none}
.filter-tab:hover{border-color:var(--admin-light);color:var(--admin-deep)}
.filter-tab.active{background:var(--admin-sidebar);border-color:var(--admin-sidebar);color:var(--white)}
.filter-count{background:rgba(255,255,255,0.2);font-size:0.68rem;font-weight:700;padding:1px 5px;border-radius:99px}
.filter-tab:not(.active) .filter-count{background:var(--cream-dark);color:var(--text-soft)}
.search-wrap{position:relative}
.search-wrap input{padding:8px 14px 8px 36px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.84rem;background:var(--white);outline:none;width:220px;transition:all 0.2s}
.search-wrap input:focus{border-color:var(--admin-light);box-shadow:0 0 0 3px rgba(79,70,229,0.1)}
.search-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:0.85rem;pointer-events:none}
.card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden}
.table-wrap{overflow-x:auto}
.admin-table{width:100%;border-collapse:collapse;min-width:900px}
.admin-table th{font-size:0.69rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:var(--text-soft);padding:11px 16px;text-align:left;background:var(--cream);border-bottom:1px solid var(--cream-dark);white-space:nowrap}
.admin-table td{padding:13px 16px;font-size:0.83rem;border-bottom:1px solid var(--cream-dark);vertical-align:middle}
.admin-table tr:last-child td{border-bottom:none}
.admin-table tr:hover td{background:#fefcf8}
.client-cell{display:flex;align-items:center;gap:8px}
.client-av{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--admin-light),var(--admin-deep));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:0.8rem;font-weight:700;color:var(--white);flex-shrink:0}
.cell-name{font-weight:600;color:var(--text-dark);font-size:0.83rem}
.cell-sub{font-size:0.72rem;color:var(--text-soft);margin-top:1px}
.service-chip{display:inline-flex;align-items:center;gap:5px;font-size:0.81rem;color:var(--text-mid)}
.status-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;font-size:0.7rem;font-weight:700;white-space:nowrap}
.s-pending{background:#FFFBEB;color:#92400E}
.s-under_review{background:#EFF6FF;color:#1E40AF}
.s-approved{background:#ECFDF5;color:#065F46}
.s-completed{background:var(--admin-soft);color:var(--admin-deep)}
.s-rejected{background:#FEF2F2;color:#991B1B}
.s-cancelled{background:#F3F4F6;color:#6B7280}
.date-txt{font-size:0.77rem;color:var(--text-soft);white-space:nowrap}
.actions-cell{display:flex;gap:5px;flex-wrap:wrap}
.act-btn{display:inline-flex;align-items:center;gap:3px;padding:4px 9px;border-radius:var(--radius-sm);font-size:0.73rem;font-weight:600;cursor:pointer;border:1.5px solid;transition:all 0.2s;background:var(--white);font-family:var(--font-body);white-space:nowrap}
.act-approve{border-color:#BBF7D0;color:#065F46}.act-approve:hover{background:var(--success-bg)}
.act-reject{border-color:#FECACA;color:#B91C1C}.act-reject:hover{background:var(--danger-bg)}
.act-review{border-color:#BFDBFE;color:#1E40AF}.act-review:hover{background:var(--info-bg)}
.act-complete{border-color:var(--admin-soft);color:var(--admin-deep)}.act-complete:hover{background:var(--admin-soft)}
.act-view{border-color:var(--amber);color:#92400E;background:var(--amber-light)}.act-view:hover{background:#FDE68A}
.empty-state{text-align:center;padding:48px 20px}
.empty-state p{color:var(--text-soft);font-size:0.9rem}
.assign-wrap{display:flex;gap:6px;align-items:center}
.assign-select{padding:5px 8px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.75rem;background:var(--white);outline:none;max-width:130px}
.btn-assign{padding:5px 10px;border-radius:var(--radius-sm);background:var(--admin-deep);border:none;color:var(--white);font-size:0.73rem;font-weight:700;cursor:pointer;font-family:var(--font-body)}

/* ACTION MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:500;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:var(--white);border-radius:var(--radius);padding:28px;max-width:440px;width:100%;box-shadow:var(--shadow-md)}
.modal h3{font-family:var(--font-display);font-size:1.35rem;color:var(--admin-deep);margin-bottom:6px}
.modal p{font-size:0.86rem;color:var(--text-mid);margin-bottom:16px}
.modal textarea{width:100%;padding:10px 14px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.86rem;resize:vertical;min-height:80px;outline:none;background:var(--cream);margin-bottom:16px}
.modal textarea:focus{border-color:var(--admin-light)}
.modal-actions{display:flex;gap:10px;justify-content:flex-end}
.btn-modal-cancel{padding:9px 18px;border-radius:var(--radius-sm);border:1.5px solid var(--cream-dark);background:transparent;color:var(--text-mid);font-size:0.86rem;font-weight:600;cursor:pointer;font-family:var(--font-body)}
.btn-modal-approve{padding:9px 18px;border-radius:var(--radius-sm);background:var(--admin-deep);border:none;color:var(--white);font-size:0.86rem;font-weight:700;cursor:pointer;font-family:var(--font-body)}
.btn-modal-approve:hover{background:var(--admin-mid)}
.btn-modal-reject{padding:9px 18px;border-radius:var(--radius-sm);background:var(--danger);border:none;color:var(--white);font-size:0.86rem;font-weight:700;cursor:pointer;font-family:var(--font-body)}

/* VIEW DETAILS MODAL */
.detail-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:600;align-items:center;justify-content:center;padding:20px}
.detail-overlay.open{display:flex}
.detail-box{background:var(--white);border-radius:var(--radius);max-width:640px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.2);overflow:hidden;max-height:90vh;display:flex;flex-direction:column}
.detail-head{background:linear-gradient(135deg,var(--admin-sidebar),var(--admin-mid));padding:22px 26px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.detail-head-left{display:flex;align-items:center;gap:14px}
.detail-svc-icon{width:50px;height:50px;border-radius:14px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0}
.detail-svc-name{font-family:var(--font-display);font-size:1.2rem;font-weight:700;color:var(--white)}
.detail-ref{font-size:0.75rem;color:rgba(255,255,255,0.6);margin-top:2px}
.detail-close{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.15);border:none;color:var(--white);font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.2s;flex-shrink:0}
.detail-close:hover{background:rgba(255,255,255,0.25)}

/* Client strip */
.client-strip{background:var(--cream);padding:12px 26px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--cream-dark);flex-shrink:0}
.client-strip-av{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--admin-light),var(--admin-deep));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--white);flex-shrink:0}
.client-strip-name{font-weight:700;font-size:0.92rem;color:var(--text-dark)}
.client-strip-contact{font-size:0.76rem;color:var(--text-soft);margin-top:1px}
.detail-status-pill{margin-left:auto;display:inline-flex;align-items:center;gap:4px;padding:4px 11px;border-radius:99px;font-size:0.74rem;font-weight:700;flex-shrink:0}

/* Price strip */
.detail-price-strip{background:linear-gradient(135deg,var(--admin-sidebar),var(--admin-mid));padding:0 26px 16px;flex-shrink:0}
.detail-price-inner{background:rgba(255,255,255,0.1);border-radius:10px;padding:12px 18px;display:flex;align-items:center;justify-content:space-between}
.detail-price-label{font-size:0.76rem;color:rgba(255,255,255,0.65)}
.detail-price-amount{font-family:var(--font-display);font-size:1.5rem;font-weight:700;color:var(--white)}

.detail-body{padding:22px 26px;overflow-y:auto;flex:1}
.detail-section{margin-bottom:20px}
.detail-section:last-child{margin-bottom:0}
.detail-section-title{font-size:0.68rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-soft);margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid var(--cream-dark);display:flex;align-items:center;gap:6px}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.detail-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.detail-field{display:flex;flex-direction:column;gap:3px}
.detail-field-label{font-size:0.7rem;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.05em}
.detail-field-value{font-size:0.87rem;font-weight:600;color:var(--text-dark)}

/* Tags */
.detail-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:2px}
.detail-tag{display:inline-block;padding:3px 10px;border-radius:99px;font-size:0.76rem;font-weight:600;background:var(--admin-soft);color:var(--admin-deep)}

/* Checklist */
.detail-checklist{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:4px}
.detail-check{display:flex;align-items:center;gap:7px;font-size:0.83rem;color:var(--text-mid)}

.detail-text-block{font-size:0.86rem;color:var(--text-mid);line-height:1.65;background:var(--cream);border-radius:var(--radius-sm);padding:12px 14px}
.detail-note-block{background:var(--amber-light);border:1.5px solid #FCD34D;border-radius:var(--radius-sm);padding:12px 14px;font-size:0.85rem;color:#92400E;margin-top:6px}

.detail-foot{padding:16px 26px;border-top:1px solid var(--cream-dark);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-shrink:0;flex-wrap:wrap}
.detail-foot-actions{display:flex;gap:8px}
.btn-detail-approve{padding:9px 18px;border-radius:99px;background:var(--admin-deep);border:none;color:var(--white);font-size:0.85rem;font-weight:700;cursor:pointer;font-family:var(--font-body)}
.btn-detail-approve:hover{background:var(--admin-mid)}
.btn-detail-reject{padding:9px 18px;border-radius:99px;background:var(--danger);border:none;color:var(--white);font-size:0.85rem;font-weight:700;cursor:pointer;font-family:var(--font-body)}
.btn-detail-close-foot{padding:9px 18px;border-radius:99px;border:1.5px solid var(--cream-dark);background:transparent;color:var(--text-mid);font-size:0.85rem;font-weight:600;cursor:pointer;font-family:var(--font-body)}

.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:199}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .content{padding:16px}.topbar{padding:0 16px}.search-wrap input{width:160px}
  .detail-grid{grid-template-columns:1fr}.detail-grid-3{grid-template-columns:1fr 1fr}
  .detail-checklist{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- ACTION MODAL -->
<div class="modal-overlay" id="actionModal">
  <div class="modal">
    <h3 id="modalTitle">Confirm Action</h3>
    <p id="modalDesc">Add a note for the client (optional).</p>
    <textarea id="modalNotes" placeholder="Optional note to client..."></textarea>
    <div class="modal-actions">
      <button class="btn-modal-cancel" onclick="closeActionModal()">Cancel</button>
      <button id="modalConfirmBtn" class="btn-modal-approve" onclick="submitAction()">Confirm</button>
    </div>
  </div>
</div>

<!-- VIEW DETAILS MODAL -->
<div class="detail-overlay" id="detailModal" onclick="handleDetailOverlay(event)">
  <div class="detail-box">

    <!-- Header -->
    <div class="detail-head">
      <div class="detail-head-left">
        <div class="detail-svc-icon" id="dm_icon">🏠</div>
        <div>
          <div class="detail-svc-name" id="dm_service">Service</div>
          <div class="detail-ref"      id="dm_ref">Booking #</div>
        </div>
      </div>
      <button class="detail-close" onclick="closeDetailModal()">✕</button>
    </div>

    <!-- Price strip -->
    <div class="detail-price-strip">
      <div class="detail-price-inner">
        <div>
          <div class="detail-price-label">Estimated Monthly Fee</div>
          <div style="font-size:0.72rem;color:rgba(255,255,255,0.5)">Based on selected services &amp; details</div>
        </div>
        <div class="detail-price-amount" id="dm_price">0 RWF</div>
      </div>
    </div>

    <!-- Client Strip -->
    <div class="client-strip">
      <div class="client-strip-av" id="dm_client_av">C</div>
      <div>
        <div class="client-strip-name"    id="dm_client_name">—</div>
        <div class="client-strip-contact" id="dm_client_contact">—</div>
      </div>
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
          <div class="detail-field"><span class="detail-field-label">🗺️ District</span><span class="detail-field-value" id="dm_district">—</span></div>
          <div class="detail-field"><span class="detail-field-label">🏠 Accommodation</span><span class="detail-field-value" id="dm_accomm">—</span></div>
          <div class="detail-field"><span class="detail-field-label">💰 Budget</span><span class="detail-field-value" id="dm_budget">—</span></div>
          <div class="detail-field"><span class="detail-field-label">👤 Assigned Team</span><span class="detail-field-value" id="dm_agent">—</span></div>
          <div class="detail-field"><span class="detail-field-label">📅 Submitted On</span><span class="detail-field-value" id="dm_submitted">—</span></div>
        </div>
      </div>

      <!-- Cleaning/Housekeeping specifics -->
      <div class="detail-section" id="dm_cleaning_section">
        <div class="detail-section-title">🚪 Property Details</div>
        <div class="detail-grid-3" style="margin-bottom:12px">
          <div class="detail-field"><span class="detail-field-label">Rooms</span><span class="detail-field-value" id="dm_rooms">—</span></div>
        </div>
        <div class="detail-checklist" id="dm_checklist"></div>
      </div>

      <!-- Chef specifics -->
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

      <!-- Requirements -->
      <div class="detail-section" id="dm_req_section">
        <div class="detail-section-title">📝 Special Requirements</div>
        <div class="detail-text-block" id="dm_requirements">—</div>
      </div>

      <!-- Team note -->
      <div class="detail-section" id="dm_note_section" style="display:none">
        <div class="detail-section-title">💬 Team Note to Client</div>
        <div class="detail-note-block" id="dm_note">—</div>
      </div>

    </div>

    <!-- Footer -->
    <div class="detail-foot">
      <button class="btn-detail-close-foot" onclick="closeDetailModal()">Close</button>
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

<div class="dash-wrap">
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="../Image/icon/homehelp_logo_transparent_white.svg" alt="Home Help" onerror="this.style.display='none'"/>
      <span class="sidebar-logo-text">Home Help</span>
      <span class="admin-badge">Admin</span>
    </div>
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($admin_name,0,1)) ?></div>
      <div class="user-info"><strong><?= e($admin_name) ?></strong><span>Super Admin</span></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="<?= BASE_URL ?>/admin"              class="nav-item"><span class="nav-icon">📊</span> Dashboard</a>
      <div class="nav-section-label">Manage</div>
      <a href="<?= BASE_URL ?>/admin/bookings"     class="nav-item active">
        <span class="nav-icon">📋</span> Bookings
        <?php if(($counts['pending']??0) > 0): ?><span class="nav-badge"><?= $counts['pending'] ?></span><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>/admin/applications" class="nav-item"><span class="nav-icon">💼</span> Job Applications</a>
      <a href="<?= BASE_URL ?>/admin/users"        class="nav-item"><span class="nav-icon">👥</span> Users</a>
      <a href="<?= BASE_URL ?>/admin/services"     class="nav-item"><span class="nav-icon">🛎️</span> Services</a>
      <a href="<?= BASE_URL ?>/admin/agents"       class="nav-item"><span class="nav-icon">🎯</span> Team</a>
      <div class="nav-section-label">Reports</div>
      <a href="<?= BASE_URL ?>/admin/reports"      class="nav-item"><span class="nav-icon">📈</span> Reports</a>
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
        <span class="topbar-title">Bookings</span>
      </div>
      <div class="topbar-right">
        <span class="topbar-chip">🛡️ Admin Panel</span>
      </div>
    </header>

    <div class="content">

      <?php if($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>">
          <?= $flash['type']==='success' ? '✅' : '⚠️' ?> <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="page-header">
        <h1>All <em>Bookings</em></h1>
      </div>

      <div class="filter-bar">
        <div class="filter-tabs">
          <?php
          $tabs = ['all'=>'All','pending'=>'Pending','under_review'=>'Under Review','approved'=>'Approved','completed'=>'Completed','rejected'=>'Rejected'];
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
          <span class="search-icon">🔍</span>
          <?php if($filter !== 'all'): ?><input type="hidden" name="status" value="<?= e($filter) ?>"/><?php endif; ?>
          <input type="text" name="search" placeholder="Search client, service..."
            value="<?= e($search) ?>" autocomplete="off"/>
        </form>
      </div>

      <div class="card">
        <div class="table-wrap">
          <table class="admin-table">
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
                <tr><td colspan="8"><div class="empty-state"><p>📋 No bookings found<?= $search ? " for \"$search\"":'' ?>.</p></div></td></tr>
              <?php else: ?>
                <?php foreach($bookings as $b):
                  $sc = $status_config[$b['status']] ?? ['Unknown','s-pending','❓'];

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
                    'service_icon'      => $b['service_icon'] ?: '🏠',
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
                    'calculated_price'  => number_format((float)($b['calculated_price'] ?? 0)),
                    'created_at'        => date('d M Y', strtotime($b['created_at'])),
                    'has_cleaning'      => $has_cleaning,
                    'has_chef'          => $has_chef,
                  ], JSON_HEX_QUOT | JSON_HEX_TAG);
                ?>
                  <tr>
                    <td style="font-size:0.75rem;color:var(--text-soft);font-weight:700">#<?= $b['id'] ?></td>
                    <td>
                      <div class="client-cell">
                        <div class="client-av"><?= strtoupper(substr($b['client_name'],0,1)) ?></div>
                        <div>
                          <div class="cell-name"><?= e($b['client_name']) ?></div>
                          <div class="cell-sub"><?= e($b['client_phone'] ?? '') ?></div>
                        </div>
                      </div>
                    </td>
                    <td><span class="service-chip"><?= $b['service_icon']?:'🏠' ?> <?= e(implode(' + ', $svc_names)) ?></span></td>
                    <td><div class="cell-name" style="max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($b['neighbourhood'] ?: $b['location'] ?: '—') ?></div></td>
                    <td class="date-txt"><?= date('d M Y', strtotime($b['created_at'])) ?></td>
                    <td>
                      <?php if($b['agent_name']): ?>
                        <span style="font-size:0.8rem;font-weight:600;color:var(--admin-deep)">👤 <?= e($b['agent_name']) ?></span>
                      <?php elseif(!empty($agents_list)): ?>
                        <div class="assign-wrap">
                          <select class="assign-select" id="agentSel<?= $b['id'] ?>">
                            <option value="">Assign...</option>
                            <?php foreach($agents_list as $ag): ?>
                              <option value="<?= $ag['id'] ?>"><?= e($ag['name']) ?></option>
                            <?php endforeach; ?>
                          </select>
                          <button class="btn-assign" onclick="assignAgent(<?= $b['id'] ?>)">Go</button>
                        </div>
                      <?php else: ?>
                        <span style="font-size:0.75rem;color:var(--text-soft)">No team members yet</span>
                      <?php endif; ?>
                    </td>
                    <td><span class="status-pill <?= $sc[1] ?>"><?= $sc[2] ?> <?= $sc[0] ?></span></td>
                    <td>
                      <div class="actions-cell">
                        <!-- 👁 VIEW DETAILS -->
                        <button class="act-btn act-view" onclick='openDetailModal(<?= $modal_data ?>)'>
                          👁 View
                        </button>
                        <?php if($b['status']==='pending' || $b['status']==='under_review'): ?>
                          <button class="act-btn act-approve" onclick="openActionModal('approve',<?= $b['id'] ?>,'Approve Booking #<?= $b['id'] ?>','Optional note for the client.')">✅</button>
                          <button class="act-btn act-reject"  onclick="openActionModal('reject', <?= $b['id'] ?>,'Reject Booking #<?= $b['id'] ?>','Please provide a reason.')">❌</button>
                        <?php endif; ?>
                        <?php if($b['status']==='pending'): ?>
                          <button class="act-btn act-review" onclick="quickAction('under_review',<?= $b['id'] ?>)">🔍</button>
                        <?php endif; ?>
                        <?php if($b['status']==='approved'): ?>
                          <button class="act-btn act-complete" onclick="quickAction('complete',<?= $b['id'] ?>)">🏆</button>
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

// ACTION MODAL
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
function assignAgent(id){
  var sel=document.getElementById('agentSel'+id);
  if(!sel.value){alert('Please select a team member first.');return;}
  document.getElementById('agentVal'+id).value=sel.value;
  document.getElementById('assignForm'+id).submit();
}
document.getElementById('actionModal').addEventListener('click',function(e){if(e.target===this)closeActionModal();});

// VIEW DETAILS MODAL
function openDetailModal(d){
  document.getElementById('dm_icon').textContent          = d.service_icon;
  document.getElementById('dm_service').textContent       = d.service_name;
  document.getElementById('dm_ref').textContent           = 'Booking #' + d.id + ' · ' + d.created_at;
  document.getElementById('dm_client_av').textContent     = d.client_name.charAt(0).toUpperCase();
  document.getElementById('dm_client_name').textContent   = d.client_name;
  document.getElementById('dm_client_contact').textContent= d.client_phone || d.client_email || '—';

  // Price
  document.getElementById('dm_price').textContent = d.calculated_price + ' RWF';

  // Status pill
  var pill = document.getElementById('dm_status_pill');
  pill.textContent = d.status_icon + ' ' + d.status_label;
  pill.className   = 'detail-status-pill ' + d.status_class;

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
      div.innerHTML = '✅ ' + item;
      cl.appendChild(div);
    });
    if (!d.checklist || !d.checklist.length) {
      var div = document.createElement('div');
      div.className = 'detail-check';
      div.innerHTML = '— No extras selected';
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
    ltags.innerHTML = '<span style="font-size:0.84rem;color:var(--text-soft);font-style:italic">No preference specified</span>';
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

  // Footer actions
  var acts = document.getElementById('dm_actions');
  acts.innerHTML = '';
  if(d.status==='pending'||d.status==='under_review'){
    acts.innerHTML =
      '<button class="btn-detail-approve" onclick="closeDetailModal();openActionModal(\'approve\',' + d.id + ',\'Approve Booking #' + d.id + '\',\'Optional note for the client.\')">✅ Approve</button>' +
      '<button class="btn-detail-reject"  onclick="closeDetailModal();openActionModal(\'reject\','  + d.id + ',\'Reject Booking #'  + d.id + '\',\'Please provide a reason.\')">❌ Reject</button>';
  }
  if(d.status==='approved'){
    acts.innerHTML = '<button class="btn-detail-approve" onclick="closeDetailModal();quickAction(\'complete\',' + d.id + ')">🏆 Mark Complete</button>';
  }
  document.getElementById('detailModal').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeDetailModal(){document.getElementById('detailModal').classList.remove('open');document.body.style.overflow='';}
function handleDetailOverlay(e){if(e.target===document.getElementById('detailModal'))closeDetailModal();}
</script>
</body>
</html>