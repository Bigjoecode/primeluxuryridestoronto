/* ===================================================================
   Booking wizard — step navigation, conditional fields, live quoting.
   Depends on window.PLR (injected by booking.php).
   =================================================================== */
(function () {
  'use strict';

  var cfg = window.PLR;
  if (!cfg) return;

  var form = document.getElementById('bookingForm');
  if (!form) return;

  var TOTAL_STEPS = 5;
  var current     = 1;

  var steps       = form.querySelectorAll('[data-step]');
  var progressBar = document.getElementById('progressBar');
  var progSteps   = form.querySelectorAll('[data-progress-step]');
  var btnBack     = document.getElementById('btnBack');
  var btnNext     = document.getElementById('btnNext');
  var btnSubmit   = document.getElementById('btnSubmit');
  var summaryBody = document.getElementById('summaryBody');
  var reviewList  = document.getElementById('reviewList');

  var fieldDropoff  = form.querySelector('[data-field="dropoff"]');
  var fieldReturn   = form.querySelector('[data-field="return"]');
  var fieldHours    = form.querySelector('[data-field="hours"]');
  var fieldFlight   = form.querySelector('[data-field="flight"]');
  var fieldDistance = form.querySelector('[data-field="distance"]');

  var $ = function (id) { return document.getElementById(id); };

  /* ── Helpers ─────────────────────────────────────────────────── */

  function serviceType() {
    var el = form.querySelector('[data-service-radio]:checked');
    return el ? el.value : '';
  }

  function vehicleId() {
    var el = form.querySelector('[data-vehicle-radio]:checked');
    return el ? parseInt(el.value, 10) : 0;
  }

  function vehicleById(id) {
    for (var i = 0; i < cfg.vehicles.length; i++) {
      if (cfg.vehicles[i].id === id) return cfg.vehicles[i];
    }
    return null;
  }

  function setError(input, message) {
    var field = input.closest('.field') || input.closest('.option-grid') || input.parentNode;
    clearError(input);
    if (!message) return;

    input.setAttribute('aria-invalid', 'true');
    var span = document.createElement('span');
    span.className = 'field__error';
    span.setAttribute('role', 'alert');
    span.innerHTML =
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
      'stroke-width="2" stroke-linecap="round" aria-hidden="true">' +
      '<circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>' +
      '<span></span>';
    span.querySelector('span').textContent = message;
    span.setAttribute('data-generated-error', '');
    field.appendChild(span);
  }

  function clearError(input) {
    input.removeAttribute('aria-invalid');
    var field = input.closest('.field') || input.closest('.option-grid') || input.parentNode;
    var old = field.querySelector('[data-generated-error]');
    if (old) old.remove();
  }

  /* ── Additional stops ────────────────────────────────────────── */

  var stopList  = document.getElementById('stopList');
  var addStop   = document.getElementById('addStopBtn');
  var maxStops  = parseInt(cfg.maxStops, 10) || 3;

  function stopCount() {
    return stopList ? stopList.querySelectorAll('input[name="stops[]"]').length : 0;
  }

  function syncAddStop() {
    if (!addStop) return;
    var n = stopCount();
    addStop.disabled = n >= maxStops;
    addStop.querySelector('span').textContent =
      n >= maxStops ? 'Maximum ' + maxStops + ' stops' : 'Add a stop';
  }

  function addStopRow(value) {
    if (!stopList || stopCount() >= maxStops) return;

    var row = document.createElement('div');
    row.style.cssText = 'display:flex;gap:var(--s-3);align-items:center;';
    row.innerHTML =
      '<input class="input" type="text" name="stops[]" placeholder="Stop address">' +
      '<button type="button" class="btn btn--ghost btn--sm" data-remove-stop ' +
      'aria-label="Remove this stop">' +
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
      'stroke-width="1.75" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>' +
      '</button>';

    if (value) row.querySelector('input').value = value;
    stopList.appendChild(row);
    syncAddStop();
    requestQuote();
    return row;
  }

  if (addStop) {
    addStop.addEventListener('click', function () {
      var row = addStopRow('');
      if (row) row.querySelector('input').focus();
    });
  }

  if (stopList) {
    stopList.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-remove-stop]');
      if (!btn) return;
      btn.parentNode.remove();
      syncAddStop();
      lastKey = '';
      requestQuote();
    });
  }

  /* ── Conditional fields per service type ─────────────────────── */

  var fieldTripType   = form.querySelector('[data-field="triptype"]');
  var fieldStops      = form.querySelector('[data-field="stops"]');
  var fieldReturnTrip = form.querySelector('[data-field="returntrip"]');

  function isReturnTrip() {
    var el = form.querySelector('[data-trip-radio]:checked');
    return !!el && el.value === '1';
  }

  function applyServiceRules() {
    var svc = serviceType();
    var v   = vehicleById(vehicleId());

    var needsDropoff = (svc === 'airport' || svc === 'city' || svc === 'city_to_city');
    var isHourly     = (svc === 'hourly');
    var isRental     = (svc === 'rental');

    if (fieldDropoff)  fieldDropoff.hidden  = !needsDropoff;
    if (fieldHours)    fieldHours.hidden    = !isHourly;
    if (fieldReturn)   fieldReturn.hidden   = !isRental;
    if (fieldFlight)   fieldFlight.hidden   = (svc !== 'airport');
    if (fieldDistance) fieldDistance.hidden = isHourly || isRental;

    // Returns and per-stop fees only apply to point-to-point journeys.
    // Hourly hire already includes unlimited stops; rentals have their
    // own return date.
    var pointToPoint = needsDropoff;
    if (fieldTripType) fieldTripType.hidden = !pointToPoint;
    if (fieldStops)    fieldStops.hidden    = !pointToPoint;
    if (fieldReturnTrip) {
      fieldReturnTrip.hidden = !(pointToPoint && isReturnTrip());
    }
    if (!pointToPoint) {
      var oneWay = form.querySelector('[data-trip-radio][value="0"]');
      if (oneWay) oneWay.checked = true;
    }

    // Pickup label wording
    var hint = $('pickupHint');
    if (hint) {
      hint.textContent = isRental
        ? 'Where you would like to collect the vehicle.'
        : 'Street address, hotel, airport terminal or postcode.';
    }

    // Minimum hours for the selected vehicle
    var hoursInput = $('hours');
    var hoursHint  = $('hoursHint');
    if (isHourly && hoursInput) {
      var min = v ? v.minHours : cfg.minHoursDefault;
      hoursInput.min = String(min);
      if (parseInt(hoursInput.value, 10) < min || !hoursInput.value) {
        hoursInput.value = String(min);
      }
      if (hoursHint) {
        hoursHint.textContent = v
          ? 'Minimum ' + min + ' hours on the ' + v.name + '.'
          : 'Minimum ' + min + ' hours.';
      }
    }

    // Passenger cap from the chosen vehicle
    var pax     = $('passengers');
    var paxHint = $('passengersHint');
    if (pax && v) {
      pax.max = String(v.passengers);
      if (parseInt(pax.value, 10) > v.passengers) pax.value = String(v.passengers);
      if (paxHint) paxHint.textContent = 'The ' + v.name + ' seats up to ' + v.passengers + '.';
    } else if (paxHint) {
      paxHint.textContent = '';
    }

    applyVehicleAvailability();
  }

  /* ── Dim vehicles that cannot take the chosen service ────────── */

  function applyVehicleAvailability() {
    var svc = serviceType();
    if (!svc) return;

    cfg.vehicles.forEach(function (v) {
      var label = form.querySelector('[data-vehicle-option="' + v.id + '"]');
      if (!label) return;

      var input   = label.querySelector('input');
      var note    = label.querySelector('[data-vehicle-note]');
      var allowed = !!(v.allows && v.allows[svc]);

      label.classList.toggle('is-disabled', !allowed);
      input.disabled = !allowed;

      if (!allowed && input.checked) {
        input.checked = false;
        label.classList.remove('is-selected');
      }

      if (note) {
        if (!allowed) {
          note.textContent = 'Not available for this service';
        } else if (svc === 'hourly') {
          note.textContent = v.minHours + '-hour minimum';
        } else {
          note.textContent = '';
        }
      }
    });
  }

  /* ── Validation per step ─────────────────────────────────────── */

  function validateStep(step) {
    var ok = true;
    var firstBad = null;

    var fail = function (input, msg) {
      setError(input, msg);
      if (!firstBad) firstBad = input;
      ok = false;
    };

    if (step === 1) {
      if (!serviceType()) {
        var anyRadio = form.querySelector('[data-service-radio]');
        fail(anyRadio, 'Please choose a service type.');
      }
    }

    if (step === 2) {
      var svc    = serviceType();
      var pickup = $('pickup_address');
      var dropIn = $('dropoff_address');
      var when   = $('pickup_at');
      var ret    = $('return_at');

      clearError(pickup); clearError(dropIn); clearError(when); clearError(ret);

      if (!pickup.value.trim()) fail(pickup, 'Please enter a pickup address.');

      if ((svc === 'airport' || svc === 'city' || svc === 'city_to_city') && !dropIn.value.trim()) {
        fail(dropIn, 'Please enter a drop-off address.');
      }

      if (!when.value) {
        fail(when, 'Please choose a pickup date and time.');
      } else if (when.min && when.value < when.min) {
        fail(when, 'Please choose a later pickup time.');
      }

      if (svc === 'rental') {
        if (!ret.value) {
          fail(ret, 'Please choose a return date.');
        } else if (when.value && ret.value <= when.value) {
          fail(ret, 'The return must be after collection.');
        }
      }

      var retTrip = $('return_at_trip');
      if (retTrip && isReturnTrip() && fieldReturnTrip && !fieldReturnTrip.hidden) {
        clearError(retTrip);
        if (!retTrip.value) {
          fail(retTrip, 'Please choose when you would like collecting for the return leg.');
        } else if (when.value && retTrip.value <= when.value) {
          fail(retTrip, 'The return leg must be after the outbound pickup.');
        }
      }
    }

    if (step === 3) {
      if (!vehicleId()) {
        var anyVeh = form.querySelector('[data-vehicle-radio]');
        fail(anyVeh, 'Please select a vehicle.');
      }
    }

    if (step === 4) {
      var name  = $('full_name');
      var email = $('email');
      var phone = $('phone');

      clearError(name); clearError(email); clearError(phone);

      if (!name.value.trim()) fail(name, 'Please enter your full name.');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.value.trim())) {
        fail(email, 'Please enter a valid email address.');
      }
      if (phone.value.replace(/\D+/g, '').length < 7) {
        fail(phone, 'Please enter a valid phone number.');
      }
    }

    if (firstBad) {
      firstBad.focus({ preventScroll: false });
      firstBad.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
    return ok;
  }

  /* ── Step navigation ─────────────────────────────────────────── */

  function goTo(step) {
    current = Math.max(1, Math.min(TOTAL_STEPS, step));

    steps.forEach(function (el) {
      el.classList.toggle('is-active',
        parseInt(el.getAttribute('data-step'), 10) === current);
    });

    progSteps.forEach(function (el) {
      var n = parseInt(el.getAttribute('data-progress-step'), 10);
      el.classList.toggle('is-active', n === current);
      el.classList.toggle('is-done',   n <  current);
    });

    if (progressBar) {
      progressBar.style.width = (current / TOTAL_STEPS * 100) + '%';
    }

    btnBack.hidden   = (current === 1);
    btnNext.hidden   = (current === TOTAL_STEPS);
    btnSubmit.hidden = (current !== TOTAL_STEPS);

    if (current === TOTAL_STEPS) buildReview();

    var main = document.querySelector('.wizard__main');
    if (main) {
      var top = main.getBoundingClientRect().top + window.scrollY - 100;
      window.scrollTo({ top: top, behavior: 'smooth' });
    }
  }

  btnNext.addEventListener('click', function () {
    if (validateStep(current)) {
      requestQuote();
      goTo(current + 1);
    }
  });

  btnBack.addEventListener('click', function () { goTo(current - 1); });

  // Enter key should advance, not submit early.
  form.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' && ev.target.tagName !== 'TEXTAREA' && current < TOTAL_STEPS) {
      ev.preventDefault();
      btnNext.click();
    }
  });

  // Final guard: re-validate everything on submit.
  form.addEventListener('submit', function (ev) {
    for (var s = 1; s <= 4; s++) {
      if (!validateStep(s)) {
        ev.preventDefault();
        goTo(s);
        return;
      }
    }
    btnSubmit.classList.add('is-loading');
    btnSubmit.disabled = true;
  });

  /* ── Live quote ──────────────────────────────────────────────── */

  var quoteTimer = null;
  var lastKey    = '';

  function quotePayload() {
    return {
      csrf:         cfg.csrf,
      service_type: serviceType(),
      vehicle_id:   vehicleId(),
      distance_km:  parseFloat(($('distance_km') || {}).value) || 0,
      duration_min: parseFloat(($('duration_min') || {}).value) || 0,
      hours:        parseInt(($('hours') || {}).value, 10) || 0,
      days:         rentalDays(),
      pickup:       ($('pickup_address')  || {}).value || '',
      dropoff:      ($('dropoff_address') || {}).value || '',
      is_return:    isReturnTrip(),
      stops:        stopCount()
    };
  }

  function rentalDays() {
    var a = $('pickup_at'), b = $('return_at');
    if (!a || !b || !a.value || !b.value) return 0;
    var d1 = new Date(a.value), d2 = new Date(b.value);
    if (isNaN(d1) || isNaN(d2) || d2 <= d1) return 0;
    return Math.max(1, Math.ceil((d2 - d1) / 86400000));
  }

  function requestQuote() {
    var payload = quotePayload();
    if (!payload.service_type || !payload.vehicle_id) return;

    var key = JSON.stringify(payload);
    if (key === lastKey) return;
    lastKey = key;

    showQuoteSkeleton();

    fetch(cfg.quoteUrl, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    })
      .then(function (r) { return r.json(); })
      .then(renderQuote)
      .catch(function () {
        summaryBody.innerHTML =
          '<p class="summary__empty">We could not calculate a price just now. ' +
          'You can still submit your booking and we will confirm the fare.</p>';
      });
  }

  function showQuoteSkeleton() {
    summaryBody.innerHTML =
      '<div class="skeleton" style="width:70%;margin-bottom:12px;"></div>' +
      '<div class="skeleton" style="width:90%;margin-bottom:12px;"></div>' +
      '<div class="skeleton" style="width:55%;margin-bottom:20px;"></div>' +
      '<div class="skeleton" style="width:100%;height:2.2em;"></div>';
  }

  function renderQuote(data) {
    if (!data || !data.ok) {
      summaryBody.innerHTML = '';
      var box = document.createElement('div');
      box.className = 'alert alert--error';
      box.style.marginBottom = '0';
      box.textContent = (data && data.error) ? data.error : 'We could not calculate a price.';
      summaryBody.appendChild(box);
      return;
    }

    var html = '';

    data.lines.forEach(function (l) {
      html += '<div class="summary__line"><span>' + esc(l.label) + '</span>' +
              '<span>' + esc(l.amount) + '</span></div>';
    });

    html += '<div class="summary__divider"></div>';
    html += '<div class="summary__line"><span>Subtotal</span><span>' +
            esc(data.subtotal) + '</span></div>';

    if (data.has_discount) {
      html += '<div class="summary__line summary__line--discount"><span>' +
              esc(cap(data.membership)) + ' discount (' + data.discount_percent + '%)</span>' +
              '<span>− ' + esc(data.discount) + '</span></div>';
    }

    html += '<div class="summary__line"><span>HST (' + data.hst_rate + '%)</span><span>' +
            esc(data.hst) + '</span></div>';

    html += '<dl class="summary__total"><dt>Total</dt><dd>' + esc(data.total) + '</dd></dl>';

    if (data.notes && data.notes.length) {
      data.notes.forEach(function (n) {
        html += '<p class="summary__note" style="color:var(--gold);">' + esc(n) + '</p>';
      });
    }

    summaryBody.innerHTML = html;
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
  }

  function cap(s) {
    s = String(s || '');
    return s.charAt(0).toUpperCase() + s.slice(1);
  }

  /* ── Review step ─────────────────────────────────────────────── */

  function buildReview() {
    var svc = serviceType();
    var v   = vehicleById(vehicleId());

    var labels = {
      airport: 'Airport Transfer', city: 'In-City Ride',
      city_to_city: 'City to City Transfer', hourly: 'Hourly Chauffeur',
      rental: 'Vehicle Rental'
    };

    var stops = [];
    form.querySelectorAll('input[name="stops[]"]').forEach(function (i) {
      if (i.value.trim()) stops.push(i.value.trim());
    });

    var rows = [
      ['Service',       labels[svc] || svc],
      ['Trip type',     isReturnTrip() ? 'Return trip' : 'One way'],
      ['Extra stops',   stops.length ? stops.join(' · ') : ''],
      ['Return leg',    isReturnTrip() ? fmtDT(($('return_at_trip') || {}).value) : ''],
      ['Vehicle',       v ? v.name : '—'],
      ['Pickup',        ($('pickup_address') || {}).value],
      ['Drop-off',      (svc === 'hourly' || svc === 'rental') ? '' : ($('dropoff_address') || {}).value],
      ['Date & time',   fmtDT(($('pickup_at') || {}).value)],
      ['Return',        svc === 'rental' ? fmtDT(($('return_at') || {}).value) : ''],
      ['Duration',      svc === 'hourly' ? (($('hours') || {}).value + ' hours') : ''],
      ['Flight number', svc === 'airport' ? ($('flight_number') || {}).value : ''],
      ['Name',          ($('full_name') || {}).value],
      ['Email',         ($('email')     || {}).value],
      ['Phone',         ($('phone')     || {}).value],
      ['Passengers',    ($('passengers')|| {}).value],
      ['Luggage',       ($('luggage')   || {}).value],
      ['Extra requests',($('notes')     || {}).value]
    ];

    var html = '';
    rows.forEach(function (r) {
      var val = (r[1] == null ? '' : String(r[1])).trim();
      if (!val) return;
      html += '<div class="summary__row"><dt>' + esc(r[0]) + '</dt>' +
              '<dd>' + esc(val) + '</dd></div>';
    });

    reviewList.innerHTML = html;
  }

  function fmtDT(v) {
    if (!v) return '';
    var d = new Date(v);
    if (isNaN(d)) return v;
    return d.toLocaleString('en-CA', {
      weekday: 'short', day: 'numeric', month: 'short', year: 'numeric',
      hour: 'numeric', minute: '2-digit'
    });
  }

  /* ── Wiring ──────────────────────────────────────────────────── */

  form.addEventListener('change', function (ev) {
    var t = ev.target;

    if (t.matches('[data-service-radio]')) {
      applyServiceRules();
      lastKey = '';
      requestQuote();
    }

    if (t.matches('[data-vehicle-radio]')) {
      applyServiceRules();
      requestQuote();
    }

    if (t.matches('[data-trip-radio]')) {
      applyServiceRules();
      lastKey = '';
      requestQuote();
    }

    if (t.matches('#hours, #distance_km, #pickup_at, #return_at, #return_at_trip')) {
      requestQuote();
    }
  });

  // Debounced re-quote while typing addresses (affects flat-rate matching).
  form.addEventListener('input', function (ev) {
    if (!ev.target.matches('#pickup_address, #dropoff_address, #distance_km, input[name="stops[]"]')) return;
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(requestQuote, 600);
  });

  // Clear a field error as soon as the user corrects it.
  form.addEventListener('blur', function (ev) {
    if (ev.target.matches('.input, .select, .textarea') &&
        ev.target.getAttribute('aria-invalid') === 'true') {
      if (ev.target.value.trim()) clearError(ev.target);
    }
  }, true);

  /* ── Init ────────────────────────────────────────────────────── */

  applyServiceRules();
  syncAddStop();

  // Open on the first step the hero quick-search did NOT already answer,
  // but never skip past a step whose fields failed validation.
  var start = parseInt(cfg.startStep, 10) || 1;
  start = Math.max(1, Math.min(TOTAL_STEPS, start));
  while (start > 1 && !validateStep(start - 1)) {
    start--;
  }
  // validateStep paints errors as a side effect; clear them on first view.
  form.querySelectorAll('[data-generated-error]').forEach(function (el) { el.remove(); });
  form.querySelectorAll('[aria-invalid="true"]').forEach(function (el) {
    el.removeAttribute('aria-invalid');
  });

  goTo(start);
  if (serviceType() && vehicleId()) requestQuote();

  // If the server bounced us back with errors, show them.
  var errBox = document.getElementById('formErrors');
  if (errBox) {
    errBox.focus();
    errBox.scrollIntoView({ block: 'center' });
  }
})();
