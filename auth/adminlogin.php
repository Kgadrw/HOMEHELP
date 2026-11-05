<?php
require_once '../config/db.php';
require_once '../config/config.php';

$error = '';

// /admin-login (or ?next=/admin*) is admin-only — agents cannot use it to reach /agent
$reqPath   = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$nextRaw   = $_GET['next'] ?? $_POST['next'] ?? '';
$nextPath  = (is_string($nextRaw) && $nextRaw !== '') ? (parse_url($nextRaw, PHP_URL_PATH) ?: '') : '';
$adminOnly = str_contains($reqPath, 'admin-login')
    || (is_string($nextPath) && str_starts_with($nextPath, '/admin'));

if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'admin';
    if ($adminOnly && $role === 'admin') {
        redirect(BASE_URL . '/admin');
    }
    if (!$adminOnly && in_array($role, ['admin', 'agent'], true)) {
        redirect(BASE_URL . clean_redirect($role));
    }
    // Wrong role for this portal — clear and show the form
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $email = sanitize($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!$email || !$pass) {
        $error = 'Please fill in all fields.';
    } elseif (isLoginBlocked($email, $ip)) {
        $error = 'Too many failed attempts. Please wait 15 minutes.';
    } else {
        if ($adminOnly) {
            $stmt = $conn->prepare("
                SELECT * FROM users
                WHERE email = ? AND role = 'admin' AND status = 'active'
                LIMIT 1
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT * FROM users
                WHERE email = ? AND role IN ('admin','agent') AND status = 'active'
                LIMIT 1
            ");
        }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($pass, $user['password'])) {
            session_regenerate_id(true);
            clearLoginAttempts($email);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            $next     = $_GET['next'] ?? $_POST['next'] ?? '';
            $nextPath = (is_string($next) && $next !== '' && str_starts_with($next, '/') && !str_starts_with($next, '//'))
                ? (parse_url($next, PHP_URL_PATH) ?: '')
                : '';
            if ($user['role'] === 'admin') {
                if (is_string($nextPath) && str_starts_with($nextPath, '/admin')) {
                    redirect(BASE_URL . $next);
                }
                redirect(BASE_URL . '/admin');
            }
            if ($user['role'] === 'agent') {
                if (is_string($nextPath) && str_starts_with($nextPath, '/agent')) {
                    redirect(BASE_URL . $next);
                }
                redirect(BASE_URL . '/agent');
            }
            post_login_redirect($user['role']);
        } else {
            logLoginAttempt($email, $ip);
            $error = $adminOnly
                ? 'Invalid admin email or password. Use the administrator account (admin@homehelp.rw), not a team/agent account.'
                : 'Invalid email or password. This portal is for admin and agents only.';
        }
    }
}

$page_title = $adminOnly ? 'Admin Login | Home Help Rwanda' : 'Team Login | Home Help Rwanda';
$heading    = $adminOnly ? 'Admin sign in' : 'Admin / Team sign in';
$sub        = $adminOnly
    ? 'Admin portal — opens the Admin dashboard at /admin.'
    : 'Team portal — agents open /agent. Admins use Admin login for /admin.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<?php include __DIR__ . '/../includes/seo-meta.php'; ?>
