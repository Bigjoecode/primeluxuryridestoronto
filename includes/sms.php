<?php
/**
 * SMS via Twilio — optional and config-gated.
 *
 * With no credentials configured, sms_enabled() is false and every call
 * becomes a no-op that still writes to logs/sms.log, so the operator can
 * see exactly what would have been sent.
 */
require_once __DIR__ . '/functions.php';

function sms_enabled(): bool
{
    return defined('TWILIO_SID') && trim(TWILIO_SID) !== ''
        && defined('TWILIO_TOKEN') && trim(TWILIO_TOKEN) !== ''
        && defined('TWILIO_FROM') && trim(TWILIO_FROM) !== '';
}

/**
 * Normalise a Canadian number to E.164 (+1XXXXXXXXXX).
 * Returns '' when the input cannot be read as a phone number.
 */
function sms_normalise(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if ($digits === '') {
        return '';
    }
    if (str_starts_with($phone, '+')) {
        return '+' . $digits;
    }
    if (strlen($digits) === 10) {
        return '+1' . $digits;          // Canadian/US local
    }
    if (strlen($digits) === 11 && $digits[0] === '1') {
        return '+' . $digits;
    }
    return '+' . $digits;
}

/**
 * Send an SMS. Returns true when Twilio accepted it.
 * Always logs, whether or not sending is configured.
 */
function send_sms(string $to, string $message): bool
{
    $to      = sms_normalise($to);
    $message = trim($message);

    if ($to === '' || $message === '') {
        return false;
    }

    app_log('sms.log', sprintf('TO: %s | %s', $to, str_replace("\n", ' / ', $message)));

    if (!sms_enabled()) {
        app_log('sms.log', 'NOT SENT — Twilio is not configured.');
        return false;
    }
    if (!function_exists('curl_init')) {
        app_log('errors.log', 'SMS: cURL extension is not available.');
        return false;
    }

    $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
                   rawurlencode(TWILIO_SID));

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERPWD        => TWILIO_SID . ':' . TWILIO_TOKEN,
        CURLOPT_POSTFIELDS     => http_build_query([
            'From' => TWILIO_FROM,
            'To'   => $to,
            'Body' => mb_substr($message, 0, 1500),
        ]),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr   = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        app_log('errors.log', 'SMS cURL error: ' . $cerr);
        return false;
    }
    if ($status >= 400) {
        $body = json_decode((string)$raw, true);
        app_log('errors.log', 'Twilio ' . $status . ': '
            . (string)($body['message'] ?? substr((string)$raw, 0, 200)));
        return false;
    }

    app_log('sms.log', 'Sent OK (HTTP ' . $status . ')');
    return true;
}

/**
 * Public tracking URL for a booking.
 */
function track_url(array $booking): string
{
    if (empty($booking['track_token'])) {
        return rtrim(SITE_URL, '/') . '/confirmation.php?ref='
             . urlencode((string)$booking['reference']);
    }
    return rtrim(SITE_URL, '/') . '/track.php?t=' . urlencode((string)$booking['track_token']);
}

/**
 * The "your chauffeur is assigned" message, used for both SMS and email.
 */
function driver_assigned_text(array $booking, array $driver, ?array $vehicle): string
{
    $first = explode(' ', trim((string)$booking['full_name']))[0] ?: 'there';
    $car   = (string)($booking['vehicle_name'] ?? 'your vehicle');
    $plate = $vehicle['plate'] ?? '';

    $lines = [];
    $lines[] = sprintf('Hi %s — your %s chauffeur is confirmed.', $first, SITE_NAME);
    $lines[] = sprintf('%s will collect you at %s.',
        (string)$driver['full_name'], fmt_datetime($booking['pickup_at']));
    $lines[] = sprintf('Vehicle: %s%s.', $car, $plate !== '' ? ' (' . $plate . ')' : '');
    $lines[] = sprintf('Driver: %s', (string)$driver['phone']);
    $lines[] = 'Track: ' . track_url($booking);
    $lines[] = 'Ref ' . (string)$booking['reference'];

    return implode("\n", $lines);
}
