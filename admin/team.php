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

    redirect(BASE_URL . '/admin/team');
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

// ── SHELL ─────────────────────────────────────────────────────
$page_title   = 'Team';
$page_heading = 'Team';
$dash_role    = 'admin';
$dash_page    = 'team';
$user_name    = $admin_name;
include '../includes/dash-head.php';
?>
</head>
<body>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <h3>Delete Team Member?</h3>
    <p id="deleteModalMsg">This will permanently delete this team member's account.</p>
    <div class="modal-actions">
      <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
      <button class="btn btn-danger" onclick="confirmDelete()"><i data-lucide="trash-2"></i> Delete</button>
    </div>
  </div>
</div>

<!-- Toggle & Delete forms -->
<form method="POST" id="toggleForm" style="display:none">
  <input type="hidden" name="csrf_token"    value="<?= generateCSRFToken() ?>"/>
  <input type="hidden" name="action"        value="toggle_agent"/>
  <input type="hidden" name="agent_user_id" id="toggleUserId" value=""/>
</form>
<form method="POST" id="deleteForm" style="display:none">
  <input type="hidden" name="csrf_token"    value="<?= generateCSRFToken() ?>"/>
  <input type="hidden" name="action"        value="delete_agent"/>
  <input type="hidden" name="agent_user_id" id="deleteUserId" value=""/>
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
    <h1>Manage Team</h1>
    <p>Team members review bookings and connect clients with workers.</p>
  </div>
  <button type="button" class="btn btn-navy" onclick="resetForm()"><i data-lucide="user-plus"></i> Add Team Member</button>
</div>