<title><?= e($page_title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<style>
:root{
  --navy:#0D1B4B;
  --navy-mid:#152268;
  --navy-light:#2E5FC2;
  --line:#E6E2D8;
  --bg:#FAFAF8;
  --text:#111827;
  --muted:#6B7280;
  --white:#FFFFFF;
  --font:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font);background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
.auth-wrap{display:grid;grid-template-columns:1fr 1fr;min-height:100vh}

.auth-left{position:relative;overflow:hidden;background:var(--navy)}
.auth-left-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;display:block}
.back-home{
  position:absolute;top:28px;left:28px;z-index:2;
  display:inline-flex;align-items:center;gap:6px;
  font-size:0.875rem;font-weight:600;color:rgba(255,255,255,0.95);
  padding:8px 12px 8px 10px;border-radius:999px;
  background:rgba(13,27,75,0.4);backdrop-filter:blur(8px);
  transition:background .15s,color .15s;
}
.back-home i{width:16px;height:16px;stroke-width:2}
.back-home:hover{background:rgba(13,27,75,0.6);color:#fff}

.auth-right{display:flex;align-items:center;justify-content:center;padding:40px 32px;background:var(--bg)}
.auth-card{width:100%;max-width:380px}
.back-home-mobile{
  display:none;align-items:center;gap:6px;
  font-size:0.875rem;font-weight:500;color:var(--muted);
  margin:0 auto 20px;width:fit-content;transition:color .15s;
}
.back-home-mobile i{width:16px;height:16px;stroke-width:2}
.back-home-mobile:hover{color:var(--navy)}
.auth-brand{display:block;width:56px;height:56px;margin:0 auto 28px;line-height:0}
.auth-brand img{width:100%;height:100%;object-fit:contain;border-radius:50%}

.staff-badge{
  display:inline-flex;align-items:center;gap:6px;
  padding:5px 10px;border-radius:999px;margin:0 auto 14px;
  background:rgba(13,27,75,.06);color:var(--navy);
  font-size:.72rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;
}
.staff-badge i{width:13px;height:13px}
.badge-wrap{text-align:center}

.auth-card h1{
  font-size:1.75rem;font-weight:800;letter-spacing:-0.03em;
  color:var(--navy);margin-bottom:8px;line-height:1.2;text-align:center;
}
.auth-card .sub{font-size:0.95rem;color:var(--muted);margin-bottom:28px;line-height:1.5;text-align:center}

.alert{
  display:flex;align-items:flex-start;gap:10px;
  padding:12px 14px;margin-bottom:20px;border-radius:10px;
  background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C;
  font-size:0.875rem;line-height:1.45;
}
.alert i{width:18px;height:18px;flex-shrink:0;margin-top:1px}

.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:0.8125rem;font-weight:600;color:var(--text);margin-bottom:7px}
.input-wrap{position:relative}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);display:flex;pointer-events:none}
.input-icon i{width:18px;height:18px;stroke-width:2}
.form-group input{
  width:100%;padding:13px 44px;border:1px solid var(--line);border-radius:10px;
  font-family:var(--font);font-size:0.9375rem;color:var(--text);
  background:var(--white);outline:none;transition:border-color .15s,box-shadow .15s;
}
.form-group input:focus{border-color:var(--navy-light);box-shadow:0 0 0 3px rgba(46,95,194,.12)}
.form-group input.error-field{border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.1)}
.eye-toggle{
  position:absolute;right:10px;top:50%;transform:translateY(-50%);
  border:0;background:transparent;color:var(--muted);cursor:pointer;
  padding:6px;display:flex;border-radius:6px;
}
.eye-toggle:hover{color:var(--navy)}
.eye-toggle i{width:18px;height:18px;stroke-width:2}

.form-meta{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:4px 0 22px;flex-wrap:wrap}
.checkbox-label{display:flex;align-items:center;gap:8px;font-size:0.875rem;color:var(--muted);cursor:pointer}
.checkbox-label input{width:16px;height:16px;accent-color:var(--navy);cursor:pointer}
.forgot{font-size:0.875rem;font-weight:600;color:var(--navy-light)}
.forgot:hover{text-decoration:underline}

.btn-submit{
  width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;
  padding:14px 18px;border:0;border-radius:999px;cursor:pointer;
  background:var(--navy);color:var(--white);
  font-family:var(--font);font-size:0.9375rem;font-weight:700;
  transition:background .15s,transform .15s;
}
.btn-submit i{width:18px;height:18px;stroke-width:2.25}
.btn-submit:hover{background:var(--navy-mid)}
.btn-submit.loading{opacity:.7;pointer-events:none}

.auth-notice{
  display:flex;align-items:flex-start;gap:10px;margin-top:24px;
  padding:12px 14px;border-radius:10px;background:#F3F4F6;
  font-size:0.8125rem;color:var(--muted);line-height:1.55;
}
.auth-notice i{width:16px;height:16px;flex-shrink:0;margin-top:2px;stroke-width:2;color:var(--navy)}

