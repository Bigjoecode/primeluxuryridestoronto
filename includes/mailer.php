<?php
/**
 * Email delivery + branded HTML templates.
 *
 * Uses SMTP when SMTP_ENABLED is true, otherwise PHP mail().
 * On local XAMPP neither usually delivers, so every message is also
 * written to logs/mail.log — nothing is ever silently lost.
 */
require_once __DIR__ . '/functions.php';

/**
 * Send an HTML email. Returns true when the transport accepted it.
 */
function send_mail(string $to, string $subject, string $html, string $reply_to = ''): bool
{
    $boundary = 'plr_' . bin2hex(random_bytes(8));
    $text     = trim(html_entity_decode(strip_tags(
                    preg_replace('~<(br|/p|/tr|/h[1-6])[^>]*>~i', "\n", $html)
                ), ENT_QUOTES, 'UTF-8'));
    $text     = preg_replace("/\n{3,}/", "\n\n", $text);

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . sprintf('=?UTF-8?B?%s?= <%s>', base64_encode(MAIL_FROM_NAME), MAIL_FROM),
        'X-Mailer: PrimeLuxuryRides',
    ];
    if ($reply_to !== '' && filter_var($reply_to, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $reply_to;
    }

    $body = "--$boundary\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: 8bit\r\n\r\n"
          . $text . "\r\n\r\n"
          . "--$boundary\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: 8bit\r\n\r\n"
          . $html . "\r\n\r\n"
          . "--$boundary--";

    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    // Always keep a local copy so nothing is lost during setup/testing.
    app_log('mail.log', sprintf("TO: %s | SUBJECT: %s", $to, $subject));

    $sent = false;
    try {
        if (SMTP_ENABLED) {
            $sent = smtp_send($to, $encoded_subject, $body, $headers);
        } else {
            $sent = @mail($to, $encoded_subject, $body, implode("\r\n", $headers));
        }
    } catch (Throwable $ex) {
        app_log('mail.log', 'EXCEPTION: ' . $ex->getMessage());
        $sent = false;
    }

    if (!$sent) {
        // Persist the full message so it can be recovered / re-sent.
        app_log('mail.log', "NOT DELIVERED — full body follows:\n" . $html . "\n--- end ---");
    }
    return $sent;
}

/**
 * Minimal SMTP client (AUTH LOGIN + STARTTLS/SSL). No external dependency.
 */
