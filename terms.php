<?php
/** Terms & Conditions. */
require_once __DIR__ . '/includes/functions.php';

$page_slug        = 'terms';
$page_title       = 'Terms & Conditions';
$page_description = 'Terms and conditions for chauffeur bookings and vehicle rentals with Prime Luxury Rides Toronto.';

$company = setting('company_name', SITE_NAME);
$email   = setting('email', ADMIN_EMAIL);
$phone   = setting('phone');
$hst     = rtrim(rtrim(number_format(setting_num('hst_rate', DEFAULT_HST_RATE), 2), '0'), '.');
$thresh  = (int)setting_num('flat_rate_threshold_km', FLAT_RATE_THRESHOLD_KM);
$updated = 'July 2026';

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container container--narrow">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="index.php">Home</a><?= icon('chevron-right') ?><span>Terms &amp; Conditions</span>
    </nav>
    <h1 class="page-head__title">Terms &amp; Conditions</h1>
    <p class="page-head__lead">Last updated: <?= e($updated) ?></p>
  </div>
</section>

<section class="section">
  <div class="container container--narrow legal">

    <div class="alert alert--info mb-7">
      <?= icon('info') ?>
      <span>These terms are provided as a starting template reflecting the pricing and booking
        rules configured on this site. Please have them reviewed by a qualified legal
        professional, and adjust the cancellation, liability and rental clauses to match your
        insurance and licensing before publishing.</span>
    </div>

    <?php
    $sections = [
      ['Agreement',
        "<p>These terms govern all bookings made with {$company} (&ldquo;we&rdquo;, &ldquo;us&rdquo;), whether made through this website, by telephone, by email or by messaging. By making a booking you accept these terms on behalf of yourself and all passengers travelling with you.</p>"],

      ['Bookings and confirmation',
        "<p>A booking request submitted through our website is an offer, not a confirmed reservation. Your booking is confirmed only when we send you a confirmation and assign a chauffeur and vehicle.</p>
         <p>We ask for a minimum of " . MIN_LEAD_TIME_HOURS . " hours&rsquo; notice for online bookings. For shorter notice, please call us on {$phone} and we will accommodate you where possible.</p>
         <p>You are responsible for the accuracy of the details you give us &mdash; particularly pickup addresses, dates, times and flight numbers. We are not liable for delays or missed pickups caused by incorrect information.</p>"],

      ['Pricing',
        "<p>Our pricing works as follows:</p>
         <ul class='feature-list'>
           <li><strong>Journeys under {$thresh}&nbsp;km</strong> are priced dynamically: a base fare plus a per-kilometre and per-minute rate for the selected vehicle.</li>
           <li><strong>Journeys of {$thresh}&nbsp;km or more</strong> between listed cities use our published one-way flat rate for the selected vehicle.</li>
           <li><strong>Hourly chauffeur hire</strong> is charged at the vehicle&rsquo;s hourly rate, subject to the stated minimum &mdash; three hours on the Mercedes-Benz S580, Cadillac Escalade ESV and Chevrolet Suburban, and four hours on the Mercedes-Maybach GLS 600.</li>
           <li><strong>Rentals</strong> are charged at the published daily or weekly rate.</li>
         </ul>
         <p>All prices are in Canadian dollars. HST at {$hst}% is added to the final amount. Membership discounts, where applicable, are applied before tax and are subject to verification.</p>
         <p>Quotes shown on the website are estimates based on the information you provide. The final fare is confirmed by our team before your journey. Where the actual route differs materially from the one quoted &mdash; for example additional stops or a changed destination &mdash; the fare may be adjusted, and we will tell you before proceeding.</p>"],

      ['Vehicle availability',
        "<p>We reserve the right to substitute a vehicle of equivalent or higher specification where the booked vehicle becomes unavailable through mechanical failure, accident or other circumstances beyond our control. We will notify you as soon as possible.</p>
         <p>The Mercedes-Maybach GLS 600 is available for hourly chauffeur hire and long-distance city-to-city transfers only. It is not available for airport transfers or in-city point-to-point rides.</p>
         <p>Stated passenger and luggage capacities are maximums and must not be exceeded. Where a party arrives with more passengers or luggage than booked, we may be unable to carry them, and the booking will be treated as a no-show.</p>"],

      ['Waiting time',
        "<p>For airport pickups we include 60 minutes of complimentary waiting time from the actual landing time of a tracked flight. For all other pickups we include 15 minutes. Waiting time beyond these allowances is charged in 15-minute increments at the vehicle&rsquo;s hourly rate.</p>"],

      ['Cancellations and no-shows',
        "<p>Unless otherwise agreed in writing:</p>
         <ul class='feature-list'>
           <li>Cancellations made <strong>more than 24 hours</strong> before the scheduled pickup are free of charge.</li>
           <li>Cancellations made <strong>within 24 hours</strong> of pickup may be charged up to 50% of the quoted fare.</li>
           <li><strong>No-shows</strong> &mdash; where no passenger presents at the pickup location within the included waiting time and we cannot reach you &mdash; are charged at the full quoted fare.</li>
         </ul>
         <p>To cancel or change a booking, call us on {$phone} or reply to your confirmation email quoting your booking reference.</p>"],

      ['Payment',
        "<p>Payment terms are confirmed with your booking. We may require full payment in advance, a deposit, or a pre-authorisation on your card to secure a reservation. Corporate accounts may be invoiced by prior arrangement.</p>
         <p>Card payments are processed securely by our payment provider. We do not store your full card details.</p>"],

      ['Conduct in our vehicles',
        "<p>For the safety and comfort of everyone:</p>
         <ul class='feature-list'>
           <li>Smoking and vaping are not permitted in any vehicle.</li>
           <li>Seat belts must be worn by all passengers at all times.</li>
           <li>Our chauffeurs may decline to carry, or may end a journey involving, any passenger who is abusive, threatening, or intoxicated to a degree that presents a risk.</li>
           <li>You are responsible for any damage or soiling caused by you or your party. A cleaning or repair charge may be applied, and will be evidenced before it is charged.</li>
         </ul>"],

      ['Vehicle rentals',
        "<p>Self-drive rentals are subject to an additional rental agreement signed at collection. In summary:</p>
         <ul class='feature-list'>
           <li>Drivers must be 25 or older and hold a valid full licence held for at least three years. Drivers aged 21&ndash;24 may be considered on the Mercedes-Benz S580 only, subject to a young-driver surcharge.</li>
           <li>A refundable security deposit is pre-authorised on a credit card in the renter&rsquo;s own name.</li>
           <li>Valid personal auto insurance or our optional damage waiver is required.</li>
           <li>250&nbsp;km per day or 1,500&nbsp;km per week is included; additional kilometres are charged at the rate stated in your agreement.</li>
           <li>Vehicles must be returned fuelled and in the condition supplied. Refuelling and valet charges apply otherwise.</li>
           <li>Travel outside Ontario, off-road use, and any use for hire or reward require our prior written approval.</li>
         </ul>
         <p>Where these terms and your signed rental agreement differ, the rental agreement prevails.</p>"],

      ['Delays and liability',
        "<p>We plan every journey with time in hand and track inbound flights, but we cannot control traffic, weather, road closures or other events beyond our reasonable control. We are not liable for any indirect or consequential loss &mdash; including missed flights, connections, appointments or events &mdash; arising from delays outside our control.</p>
         <p>Nothing in these terms excludes or limits our liability for death or personal injury caused by our negligence, for fraud, or for any liability that cannot lawfully be excluded.</p>
         <p>Personal property is carried at your own risk. Please check the vehicle before you leave. Items found are held for 30 days; we will contact you where we can identify the owner.</p>"],

      ['Licensing and insurance',
        "<p>We operate as a licensed and commercially insured private transportation provider in Ontario. Proof of licensing and insurance is available on request.</p>"],

      ['Governing law',
        "<p>These terms are governed by the laws of the Province of Ontario and the federal laws of Canada applicable therein. Any dispute will be subject to the exclusive jurisdiction of the courts of Ontario.</p>"],

      ['Contact',
        "<p>Questions about these terms:</p>
         <ul class='feature-list'>
           <li><strong>Email</strong> &mdash; <a href='mailto:{$email}'>{$email}</a></li>
           <li><strong>Phone</strong> &mdash; {$phone}</li>
         </ul>"],
    ];

    foreach ($sections as $i => [$heading, $body]): ?>
    <div class="mb-7 reveal">
      <h2 class="card__title mb-4"><?= e(sprintf('%d. ', $i + 1)) . e($heading) ?></h2>
      <div class="legal__body"><?= $body ?></div>
    </div>
    <?php endforeach; ?>

  </div>
</section>

<style>
  .legal__body p { color: var(--text-muted); margin-bottom: var(--s-4); }
  .legal__body p:last-child { margin-bottom: 0; }
  .legal__body ul { margin-bottom: var(--s-4); }
  .legal__body a { color: var(--gold); text-decoration: underline; }
  .legal__body strong { color: var(--text); }
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
