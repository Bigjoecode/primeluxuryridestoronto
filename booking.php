<?php
/**
 * Multi-step booking / quote flow.
 * Steps: 1 Service → 2 Journey → 3 Vehicle → 4 Your details → 5 Review
 */
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/customer.php';

// Membership is read from the signed-in account, never from the form —
// otherwise anyone could select "VIP" and take 40% off.
$cust          = customer();
$cust_tier     = $cust ? (string)$cust['membership_tier'] : 'none';
$cust_discount = membership_discount($cust_tier);

$page_slug        = 'booking';
$page_title       = 'Book a Ride';
$page_description = 'Book a luxury chauffeur in Toronto in under two minutes. Instant price, transparent flat rates and confirmation by email.';
$page_scripts     = ['assets/js/booking.js'];

$vehicles  = get_vehicles();
$errors    = [];
$submitted = null;

// ── Pre-fill from the query string ─────────────────────────────────
// Used by the fleet/rates pages and by the hero quick-search widget.
$pre_vehicle = isset($_GET['vehicle']) ? get_vehicle_by_slug((string)$_GET['vehicle']) : null;
$pre_service = (string)($_GET['service'] ?? '');
$pre_from    = trim((string)($_GET['from'] ?? ''));
$pre_to      = trim((string)($_GET['to'] ?? ''));
$pre_pax     = (int)($_GET['pax'] ?? 0);
$pre_hours   = (int)($_GET['hours'] ?? 0);
$is_rental   = (($_GET['type'] ?? '') === 'rental');

if ($is_rental) {
    $pre_service = 'rental';
}
if (!in_array($pre_service, ['airport', 'city', 'city_to_city', 'hourly', 'rental'], true)) {
    $pre_service = '';
}

// Normalise an incoming date/time into the datetime-local format.
$pre_when = '';
$when_raw = trim((string)($_GET['when'] ?? ''));
if ($when_raw !== '') {
    try {
        $pre_when = (new DateTime($when_raw))->format('Y-m-d\TH:i');
    } catch (Throwable $ex) {
        $pre_when = '';
    }
}

/**
 * Which step should the wizard open on?
 * Anything the quick-search already answered is skipped, so the customer
 * lands on the first question still outstanding rather than re-typing.
 */
$start_step = 1;
if ($pre_service !== '') {
    $needs_dropoff = in_array($pre_service, ['airport', 'city', 'city_to_city'], true);
    $journey_done  = $pre_from !== ''
                  && $pre_when !== ''
                  && (!$needs_dropoff || $pre_to !== '');

    $start_step = $journey_done ? 3 : 2;      // vehicle, or journey
    if ($journey_done && $pre_vehicle) {
        $start_step = 4;                      // straight to customer details
    }
}

