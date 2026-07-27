<?php
/**
 * Customer accounts: registration, sign-in, saved addresses, trip history.
 *
 * Membership tier lives on the customer record and is set by the operator
 * in the admin panel — it is never chosen by the customer, so a discount
 * cannot be claimed simply by picking it from a dropdown.
 */
require_once __DIR__ . '/functions.php';

const CUSTOMER_MAX_ATTEMPTS = 8;
const CUSTOMER_LOCKOUT_SECS = 600;   // 10 minutes

/** Signed-in customer, or null. */
function customer(): ?array
{
    static $cache = null;
    session_boot();

    if (empty($_SESSION['customer_id'])) {
        return null;
    }
    if ($cache !== null) {
        return $cache;
    }

    $row = db_one('SELECT * FROM `customers` WHERE `id` = ? AND `is_active` = 1 LIMIT 1',
                  [(int)$_SESSION['customer_id']]);

    if ($row === null) {                 // deleted or deactivated mid-session
        unset($_SESSION['customer_id']);
        return null;
    }
    return $cache = $row;
}

/** Redirect to sign-in unless a customer is logged in. */
function require_customer(): array
{
    $c = customer();
    if ($c === null) {
        $next = basename(strtok((string)($_SERVER['REQUEST_URI'] ?? 'account.php'), '?'));
        header('Location: signin.php?next=' . urlencode($next));
        exit;
    }
    return $c;
}

/**
 * Create an account. Returns [customer|null, error|null].
 */
function customer_register(string $name, string $email, string $phone, string $password): array
{
    $name  = trim($name);
    $email = mb_strtolower(trim($email));
    $phone = trim($phone);

    if ($name === '')                                 { return [null, 'Please enter your full name.']; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))   { return [null, 'Please enter a valid email address.']; }
    if (strlen($password) < 8)                        { return [null, 'Your password must be at least 8 characters.']; }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return [null, 'Your password must contain both letters and numbers.'];
    }

    if (db_one('SELECT `id` FROM `customers` WHERE `email` = ? LIMIT 1', [$email])) {
        return [null, 'An account already exists with that email address. Please sign in instead.'];
    }

    try {
        db_exec('INSERT INTO `customers` (`email`,`password_hash`,`full_name`,`phone`)
                 VALUES (?,?,?,?)',
                [$email, password_hash($password, PASSWORD_BCRYPT), $name,
                 $phone !== '' ? $phone : null]);
    } catch (Throwable $ex) {
        app_log('errors.log', 'customer register failed: ' . $ex->getMessage());
        return [null, 'We could not create your account. Please try again.'];
    }

    $row = db_one('SELECT * FROM `customers` WHERE `email` = ? LIMIT 1', [$email]);
    customer_start_session($row);

    // Adopt any past bookings made with this email before signing up.
    db_exec('UPDATE `bookings` SET `customer_id` = ?
             WHERE `customer_id` IS NULL AND `email` = ?',
            [(int)$row['id'], $email]);

    return [$row, null];
}

/** Sign in. Returns an error string, or null on success. */
function customer_login(string $email, string $password): ?string
{
    session_boot();

    $fails = (int)($_SESSION['cust_fails'] ?? 0);
    $until = (int)($_SESSION['cust_until'] ?? 0);
    if ($fails >= CUSTOMER_MAX_ATTEMPTS && time() < $until) {
        $mins = max(1, (int)ceil(($until - time()) / 60));
        return "Too many failed attempts. Please try again in {$mins} minute"
             . ($mins > 1 ? 's' : '') . '.';
    }

    $row = db_one('SELECT * FROM `customers` WHERE `email` = ? LIMIT 1',
                  [mb_strtolower(trim($email))]);

    if ($row === null || !password_verify($password, (string)$row['password_hash'])) {
        $_SESSION['cust_fails'] = $fails + 1;
        $_SESSION['cust_until'] = time() + CUSTOMER_LOCKOUT_SECS;
        return 'That email address or password is not correct.';
    }

    if ((int)$row['is_active'] !== 1) {
        return 'That account has been deactivated. Please contact us.';
    }

    if (password_needs_rehash((string)$row['password_hash'], PASSWORD_BCRYPT)) {
        db_exec('UPDATE `customers` SET `password_hash` = ? WHERE `id` = ?',
                [password_hash($password, PASSWORD_BCRYPT), (int)$row['id']]);
    }

    customer_start_session($row);
    unset($_SESSION['cust_fails'], $_SESSION['cust_until']);

    db_exec('UPDATE `customers` SET `last_login_at` = NOW() WHERE `id` = ?', [(int)$row['id']]);
    db_exec('UPDATE `bookings` SET `customer_id` = ?
             WHERE `customer_id` IS NULL AND `email` = ?',
            [(int)$row['id'], $row['email']]);

    return null;
}

