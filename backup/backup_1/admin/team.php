<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('admin');

$admin_name = $_SESSION['user_name'];

// ── HANDLE ACTIONS ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();

    $action = $_POST['action'] ?? '';

    // ── CREATE TEAM MEMBER ──────────────────────────────────────
    if ($action === 'create_agent') {
        $name     = sanitize($_POST['name']     ?? '');
        $email    = sanitize($_POST['email']    ?? '');
        $phone    = sanitize($_POST['phone']    ?? '');
        $password = $_POST['password'] ?? '';
        $zone     = sanitize($_POST['zone']     ?? '');
        $services = $_POST['services'] ?? [];

        if (!$name || !$email || !$phone || !$password) {
            flashSet('error', 'Please fill in all required fields.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flashSet('error', 'Please enter a valid email address.');
        } elseif (strlen($password) < 6) {
            flashSet('error', 'Password must be at least 6 characters.');
        } else {
            // Check duplicate email
            $chk = $conn->prepare("SELECT id FROM users WHERE email=?");
            $chk->bind_param('s', $email); $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                flashSet('error', "Email \"$email\" is already registered.");
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);

                // Create user with role=agent
                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?,?,?,?,'agent','active')");
                $stmt->bind_param('ssss', $name, $email, $phone, $hash);
                $stmt->execute();
                $user_id = $conn->insert_id;

                // Create team member profile
                $svc_ids = implode(',', array_map('intval', $services));
                $stmt2 = $conn->prepare("INSERT INTO agents (user_id, assigned_service_ids, whatsapp_number, is_active) VALUES (?,?,?,1)");
                $stmt2->bind_param('iss', $user_id, $svc_ids, $phone);
                $stmt2->execute();

                flashSet('success', "Team member \"$name\" created successfully.");
            }
        }

    // ── UPDATE TEAM MEMBER ──────────────────────────────────────
    } elseif ($action === 'update_agent') {
        $agent_user_id = (int)($_POST['agent_user_id'] ?? 0);
        $name          = sanitize($_POST['name']       ?? '');
        $phone         = sanitize($_POST['phone']      ?? '');
        $services      = $_POST['services'] ?? [];
        $is_active     = isset($_POST['is_active']) ? 1 : 0;

        if ($agent_user_id) {
            $svc_ids = implode(',', array_map('intval', $services));

            // Update name + phone in users table
            $s1 = $conn->prepare("UPDATE users SET name=?, phone=? WHERE id=?");
            $s1->bind_param('ssi', $name, $phone, $agent_user_id); $s1->execute();

            // Update team member profile
            $s2 = $conn->prepare("UPDATE agents SET assigned_service_ids=?, whatsapp_number=?, is_active=? WHERE user_id=?");
            $s2->bind_param('ssii', $svc_ids, $phone, $is_active, $agent_user_id); $s2->execute();

            flashSet('success', 'Team member updated successfully.');
        }

    // ── TOGGLE ACTIVE ─────────────────────────────────────────
    } elseif ($action === 'toggle_agent') {
        $agent_user_id = (int)($_POST['agent_user_id'] ?? 0);
        if ($agent_user_id) {
            $cur = $conn->prepare("SELECT is_active FROM agents WHERE user_id=?");
            $cur->bind_param('i', $agent_user_id); $cur->execute();
            $row = $cur->get_result()->fetch_assoc();
            $new = ($row['is_active'] ?? 1) ? 0 : 1;
            $stmt = $conn->prepare("UPDATE agents SET is_active=? WHERE user_id=?");
            $stmt->bind_param('ii', $new, $agent_user_id); $stmt->execute();
            // Also update user status
            $us = $new ? 'active' : 'blocked';
            $stmt2 = $conn->prepare("UPDATE users SET status=? WHERE id=?");
            $stmt2->bind_param('si', $us, $agent_user_id); $stmt2->execute();
            flashSet('success', 'Team member '.($new ? 'activated' : 'deactivated').' successfully.');
        }

    // ── DELETE TEAM MEMBER ──────────────────────────────────────
    } elseif ($action === 'delete_agent') {
        $agent_user_id = (int)($_POST['agent_user_id'] ?? 0);
        if ($agent_user_id) {
            // Check if team member has assigned bookings
            $chk = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id=? AND status NOT IN ('completed','rejected','cancelled')");
            $chk->bind_param('i', $agent_user_id); $chk->execute();
            $active_bookings = $chk->get_result()->fetch_row()[0];
            if ($active_bookings > 0) {
                flashSet('error', "Cannot delete — team member has $active_bookings active booking(s). Deactivate them first.");
            } else {
                $da = $conn->prepare("DELETE FROM agents WHERE user_id=?");
                $da->bind_param('i', $agent_user_id); $da->execute();
                $du = $conn->prepare("DELETE FROM users WHERE id=? AND role='agent'");
                $du->bind_param('i', $agent_user_id); $du->execute();
                flashSet('success', 'Team member deleted successfully.');
            }
        }
    }

    redirect(BASE_URL . '/admin/agents');
}

