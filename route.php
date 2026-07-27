<?php
/**
 * SEO landing page for a single Toronto → city route.
 *
 * Generated from the flat_rates table, so adding a destination in the
 * admin panel automatically creates a new indexable page — no code and
 * no duplicated content to maintain.
 *
 * Pretty URL:  /toronto-to-niagara-falls-car-service
 * Real URL:    /route.php?to=niagara-falls
 */
require_once __DIR__ . '/includes/pricing.php';

$slug = strtolower(trim((string)($_GET['to'] ?? '')));
$slug = preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';

if ($slug === '') {
    header('Location: rates.php');
    exit;
}

// Match the slug back to a city in the rate table.
$city_key = str_replace('-', ' ', $slug);
$rates = db_all(
    'SELECT f.*, v.`name` AS vehicle_name, v.`slug` AS vehicle_slug,
            v.`class_label`, v.`passengers`, v.`luggage`, v.`hourly_rate`, v.`image`
       FROM `flat_rates` f
       JOIN `vehicles` v ON v.`id` = f.`vehicle_id`
      WHERE f.`city_key` = ? AND v.`is_active` = 1
   ORDER BY v.`sort_order`, v.`id`',
    [$city_key]
);

if (!$rates) {
    http_response_code(404);
    $page_slug        = '404';
    $page_title       = 'Route not found';
    $page_description = 'That route could not be found.';
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="page-head">
      <div class="container container--narrow center">
        <h1 class="page-head__title">Route not found</h1>
        <p class="page-head__lead" style="margin-inline:auto;">
          We don&rsquo;t have a published flat rate for that destination yet &mdash; but we
          almost certainly cover it. Ask us for a quote.
        </p>
        <div class="btn-row btn-row--center mt-7">
          <a href="rates.php" class="btn btn--gold btn--lg">See all flat rates</a>
          <a href="contact.php" class="btn btn--outline btn--lg">Request a quote</a>
        </div>
      </div>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$city     = (string)$rates[0]['city'];
$distance = (int)$rates[0]['distance_km'];

// Cheapest published fare drives the headline price.
$priced = array_values(array_filter($rates, fn($r) => $r['price'] !== null));
$from_price = $priced ? min(array_map(fn($r) => (float)$r['price'], $priced)) : null;

// Rough drive time at highway average — stated as an estimate, never as a promise.
$drive_min  = $distance > 0 ? (int)round($distance / 95 * 60) : 0;
$drive_text = $drive_min >= 60
    ? sprintf('%dh %02dm', intdiv($drive_min, 60), $drive_min % 60)
    : $drive_min . ' min';

$hst = rtrim(rtrim(number_format(setting_num('hst_rate', DEFAULT_HST_RATE), 2), '0'), '.');

$page_slug  = 'route';
$page_title = 'Toronto to ' . $city . ' Car Service';
$page_description = sprintf(
    'Private chauffeur from Toronto to %s — %s, about %s. %sLicensed, insured, available 24/7. Book online in two minutes.',
    $city, $distance . ' km', $drive_text,
    $from_price !== null ? 'Flat rates from ' . money_short($from_price) . '. ' : ''
);

$canonical_path = '/toronto-to-' . $slug . '-car-service';

// Rich results: a priced service plus the FAQ block below.
$schema_extra = [
    '@context' => 'https://schema.org',
    '@graph'   => [
        [
            '@type'       => 'Service',
            'serviceType' => 'Chauffeur service',
            'name'        => 'Toronto to ' . $city . ' car service',
            'description' => $page_description,
            'provider'    => ['@type' => 'LimousineService', 'name' => SITE_NAME],
            'areaServed'  => [
                ['@type' => 'City', 'name' => 'Toronto'],
                ['@type' => 'City', 'name' => $city],
            ],
            'offers' => array_values(array_map(fn($r) => [
                '@type'        => 'Offer',
                'name'         => (string)$r['vehicle_name'],
                'price'        => (string)(float)$r['price'],
                'priceCurrency' => 'CAD',
            ], $priced)),
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name'  => 'How much is a car from Toronto to ' . $city . '?',
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' =>
                        $from_price !== null
                            ? 'Our published one-way flat rate starts at ' . money_short($from_price)
                              . ' plus ' . $hst . '% HST, depending on the vehicle you choose.'
                            : 'This route is priced dynamically based on distance and travel time. '
                              . 'Enter your addresses on our booking page for an instant quote.'],
                ],
                [
                    '@type' => 'Question',
                    'name'  => 'How long does it take to drive from Toronto to ' . $city . '?',
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' =>
                        'The journey is roughly ' . $distance . ' km and typically takes about '
                        . $drive_text . ', depending on traffic and the time of day.'],
                ],
                [
                    '@type' => 'Question',
                    'name'  => 'Is the price fixed?',
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' =>
                        'Yes. Our flat rate is fixed for the journey regardless of traffic or route. '
                        . 'HST is added at checkout, and there are no hidden extras.'],
                ],
            ],
        ],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="index.php">Home</a><?= icon('chevron-right') ?>
      <a href="rates.php">Flat Rates</a><?= icon('chevron-right') ?>
      <span>Toronto to <?= e($city) ?></span>
    </nav>

    <span class="eyebrow">Private Chauffeur &middot; <?= $distance ?> km</span>
    <h1 class="page-head__title">
      Toronto to <span class="gold-text"><?= e($city) ?></span><br>car service
    </h1>
    <p class="page-head__lead">
      A private chauffeur, a fixed price agreed before you travel, and a late-model
      luxury vehicle. Roughly <?= $distance ?>&nbsp;km &mdash; about <?= e($drive_text) ?> on the road.
    </p>

    <div class="trust-bar" style="margin-top:var(--s-6);">
      <?php if ($from_price !== null): ?>
      <div class="trust-item"><?= icon('tag') ?>
        <span>Flat rates from <strong class="text-gold"><?= money_short($from_price) ?></strong></span>
      </div>
      <?php endif; ?>
      <div class="trust-item"><?= icon('clock') ?><span>About <?= e($drive_text) ?></span></div>
      <div class="trust-item"><?= icon('shield-check') ?><span>Licensed &amp; insured</span></div>
      <div class="trust-item"><?= icon('headset') ?><span>Available 24/7</span></div>
    </div>

    <div class="btn-row mt-6">
      <a href="booking.php?service=city_to_city&amp;from=Toronto,+ON&amp;to=<?= e(urlencode($city)) ?>"
         class="btn btn--gold btn--lg">
        <?= icon('calendar') ?><span>Book Toronto &rarr; <?= e($city) ?></span>
      </a>
      <a href="<?= e(tel_url()) ?>" class="btn btn--outline btn--lg">
        <?= icon('phone') ?><span>Call Us</span>
      </a>
    </div>
  </div>
