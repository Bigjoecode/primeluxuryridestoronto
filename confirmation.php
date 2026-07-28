<?php
/** Booking confirmation. */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/stripe.php';

$page_slug        = 'confirmation';
$page_title       = 'Booking Confirmed';
$page_description = 'Your booking with Prime Luxury Rides Toronto has been received.';

$ref     = trim((string)($_GET['ref'] ?? ''));
$booking = $ref !== ''
    ? db_one('SELECT * FROM `bookings` WHERE `reference` = ? LIMIT 1', [$ref])
    : null;

$breakdown = [];
if ($booking && !empty($booking['price_breakdown'])) {
    $decoded = json_decode((string)$booking['price_breakdown'], true);
    if (is_array($decoded)) {
        $breakdown = $decoded;
    }
}

// Payment return states (set by Stripe success/cancel URLs)
$paid_flag  = (string)($_GET['paid'] ?? '');
$pay_error  = trim((string)($_GET['pay_error'] ?? ''));

/*
 * Confirm payment on the return trip rather than waiting for the webhook.
 *
 * The webhook is the reliable backstop — it fires even if the customer
 * closes the tab — but it may not be configured yet, and a customer who
 * has just paid should never be shown "unpaid". We ask Stripe directly
 * what happened to the session and record it. Both routes call the same
 * function, so they cannot disagree, and it is idempotent.
 */
if ($booking && $paid_flag === '1'
    && stripe_enabled()
    && $booking['payment_status'] === 'unpaid'
    && !empty($booking['stripe_session_id'])) {

    try {
        $session = stripe_get_session((string)$booking['stripe_session_id']);
        if ($session && stripe_apply_paid_session($booking, $session)) {
            // Re-read so the page below renders the new status.
            $booking = db_one('SELECT * FROM `bookings` WHERE `id` = ? LIMIT 1',
                              [(int)$booking['id']]);
        }
    } catch (Throwable $ex) {
        app_log('errors.log', 'payment confirm failed: ' . $ex->getMessage());
    }
}
$can_pay    = $booking
           && stripe_enabled()
           && $booking['payment_status'] === 'unpaid'
           && $booking['status'] !== 'cancelled';

$deposit_pct = setting_num('deposit_percent', 100);
$pay_amount  = $booking
    ? round((float)$booking['total'] * max(1.0, min(100.0, $deposit_pct)) / 100, 2)
    : 0.0;

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container container--narrow">

  <?php if (!$booking): ?>

    <div class="alert alert--error">
      <?= icon('alert') ?>
      <span>We couldn&rsquo;t find that booking reference. If you have just booked, please check
        your email for confirmation, or <a href="contact.php">contact us</a> and we&rsquo;ll
        look it up for you.</span>
    </div>
    <div class="btn-row mt-6">
      <a href="booking.php" class="btn btn--gold"><?= icon('calendar') ?><span>Make a Booking</span></a>
      <a href="index.php" class="btn btn--outline"><span>Back to Home</span></a>
    </div>

  <?php else: ?>

    <div class="center">
      <div style="width:82px;height:82px;margin:0 auto var(--s-5);border-radius:50%;
                  display:grid;place-items:center;background:var(--gold-soft);
                  border:1px solid var(--gold);color:var(--gold);">
        <?= icon('check', '', 40) ?>
      </div>

      <span class="eyebrow eyebrow--center" style="justify-content:center;">Confirmed</span>
      <h1 class="page-head__title">Thank you, <span class="gold-text"><?=
        e(explode(' ', trim((string)$booking['full_name']))[0]) ?></span></h1>
      <p class="page-head__lead" style="margin-inline:auto;">
        Your booking request has been received. A confirmation has been sent to
        <strong><?= e($booking['email']) ?></strong>, and our reservations team will confirm
        your chauffeur and vehicle shortly.
      </p>

      <p style="margin-top:var(--s-6);font-size:var(--fs-sm);letter-spacing:.16em;
                text-transform:uppercase;color:var(--text-dim);">Your reference</p>
      <p style="font-family:var(--font-display);font-size:var(--fs-2xl);color:var(--gold);
                letter-spacing:.04em;"><?= e($booking['reference']) ?></p>
    </div>

  <?php endif; ?>
  </div>
</section>