// ── Handle submission ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_check($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please review your details and submit again.';
    } else {
        $service    = (string)($_POST['service_type'] ?? '');
        $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
        $name       = trim((string)($_POST['full_name'] ?? ''));
        $email      = trim((string)($_POST['email'] ?? ''));
        $phone      = trim((string)($_POST['phone'] ?? ''));
        $pickup     = trim((string)($_POST['pickup_address'] ?? ''));
        $dropoff    = trim((string)($_POST['dropoff_address'] ?? ''));
        $pickup_at  = trim((string)($_POST['pickup_at'] ?? ''));
        $return_at  = trim((string)($_POST['return_at'] ?? ''));
        $hours      = (int)($_POST['hours'] ?? 0);
        $passengers = max(1, (int)($_POST['passengers'] ?? 1));
        $luggage    = max(0, (int)($_POST['luggage'] ?? 0));
        $flight     = trim((string)($_POST['flight_number'] ?? ''));
        $notes      = trim((string)($_POST['notes'] ?? ''));
        $membership = $cust_tier;                       // account-verified only
        $is_return  = !empty($_POST['is_return']);
        $stops_in   = array_values(array_filter(
            array_map('trim', (array)($_POST['stops'] ?? [])),
            fn($v) => $v !== ''
        ));
        $return_at_trip = trim((string)($_POST['return_at_trip'] ?? ''));
        $distance   = (float)($_POST['distance_km'] ?? 0);
        $duration   = (float)($_POST['duration_min'] ?? 0);

        // --- Validation -------------------------------------------
        $valid_services = ['airport', 'city', 'city_to_city', 'hourly', 'rental'];
        if (!in_array($service, $valid_services, true)) {
            $errors[] = 'Please choose a service type.';
        }
        if ($name === '')                                        { $errors[] = 'Please enter your full name.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))          { $errors[] = 'Please enter a valid email address.'; }
        if (preg_replace('/\D+/', '', $phone) === '')            { $errors[] = 'Please enter a contact phone number.'; }
        if ($pickup === '')                                      { $errors[] = 'Please enter a pickup address.'; }
        if ($service !== 'hourly' && $service !== 'rental' && $dropoff === '') {
            $errors[] = 'Please enter a drop-off address.';
        }
        if ($vehicle_id <= 0)                                    { $errors[] = 'Please select a vehicle.'; }

        // Pickup date/time
        $pickup_dt = null;
        if ($pickup_at === '') {
            $errors[] = 'Please choose a pickup date and time.';
        } else {
            try {
                $pickup_dt = new DateTime($pickup_at);
                $earliest  = (new DateTime())->modify('+' . MIN_LEAD_TIME_HOURS . ' hours');
                if ($pickup_dt < $earliest) {
                    $errors[] = 'Please choose a pickup time at least ' . MIN_LEAD_TIME_HOURS
                              . ' hours from now, or call us to arrange an immediate pickup.';
                }
            } catch (Throwable $ex) {
                $errors[] = 'That pickup date and time could not be read. Please re-enter it.';
            }
        }

        // Rental return date
        $return_dt = null;
        $days      = 0;
        if ($service === 'rental') {
            if ($return_at === '') {
                $errors[] = 'Please choose a return date for your rental.';
            } else {
                try {
                    $return_dt = new DateTime($return_at);
                    if ($pickup_dt && $return_dt <= $pickup_dt) {
                        $errors[] = 'The rental return date must be after the collection date.';
                    } elseif ($pickup_dt) {
                        $days = max(1, (int)ceil(
                            ($return_dt->getTimestamp() - $pickup_dt->getTimestamp()) / 86400));
                    }
                } catch (Throwable $ex) {
                    $errors[] = 'That return date could not be read. Please re-enter it.';
                }
            }
        }

        // Simple honeypot
        if (trim((string)($_POST['website'] ?? '')) !== '') {
            $errors[] = 'Your submission could not be processed.';
        }

        // --- Price + persist --------------------------------------
        if (!$errors) {
            $quote = calculate_quote([
                'service_type' => $service,
                'vehicle_id'   => $vehicle_id,
                'distance_km'  => $distance,
                'duration_min' => $duration,
                'hours'        => $hours,
                'days'         => $days,
                'pickup'       => $pickup,
                'dropoff'      => $dropoff,
                'membership'   => $membership,
                'is_return'    => $is_return,
                'stops'        => count($stops_in),
            ]);

            if (!$quote['ok']) {
                $errors[] = $quote['error'];
            } else {
                $vehicle = $quote['vehicle'];
                $ref     = next_booking_reference();

                // Return leg date, when a return trip was requested.
                $ret_trip_dt = null;
                if (($quote['is_return'] ?? false) && $return_at_trip !== '') {
                    try {
                        $candidate = new DateTime($return_at_trip);
                        if ($pickup_dt && $candidate > $pickup_dt) {
                            $ret_trip_dt = $candidate;
                        }
                    } catch (Throwable $ex) {
                        $ret_trip_dt = null;
                    }
                }

                try {
                    db_exec(
                        'INSERT INTO `bookings`
                          (`reference`,`customer_id`,`booking_type`,`service_type`,`is_return`,
                           `full_name`,`email`,`phone`,
                           `pickup_address`,`dropoff_address`,`stops`,`pickup_at`,`return_at`,
                           `return_at_trip`,`hours`,
                           `flight_number`,`distance_km`,`duration_min`,`vehicle_id`,`vehicle_name`,
                           `passengers`,`luggage`,`notes`,`pricing_method`,`subtotal`,
                           `membership_tier`,`discount`,`hst`,`total`,`price_breakdown`,
                           `track_token`,`ip_address`)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                        [
                            $ref,
                            $cust ? (int)$cust['id'] : null,
                            $service === 'rental' ? 'rental' : 'ride',
                            $service,
                            ($quote['is_return'] ?? false) ? 1 : 0,
                            $name, $email, $phone,
                            $pickup,
                            $dropoff !== '' ? $dropoff : null,
                            $stops_in ? json_encode($stops_in, JSON_UNESCAPED_UNICODE) : null,
                            $pickup_dt->format('Y-m-d H:i:s'),
                            $return_dt ? $return_dt->format('Y-m-d H:i:s') : null,
                            $ret_trip_dt ? $ret_trip_dt->format('Y-m-d H:i:s') : null,
                            ($quote['hours'] ?? 0) ?: null,
                            $flight !== '' ? $flight : null,
                            $distance > 0 ? $distance : null,
                            $duration > 0 ? $duration : null,
                            (int)$vehicle['id'],
                            $vehicle['name'],
                            $passengers, $luggage,
                            $notes !== '' ? $notes : null,
                            $quote['method'],
                            $quote['subtotal'],
                            $quote['membership'],
                            $quote['discount'],
                            $quote['hst'],
                            $quote['total'],
                            quote_snapshot($quote),
                            bin2hex(random_bytes(8)),      // tracking token
                            client_ip(),
                        ]
                    );

                    $booking = db_one('SELECT * FROM `bookings` WHERE `reference` = ? LIMIT 1', [$ref]);

                    // Emails are best-effort: a delivery failure must not
                    // lose the booking, which is already saved.
                    try {
                        send_booking_customer_email($booking);
                        send_booking_admin_email($booking);
                    } catch (Throwable $ex) {
                        app_log('errors.log', 'booking email failed: ' . $ex->getMessage());
                    }

                    header('Location: confirmation.php?ref=' . urlencode($ref));
                    exit;

                } catch (Throwable $ex) {
                    app_log('errors.log', 'booking insert failed: ' . $ex->getMessage());
                    $errors[] = 'We could not save your booking. Please call us on '
                              . setting('phone') . ' and we will take the details directly.';
                }
            }
        }
    }
    $submitted = $_POST;
}

