<?php
/**
 * testsms2.php
 * Standalone test file for eSMS Africa SMS API (sms.esmsafrica.io)
 *
 * NOT part of the main system — safe to delete once confirmed working.
 */

// ── CREDENTIALS ─────────────────────────────────────────────────
$ESMS_CONFIG = [
    'url'   => 'https://sms.esmsafrica.io/api/messages/send',
    'token' => 'esms_test_1d5a2f2646bf097168b3f1a4fe55d6da', // TEST/sandbox key
    'sender'=> 'eSMS', // pending approval as of now — may need to wait
];

// ── CORE SEND FUNCTION ───────────────────────────────────────────
function sendSMS_eSMS(string $phone, string $message, array $config): array {
    $payload = [
        'to'   => $phone,
        'text' => $message,
    ];
    // Include sender if we have one — API may ignore or require it
    if (!empty($config['sender'])) {
        $payload['senderId'] = $config['sender'];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $config['token'],
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $result   = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    return [
        'success'  => ($httpcode >= 200 && $httpcode < 300 && !$error),
        'httpcode' => $httpcode,
        'result'   => $result,
        'error'    => $error,
        'payload'  => $payload,
    ];
}

// ── NORMALIZE TO INTERNATIONAL FORMAT (+250...) ───────────────────
// eSMS Africa's example uses full international format (+254...),
// unlike InTouch which used local 07XXXXXXXX — so normalize differently here.
function normalizeToIntl(string $phone): string {
    $phone = preg_replace('/\D/', '', $phone);
    if (strpos($phone, '250') === 0) {
        return '+' . $phone;
    }
    if (strlen($phone) === 9) { // e.g. 789239908
        return '+250' . $phone;
    }
    if ($phone[0] === '0' && strlen($phone) === 10) { // e.g. 0789239908
        return '+250' . substr($phone, 1);
    }
    return '+' . $phone;
}

// ── SMS SEGMENT CALCULATOR (same as testsms.php) ──────────────────
function smsSegmentInfo(string $message): array {
    $len = mb_strlen($message);
    $is_gsm7 = (bool) preg_match('/^[\x20-\x7E\x0A\x0D£¥€]*$/u', $message);
    $limit   = $is_gsm7 ? 160 : 70;
    $segments = (int) ceil($len / $limit) ?: 1;
    return [
        'length'    => $len,
        'encoding'  => $is_gsm7 ? 'GSM-7' : 'Unicode (UCS-2)',
        'limit'     => $limit,
        'segments'  => $segments,
        'fits_one'  => $segments <= 1,
    ];
}

// ── HANDLE TEST SEND ───────────────────────────────────────────
$test_result  = null;
$test_phone   = '0789239908';
$test_message = "Hey Dear Client, this is a test from eSMS Africa";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_phone   = trim($_POST['phone']   ?? $test_phone);
    $test_message = trim($_POST['message'] ?? $test_message);
    $normalized   = normalizeToIntl($test_phone);
    $test_result  = sendSMS_eSMS($normalized, $test_message, $ESMS_CONFIG);
    $test_result['normalized_phone'] = $normalized;
}

