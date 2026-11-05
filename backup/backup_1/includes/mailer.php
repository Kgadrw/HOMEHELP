<?php
// includes/mailer.php
// Silent email notification sender via Gmail SMTP + PHPMailer

function sendBookingEmail(string $to, string $subject, string $body): bool {
    // ── CONFIG ────────────────────────────────────────────────
    $smtp_host = 'smtp.gmail.com';
    $smtp_user = 'homehelpservicerw@gmail.com';
    $smtp_pass = 'qrwc ryee lpds hqss';   // Gmail App Password
    $smtp_port = 587;
    $from_name = 'Home Help Rwanda';
    // ─────────────────────────────────────────────────────────

    // Try PHPMailer if available
    $phpmailer_paths = [
        '../vendor/phpmailer/phpmailer/src/PHPMailer.php',
        '../vendor/autoload.php',
        '../PHPMailer/src/PHPMailer.php',
        '../PHPMailer/PHPMailer.php',
    ];

    $phpmailer_found = false;
    foreach ($phpmailer_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            if (file_exists(str_replace('PHPMailer.php', 'SMTP.php', $path))) {
                require_once str_replace('PHPMailer.php', 'SMTP.php', $path);
            }
            if (file_exists(str_replace('PHPMailer.php', 'Exception.php', $path))) {
                require_once str_replace('PHPMailer.php', 'Exception.php', $path);
            }
            $phpmailer_found = true;
            break;
        }
    }

    if ($phpmailer_found && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // ── USE PHPMAILER ─────────────────────────────────────
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp_port;
            $mail->setFrom($smtp_user, $from_name);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            logEmailAttempt($to, $subject, true);
            return true;
        } catch (Exception $e) {
            error_log('[Mailer] PHPMailer error: ' . $e->getMessage());
            logEmailAttempt($to, $subject, false);
            return false;
        }
    }

    // ── FALLBACK: PHP mail() with SMTP stream ─────────────────
    return sendViaSocket($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $from_name, $to, $subject, $body);
}

function sendViaSocket(string $host, int $port, string $user, string $pass, string $from_name, string $to, string $subject, string $body): bool {
    try {
        $socket = fsockopen('tls://' . $host, 465, $errno, $errstr, 10);
        if (!$socket) {
            // Try native mail() as last resort
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$from_name} <{$user}>\r\n";
            $result = mail($to, $subject, $body, $headers);
            logEmailAttempt($to, $subject, $result);
            return $result;
        }

        $read = function() use ($socket) {
            $res = '';
            while ($str = fgets($socket, 515)) {
                $res .= $str;
                if (substr($str, 3, 1) === ' ') break;
            }
            return $res;
        };

        $send = function(string $cmd) use ($socket, $read) {
            fputs($socket, $cmd . "\r\n");
            return $read();
        };

        $read(); // greeting
        $send('EHLO homehelp.rw');
        $send('AUTH LOGIN');
        $send(base64_encode($user));
        $send(base64_encode($pass));
        $send('MAIL FROM:<' . $user . '>');
        $send('RCPT TO:<' . $to . '>');
        $send('DATA');

        $message  = "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "From: {$from_name} <{$user}>\r\n";
        $message .= "To: {$to}\r\n";
        $message .= "Subject: {$subject}\r\n\r\n";
        $message .= $body . "\r\n.";
        $send($message);
        $send('QUIT');
        fclose($socket);

        logEmailAttempt($to, $subject, true);
        return true;
    } catch (Exception $e) {
        error_log('[Mailer] Socket error: ' . $e->getMessage());
        logEmailAttempt($to, $subject, false);
        return false;
    }
}

function logEmailAttempt(string $to, string $subject, bool $success): void {
    global $conn;
    if (!isset($conn)) return;
    $status = $success ? 'sent' : 'failed';
    $stmt = $conn->prepare(
        "INSERT INTO email_log (recipient, subject, status, created_at) VALUES (?,?,?,NOW())"
    );
    if ($stmt) {
        $stmt->bind_param('sss', $to, $subject, $status);
        $stmt->execute();
    }
}

