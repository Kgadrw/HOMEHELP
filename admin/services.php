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
        $icon        = serviceLucideIcon(sanitize($_POST['icon'] ?? ''), $name);

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
        $icon        = serviceLucideIcon(sanitize($_POST['icon'] ?? ''), $name);

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

$lucide_options = serviceIconOptions();
$default_icon   = 'sparkles';

// ── SHELL ─────────────────────────────────────────────────────
$page_title   = 'Services';
$page_heading = 'Services';
$dash_role    = 'admin';
$dash_page    = 'services';
$user_name    = $admin_name;
include '../includes/dash-head.php';
?>
</head>
<body>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <h3>Delete Service?</h3>
    <p id="deleteModalMsg">This will permanently delete this service. This cannot be undone.</p>
    <div class="modal-actions">
      <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
      <button class="btn btn-danger" onclick="confirmDelete()"><i data-lucide="trash-2"></i> Delete</button>
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

<?php include '../includes/dash-sidebar.php'; ?>

<?php if($flash): ?>
  <div class="flash flash-<?= e($flash['type']) ?>">
    <i data-lucide="<?= $flash['type']==='success' ? 'check-circle-2' : 'alert-triangle' ?>"></i>
    <span><?= e($flash['message']) ?></span>
  </div>
<?php endif; ?>

<div class="page-head">
  <div>
    <h1>Manage Services</h1>
    <p>Create, edit, reorder and enable the services offered to clients.</p>
  </div>
  <button type="button" class="btn btn-navy" onclick="resetForm()"><i data-lucide="plus"></i> Add Service</button>
</div>

<div class="two-col side">

  <!-- LEFT: SERVICE TABLE -->
  <div>
    <div class="card">
      <div class="table-wrap">
        <table class="data-table" id="servicesList" style="min-width:640px">
          <thead>
            <tr>
              <th style="width:36px"></th>
              <th>Service</th>
              <th>Bookings</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($services)): ?>
              <tr><td colspan="5" class="empty-row"><i data-lucide="layers"></i> No services yet. Add your first service using the form.</td></tr>
            <?php else: ?>
              <?php foreach ($services as $svc):
                $svc_icon = serviceLucideIcon($svc['icon'] ?? '', $svc['name'] ?? '');
              ?>
                <tr class="service-row <?= $svc['status']==='inactive' ? 'row-inactive' : '' ?>" data-id="<?= $svc['id'] ?>" draggable="true">
                  <td><span class="drag-handle" title="Drag to reorder"><i data-lucide="grip-vertical"></i></span></td>
                  <td>
                    <div class="cell-user">
                      <div class="cell-av svc-av"><i data-lucide="<?= e($svc_icon) ?>"></i></div>
                      <div>
                        <div class="cell-name"><?= e($svc['name']) ?></div>
                        <div class="cell-sub trunc"><?= e($svc['description'] ?: 'No description') ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="num-strong text-navy"><?= (int)$svc['booking_count'] ?></td>
                  <td>
                    <span class="status-pill <?= $svc['status']==='active' ? 's-approved' : 's-rejected' ?>">
                      <i data-lucide="<?= $svc['status']==='active' ? 'check-circle-2' : 'pause-circle' ?>"></i>
                      <?= $svc['status']==='active' ? 'Active' : 'Inactive' ?>
                    </span>
                  </td>
                  <td>
                    <div class="actions-cell">
                      <button class="act-btn act-edit"
                        onclick="loadEdit(<?= $svc['id'] ?>, '<?= e(addslashes($svc['name'])) ?>', '<?= e(addslashes($svc['description']??'')) ?>', '<?= e($svc_icon) ?>')">
                        <i data-lucide="pencil"></i> Edit
                      </button>
                      <?php if ($svc['status']==='active'): ?>
                        <button class="act-btn act-toggle-on" onclick="toggleService(<?= $svc['id'] ?>)"><i data-lucide="pause"></i> Disable</button>
                      <?php else: ?>
                        <button class="act-btn act-toggle-off" onclick="toggleService(<?= $svc['id'] ?>)"><i data-lucide="play"></i> Enable</button>
                      <?php endif; ?>
                      <?php if ((int)$svc['booking_count'] === 0): ?>
                        <button class="act-btn act-delete" title="Delete service" onclick="askDelete(<?= $svc['id'] ?>, '<?= e(addslashes($svc['name'])) ?>')"><i data-lucide="trash-2"></i></button>
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
    <?php if (!empty($services)): ?>
      <p class="table-note">Drag rows to reorder services on the homepage · <?= count($services) ?> service<?= count($services)!==1?'s':'' ?></p>
    <?php endif; ?>
  </div>

  <!-- RIGHT: ADD / EDIT FORM -->
  <div class="card form-card" id="formCard">
    <div class="card-header">
      <span class="card-title" id="formCardTitle">Add New Service</span>
    </div>
    <div class="form-card-body">
      <form method="POST" id="serviceForm">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
        <input type="hidden" name="action"     id="formAction" value="create"/>
        <input type="hidden" name="service_id" id="formServiceId" value=""/>

        <div class="form-group">
          <label>Icon</label>
          <div class="icon-preview" id="iconPreview"><i data-lucide="<?= e($default_icon) ?>"></i></div>
          <input type="hidden" name="icon" id="iconInput" value="<?= e($default_icon) ?>"/>
          <div class="icon-picker" id="iconPicker">
            <?php foreach ($lucide_options as $key => $label): ?>
              <button type="button" class="icon-opt <?= $key === $default_icon ? 'selected' : '' ?>"
                data-icon="<?= e($key) ?>" title="<?= e($label) ?>"
                onclick="selectIcon('<?= e($key) ?>', this)">
                <i data-lucide="<?= e($key) ?>"></i>
              </button>
            <?php endforeach; ?>
          </div>
          <span class="hint-text">Pick a Lucide icon that matches the service.</span>
        </div>

        <div class="form-group">
          <label for="svcName">Service Name <span class="req">*</span></label>
          <input type="text" id="svcName" name="name" placeholder="e.g. Nanny" required/>
        </div>

        <div class="form-group">
          <label for="svcDesc">Description</label>
          <textarea id="svcDesc" name="description" placeholder="Brief description shown to clients..."></textarea>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-submit" id="submitBtn"><i data-lucide="plus"></i> <span>Create Service</span></button>
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

