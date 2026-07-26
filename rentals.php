<?php
/** Car rentals. */
require_once __DIR__ . '/includes/functions.php';

$page_slug        = 'rentals';
$page_title       = 'Luxury Car Rental';
$page_description = 'Rent a Mercedes-Benz S580, Cadillac Escalade ESV or Chevrolet Suburban in Toronto. Daily and weekly rates, clear requirements, and a simple rental request form.';

$rentals = get_rental_vehicles();

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="index.php">Home</a><?= icon('chevron-right') ?><span>Car Rentals</span>
    </nav>
    <span class="eyebrow">Self-Drive</span>
    <h1 class="page-head__title">Rent a <span class="gold-text">luxury vehicle</span></h1>
    <p class="page-head__lead">Prefer to drive yourself? Our rental fleet is available by the day
       or the week, prepared to exactly the same standard as our chauffeured vehicles.</p>
    <div class="btn-row mt-6">
      <a href="booking.php?type=rental" class="btn btn--gold"><?= icon('key') ?><span>Request a Rental</span></a>
      <a href="<?= e(tel_url()) ?>" class="btn btn--outline"><?= icon('phone') ?><span>Call Us</span></a>
    </div>
  </div>
</section>


<!-- ══ RENTAL FLEET ═══════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">Available to Rent</span>
      <h2 class="section-title">Rental <span class="gold-text">fleet &amp; rates</span></h2>
      <p class="section-lead">Rates below are before HST and assume standard mileage.
         Weekly hire offers the best value on bookings of five days or more.</p>
    </div>

    <?php if (!$rentals): ?>
      <div class="alert alert--info">
        <?= icon('info') ?>
        <span>Our rental fleet is being updated. Please
          <a href="contact.php">contact us</a> for current availability and rates.</span>
      </div>
    <?php else: ?>

    <div class="grid grid--3 reveal-group">
      <?php foreach ($rentals as $v):
        $img   = vehicle_image_url($v);
        $feats = array_slice(vehicle_features($v), 0, 4); ?>
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
            <span class="spec"><?= icon('users') ?><?= (int)$v['passengers'] ?> seats</span>
            <span class="spec"><?= icon('luggage') ?><?= (int)$v['luggage'] ?> bags</span>
          </div>

          <ul class="feature-list">
            <?php foreach ($feats as $f): ?>
            <li><?= icon('check') ?><span><?= e($f) ?></span></li>
            <?php endforeach; ?>
          </ul>

          <div class="vehicle-card__foot">
            <dl style="display:grid;gap:var(--s-2);margin-bottom:var(--s-5);">
              <div style="display:flex;justify-content:space-between;align-items:baseline;gap:var(--s-4);">
                <dt style="font-size:var(--fs-sm);color:var(--text-dim);">Daily</dt>
                <dd class="tabular" style="font-family:var(--font-display);font-size:var(--fs-lg);color:var(--gold);">
                  <?= money_short((float)$v['rental_daily']) ?></dd>
              </div>
              <?php if ((float)$v['rental_weekly'] > 0): ?>
              <div style="display:flex;justify-content:space-between;align-items:baseline;gap:var(--s-4);">
                <dt style="font-size:var(--fs-sm);color:var(--text-dim);">Weekly</dt>
                <dd class="tabular" style="font-family:var(--font-display);font-size:var(--fs-lg);color:var(--gold);">
                  <?= money_short((float)$v['rental_weekly']) ?></dd>
              </div>
              <?php endif; ?>
            </dl>

            <a href="booking.php?type=rental&amp;vehicle=<?= e($v['slug']) ?>"
               class="btn btn--gold btn--sm btn--block">
              <?= icon('key') ?><span>Rent this vehicle</span>
            </a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>


<!-- ══ REQUIREMENTS ═══════════════════════════════════════════════ -->
<section class="section section--alt">
  <div class="container">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">Before You Book</span>
      <h2 class="section-title">Rental <span class="gold-text">requirements</span></h2>
      <p class="section-lead">Straightforward conditions, stated up front. Bring these with you at
         collection and you&rsquo;ll be on the road in minutes.</p>
    </div>

    <div class="grid grid--3 reveal-group">
      <?php
      $reqs = [
        ['users',  'Minimum age 25',        'Drivers must be 25 or older. Drivers aged 21–24 may be considered on the S-Class only, subject to an additional young-driver surcharge.'],
        ['key',    'Full driving licence',  'A valid full licence held for at least three years, plus a second piece of government-issued photo ID. International licences accepted with an IDP.'],
        ['lock',   'Security deposit',      'A refundable deposit is pre-authorised on a credit card in the renter’s own name at collection, and released on safe return of the vehicle.'],
        ['shield', 'Insurance',             'Valid personal auto insurance or our optional damage waiver. Proof of coverage is required before keys are handed over.'],
        ['route',  'Mileage allowance',     '250 km per day or 1,500 km per week included. Additional kilometres are charged at a per-km rate quoted on your agreement.'],
        ['droplet','Fuel &amp; condition',  'Vehicles leave fully fuelled and detailed, and should be returned the same way. Refuelling and valet charges apply otherwise.'],
      ];
      foreach ($reqs as [$ico, $title, $text]): ?>
      <article class="card">
        <div class="card__icon"><?= icon($ico) ?></div>
        <h3 class="card__title"><?= $title ?></h3>
        <p class="card__text"><?= e($text) ?></p>
      </article>
      <?php endforeach; ?>
    </div>

    <div class="alert alert--gold mt-6 reveal">
      <?= icon('info') ?>
      <span>Rental terms are confirmed in writing before collection. Smoking, off-road use and
        travel outside Ontario without prior written approval are not permitted. Full terms are
        set out in your <a href="terms.php">rental agreement</a>.</span>
    </div>
  </div>
</section>


<!-- ══ HOW IT WORKS ═══════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">The Process</span>
      <h2 class="section-title">Renting is <span class="gold-text">simple</span></h2>
    </div>

    <div class="steps reveal-group">
      <div class="step">
        <h3 class="step__title">Send a Request</h3>
        <p class="step__text">Choose your vehicle and dates and submit the rental request form.
           No payment is taken at this stage.</p>
      </div>
      <div class="step">
        <h3 class="step__title">We Confirm &amp; Quote</h3>
        <p class="step__text">We check availability, confirm your total including HST, and send the
           rental agreement and requirements list by email.</p>
      </div>
      <div class="step">
        <h3 class="step__title">Collect &amp; Drive</h3>
        <p class="step__text">Bring your licence, ID and deposit card. We walk you around the
           vehicle, hand over the keys, and you&rsquo;re away.</p>
      </div>
    </div>
  </div>
</section>


<!-- ══ CTA ════════════════════════════════════════════════════════ -->
<section class="section section--alt">
  <div class="container">
    <div class="cta-banner reveal">
      <h2 class="cta-banner__title">Ready to <span class="gold-text">take the wheel?</span></h2>
      <p class="cta-banner__text">Send us your dates and preferred vehicle. We&rsquo;ll come back
         with availability and a firm all-in price.</p>
      <div class="btn-row btn-row--center">
        <a href="booking.php?type=rental" class="btn btn--gold btn--lg">
          <?= icon('key') ?><span>Request a Rental</span>
        </a>
        <a href="<?= e(whatsapp_url('Hello Prime Luxury Rides, I would like to enquire about renting a vehicle.')) ?>"
           target="_blank" rel="noopener noreferrer" class="btn btn--outline btn--lg">
          <?= icon('whatsapp') ?><span>WhatsApp Us</span>
        </a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
