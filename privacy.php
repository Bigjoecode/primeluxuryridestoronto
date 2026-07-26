<?php
/** Privacy Policy. */
require_once __DIR__ . '/includes/functions.php';

$page_slug        = 'privacy';
$page_title       = 'Privacy Policy';
$page_description = 'How Prime Luxury Rides Toronto collects, uses and protects your personal information.';

$company = setting('company_name', SITE_NAME);
$email   = setting('email', ADMIN_EMAIL);
$updated = 'July 2026';

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container container--narrow">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="index.php">Home</a><?= icon('chevron-right') ?><span>Privacy Policy</span>
    </nav>
    <h1 class="page-head__title">Privacy Policy</h1>
    <p class="page-head__lead">Last updated: <?= e($updated) ?></p>
  </div>
</section>

<section class="section">
  <div class="container container--narrow legal">

    <div class="alert alert--info mb-7">
      <?= icon('info') ?>
      <span>This policy is provided as a starting template. Please have it reviewed by a
        qualified legal professional before you publish the site, so that it accurately
        reflects your actual data practices and Canadian privacy law (PIPEDA).</span>
    </div>

    <?php
    $sections = [
      ['Who we are',
        "<p>{$company} (&ldquo;we&rdquo;, &ldquo;us&rdquo;, &ldquo;our&rdquo;) provides private chauffeur and vehicle rental services in Toronto and the Greater Toronto Area. This policy explains what personal information we collect when you use our website or book a journey, why we collect it, and what we do with it.</p>"],

      ['Information we collect',
        "<p>We collect only what we need to arrange and deliver your journey:</p>
         <ul class='feature-list'>
           <li><strong>Contact details</strong> &mdash; your name, email address and telephone number.</li>
           <li><strong>Journey details</strong> &mdash; pickup and drop-off addresses, dates and times, flight numbers, passenger and luggage counts, and any special requests you give us.</li>
           <li><strong>Booking records</strong> &mdash; your booking reference, chosen vehicle, quoted price and booking status.</li>
           <li><strong>Technical data</strong> &mdash; your IP address and basic browser information, recorded with form submissions to help prevent abuse.</li>
         </ul>
         <p>We do <strong>not</strong> store your full payment card details on our servers. Card payments, where offered, are handled directly by our payment processor.</p>"],

      ['How we use your information',
        "<p>Your information is used to:</p>
         <ul class='feature-list'>
           <li>Arrange, confirm and deliver the journey or rental you have booked.</li>
           <li>Send you booking confirmations and service updates by email.</li>
           <li>Contact you about your booking, including changes or delays.</li>
           <li>Keep accounting and tax records as required by Canadian law.</li>
           <li>Protect our website and customers from fraudulent or abusive use.</li>
         </ul>
         <p>We do not sell your personal information, and we do not share it for third-party marketing.</p>"],

      ['Who we share it with',
        "<p>We share your details only where it is necessary to provide the service:</p>
         <ul class='feature-list'>
           <li><strong>Your chauffeur</strong> &mdash; the name, contact number and journey details needed to complete your booking.</li>
           <li><strong>Our payment processor</strong> &mdash; where you pay online, to take payment securely.</li>
           <li><strong>Our email and hosting providers</strong> &mdash; who process data on our behalf under contract.</li>
           <li><strong>Authorities</strong> &mdash; where we are legally required to disclose information.</li>
         </ul>"],

      ['Cookies',
        "<p>Our website uses a single essential session cookie so that booking and contact forms work correctly and to protect them against cross-site request forgery. This cookie contains no personal information and is removed when you close your browser. We do not use advertising or tracking cookies without your consent.</p>"],

      ['How long we keep it',
        "<p>Booking records are retained for as long as necessary to provide the service and to meet our legal, accounting and tax obligations &mdash; generally seven years. General enquiries that do not lead to a booking are kept for up to two years, then deleted.</p>"],

      ['Keeping your information secure',
        "<p>We use appropriate technical and organisational measures to protect your information, including encrypted connections (SSL/TLS), restricted administrative access, and secure password storage. No system is perfectly secure, but we take these responsibilities seriously.</p>"],

      ['Your rights',
        "<p>Under Canadian privacy law you have the right to:</p>
         <ul class='feature-list'>
           <li>Ask what personal information we hold about you.</li>
           <li>Ask us to correct information that is inaccurate or incomplete.</li>
           <li>Ask us to delete information we no longer need to keep.</li>
           <li>Withdraw consent for non-essential communications at any time.</li>
           <li>Make a complaint to the Office of the Privacy Commissioner of Canada.</li>
         </ul>
         <p>To exercise any of these rights, email us at <a href='mailto:{$email}'>{$email}</a>. We will respond within 30 days.</p>"],

      ['Children',
        "<p>Our services are intended for adults. We do not knowingly collect personal information from anyone under 16 except as part of a booking made by a parent or guardian.</p>"],

      ['Changes to this policy',
        "<p>We may update this policy from time to time. The date at the top of this page shows when it was last revised. Material changes will be highlighted on this page.</p>"],

      ['Contact us',
        "<p>Questions about this policy or about how we handle your information:</p>
         <ul class='feature-list'>
           <li><strong>Email</strong> &mdash; <a href='mailto:{$email}'>{$email}</a></li>
           <li><strong>Phone</strong> &mdash; " . e(setting('phone')) . "</li>
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
