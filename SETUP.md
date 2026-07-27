# Prime Luxury Rides Toronto — Setup & Owner's Guide

A complete chauffeur booking website: public site, live pricing engine,
multi-step booking flow, email notifications, and a full admin panel.

**Stack:** PHP 8 + MySQL/MariaDB + vanilla CSS/JS.
No framework, no build step, no Composer — upload and run.

---

## 1. Quick start (local, XAMPP)

Already done on this machine, but for reference:

```bash
# 1. Import the database (fresh install - includes every table)
mysql -u root -P 3307 -h 127.0.0.1 < sql/schema.sql

#    Upgrading an existing database instead? Run this once:
#    mysql -u root -P 3307 -h 127.0.0.1 primeluxuryrides < sql/upgrade-002.sql

# 2. Serve the folder
php -S 127.0.0.1:8899 -t .
```

Then open <http://127.0.0.1:8899/>.

> **Note on this machine:** XAMPP's MySQL runs on **port 3307**, not the usual 3306.
> That is already set in `includes/config.php`.

---

## 2. Going live — the only file you must edit

Everything server-specific lives in **`includes/config.php`**.

```php
define('APP_ENV',  'live');                            // hides PHP errors from visitors
define('SITE_URL', 'https://primeluxuryridestoronto.ca');   // no trailing slash

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_PORT', '3306');                             // usually 3306 on real hosting

define('ADMIN_EMAIL', 'info@primeluxuryridestoronto.ca');
```

### Deployment checklist

1. Upload every file **except** `READ.md` and the `flat rates (*).jpeg` source images.
2. Create a MySQL database, then import `sql/schema.sql` (phpMyAdmin → Import).
3. Edit `includes/config.php` with your real database credentials and `SITE_URL`.
4. Set `APP_ENV` to `'live'`.
5. Make these folders writable by the web server (`chmod 775`):
   - `uploads/vehicles/` — vehicle photos
   - `logs/` — mail and error logs
6. Install your SSL certificate, then **uncomment the HTTPS redirect block** in `.htaccess`.
7. Sign in at `/admin/` and **change the password immediately** (see §3).
8. Submit `https://primeluxuryridestoronto.ca/sitemap.xml` in Google Search Console.

---

## 3. Admin panel

**URL:** `/admin/`

| | |
|---|---|
| **Email** | `info@primeluxuryridestoronto.ca` |
| **Password** | `PrimeAdmin2026!` |

> ⚠️ **Change this password the first time you sign in.**
> Admin → Settings → *Change your password*. Minimum 10 characters, letters and numbers.

### What you can do without touching code

| Page | What it controls |
|---|---|
| **Dashboard** | Today's bookings, pending count, upcoming rides, revenue this month |
| **Bookings** | Search & filter all bookings, update status, assign a chauffeur, add internal notes, **export to CSV/Excel** |
| **Enquiries** | Contact-form messages, mark read, reply by email or WhatsApp |
| **Vehicles** | Add / edit / remove vehicles, **upload photos**, set all prices, control which services each vehicle allows, reorder, hide |
| **Flat Rates** | Edit the city-to-city price table per vehicle, add destinations, copy a full rate card between vehicles |
| **Chauffeurs** | Driver roster - add, edit, mark available or off duty |
| **Customers** | Registered accounts, spend per customer, and **granting Elite / VIP membership** |
| **Settings** | Phone, WhatsApp, email, hours, social links, **HST rate**, membership discounts, return-trip discount, per-stop fee, flat-rate threshold, home page headline, meta description |

---

## 4. How pricing works

Configured entirely from the admin panel. The rules implemented are exactly as specified:

```
distance < 40 km   →  base + (km × rate_per_km) + (min × rate_per_min)
distance ≥ 40 km   →  flat rate from the city table
hourly             →  hours × hourly_rate  (below the minimum is raised automatically)
rental             →  cheapest split of weekly + daily rates

  + per-stop fee for each additional stop (hourly hire includes unlimited stops)
  + return leg at the same price, then a return discount for booking both together

then:  − membership discount  →  + 13% HST  →  total
```

**Membership is granted by you, never chosen by the customer.** Set a tier in
Admin -> Customers and the discount applies automatically whenever that person is
signed in. A crafted request cannot claim a discount: the server reads the tier
from the account session and ignores anything sent in the form.

### Seeded from your rate sheets

