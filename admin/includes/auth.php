<?php
/**
 * Admin authentication guard.
 * Include at the very top of every admin page except login.php.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/icons.php';

const ADMIN_IDLE_TIMEOUT = 7200;   // 2 hours
const LOGIN_MAX_ATTEMPTS = 6;
const LOGIN_LOCKOUT_SECS = 900;    // 15 minutes

/** Currently signed-in admin, or null. */
function admin_user(): ?array
{
    session_boot();
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    // Idle timeout
    $last = (int)($_SESSION['admin_seen'] ?? 0);
    if ($last > 0 && (time() - $last) > ADMIN_IDLE_TIMEOUT) {
        admin_logout();
        return null;
    }
    $_SESSION['admin_seen'] = time();

    return [
        'id'    => (int)$_SESSION['admin_id'],
        'email' => (string)($_SESSION['admin_email'] ?? ''),
        'name'  => (string)($_SESSION['admin_name'] ?? 'Administrator'),
    ];
}

/** Redirect to login unless signed in. */
function require_admin(): array
{
    $user = admin_user();
    if ($user === null) {
        $to = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
        header('Location: login.php?next=' . $to);
        exit;
    }
    return $user;
}

/** Attempt sign-in. Returns an error string, or null on success. */
function admin_login(string $email, string $password): ?string
{
    session_boot();

    // Throttle repeated failures per session.
    $fails = (int)($_SESSION['login_fails'] ?? 0);
    $until = (int)($_SESSION['login_until'] ?? 0);
    if ($fails >= LOGIN_MAX_ATTEMPTS && time() < $until) {
        $mins = max(1, (int)ceil(($until - time()) / 60));
        return "Too many failed attempts. Please try again in {$mins} minute"
             . ($mins > 1 ? 's' : '') . '.';
    }

    $row = db_one('SELECT * FROM `admin_users` WHERE `email` = ? LIMIT 1',
                  [mb_strtolower(trim($email))]);

    if ($row === null || !password_verify($password, (string)$row['password_hash'])) {
        $_SESSION['login_fails'] = $fails + 1;
        $_SESSION['login_until'] = time() + LOGIN_LOCKOUT_SECS;
        app_log('admin.log', 'Failed login for ' . $email . ' from ' . client_ip());
        return 'That email address or password is not correct.';
    }

    // Upgrade the stored hash if PHP's default cost has changed.
    if (password_needs_rehash((string)$row['password_hash'], PASSWORD_BCRYPT)) {
        db_exec('UPDATE `admin_users` SET `password_hash` = ? WHERE `id` = ?',
                [password_hash($password, PASSWORD_BCRYPT), (int)$row['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['admin_id']    = (int)$row['id'];
    $_SESSION['admin_email'] = $row['email'];
    $_SESSION['admin_name']  = $row['full_name'];
    $_SESSION['admin_seen']  = time();
    unset($_SESSION['login_fails'], $_SESSION['login_until']);

    db_exec('UPDATE `admin_users` SET `last_login_at` = NOW() WHERE `id` = ?', [(int)$row['id']]);
    app_log('admin.log', 'Login OK: ' . $row['email'] . ' from ' . client_ip());

    return null;
}

/** Sign out and clear the session. */
function admin_logout(): void
{
    session_boot();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
                  $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Flash message helper. */
function flash(string $type = '', string $message = '')
{
    session_boot();
    if ($message !== '') {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Guard a POST action: verifies CSRF or aborts with a flash + redirect. */
function admin_post_guard(string $redirect_to): void
{
    if (!csrf_check($_POST['csrf'] ?? null)) {
        flash('error', 'Your session expired. Please try that again.');
        header('Location: ' . $redirect_to);
        exit;
    }
}
