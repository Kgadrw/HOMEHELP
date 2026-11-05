<?php
require_once '../config/db.php';
require_once '../config/config.php';

$error = '';

if (isLoggedIn()) {
    redirect(BASE_URL . '/provider-dashboard');
}

// Role is ALWAYS provider on this page
$role = 'provider';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $name     = sanitize($_POST['name']             ?? '');
    $email    = sanitize($_POST['email']            ?? '');
    $phone    = sanitize($_POST['phone']            ?? '');
    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $terms    = $_POST['terms']            ?? '';
    $district = sanitize($_POST['district'] ?? '');

    if (!$name || !$email || !$phone || !$password || !$confirm) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!$terms) {
        $error = 'Please accept the Terms of Service to continue.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'This email is already registered. <a href="' . BASE_URL . '/worker-login">Sign in instead</a>.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt   = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?,?,?,?,?)");
            $stmt->bind_param('sssss', $name, $email, $phone, $hashed, $role);
            $stmt->execute();
            session_regenerate_id(true);
            $_SESSION['user_id']   = $conn->insert_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = $role;
            // Send straight to job application
            redirect(BASE_URL . '/apply');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Join as a Job Seeker | Home Help Rwanda</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --navy:#0D1B4B;--navy-mid:#152268;--navy-light:#2E5FC2;--navy-soft:#D4DCF5;
  --cream:#FDFAF5;--cream-dark:#F2EDE3;
  --text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;--white:#FFFFFF;
  --success:#10B981;
  --shadow-sm:0 2px 12px rgba(13,27,75,0.08);
  --shadow-md:0 8px 32px rgba(13,27,75,0.14);
  --radius:16px;--radius-sm:10px;
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden}
a{text-decoration:none;color:inherit}
.auth-wrap{display:grid;grid-template-columns:1fr 1fr;min-height:100vh}

