<?php
/** Bookings list with filters, search, pagination. */
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();

$admin_page  = 'bookings.php';
$admin_title = 'Bookings';

// ── Filters ────────────────────────────────────────────────────────
$status  = (string)($_GET['status']  ?? '');
$service = (string)($_GET['service'] ?? '');
$range   = (string)($_GET['range']   ?? '');
$q       = trim((string)($_GET['q']  ?? ''));
$page    = max(1, (int)($_GET['p'] ?? 1));
$per     = 25;

$where  = [];
$params = [];

if (in_array($status, ['pending','confirmed','assigned','completed','cancelled'], true)) {
    $where[] = '`status` = ?';
    $params[] = $status;
}
if (in_array($service, ['airport','city','city_to_city','hourly','rental'], true)) {
    $where[] = '`service_type` = ?';
    $params[] = $service;
}
if ($range === 'upcoming') {
    $where[] = '`pickup_at` >= NOW()';
} elseif ($range === 'past') {
    $where[] = '`pickup_at` < NOW()';
} elseif ($range === 'today') {
    $where[] = 'DATE(`pickup_at`) = CURDATE()';
}
if ($q !== '') {
    $where[] = '(`reference` LIKE ? OR `full_name` LIKE ? OR `email` LIKE ?
                 OR `phone` LIKE ? OR `pickup_address` LIKE ? OR `dropoff_address` LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = (int)(db_one("SELECT COUNT(*) n FROM `bookings` $where_sql", $params)['n'] ?? 0);
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);
$offset = ($page - 1) * $per;

// LIMIT/OFFSET are integers we control — safe to interpolate.
$rows = db_all(
    "SELECT * FROM `bookings` $where_sql ORDER BY `pickup_at` DESC, `id` DESC
     LIMIT $per OFFSET $offset",
    $params
);

$sum = (float)(db_one("SELECT COALESCE(SUM(`total`),0) t FROM `bookings` $where_sql", $params)['t'] ?? 0);

$admin_sub = $total . ' booking' . ($total === 1 ? '' : 's')
           . ($where ? ' matching your filters' : '')
           . ' · ' . money_short($sum) . ' total';

// Preserve filters in the export link
$export_qs = http_build_query(array_filter([
    'status' => $status, 'service' => $service, 'range' => $range, 'q' => $q,
]));

$admin_actions = '<a class="btn btn--gold btn--sm" href="export.php?' . e($export_qs) . '">'
               . icon('download') . '<span>Export CSV</span></a>';

/** Build a query string preserving current filters. */
function qs(array $overrides = []): string
{
    $base = array_filter([
        'status'  => $_GET['status']  ?? '',
        'service' => $_GET['service'] ?? '',
        'range'   => $_GET['range']   ?? '',
        'q'       => $_GET['q']       ?? '',
    ], fn($v) => $v !== '');
    return '?' . http_build_query(array_merge($base, $overrides));
}

require __DIR__ . '/includes/header.php';
?>

