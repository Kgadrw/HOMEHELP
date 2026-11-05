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

$stats['total_bookings']     = array_sum($booking_counts);
$stats['pending_bookings']   = $booking_counts['pending']   ?? 0;
$stats['approved_bookings']  = $booking_counts['approved']  ?? 0;
$stats['completed_bookings'] = $booking_counts['completed'] ?? 0;

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Dashboard | Home Help</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --admin-deep:#13103A;--admin-mid:#2D2A6E;--admin-light:#4F46E5;--admin-soft:#E0E7FF;
  --admin-sidebar:#0E0C2E;
  --amber:#E8A020;--amber-light:#FFF0CC;
  --cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --danger:#EF4444;--danger-bg:#FEF2F2;
  --success:#10B981;--success-bg:#ECFDF5;
  --warning:#F59E0B;--warning-bg:#FFFBEB;
  --info:#3B82F6;--info-bg:#EFF6FF;
  --shadow-sm:0 2px 12px rgba(19,16,58,0.08);
  --shadow-md:0 6px 28px rgba(19,16,58,0.14);
  --radius:14px;--radius-sm:8px;--sidebar:260px;
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Plus Jakarta Sans',sans-serif;
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
.user-info strong{display:block;font-size:0.86rem;font-weight:700;color:var(--white);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-info span{font-size:0.71rem;color:var(--amber);font-weight:600}
.sidebar-nav{padding:14px 0;flex:1}
.nav-section-label{font-size:0.64rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.25);padding:12px 20px 5px}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 20px;font-size:0.86rem;font-weight:500;color:rgba(255,255,255,0.6);cursor:pointer;transition:all 0.2s;position:relative;text-decoration:none}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,0.06)}
.nav-item.active{color:var(--white);background:rgba(79,70,229,0.18);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--amber);border-radius:0 3px 3px 0}
.nav-icon{font-size:1rem;width:20px;text-align:center;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--danger);color:var(--white);font-size:0.63rem;font-weight:700;padding:2px 6px;border-radius:99px}
.nav-badge-green{margin-left:auto;background:var(--success);color:var(--white);font-size:0.63rem;font-weight:700;padding:2px 6px;border-radius:99px}
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
.topbar-notif{position:relative;width:36px;height:36px;border-radius:var(--radius-sm);background:var(--cream);border:1.5px solid var(--cream-dark);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1rem;transition:all 0.2s;text-decoration:none}
.topbar-notif:hover{border-color:var(--admin-light);background:var(--admin-soft)}
.content{padding:24px 28px;flex:1}
.flash{padding:12px 16px;border-radius:var(--radius-sm);font-size:0.86rem;font-weight:500;margin-bottom:20px;display:flex;align-items:center;gap:10px}
.flash-success{background:var(--success-bg);border:1.5px solid #BBF7D0;color:#065F46}
.flash-error{background:var(--danger-bg);border:1.5px solid #FECACA;color:#B91C1C}
.welcome-banner{background:linear-gradient(135deg,var(--admin-sidebar) 0%,var(--admin-mid) 100%);border-radius:var(--radius);padding:24px 28px;display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;position:relative;overflow:hidden}
.welcome-banner::before{content:'';position:absolute;top:-40%;right:-8%;width:40%;height:200%;background:radial-gradient(ellipse,rgba(232,160,32,0.22) 0%,transparent 65%);border-radius:50%}
.welcome-banner::after{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px}
.welcome-text{position:relative;z-index:1}
.welcome-text h2{font-family:var(--font-display);font-size:1.7rem;font-weight:700;color:var(--white);margin-bottom:4px}
.welcome-text h2 em{font-style:italic;color:var(--amber)}
.welcome-text p{font-size:0.85rem;color:rgba(255,255,255,0.55)}
.welcome-right{position:relative;z-index:1;display:flex;gap:10px;flex-wrap:wrap}
.welcome-quick-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:99px;font-size:0.8rem;font-weight:700;cursor:pointer;transition:all 0.25s;text-decoration:none;border:none;font-family:var(--font-body)}
.btn-admin-amber{background:var(--amber);color:var(--white)}.btn-admin-amber:hover{background:#d4911a;transform:translateY(-1px)}
.btn-admin-ghost{background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.85);border:1px solid rgba(255,255,255,0.2)}.btn-admin-ghost:hover{background:rgba(255,255,255,0.18)}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
.stat-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:18px 20px;transition:all 0.3s}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);border-color:var(--admin-soft)}
.stat-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px}
.stat-icon{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.15rem}
.stat-icon.users{background:#EFF6FF}.stat-icon.bookings{background:var(--warning-bg)}.stat-icon.services{background:var(--success-bg)}.stat-icon.pending{background:var(--danger-bg)}.stat-icon.messages{background:#F0FDF4}
.stat-num{font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--admin-deep);line-height:1}
.stat-label{font-size:0.78rem;color:var(--text-soft);margin-top:3px;font-weight:500}
.stat-sub{font-size:0.72rem;margin-top:6px;font-weight:600}
.stat-sub.up{color:var(--success)}.stat-sub.warn{color:var(--warning)}.stat-sub.danger{color:var(--danger)}
.two-col{display:grid;grid-template-columns:1fr 340px;gap:18px;margin-bottom:22px}
.card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden}
.card-header{padding:16px 20px;border-bottom:1px solid var(--cream-dark);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.card-title{font-family:var(--font-display);font-size:1.05rem;font-weight:700;color:var(--admin-deep)}
.card-link{font-size:0.78rem;color:var(--admin-light);font-weight:600;text-decoration:none}
.card-link:hover{color:var(--amber)}
.table-wrap{overflow-x:auto}
.admin-table{width:100%;border-collapse:collapse;min-width:600px}
.admin-table th{font-size:0.7rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:var(--text-soft);padding:10px 18px;text-align:left;background:var(--cream);border-bottom:1px solid var(--cream-dark);white-space:nowrap}
.admin-table td{padding:12px 18px;font-size:0.84rem;border-bottom:1px solid var(--cream-dark);vertical-align:middle}
.admin-table tr:last-child td{border-bottom:none}
.admin-table tr:hover td{background:#fafafe}
.client-cell{display:flex;align-items:center;gap:9px}
.client-avatar{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--admin-light),var(--admin-deep));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:0.8rem;font-weight:700;color:var(--white);flex-shrink:0}
.client-name{font-weight:600;font-size:0.84rem;color:var(--text-dark)}
.service-chip{display:inline-flex;align-items:center;gap:5px;font-size:0.8rem;color:var(--text-mid)}
.status-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;font-size:0.7rem;font-weight:700;white-space:nowrap}
.s-pending{background:#FFFBEB;color:#92400E}.s-under_review{background:#EFF6FF;color:#1E40AF}.s-approved{background:#ECFDF5;color:#065F46}.s-completed{background:var(--admin-soft);color:var(--admin-deep)}.s-rejected{background:#FEF2F2;color:#991B1B}.s-cancelled{background:#F3F4F6;color:#6B7280}
.date-cell{font-size:0.78rem;color:var(--text-soft);white-space:nowrap}
.action-btn{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:var(--radius-sm);font-size:0.75rem;font-weight:600;cursor:pointer;border:1.5px solid var(--cream-dark);background:var(--white);color:var(--text-mid);transition:all 0.2s;text-decoration:none;font-family:var(--font-body)}
.action-btn:hover{border-color:var(--admin-light);color:var(--admin-deep);background:var(--admin-soft)}
.users-list{display:flex;flex-direction:column}
.user-row{display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid var(--cream-dark);transition:background 0.2s}
.user-row:last-child{border-bottom:none}
.user-row:hover{background:var(--cream)}
.user-av{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:0.9rem;font-weight:700;color:var(--white);flex-shrink:0}
.user-av.client{background:linear-gradient(135deg,#3B82F6,#1d4ed8)}.user-av.provider{background:linear-gradient(135deg,var(--amber),#c47d0a)}.user-av.agent{background:linear-gradient(135deg,#10B981,#065F46)}.user-av.admin{background:linear-gradient(135deg,var(--admin-light),var(--admin-deep))}
.user-row-info{flex:1;min-width:0}
.user-row-name{font-size:0.86rem;font-weight:700;color:var(--text-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-row-email{font-size:0.74rem;color:var(--text-soft);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.role-badge{padding:2px 8px;border-radius:99px;font-size:0.68rem;font-weight:700;flex-shrink:0}
.role-client{background:#EFF6FF;color:#1E40AF}.role-provider{background:var(--amber-light);color:#92400E}.role-agent{background:var(--success-bg);color:#065F46}.role-admin{background:var(--admin-soft);color:var(--admin-deep)}
.service-bars{padding:16px 20px;display:flex;flex-direction:column;gap:14px}
.service-bar-row{display:flex;align-items:center;gap:10px}
.service-bar-label{display:flex;align-items:center;gap:6px;width:120px;flex-shrink:0;font-size:0.83rem;font-weight:600;color:var(--text-dark)}
.service-bar-track{flex:1;background:var(--cream-dark);border-radius:99px;height:8px;overflow:hidden}
.service-bar-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--admin-light),var(--admin-mid));transition:width 0.6s ease}
.service-bar-count{font-size:0.78rem;font-weight:700;color:var(--text-mid);min-width:24px;text-align:right}
.empty-row td{text-align:center;padding:32px;color:var(--text-soft);font-size:0.86rem}

/* ── CONTACT MESSAGES ── */
.msg-list{display:flex;flex-direction:column}
.msg-row{
  display:flex;align-items:flex-start;gap:14px;
  padding:16px 20px;border-bottom:1px solid var(--cream-dark);
  transition:background 0.2s;position:relative;
}
.msg-row:last-child{border-bottom:none}
.msg-row:hover{background:#fafafe}
.msg-row.unread{background:#F5F3FF;border-left:3px solid var(--admin-light)}
.msg-row.unread:hover{background:#EDE9FE}
.msg-avatar{
  width:40px;height:40px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,var(--admin-light),var(--admin-deep));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--white);
}
.msg-body{flex:1;min-width:0}
.msg-sender{font-weight:700;font-size:0.88rem;color:var(--text-dark);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.msg-unread-dot{width:8px;height:8px;border-radius:50%;background:var(--admin-light);flex-shrink:0}
.msg-email{font-size:0.74rem;color:var(--text-soft);margin-top:1px}
.msg-topic{display:inline-block;padding:2px 8px;border-radius:99px;background:var(--admin-soft);color:var(--admin-deep);font-size:0.68rem;font-weight:700;margin-top:4px}
.msg-text{font-size:0.83rem;color:var(--text-mid);margin-top:6px;line-height:1.55;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.msg-meta{display:flex;align-items:center;gap:10px;margin-top:8px;flex-wrap:wrap}
.msg-date{font-size:0.72rem;color:var(--text-soft)}
.msg-actions-inline{display:flex;gap:6px}
.btn-msg{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:var(--radius-sm);font-size:0.73rem;font-weight:600;cursor:pointer;border:1.5px solid;transition:all 0.2s;background:var(--white);font-family:var(--font-body)}
.btn-read{border-color:#BBF7D0;color:#065F46}.btn-read:hover{background:var(--success-bg)}
.btn-del{border-color:#FECACA;color:#B91C1C}.btn-del:hover{background:var(--danger-bg)}
.btn-expand{border-color:var(--cream-dark);color:var(--text-mid)}.btn-expand:hover{border-color:var(--admin-light);color:var(--admin-deep)}
.msg-full{display:none;margin-top:8px;font-size:0.85rem;color:var(--text-mid);line-height:1.65;background:var(--cream);border-radius:var(--radius-sm);padding:12px 14px}
.msg-full.open{display:block}
.no-messages{text-align:center;padding:40px 20px;color:var(--text-soft)}
.no-messages-icon{font-size:2.5rem;margin-bottom:10px}
.no-messages p{font-size:0.88rem}
.msg-unread-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;background:#EDE9FE;color:var(--admin-deep);font-size:0.72rem;font-weight:700}

/* ── MOBILE ── */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:199}
@media(max-width:1200px){.stats-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:1000px){.two-col{grid-template-columns:1fr}}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .content{padding:16px}.topbar{padding:0 16px}.welcome-right{display:none}
}
</style>
</head>
<body>

<!-- Hidden forms for message actions -->
<form method="POST" id="msgActionForm" style="display:none">
  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
  <input type="hidden" name="msg_action" id="msgActionInput" value=""/>
  <input type="hidden" name="msg_id"     id="msgIdInput"     value=""/>
</form>

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
      <div class="user-info">
        <strong><?= e($admin_name) ?></strong>
        <span>Super Admin</span>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="<?= BASE_URL ?>/admin" class="nav-item active"><span class="nav-icon">📊</span> Dashboard</a>
      <div class="nav-section-label">Manage</div>
      <a href="<?= BASE_URL ?>/admin/bookings" class="nav-item">
        <span class="nav-icon">📋</span> Bookings
        <?php if($stats['pending_bookings'] > 0): ?><span class="nav-badge"><?= $stats['pending_bookings'] ?></span><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>/admin/applications" class="nav-item">
        <span class="nav-icon">💼</span> Job Applications
        <?php if($stats['pending_applications'] > 0): ?><span class="nav-badge"><?= $stats['pending_applications'] ?></span><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>/admin/users"    class="nav-item"><span class="nav-icon">👥</span> Users</a>
      <a href="<?= BASE_URL ?>/admin/services" class="nav-item"><span class="nav-icon">🛎️</span> Services</a>
      <a href="<?= BASE_URL ?>/admin/agents"   class="nav-item"><span class="nav-icon">🎯</span> Team</a>
      <div class="nav-section-label">Reports</div>
      <a href="<?= BASE_URL ?>/admin/reports"  class="nav-item"><span class="nav-icon">📈</span> Reports</a>
      <div class="nav-section-label">Inbox</div>
      <a href="<?= BASE_URL ?>/admin" class="nav-item active">
        <span class="nav-icon">✉️</span> Messages
        <?php if($stats['unread_messages'] > 0): ?>
          <span class="nav-badge-green"><?= $stats['unread_messages'] ?></span>
        <?php endif; ?>
      </a>
      <div class="nav-section-label">View As</div>
      <a href="<?= BASE_URL ?>"                class="nav-item"><span class="nav-icon">🌐</span> View Website</a>
    </nav>
    <div class="sidebar-footer">
      <a href="<?= BASE_URL ?>/logout" class="btn-logout"><span>🚪</span> Sign Out</a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="burger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <span class="topbar-title">Admin Dashboard</span>
      </div>
      <div class="topbar-right">
        <span class="topbar-chip">🛡️ Admin Panel</span>
        <a href="<?= BASE_URL ?>" class="topbar-notif" title="View Website">🌐</a>
      </div>
    </header>

    <div class="content">

      <?php if($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>">
          <?= $flash['type']==='success' ? '✅' : '⚠️' ?> <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- WELCOME BANNER -->
      <div class="welcome-banner">
        <div class="welcome-text">
          <h2>Welcome, <em><?= e(explode(' ',$admin_name)[0]) ?></em> 👋</h2>
          <p>Here's your platform overview for today — <?= date('l, d F Y') ?></p>
        </div>
        <div class="welcome-right">
          <a href="<?= BASE_URL ?>/admin/bookings"     class="welcome-quick-btn btn-admin-amber">📋 View Bookings</a>
          <a href="<?= BASE_URL ?>/admin/applications" class="welcome-quick-btn btn-admin-ghost">💼 Job Applications</a>
        </div>
      </div>

      <!-- STATS ROW 1 -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon users">👥</div></div>
          <div class="stat-num"><?= $stats['total_users'] ?></div>
          <div class="stat-label">Total Users</div>
          <div class="stat-sub up">+<?= $stats['new_users_month'] ?> this month</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon bookings">📋</div></div>
          <div class="stat-num"><?= $stats['total_bookings'] ?></div>
          <div class="stat-label">Total Bookings</div>
          <div class="stat-sub up">+<?= $stats['new_bookings_month'] ?> this month</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon services">🛎️</div></div>
          <div class="stat-num"><?= $stats['active_services'] ?></div>
          <div class="stat-label">Active Services</div>
          <div class="stat-sub up"><?= $stats['total_agents'] ?> team members assigned</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon pending">⏳</div></div>
          <div class="stat-num"><?= $stats['pending_bookings'] ?></div>
          <div class="stat-label">Pending Bookings</div>
          <div class="stat-sub <?= $stats['pending_bookings'] > 0 ? 'warn':'up' ?>">
            <?= $stats['pending_bookings'] > 0 ? 'Needs review':'All clear!' ?>
          </div>
        </div>
      </div>

      <!-- STATS ROW 2 -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon users" style="background:var(--admin-soft)">🏠</div></div>
          <div class="stat-num"><?= $stats['total_clients'] ?></div>
          <div class="stat-label">Clients</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon users" style="background:var(--amber-light)">💼</div></div>
          <div class="stat-num"><?= $stats['total_providers'] ?></div>
          <div class="stat-label">Job Seekers</div>
          <div class="stat-sub <?= $stats['pending_applications'] > 0 ? 'warn':'up' ?>">
            <?= $stats['pending_applications'] > 0 ? $stats['pending_applications'].' pending apps':'No pending' ?>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon services">✅</div></div>
          <div class="stat-num"><?= $stats['approved_bookings'] ?></div>
          <div class="stat-label">Approved Bookings</div>
        </div>
        <!-- NEW: Unread Messages Stat -->
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon messages">✉️</div></div>
          <div class="stat-num" style="color:<?= $stats['unread_messages'] > 0 ? 'var(--admin-light)' : 'var(--admin-deep)' ?>"><?= $stats['unread_messages'] ?></div>
          <div class="stat-label">Unread Messages</div>
          <div class="stat-sub <?= $stats['unread_messages'] > 0 ? 'warn':'up' ?>">
            <?= $stats['unread_messages'] > 0 ? 'New messages!' : 'All caught up 🎉' ?>
          </div>
        </div>
      </div>

      <!-- RECENT BOOKINGS + RECENT USERS -->
      <div class="two-col">
        <div class="card">
          <div class="card-header">
            <span class="card-title">Recent Bookings</span>
            <a href="<?= BASE_URL ?>/admin/bookings" class="card-link">View all →</a>
          </div>
          <div class="table-wrap">
            <table class="admin-table">
              <thead>
                <tr><th>Client</th><th>Service</th><th>Date</th><th>Status</th><th>Action</th></tr>
              </thead>
              <tbody>
                <?php if(empty($recent_bookings)): ?>
                  <tr class="empty-row"><td colspan="5">No bookings yet</td></tr>
                <?php else: ?>
                  <?php foreach($recent_bookings as $b):
                    $sc_map = ['pending'=>'s-pending','under_review'=>'s-under_review','approved'=>'s-approved','completed'=>'s-completed','rejected'=>'s-rejected','cancelled'=>'s-cancelled'];
                    $sc = $sc_map[$b['status']] ?? 's-pending';
                    $label = ucfirst(str_replace('_',' ',$b['status']));
                  ?>
                    <tr>
                      <td><div class="client-cell"><div class="client-avatar"><?= strtoupper(substr($b['client_name'],0,1)) ?></div><span class="client-name"><?= e(explode(' ',$b['client_name'])[0]) ?></span></div></td>
                      <td><span class="service-chip"><?= $b['service_icon']?:'🏠' ?> <?= e($b['service_name']) ?></span></td>
                      <td class="date-cell"><?= date('d M', strtotime($b['start_date'])) ?></td>
                      <td><span class="status-pill <?= $sc ?>"><?= $label ?></span></td>
                      <td><a href="<?= BASE_URL ?>/admin/bookings" class="action-btn">View</a></td>
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
            <a href="<?= BASE_URL ?>/admin/users" class="card-link">View all →</a>
          </div>
          <div class="users-list">
            <?php if(empty($recent_users)): ?>
              <div style="padding:24px;text-align:center;color:var(--text-soft);font-size:0.85rem">No users yet</div>
            <?php else: ?>
              <?php foreach($recent_users as $u): ?>
                <div class="user-row">
                  <div class="user-av <?= $u['role'] ?>"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                  <div class="user-row-info">
                    <div class="user-row-name"><?= e($u['name']) ?></div>
                    <div class="user-row-email"><?= e($u['email']) ?></div>
                  </div>
                  <span class="role-badge role-<?= $u['role'] ?>"><?= $u['role']==='agent' ? 'Team' : ucfirst($u['role']) ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- BOOKINGS BY SERVICE -->
      <div class="card" style="margin-bottom:22px">
        <div class="card-header">
          <span class="card-title">Bookings by Service</span>
          <a href="<?= BASE_URL ?>/admin/reports" class="card-link">Full report →</a>
        </div>
        <div class="service-bars">
          <?php foreach($service_stats as $ss):
            $pct = $max_service_cnt > 0 ? round(($ss['cnt']/$max_service_cnt)*100) : 0;
          ?>
            <div class="service-bar-row">
              <div class="service-bar-label"><?= $ss['icon']?:'🏠' ?> <?= e($ss['name']) ?></div>
              <div class="service-bar-track"><div class="service-bar-fill" style="width:<?= $pct ?>%"></div></div>
              <div class="service-bar-count"><?= $ss['cnt'] ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════
           CONTACT MESSAGES INBOX
      ════════════════════════════════════════════════ -->
      <div class="card" id="messages-section">
        <div class="card-header">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span class="card-title">✉️ Contact Messages</span>
            <?php if($stats['unread_messages'] > 0): ?>
              <span class="msg-unread-badge">
                <?= $stats['unread_messages'] ?> unread
              </span>
            <?php endif; ?>
          </div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <?php if($stats['unread_messages'] > 0): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
                <input type="hidden" name="msg_action" value="read_all"/>
                <input type="hidden" name="msg_id"     value="0"/>
                <button type="submit" style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:var(--radius-sm);font-size:0.76rem;font-weight:600;cursor:pointer;border:1.5px solid #BBF7D0;color:#065F46;background:var(--white);font-family:var(--font-body);transition:all 0.2s"
                  onmouseover="this.style.background='var(--success-bg)'" onmouseout="this.style.background='var(--white)'">
                  ✅ Mark All Read
                </button>
              </form>
            <?php endif; ?>
            <span style="font-size:0.77rem;color:var(--text-soft)"><?= count($contact_messages) ?> message<?= count($contact_messages)!=1?'s':'' ?></span>
          </div>
        </div>

        <?php if(empty($contact_messages)): ?>
          <div class="no-messages">
            <div class="no-messages-icon">📭</div>
            <p>No messages yet. When visitors send a message from the homepage, it will appear here.</p>
          </div>
        <?php else: ?>
          <div class="msg-list">
            <?php foreach($contact_messages as $msg):
              $is_unread = !$msg['is_read'];
              $initials  = strtoupper(substr($msg['first_name'],0,1) . substr($msg['last_name'],0,1));
              $full_name = e($msg['first_name'] . ' ' . $msg['last_name']);
              $short_msg = strlen($msg['message']) > 120 ? substr($msg['message'],0,120).'...' : $msg['message'];
              $need_expand = strlen($msg['message']) > 120;
            ?>
              <div class="msg-row <?= $is_unread ? 'unread' : '' ?>" id="msgRow<?= $msg['id'] ?>">
                <div class="msg-avatar"><?= $initials ?></div>
                <div class="msg-body">
                  <div class="msg-sender">
                    <?= $full_name ?>
                    <?php if($is_unread): ?><span class="msg-unread-dot" title="Unread"></span><?php endif; ?>
                  </div>
                  <div class="msg-email">✉️ <?= e($msg['email']) ?></div>
                  <?php if($msg['topic']): ?>
                    <span class="msg-topic"><?= e($msg['topic']) ?></span>
                  <?php endif; ?>
                  <div class="msg-text" id="msgShort<?= $msg['id'] ?>"><?= e($short_msg) ?></div>
                  <div class="msg-full" id="msgFull<?= $msg['id'] ?>"><?= nl2br(e($msg['message'])) ?></div>
                  <div class="msg-meta">
                    <span class="msg-date">🕐 <?= date('d M Y, H:i', strtotime($msg['created_at'])) ?></span>
                    <div class="msg-actions-inline">
                      <?php if($need_expand): ?>
                        <button class="btn-msg btn-expand" onclick="toggleMsg(<?= $msg['id'] ?>)" id="expandBtn<?= $msg['id'] ?>">
                          👁 Read more
                        </button>
                      <?php endif; ?>
                      <?php if($is_unread): ?>
                        <button class="btn-msg btn-read" onclick="msgAction('read',<?= $msg['id'] ?>)">✅ Mark Read</button>
                      <?php endif; ?>
                      <button class="btn-msg btn-del" onclick="if(confirm('Delete this message?'))msgAction('delete',<?= $msg['id'] ?>)">🗑️ Delete</button>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>
    <?php include_once '../includes/footer.php'; ?>
  </div>
</div>

<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');document.body.style.overflow=document.getElementById('sidebar').classList.contains('open')?'hidden':'';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
window.addEventListener('resize',function(){if(window.innerWidth>768)closeSidebar();});

function msgAction(action, id){
  document.getElementById('msgActionInput').value = action;
  document.getElementById('msgIdInput').value     = id;
  document.getElementById('msgActionForm').submit();
}

function toggleMsg(id){
  var full  = document.getElementById('msgFull'+id);
  var short = document.getElementById('msgShort'+id);
  var btn   = document.getElementById('expandBtn'+id);
  var open  = full.classList.contains('open');
  full.classList.toggle('open', !open);
  short.style.display = open ? '' : 'none';
  btn.textContent = open ? '👁 Read more' : '▲ Collapse';
}
</script>
</body>
</html>