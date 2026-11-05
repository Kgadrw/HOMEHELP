<?php
require_once '../config/db.php';
require_once '../config/config.php';

// Allow both client and provider
if(!isLoggedIn() || !in_array($_SESSION['user_role'] ?? '', ['client','provider'])) { redirect(BASE_URL . '/login'); }

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$error     = '';
$success   = '';

// ── FETCH CURRENT USER DATA ───────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// ── HANDLE PROFILE UPDATE ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCSRF();

    // ── UPDATE PROFILE INFO ───────────────────────────────────
    if ($_POST['action'] === 'update_profile') {
        $name  = sanitize($_POST['name']  ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');

        if (!$name || !$phone || !$email) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check email not taken by another user
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $chk->bind_param('si', $email, $user_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'This email is already used by another account.';
            } else {
                $upd = $conn->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=?");
                $upd->bind_param('sssi', $name, $email, $phone, $user_id);
                if ($upd->execute()) {
                    $_SESSION['user_name'] = $name;
                    $success = 'Profile updated successfully!';
                    $user['name']  = $name;
                    $user['email'] = $email;
                    $user['phone'] = $phone;
                } else {
                    $error = 'Something went wrong. Please try again.';
                }
            }
        }
    }

    // ── CHANGE PASSWORD ───────────────────────────────────────
    elseif ($_POST['action'] === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            $error = 'Please fill in all password fields.';
        } elseif (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hashed = password_hash($new, PASSWORD_BCRYPT);
            $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $upd->bind_param('si', $hashed, $user_id);
            if ($upd->execute()) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }

    // ── UPLOAD PHOTO ──────────────────────────────────────────
    elseif ($_POST['action'] === 'upload_photo') {
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please select a photo to upload.';
        } else {
            $file     = $_FILES['photo'];
            $allowed  = ['image/jpeg','image/png','image/webp','image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if (!in_array($file['type'], $allowed)) {
                $error = 'Only JPG, PNG, WebP or GIF images are allowed.';
            } elseif ($file['size'] > $max_size) {
                $error = 'Image must be smaller than 2MB.';
            } else {
                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                $upload_dir = dirname(__DIR__) . '/uploads/avatars/';

                // Create dir if not exists
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $dest = $upload_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Delete old photo if exists
                    if (!empty($user['photo']) && file_exists($upload_dir . $user['photo'])) {
                        unlink($upload_dir . $user['photo']);
                    }
                    $upd = $conn->prepare("UPDATE users SET photo=? WHERE id=?");
                    $upd->bind_param('si', $filename, $user_id);
                    $upd->execute();
                    $user['photo'] = $filename;
                    $success = 'Profile photo updated!';
                } else {
                    $error = 'Failed to upload photo. Please try again.';
                }
            }
        }
    }
}

$avatar_url  = !empty($user['photo'])
    ? BASE_URL . '/uploads/avatars/' . $user['photo']
    : '';

// ── ACCOUNT STATS ─────────────────────────────────────────────
$total_bookings    = $conn->query("SELECT COUNT(*) FROM bookings WHERE client_id={$user_id}")->fetch_row()[0];
$approved_bookings = $conn->query("SELECT COUNT(*) FROM bookings WHERE client_id={$user_id} AND status='approved'")->fetch_row()[0];
$pending_bookings  = $conn->query("SELECT COUNT(*) FROM bookings WHERE client_id={$user_id} AND status='pending'")->fetch_row()[0];

$is_provider = (($user['role'] ?? '') === 'provider');
$home_href   = BASE_URL . ($is_provider ? '/provider-dashboard' : '/dashboard');