// ── FETCH TEAM MEMBERS ──────────────────────────────────────────
$agents = $conn->query("
    SELECT u.id AS user_id, u.name, u.email, u.phone, u.status, u.created_at,
           a.id AS agent_id, a.assigned_service_ids, a.whatsapp_number, a.is_active,
           (SELECT COUNT(*) FROM bookings b WHERE b.agent_id = u.id) AS total_bookings,
           (SELECT COUNT(*) FROM bookings b WHERE b.agent_id = u.id AND b.status='pending') AS pending_bookings,
           (SELECT COUNT(*) FROM bookings b WHERE b.agent_id = u.id AND b.status='completed') AS completed_bookings
    FROM users u
    LEFT JOIN agents a ON a.user_id = u.id
    WHERE u.role = 'agent'
    ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// ── FETCH SERVICES (for checkboxes) ──────────────────────────
$services = $conn->query("SELECT * FROM services WHERE status='active' ORDER BY sort_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);

$flash = flashGet();
$notif_count = unreadNotifCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Team | Admin — Home Help</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --admin-deep:#13103A;--admin-mid:#2D2A6E;--admin-light:#4F46E5;--admin-soft:#E0E7FF;
  --amber:#E8A020;--amber-light:#FFF0CC;
  --cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --danger:#EF4444;--danger-bg:#FEF2F2;
  --success:#10B981;--success-bg:#ECFDF5;
  --warning:#F59E0B;--warning-bg:#FFFBEB;
  --shadow-sm:0 2px 12px rgba(19,16,58,0.07);
  --shadow-md:0 6px 28px rgba(19,16,58,0.11);
  --radius:14px;--radius-sm:8px;
  --admin-sidebar:#0E0C2E;
  --sidebar:260px;
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden}
a{text-decoration:none;color:inherit}
.dash-wrap{display:flex;min-height:100vh}

/* SIDEBAR */
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
.sidebar-footer{padding:14px 20px;border-top:1px solid rgba(255,255,255,0.07)}
.btn-logout{display:flex;align-items:center;gap:8px;width:100%;padding:9px 14px;border-radius:var(--radius-sm);background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.18);color:rgba(255,100,100,0.85);font-size:0.83rem;font-weight:600;cursor:pointer;transition:all 0.2s;text-decoration:none;font-family:var(--font-body)}
.btn-logout:hover{background:rgba(239,68,68,0.2)}

/* MAIN */
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--white);border-bottom:1px solid var(--cream-dark);padding:0 28px;height:62px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar-left{display:flex;align-items:center;gap:14px}
.burger{display:none;background:none;border:none;cursor:pointer;padding:6px;flex-direction:column;gap:4px}
.burger span{display:block;width:20px;height:2px;background:var(--text-dark);border-radius:2px}
.topbar-title{font-family:var(--font-display);font-size:1.35rem;font-weight:700;color:var(--admin-deep)}
.topbar-right{display:flex;align-items:center;gap:10px}
.topbar-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:99px;background:var(--amber-light);color:#92400E;font-size:0.78rem;font-weight:700}
.btn-add{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:99px;background:var(--admin-deep);color:var(--white);font-size:0.83rem;font-weight:700;border:none;cursor:pointer;transition:all 0.25s;font-family:var(--font-body)}
.btn-add:hover{background:var(--admin-mid);transform:translateY(-1px)}

