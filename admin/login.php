<?php
/** Admin sign-in. */
require_once __DIR__ . '/includes/auth.php';

// Already signed in? Go straight through.
if (admin_user() !== null) {
    header('Location: index.php');
    exit;
}

$error = null;
$email = '';

/**
 * First-run setup.
 *
 * The schema ships with no administrator on purpose — a known password in
 * a public repository would let anyone walk into the admin panel. Until an
 * admin exists this page creates one instead of signing in; once one does,
 * this branch can never run again.
 */
$needs_setup = (int)(db_one('SELECT COUNT(*) AS n FROM `admin_users`')['n'] ?? 0) === 0;

if ($needs_setup && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $pw    = (string)($_POST['password'] ?? '');
        $conf  = (string)($_POST['confirm_password'] ?? '');
        $name  = trim((string)($_POST['full_name'] ?? '')) ?: 'Administrator';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($pw) < 12) {
            $error = 'Your password must be at least 12 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
            $error = 'Your password must contain both letters and numbers.';
        } elseif ($pw !== $conf) {
            $error = 'The two passwords do not match.';
        } else {
            // Re-check inside the request that creates the row, so two
            // simultaneous visitors cannot both create an administrator.
            db_exec('INSERT INTO `admin_users` (`email`,`password_hash`,`full_name`)
                     SELECT ?, ?, ? FROM DUAL
                      WHERE NOT EXISTS (SELECT 1 FROM `admin_users`)',
                    [mb_strtolower($email), password_hash($pw, PASSWORD_BCRYPT), $name]);

            app_log('admin.log', 'Initial administrator created: ' . $email);

            if (admin_login($email, $pw) === null) {
                header('Location: index.php');
                exit;
            }
            $error = 'Account created. Please sign in.';
            $needs_setup = false;
        }
    }
}

$next  = (string)($_GET['next'] ?? 'index.php');
// Only allow same-directory redirects.
if (!preg_match('~^[a-z0-9._-]+\.php(\?.*)?$~i', $next)) {
    $next = 'index.php';
}

if (!$needs_setup && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try signing in again.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $error = admin_login($email, (string)($_POST['password'] ?? ''));
        if ($error === null) {
            header('Location: ' . $next);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en-CA">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Admin Sign In &mdash; <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#08070a">
<link rel="icon" href="../assets/img/icon.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../assets/css/main.css?v=<?= @filemtime(ROOT_PATH . '/assets/css/main.css') ?: '1' ?>">
<link rel="stylesheet" href="../assets/css/admin.css?v=<?= @filemtime(ROOT_PATH . '/assets/css/admin.css') ?: '1' ?>">
</head>
<body class="admin">

<div class="login-wrap">
  <div class="login-card">
    <img src="../assets/img/logo.png" alt="<?= e(SITE_NAME) ?>" width="212" height="70">
    <?php if ($needs_setup): ?>
    <h1>Create your administrator</h1>
    <p class="login-card__sub">
      This is a one-time setup. Choose the credentials you&rsquo;ll use to manage
      bookings, vehicles and pricing.
    </p>
    <div class="alert alert--gold" style="text-align:left;">
      <?= icon('lock') ?>
      <span>No administrator exists yet, so this form is open to anyone who reaches it.
        Complete it now &mdash; once your account is created this page becomes a normal
        sign-in and can never be used to create another.</span>
    </div>
    <?php else: ?>
    <h1>Admin Sign In</h1>
    <p class="login-card__sub">Manage bookings, vehicles and pricing.</p>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert--error" role="alert">
      <?= icon('alert') ?><span><?= e($error) ?></span>
    </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>

      <div class="field">
        <label class="field__label" for="email">Email address</label>
        <input class="input" type="email" id="email" name="email"
               value="<?= e($email) ?>" autocomplete="username"
               inputmode="email" required autofocus>
      </div>

      <?php if ($needs_setup): ?>
      <div class="field">
        <label class="field__label" for="full_name">Your name</label>
        <input class="input" type="text" id="full_name" name="full_name"
               placeholder="Administrator" autocomplete="name">
      </div>
      <?php endif; ?>

      <div class="field">
        <label class="field__label" for="password">Password</label>
        <div class="pw-wrap">
          <input class="input" type="password" id="password" name="password"
                 autocomplete="<?= $needs_setup ? 'new-password' : 'current-password' ?>"
                 <?= $needs_setup ? 'minlength="12"' : '' ?> required>
          <button type="button" class="pw-toggle" data-pw-toggle="password" aria-label="Show password">
            <span data-eye-open><?= icon('eye') ?></span>
            <span data-eye-close hidden><?= icon('close') ?></span>
          </button>
        </div>
      </div>

      <?php if ($needs_setup): ?>
      <div class="field">
        <label class="field__label" for="confirm_password">Confirm password</label>
        <input class="input" type="password" id="confirm_password" name="confirm_password"
               autocomplete="new-password" minlength="12" required>
        <span class="field__hint">At least 12 characters, with letters and numbers.</span>
      </div>
      <?php endif; ?>

      <button type="submit" class="btn btn--gold btn--block btn--lg mt-5">
        <?= icon('lock') ?><span><?= $needs_setup ? 'Create Administrator' : 'Sign In' ?></span>
      </button>
    </form>

    <a class="login-back" href="../index.php">&larr; Back to website</a>
  </div>
</div>

<script>
document.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = document.getElementById(btn.getAttribute('data-pw-toggle'));
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    btn.querySelector('[data-eye-open]').hidden  = show;
    btn.querySelector('[data-eye-close]').hidden = !show;
  });
});
</script>
</body>
</html>
