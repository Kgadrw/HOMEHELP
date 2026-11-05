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

// ── SHELL ─────────────────────────────────────────────────────
$page_title   = 'Users';
$page_heading = 'Users';
$dash_role    = 'admin';
$dash_page    = 'users';
$user_name    = $admin_name;
include '../includes/dash-head.php';
?>
</head>
<body>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <h3>Delete User?</h3>
    <p id="deleteModalMsg">This will permanently delete the user and all their data. This action cannot be undone.</p>
    <div class="modal-actions">
      <button class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
      <button class="btn btn-danger" onclick="confirmDelete()"><i data-lucide="trash-2"></i> Delete</button>
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

<?php include '../includes/dash-sidebar.php'; ?>

<?php if($flash): ?>
  <div class="flash flash-<?= e($flash['type']) ?>">
    <i data-lucide="<?= $flash['type']==='success' ? 'check-circle-2' : 'alert-triangle' ?>"></i>
    <span><?= e($flash['message']) ?></span>
  </div>
<?php endif; ?>

<div class="page-head">
  <div>
    <h1>Users</h1>
    <p>Manage accounts, roles and access across the platform.</p>
  </div>
</div>

<!-- ROLE FILTER CARDS -->
<div class="stats-grid cols-5">
  <?php
  $role_tabs = [
    'all'      => ['All Users',   'users'],
    'client'   => ['Clients',     'home'],
    'provider' => ['Job Seekers', 'briefcase'],
    'agent'    => ['Team',        'user-cog'],
    'admin'    => ['Admins',      'shield'],
  ];
  foreach($role_tabs as $key => [$label, $icon]):
    $cnt = $role_counts[$key] ?? 0;
    $active = $filter_role === $key;
  ?>
    <a href="?role=<?= $key ?>&status=<?= e($filter_status) ?><?= $search ? '&search='.urlencode($search) : '' ?>"
       class="stat-card <?= $active ? 'active-filter' : '' ?>">
      <div class="stat-num"><?= $cnt ?></div>
      <div class="stat-label" style="display:flex;align-items:center;gap:6px"><i data-lucide="<?= $icon ?>"></i> <?= $label ?></div>
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
    <i data-lucide="search"></i>
    <input type="hidden" name="role"   value="<?= e($filter_role) ?>"/>
    <input type="hidden" name="status" value="<?= e($filter_status) ?>"/>
    <input type="text" name="search" placeholder="Search name, email..."
      value="<?= e($search) ?>" autocomplete="off"/>
  </form>
</div>

<!-- TABLE -->
<div class="card">
  <div class="table-wrap">
    <table class="data-table">
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
          <tr><td colspan="8" class="empty-row"><i data-lucide="users"></i> No users found.</td></tr>
        <?php else: ?>
          <?php foreach($users as $u):
            $is_me = ($u['id'] == $admin_id);
          ?>
            <tr>
              <td class="id-cell"><?= $u['id'] ?></td>
              <td>
                <div class="cell-user">
                  <div class="cell-av av-<?= e($u['role']) ?>"><?= e(strtoupper(substr($u['name'],0,1))) ?></div>
                  <div>
                    <div class="cell-name">
                      <?= e($u['name']) ?>
                      <?php if($is_me): ?><span class="you-badge">You</span><?php endif; ?>
                    </div>
                    <div class="cell-sub"><?= e($u['email']) ?></div>
                  </div>
                </div>
              </td>
              <td class="muted-cell"><?= e($u['phone'] ?? '—') ?></td>
              <td>
                <?php if($is_me): ?>
                  <span class="role-badge rb-<?= e($u['role']) ?>"><?= roleLabel($u['role']) ?></span>
                <?php else: ?>
                  <div class="inline-assign">
                    <select class="mini-select" id="roleSelect<?= $u['id'] ?>">
                      <?php foreach(['client','provider','agent','admin'] as $r): ?>
                        <option value="<?= $r ?>" <?= $u['role']===$r ? 'selected':'' ?>><?= roleLabel($r) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn-mini" title="Apply role" onclick="changeRole(<?= $u['id'] ?>)"><i data-lucide="check"></i></button>
                  </div>
                <?php endif; ?>
              </td>
              <td class="num-strong text-navy"><?= $booking_counts[$u['id']] ?? 0 ?></td>
              <td>
                <span class="status-pill <?= $u['status']==='active' ? 's-approved' : 's-rejected' ?>">
                  <i data-lucide="<?= $u['status']==='active' ? 'check-circle-2' : 'ban' ?>"></i>
                  <?= $u['status']==='active' ? 'Active' : 'Blocked' ?>
                </span>
              </td>
              <td class="date-cell"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              <td>
                <?php if($is_me): ?>
                  <span class="muted-cell">Your account</span>
                <?php else: ?>
                  <div class="actions-cell">
                    <?php if($u['status']==='active'): ?>
                      <button class="act-btn act-block" onclick="quickAction('block',  <?= $u['id'] ?>)"><i data-lucide="ban"></i> Block</button>
                    <?php else: ?>
                      <button class="act-btn act-unblock" onclick="quickAction('unblock',<?= $u['id'] ?>)"><i data-lucide="check-circle-2"></i> Unblock</button>
                    <?php endif; ?>
                    <?php if($u['role'] !== 'admin'): ?>
                      <button class="act-btn act-delete" title="Delete user" onclick="askDelete(<?= $u['id'] ?>, '<?= e(addslashes($u['name'])) ?>')"><i data-lucide="trash-2"></i></button>
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

<p class="table-note">
  Showing <?= count($users) ?> user<?= count($users)!==1?'s':'' ?>
  <?= $search ? 'matching "'.e($search).'"' : '' ?>
</p>

<script>
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
<?php include '../includes/dash-end.php'; ?>
