<?php
/** Customer dashboard: trips, one-tap rebook, saved addresses. */
require_once __DIR__ . '/includes/customer.php';

$cust = require_customer();

$page_slug        = 'account';
$page_title       = 'My Account';
$page_description = 'Your Prime Luxury Rides trips, saved addresses and account details.';

$notice = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try that again.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'add_address') {
            $error = customer_add_address(
                (int)$cust['id'],
                (string)($_POST['label'] ?? ''),
                (string)($_POST['address'] ?? '')
            );
            if ($error === null) { $notice = 'Address saved.'; }

        } elseif ($action === 'delete_address') {
            customer_delete_address((int)$cust['id'], (int)($_POST['address_id'] ?? 0));
            $notice = 'Address removed.';

        } elseif ($action === 'update_profile') {
            $name  = trim((string)($_POST['full_name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            if ($name === '') {
                $error = 'Please enter your name.';
            } else {
                db_exec('UPDATE `customers` SET `full_name` = ?, `phone` = ? WHERE `id` = ?',
                        [$name, $phone !== '' ? $phone : null, (int)$cust['id']]);
                $notice = 'Your details were updated.';
                $cust   = db_one('SELECT * FROM `customers` WHERE `id` = ?', [(int)$cust['id']]);
            }

        } elseif ($action === 'change_password') {
            $current = (string)($_POST['current_password'] ?? '');
            $new     = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');

            if (!password_verify($current, (string)$cust['password_hash'])) {
                $error = 'Your current password is not correct.';
            } elseif (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
                $error = 'Your new password must be at least 8 characters, with letters and numbers.';
            } elseif ($new !== $confirm) {
                $error = 'The two new passwords do not match.';
            } else {
                db_exec('UPDATE `customers` SET `password_hash` = ? WHERE `id` = ?',
                        [password_hash($new, PASSWORD_BCRYPT), (int)$cust['id']]);
                $notice = 'Your password has been changed.';
            }
        }
    }
}

$upcoming  = customer_bookings((int)$cust['id'], 'upcoming');
$past      = customer_bookings((int)$cust['id'], 'past');
$addresses = customer_addresses((int)$cust['id']);
$tier      = (string)$cust['membership_tier'];
$discount  = membership_discount($tier);
$welcome   = isset($_GET['welcome']);

