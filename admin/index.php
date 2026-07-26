<?php
/** Admin dashboard. */
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();

$admin_page  = 'index.php';
$admin_title = 'Dashboard';
$admin_sub   = 'Welcome back, ' . explode(' ', (string)$admin['name'])[0] . '.';

// ── Stats ──────────────────────────────────────────────────────────
$stats = [
    'today'     => (int)(db_one("SELECT COUNT(*) n FROM `bookings` WHERE DATE(`created_at`) = CURDATE()")['n'] ?? 0),
    'pending'   => (int)(db_one("SELECT COUNT(*) n FROM `bookings` WHERE `status` = 'pending'")['n'] ?? 0),
    'upcoming'  => (int)(db_one("SELECT COUNT(*) n FROM `bookings`
                                 WHERE `pickup_at` >= NOW()
                                   AND `status` IN ('pending','confirmed','assigned')")['n'] ?? 0),
    'enquiries' => (int)(db_one('SELECT COUNT(*) n FROM `enquiries` WHERE `is_read` = 0')['n'] ?? 0),
];

$revenue_month = (float)(db_one("SELECT COALESCE(SUM(`total`),0) t FROM `bookings`
                                 WHERE `status` != 'cancelled'
                                   AND YEAR(`created_at`)  = YEAR(CURDATE())
                                   AND MONTH(`created_at`) = MONTH(CURDATE())")['t'] ?? 0);

$revenue_all = (float)(db_one("SELECT COALESCE(SUM(`total`),0) t FROM `bookings`
                               WHERE `status` != 'cancelled'")['t'] ?? 0);

$recent = db_all('SELECT * FROM `bookings` ORDER BY `created_at` DESC LIMIT 8');

$upcoming = db_all("SELECT * FROM `bookings`
                    WHERE `pickup_at` >= NOW() AND `status` IN ('pending','confirmed','assigned')
                    ORDER BY `pickup_at` ASC LIMIT 6");

$by_vehicle = db_all("SELECT `vehicle_name`, COUNT(*) n, COALESCE(SUM(`total`),0) t
                      FROM `bookings` WHERE `status` != 'cancelled'
                      GROUP BY `vehicle_name` ORDER BY n DESC LIMIT 6");

require __DIR__ . '/includes/header.php';
?>

<!-- ══ STATS ═══════════════════════════════════════════════════════ -->
<div class="stat-grid">
  <?php
  $cards = [
    ['calendar', 'Booked today',    (string)$stats['today'],     '', 'bookings.php'],
    ['clock',    'Awaiting action', (string)$stats['pending'],   'Pending confirmation', 'bookings.php?status=pending'],
    ['route',    'Upcoming rides',  (string)$stats['upcoming'],  'Scheduled from now',   'bookings.php?range=upcoming'],
    ['inbox',    'Unread enquiries',(string)$stats['enquiries'], '', 'enquiries.php'],
  ];
  foreach ($cards as [$ico, $label, $value, $foot, $href]): ?>
  <a class="stat-card" href="<?= e($href) ?>">
    <div class="stat-card__head">
      <span class="stat-card__icon"><?= icon($ico) ?></span>
      <span class="stat-card__label"><?= e($label) ?></span>
    </div>
    <div class="stat-card__value"><?= e($value) ?></div>
    <?php if ($foot !== ''): ?><div class="stat-card__foot"><?= e($foot) ?></div><?php endif; ?>
  </a>
  <?php endforeach; ?>

  <div class="stat-card">
    <div class="stat-card__head">
      <span class="stat-card__icon"><?= icon('tag') ?></span>
      <span class="stat-card__label">Revenue this month</span>
    </div>
    <div class="stat-card__value stat-card__value--gold"><?= money_short($revenue_month) ?></div>
    <div class="stat-card__foot">All time: <?= money_short($revenue_all) ?></div>
  </div>
</div>


<!-- ══ UPCOMING ════════════════════════════════════════════════════ -->
<div class="panel">
  <div class="panel__head">
    <h2 class="panel__title">Next rides</h2>
    <a href="bookings.php?range=upcoming" class="btn btn--outline btn--sm">
      <span>View all</span><?= icon('arrow-right') ?>
    </a>
  </div>

  <?php if (!$upcoming): ?>
    <div class="empty-state">
      <div class="empty-state__icon"><?= icon('calendar') ?></div>
      <h3>No upcoming rides</h3>
      <p>New bookings made through the website will appear here automatically.</p>
    </div>
  <?php else: ?>
  <div class="panel__body panel__body--flush">
    <div class="table-scroll" style="border:0;border-radius:0;">
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col">Reference</th>
            <th scope="col">Pickup</th>
            <th scope="col">Customer</th>
            <th scope="col">Vehicle</th>
            <th scope="col">Status</th>
            <th scope="col"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($upcoming as $b): ?>
          <tr>
            <td class="ref" data-label="Ref"><?= e($b['reference']) ?></td>
            <td data-label="Pickup">
              <?= e(fmt_datetime($b['pickup_at'])) ?><br>
              <span class="muted"><?= e(mb_strimwidth((string)$b['pickup_address'], 0, 46, '…')) ?></span>
            </td>
            <td data-label="Customer">
              <?= e($b['full_name']) ?><br>
              <span class="muted"><?= e($b['phone']) ?></span>
            </td>
            <td class="muted" data-label="Vehicle"><?= e((string)$b['vehicle_name']) ?></td>
            <td data-label="Status"><span class="badge-status badge-<?= e($b['status']) ?>"><?= e($b['status']) ?></span></td>
            <td>
              <div class="actions">
                <a class="btn btn--outline btn--sm" href="booking-view.php?id=<?= (int)$b['id'] ?>">Open</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>


<!-- ══ RECENT + BY VEHICLE ═════════════════════════════════════════ -->
<div class="panel">
  <div class="panel__head">
    <h2 class="panel__title">Recent bookings</h2>
    <a href="bookings.php" class="btn btn--outline btn--sm">
      <span>All bookings</span><?= icon('arrow-right') ?>
    </a>
  </div>

  <?php if (!$recent): ?>
    <div class="empty-state">
      <div class="empty-state__icon"><?= icon('inbox') ?></div>
      <h3>No bookings yet</h3>
      <p>Once customers start booking through the website, you&rsquo;ll see them here.</p>
      <a href="../booking.php" target="_blank" rel="noopener" class="btn btn--gold btn--sm">
        <?= icon('external') ?><span>Open the booking form</span>
      </a>
    </div>
  <?php else: ?>
  <div class="panel__body panel__body--flush">
    <div class="table-scroll" style="border:0;border-radius:0;">
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col">Reference</th>
            <th scope="col">Received</th>
            <th scope="col">Service</th>
            <th scope="col">Customer</th>
            <th scope="col" class="num">Total</th>
            <th scope="col">Status</th>
            <th scope="col"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $b): ?>
          <tr>
            <td class="ref" data-label="Ref"><?= e($b['reference']) ?></td>
            <td class="muted" data-label="Received"><?= e(fmt_date($b['created_at'])) ?></td>
            <td data-label="Service"><?= e(service_label($b['service_type'])) ?></td>
            <td data-label="Customer"><?= e($b['full_name']) ?></td>
            <td class="num" data-label="Total"><?= money((float)$b['total']) ?></td>
            <td data-label="Status"><span class="badge-status badge-<?= e($b['status']) ?>"><?= e($b['status']) ?></span></td>
            <td>
              <div class="actions">
                <a class="btn btn--outline btn--sm" href="booking-view.php?id=<?= (int)$b['id'] ?>">Open</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if ($by_vehicle): ?>
<div class="panel">
  <div class="panel__head"><h2 class="panel__title">Bookings by vehicle</h2></div>
  <div class="panel__body">
    <?php
    $max = max(array_map(fn($r) => (int)$r['n'], $by_vehicle)) ?: 1;
    foreach ($by_vehicle as $r): ?>
    <div style="margin-bottom:var(--s-5);">
      <div style="display:flex;justify-content:space-between;gap:var(--s-4);margin-bottom:var(--s-2);">
        <span style="font-size:var(--fs-sm);"><?= e((string)$r['vehicle_name']) ?></span>
        <span style="font-size:var(--fs-sm);" class="text-muted tabular">
          <?= (int)$r['n'] ?> &middot; <?= money_short((float)$r['t']) ?>
        </span>
      </div>
      <div style="height:6px;background:var(--surface-2);border-radius:var(--r-pill);overflow:hidden;">
        <div style="height:100%;width:<?= (int)round((int)$r['n'] / $max * 100) ?>%;
                    background:var(--gold-grad);border-radius:var(--r-pill);"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
