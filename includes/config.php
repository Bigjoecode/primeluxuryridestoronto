<?php
/**
 * Prime Luxury Rides Toronto — Configuration
 * ------------------------------------------------------------------
 * Edit the values in this file to match your server / accounts.
 * Everything else on the site reads from here.
 */

// ── Environment ────────────────────────────────────────────────────
// 'dev' shows PHP errors on screen. Set to 'live' before going public.
define('APP_ENV', 'dev');

// Public base URL (no trailing slash). Used for canonical tags, emails, Stripe returns.
define('SITE_URL', 'http://localhost/primeluxuryridestoronto.ca');

define('SITE_NAME',    'Prime Luxury Rides Toronto');
define('SITE_TAGLINE', 'Luxury Chauffeur Services in Toronto');

// ── Database ───────────────────────────────────────────────────────
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'primeluxuryrides');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', '3307');   // XAMPP on this machine runs MySQL on 3307

// ── Email ──────────────────────────────────────────────────────────
// Bookings and enquiries are sent here.
define('ADMIN_EMAIL', 'info@primeluxuryridestoronto.ca');
define('MAIL_FROM',   'info@primeluxuryridestoronto.ca');
define('MAIL_FROM_NAME', 'Prime Luxury Rides Toronto');

// SMTP — leave SMTP_ENABLED false to use PHP mail(). On XAMPP locally,
// mail() usually will not deliver; messages are logged instead (see logs/mail.log).
define('SMTP_ENABLED',  false);
define('SMTP_HOST',     'smtp.example.com');
define('SMTP_PORT',     587);
define('SMTP_USER',     '');
define('SMTP_PASS',     '');
define('SMTP_SECURE',   'tls');           // 'tls' or 'ssl'

// ── Google Maps ────────────────────────────────────────────────────
// Enable "Places API", "Maps JavaScript API" and "Distance Matrix API"
// on the same key. Leave blank → booking form falls back to plain
// address inputs + a manual distance field (site still works).
define('GOOGLE_MAPS_API_KEY', '');

// ── Stripe ─────────────────────────────────────────────────────────
// Leave blank → booking completes as "pay on confirmation" (no card step).
define('STRIPE_PUBLISHABLE_KEY', '');
define('STRIPE_SECRET_KEY',      '');
define('STRIPE_WEBHOOK_SECRET',  '');
define('STRIPE_CURRENCY',        'cad');

// ── SMS (Twilio) ───────────────────────────────────────────────────
// Used to text the customer their chauffeur's name, car and plate when
// you assign a driver. Leave blank → the site logs the message to
// logs/sms.log instead and still sends the email version.
define('TWILIO_SID',   '');
define('TWILIO_TOKEN', '');
define('TWILIO_FROM',  '');          // e.g. +14165550000

// ── Business rules (defaults; overridable in Admin → Settings) ─────
define('DEFAULT_HST_RATE',      13.0);   // %
define('FLAT_RATE_THRESHOLD_KM', 40);    // >= this distance uses the flat-rate table
define('MIN_LEAD_TIME_HOURS',    3);     // earliest bookable pickup from now

// ── Paths ──────────────────────────────────────────────────────────
define('ROOT_PATH',   dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads/vehicles');
define('UPLOAD_URL',  '/uploads/vehicles');
define('LOG_PATH',    ROOT_PATH . '/logs');

// ── Error reporting ────────────────────────────────────────────────
if (APP_ENV === 'dev') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH . '/php-errors.log');
}

date_default_timezone_set('America/Toronto');
