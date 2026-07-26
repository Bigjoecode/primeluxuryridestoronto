<?php
/** Home page. */
require_once __DIR__ . '/includes/functions.php';

$page_slug        = 'home';
$page_title       = setting('hero_title', 'Luxury Chauffeur Services in Toronto');
$page_description = setting('meta_description',
    'Prime Luxury Rides Toronto — premium chauffeur service for airport transfers, corporate travel and special events across Toronto & the GTA. Licensed, insured, available 24/7.');

$vehicles  = get_vehicles();
$hero_file = ROOT_PATH . '/assets/img/hero.jpg';
$has_hero  = is_file($hero_file);

require __DIR__ . '/includes/header.php';
?>

<!-- ══ HERO ═══════════════════════════════════════════════════════ -->
<section class="hero">
  <?php if ($has_hero): ?>
  <div class="hero__bg">
    <img src="assets/img/hero.jpg" alt="" width="1920" height="1080" fetchpriority="high">
  </div>
  <?php else: ?>
  <div class="hero__bg" aria-hidden="true"
       style="background:
         radial-gradient(ellipse 90% 60% at 78% 18%, rgba(212,175,55,.18), transparent 62%),
         linear-gradient(155deg, #121115 0%, #08070a 55%, #0d0c10 100%);"></div>
  <?php endif; ?>

  <div class="container">
    <div class="hero__inner">
      <span class="eyebrow">Toronto &amp; the GTA &middot; Available 24/7</span>

      <h1 class="hero__title">
        <span class="line">Luxury Chauffeur</span>
        <span class="line gold-text">Services in Toronto</span>
      </h1>

      <p class="hero__sub"><?= e(setting('hero_subtitle',
        'Premium airport transfers, corporate travel, events & more.')) ?></p>

      <div class="btn-row">
        <a href="booking.php" class="btn btn--gold btn--lg">
          <?= icon('calendar') ?><span>Book a Ride</span>
        </a>
        <a href="<?= e(tel_url()) ?>" class="btn btn--outline btn--lg">
          <?= icon('phone') ?><span>Call Us</span>
        </a>
      </div>
    </div>

    <!-- Full container width: the five search fields need the room -->
    <?php require __DIR__ . '/includes/quick-search.php'; ?>

    <div class="trust-bar">
      <div class="trust-item"><?= icon('shield-check') ?><span>Licensed &amp; Commercially Insured</span></div>
      <div class="trust-item"><?= icon('award') ?><span>Professional Chauffeurs</span></div>
      <div class="trust-item"><?= icon('plane') ?><span>Flight Tracking Included</span></div>
      <div class="trust-item"><?= icon('headset') ?><span>24/7 Availability</span></div>
    </div>
  </div>

  <span class="hero__scroll" aria-hidden="true">Scroll</span>
</section>


<!-- ══ SERVICES ═══════════════════════════════════════════════════ -->
<section class="section" id="services">
  <div class="container">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">What We Do</span>
      <h2 class="section-title">Chauffeur services, <span class="gold-text">done properly</span></h2>
      <p class="section-lead">From a 6&nbsp;a.m. run to Pearson to a full evening on call &mdash;
         one standard of service, whatever the occasion.</p>
    </div>

    <div class="grid grid--3 reveal-group">
      <?php
      $services = [
        ['airport',   'plane',     'Airport Transfers',    'Pearson (YYZ), Billy Bishop (YTZ) and Hamilton (YHM). We track your flight and adjust for delays at no extra cost.'],
        ['corporate', 'briefcase', 'Corporate Chauffeur',  'Reliable, discreet ground transport for executives and clients. Account billing and repeat scheduling available.'],
        ['events',    'sparkles',  'Event Transportation', 'Weddings, galas, premieres and conferences — arrive composed, on time, every time.'],
        ['hourly',    'clock',     'Hourly Chauffeur',     'Your car and chauffeur on standby for the duration. Three-hour minimum, four on the Maybach.'],
        ['vip',       'crown',     'VIP Service',          'Maximum discretion for high-profile clients. Vetted chauffeurs and unmarked luxury vehicles.'],
        ['night-out', 'moon',      'Night Out',            'Dinner, theatre or celebration — travel together and let someone else handle the driving.'],
      ];
      foreach ($services as [$slug, $ico, $title, $text]): ?>
      <article class="card card--linked">
        <div class="card__icon"><?= icon($ico) ?></div>
        <h3 class="card__title"><?= e($title) ?></h3>
        <p class="card__text"><?= e($text) ?></p>
        <a class="card__link card__stretch" href="services.php#<?= e($slug) ?>">
          <span>Learn more</span><?= icon('arrow-right') ?>
        </a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- ══ FLEET PREVIEW ══════════════════════════════════════════════ -->
<section class="section section--alt" id="fleet">
  <div class="container">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">The Fleet</span>
      <h2 class="section-title">Immaculate vehicles, <span class="gold-text">without exception</span></h2>
      <p class="section-lead">Every car in our fleet is late-model, professionally detailed before
         each booking and maintained to manufacturer schedule.</p>
    </div>

    <div class="grid grid--3 reveal-group">
      <?php foreach ($vehicles as $v):
        $img = vehicle_image_url($v);
        $feats = array_slice(vehicle_features($v), 0, 3); ?>
      <article class="vehicle-card">
        <div class="vehicle-card__media">
          <?php if ($img): ?>
            <img src="<?= e($img) ?>" alt="<?= e($v['name']) ?>" loading="lazy" width="640" height="400">
          <?php else: ?>
            <div class="vehicle-placeholder">
              <?= vehicle_placeholder_svg($v['class_label']) ?>
              <span>Photo coming soon</span>
            </div>
          <?php endif; ?>
          <span class="vehicle-card__badge"><?= e($v['class_label']) ?></span>
        </div>

        <div class="vehicle-card__body">
          <h3 class="vehicle-card__name"><?= e($v['name']) ?></h3>
          <p class="vehicle-card__tagline"><?= e($v['tagline']) ?></p>

          <div class="spec-row">
            <span class="spec"><?= icon('users') ?><?= (int)$v['passengers'] ?> passengers</span>
            <span class="spec"><?= icon('luggage') ?><?= (int)$v['luggage'] ?> bags</span>
          </div>

          <ul class="feature-list">
            <?php foreach ($feats as $f): ?>
            <li><?= icon('check') ?><span><?= e($f) ?></span></li>
            <?php endforeach; ?>
          </ul>

          <div class="vehicle-card__foot">
            <div class="price-from">
              <span class="price-from__label">Hourly from</span>
              <span class="price-from__value tabular"><?= money_short((float)$v['hourly_rate']) ?></span>
            </div>
            <a href="booking.php?vehicle=<?= e($v['slug']) ?>" class="btn btn--outline btn--sm btn--block">
              Reserve this vehicle
            </a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <div class="btn-row btn-row--center mt-7 reveal">
      <a href="fleet.php" class="btn btn--gold">
        <span>View the full fleet</span><?= icon('arrow-right') ?>
      </a>
    </div>
  </div>
</section>


<!-- ══ HOW IT WORKS ═══════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">How It Works</span>
      <h2 class="section-title">Booked in <span class="gold-text">under two minutes</span></h2>
    </div>

    <div class="steps reveal-group">
      <div class="step">
        <h3 class="step__title">Book Online</h3>
        <p class="step__text">Choose your pickup and drop-off locations, date, and time in seconds.</p>
      </div>
      <div class="step">
        <h3 class="step__title">We Confirm Your Ride</h3>
        <p class="step__text">A professional chauffeur and luxury vehicle are assigned to your booking.</p>
      </div>
      <div class="step">
        <h3 class="step__title">Enjoy a Premium Experience</h3>
        <p class="step__text">Relax in comfort while we ensure a smooth, on-time ride to your destination.</p>
      </div>
    </div>

    <div class="btn-row btn-row--center mt-7 reveal">
      <a href="booking.php" class="btn btn--gold btn--lg">
        <?= icon('calendar') ?><span>Start your booking</span>
      </a>
    </div>
  </div>
</section>


<!-- ══ WHY CHOOSE US ══════════════════════════════════════════════ -->
<section class="section section--alt">
  <div class="container">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">Why Choose Us</span>
      <h2 class="section-title">Why Choose <span class="gold-text">Prime Luxury Rides</span></h2>
    </div>

    <div class="grid grid--3 reveal-group">
      <?php
      $why = [
        ['award',        'Professional Chauffeurs',   'Experienced, courteous, and trained for VIP service.'],
        ['car',          'Luxury Fleet',              'Executive sedans, SUVs, and our flagship Maybach for any occasion.'],
        ['plane',        'Flight Tracking',           'We monitor your flight to ensure on-time pickup.'],
        ['shield-check', 'Safe &amp; Insured',        'Fully licensed and commercially insured for your peace of mind.'],
        ['headset',      '24/7 Availability',         'Book anytime — day or night.'],
        ['sparkle-clean','Clean &amp; Sanitized Vehicles', 'Every ride is a premium, comfortable experience.'],
      ];
      foreach ($why as [$ico, $title, $text]): ?>
      <article class="card">
        <div class="card__icon"><?= icon($ico) ?></div>
        <h3 class="card__title"><?= $title /* contains intentional entity */ ?></h3>
        <p class="card__text"><?= e($text) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- ══ INCLUDED WITH EVERY RIDE ═══════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="split">
      <div class="split__media reveal" style="aspect-ratio:1/1;display:grid;place-items:center;">
        <div style="text-align:center;padding:var(--s-7);">
          <div style="color:var(--gold);opacity:.85;margin-bottom:var(--s-5);display:flex;justify-content:center;">
            <?= icon('sparkles', '', 64) ?>
          </div>
          <p style="font-family:var(--font-display);font-size:var(--fs-xl);line-height:1.3;">
            Every journey includes<br><span class="gold-text">the full Prime standard</span>
          </p>
        </div>
      </div>

      <div class="reveal">
        <span class="eyebrow">Included As Standard</span>
        <h2 class="section-title">Meet &amp; Greet Service</h2>
        <p class="section-lead mb-6">Your professional chauffeur will meet you at your pickup location,
           open the door for you, and assist with your luggage to ensure a seamless and
           comfortable experience.</p>

        <h3 class="card__title mb-4">Onboard Amenities</h3>
        <p class="section-lead mb-6">All PRIME vehicles include complimentary bottled water,
           Wi-Fi connection, and reading material to enhance your journey.</p>

        <ul class="feature-list">
          <li><?= icon('droplet') ?><span>Complimentary bottled water</span></li>
          <li><?= icon('wifi') ?><span>Onboard Wi-Fi connection</span></li>
          <li><?= icon('list') ?><span>Current reading material</span></li>
          <li><?= icon('snowflake') ?><span>Individual climate control</span></li>
          <li><?= icon('luggage') ?><span>Luggage assistance, door to door</span></li>
        </ul>
      </div>
    </div>
  </div>
</section>


<!-- ══ TESTIMONIALS ═══════════════════════════════════════════════ -->
<section class="section section--alt">
  <div class="container">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">Testimonials</span>
      <h2 class="section-title">What Our <span class="gold-text">Clients Say</span></h2>
    </div>

    <div class="grid grid--3 reveal-group">
      <?php
      $reviews = [
        ['Professional, on time, and very smooth experience. Highly recommend!', 'Michael A.'],
        ['My go-to chauffeur service in Toronto. Amazing fleet and excellent drivers.', 'Sarah O.'],
        ['Booked for the airport — driver was early, car was spotless, and the ride was perfect.', 'Daniel K.'],
      ];
      foreach ($reviews as [$quote, $name]): ?>
      <figure class="quote-card">
        <div class="stars" role="img" aria-label="Rated 5 out of 5 stars">
          <?= str_repeat(icon('star'), 5) ?>
        </div>
        <blockquote>&ldquo;<?= e($quote) ?>&rdquo;</blockquote>
        <figcaption>&mdash; <?= e($name) ?></figcaption>
      </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- ══ SERVICE AREA ═══════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="split split--reverse">
      <div class="split__media reveal" style="display:grid;place-items:center;aspect-ratio:4/3;">
        <div style="color:var(--gold);opacity:.3;"><?= icon('map', '', 140) ?></div>
      </div>

      <div class="reveal">
        <span class="eyebrow">Coverage</span>
        <h2 class="section-title">Serving <span class="gold-text">Toronto &amp; the GTA</span></h2>
        <p class="section-lead mb-6">Door-to-door service across the Greater Toronto Area, with
           long-distance transfers to Ottawa, Montreal, Niagara and beyond.</p>

        <h3 style="font-size:var(--fs-sm);letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin-bottom:var(--s-4);font-family:var(--font-body);">Cities</h3>
        <div class="area-cloud mb-6">
          <?php foreach (['Toronto','Mississauga','Brampton','Vaughan','Markham','Scarborough','Etobicoke','North York','Oakville','Richmond Hill'] as $city): ?>
            <span class="area-chip"><?= e($city) ?></span>
          <?php endforeach; ?>
        </div>

        <h3 style="font-size:var(--fs-sm);letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin-bottom:var(--s-4);font-family:var(--font-body);">Airports</h3>
        <div class="area-cloud">
          <span class="area-chip area-chip--gold">YYZ &mdash; Toronto Pearson</span>
          <span class="area-chip area-chip--gold">YTZ &mdash; Billy Bishop</span>
          <span class="area-chip area-chip--gold">YHM &mdash; Hamilton</span>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ══ CTA ════════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="cta-banner reveal">
      <span class="eyebrow eyebrow--center" style="justify-content:center;">Ready When You Are</span>
      <h2 class="cta-banner__title">Reserve your <span class="gold-text">Prime</span> experience</h2>
      <p class="cta-banner__text">Instant online quotes, transparent flat rates on long-distance
         transfers, and a chauffeur confirmed the moment you book.</p>
      <div class="btn-row btn-row--center">
        <a href="booking.php" class="btn btn--gold btn--lg"><?= icon('calendar') ?><span>Book a Ride</span></a>
        <a href="<?= e(whatsapp_url()) ?>" target="_blank" rel="noopener noreferrer" class="btn btn--outline btn--lg">
          <?= icon('whatsapp') ?><span>WhatsApp Us</span>
        </a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
