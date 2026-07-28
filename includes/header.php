<?php
/**
 * Shared document head + site header.
 *
 * Set before including:
 *   $page_title, $page_description, $page_slug, $og_image (optional),
 *   $body_class (optional), $schema_extra (optional JSON-LD array)
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/icons.php';

$page_slug        = $page_slug        ?? '';
$page_title       = $page_title       ?? SITE_TAGLINE;
$page_description = $page_description ?? setting('meta_description',
    'Premium chauffeur service for airport transfers, corporate travel and events across Toronto & the GTA.');
$body_class       = $body_class       ?? '';
$page_robots      = $page_robots      ?? 'index, follow, max-image-preview:large';

$full_title = ($page_slug === 'home')
    ? $page_title . ' | ' . SITE_NAME
    : $page_title . ' | ' . SITE_NAME;

// Pages with a rewritten pretty URL can declare their canonical path.
if (!empty($canonical_path)) {
    $canonical = rtrim(SITE_URL, '/') . $canonical_path;
} else {
    $canonical = rtrim(SITE_URL, '/') . ($_SERVER['REQUEST_URI'] ?? '/');
    $canonical = strtok($canonical, '?');
}
$og_image  = $og_image ?? rtrim(SITE_URL, '/') . '/assets/img/logo.png';

$nav_items = [
    ['href' => 'index.php',    'label' => 'Home',     'slug' => 'home',     'icon' => 'home'],
    ['href' => 'about.php',    'label' => 'About',    'slug' => 'about',    'icon' => 'users'],
    ['href' => 'services.php', 'label' => 'Services', 'slug' => 'services', 'icon' => 'sparkles'],
    ['href' => 'fleet.php',    'label' => 'Fleet',    'slug' => 'fleet',    'icon' => 'car'],
    ['href' => 'rates.php',    'label' => 'Rates',    'slug' => 'rates',    'icon' => 'tag'],
    ['href' => 'rentals.php',  'label' => 'Rentals',  'slug' => 'rentals',  'icon' => 'key'],
    ['href' => 'membership.php', 'label' => 'Membership', 'slug' => 'membership', 'icon' => 'crown'],
    ['href' => 'contact.php',  'label' => 'Contact',  'slug' => 'contact',  'icon' => 'mail'],
];

// Membership can be switched off entirely in Admin → Settings.
if ((int)setting_num('membership_enabled', 1) !== 1) {
    $nav_items = array_values(array_filter($nav_items,
        fn($i) => $i['slug'] !== 'membership'));
}
?>
<!DOCTYPE html>
<html lang="en-CA">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($full_title) ?></title>
<meta name="description" content="<?= e($page_description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta name="theme-color" content="#08070a">
<meta name="robots" content="<?= e($page_robots) ?>">

<!-- Open Graph / social -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($full_title) ?>">
<meta property="og:description" content="<?= e($page_description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($og_image) ?>">
<meta property="og:locale" content="en_CA">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($full_title) ?>">
<meta name="twitter:description" content="<?= e($page_description) ?>">
<meta name="twitter:image" content="<?= e($og_image) ?>">

<link rel="icon" href="assets/img/icon.png" type="image/png">
<link rel="apple-touch-icon" href="assets/img/icon.png">

<!-- Fonts: preconnect + swap so text is never invisible -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap">

<link rel="stylesheet" href="assets/css/main.css?v=<?= @filemtime(ROOT_PATH . '/assets/css/main.css') ?: '1' ?>">

<!-- Local business structured data -->
<script type="application/ld+json">
<?= e_json(array_filter([
    '@context'    => 'https://schema.org',
    '@type'       => 'LimousineService',
    'name'        => SITE_NAME,
    'description' => $page_description,
    'url'         => rtrim(SITE_URL, '/'),
    'logo'        => rtrim(SITE_URL, '/') . '/assets/img/logo.png',
    'image'       => $og_image,
    'telephone'   => setting('phone'),
    'email'       => setting('email', ADMIN_EMAIL),
    'priceRange'  => '$$$',
    'address'     => [
        '@type'           => 'PostalAddress',
        'addressLocality' => 'Toronto',
        'addressRegion'   => 'ON',
        'addressCountry'  => 'CA',
    ],
    'areaServed'  => array_map(
        fn($c) => ['@type' => 'City', 'name' => trim($c)],
        ['Toronto', 'Mississauga', 'Brampton', 'Vaughan', 'Markham', 'Scarborough', 'Hamilton', 'Oakville']
    ),
    'openingHoursSpecification' => [[
        '@type'     => 'OpeningHoursSpecification',
        'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],
        'opens'     => '00:00',
        'closes'    => '23:59',
    ]],
    'sameAs' => array_values(array_filter([
        setting('facebook'), setting('instagram'), setting('x_twitter'), setting('linkedin'),
    ])),
])) ?>
</script>
<?php if (!empty($schema_extra)): ?>
<script type="application/ld+json"><?= e_json($schema_extra) ?></script>
<?php endif; ?>
</head>

<body class="<?= e($body_class) ?>">
<a class="skip-link" href="#main">Skip to main content</a>

<header class="site-header" id="siteHeader">
  <div class="container nav">
    <a href="index.php" class="brand" aria-label="<?= e(SITE_NAME) ?> — home">
      <img src="assets/img/logo.png" alt="<?= e(SITE_NAME) ?>" width="243" height="80" fetchpriority="high">
    </a>

    <nav class="nav-links" id="navLinks" aria-label="Main navigation">

      <!-- Sheet chrome — mobile only -->
      <div class="nav-sheet__head">
        <span class="nav-sheet__grab" aria-hidden="true"></span>
        <span class="nav-sheet__title">Menu</span>
        <button type="button" class="nav-sheet__close" data-nav-close aria-label="Close menu">
          <?= icon('close') ?>
        </button>
      </div>

      <div class="nav-links__list">
        <?php foreach ($nav_items as $item): ?>
        <a href="<?= e($item['href']) ?>"
           <?= $page_slug === $item['slug'] ? 'aria-current="page"' : '' ?>>
          <span class="nav-links__icon"><?= icon($item['icon']) ?></span>
          <span class="nav-links__label"><?= e($item['label']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Sheet footer — mobile only -->
      <div class="nav-sheet__foot">
        <a href="booking.php" class="btn btn--gold btn--block">
          <?= icon('calendar') ?><span>Book a Ride</span>
        </a>
        <a href="<?= e(whatsapp_url()) ?>" target="_blank" rel="noopener noreferrer"
           class="btn btn--outline btn--block">
          <?= icon('whatsapp') ?><span>WhatsApp Us</span>
        </a>
        <p class="nav-sheet__meta">
          <a href="<?= e(tel_url()) ?>"><?= e(setting('phone')) ?></a><br>
          <span><?= e(setting('hours', '24/7')) ?></span>
        </p>
      </div>

      <a href="booking.php" class="btn btn--gold btn--sm nav-drawer-cta">Book a Ride</a>
    </nav>

    <div class="nav-actions">
      <?php $nav_customer = function_exists('customer') ? customer() : null; ?>
      <?php if ($nav_customer): ?>
      <a href="account.php" class="btn btn--outline btn--sm btn--desktop-only"
         aria-label="My account">
        <?= icon('users') ?><span><?= e(explode(' ', trim((string)$nav_customer['full_name']))[0]) ?></span>
      </a>
      <?php else: ?>
      <a href="signin.php" class="btn btn--ghost btn--sm btn--desktop-only">Sign in</a>
      <?php endif; ?>

      <a href="<?= e(tel_url()) ?>" class="btn btn--outline btn--sm btn--desktop-only">
        <?= icon('phone') ?><span><?= e(setting('phone', 'Call Us')) ?></span>
      </a>
      <a href="booking.php" class="btn btn--gold btn--sm btn--desktop-only">Book a Ride</a>

      <button class="nav-toggle" id="navToggle" type="button"
              aria-expanded="false" aria-controls="navLinks" aria-label="Open menu">
        <?= icon('menu', 'icon-open') ?>
        <?= icon('close', 'icon-close') ?>
      </button>
    </div>
  </div>
</header>
<div class="nav-scrim" id="navScrim" hidden></div>

<main id="main">
