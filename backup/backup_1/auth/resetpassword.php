<?php
require_once '../config/db.php';
require_once '../config/config.php';

// ── SMTP CONFIG ───────────────────────────────────────────────
define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_PORT',     587);
define('SMTP_USER',     'homehelpservicerw@gmail.com');
define('SMTP_PASS',     'oxpaodrrtmzavrir');
define('SMTP_FROM',     'homehelpservicerw@gmail.com');
define('SMTP_NAME',     'Home Help Rwanda');

// ── SIMPLE SMTP MAILER ────────────────────────────────────────
function sendResetEmail($to_email, $to_name, $reset_link) {
    $subject  = 'Reset Your Home Help Password';
    $message  = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'/></head>
<body style='margin:0;padding:0;background:#FDFAF5;font-family:Plus Jakarta Sans,sans-serif'>
<div style='max-width:520px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(13,27,75,0.1)'>
  <div style='background:linear-gradient(135deg,#0D1B4B,#152268);padding:32px 40px;text-align:center'>
    <h1 style='color:#fff;font-size:28px;margin:0;font-family:Georgia,serif'>Home Help</h1>
    <p style='color:rgba(255,255,255,0.6);font-size:13px;margin:6px 0 0'>Rwanda's Trusted Domestic Services</p>
  </div>
  <div style='padding:36px 40px'>
    <h2 style='color:#0D1B4B;font-size:22px;margin:0 0 12px;font-family:Georgia,serif'>Password Reset Request</h2>
    <p style='color:#4A4A4A;font-size:15px;line-height:1.7;margin:0 0 24px'>Hi <strong>{$to_name}</strong>,<br/><br/>
    We received a request to reset your Home Help password. Click the button below to choose a new password.</p>
    <div style='text-align:center;margin:28px 0'>
      <a href='{$reset_link}' style='background:#0D1B4B;color:#fff;padding:14px 32px;border-radius:99px;text-decoration:none;font-size:15px;font-weight:700;display:inline-block'>
        Reset My Password →
      </a>
    </div>
    <p style='color:#888;font-size:13px;line-height:1.6;margin:24px 0 0'>
      This link expires in <strong>1 hour</strong>. If you did not request a password reset, you can safely ignore this email — your password will not change.<br/><br/>
      If the button doesn't work, copy this link:<br/>
      <a href='{$reset_link}' style='color:#2E5FC2;word-break:break-all'>{$reset_link}</a>
    </p>
  </div>
  <div style='background:#F2EDE3;padding:20px 40px;text-align:center'>
    <p style='color:#888;font-size:12px;margin:0'>© " . date('Y') . " Home Help · Brilliant Elite Ltd · Kigali, Rwanda 🇷🇼</p>
  </div>
</div>
</body>
</html>";

    // Send via SMTP using raw socket
    $smtp = smtp_send(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM, SMTP_NAME, $to_email, $to_name, $subject, $message);
    return $smtp;
}

function smtp_send($host, $port, $user, $pass, $from, $from_name, $to, $to_name, $subject, $body) {
    $boundary = md5(time());

    // Connect
    $socket = fsockopen("tcp://{$host}", $port, $errno, $errstr, 30);
    if (!$socket) return false;

    $read = fgets($socket, 515);
    if (strpos($read, '220') === false) { fclose($socket); return false; }

    $steps = [
        "EHLO homehelp.rw\r\n"                                     => '250',
        "STARTTLS\r\n"                                              => '220',
    ];
    foreach ($steps as $cmd => $expect) {
        fputs($socket, $cmd);
        $r = fgets($socket, 515);
        // read multi-line
        while (substr($r, 3, 1) === '-') $r = fgets($socket, 515);
        if (strpos($r, $expect) === false) { fclose($socket); return false; }
    }

    // Upgrade to TLS
    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

    $steps2 = [
        "EHLO homehelp.rw\r\n"                                         => '250',
        "AUTH LOGIN\r\n"                                                => '334',
        base64_encode($user) . "\r\n"                                   => '334',
        base64_encode($pass) . "\r\n"                                   => '235',
        "MAIL FROM:<{$from}>\r\n"                                       => '250',
        "RCPT TO:<{$to}>\r\n"                                           => '250',
        "DATA\r\n"                                                      => '354',
    ];
    foreach ($steps2 as $cmd => $expect) {
        fputs($socket, $cmd);
        $r = fgets($socket, 515);
        while (substr($r, 3, 1) === '-') $r = fgets($socket, 515);
        if (strpos($r, $expect) === false) { fclose($socket); return false; }
    }

    // Send email headers + body
    $headers  = "From: {$from_name} <{$from}>\r\n";
    $headers .= "To: {$to_name} <{$to}>\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";
    $headers .= "\r\n";
    $headers .= chunk_split(base64_encode($body));
    $headers .= "\r\n.\r\n";

    fputs($socket, $headers);
    $r = fgets($socket, 515);
    fputs($socket, "QUIT\r\n");
    fclose($socket);

    return strpos($r, '250') !== false;
}

