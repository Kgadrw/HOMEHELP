<?php
/**
 * includes/mailer.php
 * Home Help Rwanda — Email System
 *
 * Sends via your own domain's SMTP (bookings@homehelp.rw) instead of a
 * personal Gmail account — matches SPF/DKIM/DMARC on homehelp.rw itself,
 * which is the correct professional setup for deliverability.
 */

// ── INCLUDE GUARD ─────────────────────────────────────────────
if (defined('MAILER_LOADED')) return;
define('MAILER_LOADED', true);

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// ── CONFIG ────────────────────────────────────────────────────
$_MAILER_CONFIG = [
    'smtp_host' => 'mail.homehelp.rw',
    'smtp_port' => 465,
    'smtp_user' => 'bookings@homehelp.rw',
    'smtp_pass' => 'bookingsHomeHelp@123',
    'from_name' => 'Home Help Rwanda',
    'phone'     => '+250 790 533 144',
    'email'     => 'info@homehelp.rw',
    'address'   => 'Kigali, Rwanda',
    'site'      => 'https://homehelp.rw',
];


// ── SEND EMAIL (real SMTP, authenticated) ──────────────────────
function sendBookingEmail($to, string $subject, string $body): bool {
    global $_MAILER_CONFIG;

    $recipients = is_array($to) ? $to : [$to];

    $mail = new PHPMailer(true);
    $result = false;
    $error_msg = '';

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $_MAILER_CONFIG['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_MAILER_CONFIG['smtp_user'];
        $mail->Password   = $_MAILER_CONFIG['smtp_pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $_MAILER_CONFIG['smtp_port'];

        $mail->Timeout    = 15;
        $mail->SMTPKeepAlive = false;

        $mail->setFrom($_MAILER_CONFIG['smtp_user'], $_MAILER_CONFIG['from_name']);
        foreach ($recipients as $r) {
            $mail->addAddress($r);
        }
        $mail->addReplyTo($_MAILER_CONFIG['smtp_user'], $_MAILER_CONFIG['from_name']);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $body;
        // Plain-text fallback — HTML-only emails score high on spam filters
        $mail->AltBody = trim(preg_replace('/\n{3,}/', "\n\n", strip_tags(
            str_replace(['<br/>', '<br>', '<br />', '</p>', '</tr>'], "\n", $body)
        )));

        $mail->send();
        $result = true;
    } catch (PHPMailerException $e) {
        $result = false;
        $error_msg = $mail->ErrorInfo ?: $e->getMessage();
        error_log('[Mailer] sendBookingEmail FAILED to ' . implode(', ', $recipients) . ' — ' . $error_msg);
    } catch (\Throwable $e) {
        $result = false;
        $error_msg = $e->getMessage();
        error_log('[Mailer] sendBookingEmail EXCEPTION to ' . implode(', ', $recipients) . ' — ' . $error_msg);
    }

    logEmailAttempt(implode(', ', $recipients), $subject, $result, $error_msg);
    return $result;
}


function logEmailAttempt(string $to, string $subject, bool $success, string $error_msg = ''): void {
    global $conn;
    if (!isset($conn)) return;
    try {
        $status = $success ? 'sent' : 'failed';
        // If your email_log table doesn't have an `error_message` column yet,
        // run: ALTER TABLE email_log ADD COLUMN error_message TEXT NULL;
        $stmt = $conn->prepare(
            "INSERT INTO email_log (recipient, subject, status, error_message, created_at) VALUES (?,?,?,?,NOW())"
        );
        if ($stmt) {
            $stmt->bind_param('ssss', $to, $subject, $status, $error_msg);
            $stmt->execute();
        }
    } catch (Exception $e) {
        error_log('[Mailer] logEmailAttempt error: ' . $e->getMessage());
    }
}

// ── EMAIL FOOTER (branded, appears on all emails) ─────────────
function emailFooter(): string {
    global $_MAILER_CONFIG;
    $year = date('Y');
    return '
<tr>
  <td style="background:#F8F9FF;border-top:2px solid #E8ECF4;padding:20px 32px;">
    <p style="margin:0 0 6px;font-family:Georgia,serif;font-size:16px;font-weight:700;color:#0D1B4B;">Home Help Rwanda</p>
    <p style="margin:0;font-size:12px;color:#4A4A4A;line-height:1.8;">
      📍 ' . $_MAILER_CONFIG['address'] . '<br/>
      📞 ' . $_MAILER_CONFIG['phone'] . '<br/>
      ✉️ <a href="mailto:' . $_MAILER_CONFIG['email'] . '" style="color:#2E5FC2;">' . $_MAILER_CONFIG['email'] . '</a><br/>
      🌐 <a href="' . $_MAILER_CONFIG['site'] . '" style="color:#2E5FC2;">' . $_MAILER_CONFIG['site'] . '</a>
    </p>
  </td>
</tr>
<tr>
  <td style="background:#0D1B4B;padding:12px 32px;text-align:center;">
    <p style="margin:0;font-size:11px;color:rgba(255,255,255,0.5);">
      &copy; ' . $year . ' Home Help Rwanda &middot; Brilliant Elite Ltd &middot; Kigali, Rwanda
    </p>
  </td>
</tr>
</table></td></tr></table></body></html>';
}

// ── EMAIL WRAPPER ─────────────────────────────────────────────
function emailStart(string $badge = '', string $badge_color = '#0D1B4B'): string {
    $badge_row = $badge ? '
<tr>
  <td style="background:' . $badge_color . ';padding:12px 32px;">
    <p style="margin:0;font-size:14px;font-weight:700;color:#FFFFFF;">' . $badge . '</p>
  </td>
</tr>' : '';

    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"/></head>
<body style="margin:0;padding:0;background:#F4F6FB;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6FB;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(13,27,75,0.10);">
<tr>
  <td style="background:#0D1B4B;padding:22px 32px;">
    <p style="margin:0;font-family:Georgia,serif;font-size:20px;font-weight:700;color:#FFFFFF;">
      Home Help Rwanda
    </p>
    <p style="margin:4px 0 0;font-size:12px;color:rgba(255,255,255,0.55);">homehelp.rw</p>
  </td>
</tr>' . $badge_row . '
<tr><td style="padding:28px 32px;">';
}

// ── 1. NEW BOOKING EMAIL (to admin) ───────────────────────────
function buildBookingEmailHTML(array $data): string {
    $service = htmlspecialchars($data['service_name']  ?? '—');
    $client  = htmlspecialchars($data['user_name']     ?? '—');
    $phone   = htmlspecialchars($data['phone']         ?? '—');
    $email   = htmlspecialchars($data['email']         ?? '—');
    $nb      = htmlspecialchars($data['neighbourhood'] ?? '—');
    $freq    = htmlspecialchars($data['frequency']     ?? '—');
    $langs   = htmlspecialchars($data['languages']     ?? '—');
    $rooms   = htmlspecialchars($data['rooms']         ?? '—');
    $chef    = htmlspecialchars($data['chef_type']     ?? '');
    $people  = htmlspecialchars($data['num_people']    ?? '');
    $live_in = !empty($data['live_in'])  ? 'Live In'  : '';
    $live_out= !empty($data['live_out']) ? 'Live Out' : '';
    $accomm  = $live_in ?: ($live_out ?: '—');

    $chef_row   = $chef   ? '<tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;width:140px;">Chef Type</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $chef . '</td></tr>' : '';
    $people_row = $people ? '<tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">People</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $people . '</td></tr>' : '';

    return emailStart('New Booking Submitted — Action Required', '#E8A020') . '
    <p style="margin:0 0 20px;font-size:15px;color:#1A1A2E;line-height:1.7;">
      A new service booking has been submitted and is awaiting your review.
    </p>

    <p style="margin:0 0 10px;font-size:12px;font-weight:700;color:#0D1B4B;text-transform:uppercase;letter-spacing:0.06em;">Client Information</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
      <tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;width:140px;">Full Name</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $client . '</td></tr>
      <tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">Phone</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $phone . '</td></tr>
      <tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">Email</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $email . '</td></tr>
      <tr><td style="padding:8px 0;font-size:13px;color:#888;">Neighbourhood</td><td style="padding:8px 0;font-size:14px;font-weight:600;color:#1A1A2E;">' . $nb . '</td></tr>
    </table>

    <p style="margin:0 0 10px;font-size:12px;font-weight:700;color:#0D1B4B;text-transform:uppercase;letter-spacing:0.06em;">Booking Details</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
      <tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;width:140px;">Service</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $service . '</td></tr>
      <tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">Frequency</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $freq . '</td></tr>
      <tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">Languages</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $langs . '</td></tr>
      <tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">Rooms</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $rooms . '</td></tr>
      ' . $chef_row . $people_row . '
      <tr><td style="padding:8px 0;font-size:13px;color:#888;">Accommodation</td><td style="padding:8px 0;font-size:14px;font-weight:600;color:#1A1A2E;">' . $accomm . '</td></tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0">
      <tr><td align="center">
        <a href="https://homehelp.rw/admin/bookings" style="display:inline-block;background:#E8A020;color:#FFFFFF;font-size:14px;font-weight:700;padding:14px 32px;border-radius:8px;text-decoration:none;">
          Review on Admin Dashboard
        </a>
      </td></tr>
    </table>
</td></tr>' . emailFooter();
}

// ── 2. UNDER REVIEW EMAIL (to client) ────────────────────────
function buildReviewEmailHTML(array $data): string {
    $client     = htmlspecialchars($data['client_name']  ?? '—');
    $service    = htmlspecialchars($data['service_name'] ?? '—');
    $booking_id = htmlspecialchars($data['booking_id']   ?? '');

    return emailStart('Your Request is Under Review', '#2E5FC2') . '
    <p style="margin:0 0 20px;font-size:15px;color:#1A1A2E;line-height:1.7;">
      Hello <strong>' . $client . '</strong>,<br/><br/>
      Thank you for booking with HomeHelp Rwanda! We have received your request.<br/><br/>
      Our team is reviewing your booking and will contact you shortly to confirm the details and next steps.<br/><br/>
      If you have any questions, simply reply to this message.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
      <tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;width:140px;">Booking ID</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">#' . $booking_id . '</td></tr>
      <tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">Service</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $service . '</td></tr>
      <tr><td style="padding:8px 0;font-size:13px;color:#888;">Status</td><td style="padding:8px 0;font-size:14px;font-weight:700;color:#1E40AF;">Under Review</td></tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0">
      <tr><td align="center">
        <a href="https://homehelp.rw/my-bookings" style="display:inline-block;background:#0D1B4B;color:#FFFFFF;font-size:14px;font-weight:700;padding:14px 32px;border-radius:8px;text-decoration:none;">
          Track My Booking
        </a>
      </td></tr>
    </table>
</td></tr>' . emailFooter();
}

// ── 3. APPROVED EMAIL (to client) ─────────────────────────────
function buildApprovalEmailHTML(array $data): string {
    $client     = htmlspecialchars($data['client_name']  ?? '—');
    $service    = htmlspecialchars($data['service_name'] ?? '—');
    $booking_id = htmlspecialchars($data['booking_id']   ?? '');
    $note       = htmlspecialchars($data['agent_note']   ?? '');
    $note_block = $note ? '<tr><td style="padding-top:16px;"><div style="background:#FFF8EC;border-left:4px solid #E8A020;padding:14px 18px;border-radius:0 8px 8px 0;"><p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#92400E;">NOTE FROM OUR TEAM</p><p style="margin:0;font-size:14px;color:#78350F;line-height:1.6;">' . $note . '</p></div></td></tr>' : '';

    return emailStart('Your Booking Has Been Approved', '#10B981') . '
    <p style="margin:0 0 20px;font-size:15px;color:#1A1A2E;line-height:1.7;">
      Hi <strong>' . $client . '</strong>,<br/><br/>
      Great news! Your booking request for <strong>' . $service . '</strong> has been <strong style="color:#065F46;">approved</strong> by our team. We will be in touch shortly to coordinate the next steps.
    </p>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
      <tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;width:140px;">Booking ID</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">#' . $booking_id . '</td></tr>
      <tr><td style="padding:8px 0;font-size:13px;color:#888;">Service</td><td style="padding:8px 0;font-size:14px;font-weight:600;color:#1A1A2E;">' . $service . '</td></tr>
      ' . $note_block . '
    </table>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;">
      <tr><td align="center">
        <a href="https://homehelp.rw/my-bookings" style="display:inline-block;background:#0D1B4B;color:#FFFFFF;font-size:14px;font-weight:700;padding:14px 32px;border-radius:8px;text-decoration:none;">View My Booking</a>
      </td></tr>
    </table>
</td></tr>' . emailFooter();
}

// ── 4. COMPLETED EMAIL (to client) ────────────────────────────
function buildCompletedEmailHTML(array $data): string {
    $client     = htmlspecialchars($data['client_name']  ?? '—');
    $service    = htmlspecialchars($data['service_name'] ?? '—');
    $booking_id = htmlspecialchars($data['booking_id']   ?? '');
    $note       = htmlspecialchars($data['agent_note']   ?? '');
    $note_block = $note ? '<div style="background:#FFF8EC;border-left:4px solid #E8A020;padding:14px 18px;border-radius:0 8px 8px 0;margin-top:16px;"><p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#92400E;">NOTE FROM OUR TEAM</p><p style="margin:0;font-size:14px;color:#78350F;line-height:1.6;">' . $note . '</p></div>' : '';

    return emailStart('Your HomeHelp Request Has Been Confirmed', '#10B981') . '
    <p style="margin:0 0 20px;font-size:15px;color:#1A1A2E;line-height:1.7;">
      Dear <strong>' . $client . '</strong>,<br/><br/>
      We are delighted to inform you that a <strong>HomeHelp Rwanda worker has been assigned</strong> to your service request for <strong>' . $service . '</strong>.<br/><br/>
      Our team will contact you shortly to confirm the start date and provide all necessary details about your assigned worker. Please ensure your phone is reachable.
    </p>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
      <tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;width:140px;">Booking ID</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">#' . $booking_id . '</td></tr>
      <tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;">Service</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">' . $service . '</td></tr>
      <tr><td style="padding:8px 0;font-size:13px;color:#888;">Status</td><td style="padding:8px 0;font-size:14px;font-weight:700;color:#065F46;">Completed</td></tr>
    </table>
    ' . $note_block . '
    <div style="background:#ECFDF5;border:1.5px solid #BBF7D0;border-radius:10px;padding:16px 20px;margin-top:20px;margin-bottom:24px;">
      <p style="margin:0 0 6px;font-size:13px;font-weight:700;color:#065F46;">What happens next?</p>
      <p style="margin:0;font-size:13px;color:#047857;line-height:1.7;">Our team will reach out to confirm start date and worker details. Thank you for choosing HomeHelp Rwanda!</p>
    </div>
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr><td align="center">
        <a href="https://homehelp.rw/my-bookings" style="display:inline-block;background:#0D1B4B;color:#FFFFFF;font-size:14px;font-weight:700;padding:14px 32px;border-radius:8px;text-decoration:none;">View My Booking</a>
      </td></tr>
    </table>
</td></tr>' . emailFooter();
}

// ── 5. HOLD EMAIL (to client) ─────────────────────────────────
function buildHoldEmailHTML(array $data): string {
    $client     = htmlspecialchars($data['client_name']  ?? '—');
    $service    = htmlspecialchars($data['service_name'] ?? '—');
    $booking_id = htmlspecialchars($data['booking_id']   ?? '');
    $note       = htmlspecialchars($data['agent_note']   ?? '');
    $note_block = $note ? '<div style="background:#FFF8EC;border-left:4px solid #E8A020;padding:14px 18px;border-radius:0 8px 8px 0;margin-top:16px;"><p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#92400E;">ADDITIONAL INFORMATION</p><p style="margin:0;font-size:14px;color:#78350F;line-height:1.6;">' . $note . '</p></div>' : '';

    return emailStart('Update on Your HomeHelp Request', '#F59E0B') . '
    <p style="margin:0 0 20px;font-size:15px;color:#1A1A2E;line-height:1.7;">
      Dear <strong>' . $client . '</strong>,<br/><br/>
      We regret to inform you that, unfortunately, your service request for <strong>' . $service . '</strong> could not proceed at this time.<br/><br/>
      We sincerely apologize for any inconvenience. Please feel free to contact us directly or submit a new request and we will do our best to assist you.
    </p>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
      <tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;width:140px;">Booking ID</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">#' . $booking_id . '</td></tr>
      <tr><td style="padding:8px 0;font-size:13px;color:#888;">Service</td><td style="padding:8px 0;font-size:14px;font-weight:600;color:#1A1A2E;">' . $service . '</td></tr>
    </table>
    ' . $note_block . '
    <div style="background:#FFFBEB;border:1.5px solid #FCD34D;border-radius:10px;padding:16px 20px;margin-top:16px;margin-bottom:24px;">
      <p style="margin:0 0 6px;font-size:13px;font-weight:700;color:#92400E;">What can you do?</p>
      <p style="margin:0;font-size:13px;color:#78350F;line-height:1.7;">Contact our team directly using the information below, or submit a new booking request on our website. We are happy to help.</p>
    </div>
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td align="center" style="padding-right:8px;" width="50%">
          <a href="https://homehelp.rw/register" style="display:inline-block;background:#0D1B4B;color:#FFFFFF;font-size:13px;font-weight:700;padding:12px 24px;border-radius:8px;text-decoration:none;">Book Again</a>
        </td>
        <td align="left" width="50%">
          <a href="mailto:info@homehelp.rw" style="display:inline-block;background:#F59E0B;color:#FFFFFF;font-size:13px;font-weight:700;padding:12px 24px;border-radius:8px;text-decoration:none;">Contact Us</a>
        </td>
      </tr>
    </table>
</td></tr>' . emailFooter();
}

// ── 6. APPLICATION APPROVED (to job seeker) ───────────────────
function buildApplicationApprovalEmailHTML(array $data): string {
    $applicant = htmlspecialchars($data['applicant_name'] ?? '—');
    $service   = htmlspecialchars($data['service_name']   ?? '—');
    $note      = htmlspecialchars($data['agent_note']     ?? '');
    $app_id    = htmlspecialchars($data['app_id']         ?? '');
    $note_block = $note ? '<div style="background:#FFF8EC;border-left:4px solid #E8A020;padding:14px 18px;border-radius:0 8px 8px 0;margin-top:16px;"><p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#92400E;">NOTE FROM OUR TEAM</p><p style="margin:0;font-size:14px;color:#78350F;line-height:1.6;">' . $note . '</p></div>' : '';

    return emailStart('Your Job Application Has Been Approved', '#10B981') . '
    <p style="margin:0 0 20px;font-size:15px;color:#1A1A2E;line-height:1.7;">
      Hi <strong>' . $applicant . '</strong>,<br/><br/>
      Congratulations! Your job application for <strong>' . $service . '</strong> has been <strong style="color:#065F46;">approved</strong> by our team. A member of our team will contact you shortly to discuss the next steps.
    </p>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
      <tr><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:13px;color:#888;width:160px;">Application ID</td><td style="padding:8px 0;border-bottom:1px solid #F2EDE3;font-size:14px;font-weight:600;color:#1A1A2E;">#' . $app_id . '</td></tr>
      <tr><td style="padding:8px 0;font-size:13px;color:#888;">Applied For</td><td style="padding:8px 0;font-size:14px;font-weight:600;color:#1A1A2E;">' . $service . '</td></tr>
    </table>
    ' . $note_block . '
    <div style="background:#EFF6FF;border:1.5px solid #BFDBFE;border-radius:10px;padding:16px 20px;margin-top:16px;margin-bottom:24px;">
      <p style="margin:0 0 6px;font-size:13px;font-weight:700;color:#1E40AF;">What happens next?</p>
      <p style="margin:0;font-size:13px;color:#1E40AF;line-height:1.7;">Our team will reach out via phone or email to confirm your start date and placement details. Please ensure your contact information is up to date.</p>
    </div>
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr><td align="center">
        <a href="https://homehelp.rw/worker-login" style="display:inline-block;background:#0D1B4B;color:#FFFFFF;font-size:14px;font-weight:700;padding:14px 32px;border-radius:8px;text-decoration:none;">Log In to Your Account</a>
      </td></tr>
    </table>
</td></tr>' . emailFooter();
}