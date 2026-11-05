<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/config.php';

requireLogin();

$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['admin', 'agent', 'client', 'provider'], true)) {
    redirect(BASE_URL . '/login');
}

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$error     = '';
$success   = '';
$active_tab = 'account';

$stmt = $conn->prepare("SELECT id, name, email, phone, password, role, status, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    redirect(BASE_URL . '/logout');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_account') {
        $active_tab = 'account';
        $name  = sanitize($_POST['name']  ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');

        if (!$name || !$phone || !$email) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $chk->bind_param('si', $email, $user_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'This email is already used by another account.';
            } else {
                $upd = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                $upd->bind_param('sssi', $name, $email, $phone, $user_id);
                if ($upd->execute()) {
                    $_SESSION['user_name'] = $name;
                    $user_name = $name;
                    $user['name']  = $name;
                    $user['email'] = $email;
                    $user['phone'] = $phone;
                    $success = 'Account details updated successfully.';
                } else {
                    $error = 'Could not update account. Please try again.';
                }
            }
        }
    } elseif ($action === 'change_password') {
        $active_tab = 'password';
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            $error = 'Please fill in all password fields.';
        } elseif (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif (password_verify($new, $user['password'])) {
            $error = 'New password must be different from your current password.';
        } else {
            $hashed = password_hash($new, PASSWORD_BCRYPT);
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->bind_param('si', $hashed, $user_id);
            if ($upd->execute()) {
                $user['password'] = $hashed;
                $success = 'Password reset successfully. Use your new password next time you sign in.';
            } else {
                $error = 'Could not reset password. Please try again.';
            }
        }
    }
}

$role_labels = [
    'admin'    => 'Admin',
    'agent'    => 'Team Member',
    'client'   => 'Client',
    'provider' => 'Job Seeker',
];
$dash_homes = [
    'admin'    => '/admin',
    'agent'    => '/agent',
    'client'   => '/dashboard',
    'provider' => '/provider-dashboard',
];