/* CONTENT */
.content{padding:24px 28px;flex:1}
.flash{padding:12px 16px;border-radius:var(--radius-sm);font-size:0.86rem;font-weight:500;margin-bottom:18px;display:flex;align-items:center;gap:10px}
.flash-success{background:var(--success-bg);border:1.5px solid #BBF7D0;color:#065F46}
.flash-error{background:var(--danger-bg);border:1.5px solid #FECACA;color:#B91C1C}

/* PAGE HEADER */
.page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:24px}
.page-header h1{font-family:var(--font-display);font-size:1.9rem;font-weight:700;color:var(--admin-deep)}
.page-header h1 em{font-style:italic;color:var(--admin-light)}

/* TWO COL */
.two-col{display:grid;grid-template-columns:1fr 380px;gap:20px}

/* TEAM CARDS */
.agents-list{display:flex;flex-direction:column;gap:14px}
.agent-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden;transition:all 0.25s}
.agent-card:hover{box-shadow:var(--shadow-sm);border-color:var(--admin-soft)}
.agent-card.inactive{opacity:0.6}
.agent-card-top{padding:18px 20px;display:flex;align-items:center;gap:14px}
.agent-av{
  width:50px;height:50px;border-radius:50%;
  background:linear-gradient(135deg,#3B82F6,#1d4ed8);
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-display);font-size:1.2rem;font-weight:700;
  color:var(--white);flex-shrink:0;
}
.agent-info{flex:1;min-width:0}
.agent-name{font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--admin-deep)}
.agent-email{font-size:0.78rem;color:var(--text-soft);margin-top:1px}
.agent-phone{font-size:0.8rem;color:var(--text-mid);margin-top:2px}
.agent-status{display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0}
.status-pill{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:99px;font-size:0.72rem;font-weight:700}
.sp-active{background:var(--success-bg);color:#065F46}
.sp-inactive{background:var(--danger-bg);color:#991B1B}

.agent-card-stats{
  display:grid;grid-template-columns:repeat(3,1fr);
  border-top:1px solid var(--cream-dark);border-bottom:1px solid var(--cream-dark);
}
.agent-stat{padding:10px 16px;text-align:center}
.agent-stat-num{font-family:var(--font-display);font-size:1.3rem;font-weight:700;color:var(--admin-deep)}
.agent-stat-label{font-size:0.7rem;color:var(--text-soft);margin-top:2px;font-weight:500}

.agent-card-services{padding:12px 20px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;border-bottom:1px solid var(--cream-dark)}
.svc-tag{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;background:var(--admin-soft);color:var(--admin-deep);font-size:0.74rem;font-weight:600}
.no-services{font-size:0.78rem;color:var(--text-soft);font-style:italic}

.agent-card-actions{padding:12px 20px;display:flex;gap:8px;flex-wrap:wrap}
.act-btn{display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border-radius:var(--radius-sm);font-size:0.76rem;font-weight:600;cursor:pointer;border:1.5px solid;transition:all 0.2s;background:var(--white);font-family:var(--font-body)}
.act-edit{border-color:var(--cream-dark);color:var(--text-mid)}.act-edit:hover{border-color:var(--admin-light);color:var(--admin-deep);background:var(--admin-soft)}
.act-deactivate{border-color:#FECACA;color:#B91C1C}.act-deactivate:hover{background:var(--danger-bg)}
.act-activate{border-color:#BBF7D0;color:#065F46}.act-activate:hover{background:var(--success-bg)}
.act-delete{border-color:#FECACA;color:#991B1B}.act-delete:hover{background:var(--danger-bg)}

/* EMPTY STATE */
.empty-state{text-align:center;padding:56px 20px;background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius)}
.empty-icon{font-size:2.5rem;margin-bottom:12px}
.empty-state h3{font-family:var(--font-display);font-size:1.2rem;color:var(--admin-deep);margin-bottom:6px}
.empty-state p{font-size:0.86rem;color:var(--text-soft)}

/* FORM CARD */
.form-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden;position:sticky;top:82px}
.form-card-header{padding:16px 20px;border-bottom:1px solid var(--cream-dark);background:linear-gradient(135deg,var(--admin-sidebar),#1a1860)}
.form-card-title{font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--white)}
.form-card-body{padding:20px}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.form-group label{font-size:0.8rem;font-weight:600;color:var(--text-dark)}
.form-group label span.req{color:var(--danger);margin-left:2px}
.form-group label span.opt{color:var(--text-soft);font-weight:400;font-size:0.73rem;margin-left:4px}
.form-group input{width:100%;padding:10px 14px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.86rem;color:var(--text-dark);background:var(--cream);transition:all 0.2s;outline:none}
.form-group input:focus{border-color:var(--admin-light);background:var(--white);box-shadow:0 0 0 3px rgba(79,70,229,0.1)}
.hint-text{font-size:0.73rem;color:var(--text-soft);margin-top:3px}

/* SERVICE CHECKBOXES */
.services-check-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.svc-check-item{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);cursor:pointer;transition:all 0.2s}
.svc-check-item:hover{border-color:var(--admin-light);background:var(--admin-soft)}
.svc-check-item input[type="checkbox"]{accent-color:var(--admin-deep);width:15px;height:15px;flex-shrink:0}
.svc-check-item.checked{border-color:var(--admin-deep);background:var(--admin-soft)}
.svc-check-label{font-size:0.82rem;font-weight:600;color:var(--text-dark);display:flex;align-items:center;gap:4px}

/* TOGGLE SWITCH */
.toggle-wrap{display:flex;align-items:center;gap:10px}
.toggle-label{font-size:0.84rem;font-weight:600;color:var(--text-dark)}
.toggle{position:relative;width:40px;height:22px}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:var(--cream-dark);border-radius:99px;transition:0.3s;cursor:pointer}
.toggle-slider::before{content:'';position:absolute;width:16px;height:16px;left:3px;top:3px;background:var(--white);border-radius:50%;transition:0.3s}
.toggle input:checked + .toggle-slider{background:var(--admin-deep)}
.toggle input:checked + .toggle-slider::before{transform:translateX(18px)}

/* SUBMIT */
.form-actions{display:flex;gap:8px;margin-top:18px}
.btn-submit{flex:1;padding:11px;border-radius:var(--radius-sm);background:var(--admin-deep);color:var(--white);font-family:var(--font-body);font-size:0.88rem;font-weight:700;border:none;cursor:pointer;transition:all 0.25s}
.btn-submit:hover{background:var(--admin-mid)}
.btn-reset{padding:11px 16px;border-radius:var(--radius-sm);border:1.5px solid var(--cream-dark);background:transparent;color:var(--text-mid);font-family:var(--font-body);font-size:0.86rem;font-weight:600;cursor:pointer}
.btn-reset:hover{border-color:var(--admin-light);color:var(--admin-deep)}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:500;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:var(--white);border-radius:var(--radius);padding:28px;max-width:400px;width:100%;box-shadow:var(--shadow-md)}
.modal h3{font-family:var(--font-display);font-size:1.3rem;color:var(--admin-deep);margin-bottom:8px}
.modal p{font-size:0.86rem;color:var(--text-mid);line-height:1.65;margin-bottom:22px}
.modal-actions{display:flex;gap:10px;justify-content:flex-end}
.btn-modal-cancel{padding:9px 18px;border-radius:var(--radius-sm);border:1.5px solid var(--cream-dark);background:transparent;color:var(--text-mid);font-size:0.86rem;font-weight:600;cursor:pointer;font-family:var(--font-body)}
.btn-modal-danger{padding:9px 18px;border-radius:var(--radius-sm);background:var(--danger);border:none;color:var(--white);font-size:0.86rem;font-weight:700;cursor:pointer;font-family:var(--font-body)}

/* MOBILE */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:199}
@media(max-width:1000px){.two-col{grid-template-columns:1fr}.form-card{position:static}}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .content{padding:16px}.topbar{padding:0 16px}
  .agent-card-stats{grid-template-columns:repeat(3,1fr)}
  .services-check-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <h3>Delete Team Member?</h3>
    <p id="deleteModalMsg">This will permanently delete this team member's account.</p>
    <div class="modal-actions">
      <button class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
      <button class="btn-modal-danger" onclick="confirmDelete()">🗑️ Delete</button>
    </div>
  </div>
