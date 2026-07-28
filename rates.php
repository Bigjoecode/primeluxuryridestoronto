<?php
/** City-to-city flat rates. */
require_once __DIR__ . '/includes/functions.php';

$page_slug        = 'rates';
$page_title       = 'City-to-City Flat Rates';
$page_description = 'Transparent one-way flat rates from Toronto to Hamilton, Barrie, Niagara Falls, London, Kingston and Ottawa. Published pricing for every vehicle in our fleet. HST not included.';

$vehicles = get_vehicles();
$threshold = (int)setting_num('flat_rate_threshold_km', FLAT_RATE_THRESHOLD_KM);

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="index.php">Home</a><?= icon('chevron-right') ?><span>Flat Rates</span>
    </nav>
    <span class="eyebrow">Pricing</span>
    <h1 class="page-head__title">Transparent. Affordable.<br>
      <span class="gold-text">Luxury without the luxury price.</span></h1>
    <p class="page-head__lead">These prices are for <strong>one-way trips</strong> between major
       cities in Ontario. HST not included.</p>
  </div>
</section>


<!-- ══ RATE TABLES ════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">

    <div class="alert alert--info reveal">
      <?= icon('info') ?>
      <span>Journeys under <strong><?= $threshold ?>&nbsp;km</strong> are priced dynamically
        &mdash; a base fare plus a per-kilometre and per-minute rate &mdash; which usually works
        out cheaper than a flat rate on short trips. Anything <?= $threshold ?>&nbsp;km or more
        uses the published flat rate below.</span>
    </div>

    <!-- Vehicle tabs -->
    <div class="rate-tabs reveal" role="tablist" aria-label="Choose a vehicle">
      <?php foreach ($vehicles as $i => $v): ?>
      <button type="button" class="rate-tab" role="tab"
              data-rate-tab="<?= e($v['slug']) ?>"
              aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
              aria-controls="panel-<?= e($v['slug']) ?>">
        <?= e($v['name']) ?>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Panels -->
    <?php foreach ($vehicles as $i => $v):
      $rates = get_flat_rates((int)$v['id']); ?>
    <div id="panel-<?= e($v['slug']) ?>" data-rate-panel="<?= e($v['slug']) ?>"
         role="tabpanel" <?= $i === 0 ? '' : 'hidden' ?>>

      <div class="mb-5" id="<?= e($v['slug']) ?>" style="scroll-margin-top:7rem;">
        <h2 class="section-title" style="font-size:var(--fs-xl);margin-bottom:var(--s-2);">
          <?= e($v['class_label']) ?> &mdash; <span class="gold-text"><?= e($v['name']) ?></span>
        </h2>
        <p class="text-muted" style="font-size:var(--fs-sm);">
          <?= (int)$v['passengers'] ?> passengers &middot; <?= (int)$v['luggage'] ?> bags
          &middot; Hourly from <?= money_short((float)$v['hourly_rate']) ?>/hr
          (<?= (int)$v['min_hours'] ?>h minimum)
        </p>
      </div>

      <div class="table-scroll">
        <table class="rate-table">
          <caption class="sr-only">One-way flat rates from Toronto for the <?= e($v['name']) ?></caption>
          <thead>
            <tr>
              <th scope="col">From Toronto &rarr;</th>
              <th scope="col">Distance</th>
              <th scope="col">Your Price</th>
              <th scope="col"><span class="sr-only">Book</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rates as $r): ?>
            <tr>
              <th scope="row" style="font-weight:600;"><?= e($r['city']) ?></th>
              <td class="col-km">~<?= (int)$r['distance_km'] ?> km</td>
              <td class="col-price">
                <?php if ($r['price'] !== null): ?>
                  <?= money_short((float)$r['price']) ?>
                <?php else: ?>
                  <span class="is-dynamic">Dynamic Pricing</span>
                <?php endif; ?>
              </td>
              <td>
                <a class="btn btn--outline btn--sm"
                   href="booking.php?vehicle=<?= e($v['slug']) ?>&amp;service=city_to_city&amp;to=<?= rawurlencode($r['city']) ?>">
                  Book
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <p class="text-muted mt-4" style="font-size:var(--fs-sm);">
        Prices shown are one-way and exclude HST (<?= rtrim(rtrim(number_format(setting_num('hst_rate', DEFAULT_HST_RATE), 2), '0'), '.') ?>%),
        which is added at checkout. Return journeys are booked as two one-way trips.
      </p>
    </div>
    <?php endforeach; ?>

  </div>