$page_title = 'My Profile';
include '../includes/dash-head.php';
?>
<style>
.profile-wrap{max-width:860px}
.profile-hero{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 100%);border-radius:var(--radius);padding:24px 28px;display:flex;align-items:center;gap:22px;margin-bottom:18px}
.profile-avatar-wrap{position:relative;flex-shrink:0;cursor:pointer}
.profile-avatar{width:84px;height:84px;border-radius:50%;background:rgba(255,255,255,.12);border:3px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#fff;overflow:hidden}
.profile-avatar img{width:100%;height:100%;object-fit:cover}
.avatar-overlay{position:absolute;inset:0;border-radius:50%;background:rgba(10,31,107,.6);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s;color:#fff}
.avatar-overlay i{width:22px;height:22px}
.profile-avatar-wrap:hover .avatar-overlay{opacity:1}
.profile-info{flex:1;min-width:0}
.profile-name{font-size:1.4rem;font-weight:800;color:#fff;letter-spacing:-.02em;margin-bottom:4px}
.profile-email{display:flex;align-items:center;gap:7px;font-size:.88rem;color:rgba(255,255,255,.7);margin-bottom:12px;word-break:break-all}
.profile-email i{width:15px;height:15px;flex-shrink:0}
.profile-badges{display:flex;gap:8px;flex-wrap:wrap}
.profile-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:99px;padding:4px 12px;font-size:.75rem;color:rgba(255,255,255,.9);font-weight:600}
.profile-badge i{width:13px;height:13px}

.stats-grid.three{grid-template-columns:repeat(3,1fr)}
.stats-grid.three .stat-card{padding:16px 18px}
@media(max-width:768px){.stats-grid.three{grid-template-columns:1fr}}

.tabs{display:flex;gap:4px;background:var(--white);border:1px solid var(--line);border-radius:var(--radius);padding:5px;margin-bottom:18px}
.tab{flex:1;display:flex;align-items:center;justify-content:center;gap:7px;padding:10px 14px;border-radius:var(--radius-sm);font-size:.85rem;font-weight:600;color:var(--muted);cursor:pointer;transition:all .15s;border:none;background:transparent;font-family:var(--font)}
.tab i{width:16px;height:16px}
.tab:hover{color:var(--navy);background:var(--bg)}
.tab.active{background:var(--navy);color:#fff}
.tab-panel{display:none}
.tab-panel.active{display:block}

.form-card{background:var(--white);border:1px solid var(--line);border-radius:var(--radius);padding:26px}
.form-card-title{font-size:1.05rem;font-weight:800;color:var(--navy);letter-spacing:-.01em;margin-bottom:4px}
.form-card-sub{font-size:.84rem;color:var(--muted);margin-bottom:22px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.form-group label .req{color:var(--danger);margin-left:2px}
.input-wrap{position:relative}
.input-wrap .input-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--soft);pointer-events:none;z-index:1}
.input-wrap input{padding-left:40px}
.input-wrap input.has-eye{padding-right:42px}
.eye-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--soft);padding:4px;display:flex}
.eye-toggle:hover{color:var(--navy)}
.eye-toggle i{width:17px;height:17px}

