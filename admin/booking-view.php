<?php
/** Single booking: view, update status/payment, assign driver, add notes. */
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();

$id = (int)($_GET['id'] ?? 0);
$booking = $id > 0 ? db_one('SELECT * FROM `bookings` WHERE `id` = ? LIMIT 1', [$id]) : null;

if (!$booking) {
    flash('error', 'That booking could not be found.');
    header('Location: bookings.php');
    exit;
}

// ── Updates ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('booking-view.php?id=' . $id);

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'update') {
        $status  = (string)($_POST['status'] ?? $booking['status']);
        $payment = (string)($_POST['payment_status'] ?? $booking['payment_status']);
        $driver  = trim((string)($_POST['assigned_driver'] ?? ''));
        $notes   = trim((string)($_POST['admin_notes'] ?? ''));

        $valid_status  = ['pending','confirmed','assigned','completed','cancelled'];
        $valid_payment = ['unpaid','deposit_paid','paid','refunded'];

        if (!in_array($status, $valid_status, true))   { $status  = $booking['status']; }
        if (!in_array($payment, $valid_payment, true)) { $payment = $booking['payment_status']; }

        db_exec('UPDATE `bookings`
                 SET `status` = ?, `payment_status` = ?, `assigned_driver` = ?, `admin_notes` = ?
                 WHERE `id` = ?',
                [$status, $payment, $driver !== '' ? $driver : null,
                 $notes !== '' ? $notes : null, $id]);

        app_log('admin.log', sprintf('%s updated booking %s → %s / %s',
                $admin['email'], $booking['reference'], $status, $payment));

        flash('success', 'Booking ' . $booking['reference'] . ' updated.');
        header('Location: booking-view.php?id=' . $id);
        exit;
    }

    if ($action === 'delete') {
        db_exec('DELETE FROM `bookings` WHERE `id` = ?', [$id]);
        app_log('admin.log', $admin['email'] . ' deleted booking ' . $booking['reference']);
        flash('success', 'Booking ' . $booking['reference'] . ' was deleted.');
        header('Location: bookings.php');
        exit;
    }
}

$admin_page  = 'bookings.php';
$admin_title = $booking['reference'];
$admin_sub   = service_label($booking['service_type']) . ' · received ' . fmt_datetime($booking['created_at']);

$admin_actions = '<a class="btn btn--outline btn--sm" href="bookings.php">'
               . icon('arrow-left') . '<span>All bookings</span></a>';

$breakdown = [];
if (!empty($booking['price_breakdown'])) {
    $decoded = json_decode((string)$booking['price_breakdown'], true);
    if (is_array($decoded)) $breakdown = $decoded;
}

require __DIR__ . '/includes/header.php';
?>

