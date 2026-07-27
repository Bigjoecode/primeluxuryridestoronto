<?php
/**
 * Pricing engine.
 * ------------------------------------------------------------------
 * Rules (from the client brief):
 *
 *   Distance < 40 km   →  dynamic:  base + (km × rate_km) + (min × rate_min)
 *   Distance >= 40 km  →  flat rate from the city table, when the
 *                         drop-off matches a known city; otherwise fall
 *                         back to dynamic so we never fail to quote.
 *   Hourly             →  hours × hourly_rate, hours forced to the
 *                         vehicle's minimum (S-Class/Escalade/Suburban 3h,
 *                         Maybach 4h).
 *   Rental             →  weeks × weekly + days × daily (cheapest split).
 *
 *   Then:  discount (Elite 30% / VIP 40%)  →  + 13% HST  →  total.
 */
require_once __DIR__ . '/functions.php';

/**
 * Normalise a free-text address into a city key we can match against
 * the flat_rates table. Returns '' when nothing recognisable is found.
 */
function normalise_city_key(string $address): string
{
    $a = mb_strtolower(trim($address), 'UTF-8');
    if ($a === '') {
        return '';
    }

    // Known aliases → canonical city_key used in the flat_rates table.
    $aliases = [
        'hamilton'      => ['hamilton', 'yhm', 'john c. munro'],
        'mississauga'   => ['mississauga', 'port credit', 'streetsville'],
        'brampton'      => ['brampton', 'bramalea'],
        'oshawa'        => ['oshawa', 'whitby', 'ajax', 'pickering'],
        'barrie'        => ['barrie', 'innisfil'],
        'kitchener'     => ['kitchener', 'waterloo', 'cambridge'],
        'niagara falls' => ['niagara falls', 'niagara-on-the-lake', 'niagara'],
        'london'        => ['london, on', 'london ontario', 'london'],
        'kingston'      => ['kingston'],
        'ottawa'        => ['ottawa', 'yow', 'gatineau'],
    ];

    foreach ($aliases as $key => $needles) {
        foreach ($needles as $n) {
            if (str_contains($a, $n)) {
                return $key;
            }
        }
    }
    return '';
}

/**
 * Look up a flat rate for a vehicle + destination address.
 * Returns the matching row, or null when there is no flat rate
 * (either the city is unknown, or the row is marked "Dynamic Pricing").
 */
function find_flat_rate(int $vehicle_id, string $dropoff_address, string $pickup_address = ''): ?array
{
    $key = normalise_city_key($dropoff_address);

    // If the drop-off is inside Toronto, the *pickup* may be the out-of-town
    // end of the trip (e.g. Ottawa → Toronto). Flat rates are symmetrical.
    if ($key === '' || $key === 'toronto') {
        $key = normalise_city_key($pickup_address);
    }
    if ($key === '') {
        return null;
    }

    $row = db_one('SELECT * FROM `flat_rates` WHERE `vehicle_id` = ? AND `city_key` = ? LIMIT 1',
                  [$vehicle_id, $key]);

    // price NULL means "Dynamic Pricing" on the published rate card.
    if ($row === null || $row['price'] === null || (float)$row['price'] <= 0) {
        return null;
    }
    return $row;
}

/**
 * Calculate a full quote.
 *
 * Input keys:
 *   service_type  airport|city|city_to_city|hourly|rental
 *   vehicle_id    int
 *   distance_km   float  (0 when unknown)
 *   duration_min  float  (0 when unknown)
 *   hours         int    (hourly bookings)
 *   days          int    (rentals)
 *   pickup        string
 *   dropoff       string
 *   membership    none|elite|vip
 *
 * Returned keys:
 *   ok, error, method, subtotal, discount, discount_percent,
 *   taxable, hst, hst_rate, total, lines[], notes[], vehicle
 *
 * @param  array $in
 * @return array
 */