.strength-bar{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:8px}
.strength-seg{height:3px;border-radius:2px;background:var(--line);transition:background .3s}
.strength-seg.weak{background:#ef4444}.strength-seg.fair{background:#f59e0b}.strength-seg.good{background:#22c55e}
.strength-text{display:block;font-size:.73rem;color:var(--soft);margin-top:4px;min-height:1em}

.form-actions{display:flex;align-items:center;gap:12px;margin-top:22px;flex-wrap:wrap}
.btn-save{gap:8px}
.btn-save i{width:16px;height:16px}
.btn-save.loading{opacity:.7;cursor:not-allowed;pointer-events:none}

.photo-upload-area{border:2px dashed var(--line);border-radius:var(--radius);padding:40px 24px;text-align:center;cursor:pointer;transition:all .2s;background:var(--bg);position:relative}
.photo-upload-area:hover,.photo-upload-area.dragover{border-color:var(--navy-light);background:var(--navy-soft)}
.photo-upload-area input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;padding:0;border:0}
.upload-icon{width:46px;height:46px;border-radius:12px;background:var(--navy-soft);color:var(--navy);display:flex;align-items:center;justify-content:center;margin:0 auto 12px}
.upload-icon i{width:22px;height:22px}
.upload-title{font-size:1rem;font-weight:800;color:var(--navy);margin-bottom:4px}
.upload-sub{font-size:.84rem;color:var(--muted)}
.upload-limit{font-size:.76rem;color:var(--soft);margin-top:6px}
.photo-preview{margin-top:18px;display:none}
.photo-preview img{width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--navy-soft);margin:0 auto}
.photo-preview p{text-align:center;font-size:.82rem;color:var(--muted);margin-top:8px}
.current-photo{display:flex;align-items:center;gap:16px;background:var(--bg);border:1px solid var(--line);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:18px}
.current-photo img,.current-photo .current-initials{width:54px;height:54px;border-radius:50%;object-fit:cover;flex-shrink:0}
.current-initials{background:var(--navy);display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:800;color:#fff}
.current-photo-info strong{display:block;font-size:.88rem;font-weight:700;color:var(--navy);margin-bottom:2px}
.current-photo-info span{font-size:.78rem;color:var(--muted)}

@media(max-width:768px){
  .form-row{grid-template-columns:1fr;gap:0}
  .profile-hero{flex-direction:column;text-align:center}
  .profile-badges,.profile-email{justify-content:center}
  .tabs{flex-wrap:wrap}
}
</style>
<?php
echo '</head><body>';
$dash_role    = $is_provider ? 'provider' : 'client';
$dash_page    = 'profile';
$page_heading = 'My Profile';
$user_name    = $_SESSION['user_name'];
$topbar_action_href  = $home_href;
$topbar_action_label = 'Dashboard';
$topbar_action_icon  = 'arrow-left';
include '../includes/dash-sidebar.php';
?>
      <div class="profile-wrap">

      <!-- ALERTS -->
      <?php if($error): ?>
        <div class="flash flash-error"><i data-lucide="triangle-alert"></i><span><?= e($error) ?></span></div>
      <?php endif; ?>
      <?php if($success): ?>
        <div class="flash flash-success"><i data-lucide="circle-check"></i><span><?= e($success) ?></span></div>
      <?php endif; ?>

      <!-- PROFILE HERO -->
      <div class="profile-hero">
        <div class="profile-avatar-wrap" onclick="switchTab('photo')" title="Change photo">
          <div class="profile-avatar">
            <?php if($avatar_url): ?>
              <img src="<?= $avatar_url ?>" alt="<?= e($user['name']) ?>"/>
            <?php else: ?>
              <?= strtoupper(substr($user['name'],0,1)) ?>
            <?php endif; ?>
          </div>
          <div class="avatar-overlay"><i data-lucide="camera"></i></div>
        </div>
        <div class="profile-info">
          <div class="profile-name"><?= e($user['name']) ?></div>
          <div class="profile-email"><i data-lucide="mail"></i><span><?= e($user['email']) ?></span></div>
          <div class="profile-badges">
            <span class="profile-badge"><i data-lucide="<?= $is_provider ? 'briefcase' : 'house' ?>"></i> <?= $is_provider ? 'Job Seeker' : 'Client' ?></span>
            <span class="profile-badge"><i data-lucide="map-pin"></i> Rwanda</span>
            <span class="profile-badge"><i data-lucide="calendar"></i> Joined <?= date('M Y', strtotime($user['created_at'])) ?></span>
          </div>
        </div>
      </div>

      <!-- STATS -->
      <div class="stats-grid three">
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon"><i data-lucide="layers"></i></div></div>
          <div class="stat-num"><?= $total_bookings ?></div>
          <div class="stat-label">Total Bookings</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon"><i data-lucide="circle-check"></i></div></div>
          <div class="stat-num"><?= $approved_bookings ?></div>
          <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon"><i data-lucide="clock"></i></div></div>
          <div class="stat-num"><?= $pending_bookings ?></div>
          <div class="stat-label">Pending</div>
        </div>
      </div>

      <!-- TABS -->
      <div class="tabs">
        <button type="button" class="tab active" onclick="switchTab('info')"     id="tab-info"><i data-lucide="user"></i> Personal Info</button>
        <button type="button" class="tab"        onclick="switchTab('password')" id="tab-password"><i data-lucide="lock"></i> Change Password</button>
        <button type="button" class="tab"        onclick="switchTab('photo')"    id="tab-photo"><i data-lucide="camera"></i> Profile Photo</button>
      </div>

      <!-- TAB: PERSONAL INFO -->
      <div class="tab-panel active" id="panel-info">
        <div class="form-card">
          <div class="form-card-title">Personal Information</div>
          <div class="form-card-sub">Update your name, email address and phone number.</div>
          <form method="POST" id="profileForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
            <input type="hidden" name="action" value="update_profile"/>
            <div class="form-group">
              <label for="name">Full Name <span class="req">*</span></label>
              <div class="input-wrap">
                <i data-lucide="user" class="input-icon"></i>
                <input type="text" id="name" name="name"
                  value="<?= e($user['name']) ?>" placeholder="Your full name" required/>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="email">Email Address <span class="req">*</span></label>
                <div class="input-wrap">
                  <i data-lucide="mail" class="input-icon"></i>
                  <input type="email" id="email" name="email"
                    value="<?= e($user['email']) ?>" placeholder="you@example.com" required/>
                </div>
              </div>
              <div class="form-group">
                <label for="phone">WhatsApp / Phone <span class="req">*</span></label>
                <div class="input-wrap">
                  <i data-lucide="phone" class="input-icon"></i>
                  <input type="tel" id="phone" name="phone"
                    value="<?= e($user['phone'] ?? '') ?>" placeholder="+250 7XX XXX XXX" required/>
                </div>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-submit btn-save" id="saveProfileBtn">
                <span id="saveProfileText">Save Changes</span> <i data-lucide="check"></i>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- TAB: CHANGE PASSWORD -->
      <div class="tab-panel" id="panel-password">
        <div class="form-card">
          <div class="form-card-title">Change Password</div>
          <div class="form-card-sub">Choose a strong password with at least 8 characters.</div>
          <form method="POST" id="passwordForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
            <input type="hidden" name="action" value="change_password"/>
            <div class="form-group">
              <label for="current_password">Current Password <span class="req">*</span></label>
              <div class="input-wrap">
                <i data-lucide="lock" class="input-icon"></i>
                <input type="password" id="current_password" name="current_password" class="has-eye"
                  placeholder="Enter your current password" required/>
                <button type="button" class="eye-toggle" onclick="togglePass('current_password',this)" aria-label="Show password"><i data-lucide="eye"></i></button>
              </div>
            </div>
            <div class="form-group">
              <label for="new_password">New Password <span class="req">*</span></label>
              <div class="input-wrap">
                <i data-lucide="key-round" class="input-icon"></i>
                <input type="password" id="new_password" name="new_password" class="has-eye"
                  placeholder="Min. 8 characters" required
                  oninput="checkStrength(this.value)"/>
                <button type="button" class="eye-toggle" onclick="togglePass('new_password',this)" aria-label="Show password"><i data-lucide="eye"></i></button>
              </div>
              <div class="strength-bar">
                <div class="strength-seg" id="s1"></div>
                <div class="strength-seg" id="s2"></div>
                <div class="strength-seg" id="s3"></div>
                <div class="strength-seg" id="s4"></div>
              </div>
              <span class="strength-text" id="strengthText"></span>
            </div>
            <div class="form-group">
              <label for="confirm_password">Confirm New Password <span class="req">*</span></label>
              <div class="input-wrap">
                <i data-lucide="key-round" class="input-icon"></i>
                <input type="password" id="confirm_password" name="confirm_password" class="has-eye"
                  placeholder="Repeat new password" required/>
                <button type="button" class="eye-toggle" onclick="togglePass('confirm_password',this)" aria-label="Show password"><i data-lucide="eye"></i></button>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-submit btn-save" id="savePassBtn">
                <span id="savePassText">Change Password</span> <i data-lucide="check"></i>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- TAB: PROFILE PHOTO -->
      <div class="tab-panel" id="panel-photo">
        <div class="form-card">
          <div class="form-card-title">Profile Photo</div>
          <div class="form-card-sub">Upload a clear photo so agents and workers can recognize you.</div>

          <div class="current-photo">
            <?php if($avatar_url): ?>
              <img src="<?= $avatar_url ?>" alt="Current photo"/>
            <?php else: ?>
              <div class="current-initials"><?= strtoupper(substr($user['name'],0,1)) ?></div>
            <?php endif; ?>
            <div class="current-photo-info">
              <strong>Current Photo</strong>
              <span><?= $avatar_url ? 'Custom photo uploaded' : 'Using initials avatar — upload a photo!' ?></span>
            </div>
          </div>

          <form method="POST" enctype="multipart/form-data" id="photoForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
            <input type="hidden" name="action" value="upload_photo"/>

            <div class="photo-upload-area" id="uploadArea">
              <input type="file" name="photo" accept="image/*" id="photoInput"
                onchange="previewPhoto(this)"/>
              <div class="upload-icon"><i data-lucide="upload"></i></div>
              <div class="upload-title">Click to upload or drag &amp; drop</div>
              <div class="upload-sub">JPG, PNG or WebP — clear face photo recommended</div>
              <div class="upload-limit">Maximum size: 2MB</div>
            </div>

            <div class="photo-preview" id="photoPreview">
              <img id="previewImg" src="" alt="Preview"/>
              <p id="previewName"></p>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn-submit btn-save" id="savePhotoBtn" style="display:none">
                <span id="savePhotoText">Upload Photo</span> <i data-lucide="upload"></i>
              </button>
            </div>
          </form>
        </div>
      </div>

      </div><!-- /profile-wrap -->

<script>
// Tabs
function switchTab(tab) {
  document.querySelectorAll('.tab').forEach(function(t){t.classList.remove('active');});
  document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active');});
  document.getElementById('tab-'+tab).classList.add('active');
  document.getElementById('panel-'+tab).classList.add('active');
}

// Auto-switch tab based on which form was submitted
<?php if($success || $error): ?>
  <?php
  $action = $_POST['action'] ?? '';
  $tab = $action === 'change_password' ? 'password' : ($action === 'upload_photo' ? 'photo' : 'info');
  ?>
  switchTab('<?= $tab ?>');
<?php endif; ?>

// Password toggle
function togglePass(id,btn){
  var i=document.getElementById(id);
  i.type=i.type==='password'?'text':'password';
  btn.innerHTML='<i data-lucide="'+(i.type==='password'?'eye':'eye-off')+'"></i>';
  if(window.lucide) lucide.createIcons();
}

// Password strength
function checkStrength(v){
  var segs=[1,2,3,4].map(function(n){return document.getElementById('s'+n)});
  var txt=document.getElementById('strengthText');
  segs.forEach(function(s){s.className='strength-seg'});
  if(!v){txt.textContent='';return;}
  var sc=0;
  if(v.length>=8)sc++;if(/[A-Z]/.test(v))sc++;if(/[0-9]/.test(v))sc++;if(/[^A-Za-z0-9]/.test(v))sc++;
  var cls=['','weak','weak','fair','good'];
  var lbl=['','Too short','Fair','Good','Strong'];
  for(var i=0;i<sc;i++)segs[i].classList.add(cls[sc]);
  txt.textContent=sc?'Strength: '+lbl[sc]:'';
  txt.style.color=sc<=1?'#ef4444':sc===2?'#f59e0b':'#10b981';
}

// Photo preview
function previewPhoto(input){
  if(!input.files||!input.files[0])return;
  var file=input.files[0];
  var reader=new FileReader();
  reader.onload=function(e){
    document.getElementById('previewImg').src=e.target.result;
    document.getElementById('previewName').textContent=file.name+' ('+Math.round(file.size/1024)+'KB)';
    document.getElementById('photoPreview').style.display='block';
    document.getElementById('savePhotoBtn').style.display='inline-flex';
  };
  reader.readAsDataURL(file);
}

// Drag & drop
var uploadArea=document.getElementById('uploadArea');
uploadArea.addEventListener('dragover',function(e){e.preventDefault();uploadArea.classList.add('dragover');});
uploadArea.addEventListener('dragleave',function(){uploadArea.classList.remove('dragover');});
uploadArea.addEventListener('drop',function(e){
  e.preventDefault();uploadArea.classList.remove('dragover');
  var files=e.dataTransfer.files;
  if(files.length){document.getElementById('photoInput').files=files;previewPhoto(document.getElementById('photoInput'));}
});

// Submit loading states
document.getElementById('profileForm').addEventListener('submit',function(){
  var btn=document.getElementById('saveProfileBtn');
  btn.classList.add('loading');
  document.getElementById('saveProfileText').textContent='Saving...';
});
document.getElementById('passwordForm').addEventListener('submit',function(){
  var btn=document.getElementById('savePassBtn');
  btn.classList.add('loading');
  document.getElementById('savePassText').textContent='Changing...';
});
document.getElementById('photoForm').addEventListener('submit',function(){
  var btn=document.getElementById('savePhotoBtn');
  btn.classList.add('loading');
  document.getElementById('savePhotoText').textContent='Uploading...';
});
</script>
<?php include '../includes/dash-end.php'; ?>
