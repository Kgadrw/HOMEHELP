<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('admin');

$admin_name = $_SESSION['user_name'];

// ── HANDLE ACTIONS ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name        = sanitize($_POST['name']        ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $icon        = sanitize($_POST['icon']        ?? '🏠');

        if (!$name) {
            flashSet('error', 'Service name is required.');
        } else {
            // Check duplicate
            $chk = $conn->prepare("SELECT id FROM services WHERE name = ?");
            $chk->bind_param('s', $name); $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                flashSet('error', "A service named \"$name\" already exists.");
            } else {
                $max = $conn->query("SELECT IFNULL(MAX(sort_order),0)+1 AS n FROM services")->fetch_row()[0];
                $stmt = $conn->prepare("INSERT INTO services (name, description, icon, status, sort_order) VALUES (?,?,?,'active',?)");
                $stmt->bind_param('sssi', $name, $description, $icon, $max);
                $stmt->execute();
                flashSet('success', "Service \"$name\" created successfully.");
            }
        }

    } elseif ($action === 'edit') {
        $id          = (int)($_POST['service_id']   ?? 0);
        $name        = sanitize($_POST['name']        ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $icon        = sanitize($_POST['icon']        ?? '🏠');

        if (!$id || !$name) {
            flashSet('error', 'Service name is required.');
        } else {
            $stmt = $conn->prepare("UPDATE services SET name=?, description=?, icon=? WHERE id=?");
            $stmt->bind_param('sssi', $name, $description, $icon, $id);
            $stmt->execute();
            flashSet('success', "Service updated successfully.");
        }

    } elseif ($action === 'toggle') {
        $id = (int)($_POST['service_id'] ?? 0);
        if ($id) {
            $cur = $conn->prepare("SELECT status FROM services WHERE id=?");
            $cur->bind_param('i', $id); $cur->execute();
            $row = $cur->get_result()->fetch_assoc();
            $new = ($row['status'] === 'active') ? 'inactive' : 'active';
            $stmt = $conn->prepare("UPDATE services SET status=? WHERE id=?");
            $stmt->bind_param('si', $new, $id); $stmt->execute();
            flashSet('success', "Service ".($new==='active'?'activated':'deactivated')." successfully.");
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['service_id'] ?? 0);
        if ($id) {
            // Check if any bookings use this service
            $chk = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE service_id=?");
            $chk->bind_param('i', $id); $chk->execute();
            $used = $chk->get_result()->fetch_row()[0];
            if ($used > 0) {
                flashSet('error', "Cannot delete — this service has $used booking(s). Deactivate it instead.");
            } else {
                $stmt = $conn->prepare("DELETE FROM services WHERE id=?");
                $stmt->bind_param('i', $id); $stmt->execute();
                flashSet('success', "Service deleted successfully.");
            }
        }

    } elseif ($action === 'reorder') {
        $ids = $_POST['order'] ?? [];
        foreach ($ids as $pos => $sid) {
            $sid = (int)$sid; $pos = (int)$pos + 1;
            $conn->prepare("UPDATE services SET sort_order=? WHERE id=?")->execute() ;
            $s = $conn->prepare("UPDATE services SET sort_order=? WHERE id=?");
            $s->bind_param('ii', $pos, $sid); $s->execute();
        }
        echo json_encode(['ok' => true]); exit;
    }

    redirect(BASE_URL . '/admin/services');
}

