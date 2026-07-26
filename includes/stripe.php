<?php
/**
 * Stripe Checkout — minimal REST client (no Composer dependency).
 *
 * Card, Apple Pay and Google Pay are all enabled automatically by
 * Stripe Checkout when 'card' is the payment method type.
 *
 * Everything degrades safely: with no keys configured, stripe_enabled()
 * is false and the site simply never offers the payment step.
 */
require_once __DIR__ . '/functions.php';

/**
 * Low-level POST to the Stripe API (form-encoded, as Stripe expects).
 *
 * @return array{ok:bool, status:int, data:array, error:?string}
 */
function stripe_request(string $endpoint, array $params = [], string $method = 'POST'): array
{
    if (trim(STRIPE_SECRET_KEY) === '') {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'Stripe is not configured.'];
    }

    $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');
    $body = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    if (!function_exists('curl_init')) {
        app_log('errors.log', 'Stripe: cURL extension is not available.');
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'Payment service unavailable.'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $method === 'GET' && $body !== '' ? $url . '?' . $body : $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . STRIPE_SECRET_KEY,
            'Content-Type: application/x-www-form-urlencoded',
            'Stripe-Version: 2024-06-20',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr   = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        app_log('errors.log', 'Stripe cURL error: ' . $cerr);
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'Could not reach the payment service.'];
    }

    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'Unexpected payment service response.'];
    }

    if ($status >= 400) {
        $msg = (string)($data['error']['message'] ?? 'Payment request failed.');
        app_log('errors.log', "Stripe API $status: $msg");
        return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => $msg];
    }

    return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => null];
}

/**
 * Create a Checkout Session for a booking.
 * Returns the redirect URL, or null with $error set.
 */
function stripe_create_checkout(array $booking, ?string &$error = null): ?string
{
    if (!stripe_enabled()) {
        $error = 'Online payment is not enabled.';
        return null;
    }

    $total = (float)$booking['total'];
    $pct   = max(1.0, min(100.0, setting_num('deposit_percent', 100)));
    $charge = round($total * $pct / 100, 2);

    if ($charge < 0.50) {
        $error = 'That amount is too small to charge online.';
        return null;
    }

    $is_deposit = $pct < 100;
    $label = sprintf('%s — %s',
        service_label((string)$booking['service_type']),
        (string)$booking['vehicle_name']);

    $desc = sprintf('Booking %s · %s%s',
        $booking['reference'],
        fmt_datetime($booking['pickup_at']),
        $is_deposit ? sprintf(' · %d%% deposit of %s', (int)$pct, money($total)) : '');

    $base = rtrim(SITE_URL, '/');

    $params = [
        'mode'                 => 'payment',
        'client_reference_id'  => (string)$booking['reference'],
        'customer_email'       => (string)$booking['email'],
        'success_url'          => $base . '/confirmation.php?ref=' . urlencode((string)$booking['reference']) . '&paid=1',
        'cancel_url'           => $base . '/confirmation.php?ref=' . urlencode((string)$booking['reference']) . '&paid=0',

        'payment_method_types[0]' => 'card',

        'line_items[0][quantity]'                       => 1,
        'line_items[0][price_data][currency]'           => STRIPE_CURRENCY,
        'line_items[0][price_data][unit_amount]'        => (int)round($charge * 100),
        'line_items[0][price_data][product_data][name]' => mb_substr($label, 0, 250),
        'line_items[0][price_data][product_data][description]' => mb_substr($desc, 0, 250),

        'metadata[booking_id]'  => (string)$booking['id'],
        'metadata[reference]'   => (string)$booking['reference'],
        'metadata[is_deposit]'  => $is_deposit ? 'yes' : 'no',
        'metadata[full_total]'  => (string)$total,
    ];

    $res = stripe_request('checkout/sessions', $params);

    if (!$res['ok']) {
        $error = $res['error'] ?: 'Could not start the payment.';
        return null;
    }

    $session_id = (string)($res['data']['id'] ?? '');
    $url        = (string)($res['data']['url'] ?? '');

    if ($session_id !== '') {
        db_exec('UPDATE `bookings` SET `stripe_session_id` = ? WHERE `id` = ?',
                [$session_id, (int)$booking['id']]);
    }

    if ($url === '') {
        $error = 'The payment service did not return a checkout link.';
        return null;
    }

    return $url;
}

/**
 * Verify a Stripe webhook signature (v1 scheme). Returns the decoded
 * event, or null when the signature is missing/invalid/stale.
 */
function stripe_verify_webhook(string $payload, string $sig_header, ?string &$error = null): ?array
{
    $secret = trim(STRIPE_WEBHOOK_SECRET);
    if ($secret === '') {
        $error = 'Webhook secret is not configured.';
        return null;
    }

    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $sig_header) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) continue;
        if ($kv[0] === 't')  { $timestamp = $kv[1]; }
        if ($kv[0] === 'v1') { $signatures[] = $kv[1]; }
    }

    if ($timestamp === null || !$signatures) {
        $error = 'Malformed signature header.';
        return null;
    }

    // Reject events older than 5 minutes (replay protection).
    if (abs(time() - (int)$timestamp) > 300) {
        $error = 'Signature timestamp is outside the tolerance window.';
        return null;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

    $match = false;
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) { $match = true; break; }
    }
    if (!$match) {
        $error = 'Signature verification failed.';
        return null;
    }

    $event = json_decode($payload, true);
    if (!is_array($event)) {
        $error = 'Event payload was not valid JSON.';
        return null;
    }
    return $event;
}