| From Toronto | S580 | Escalade ESV | Suburban | Maybach GLS 600 |
|---|---|---|---|---|
| Hamilton (~70 km) | $150 | $175 | $175 | $220 |
| Mississauga (~25 km) | Dynamic | Dynamic | Dynamic | Dynamic |
| Brampton (~40 km) | Dynamic | Dynamic | Dynamic | Dynamic |
| Oshawa (~60 km) | $140 | $165 | $165 | $200 |
| Barrie (~90 km) | $180 | $220 | $220 | $300 |
| Kitchener / Waterloo (~110 km) | $200 | $240 | $240 | $320 |
| Niagara Falls (~130 km) | $250 | $300 | $300 | $400 |
| London, ON (~190 km) | $350 | $400 | $400 | $520 |
| Kingston (~260 km) | $450 | $500 | $500 | $650 |
| Ottawa (~450 km) | $750 | $800 | $800 | $1,000 |

Dynamic rates per vehicle:

| Vehicle | Base | Per km | Per min | Hourly | Min hours | Seats |
|---|---|---|---|---|---|---|
| Mercedes-Benz S580 | $20 | $2.25 | $0.95 | $100 | 3 | 3 |
| Cadillac Escalade ESV | $25 | $2.75 | $1.10 | $130 | 3 | 6 |
| Chevrolet Suburban | $25 | $2.75 | $1.10 | $120 | 3 | 6 |
| Mercedes-Maybach GLS 600 | $35 | $3.50 | $1.40 | $150 | **4** | 2 |

**Vehicle availability rules** (enforced in the booking form *and* server-side):

- **Maybach** — Hourly Chauffeur and City-to-City only. No airport, no in-city rides.
- **S580, Escalade, Suburban** — available for every service.

**Membership:** Elite 30% off · VIP 40% off — applied before HST, editable in Settings.

### ⚠️ Two things to confirm

1. **Suburban flat rates** — your rate sheets covered the S580, Escalade and Maybach only.
   The Suburban has been given the **same rates as the Escalade** (both are 6-passenger SUVs).
   If it should be priced differently, change it in Admin → Flat Rates.
2. **Suburban and Maybach hourly rates** ($120 and $150) are sensible placeholders derived
   from your example ("$100/hour S-Class, $150/hour Maybach"). Confirm before launch.

---

## 5. Email notifications

Every booking sends two branded emails: a confirmation to the customer and an alert to you.
Contact-form messages do the same.

**On local XAMPP, email will not actually deliver** — there is no mail server. That is expected.
Every message is still written in full to `logs/mail.log`, so nothing is lost.

For reliable delivery on the live server, set up SMTP in `includes/config.php`:

```php
define('SMTP_ENABLED', true);
define('SMTP_HOST',   'smtp.your-provider.com');
define('SMTP_PORT',   587);
define('SMTP_USER',   'info@primeluxuryridestoronto.ca');
define('SMTP_PASS',   'your-mailbox-password');
define('SMTP_SECURE', 'tls');
```

Use the mailbox your host provides, or a service like Zoho / Google Workspace / SendGrid.
Sending from your own domain dramatically improves inbox placement.

---

## 5b. SMS notifications (optional)

When you assign a chauffeur, the customer is emailed their driver's name, the
vehicle and its number plate, plus a private tracking link. Add Twilio credentials
to send it as a text as well:

```php
define('TWILIO_SID',   'AC...');
define('TWILIO_TOKEN', '...');
define('TWILIO_FROM',  '+14165550000');
```

Without them the email still sends and the text is written to `logs/sms.log`, so
you can see exactly what would have gone out.

Add number plates in Admin -> Vehicles so customers can identify the car on arrival.

## 5c. SEO route pages

Every destination in your flat-rate table automatically gets its own landing page:

```
/toronto-to-niagara-falls-car-service
/toronto-to-ottawa-car-service
```

Each carries that route's published prices, distance, drive time, an FAQ block with
schema markup, and a pre-filled booking button. **Add a city in Admin -> Flat Rates
and a new indexable page appears**, and it is added to the sitemap automatically.

These pages are how you rank for searches like "toronto to niagara car service",
which is where paying customers actually come from.

## 6. Google Maps (optional)

Without a key the site works fine — customers type addresses and you confirm the distance.

**With a key** you get address autocomplete and automatic distance/duration, so quotes
price the real route instantly.

1. Go to <https://console.cloud.google.com/> → create a project.
2. Enable: **Maps JavaScript API**, **Places API**, **Distance Matrix API**.
3. Create an API key, then **restrict it** to your domain (HTTP referrers) — this matters,
   an unrestricted key can be abused and billed to you.
4. Paste it into `includes/config.php`:

```php
define('GOOGLE_MAPS_API_KEY', 'AIza...');
```

---

## 7. Stripe payments (optional)

Without keys, bookings complete as "pay on confirmation" and no card step is shown.

**With keys**, customers can pay by card, Apple Pay and Google Pay.

1. Create an account at <https://dashboard.stripe.com/>.
2. Copy your **Publishable** and **Secret** keys.
3. Add a webhook endpoint pointing to
   `https://primeluxuryridestoronto.ca/api/stripe-webhook.php`,
   subscribed to `checkout.session.completed`, `checkout.session.expired`, `charge.refunded`.
   Copy the **signing secret**.
