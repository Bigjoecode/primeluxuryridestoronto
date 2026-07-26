<?php
/** Flat-rate editor, per vehicle. */
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();

$vehicles = get_vehicles(false);
if (!$vehicles) {
    flash('error', 'Add a vehicle before setting flat rates.');
    header('Location: vehicles.php');
    exit;
}

$vehicle_id = (int)($_GET['vehicle'] ?? ($_POST['vehicle_id'] ?? 0));
if ($vehicle_id <= 0) { $vehicle_id = (int)$vehicles[0]['id']; }

$vehicle = get_vehicle($vehicle_id);
if (!$vehicle) {
    flash('error', 'That vehicle could not be found.');
    header('Location: rates.php');
    exit;
}

/** Normalise a city name into the key used for matching. */
function city_key_from(string $city): string
{
    $k = mb_strtolower(trim($city));
    $k = preg_replace('~\s*/\s*~', ' / ', $k) ?? $k;
    // "Kitchener / Waterloo" → "kitchener";  "London, ON" → "london"
    $k = explode(',', $k)[0];
    $k = explode(' / ', $k)[0];
    return trim($k);
}

// ── Save ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('rates.php?vehicle=' . $vehicle_id);

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $prices    = (array)($_POST['price'] ?? []);
        $distances = (array)($_POST['distance'] ?? []);
        $updated   = 0;

        foreach ($prices as $rate_id => $raw) {
            $rate_id = (int)$rate_id;
            $raw     = trim((string)$raw);
            $price   = ($raw === '') ? null : max(0, (float)$raw);
            $dist    = max(0, (int)($distances[$rate_id] ?? 0));

            db_exec('UPDATE `flat_rates` SET `price` = ?, `distance_km` = ?
                     WHERE `id` = ? AND `vehicle_id` = ?',
                    [$price, $dist, $rate_id, $vehicle_id]);
            $updated++;
        }

        app_log('admin.log', $admin['email'] . ' updated ' . $updated . ' rates for ' . $vehicle['name']);
        flash('success', 'Flat rates for ' . $vehicle['name'] . ' were saved.');
        header('Location: rates.php?vehicle=' . $vehicle_id);
        exit;
    }

    if ($action === 'add') {
        $city = trim((string)($_POST['city'] ?? ''));
        $dist = max(0, (int)($_POST['new_distance'] ?? 0));
        $raw  = trim((string)($_POST['new_price'] ?? ''));
        $price = ($raw === '') ? null : max(0, (float)$raw);

        if ($city === '') {
            flash('error', 'Please enter a city name.');
        } else {
            $key = city_key_from($city);
            $dup = db_one('SELECT `id` FROM `flat_rates` WHERE `vehicle_id` = ? AND `city_key` = ? LIMIT 1',
                          [$vehicle_id, $key]);
            if ($dup) {
                flash('error', $city . ' already has a rate for this vehicle.');
            } else {
                $next = (int)(db_one('SELECT COALESCE(MAX(`sort_order`),0)+1 n FROM `flat_rates`
                                      WHERE `vehicle_id` = ?', [$vehicle_id])['n'] ?? 1);
                db_exec('INSERT INTO `flat_rates`
                          (`vehicle_id`,`city`,`city_key`,`distance_km`,`price`,`sort_order`)
                         VALUES (?,?,?,?,?,?)',
                        [$vehicle_id, $city, $key, $dist, $price, $next]);
                flash('success', $city . ' was added.');
            }
        }
        header('Location: rates.php?vehicle=' . $vehicle_id);
        exit;
    }

    if ($action === 'delete') {
        $rate_id = (int)($_POST['rate_id'] ?? 0);
        db_exec('DELETE FROM `flat_rates` WHERE `id` = ? AND `vehicle_id` = ?',
                [$rate_id, $vehicle_id]);
        flash('success', 'Destination removed.');
        header('Location: rates.php?vehicle=' . $vehicle_id);
        exit;
    }

    if ($action === 'copy') {
        $from_id = (int)($_POST['from_vehicle'] ?? 0);
        $from    = get_vehicle($from_id);

        if (!$from || $from_id === $vehicle_id) {
            flash('error', 'Choose a different vehicle to copy from.');
        } else {
            $copied = 0;
            foreach (get_flat_rates($from_id) as $r) {
                $exists = db_one('SELECT `id` FROM `flat_rates`
                                  WHERE `vehicle_id` = ? AND `city_key` = ? LIMIT 1',
                                 [$vehicle_id, $r['city_key']]);
                if ($exists) {
                    db_exec('UPDATE `flat_rates` SET `price` = ?, `distance_km` = ? WHERE `id` = ?',
                            [$r['price'], $r['distance_km'], (int)$exists['id']]);
                } else {
                    db_exec('INSERT INTO `flat_rates`
                              (`vehicle_id`,`city`,`city_key`,`distance_km`,`price`,`sort_order`)
                             VALUES (?,?,?,?,?,?)',
                            [$vehicle_id, $r['city'], $r['city_key'], $r['distance_km'],
                             $r['price'], $r['sort_order']]);
                }
                $copied++;
            }
            flash('success', $copied . ' rates copied from ' . $from['name'] . '.');
        }
        header('Location: rates.php?vehicle=' . $vehicle_id);
        exit;
    }
}

$rates     = get_flat_rates($vehicle_id);
$threshold = (int)setting_num('flat_rate_threshold_km', FLAT_RATE_THRESHOLD_KM);

$admin_page  = 'rates.php';
$admin_title = 'Flat Rates';
$admin_sub   = 'One-way published prices, before HST. Leave a price blank to use dynamic pricing.';

require __DIR__ . '/includes/header.php';
?>

<!-- Vehicle switcher -->
<div class="rate-tabs mb-6">
  <?php foreach ($vehicles as $v): ?>
  <a class="rate-tab" href="rates.php?vehicle=<?= (int)$v['id'] ?>"
     aria-selected="<?= (int)$v['id'] === $vehicle_id ? 'true' : 'false' ?>">
    <?= e($v['name']) ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="alert alert--info">
  <?= icon('info') ?>
  <span>Journeys of <strong><?= $threshold ?>&nbsp;km or more</strong> to a listed city use these
    flat rates. Shorter journeys, and any city left blank, use dynamic pricing
    (base + per-km + per-minute) from the vehicle&rsquo;s settings. HST is added on top at checkout.</span>
</div>

<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="vehicle_id" value="<?= $vehicle_id ?>">
  <input type="hidden" name="action" value="save">

  <div class="panel">
    <div class="panel__head">
      <h2 class="panel__title"><?= e($vehicle['name']) ?></h2>
      <button type="submit" class="btn btn--gold btn--sm">
        <?= icon('check') ?><span>Save all rates</span>
      </button>
    </div>

    <?php if (!$rates): ?>
      <div class="empty-state">
        <div class="empty-state__icon"><?= icon('tag') ?></div>
        <h3>No flat rates yet</h3>
        <p>Add destinations below, or copy the full rate card from another vehicle.</p>
      </div>
    <?php else: ?>
    <div class="panel__body panel__body--flush">
      <div class="table-scroll" style="border:0;border-radius:0;">
        <table class="data-table">
          <thead>
            <tr>
              <th scope="col">Destination from Toronto</th>
              <th scope="col" style="width:150px;">Distance (km)</th>
              <th scope="col" style="width:190px;">Price (CAD, ex HST)</th>
              <th scope="col" style="width:90px;"><span class="sr-only">Remove</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rates as $r): ?>
            <tr>
              <th scope="row" data-label="City" style="font-weight:600;">
                <?= e($r['city']) ?>
                <br><span class="muted" style="font-weight:400;">matches: <?= e($r['city_key']) ?></span>
              </th>
              <td data-label="Distance">
                <input class="input" type="number" name="distance[<?= (int)$r['id'] ?>]"
                       min="0" max="5000" inputmode="numeric"
                       value="<?= (int)$r['distance_km'] ?>"
                       aria-label="Distance to <?= e($r['city']) ?> in km">
              </td>
              <td data-label="Price">
                <input class="input" type="number" name="price[<?= (int)$r['id'] ?>]"
                       min="0" step="0.01" inputmode="decimal"
                       value="<?= $r['price'] !== null ? e(number_format((float)$r['price'], 2, '.', '')) : '' ?>"
                       placeholder="Dynamic pricing"
                       aria-label="Price to <?= e($r['city']) ?>">
              </td>
              <td>
                <div class="actions">
                  <button type="submit" class="btn btn--ghost btn--sm"
                          form="delete-<?= (int)$r['id'] ?>"
                          data-confirm="Remove <?= e($r['city']) ?> from this vehicle's rate card?"
                          aria-label="Remove <?= e($r['city']) ?>">
                    <?= icon('trash') ?>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel__body" style="border-top:1px solid var(--line);">
      <button type="submit" class="btn btn--gold">
        <?= icon('check') ?><span>Save all rates</span>
      </button>
      <span class="text-muted" style="font-size:var(--fs-sm);margin-left:var(--s-4);">
        Blank price = dynamic pricing for that city.
      </span>
    </div>
    <?php endif; ?>
  </div>