<?php if ($booking): ?>
<section class="section" style="padding-top:var(--s-7);">
  <div class="container container--narrow">

    <div class="card mb-6">
      <h2 class="card__title mb-5">Booking summary</h2>

      <dl>
        <?php
        $rows = [
          'Service'      => service_label($booking['service_type']),
          'Vehicle'      => $booking['vehicle_name'],
          'Pickup'       => $booking['pickup_address'],
          'Drop-off'     => $booking['dropoff_address'],
          'Date & time'  => fmt_datetime($booking['pickup_at']),
          'Return'       => $booking['return_at'] ? fmt_datetime($booking['return_at']) : '',
          'Duration'     => $booking['hours'] ? $booking['hours'] . ' hours' : '',
          'Distance'     => $booking['distance_km'] ? number_format((float)$booking['distance_km'], 1) . ' km' : '',
          'Flight'       => $booking['flight_number'],
          'Passengers'   => $booking['passengers'],
          'Luggage'      => $booking['luggage'] ? $booking['luggage'] . ' bags' : '',
          'Status'       => ucfirst($booking['status']),
        ];
        foreach ($rows as $label => $value):
          if ($value === null || trim((string)$value) === '') continue; ?>
        <div class="summary__row" style="padding-block:var(--s-3);border-bottom:1px solid var(--line);">
          <dt><?= e($label) ?></dt>
          <dd><?= e((string)$value) ?></dd>
        </div>
        <?php endforeach; ?>
      </dl>

      <?php if (!empty($booking['notes'])): ?>
      <div class="included">
        <h4>Your extra requests</h4>
        <p><?= e_nl($booking['notes']) ?></p>
      </div>
      <?php endif; ?>
    </div>

    <div class="card mb-6">
      <h2 class="card__title mb-5">Price</h2>

      <?php if (!empty($breakdown['lines'])): ?>
        <?php foreach ($breakdown['lines'] as $line): ?>
        <div class="summary__line">
          <span><?= e((string)($line['label'] ?? '')) ?></span>
          <span><?= money((float)($line['amount'] ?? 0)) ?></span>
        </div>
        <?php endforeach; ?>
        <div class="summary__divider"></div>
      <?php endif; ?>

      <div class="summary__line"><span>Subtotal</span><span><?= money((float)$booking['subtotal']) ?></span></div>

      <?php if ((float)$booking['discount'] > 0): ?>
      <div class="summary__line summary__line--discount">
        <span><?= e(ucfirst($booking['membership_tier'])) ?> discount</span>
        <span>&minus; <?= money((float)$booking['discount']) ?></span>
      </div>
      <?php endif; ?>

      <div class="summary__line">
        <span>HST (<?= rtrim(rtrim(number_format((float)($breakdown['hst_rate'] ?? setting_num('hst_rate', DEFAULT_HST_RATE)), 2), '0'), '.') ?>%)</span>
        <span><?= money((float)$booking['hst']) ?></span>
      </div>

      <dl class="summary__total">
        <dt>Total (CAD)</dt>
        <dd><?= money((float)$booking['total']) ?></dd>
      </dl>

      <?php if ($booking['payment_status'] === 'paid'): ?>
        <div class="alert alert--success mt-5" style="margin-bottom:0;">
          <?= icon('check-circle') ?><span>Payment received in full. Thank you.</span>
        </div>

      <?php elseif ($booking['payment_status'] === 'deposit_paid'): ?>
        <div class="alert alert--success mt-5" style="margin-bottom:0;">
          <?= icon('check-circle') ?>
          <span>Deposit received. The balance is payable to your chauffeur on the day.</span>
        </div>

      <?php elseif ($can_pay): ?>
        <?php if ($paid_flag === '0'): ?>
        <div class="alert alert--info mt-5">
          <?= icon('info') ?>
          <span>Your payment was cancelled &mdash; nothing has been charged.
            Your booking is still held. You can pay below whenever you&rsquo;re ready.</span>
        </div>
        <?php endif; ?>

        <?php if ($pay_error !== ''): ?>
        <div class="alert alert--error mt-5">
          <?= icon('alert') ?>
          <span><?= e($pay_error) ?> Please call us on
            <a href="<?= e(tel_url()) ?>"><?= e(setting('phone')) ?></a> to pay by phone.</span>
        </div>
        <?php endif; ?>

        <a class="btn btn--gold btn--block btn--lg mt-5"
           href="api/stripe-checkout.php?ref=<?= e(urlencode($booking['reference'])) ?>">
          <?= icon('lock') ?>
          <span>Pay <?= money($pay_amount) ?><?= $deposit_pct < 100 ? ' deposit' : '' ?> securely</span>
        </a>

        <p class="summary__note center">
          Card, Apple&nbsp;Pay and Google&nbsp;Pay accepted. Payments are processed
          securely by Stripe &mdash; we never see your card details.
        </p>

      <?php else: ?>
        <p class="summary__note">
          Payment is arranged once your booking is confirmed by our team.
        </p>
      <?php endif; ?>
    </div>

    <div class="card mb-6">
      <h2 class="card__title mb-4">Included with your journey</h2>
      <p class="card__text mb-5"><strong style="color:var(--text);">Meet &amp; Greet Service</strong><br>
        Your professional chauffeur will meet you at your pickup location, open the door for you,
        and assist with your luggage to ensure a seamless and comfortable experience.</p>
      <p class="card__text"><strong style="color:var(--text);">Onboard Amenities</strong><br>
        All PRIME vehicles include complimentary bottled water, Wi-Fi connection,
        and reading material to enhance your journey.</p>
    </div>

    <div class="alert alert--gold">
      <?= icon('info') ?>
      <span>Need to change or cancel? Call us on
        <a href="<?= e(tel_url()) ?>"><?= e(setting('phone')) ?></a> or reply to your
        confirmation email, quoting <strong><?= e($booking['reference']) ?></strong>.
        We&rsquo;re available <?= e(setting('hours', '24/7')) ?>.</span>
    </div>

    <div class="btn-row btn-row--center mt-7">
      <a href="index.php" class="btn btn--gold"><span>Back to Home</span></a>
      <a href="<?= e(whatsapp_url('Hello, I would like to ask about booking ' . $booking['reference'] . '.')) ?>"
         target="_blank" rel="noopener noreferrer" class="btn btn--outline">
        <?= icon('whatsapp') ?><span>WhatsApp Us</span>
      </a>
      <button type="button" class="btn btn--ghost" onclick="window.print()">
        <?= icon('download') ?><span>Print / Save</span>
      </button>
    </div>

  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