/** Repopulate a field after a validation failure. */
function old(string $key, $default = '')
{
    global $submitted;
    return $submitted[$key] ?? $default;
}

// Vehicle data for the client-side wizard
$vehicle_js = array_map(fn($v) => [
    'id'         => (int)$v['id'],
    'slug'       => $v['slug'],
    'name'       => $v['name'],
    'class'      => $v['class_label'],
    'passengers' => (int)$v['passengers'],
    'luggage'    => (int)$v['luggage'],
    'hourly'     => (float)$v['hourly_rate'],
    'minHours'   => (int)$v['min_hours'],
    'rental'     => (int)$v['rental_available'] === 1,
    'allows'     => [
        'airport'      => (int)$v['allow_airport'] === 1,
        'city'         => (int)$v['allow_city'] === 1,
        'city_to_city' => (int)$v['allow_city_to_city'] === 1,
        'hourly'       => (int)$v['allow_hourly'] === 1,
        'rental'       => (int)$v['rental_available'] === 1,
    ],
], $vehicles);

$min_pickup = (new DateTime())->modify('+' . MIN_LEAD_TIME_HOURS . ' hours')->format('Y-m-d\TH:i');

require __DIR__ . '/includes/header.php';
?>

<section class="page-head" style="padding-bottom:var(--s-7);">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="index.php">Home</a><?= icon('chevron-right') ?><span>Book a Ride</span>
    </nav>
    <span class="eyebrow">Reservations</span>
    <h1 class="page-head__title">Book your <span class="gold-text">chauffeur</span></h1>
    <p class="page-head__lead">Five short steps. You&rsquo;ll see your full price &mdash;
       including HST &mdash; before you confirm anything.</p>
  </div>
</section>


