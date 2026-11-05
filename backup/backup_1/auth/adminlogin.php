<?php
require_once '../config/db.php';
require_once '../config/config.php';

$error = '';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'admin';
    redirect(BASE_URL . clean_redirect($role));
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
        // Only allow admin and agent roles
        $stmt = $conn->prepare("
            SELECT * FROM users
            WHERE email = ? AND role IN ('admin','agent') AND status = 'active'
            LIMIT 1
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($pass, $user['password'])) {
            session_regenerate_id(true);
            clearLoginAttempts($email);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $redirects = ['admin' => '/admin', 'agent' => '/agent'];
            redirect(BASE_URL . ($redirects[$user['role']] ?? '/admin'));
        } else {
            logLoginAttempt($email, $ip);
            $error = 'Invalid email or password. This portal is for admin and agents only.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Staff Login | Home Help Rwanda</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --navy:#0D1B4B;--navy-mid:#152268;--navy-light:#2E5FC2;--navy-soft:#D4DCF5;
  --cream:#FDFAF5;--cream-dark:#F2EDE3;
  --text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;--white:#FFFFFF;
  --shadow-sm:0 2px 12px rgba(13,27,75,0.08);
  --shadow-md:0 8px 32px rgba(13,27,75,0.14);
  --radius:16px;--radius-sm:10px;
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden}
a{text-decoration:none;color:inherit}
.auth-wrap{display:grid;grid-template-columns:1fr 1fr;min-height:100vh}