function calculate_quote(array $in): array
{
    $out = [
        'ok'               => false,
        'error'            => null,
        'method'           => 'dynamic',
        'subtotal'         => 0.0,
        'discount'         => 0.0,
        'discount_percent' => 0.0,
        'taxable'          => 0.0,
        'hst'              => 0.0,
        'hst_rate'         => setting_num('hst_rate', DEFAULT_HST_RATE),
        'total'            => 0.0,
        'lines'            => [],
        'notes'            => [],
        'vehicle'          => null,
    ];

    $service    = (string)($in['service_type'] ?? '');
    $vehicle_id = (int)($in['vehicle_id'] ?? 0);
    $membership = $in['membership'] ?? 'none';
    if (!in_array($membership, ['none', 'elite', 'vip'], true)) {
        $membership = 'none';
    }

    $vehicle = $vehicle_id > 0 ? get_vehicle($vehicle_id) : null;
    if (!$vehicle || (int)$vehicle['is_active'] !== 1) {
        $out['error'] = 'Please choose a vehicle.';
        return $out;
    }
    $out['vehicle'] = $vehicle;

    // Service eligibility (Maybach: hourly + city-to-city only).
    if ($service !== 'rental' && !vehicle_allows($vehicle, $service)) {
        $out['error'] = $vehicle['name'] . ' is not available for '
                      . service_label($service) . '. It can be booked for '
                      . 'Hourly Chauffeur or City to City transfers.';
        return $out;
    }
    if ($service === 'rental' && (int)$vehicle['rental_available'] !== 1) {
        $out['error'] = $vehicle['name'] . ' is not available for rental.';
        return $out;
    }

    // ── Subtotal by service type ───────────────────────────────────
    switch ($service) {

        case 'hourly': {
            $min   = max(1, (int)$vehicle['min_hours']);
            $hours = max(0, (int)($in['hours'] ?? 0));
            if ($hours < $min) {
                $out['notes'][] = $vehicle['name'] . ' has a ' . $min
                                . '-hour minimum for hourly chauffeur hire.';
                $hours = $min;
            }
            $rate     = (float)$vehicle['hourly_rate'];
            $subtotal = $hours * $rate;

            $out['method']  = 'hourly';
            $out['hours']   = $hours;
            $out['lines'][] = ['label' => sprintf('%d hours × %s / hour', $hours, money_short($rate)),
                               'amount' => $subtotal];
            break;
        }

        case 'rental': {
            $days = max(1, (int)($in['days'] ?? 1));
            $daily  = (float)($vehicle['rental_daily']  ?? 0);
            $weekly = (float)($vehicle['rental_weekly'] ?? 0);
            if ($daily <= 0) {
                $out['error'] = 'Rental pricing is not configured for this vehicle. Please request a quote.';
                return $out;
            }

            $weeks     = ($weekly > 0) ? intdiv($days, 7) : 0;
            $rem_days  = $days - ($weeks * 7);
            $subtotal  = ($weeks * $weekly) + ($rem_days * $daily);

            // Never charge more than the plain daily rate would cost.
            $plain = $days * $daily;
            if ($plain < $subtotal) {
                $weeks = 0; $rem_days = $days; $subtotal = $plain;
            }

            $out['method'] = 'rental';
            $out['days']   = $days;
            if ($weeks > 0) {
                $out['lines'][] = ['label' => sprintf('%d week%s × %s', $weeks, $weeks > 1 ? 's' : '', money_short($weekly)),
                                   'amount' => $weeks * $weekly];
            }
            if ($rem_days > 0) {
                $out['lines'][] = ['label' => sprintf('%d day%s × %s', $rem_days, $rem_days > 1 ? 's' : '', money_short($daily)),
                                   'amount' => $rem_days * $daily];
            }
            break;
        }

        default: { // airport | city | city_to_city
            $km        = max(0.0, (float)($in['distance_km'] ?? 0));
            $min       = max(0.0, (float)($in['duration_min'] ?? 0));
            $threshold = (float)setting_num('flat_rate_threshold_km', FLAT_RATE_THRESHOLD_KM);

            $flat = null;
            if ($km >= $threshold || $km === 0.0) {
                $flat = find_flat_rate((int)$vehicle['id'],
                                       (string)($in['dropoff'] ?? ''),
                                       (string)($in['pickup']  ?? ''));
            }

            if ($flat !== null) {
                $subtotal = (float)$flat['price'];
                $out['method']  = 'flat';
                $out['lines'][] = ['label' => 'Flat rate — Toronto ⇄ ' . $flat['city']
                                              . ' (~' . (int)$flat['distance_km'] . ' km)',
                                   'amount' => $subtotal];
            } else {
                if ($km <= 0) {
                    $out['error'] = 'We need a pickup and drop-off address to calculate your fare.';
                    return $out;
                }
                $base     = (float)$vehicle['base_fare'];
                $rate_km  = (float)$vehicle['rate_per_km'];
                $rate_min = (float)$vehicle['rate_per_min'];

                // Estimate travel time when Maps did not supply one:
                // ~45 km/h average across the GTA.
                if ($min <= 0) {
                    $min = round($km / 45 * 60, 1);
                    $out['notes'][] = 'Travel time estimated at Toronto average traffic speed.';
                }

                $subtotal = $base + ($km * $rate_km) + ($min * $rate_min);

                $out['method']  = 'dynamic';
                $out['lines'][] = ['label' => 'Base fare',  'amount' => $base];
                $out['lines'][] = ['label' => sprintf('%.1f km × %s', $km, money_short($rate_km)),
                                   'amount' => $km * $rate_km];
                $out['lines'][] = ['label' => sprintf('%.0f min × %s', $min, money_short($rate_min)),
                                   'amount' => $min * $rate_min];
            }

            $out['distance_km']  = $km;
            $out['duration_min'] = $min;
            break;
        }
    }

    // ── Additional stops ───────────────────────────────────────────
    // Hourly hire already covers unlimited stops within the booked time.
    $stops = 0;
    if ($service !== 'hourly' && $service !== 'rental') {
        $max_stops = (int)setting_num('max_stops', 3);
        $stops     = max(0, min($max_stops, (int)($in['stops'] ?? 0)));
        $stop_fee  = setting_num('stop_fee', 15);

        if ($stops > 0 && $stop_fee > 0) {
            $stops_total = $stops * $stop_fee;
            $subtotal   += $stops_total;
            $out['lines'][] = [
                'label'  => sprintf('%d extra stop%s × %s', $stops, $stops > 1 ? 's' : '', money_short($stop_fee)),
                'amount' => $stops_total,
            ];
        }
    }
    $out['stops'] = $stops;

    // ── Return trip ────────────────────────────────────────────────
    // The return leg mirrors the outbound, so it costs the same before
    // any return discount. Not offered on rentals (a rental already has
    // a return date) or hourly hire (which is booked by duration).
    $is_return = !empty($in['is_return'])
              && $service !== 'rental'
              && $service !== 'hourly';

    if ($is_return) {
        $outbound = $subtotal;
        $subtotal += $outbound;
        $out['lines'][] = ['label' => 'Return leg', 'amount' => $outbound];

        $ret_pct = max(0.0, min(100.0, setting_num('return_discount', 10)));
        if ($ret_pct > 0) {
            $ret_off   = round($subtotal * $ret_pct / 100, 2);
            $subtotal -= $ret_off;
            $out['lines'][] = [
                'label'  => sprintf('Return-trip discount (%s%%)', rtrim(rtrim(number_format($ret_pct, 1), '0'), '.')),
                'amount' => -$ret_off,
            ];
            $out['return_discount'] = $ret_off;
        }
    }
    $out['is_return'] = $is_return;

    // ── Discount → HST → total ─────────────────────────────────────
    $subtotal = round((float)$subtotal, 2);

    $pct = 0.0;
    if ($membership === 'elite') { $pct = setting_num('elite_discount', 30); }
    if ($membership === 'vip')   { $pct = setting_num('vip_discount',   40); }
    $pct = max(0.0, min(100.0, $pct));

    $discount = round($subtotal * $pct / 100, 2);
    $taxable  = round($subtotal - $discount, 2);

    $hst_rate = max(0.0, setting_num('hst_rate', DEFAULT_HST_RATE));
    $hst      = round($taxable * $hst_rate / 100, 2);
    $total    = round($taxable + $hst, 2);

    $out['ok']               = true;
    $out['subtotal']         = $subtotal;
    $out['membership']       = $membership;
    $out['discount']         = $discount;
    $out['discount_percent'] = $pct;
    $out['taxable']          = $taxable;
    $out['hst_rate']         = $hst_rate;
    $out['hst']              = $hst;
    $out['total']            = $total;

    return $out;
}

/**
 * Compact JSON snapshot stored on the booking row so a quote can always
 * be reproduced exactly as the customer saw it.
 */
function quote_snapshot(array $q): string
{
    return (string)json_encode([
        'method'           => $q['method']           ?? null,
        'lines'            => $q['lines']            ?? [],
        'subtotal'         => $q['subtotal']         ?? 0,
        'membership'       => $q['membership']       ?? 'none',
        'discount'         => $q['discount']         ?? 0,
        'discount_percent' => $q['discount_percent'] ?? 0,
        'hst_rate'         => $q['hst_rate']         ?? 0,
        'hst'              => $q['hst']              ?? 0,
        'total'            => $q['total']            ?? 0,
        'notes'            => $q['notes']            ?? [],
        'distance_km'      => $q['distance_km']      ?? null,
        'duration_min'     => $q['duration_min']     ?? null,
        'hours'            => $q['hours']            ?? null,
        'days'             => $q['days']             ?? null,
        'stops'            => $q['stops']            ?? 0,
        'is_return'        => $q['is_return']        ?? false,
        'return_discount'  => $q['return_discount']  ?? 0,
    ], JSON_UNESCAPED_UNICODE);
}