<section class="section" style="padding-top:var(--s-7);">
  <div class="container">

    <?php if ($errors): ?>
    <div class="alert alert--error" role="alert" tabindex="-1" id="formErrors">
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

    <?php if (!maps_enabled()): ?>
    <div class="alert alert--info">
      <?= icon('info') ?>
      <span>Enter your addresses below and we&rsquo;ll confirm the exact distance when we
        review your booking. For an instant distance-based price, you can optionally enter
        the approximate trip distance.</span>
    </div>
    <?php endif; ?>

    <form method="post" action="booking.php" id="bookingForm" novalidate>
      <?= csrf_field() ?>
      <!-- honeypot -->
      <div style="position:absolute;left:-9999px;" aria-hidden="true">
        <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <div class="wizard">
        <div class="wizard__main">

          <!-- ══ PROGRESS ══════════════════════════════════════════ -->
          <div class="progress">
            <div class="progress__track">
              <div class="progress__bar" id="progressBar" style="width:20%"></div>
            </div>
            <div class="progress__steps" role="list">
              <?php
              $step_labels = ['Service', 'Journey', 'Vehicle', 'Details', 'Review'];
              foreach ($step_labels as $i => $label): ?>
              <div class="progress__step <?= $i === 0 ? 'is-active' : '' ?>"
                   data-progress-step="<?= $i + 1 ?>" role="listitem">
                <span class="progress__dot"><?= $i + 1 ?></span>
                <span class="progress__label"><?= e($label) ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- ══ STEP 1 — SERVICE ══════════════════════════════════ -->
          <fieldset class="wizard-step is-active" data-step="1">
            <legend class="sr-only">Choose your service</legend>
            <h2 class="wizard-step__title">What do you need?</h2>
            <p class="wizard-step__lead">Choose the service that fits your journey.</p>

            <div class="option-grid option-grid--2">
              <?php
              $service_opts = [
                ['airport',      'plane',     'Airport Transfer',      'Pickup or drop-off at YYZ, YTZ or Hamilton. Flight tracking included.'],
                ['city',         'map-pin',   'In-City Ride',          'Point-to-point travel within Toronto and the GTA.'],
                ['city_to_city', 'route',     'City to City Transfer', 'Long-distance transfers at published flat rates.'],
                ['hourly',       'clock',     'Hourly Chauffeur',      'Car and chauffeur on standby. Minimum 3 hours (4 on the Maybach).'],
                ['rental',       'key',       'Vehicle Rental',        'Self-drive hire by the day or week.'],
              ];
              $checked_service = old('service_type', $pre_service);
              foreach ($service_opts as [$val, $ico, $title, $desc]): ?>
              <label class="option">
                <input type="radio" name="service_type" value="<?= e($val) ?>"
                       data-service-radio
                       <?= $checked_service === $val ? 'checked' : '' ?>>
                <span class="option__icon"><?= icon($ico) ?></span>
                <span>
                  <span class="option__title"><?= e($title) ?></span>
                  <span class="option__desc"><?= e($desc) ?></span>
                </span>
                <span class="option__check"><?= icon('check') ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <!-- ══ STEP 2 — JOURNEY ══════════════════════════════════ -->
          <fieldset class="wizard-step" data-step="2">
            <legend class="sr-only">Journey details</legend>
            <h2 class="wizard-step__title">Where and when?</h2>
            <p class="wizard-step__lead">Tell us your pickup, destination and timing.</p>

            <!-- One way / return -->
            <div class="option-grid option-grid--2 mb-6" data-field="triptype">
              <label class="option">
                <input type="radio" name="is_return" value="0" data-trip-radio
                       <?= old('is_return', '0') === '0' ? 'checked' : '' ?>>
                <span class="option__icon"><?= icon('arrow-right') ?></span>
                <span>
                  <span class="option__title">One way</span>
                  <span class="option__desc">A single journey.</span>
                </span>
                <span class="option__check"><?= icon('check') ?></span>
              </label>

              <label class="option">
                <input type="radio" name="is_return" value="1" data-trip-radio
                       <?= old('is_return', '0') === '1' ? 'checked' : '' ?>>
                <span class="option__icon"><?= icon('route') ?></span>
                <span>
                  <span class="option__title">Return trip</span>
                  <span class="option__desc">Book both legs together and save
                    <?= (int)setting_num('return_discount', 10) ?>%.</span>
                </span>
                <span class="option__check"><?= icon('check') ?></span>
              </label>
            </div>

            <div class="field">
              <label class="field__label" for="pickup_address">
                Pickup address <span class="req" aria-hidden="true">*</span>
              </label>
              <input class="input" type="text" id="pickup_address" name="pickup_address"
                     value="<?= e(old('pickup_address', $pre_from)) ?>"
                     placeholder="e.g. 100 Front St W, Toronto"
                     autocomplete="street-address" required>
              <span class="field__hint" id="pickupHint">Street address, hotel, airport terminal or postcode.</span>
            </div>

            <div class="field" data-field="dropoff">
              <label class="field__label" for="dropoff_address">
                Drop-off address <span class="req" aria-hidden="true">*</span>
              </label>
              <input class="input" type="text" id="dropoff_address" name="dropoff_address"
                     value="<?= e(old('dropoff_address', $pre_to)) ?>"
                     placeholder="e.g. Toronto Pearson International Airport"
                     autocomplete="street-address">
              <span class="field__hint">For city-to-city trips, naming the city gets you our published flat rate.</span>
            </div>

            <!-- Additional stops -->
            <div class="field" data-field="stops">
              <span class="field__label">Additional stops</span>
              <div id="stopList" style="display:grid;gap:var(--s-3);"></div>
              <button type="button" class="btn btn--outline btn--sm mt-4" id="addStopBtn"
                      style="justify-self:start;">
                <?= icon('plus') ?><span>Add a stop</span>
              </button>
              <span class="field__hint">
                <?= money_short(setting_num('stop_fee', 15)) ?> per extra stop, up to
                <?= (int)setting_num('max_stops', 3) ?>. Hourly hire includes unlimited stops.
              </span>
            </div>

            <!-- Return leg date -->
            <div class="field" data-field="returntrip" hidden>
              <label class="field__label" for="return_at_trip">
                Return date &amp; time <span class="req" aria-hidden="true">*</span>
              </label>
              <input class="input" type="datetime-local" id="return_at_trip" name="return_at_trip"
                     value="<?= e(old('return_at_trip')) ?>">
              <span class="field__hint">When you would like collecting for the journey back.</span>
            </div>

            <div class="field-row field-row--2">
              <div class="field">
                <label class="field__label" for="pickup_at">
                  Pickup date &amp; time <span class="req" aria-hidden="true">*</span>
                </label>
                <input class="input" type="datetime-local" id="pickup_at" name="pickup_at"
                       value="<?= e(old('pickup_at', $pre_when)) ?>" min="<?= e($min_pickup) ?>" required>
                <span class="field__hint">At least <?= MIN_LEAD_TIME_HOURS ?> hours from now.</span>
              </div>

              <div class="field" data-field="return" hidden>
                <label class="field__label" for="return_at">
                  Return date &amp; time <span class="req" aria-hidden="true">*</span>
                </label>
                <input class="input" type="datetime-local" id="return_at" name="return_at"
                       value="<?= e(old('return_at')) ?>">
                <span class="field__hint">When the vehicle will be returned.</span>
              </div>

              <div class="field" data-field="hours" hidden>
                <label class="field__label" for="hours">
                  How many hours? <span class="req" aria-hidden="true">*</span>
                </label>
                <input class="input" type="number" id="hours" name="hours" min="1" max="24" step="1"
                       value="<?= e(old('hours', $pre_hours > 0 ? (string)$pre_hours : '3')) ?>" inputmode="numeric">
                <span class="field__hint" id="hoursHint">Minimum 3 hours on most vehicles.</span>
              </div>
            </div>

            <div class="field-row field-row--2">
              <div class="field" data-field="flight" hidden>
                <label class="field__label" for="flight_number">Flight number</label>
                <input class="input" type="text" id="flight_number" name="flight_number"
                       value="<?= e(old('flight_number')) ?>" placeholder="e.g. AC 848">
                <span class="field__hint">We track your flight and adjust for delays automatically.</span>
              </div>

              <div class="field" data-field="distance">
                <label class="field__label" for="distance_km">Approximate distance (km)</label>
                <input class="input" type="number" id="distance_km" name="distance_km"
                       min="0" max="2000" step="0.1" inputmode="decimal"
                       value="<?= e(old('distance_km')) ?>" placeholder="Optional">
                <span class="field__hint">Optional &mdash; helps us price short trips instantly.</span>
                <input type="hidden" id="duration_min" name="duration_min" value="<?= e(old('duration_min')) ?>">
              </div>
            </div>
          </fieldset>

          <!-- ══ STEP 3 — VEHICLE ══════════════════════════════════ -->
          <fieldset class="wizard-step" data-step="3">
            <legend class="sr-only">Choose your vehicle</legend>
            <h2 class="wizard-step__title">Choose your vehicle</h2>
            <p class="wizard-step__lead">Vehicles unavailable for your chosen service are dimmed.</p>

            <div class="option-grid" id="vehicleOptions">
              <?php
              $checked_vehicle = (int)old('vehicle_id', $pre_vehicle['id'] ?? 0);
              foreach ($vehicles as $v): ?>
              <label class="option" data-vehicle-option="<?= (int)$v['id'] ?>">
                <input type="radio" name="vehicle_id" value="<?= (int)$v['id'] ?>"
                       data-vehicle-radio
                       <?= $checked_vehicle === (int)$v['id'] ? 'checked' : '' ?>>
                <span class="option__icon">
                  <?= icon(stripos($v['class_label'], 'sedan') !== false ? 'car' : 'suv') ?>
                </span>
                <span>
                  <span class="option__title"><?= e($v['name']) ?></span>
                  <span class="option__desc">
                    <?= (int)$v['passengers'] ?> passengers &middot; <?= (int)$v['luggage'] ?> bags
                    &middot; from <?= money_short((float)$v['hourly_rate']) ?>/hr
                  </span>
                  <span class="option__note" data-vehicle-note></span>
                </span>
                <span class="option__check"><?= icon('check') ?></span>
              </label>
              <?php endforeach; ?>
            </div>

            <div class="included">
              <h4>Included with every vehicle</h4>
              <p><strong style="color:var(--text);">Meet &amp; Greet Service</strong><br>
                 Your professional chauffeur will meet you at your pickup location, open the door
                 for you, and assist with your luggage to ensure a seamless and comfortable experience.</p>
              <p><strong style="color:var(--text);">Onboard Amenities</strong><br>
                 All PRIME vehicles include complimentary bottled water, Wi-Fi connection,
                 and reading material to enhance your journey.</p>
            </div>
          </fieldset>

          <!-- ══ STEP 4 — DETAILS ══════════════════════════════════ -->
          <fieldset class="wizard-step" data-step="4">
            <legend class="sr-only">Your details</legend>
            <h2 class="wizard-step__title">Your details</h2>
            <p class="wizard-step__lead">We&rsquo;ll send your confirmation here.</p>

            <div class="field">
              <label class="field__label" for="full_name">
                Full name <span class="req" aria-hidden="true">*</span>
              </label>
              <input class="input" type="text" id="full_name" name="full_name"
                     value="<?= e(old('full_name', $cust['full_name'] ?? '')) ?>" autocomplete="name" required>
            </div>

            <div class="field-row field-row--2">
              <div class="field">
                <label class="field__label" for="email">
                  Email <span class="req" aria-hidden="true">*</span>
                </label>
                <input class="input" type="email" id="email" name="email"
                       value="<?= e(old('email', $cust['email'] ?? '')) ?>" autocomplete="email"
                       inputmode="email" required>
              </div>
              <div class="field">
                <label class="field__label" for="phone">
                  Phone <span class="req" aria-hidden="true">*</span>
                </label>
                <input class="input" type="tel" id="phone" name="phone"
                       value="<?= e(old('phone', $cust['phone'] ?? '')) ?>" autocomplete="tel"
                       inputmode="tel" placeholder="+1 (416) 000-0000" required>
              </div>
            </div>

            <div class="field-row field-row--2">
              <div class="field">
                <label class="field__label" for="passengers">Passengers</label>
                <input class="input" type="number" id="passengers" name="passengers"
                       min="1" max="8" step="1" inputmode="numeric"
                       value="<?= e(old('passengers', $pre_pax > 0 ? (string)$pre_pax : '1')) ?>">
                <span class="field__hint" id="passengersHint"></span>
              </div>
              <div class="field">
                <label class="field__label" for="luggage">Luggage (bags)</label>
                <input class="input" type="number" id="luggage" name="luggage"
                       min="0" max="10" step="1" inputmode="numeric"
                       value="<?= e(old('luggage', '0')) ?>">
              </div>
            </div>

            <?php if ($cust !== null && $cust_discount > 0): ?>
            <div class="alert alert--gold">
              <?= icon('crown') ?>
              <span>You&rsquo;re signed in as a <strong><?= e(membership_label($cust_tier)) ?></strong>
                &mdash; <?= (int)$cust_discount ?>% has already been taken off the price shown.</span>
            </div>
            <?php elseif ($cust === null): ?>
            <div class="alert alert--info">
              <?= icon('info') ?>
              <span><a href="signin.php?next=booking.php">Sign in</a> if you&rsquo;re an Elite or
                VIP member and your discount will be applied automatically, or
                <a href="signup.php">create an account</a> to save this trip and rebook in one tap.</span>
            </div>
            <?php endif; ?>

            <div class="field">
              <label class="field__label" for="notes">Extra requests</label>
              <textarea class="textarea" id="notes" name="notes"
                        placeholder="Child seat, additional stops, accessibility needs, preferred route…"><?= e(old('notes')) ?></textarea>
            </div>
          </fieldset>

          <!-- ══ STEP 5 — REVIEW ═══════════════════════════════════ -->
          <fieldset class="wizard-step" data-step="5">
            <legend class="sr-only">Review and confirm</legend>
            <h2 class="wizard-step__title">Review your booking</h2>
            <p class="wizard-step__lead">Check the details below, then confirm.</p>

            <dl id="reviewList"></dl>

            <div class="alert alert--gold mt-6">
              <?= icon('info') ?>
              <span>Submitting sends your request to our reservations team. You&rsquo;ll receive an
                email confirmation immediately, and we&rsquo;ll confirm your chauffeur and vehicle
                shortly afterwards.<?= stripe_enabled() ? ' Payment is taken securely after confirmation.' : '' ?></span>
            </div>
          </fieldset>

          <!-- ══ NAV ═══════════════════════════════════════════════ -->
          <div class="wizard__nav">
            <button type="button" class="btn btn--ghost" id="btnBack" hidden>
              <?= icon('arrow-left') ?><span>Back</span>
            </button>
            <button type="button" class="btn btn--gold" id="btnNext">
              <span>Continue</span><?= icon('arrow-right') ?>
            </button>
            <button type="submit" class="btn btn--gold" id="btnSubmit" hidden>
              <?= icon('check') ?><span>Confirm Booking</span>
            </button>
          </div>

        </div>

        <!-- ══ SUMMARY RAIL ════════════════════════════════════════ -->
        <aside class="summary" aria-live="polite" aria-atomic="true">
          <h2 class="summary__title">Your quote</h2>
          <div id="summaryBody">
            <p class="summary__empty">Choose a service and vehicle to see your price.</p>
          </div>
          <p class="summary__note">
            All prices in CAD and include
            <?= rtrim(rtrim(number_format(setting_num('hst_rate', DEFAULT_HST_RATE), 2), '0'), '.') ?>% HST
            where shown. Final fare is confirmed by our team before your ride.
          </p>
        </aside>

      </div>
    </form>
  </div>
</section>

<script>
  window.PLR = {
    vehicles: <?= e_json($vehicle_js) ?>,
    csrf:     <?= e_json(csrf_token()) ?>,
    quoteUrl: 'api/quote.php',
    minHoursDefault: 3,
    startStep: <?= (int)$start_step ?>,
    maxStops: <?= (int)setting_num('max_stops', 3) ?>,
    stopFee: <?= (float)setting_num('stop_fee', 15) ?>,
    savedPlaces: <?= e_json($cust ? array_map(
        fn($a) => ['label' => $a['label'], 'address' => $a['address']],
        customer_addresses((int)$cust['id'])) : []) ?>,
    mapsEnabled: <?= maps_enabled() ? 'true' : 'false' ?>
  };
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