/** Render one trip card. */
function trip_card(array $b, bool $upcoming): void
{
    ?>
    <article class="card" style="padding:var(--s-5);">
      <div style="display:flex;align-items:flex-start;gap:var(--s-4);flex-wrap:wrap;margin-bottom:var(--s-4);">
        <div style="min-width:0;flex:1;">
          <p style="font-family:var(--font-display);font-size:var(--fs-lg);color:var(--gold);">
            <?= e($b['reference']) ?></p>
          <p class="text-muted" style="font-size:var(--fs-sm);">
            <?= e(service_label($b['service_type'])) ?>
            <?= (int)$b['is_return'] === 1 ? ' &middot; Return trip' : '' ?>
            &middot; <?= e((string)$b['vehicle_name']) ?>
          </p>
        </div>
        <span class="badge-status badge-<?= e($b['status']) ?>"><?= e($b['status']) ?></span>
      </div>

      <dl style="display:grid;gap:var(--s-2);margin-bottom:var(--s-5);">
        <div class="summary__row"><dt>When</dt><dd><?= e(fmt_datetime($b['pickup_at'])) ?></dd></div>
        <div class="summary__row"><dt>From</dt><dd><?= e($b['pickup_address']) ?></dd></div>
        <?php if ($b['dropoff_address']): ?>
        <div class="summary__row"><dt>To</dt><dd><?= e($b['dropoff_address']) ?></dd></div>
        <?php endif; ?>
        <div class="summary__row"><dt>Total</dt>
          <dd class="text-gold tabular" style="font-weight:600;"><?= money((float)$b['total']) ?></dd></div>
      </dl>

      <div style="display:flex;gap:var(--s-3);flex-wrap:wrap;">
        <?php if ($upcoming && !empty($b['track_token'])): ?>
        <a class="btn btn--gold btn--sm" style="flex:1;min-width:150px;"
           href="track.php?t=<?= e(urlencode((string)$b['track_token'])) ?>">
          <?= icon('navigation') ?><span>Track ride</span>
        </a>
        <?php endif; ?>

        <a class="btn btn--outline btn--sm" style="flex:1;min-width:140px;"
           href="confirmation.php?ref=<?= e(urlencode((string)$b['reference'])) ?>">
          <?= icon('eye') ?><span>View receipt</span>
        </a>

        <?php if (!$upcoming): ?>
        <a class="btn btn--gold btn--sm" style="flex:1;min-width:150px;" href="<?= e(rebook_url($b)) ?>">
          <?= icon('route') ?><span>Book this again</span>
        </a>
        <?php else: ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(tel_url()) ?>">
          <?= icon('phone') ?><span>Change</span>
        </a>
        <?php endif; ?>
      </div>
    </article>
    <?php
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-head" style="padding-bottom:var(--s-7);">
  <div class="container">
    <span class="eyebrow">My Account</span>
    <h1 class="page-head__title">
      Hello, <span class="gold-text"><?= e(explode(' ', trim((string)$cust['full_name']))[0]) ?></span>
    </h1>
    <p class="page-head__lead"><?= e($cust['email']) ?></p>

    <div style="display:flex;gap:var(--s-3);flex-wrap:wrap;margin-top:var(--s-5);">
      <a href="booking.php" class="btn btn--gold"><?= icon('calendar') ?><span>Book a Ride</span></a>
      <a href="signout.php" class="btn btn--outline"><?= icon('logout') ?><span>Sign out</span></a>
    </div>
  </div>
</section>

<section class="section" style="padding-top:var(--s-6);">
  <div class="container">

    <?php if ($welcome): ?>
    <div class="alert alert--success">
      <?= icon('check-circle') ?>
      <span><strong>Your account is ready.</strong> Save your regular addresses below and
        your next booking will take seconds.</span>
    </div>
    <?php endif; ?>

    <?php if ($notice): ?>
    <div class="alert alert--success" role="status"><?= icon('check-circle') ?><span><?= e($notice) ?></span></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert--error" role="alert"><?= icon('alert') ?><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <!-- ══ MEMBERSHIP ═══════════════════════════════════════════════ -->
    <div class="cta-banner mb-7" style="text-align:left;padding:clamp(1.5rem,1rem+2.5vw,2.5rem);">
      <div style="display:flex;align-items:center;gap:var(--s-5);flex-wrap:wrap;">
        <span style="width:56px;height:56px;border-radius:50%;display:grid;place-items:center;
                     background:var(--gold-soft);border:1px solid var(--gold-line);color:var(--gold);flex:none;">
          <?= icon($tier === 'none' ? 'users' : 'crown', '', 26) ?>
        </span>
        <div style="min-width:0;flex:1;">
          <p style="font-size:var(--fs-xs);letter-spacing:.14em;text-transform:uppercase;color:var(--text-dim);">
            Membership</p>
          <p style="font-family:var(--font-display);font-size:var(--fs-xl);" class="gold-text">
            <?= e(membership_label($tier)) ?></p>
          <p class="text-muted" style="font-size:var(--fs-sm);margin-top:var(--s-2);">
            <?php if ($discount > 0): ?>
              <?= (int)$discount ?>% is taken off every fare automatically &mdash; you don&rsquo;t
              need to do anything at checkout.
            <?php else: ?>
              Ride with us regularly? Ask about Elite and VIP membership for
              <?= (int)setting_num('elite_discount', 30) ?>&ndash;<?= (int)setting_num('vip_discount', 40) ?>%
              off every fare.
            <?php endif; ?>
          </p>
        </div>
        <?php if ($discount <= 0): ?>
        <a href="contact.php" class="btn btn--outline btn--sm">Enquire</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- ══ UPCOMING ═════════════════════════════════════════════════ -->
    <h2 class="section-title" style="font-size:var(--fs-xl);">Upcoming trips</h2>
    <div class="divider-gold"></div>

    <?php if (!$upcoming): ?>
      <div class="card center mb-7" style="padding:var(--s-8) var(--s-5);">
        <div style="color:var(--text-dim);margin-bottom:var(--s-4);display:flex;justify-content:center;">
          <?= icon('calendar', '', 40) ?>
        </div>
        <h3 class="card__title" style="font-size:var(--fs-lg);">No upcoming trips</h3>
        <p class="card__text mb-5">When you book, your ride will appear here with live status.</p>
        <a href="booking.php" class="btn btn--gold btn--sm"><?= icon('calendar') ?><span>Book a Ride</span></a>
      </div>
    <?php else: ?>
      <div class="grid grid--2 mb-7">
        <?php foreach ($upcoming as $b) { trip_card($b, true); } ?>
      </div>
    <?php endif; ?>

    <!-- ══ SAVED ADDRESSES ══════════════════════════════════════════ -->
    <h2 class="section-title" style="font-size:var(--fs-xl);">Saved places</h2>
    <div class="divider-gold"></div>

    <div class="grid grid--2 mb-7">
      <div class="card">
        <h3 class="card__title" style="font-size:var(--fs-lg);">Your addresses</h3>
        <?php if (!$addresses): ?>
          <p class="card__text">Nothing saved yet. Add the places you travel from most &mdash;
             home, the office, your usual terminal.</p>
        <?php else: ?>
          <ul style="display:grid;gap:var(--s-3);margin-top:var(--s-4);">
            <?php foreach ($addresses as $a): ?>
            <li style="display:flex;align-items:flex-start;gap:var(--s-3);padding:var(--s-4);
                       background:var(--surface-2);border:1px solid var(--line);border-radius:var(--r-md);">
              <span style="color:var(--gold);flex:none;margin-top:2px;"><?= icon('map-pin', '', 18) ?></span>
              <span style="min-width:0;flex:1;">
                <strong style="display:block;font-size:var(--fs-sm);"><?= e($a['label']) ?></strong>
                <span class="text-muted" style="font-size:var(--fs-sm);"><?= e($a['address']) ?></span>
              </span>
              <a class="btn btn--ghost btn--sm" title="Book from here"
                 href="booking.php?service=airport&amp;from=<?= e(urlencode((string)$a['address'])) ?>">
                <?= icon('arrow-right') ?>
              </a>
              <form method="post" style="display:contents;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_address">
                <input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>">
                <button type="submit" class="btn btn--ghost btn--sm" style="color:#fca5a5;"
                        aria-label="Remove <?= e($a['label']) ?>"><?= icon('trash') ?></button>
              </form>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3 class="card__title" style="font-size:var(--fs-lg);">Add a place</h3>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_address">

          <div class="field">
            <label class="field__label" for="label">Label</label>
            <input class="input" type="text" id="label" name="label"
                   placeholder="Home, Office, Pearson T1" maxlength="60">
          </div>
          <div class="field">
            <label class="field__label" for="address">
              Address <span class="req" aria-hidden="true">*</span>
            </label>
            <input class="input" type="text" id="address" name="address"
                   placeholder="123 Bay St, Toronto, ON" maxlength="255" required>
          </div>
          <button type="submit" class="btn btn--gold btn--block">
            <?= icon('plus') ?><span>Save address</span>
          </button>
        </form>
      </div>
    </div>

    <!-- ══ PAST TRIPS ═══════════════════════════════════════════════ -->
    <h2 class="section-title" style="font-size:var(--fs-xl);">Past trips</h2>
    <div class="divider-gold"></div>

    <?php if (!$past): ?>
      <p class="text-muted mb-7">Your completed journeys will be listed here.</p>
    <?php else: ?>
      <div class="grid grid--2 mb-7">
        <?php foreach (array_slice($past, 0, 8) as $b) { trip_card($b, false); } ?>
      </div>
    <?php endif; ?>

    <!-- ══ ACCOUNT SETTINGS ═════════════════════════════════════════ -->
    <h2 class="section-title" style="font-size:var(--fs-xl);">Account details</h2>
    <div class="divider-gold"></div>

    <div class="grid grid--2">
      <div class="card">
        <h3 class="card__title" style="font-size:var(--fs-lg);">Your details</h3>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_profile">

          <div class="field">
            <label class="field__label" for="acc_name">Full name</label>
            <input class="input" type="text" id="acc_name" name="full_name"
                   value="<?= e((string)$cust['full_name']) ?>" autocomplete="name" required>
          </div>
          <div class="field">
            <label class="field__label" for="acc_phone">Phone</label>
            <input class="input" type="tel" id="acc_phone" name="phone"
                   value="<?= e((string)$cust['phone']) ?>" autocomplete="tel">
          </div>
          <p class="field__hint mb-5">Email address: <?= e($cust['email']) ?>
             &mdash; <a href="contact.php" class="text-gold">contact us</a> to change it.</p>

          <button type="submit" class="btn btn--gold btn--block">
            <?= icon('check') ?><span>Save changes</span>
          </button>
        </form>
      </div>

      <div class="card">
        <h3 class="card__title" style="font-size:var(--fs-lg);">Change password</h3>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">

          <div class="field">
            <label class="field__label" for="current_password">Current password</label>
            <input class="input" type="password" id="current_password" name="current_password"
                   autocomplete="current-password" required>
          </div>
          <div class="field">
            <label class="field__label" for="new_password">New password</label>
            <input class="input" type="password" id="new_password" name="new_password"
                   autocomplete="new-password" minlength="8" required>
            <span class="field__hint">At least 8 characters, with letters and numbers.</span>
          </div>
          <div class="field">
            <label class="field__label" for="confirm_password">Confirm new password</label>
            <input class="input" type="password" id="confirm_password" name="confirm_password"
                   autocomplete="new-password" minlength="8" required>
          </div>

          <button type="submit" class="btn btn--outline btn--block">
            <?= icon('lock') ?><span>Change password</span>
          </button>
        </form>
      </div>
    </div>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
