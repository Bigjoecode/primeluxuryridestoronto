<?php
/** Customer accounts — set membership tiers, review spend. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/customer.php';
$admin = require_admin();

$admin_page  = 'customers.php';
$admin_title = 'Customers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('customers.php');

    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);
    $row    = $id > 0 ? db_one('SELECT * FROM `customers` WHERE `id` = ? LIMIT 1', [$id]) : null;

    if ($row) {
        if ($action === 'tier') {
            $tier = (string)($_POST['membership_tier'] ?? 'none');
            if (!in_array($tier, ['none', 'elite', 'vip'], true)) {
                $tier = 'none';
            }
            $note = trim((string)($_POST['membership_note'] ?? ''));

            db_exec('UPDATE `customers` SET `membership_tier` = ?, `membership_note` = ? WHERE `id` = ?',
                    [$tier, $note !== '' ? $note : null, $id]);

            app_log('admin.log', sprintf('%s set %s to %s',
                    $admin['email'], $row['email'], $tier));

            flash('success', $row['full_name'] . ' is now ' . membership_label($tier) . '.');

        } elseif ($action === 'toggle') {
            db_exec('UPDATE `customers` SET `is_active` = 1 - `is_active` WHERE `id` = ?', [$id]);
            flash('success', 'Account status updated.');
        }
    }
    header('Location: customers.php' . ($id ? '?open=' . $id : ''));
    exit;
}

$q     = trim((string)($_GET['q'] ?? ''));
$tierf = (string)($_GET['tier'] ?? '');

$where  = [];
$params = [];
if ($q !== '') {
    $where[]  = '(c.`full_name` LIKE ? OR c.`email` LIKE ? OR c.`phone` LIKE ?)';
    $like     = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if (in_array($tierf, ['none', 'elite', 'vip'], true)) {
    $where[]  = 'c.`membership_tier` = ?';
    $params[] = $tierf;
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$customers = db_all(
    "SELECT c.*,
            (SELECT COUNT(*) FROM `bookings` b
              WHERE b.`customer_id` = c.`id` AND b.`status` != 'cancelled') AS trips,
            (SELECT COALESCE(SUM(b.`total`),0) FROM `bookings` b
              WHERE b.`customer_id` = c.`id` AND b.`status` != 'cancelled') AS spend
       FROM `customers` c
       $where_sql
   ORDER BY c.`created_at` DESC
      LIMIT 200", $params);

$open = (int)($_GET['open'] ?? 0);

$admin_sub = count($customers) . ' account' . (count($customers) === 1 ? '' : 's')
           . ($where ? ' matching your filters' : '');

require __DIR__ . '/includes/header.php';
?>

<div class="alert alert--info">
  <?= icon('info') ?>
  <span>Membership discounts are applied automatically at checkout for signed-in customers
    &mdash; <strong><?= (int)setting_num('elite_discount', 30) ?>%</strong> for Elite and
    <strong><?= (int)setting_num('vip_discount', 40) ?>%</strong> for VIP. Customers cannot
    set their own tier, so a discount can only ever come from this page.</span>
</div>

<div class="panel">
  <form method="get" class="filter-bar">
    <div class="field">
      <label class="field__label" for="f_q">Search</label>
      <input class="input" type="search" id="f_q" name="q" value="<?= e($q) ?>"
             placeholder="Name, email or phone">
    </div>
    <div class="field">
      <label class="field__label" for="f_tier">Membership</label>
      <select class="select" id="f_tier" name="tier" data-autosubmit>
        <option value="">All tiers</option>
        <?php foreach (['none' => 'Standard', 'elite' => 'Elite', 'vip' => 'VIP'] as $v => $l): ?>
        <option value="<?= e($v) ?>" <?= $tierf === $v ? 'selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="display:flex;gap:var(--s-2);">
      <button type="submit" class="btn btn--gold btn--sm" style="flex:1;">
        <?= icon('search') ?><span>Filter</span>
      </button>
      <?php if ($where): ?>
      <a href="customers.php" class="btn btn--ghost btn--sm" aria-label="Clear"><?= icon('close') ?></a>
      <?php endif; ?>
    </div>
  </form>

  <?php if (!$customers): ?>
    <div class="empty-state">
      <div class="empty-state__icon"><?= icon('users') ?></div>
      <h3><?= $where ? 'No accounts match' : 'No customer accounts yet' ?></h3>
      <p>Customers who register on the website appear here, and you can grant
         Elite or VIP membership from this page.</p>
    </div>
  <?php else: ?>
  <div class="panel__body panel__body--flush">
    <div class="table-scroll" style="border:0;border-radius:0;">
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col">Customer</th>
            <th scope="col">Contact</th>
            <th scope="col" class="num">Trips</th>
            <th scope="col" class="num">Spend</th>
            <th scope="col">Membership</th>
            <th scope="col"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $c): ?>
          <tr <?= $open === (int)$c['id'] ? 'style="background:rgba(212,175,55,.07);"' : '' ?>>
            <th scope="row" data-label="Customer" style="font-weight:600;">
              <?= e($c['full_name']) ?>
              <?php if ((int)$c['is_active'] !== 1): ?>
              <br><span class="badge-status badge-cancelled">Deactivated</span>
              <?php endif; ?>
            </th>
            <td data-label="Contact">
              <a class="text-gold" href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a>
              <?php if ($c['phone']): ?><br><span class="muted"><?= e($c['phone']) ?></span><?php endif; ?>
            </td>
            <td class="num" data-label="Trips"><?= (int)$c['trips'] ?></td>
            <td class="num" data-label="Spend"><?= money_short((float)$c['spend']) ?></td>
            <td data-label="Membership">
              <form method="post" style="display:flex;gap:var(--s-2);align-items:center;flex-wrap:wrap;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="tier">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <select class="select" name="membership_tier"
                        style="min-height:44px;min-width:130px;flex:1;">
                  <?php foreach (['none' => 'Standard', 'elite' => 'Elite', 'vip' => 'VIP'] as $v => $l): ?>
                  <option value="<?= e($v) ?>" <?= $c['membership_tier'] === $v ? 'selected' : '' ?>>
                    <?= e($l) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn--gold btn--sm"><?= icon('check') ?></button>
              </form>
            </td>
            <td>
              <div class="actions">
                <a class="btn btn--outline btn--sm"
                   href="bookings.php?q=<?= e(urlencode((string)$c['email'])) ?>">
                  <?= icon('list') ?><span class="sr-only">Trips</span>
                </a>
                <form method="post" style="display:contents;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <button type="submit" class="btn btn--ghost btn--sm"
                          data-confirm="<?= (int)$c['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?> <?= e($c['full_name']) ?>?"
                          aria-label="Toggle account"><?= icon('eye') ?></button>
                </form>
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

<?php require __DIR__ . '/includes/footer.php'; ?>