function buildBookingEmailHTML(array $data): string {
    $service   = htmlspecialchars($data['service_name']   ?? '—');
    $client    = htmlspecialchars($data['user_name']      ?? '—');
    $phone     = htmlspecialchars($data['phone']          ?? '—');
    $email     = htmlspecialchars($data['email']          ?? '—');
    $nb        = htmlspecialchars($data['neighbourhood']  ?? '—');
    $freq      = htmlspecialchars($data['frequency']      ?? '—');
    $langs     = htmlspecialchars($data['languages']      ?? '—');
    $rooms     = htmlspecialchars($data['rooms']          ?? '—');
    $chef      = htmlspecialchars($data['chef_type']      ?? '');
    $people    = htmlspecialchars($data['num_people']     ?? '');
    $live_in   = !empty($data['live_in'])  ? 'Live In' : '';
    $live_out  = !empty($data['live_out']) ? 'Live Out (' . htmlspecialchars($data['living_area'] ?? '') . ')' : '';
    $accomm    = $live_in ?: ($live_out ?: '—');
    $price     = number_format((float)($data['calculated_price'] ?? 0));

    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"/></head>
<body style="margin:0;padding:0;background:#F4F6FB;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6FB;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(13,27,75,0.10);">

      <!-- HEADER -->
      <tr>
        <td style="background:#0D1B4B;padding:28px 32px;">
          <h1 style="margin:0;font-size:22px;font-weight:700;color:#FFFFFF;font-family:Georgia,serif;">
            🏠 Home Help Rwanda
          </h1>
          <p style="margin:6px 0 0;font-size:13px;color:rgba(255,255,255,0.6);">New Booking Notification</p>
        </td>
      </tr>

      <!-- ALERT BADGE -->
      <tr>
        <td style="background:#FFF8EC;border-bottom:2px solid #E8A020;padding:14px 32px;">
          <p style="margin:0;font-size:14px;font-weight:700;color:#92400E;">
            ✅ New booking submitted — please review on the admin dashboard.
          </p>
        </td>
      </tr>

      <!-- BODY -->
      <tr>
        <td style="padding:28px 32px;">

          <h2 style="margin:0 0 16px;font-size:16px;font-weight:700;color:#0D1B4B;text-transform:uppercase;letter-spacing:0.06em;">Client Details</h2>
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
            <tr>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;width:140px;">👤 Full Name</td>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $client . '</td>
            </tr>
            <tr>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">📱 Phone</td>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $phone . '</td>
            </tr>
            <tr>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">✉️ Email</td>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $email . '</td>
            </tr>
            <tr>
              <td style="padding:10px 0;font-size:13px;color:#888;">📍 Neighbourhood</td>
              <td style="padding:10px 0;font-size:14px;font-weight:600;color:#1A1A2E;">' . $nb . '</td>
            </tr>
          </table>

          <h2 style="margin:0 0 16px;font-size:16px;font-weight:700;color:#0D1B4B;text-transform:uppercase;letter-spacing:0.06em;">Booking Details</h2>
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
            <tr>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;width:140px;">🧹 Services</td>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $service . '</td>
            </tr>
            <tr>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">🔄 Frequency</td>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $freq . '</td>
            </tr>
            <tr>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">🗣️ Languages</td>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $langs . '</td>
            </tr>
            <tr>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">🚪 Rooms</td>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $rooms . '</td>
            </tr>
            ' . ($chef ? '<tr><td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">👨‍🍳 Chef Type</td><td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $chef . '</td></tr>' : '') . '
            ' . ($people ? '<tr><td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">👥 People</td><td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $people . '</td></tr>' : '') . '
            <tr>
              <td style="padding:10px 0;font-size:13px;color:#888;">🏠 Accommodation</td>
              <td style="padding:10px 0;font-size:14px;font-weight:600;color:#1A1A2E;">' . $accomm . '</td>
            </tr>
          </table>

          <!-- PRICE BADGE -->
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
            <tr>
              <td style="background:#0D1B4B;border-radius:10px;padding:18px 24px;">
                <p style="margin:0;font-size:12px;color:rgba(255,255,255,0.6);">Estimated Monthly Fee</p>
                <p style="margin:6px 0 0;font-size:28px;font-weight:700;color:#FFFFFF;font-family:Georgia,serif;">' . $price . ' RWF</p>
              </td>
            </tr>
          </table>

          <!-- CTA BUTTON -->
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td align="center">
                <a href="https://homehelp.rw/admin/bookings" style="display:inline-block;background:#E8A020;color:#FFFFFF;font-size:14px;font-weight:700;padding:14px 32px;border-radius:8px;text-decoration:none;">
                  Review on Admin Dashboard →
                </a>
              </td>
            </tr>
          </table>

        </td>
      </tr>

      <!-- FOOTER -->
      <tr>
        <td style="background:#F4F6FB;padding:20px 32px;text-align:center;border-top:1px solid #F2EDE3;">
          <p style="margin:0;font-size:12px;color:#888;">Home Help Rwanda · homehelp.rw · This is an automated notification.</p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';
}

