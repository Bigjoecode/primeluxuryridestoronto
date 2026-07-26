<?php
/** Contact page + enquiry form. */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

$page_slug        = 'contact';
$page_title       = 'Contact Us';
$page_description = 'Contact Prime Luxury Rides Toronto — phone, email, WhatsApp and our enquiry form. Available 24 hours a day across Toronto and the GTA.';

$errors  = [];
$success = false;
$sent    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_check($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please send your message again.';
    } else {
        $name    = trim((string)($_POST['full_name'] ?? ''));
        $email   = trim((string)($_POST['email'] ?? ''));
        $phone   = trim((string)($_POST['phone'] ?? ''));
        $subject = trim((string)($_POST['subject'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        if ($name === '')                               { $errors[] = 'Please enter your name.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Please enter a valid email address.'; }
        if (mb_strlen($message) < 10)                   { $errors[] = 'Please tell us a little more (at least 10 characters).'; }
        if (trim((string)($_POST['website'] ?? '')) !== '') { $errors[] = 'Your message could not be sent.'; }

        if (!$errors) {
            $row = [
                'kind'      => 'contact',
                'full_name' => $name,
                'email'     => $email,
                'phone'     => $phone,
                'subject'   => $subject !== '' ? $subject : 'General enquiry',
                'message'   => $message,
            ];

            try {
                db_exec('INSERT INTO `enquiries`
                          (`kind`,`full_name`,`email`,`phone`,`subject`,`message`,`ip_address`)
                         VALUES (?,?,?,?,?,?,?)',
                    [$row['kind'], $name, $email, $phone !== '' ? $phone : null,
                     $row['subject'], $message, client_ip()]);
            } catch (Throwable $ex) {
                app_log('errors.log', 'enquiry insert failed: ' . $ex->getMessage());
            }

            try {
                send_enquiry_email($row);
                send_enquiry_ack_email($row);
            } catch (Throwable $ex) {
                app_log('errors.log', 'enquiry email failed: ' . $ex->getMessage());
            }

            $success = true;
        }
        $sent = $_POST;
    }
}

function old_c(string $key, string $default = ''): string
{
    global $sent, $success;
    if ($success) return $default;
    return (string)($sent[$key] ?? $default);
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="index.php">Home</a><?= icon('chevron-right') ?><span>Contact</span>
    </nav>
    <span class="eyebrow">Get In Touch</span>
    <h1 class="page-head__title">We&rsquo;re here <span class="gold-text">around the clock</span></h1>
    <p class="page-head__lead">Call, message or email us any time. For immediate bookings,
       phoning is always fastest.</p>
  </div>
</section>


<section class="section">
  <div class="container">
    <div class="split" style="align-items:start;">

      <!-- ══ CONTACT DETAILS ══════════════════════════════════════ -->
      <div class="reveal">
        <div class="grid" style="gap:var(--s-4);">

          <a class="card card--linked" href="<?= e(tel_url()) ?>">
            <div class="card__icon"><?= icon('phone') ?></div>
            <h2 class="card__title">Call us</h2>
            <p class="card__text" style="font-size:var(--fs-lg);color:var(--gold);">
              <?= e(setting('phone')) ?></p>
            <span class="card__link card__stretch"><span>Tap to call</span><?= icon('arrow-right') ?></span>
          </a>

          <a class="card card--linked" href="<?= e(whatsapp_url()) ?>"
             target="_blank" rel="noopener noreferrer">
            <div class="card__icon"><?= icon('whatsapp') ?></div>
            <h2 class="card__title">WhatsApp</h2>
            <p class="card__text">Message us directly for a fast reply, day or night.</p>
            <span class="card__link card__stretch"><span>Open WhatsApp</span><?= icon('arrow-right') ?></span>
          </a>

          <a class="card card--linked" href="mailto:<?= e(setting('email', ADMIN_EMAIL)) ?>">
            <div class="card__icon"><?= icon('mail') ?></div>
            <h2 class="card__title">Email us</h2>
            <p class="card__text" style="word-break:break-word;">
              <?= e(setting('email', ADMIN_EMAIL)) ?></p>
            <span class="card__link card__stretch"><span>Send an email</span><?= icon('arrow-right') ?></span>
          </a>

          <div class="card">
            <div class="card__icon"><?= icon('clock') ?></div>
            <h2 class="card__title">Operating hours</h2>
            <p class="card__text"><?= e(setting('hours', '24 hours a day, 7 days a week')) ?></p>
            <p class="card__text mt-4" style="font-size:var(--fs-sm);color:var(--text-dim);">
              Reservations, changes and cancellations are handled at any hour.</p>
          </div>

          <div class="card">
            <div class="card__icon"><?= icon('map-pin') ?></div>
            <h2 class="card__title">Service area</h2>
            <p class="card__text mb-4">Toronto and the Greater Toronto Area, plus long-distance
               transfers across Southern Ontario.</p>
            <div class="area-cloud">
              <span class="area-chip area-chip--gold">YYZ</span>
              <span class="area-chip area-chip--gold">YTZ</span>
              <span class="area-chip area-chip--gold">YHM</span>
            </div>
          </div>

        </div>
      </div>

      <!-- ══ FORM ═════════════════════════════════════════════════ -->
      <div class="reveal">
        <div class="wizard__main">
          <h2 class="wizard-step__title">Send us a message</h2>
          <p class="wizard-step__lead">We reply to most enquiries within a couple of hours.</p>

          <?php if ($success): ?>
          <div class="alert alert--success" role="status">
            <?= icon('check-circle') ?>
            <div>
              <strong>Thank you &mdash; your message has been sent.</strong><br>
              We&rsquo;ve emailed you a copy and will be in touch shortly. For anything urgent,
              please call <a href="<?= e(tel_url()) ?>"><?= e(setting('phone')) ?></a>.
            </div>
          </div>
          <?php endif; ?>

          <?php if ($errors): ?>
          <div class="alert alert--error" role="alert">
            <?= icon('alert') ?>
            <div>
              <strong>Please check the following:</strong>
              <ul style="margin-top:var(--s-2);display:grid;gap:var(--s-1);">
                <?php foreach ($errors as $err): ?>
                <li>&bull; <?= e($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
          <?php endif; ?>

          <form method="post" action="contact.php#contact-form" id="contact-form" novalidate>
            <?= csrf_field() ?>
            <div style="position:absolute;left:-9999px;" aria-hidden="true">
              <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="field">
              <label class="field__label" for="c_name">
                Your name <span class="req" aria-hidden="true">*</span>
              </label>
              <input class="input" type="text" id="c_name" name="full_name"
                     value="<?= e(old_c('full_name')) ?>" autocomplete="name" required>
            </div>

            <div class="field-row field-row--2">
              <div class="field">
                <label class="field__label" for="c_email">
                  Email <span class="req" aria-hidden="true">*</span>
                </label>
                <input class="input" type="email" id="c_email" name="email"
                       value="<?= e(old_c('email')) ?>" autocomplete="email"
                       inputmode="email" required>
              </div>
              <div class="field">
                <label class="field__label" for="c_phone">Phone</label>
                <input class="input" type="tel" id="c_phone" name="phone"
                       value="<?= e(old_c('phone')) ?>" autocomplete="tel" inputmode="tel">
              </div>
            </div>

            <div class="field">
              <label class="field__label" for="c_subject">Subject</label>
              <select class="select" id="c_subject" name="subject">
                <?php
                $subjects = ['General enquiry', 'Booking enquiry', 'Corporate account',
                             'Vehicle rental', 'Event transportation', 'Feedback', 'Something else'];
                $cur = old_c('subject', 'General enquiry');
                foreach ($subjects as $s): ?>
                <option value="<?= e($s) ?>" <?= $cur === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field">
              <label class="field__label" for="c_message">
                Message <span class="req" aria-hidden="true">*</span>
              </label>
              <textarea class="textarea" id="c_message" name="message"
                        placeholder="Tell us about your journey, dates and any specific requirements…"
                        required><?= e(old_c('message')) ?></textarea>
              <span class="field__hint">Include dates, locations and passenger numbers for the fastest quote.</span>
            </div>

            <button type="submit" class="btn btn--gold btn--block btn--lg">
              <?= icon('mail') ?><span>Send Message</span>
            </button>

            <p class="summary__note center mt-5">
              Prefer to book directly? <a href="booking.php" class="text-gold">Use our booking form</a>
              for an instant price.
            </p>
          </form>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ══ CTA ════════════════════════════════════════════════════════ -->
<section class="section section--alt">
  <div class="container">
    <div class="cta-banner reveal">
      <h2 class="cta-banner__title">Ready to <span class="gold-text">book?</span></h2>
      <p class="cta-banner__text">Get an instant quote in under two minutes &mdash;
         no phone call required.</p>
      <div class="btn-row btn-row--center">
        <a href="booking.php" class="btn btn--gold btn--lg"><?= icon('calendar') ?><span>Book a Ride</span></a>
        <a href="rates.php" class="btn btn--outline btn--lg"><span>View Flat Rates</span></a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