</section>


<!-- ══ PRICES ═════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">Published Pricing</span>
      <h2 class="section-title">Toronto to <?= e($city) ?> <span class="gold-text">flat rates</span></h2>
      <p class="section-lead">One-way, per vehicle, before <?= e($hst) ?>% HST. The price is
         fixed &mdash; traffic and route make no difference to what you pay.</p>
    </div>

    <div class="table-scroll reveal">
      <table class="rate-table">
        <caption class="sr-only">Flat rates from Toronto to <?= e($city) ?></caption>
        <thead>
          <tr>
            <th scope="col">Vehicle</th>
            <th scope="col">Capacity</th>
            <th scope="col">One-way price</th>
            <th scope="col"><span class="sr-only">Book</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rates as $r): ?>
          <tr>
            <th scope="row" style="font-weight:600;">
              <?= e($r['vehicle_name']) ?>
              <br><span class="muted" style="font-weight:400;font-size:var(--fs-sm);color:var(--text-dim);">
                <?= e($r['class_label']) ?></span>
            </th>
            <td class="col-km">
              <?= (int)$r['passengers'] ?> passengers &middot; <?= (int)$r['luggage'] ?> bags
            </td>
            <td class="col-price">
              <?php if ($r['price'] !== null): ?>
                <?= money_short((float)$r['price']) ?>
              <?php else: ?>
                <span class="is-dynamic">Dynamic pricing</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn btn--outline btn--sm"
                 href="booking.php?service=city_to_city&amp;from=Toronto,+ON&amp;to=<?= e(urlencode($city)) ?>&amp;vehicle=<?= e($r['vehicle_slug']) ?>">
                Book
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="text-muted mt-4" style="font-size:var(--fs-sm);">
      Prices are one-way and exclude <?= e($hst) ?>% HST. Booking a return trip together
      saves a further <?= (int)setting_num('return_discount', 10) ?>%.
    </p>
  </div>
</section>