$seg = smsSegmentInfo($test_message);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title>eSMS Africa API Test — Home Help</title>
<style>
  body{font-family:Arial,sans-serif;max-width:640px;margin:40px auto;padding:0 20px;background:#FDFAF5;color:#1A1A1A}
  h1{color:#0D1B4B;font-size:1.4rem}
  .card{background:#fff;border:1.5px solid #F2EDE3;border-radius:12px;padding:24px;margin-bottom:20px}
  label{display:block;font-weight:600;font-size:0.85rem;margin-bottom:4px;margin-top:14px}
  input[type=text],textarea{width:100%;padding:10px;border:1.5px solid #F2EDE3;border-radius:8px;font-family:inherit;font-size:0.9rem;box-sizing:border-box}
  textarea{min-height:80px;resize:vertical}
  button{margin-top:16px;padding:10px 22px;background:#0D1B4B;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:0.9rem}
  button:hover{background:#152268}
  .meta{font-size:0.78rem;color:#666;margin-top:6px}
  .ok{color:#065F46;font-weight:700}
  .fail{color:#B91C1C;font-weight:700}
  pre{background:#F4F6FB;padding:14px;border-radius:8px;overflow-x:auto;font-size:0.8rem;white-space:pre-wrap;word-break:break-word}
  .badge{display:inline-block;padding:2px 8px;border-radius:99px;font-size:0.72rem;font-weight:700;margin-left:6px}
  .badge-ok{background:#ECFDF5;color:#065F46}
  .badge-warn{background:#FFFBEB;color:#92400E}
</style>
</head>
<body>

<h1>📱 eSMS Africa API — Standalone Test</h1>
<p style="font-size:0.85rem;color:#666">Delete this file once integration is confirmed. Not linked from the main site.</p>

<div class="card">
  <p style="font-size:0.85rem;background:#FFFBEB;border:1.5px solid #FCD34D;padding:10px 14px;border-radius:8px;color:#92400E">
    ⚠️ Sender ID "eSMS" is still <strong>pending</strong> approval for Rwanda as of this test. If the send fails with a sender-related error, that's expected — we're just confirming the connection/auth work while waiting.
  </p>
</div>

<div class="card">
  <form method="POST">
    <label>Phone Number (any format — normalized to +250XXXXXXXXX)</label>
    <input type="text" name="phone" value="<?= htmlspecialchars($test_phone) ?>" placeholder="0789239908"/>

    <label>Message</label>
    <textarea name="message" oninput="updateCount(this.value)"><?= htmlspecialchars($test_message) ?></textarea>
    <div class="meta" id="charCount">
      <?= $seg['length'] ?> characters · <?= $seg['encoding'] ?> ·
      <span class="badge <?= $seg['fits_one'] ? 'badge-ok' : 'badge-warn' ?>">
        <?= $seg['segments'] ?> SMS segment<?= $seg['segments']>1?'s':'' ?><?= $seg['fits_one'] ? ' (fits in one!)' : ' (multiple segments!)' ?>
      </span>
    </div>

    <button type="submit">Send Test SMS</button>
  </form>
</div>

<?php if ($test_result): ?>
<div class="card">
  <h3 style="margin-top:0">Result</h3>
  <p>
    Sent to: <strong><?= htmlspecialchars($test_result['normalized_phone']) ?></strong>
    (original input: <?= htmlspecialchars($test_phone) ?>)<br/>
    HTTP Code: <strong><?= htmlspecialchars((string)$test_result['httpcode']) ?></strong><br/>
    Status:
    <?php if ($test_result['success']): ?>
      <span class="ok">✅ Request completed</span>
    <?php else: ?>
      <span class="fail">❌ Request failed</span>
    <?php endif; ?>
  </p>
  <?php if ($test_result['error']): ?>
    <p class="fail">cURL Error: <?= htmlspecialchars($test_result['error']) ?></p>
  <?php endif; ?>
  <p><strong>Payload sent:</strong></p>
  <pre><?= htmlspecialchars(json_encode($test_result['payload'], JSON_PRETTY_PRINT)) ?></pre>
  <p><strong>Raw API Response:</strong></p>
  <pre><?= htmlspecialchars($test_result['result'] ?? '(empty response)') ?></pre>
</div>
<?php endif; ?>

<script>
function updateCount(val){
  var len = val.length;
  var isGsm7 = /^[\x20-\x7E\x0A\x0D£¥€]*$/.test(val);
  var limit = isGsm7 ? 160 : 70;
  var segments = Math.max(1, Math.ceil(len / limit));
  var fits = segments <= 1;
  document.getElementById('charCount').innerHTML =
    len + ' characters · ' + (isGsm7 ? 'GSM-7' : 'Unicode (UCS-2)') + ' · ' +
    '<span class="badge ' + (fits ? 'badge-ok' : 'badge-warn') + '">' +
    segments + ' SMS segment' + (segments>1?'s':'') + (fits ? ' (fits in one!)' : ' (multiple segments!)') +
    '</span>';
}
</script>

</body>
</html>