<?php
/** Services. */
require_once __DIR__ . '/includes/functions.php';

$page_slug        = 'services';
$page_title       = 'Chauffeur Services';
$page_description = 'Airport transfers, corporate chauffeur, event transportation, hourly hire, VIP service and night-out travel across Toronto and the GTA. Request a quote online.';

$services = [
  ['airport',   'plane',     'Airport Pickup &amp; Drop-off', 'Toronto Pearson (YYZ), Billy Bishop (YTZ) and Hamilton (YHM).',
    'Your chauffeur monitors your flight in real time and adjusts for delays at no additional charge. Meet &amp; greet inside the terminal on request, luggage handled door to door, and a car that is already waiting when you land.',
    ['Live flight tracking', 'Meet &amp; greet at arrivals', 'Complimentary wait time', 'All three GTA airports']],

  ['corporate', 'briefcase', 'Corporate Chauffeur Service',  'For business travellers and client-facing teams.',
    'Professional chauffeurs, reliable on-time service, available 24/7 across Toronto and the GTA. Consistent standards for roadshows, client collection and executive travel, with account billing and repeat scheduling available.',
    ['Available 24/7 in Toronto &amp; GTA', 'Account billing available', 'Repeat &amp; recurring schedules', 'Discreet, professional chauffeurs']],

  ['events',    'sparkles',  'Event Transportation',         'Weddings, galas, premieres and conferences.',
    'Coordinated arrivals and departures for events of any size. We work to your run sheet, stage vehicles in advance and keep the whole party moving on schedule.',
    ['Multi-vehicle coordination', 'Run-sheet scheduling', 'Red-carpet arrivals', 'Guest shuttle options']],

  ['hourly',    'clock',     'Hourly Chauffeur',             'Your car and chauffeur, on standby.',
    'Ideal for meetings across town, shopping days, city tours or any itinerary that changes as it goes. The vehicle stays with you for the full duration &mdash; no re-booking between stops.',
    ['3-hour minimum (S-Class, Escalade, Suburban)', '4-hour minimum (Maybach)', 'Unlimited stops within the hire', 'Chauffeur waits on site']],

  ['vip',       'crown',     'VIP Chauffeur Service',        'Maximum discretion for high-profile clients.',
    'Vetted, background-checked chauffeurs and unmarked luxury vehicles. Route planning, private-entrance drop-offs and full confidentiality as standard.',
    ['Unmarked vehicles', 'Background-checked chauffeurs', 'Private entrance drop-offs', 'Full confidentiality']],

  ['night-out', 'moon',      'Night Out &amp; Special Occasions', 'Dinner, theatre, birthdays and celebrations.',
    'Travel together, arrive together and let someone else handle the driving and the parking. Available for the full evening or point to point.',
    ['Full-evening hire available', 'Multiple pickups', 'Late-night availability', 'Safe ride home guaranteed']],

  ['long-distance', 'route', 'Long-Distance Transfers',      'City-to-city across Southern Ontario.',
    'Published flat rates to Ottawa, Kingston, London, Niagara Falls, Barrie and more &mdash; no meter, no surprises. Distance-based dynamic pricing applies within 40&nbsp;km.',
    ['Transparent published flat rates', 'Ottawa, Kingston, London, Niagara', 'One-way or return', 'HST added at checkout']],
];

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="index.php">Home</a><?= icon('chevron-right') ?><span>Services</span>
    </nav>
    <span class="eyebrow">What We Offer</span>
    <h1 class="page-head__title">Chauffeur services for <span class="gold-text">every occasion</span></h1>
    <p class="page-head__lead">One standard of vehicle, one standard of chauffeur &mdash;
       applied to whatever you need moving, whenever you need it.</p>
    <div class="btn-row mt-6">
      <a href="booking.php" class="btn btn--gold"><?= icon('calendar') ?><span>Request a Quote</span></a>
      <a href="<?= e(tel_url()) ?>" class="btn btn--outline"><?= icon('phone') ?><span>Call Us</span></a>
    </div>
  </div>
</section>


<!-- ══ SERVICE DETAIL SECTIONS ════════════════════════════════════ -->
<?php foreach ($services as $i => [$slug, $ico, $title, $sub, $body, $points]):
  $alt = ($i % 2 === 1);
?>
<section class="section <?= $alt ? 'section--alt' : '' ?>" id="<?= e($slug) ?>">
  <div class="container">
    <div class="split <?= $alt ? 'split--reverse' : '' ?>">

      <div class="split__media reveal" style="display:grid;place-items:center;aspect-ratio:4/3;">
        <div style="color:var(--gold);opacity:.32;"><?= icon($ico, '', 130) ?></div>
      </div>

      <div class="reveal">
        <span class="eyebrow"><?= e(sprintf('%02d', $i + 1)) ?> &middot; Service</span>
        <h2 class="section-title"><?= $title ?></h2>
        <p style="color:var(--gold);font-size:var(--fs-lg);margin-bottom:var(--s-5);"><?= $sub ?></p>
        <p class="section-lead mb-6"><?= $body ?></p>

        <ul class="feature-list mb-6">
          <?php foreach ($points as $p): ?>
          <li><?= icon('check') ?><span><?= $p ?></span></li>
          <?php endforeach; ?>
        </ul>

        <a href="booking.php" class="btn btn--gold">
          <span>Request a Quote</span><?= icon('arrow-right') ?>
        </a>
      </div>

    </div>
  </div>
</section>
<?php endforeach; ?>


<!-- ══ INCLUDED AS STANDARD ═══════════════════════════════════════ -->
<section class="section">
  <div class="container container--narrow">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">Included With Every Service</span>
      <h2 class="section-title">The <span class="gold-text">Prime standard</span></h2>
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
  </div>
</section>


<!-- ══ CTA ════════════════════════════════════════════════════════ -->
<section class="section section--alt">
  <div class="container">
    <div class="cta-banner reveal">
      <h2 class="cta-banner__title">Not sure which service <span class="gold-text">you need?</span></h2>
      <p class="cta-banner__text">Tell us where you&rsquo;re going and we&rsquo;ll recommend the
         right vehicle and service &mdash; with a firm quote up front.</p>
      <div class="btn-row btn-row--center">
        <a href="booking.php" class="btn btn--gold btn--lg"><?= icon('calendar') ?><span>Get a Quote</span></a>
        <a href="<?= e(whatsapp_url()) ?>" target="_blank" rel="noopener noreferrer" class="btn btn--outline btn--lg">
          <?= icon('whatsapp') ?><span>WhatsApp Us</span>
        </a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