$page_title   = 'Settings';
$page_heading = 'Settings';
$dash_role    = $role;
$dash_page    = 'settings';
include __DIR__ . '/includes/dash-head.php';
?>
<style>
.settings-wrap{max-width:760px}
.settings-hero{display:flex;align-items:center;gap:16px;padding:18px 20px;border:1px solid var(--line);border-radius:var(--radius);background:#fff;margin-bottom:18px}
.settings-av{width:52px;height:52px;border-radius:50%;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.15rem;flex-shrink:0}
.settings-hero h2{margin:0;font-size:1.1rem;font-weight:800;color:var(--navy)}
.settings-hero p{margin:3px 0 0;font-size:.84rem;color:var(--muted)}
.settings-tabs{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap}
.settings-tab{display:inline-flex;align-items:center;gap:7px;padding:10px 14px;border-radius:var(--radius-sm);border:1px solid var(--line);background:#fff;color:var(--muted);font-family:var(--font);font-size:.84rem;font-weight:700;cursor:pointer}
.settings-tab i{width:16px;height:16px}
.settings-tab:hover{color:var(--navy);border-color:var(--navy-light)}
.settings-tab.active{background:var(--navy);border-color:var(--navy);color:#fff}
.settings-panel{display:none}
.settings-panel.active{display:block}
.settings-card{background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:22px 22px 20px}
.settings-card h3{margin:0 0 4px;font-size:1.02rem;font-weight:800;color:var(--navy)}
.settings-card .sub{margin:0 0 20px;font-size:.84rem;color:var(--muted)}
.settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.input-wrap{position:relative}
.input-wrap .input-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--soft);pointer-events:none}
.input-wrap input{padding-left:40px}
.input-wrap input.has-eye{padding-right:42px}
.eye-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--soft);padding:4px;display:flex}
.eye-toggle:hover{color:var(--navy)}
.eye-toggle i{width:17px;height:17px}
.form-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.hint-list{margin:0 0 16px;padding-left:18px;color:var(--muted);font-size:.82rem;line-height:1.6}
@media(max-width:720px){.settings-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include __DIR__ . '/includes/dash-sidebar.php'; ?>

<div class="settings-wrap">
  <?php if ($error): ?>
    <div class="flash flash-error"><i data-lucide="alert-triangle"></i><span><?= e($error) ?></span></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="flash flash-success"><i data-lucide="check-circle-2"></i><span><?= e($success) ?></span></div>
  <?php endif; ?>

  <div class="settings-hero">
    <div class="settings-av"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></div>
    <div>
      <h2><?= e($user['name']) ?></h2>
      <p><?= e($role_labels[$role] ?? ucfirst($role)) ?> · <?= e($user['email']) ?></p>
    </div>
  </div>

  <div class="settings-tabs">
    <button type="button" class="settings-tab <?= $active_tab === 'account' ? 'active' : '' ?>" onclick="switchSettings('account')" id="tab-account">
      <i data-lucide="user-cog"></i> Account
    </button>
    <button type="button" class="settings-tab <?= $active_tab === 'password' ? 'active' : '' ?>" onclick="switchSettings('password')" id="tab-password">
      <i data-lucide="key-round"></i> Reset password
    </button>
  </div>

  <div class="settings-panel <?= $active_tab === 'account' ? 'active' : '' ?>" id="panel-account">
    <div class="settings-card">
      <h3>Account credentials</h3>
      <p class="sub">Update the name, email and phone used to sign in and receive updates.</p>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
        <input type="hidden" name="action" value="update_account"/>
        <div class="form-group">
          <label for="name">Full name <span class="req">*</span></label>
          <div class="input-wrap">
            <i data-lucide="user" class="input-icon"></i>
            <input type="text" id="name" name="name" value="<?= e($user['name']) ?>" required/>
          </div>
        </div>
        <div class="settings-grid">
          <div class="form-group">
            <label for="email">Email <span class="req">*</span></label>
            <div class="input-wrap">
              <i data-lucide="mail" class="input-icon"></i>
              <input type="email" id="email" name="email" value="<?= e($user['email']) ?>" required/>
            </div>
          </div>
          <div class="form-group">
            <label for="phone">Phone / WhatsApp <span class="req">*</span></label>
            <div class="input-wrap">
              <i data-lucide="phone" class="input-icon"></i>
              <input type="tel" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>" required/>
            </div>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-navy"><i data-lucide="save"></i> Save changes</button>
          <a href="<?= BASE_URL . ($dash_homes[$role] ?? '/') ?>" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>
  </div>

  <div class="settings-panel <?= $active_tab === 'password' ? 'active' : '' ?>" id="panel-password">
    <div class="settings-card">
      <h3>Reset password</h3>
      <p class="sub">Choose a new password for this account. You’ll need your current password to confirm.</p>
      <ul class="hint-list">
        <li>At least 8 characters</li>
        <li>Must be different from your current password</li>
      </ul>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
        <input type="hidden" name="action" value="change_password"/>
        <div class="form-group">
          <label for="current_password">Current password <span class="req">*</span></label>
          <div class="input-wrap">
            <i data-lucide="lock" class="input-icon"></i>
            <input class="has-eye" type="password" id="current_password" name="current_password" required/>
            <button type="button" class="eye-toggle" onclick="toggleEye('current_password', this)" aria-label="Show password"><i data-lucide="eye"></i></button>
          </div>
        </div>
        <div class="settings-grid">
          <div class="form-group">
            <label for="new_password">New password <span class="req">*</span></label>
            <div class="input-wrap">
              <i data-lucide="key-round" class="input-icon"></i>
              <input class="has-eye" type="password" id="new_password" name="new_password" minlength="8" required/>
              <button type="button" class="eye-toggle" onclick="toggleEye('new_password', this)" aria-label="Show password"><i data-lucide="eye"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label for="confirm_password">Confirm new password <span class="req">*</span></label>
            <div class="input-wrap">
              <i data-lucide="key-round" class="input-icon"></i>
              <input class="has-eye" type="password" id="confirm_password" name="confirm_password" minlength="8" required/>
              <button type="button" class="eye-toggle" onclick="toggleEye('confirm_password', this)" aria-label="Show password"><i data-lucide="eye"></i></button>
            </div>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-navy"><i data-lucide="refresh-cw"></i> Reset password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function switchSettings(tab){
  document.querySelectorAll('.settings-panel').forEach(function(p){ p.classList.remove('active'); });
  document.querySelectorAll('.settings-tab').forEach(function(t){ t.classList.remove('active'); });
  document.getElementById('panel-' + tab).classList.add('active');
  document.getElementById('tab-' + tab).classList.add('active');
}
function toggleEye(id, btn){
  var input = document.getElementById(id);
  var show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btn.innerHTML = show ? '<i data-lucide="eye-off"></i>' : '<i data-lucide="eye"></i>';
  if (window.lucide) lucide.createIcons();
}
</script>
<?php include __DIR__ . '/includes/dash-end.php'; ?>
