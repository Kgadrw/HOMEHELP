<?php
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!str_starts_with($host, 'localhost') && !str_starts_with($host, '127.0.0.1')) {
    http_response_code(403);
    exit('Forbidden');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Dashboard gallery — Home Help</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet"/>
<style>
  :root { --navy:#0D1B4B; --bg:#F4F6FB; }
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--navy);min-height:100vh;padding:48px 24px}
  .wrap{max-width:720px;margin:0 auto}
  h1{font-size:28px;font-weight:800;margin-bottom:8px}
  p{color:#5B6478;margin-bottom:28px}
  .grid{display:grid;gap:14px}
  a{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;background:#fff;border-radius:14px;text-decoration:none;color:var(--navy);font-weight:700;box-shadow:0 1px 3px rgba(13,27,75,.06);border:1px solid #E6EAF2}
  a:hover{border-color:#0D1B4B;box-shadow:0 8px 24px rgba(13,27,75,.08)}
  a span{font-size:13px;font-weight:500;color:#5B6478}
  .note{margin-top:28px;font-size:13px;color:#8A93A6}
</style>
</head>
<body>
  <div class="wrap">
    <h1>Open a dashboard</h1>
    <p>Local preview — signs you in as that role and opens the live page.</p>
    <div class="grid">
      <a href="/_dash_preview.php?role=client">Client <span>/dashboard</span></a>
      <a href="/_dash_preview.php?role=provider">Job Seeker (Provider) <span>/provider-dashboard</span></a>
      <a href="/_dash_preview.php?role=agent">Agent <span>/agent</span></a>
      <a href="/_dash_preview.php?role=admin">Admin <span>/admin</span></a>
    </div>
    <p class="note">Temporary files: <code>_dash_gallery.php</code>, <code>_dash_preview.php</code> — delete when done.</p>
  </div>
</body>
</html>