<div style="display:grid;gap:var(--s-6);align-items:start;"
     class="booking-layout">

  <!-- ══ DETAILS ═══════════════════════════════════════════════════ -->
  <div>
    <div class="panel">
      <div class="panel__head">
        <h2 class="panel__title">Customer</h2>
        <span class="badge-status badge-<?= e($booking['status']) ?>"><?= e($booking['status']) ?></span>
      </div>
      <div class="panel__body">
        <div class="dl-grid">
          <div class="dl-item"><dt>Name</dt><dd><?= e($booking['full_name']) ?></dd></div>
          <div class="dl-item"><dt>Phone</dt>
            <dd><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string)$booking['phone'])) ?>"
                   class="text-gold"><?= e($booking['phone']) ?></a></dd></div>
          <div class="dl-item"><dt>Email</dt>
            <dd><a href="mailto:<?= e($booking['email']) ?>" class="text-gold"><?= e($booking['email']) ?></a></dd></div>
          <div class="dl-item"><dt>Membership</dt><dd><?= e(ucfirst($booking['membership_tier'])) ?></dd></div>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel__head"><h2 class="panel__title">Journey</h2></div>
      <div class="panel__body">
        <div class="dl-grid">
          <div class="dl-item"><dt>Service</dt><dd><?= e(service_label($booking['service_type'])) ?></dd></div>
          <div class="dl-item"><dt>Vehicle</dt><dd><?= e((string)$booking['vehicle_name']) ?></dd></div>
          <div class="dl-item"><dt>Pickup address</dt><dd><?= e($booking['pickup_address']) ?></dd></div>
          <div class="dl-item"><dt>Drop-off address</dt>
            <dd><?= $booking['dropoff_address'] ? e($booking['dropoff_address']) : '—' ?></dd></div>
          <div class="dl-item"><dt>Pickup date &amp; time</dt><dd><?= e(fmt_datetime($booking['pickup_at'])) ?></dd></div>
          <?php if ($booking['return_at']): ?>
          <div class="dl-item"><dt>Return</dt><dd><?= e(fmt_datetime($booking['return_at'])) ?></dd></div>
          <?php endif; ?>
          <?php if ($booking['hours']): ?>
          <div class="dl-item"><dt>Duration</dt><dd><?= (int)$booking['hours'] ?> hours</dd></div>
          <?php endif; ?>
          <?php if ($booking['distance_km']): ?>
          <div class="dl-item"><dt>Distance</dt><dd><?= number_format((float)$booking['distance_km'], 1) ?> km</dd></div>
          <?php endif; ?>
          <?php if ($booking['flight_number']): ?>
          <div class="dl-item"><dt>Flight number</dt><dd><?= e($booking['flight_number']) ?></dd></div>
          <?php endif; ?>
          <div class="dl-item"><dt>Passengers</dt><dd><?= (int)$booking['passengers'] ?></dd></div>
          <div class="dl-item"><dt>Luggage</dt><dd><?= (int)$booking['luggage'] ?> bags</dd></div>
        </div>

        <?php if (!empty($booking['notes'])): ?>
        <div class="fieldset-group mt-5">
          <legend>Customer requests</legend>
          <p class="text-muted" style="font-size:var(--fs-sm);"><?= e_nl($booking['notes']) ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel__head">
        <h2 class="panel__title">Price</h2>
        <span class="badge-status badge-<?= e($booking['payment_status']) ?>">
          <?= e(str_replace('_', ' ', $booking['payment_status'])) ?></span>
      </div>
      <div class="panel__body">
        <?php if (!empty($breakdown['lines'])): ?>
          <?php foreach ($breakdown['lines'] as $line): ?>
          <div class="summary__line">
            <span><?= e((string)($line['label'] ?? '')) ?></span>
            <span><?= money((float)($line['amount'] ?? 0)) ?></span>
          </div>
          <?php endforeach; ?>
          <div class="summary__divider"></div>
        <?php endif; ?>

        <div class="summary__line"><span>Subtotal</span><span><?= money((float)$booking['subtotal']) ?></span></div>
        <?php if ((float)$booking['discount'] > 0): ?>
        <div class="summary__line summary__line--discount">
          <span><?= e(ucfirst($booking['membership_tier'])) ?> discount</span>
          <span>&minus; <?= money((float)$booking['discount']) ?></span>
        </div>
        <?php endif; ?>
        <div class="summary__line">
          <span>HST (<?= e(rtrim(rtrim(number_format((float)($breakdown['hst_rate'] ?? setting_num('hst_rate', DEFAULT_HST_RATE)), 2), '0'), '.')) ?>%)</span>
          <span><?= money((float)$booking['hst']) ?></span>
        </div>
        <dl class="summary__total">
          <dt>Total (CAD)</dt><dd><?= money((float)$booking['total']) ?></dd>
        </dl>
        <p class="summary__note">Priced using the
          <strong><?= e($booking['pricing_method']) ?></strong> method.</p>
      </div>
    </div>
  </div>

  <!-- ══ MANAGE ════════════════════════════════════════════════════ -->
  <div>
    <div class="panel">
      <div class="panel__head"><h2 class="panel__title">Manage booking</h2></div>
      <div class="panel__body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update">

          <div class="field">
            <label class="field__label" for="status">Booking status</label>
            <select class="select" id="status" name="status">
              <?php foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed',
                              'assigned' => 'Chauffeur assigned', 'completed' => 'Completed',
                              'cancelled' => 'Cancelled'] as $val => $label): ?>
              <option value="<?= e($val) ?>" <?= $booking['status'] === $val ? 'selected' : '' ?>>
                <?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label class="field__label" for="payment_status">Payment</label>
            <select class="select" id="payment_status" name="payment_status">
              <?php foreach (['unpaid' => 'Unpaid', 'deposit_paid' => 'Deposit paid',
                              'paid' => 'Paid in full', 'refunded' => 'Refunded'] as $val => $label): ?>
              <option value="<?= e($val) ?>" <?= $booking['payment_status'] === $val ? 'selected' : '' ?>>
                <?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label class="field__label" for="assigned_driver">Assigned chauffeur</label>
            <input class="input" type="text" id="assigned_driver" name="assigned_driver"
                   value="<?= e((string)$booking['assigned_driver']) ?>"
                   placeholder="Chauffeur name">
          </div>

          <div class="field">
            <label class="field__label" for="admin_notes">Internal notes</label>
            <textarea class="textarea" id="admin_notes" name="admin_notes"
                      placeholder="Notes visible only to you and your team…"><?= e((string)$booking['admin_notes']) ?></textarea>
            <span class="field__hint">Never shown to the customer.</span>
          </div>

          <button type="submit" class="btn btn--gold btn--block">
            <?= icon('check') ?><span>Save changes</span>
          </button>
        </form>
      </div>
    </div>

    <div class="panel">
      <div class="panel__head"><h2 class="panel__title">Quick actions</h2></div>
      <div class="panel__body" style="display:grid;gap:var(--s-3);">
        <a class="btn btn--outline btn--block"
           href="mailto:<?= e($booking['email']) ?>?subject=<?= rawurlencode('Your booking ' . $booking['reference'] . ' — ' . SITE_NAME) ?>">
          <?= icon('mail') ?><span>Email customer</span>
        </a>
        <a class="btn btn--outline btn--block"
           href="tel:<?= e(preg_replace('/[^\d+]/', '', (string)$booking['phone'])) ?>">
          <?= icon('phone') ?><span>Call customer</span>
        </a>
        <a class="btn btn--outline btn--block" target="_blank" rel="noopener"
           href="https://wa.me/<?= e(preg_replace('/\D+/', '', (string)$booking['phone'])) ?>">
          <?= icon('whatsapp') ?><span>WhatsApp customer</span>
        </a>
        <a class="btn btn--outline btn--block" target="_blank" rel="noopener"
           href="../confirmation.php?ref=<?= e(urlencode($booking['reference'])) ?>">
          <?= icon('eye') ?><span>View customer receipt</span>
        </a>
      </div>
    </div>

    <div class="panel" style="border-color:rgba(248,113,113,.3);">
      <div class="panel__head"><h2 class="panel__title" style="color:var(--danger);">Danger zone</h2></div>
      <div class="panel__body">
        <p class="text-muted mb-5" style="font-size:var(--fs-sm);">
          Deleting removes this booking permanently. To keep a record instead,
          set the status to <strong>Cancelled</strong>.
        </p>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <button type="submit" class="btn btn--outline btn--block"
                  style="border-color:rgba(248,113,113,.45);color:#fca5a5;"
                  data-confirm="Permanently delete booking <?= e($booking['reference']) ?>? This cannot be undone.">
            <?= icon('trash') ?><span>Delete booking</span>
          </button>
        </form>
      </div>
    </div>
  </div>

</div>

<style>
  @media (min-width: 1100px) {
    .booking-layout { grid-template-columns: minmax(0, 1fr) 380px; }
  }
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
