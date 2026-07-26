<?php
/** Fleet. */
require_once __DIR__ . '/includes/functions.php';

$page_slug        = 'fleet';
$page_title       = 'Our Luxury Fleet';
$page_description = 'Mercedes-Benz S580, Cadillac Escalade ESV, Chevrolet Suburban and the Mercedes-Maybach GLS 600. Seats, luggage capacity, features and hourly rates for every vehicle.';

$vehicles = get_vehicles();

// Rich results for the fleet listing
$schema_extra = [
  '@context'        => 'https://schema.org',
  '@type'           => 'ItemList',
  'name'            => 'Prime Luxury Rides Toronto — Fleet',
  'itemListElement' => array_map(fn($i, $v) => [
      '@type'    => 'ListItem',
      'position' => $i + 1,
      'item'     => [
          '@type'       => 'Vehicle',
          'name'        => $v['name'],
          'description' => $v['tagline'],
          'vehicleSeatingCapacity' => (int)$v['passengers'],
      ],
  ], array_keys($vehicles), $vehicles),
];

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="index.php">Home</a><?= icon('chevron-right') ?><span>Fleet</span>
    </nav>
    <span class="eyebrow">The Vehicles</span>
    <h1 class="page-head__title">A compact fleet, <span class="gold-text">impeccably kept</span></h1>
    <p class="page-head__lead">We run a deliberately small selection of late-model vehicles rather
       than a large mixed one &mdash; so the car that arrives is exactly the car you booked.</p>
  </div>
</section>


<!-- ══ VEHICLE DETAIL ═════════════════════════════════════════════ -->
<?php foreach ($vehicles as $i => $v):
  $img    = vehicle_image_url($v);
  $feats  = vehicle_features($v);
  $alt    = ($i % 2 === 1);
  $rates  = get_flat_rates((int)$v['id']);
  $cheap  = null;
  foreach ($rates as $r) { if ($r['price'] !== null) { $cheap = $r; break; } }

  $allowed = [];
  if ((int)$v['allow_airport'])      $allowed[] = 'Airport Transfers';
  if ((int)$v['allow_city'])         $allowed[] = 'In-City Rides';
  if ((int)$v['allow_city_to_city']) $allowed[] = 'City to City';
  if ((int)$v['allow_hourly'])       $allowed[] = 'Hourly Chauffeur';
?>
<section class="section <?= $alt ? 'section--alt' : '' ?>" id="<?= e($v['slug']) ?>">
  <div class="container">
    <div class="split <?= $alt ? 'split--reverse' : '' ?>">

      <div class="split__media reveal" style="aspect-ratio:16/11;">
        <?php if ($img): ?>
          <img src="<?= e($img) ?>" alt="<?= e($v['name']) ?>" loading="lazy" width="900" height="620">
        <?php else: ?>
          <div class="vehicle-placeholder">
            <?= vehicle_placeholder_svg($v['class_label']) ?>
            <span>Photo coming soon</span>
          </div>
        <?php endif; ?>
      </div>

      <div class="reveal">
        <span class="eyebrow"><?= e($v['class_label']) ?></span>
        <h2 class="section-title"><?= e($v['name']) ?></h2>
        <p style="color:var(--gold);font-size:var(--fs-lg);margin-bottom:var(--s-5);"><?= e($v['tagline']) ?></p>
        <p class="section-lead mb-6"><?= e($v['description']) ?></p>

        <div class="spec-row">
          <span class="spec"><?= icon('users') ?><strong><?= (int)$v['passengers'] ?></strong>&nbsp;passengers</span>
          <span class="spec"><?= icon('luggage') ?><strong><?= (int)$v['luggage'] ?></strong>&nbsp;bags</span>
          <span class="spec"><?= icon('clock') ?><strong><?= (int)$v['min_hours'] ?></strong>h&nbsp;minimum hire</span>
        </div>

        <h3 style="font-size:var(--fs-sm);letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin:var(--s-5) 0 var(--s-4);font-family:var(--font-body);">Features</h3>
        <ul class="feature-list mb-6">
          <?php foreach ($feats as $f): ?>
          <li><?= icon('check') ?><span><?= e($f) ?></span></li>
          <?php endforeach; ?>
        </ul>

        <h3 style="font-size:var(--fs-sm);letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin-bottom:var(--s-4);font-family:var(--font-body);">Available For</h3>
        <div class="area-cloud mb-6">
          <?php foreach ($allowed as $a): ?>
            <span class="area-chip area-chip--gold"><?= e($a) ?></span>
          <?php endforeach; ?>
        </div>

        <?php if ((int)$v['allow_airport'] === 0): ?>
        <div class="alert alert--gold">
          <?= icon('crown') ?>
          <span>The <?= e($v['name']) ?> is reserved for <strong>hourly chauffeur hire
          (<?= (int)$v['min_hours'] ?>-hour minimum)</strong> and <strong>long-distance
          city-to-city transfers</strong> only.</span>
        </div>
        <?php endif; ?>

        <div class="spec-row" style="border-bottom:0;">
          <span class="spec"><?= icon('clock') ?>Hourly from
            <strong class="text-gold tabular">&nbsp;<?= money_short((float)$v['hourly_rate']) ?>/hr</strong></span>
          <?php if ($cheap): ?>
          <span class="spec"><?= icon('route') ?>Flat rates from
            <strong class="text-gold tabular">&nbsp;<?= money_short((float)$cheap['price']) ?></strong></span>
          <?php endif; ?>
        </div>

        <div class="btn-row mt-5">
          <a href="booking.php?vehicle=<?= e($v['slug']) ?>" class="btn btn--gold">
            <?= icon('calendar') ?><span>Reserve this vehicle</span>
          </a>
          <a href="rates.php#<?= e($v['slug']) ?>" class="btn btn--outline">
            <span>View flat rates</span><?= icon('arrow-right') ?>
          </a>
        </div>
      </div>

    </div>
  </div>
</section>
<?php endforeach; ?>


<!-- ══ INCLUDED AS STANDARD ═══════════════════════════════════════ -->
<section class="section">
  <div class="container container--narrow">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">Every Vehicle, Every Journey</span>
      <h2 class="section-title">Included as <span class="gold-text">standard</span></h2>
    </div>

    <div class="grid grid--2 reveal-group">
      <article class="card">
        <div class="card__icon"><?= icon('users') ?></div>
        <h3 class="card__title">Meet &amp; Greet Service</h3>
        <p class="card__text">Your professional chauffeur will meet you at your pickup location,
           open the door for you, and assist with your luggage to ensure a seamless and
           comfortable experience.</p>
      </article>

      <article class="card">
        <div class="card__icon"><?= icon('sparkles') ?></div>
        <h3 class="card__title">Onboard Amenities</h3>
        <p class="card__text">All PRIME vehicles include complimentary bottled water,
           Wi-Fi connection, and reading material to enhance your journey.</p>
      </article>
    </div>

    <div class="btn-row btn-row--center mt-7 reveal">
      <a href="booking.php" class="btn btn--gold btn--lg"><?= icon('calendar') ?><span>Book a Ride</span></a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
