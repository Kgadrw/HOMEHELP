<?php
/**
 * Hiring (client) register route — /register
 * Host page that opens the create hiring account modal.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

if (isLoggedIn()) {
    redirect(BASE_URL . clean_redirect($_SESSION['user_role'] ?? 'client'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<?php include __DIR__ . '/../includes/seo-meta.php'; ?>
<title>Create Hiring Account | Home Help Rwanda</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
  html,body{min-height:100%;margin:0;font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:#0D1B4B}
  .reg-host{
    min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
    background:
      linear-gradient(160deg,rgba(13,27,75,.88),rgba(13,27,75,.72)),
      url('../Image/slider/Slider_1.jpeg') center/cover no-repeat;
  }
  .reg-host-card{
    width:100%;max-width:380px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);
    border-radius:18px;padding:28px 24px;text-align:center;color:#fff;backdrop-filter:blur(10px)
  }
  .reg-host-card img{width:52px;height:52px;border-radius:50%;object-fit:contain;background:#fff;padding:3px;margin-bottom:14px}
  .reg-host-card h1{margin:0 0 8px;font-size:1.25rem;font-weight:800}
  .reg-host-card p{margin:0;font-size:.88rem;opacity:.85;line-height:1.45}
</style>
</head>
<body>
<div class="reg-host">
  <div class="reg-host-card">
    <img src="../Image/homehelp-logo.png" alt="Home Help"/>
    <h1>Create hiring account</h1>
    <p>Opening sign-up…</p>
  </div>
</div>
<?php include __DIR__ . '/../includes/client-login-modal.php'; ?>
<?php include __DIR__ . '/../includes/register-modal.php'; ?>
<script>
(function(){
  function boot(){ if (window.HomeHelpRegister) HomeHelpRegister.open(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
</script>
</body>
</html>
