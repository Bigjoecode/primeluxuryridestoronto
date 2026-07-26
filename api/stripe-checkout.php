<?php
/**
 * Start a Stripe Checkout session for an existing booking and redirect.
 *   GET /api/stripe-checkout.php?ref=PLR-2026-0001
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/stripe.php';

$ref     = trim((string)($_GET['ref'] ?? ''));
$fallback = '../confirmation.php?ref=' . urlencode($ref);

if ($ref === '') {
    header('Location: ../booking.php');
    exit;
}

if (!stripe_enabled()) {
    header('Location: ' . $fallback . '&pay_error=' . urlencode('Online payment is not enabled yet.'));
    exit;
}

$booking = db_one('SELECT * FROM `bookings` WHERE `reference` = ? LIMIT 1', [$ref]);

if (!$booking) {
    header('Location: ../booking.php');
    exit;
}

if ($booking['payment_status'] === 'paid') {
    header('Location: ' . $fallback);
    exit;
}

$error = null;
$url   = stripe_create_checkout($booking, $error);

if ($url === null) {
    app_log('errors.log', 'Stripe checkout failed for ' . $ref . ': ' . (string)$error);
    header('Location: ' . $fallback . '&pay_error=' . urlencode((string)$error));
    exit;
}

header('Location: ' . $url, true, 303);
exit;
