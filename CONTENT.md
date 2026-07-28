# Content You Need to Supply

Everything on the site that is currently a placeholder, what to replace it
with, and exactly where to do it. Nothing here needs a developer — it is all
editable from the admin panel or by dropping a file on the server.

Work top to bottom. **Section 1 blocks launch. Section 2 is what makes it beautiful.**

---

## 1. Business details — the site is wrong until these are set

**Admin → Settings → Contact details**

| Field | Currently | What to supply |
|---|---|---|
| Phone number | `+1 (416) 000-0000` | Your real number. **Every "Call Now" button on the site dials this** — including the mobile bar and the floating button. Right now they dial nothing. |
| WhatsApp number | `14160000000` | Digits only with country code, e.g. `14165551234`. No `+`, spaces or dashes. Powers the green WhatsApp button. |
| Public email | `info@primeluxuryridestoronto.ca` | Correct if that mailbox exists. This is where booking and enquiry emails are sent, and what customers reply to. |
| Operating hours | `24 hours a day, 7 days a week` | Change only if you are not actually 24/7. |
| Service area line | Toronto • Mississauga • Brampton… | Add or remove cities as you like. |
| Airports served | YYZ • YTZ • YHM | Fine as-is unless you add more. |

**Admin → Settings → Social media** — Facebook, Instagram, X, LinkedIn.
Full URLs (`https://instagram.com/yourhandle`). **Leave blank and the icon is
hidden** — better an empty footer than a dead link.

---

## 2. Photography — this is what sells the service

The site is built to look good without photographs (designed gradients and gold
line art), but photographs are what turn a visitor into a booking. This is the
single highest-value thing on the list.

### 2.1 Vehicle photographs — **most important**

**Admin → Vehicles → Edit → Upload a photo**

One per vehicle. Four needed:

- Mercedes-Benz S580
- Cadillac Escalade ESV
- Chevrolet Suburban
- Mercedes-Maybach GLS 600

| | Specification |
|---|---|
| Size | 1600 × 1000 px or larger |
| Shape | **Landscape, roughly 16:10** — cropped to that automatically |
| Format | JPG or WebP (PNG and GIF accepted) |
| Max file size | 8 MB |

**What works:**
- Three-quarter front angle — the classic car-advert shot
- Shot at dusk or golden hour; dark backgrounds suit the black-and-gold theme
- Clean, uncluttered background — a hotel forecourt, an empty parking level, a bridge at night
- The actual car you will send. A stock Escalade that is not your Escalade will be noticed.

**What to avoid:**
- Bright midday sun with harsh reflections
- Other cars, bins, signage or people in the frame
- Portrait orientation — it will be cropped badly
- Dealer stock photos with watermarks

Until a photo is uploaded, that vehicle shows a gold line drawing and the words
*"Photo coming soon"* on the home page, fleet page and booking form.

### 2.2 Home page hero — **most visible**

**Admin → Site Images → Home page hero**

| | Specification |
|---|---|
| Size | 2400 × 1400 px or larger |
| Shape | Landscape, wide |
| Format | JPG or WebP |

The headline, search bar and buttons sit over the **left half**, so keep that
side visually quiet — sky, road, a plain wall. The site darkens the image
automatically so white text stays readable.

Best subject: a chauffeur in a suit beside an open rear door, or a vehicle
pulling up outside a hotel at dusk.

### 2.3 About page

**Admin → Site Images → About page**

1600 × 1200 px, landscape, shown in a 4:3 frame.

A photograph of **you or your chauffeurs** works far better here than another
car — this is the page where people decide whether to trust you.

### 2.4 Logo

Replace `assets/img/logo.png` on the server. Updates the header, footer,
favicon and email templates together. Keep it wide with transparency, at least
480 px across.

---

## 3. Words you may want to change

**Admin → Settings → Website text**

| Setting | Currently |
|---|---|
| Home hero headline | "Luxury Chauffeur Services in Toronto" |
| Home hero sub-headline | "Premium airport transfers, corporate travel, events & more." |
| Mission statement | Shown on the About page |
| Elite tier description | Shown on the Membership page |
| VIP tier description | Shown on the Membership page |
| Default meta description | Used by Google when a page has no specific description |

Everything else — service descriptions, vehicle descriptions, FAQs — is editable
per item under **Vehicles**, or is written into the page templates.

### Testimonials

The three reviews on the home page are **placeholders** with invented names
(Michael A., Sarah O., Daniel K.). Replace them with real ones before launch —
inventing customer reviews is illegal under Canadian consumer protection law.
They live in `index.php`; send me real ones and I will swap them in, or ask
customers for a line each after their ride.

---

## 4. Pricing — check before you launch

**Admin → Settings → Pricing rules**

Already loaded from your rate sheets. Confirm they are still current:

| Setting | Current |
|---|---|
| HST rate | 13% |
| Flat-rate threshold | 40 km |
| Elite discount | 30% |
| VIP discount | 40% |
| Return-trip discount | 10% |
| Fee per extra stop | $15 |
| Maximum extra stops | 3 |
| Charge at booking | 100% (full amount) |

Set *Charge at booking* to e.g. `25` to take a quarter as a deposit instead.

**Admin → Flat Rates** — per-vehicle city pricing, exactly as your sheets.
A blank price means "use dynamic pricing" for that city.

**Note on the Chevrolet Suburban:** your rate sheets covered the S-Class,
Escalade and Maybach only. I priced the Suburban in the same tier as the
Escalade (both six-passenger SUVs). **Please confirm or correct this** under
Admin → Flat Rates → Chevrolet Suburban.

---

## 5. Chauffeurs and vehicle plates

**Admin → Chauffeurs** — add each driver with their name and **mobile number**.
When you assign one to a booking, the customer is emailed (and texted, if SMS is
configured) their chauffeur's name, the vehicle, its plate and a tracking link.

**Admin → Vehicles → Edit → Number plate** — so customers can identify the car
that pulls up. Currently blank on all four.

---

## 6. Legal

`privacy.php` and `terms.php` are **drafts** written to match the pricing and
booking rules actually configured on the site. They are a solid starting point,
not legal advice.

Have both reviewed by an Ontario lawyer before launch — particularly the
cancellation terms, liability limits and the self-drive rental clauses, which
must match your insurance policy.

---

## Quick reference — who edits what

| Content | Where |
|---|---|
| Phone, WhatsApp, email, hours, social | Admin → Settings |
| Prices, discounts, HST, deposit | Admin → Settings → Pricing |
| City flat rates | Admin → Flat Rates |
| Vehicles, photos, plates, features | Admin → Vehicles |
| Hero and About photographs | Admin → Site Images |
| Chauffeur roster | Admin → Chauffeurs |
| Membership tiers granted | Admin → Customers, or Enquiries → Membership |
| Headlines, mission, tier blurbs | Admin → Settings → Website text |
| Testimonials | `index.php` (ask me) |
| Logo | `assets/img/logo.png` on the server |
| Privacy / Terms | `privacy.php`, `terms.php` (have a lawyer review) |