<div class="panel">
  <form method="get" class="filter-bar">
    <div class="field">
      <label class="field__label" for="f_q">Search</label>
      <input class="input" type="search" id="f_q" name="q" value="<?= e($q) ?>"
             placeholder="Reference, name, email, address…">
    </div>

    <div class="field">
      <label class="field__label" for="f_status">Status</label>
      <select class="select" id="f_status" name="status" data-autosubmit>
        <option value="">All statuses</option>
        <?php foreach (['pending','confirmed','assigned','completed','cancelled'] as $s): ?>
        <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label class="field__label" for="f_service">Service</label>
      <select class="select" id="f_service" name="service" data-autosubmit>
        <option value="">All services</option>
        <?php foreach (['airport','city','city_to_city','hourly','rental'] as $s): ?>
        <option value="<?= e($s) ?>" <?= $service === $s ? 'selected' : '' ?>><?= e(service_label($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label class="field__label" for="f_range">Date</label>
      <select class="select" id="f_range" name="range" data-autosubmit>
        <option value="">All dates</option>
        <option value="today"    <?= $range === 'today'    ? 'selected' : '' ?>>Pickup today</option>
        <option value="upcoming" <?= $range === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
        <option value="past"     <?= $range === 'past'     ? 'selected' : '' ?>>Past</option>
      </select>
    </div>

    <div class="field" style="display:flex;gap:var(--s-2);">
      <button type="submit" class="btn btn--gold btn--sm" style="flex:1;">
        <?= icon('search') ?><span>Filter</span>
      </button>
      <?php if ($where): ?>
      <a href="bookings.php" class="btn btn--ghost btn--sm" aria-label="Clear filters"><?= icon('close') ?></a>
      <?php endif; ?>
    </div>
  </form>

  <?php if (!$rows): ?>
    <div class="empty-state">
      <div class="empty-state__icon"><?= icon('inbox') ?></div>
      <h3><?= $where ? 'No bookings match those filters' : 'No bookings yet' ?></h3>
      <p><?= $where
            ? 'Try widening your search or clearing the filters.'
            : 'Bookings made through the website will appear here automatically, and you’ll be emailed each time.' ?></p>
      <?php if ($where): ?>
      <a href="bookings.php" class="btn btn--outline btn--sm">Clear filters</a>
      <?php endif; ?>
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
            <th scope="col">Service / Vehicle</th>
            <th scope="col" class="num">Total</th>
            <th scope="col">Status</th>
            <th scope="col">Payment</th>
            <th scope="col"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $b): ?>
          <tr>
            <td class="ref" data-label="Ref"><?= e($b['reference']) ?></td>
            <td data-label="Pickup">
              <?= e(fmt_datetime($b['pickup_at'])) ?><br>
              <span class="muted"><?= e(mb_strimwidth((string)$b['pickup_address'], 0, 40, '…')) ?>
                <?php if ($b['dropoff_address']): ?>
                  &rarr; <?= e(mb_strimwidth((string)$b['dropoff_address'], 0, 32, '…')) ?>
                <?php endif; ?>
              </span>
            </td>
            <td data-label="Customer">
              <?= e($b['full_name']) ?><br>
              <span class="muted"><?= e($b['phone']) ?></span>
            </td>
            <td data-label="Service">
              <?= e(service_label($b['service_type'])) ?><br>
              <span class="muted"><?= e((string)$b['vehicle_name']) ?></span>
            </td>
            <td class="num" data-label="Total"><?= money((float)$b['total']) ?></td>
            <td data-label="Status"><span class="badge-status badge-<?= e($b['status']) ?>"><?= e($b['status']) ?></span></td>
            <td data-label="Payment"><span class="badge-status badge-<?= e($b['payment_status']) ?>">
                  <?= e(str_replace('_', ' ', $b['payment_status'])) ?></span></td>
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

  <?php if ($pages > 1): ?>
  <nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?>
      <a href="<?= e(qs(['p' => $page - 1])) ?>" aria-label="Previous page"><?= icon('arrow-left') ?></a>
    <?php endif; ?>

    <?php
    $from = max(1, $page - 2);
    $to   = min($pages, $page + 2);
    if ($from > 1) echo '<a href="' . e(qs(['p' => 1])) . '">1</a>' . ($from > 2 ? '<span>…</span>' : '');
    for ($i = $from; $i <= $to; $i++):
      if ($i === $page): ?>
        <span aria-current="page"><?= $i ?></span>
      <?php else: ?>
        <a href="<?= e(qs(['p' => $i])) ?>"><?= $i ?></a>
      <?php endif;
    endfor;
    if ($to < $pages) echo ($to < $pages - 1 ? '<span>…</span>' : '')
                         . '<a href="' . e(qs(['p' => $pages])) . '">' . $pages . '</a>';
    ?>

    <?php if ($page < $pages): ?>
      <a href="<?= e(qs(['p' => $page + 1])) ?>" aria-label="Next page"><?= icon('arrow-right') ?></a>
    <?php endif; ?>
  </nav>
  <?php endif; ?>

  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
