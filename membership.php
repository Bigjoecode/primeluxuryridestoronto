<?php
/**
 * Membership — public tiers page and application flow.
 *
 * Applying never grants a tier. It records a request the operator reviews
 * in the admin panel; the discount only ever comes from them setting the
 * tier on the customer record.
 */
require_once __DIR__ . '/includes/customer.php';
require_once __DIR__ . '/includes/mailer.php';

if ((int)setting_num('membership_enabled', 1) !== 1) {
    header('Location: index.php');
    exit;
}

$page_slug        = 'membership';
$page_title       = 'Membership';
$page_description = 'Elite and VIP membership at Prime Luxury Rides Toronto — a standing discount on every fare, priority allocation and guaranteed availability across Toronto and the GTA.';

$cust     = customer();
$elite    = (int)setting_num('elite_discount', 30);
$vip      = (int)setting_num('vip_discount', 40);
$elite_fee = setting_num('elite_price', 0);
$vip_fee   = setting_num('vip_price', 0);

$sent   = false;
$error  = null;
$old    = ['full_name' => '', 'email' => '', 'phone' => '', 'message' => '', 'tier' => 'elite'];

if ($cust) {
    $old['full_name'] = (string)$cust['full_name'];
    $old['email']     = (string)$cust['email'];
    $old['phone']     = (string)($cust['phone'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please submit the form again.';
    } elseif (trim((string)($_POST['website'] ?? '')) !== '') {
        $error = 'Your application could not be processed.';
    } else {
        $old = [
            'full_name' => trim((string)($_POST['full_name'] ?? '')),
            'email'     => trim((string)($_POST['email'] ?? '')),
            'phone'     => trim((string)($_POST['phone'] ?? '')),
            'message'   => trim((string)($_POST['message'] ?? '')),
            'tier'      => (string)($_POST['tier'] ?? 'elite'),
        ];
        if (!in_array($old['tier'], ['elite', 'vip'], true)) {
            $old['tier'] = 'elite';
        }

        if ($old['full_name'] === '') {
            $error = 'Please enter your name.';
        } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (preg_replace('/\D+/', '', $old['phone']) === '') {
            $error = 'Please enter a contact phone number.';
        } else {
            $subject = membership_label($old['tier']) . ' application';
            $body    = $old['message'] !== ''
                ? $old['message']
                : 'No additional notes supplied.';

            try {
                db_exec('INSERT INTO `enquiries`
                          (`kind`,`requested_tier`,`customer_id`,`full_name`,`email`,`phone`,
                           `subject`,`message`,`ip_address`)
                         VALUES (?,?,?,?,?,?,?,?,?)',
                        ['membership', $old['tier'], $cust ? (int)$cust['id'] : null,
                         $old['full_name'], $old['email'], $old['phone'],
                         $subject, $body, client_ip()]);
            } catch (Throwable $ex) {
                app_log('errors.log', 'membership application failed: ' . $ex->getMessage());
            }

            try {
                send_enquiry_email([
                    'kind'      => 'membership',
                    'full_name' => $old['full_name'],
                    'email'     => $old['email'],
                    'phone'     => $old['phone'],
                    'subject'   => $subject,
                    'message'   => $body . "\n\nRequested tier: " . membership_label($old['tier'])
                                 . ($cust ? "\nExisting account: yes (#" . (int)$cust['id'] . ')'
                                          : "\nExisting account: no"),
                ]);
                send_enquiry_ack_email([
                    'full_name' => $old['full_name'],
                    'email'     => $old['email'],
                    'message'   => 'Your ' . membership_label($old['tier'])
                                 . " application has been received. We review every application "
                                 . "personally and will be in touch shortly.",
                ]);
            } catch (Throwable $ex) {
                app_log('errors.log', 'membership email failed: ' . $ex->getMessage());
            }

            $sent = true;
        }
    }
}

$tiers = [
    [
        'key'      => 'elite',
        'name'     => 'Elite Member',
        'discount' => $elite,
        'fee'      => $elite_fee,
        'blurb'    => setting('elite_blurb'),
        'perks'    => [
            $elite . '% off every fare, applied automatically',
            'Priority vehicle allocation at peak times',
            'Direct line to dispatch, day or night',
            'Saved addresses and one-tap rebooking',
            'Consolidated monthly statement',
        ],
        'featured' => false,
    ],
    [
        'key'      => 'vip',
        'name'     => 'VIP Member',
        'discount' => $vip,
        'fee'      => $vip_fee,
        'blurb'    => setting('vip_blurb'),
        'perks'    => [
            $vip . '% off every fare, applied automatically',
            'First call on the Mercedes-Maybach GLS 600',
            'Guaranteed availability at any hour',
            'A named account manager',
            'Complimentary waiting time extended to 90 minutes',
            'Unmarked vehicles on request',
        ],
        'featured' => true,
    ],
];

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="index.php">Home</a><?= icon('chevron-right') ?><span>Membership</span>
    </nav>
    <span class="eyebrow">For Regular Clients</span>
    <h1 class="page-head__title">Travel with us often?<br>
      <span class="gold-text">Stop paying full fare.</span></h1>
    <p class="page-head__lead">
      Our membership tiers give a standing discount on every journey &mdash;
      no codes, no minimum spend, nothing to remember. The reduction is applied
      the moment you sign in.
    </p>

    <?php if ($cust && $cust['membership_tier'] !== 'none'): ?>
    <div class="alert alert--gold mt-6" style="max-width:60ch;">
      <?= icon('crown') ?>
      <span>You&rsquo;re already a <strong><?= e(membership_label((string)$cust['membership_tier'])) ?></strong>
        &mdash; <?= (int)membership_discount((string)$cust['membership_tier']) ?>% is taken off
        every fare automatically. <a href="account.php">View your account</a>.</span>
    </div>
    <?php endif; ?>
  </div>
</section>


<!-- ══ TIERS ══════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="grid grid--2 reveal-group" style="max-width:1000px;margin-inline:auto;">
      <?php foreach ($tiers as $t): ?>
      <article class="card" style="<?= $t['featured'] ? 'border-color:var(--gold);' : '' ?>padding:var(--s-7);">
        <?php if ($t['featured']): ?>
        <span class="vehicle-card__badge" style="position:static;display:inline-flex;margin-bottom:var(--s-4);">
          Highest tier
        </span>
        <?php endif; ?>

        <div class="card__icon"><?= icon($t['key'] === 'vip' ? 'crown' : 'award') ?></div>
        <h2 class="card__title" style="font-size:var(--fs-xl);"><?= e($t['name']) ?></h2>

        <p style="font-family:var(--font-display);font-size:var(--fs-3xl);line-height:1;
                  margin:var(--s-4) 0 var(--s-2);" class="gold-text">
          <?= (int)$t['discount'] ?>%
        </p>
        <p class="text-muted mb-5" style="font-size:var(--fs-sm);letter-spacing:.06em;
                  text-transform:uppercase;">off every fare</p>

        <p class="card__text mb-5"><?= e($t['blurb']) ?></p>

        <ul class="feature-list mb-6">
          <?php foreach ($t['perks'] as $perk): ?>
          <li><?= icon('check') ?><span><?= e($perk) ?></span></li>
          <?php endforeach; ?>
        </ul>

        <div style="padding-top:var(--s-5);border-top:1px solid var(--line);">
          <?php if ($t['fee'] > 0): ?>
          <p class="mb-4">
            <span style="font-family:var(--font-display);font-size:var(--fs-xl);" class="text-gold">
              <?= money_short((float)$t['fee']) ?></span>
            <span class="text-muted" style="font-size:var(--fs-sm);"> / year</span>
          </p>
          <?php else: ?>
          <p class="text-muted mb-4" style="font-size:var(--fs-sm);">
            By application &mdash; we&rsquo;ll discuss terms with you directly.
          </p>
          <?php endif; ?>

          <a href="#apply" class="btn <?= $t['featured'] ? 'btn--gold' : 'btn--outline' ?> btn--block"
             data-choose-tier="<?= e($t['key']) ?>">
            <span>Apply for <?= e($t['name']) ?></span><?= icon('arrow-right') ?>
          </a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- ══ HOW IT WORKS ═══════════════════════════════════════════════ -->
<section class="section section--alt">
  <div class="container">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">How Membership Works</span>
      <h2 class="section-title">No codes. <span class="gold-text">No small print.</span></h2>
    </div>

    <div class="steps reveal-group">
      <div class="step">
        <h3 class="step__title">Apply</h3>
        <p class="step__text">Tell us how often you travel and what you need. We review every
           application personally &mdash; usually within a working day.</p>
      </div>
      <div class="step">
        <h3 class="step__title">We activate your tier</h3>
        <p class="step__text">Once approved, your membership is attached to your account.
           Nothing to install, no card to carry.</p>
      </div>
      <div class="step">
        <h3 class="step__title">Sign in and save</h3>
        <p class="step__text">Your discount is applied to the price before you confirm,
           on every booking, automatically.</p>
      </div>
    </div>

    <div class="alert alert--info mt-7" style="max-width:70ch;margin-inline:auto;">
      <?= icon('info') ?>
      <span>Your discount applies only while you are signed in, because it is tied to your
        account rather than to a code that could be shared. If you book as a guest, full fare
        applies &mdash; call us and we&rsquo;ll put it right.</span>
    </div>
  </div>
</section>


<!-- ══ APPLY ══════════════════════════════════════════════════════ -->
<section class="section" id="apply">
  <div class="container" style="max-width:620px;">
    <div class="section-head section-head--center reveal">
      <span class="eyebrow eyebrow--center">Apply</span>
      <h2 class="section-title">Request <span class="gold-text">membership</span></h2>
    </div>

    <div class="wizard__main">
      <?php if ($sent): ?>
      <div class="alert alert--success" role="status">
        <?= icon('check-circle') ?>
        <div>
          <strong>Application received.</strong><br>
          We&rsquo;ve emailed you a copy and will be in touch shortly. Nothing has been
          charged, and your tier is activated only once we&rsquo;ve spoken.
        </div>
      </div>
      <div class="btn-row btn-row--center mt-6">
        <a href="index.php" class="btn btn--outline">Back to Home</a>
        <a href="booking.php" class="btn btn--gold"><?= icon('calendar') ?><span>Book a Ride</span></a>
      </div>

      <?php else: ?>

      <?php if ($error): ?>
      <div class="alert alert--error" role="alert">
        <?= icon('alert') ?><span><?= e($error) ?></span>
      </div>
      <?php endif; ?>

      <?php if (!$cust): ?>
      <div class="alert alert--info">
        <?= icon('info') ?>
        <span>Membership attaches to an account.
          <a href="signup.php">Create one</a> or <a href="signin.php?next=membership.php">sign in</a>
          first and we can activate your tier the moment it&rsquo;s approved.</span>
      </div>
      <?php endif; ?>

      <form method="post" action="membership.php#apply" novalidate>
        <?= csrf_field() ?>
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
          <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="field">
          <span class="field__label">Which tier? <span class="req" aria-hidden="true">*</span></span>
          <div class="option-grid option-grid--2">
            <?php foreach ($tiers as $t): ?>
            <label class="option">
              <input type="radio" name="tier" value="<?= e($t['key']) ?>"
                     data-tier-radio <?= $old['tier'] === $t['key'] ? 'checked' : '' ?>>
              <span class="option__icon"><?= icon($t['key'] === 'vip' ? 'crown' : 'award') ?></span>
              <span>
                <span class="option__title"><?= e($t['name']) ?></span>
                <span class="option__desc"><?= (int)$t['discount'] ?>% off every fare</span>
              </span>
              <span class="option__check"><?= icon('check') ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <label class="field__label" for="m_name">
            Full name <span class="req" aria-hidden="true">*</span>
          </label>
          <input class="input" type="text" id="m_name" name="full_name"
                 value="<?= e($old['full_name']) ?>" autocomplete="name" required>
        </div>

        <div class="field-row field-row--2">
          <div class="field">
            <label class="field__label" for="m_email">
              Email <span class="req" aria-hidden="true">*</span>
            </label>
            <input class="input" type="email" id="m_email" name="email"
                   value="<?= e($old['email']) ?>" autocomplete="email" inputmode="email" required>
          </div>
          <div class="field">
            <label class="field__label" for="m_phone">
              Phone <span class="req" aria-hidden="true">*</span>
            </label>
            <input class="input" type="tel" id="m_phone" name="phone"
                   value="<?= e($old['phone']) ?>" autocomplete="tel" inputmode="tel" required>
          </div>
        </div>

        <div class="field">
          <label class="field__label" for="m_message">How do you travel with us?</label>
          <textarea class="textarea" id="m_message" name="message"
                    placeholder="Roughly how often you travel, typical routes, whether it's personal or corporate…"><?= e($old['message']) ?></textarea>
          <span class="field__hint">It helps us recommend the right tier.</span>
        </div>

        <button type="submit" class="btn btn--gold btn--block btn--lg">
          <?= icon('crown') ?><span>Submit Application</span>
        </button>

        <p class="summary__note center mt-5" style="font-size:var(--fs-xs);">
          No payment is taken now. We&rsquo;ll confirm terms with you before anything is charged.
        </p>
      </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
// Clicking "Apply for X" preselects that tier in the form below.
document.querySelectorAll('[data-choose-tier]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var tier = btn.getAttribute('data-choose-tier');
    var radio = document.querySelector('[data-tier-radio][value="' + tier + '"]');
    if (radio) {
      radio.checked = true;
      radio.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