// ── FETCH SERVICES ────────────────────────────────────────────
$services = $conn->query("
    SELECT s.*,
           COUNT(b.id) AS booking_count
    FROM services s
    LEFT JOIN bookings b ON b.service_id = s.id
    GROUP BY s.id
    ORDER BY s.sort_order ASC, s.id ASC
")->fetch_all(MYSQLI_ASSOC);

$flash = flashGet();
$notif_count = unreadNotifCount();

// Common emoji options for picker
$emoji_options = ['🏠','👧','🧹','👨‍🍳','🤝','🐾','🛁','🌿','🔧','🚗','👶','🏥','📦','🎓','🍽️'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Services | Admin — Home Help</title>
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
.two-col{display:grid;grid-template-columns:1fr 360px;gap:20px}

/* SERVICE CARDS */
.services-list{display:flex;flex-direction:column;gap:12px}
.service-card{
  background:var(--white);border:1.5px solid var(--cream-dark);
  border-radius:var(--radius);padding:18px 20px;
  display:flex;align-items:center;gap:16px;
  transition:all 0.25s;
}
.service-card:hover{box-shadow:var(--shadow-sm);border-color:var(--admin-soft)}
.service-card.inactive{opacity:0.55}
.drag-handle{color:var(--text-soft);cursor:grab;font-size:1.1rem;flex-shrink:0;user-select:none}
.drag-handle:active{cursor:grabbing}
.service-icon-big{
  width:50px;height:50px;border-radius:14px;
  background:var(--admin-soft);
  display:flex;align-items:center;justify-content:center;
  font-size:1.5rem;flex-shrink:0;
}
.service-card.inactive .service-icon-big{background:var(--cream-dark)}
.service-info{flex:1;min-width:0}
.service-name{font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--admin-deep)}
.service-desc{font-size:0.8rem;color:var(--text-soft);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:300px}
.service-meta{display:flex;align-items:center;gap:10px;margin-top:6px}
.booking-count{font-size:0.75rem;font-weight:600;color:var(--text-mid)}
.status-chip{display:inline-block;padding:2px 8px;border-radius:99px;font-size:0.7rem;font-weight:700}
.chip-active{background:var(--success-bg);color:#065F46}
.chip-inactive{background:var(--cream-dark);color:var(--text-soft)}
.service-actions{display:flex;gap:6px;flex-shrink:0}
.act-btn{display:inline-flex;align-items:center;gap:3px;padding:6px 12px;border-radius:var(--radius-sm);font-size:0.76rem;font-weight:600;cursor:pointer;border:1.5px solid;transition:all 0.2s;background:var(--white);font-family:var(--font-body)}
.act-edit{border-color:var(--cream-dark);color:var(--text-mid)}.act-edit:hover{border-color:var(--admin-light);color:var(--admin-deep);background:var(--admin-soft)}
.act-toggle-on{border-color:#FECACA;color:#B91C1C}.act-toggle-on:hover{background:var(--danger-bg)}
.act-toggle-off{border-color:#BBF7D0;color:#065F46}.act-toggle-off:hover{background:var(--success-bg)}
.act-delete{border-color:#FECACA;color:#991B1B}.act-delete:hover{background:var(--danger-bg)}

/* ADD / EDIT FORM CARD */
.form-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden;position:sticky;top:82px}
.form-card-header{padding:16px 20px;border-bottom:1px solid var(--cream-dark);display:flex;align-items:center;justify-content:space-between}
.form-card-title{font-family:var(--font-display);font-size:1.05rem;font-weight:700;color:var(--admin-deep)}
.form-card-body{padding:20px}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.form-group:last-child{margin-bottom:0}
.form-group label{font-size:0.8rem;font-weight:600;color:var(--text-dark)}
.form-group input,.form-group textarea,.form-group select{
  width:100%;padding:10px 14px;border:1.5px solid var(--cream-dark);
  border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.86rem;
  color:var(--text-dark);background:var(--cream);transition:all 0.2s;outline:none;
}
.form-group textarea{resize:vertical;min-height:80px}
.form-group input:focus,.form-group textarea:focus,.form-group select:focus{
  border-color:var(--admin-light);background:var(--white);
  box-shadow:0 0 0 3px rgba(79,70,229,0.1)
}

/* EMOJI PICKER */
.emoji-picker{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.emoji-opt{
  width:36px;height:36px;border-radius:8px;border:1.5px solid var(--cream-dark);
  background:var(--white);font-size:1.1rem;cursor:pointer;
  display:flex;align-items:center;justify-content:center;transition:all 0.2s;
}
.emoji-opt:hover{border-color:var(--admin-light);background:var(--admin-soft)}
.emoji-opt.selected{border-color:var(--admin-deep);background:var(--admin-soft);box-shadow:0 0 0 2px rgba(19,16,58,0.15)}
.emoji-preview{font-size:2rem;text-align:center;padding:8px;background:var(--admin-soft);border-radius:var(--radius-sm);margin-bottom:8px}

/* SUBMIT BUTTONS */
.form-actions{display:flex;gap:8px;margin-top:18px}
.btn-submit{flex:1;padding:11px;border-radius:var(--radius-sm);background:var(--admin-deep);color:var(--white);font-family:var(--font-body);font-size:0.88rem;font-weight:700;border:none;cursor:pointer;transition:all 0.25s}
.btn-submit:hover{background:var(--admin-mid)}
.btn-reset{padding:11px 16px;border-radius:var(--radius-sm);border:1.5px solid var(--cream-dark);background:transparent;color:var(--text-mid);font-family:var(--font-body);font-size:0.86rem;font-weight:600;cursor:pointer;transition:all 0.2s}
.btn-reset:hover{border-color:var(--admin-light);color:var(--admin-deep)}

/* EMPTY */
.empty-state{text-align:center;padding:48px 20px;background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius)}
.empty-state p{color:var(--text-soft);font-size:0.9rem}

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
@media(max-width:1000px){.two-col{grid-template-columns:1fr}.form-card{position:static}}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .content{padding:16px}.topbar{padding:0 16px}
  .service-card{flex-wrap:wrap}.service-actions{width:100%}
}
</style>
</head>
<body>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <h3>Delete Service?</h3>
    <p id="deleteModalMsg">This will permanently delete this service. This cannot be undone.</p>
    <div class="modal-actions">
      <button class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
      <button class="btn-modal-danger" onclick="confirmDelete()">🗑️ Delete</button>
    </div>
  </div>
</div>

<!-- Hidden forms -->
<form method="POST" id="toggleForm" style="display:none">
  <input type="hidden" name="csrf_token"  value="<?= generateCSRFToken() ?>"/>
  <input type="hidden" name="action"      value="toggle"/>
  <input type="hidden" name="service_id"  id="toggleId" value=""/>
</form>
<form method="POST" id="deleteForm" style="display:none">
  <input type="hidden" name="csrf_token"  value="<?= generateCSRFToken() ?>"/>
  <input type="hidden" name="action"      value="delete"/>
  <input type="hidden" name="service_id"  id="deleteId" value=""/>
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
      <a href="<?= BASE_URL ?>/admin/services" class="nav-item active"><span class="nav-icon">🛎️</span> Services</a>
      <a href="<?= BASE_URL ?>/admin/agents"   class="nav-item"><span class="nav-icon">🎯</span> Team</a>
      <a href="<?= BASE_URL ?>/admin/applications" class="nav-item"><span class="nav-icon">💼</span> Job Applications</a>
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
        <span class="topbar-title">Services</span>
      </div>
      <div class="topbar-right">
        <span class="topbar-chip">🛡️ Admin Panel</span>
        <button class="btn-add" onclick="resetForm()">＋ Add Service</button>
      </div>
    </header>

    <div class="content">

      <?php if($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>">
          <?= $flash['type']==='success' ? '✅' : '⚠️' ?> <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="page-header">
        <h1>Manage <em>Services</em></h1>
      </div>

      <div class="two-col">

        <!-- LEFT: SERVICE LIST -->
        <div>
          <?php if(empty($services)): ?>
            <div class="empty-state"><p>🛎️ No services yet. Add your first service using the form →</p></div>
          <?php else: ?>
            <div class="services-list" id="servicesList">
              <?php foreach($services as $svc): ?>
                <div class="service-card <?= $svc['status']==='inactive' ? 'inactive' : '' ?>"
                     data-id="<?= $svc['id'] ?>">
                  <span class="drag-handle" title="Drag to reorder">⠿</span>
                  <div class="service-icon-big"><?= $svc['icon'] ?: '🏠' ?></div>
                  <div class="service-info">
                    <div class="service-name"><?= e($svc['name']) ?></div>
                    <div class="service-desc"><?= e($svc['description'] ?: 'No description') ?></div>
                    <div class="service-meta">
                      <span class="booking-count">📋 <?= $svc['booking_count'] ?> booking<?= $svc['booking_count']!=1?'s':'' ?></span>
                      <span class="status-chip <?= $svc['status']==='active' ? 'chip-active' : 'chip-inactive' ?>">
                        <?= $svc['status']==='active' ? '● Active' : '○ Inactive' ?>
                      </span>
                    </div>
                  </div>
                  <div class="service-actions">
                    <button class="act-btn act-edit"
                      onclick="loadEdit(<?= $svc['id'] ?>, '<?= e(addslashes($svc['name'])) ?>', '<?= e(addslashes($svc['description']??'')) ?>', '<?= e($svc['icon']??'🏠') ?>')">
                      ✏️ Edit
                    </button>
                    <?php if($svc['status']==='active'): ?>
                      <button class="act-btn act-toggle-on" onclick="toggleService(<?= $svc['id'] ?>)">⏸ Disable</button>
                    <?php else: ?>
                      <button class="act-btn act-toggle-off" onclick="toggleService(<?= $svc['id'] ?>)">▶ Enable</button>
                    <?php endif; ?>
                    <?php if($svc['booking_count'] == 0): ?>
                      <button class="act-btn act-delete" onclick="askDelete(<?= $svc['id'] ?>, '<?= e(addslashes($svc['name'])) ?>')">🗑️</button>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <p style="font-size:0.76rem;color:var(--text-soft);margin-top:12px;text-align:center">
              ⠿ Drag cards to reorder services on the homepage
            </p>
          <?php endif; ?>
        </div>

        <!-- RIGHT: ADD / EDIT FORM -->
        <div class="form-card" id="formCard">
          <div class="form-card-header">
            <span class="form-card-title" id="formCardTitle">➕ Add New Service</span>
          </div>
          <div class="form-card-body">
            <form method="POST" id="serviceForm">
              <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
              <input type="hidden" name="action"     id="formAction" value="create"/>
              <input type="hidden" name="service_id" id="formServiceId" value=""/>

              <!-- ICON PICKER -->
              <div class="form-group">
                <label>Icon / Emoji</label>
                <div class="emoji-preview" id="emojiPreview">🏠</div>
                <input type="hidden" name="icon" id="iconInput" value="🏠"/>
                <div class="emoji-picker" id="emojiPicker">
                  <?php foreach($emoji_options as $em): ?>
                    <button type="button" class="emoji-opt <?= $em==='🏠'?'selected':'' ?>"
                      data-emoji="<?= $em ?>" onclick="selectEmoji('<?= $em ?>', this)">
                      <?= $em ?>
                    </button>
                  <?php endforeach; ?>
                </div>
                <p style="font-size:0.73rem;color:var(--text-soft);margin-top:6px">
                  Or type any emoji directly:
                  <input type="text" id="customEmoji" maxlength="4" placeholder="✨"
                    style="width:48px;padding:4px 8px;border:1.5px solid var(--cream-dark);border-radius:6px;font-size:1rem;text-align:center"
                    oninput="selectEmoji(this.value, null)"/>
                </p>
              </div>

              <div class="form-group">
                <label for="svcName">Service Name <span style="color:var(--danger)">*</span></label>
                <input type="text" id="svcName" name="name" placeholder="e.g. Nanny" required/>
              </div>

              <div class="form-group">
                <label for="svcDesc">Description</label>
                <textarea id="svcDesc" name="description" placeholder="Brief description shown to clients..."></textarea>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn-submit" id="submitBtn">➕ Create Service</button>
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

// Emoji picker
function selectEmoji(emoji, btn){
  if(!emoji) return;
  document.getElementById('iconInput').value = emoji;
  document.getElementById('emojiPreview').textContent = emoji;
  document.querySelectorAll('.emoji-opt').forEach(function(b){b.classList.remove('selected')});
  if(btn) btn.classList.add('selected');
}

// Load edit form
function loadEdit(id, name, desc, icon){
  document.getElementById('formAction').value    = 'edit';
  document.getElementById('formServiceId').value = id;
  document.getElementById('svcName').value        = name;
  document.getElementById('svcDesc').value        = desc;
  selectEmoji(icon || '🏠', null);
  document.getElementById('customEmoji').value   = icon || '';
  document.getElementById('formCardTitle').textContent = '✏️ Edit Service';
  document.getElementById('submitBtn').textContent    = '💾 Save Changes';
  document.getElementById('formCard').scrollIntoView({behavior:'smooth', block:'nearest'});
}

// Reset to create mode
function resetForm(){
  document.getElementById('formAction').value    = 'create';
  document.getElementById('formServiceId').value = '';
  document.getElementById('serviceForm').reset();
  selectEmoji('🏠', document.querySelector('.emoji-opt[data-emoji="🏠"]'));
  document.getElementById('formCardTitle').textContent = '➕ Add New Service';
  document.getElementById('submitBtn').textContent    = '➕ Create Service';
}

// Toggle service
function toggleService(id){
  document.getElementById('toggleId').value = id;
  document.getElementById('toggleForm').submit();
}

// Delete
var _delId = 0;
function askDelete(id, name){
  _delId = id;
  document.getElementById('deleteModalMsg').textContent =
    'Delete service "' + name + '"? This cannot be undone.';
  document.getElementById('deleteModal').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeModal(){
  document.getElementById('deleteModal').classList.remove('open');
  document.body.style.overflow='';
}
function confirmDelete(){
  document.getElementById('deleteId').value = _delId;
  document.getElementById('deleteForm').submit();
}
document.getElementById('deleteModal').addEventListener('click',function(e){if(e.target===this)closeModal();});

// Drag to reorder
var list = document.getElementById('servicesList');
if(list){
  var dragging = null;
  list.addEventListener('dragstart',function(e){
    dragging = e.target.closest('.service-card');
    setTimeout(function(){if(dragging)dragging.style.opacity='0.4';},0);
  });
  list.addEventListener('dragend',function(){
    if(dragging) dragging.style.opacity='';
    dragging = null;
    saveOrder();
  });
  list.addEventListener('dragover',function(e){
    e.preventDefault();
    var over = e.target.closest('.service-card');
    if(over && over !== dragging){
      var rect = over.getBoundingClientRect();
      var mid  = rect.top + rect.height/2;
      if(e.clientY < mid) list.insertBefore(dragging, over);
      else list.insertBefore(dragging, over.nextSibling);
    }
  });
  list.querySelectorAll('.service-card').forEach(function(c){ c.setAttribute('draggable','true'); });

  function saveOrder(){
    var ids = Array.from(list.querySelectorAll('.service-card')).map(function(c){return c.dataset.id;});
    fetch('', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'action=reorder&csrf_token=<?= generateCSRFToken() ?>&'+ids.map(function(id,i){return 'order[]='+id;}).join('&')
    });
  }
}
</script>
</body>
</html>