<?php
/**
 * Admin shell header. Requires $admin (from require_admin()) and $admin_page.
 */
$admin_page  = $admin_page  ?? '';
$admin_title = $admin_title ?? 'Dashboard';
$admin_sub   = $admin_sub   ?? '';

// Sidebar counters
try {
    $n_pending  = (int)(db_one("SELECT COUNT(*) n FROM `bookings` WHERE `status` = 'pending'")['n'] ?? 0);
    $n_unread   = (int)(db_one('SELECT COUNT(*) n FROM `enquiries` WHERE `is_read` = 0')['n'] ?? 0);
} catch (Throwable $e) {
    $n_pending = $n_unread = 0;
}

$nav = [
    ['Overview', [
        ['index.php',    'dashboard', 'Dashboard', ''],
    ]],
    ['Operations', [
        ['bookings.php', 'calendar',  'Bookings',  $n_pending ?: ''],
        ['enquiries.php','inbox',     'Enquiries', $n_unread ?: ''],
    ]],
    ['Configuration', [
        ['vehicles.php', 'car',       'Vehicles',  ''],
        ['rates.php',    'tag',       'Flat Rates',''],
        ['settings.php', 'settings',  'Settings',  ''],
    ]],
];

$initials = strtoupper(mb_substr((string)($admin['name'] ?? 'A'), 0, 1));
$fl = flash();
?>
<!DOCTYPE html>
<html lang="en-CA">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($admin_title) ?> | Admin &mdash; <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#08070a">
<link rel="icon" href="../assets/img/icon.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../assets/css/main.css?v=<?= @filemtime(ROOT_PATH . '/assets/css/main.css') ?: '1' ?>">
<link rel="stylesheet" href="../assets/css/admin.css?v=<?= @filemtime(ROOT_PATH . '/assets/css/admin.css') ?: '1' ?>">
</head>
<body class="admin">
<a class="skip-link" href="#adminMain">Skip to main content</a>

<div class="admin-shell">

  <aside class="admin-side" id="adminSide">
    <div class="admin-brand">
      <img src="../assets/img/logo.png" alt="<?= e(SITE_NAME) ?>" width="182" height="60">
    </div>

    <nav class="admin-nav" aria-label="Admin navigation">
      <?php foreach ($nav as [$group, $items]): ?>
        <span class="admin-nav__label"><?= e($group) ?></span>
        <?php foreach ($items as [$href, $ico, $label, $count]): ?>
        <a href="<?= e($href) ?>" <?= $admin_page === $href ? 'aria-current="page"' : '' ?>>
          <?= icon($ico) ?><span><?= e($label) ?></span>
          <?php if ($count !== ''): ?><span class="badge"><?= (int)$count ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
      <?php endforeach; ?>

      <span class="admin-nav__label">Site</span>
      <a href="../index.php" target="_blank" rel="noopener">
        <?= icon('external') ?><span>View website</span>
      </a>
    </nav>

    <div class="admin-side__foot">
      <div class="admin-user">
        <span class="admin-user__avatar"><?= e($initials) ?></span>
        <span style="min-width:0;">
          <span class="admin-user__name"><?= e($admin['name'] ?? '') ?></span><br>
          <span class="admin-user__mail"><?= e($admin['email'] ?? '') ?></span>
        </span>
      </div>
      <a href="logout.php" class="btn btn--outline btn--sm btn--block">
        <?= icon('logout') ?><span>Sign out</span>
      </a>
    </div>
  </aside>

  <div class="admin-scrim" id="adminScrim"></div>

  <main class="admin-main" id="adminMain">
    <div class="admin-topbar">
      <button type="button" class="admin-burger" id="adminBurger"
              aria-label="Open navigation" aria-expanded="false" aria-controls="adminSide">
        <?= icon('menu') ?>
      </button>
      <div style="min-width:0;">
        <h1 class="admin-title"><?= e($admin_title) ?></h1>
        <?php if ($admin_sub !== ''): ?>
        <p class="admin-sub"><?= e($admin_sub) ?></p>
        <?php endif; ?>
      </div>
      <?php if (!empty($admin_actions)): ?>
      <div class="admin-topbar__actions"><?= $admin_actions ?></div>
      <?php endif; ?>
    </div>

    <?php if ($fl): ?>
    <div class="alert alert--<?= e($fl['type'] === 'error' ? 'error' : ($fl['type'] === 'info' ? 'info' : 'success')) ?>"
         role="status">
      <?= icon($fl['type'] === 'error' ? 'alert' : 'check-circle') ?>
      <span><?= e($fl['message']) ?></span>
    </div>
    <?php endif; ?>
