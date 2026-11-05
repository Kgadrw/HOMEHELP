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
<?php include __DIR__ . '/../includes/seo-meta.php'; ?>
<title>Reset Password | Home Help Rwanda</title>
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

.auth-card h1{
  font-size:1.75rem;font-weight:800;letter-spacing:-0.03em;
  color:var(--navy);margin-bottom:8px;line-height:1.2;
}
.auth-card .sub{font-size:0.95rem;color:var(--muted);margin-bottom:32px;line-height:1.5}
.auth-card .sub a{color:var(--navy-light);font-weight:600}
.auth-card .sub a:hover{text-decoration:underline}
.auth-card .sub strong{color:var(--text);font-weight:600}

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

.strength-bar{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:8px}
.strength-seg{height:3px;border-radius:2px;background:var(--line);transition:background .3s}
.strength-seg.weak{background:#ef4444}.strength-seg.fair{background:#f59e0b}.strength-seg.good{background:#22c55e}
.strength-text{display:block;font-size:0.75rem;color:var(--muted);margin-top:4px}

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

.state-icon{
  width:56px;height:56px;border-radius:50%;margin:0 auto 20px;
  display:flex;align-items:center;justify-content:center;
  background:#EEF2FF;color:var(--navy);
}
.state-icon.ok{background:#ECFDF5;color:#059669}
.state-icon i{width:26px;height:26px;stroke-width:2}
.state-center{text-align:center}
.sent-note{
  display:flex;align-items:flex-start;gap:10px;margin-top:20px;text-align:left;
  padding:12px 14px;border-radius:10px;background:#F3F4F6;
  font-size:0.8125rem;color:var(--muted);line-height:1.55;
}
.sent-note i{width:16px;height:16px;flex-shrink:0;margin-top:2px;stroke-width:2}
.sent-note a{color:var(--navy-light);font-weight:600}
.sent-note a:hover{text-decoration:underline}

.auth-links{margin-top:28px;padding-top:24px;border-top:1px solid var(--line)}
.auth-links a{
  display:flex;align-items:center;gap:8px;
  font-size:0.875rem;color:var(--muted);font-weight:500;
  margin-bottom:12px;transition:color .15s;
}
.auth-links a:last-child{margin-bottom:0}
.auth-links a:hover{color:var(--navy)}
.auth-links a i{width:16px;height:16px;stroke-width:2}

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

      <?php if ($step === 'request'): ?>
      <!-- STEP 1: Enter email -->
        <h1>Forgot password?</h1>
        <p class="sub">Enter the email linked to your Home Help account and we will send you a reset link.</p>

        <?php if($error): ?>
          <div class="alert" role="alert">
            <i data-lucide="circle-alert"></i>
            <span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <form method="POST" id="resetForm">
          <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
          <div class="form-group">
            <label for="email">Email</label>
            <div class="input-wrap">
              <span class="input-icon" aria-hidden="true"><i data-lucide="mail"></i></span>
              <input type="email" id="email" name="email" placeholder="you@example.com"
                value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required/>
            </div>
          </div>
          <button type="submit" class="btn-submit" id="submitBtn">
            <span id="btnText">Send reset link</span>
            <i data-lucide="arrow-right"></i>
          </button>
        </form>

        <div class="auth-links">
          <a href="<?= BASE_URL ?>/login"><i data-lucide="arrow-left"></i> Back to sign in</a>
        </div>

      <?php elseif ($step === 'sent'): ?>
      <!-- STEP 2: Email sent -->
        <div class="state-center">
          <div class="state-icon"><i data-lucide="mail-check"></i></div>
          <h1>Check your email</h1>
          <p class="sub">If an account exists for <strong><?= e($_POST['email'] ?? 'your email') ?></strong>, we have sent a password reset link. Check your inbox and spam folder.</p>
          <a href="<?= BASE_URL ?>/login" class="btn-submit">
            <i data-lucide="arrow-left"></i> <span>Back to sign in</span>
          </a>
          <div class="sent-note">
            <i data-lucide="clock"></i>
            <span>The link expires in <strong>1 hour</strong>. Did not receive it? Check spam or
            <a href="<?= BASE_URL ?>/reset-password">try again</a>.</span>
          </div>
        </div>

      <?php elseif ($step === 'reset'): ?>
      <!-- STEP 3: Set new password -->
        <h1>New password</h1>
        <p class="sub">Choose a strong password with at least 8 characters.</p>

        <?php if($error): ?>
          <div class="alert" role="alert">
            <i data-lucide="circle-alert"></i>
            <span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <form method="POST" id="newPassForm">
          <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
          <input type="hidden" name="token" value="<?= e($_GET['token'] ?? $token ?? '') ?>"/>
          <div class="form-group">
            <label for="new_password">New password</label>
            <div class="input-wrap">
              <span class="input-icon" aria-hidden="true"><i data-lucide="lock"></i></span>
              <input type="password" id="new_password" name="new_password"
                placeholder="Min. 8 characters" autocomplete="new-password" required
                oninput="checkStrength(this.value)"/>
              <button type="button" class="eye-toggle" onclick="togglePass('new_password',this)" aria-label="Show password">
                <i data-lucide="eye"></i>
              </button>
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
            <label for="confirm_password">Confirm new password</label>
            <div class="input-wrap">
              <span class="input-icon" aria-hidden="true"><i data-lucide="lock"></i></span>
              <input type="password" id="confirm_password" name="confirm_password"
                placeholder="Repeat your new password" autocomplete="new-password" required/>
              <button type="button" class="eye-toggle" onclick="togglePass('confirm_password',this)" aria-label="Show password">
                <i data-lucide="eye"></i>
              </button>
            </div>
          </div>
          <button type="submit" class="btn-submit" id="newPassBtn">
            <span id="newPassText">Set new password</span>
            <i data-lucide="arrow-right"></i>
          </button>
        </form>

      <?php elseif ($step === 'done'): ?>
      <!-- STEP 4: Done -->
        <div class="state-center">
          <div class="state-icon ok"><i data-lucide="check"></i></div>
          <h1>Password updated</h1>
          <p class="sub">Your password has been changed. You can now sign in with your new password.</p>
          <a href="<?= BASE_URL . ($reset_user_role === 'provider' ? '/worker-login' : '/login') ?>" class="btn-submit">
            <span>Sign in now</span> <i data-lucide="arrow-right"></i>
          </a>
        </div>

      <?php endif; ?>
    </div>
  </div>
</div>

<script>
lucide.createIcons();
function togglePass(id,btn){
  var i=document.getElementById(id);
  var show=i.type==='password';
  i.type=show?'text':'password';
  btn.setAttribute('aria-label',show?'Hide password':'Show password');
  btn.innerHTML=show?'<i data-lucide="eye-off"></i>':'<i data-lucide="eye"></i>';
  lucide.createIcons();
}
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
