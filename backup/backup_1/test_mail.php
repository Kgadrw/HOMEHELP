<?php
// ⚠️ DELETE THIS FILE AFTER TESTING
require_once 'config/db.php';
require_once 'config/config.php';
require_once 'includes/mailer.php';

// ── CREATE email_log TABLE IF NOT EXISTS ──────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS email_log (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        recipient  VARCHAR(255),
        subject    VARCHAR(255),
        status     ENUM('sent','failed') DEFAULT 'sent',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// ── BUILD TEST EMAIL ──────────────────────────────────────────
$test_data = [
    'service_name'      => 'Cleaning + Housekeeping',
    'user_name'         => 'MUHIRE Jean (TEST)',
    'phone'             => '+250789239908',
    'email'             => 'muhirejean3@gmail.com',
    'neighbourhood'     => 'Mbugangari',
    'frequency'         => '5 days/week',
    'languages'         => 'English, Kinyarwanda',
    'rooms'             => '3',
    'chef_type'         => '',
    'num_people'        => '',
    'live_in'           => 0,
    'live_out'          => 1,
    'living_area'       => 'Within Kigali',
    'calculated_price'  => 45000,
];

$subject  = '🏠 [TEST] New Booking — Home Help Rwanda';
$body     = buildBookingEmailHTML($test_data);
$result   = sendBookingEmail('homehelpservicerw@gmail.com', $subject, $body);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title>Email Test | Home Help</title>
<style>
  body{font-family:'Segoe UI',sans-serif;background:#f4f6fb;margin:0;padding:40px;color:#1a1a2e}
  .box{max-width:640px;margin:0 auto;background:#fff;border-radius:14px;box-shadow:0 4px 24px rgba(13,27,75,0.10);overflow:hidden}
  .box-head{padding:24px 32px;display:flex;align-items:center;gap:14px}
  .box-head.ok{background:#ecfdf5;border-bottom:2px solid #6ee7b7}
  .box-head.fail{background:#fef2f2;border-bottom:2px solid #fca5a5}
  .icon{font-size:2rem}
  .box-head h2{margin:0;font-size:1.3rem}
  .box-head.ok h2{color:#065f46}
  .box-head.fail h2{color:#991b1b}
  .box-head p{margin:4px 0 0;font-size:0.85rem}
  .box-head.ok p{color:#047857}
  .box-head.fail p{color:#b91c1c}
  .box-body{padding:28px 32px}
  .row{display:flex;gap:12px;margin-bottom:14px;align-items:flex-start}
  .label{font-size:0.78rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.06em;min-width:120px;padding-top:2px}
  .value{font-size:0.9rem;color:#1a1a2e}
  .warn{background:#fffbeb;border:1.5px solid #fde68a;border-radius:8px;padding:14px 18px;font-size:0.83rem;color:#92400e;margin-top:18px;font-weight:600}
</style>
</head>
<body>
<div class="box">
  <div class="box-head <?= $result ? 'ok' : 'fail' ?>">
    <div class="icon"><?= $result ? '✅' : '❌' ?></div>
    <div>
      <h2><?= $result ? 'Email Sent Successfully!' : 'Email Failed' ?></h2>
      <p><?= $result
        ? 'Check homehelpservicerw@gmail.com inbox — the booking notification should have arrived.'
        : 'See details below. PHPMailer may not be installed — check notes.' ?>
      </p>
    </div>
  </div>
  <div class="box-body">
    <div class="row"><span class="label">Sent To</span><span class="value">homehelpservicerw@gmail.com</span></div>
    <div class="row"><span class="label">Subject</span><span class="value"><?= $subject ?></span></div>
    <div class="row"><span class="label">Result</span><span class="value"><?= $result ? '✅ Sent' : '❌ Failed' ?></span></div>
    <div class="row"><span class="label">PHPMailer</span><span class="value"><?= class_exists('PHPMailer\PHPMailer\PHPMailer') ? '✅ Found' : '⚠️ Not found — used fallback' ?></span></div>

    <?php if (!$result): ?>
    <div class="warn">
      💡 <strong>PHPMailer not found.</strong> Install it via cPanel Terminal:<br><br>
      <code>cd ~/public_html && composer require phpmailer/phpmailer</code><br><br>
      Or download manually from <strong>github.com/PHPMailer/PHPMailer</strong> and upload to <code>/PHPMailer/</code>
    </div>
    <?php endif; ?>

    <div class="warn" style="margin-top:14px;background:#fef2f2;border-color:#fca5a5;color:#991b1b;">
      ⚠️ <strong>Remember to DELETE this file after testing:</strong> <code>test_mail.php</code>
    </div>
  </div>
</div>
</body>
</html>