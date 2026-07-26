<?php
/** Add / edit a vehicle, including photo upload. */
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();

$id      = (int)($_GET['id'] ?? 0);
$vehicle = $id > 0 ? get_vehicle($id) : null;
$is_new  = ($vehicle === null);

if ($id > 0 && !$vehicle) {
    flash('error', 'That vehicle could not be found.');
    header('Location: vehicles.php');
    exit;
}

$errors = [];

// Defaults for a new vehicle
$data = $vehicle ?? [
    'slug' => '', 'name' => '', 'class_label' => 'Executive Sedan', 'tagline' => '',
    'description' => '', 'passengers' => 3, 'luggage' => 3, 'image' => null, 'features' => '',
    'base_fare' => 20.00, 'rate_per_km' => 2.25, 'rate_per_min' => 0.95,
    'hourly_rate' => 100.00, 'min_hours' => 3,
    'rental_daily' => null, 'rental_weekly' => null, 'rental_available' => 0,
    'allow_airport' => 1, 'allow_city' => 1, 'allow_city_to_city' => 1, 'allow_hourly' => 1,
    'sort_order' => 99, 'is_active' => 1,
];

/** Make a URL-safe slug. */
function make_slug(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

/**
 * Handle an uploaded photo. Returns the stored filename, or null.
 * Validates by actual image content, not by the client-supplied name.
 */
function handle_upload(array $file, ?string &$error): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'The photo could not be uploaded (error code ' . (int)$file['error'] . ').';
        return null;
    }
    if ($file['size'] > 6 * 1024 * 1024) {
        $error = 'That photo is larger than 6 MB. Please upload a smaller file.';
        return null;
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        $error = 'That file is not a readable image.';
        return null;
    }

    $ext = [
        IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif',
    ][$info[2]] ?? null;

    if ($ext === null) {
        $error = 'Please upload a JPG, PNG, WebP or GIF image.';
        return null;
    }

    if (!is_dir(UPLOAD_PATH) && !@mkdir(UPLOAD_PATH, 0775, true)) {
        $error = 'The upload folder could not be created. Check folder permissions.';
        return null;
    }

    $name = 'vehicle-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!@move_uploaded_file($file['tmp_name'], UPLOAD_PATH . '/' . $name)) {
        $error = 'The photo could not be saved. Check folder permissions on /uploads/vehicles.';
        return null;
    }
    return $name;
}

