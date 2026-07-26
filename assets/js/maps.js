/* ===================================================================
   Google Maps integration for the booking wizard.
   Loaded ONLY when GOOGLE_MAPS_API_KEY is configured.

   Provides:
     • Address autocomplete on pickup / drop-off (biased to Ontario)
     • Automatic distance + duration via Distance Matrix
     • Writes into #distance_km / #duration_min so the quote engine
       prices the real route.

   If anything here fails, the manual distance field stays usable and
   the booking still completes — this layer is strictly an enhancement.
   =================================================================== */
(function () {
  'use strict';

  // Called by the Google Maps loader callback.
  window.plrInitMaps = function () {
    if (!window.google || !google.maps || !google.maps.places) return;

    var pickup  = document.getElementById('pickup_address');
    var dropoff = document.getElementById('dropoff_address');
    var kmField = document.getElementById('distance_km');
    var mnField = document.getElementById('duration_min');
    if (!pickup) return;

    var distanceWrap = document.querySelector('[data-field="distance"]');
    var hintEl       = null;

    // The manual distance box becomes a read-only, auto-filled display.
    if (distanceWrap && kmField) {
      kmField.readOnly = true;
      kmField.placeholder = 'Calculated automatically';
      var label = distanceWrap.querySelector('.field__label');
      if (label) label.textContent = 'Distance & travel time';
      hintEl = distanceWrap.querySelector('.field__hint');
      if (hintEl) hintEl.textContent = 'Filled in automatically once both addresses are set.';
    }

    var options = {
      componentRestrictions: { country: 'ca' },
      fields: ['formatted_address', 'geometry', 'name'],
      // Bias toward the Greater Toronto Area without excluding elsewhere.
      bounds: new google.maps.LatLngBounds(
        new google.maps.LatLng(43.40, -80.10),
        new google.maps.LatLng(44.10, -78.90)
      ),
      strictBounds: false
    };

    var acPickup  = new google.maps.places.Autocomplete(pickup, options);
    var acDropoff = dropoff ? new google.maps.places.Autocomplete(dropoff, options) : null;

    // Don't let Enter inside an autocomplete submit the form.
    [pickup, dropoff].forEach(function (el) {
      if (!el) return;
      el.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') ev.preventDefault();
      });
    });

    var service = new google.maps.DistanceMatrixService();
    var lastPair = '';

    function computeRoute() {
      if (!dropoff || !kmField) return;

      var a = pickup.value.trim();
      var b = dropoff.value.trim();
      if (a.length < 4 || b.length < 4) return;

      var pair = a + '||' + b;
      if (pair === lastPair) return;
      lastPair = pair;

      if (hintEl) hintEl.textContent = 'Calculating distance…';

      service.getDistanceMatrix({
        origins:      [a],
        destinations: [b],
        travelMode:   google.maps.TravelMode.DRIVING,
        unitSystem:   google.maps.UnitSystem.METRIC
      }, function (res, status) {

        if (status !== 'OK' || !res || !res.rows || !res.rows[0]) {
          onFailure();
          return;
        }

        var el = res.rows[0].elements && res.rows[0].elements[0];
        if (!el || el.status !== 'OK') { onFailure(); return; }

        var km  = el.distance.value / 1000;
        var min = el.duration.value / 60;

        kmField.value = km.toFixed(1);
        if (mnField) mnField.value = min.toFixed(0);

        if (hintEl) {
          hintEl.textContent = km.toFixed(1) + ' km · about ' +
            Math.round(min) + ' min in current conditions.';
        }

        // Nudge the wizard to re-price with the real route.
        kmField.dispatchEvent(new Event('change', { bubbles: true }));
      });
    }

    function onFailure() {
      // Fall back to letting the customer type a distance themselves.
      if (kmField) kmField.readOnly = false;
      if (hintEl) {
        hintEl.textContent =
          'We could not calculate that route automatically — you can enter an ' +
          'approximate distance, or leave it blank and we will confirm it.';
      }
    }

    acPickup.addListener('place_changed', computeRoute);
    if (acDropoff) acDropoff.addListener('place_changed', computeRoute);

    // Also try after manual typing settles.
    var t = null;
    [pickup, dropoff].forEach(function (el) {
      if (!el) return;
      el.addEventListener('input', function () {
        clearTimeout(t);
        t = setTimeout(computeRoute, 900);
      });
    });
  };

  // If the Maps script fails to load entirely, make sure the manual
  // distance field is definitely usable.
  window.addEventListener('error', function (ev) {
    var src = ev.target && ev.target.src;
    if (typeof src === 'string' && src.indexOf('maps.googleapis.com') !== -1) {
      var km = document.getElementById('distance_km');
      if (km) km.readOnly = false;
    }
  }, true);
})();