</div>

<!-- Toggle & Delete forms -->
<form method="POST" id="toggleForm" style="display:none">
  <input type="hidden" name="csrf_token"      value="<?= generateCSRFToken() ?>"/>
  <input type="hidden" name="action"          value="toggle_agent"/>
  <input type="hidden" name="agent_user_id"   id="toggleUserId" value=""/>
</form>
<form method="POST" id="deleteForm" style="display:none">
  <input type="hidden" name="csrf_token"      value="<?= generateCSRFToken() ?>"/>
  <input type="hidden" name="action"          value="delete_agent"/>
  <input type="hidden" name="agent_user_id"   id="deleteUserId" value=""/>
</form>

<div class="dash-wrap">
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <!-- SIDEBAR -->
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
      <a href="<?= BASE_URL ?>/admin"          class="nav-item"><span class="nav-icon">📊</span> Dashboard</a>
      <div class="nav-section-label">Manage</div>
      <a href="<?= BASE_URL ?>/admin/bookings" class="nav-item"><span class="nav-icon">📋</span> Bookings</a>
      <a href="<?= BASE_URL ?>/admin/users"    class="nav-item"><span class="nav-icon">👥</span> Users</a>
      <a href="<?= BASE_URL ?>/admin/services" class="nav-item"><span class="nav-icon">🛎️</span> Services</a>
      <a href="<?= BASE_URL ?>/admin/agents"   class="nav-item active"><span class="nav-icon">🎯</span> Team</a>
      <div class="nav-section-label">Reports</div>
      <a href="<?= BASE_URL ?>/admin/reports" class="nav-item"><span class="nav-icon">📈</span> Reports</a>
      <div class="nav-section-label">View As</div>
      <a href="<?= BASE_URL ?>/dashboard"      class="nav-item"><span class="nav-icon">🏠</span> Client View</a>
    </nav>
    <div class="sidebar-footer">
      <a href="<?= BASE_URL ?>/logout" class="btn-logout"><span>🚪</span> Sign Out</a>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="burger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <span class="topbar-title">Team</span>
      </div>
      <div class="topbar-right">
        <span class="topbar-chip">🛡️ Admin Panel</span>
        <button class="btn-add" onclick="resetForm()">＋ Add Team Member</button>
      </div>
    </header>

    <div class="content">

      <?php if($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>">
          <?= $flash['type']==='success' ? '✅' : '⚠️' ?> <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="page-header">
        <h1>Manage <em>Team</em></h1>
      </div>

      <div class="two-col">

        <!-- LEFT: TEAM LIST -->
        <div>
          <?php if(empty($agents)): ?>
            <div class="empty-state">
              <div class="empty-icon">🎯</div>
              <h3>No team members yet</h3>
              <p>Create your first team member using the form →<br/>Team members review bookings and connect clients with workers.</p>
            </div>
          <?php else: ?>
            <div class="agents-list">
              <?php foreach($agents as $ag):
                $is_active   = (int)($ag['is_active'] ?? 1);
                $svc_ids_arr = array_filter(explode(',', $ag['assigned_service_ids'] ?? ''));
                // Get service names for assigned IDs
                $svc_names = [];
                foreach($services as $svc) {
                    if(in_array($svc['id'], $svc_ids_arr)) {
                        $svc_names[] = ['icon'=>$svc['icon'], 'name'=>$svc['name']];
                    }
                }
              ?>
                <div class="agent-card <?= $is_active ? '' : 'inactive' ?>">
                  <!-- TOP: name, email, status -->
                  <div class="agent-card-top">
                    <div class="agent-av"><?= strtoupper(substr($ag['name'],0,1)) ?></div>
                    <div class="agent-info">
                      <div class="agent-name"><?= e($ag['name']) ?></div>
                      <div class="agent-email">✉️ <?= e($ag['email']) ?></div>
                      <div class="agent-phone">📱 <?= e($ag['whatsapp_number'] ?: $ag['phone'] ?: '—') ?></div>
                    </div>
                    <div class="agent-status">
                      <span class="status-pill <?= $is_active ? 'sp-active' : 'sp-inactive' ?>">
                        <?= $is_active ? '● Active' : '○ Inactive' ?>
                      </span>
                      <span style="font-size:0.72rem;color:var(--text-soft)">
                        Since <?= date('M Y', strtotime($ag['created_at'])) ?>
                      </span>
                    </div>
                  </div>

                  <!-- STATS -->
                  <div class="agent-card-stats">
                    <div class="agent-stat">
                      <div class="agent-stat-num"><?= $ag['total_bookings'] ?></div>
                      <div class="agent-stat-label">Total</div>
                    </div>
                    <div class="agent-stat">
                      <div class="agent-stat-num" style="color:var(--warning)"><?= $ag['pending_bookings'] ?></div>
                      <div class="agent-stat-label">Pending</div>
                    </div>
                    <div class="agent-stat">
                      <div class="agent-stat-num" style="color:var(--success)"><?= $ag['completed_bookings'] ?></div>
                      <div class="agent-stat-label">Completed</div>
                    </div>
                  </div>

                  <!-- ASSIGNED SERVICES -->
                  <div class="agent-card-services">
                    <span style="font-size:0.72rem;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.06em;margin-right:6px">Services:</span>
                    <?php if(empty($svc_names)): ?>
                      <span class="no-services">No services assigned yet</span>
                    <?php else: ?>
                      <?php foreach($svc_names as $sn): ?>
                        <span class="svc-tag"><?= $sn['icon'] ?> <?= e($sn['name']) ?></span>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>

                  <!-- ACTIONS -->
                  <div class="agent-card-actions">
                    <button class="act-btn act-edit"
                      onclick="loadEdit(
                        <?= $ag['user_id'] ?>,
                        '<?= e(addslashes($ag['name'])) ?>',
                        '<?= e(addslashes($ag['email'])) ?>',
                        '<?= e(addslashes($ag['whatsapp_number'] ?: $ag['phone'] ?: '')) ?>',
                        [<?= implode(',', array_filter(array_map('intval', explode(',', $ag['assigned_service_ids']??'')))) ?>],
                        <?= $is_active ?>
                      )">✏️ Edit</button>
                    <?php if($is_active): ?>
                      <button class="act-btn act-deactivate" onclick="toggleAgent(<?= $ag['user_id'] ?>)">⏸ Deactivate</button>
                    <?php else: ?>
                      <button class="act-btn act-activate"   onclick="toggleAgent(<?= $ag['user_id'] ?>)">▶ Activate</button>
                    <?php endif; ?>
                    <button class="act-btn act-delete" onclick="askDelete(<?= $ag['user_id'] ?>, '<?= e(addslashes($ag['name'])) ?>')">🗑️ Delete</button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- RIGHT: ADD / EDIT FORM -->
        <div class="form-card" id="formCard">
          <div class="form-card-header">
            <span class="form-card-title" id="formCardTitle">🎯 Add New Team Member</span>
          </div>
          <div class="form-card-body">
            <form method="POST" id="agentForm">
              <input type="hidden" name="csrf_token"      value="<?= generateCSRFToken() ?>"/>
              <input type="hidden" name="action"          id="formAction"    value="create_agent"/>
              <input type="hidden" name="agent_user_id"   id="formUserId"    value=""/>

              <div class="form-group">
                <label for="agentName">Full Name <span class="req">*</span></label>
                <input type="text" id="agentName" name="name" placeholder="e.g. Jean Habimana" required/>
              </div>

              <div class="form-group" id="emailGroup">
                <label for="agentEmail">Email Address <span class="req">*</span></label>
                <input type="email" id="agentEmail" name="email" placeholder="team@homehelp.rw"/>
              </div>

              <div class="form-group">
                <label for="agentPhone">WhatsApp / Phone <span class="req">*</span></label>
                <input type="tel" id="agentPhone" name="phone" placeholder="+250 7XX XXX XXX"/>
              </div>

              <div class="form-group" id="passwordGroup">
                <label for="agentPassword">Password <span class="req">*</span></label>
                <input type="password" id="agentPassword" name="password" placeholder="Min. 6 characters"/>
                <span class="hint-text">Team member will use this to login at /login</span>
              </div>

              <!-- SERVICE ASSIGNMENT -->
              <div class="form-group">
                <label>Assign Services <span class="opt">(select all that apply)</span></label>
                <?php if(empty($services)): ?>
                  <p style="font-size:0.8rem;color:var(--text-soft)">No active services. Add services first.</p>
                <?php else: ?>
                  <div class="services-check-grid" id="servicesGrid">
                    <?php foreach($services as $svc): ?>
                      <label class="svc-check-item" id="svcItem<?= $svc['id'] ?>">
                        <input type="checkbox" name="services[]"
                          value="<?= $svc['id'] ?>"
                          id="svcChk<?= $svc['id'] ?>"
                          onchange="updateCheckStyle(<?= $svc['id'] ?>)"/>
                        <span class="svc-check-label"><?= $svc['icon'] ?> <?= e($svc['name']) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- ACTIVE TOGGLE (only shown in edit mode) -->
              <div class="form-group" id="activeGroup" style="display:none">
                <label>Team Member Status</label>
                <div class="toggle-wrap">
                  <label class="toggle">
                    <input type="checkbox" name="is_active" id="isActive" value="1" checked/>
                    <span class="toggle-slider"></span>
                  </label>
                  <span class="toggle-label" id="toggleLabel">Active</span>
                </div>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn-submit" id="submitBtn">🎯 Create Team Member</button>
                <button type="button" class="btn-reset" onclick="resetForm()">Reset</button>
              </div>
            </form>
          </div>
        </div>

      </div>
    </div>
    <?php include_once '../includes/footer.php'; ?>
  </div>