<!-- ══ WHY ════════════════════════════════════════════════════════ -->
<section class="section section--alt">
  <div class="container">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">Why Book With Us</span>
      <h2 class="section-title">A better way to travel to <span class="gold-text"><?= e($city) ?></span></h2>
    </div>

    <div class="grid grid--3 reveal-group">
      <?php
      $why = [
        ['tag',          'A price fixed in advance', 'You see the full fare, including HST, before you confirm. No meter, no surge, no surprises on a ' . $distance . ' km drive.'],
        ['users',        'Door to door',             'Collected from your address and taken to the door at the other end — no parking, no transfers, no waiting at a station.'],
        ['briefcase',    'Work or rest on the way',  'Wi-Fi, quiet, and space to spread out. Most clients treat the ' . $drive_text . ' as useful time rather than lost time.'],
        ['award',        'Professional chauffeurs',  'Vetted, licensed and trained for long-distance work — not a rideshare driver taking a job outside their usual area.'],
        ['shield-check', 'Licensed &amp; insured',   'Full commercial insurance on every vehicle and chauffeur, for the whole journey.'],
        ['headset',      'Available around the clock','Early flights and late finishes included. Book any hour, travel any hour.'],
      ];
      foreach ($why as [$ico, $title, $text]): ?>
      <article class="card">
        <div class="card__icon"><?= icon($ico) ?></div>
        <h3 class="card__title"><?= $title ?></h3>
        <p class="card__text"><?= e($text) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- ══ FAQ ════════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container container--narrow">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">Questions</span>
      <h2 class="section-title">Toronto to <?= e($city) ?>, <span class="gold-text">answered</span></h2>
    </div>

    <div style="display:grid;gap:var(--s-4);">
      <?php
      $faqs = [
        ['How much is a car from Toronto to ' . $city . '?',
         $from_price !== null
           ? 'Our published one-way flat rate starts at ' . money_short($from_price) . ' plus '
             . $hst . '% HST, depending on which vehicle you choose. The table above shows every option.'
           : 'This route is priced dynamically from the actual distance and travel time. Enter your '
             . 'pickup and drop-off on the booking page for an instant quote.'],
        ['How long does the drive take?',
         'It is roughly ' . $distance . ' km and typically takes about ' . $drive_text
         . '. We build buffer into every schedule, and your chauffeur monitors traffic on the day.'],
        ['Is the flat rate really fixed?',
         'Yes. Once your booking is confirmed the fare does not change, whatever the traffic or route. '
         . 'HST is added at checkout and tolls are already included.'],
        ['Can you collect from an airport?',
         'Yes. We serve Toronto Pearson (YYZ), Billy Bishop (YTZ) and Hamilton (YHM), and we track '
         . 'your flight so a delay does not cost you the booking.'],
        ['Can I book the return journey at the same time?',
         'Yes, and you should — booking both legs together takes a further '
         . (int)setting_num('return_discount', 10) . '% off the total. Choose "Return trip" on the booking form.'],
        ['How many passengers can you take?',
         'Up to ' . max(array_map(fn($r) => (int)$r['passengers'], $rates)) . ' passengers depending on '
         . 'the vehicle. The table above lists the capacity of each.'],
      ];
      foreach ($faqs as [$q, $a]): ?>
      <details class="card" style="padding:var(--s-5);">
        <summary style="cursor:pointer;font-family:var(--font-display);font-size:var(--fs-lg);
                        list-style:none;display:flex;justify-content:space-between;gap:var(--s-4);
                        align-items:center;">
          <span><?= e($q) ?></span>
          <span style="color:var(--gold);flex:none;"><?= icon('chevron-down') ?></span>
        </summary>
        <p class="card__text mt-4"><?= e($a) ?></p>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- ══ OTHER ROUTES ═══════════════════════════════════════════════ -->
<?php
$others = db_all(
    'SELECT DISTINCT f.`city`, f.`city_key`, f.`distance_km`
       FROM `flat_rates` f
       JOIN `vehicles` v ON v.`id` = f.`vehicle_id`
      WHERE f.`city_key` <> ? AND v.`is_active` = 1 AND f.`price` IS NOT NULL
   ORDER BY f.`distance_km`', [$city_key]);
if ($others): ?>
<section class="section section--alt">
  <div class="container">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">More Routes</span>
      <h2 class="section-title">Other <span class="gold-text">popular journeys</span></h2>
    </div>
    <div class="area-cloud" style="justify-content:center;">
      <?php foreach ($others as $o): ?>
      <a class="area-chip" href="/toronto-to-<?= e(str_replace(' ', '-', $o['city_key'])) ?>-car-service">
        Toronto &rarr; <?= e($o['city']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ══ CTA ════════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="cta-banner reveal">
      <h2 class="cta-banner__title">Toronto to <span class="gold-text"><?= e($city) ?></span></h2>
      <p class="cta-banner__text">
        <?php if ($from_price !== null): ?>
        Fixed fares from <?= money_short($from_price) ?>. Book online in two minutes.
        <?php else: ?>
        Get an instant quote for this route in under two minutes.
        <?php endif; ?>
      </p>
      <div class="btn-row btn-row--center">
        <a href="booking.php?service=city_to_city&amp;from=Toronto,+ON&amp;to=<?= e(urlencode($city)) ?>"
           class="btn btn--gold btn--lg">
          <?= icon('calendar') ?><span>Book this route</span>
        </a>
        <a href="<?= e(whatsapp_url('Hello, I would like a quote for Toronto to ' . $city . '.')) ?>"
           target="_blank" rel="noopener noreferrer" class="btn btn--outline btn--lg">
          <?= icon('whatsapp') ?><span>WhatsApp Us</span>
        </a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