4. Fill in `includes/config.php`:

```php
define('STRIPE_PUBLISHABLE_KEY', 'pk_live_...');
define('STRIPE_SECRET_KEY',      'sk_live_...');
define('STRIPE_WEBHOOK_SECRET',  'whsec_...');
```

**Full payment vs deposit:** Admin → Settings → *Charge at booking (%)*.
`100` charges the full amount at booking; `20` would take a 20% deposit.

---

## 8. Adding your photos

The site is fully functional without photos — vehicles show an elegant gold line-art
placeholder rather than a broken image. But real photography is the single biggest visual
upgrade available.

- **Vehicle photos** — Admin → Vehicles → Edit → *Upload a photo*.
  Landscape, roughly **1600×1000**, under 6 MB. JPG or WebP.
- **Home page hero** — save your best photo as `assets/img/hero.jpg`.
  It is picked up automatically. Wide landscape, at least **1920×1080**.
  Until then a designed gradient treatment is shown.

Compress images before uploading (<https://squoosh.app>) — it keeps the site fast.

---

## 9. File map

```
├── index.php            Home
├── about.php            About Us
├── services.php         Services (7 services, anchor-linked)
├── fleet.php            Fleet with full specs
├── rates.php            Public flat-rate tables
├── rentals.php          Car rental page
├── booking.php          5-step booking flow
├── confirmation.php     Booking receipt + payment
├── route.php            SEO landing page per route (pretty URL via .htaccess)
├── track.php            Private ride tracking (/t/<token>)
├── account.php          Customer dashboard - trips, rebook, saved places
├── signin.php           Customer sign in / sign up / sign out
├── contact.php          Contact + enquiry form
├── privacy.php          Privacy Policy      ← have a lawyer review
├── terms.php            Terms & Conditions  ← have a lawyer review
├── 404.php              Not found
├── sitemap.php          Dynamic XML sitemap (served at /sitemap.xml)
│
├── includes/
│   ├── config.php       ★ ALL SETTINGS LIVE HERE
│   ├── db.php           Database connection
│   ├── functions.php    Helpers, sessions, CSRF
│   ├── pricing.php      ★ The pricing engine
│   ├── mailer.php       Email sending + templates
│   ├── stripe.php       Stripe client
│   ├── icons.php        SVG icon set
│   ├── header.php       Site header + SEO meta
│   └── footer.php       Site footer + WhatsApp/Call buttons
│
├── api/
│   ├── quote.php            Live price quotes (AJAX)
│   ├── stripe-checkout.php  Starts a payment
│   └── stripe-webhook.php   Receives payment confirmations
│
├── admin/               Admin panel (11 pages)
├── assets/css|js|img/   Styles, scripts, logo
├── uploads/vehicles/    Vehicle photos (must be writable)
├── logs/                Mail + error logs (must be writable)
├── sql/schema.sql       Database structure + rate data (fresh install)
└── sql/upgrade-002.sql  Upgrade an existing database in place
```

---

## 10. Security notes

Already in place:

- All database access uses prepared statements (no SQL injection).
- Every form is CSRF-protected; sessions are HttpOnly + SameSite=Lax, Secure over HTTPS.
- Admin passwords are bcrypt-hashed; login throttles after 6 failed attempts.
- Uploads are validated by actual image content, not filename, and script execution is
  blocked inside `/uploads` by `.htaccess`.
- `/includes`, `/logs` and `/sql` are blocked from direct web access.
- All output is escaped.

Still to do on your side:

- [ ] Change the admin password.
- [ ] Install SSL and uncomment the HTTPS redirect in `.htaccess`.
- [ ] Set `APP_ENV` to `'live'`.
- [ ] Restrict your Google Maps key to your domain.
- [ ] Have the Privacy Policy and Terms reviewed by a lawyer.

---

## 11. Troubleshooting

| Symptom | Fix |
|---|---|
| "Database connection failed" | Check credentials and `DB_PORT` in `includes/config.php`; confirm `sql/schema.sql` was imported. |
| Emails not arriving | Expected on local XAMPP — check `logs/mail.log`. On live, configure SMTP (§5). |
| Photo upload fails | Make `uploads/vehicles/` writable (chmod 775). |
| Prices look wrong | Admin → Flat Rates and Admin → Vehicles. Check the HST rate in Settings. |
| Pretty URLs 404 | Enable Apache `mod_rewrite`, and ensure `AllowOverride All` is set for the folder. |
| Locked out of admin | Run in phpMyAdmin: `UPDATE admin_users SET password_hash = '<new bcrypt hash>' WHERE id = 1;` — generate the hash with `password_hash('yourpassword', PASSWORD_BCRYPT)`. |