function buildApprovalEmailHTML(array $data): string {
    $client     = htmlspecialchars($data['client_name']  ?? '—');
    $service    = htmlspecialchars($data['service_name'] ?? '—');
    $note       = htmlspecialchars($data['agent_note']   ?? '');
    $booking_id = htmlspecialchars($data['booking_id']   ?? '');
    $price      = number_format((float)($data['calculated_price'] ?? 0));

    $note_block = '';
    if ($note) {
        $note_block = '
        <tr>
          <td style="padding-top:18px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8EC;border:1.5px solid #FCD34D;border-radius:10px;">
              <tr>
                <td style="padding:14px 18px;">
                  <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#92400E;text-transform:uppercase;letter-spacing:0.05em;">💬 Note from our team</p>
                  <p style="margin:0;font-size:14px;color:#78350F;line-height:1.6;">' . $note . '</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>';
    }

    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"/></head>
<body style="margin:0;padding:0;background:#F4F6FB;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6FB;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(13,27,75,0.10);">

      <!-- HEADER -->
      <tr>
        <td style="background:#0D1B4B;padding:28px 32px;">
          <h1 style="margin:0;font-size:22px;font-weight:700;color:#FFFFFF;font-family:Georgia,serif;">
            🏠 Home Help Rwanda
          </h1>
          <p style="margin:6px 0 0;font-size:13px;color:rgba(255,255,255,0.6);">Booking Update</p>
        </td>
      </tr>

      <!-- SUCCESS BADGE -->
      <tr>
        <td style="background:#ECFDF5;border-bottom:2px solid #10B981;padding:18px 32px;">
          <p style="margin:0;font-size:15px;font-weight:700;color:#065F46;">
            ✅ Great news! Your booking has been approved.
          </p>
        </td>
      </tr>

      <!-- BODY -->
      <tr>
        <td style="padding:28px 32px;">

          <p style="margin:0 0 20px;font-size:15px;color:#1A1A2E;line-height:1.7;">
            Hi ' . $client . ',<br/><br/>
            We are pleased to let you know that your booking request for <strong>' . $service . '</strong> has been reviewed and <strong style="color:#065F46;">approved</strong> by our team. We will be in touch shortly to coordinate the next steps.
          </p>

          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
            <tr>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;width:140px;">📋 Booking ID</td>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">#' . $booking_id . '</td>
            </tr>
            <tr>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">🧹 Service</td>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $service . '</td>
            </tr>
            <tr>
              <td style="padding:10px 0;font-size:13px;color:#888;">💰 Estimated Fee</td>
              <td style="padding:10px 0;font-size:14px;font-weight:600;color:#1A1A2E;">' . $price . ' RWF</td>
            </tr>
          </table>

          ' . $note_block . '

          <!-- CTA BUTTON -->
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;">
            <tr>
              <td align="center">
                <a href="https://homehelp.rw/my-bookings" style="display:inline-block;background:#0D1B4B;color:#FFFFFF;font-size:14px;font-weight:700;padding:14px 32px;border-radius:8px;text-decoration:none;">
                  View My Booking →
                </a>
              </td>
            </tr>
          </table>

        </td>
      </tr>

      <!-- FOOTER -->
      <tr>
        <td style="background:#F4F6FB;padding:20px 32px;text-align:center;border-top:1px solid #F2EDE3;">
          <p style="margin:0;font-size:12px;color:#888;">Home Help Rwanda · homehelp.rw · This is an automated notification.</p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';
}

