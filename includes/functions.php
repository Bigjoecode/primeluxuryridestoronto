<?php
/**
 * Shared helpers: escaping, settings, CSRF, vehicles, formatting.
 */
require_once __DIR__ . '/db.php';

// ── Session ────────────────────────────────────────────────────────

/**
 * Start the session with hardened cookie flags.
 *
 * Called at include time — before any page emits output — because
 * session_start() must run before headers are sent. CSRF tokens depend
 * on it, so starting it lazily inside csrf_token() is not safe.
 */
function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE || headers_sent()) {
        return;
    }
    $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);
    session_name('PLRSESS');
    @session_start();
}

session_boot();

// ── Output escaping ────────────────────────────────────────────────

/** Escape for HTML text/attribute context. */
function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape and convert newlines to <br>. */
function e_nl(?string $v): string
{
    return nl2br(e($v));
}

/** JSON for inline <script> — safe against </script> injection. */
function e_json($v): string
{
    return json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// ── Settings ───────────────────────────────────────────────────────

/** All settings as key => value (cached per request). */
function settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        foreach (db_all('SELECT `key_name`, `value` FROM `settings`') as $r) {
            $cache[$r['key_name']] = $r['value'];
        }
    } catch (Throwable $e) {
        error_log('settings() failed: ' . $e->getMessage());
    }
    return $cache;
}

/** One setting with fallback. */
function setting(string $key, $default = ''): string
{
    $s = settings();
    $v = $s[$key] ?? null;
    return ($v === null || $v === '') ? (string)$default : (string)$v;
}

/** Numeric setting with fallback. */
function setting_num(string $key, float $default = 0): float
{
    $v = setting($key, (string)$default);
    return is_numeric($v) ? (float)$v : $default;
}

// ── CSRF ───────────────────────────────────────────────────────────

function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(?string $token): bool
{
    session_boot();
    return !empty($_SESSION['csrf'])
        && is_string($token)
        && hash_equals($_SESSION['csrf'], $token);
}

// ── Vehicles ───────────────────────────────────────────────────────

/** Active vehicles, ordered. */
function get_vehicles(bool $only_active = true): array
{
    $sql = 'SELECT * FROM `vehicles`'
         . ($only_active ? ' WHERE `is_active` = 1' : '')
         . ' ORDER BY `sort_order`, `id`';
    return db_all($sql);
}

/** Single vehicle by slug. */
function get_vehicle_by_slug(string $slug): ?array
{
    return db_one('SELECT * FROM `vehicles` WHERE `slug` = ? LIMIT 1', [$slug]);
}

/** Single vehicle by id. */
function get_vehicle(int $id): ?array
{
    return db_one('SELECT * FROM `vehicles` WHERE `id` = ? LIMIT 1', [$id]);
}

