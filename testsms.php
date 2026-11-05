<?php
/**
 * testsms.php
 * Standalone test file for InTouch SMS API (intouchsms.co.rw)
 *
 * NOT part of the main system — safe to delete once SMS integration
 * is confirmed working and wired into includes/mailer.php / admin/bookings.php.
 *
 * Upload this to any folder on the server (e.g. public_html/testsms.php)
 * and open it in your browser to test sending an SMS interactively.
 */

// ── CREDENTIALS ─────────────────────────────────────────────────
// TODO: confirm these are correct — the "sender" ID below (AUCA) looks
// like it may belong to a different client/account. Confirm with InTouch
// which sender ID is actually approved for HomeHelp Rwanda's account,
// otherwise the API may reject the send or fall back to a default sender.
$SMS_CONFIG = [
    'url'      => 'https://www.intouchsms.co.rw/api/sendsms/.json',
    'username' => 'homeHelp',
    'password' => 'Home@321!!!',
    'sender'   => '+250785092496', // confirmed via My Profile → My SenderNames
];

// ── CORE SEND FUNCTION ───────────────────────────────────────────
function sendSMS(string $phone, string $message, array $config): array {
    $data = [
        'sender'     => $config['sender'],
        'recipients' => $phone,
        'message'    => $message,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['url']);
    curl_setopt($ch, CURLOPT_USERPWD, $config['username'] . ':' . $config['password']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
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
    ];
}

// ── NORMALIZE RWANDAN PHONE NUMBERS ───────────────────────────────
// Client phone numbers in the `users` table may be stored in different
// formats (+250789..., 250789..., 0789..., 789..., with spaces, etc).
// This forces them into the local 07XXXXXXXX format that matches your
// working example.
function normalizeRwandaPhone(string $phone): string {
    $phone = preg_replace('/\D/', '', $phone); // strip everything but digits
    if (strpos($phone, '250') === 0) {
        $phone = '0' . substr($phone, 3);
    } elseif (strlen($phone) === 9 && $phone[0] !== '0') {
        $phone = '0' . $phone;
    }
    return $phone;
}

// ── SMS SEGMENT CALCULATOR ────────────────────────────────────────
function smsSegmentInfo(string $message): array {
    $len = mb_strlen($message);
    // GSM-7 basic charset check (rough): if any character falls outside
    // typical GSM-7 range, treat as unicode (70 chars/segment instead of 160)
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
$test_message = "Hey Dear Client, You've received this message";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_phone   = trim($_POST['phone']   ?? $test_phone);
    $test_message = trim($_POST['message'] ?? $test_message);
    $normalized   = normalizeRwandaPhone($test_phone);
    $test_result  = sendSMS($normalized, $test_message, $SMS_CONFIG);
    $test_result['normalized_phone'] = $normalized;
}

$seg = smsSegmentInfo($test_message);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title>SMS API Test — Home Help</title>
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
  .warn{color:#92400E;font-weight:700}
  pre{background:#F4F6FB;padding:14px;border-radius:8px;overflow-x:auto;font-size:0.8rem;white-space:pre-wrap;word-break:break-word}
  .badge{display:inline-block;padding:2px 8px;border-radius:99px;font-size:0.72rem;font-weight:700;margin-left:6px}
  .badge-ok{background:#ECFDF5;color:#065F46}
  .badge-warn{background:#FFFBEB;color:#92400E}
</style>
</head>
<body>

<h1>📱 InTouch SMS API — Standalone Test</h1>
<p style="font-size:0.85rem;color:#666">Delete this file once integration is confirmed. Not linked from the main site.</p>

<div class="card">
  <form method="POST">
    <label>Phone Number (any format — will be normalized to 07XXXXXXXX)</label>
    <input type="text" name="phone" value="<?= htmlspecialchars($test_phone) ?>" placeholder="0789239908 or +250789239908"/>

    <label>Message</label>
    <textarea name="message" oninput="updateCount(this.value)"><?= htmlspecialchars($test_message) ?></textarea>
    <div class="meta" id="charCount">
      <?= $seg['length'] ?> characters · <?= $seg['encoding'] ?> ·
      <span class="badge <?= $seg['fits_one'] ? 'badge-ok' : 'badge-warn' ?>">
        <?= $seg['segments'] ?> SMS segment<?= $seg['segments']>1?'s':'' ?><?= $seg['fits_one'] ? ' (fits in one!)' : ' (will be billed as multiple!)' ?>
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
      <span class="ok">✅ Request completed (check phone to confirm delivery)</span>
    <?php else: ?>
      <span class="fail">❌ Request failed</span>
    <?php endif; ?>
  </p>
  <?php if ($test_result['error']): ?>
    <p class="fail">cURL Error: <?= htmlspecialchars($test_result['error']) ?></p>
  <?php endif; ?>
  <p><strong>Raw API Response:</strong></p>
  <pre><?= htmlspecialchars($test_result['result'] ?? '(empty response)') ?></pre>
</div>
<?php endif; ?>

<div class="card">
  <h3 style="margin-top:0">⚠️ Before going further, confirm with InTouch:</h3>
  <ul style="font-size:0.85rem;line-height:1.7;color:#4A4A4A">
    <li>Is <code>AUCA</code> really the approved Sender ID for the <strong>homeHelp</strong> account, or should it be something like <code>HomeHelp</code> / <code>HOMEHLP</code>? A mismatched sender ID is a common cause of silent failures on Rwandan SMS gateways.</li>
    <li>Does the API expect local format (<code>07XXXXXXXX</code>) as in your working example, or does it also accept <code>2507XXXXXXXX</code>? (This file normalizes to local format to match your known-working snippet.)</li>
    <li>What does a <strong>successful</strong> JSON response look like vs a <strong>failed</strong> one? The raw response above will help us build proper success/failure detection once this goes into the main system — this matters because a 200 HTTP code doesn't always mean the SMS itself was accepted by the gateway.</li>
  </ul>
</div>

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
    segments + ' SMS segment' + (segments>1?'s':'') + (fits ? ' (fits in one!)' : ' (will be billed as multiple!)') +
    '</span>';
}
</script>

</body>
</html>