// ── PAGE LOGIC ────────────────────────────────────────────────
if (isLoggedIn()) {
    redirect(BASE_URL . '/dashboard');
}

$step    = 'request'; // request | sent | reset | done
$error   = '';
$success = '';

// ── STEP 3: User submitted new password ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    verifyCSRF();
    $token   = sanitize($_POST['token']        ?? '');
    $new     = $_POST['new_password']           ?? '';
    $confirm = $_POST['confirm_password']       ?? '';

    if (!$token || !$new || !$confirm) {
        $error = 'Please fill in all fields.';
        $step  = 'reset';
    } elseif (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
        $step  = 'reset';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match.';
        $step  = 'reset';
    } else {
        // Verify token
        $stmt = $conn->prepare("
            SELECT * FROM password_resets
            WHERE token = ? AND expires_at > NOW() AND used = 0
            LIMIT 1
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $reset = $stmt->get_result()->fetch_assoc();

        if (!$reset) {
            $error = 'This reset link is invalid or has expired. Please request a new one.';
            $step  = 'request';
        } else {
            // Update password
            $hashed = password_hash($new, PASSWORD_BCRYPT);
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $upd->bind_param('ss', $hashed, $reset['email']);
            $upd->execute();

            // Mark token as used
            $used_stmt = $conn->prepare("UPDATE password_resets SET used=1 WHERE token=?");
            $used_stmt->bind_param('s', $token);
            $used_stmt->execute();

            // Detect user role for correct redirect
            $role_stmt = $conn->prepare("SELECT role FROM users WHERE email = ? LIMIT 1");
            $role_stmt->bind_param('s', $reset['email']);
            $role_stmt->execute();
            $role_row = $role_stmt->get_result()->fetch_assoc();
            $reset_user_role = $role_row['role'] ?? 'client';

            $step = 'done';
        }
    }
}

// ── STEP 1: User submitted email ─────────────────────────────
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    verifyCSRF();
    $email = sanitize($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check user exists
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            // Delete old tokens for this email
            $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $del->bind_param('s', $email);
            $del->execute();

            // Create new token
            $token      = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $ins = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)");
            $ins->bind_param('sss', $email, $token, $expires_at);
            $ins->execute();

            // Send email
            $reset_link = BASE_URL . '/reset-password?token=' . $token;
            sendResetEmail($email, $user['name'], $reset_link);
        }

        // Always show "sent" — don't reveal if email exists
        $step = 'sent';
    }
}

