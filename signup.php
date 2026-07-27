<?php
/** Customer registration. */
require_once __DIR__ . '/includes/customer.php';

if (customer() !== null) {
    header('Location: account.php');
    exit;
}

$page_slug        = 'signup';
$page_title       = 'Create an Account';
$page_description = 'Create a Prime Luxury Rides account to save addresses, rebook trips in one tap and track your bookings.';

$error = null;
$old   = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please submit the form again.';
    } elseif (trim((string)($_POST['website'] ?? '')) !== '') {
        $error = 'Your submission could not be processed.';
    } else {
        $old = [
            'full_name' => trim((string)($_POST['full_name'] ?? '')),
            'email'     => trim((string)($_POST['email'] ?? '')),
            'phone'     => trim((string)($_POST['phone'] ?? '')),
        ];
        $pw      = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($pw !== $confirm) {
            $error = 'The two passwords do not match.';
        } else {
            [$cust, $error] = customer_register($old['full_name'], $old['email'], $old['phone'], $pw);
            if ($cust !== null) {
                header('Location: account.php?welcome=1');
                exit;
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-head" style="padding-bottom:var(--s-7);">
  <div class="container container--narrow center">
    <span class="eyebrow eyebrow--center" style="justify-content:center;">Account</span>
    <h1 class="page-head__title">Create your <span class="gold-text">account</span></h1>
    <p class="page-head__lead" style="margin-inline:auto;">
      Save your regular addresses, rebook a journey in one tap, and keep
      every receipt in one place.
    </p>
  </div>
</section>

<section class="section" style="padding-top:var(--s-6);">
  <div class="container" style="max-width:560px;">
    <div class="wizard__main">

      <?php if ($error): ?>
      <div class="alert alert--error" role="alert">
        <?= icon('alert') ?><span><?= e($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
          <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="field">
          <label class="field__label" for="full_name">
            Full name <span class="req" aria-hidden="true">*</span>
          </label>
          <input class="input" type="text" id="full_name" name="full_name"
                 value="<?= e($old['full_name']) ?>" autocomplete="name" required autofocus>
        </div>

        <div class="field-row field-row--2">
          <div class="field">
            <label class="field__label" for="email">
              Email <span class="req" aria-hidden="true">*</span>
            </label>
            <input class="input" type="email" id="email" name="email"
                   value="<?= e($old['email']) ?>" autocomplete="email"
                   inputmode="email" required>
          </div>
          <div class="field">
            <label class="field__label" for="phone">Phone</label>
            <input class="input" type="tel" id="phone" name="phone"
                   value="<?= e($old['phone']) ?>" autocomplete="tel" inputmode="tel">
          </div>
        </div>

        <div class="field-row field-row--2">
          <div class="field">
            <label class="field__label" for="password">
              Password <span class="req" aria-hidden="true">*</span>
            </label>
            <input class="input" type="password" id="password" name="password"
                   autocomplete="new-password" minlength="8" required>
            <span class="field__hint">At least 8 characters, with letters and numbers.</span>
          </div>
          <div class="field">
            <label class="field__label" for="confirm_password">
              Confirm password <span class="req" aria-hidden="true">*</span>
            </label>
            <input class="input" type="password" id="confirm_password" name="confirm_password"
                   autocomplete="new-password" minlength="8" required>
          </div>
        </div>

        <button type="submit" class="btn btn--gold btn--block btn--lg mt-5">
          <?= icon('check') ?><span>Create Account</span>
        </button>

        <p class="summary__note center mt-5" style="font-size:var(--fs-xs);">
          By creating an account you agree to our
          <a href="terms.php" class="text-gold">Terms &amp; Conditions</a> and
          <a href="privacy.php" class="text-gold">Privacy Policy</a>.
        </p>
      </form>

      <p class="summary__note center mt-6" style="font-size:var(--fs-sm);">
        Already have an account?
        <a href="signin.php" class="text-gold" style="font-weight:600;">Sign in</a>
      </p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