/** Establish the session for a customer row. */
function customer_start_session(array $row): void
{
    session_boot();
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int)$row['id'];
}

/** Sign the customer out, leaving any admin session untouched. */
function customer_logout(): void
{
    session_boot();
    unset($_SESSION['customer_id']);
    session_regenerate_id(true);
}

// ── Saved addresses ────────────────────────────────────────────────

function customer_addresses(int $customer_id): array
{
    return db_all('SELECT * FROM `customer_addresses` WHERE `customer_id` = ?
                   ORDER BY `sort_order`, `id`', [$customer_id]);
}

function customer_add_address(int $customer_id, string $label, string $address): ?string
{
    $label   = trim($label) !== '' ? trim($label) : 'Saved place';
    $address = trim($address);

    if ($address === '') {
        return 'Please enter an address.';
    }
    if (count(customer_addresses($customer_id)) >= 12) {
        return 'You can save up to 12 addresses. Please remove one first.';
    }
    db_exec('INSERT INTO `customer_addresses` (`customer_id`,`label`,`address`) VALUES (?,?,?)',
            [$customer_id, mb_substr($label, 0, 60), mb_substr($address, 0, 255)]);
    return null;
}

function customer_delete_address(int $customer_id, int $address_id): void
{
    db_exec('DELETE FROM `customer_addresses` WHERE `id` = ? AND `customer_id` = ?',
            [$address_id, $customer_id]);
}

// ── Trips ──────────────────────────────────────────────────────────

/** Bookings belonging to a customer, newest first. */
function customer_bookings(int $customer_id, ?string $scope = null): array
{
    $sql = 'SELECT * FROM `bookings` WHERE `customer_id` = ?';
    if ($scope === 'upcoming') {
        $sql .= " AND `pickup_at` >= NOW() AND `status` != 'cancelled'";
        $sql .= ' ORDER BY `pickup_at` ASC';
    } elseif ($scope === 'past') {
        $sql .= " AND (`pickup_at` < NOW() OR `status` = 'cancelled')";
        $sql .= ' ORDER BY `pickup_at` DESC';
    } else {
        $sql .= ' ORDER BY `pickup_at` DESC';
    }
    return db_all($sql, [$customer_id]);
}

/**
 * Build the booking.php query string that reproduces a past trip,
 * so "book this again" lands on a pre-filled form.
 */
function rebook_url(array $b): string
{
    $params = array_filter([
        'service' => (string)$b['service_type'],
        'from'    => (string)$b['pickup_address'],
        'to'      => (string)($b['dropoff_address'] ?? ''),
        'pax'     => (int)$b['passengers'] ?: null,
        'hours'   => (int)($b['hours'] ?? 0) ?: null,
    ], fn($v) => $v !== null && $v !== '' && $v !== 0);

    if (!empty($b['vehicle_id'])) {
        $v = get_vehicle((int)$b['vehicle_id']);
        if ($v) {
            $params['vehicle'] = $v['slug'];
        }
    }
    return 'booking.php?' . http_build_query($params);
}

/** Membership label for display. */
function membership_label(string $tier): string
{
    return ['none' => 'Standard', 'elite' => 'Elite Member', 'vip' => 'VIP Member'][$tier] ?? 'Standard';
}

/** Discount percentage for a tier, from settings. */
function membership_discount(string $tier): float
{
    if ($tier === 'elite') { return setting_num('elite_discount', 30); }
    if ($tier === 'vip')   { return setting_num('vip_discount', 40); }
    return 0.0;
}
