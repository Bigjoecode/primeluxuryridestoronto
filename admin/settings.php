<?php
/** Site settings: contact info, social, pricing globals, page text, password. */
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();

$admin_page  = 'settings.php';
$admin_title = 'Settings';
$admin_sub   = 'Contact details, pricing rules and website text — all editable without code.';

$pw_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('settings.php');

    $action = (string)($_POST['action'] ?? '');

    // ── Save settings ───────────────────────────────────────────────
    if ($action === 'save') {
        $values = (array)($_POST['s'] ?? []);
        $known  = array_keys(settings());
        $saved  = 0;

        foreach ($values as $key => $val) {
            $key = (string)$key;
            if (!in_array($key, $known, true)) {
                continue;   // never create arbitrary keys from user input
            }
            db_exec('UPDATE `settings` SET `value` = ? WHERE `key_name` = ?',
                    [trim((string)$val), $key]);
            $saved++;
        }

        app_log('admin.log', $admin['email'] . " updated $saved settings");
        flash('success', 'Settings saved.');
        header('Location: settings.php');
        exit;
    }

    // ── Change password ─────────────────────────────────────────────
    if ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $row = db_one('SELECT * FROM `admin_users` WHERE `id` = ? LIMIT 1', [$admin['id']]);

        if (!$row || !password_verify($current, (string)$row['password_hash'])) {
            $pw_errors[] = 'Your current password is not correct.';
        }
        if (strlen($new) < 10) {
            $pw_errors[] = 'Your new password must be at least 10 characters.';
        }
        if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            $pw_errors[] = 'Your new password must contain both letters and numbers.';
        }
        if ($new !== $confirm) {
            $pw_errors[] = 'The two new passwords do not match.';
        }

        if (!$pw_errors) {
            db_exec('UPDATE `admin_users` SET `password_hash` = ? WHERE `id` = ?',
                    [password_hash($new, PASSWORD_BCRYPT), $admin['id']]);
            app_log('admin.log', $admin['email'] . ' changed their password');
            flash('success', 'Your password has been changed.');
            header('Location: settings.php');
            exit;
        }
    }
}

// Group settings for display
$all = db_all('SELECT * FROM `settings` ORDER BY `group_name`, `sort_order`, `key_name`');
$groups = [];
foreach ($all as $s) {
    $groups[$s['group_name']][] = $s;
}

$group_meta = [
    'contact' => ['Contact details', 'phone',    'Shown in the header, footer, contact page and every email.'],
    'social'  => ['Social media',    'instagram','Leave blank to hide an icon. Use full URLs.'],
    'pricing' => ['Pricing rules',   'tag',      'These drive the live quote engine. Change with care.'],
    'content' => ['Website text',    'edit',     'Headline copy shown on the home and about pages.'],
    'seo'     => ['SEO',             'search',   'Default meta description used across the site.'],
];

require __DIR__ . '/includes/header.php';
?>

<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">

  <?php foreach ($group_meta as $gkey => [$gtitle, $gicon, $gdesc]):
    if (empty($groups[$gkey])) continue; ?>
  <div class="panel">
    <div class="panel__head">
      <span class="stat-card__icon"><?= icon($gicon) ?></span>
      <div style="min-width:0;">
        <h2 class="panel__title"><?= e($gtitle) ?></h2>
        <p class="muted" style="font-size:var(--fs-sm);"><?= e($gdesc) ?></p>
      </div>
    </div>
    <div class="panel__body">
      <?php if ($gkey === 'pricing'): ?>
      <div class="alert alert--gold">
        <?= icon('alert') ?>
        <span>These values are applied to every new quote immediately. Existing bookings keep
          the price they were quoted at.</span>
      </div>
      <?php endif; ?>

      <div class="field-row <?= $gkey === 'pricing' ? 'field-row--3' : 'field-row--2' ?>">
        <?php foreach ($groups[$gkey] as $s):
          $key   = $s['key_name'];
          $label = $s['label'] ?: ucwords(str_replace('_', ' ', $key));
          $val   = (string)$s['value'];
          $wide  = ($s['input_type'] === 'textarea'); ?>

        <div class="field" <?= $wide ? 'style="grid-column:1/-1;"' : '' ?>>
          <label class="field__label" for="s_<?= e($key) ?>"><?= e($label) ?></label>

          <?php if ($s['input_type'] === 'textarea'): ?>
            <textarea class="textarea" id="s_<?= e($key) ?>" name="s[<?= e($key) ?>]"><?= e($val) ?></textarea>
          <?php elseif ($s['input_type'] === 'number'): ?>
            <input class="input" type="number" id="s_<?= e($key) ?>" name="s[<?= e($key) ?>]"
                   value="<?= e($val) ?>" min="0" step="0.01" inputmode="decimal">
          <?php else: ?>
            <input class="input" type="text" id="s_<?= e($key) ?>" name="s[<?= e($key) ?>]"
                   value="<?= e($val) ?>">
          <?php endif; ?>

          <?php if ($key === 'whatsapp'): ?>
            <span class="field__hint">Digits only including country code, e.g. 14160000000.</span>
          <?php elseif ($key === 'hst_rate'): ?>
            <span class="field__hint">Ontario HST is 13.</span>
          <?php elseif ($key === 'flat_rate_threshold_km'): ?>
            <span class="field__hint">Trips this far or further use the flat-rate table.</span>
          <?php elseif ($key === 'deposit_percent'): ?>
            <span class="field__hint">100 = charge the full amount at booking.</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="panel">
    <div class="panel__body">
      <button type="submit" class="btn btn--gold btn--lg">
        <?= icon('check') ?><span>Save all settings</span>
      </button>
    </div>
  </div>