// ── STEP 2: User clicked link with token ─────────────────────
elseif (isset($_GET['token'])) {
    $token = sanitize($_GET['token']);
    $stmt  = $conn->prepare("
        SELECT * FROM password_resets
        WHERE token = ? AND expires_at > NOW() AND used = 0
        LIMIT 1
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();

    if (!$reset) {
        $error = 'This reset link is invalid or has expired.';
        $step  = 'request';
    } else {
        $step = 'reset';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Reset Password | Home Help Rwanda</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --navy:#0D1B4B;--navy-mid:#152268;--navy-light:#2E5FC2;--navy-soft:#D4DCF5;
  --cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --shadow-md:0 8px 32px rgba(13,27,75,0.14);
  --radius:16px;--radius-sm:10px;
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font-body);background:var(--cream);color:var(--text-dark)}
a{text-decoration:none;color:inherit}
.auth-wrap{display:grid;grid-template-columns:1fr 1fr;min-height:100vh}

/* LEFT */
.auth-left{position:relative;background:var(--navy);display:flex;flex-direction:column;padding:48px;overflow:hidden}
.auth-left::before{content:'';position:absolute;inset:0;background-image:url('https://images.unsplash.com/photo-1560472355-536de3962603?w=900&q=80&fit=crop');background-size:cover;background-position:center;opacity:0.12}
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
.auth-left-desc{font-size:0.93rem;color:rgba(255,255,255,0.6);line-height:1.75;max-width:360px;margin-bottom:32px}
.steps-list{display:flex;flex-direction:column;gap:16px}
.step-item{display:flex;align-items:flex-start;gap:12px}
.step-num{width:28px;height:28px;border-radius:8px;background:var(--navy-light);color:var(--white);font-size:0.85rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.step-body strong{display:block;font-size:0.86rem;color:var(--white);font-weight:600;margin-bottom:2px}
.step-body span{font-size:0.78rem;color:rgba(255,255,255,0.5)}

/* RIGHT */
.auth-right{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 40px;overflow-y:auto}
.auth-card{width:100%;max-width:440px;animation:slideUp 0.55s ease both}
.auth-card-title{font-family:var(--font-display);font-size:2.4rem;font-weight:700;color:var(--navy);line-height:1.1;margin-bottom:8px}
.auth-card-title em{font-style:italic;color:var(--navy-light)}
.auth-card-sub{font-size:0.9rem;color:var(--text-soft);margin-bottom:24px;line-height:1.6}

/* Form */
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:18px}
.form-group label{font-size:0.82rem;font-weight:600;color:var(--text-dark)}
.input-wrap{position:relative}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:0.95rem;pointer-events:none;z-index:1}
.form-group input{width:100%;padding:13px 14px 13px 42px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.9rem;color:var(--text-dark);background:var(--cream);transition:all 0.2s;outline:none}
.form-group input:focus{border-color:var(--navy-light);background:var(--white);box-shadow:0 0 0 3px rgba(46,95,194,0.12)}
.form-group input.error-field{border-color:#ef4444}
.eye-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;color:var(--text-soft);padding:0;line-height:1}
.eye-toggle:hover{color:var(--navy)}

