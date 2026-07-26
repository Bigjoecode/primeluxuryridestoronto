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
$next  = (string)($_GET['next'] ?? 'index.php');
// Only allow same-directory redirects.
if (!preg_match('~^[a-z0-9._-]+\.php(\?.*)?$~i', $next)) {
    $next = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    <h1>Admin Sign In</h1>
    <p class="login-card__sub">Manage bookings, vehicles and pricing.</p>

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

      <div class="field">
        <label class="field__label" for="password">Password</label>
        <div class="pw-wrap">
          <input class="input" type="password" id="password" name="password"
                 autocomplete="current-password" required>
          <button type="button" class="pw-toggle" data-pw-toggle="password" aria-label="Show password">
            <span data-eye-open><?= icon('eye') ?></span>
            <span data-eye-close hidden><?= icon('close') ?></span>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn--gold btn--block btn--lg mt-5">
        <?= icon('lock') ?><span>Sign In</span>
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
