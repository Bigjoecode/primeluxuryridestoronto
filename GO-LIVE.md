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

Your hosting is GoDaddy/cPanel at `198.12.234.178`, and
`mail.primeluxuryridestoronto.ca` already resolves there. Create the
`info@` mailbox in cPanel → *Email Accounts*, then in `includes/config.php`:

```php
define('SMTP_ENABLED',  true);
define('SMTP_HOST',     'mail.primeluxuryridestoronto.ca');
define('SMTP_PORT',     587);
define('SMTP_USER',     'info@primeluxuryridestoronto.ca');
define('SMTP_PASS',     'the-mailbox-password');
define('SMTP_SECURE',   'tls');
```

**These settings are confirmed working.** Tested against the live mailbox on
28 July 2026: TLS negotiated, authentication accepted, sender approved, and a
real booking delivered both the customer confirmation and the operator
notification. Copy the same values into the config on the production server.

If port 587 is refused, try `465` with `SMTP_SECURE` set to `'ssl'`. Some
GoDaddy shared plans only accept `localhost` as the host when sending from
the same server — if both ports fail, set `SMTP_HOST` to `'localhost'`.

### If mail is accepted but never arrives

Two settings decide this, and both must agree. The site can authenticate and
get a `250 OK` while nothing reaches the inbox, because acceptance is not
delivery.

1. **MX record** — must point at `mail.primeluxuryridestoronto.ca`.
2. **cPanel → Email → Email Routing** — select the domain and choose
   **Local Mail Exchanger**, then *Change Routing*.

If routing is left on *Remote Mail Exchanger* while MX points at this same
server, mail is handed to the "remote" host — which is itself — so it loops
and is discarded. The symptom is an empty inbox at 0% usage despite every send
reporting success.

**Admin → Settings → Email delivery test** detects this: it offers the server a
mailbox that cannot exist. A server delivering locally rejects it with 550; one
routing remotely accepts anything, and the test says so.

### ⚠ Historical note: the MX record previously pointed at Microsoft 365

`primeluxuryridestoronto.ca` currently has one MX record:
`primeluxuryridestoronto-ca.mail.protection.outlook.com`.

**Sending** through cPanel will work regardless — SMTP authentication does not
depend on MX. But **incoming** mail to `info@` will be delivered to Microsoft
365, not to the cPanel mailbox. You would send from cPanel and never see the
replies, because they would be sitting in Outlook.

Pick one and be consistent:

- **Using cPanel mail:** change the MX record to `mail.primeluxuryridestoronto.ca`
  (priority 0) and remove the Outlook one. Then create `info@` in cPanel.
- **Staying on Microsoft 365:** leave MX alone and use `smtp.office365.com:587`
  instead, having first enabled *Authenticated SMTP* for the mailbox in the
  Microsoft 365 admin centre (Users → Active users → the user → Mail →
  Manage email apps). If the account uses MFA you will need an app password.

Do not create the mailbox in both places — mail will go to whichever the MX
record names, and the other will stay silently empty.

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
