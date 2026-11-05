<?php
/**
 * Job seeker login route — kept at /worker-login for Google OAuth and deep links.
 * Renders a light host page and opens the in-page worker login modal.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

if (isLoggedIn()) {
    redirect(BASE_URL . clean_redirect($_SESSION['user_role'] ?? 'provider'));
}

$flash = function_exists('flashGet') ? flashGet() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<?php include __DIR__ . '/../includes/seo-meta.php'; ?>
<title>Job Seeker Login | Home Help Rwanda</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
  html,body{min-height:100%;margin:0;font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:#0D1B4B}
  .login-host{
    min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
    background:
      linear-gradient(160deg,rgba(13,27,75,.88),rgba(13,27,75,.72)),
      url('../Image/slider/Slider_2.jpeg') center/cover no-repeat;
  }
  .login-host-card{
    width:100%;max-width:420px;background:#fff;border-radius:16px;padding:28px;
    box-shadow:0 20px 60px rgba(0,0,0,.28);text-align:center;color:#111827
  }
  .login-host-card img{width:48px;height:48px;border-radius:50%;object-fit:contain;margin-bottom:14px}
  .login-host-card h1{margin:0 0 8px;font-size:1.35rem;font-weight:800;color:#0D1B4B}
  .login-host-card p{margin:0 0 18px;font-size:.9rem;color:#6B7280;line-height:1.45}
  .login-host-card .btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:12px 18px;border-radius:999px;background:#0D1B4B;color:#fff;
    font-weight:700;text-decoration:none;border:0;cursor:pointer;font:inherit;font-size:.9rem
  }
  .login-host-flash{margin:0 0 14px;padding:10px 12px;border-radius:10px;font-size:.84rem;line-height:1.4}
  .login-host-flash.error{background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C}
  .login-host-flash.success{background:#ECFDF5;border:1px solid #A7F3D0;color:#047857}
</style>
</head>
<body>
<div class="login-host">
  <div class="login-host-card">
    <img src="../Image/homehelp-logo.png" alt="Home Help"/>
    <h1>Job seeker sign in</h1>
    <p>Continue to your worker dashboard, or create a free job seeker account.</p>
    <?php if (!empty($flash['message'])): ?>
      <div class="login-host-flash <?= e($flash['type'] ?? 'error') ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <button type="button" class="btn" id="openWorkerLoginBtn">Sign in</button>
  </div>
</div>
<?php include __DIR__ . '/../includes/worker-login-modal.php'; ?>
<script>
(function(){
  function openLogin(){
    if (window.HomeHelpWorkerLogin) HomeHelpWorkerLogin.open();
  }
  var btn = document.getElementById('openWorkerLoginBtn');
  if (btn) btn.addEventListener('click', openLogin);
  openLogin();
})();
</script>
</body>
</html>
