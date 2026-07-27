<?php
/**
 * Public ride tracking.
 *
 * Reachable only with the booking's unguessable token, so it needs no
 * login. Deliberately shows nothing sensitive: no price, no email, no
 * home address history — just what the passenger needs on the day.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/sms.php';

$token = trim((string)($_GET['t'] ?? ''));

$booking = $token !== '' && preg_match('/^[a-f0-9]{8,32}$/i', $token)
    ? db_one('SELECT * FROM `bookings` WHERE `track_token` = ? LIMIT 1', [$token])
    : null;

$driver  = $booking && !empty($booking['driver_id'])
    ? db_one('SELECT * FROM `drivers` WHERE `id` = ? LIMIT 1', [(int)$booking['driver_id']])
    : null;

$vehicle = $booking && !empty($booking['vehicle_id'])
    ? get_vehicle((int)$booking['vehicle_id'])
    : null;

$page_slug        = 'track';
$page_title       = $booking ? 'Track ride ' . $booking['reference'] : 'Track your ride';
$page_description = 'Track your Prime Luxury Rides booking.';
// A private link — never index it, and don't leak the token via Referer.
$page_robots      = 'noindex, nofollow, noarchive';

require __DIR__ . '/includes/header.php';
?>

<?php if (!$booking): ?>
<section class="page-head">
  <div class="container container--narrow center">
    <h1 class="page-head__title">Ride not found</h1>
    <p class="page-head__lead" style="margin-inline:auto;">
      That tracking link isn&rsquo;t valid or has expired. Please check the link in your
      confirmation email, or call us and we&rsquo;ll help straight away.
    </p>
    <div class="btn-row btn-row--center mt-7">
      <a href="<?= e(tel_url()) ?>" class="btn btn--gold btn--lg">
        <?= icon('phone') ?><span>Call <?= e(setting('phone')) ?></span>
      </a>
      <a href="index.php" class="btn btn--outline btn--lg">Back to Home</a>
    </div>
  </div>
</section>

<?php else:
  // Progress through the journey, for the step indicator.
  $stages = ['pending' => 1, 'confirmed' => 2, 'assigned' => 3, 'completed' => 4, 'cancelled' => 0];
  $stage  = $stages[$booking['status']] ?? 1;
  $labels = [1 => 'Received', 2 => 'Confirmed', 3 => 'Chauffeur assigned', 4 => 'Completed'];
?>

<section class="page-head" style="padding-bottom:var(--s-6);">
  <div class="container container--narrow center">
    <span class="eyebrow eyebrow--center" style="justify-content:center;">Live status</span>
    <h1 class="page-head__title" style="font-size:var(--fs-2xl);">
      <?= e($booking['reference']) ?>
    </h1>
    <p class="page-head__lead" style="margin-inline:auto;">
      <?= e(service_label($booking['service_type'])) ?>
      &middot; <?= e(fmt_datetime($booking['pickup_at'])) ?>
    </p>
  </div>
</section>

<section class="section" style="padding-top:var(--s-5);">
  <div class="container container--narrow">

    <?php if ($booking['status'] === 'cancelled'): ?>
    <div class="alert alert--error">
      <?= icon('alert') ?>
      <span>This booking has been cancelled. If that&rsquo;s unexpected, please call us on
        <a href="<?= e(tel_url()) ?>"><?= e(setting('phone')) ?></a>.</span>
    </div>
    <?php else: ?>

    <!-- Progress -->
    <div class="progress mb-7">
      <div class="progress__track">
        <div class="progress__bar" style="width:<?= (int)($stage / 4 * 100) ?>%"></div>
      </div>
      <div class="progress__steps">
        <?php foreach ($labels as $n => $label): ?>
        <div class="progress__step <?= $n === $stage ? 'is-active' : ($n < $stage ? 'is-done' : '') ?>">
          <span class="progress__dot"><?= $n < $stage ? icon('check') : $n ?></span>
          <span class="progress__label"><?= e($label) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ══ CHAUFFEUR ════════════════════════════════════════════════ -->
    <?php if ($driver && $booking['status'] !== 'cancelled'): ?>
    <div class="card mb-6" style="border-color:var(--gold-line);">
      <div style="display:flex;align-items:center;gap:var(--s-5);flex-wrap:wrap;">
        <span style="width:60px;height:60px;border-radius:50%;flex:none;display:grid;
                     place-items:center;background:var(--gold-grad);color:#0a0a0a;
                     font-family:var(--font-display);font-size:var(--fs-xl);">
          <?= e(strtoupper(mb_substr((string)$driver['full_name'], 0, 1))) ?>
        </span>
        <div style="min-width:0;flex:1;">
          <p style="font-size:var(--fs-xs);letter-spacing:.14em;text-transform:uppercase;color:var(--text-dim);">
            Your chauffeur</p>
          <p style="font-family:var(--font-display);font-size:var(--fs-xl);">
            <?= e($driver['full_name']) ?></p>
        </div>
        <a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string)$driver['phone'])) ?>"
           class="btn btn--gold">
          <?= icon('phone') ?><span>Call chauffeur</span>
        </a>
      </div>

      <div class="spec-row mt-5" style="border-bottom:0;">
        <span class="spec"><?= icon('car') ?><?= e((string)$booking['vehicle_name']) ?></span>
        <?php if (!empty($vehicle['plate'])): ?>
        <span class="spec"><?= icon('tag') ?><strong class="text-gold"><?= e($vehicle['plate']) ?></strong></span>
        <?php endif; ?>
      </div>
    </div>

    <?php elseif ($booking['status'] !== 'cancelled'): ?>
    <div class="alert alert--info">
      <?= icon('clock') ?>
      <span>We&rsquo;re assigning your chauffeur now. You&rsquo;ll get an email
        <?= sms_enabled() ? 'and a text ' : '' ?>with their name, the vehicle and its
        number plate as soon as it&rsquo;s confirmed.</span>
    </div>
    <?php endif; ?>

    <!-- ══ JOURNEY ══════════════════════════════════════════════════ -->
    <div class="card mb-6">
      <h2 class="card__title mb-5">Your journey</h2>
      <dl>
        <?php
        $rows = [
          'Pickup'      => (string)$booking['pickup_address'],
          'Drop-off'    => (string)($booking['dropoff_address'] ?? ''),
          'Date & time' => fmt_datetime($booking['pickup_at']),
          'Return leg'  => $booking['return_at_trip'] ? fmt_datetime($booking['return_at_trip']) : '',
          'Duration'    => $booking['hours'] ? $booking['hours'] . ' hours' : '',
          'Flight'      => (string)($booking['flight_number'] ?? ''),
          'Passengers'  => (string)$booking['passengers'],
          'Vehicle'     => (string)$booking['vehicle_name'],
        ];
        foreach ($rows as $label => $value):
          if (trim((string)$value) === '') continue; ?>
        <div class="summary__row" style="padding-block:var(--s-3);border-bottom:1px solid var(--line);">
          <dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
        </div>
        <?php endforeach; ?>
      </dl>

      <?php
      $stops = $booking['stops'] ? json_decode((string)$booking['stops'], true) : null;
      if (is_array($stops) && $stops): ?>
      <div class="included">
        <h4>Stops along the way</h4>
        <?php foreach ($stops as $i => $st): ?>
        <p><?= (int)$i + 1 ?>. <?= e((string)$st) ?></p>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="card mb-6">
      <h2 class="card__title mb-4">Included with your journey</h2>
      <p class="card__text mb-4"><strong style="color:var(--text);">Meet &amp; Greet Service</strong><br>
        Your professional chauffeur will meet you at your pickup location, open the door for you,
        and assist with your luggage.</p>
      <p class="card__text"><strong style="color:var(--text);">Onboard Amenities</strong><br>
        Complimentary bottled water, Wi-Fi connection and reading material.</p>
    </div>

    <div class="btn-row btn-row--center">
      <a href="<?= e(tel_url()) ?>" class="btn btn--outline">
        <?= icon('phone') ?><span>Call us</span>
      </a>
      <a href="<?= e(whatsapp_url('Hello, I have a question about booking ' . $booking['reference'] . '.')) ?>"
         target="_blank" rel="noopener noreferrer" class="btn btn--outline">
        <?= icon('whatsapp') ?><span>WhatsApp</span>
      </a>
    </div>

  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
