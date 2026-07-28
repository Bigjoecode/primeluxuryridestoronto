<?php
/** About Us. */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/uploads.php';

$about_url = site_image_url('about_image');

$page_slug        = 'about';
$page_title       = 'About Us';
$page_description = 'Prime Luxury Rides Toronto is a licensed, insured chauffeur company serving Toronto and the GTA with professional drivers and a meticulously maintained luxury fleet.';

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="index.php">Home</a><?= icon('chevron-right') ?><span>About Us</span>
    </nav>
    <span class="eyebrow">Who We Are</span>
    <h1 class="page-head__title">Toronto&rsquo;s chauffeur service, <span class="gold-text">built on standards</span></h1>
    <p class="page-head__lead">Prime Luxury Rides Toronto was founded on a straightforward idea:
       private ground transportation should be genuinely reliable, genuinely comfortable,
       and handled by people who take it seriously.</p>
  </div>
</section>


<!-- ══ INTRO ══════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="split">
      <div class="split__media reveal" style="display:grid;place-items:center;">
        <div style="text-align:center;padding:var(--s-7);">
          <img src="assets/img/logo.png" alt="" width="304" height="100"
               style="max-width:74%;margin-inline:auto;opacity:.95;" loading="lazy">
        </div>
      </div>

      <div class="reveal">
        <span class="eyebrow">Our Story</span>
        <h2 class="section-title">Punctual, discreet, <span class="gold-text">immaculate</span></h2>
        <p class="section-lead mb-5">We operate a compact, deliberately curated fleet rather than a
           large mixed one. Every vehicle is late-model, professionally detailed before each booking,
           and maintained on manufacturer schedule &mdash; because the car that arrives should look
           exactly like the car you were shown.</p>
        <p class="section-lead">Our chauffeurs are vetted, licensed, commercially insured and trained
           in the details that actually matter: arriving early, handling luggage without being asked,
           knowing which terminal door is quickest, and knowing when not to make conversation.</p>

        <div class="stat-row">
          <div class="stat"><div class="stat__value">24/7</div><div class="stat__label">Availability, every day of the year</div></div>
          <div class="stat"><div class="stat__value">3</div><div class="stat__label">Airports served across the GTA</div></div>
          <div class="stat"><div class="stat__value">100%</div><div class="stat__label">Licensed &amp; commercially insured</div></div>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ══ MISSION ════════════════════════════════════════════════════ -->
<section class="section section--alt">
  <div class="container container--narrow center">
    <div class="reveal">
      <span class="eyebrow eyebrow--center" style="justify-content:center;">Our Mission</span>
      <h2 class="section-title">Why we do this</h2>
      <div class="divider-gold divider-gold--center"></div>
      <p style="font-family:var(--font-display);font-size:var(--fs-xl);line-height:1.5;color:var(--text);">
        <?= e(setting('about_mission',
          'To redefine private ground transportation in the Greater Toronto Area by pairing an impeccably maintained luxury fleet with chauffeurs who treat every journey as a matter of personal pride.')) ?>
      </p>
    </div>
  </div>
</section>


<!-- ══ VALUES ═════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">What Sets Us Apart</span>
      <h2 class="section-title">Experience &amp; <span class="gold-text">professionalism</span></h2>
    </div>

    <div class="grid grid--3 reveal-group">
      <?php
      $values = [
        ['clock',        'Punctuality as policy',   'We build buffer into every schedule and track inbound flights automatically. Early is on time; on time is late.'],
        ['shield-check', 'Licensed &amp; insured',  'Full commercial insurance and municipal licensing on every vehicle and every chauffeur, without exception.'],
        ['award',        'Trained chauffeurs',      'Vetted, background-checked professionals trained in VIP protocol, defensive driving and discretion.'],
        ['sparkle-clean','Presentation standards',  'Interior and exterior detailing before every single booking. No exceptions, no shortcuts.'],
        ['headset',      'Reachable, always',       'A real person on the phone at any hour — not a queue, not a chatbot, not a callback promise.'],
        ['lock',         'Discretion by default',   'What happens in the car stays in the car. Unmarked vehicles available for high-profile clients.'],
      ];
      foreach ($values as [$ico, $title, $text]): ?>
      <article class="card">
        <div class="card__icon"><?= icon($ico) ?></div>
        <h3 class="card__title"><?= $title ?></h3>
        <p class="card__text"><?= e($text) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- ══ SERVICE AREAS ══════════════════════════════════════════════ -->
<section class="section section--alt">
  <div class="container">
    <div class="split split--reverse">
      <div class="split__media reveal" style="display:grid;place-items:center;">
        <div style="color:var(--gold);opacity:.3;"><?= icon('map', '', 150) ?></div>
      </div>

      <div class="reveal">
        <span class="eyebrow">Where We Operate</span>
        <h2 class="section-title">Service <span class="gold-text">areas</span></h2>
        <p class="section-lead mb-6">Toronto and the full Greater Toronto Area as standard, with
           long-distance city-to-city transfers across Southern Ontario at published flat rates.</p>

        <div class="area-cloud mb-6">
          <?php foreach (['Toronto','Mississauga','Brampton','Vaughan','Markham','Scarborough',
                          'Etobicoke','North York','Oakville','Burlington','Richmond Hill','Pickering'] as $c): ?>
            <span class="area-chip"><?= e($c) ?></span>
          <?php endforeach; ?>
        </div>

        <h3 style="font-size:var(--fs-sm);letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin-bottom:var(--s-4);font-family:var(--font-body);">Airport Transfers</h3>
        <div class="area-cloud mb-6">
          <span class="area-chip area-chip--gold">YYZ &mdash; Toronto Pearson</span>
          <span class="area-chip area-chip--gold">YTZ &mdash; Billy Bishop</span>
          <span class="area-chip area-chip--gold">YHM &mdash; Hamilton</span>
        </div>

        <a href="rates.php" class="btn btn--outline">
          <span>See long-distance flat rates</span><?= icon('arrow-right') ?>
        </a>
      </div>
    </div>
  </div>
</section>


<!-- ══ CTA ════════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="cta-banner reveal">
      <h2 class="cta-banner__title">Travel with <span class="gold-text">Prime</span></h2>
      <p class="cta-banner__text">Get an instant quote online, or speak to us directly &mdash;
         any hour, any day.</p>
      <div class="btn-row btn-row--center">
        <a href="booking.php" class="btn btn--gold btn--lg"><?= icon('calendar') ?><span>Book a Ride</span></a>
        <a href="contact.php" class="btn btn--outline btn--lg"><?= icon('mail') ?><span>Contact Us</span></a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
