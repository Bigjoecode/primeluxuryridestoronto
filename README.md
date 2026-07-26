<div align="center">

<img src="assets/img/logo.png" alt="Prime Luxury Rides Toronto" width="360">

### Luxury chauffeur booking platform for Toronto & the GTA

Airport transfers · Corporate travel · Events · Hourly hire · Vehicle rental

<sub>PHP 8 · MySQL · Vanilla CSS & JS · No framework, no build step</sub>

</div>

---

## What this is

A complete, production-ready website and booking system for a private chauffeur
company: a public marketing site, a five-step booking flow with live pricing, and
a full admin panel for managing bookings, vehicles and prices.

It runs on any host with PHP and MySQL. There is nothing to compile — upload the
files, import one SQL file, edit one config file.

## Features

**Public site**
- Home, About, Services, Fleet, Rates, Rentals, Booking, Contact, Privacy, Terms, 404
- Black & gold luxury theme — Playfair Display + Inter
- Fully responsive, with an app-style bottom tab bar and menu sheet on phones
- WhatsApp click-to-chat and one-tap Call throughout
- SEO: per-page meta, Open Graph, JSON-LD `LimousineService` schema, dynamic sitemap

**Booking & pricing**
- Five steps — service → journey → vehicle → details → review
- Live quotes as the customer types, re-validated server-side before saving
- Confirmation email to the customer and an alert to the operator

**Admin panel** (`/admin/`)
- Dashboard with today's bookings, pending count, upcoming rides and revenue
- Bookings: filter, search, paginate, update status, assign a chauffeur, export CSV
- Vehicles: add/edit/remove, upload photos, set every price, control availability
- Flat rates: edit the city-to-city table per vehicle, copy rate cards between vehicles
- Settings: contact details, social links, HST, discounts, headline copy
- Enquiries inbox

**Integrations** — both optional and config-gated; the site degrades cleanly without them
- **Stripe Checkout** (card, Apple Pay, Google Pay) + signed webhook handler
- **Google Maps** address autocomplete and automatic distance/duration

## How pricing works

```
distance < 40 km   →  base + (km × rate_per_km) + (min × rate_per_min)
distance ≥ 40 km   →  published flat rate for that city
hourly             →  hours × hourly_rate   (raised to the vehicle minimum)
rental             →  cheapest split of weekly + daily rates

then:  − membership discount  →  + 13% HST  →  total
```

Every value is editable from the admin panel. Vehicle eligibility is enforced on
both the client and the server — the Maybach, for example, is restricted to hourly
hire and long-distance transfers.

## Quick start

```bash
# 1. Import the database
mysql -u root < sql/schema.sql

# 2. Point a vhost at the folder, or run the built-in server
php -S localhost:8000

# 3. Edit includes/config.php with your database credentials and SITE_URL
```

Full deployment steps, SMTP setup, Stripe and Maps configuration, and
troubleshooting are in **[SETUP.md](SETUP.md)**.

## ⚠️ Before you deploy

This repository is public, so treat everything in it as known:

- **Change the admin password immediately.** The seed account in `sql/schema.sql`
  ships with a documented default so the panel is reachable on first run. Sign in
  at `/admin/`, then Settings → *Change your password*.
- **Never commit real credentials.** `includes/config.php` is tracked because the
  site needs it, and it currently holds only empty placeholders. Once you add real
  Stripe, Maps, SMTP or database values, either make this repository private or
  untrack the file:
  ```bash
  git rm --cached includes/config.php
  echo "includes/config.php" >> .gitignore
  ```
- **Restrict your Google Maps API key** to your domain before going live.
- **Have the Privacy Policy and Terms reviewed by a lawyer** — they are drafts
  reflecting the configured pricing rules, not legal advice.

## Security

Prepared statements everywhere · CSRF tokens on every form · bcrypt passwords with
login throttling · HttpOnly + SameSite sessions · uploads validated by image content
rather than filename · script execution blocked in `/uploads` · `/includes`, `/logs`
and `/sql` blocked from web access · all output escaped.

## Project layout

```
├── *.php                 Public pages
├── includes/             Config, DB, pricing engine, mailer, Stripe, layout
├── api/                  Live quotes, Stripe checkout + webhook
├── admin/                Admin panel
├── assets/               CSS, JS, images
├── uploads/vehicles/     Vehicle photos (writable)
├── logs/                 Mail and error logs (writable)
└── sql/schema.sql        Schema + seeded fleet and rate data
```

---

<div align="center">
<sub>© Prime Luxury Rides Toronto</sub>
</div>