</form>


<!-- ══ INTEGRATIONS ════════════════════════════════════════════════ -->
<div class="panel">
  <div class="panel__head">
    <span class="stat-card__icon"><?= icon('settings') ?></span>
    <div>
      <h2 class="panel__title">Integrations</h2>
      <p class="muted" style="font-size:var(--fs-sm);">
        Configured in <code>includes/config.php</code> on the server.</p>
    </div>
  </div>
  <div class="panel__body">
    <div class="dl-grid">
      <div class="dl-item">
        <dt>Google Maps</dt>
        <dd>
          <?php if (maps_enabled()): ?>
            <span class="badge-status badge-completed">Connected</span>
          <?php else: ?>
            <span class="badge-status badge-unpaid">Not configured</span>
            <p class="muted mt-4" style="font-size:var(--fs-sm);">
              Address autocomplete and automatic distance are off. Customers type addresses
              manually and you confirm the distance. Add <code>GOOGLE_MAPS_API_KEY</code> to enable.</p>
          <?php endif; ?>
        </dd>
      </div>

      <div class="dl-item">
        <dt>Stripe payments</dt>
        <dd>
          <?php if (stripe_enabled()): ?>
            <span class="badge-status badge-completed">Connected</span>
          <?php else: ?>
            <span class="badge-status badge-unpaid">Not configured</span>
            <p class="muted mt-4" style="font-size:var(--fs-sm);">
              Bookings complete as &ldquo;pay on confirmation&rdquo;. Add your Stripe keys to
              take card, Apple&nbsp;Pay and Google&nbsp;Pay payments online.</p>
          <?php endif; ?>
        </dd>
      </div>

      <div class="dl-item">
        <dt>Email delivery</dt>
        <dd>
          <?php if (SMTP_ENABLED): ?>
            <span class="badge-status badge-completed">SMTP configured</span>
          <?php else: ?>
            <span class="badge-status badge-pending">Using PHP mail()</span>
            <p class="muted mt-4" style="font-size:var(--fs-sm);">
              For reliable delivery set <code>SMTP_ENABLED</code> to true and add your mail
              credentials. Every message is also written to <code>logs/mail.log</code>.</p>
          <?php endif; ?>
        </dd>
      </div>

      <div class="dl-item">
        <dt>Notifications go to</dt>
        <dd><?= e(ADMIN_EMAIL) ?></dd>
      </div>
    </div>
  </div>
</div>


<!-- ══ PASSWORD ════════════════════════════════════════════════════ -->
<div class="panel">
  <div class="panel__head">
    <span class="stat-card__icon"><?= icon('lock') ?></span>
    <div>
      <h2 class="panel__title">Change your password</h2>
      <p class="muted" style="font-size:var(--fs-sm);">Signed in as <?= e($admin['email']) ?></p>
    </div>
  </div>
  <div class="panel__body">

    <?php if ($pw_errors): ?>
    <div class="alert alert--error" role="alert">
      <?= icon('alert') ?>
      <div>
        <strong>Password not changed:</strong>
        <ul style="margin-top:var(--s-2);display:grid;gap:var(--s-1);">
          <?php foreach ($pw_errors as $err): ?><li>&bull; <?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">

      <div class="field">
        <label class="field__label" for="current_password">Current password</label>
        <div class="pw-wrap">
          <input class="input" type="password" id="current_password" name="current_password"
                 autocomplete="current-password" required>
          <button type="button" class="pw-toggle" data-pw-toggle="current_password" aria-label="Show password">
            <span data-eye-open><?= icon('eye') ?></span>
            <span data-eye-close hidden><?= icon('close') ?></span>
          </button>
        </div>
      </div>

      <div class="field-row field-row--2">
        <div class="field">
          <label class="field__label" for="new_password">New password</label>
          <div class="pw-wrap">
            <input class="input" type="password" id="new_password" name="new_password"
                   autocomplete="new-password" required minlength="10">
            <button type="button" class="pw-toggle" data-pw-toggle="new_password" aria-label="Show password">
              <span data-eye-open><?= icon('eye') ?></span>
              <span data-eye-close hidden><?= icon('close') ?></span>
            </button>
          </div>
          <span class="field__hint">At least 10 characters, with letters and numbers.</span>
        </div>

        <div class="field">
          <label class="field__label" for="confirm_password">Confirm new password</label>
          <input class="input" type="password" id="confirm_password" name="confirm_password"
                 autocomplete="new-password" required minlength="10">
        </div>
      </div>

      <button type="submit" class="btn btn--gold">
        <?= icon('lock') ?><span>Change password</span>
      </button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