/* ── LEFT ── */
.auth-left{position:relative;background:var(--navy);display:flex;flex-direction:column;padding:48px;overflow:hidden}
.auth-left::before{content:'';position:absolute;inset:0;background-image:url('https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=900&q=80&fit=crop');background-size:cover;background-position:center;opacity:0.12}
.auth-left::after{content:'';position:absolute;inset:0;background:linear-gradient(160deg,rgba(13,27,75,0.96) 0%,rgba(13,27,75,0.75) 60%,rgba(21,34,104,0.92) 100%)}
.auth-left-grid{position:absolute;inset:0;z-index:1;background-image:linear-gradient(rgba(255,255,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.04) 1px,transparent 1px);background-size:48px 48px}
.auth-left-content{position:relative;z-index:2;display:flex;flex-direction:column;height:100%}
.auth-logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.auth-logo img{width:44px;height:44px;object-fit:contain}
.auth-logo-text{font-family:var(--font-display);font-size:1.55rem;font-weight:700;color:var(--white)}
.auth-left-body{margin:auto 0;padding:32px 0}
.auth-left-tag{display:inline-flex;align-items:center;gap:8px;background:rgba(46,95,194,0.25);border:1px solid rgba(46,95,194,0.45);color:var(--navy-soft);font-size:0.72rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;padding:6px 14px;border-radius:99px;margin-bottom:20px}
.auth-left-title{font-family:var(--font-display);font-size:clamp(2.2rem,3.2vw,3.6rem);font-weight:700;color:var(--white);line-height:1.1;margin-bottom:18px}
.auth-left-title em{font-style:italic;color:var(--navy-soft);display:block}
.auth-left-desc{font-size:0.93rem;color:rgba(255,255,255,0.6);line-height:1.75;max-width:360px;margin-bottom:28px}
.steps-list{display:flex;flex-direction:column;gap:16px}
.step-item{display:flex;align-items:flex-start;gap:12px}
.step-num{width:28px;height:28px;border-radius:8px;background:var(--navy-light);color:var(--white);font-family:var(--font-display);font-size:0.88rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.step-body strong{display:block;font-size:0.86rem;color:var(--white);font-weight:600;margin-bottom:2px}
.step-body span{font-size:0.78rem;color:rgba(255,255,255,0.5)}
.benefits-list{display:flex;flex-direction:column;gap:8px;margin-top:28px}
.benefit-item{display:flex;align-items:center;gap:10px;font-size:0.83rem;color:rgba(255,255,255,0.7)}
.benefit-dot{width:20px;height:20px;border-radius:50%;background:rgba(46,95,194,0.3);border:1px solid rgba(46,95,194,0.4);display:flex;align-items:center;justify-content:center;font-size:0.65rem;color:var(--navy-soft);flex-shrink:0}

/* ── RIGHT ── */
.auth-right{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:48px 40px;overflow-y:auto}
.auth-card{width:100%;max-width:460px;padding-top:8px;animation:slideUp 0.55s ease both}

/* Role badge */
.role-badge{display:inline-flex;align-items:center;gap:10px;background:rgba(46,95,194,0.08);border:1.5px solid var(--navy-soft);border-radius:10px;padding:10px 14px;margin-bottom:22px;width:100%}
.role-badge-icon{width:36px;height:36px;border-radius:9px;background:var(--navy-soft);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.role-badge-text{flex:1}
.role-badge-title{font-size:0.84rem;font-weight:700;color:var(--navy);margin-bottom:1px}
.role-badge-sub{font-size:0.74rem;color:var(--text-soft)}
.role-badge-switch{font-size:0.74rem;color:var(--navy-light);font-weight:600;white-space:nowrap}
.role-badge-switch:hover{text-decoration:underline}

.auth-card-title{font-family:var(--font-display);font-size:2.2rem;font-weight:700;color:var(--navy);line-height:1.1;margin-bottom:8px}
.auth-card-title em{font-style:italic;color:var(--navy-light)}
.auth-card-sub{font-size:0.9rem;color:var(--text-soft);margin-bottom:20px}
.auth-card-sub a{color:var(--navy-light);font-weight:600}
.auth-card-sub a:hover{text-decoration:underline}

.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.form-group label{font-size:0.82rem;font-weight:600;color:var(--text-dark)}
.form-group label .req{color:#ef4444;margin-left:2px}
.input-wrap{position:relative}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:0.9rem;pointer-events:none;z-index:1}
.form-group input,.form-group select{width:100%;padding:12px 14px 12px 40px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.88rem;color:var(--text-dark);background:var(--cream);transition:all 0.2s;outline:none}
.form-group select{cursor:pointer;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center}
.form-group input:focus,.form-group select:focus{border-color:var(--navy-light);background:var(--white);box-shadow:0 0 0 3px rgba(46,95,194,0.12)}
.form-group input.error-field,.form-group select.error-field{border-color:#ef4444}
.eye-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;color:var(--text-soft);padding:0;line-height:1}
.eye-toggle:hover{color:var(--navy)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}

/* Strength */
.strength-bar{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:6px}
.strength-seg{height:3px;border-radius:2px;background:var(--cream-dark);transition:background 0.3s}
.strength-seg.weak{background:#ef4444}.strength-seg.fair{background:#f59e0b}.strength-seg.good{background:#10b981}
.strength-text{font-size:0.73rem;color:var(--text-soft);margin-top:3px}

/* Terms */
.terms-label{display:flex;align-items:flex-start;gap:9px;cursor:pointer;font-size:0.82rem;color:var(--text-mid);margin-bottom:14px;line-height:1.6}
.terms-label input[type="checkbox"]{width:17px;height:17px;accent-color:var(--navy);cursor:pointer;flex-shrink:0;margin-top:2px}
.terms-label a{color:var(--navy-light);font-weight:600}
.terms-label a:hover{text-decoration:underline}

.btn-submit{width:100%;padding:14px 20px;border-radius:var(--radius-sm);background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:0.95rem;font-weight:700;border:none;cursor:pointer;transition:all 0.3s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-submit:hover{background:var(--navy-mid);transform:translateY(-2px);box-shadow:var(--shadow-md)}
.btn-submit.loading{opacity:0.7;cursor:not-allowed;pointer-events:none}

.alert{padding:13px 16px;border-radius:var(--radius-sm);font-size:0.87rem;font-weight:500;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px}
.alert-error{background:#FEF2F2;border:1.5px solid #FECACA;color:#B91C1C}
.alert a{color:#B91C1C;font-weight:700;text-decoration:underline}
.alert-icon{flex-shrink:0}

.divider{display:flex;align-items:center;gap:12px;margin:18px 0 14px}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--cream-dark)}
.divider span{font-size:0.78rem;color:var(--text-soft);white-space:nowrap}
.client-cta{display:flex;align-items:center;justify-content:center;gap:10px;background:var(--cream);border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);padding:12px 16px;font-size:0.84rem;color:var(--text-mid);transition:all 0.2s;text-decoration:none}
.client-cta:hover{border-color:var(--navy-light);background:var(--navy-soft)}
.client-cta strong{color:var(--navy);font-weight:700}
.back-link{display:inline-flex;align-items:center;gap:6px;font-size:0.82rem;color:var(--text-soft);margin-top:16px;transition:color 0.2s}
.back-link:hover{color:var(--navy)}

@media(max-width:900px){.auth-wrap{grid-template-columns:1fr}.auth-left{display:none}.auth-right{padding:36px 20px;padding-top:48px}}
@media(max-width:480px){.form-row{grid-template-columns:1fr}}
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
        <span class="auth-left-tag">💼 Job Seeker Registration</span>
        <h2 class="auth-left-title">Start Your<br/><em>New Career.</em></h2>
        <p class="auth-left-desc">Create your free profile and let our agents connect you with verified households across Rwanda. It takes less than 2 minutes.</p>
        <div class="steps-list">
          <div class="step-item">
            <div class="step-num">1</div>
            <div class="step-body"><strong>Fill in your details</strong><span>Name, email, phone — that's all we need to start</span></div>
          </div>
          <div class="step-item">
            <div class="step-num">2</div>
            <div class="step-body"><strong>Apply for your first job</strong><span>Choose your category and submit your application</span></div>
          </div>
          <div class="step-item">
            <div class="step-num">3</div>
            <div class="step-body"><strong>Agent reviews &amp; contacts you</strong><span>We verify your details and connect you via WhatsApp</span></div>
          </div>
        </div>
        <div class="benefits-list">
          <div class="benefit-item"><div class="benefit-dot">✓</div> 100% free to register and apply</div>
          <div class="benefit-item"><div class="benefit-dot">✓</div> Verified, safe households only</div>
          <div class="benefit-item"><div class="benefit-dot">✓</div> WhatsApp notifications at every step</div>
          <div class="benefit-item"><div class="benefit-dot">✓</div> Dedicated agent support throughout</div>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT -->
  <div class="auth-right">
    <div class="auth-card">

      <!-- Role badge -->
      <div class="role-badge">
        <div class="role-badge-icon">💼</div>
        <div class="role-badge-text">
          <div class="role-badge-title">Job Seeker Account</div>
          <div class="role-badge-sub">For domestic workers looking for employment</div>
        </div>
        <a href="<?= BASE_URL ?>/register" class="role-badge-switch">Hire help? →</a>
      </div>

      <h1 class="auth-card-title">Create <em>Account</em></h1>
      <p class="auth-card-sub">Already registered? <a href="<?= BASE_URL ?>/worker-login">Sign in &rarr;</a></p>

      <?php if ($error): ?>
        <div class="alert alert-error"><span class="alert-icon">⚠</span><span><?= $error ?></span></div>
      <?php endif; ?>

      <form method="POST" id="regForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
        <input type="hidden" name="role" value="provider"/>

        <div class="form-group">
          <label for="name">Full Name <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">👤</span>
            <input type="text" id="name" name="name" placeholder="Jean Claude Habimana"
              value="<?= e($_POST['name'] ?? '') ?>" required/>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="email">Email <span class="req">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">✉</span>
              <input type="email" id="email" name="email" placeholder="you@example.com"
                value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required/>
            </div>
          </div>
          <div class="form-group">
            <label for="phone">WhatsApp No. <span class="req">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">📱</span>
              <input type="tel" id="phone" name="phone" placeholder="+250 7XX XXX XXX"
                value="<?= e($_POST['phone'] ?? '') ?>" required/>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="district">District / Location</label>
          <div class="input-wrap">
            <span class="input-icon">📍</span>
            <select id="district" name="district" style="padding-left:40px">
              <option value="">Select your district...</option>
              <?php
              $districts = ['Gasabo','Kicukiro','Nyarugenge','Bugesera','Gatsibo','Kayonza','Kirehe','Masamuhura','Ngoma','Nyagatare','Rwamagana','Burera','Gakenke','Gicumbi','Mulindi','Musanze','Rulindo','Gisagara','Huye','Kamonyi','Muhanga','Nyamagabe','Nyanza','Nyaruguru','Ruhango','Karongi','Ngororero','Nyabihu','Nyamasheke','Rubavu','Rusizi','Rutsiro','Nyagatare'];
              sort($districts);
              $sel = $_POST['district'] ?? '';
              foreach($districts as $d): ?>
                <option value="<?= $d ?>" <?= $sel===$d?'selected':'' ?>><?= $d ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" id="password" name="password" placeholder="Min. 8 characters"
              autocomplete="new-password" required oninput="checkStrength(this.value)"/>
            <button type="button" class="eye-toggle" onclick="togglePass('password',this)">👁</button>
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
          <label for="confirm_password">Confirm Password <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" id="confirm_password" name="confirm_password"
              placeholder="Repeat your password" autocomplete="new-password" required/>
            <button type="button" class="eye-toggle" onclick="togglePass('confirm_password',this)">👁</button>
          </div>
        </div>

        <label class="terms-label">
          <input type="checkbox" name="terms" value="1" id="termsCheck" <?= isset($_POST['terms'])?'checked':'' ?>/>
          I agree to the <a href="<?= BASE_URL ?>/terms" target="_blank">Terms of Service</a>
          and <a href="<?= BASE_URL ?>/privacy" target="_blank">Privacy Policy</a>
        </label>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span id="btnText">Create My Account</span><span>→</span>
        </button>
      </form>

      <div class="divider"><span>Looking to hire help instead?</span></div>
      <a href="<?= BASE_URL ?>/register" class="client-cta">
        🏠 <span>Register as a <strong>Client</strong> instead</span>
      </a>

      <a href="<?= BASE_URL ?>/career" class="back-link">← Back to Career Page</a>
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
  txt.style.color=sc<=1?'#ef4444':sc===2?'#f59e0b':'#10b981';
}
document.getElementById('regForm').addEventListener('submit',function(e){
  var ok=true;
  ['name','email','phone','password','confirm_password'].forEach(function(id){
    var el=document.getElementById(id);
    el.classList.remove('error-field');
    if(!el.value.trim()){el.classList.add('error-field');ok=false;}
  });
  var em=document.getElementById('email');
  if(em.value&&!/\S+@\S+\.\S+/.test(em.value)){em.classList.add('error-field');ok=false;}
  var pw=document.getElementById('password').value,cp=document.getElementById('confirm_password');
  if(pw&&pw.length<8){document.getElementById('password').classList.add('error-field');ok=false;}
  if(pw&&cp.value&&pw!==cp.value){cp.classList.add('error-field');ok=false;}
  if(!document.getElementById('termsCheck').checked){ok=false;}
  if(!ok){e.preventDefault();return;}
  var btn=document.getElementById('submitBtn');
  btn.classList.add('loading');
  document.getElementById('btnText').textContent='Creating account...';
});
</script>
<?php include_once '../includes/footer.php'; ?>
</body>
</html>