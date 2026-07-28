<?php
/** Shared site footer + floating actions. */
$socials = array_filter([
    'facebook'  => setting('facebook'),
    'instagram' => setting('instagram'),
    'x-twitter' => setting('x_twitter'),
    'linkedin'  => setting('linkedin'),
]);
$social_names = [
    'facebook' => 'Facebook', 'instagram' => 'Instagram',
    'x-twitter' => 'X (Twitter)', 'linkedin' => 'LinkedIn',
];
?>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">

      <div class="footer-brand">
        <img src="assets/img/logo.png" alt="<?= e(SITE_NAME) ?>" width="243" height="80" loading="lazy">
        <p>Toronto&rsquo;s premium chauffeur service. Airport transfers, corporate travel
           and special occasions &mdash; delivered with discretion, punctuality and an
           impeccably maintained luxury fleet.</p>
        <?php if ($socials): ?>
        <div class="social-row">
          <?php foreach ($socials as $key => $url): ?>
            <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"
               aria-label="<?= e($social_names[$key] ?? $key) ?>"><?= icon($key) ?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="footer-col">
        <h3>Explore</h3>
        <ul>
          <li><a href="index.php">Home</a></li>
          <li><a href="about.php">About Us</a></li>
          <li><a href="services.php">Services</a></li>
          <li><a href="fleet.php">Our Fleet</a></li>
          <li><a href="rates.php">Flat Rates</a></li>
          <li><a href="rentals.php">Car Rentals</a></li>
          <?php if ((int)setting_num('membership_enabled', 1) === 1): ?>
          <li><a href="membership.php">Membership</a></li>
          <?php endif; ?>
          <?php if (function_exists('customer') && customer()): ?>
          <li><a href="account.php">My Account</a></li>
          <?php else: ?>
          <li><a href="signin.php">Sign In</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <?php
      // Popular routes — internal links that help these pages rank.
      $footer_routes = [];
      try {
          $footer_routes = db_all(
              'SELECT DISTINCT f.`city`, f.`city_key`
                 FROM `flat_rates` f
                 JOIN `vehicles` v ON v.`id` = f.`vehicle_id`
                WHERE v.`is_active` = 1 AND f.`price` IS NOT NULL
             ORDER BY f.`distance_km` DESC
                LIMIT 6');
      } catch (Throwable $ex) { /* footer must never break the page */ }
      if ($footer_routes): ?>
      <div class="footer-col">
        <h3>Popular Routes</h3>
        <ul>
          <?php foreach ($footer_routes as $fr): ?>
          <li><a href="/toronto-to-<?= e(str_replace(' ', '-', (string)$fr['city_key'])) ?>-car-service">
            Toronto to <?= e($fr['city']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <div class="footer-col">
        <h3>Services</h3>
        <ul>
          <li><a href="services.php#airport">Airport Transfers</a></li>
          <li><a href="services.php#corporate">Corporate Chauffeur</a></li>
          <li><a href="services.php#events">Event Transportation</a></li>
          <li><a href="services.php#hourly">Hourly Chauffeur</a></li>
          <li><a href="services.php#vip">VIP Service</a></li>
          <li><a href="services.php#night-out">Night Out</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h3>Get in Touch</h3>
        <ul class="footer-contact">
          <li><?= icon('phone') ?><a href="<?= e(tel_url()) ?>"><?= e(setting('phone')) ?></a></li>
          <li><?= icon('mail') ?><a href="mailto:<?= e(setting('email', ADMIN_EMAIL)) ?>"><?= e(setting('email', ADMIN_EMAIL)) ?></a></li>
          <li><?= icon('clock') ?><span><?= e(setting('hours', '24/7')) ?></span></li>
          <li><?= icon('map-pin') ?><span>Toronto &amp; the Greater Toronto Area</span></li>
        </ul>
        <a href="booking.php" class="btn btn--gold btn--sm mt-5">Book a Ride</a>
      </div>

    </div>

    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> <?= e(setting('company_name', SITE_NAME)) ?>. All rights reserved.</p>
      <nav aria-label="Legal">
        <a href="privacy.php">Privacy Policy</a>
        <a href="terms.php">Terms &amp; Conditions</a>
        <a href="contact.php">Contact</a>
      </nav>
    </div>
  </div>
</footer>

<!-- Floating actions -->
<div class="floating">
  <a class="fab fab--whatsapp" href="<?= e(whatsapp_url()) ?>"
     target="_blank" rel="noopener noreferrer" aria-label="Chat with us on WhatsApp">
    <?= icon('whatsapp', '', 28) ?>
  </a>
  <a class="fab fab--call" href="<?= e(tel_url()) ?>" aria-label="Call us now">
    <?= icon('phone', '', 26) ?>
  </a>
</div>

<!-- App-style bottom tab bar (mobile) -->
<nav class="app-bar" aria-label="Quick navigation">
  <a class="app-bar__item" href="index.php"
     <?= ($page_slug ?? '') === 'home' ? 'aria-current="page"' : '' ?>>
    <?= icon('home') ?><span>Home</span>
  </a>

  <a class="app-bar__item" href="fleet.php"
     <?= ($page_slug ?? '') === 'fleet' ? 'aria-current="page"' : '' ?>>
    <?= icon('car') ?><span>Fleet</span>
  </a>

  <a class="app-bar__book" href="booking.php" aria-label="Book a ride">
    <span class="app-bar__fab"><?= icon('calendar', '', 26) ?></span>
    <span>Book</span>
  </a>

  <a class="app-bar__item" href="<?= e(tel_url()) ?>">
    <?= icon('phone') ?><span>Call</span>
  </a>

  <button type="button" class="app-bar__item" id="appMenuBtn"
          aria-expanded="false" aria-controls="navLinks">
    <?= icon('menu') ?><span>Menu</span>
  </button>
</nav>

<script src="assets/js/main.js?v=<?= @filemtime(ROOT_PATH . '/assets/js/main.js') ?: '1' ?>" defer></script>
<?php
/*
 * Google Maps loads on any page carrying an address input — the booking
 * wizard and the home-page quick search. Kept here rather than per-page
 * so the two can never drift apart.
 */
if (maps_enabled() && in_array($page_slug ?? '', ['home', 'booking'], true)): ?>
<script src="assets/js/maps.js?v=<?= @filemtime(ROOT_PATH . '/assets/js/maps.js') ?: '1' ?>" defer></script>
<script async defer
        src="https://maps.googleapis.com/maps/api/js?key=<?= e(rawurlencode(GOOGLE_MAPS_API_KEY)) ?>&libraries=places&callback=plrInitMaps&loading=async"></script>
<?php endif; ?>

<?php if (!empty($page_scripts)) foreach ((array)$page_scripts as $src): ?>
<script src="<?= e($src) ?>?v=<?= @filemtime(ROOT_PATH . '/' . ltrim(parse_url($src, PHP_URL_PATH) ?? '', '/')) ?: '1' ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
