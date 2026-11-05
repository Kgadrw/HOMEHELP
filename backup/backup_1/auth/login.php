<?php
require_once '../config/db.php';
require_once '../config/config.php';

$error = '';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'client';
    redirect(BASE_URL . clean_redirect($role));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $email = sanitize($_POST['email']    ?? '');
    $pass  = $_POST['password'] ?? '';
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!$email || !$pass) {
        $error = 'Please fill in all fields.';
    } elseif (isLoginBlocked($email, $ip)) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($pass, $user['password'])) {
            session_regenerate_id(true);
            clearLoginAttempts($email);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $redirects = ['admin'=>'/admin','agent'=>'/agent','client'=>'/dashboard','provider'=>'/provider-dashboard'];
            redirect(BASE_URL . ($redirects[$user['role']] ?? '/dashboard'));
        } else {
            logLoginAttempt($email, $ip);
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Client Login | Home Help Rwanda</title>
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

/* ── LEFT ── */
.auth-left{position:relative;background:var(--navy);display:flex;flex-direction:column;padding:48px;overflow:hidden}
.auth-left::before{content:'';position:absolute;inset:0;background-image:url('https://images.unsplash.com/photo-1587654780291-39c9404d746b?w=900&q=80&fit=crop');background-size:cover;background-position:center;opacity:0.12}
.auth-left::after{content:'';position:absolute;inset:0;background:linear-gradient(160deg,rgba(13,27,75,0.96) 0%,rgba(13,27,75,0.78) 60%,rgba(21,34,104,0.92) 100%)}
.auth-left-grid{position:absolute;inset:0;z-index:1;background-image:linear-gradient(rgba(255,255,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.04) 1px,transparent 1px);background-size:48px 48px}
.auth-left-content{position:relative;z-index:2;display:flex;flex-direction:column;height:100%}
.auth-logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.auth-logo img{width:44px;height:44px;object-fit:contain}
.auth-logo-text{font-family:var(--font-display);font-size:1.55rem;font-weight:700;color:var(--white)}
.auth-left-body{margin:auto 0;padding:32px 0}
.auth-left-tag{display:inline-flex;align-items:center;gap:8px;background:rgba(46,95,194,0.25);border:1px solid rgba(46,95,194,0.45);color:var(--navy-soft);font-size:0.72rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;padding:6px 14px;border-radius:99px;margin-bottom:20px}
.auth-left-title{font-family:var(--font-display);font-size:clamp(2.4rem,3.5vw,3.8rem);font-weight:700;color:var(--white);line-height:1.1;margin-bottom:18px}
.auth-left-title em{font-style:italic;color:var(--navy-soft);display:block}
.auth-left-desc{font-size:0.95rem;color:rgba(255,255,255,0.62);line-height:1.75;max-width:360px;margin-bottom:36px}
.trust-list{display:flex;flex-direction:column;gap:14px}
.trust-item{display:flex;align-items:center;gap:12px}
.trust-icon{width:38px;height:38px;border-radius:10px;background:rgba(46,95,194,0.2);border:1px solid rgba(46,95,194,0.3);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.trust-body strong{display:block;font-size:0.86rem;color:var(--white);font-weight:600;margin-bottom:1px}
.trust-body span{font-size:0.78rem;color:rgba(255,255,255,0.5)}

/* ── RIGHT ── */
.auth-right{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 40px;overflow-y:auto}
.auth-card{width:100%;max-width:440px;animation:slideUp 0.55s ease both}

/* Client badge */
.role-badge{display:inline-flex;align-items:center;gap:10px;background:rgba(46,95,194,0.08);border:1.5px solid var(--navy-soft);border-radius:10px;padding:10px 14px;margin-bottom:24px;width:100%}
.role-badge-icon{width:36px;height:36px;border-radius:9px;background:var(--navy-soft);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.role-badge-text{flex:1}
.role-badge-title{font-size:0.84rem;font-weight:700;color:var(--navy);margin-bottom:1px}
.role-badge-sub{font-size:0.74rem;color:var(--text-soft)}
.role-badge-switch{font-size:0.74rem;color:var(--navy-light);font-weight:600;white-space:nowrap;text-decoration:none}
.role-badge-switch:hover{text-decoration:underline}

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
.eye-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;color:var(--text-soft);transition:color 0.2s;padding:0;line-height:1}
.eye-toggle:hover{color:var(--navy)}
.form-row-inline{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:8px;flex-wrap:wrap}
.checkbox-label{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.84rem;color:var(--text-mid)}
.checkbox-label input[type="checkbox"]{width:17px;height:17px;accent-color:var(--navy);cursor:pointer;flex-shrink:0}
.forgot-link{font-size:0.84rem;color:var(--navy-light);font-weight:600}
.forgot-link:hover{text-decoration:underline}

/* Submit */
.btn-submit{width:100%;padding:14px 20px;border-radius:var(--radius-sm);background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:0.95rem;font-weight:700;border:none;cursor:pointer;transition:all 0.3s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-submit:hover{background:var(--navy-mid);transform:translateY(-2px);box-shadow:var(--shadow-md)}
.btn-submit.loading{opacity:0.7;cursor:not-allowed;pointer-events:none}

/* Alert */
.alert{padding:13px 16px;border-radius:var(--radius-sm);font-size:0.87rem;font-weight:500;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px}
.alert-error{background:#FEF2F2;border:1.5px solid #FECACA;color:#B91C1C}
.alert-icon{flex-shrink:0}

/* Worker CTA */
.divider{display:flex;align-items:center;gap:12px;margin:20px 0 14px}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--cream-dark)}
.divider span{font-size:0.78rem;color:var(--text-soft);white-space:nowrap}
.worker-cta{display:flex;align-items:center;justify-content:center;gap:10px;background:var(--cream);border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);padding:12px 16px;font-size:0.84rem;color:var(--text-mid);transition:all 0.2s;text-decoration:none}
.worker-cta:hover{border-color:var(--navy-light);background:var(--navy-soft)}
.worker-cta strong{color:var(--navy);font-weight:700}
.back-link{display:inline-flex;align-items:center;gap:6px;font-size:0.82rem;color:var(--text-soft);margin-top:20px;transition:color 0.2s}
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
        <span class="auth-left-tag">🏠 Client Portal</span>
        <h2 class="auth-left-title">Welcome<br/><em>Back.</em></h2>
        <p class="auth-left-desc">Sign in to manage your bookings, track your workers, and connect with verified domestic helpers across Rwanda.</p>
      </div>
      <div class="trust-list">
        <div class="trust-item">
          <div class="trust-icon">📋</div>
          <div class="trust-body"><strong>Manage Your Bookings</strong><span>Track every service request in real-time</span></div>
        </div>
        <div class="trust-item">
          <div class="trust-icon">📧</div>
          <div class="trust-body"><strong>Email Notifications</strong><span>Stay updated on every booking step</span></div>
        </div>
        <div class="trust-item">
          <div class="trust-icon">🔒</div>
          <div class="trust-body"><strong>Secure &amp; Private</strong><span>Your data is encrypted and protected</span></div>
        </div>
        <div class="trust-item">
          <div class="trust-icon">🇷🇼</div>
          <div class="trust-body"><strong>Made for Rwanda</strong><span>Built locally, trusted nationally</span></div>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT -->
  <div class="auth-right">
    <div class="auth-card">

      <!-- Client badge -->
      <div class="role-badge">
        <div class="role-badge-icon">🏠</div>
        <div class="role-badge-text">
          <div class="role-badge-title">Client Login</div>
          <div class="role-badge-sub">For households looking to hire domestic workers</div>
        </div>
        <a href="<?= BASE_URL ?>/worker-login" class="role-badge-switch">Job Seeker? →</a>
      </div>

      <h1 class="auth-card-title">Sign <em>In</em></h1>
      <p class="auth-card-sub">Don't have an account? <a href="<?= BASE_URL ?>/register">Create one free &rarr;</a></p>

      <?php if ($error): ?>
        <div class="alert alert-error"><span class="alert-icon">⚠</span><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" id="loginForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
        <div class="form-group">
          <label for="email">Email Address</label>
          <div class="input-wrap">
            <span class="input-icon">✉</span>
            <input type="email" id="email" name="email" placeholder="you@example.com"
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
          <span>Sign In</span><span>→</span>
        </button>
      </form>

      <div class="divider"><span>Are you a job seeker?</span></div>
      <a href="<?= BASE_URL ?>/worker-login" class="worker-cta">
        💼 <span>Sign in as a <strong>Job Seeker</strong> instead</span>
      </a>

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