</div>

<script>
// Sidebar
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');document.body.style.overflow=document.getElementById('sidebar').classList.contains('open')?'hidden':'';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
window.addEventListener('resize',function(){if(window.innerWidth>768)closeSidebar();});

// Checkbox styling
function updateCheckStyle(id){
  var chk = document.getElementById('svcChk'+id);
  var item = document.getElementById('svcItem'+id);
  if(chk && item) item.classList.toggle('checked', chk.checked);
}

// Load edit form
function loadEdit(userId, name, email, phone, serviceIds, isActive){
  document.getElementById('formAction').value  = 'update_agent';
  document.getElementById('formUserId').value  = userId;
  document.getElementById('agentName').value   = name;
  document.getElementById('agentPhone').value  = phone;

  // Hide email + password in edit mode
  document.getElementById('emailGroup').style.display    = 'none';
  document.getElementById('passwordGroup').style.display = 'none';
  document.getElementById('activeGroup').style.display   = 'block';

  // Set active toggle
  var chkActive = document.getElementById('isActive');
  chkActive.checked = (isActive == 1);
  document.getElementById('toggleLabel').textContent = isActive ? 'Active' : 'Inactive';

  // Check assigned services
  document.querySelectorAll('[id^="svcChk"]').forEach(function(c){
    c.checked = serviceIds.includes(parseInt(c.value));
    var id = c.value;
    var item = document.getElementById('svcItem'+id);
    if(item) item.classList.toggle('checked', c.checked);
  });

  document.getElementById('formCardTitle').textContent = '✏️ Edit Team Member';
  document.getElementById('submitBtn').textContent     = '💾 Save Changes';
  document.getElementById('formCard').scrollIntoView({behavior:'smooth',block:'nearest'});
}