// ── Save ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_check($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please save again.';
    } else {
        $data = array_merge($data, [
            'name'         => trim((string)($_POST['name'] ?? '')),
            'class_label'  => trim((string)($_POST['class_label'] ?? '')),
            'tagline'      => trim((string)($_POST['tagline'] ?? '')),
            'description'  => trim((string)($_POST['description'] ?? '')),
            'features'     => trim((string)($_POST['features'] ?? '')),
            'passengers'   => max(1, (int)($_POST['passengers'] ?? 1)),
            'luggage'      => max(0, (int)($_POST['luggage'] ?? 0)),
            'base_fare'    => max(0, (float)($_POST['base_fare'] ?? 0)),
            'rate_per_km'  => max(0, (float)($_POST['rate_per_km'] ?? 0)),
            'rate_per_min' => max(0, (float)($_POST['rate_per_min'] ?? 0)),
            'hourly_rate'  => max(0, (float)($_POST['hourly_rate'] ?? 0)),
            'min_hours'    => max(1, (int)($_POST['min_hours'] ?? 1)),
            'rental_daily'  => ($_POST['rental_daily']  ?? '') === '' ? null : max(0, (float)$_POST['rental_daily']),
            'rental_weekly' => ($_POST['rental_weekly'] ?? '') === '' ? null : max(0, (float)$_POST['rental_weekly']),
            'rental_available'   => isset($_POST['rental_available']) ? 1 : 0,
            'allow_airport'      => isset($_POST['allow_airport']) ? 1 : 0,
            'allow_city'         => isset($_POST['allow_city']) ? 1 : 0,
            'allow_city_to_city' => isset($_POST['allow_city_to_city']) ? 1 : 0,
            'allow_hourly'       => isset($_POST['allow_hourly']) ? 1 : 0,
            'is_active'    => isset($_POST['is_active']) ? 1 : 0,
            'sort_order'   => (int)($_POST['sort_order'] ?? 99),
        ]);

        if ($data['name'] === '')        { $errors[] = 'Please enter the vehicle name.'; }
        if ($data['class_label'] === '') { $errors[] = 'Please enter a vehicle class (e.g. Executive Sedan).'; }
        if ($data['hourly_rate'] <= 0)   { $errors[] = 'Please enter an hourly rate greater than zero.'; }
        if ($data['rental_available'] === 1 && ($data['rental_daily'] === null || $data['rental_daily'] <= 0)) {
            $errors[] = 'Enter a daily rental price, or untick "Available to rent".';
        }

        // Slug
        $slug = make_slug((string)($_POST['slug'] ?? '')) ?: make_slug($data['name']);
        if ($slug === '') { $slug = 'vehicle-' . time(); }
        $clash = db_one('SELECT `id` FROM `vehicles` WHERE `slug` = ? AND `id` <> ? LIMIT 1',
                        [$slug, $id]);
        if ($clash) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }
        $data['slug'] = $slug;

        // Photo
        $upload_error = null;
        $new_image = handle_upload($_FILES['image'] ?? [], $upload_error);
        if ($upload_error) { $errors[] = $upload_error; }

        if (!empty($_POST['remove_image']) && $vehicle && !empty($vehicle['image'])) {
            @unlink(UPLOAD_PATH . '/' . basename((string)$vehicle['image']));
            $data['image'] = null;
        }
        if ($new_image !== null) {
            if ($vehicle && !empty($vehicle['image'])) {
                @unlink(UPLOAD_PATH . '/' . basename((string)$vehicle['image']));
            }
            $data['image'] = $new_image;
        }

        if (!$errors) {
            $cols = ['slug','name','class_label','tagline','description','passengers','luggage',
                     'image','features','base_fare','rate_per_km','rate_per_min','hourly_rate',
                     'min_hours','rental_daily','rental_weekly','rental_available',
                     'allow_airport','allow_city','allow_city_to_city','allow_hourly',
                     'sort_order','is_active'];

            $values = array_map(fn($c) => $data[$c], $cols);

            try {
                if ($is_new) {
                    $ph = implode(',', array_fill(0, count($cols), '?'));
                    db_exec('INSERT INTO `vehicles` (`' . implode('`,`', $cols) . "`) VALUES ($ph)", $values);
                    $newId = (int)db()->lastInsertId();
                    app_log('admin.log', $admin['email'] . ' created vehicle ' . $data['name']);
                    flash('success', $data['name'] . ' was added. Now set its flat rates.');
                    header('Location: rates.php?vehicle=' . $newId);
                    exit;
                }

                $set = implode(' = ?, ', $cols) . ' = ?';
                $values[] = $id;
                db_exec("UPDATE `vehicles` SET $set WHERE `id` = ?", $values);
                app_log('admin.log', $admin['email'] . ' updated vehicle ' . $data['name']);
                flash('success', $data['name'] . ' was saved.');
                header('Location: vehicles.php');
                exit;

            } catch (Throwable $ex) {
                app_log('errors.log', 'vehicle save failed: ' . $ex->getMessage());
                $errors[] = 'The vehicle could not be saved. Please try again.';
            }
        }
    }
}

$admin_page  = 'vehicles.php';
$admin_title = $is_new ? 'Add vehicle' : 'Edit ' . $data['name'];
$admin_sub   = $is_new ? 'Add a new vehicle to your fleet.' : 'Update details, pricing and availability.';
$admin_actions = '<a class="btn btn--outline btn--sm" href="vehicles.php">'
               . icon('arrow-left') . '<span>All vehicles</span></a>';

$current_image = $vehicle ? vehicle_image_url($data) : null;

require __DIR__ . '/includes/header.php';
?>

