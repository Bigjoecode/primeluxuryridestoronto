<?php
/**
 * Hero quick-search widget.
 *
 * Collects just enough to open the booking wizard part-way through:
 * service type, pickup, drop-off (or hours), date/time and passengers.
 * Submits by GET to booking.php, which pre-fills the form and skips
 * straight to the first unanswered step.
 *
 * Deliberately a plain <form> with a real action — it works with
 * JavaScript disabled, and needs no CSRF token because it is a
 * navigation, not a state change.
 */
$qs_min = (new DateTime())->modify('+' . MIN_LEAD_TIME_HOURS . ' hours')->format('Y-m-d\TH:i');
?>
<form class="quick-search" action="booking.php" method="get" id="quickSearch">

  <!-- Service type -->
  <div class="qs-tabs" role="tablist" aria-label="Type of service">
    <?php
    $qs_services = [
      ['airport',      'plane',   'Airport'],
      ['city',         'map-pin', 'In-City'],
      ['city_to_city', 'route',   'City to City'],
      ['hourly',       'clock',   'Hourly'],
    ];
    foreach ($qs_services as $i => [$val, $ico, $label]): ?>
    <label class="qs-tab">
      <input type="radio" name="service" value="<?= e($val) ?>"
             data-qs-service <?= $i === 0 ? 'checked' : '' ?>>
      <?= icon($ico, '', 17) ?><span><?= e($label) ?></span>
    </label>
    <?php endforeach; ?>
  </div>

  <!-- Fields -->
  <div class="qs-fields">

    <div class="qs-field">
      <label class="qs-field__label" for="qs_from">Pickup</label>
      <div class="qs-field__control">
        <?= icon('map-pin', 'qs-field__icon', 18) ?>
        <input class="qs-field__input" type="text" id="qs_from" name="from"
               placeholder="Address, hotel or airport" autocomplete="off">
      </div>
    </div>

    <div class="qs-field" data-qs-dropoff>
      <label class="qs-field__label" for="qs_to">Drop-off</label>
      <div class="qs-field__control">
        <?= icon('navigation', 'qs-field__icon', 18) ?>
        <input class="qs-field__input" type="text" id="qs_to" name="to"
               placeholder="Where are you going?" autocomplete="off">
      </div>
    </div>

    <div class="qs-field qs-field--hours" data-qs-hours hidden>
      <label class="qs-field__label" for="qs_hours">Hours</label>
      <div class="qs-field__control">
        <?= icon('clock', 'qs-field__icon', 18) ?>
        <input class="qs-field__input" type="number" id="qs_hours" name="hours"
               min="3" max="24" value="3" inputmode="numeric">
      </div>
    </div>

    <div class="qs-field">
      <label class="qs-field__label" for="qs_when">Date &amp; time</label>
      <div class="qs-field__control">
        <?= icon('calendar', 'qs-field__icon', 18) ?>
        <input class="qs-field__input" type="datetime-local" id="qs_when" name="when"
               min="<?= e($qs_min) ?>">
      </div>
    </div>

    <div class="qs-field qs-field--pax">
      <label class="qs-field__label" for="qs_pax">Passengers</label>
      <div class="qs-field__control">
        <?= icon('users', 'qs-field__icon', 18) ?>
        <select class="qs-field__input" id="qs_pax" name="pax">
          <?php for ($i = 1; $i <= 6; $i++): ?>
          <option value="<?= $i ?>"><?= $i ?> passenger<?= $i > 1 ? 's' : '' ?></option>
          <?php endfor; ?>
        </select>
      </div>
    </div>

    <button type="submit" class="qs-submit">
      <?= icon('search', '', 20) ?><span>Search rides</span>
    </button>
  </div>

  <p class="qs-note">
    <?= icon('shield-check', '', 15) ?>
    <span>Instant price &mdash; no card required to see your fare.</span>
  </p>
</form>
