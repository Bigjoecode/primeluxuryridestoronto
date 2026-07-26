<?php
/** Vehicle list. */
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();

$admin_page  = 'vehicles.php';
$admin_title = 'Vehicles';

// Quick actions: toggle active, reorder, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('vehicles.php');

    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);
    $v      = $id > 0 ? get_vehicle($id) : null;

    if ($v) {
        if ($action === 'toggle') {
            db_exec('UPDATE `vehicles` SET `is_active` = 1 - `is_active` WHERE `id` = ?', [$id]);
            flash('success', $v['name'] . ((int)$v['is_active'] === 1 ? ' hidden from' : ' shown on') . ' the website.');
        } elseif ($action === 'delete') {
            // Bookings keep a text snapshot of the vehicle name, so history survives.
            db_exec('DELETE FROM `vehicles` WHERE `id` = ?', [$id]);
            app_log('admin.log', $admin['email'] . ' deleted vehicle ' . $v['name']);
            flash('success', $v['name'] . ' was deleted.');
        } elseif ($action === 'move') {
            $dir = ((string)($_POST['dir'] ?? '')) === 'up' ? -1 : 1;
            db_exec('UPDATE `vehicles` SET `sort_order` = `sort_order` + ? WHERE `id` = ?',
                    [$dir * 15, $id]);
            // Renumber so ordering stays tidy.
            $i = 1;
            foreach (db_all('SELECT `id` FROM `vehicles` ORDER BY `sort_order`, `id`') as $row) {
                db_exec('UPDATE `vehicles` SET `sort_order` = ? WHERE `id` = ?', [$i++, (int)$row['id']]);
            }
            flash('success', 'Vehicle order updated.');
        }
    }
    header('Location: vehicles.php');
    exit;
}

$vehicles = get_vehicles(false);
$admin_sub = count($vehicles) . ' vehicle' . (count($vehicles) === 1 ? '' : 's') . ' in your fleet';

$admin_actions = '<a class="btn btn--gold btn--sm" href="vehicle-edit.php">'
               . icon('plus') . '<span>Add vehicle</span></a>';

require __DIR__ . '/includes/header.php';
?>

<?php if (!$vehicles): ?>
<div class="panel">
  <div class="empty-state">
    <div class="empty-state__icon"><?= icon('car') ?></div>
    <h3>No vehicles yet</h3>
    <p>Add your first vehicle to show it on the fleet page and make it bookable.</p>
    <a href="vehicle-edit.php" class="btn btn--gold btn--sm"><?= icon('plus') ?><span>Add vehicle</span></a>
  </div>
</div>
<?php else: ?>

<div class="grid grid--3">
  <?php foreach ($vehicles as $i => $v):
    $img = vehicle_image_url($v); ?>
  <div class="panel" style="margin-bottom:0;<?= (int)$v['is_active'] === 0 ? 'opacity:.6;' : '' ?>">
    <div class="img-preview" style="border-radius:0;border:0;border-bottom:1px solid var(--line);margin:0;">
      <?php if ($img): ?>
        <img src="<?= e($img) ?>" alt="<?= e($v['name']) ?>" loading="lazy">
      <?php else: ?>
        <div class="vehicle-placeholder"><?= vehicle_placeholder_svg($v['class_label']) ?></div>
      <?php endif; ?>
    </div>

    <div class="panel__body">
      <div style="display:flex;align-items:flex-start;gap:var(--s-3);margin-bottom:var(--s-4);">
        <div style="min-width:0;flex:1;">
          <h2 class="panel__title" style="font-size:var(--fs-lg);"><?= e($v['name']) ?></h2>
          <p class="muted" style="font-size:var(--fs-sm);color:var(--gold);"><?= e($v['class_label']) ?></p>
        </div>
        <span class="badge-status <?= (int)$v['is_active'] === 1 ? 'badge-completed' : 'badge-cancelled' ?>">
          <?= (int)$v['is_active'] === 1 ? 'Live' : 'Hidden' ?>
        </span>
      </div>

      <div class="spec-row" style="margin-bottom:var(--s-4);">
        <span class="spec"><?= icon('users') ?><?= (int)$v['passengers'] ?></span>
        <span class="spec"><?= icon('luggage') ?><?= (int)$v['luggage'] ?></span>
        <span class="spec"><?= icon('clock') ?><?= money_short((float)$v['hourly_rate']) ?>/hr</span>
      </div>

      <dl style="display:grid;gap:var(--s-2);margin-bottom:var(--s-5);font-size:var(--fs-sm);">
        <div style="display:flex;justify-content:space-between;gap:var(--s-3);">
          <dt class="muted">Base / km / min</dt>
          <dd class="tabular"><?= money_short((float)$v['base_fare']) ?> ·
            <?= money_short((float)$v['rate_per_km']) ?> ·
            <?= money_short((float)$v['rate_per_min']) ?></dd>
        </div>
        <div style="display:flex;justify-content:space-between;gap:var(--s-3);">
          <dt class="muted">Minimum hire</dt>
          <dd><?= (int)$v['min_hours'] ?> hours</dd>
        </div>
        <div style="display:flex;justify-content:space-between;gap:var(--s-3);">
          <dt class="muted">Rental</dt>
          <dd><?= (int)$v['rental_available'] === 1
                ? e(money_short((float)$v['rental_daily'])) . '/day' : 'Not available' ?></dd>
        </div>
      </dl>

      <div style="display:flex;gap:var(--s-2);flex-wrap:wrap;">
        <a class="btn btn--gold btn--sm" style="flex:1;" href="vehicle-edit.php?id=<?= (int)$v['id'] ?>">
          <?= icon('edit') ?><span>Edit</span>
        </a>
        <a class="btn btn--outline btn--sm" href="rates.php?vehicle=<?= (int)$v['id'] ?>"
           aria-label="Edit flat rates for <?= e($v['name']) ?>"><?= icon('tag') ?></a>

        <form method="post" style="display:contents;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
          <button type="submit" class="btn btn--outline btn--sm"
                  aria-label="<?= (int)$v['is_active'] === 1 ? 'Hide' : 'Show' ?> <?= e($v['name']) ?>">
            <?= icon('eye') ?>
          </button>
        </form>

        <?php if ($i > 0): ?>
        <form method="post" style="display:contents;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="move">
          <input type="hidden" name="dir" value="up">
          <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
          <button type="submit" class="btn btn--ghost btn--sm" aria-label="Move <?= e($v['name']) ?> up">
            <?= icon('arrow-left') ?>
          </button>
        </form>
        <?php endif; ?>

        <?php if ($i < count($vehicles) - 1): ?>
        <form method="post" style="display:contents;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="move">
          <input type="hidden" name="dir" value="down">
          <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
          <button type="submit" class="btn btn--ghost btn--sm" aria-label="Move <?= e($v['name']) ?> down">
            <?= icon('arrow-right') ?>
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="alert alert--info mt-6">
  <?= icon('info') ?>
  <span>Hiding a vehicle removes it from the website immediately but keeps all its booking
    history. Deleting is permanent &mdash; past bookings keep the vehicle name on record,
    but its photo and pricing are lost.</span>
</div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
