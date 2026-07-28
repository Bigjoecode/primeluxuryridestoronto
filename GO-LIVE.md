# Go-Live Checklist

Work through in order. Sections 1–4 are required; 5–6 make the site
better but will not stop you taking bookings.

---

## 1. Server and configuration

```bash
# On the server
cp includes/config.example.php includes/config.php
```

Then edit `includes/config.php`:

| Setting | Value |
|---|---|
| `APP_ENV` | `'live'` — **critical.** On `'dev'` PHP errors are printed to visitors. |
| `SITE_URL` | `https://primeluxuryridestoronto.ca` — no trailing slash, and **https**. Used for canonical tags, email links, Stripe returns and tracking links. |
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` / `DB_PORT` | From your host. Port is usually `3306`. |
| `ADMIN_EMAIL` | Where bookings and enquiries are sent. |

Import the database:

```bash
mysql -u USER -p DBNAME < sql/schema.sql
mysql -u USER -p DBNAME < sql/upgrade-002.sql
mysql -u USER -p DBNAME < sql/upgrade-003.sql
```

Make these writable by the web server:

```bash
chmod -R 775 uploads logs
```

**Then open `/admin/login.php` immediately** and create your administrator
account. Until you do, that form is open to anyone who finds it.

---

## 2. HTTPS

Install your SSL certificate, then uncomment two blocks in `.htaccess`:

```apache
# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# HSTS — only after HTTPS is confirmed working
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

Turn on HSTS **only once HTTPS definitely works** — browsers remember it for a
year and you cannot easily undo it.

---

## 3. Email — do not skip this

Without this, a customer books and **neither of you receives anything**. The
booking is saved, but it sits in the database unseen. This is the most common
way a site like this quietly loses business.

In `includes/config.php`:

```php
define('SMTP_ENABLED',  true);
define('SMTP_HOST',     'smtp.your-provider.com');
define('SMTP_PORT',     587);
define('SMTP_USER',     'info@primeluxuryridestoronto.ca');
define('SMTP_PASS',     'your-mailbox-password');
define('SMTP_SECURE',   'tls');
```

Most hosts give you these with your mailbox. If yours does not, a free
Brevo or Mailgun account works and improves deliverability.

**Test it:** make a real booking on the live site. You should receive two
emails (customer confirmation and operator notification). If nothing arrives,
check `logs/mail.log` — every message is written there whether or not it sends,
so nothing is ever lost.

---

## 4. Google Maps API key

This gives you address autocomplete and automatic distance, which makes the
booking form feel professional and prices trips accurately.

### Getting the key

1. Go to **https://console.cloud.google.com/**
2. Sign in with a Google account, and create a project — call it
   *Prime Luxury Rides*.
3. **Add billing.** Menu → *Billing* → link a card. Google gives a recurring
   monthly free allowance that comfortably covers a site of this size, but the
   APIs will not work at all without a billing account attached. You will
   almost certainly never be charged; set a budget alert at $10 if you want
   certainty.
4. Menu → **APIs & Services → Library**, and enable these three:
   - **Maps JavaScript API**
   - **Places API**
   - **Distance Matrix API**
5. Menu → **APIs & Services → Credentials** → *Create credentials* → **API key**.
   Copy it.
6. **Restrict the key before you use it** — an unrestricted key can be copied
   off your page and run up your bill:
   - *Application restrictions* → **HTTP referrers**, then add:
     ```
     https://primeluxuryridestoronto.ca/*
     https://www.primeluxuryridestoronto.ca/*
     ```
   - *API restrictions* → **Restrict key**, and tick only the three APIs above.

Then in `includes/config.php`:

```php
define('GOOGLE_MAPS_API_KEY', 'AIza...your-key');
```

**Test it:** open the booking form and start typing an address — suggestions
should drop down. Enter both pickup and drop-off and the distance fills in
automatically.

If the key is wrong or restricted incorrectly, the site logs a clear warning to
the browser console and hands the distance field back to the customer, so
booking still works.

---

## 5. Stripe — online payments

### Test mode first

1. Create a free account at **https://dashboard.stripe.com/register**.
   No business verification is needed for test mode.
2. Make sure the dashboard toggle says **Test mode** (top right).
3. Go to **Developers → API keys** and copy:
   - *Publishable key* — starts `pk_test_`
   - *Secret key* — click reveal, starts `sk_test_`

```php
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...');
define('STRIPE_SECRET_KEY',      'sk_test_...');
define('STRIPE_CURRENCY',        'cad');
```

**Test a payment** with Stripe's test card — no real money moves:

| | |
|---|---|
| Card number | `4242 4242 4242 4242` |
| Expiry | any future date, e.g. `12/34` |
| CVC | any 3 digits |
| Postal code | any, e.g. `M5V 3L9` |

Make a booking, click *Pay securely*, and use that card. You should return to
the confirmation page showing **"Payment received in full"**, and the booking
should read *paid* in the admin panel.

To test a decline, use `4000 0000 0000 0002`.

### Webhook (recommended)

The site confirms payment when the customer returns from Stripe, so it works
without a webhook. But if someone pays and closes the tab immediately, only a
webhook catches it.

1. **Developers → Webhooks → Add endpoint**
2. URL: `https://primeluxuryridestoronto.ca/api/stripe-webhook.php`
3. Events: `checkout.session.completed` and `checkout.session.expired`
4. Copy the **signing secret** (`whsec_...`):

```php
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
```

### Going live with real payments

Complete Stripe's business verification, switch the dashboard out of test mode,
and swap the keys for the `pk_live_` / `sk_live_` pair. Add a second webhook
endpoint for live mode — the signing secret is different.

**Deposits:** Admin → Settings → *Charge at booking*. `100` takes the full fare;
`25` takes a quarter as a deposit with the balance due on the day.

---

## 6. SMS (optional)

Texts the customer their chauffeur's name, car, plate and tracking link when you
assign a driver. Without it they still get the email version.

Create a **Twilio** account, buy a Canadian number, then:

```php
define('TWILIO_SID',   'AC...');
define('TWILIO_TOKEN', 'your-auth-token');
define('TWILIO_FROM',  '+1416...');
```

Messages are written to `logs/sms.log` either way, so you can always see what
would have been sent.

---

## 7. Search engines

Once live:

1. **Google Search Console** → add the property, verify ownership.
2. Submit `https://primeluxuryridestoronto.ca/sitemap.xml` — it is generated
   automatically and already includes every route landing page.
3. **Google Business Profile** — for a local service this drives more calls than
   the website does. Same name, address and phone as the site.

---

## Final checks

- [ ] `APP_ENV` is `'live'` and no PHP errors appear on any page
- [ ] HTTPS forced, padlock shows, HSTS on
- [ ] Test booking received by **both** customer and operator
- [ ] Address autocomplete works on the booking form
- [ ] Test card payment completes and shows as paid in admin
- [ ] Phone and WhatsApp buttons dial the right number
- [ ] All four vehicles have photographs
- [ ] Hero image uploaded
- [ ] Real testimonials replace the placeholders
- [ ] Privacy Policy and Terms reviewed by a lawyer
- [ ] Admin password is strong and stored safely
- [ ] Test bookings and test customers deleted from the database

See **CONTENT.md** for everything you need to supply, and **README.md** for how
the site is put together.