</form>

<!-- Delete forms (kept outside the main form so they submit independently) -->
<?php foreach ($rates as $r): ?>
<form method="post" id="delete-<?= (int)$r['id'] ?>" class="hide">
  <?= csrf_field() ?>
  <input type="hidden" name="vehicle_id" value="<?= $vehicle_id ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="rate_id" value="<?= (int)$r['id'] ?>">
</form>
<?php endforeach; ?>


<div class="grid grid--2">

  <div class="panel">
    <div class="panel__head"><h2 class="panel__title">Add a destination</h2></div>
    <div class="panel__body">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="vehicle_id" value="<?= $vehicle_id ?>">
        <input type="hidden" name="action" value="add">

        <div class="field">
          <label class="field__label" for="city">City name <span class="req">*</span></label>
          <input class="input" type="text" id="city" name="city"
                 placeholder="e.g. Montreal, QC" required>
          <span class="field__hint">Shown on the public rates page. Matching uses the first
            word before a comma or slash.</span>
        </div>

        <div class="field-row field-row--2">
          <div class="field">
            <label class="field__label" for="new_distance">Distance (km)</label>
            <input class="input" type="number" id="new_distance" name="new_distance"
                   min="0" max="5000" inputmode="numeric" placeholder="e.g. 540">
          </div>
          <div class="field">
            <label class="field__label" for="new_price">Price ($)</label>
            <input class="input" type="number" id="new_price" name="new_price"
                   min="0" step="0.01" inputmode="decimal" placeholder="Blank = dynamic">
          </div>
        </div>

        <button type="submit" class="btn btn--gold btn--block">
          <?= icon('plus') ?><span>Add destination</span>
        </button>
      </form>
    </div>
  </div>

  <div class="panel">
    <div class="panel__head"><h2 class="panel__title">Copy from another vehicle</h2></div>
    <div class="panel__body">
      <p class="text-muted mb-5" style="font-size:var(--fs-sm);">
        Copies the full rate card onto <strong><?= e($vehicle['name']) ?></strong>,
        overwriting any city they share. Useful when two vehicles sit in the same price tier.
      </p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="vehicle_id" value="<?= $vehicle_id ?>">
        <input type="hidden" name="action" value="copy">

        <div class="field">
          <label class="field__label" for="from_vehicle">Copy rates from</label>
          <select class="select" id="from_vehicle" name="from_vehicle">
            <?php foreach ($vehicles as $v):
              if ((int)$v['id'] === $vehicle_id) continue; ?>
            <option value="<?= (int)$v['id'] ?>"><?= e($v['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="btn btn--outline btn--block"
                data-confirm="Copy all rates onto <?= e($vehicle['name']) ?>? Shared cities will be overwritten.">
          <?= icon('download') ?><span>Copy rates</span>
        </button>
      </form>
    </div>
  </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
