<?php
/** Chauffeur roster. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/sms.php';
$admin = require_admin();

$admin_page  = 'drivers.php';
$admin_title = 'Chauffeurs';

$errors = [];
$edit   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('drivers.php');

    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name    = trim((string)($_POST['full_name'] ?? ''));
        $phone   = trim((string)($_POST['phone'] ?? ''));
        $email   = trim((string)($_POST['email'] ?? ''));
        $licence = trim((string)($_POST['licence_no'] ?? ''));
        $notes   = trim((string)($_POST['notes'] ?? ''));
        $active  = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '')                                   { $errors[] = 'Please enter the chauffeur’s name.'; }
        if (preg_replace('/\D+/', '', $phone) === '')       { $errors[] = 'Please enter a mobile number — this is how they receive job details.'; }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'That email address is not valid.';
        }

        if (!$errors) {
            if ($id > 0) {
                db_exec('UPDATE `drivers` SET `full_name` = ?, `phone` = ?, `email` = ?,
                         `licence_no` = ?, `notes` = ?, `is_active` = ? WHERE `id` = ?',
                        [$name, $phone, $email !== '' ? $email : null,
                         $licence !== '' ? $licence : null,
                         $notes !== '' ? $notes : null, $active, $id]);
                flash('success', $name . ' was updated.');
            } else {
                db_exec('INSERT INTO `drivers` (`full_name`,`phone`,`email`,`licence_no`,`notes`,`is_active`)
                         VALUES (?,?,?,?,?,?)',
                        [$name, $phone, $email !== '' ? $email : null,
                         $licence !== '' ? $licence : null,
                         $notes !== '' ? $notes : null, $active]);
                flash('success', $name . ' was added to the roster.');
            }
            header('Location: drivers.php');
            exit;
        }
        $edit = ['id' => $id, 'full_name' => $name, 'phone' => $phone, 'email' => $email,
                 'licence_no' => $licence, 'notes' => $notes, 'is_active' => $active];

    } elseif ($action === 'toggle' && $id > 0) {
        db_exec('UPDATE `drivers` SET `is_active` = 1 - `is_active` WHERE `id` = ?', [$id]);
        flash('success', 'Availability updated.');
        header('Location: drivers.php');
        exit;

    } elseif ($action === 'delete' && $id > 0) {
        // Bookings keep the chauffeur's name in assigned_driver, so history survives.
        db_exec('UPDATE `bookings` SET `driver_id` = NULL WHERE `driver_id` = ?', [$id]);
        db_exec('DELETE FROM `drivers` WHERE `id` = ?', [$id]);
        flash('success', 'Chauffeur removed.');
        header('Location: drivers.php');
        exit;
    }
}

if ($edit === null && isset($_GET['edit'])) {
    $edit = db_one('SELECT * FROM `drivers` WHERE `id` = ? LIMIT 1', [(int)$_GET['edit']]);
}

$drivers = db_all('SELECT d.*,
                     (SELECT COUNT(*) FROM `bookings` b
                       WHERE b.`driver_id` = d.`id`
                         AND b.`status` NOT IN (\'cancelled\')) AS jobs,
                     (SELECT COUNT(*) FROM `bookings` b
                       WHERE b.`driver_id` = d.`id`
                         AND b.`pickup_at` >= NOW()
                         AND b.`status` NOT IN (\'cancelled\',\'completed\')) AS upcoming
                   FROM `drivers` d
                   ORDER BY d.`is_active` DESC, d.`full_name`');

$admin_sub = count($drivers) . ' chauffeur' . (count($drivers) === 1 ? '' : 's') . ' on the roster';

require __DIR__ . '/includes/header.php';
?>

<?php if ($errors): ?>
<div class="alert alert--error" role="alert">
  <?= icon('alert') ?>
  <div>
    <strong>Please check the following:</strong>
    <ul style="margin-top:var(--s-2);display:grid;gap:var(--s-1);">
      <?php foreach ($errors as $err): ?><li>&bull; <?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<?php if (!sms_enabled()): ?>
<div class="alert alert--info">
  <?= icon('info') ?>
  <span>SMS is not configured, so assignment texts are written to
    <code>logs/sms.log</code> instead of being sent. The customer still receives the
    email version. Add your Twilio credentials in <code>includes/config.php</code> to
    switch texting on.</span>
</div>
<?php endif; ?>

<div style="display:grid;gap:var(--s-6);align-items:start;" class="drv-layout">

  <!-- ══ ROSTER ════════════════════════════════════════════════════ -->
  <div>
    <div class="panel">
      <div class="panel__head"><h2 class="panel__title">Roster</h2></div>

      <?php if (!$drivers): ?>
        <div class="empty-state">
          <div class="empty-state__icon"><?= icon('users') ?></div>
          <h3>No chauffeurs yet</h3>
          <p>Add your drivers so you can assign them to bookings and send customers
             their name, car and plate automatically.</p>
        </div>
      <?php else: ?>
      <div class="panel__body panel__body--flush">
        <div class="table-scroll" style="border:0;border-radius:0;">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">Chauffeur</th>
                <th scope="col">Contact</th>
                <th scope="col" class="num">Jobs</th>
                <th scope="col">Status</th>
                <th scope="col"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($drivers as $d): ?>
              <tr>
                <th scope="row" data-label="Chauffeur" style="font-weight:600;">
                  <?= e($d['full_name']) ?>
                  <?php if ($d['licence_no']): ?>
                  <br><span class="muted" style="font-weight:400;">Licence <?= e($d['licence_no']) ?></span>
                  <?php endif; ?>
                </th>
                <td data-label="Contact">
                  <a class="text-gold" href="tel:<?= e(preg_replace('/[^\d+]/', '', (string)$d['phone'])) ?>">
                    <?= e($d['phone']) ?></a>
                  <?php if ($d['email']): ?>
                  <br><span class="muted"><?= e($d['email']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="num" data-label="Jobs">
                  <?= (int)$d['jobs'] ?>
                  <?php if ((int)$d['upcoming'] > 0): ?>
                  <br><span class="muted"><?= (int)$d['upcoming'] ?> upcoming</span>
                  <?php endif; ?>
                </td>
                <td data-label="Status">
                  <span class="badge-status <?= (int)$d['is_active'] === 1 ? 'badge-completed' : 'badge-cancelled' ?>">
                    <?= (int)$d['is_active'] === 1 ? 'Available' : 'Off duty' ?>
                  </span>
                </td>
                <td>
                  <div class="actions">
                    <a class="btn btn--outline btn--sm" href="drivers.php?edit=<?= (int)$d['id'] ?>">
                      <?= icon('edit') ?>
                    </a>
                    <form method="post" style="display:contents;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                      <button type="submit" class="btn btn--ghost btn--sm"
                              aria-label="Toggle availability for <?= e($d['full_name']) ?>">
                        <?= icon('eye') ?>
                      </button>
                    </form>
                    <form method="post" style="display:contents;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                      <button type="submit" class="btn btn--ghost btn--sm" style="color:#fca5a5;"
                              data-confirm="Remove <?= e($d['full_name']) ?> from the roster? Past bookings keep their name on record."
                              aria-label="Remove <?= e($d['full_name']) ?>">
                        <?= icon('trash') ?>
                      </button>
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
  </div>

  <!-- ══ ADD / EDIT ════════════════════════════════════════════════ -->
  <div>
    <div class="panel">
      <div class="panel__head">
        <h2 class="panel__title"><?= $edit ? 'Edit chauffeur' : 'Add a chauffeur' ?></h2>
      </div>
      <div class="panel__body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

          <div class="field">
            <label class="field__label" for="full_name">Full name <span class="req">*</span></label>
            <input class="input" type="text" id="full_name" name="full_name"
                   value="<?= e((string)($edit['full_name'] ?? '')) ?>" required>
          </div>

          <div class="field">
            <label class="field__label" for="phone">Mobile number <span class="req">*</span></label>
            <input class="input" type="tel" id="phone" name="phone" inputmode="tel"
                   value="<?= e((string)($edit['phone'] ?? '')) ?>"
                   placeholder="+1 (416) 000-0000" required>
            <span class="field__hint">Shared with the customer when you assign this chauffeur.</span>
          </div>

          <div class="field">
            <label class="field__label" for="email">Email</label>
            <input class="input" type="email" id="email" name="email" inputmode="email"
                   value="<?= e((string)($edit['email'] ?? '')) ?>">
          </div>

          <div class="field">
            <label class="field__label" for="licence_no">Licence number</label>
            <input class="input" type="text" id="licence_no" name="licence_no"
                   value="<?= e((string)($edit['licence_no'] ?? '')) ?>">
            <span class="field__hint">Internal only — never shown to customers.</span>
          </div>

          <div class="field">
            <label class="field__label" for="notes">Notes</label>
            <textarea class="textarea" id="notes" name="notes"
                      placeholder="Languages spoken, preferred vehicles, availability…"><?= e((string)($edit['notes'] ?? '')) ?></textarea>
          </div>

          <div class="checkbox-row mb-5">
            <input type="checkbox" id="is_active" name="is_active" value="1"
                   <?= (int)($edit['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
            <label for="is_active">Available for assignment</label>
          </div>

          <button type="submit" class="btn btn--gold btn--block">
            <?= icon('check') ?><span><?= $edit ? 'Save changes' : 'Add chauffeur' ?></span>
          </button>
          <?php if ($edit): ?>
          <a href="drivers.php" class="btn btn--ghost btn--block mt-4">Cancel</a>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

</div>

<style>
  @media (min-width: 1100px) { .drv-layout { grid-template-columns: minmax(0, 1fr) 400px; } }
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