<div class="two-col side">

  <!-- LEFT: TEAM TABLE -->
  <div>
    <div class="card">
      <div class="table-wrap">
        <table class="data-table" style="min-width:760px">
          <thead>
            <tr>
              <th>Team Member</th>
              <th>Phone</th>
              <th>Services</th>
              <th>Bookings</th>
              <th>Status</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($agents)): ?>
              <tr><td colspan="7" class="empty-row"><i data-lucide="user-cog"></i> No team members yet. Create one using the form.</td></tr>
            <?php else: ?>
              <?php foreach ($agents as $ag):
                $is_active   = (int)($ag['is_active'] ?? 1);
                $svc_ids_arr = array_map('intval', array_filter(explode(',', $ag['assigned_service_ids'] ?? '')));
                $svc_names = [];
                foreach ($services as $svc) {
                  if (in_array((int)$svc['id'], $svc_ids_arr, true)) {
                    $svc_names[] = [
                      'icon' => serviceLucideIcon($svc['icon'] ?? '', $svc['name'] ?? ''),
                      'name' => $svc['name'],
                    ];
                  }
                }
              ?>
                <tr class="<?= $is_active ? '' : 'row-inactive' ?>">
                  <td>
                    <div class="cell-user">
                      <div class="cell-av av-agent"><?= e(strtoupper(substr($ag['name'], 0, 1))) ?></div>
                      <div>
                        <div class="cell-name"><?= e($ag['name']) ?></div>
                        <div class="cell-sub"><?= e($ag['email']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="muted-cell"><?= e($ag['whatsapp_number'] ?: $ag['phone'] ?: '—') ?></td>
                  <td>
                    <?php if (empty($svc_names)): ?>
                      <span class="muted-cell">None assigned</span>
                    <?php else: ?>
                      <div class="svc-tag-row">
                        <?php foreach ($svc_names as $sn): ?>
                          <span class="svc-tag"><i data-lucide="<?= e($sn['icon']) ?>"></i> <?= e($sn['name']) ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="cell-name"><?= (int)$ag['total_bookings'] ?> total</div>
                    <div class="cell-sub"><?= (int)$ag['pending_bookings'] ?> pending · <?= (int)$ag['completed_bookings'] ?> done</div>
                  </td>
                  <td>
                    <span class="status-pill <?= $is_active ? 's-approved' : 's-rejected' ?>">
                      <i data-lucide="<?= $is_active ? 'check-circle-2' : 'pause-circle' ?>"></i>
                      <?= $is_active ? 'Active' : 'Inactive' ?>
                    </span>
                  </td>
                  <td class="date-cell"><?= date('d M Y', strtotime($ag['created_at'])) ?></td>
                  <td>
                    <div class="actions-cell">
                      <button class="act-btn act-edit"
                        onclick="loadEdit(
                          <?= (int)$ag['user_id'] ?>,
                          '<?= e(addslashes($ag['name'])) ?>',
                          '<?= e(addslashes($ag['email'])) ?>',
                          '<?= e(addslashes($ag['whatsapp_number'] ?: $ag['phone'] ?: '')) ?>',
                          [<?= implode(',', array_filter(array_map('intval', explode(',', $ag['assigned_service_ids'] ?? '')))) ?>],
                          <?= $is_active ?>
                        )"><i data-lucide="pencil"></i> Edit</button>
                      <?php if ($is_active): ?>
                        <button class="act-btn act-toggle-on" onclick="toggleAgent(<?= (int)$ag['user_id'] ?>)"><i data-lucide="pause"></i></button>
                      <?php else: ?>
                        <button class="act-btn act-toggle-off" onclick="toggleAgent(<?= (int)$ag['user_id'] ?>)"><i data-lucide="play"></i></button>
                      <?php endif; ?>
                      <button class="act-btn act-delete" onclick="askDelete(<?= (int)$ag['user_id'] ?>, '<?= e(addslashes($ag['name'])) ?>')"><i data-lucide="trash-2"></i></button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if (!empty($agents)): ?>
      <p class="table-note">Showing <?= count($agents) ?> team member<?= count($agents)!==1?'s':'' ?></p>
    <?php endif; ?>
  </div>

  <!-- RIGHT: ADD / EDIT FORM -->
  <div class="card form-card" id="formCard">
    <div class="card-header dark">
      <span class="card-title" id="formCardTitle">Add New Team Member</span>
    </div>
    <div class="form-card-body">
      <form method="POST" id="agentForm">
        <input type="hidden" name="csrf_token"    value="<?= generateCSRFToken() ?>"/>
        <input type="hidden" name="action"        id="formAction" value="create_agent"/>
        <input type="hidden" name="agent_user_id" id="formUserId" value=""/>

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
          <span class="hint-text">Team member will use this to login at /staff-login</span>
        </div>

        <div class="form-group">
          <label>Assign Services <span class="opt">(select all that apply)</span></label>
          <?php if (empty($services)): ?>
            <p class="hint-text">No active services. Add services first.</p>
          <?php else: ?>
            <div class="services-check-grid" id="servicesGrid">
              <?php foreach ($services as $svc):
                $chk_icon = serviceLucideIcon($svc['icon'] ?? '', $svc['name'] ?? '');
              ?>
                <label class="svc-check-item" id="svcItem<?= $svc['id'] ?>">
                  <input type="checkbox" name="services[]"
                    value="<?= $svc['id'] ?>"
                    id="svcChk<?= $svc['id'] ?>"
                    onchange="updateCheckStyle(<?= $svc['id'] ?>)"/>
                  <span class="svc-check-label"><i data-lucide="<?= e($chk_icon) ?>"></i> <?= e($svc['name']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

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
          <button type="submit" class="btn-submit" id="submitBtn"><i data-lucide="user-plus"></i> <span>Create Team Member</span></button>
          <button type="button" class="btn btn-outline" onclick="resetForm()">Reset</button>
        </div>
      </form>
    </div>
  </div>

</div>

<script>
function setSubmitLabel(iconName, text){
  var b = document.getElementById('submitBtn');
  b.innerHTML = '<i data-lucide="' + iconName + '"></i> <span>' + text + '</span>';
  if (window.lucide) lucide.createIcons();
}

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
  document.getElementById('activeGroup').style.display   = 'flex';

  // Set active toggle
  var chkActive = document.getElementById('isActive');
  chkActive.checked = (isActive == 1);
  document.getElementById('toggleLabel').textContent = isActive ? 'Active' : 'Inactive';

  // Check assigned services
  document.querySelectorAll('[id^="svcChk"]').forEach(function(c){
    c.checked = serviceIds.includes(parseInt(c.value));
    var item = document.getElementById('svcItem'+c.value);
    if(item) item.classList.toggle('checked', c.checked);
  });

  document.getElementById('formCardTitle').textContent = 'Edit Team Member';
  setSubmitLabel('save', 'Save Changes');
  document.getElementById('formCard').scrollIntoView({behavior:'smooth',block:'nearest'});
}

// Reset to create mode
function resetForm(){
  document.getElementById('formAction').value = 'create_agent';
  document.getElementById('formUserId').value = '';
  document.getElementById('agentForm').reset();
  document.getElementById('emailGroup').style.display    = 'flex';
  document.getElementById('passwordGroup').style.display = 'flex';
  document.getElementById('activeGroup').style.display   = 'none';
  document.querySelectorAll('.svc-check-item').forEach(function(i){i.classList.remove('checked')});
  document.getElementById('formCardTitle').textContent = 'Add New Team Member';
  setSubmitLabel('user-plus', 'Create Team Member');
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
<?php include '../includes/dash-end.php'; ?>