function buildApplicationApprovalEmailHTML(array $data): string {
    $applicant  = htmlspecialchars($data['applicant_name'] ?? '—');
    $service    = htmlspecialchars($data['service_name']   ?? '—');
    $note       = htmlspecialchars($data['agent_note']     ?? '');
    $app_id     = htmlspecialchars($data['app_id']         ?? '');

    $note_block = '';
    if ($note) {
        $note_block = '
        <tr>
          <td style="padding-top:18px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8EC;border:1.5px solid #FCD34D;border-radius:10px;">
              <tr>
                <td style="padding:14px 18px;">
                  <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#92400E;text-transform:uppercase;letter-spacing:0.05em;">💬 Note from our team</p>
                  <p style="margin:0;font-size:14px;color:#78350F;line-height:1.6;">' . $note . '</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>';
    }

    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"/></head>
<body style="margin:0;padding:0;background:#F4F6FB;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6FB;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(13,27,75,0.10);">

      <!-- HEADER -->
      <tr>
        <td style="background:#0D1B4B;padding:28px 32px;">
          <h1 style="margin:0;font-size:22px;font-weight:700;color:#FFFFFF;font-family:Georgia,serif;">
            🏠 Home Help Rwanda
          </h1>
          <p style="margin:6px 0 0;font-size:13px;color:rgba(255,255,255,0.6);">Job Application Update</p>
        </td>
      </tr>

      <!-- SUCCESS BADGE -->
      <tr>
        <td style="background:#ECFDF5;border-bottom:2px solid #10B981;padding:18px 32px;">
          <p style="margin:0;font-size:15px;font-weight:700;color:#065F46;">
            🎉 Congratulations! Your job application has been approved.
          </p>
        </td>
      </tr>

      <!-- BODY -->
      <tr>
        <td style="padding:28px 32px;">

          <p style="margin:0 0 20px;font-size:15px;color:#1A1A2E;line-height:1.7;">
            Hi ' . $applicant . ',<br/><br/>
            We are excited to let you know that your job application for <strong>' . $service . '</strong> has been reviewed and <strong style="color:#065F46;">approved</strong> by our team. A team member will contact you shortly to discuss the next steps and get you started.
          </p>

          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
            <tr>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;width:160px;">📋 Application ID</td>
              <td style="padding:10px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">#' . $app_id . '</td>
            </tr>
            <tr>
              <td style="padding:10px 0;font-size:13px;color:#888;">💼 Applied For</td>
              <td style="padding:10px 0;font-size:14px;font-weight:600;color:#1A1A2E;">' . $service . '</td>
            </tr>
          </table>

          ' . $note_block . '

          <!-- NEXT STEPS BOX -->
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:22px;margin-bottom:24px;">
            <tr>
              <td style="background:#EFF6FF;border:1.5px solid #BFDBFE;border-radius:10px;padding:16px 20px;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#1E40AF;">📌 What happens next?</p>
                <p style="margin:0;font-size:13px;color:#1E40AF;line-height:1.7;">
                  Our team will reach out to you directly via phone or email to confirm your start date and placement details. Please make sure your contact information is up to date.
                </p>
              </td>
            </tr>
          </table>

          <!-- CTA BUTTON -->
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td align="center">
                <a href="https://homehelp.rw/worker-login" style="display:inline-block;background:#0D1B4B;color:#FFFFFF;font-size:14px;font-weight:700;padding:14px 32px;border-radius:8px;text-decoration:none;">
                  Log In to Your Account →
                </a>
              </td>
            </tr>
          </table>

        </td>
      </tr>

      <!-- FOOTER -->
      <tr>
        <td style="background:#F4F6FB;padding:20px 32px;text-align:center;border-top:1px solid #F2EDE3;">
          <p style="margin:0;font-size:12px;color:#888;">Home Help Rwanda · homehelp.rw · This is an automated notification.</p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';
}