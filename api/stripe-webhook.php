<?php
/**
 * Stripe webhook receiver.
 *
 * Point your Stripe dashboard webhook at:
 *   https://your-domain/api/stripe-webhook.php
 * and subscribe to:
 *   checkout.session.completed
 *   checkout.session.expired
 *   charge.refunded
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/stripe.php';
require_once __DIR__ . '/../includes/mailer.php';

$payload = file_get_contents('php://input') ?: '';
$sig     = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

$error = null;
$event = stripe_verify_webhook($payload, $sig, $error);

if ($event === null) {
    app_log('errors.log', 'Stripe webhook rejected: ' . (string)$error);
    http_response_code(400);
    echo 'Invalid signature';
    exit;
}

$type   = (string)($event['type'] ?? '');
$object = (array)($event['data']['object'] ?? []);

app_log('stripe.log', 'Event ' . $type . ' (' . (string)($event['id'] ?? '') . ')');

/** Locate the booking this event refers to. */
function booking_for_event(array $object): ?array
{
    $ref = (string)($object['client_reference_id'] ?? ($object['metadata']['reference'] ?? ''));
    if ($ref !== '') {
        $b = db_one('SELECT * FROM `bookings` WHERE `reference` = ? LIMIT 1', [$ref]);
        if ($b) return $b;
    }
    $sid = (string)($object['id'] ?? '');
    if ($sid !== '') {
        $b = db_one('SELECT * FROM `bookings` WHERE `stripe_session_id` = ? LIMIT 1', [$sid]);
        if ($b) return $b;
    }
    $pi = (string)($object['payment_intent'] ?? '');
    if ($pi !== '') {
        return db_one('SELECT * FROM `bookings` WHERE `stripe_payment_intent` = ? LIMIT 1', [$pi]);
    }
    return null;
}

try {
    switch ($type) {

        case 'checkout.session.completed': {
            $booking = booking_for_event($object);
            if (!$booking) {
                app_log('stripe.log', 'No booking matched a completed session.');
                break;
            }

            $was_unpaid = ($booking['payment_status'] === 'unpaid');

            // Same rules as the success redirect, so the two routes can
            // never disagree. Idempotent if the redirect already ran.
            if (!stripe_apply_paid_session($booking, $object)) {
                app_log('stripe.log', 'Session completed but was not applied (not paid, or mismatched booking).');
                break;
            }

            $is_deposit = (($object['metadata']['is_deposit'] ?? 'no') === 'yes');
            app_log('stripe.log', 'Booking ' . $booking['reference'] . ' → '
                                . ($is_deposit ? 'deposit_paid' : 'paid'));

            // Stripe retries webhooks; only email the operator the first time.
            if (!$was_unpaid) {
                break;
            }

            // Tell the operator the money landed.
            try {
                $fresh = db_one('SELECT * FROM `bookings` WHERE `id` = ? LIMIT 1', [(int)$booking['id']]);
                if ($fresh) {
                    send_mail(
                        ADMIN_EMAIL,
                        'PAYMENT RECEIVED — ' . $fresh['reference'] . ' — ' . money((float)$fresh['total']),
                        email_shell('Payment received',
                            '<p style="margin:0 0 16px;">Payment has been received for booking '
                            . '<strong style="color:#d4af37;">' . e($fresh['reference']) . '</strong>'
                            . ($is_deposit ? ' (deposit)' : ' (paid in full)') . '.</p>'
                            . booking_detail_html($fresh)
                            . email_button(rtrim(SITE_URL, '/') . '/admin/booking-view.php?id='
                                           . (int)$fresh['id'], 'Open in Admin')),
                        (string)$fresh['email']
                    );
                }
            } catch (Throwable $ex) {
                app_log('errors.log', 'payment email failed: ' . $ex->getMessage());
            }
            break;
        }

        case 'checkout.session.expired': {
            $booking = booking_for_event($object);
            if ($booking) {
                app_log('stripe.log', 'Checkout expired for ' . $booking['reference']);
            }
            break;
        }

        case 'charge.refunded': {
            $pi = (string)($object['payment_intent'] ?? '');
            if ($pi !== '') {
                db_exec("UPDATE `bookings` SET `payment_status` = 'refunded'
                         WHERE `stripe_payment_intent` = ?", [$pi]);
                app_log('stripe.log', 'Refund recorded for payment intent ' . $pi);
            }
            break;
        }

        default:
            // Acknowledge everything else so Stripe stops retrying.
            break;
    }
} catch (Throwable $ex) {
    app_log('errors.log', 'Stripe webhook handler error: ' . $ex->getMessage());
    http_response_code(500);
    echo 'Handler error';
    exit;
}

http_response_code(200);
echo 'OK';