/* Strength */
.strength-bar{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:6px}
.strength-seg{height:3px;border-radius:2px;background:var(--cream-dark);transition:background 0.3s}
.strength-seg.weak{background:#ef4444}.strength-seg.fair{background:#f59e0b}.strength-seg.good{background:#22c55e}
.strength-text{font-size:0.73rem;color:var(--text-soft);margin-top:3px}

.btn-submit{width:100%;padding:14px 20px;border-radius:var(--radius-sm);background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:0.95rem;font-weight:700;border:none;cursor:pointer;transition:all 0.3s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-submit:hover{background:var(--navy-mid);transform:translateY(-2px);box-shadow:var(--shadow-md)}
.btn-submit.loading{opacity:0.7;cursor:not-allowed;pointer-events:none}
.alert{padding:13px 16px;border-radius:var(--radius-sm);font-size:0.87rem;font-weight:500;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px}
.alert-error{background:#FEF2F2;border:1.5px solid #FECACA;color:#B91C1C}
.alert-success{background:#ECFDF5;border:1.5px solid #BBF7D0;color:#065F46}
.back-link{display:inline-flex;align-items:center;gap:6px;font-size:0.82rem;color:var(--text-soft);margin-top:20px;transition:color 0.2s}
.back-link:hover{color:var(--navy)}

/* Sent state */
.sent-box{text-align:center;padding:16px 0}
.sent-icon{font-size:3.5rem;margin-bottom:16px}
.sent-title{font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--navy);margin-bottom:10px}
.sent-desc{font-size:0.92rem;color:var(--text-mid);line-height:1.75;margin-bottom:28px}
.sent-note{font-size:0.82rem;color:var(--text-soft);background:var(--cream);border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);padding:12px 16px;margin-top:16px}

/* Done state */
.done-box{text-align:center;padding:16px 0}
.done-icon{font-size:3.5rem;margin-bottom:16px}

@media(max-width:900px){.auth-wrap{grid-template-columns:1fr}.auth-left{display:none}.auth-right{padding:36px 20px}}
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
        <span class="auth-left-tag">🔒 Password Recovery</span>
        <h2 class="auth-left-title">Reset Your<br/><em>Password.</em></h2>
        <p class="auth-left-desc">Don't worry — it happens to everyone. We'll send you a secure link to reset your password in seconds.</p>
        <div class="steps-list">
          <div class="step-item">
            <div class="step-num">1</div>
            <div class="step-body"><strong>Enter your email address</strong><span>The one you registered with</span></div>
          </div>
          <div class="step-item">
            <div class="step-num">2</div>
            <div class="step-body"><strong>Check your inbox</strong><span>We'll send a reset link valid for 1 hour</span></div>
          </div>
          <div class="step-item">
            <div class="step-num">3</div>
            <div class="step-body"><strong>Set your new password</strong><span>Click the link and choose a new one</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT -->
  <div class="auth-right">
    <div class="auth-card">

      <?php if ($step === 'request'): ?>
      <!-- STEP 1: Enter email -->
        <?php if($error): ?>
          <div class="alert alert-error">⚠️ <?= e($error) ?></div>
        <?php endif; ?>
        <h1 class="auth-card-title">Forgot <em>Password?</em></h1>
        <p class="auth-card-sub">Enter the email address linked to your Home Help account and we'll send you a reset link.</p>
        <form method="POST" id="resetForm">
          <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
          <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-wrap">
              <span class="input-icon">✉️</span>
              <input type="email" id="email" name="email" placeholder="you@example.com"
                value="<?= e($_POST['email'] ?? '') ?>" required/>
            </div>
          </div>
          <button type="submit" class="btn-submit" id="submitBtn">
            <span id="btnText">Send Reset Link →</span>
          </button>
        </form>
        <a href="<?= BASE_URL ?>/login" class="back-link">← Back to Login</a>

      <?php elseif ($step === 'sent'): ?>
      <!-- STEP 2: Email sent -->
        <div class="sent-box">
          <div class="sent-icon">📧</div>
          <div class="sent-title">Check Your Email</div>
          <p class="sent-desc">If an account exists for <strong><?= e($_POST['email'] ?? 'your email') ?></strong>, we've sent a password reset link. Check your inbox (and spam folder just in case).</p>
          <button class="btn-submit" onclick="window.location='<?= BASE_URL ?>/login'">← Back to Login</button>
          <div class="sent-note">
            ⏰ The reset link expires in <strong>1 hour</strong>. Didn't receive it? Check your spam folder or
            <a href="<?= BASE_URL ?>/reset-password" style="color:var(--navy-light);font-weight:600">try again</a>.
          </div>
        </div>

      <?php elseif ($step === 'reset'): ?>
      <!-- STEP 3: Set new password -->
        <?php if($error): ?>
          <div class="alert alert-error">⚠️ <?= e($error) ?></div>
        <?php endif; ?>
        <h1 class="auth-card-title">New <em>Password</em></h1>
        <p class="auth-card-sub">Choose a strong password with at least 8 characters.</p>
        <form method="POST" id="newPassForm">
          <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
          <input type="hidden" name="token" value="<?= e($_GET['token'] ?? $token ?? '') ?>"/>
          <div class="form-group">
            <label>New Password</label>
            <div class="input-wrap">
              <span class="input-icon">🔒</span>
              <input type="password" id="new_password" name="new_password"
                placeholder="Min. 8 characters" required
                oninput="checkStrength(this.value)"/>
              <button type="button" class="eye-toggle" onclick="togglePass('new_password',this)">👁</button>
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
            <label>Confirm New Password</label>
            <div class="input-wrap">
              <span class="input-icon">🔒</span>
              <input type="password" id="confirm_password" name="confirm_password"
                placeholder="Repeat your new password" required/>
              <button type="button" class="eye-toggle" onclick="togglePass('confirm_password',this)">👁</button>
            </div>
          </div>
          <button type="submit" class="btn-submit" id="newPassBtn">
            <span id="newPassText">Set New Password →</span>
          </button>
        </form>

      <?php elseif ($step === 'done'): ?>
      <!-- STEP 4: Done! -->
        <div class="done-box">
          <div class="done-icon">✅</div>
          <h1 class="auth-card-title">Password <em>Updated!</em></h1>
          <p class="auth-card-sub">Your password has been changed successfully. You can now sign in with your new password.</p>
          <a href="<?= BASE_URL . ($reset_user_role === 'provider' ? '/worker-login' : '/login') ?>" class="btn-submit" style="text-decoration:none;margin-top:8px">
            Sign In Now →
          </a>
        </div>

      <?php endif; ?>

    </div>
  </div>
</div>

<script>
function togglePass(id,btn){var i=document.getElementById(id);i.type=i.type==='password'?'text':'password';btn.innerHTML=i.type==='password'?'👁':'🫣';}
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
  txt.style.color=sc<=1?'#ef4444':sc===2?'#f59e0b':'#22c55e';
}
var f=document.getElementById('resetForm');
if(f)f.addEventListener('submit',function(){
  var btn=document.getElementById('submitBtn');
  btn.classList.add('loading');
  document.getElementById('btnText').textContent='Sending...';
});
var f2=document.getElementById('newPassForm');
if(f2)f2.addEventListener('submit',function(){
  var btn=document.getElementById('newPassBtn');
  btn.classList.add('loading');
  document.getElementById('newPassText').textContent='Saving...';
});
</script>
</body>
</html>