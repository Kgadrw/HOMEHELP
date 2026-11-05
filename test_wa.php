<?php
// ⚠️ DELETE THIS FILE AFTER TESTING — do not leave on production!
require_once 'config/db.php';
require_once 'config/config.php';

// ── CONFIG — paste your real token here ──────────────────────
$api_url   = 'https://wa-api.me/api/wa-accounts/messages';
$api_token = 'YOUR_WA_API_ME_TOKEN_HERE'; // ← replace this
$phone     = '250789239908';              // admin number without +
$message   = "🧪 *TEST — Home Help Rwanda*\n\nWhatsApp API integration is working correctly! ✅";
// ─────────────────────────────────────────────────────────────

$payload = json_encode([
    'participants' => [['number' => $phone]],
    'messageType'  => 'text',
    'message'      => $message,
]);

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_token,
    ],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$curl_err = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$success = !$curl_err && ($httpCode === 200 || $httpCode === 201);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title>WhatsApp API Test | Home Help</title>
<style>
  body{font-family:'Segoe UI',sans-serif;background:#f4f6fb;margin:0;padding:40px;color:#1a1a2e}
  .box{max-width:640px;margin:0 auto;background:#fff;border-radius:14px;box-shadow:0 4px 24px rgba(13,27,75,0.10);overflow:hidden}
  .box-head{padding:24px 32px;display:flex;align-items:center;gap:14px}
  .box-head.ok{background:#ecfdf5;border-bottom:2px solid #6ee7b7}
  .box-head.fail{background:#fef2f2;border-bottom:2px solid #fca5a5}
  .box-head .icon{font-size:2rem}
  .box-head h2{margin:0;font-size:1.3rem}
  .box-head.ok h2{color:#065f46}
  .box-head.fail h2{color:#991b1b}
  .box-head p{margin:4px 0 0;font-size:0.85rem}
  .box-head.ok p{color:#047857}
  .box-head.fail p{color:#b91c1c}
  .box-body{padding:28px 32px}
  .row{display:flex;gap:12px;margin-bottom:12px;align-items:flex-start}
  .label{font-size:0.78rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.06em;min-width:110px;padding-top:2px}
  .value{font-size:0.9rem;color:#1a1a2e;word-break:break-all}
  .value.code{background:#f4f6fb;border:1px solid #e8ecf4;border-radius:6px;padding:10px 14px;font-family:monospace;font-size:0.82rem;white-space:pre-wrap;width:100%}
  .pill{display:inline-block;padding:3px 12px;border-radius:99px;font-size:0.8rem;font-weight:700}
  .pill.ok{background:#d1fae5;color:#065f46}
  .pill.fail{background:#fee2e2;color:#991b1b}
  .divider{border:none;border-top:1px solid #e8ecf4;margin:18px 0}
  .tip{background:#fffbeb;border:1.5px solid #fde68a;border-radius:8px;padding:14px 18px;font-size:0.83rem;color:#92400e;line-height:1.7;margin-top:18px}
  .tip strong{display:block;margin-bottom:4px}
  .delete-warn{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:8px;padding:12px 18px;font-size:0.82rem;color:#991b1b;margin-top:14px;font-weight:600}
</style>
</head>
<body>
<div class="box">

  <div class="box-head <?= $success ? 'ok' : 'fail' ?>">
    <div class="icon"><?= $success ? '✅' : '❌' ?></div>
    <div>
      <h2><?= $success ? 'Message Sent Successfully!' : 'Send Failed' ?></h2>
      <p><?= $success
        ? 'Check +250789239908 — the WhatsApp message should have arrived.'
        : 'See the details below to identify the problem.' ?>
      </p>
    </div>
  </div>

  <div class="box-body">

    <div class="row">
      <span class="label">HTTP Code</span>
      <span class="value">
        <span class="pill <?= ($httpCode >= 200 && $httpCode < 300) ? 'ok' : 'fail' ?>">
          <?= $httpCode ?>
        </span>
      </span>
    </div>

    <div class="row">
      <span class="label">cURL Error</span>
      <span class="value <?= $curl_err ? '' : '' ?>">
        <?= $curl_err ? '⚠️ ' . htmlspecialchars($curl_err) : '✔ None' ?>
      </span>
    </div>

    <div class="row">
      <span class="label">Endpoint</span>
      <span class="value"><?= htmlspecialchars($api_url) ?></span>
    </div>

    <div class="row">
      <span class="label">Phone</span>
      <span class="value"><?= htmlspecialchars($phone) ?></span>
    </div>

    <hr class="divider"/>

    <div class="row" style="flex-direction:column;gap:6px">
      <span class="label">API Response</span>
      <span class="value code"><?= $response ? htmlspecialchars(json_encode(json_decode($response), JSON_PRETTY_PRINT)) : '(empty response)' ?></span>
    </div>

    <hr class="divider"/>

    <div class="row" style="flex-direction:column;gap:6px">
      <span class="label">Payload Sent</span>
      <span class="value code"><?= htmlspecialchars(json_encode(json_decode($payload), JSON_PRETTY_PRINT)) ?></span>
    </div>

    <?php if (!$success): ?>
    <div class="tip">
      <strong>🔍 How to fix based on HTTP code:</strong>
      <?php if ($httpCode === 404): ?>
        <b>404</b> — Wrong endpoint URL. Double-check your wa-api.me dashboard for the correct URL.
      <?php elseif ($httpCode === 401): ?>
        <b>401</b> — Invalid or missing token. Go to wa-api.me → Dashboard → copy your API token and paste it in this file.
      <?php elseif ($httpCode === 403): ?>
        <b>403</b> — Token valid but access denied. Check if your wa-api.me account is active and WhatsApp number is connected.
      <?php elseif ($httpCode === 422 || $httpCode === 400): ?>
        <b><?= $httpCode ?></b> — Wrong payload format. The API rejected the data structure. Check the response above for details.
      <?php elseif ($httpCode === 0): ?>
        <b>0 / cURL error</b> — Your server cannot reach wa-api.me. This may be a firewall or outbound connection block on your cPanel host. Contact StableServer support and ask them to allow outbound HTTPS to wa-api.me.
      <?php else: ?>
        <b><?= $httpCode ?></b> — Unexpected error. Share the response above for further debugging.
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="delete-warn">
      ⚠️ Remember to DELETE this file after testing: <code>test_wa.php</code>
    </div>

  </div>
</div>
</body>
</html>