function smtp_send(string $to, string $subject, string $body, array $headers): bool
{
    $host    = SMTP_SECURE === 'ssl' ? 'ssl://' . SMTP_HOST : SMTP_HOST;
    $ctx     = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $socket  = @stream_socket_client("$host:" . SMTP_PORT, $errno, $errstr, 20,
                                     STREAM_CLIENT_CONNECT, $ctx);
    if (!$socket) {
        app_log('mail.log', "SMTP connect failed: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($socket, 20);

    $read = function () use ($socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (string $c, string $expect) use ($socket, $read): bool {
        fwrite($socket, $c . "\r\n");
        $r = $read();
        if (strncmp($r, $expect, strlen($expect)) !== 0) {
            app_log('mail.log', "SMTP: `$c` → $r");
            return false;
        }
        return true;
    };

    $read(); // greeting
    $ehlo = 'EHLO ' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost');

    if (!$cmd($ehlo, '250')) { fclose($socket); return false; }

    if (SMTP_SECURE === 'tls') {
        if (!$cmd('STARTTLS', '220')) { fclose($socket); return false; }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            app_log('mail.log', 'SMTP: TLS handshake failed');
            fclose($socket); return false;
        }
        if (!$cmd($ehlo, '250')) { fclose($socket); return false; }
    }

    if (SMTP_USER !== '') {
        if (!$cmd('AUTH LOGIN', '334'))                    { fclose($socket); return false; }
        if (!$cmd(base64_encode(SMTP_USER), '334'))        { fclose($socket); return false; }
        if (!$cmd(base64_encode(SMTP_PASS), '235'))        { fclose($socket); return false; }
    }

    if (!$cmd('MAIL FROM:<' . MAIL_FROM . '>', '250')) { fclose($socket); return false; }
    if (!$cmd('RCPT TO:<' . $to . '>', '250'))         { fclose($socket); return false; }
    if (!$cmd('DATA', '354'))                          { fclose($socket); return false; }

    // Dot-stuffing per RFC 5321.
    $payload = implode("\r\n", $headers) . "\r\n"
             . 'Subject: ' . $subject . "\r\n"
             . 'To: ' . $to . "\r\n"
             . 'Date: ' . date('r') . "\r\n\r\n"
             . preg_replace('/^\./m', '..', $body);

    fwrite($socket, $payload . "\r\n.\r\n");
    $ok = strncmp($read(), '250', 3) === 0;

    $cmd('QUIT', '221');
    fclose($socket);
    return $ok;
}

// ── Templates ──────────────────────────────────────────────────────

/** Branded HTML shell. Table-based + inline CSS for email-client support. */
function email_shell(string $heading, string $inner, string $preheader = ''): string
{
    $site = rtrim(SITE_URL, '/');
    return '<!DOCTYPE html><html><head><meta charset="utf-8">'
      . '<meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<title>' . e($heading) . '</title></head>'
      . '<body style="margin:0;padding:0;background:#08070a;font-family:Helvetica,Arial,sans-serif;">'
      . ($preheader !== ''
          ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . e($preheader) . '</div>'
          : '')
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#08070a;padding:28px 12px;">'
      . '<tr><td align="center">'
      . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" '
      .   'style="max-width:600px;width:100%;background:#121115;border:1px solid rgba(212,175,55,.28);border-radius:14px;overflow:hidden;">'

      // Header
      . '<tr><td style="padding:30px 32px 22px;border-bottom:1px solid rgba(255,255,255,.09);text-align:center;">'
      .   '<img src="' . e($site) . '/assets/img/logo.png" alt="' . e(SITE_NAME) . '" '
      .     'width="220" style="max-width:220px;height:auto;display:inline-block;">'
      . '</td></tr>'

      // Heading
      . '<tr><td style="padding:32px 32px 6px;">'
      .   '<h1 style="margin:0;font-family:Georgia,serif;font-size:25px;line-height:1.25;'
      .     'color:#d4af37;font-weight:600;">' . e($heading) . '</h1>'
      . '</td></tr>'

      // Body
      . '<tr><td style="padding:14px 32px 32px;color:#e8e5e1;font-size:15px;line-height:1.65;">'
      .   $inner
      . '</td></tr>'

      // Footer
      . '<tr><td style="padding:22px 32px;background:#0d0c10;border-top:1px solid rgba(255,255,255,.09);'
      .   'text-align:center;color:#8b867f;font-size:12px;line-height:1.7;">'
      .   '<strong style="color:#d4af37;">' . e(setting('company_name', SITE_NAME)) . '</strong><br>'
      .   'Toronto &amp; the Greater Toronto Area<br>'
      .   '<a href="tel:' . e(preg_replace('/[^\d+]/', '', setting('phone'))) . '" style="color:#b9b4ae;text-decoration:none;">'
      .     e(setting('phone')) . '</a> &nbsp;&middot;&nbsp; '
      .   '<a href="mailto:' . e(setting('email', ADMIN_EMAIL)) . '" style="color:#b9b4ae;text-decoration:none;">'
      .     e(setting('email', ADMIN_EMAIL)) . '</a><br><br>'
      .   '<span style="color:#6b6760;">&copy; ' . date('Y') . ' ' . e(setting('company_name', SITE_NAME))
      .   '. All rights reserved.</span>'
      . '</td></tr>'

      . '</table></td></tr></table></body></html>';
}

/** A label/value row for the email tables. */
function email_row(string $label, string $value, bool $strong = false): string
{
    if (trim($value) === '' || $value === '—') {
        return '';
    }
    return '<tr>'
      . '<td style="padding:9px 0;color:#8b867f;font-size:13px;vertical-align:top;width:42%;">'
      .   e($label) . '</td>'
      . '<td style="padding:9px 0;color:' . ($strong ? '#d4af37' : '#f6f4f1') . ';font-size:'
      .   ($strong ? '17px' : '14px') . ';font-weight:' . ($strong ? '700' : '500')
      .   ';text-align:right;vertical-align:top;">' . e($value) . '</td>'
      . '</tr>';
}

/** Wrap rows in a bordered panel. */
function email_panel(string $rows, string $title = ''): string
{
    return ($title !== ''
        ? '<p style="margin:26px 0 8px;color:#d4af37;font-size:12px;letter-spacing:.14em;'
          . 'text-transform:uppercase;font-weight:700;">' . e($title) . '</p>'
        : '')
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
      .   'style="background:#191720;border:1px solid rgba(255,255,255,.09);border-radius:10px;'
      .   'padding:6px 18px;margin:6px 0 4px;">'
      . $rows . '</table>';
}

/** Gold CTA button. */
function email_button(string $href, string $label): string
{
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:26px 0 8px;">'
      . '<tr><td style="background:#d4af37;border-radius:999px;">'
      . '<a href="' . e($href) . '" style="display:inline-block;padding:14px 30px;color:#0a0a0a;'
      .   'font-size:14px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;'
      .   'text-decoration:none;">' . e($label) . '</a>'
      . '</td></tr></table>';
}

/**
 * Build the shared booking detail block used in both emails.
 */
function booking_detail_html(array $b): string
{
    $trip = email_row('Service',      service_label($b['service_type']))
          . email_row('Vehicle',      (string)$b['vehicle_name'])
          . email_row('Pickup',       (string)$b['pickup_address'])
          . email_row('Drop-off',     (string)($b['dropoff_address'] ?? ''))
          . email_row('Date & time',  fmt_datetime($b['pickup_at']))
          . email_row('Return',       $b['return_at'] ? fmt_datetime($b['return_at']) : '')
          . email_row('Duration',     $b['hours'] ? $b['hours'] . ' hours' : '')
          . email_row('Distance',     $b['distance_km'] ? number_format((float)$b['distance_km'], 1) . ' km' : '')
          . email_row('Flight no.',   (string)($b['flight_number'] ?? ''))
          . email_row('Passengers',   (string)$b['passengers'])
          . email_row('Luggage',      (string)$b['luggage']);

    $money = email_row('Subtotal', money((float)$b['subtotal']));
    if ((float)$b['discount'] > 0) {
        $money .= email_row(ucfirst($b['membership_tier']) . ' discount',
                            '− ' . money((float)$b['discount']));
    }
    $money .= email_row('HST', money((float)$b['hst']))
            . email_row('Total (CAD)', money((float)$b['total']), true);

    $out = email_panel($trip, 'Trip details') . email_panel($money, 'Price');

    if (!empty($b['notes'])) {
        $out .= '<p style="margin:24px 0 6px;color:#d4af37;font-size:12px;letter-spacing:.14em;'
              . 'text-transform:uppercase;font-weight:700;">Special requests</p>'
              . '<p style="margin:0;padding:14px 18px;background:#191720;border-radius:10px;'
              . 'border:1px solid rgba(255,255,255,.09);color:#e8e5e1;font-size:14px;">'
              . nl2br(e((string)$b['notes'])) . '</p>';
    }
    return $out;
}

/** Confirmation email to the customer. */
function send_booking_customer_email(array $b): bool
{
    $first = explode(' ', trim((string)$b['full_name']))[0] ?: 'there';

    $inner = '<p style="margin:0 0 16px;">Hello ' . e($first) . ',</p>'
      . '<p style="margin:0 0 16px;">Thank you for booking with '
      .   e(setting('company_name', SITE_NAME)) . '. We have received your request and '
      .   'a member of our team will confirm your chauffeur and vehicle shortly.</p>'
      . '<p style="margin:0 0 4px;">Your booking reference is '
      .   '<strong style="color:#d4af37;font-size:17px;">' . e($b['reference']) . '</strong>'
      .   ' — please quote it in any correspondence.</p>'
      . booking_detail_html($b)
      . '<p style="margin:26px 0 6px;color:#d4af37;font-size:12px;letter-spacing:.14em;'
      .   'text-transform:uppercase;font-weight:700;">Included as standard</p>'
      . '<p style="margin:0 0 10px;color:#b9b4ae;font-size:14px;"><strong style="color:#f6f4f1;">'
      .   'Meet &amp; Greet Service</strong><br>Your professional chauffeur will meet you at your '
      .   'pickup location, open the door for you, and assist with your luggage to ensure a '
      .   'seamless and comfortable experience.</p>'
      . '<p style="margin:0;color:#b9b4ae;font-size:14px;"><strong style="color:#f6f4f1;">'
      .   'Onboard Amenities</strong><br>All PRIME vehicles include complimentary bottled water, '
      .   'Wi-Fi connection, and reading material to enhance your journey.</p>'
      . email_button(rtrim(SITE_URL, '/') . '/contact.php', 'Contact Us')
      . '<p style="margin:18px 0 0;color:#8b867f;font-size:13px;">Need to change something? '
      .   'Reply to this email or call us on ' . e(setting('phone')) . ' — we are available '
      .   e(setting('hours', '24/7')) . '.</p>';

    return send_mail(
        (string)$b['email'],
        'Booking received — ' . $b['reference'] . ' | ' . SITE_NAME,
        email_shell('Your booking is received', $inner,
            'Reference ' . $b['reference'] . ' — ' . fmt_datetime($b['pickup_at'])),
        setting('email', ADMIN_EMAIL)
    );
}

/** Notification email to the operator. */
function send_booking_admin_email(array $b): bool
{
    $customer = email_row('Name',  (string)$b['full_name'])
              . email_row('Email', (string)$b['email'])
              . email_row('Phone', (string)$b['phone']);

    $inner = '<p style="margin:0 0 16px;">A new booking has been submitted through the website.</p>'
      . '<p style="margin:0 0 4px;">Reference '
      .   '<strong style="color:#d4af37;font-size:17px;">' . e($b['reference']) . '</strong></p>'
      . email_panel($customer, 'Customer')
      . booking_detail_html($b)
      . email_button(rtrim(SITE_URL, '/') . '/admin/bookings.php', 'Open Admin Panel');

    return send_mail(
        ADMIN_EMAIL,
        'NEW BOOKING ' . $b['reference'] . ' — ' . service_label($b['service_type'])
            . ' — ' . money((float)$b['total']),
        email_shell('New booking received', $inner,
            $b['full_name'] . ' — ' . fmt_datetime($b['pickup_at'])),
        (string)$b['email']
    );
}

/** Contact / quote enquiry notification. */
function send_enquiry_email(array $q): bool
{
    $rows = email_row('Name',    (string)$q['full_name'])
          . email_row('Email',   (string)$q['email'])
          . email_row('Phone',   (string)($q['phone'] ?? ''))
          . email_row('Subject', (string)($q['subject'] ?? ''))
          . email_row('Type',    ucfirst((string)$q['kind']));

    $inner = '<p style="margin:0 0 16px;">A new enquiry has been submitted through the website.</p>'
      . email_panel($rows, 'From')
      . '<p style="margin:26px 0 6px;color:#d4af37;font-size:12px;letter-spacing:.14em;'
      .   'text-transform:uppercase;font-weight:700;">Message</p>'
      . '<p style="margin:0;padding:16px 18px;background:#191720;border-radius:10px;'
      .   'border:1px solid rgba(255,255,255,.09);color:#e8e5e1;font-size:14px;">'
      .   nl2br(e((string)$q['message'])) . '</p>'
      . email_button('mailto:' . $q['email'], 'Reply to ' . explode(' ', (string)$q['full_name'])[0]);

    return send_mail(
        ADMIN_EMAIL,
        'Website enquiry — ' . ($q['subject'] ?: ucfirst((string)$q['kind'])) . ' — ' . $q['full_name'],
        email_shell('New website enquiry', $inner),
        (string)$q['email']
    );
}

/** Acknowledgement to whoever sent an enquiry. */
function send_enquiry_ack_email(array $q): bool
{
    $first = explode(' ', trim((string)$q['full_name']))[0] ?: 'there';

    $inner = '<p style="margin:0 0 16px;">Hello ' . e($first) . ',</p>'
      . '<p style="margin:0 0 16px;">Thank you for contacting '
      .   e(setting('company_name', SITE_NAME)) . '. We have received your message and will '
      .   'respond shortly — usually within a couple of hours.</p>'
      . '<p style="margin:0 0 6px;color:#d4af37;font-size:12px;letter-spacing:.14em;'
      .   'text-transform:uppercase;font-weight:700;">Your message</p>'
      . '<p style="margin:0;padding:16px 18px;background:#191720;border-radius:10px;'
      .   'border:1px solid rgba(255,255,255,.09);color:#b9b4ae;font-size:14px;">'
      .   nl2br(e((string)$q['message'])) . '</p>'
      . '<p style="margin:22px 0 0;">If your enquiry is urgent, please call us on '
      .   '<strong style="color:#d4af37;">' . e(setting('phone')) . '</strong> — we are '
      .   'available ' . e(setting('hours', '24/7')) . '.</p>'
      . email_button(rtrim(SITE_URL, '/') . '/booking.php', 'Book a Ride');

    return send_mail(
        (string)$q['email'],
        'We received your message | ' . SITE_NAME,
        email_shell('Thank you for getting in touch', $inner),
        setting('email', ADMIN_EMAIL)
    );
}
