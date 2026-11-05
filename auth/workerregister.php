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
<?php include __DIR__ . '/../includes/seo-meta.php'; ?>
<title>Join as a Job Seeker | Home Help Rwanda</title>
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
html,body{min-height:100%;font-family:var(--font);background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
.auth-wrap{display:grid;grid-template-columns:1fr 1fr;min-height:100vh}

.auth-left{position:relative;overflow:hidden;background:var(--navy)}
.auth-left-inner{position:sticky;top:0;height:100vh;overflow:hidden}
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
.auth-card{width:100%;max-width:420px}
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
.auth-card .sub{font-size:0.95rem;color:var(--muted);margin-bottom:28px;line-height:1.5}
.auth-card .sub a{color:var(--navy-light);font-weight:600}
.auth-card .sub a:hover{text-decoration:underline}

.alert{
  display:flex;align-items:flex-start;gap:10px;
  padding:12px 14px;margin-bottom:20px;border-radius:10px;
  background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C;
  font-size:0.875rem;line-height:1.45;
}
.alert i{width:18px;height:18px;flex-shrink:0;margin-top:1px}
.alert a{color:#B91C1C;font-weight:700;text-decoration:underline}

.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:0.8125rem;font-weight:600;color:var(--text);margin-bottom:7px}
.form-group label .req{color:#ef4444;margin-left:2px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.input-wrap{position:relative}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);display:flex;pointer-events:none;z-index:1}
.input-icon i{width:18px;height:18px;stroke-width:2}
.form-group input,.form-group select{
  width:100%;padding:13px 44px;border:1px solid var(--line);border-radius:10px;
  font-family:var(--font);font-size:0.9375rem;color:var(--text);
  background:var(--white);outline:none;transition:border-color .15s,box-shadow .15s;
}
.form-group select{
  cursor:pointer;appearance:none;-webkit-appearance:none;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:right 16px center;
}
.form-group input:focus,.form-group select:focus{border-color:var(--navy-light);box-shadow:0 0 0 3px rgba(46,95,194,.12)}
.form-group input.error-field,.form-group select.error-field{border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.1)}
.form-row .form-group input{padding-left:42px;padding-right:12px}
.eye-toggle{
  position:absolute;right:10px;top:50%;transform:translateY(-50%);
  border:0;background:transparent;color:var(--muted);cursor:pointer;
  padding:6px;display:flex;border-radius:6px;
}
.eye-toggle:hover{color:var(--navy)}
.eye-toggle i{width:18px;height:18px;stroke-width:2}

.strength-bar{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:8px}
.strength-seg{height:3px;border-radius:2px;background:var(--line);transition:background .3s}
.strength-seg.weak{background:#ef4444}.strength-seg.fair{background:#f59e0b}.strength-seg.good{background:#10b981}
.strength-text{display:block;font-size:0.75rem;color:var(--muted);margin-top:4px}

.terms-label{display:flex;align-items:flex-start;gap:9px;cursor:pointer;font-size:0.8125rem;color:var(--muted);margin:4px 0 20px;line-height:1.6}
.terms-label input[type="checkbox"]{width:16px;height:16px;accent-color:var(--navy);cursor:pointer;flex-shrink:0;margin-top:2px}
.terms-label a{color:var(--navy-light);font-weight:600}
.terms-label a:hover{text-decoration:underline}

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

.btn-google{
  width:100%;display:flex;align-items:center;justify-content:center;gap:10px;
  padding:13px 18px;margin-bottom:18px;border-radius:10px;
  border:1px solid var(--line);background:var(--white);
  font-family:var(--font);font-size:0.9375rem;font-weight:600;color:var(--text);
  transition:border-color .15s,background .15s;
}
.btn-google:hover{border-color:#cfc9bc;background:#fff}
.btn-google svg{width:18px;height:18px;flex-shrink:0}
.auth-divider{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.auth-divider::before,.auth-divider::after{content:'';flex:1;height:1px;background:var(--line)}
.auth-divider span{font-size:0.75rem;color:var(--muted);white-space:nowrap;font-weight:500}

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
@media(max-width:480px){.form-row{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="auth-wrap">

  <div class="auth-left">
    <div class="auth-left-inner">
      <a href="<?= BASE_URL ?>" class="back-home">
        <i data-lucide="arrow-left"></i> Back to home
      </a>
      <img class="auth-left-img" src="../Image/slider/Slider_2.jpeg" alt=""/>
    </div>
  </div>

  <div class="auth-right">
    <div class="auth-card">
      <a href="<?= BASE_URL ?>" class="back-home-mobile">
        <i data-lucide="arrow-left"></i> Back to home
      </a>
      <a href="<?= BASE_URL ?>" class="auth-brand" aria-label="Home Help">
        <img src="../Image/homehelp-logo.png" alt="Home Help"/>
      </a>

      <h1>Create job seeker account</h1>
      <p class="sub">Already registered? <a href="<?= BASE_URL ?>/worker-login" data-open-worker-login>Sign in</a></p>

      <?php if ($error): ?>
        <div class="alert" role="alert">
          <i data-lucide="circle-alert"></i>
          <span><?= $error ?></span>
        </div>
      <?php endif; ?>

      <a href="<?= BASE_URL ?>/google-login?role=provider" class="btn-google">
        <svg viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
          <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
          <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
          <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
          <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
        Continue with Google
      </a>
      <div class="auth-divider"><span>or register with email</span></div>

      <form method="POST" id="regForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
        <input type="hidden" name="role" value="provider"/>

        <div class="form-group">
          <label for="name">Full name <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="input-icon" aria-hidden="true"><i data-lucide="user"></i></span>
            <input type="text" id="name" name="name" placeholder="Jean Claude Habimana"
              value="<?= e($_POST['name'] ?? '') ?>" required/>
          </div>
        </div>

        <div class="form-group">
          <label for="email">Email <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="input-icon" aria-hidden="true"><i data-lucide="mail"></i></span>
            <input type="email" id="email" name="email" placeholder="you@example.com"
              value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required/>
          </div>
        </div>

        <div class="form-group">
          <label for="phone">WhatsApp number <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="input-icon" aria-hidden="true"><i data-lucide="phone"></i></span>
            <input type="tel" id="phone" name="phone" placeholder="+250 7XX XXX XXX"
              value="<?= e($_POST['phone'] ?? '') ?>" required/>
          </div>
        </div>

        <div class="form-group">
          <label for="district">District</label>
          <div class="input-wrap">
            <span class="input-icon" aria-hidden="true"><i data-lucide="map-pin"></i></span>
            <select id="district" name="district">
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
            <span class="input-icon" aria-hidden="true"><i data-lucide="lock"></i></span>
            <input type="password" id="password" name="password" placeholder="Min. 8 characters"
              autocomplete="new-password" required oninput="checkStrength(this.value)"/>
            <button type="button" class="eye-toggle" onclick="togglePass('password',this)" aria-label="Show password">
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
          <label for="confirm_password">Confirm password <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="input-icon" aria-hidden="true"><i data-lucide="lock"></i></span>
            <input type="password" id="confirm_password" name="confirm_password"
              placeholder="Repeat your password" autocomplete="new-password" required/>
            <button type="button" class="eye-toggle" onclick="togglePass('confirm_password',this)" aria-label="Show password">
              <i data-lucide="eye"></i>
            </button>
          </div>
        </div>

        <label class="terms-label">
          <input type="checkbox" name="terms" value="1" id="termsCheck" <?= isset($_POST['terms'])?'checked':'' ?>/>
          <span>I agree to the <a href="<?= BASE_URL ?>/terms" target="_blank">Terms of Service</a>
          and <a href="<?= BASE_URL ?>/privacy" target="_blank">Privacy Policy</a></span>
        </label>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span id="btnText">Create account</span>
          <i data-lucide="arrow-right"></i>
        </button>
      </form>

      <div class="auth-links">
        <a href="<?= BASE_URL ?>/career">
          <i data-lucide="arrow-left"></i> Back to careers
        </a>
      </div>
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
<?php include __DIR__ . '/../includes/worker-login-modal.php'; ?>
</body>
</html>