/** Vehicles available for rental. */
function get_rental_vehicles(): array
{
    return db_all('SELECT * FROM `vehicles`
                   WHERE `is_active` = 1 AND `rental_available` = 1
                   ORDER BY `sort_order`, `id`');
}

/** Flat rates for a vehicle. */
function get_flat_rates(int $vehicle_id): array
{
    return db_all('SELECT * FROM `flat_rates` WHERE `vehicle_id` = ?
                   ORDER BY `sort_order`, `distance_km`', [$vehicle_id]);
}

/** Does this vehicle allow the given service type? */
function vehicle_allows(array $vehicle, string $service): bool
{
    $map = [
        'airport'      => 'allow_airport',
        'city'         => 'allow_city',
        'city_to_city' => 'allow_city_to_city',
        'hourly'       => 'allow_hourly',
    ];
    if (!isset($map[$service])) {
        return true; // rentals handled by rental_available
    }
    return (int)($vehicle[$map[$service]] ?? 0) === 1;
}

/**
 * Public image URL for a vehicle. Returns null when no photo has been
 * uploaded yet — templates then render an inline SVG placeholder.
 */
function vehicle_image_url(array $vehicle): ?string
{
    $img = trim((string)($vehicle['image'] ?? ''));
    if ($img === '') {
        return null;
    }
    // Absolute URL supplied by admin
    if (preg_match('~^https?://~i', $img)) {
        return $img;
    }
    $path = UPLOAD_PATH . '/' . basename($img);
    return is_file($path) ? UPLOAD_URL . '/' . rawurlencode(basename($img)) : null;
}

/** Feature list as array. */
function vehicle_features(array $vehicle): array
{
    $raw = (string)($vehicle['features'] ?? '');
    $out = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    return array_values(array_filter(array_map('trim', $out), fn($v) => $v !== ''));
}

// ── Formatting ─────────────────────────────────────────────────────

/** CAD money, e.g. $1,250.00 */
function money(float $amount): string
{
    return '$' . number_format($amount, 2);
}

/** CAD money without cents when whole, e.g. $750 / $187.50 */
function money_short(float $amount): string
{
    return '$' . (fmod($amount, 1.0) === 0.0
        ? number_format($amount, 0)
        : number_format($amount, 2));
}

/** Human date-time, e.g. Sat 26 Jul 2026, 4:30 PM */
function fmt_datetime(?string $sql): string
{
    if (!$sql) {
        return '—';
    }
    try {
        return (new DateTime($sql))->format('D j M Y, g:i A');
    } catch (Throwable $e) {
        return e($sql);
    }
}

/** Human date only. */
function fmt_date(?string $sql): string
{
    if (!$sql) {
        return '—';
    }
    try {
        return (new DateTime($sql))->format('j M Y');
    } catch (Throwable $e) {
        return e($sql);
    }
}

/** Service type → display label. */
function service_label(string $key): string
{
    return [
        'airport'      => 'Airport Transfer',
        'city'         => 'In-City Ride',
        'city_to_city' => 'City to City Transfer',
        'hourly'       => 'Hourly Chauffeur',
        'rental'       => 'Vehicle Rental',
    ][$key] ?? ucfirst(str_replace('_', ' ', $key));
}

// ── Misc ───────────────────────────────────────────────────────────

/** WhatsApp click-to-chat URL. */
function whatsapp_url(string $message = ''): string
{
    $num = preg_replace('/\D+/', '', setting('whatsapp', '14160000000'));
    $msg = $message !== '' ? $message
        : 'Hello Prime Luxury Rides, I would like to enquire about a chauffeur booking.';
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($msg);
}

/** tel: URL from the configured phone number. */
function tel_url(): string
{
    return 'tel:' . preg_replace('/[^\d+]/', '', setting('phone', '+14160000000'));
}

/** Client IP (best effort). */
function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Is a feature configured? */
function maps_enabled(): bool  { return trim(GOOGLE_MAPS_API_KEY) !== ''; }
function stripe_enabled(): bool { return trim(STRIPE_SECRET_KEY) !== '' && trim(STRIPE_PUBLISHABLE_KEY) !== ''; }

/** Append a line to a log file under /logs. */
function app_log(string $file, string $line): void
{
    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0775, true);
    }
    @file_put_contents(
        LOG_PATH . '/' . basename($file),
        '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL,
        FILE_APPEND
    );
}

/**
 * Send the redirect to the browser and keep running server-side.
 *
 * Booking confirmation emails go through a shared mail host that
 * throttles rapid connections, which can stall a send for the better
 * part of a minute. That must not leave the customer watching a spinner
 * after they have already pressed Confirm — the booking is saved by this
 * point, so the redirect is honest. This flushes the response, then lets
 * the script carry on sending mail with nobody waiting on it.
 *
 * Call instead of header('Location: …') + exit, then do the slow work.
 */
function redirect_then_continue(string $location): void
{
    ignore_user_abort(true);

    if (!headers_sent()) {
        header('Location: ' . $location, true, 303);
        header('Content-Length: 0');
        header('Connection: close');
    }

    // Clear any buffering so the response actually leaves now.
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();

    // php-fpm can hand the connection back immediately; other SAPIs rely
    // on the headers above plus the flush.
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }

    // Slow work should not be killed by the web server's normal limit.
    @set_time_limit(120);
}

/** Generate the next booking reference, e.g. PLR-2026-0042. */
function next_booking_reference(): string
{
    $year = date('Y');
    $row  = db_one("SELECT COUNT(*) AS n FROM `bookings`
                    WHERE `reference` LIKE ?", ["PLR-$year-%"]);
    $seq  = (int)($row['n'] ?? 0) + 1;

    // Guard against collisions if a row was deleted.
    do {
        $ref = sprintf('PLR-%s-%04d', $year, $seq);
        $hit = db_one('SELECT id FROM `bookings` WHERE `reference` = ? LIMIT 1', [$ref]);
        $seq++;
    } while ($hit !== null);

    return $ref;
}