// Reset to create mode
function resetForm(){
  document.getElementById('formAction').value = 'create_agent';
  document.getElementById('formUserId').value = '';
  document.getElementById('agentForm').reset();
  document.getElementById('emailGroup').style.display    = 'block';
  document.getElementById('passwordGroup').style.display = 'block';
  document.getElementById('activeGroup').style.display   = 'none';
  document.querySelectorAll('.svc-check-item').forEach(function(i){i.classList.remove('checked')});
  document.getElementById('formCardTitle').textContent = '🎯 Add New Team Member';
  document.getElementById('submitBtn').textContent     = '🎯 Create Team Member';
}

// Active toggle label
document.getElementById('isActive').addEventListener('change', function(){
  document.getElementById('toggleLabel').textContent = this.checked ? 'Active' : 'Inactive';
});

// Toggle team member
function toggleAgent(userId){
  document.getElementById('toggleUserId').value = userId;
  document.getElementById('toggleForm').submit();
}

// Delete
var _delUserId = 0;
function askDelete(userId, name){
  _delUserId = userId;
  document.getElementById('deleteModalMsg').textContent =
    'Delete team member "' + name + '"? Their account and profile will be permanently removed.';
  document.getElementById('deleteModal').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeModal(){
  document.getElementById('deleteModal').classList.remove('open');
  document.body.style.overflow='';
}
function confirmDelete(){
  document.getElementById('deleteUserId').value = _delUserId;
  document.getElementById('deleteForm').submit();
}
document.getElementById('deleteModal').addEventListener('click',function(e){if(e.target===this)closeModal();});
</script>
</body>
</html>