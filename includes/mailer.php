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
            /*
             * Shared hosts throttle rapid SMTP connections — GoDaddy will
             * accept one message and then stall the next handshake for
             * ~40s before dropping it. A single booking triggers two
             * emails back to back, which is exactly the pattern that
             * trips it, so retry briefly rather than losing the second.
             */
            $attempts = 3;
            for ($i = 1; $i <= $attempts; $i++) {
                $sent = smtp_send($to, $encoded_subject, $body, $headers);
                if ($sent) {
                    break;
                }
                if ($i < $attempts) {
                    app_log('mail.log', sprintf('Attempt %d/%d failed; retrying in %ds.',
                            $i, $attempts, $i * 5));
                    sleep($i * 5);          // 5s, then 10s
                }
            }
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
 * Step-by-step SMTP diagnostic.
 *
 * Exists because "email didn't arrive" is the single hardest thing to debug
 * on a live site, and the usual failure — a provider refusing basic
 * authentication — looks identical to a wrong password unless you read the
 * actual server reply. This walks the handshake and reports each stage with
 * the server's own response.
 *
 * @return array{steps:array<int,array{label:string,ok:bool,detail:string}>, ok:bool, hint:?string}
 */
function smtp_diagnose(): array
{
    $steps = [];
    $hint  = null;
    $add   = function (string $label, bool $ok, string $detail = '') use (&$steps) {
        $steps[] = ['label' => $label, 'ok' => $ok, 'detail' => trim($detail)];
    };

    if (!SMTP_ENABLED) {
        $add('SMTP enabled', false, 'SMTP_ENABLED is false — the site is using PHP mail().');
        return ['steps' => $steps, 'ok' => false,
                'hint'  => 'Set SMTP_ENABLED to true in includes/config.php.'];
    }
    $add('SMTP enabled', true, SMTP_HOST . ':' . SMTP_PORT . ' (' . SMTP_SECURE . ')');

    // Catch this before the handshake — an empty password produces a
    // generic "authentication failed" that looks like a wrong password.
    if (SMTP_USER !== '' && SMTP_PASS === '') {
        $add('Credentials present', false, 'SMTP_USER is set but SMTP_PASS is empty.');
        return ['steps' => $steps, 'ok' => false,
                'hint'  => 'Add the mailbox password for ' . SMTP_USER
                         . ' to SMTP_PASS in includes/config.php.'];
    }
    if (SMTP_USER !== '') {
        $add('Credentials present', true, SMTP_USER);
    }

    // 1. DNS
    $ip = @gethostbyname(SMTP_HOST);
    if ($ip === SMTP_HOST) {
        $add('Resolve host', false, 'DNS lookup for ' . SMTP_HOST . ' failed.');
        return ['steps' => $steps, 'ok' => false,
                'hint'  => 'Check SMTP_HOST spelling, and that this server can resolve DNS.'];
    }
    $add('Resolve host', true, SMTP_HOST . ' → ' . $ip);

    // 2. TCP
    $host = SMTP_SECURE === 'ssl' ? 'ssl://' . SMTP_HOST : SMTP_HOST;
    $ctx  = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $sock = @stream_socket_client("$host:" . SMTP_PORT, $errno, $errstr, 15,
                                  STREAM_CLIENT_CONNECT, $ctx);
    if (!$sock) {
        $add('Connect', false, "$errstr (errno $errno)");
        return ['steps' => $steps, 'ok' => false,
                'hint'  => 'Port ' . SMTP_PORT . ' is not reachable from this server. Many '
                         . 'networks block outbound SMTP — ask your host to open it, or try '
                         . 'port 465 with SMTP_SECURE set to "ssl".'];
    }
    $add('Connect', true, 'TCP connection established');
    stream_set_timeout($sock, 15);

    $read = function () use ($sock): string {
        $data = '';
        while (($line = fgets($sock, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return trim($data);
    };
    $say = function (string $cmd) use ($sock, $read): string {
        fwrite($sock, $cmd . "\r\n");
        return $read();
    };

    $greeting = $read();
    $add('Server greeting', strncmp($greeting, '220', 3) === 0, $greeting);

    $ehlo = 'EHLO ' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost');
    $caps = $say($ehlo);
    $add('EHLO', strncmp($caps, '250', 3) === 0, $caps);

    // 3. TLS
    if (SMTP_SECURE === 'tls') {
        $r = $say('STARTTLS');
        if (strncmp($r, '220', 3) !== 0) {
            $add('STARTTLS', false, $r);
            fclose($sock);
            return ['steps' => $steps, 'ok' => false,
                    'hint'  => 'The server refused STARTTLS. Try port 465 with SMTP_SECURE = "ssl".'];
        }
        if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $add('STARTTLS', false, 'TLS handshake failed.');
            fclose($sock);
            return ['steps' => $steps, 'ok' => false,
                    'hint'  => 'TLS negotiation failed — usually an out-of-date CA bundle on the server.'];
        }
        $add('STARTTLS', true, 'Encrypted');
        $caps = $say($ehlo);
        $add('EHLO (encrypted)', strncmp($caps, '250', 3) === 0, $caps);
    }

    // 4. Auth
    if (SMTP_USER !== '') {
        if (stripos($caps, 'AUTH') === false) {
            $add('AUTH offered', false, 'The server did not advertise AUTH after TLS.');
            fclose($sock);
            return ['steps' => $steps, 'ok' => false,
                    'hint'  => 'This server is not accepting authenticated submission on this port.'];
        }
        $add('AUTH offered', true, 'Server advertises authentication');

        $r = $say('AUTH LOGIN');
        if (strncmp($r, '334', 3) !== 0) {
            $add('AUTH LOGIN', false, $r);
            fclose($sock);
            return ['steps' => $steps, 'ok' => false, 'hint' => smtp_auth_hint($r)];
        }
        $say(base64_encode(SMTP_USER));
        $r = $say(base64_encode(SMTP_PASS));

        if (strncmp($r, '235', 3) !== 0) {
            $add('Authenticate', false, $r);
            fclose($sock);
            return ['steps' => $steps, 'ok' => false, 'hint' => smtp_auth_hint($r)];
        }
        $add('Authenticate', true, 'Signed in as ' . SMTP_USER);
    }

    // 5. Envelope — proves the From address is permitted, without sending.
    $r = $say('MAIL FROM:<' . MAIL_FROM . '>');
    $ok_from = strncmp($r, '250', 3) === 0;
    $add('Sender accepted', $ok_from, $r);
    if (!$ok_from) {
        $hint = 'The server rejected ' . MAIL_FROM . ' as a sender. It usually must match '
              . 'the mailbox you authenticated with (' . SMTP_USER . ').';
    }

    /*
     * Is this domain actually delivered here, or relayed onward?
     *
     * Offer the server an address that cannot exist. A host delivering
     * the domain locally knows its own mailboxes and answers 550. One
     * that is configured to hand the domain to a remote mail exchanger
     * accepts anything, because it is not the final destination — and
     * that is why mail can be accepted with 250 and then never appear
     * in the local inbox. Only ever RCPT here; no DATA, so no mail is
     * created either way.
     */
    $domain_local = substr(strrchr(ADMIN_EMAIL, '@') ?: '', 1);
    if ($ok_from && $domain_local !== '') {
        $canary = 'plr-no-such-mailbox-' . bin2hex(random_bytes(4)) . '@' . $domain_local;
        $rc = $say('RCPT TO:<' . $canary . '>');
        $say('RSET');

        $rejects_unknown = (strncmp($rc, '550', 3) === 0 || strncmp($rc, '551', 3) === 0
                         || strncmp($rc, '553', 3) === 0);

        $add('Delivers locally', $rejects_unknown,
             $rejects_unknown
                ? 'Unknown mailboxes are rejected, as they should be'
                : 'Server accepted a non-existent mailbox (' . trim($rc) . ')');

        if (!$rejects_unknown) {
            $hint = 'Mail is being sent and accepted, but this server is not delivering '
                  . $domain_local . ' to its own mailboxes — it accepts any address and '
                  . 'forwards it on, so nothing reaches the cPanel inbox. Fix it in cPanel: '
                  . 'Email → Email Routing → select ' . $domain_local . ' → choose '
                  . '"Local Mail Exchanger" → Change Routing. With the MX record already '
                  . 'pointing here, remote routing also makes the server mail itself, so '
                  . 'messages are looped and discarded.';
        }
    }

    $say('QUIT');
    fclose($sock);

    /*
     * Where will mail to ADMIN_EMAIL actually land?
     *
     * A successful handshake only proves the server queued the message. If
     * the recipient domain's MX points somewhere other than the host we
     * just authenticated with, the mail is relayed on to that provider
     * instead of being delivered to the local mailbox — so it is accepted
     * with 250 and then quietly arrives somewhere nobody is watching.
     * This is the single most confusing mail failure there is, so name it.
     */
    $domain = substr(strrchr(ADMIN_EMAIL, '@') ?: '', 1);
    if ($domain !== '') {
        // getmxrr() is unreliable on Windows, so prefer dns_get_record()
        // and fall back only if it is unavailable.
        $mx = [];
        $recs = function_exists('dns_get_record') ? @dns_get_record($domain, DNS_MX) : false;
        if (is_array($recs) && $recs) {
            usort($recs, fn($a, $b) => ($a['pri'] ?? 0) <=> ($b['pri'] ?? 0));
            foreach ($recs as $rec) {
                if (!empty($rec['target'])) { $mx[] = $rec['target']; }
            }
        } elseif (function_exists('getmxrr')) {
            @getmxrr($domain, $mx);
        }

        if ($mx) {
            $smtp_host = strtolower(SMTP_HOST);
            $local = false;
            foreach ($mx as $host) {
                $host = strtolower(rtrim($host, '.'));
                if ($host === $smtp_host
                    || str_ends_with($smtp_host, $host)
                    || str_ends_with($host, $domain)) {
                    $local = true;
                    break;
                }
            }
            $first = strtolower(rtrim($mx[0], '.'));
            $add('Inbound routing (MX)', $local, $domain . ' → ' . $first);

            if (!$local) {
                $hint = 'Mail is being SENT correctly, but ' . $domain . ' has its MX record '
                      . 'pointing at "' . $first . '", not at ' . SMTP_HOST . '. Your mail '
                      . 'server accepts the message and then forwards it there, so it never '
                      . 'reaches the mailbox on this host. Fix it in two places: in cPanel '
                      . 'set Email Routing for this domain to "Local Mail Exchanger", and in '
                      . 'DNS point the MX record at ' . SMTP_HOST . ' (priority 0), removing '
                      . 'the old one.';
            }
        }
    }

    $all_ok = $ok_from;
    foreach ($steps as $s) {
        if (!$s['ok']) { $all_ok = false; }
    }

    return ['steps' => $steps, 'ok' => $all_ok, 'hint' => $hint];
}

/** Turn a rejected-auth reply into something actionable. */
function smtp_auth_hint(string $reply): string
{
    $r = strtolower($reply);

    if (str_contains($r, 'basic authentication is disabled')
        || str_contains($r, 'smtpclientauthentication')
        || str_contains($r, 'authentication unsuccessful') && str_contains($r, 'outlook')) {
        return 'Microsoft 365 has SMTP AUTH disabled for this mailbox. In the Microsoft 365 '
             . 'admin centre open Users → Active users → this user → Mail → Manage email apps, '
             . 'and tick "Authenticated SMTP". If the account uses MFA you must also create an '
             . 'app password and use that here instead of the normal one.';
    }
    if (str_contains($r, '535') || str_contains($r, 'authentication failed')
        || str_contains($r, 'invalid credentials')) {
        return 'The mailbox rejected these credentials. Check the username is the full email '
             . 'address and the password is current. If the account has MFA enabled, a normal '
             . 'password will always be refused — you need an app password.';
    }
    if (str_contains($r, '5.7.57') || str_contains($r, 'must issue a starttls')) {
        return 'The server requires encryption before authentication. Set SMTP_SECURE to "tls" '
             . 'with port 587, or "ssl" with port 465.';
    }
    return 'The mail server refused authentication. Its exact reply is shown above.';
}

/**
 * Minimal SMTP client (AUTH LOGIN + STARTTLS/SSL). No external dependency.
 */
function smtp_send(string $to, string $subject, string $body, array $headers): bool
{
    /*
     * The authenticated connection is held open for the life of the
     * request and reused.
     *
     * A single booking sends two emails back to back. Opening a second
     * connection immediately is exactly what shared hosts throttle —
     * GoDaddy stalls the handshake for ~40s and then drops it. Sending
     * both messages down one already-authenticated connection avoids the
     * throttle entirely, and is what SMTP is designed for anyway.
     */
    static $socket = null;

    $ehlo = 'EHLO ' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost');

    $read = function () use (&$socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (string $c, string $expect) use (&$socket, $read): bool {
        fwrite($socket, $c . "\r\n");
        $r = $read();
        if (strncmp($r, $expect, strlen($expect)) !== 0) {
            app_log('mail.log', "SMTP: `$c` → " . trim($r));
            return false;
        }
        return true;
    };
    $drop = function () use (&$socket): void {
        if (is_resource($socket)) { @fclose($socket); }
        $socket = null;
    };

    // Reuse an open connection, but prove it is still alive first — the
    // server may have timed it out since the previous message.
    if (is_resource($socket)) {
        if (feof($socket) || !$cmd('RSET', '250')) {
            $drop();
        }
    }

    if (!is_resource($socket)) {
        $host = SMTP_SECURE === 'ssl' ? 'ssl://' . SMTP_HOST : SMTP_HOST;
        $ctx  = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $socket = @stream_socket_client("$host:" . SMTP_PORT, $errno, $errstr, 20,
                                        STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) {
            app_log('mail.log', "SMTP connect failed: $errstr ($errno)");
            $socket = null;
            return false;
        }
        stream_set_timeout($socket, 20);

        $read(); // greeting
        if (!$cmd($ehlo, '250')) { $drop(); return false; }

        if (SMTP_SECURE === 'tls') {
            if (!$cmd('STARTTLS', '220')) { $drop(); return false; }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                app_log('mail.log', 'SMTP: TLS handshake failed');
                $drop(); return false;
            }
            if (!$cmd($ehlo, '250')) { $drop(); return false; }
        }

        if (SMTP_USER !== '') {
            if (!$cmd('AUTH LOGIN', '334'))             { $drop(); return false; }
            if (!$cmd(base64_encode(SMTP_USER), '334')) { $drop(); return false; }
            if (!$cmd(base64_encode(SMTP_PASS), '235')) { $drop(); return false; }
        }
    }

    if (!$cmd('MAIL FROM:<' . MAIL_FROM . '>', '250')) { $drop(); return false; }
    if (!$cmd('RCPT TO:<' . $to . '>', '250'))         { $drop(); return false; }
    if (!$cmd('DATA', '354'))                          { $drop(); return false; }

    // Dot-stuffing per RFC 5321.
    $payload = implode("\r\n", $headers) . "\r\n"
             . 'Subject: ' . $subject . "\r\n"
             . 'To: ' . $to . "\r\n"
             . 'Date: ' . date('r') . "\r\n\r\n"
             . preg_replace('/^\./m', '..', $body);

    fwrite($socket, $payload . "\r\n.\r\n");
    $ok = strncmp($read(), '250', 3) === 0;

    if (!$ok) {
        $drop();                    // connection state is unknown after a refusal
        return false;
    }

    // Deliberately no QUIT — the connection stays open for the next
    // message. PHP closes it when the request ends.
    return true;
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

/**
 * "Your chauffeur is confirmed" — sent when a driver is assigned.
 * This is the message customers judge the service on, so it leads with
 * the three things they actually need: who, what car, and when.
 */
function send_driver_assigned_email(array $b, array $driver, ?array $vehicle = null): bool
{
    require_once __DIR__ . '/sms.php';

    $first = explode(' ', trim((string)$b['full_name']))[0] ?: 'there';
    $plate = trim((string)($vehicle['plate'] ?? ''));

    $rows = email_row('Chauffeur', (string)$driver['full_name'])
          . email_row('Contact number', (string)$driver['phone'])
          . email_row('Vehicle', (string)$b['vehicle_name'] . ($plate !== '' ? ' — ' . $plate : ''))
          . email_row('Collecting you', fmt_datetime($b['pickup_at']))
          . email_row('From', (string)$b['pickup_address']);

    $inner = '<p style="margin:0 0 16px;">Hello ' . e($first) . ',</p>'
      . '<p style="margin:0 0 16px;">Good news &mdash; your chauffeur for booking '
      .   '<strong style="color:#d4af37;">' . e($b['reference']) . '</strong> is confirmed '
      .   'and your vehicle is reserved.</p>'
      . email_panel($rows, 'Your chauffeur')
      . '<p style="margin:22px 0 0;color:#b9b4ae;font-size:14px;">'
      .   'Your chauffeur will meet you at the pickup location, open the door for you and '
      .   'assist with your luggage. If your plans change, call us on '
      .   '<strong style="color:#d4af37;">' . e(setting('phone')) . '</strong> at any hour.</p>'
      . email_button(track_url($b), 'Track your ride')
      . '<p style="margin:14px 0 0;color:#8b867f;font-size:12px;">'
      .   'This link shows your ride status and your chauffeur&rsquo;s details. '
      .   'Please keep it private.</p>';

    return send_mail(
        (string)$b['email'],
        'Your chauffeur is confirmed — ' . $b['reference'] . ' | ' . SITE_NAME,
        email_shell('Your chauffeur is confirmed', $inner,
            $driver['full_name'] . ' · ' . fmt_datetime($b['pickup_at'])),
        setting('email', ADMIN_EMAIL)
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
