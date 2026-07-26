<?php
/** 404 — page not found. */
require_once __DIR__ . '/includes/functions.php';

http_response_code(404);

$page_slug        = '404';
$page_title       = 'Page Not Found';
$page_description = 'The page you were looking for could not be found.';

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container container--narrow center">
    <span class="eyebrow eyebrow--center" style="justify-content:center;">Error 404</span>
    <h1 class="page-head__title">This page has <span class="gold-text">taken a detour</span></h1>
    <p class="page-head__lead" style="margin-inline:auto;">
      The page you were looking for doesn&rsquo;t exist, or has moved.
      Let&rsquo;s get you back on route.
    </p>

    <div class="btn-row btn-row--center mt-7">
      <a href="index.php" class="btn btn--gold btn--lg"><span>Back to Home</span></a>
      <a href="booking.php" class="btn btn--outline btn--lg">
        <?= icon('calendar') ?><span>Book a Ride</span>
      </a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head section-head--center">
      <h2 class="section-title" style="font-size:var(--fs-xl);">Popular pages</h2>
    </div>

    <div class="grid grid--4">
      <?php
      $links = [
        ['fleet.php',    'car',      'Our Fleet',    'Vehicles, seats and features'],
        ['services.php', 'sparkles', 'Services',     'Airport, corporate, events'],
        ['rates.php',    'tag',      'Flat Rates',   'Published city-to-city pricing'],
        ['contact.php',  'phone',    'Contact Us',   'Call, WhatsApp or email'],
      ];
      foreach ($links as [$href, $ico, $title, $text]): ?>
      <a class="card card--linked" href="<?= e($href) ?>">
        <div class="card__icon"><?= icon($ico) ?></div>
        <h3 class="card__title" style="font-size:var(--fs-lg);"><?= e($title) ?></h3>
        <p class="card__text" style="font-size:var(--fs-sm);"><?= e($text) ?></p>
        <span class="card__link card__stretch"><span>Open</span><?= icon('arrow-right') ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