function selectIcon(name, btn){
  if(!name) return;
  document.getElementById('iconInput').value = name;
  var preview = document.getElementById('iconPreview');
  preview.innerHTML = '<i data-lucide="' + name + '"></i>';
  document.querySelectorAll('.icon-opt').forEach(function(b){ b.classList.remove('selected'); });
  if(btn) {
    btn.classList.add('selected');
  } else {
    var match = document.querySelector('.icon-opt[data-icon="' + name + '"]');
    if(match) match.classList.add('selected');
  }
  if (window.lucide) lucide.createIcons();
}

function loadEdit(id, name, desc, icon){
  document.getElementById('formAction').value    = 'edit';
  document.getElementById('formServiceId').value = id;
  document.getElementById('svcName').value        = name;
  document.getElementById('svcDesc').value        = desc;
  selectIcon(icon || '<?= e($default_icon) ?>', null);
  document.getElementById('formCardTitle').textContent = 'Edit Service';
  setSubmitLabel('save', 'Save Changes');
  document.getElementById('formCard').scrollIntoView({behavior:'smooth', block:'nearest'});
}

function resetForm(){
  document.getElementById('formAction').value    = 'create';
  document.getElementById('formServiceId').value = '';
  document.getElementById('serviceForm').reset();
  selectIcon('<?= e($default_icon) ?>', document.querySelector('.icon-opt[data-icon="<?= e($default_icon) ?>"]'));
  document.getElementById('formCardTitle').textContent = 'Add New Service';
  setSubmitLabel('plus', 'Create Service');
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

// Drag rows to reorder
var list = document.querySelector('#servicesList tbody');
if(list){
  var dragging = null;
  list.addEventListener('dragstart',function(e){
    dragging = e.target.closest('.service-row');
    if(!dragging) return;
    setTimeout(function(){ if(dragging) dragging.style.opacity='0.4'; },0);
  });
  list.addEventListener('dragend',function(){
    if(dragging) dragging.style.opacity='';
    dragging = null;
    saveOrder();
  });
  list.addEventListener('dragover',function(e){
    e.preventDefault();
    var over = e.target.closest('.service-row');
    if(over && over !== dragging){
      var rect = over.getBoundingClientRect();
      var mid  = rect.top + rect.height/2;
      if(e.clientY < mid) list.insertBefore(dragging, over);
      else list.insertBefore(dragging, over.nextSibling);
    }
  });

  function saveOrder(){
    var ids = Array.from(list.querySelectorAll('.service-row')).map(function(c){ return c.dataset.id; });
    fetch('', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'action=reorder&csrf_token=<?= generateCSRFToken() ?>&'+ids.map(function(id){return 'order[]='+id;}).join('&')
    });
  }
}
</script>
<?php include '../includes/dash-end.php'; ?>