<?php if ($errors): ?>
<div class="alert alert--error" role="alert">
  <?= icon('alert') ?>
  <div>
    <strong>Please check the following:</strong>
    <ul style="margin-top:var(--s-2);display:grid;gap:var(--s-1);">
      <?php foreach ($errors as $err): ?><li>&bull; <?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div style="display:grid;gap:var(--s-6);align-items:start;" class="veh-layout">

    <!-- ══ MAIN ════════════════════════════════════════════════════ -->
    <div>
      <div class="panel">
        <div class="panel__head"><h2 class="panel__title">Vehicle details</h2></div>
        <div class="panel__body">

          <div class="field">
            <label class="field__label" for="name">Vehicle name <span class="req">*</span></label>
            <input class="input" type="text" id="name" name="name"
                   value="<?= e((string)$data['name']) ?>"
                   placeholder="e.g. Mercedes-Benz S580" required>
          </div>

          <div class="field-row field-row--2">
            <div class="field">
              <label class="field__label" for="class_label">Class <span class="req">*</span></label>
              <input class="input" type="text" id="class_label" name="class_label"
                     value="<?= e((string)$data['class_label']) ?>"
                     placeholder="Executive Sedan / Luxury SUV / Ultra-Luxury" required>
            </div>
            <div class="field">
              <label class="field__label" for="slug">URL name</label>
              <input class="input" type="text" id="slug" name="slug"
                     value="<?= e((string)$data['slug']) ?>" placeholder="Auto-generated from the name">
              <span class="field__hint">Used in links. Leave blank to generate automatically.</span>
            </div>
          </div>

          <div class="field">
            <label class="field__label" for="tagline">Tagline</label>
            <input class="input" type="text" id="tagline" name="tagline"
                   value="<?= e((string)$data['tagline']) ?>"
                   placeholder="One short line shown under the vehicle name">
          </div>

          <div class="field">
            <label class="field__label" for="description">Description</label>
            <textarea class="textarea" id="description" name="description"
                      placeholder="A paragraph describing the vehicle and who it suits."><?= e((string)$data['description']) ?></textarea>
          </div>

          <div class="field">
            <label class="field__label" for="features">Features</label>
            <textarea class="textarea" id="features" name="features" style="min-height:160px;"
                      placeholder="One feature per line"><?= e((string)$data['features']) ?></textarea>
            <span class="field__hint">One per line. Each appears as a ticked bullet on the website.</span>
          </div>

          <div class="field-row field-row--2">
            <div class="field">
              <label class="field__label" for="passengers">Passengers</label>
              <input class="input" type="number" id="passengers" name="passengers" min="1" max="20"
                     value="<?= (int)$data['passengers'] ?>" inputmode="numeric">
            </div>
            <div class="field">
              <label class="field__label" for="luggage">Luggage (bags)</label>
              <input class="input" type="number" id="luggage" name="luggage" min="0" max="20"
                     value="<?= (int)$data['luggage'] ?>" inputmode="numeric">
            </div>
          </div>

        </div>
      </div>

      <div class="panel">
        <div class="panel__head"><h2 class="panel__title">Pricing</h2></div>
        <div class="panel__body">

          <fieldset class="fieldset-group">
            <legend>Dynamic pricing &mdash; short trips</legend>
            <p class="text-muted mb-5" style="font-size:var(--fs-sm);">
              Used for journeys under
              <?= (int)setting_num('flat_rate_threshold_km', FLAT_RATE_THRESHOLD_KM) ?>&nbsp;km:
              <strong>base + (km × rate) + (min × rate)</strong>.
            </p>
            <div class="field-row field-row--3">
              <div class="field">
                <label class="field__label" for="base_fare">Base fare ($)</label>
                <input class="input" type="number" id="base_fare" name="base_fare"
                       min="0" step="0.01" inputmode="decimal"
                       value="<?= e(number_format((float)$data['base_fare'], 2, '.', '')) ?>">
              </div>
              <div class="field">
                <label class="field__label" for="rate_per_km">Per km ($)</label>
                <input class="input" type="number" id="rate_per_km" name="rate_per_km"
                       min="0" step="0.01" inputmode="decimal"
                       value="<?= e(number_format((float)$data['rate_per_km'], 2, '.', '')) ?>">
              </div>
              <div class="field">
                <label class="field__label" for="rate_per_min">Per minute ($)</label>
                <input class="input" type="number" id="rate_per_min" name="rate_per_min"
                       min="0" step="0.01" inputmode="decimal"
                       value="<?= e(number_format((float)$data['rate_per_min'], 2, '.', '')) ?>">
              </div>
            </div>
          </fieldset>

          <fieldset class="fieldset-group">
            <legend>Hourly chauffeur hire</legend>
            <div class="field-row field-row--2">
              <div class="field">
                <label class="field__label" for="hourly_rate">Hourly rate ($) <span class="req">*</span></label>
                <input class="input" type="number" id="hourly_rate" name="hourly_rate"
                       min="0" step="0.01" inputmode="decimal"
                       value="<?= e(number_format((float)$data['hourly_rate'], 2, '.', '')) ?>" required>
              </div>
              <div class="field">
                <label class="field__label" for="min_hours">Minimum hours</label>
                <input class="input" type="number" id="min_hours" name="min_hours"
                       min="1" max="24" inputmode="numeric" value="<?= (int)$data['min_hours'] ?>">
                <span class="field__hint">Bookings below this are raised to the minimum automatically.</span>
              </div>
            </div>
          </fieldset>

          <fieldset class="fieldset-group" style="margin-bottom:0;">
            <legend>Self-drive rental</legend>
            <div class="checkbox-row mb-5">
              <input type="checkbox" id="rental_available" name="rental_available" value="1"
                     <?= (int)$data['rental_available'] === 1 ? 'checked' : '' ?>>
              <label for="rental_available">Available to rent (shows on the Rentals page)</label>
            </div>
            <div class="field-row field-row--2">
              <div class="field">
                <label class="field__label" for="rental_daily">Daily rate ($)</label>
                <input class="input" type="number" id="rental_daily" name="rental_daily"
                       min="0" step="0.01" inputmode="decimal"
                       value="<?= $data['rental_daily'] !== null ? e(number_format((float)$data['rental_daily'], 2, '.', '')) : '' ?>">
              </div>
              <div class="field">
                <label class="field__label" for="rental_weekly">Weekly rate ($)</label>
                <input class="input" type="number" id="rental_weekly" name="rental_weekly"
                       min="0" step="0.01" inputmode="decimal"
                       value="<?= $data['rental_weekly'] !== null ? e(number_format((float)$data['rental_weekly'], 2, '.', '')) : '' ?>">
              </div>
            </div>
          </fieldset>

        </div>
      </div>
    </div>

    <!-- ══ SIDE ════════════════════════════════════════════════════ -->
    <div>
      <div class="panel">
        <div class="panel__head"><h2 class="panel__title">Photo</h2></div>
        <div class="panel__body">
          <div class="img-preview">
            <?php if ($current_image): ?>
              <img src="<?= e($current_image) ?>" alt="<?= e((string)$data['name']) ?>">
            <?php else: ?>
              <div class="vehicle-placeholder">
                <?= vehicle_placeholder_svg((string)$data['class_label']) ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="field">
            <label class="field__label" for="image">Upload a photo</label>
            <input class="input" type="file" id="image" name="image"
                   accept="image/jpeg,image/png,image/webp,image/gif"
                   style="padding:var(--s-3);">
            <span class="field__hint">JPG, PNG, WebP or GIF. Max 6 MB.
              Landscape at roughly 1600×1000 works best.</span>
          </div>

          <?php if ($current_image): ?>
          <div class="checkbox-row">
            <input type="checkbox" id="remove_image" name="remove_image" value="1">
            <label for="remove_image">Remove the current photo</label>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="panel">
        <div class="panel__head"><h2 class="panel__title">Available for</h2></div>
        <div class="panel__body" style="display:grid;gap:var(--s-4);">
          <?php
          $flags = [
            'allow_airport'      => 'Airport transfers',
            'allow_city'         => 'In-city rides',
            'allow_city_to_city' => 'City to city transfers',
            'allow_hourly'       => 'Hourly chauffeur hire',
          ];
          foreach ($flags as $key => $label): ?>
          <div class="checkbox-row">
            <input type="checkbox" id="<?= e($key) ?>" name="<?= e($key) ?>" value="1"
                   <?= (int)$data[$key] === 1 ? 'checked' : '' ?>>
            <label for="<?= e($key) ?>"><?= e($label) ?></label>
          </div>
          <?php endforeach; ?>
          <p class="text-muted" style="font-size:var(--fs-xs);">
            Unticked services hide this vehicle in the booking form and reject it server-side.
            The Maybach, for example, is hourly and city-to-city only.
          </p>
        </div>
      </div>

      <div class="panel">
        <div class="panel__head"><h2 class="panel__title">Visibility</h2></div>
        <div class="panel__body">
          <div class="checkbox-row mb-5">
            <input type="checkbox" id="is_active" name="is_active" value="1"
                   <?= (int)$data['is_active'] === 1 ? 'checked' : '' ?>>
            <label for="is_active">Show on the website</label>
          </div>
          <div class="field" style="margin-bottom:0;">
            <label class="field__label" for="sort_order">Display order</label>
            <input class="input" type="number" id="sort_order" name="sort_order"
                   min="0" max="999" inputmode="numeric" value="<?= (int)$data['sort_order'] ?>">
            <span class="field__hint">Lower numbers appear first.</span>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel__body">
          <button type="submit" class="btn btn--gold btn--block btn--lg">
            <?= icon('check') ?><span><?= $is_new ? 'Add vehicle' : 'Save changes' ?></span>
          </button>
          <a href="vehicles.php" class="btn btn--ghost btn--block mt-4">Cancel</a>
        </div>
      </div>
    </div>

  </div>
</form>

<style>
  @media (min-width: 1100px) {
    .veh-layout { grid-template-columns: minmax(0, 1fr) 380px; }
  }
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