/* LEFT */
.auth-left{position:relative;background:var(--navy);display:flex;flex-direction:column;padding:48px;overflow:hidden}
.auth-left::before{content:'';position:absolute;inset:0;background-image:url('https://images.unsplash.com/photo-1497366216548-37526070297c?w=900&q=80&fit=crop');background-size:cover;background-position:center;opacity:0.1}
.auth-left::after{content:'';position:absolute;inset:0;background:linear-gradient(160deg,rgba(13,27,75,0.97) 0%,rgba(13,27,75,0.82) 60%,rgba(21,34,104,0.95) 100%)}
.auth-left-grid{position:absolute;inset:0;z-index:1;background-image:linear-gradient(rgba(255,255,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.04) 1px,transparent 1px);background-size:48px 48px}
.auth-left-content{position:relative;z-index:2;display:flex;flex-direction:column;height:100%}
.auth-logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.auth-logo img{width:44px;height:44px;object-fit:contain}
.auth-logo-text{font-family:var(--font-display);font-size:1.55rem;font-weight:700;color:var(--white)}
.auth-left-body{margin:auto 0;padding:32px 0}
.auth-left-tag{display:inline-flex;align-items:center;gap:8px;background:rgba(46,95,194,0.25);border:1px solid rgba(46,95,194,0.45);color:var(--navy-soft);font-size:0.72rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;padding:6px 14px;border-radius:99px;margin-bottom:20px}
.auth-left-title{font-family:var(--font-display);font-size:clamp(2.4rem,3.5vw,3.8rem);font-weight:700;color:var(--white);line-height:1.1;margin-bottom:18px}
.auth-left-title em{font-style:italic;color:var(--navy-soft);display:block}
.auth-left-desc{font-size:0.93rem;color:rgba(255,255,255,0.6);line-height:1.75;max-width:360px;margin-bottom:32px}
.access-list{display:flex;flex-direction:column;gap:14px}
.access-item{display:flex;align-items:center;gap:12px}
.access-icon{width:42px;height:42px;border-radius:12px;background:rgba(46,95,194,0.2);border:1px solid rgba(46,95,194,0.3);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.access-body strong{display:block;font-size:0.88rem;color:var(--white);font-weight:600;margin-bottom:2px}
.access-body span{font-size:0.78rem;color:rgba(255,255,255,0.5)}

/* RIGHT */
.auth-right{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 40px;overflow-y:auto}
.auth-card{width:100%;max-width:440px;animation:slideUp 0.55s ease both}

/* Staff badge */
.staff-badge{display:inline-flex;align-items:center;gap:10px;background:rgba(46,95,194,0.08);border:1.5px solid var(--navy-soft);border-radius:10px;padding:12px 16px;margin-bottom:24px;width:100%}
.staff-badge-icon{width:40px;height:40px;border-radius:10px;background:var(--navy);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.staff-badge-text{flex:1}
.staff-badge-title{font-size:0.88rem;font-weight:700;color:var(--navy);margin-bottom:2px}
.staff-badge-sub{font-size:0.74rem;color:var(--text-soft)}
.staff-roles{display:flex;gap:6px}
.role-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;font-size:0.7rem;font-weight:700}
.role-admin{background:#EDE9FE;color:#4F46E5}
.role-agent{background:#ECFDF5;color:#065F46}

.auth-card-title{font-family:var(--font-display);font-size:2.4rem;font-weight:700;color:var(--navy);line-height:1.1;margin-bottom:8px}
.auth-card-title em{font-style:italic;color:var(--navy-light)}
.auth-card-sub{font-size:0.9rem;color:var(--text-soft);margin-bottom:24px}
.auth-card-sub a{color:var(--navy-light);font-weight:600}
.auth-card-sub a:hover{text-decoration:underline}

/* Form */
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.form-group label{font-size:0.82rem;font-weight:600;color:var(--text-dark)}
.input-wrap{position:relative}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:0.95rem;pointer-events:none;z-index:1}
.form-group input{width:100%;padding:13px 14px 13px 42px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.9rem;color:var(--text-dark);background:var(--cream);transition:all 0.2s;outline:none}
.form-group input:focus{border-color:var(--navy-light);background:var(--white);box-shadow:0 0 0 3px rgba(46,95,194,0.12)}
.form-group input.error-field{border-color:#ef4444}
.eye-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;color:var(--text-soft);padding:0;line-height:1}
.eye-toggle:hover{color:var(--navy)}
.form-row-inline{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:8px;flex-wrap:wrap}
.checkbox-label{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.84rem;color:var(--text-mid)}
.checkbox-label input[type="checkbox"]{width:17px;height:17px;accent-color:var(--navy);cursor:pointer}
.forgot-link{font-size:0.84rem;color:var(--navy-light);font-weight:600}
.forgot-link:hover{text-decoration:underline}
.btn-submit{width:100%;padding:14px 20px;border-radius:var(--radius-sm);background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:0.95rem;font-weight:700;border:none;cursor:pointer;transition:all 0.3s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-submit:hover{background:var(--navy-mid);transform:translateY(-2px);box-shadow:var(--shadow-md)}
.btn-submit.loading{opacity:0.7;cursor:not-allowed;pointer-events:none}
.alert{padding:13px 16px;border-radius:var(--radius-sm);font-size:0.87rem;font-weight:500;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px}
.alert-error{background:#FEF2F2;border:1.5px solid #FECACA;color:#B91C1C}
.alert-icon{flex-shrink:0}

/* Security notice */
.security-notice{display:flex;align-items:flex-start;gap:10px;background:var(--cream);border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);padding:12px 14px;margin-top:20px;font-size:0.8rem;color:var(--text-soft);line-height:1.6}
.back-link{display:inline-flex;align-items:center;gap:6px;font-size:0.82rem;color:var(--text-soft);margin-top:16px;transition:color 0.2s}
.back-link:hover{color:var(--navy)}

@media(max-width:900px){.auth-wrap{grid-template-columns:1fr}.auth-left{display:none}.auth-right{padding:36px 20px;padding-top:52px}}
@keyframes slideUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
</style>
</head>
<body>
<div class="auth-wrap">

  <!-- LEFT -->
  <div class="auth-left">
    <div class="auth-left-grid"></div>
    <div class="auth-left-content">
      <a href="<?= BASE_URL ?>" class="auth-logo">
        <img src="../Image/icon/homehelp_logo_transparent_white.svg" alt="Home Help" onerror="this.style.display='none'"/>
        <span class="auth-logo-text">Home Help</span>
      </a>
      <div class="auth-left-body">
        <span class="auth-left-tag">🛡️ Staff Portal</span>
        <h2 class="auth-left-title">Staff<br/><em>Access.</em></h2>
        <p class="auth-left-desc">This portal is exclusively for Home Help staff members. Unauthorized access is strictly prohibited and monitored.</p>
      </div>
      <div class="access-list">
        <div class="access-item">
          <div class="access-icon">🛡️</div>
          <div class="access-body">
            <strong>Admin Access</strong>
            <span>Full platform management and oversight</span>
          </div>
        </div>
        <div class="access-item">
          <div class="access-icon">🎯</div>
          <div class="access-body">
            <strong>Team Access</strong>
            <span>Review bookings and job applications</span>
          </div>
        </div>
        <div class="access-item">
          <div class="access-icon">🔒</div>
          <div class="access-body">
            <strong>Secure &amp; Monitored</strong>
            <span>All login attempts are logged and tracked</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT -->
  <div class="auth-right">
    <div class="auth-card">

      <!-- Staff badge -->
      <div class="staff-badge">
        <div class="staff-badge-icon">🛡️</div>
        <div class="staff-badge-text">
          <div class="staff-badge-title">Staff Login Portal</div>
          <div class="staff-badge-sub">Restricted to authorized staff only</div>
        </div>
        <div class="staff-roles">
          <span class="role-pill role-admin">Admin</span>
          <span class="role-pill role-agent">Team</span>
        </div>
      </div>

      <h1 class="auth-card-title">Sign <em>In</em></h1>
      <p class="auth-card-sub">Use your staff credentials to access the dashboard.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><span class="alert-icon">⚠</span><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" id="loginForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
        <div class="form-group">
          <label for="email">Staff Email Address</label>
          <div class="input-wrap">
            <span class="input-icon">✉️</span>
            <input type="email" id="email" name="email" placeholder="staff@homehelp.rw"
              value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required/>
          </div>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" id="password" name="password"
              placeholder="Enter your password" autocomplete="current-password" required/>
            <button type="button" class="eye-toggle" onclick="togglePass('password',this)">👁</button>
          </div>
        </div>
        <div class="form-row-inline">
          <label class="checkbox-label">
            <input type="checkbox" name="remember" value="1"/> Remember me
          </label>
          <a href="<?= BASE_URL ?>/reset-password" class="forgot-link">Forgot password?</a>
        </div>
        <button type="submit" class="btn-submit" id="submitBtn">
          <span>Sign In →</span>
        </button>
      </form>

      <div class="security-notice">
        🔐 This is a restricted area. All login attempts are logged. If you are not an authorized staff member, please <a href="<?= BASE_URL ?>/login" style="color:var(--navy-light);font-weight:600">go to the client portal</a>.
      </div>

      <a href="<?= BASE_URL ?>" class="back-link">← Back to Home Help</a>
    </div>
  </div>
</div>

<script>
function togglePass(id,btn){var i=document.getElementById(id);i.type=i.type==='password'?'text':'password';btn.innerHTML=i.type==='password'?'👁':'🫣';}
document.getElementById('loginForm').addEventListener('submit',function(e){
  var em=document.getElementById('email'),pw=document.getElementById('password'),ok=true;
  [em,pw].forEach(function(el){el.classList.remove('error-field')});
  if(!em.value.trim()||!/\S+@\S+\.\S+/.test(em.value)){em.classList.add('error-field');ok=false;}
  if(!pw.value){pw.classList.add('error-field');ok=false;}
  if(!ok){e.preventDefault();return;}
  var btn=document.getElementById('submitBtn');
  btn.classList.add('loading');btn.innerHTML='<span>Signing in...</span>';
});
</script>
<?php include_once '../includes/footer.php'; ?>
</body>
</html>