</section>


<!-- ══ HOW PRICING WORKS ══════════════════════════════════════════ -->
<section class="section section--alt">
  <div class="container">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">No Surprises</span>
      <h2 class="section-title">How our pricing <span class="gold-text">works</span></h2>
    </div>

    <div class="grid grid--3 reveal-group">
      <article class="card">
        <div class="card__icon"><?= icon('navigation') ?></div>
        <h3 class="card__title">Under <?= $threshold ?> km</h3>
        <p class="card__text">Dynamic pricing: a base fare plus a per-kilometre and per-minute
           rate, calculated from your actual route. You see the full figure before you confirm.</p>
      </article>

      <article class="card">
        <div class="card__icon"><?= icon('route') ?></div>
        <h3 class="card__title"><?= $threshold ?> km and over</h3>
        <p class="card__text">A published flat rate from the table above. The price is fixed
           regardless of traffic or the route your chauffeur takes.</p>
      </article>

      <article class="card">
        <div class="card__icon"><?= icon('clock') ?></div>
        <h3 class="card__title">Hourly hire</h3>
        <p class="card__text">A straight hourly rate with a minimum booking &mdash; three hours
           on most vehicles, four on the Maybach. Unlimited stops within the hire.</p>
      </article>
    </div>

    <div class="grid grid--2 mt-6 reveal-group">
      <article class="card">
        <div class="card__icon"><?= icon('tag') ?></div>
        <h3 class="card__title">Membership discounts</h3>
        <p class="card__text mb-4">Regular clients can join our membership programme for a
           standing discount applied automatically at checkout.
           <a href="membership.php" class="text-gold">See the tiers &rarr;</a></p>
        <ul class="feature-list">
          <li><?= icon('check') ?><span><strong>Elite Member</strong> &mdash;
              <?= (int)setting_num('elite_discount', 30) ?>% off the final price</span></li>
          <li><?= icon('check') ?><span><strong>VIP Member</strong> &mdash;
              <?= (int)setting_num('vip_discount', 40) ?>% off the final price</span></li>
        </ul>
      </article>

      <article class="card">
        <div class="card__icon"><?= icon('shield-check') ?></div>
        <h3 class="card__title">What&rsquo;s included</h3>
        <p class="card__text mb-4">Every quoted fare already covers:</p>
        <ul class="feature-list">
          <li><?= icon('check') ?><span>Meet &amp; greet and luggage assistance</span></li>
          <li><?= icon('check') ?><span>Bottled water, Wi-Fi and reading material</span></li>
          <li><?= icon('check') ?><span>Flight tracking on airport transfers</span></li>
          <li><?= icon('check') ?><span>All tolls and standard wait time</span></li>
        </ul>
      </article>
    </div>
  </div>
</section>


<!-- ══ CTA ════════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="cta-banner reveal">
      <h2 class="cta-banner__title">Get your <span class="gold-text">exact price</span></h2>
      <p class="cta-banner__text">Enter your pickup and drop-off and we&rsquo;ll show you the full
         fare &mdash; including HST &mdash; before you commit to anything.</p>
      <div class="btn-row btn-row--center">
        <a href="booking.php" class="btn btn--gold btn--lg"><?= icon('calendar') ?><span>Get an Instant Quote</span></a>
        <a href="<?= e(tel_url()) ?>" class="btn btn--outline btn--lg"><?= icon('phone') ?><span>Call Us</span></a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
