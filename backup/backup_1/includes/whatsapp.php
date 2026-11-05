<?php
// includes/whatsapp.php
// Silent WhatsApp sender via wa-api.me
// Correct endpoint: POST https://wa-api.me/api/wa-accounts/messages

function logWhatsAppAttempt(string $phone, string $message, bool $success): void {
    global $conn;
    if (!isset($conn)) return;
    $status = $success ? 'sent' : 'failed';
    $stmt = $conn->prepare(
        "INSERT INTO whatsapp_log (phone, message, status, created_at) VALUES (?,?,?,NOW())"
    );
    if ($stmt) {
        $stmt->bind_param('sss', $phone, $message, $status);
        $stmt->execute();
    }
}

function sendWhatsApp(string $phone, string $message): bool {
    // ── CONFIG ────────────────────────────────────────────────
    $api_url   = 'https://wa-api.me/api/wa-accounts/messages'; // ✅ correct endpoint
    $api_token = 'YOUR_WA_API_ME_TOKEN_HERE';                  // paste your token here
    // ─────────────────────────────────────────────────────────

    // Normalize phone → international format WITHOUT + sign
    // wa-api.me expects: "250789239908" not "+250789239908"
    $phone = preg_replace('/\s+/', '', $phone);       // strip spaces
    $phone = preg_replace('/^\+/', '', $phone);       // remove leading +
    $phone = preg_replace('/^0/', '250', $phone);     // 07XX → 2507XX (Rwanda)

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
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $curl_err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curl_err) {
        error_log('[WhatsApp] cURL error: ' . $curl_err);
        logWhatsAppAttempt($phone, $message, false);
        return false;
    }

    $data    = json_decode($response, true);
    $success = ($httpCode === 200 || $httpCode === 201);

    if (!$success) {
        error_log('[WhatsApp] Failed. HTTP ' . $httpCode . ' | ' . $response);
    }

    logWhatsAppAttempt($phone, $message, $success);
    return $success;
}