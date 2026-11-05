<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('admin');

$admin_name = $_SESSION['user_name'];
$admin_id   = $_SESSION['user_id'];

// ── HANDLE ACTIONS ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();

    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    // Prevent admin from modifying themselves
    if ($user_id === $admin_id) {
        flashSet('error', 'You cannot modify your own account from here.');
        redirect(BASE_URL . '/admin/users');
    }

    if ($user_id) {
        if ($action === 'block') {
            $stmt = $conn->prepare("UPDATE users SET status='blocked' WHERE id=?");
            $stmt->bind_param('i', $user_id); $stmt->execute();
            flashSet('success', 'User blocked successfully.');

        } elseif ($action === 'unblock') {
            $stmt = $conn->prepare("UPDATE users SET status='active' WHERE id=?");
            $stmt->bind_param('i', $user_id); $stmt->execute();
            flashSet('success', 'User unblocked successfully.');

        } elseif ($action === 'change_role') {
            $new_role = $_POST['new_role'] ?? '';
            $allowed_roles = ['client','provider','agent','admin'];
            if (in_array($new_role, $allowed_roles)) {
                $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=?");
                $stmt->bind_param('si', $new_role, $user_id); $stmt->execute();
                $role_label = $new_role === 'agent' ? 'Team' : ucfirst($new_role);
                flashSet('success', 'User role updated to ' . $role_label . '.');
            }

        } elseif ($action === 'delete') {
            // Soft delete — block first, prevent accidental permanent delete
            $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND role != 'admin'");
            $stmt->bind_param('i', $user_id); $stmt->execute();
            if ($stmt->affected_rows > 0) {
                flashSet('success', 'User deleted successfully.');
            } else {
                flashSet('error', 'Cannot delete admin accounts.');
            }
        }
    }
    redirect(BASE_URL . '/admin/users');
}

// ── FILTERS ───────────────────────────────────────────────────
$filter_role   = $_GET['role']   ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$search        = sanitize($_GET['search'] ?? '');

$allowed_roles   = ['all','client','provider','agent','admin'];
$allowed_statuses= ['all','active','blocked'];
if (!in_array($filter_role,   $allowed_roles))    $filter_role   = 'all';
if (!in_array($filter_status, $allowed_statuses)) $filter_status = 'all';

// ── COUNTS ────────────────────────────────────────────────────
$role_counts = [];
$rc = $conn->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
foreach ($rc->fetch_all(MYSQLI_ASSOC) as $r) $role_counts[$r['role']] = $r['cnt'];
$role_counts['all'] = array_sum($role_counts);

// ── FETCH USERS ───────────────────────────────────────────────
$where  = "WHERE 1=1";
$params = []; $types = '';

if ($filter_role !== 'all')   { $where .= " AND role=?";   $params[] = $filter_role;   $types .= 's'; }
if ($filter_status !== 'all') { $where .= " AND status=?"; $params[] = $filter_status; $types .= 's'; }
if ($search) {
    $like    = "%$search%";
    $where  .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types  .= 'sss';
}

$stmt = $conn->prepare("SELECT * FROM users $where ORDER BY created_at DESC");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Booking count per user
$booking_counts = [];
$bc = $conn->query("SELECT client_id, COUNT(*) as cnt FROM bookings GROUP BY client_id");
foreach ($bc->fetch_all(MYSQLI_ASSOC) as $r) $booking_counts[$r['client_id']] = $r['cnt'];

$flash = flashGet();
$notif_count = unreadNotifCount();

// Helper: display label for a role (DB value stays 'agent', UI shows 'Team')
function roleLabel($role) {
    return $role === 'agent' ? 'Team' : ucfirst($role);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Users | Admin — Home Help</title>
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
  --shadow-sm:0 2px 12px rgba(19,16,58,0.07);
  --shadow-md:0 6px 28px rgba(19,16,58,0.11);
  --radius:14px;--radius-sm:8px;
  --admin-sidebar:#0E0C2E;--admin-accent:#E8A020;
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
.nav-badge{margin-left:auto;background:var(--danger);color:var(--white);font-size:0.63rem;font-weight:700;padding:2px 6px;border-radius:99px}
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

/* CONTENT */
.content{padding:24px 28px;flex:1}
.flash{padding:12px 16px;border-radius:var(--radius-sm);font-size:0.86rem;font-weight:500;margin-bottom:18px;display:flex;align-items:center;gap:10px}
.flash-success{background:var(--success-bg);border:1.5px solid #BBF7D0;color:#065F46}
.flash-error{background:var(--danger-bg);border:1.5px solid #FECACA;color:#B91C1C}

/* STATS ROW */
.stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px}
.mini-stat{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:14px 16px;transition:all 0.25s;cursor:pointer;text-decoration:none;display:block}
.mini-stat:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm)}
.mini-stat.active-filter{border-color:var(--admin-sidebar);box-shadow:0 0 0 3px rgba(79,70,229,0.12)}
.mini-stat-num{font-family:var(--font-display);font-size:1.7rem;font-weight:700;color:var(--admin-deep);line-height:1}
.mini-stat-label{font-size:0.74rem;color:var(--text-soft);margin-top:3px;font-weight:500}