@media(max-width:900px){
  .auth-wrap{grid-template-columns:1fr}
  .auth-left{display:none}
  .auth-right{padding:32px 20px}
  .back-home-mobile{display:inline-flex}
  .auth-brand{width:48px;height:48px;margin-bottom:24px}
}
</style>
</head>
<body>
<div class="auth-wrap">

  <div class="auth-left">
    <a href="<?= BASE_URL ?>" class="back-home">
      <i data-lucide="arrow-left"></i> Back to home
    </a>
    <img class="auth-left-img" src="../Image/slider/Slider_3.jpeg" alt=""/>
  </div>

  <div class="auth-right">
    <div class="auth-card">
      <a href="<?= BASE_URL ?>" class="back-home-mobile">
        <i data-lucide="arrow-left"></i> Back to home
      </a>
      <a href="<?= BASE_URL ?>" class="auth-brand" aria-label="Home Help">
        <img src="../Image/homehelp-logo.png" alt="Home Help"/>
      </a>

      <div class="badge-wrap">
        <span class="staff-badge"><i data-lucide="shield"></i> <?= $adminOnly ? 'Admin only' : 'Staff only' ?></span>
      </div>
      <h1><?= e($heading) ?></h1>
      <p class="sub"><?= $sub ?></p>

      <?php if ($error): ?>
        <div class="alert" role="alert">
          <i data-lucide="circle-alert"></i>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

        <form method="POST" id="loginForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
          <?php if (!empty($_GET['next'])): ?><input type="hidden" name="next" value="<?= e($_GET['next']) ?>"/><?php endif; ?>

          <div class="form-group">
            <label for="email">Staff email</label>
          <div class="input-wrap">
            <span class="input-icon" aria-hidden="true"><i data-lucide="mail"></i></span>
            <input type="email" id="email" name="email" placeholder="<?= $adminOnly ? 'admin@homehelp.rw' : 'staff@homehelp.rw' ?>"
              value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required/>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrap">
            <span class="input-icon" aria-hidden="true"><i data-lucide="lock"></i></span>
            <input type="password" id="password" name="password"
              placeholder="Your password" autocomplete="current-password" required/>
            <button type="button" class="eye-toggle" id="eyeBtn" aria-label="Show password">
              <i data-lucide="eye"></i>
            </button>
          </div>
        </div>

        <div class="form-meta">
          <label class="checkbox-label">
            <input type="checkbox" name="remember" value="1"/> Remember me
          </label>
          <a href="<?= BASE_URL ?>/reset-password" class="forgot">Forgot password?</a>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span>Sign in</span>
          <i data-lucide="arrow-right"></i>
        </button>
      </form>

      <div class="auth-notice">
        <i data-lucide="shield-check"></i>
        <span>Restricted area for HomeHelp staff only. Client and job seeker accounts cannot sign in here.</span>
      </div>
    </div>
  </div>
</div>
<script>
lucide.createIcons();
(function(){
  var pw = document.getElementById('password');
  var btn = document.getElementById('eyeBtn');
  btn.addEventListener('click', function(){
    var show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    btn.innerHTML = show ? '<i data-lucide="eye-off"></i>' : '<i data-lucide="eye"></i>';
    lucide.createIcons();
  });
  document.getElementById('loginForm').addEventListener('submit', function(e){
    var em = document.getElementById('email'), ok = true;
    [em, pw].forEach(function(el){ el.classList.remove('error-field'); });
    if (!em.value.trim() || !/\S+@\S+\.\S+/.test(em.value)) { em.classList.add('error-field'); ok = false; }
    if (!pw.value) { pw.classList.add('error-field'); ok = false; }
    if (!ok) { e.preventDefault(); return; }
    var submit = document.getElementById('submitBtn');
    submit.classList.add('loading');
    submit.innerHTML = '<span>Signing in...</span>';
  });
})();
</script>
</body>
</html>
