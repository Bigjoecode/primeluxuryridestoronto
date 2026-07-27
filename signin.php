<?php
/** Customer sign-in. */
require_once __DIR__ . '/includes/customer.php';

$next = basename((string)($_GET['next'] ?? 'account.php'));
if (!preg_match('~^[a-z0-9._-]+\.php$~i', $next)) {
    $next = 'account.php';
}

if (customer() !== null) {
    header('Location: ' . $next);
    exit;
}

$page_slug        = 'signin';
$page_title       = 'Sign In';
$page_description = 'Sign in to your Prime Luxury Rides account to view trips, rebook in one tap and manage saved addresses.';

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please sign in again.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $error = customer_login($email, (string)($_POST['password'] ?? ''));
        if ($error === null) {
            header('Location: ' . $next);
            exit;
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-head" style="padding-bottom:var(--s-7);">
  <div class="container container--narrow center">
    <span class="eyebrow eyebrow--center" style="justify-content:center;">Account</span>
    <h1 class="page-head__title">Welcome back</h1>
    <p class="page-head__lead" style="margin-inline:auto;">
      Sign in to see your trips, rebook a previous journey in one tap,
      and check out faster next time.
    </p>
  </div>
</section>

<section class="section" style="padding-top:var(--s-6);">
  <div class="container" style="max-width:520px;">
    <div class="wizard__main">

      <?php if ($error): ?>
      <div class="alert alert--error" role="alert">
        <?= icon('alert') ?><span><?= e($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>

        <div class="field">
          <label class="field__label" for="email">
            Email address <span class="req" aria-hidden="true">*</span>
          </label>
          <input class="input" type="email" id="email" name="email"
                 value="<?= e($email) ?>" autocomplete="email"
                 inputmode="email" required autofocus>
        </div>

        <div class="field">
          <label class="field__label" for="password">
            Password <span class="req" aria-hidden="true">*</span>
          </label>
          <input class="input" type="password" id="password" name="password"
                 autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn--gold btn--block btn--lg mt-5">
          <?= icon('lock') ?><span>Sign In</span>
        </button>
      </form>

      <p class="summary__note center mt-6" style="font-size:var(--fs-sm);">
        Don&rsquo;t have an account?
        <a href="signup.php" class="text-gold" style="font-weight:600;">Create one</a>
        &mdash; it takes about thirty seconds.
      </p>
      <p class="summary__note center" style="font-size:var(--fs-sm);">
        You can also <a href="booking.php" class="text-gold">book without an account</a>.
      </p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