/* FILTER BAR */
.filter-bar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px}
.filter-left{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.filter-select{padding:7px 12px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.82rem;background:var(--white);color:var(--text-dark);outline:none;cursor:pointer}
.filter-select:focus{border-color:var(--admin-light)}
.search-wrap{position:relative}
.search-wrap input{padding:8px 14px 8px 36px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.84rem;background:var(--white);outline:none;width:220px;transition:all 0.2s}
.search-wrap input:focus{border-color:var(--admin-light);box-shadow:0 0 0 3px rgba(79,70,229,0.1)}
.search-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:0.85rem;pointer-events:none}

/* TABLE */
.card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden}
.table-wrap{overflow-x:auto}
.admin-table{width:100%;border-collapse:collapse;min-width:800px}
.admin-table th{font-size:0.69rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:var(--text-soft);padding:11px 16px;text-align:left;background:var(--cream);border-bottom:1px solid var(--cream-dark);white-space:nowrap}
.admin-table td{padding:13px 16px;font-size:0.83rem;border-bottom:1px solid var(--cream-dark);vertical-align:middle}
.admin-table tr:last-child td{border-bottom:none}
.admin-table tr:hover td{background:#fafafe}

/* USER CELL */
.user-cell{display:flex;align-items:center;gap:10px}
.user-av{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:0.92rem;font-weight:700;color:var(--white);flex-shrink:0}
.av-client{background:linear-gradient(135deg,var(--admin-light),var(--admin-deep))}
.av-provider{background:linear-gradient(135deg,var(--amber),#c47d0a)}
.av-agent{background:linear-gradient(135deg,#3B82F6,#1d4ed8)}
.av-admin{background:linear-gradient(135deg,#8B5CF6,#5b21b6)}
.cell-name{font-weight:700;font-size:0.84rem;color:var(--text-dark)}
.cell-sub{font-size:0.73rem;color:var(--text-soft);margin-top:1px}

/* BADGES */
.role-badge{display:inline-block;padding:3px 10px;border-radius:99px;font-size:0.71rem;font-weight:700}
.rb-client{background:#EFF6FF;color:#1E40AF}
.rb-provider{background:var(--amber-light);color:#92400E}
.rb-agent{background:#EDE9FE;color:#5b21b6}
.rb-admin{background:#FEF2F2;color:#991B1B}

.status-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;font-size:0.71rem;font-weight:700}
.sb-active{background:var(--success-bg);color:#065F46}
.sb-blocked{background:var(--danger-bg);color:#991B1B}

/* ACTION BUTTONS */
.actions-cell{display:flex;gap:5px;flex-wrap:wrap;align-items:center}
.act-btn{display:inline-flex;align-items:center;gap:3px;padding:5px 10px;border-radius:var(--radius-sm);font-size:0.73rem;font-weight:600;cursor:pointer;border:1.5px solid;transition:all 0.2s;background:var(--white);font-family:var(--font-body);white-space:nowrap}
.act-block{border-color:#FECACA;color:#B91C1C}.act-block:hover{background:var(--danger-bg)}
.act-unblock{border-color:#BBF7D0;color:#065F46}.act-unblock:hover{background:var(--success-bg)}
.act-delete{border-color:#FECACA;color:#991B1B}.act-delete:hover{background:#FEF2F2}

/* ROLE CHANGE SELECT */
.role-change-wrap{display:flex;gap:5px;align-items:center}
.role-select{padding:4px 8px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.75rem;background:var(--white);outline:none}
.role-select:focus{border-color:var(--amber)}
.btn-role{padding:5px 9px;border-radius:var(--radius-sm);background:var(--amber);border:none;color:var(--white);font-size:0.72rem;font-weight:700;cursor:pointer;font-family:var(--font-body)}
.btn-role:hover{background:#d4911a}

/* YOU badge */
.you-badge{display:inline-block;padding:1px 6px;border-radius:99px;background:var(--admin-soft);color:var(--admin-deep);font-size:0.66rem;font-weight:700;margin-left:4px}

/* EMPTY */
.empty-state{text-align:center;padding:48px 20px;color:var(--text-soft);font-size:0.9rem}

/* CONFIRM MODAL */
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
@media(max-width:1100px){.stats-row{grid-template-columns:repeat(3,1fr)}}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .content{padding:16px}.topbar{padding:0 16px}
  .stats-row{grid-template-columns:repeat(2,1fr)}
  .search-wrap input{width:160px}
}
</style>
</head>
<body>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <h3>Delete User?</h3>
    <p id="deleteModalMsg">This will permanently delete the user and all their data. This action cannot be undone.</p>
    <div class="modal-actions">
      <button class="btn-modal-cancel" onclick="closeDeleteModal()">Cancel</button>
      <button class="btn-modal-danger" onclick="confirmDelete()">🗑️ Delete</button>
    </div>
  </div>
</div>

<!-- Hidden forms -->
<form method="POST" id="actionForm" style="display:none">
  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
  <input type="hidden" name="action"   id="formAction"  value=""/>
  <input type="hidden" name="user_id"  id="formUserId"  value=""/>
  <input type="hidden" name="new_role" id="formNewRole" value=""/>
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
      <a href="<?= BASE_URL ?>/admin"           class="nav-item"><span class="nav-icon">📊</span> Dashboard</a>
      <div class="nav-section-label">Manage</div>
      <a href="<?= BASE_URL ?>/admin/bookings"  class="nav-item"><span class="nav-icon">📋</span> Bookings</a>
      <a href="<?= BASE_URL ?>/admin/users"     class="nav-item active"><span class="nav-icon">👥</span> Users</a>
      <a href="<?= BASE_URL ?>/admin/services"  class="nav-item"><span class="nav-icon">🛎️</span> Services</a>
      <a href="<?= BASE_URL ?>/admin/agents"    class="nav-item"><span class="nav-icon">🎯</span> Team</a>
      <div class="nav-section-label">Reports</div>
      <a href="<?= BASE_URL ?>/admin/reports" class="nav-item"><span class="nav-icon">📈</span> Reports</a>
      <div class="nav-section-label">View As</div>
      <a href="<?= BASE_URL ?>/dashboard"       class="nav-item"><span class="nav-icon">🏠</span> Client View</a>
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
        <span class="topbar-title">Users</span>
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

      <!-- STATS ROW -->
      <div class="stats-row">
        <?php
        $role_tabs = [
          'all'      => ['All Users',   '👥'],
          'client'   => ['Clients',     '🏠'],
          'provider' => ['Job Seekers', '💼'],
          'agent'    => ['Team',        '🎯'],
          'admin'    => ['Admins',      '🛡️'],
        ];
        foreach($role_tabs as $key => [$label, $icon]):
          $cnt = $role_counts[$key] ?? 0;
          $active = $filter_role === $key;
        ?>
          <a href="?role=<?= $key ?>&status=<?= $filter_status ?><?= $search ? '&search='.urlencode($search) : '' ?>"
             class="mini-stat <?= $active ? 'active-filter' : '' ?>">
            <div class="mini-stat-num"><?= $cnt ?></div>
            <div class="mini-stat-label"><?= $icon ?> <?= $label ?></div>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- FILTER BAR -->
      <div class="filter-bar">
        <div class="filter-left">
          <select class="filter-select" onchange="applyFilter('role', this.value)">
            <option value="all"      <?= $filter_role==='all'      ? 'selected':'' ?>>All Roles</option>
            <option value="client"   <?= $filter_role==='client'   ? 'selected':'' ?>>Clients</option>
            <option value="provider" <?= $filter_role==='provider' ? 'selected':'' ?>>Job Seekers</option>
            <option value="agent"    <?= $filter_role==='agent'    ? 'selected':'' ?>>Team</option>
            <option value="admin"    <?= $filter_role==='admin'    ? 'selected':'' ?>>Admins</option>
          </select>
          <select class="filter-select" onchange="applyFilter('status', this.value)">
            <option value="all"     <?= $filter_status==='all'     ? 'selected':'' ?>>All Status</option>
            <option value="active"  <?= $filter_status==='active'  ? 'selected':'' ?>>Active</option>
            <option value="blocked" <?= $filter_status==='blocked' ? 'selected':'' ?>>Blocked</option>
          </select>
        </div>
        <form method="GET" class="search-wrap">
          <span class="search-icon">🔍</span>
          <input type="hidden" name="role"   value="<?= e($filter_role) ?>"/>
          <input type="hidden" name="status" value="<?= e($filter_status) ?>"/>
          <input type="text" name="search" placeholder="Search name, email..."
            value="<?= e($search) ?>" autocomplete="off"/>
        </form>
      </div>

      <!-- TABLE -->
      <div class="card">
        <div class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>User</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Bookings</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($users)): ?>
                <tr><td colspan="8"><div class="empty-state">👥 No users found.</div></td></tr>
              <?php else: ?>
                <?php foreach($users as $u):
                  $is_me = ($u['id'] == $admin_id);
                ?>
                  <tr>
                    <td style="font-size:0.74rem;color:var(--text-soft);font-weight:700"><?= $u['id'] ?></td>
                    <td>
                      <div class="user-cell">
                        <div class="user-av av-<?= $u['role'] ?>"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                        <div>
                          <div class="cell-name">
                            <?= e($u['name']) ?>
                            <?php if($is_me): ?><span class="you-badge">You</span><?php endif; ?>
                          </div>
                          <div class="cell-sub"><?= e($u['email']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td style="font-size:0.8rem;color:var(--text-mid)"><?= e($u['phone'] ?? '—') ?></td>
                    <td>
                      <?php if($is_me): ?>
                        <span class="role-badge rb-<?= $u['role'] ?>"><?= roleLabel($u['role']) ?></span>
                      <?php else: ?>
                        <div class="role-change-wrap">
                          <select class="role-select" id="roleSelect<?= $u['id'] ?>">
                            <?php foreach(['client','provider','agent','admin'] as $r): ?>
                              <option value="<?= $r ?>" <?= $u['role']===$r ? 'selected':'' ?>><?= roleLabel($r) ?></option>
                            <?php endforeach; ?>
                          </select>
                          <button class="btn-role" onclick="changeRole(<?= $u['id'] ?>)">✓</button>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span style="font-size:0.83rem;font-weight:700;color:var(--admin-deep)">
                        <?= $booking_counts[$u['id']] ?? 0 ?>
                      </span>
                    </td>
                    <td>
                      <span class="status-badge sb-<?= $u['status'] ?>">
                        <?= $u['status']==='active' ? '✅ Active' : '🚫 Blocked' ?>
                      </span>
                    </td>
                    <td style="font-size:0.77rem;color:var(--text-soft);white-space:nowrap">
                      <?= date('d M Y', strtotime($u['created_at'])) ?>
                    </td>
                    <td>
                      <?php if($is_me): ?>
                        <span style="font-size:0.76rem;color:var(--text-soft)">— your account</span>
                      <?php else: ?>
                        <div class="actions-cell">
                          <?php if($u['status']==='active'): ?>
                            <button class="act-btn act-block"   onclick="quickAction('block',  <?= $u['id'] ?>)">🚫 Block</button>
                          <?php else: ?>
                            <button class="act-btn act-unblock" onclick="quickAction('unblock',<?= $u['id'] ?>)">✅ Unblock</button>
                          <?php endif; ?>
                          <?php if($u['role'] !== 'admin'): ?>
                            <button class="act-btn act-delete"  onclick="askDelete(<?= $u['id'] ?>, '<?= e(addslashes($u['name'])) ?>')">🗑️</button>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <p style="font-size:0.78rem;color:var(--text-soft);margin-top:12px">
        Showing <?= count($users) ?> user<?= count($users)!==1?'s':'' ?>
        <?= $search ? "matching \"$search\"" : '' ?>
      </p>

    </div>
    <?php include_once '../includes/footer.php'; ?>
  </div>
</div>

<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');document.body.style.overflow=document.getElementById('sidebar').classList.contains('open')?'hidden':'';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
window.addEventListener('resize',function(){if(window.innerWidth>768)closeSidebar();});

function applyFilter(key, val){
  var url = new URL(window.location.href);
  url.searchParams.set(key, val);
  window.location.href = url.toString();
}

function quickAction(action, id){
  document.getElementById('formAction').value  = action;
  document.getElementById('formUserId').value  = id;
  document.getElementById('formNewRole').value = '';
  document.getElementById('actionForm').submit();
}

function changeRole(id){
  var role = document.getElementById('roleSelect'+id).value;
  document.getElementById('formAction').value  = 'change_role';
  document.getElementById('formUserId').value  = id;
  document.getElementById('formNewRole').value = role;
  document.getElementById('actionForm').submit();
}

var _deleteId = 0;
function askDelete(id, name){
  _deleteId = id;
  document.getElementById('deleteModalMsg').textContent =
    'Delete "' + name + '"? This will permanently remove their account and all data.';
  document.getElementById('deleteModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeDeleteModal(){
  document.getElementById('deleteModal').classList.remove('open');
  document.body.style.overflow = '';
}
function confirmDelete(){
  document.getElementById('formAction').value  = 'delete';
  document.getElementById('formUserId').value  = _deleteId;
  document.getElementById('formNewRole').value = '';
  document.getElementById('actionForm').submit();
}
document.getElementById('deleteModal').addEventListener('click',function(e){if(e.target===this)closeDeleteModal();});
</script>
</body>
</html>