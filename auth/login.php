<?php
/**
 * Hiring (client) login route — /login
 * Host page for Google OAuth redirects; opens the hiring login modal.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

if (isLoggedIn()) {
    redirect(BASE_URL . clean_redirect($_SESSION['user_role'] ?? 'client'));
}

$flash = function_exists('flashGet') ? flashGet() : null;
$open_register = (($_GET['register'] ?? '') === '1');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<?php include __DIR__ . '/../includes/seo-meta.php'; ?>
<title>Hire Login | Home Help Rwanda</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
  html,body{min-height:100%;margin:0;font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:#0D1B4B}
  .login-host{
    min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
    background:
      linear-gradient(160deg,rgba(13,27,75,.88),rgba(13,27,75,.72)),
      url('../Image/slider/Slider_1.jpeg') center/cover no-repeat;
  }
  .login-host-card{
    width:100%;max-width:380px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);
    border-radius:18px;padding:28px 24px;text-align:center;color:#fff;backdrop-filter:blur(10px)
  }
  .login-host-card img{width:52px;height:52px;border-radius:50%;object-fit:contain;background:#fff;padding:3px;margin-bottom:14px}
  .login-host-card h1{margin:0 0 8px;font-size:1.25rem;font-weight:800}
  .login-host-card p{margin:0 0 16px;font-size:.88rem;opacity:.85;line-height:1.45}
  .login-host-flash{margin:0 0 14px;padding:10px 12px;border-radius:10px;font-size:.84rem;line-height:1.4;text-align:left}
  .login-host-flash.error{background:rgba(254,242,242,.95);border:1px solid #FECACA;color:#B91C1C}
  .login-host-flash.success{background:rgba(236,253,245,.95);border:1px solid #A7F3D0;color:#047857}
  .login-host-note{margin:0;font-size:.78rem;opacity:.75}
  .login-host-note a{color:#93C5FD;font-weight:600;text-decoration:none}
</style>
</head>
<body>
<div class="login-host">
  <div class="login-host-card">
    <img src="../Image/homehelp-logo.png" alt="Home Help"/>
    <h1>Hire with Home Help</h1>
    <p>Opening your hiring <?= $open_register ? 'sign-up' : 'sign-in' ?>…</p>
    <?php if (!empty($flash['message'])): ?>
      <div class="login-host-flash <?= e($flash['type'] ?? 'error') ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <p class="login-host-note">Looking for work? <a href="<?= BASE_URL ?>/worker-login">Job Seeker Login</a></p>
  </div>
</div>
<?php include __DIR__ . '/../includes/client-login-modal.php'; ?>
<?php include __DIR__ . '/../includes/register-modal.php'; ?>
<script>
(function(){
  var openRegisterFirst = <?= $open_register ? 'true' : 'false' ?>;
  function boot(){
    if (openRegisterFirst) {
      if (window.HomeHelpRegister) HomeHelpRegister.open();
    } else if (window.HomeHelpLogin) {
      HomeHelpLogin.open();
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
</script>
</body>
</html>
