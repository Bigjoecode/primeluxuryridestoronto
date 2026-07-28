<?php
/** Site imagery — hero and about-page photographs. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
$admin = require_admin();

$admin_page  = 'images.php';
$admin_title = 'Site Images';
$admin_sub   = 'The photographs that carry the whole look of the site.';

$errors = [];

/** Every slot this page manages. */
$slots = [
    'hero_image' => [
        'label' => 'Home page hero',
        'help'  => 'The first thing every visitor sees. A wide shot of a vehicle with a '
                 . 'chauffeur works best — dark or evening scenes suit the black-and-gold theme.',
        'specs' => '2400 × 1400 px or larger · landscape · JPG or WebP · under 8 MB',
        'note'  => 'Text sits over the left half, so keep that side uncluttered. '
                 . 'The site darkens the image automatically for legibility.',
    ],
    'about_image' => [
        'label' => 'About page',
        'help'  => 'A portrait of your team, a chauffeur beside a vehicle, or a detail shot '
                 . 'of an interior. Something human works better here than another car.',
        'specs' => '1600 × 1200 px or larger · landscape · JPG or WebP · under 8 MB',
        'note'  => 'Displayed in a 4:3 frame with rounded corners.',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('images.php');

    $key = (string)($_POST['slot'] ?? '');
    if (!isset($slots[$key])) {
        flash('error', 'Unknown image slot.');
        header('Location: images.php');
        exit;
    }

    $current = trim(setting($key, ''));

    if (($_POST['action'] ?? '') === 'remove') {
        upload_delete(site_image_path(), $current);
        db_exec('UPDATE `settings` SET `value` = ? WHERE `key_name` = ?', ['', $key]);
        flash('success', $slots[$key]['label'] . ' image removed.');
        header('Location: images.php');
        exit;
    }

    $err  = null;
    $name = upload_image($_FILES['image'] ?? [], site_image_path(), str_replace('_image', '', $key), $err);

    if ($err !== null) {
        $errors[] = $err;
    } elseif ($name === null) {
        $errors[] = 'Please choose an image to upload.';
    } else {
        upload_delete(site_image_path(), $current);   // replace, don't accumulate
        db_exec('UPDATE `settings` SET `value` = ? WHERE `key_name` = ?', [$name, $key]);
        app_log('admin.log', $admin['email'] . ' updated ' . $key);
        flash('success', $slots[$key]['label'] . ' image updated.');
        header('Location: images.php');
        exit;
    }
}

$vehicles_without = db_all('SELECT `id`, `name` FROM `vehicles`
                            WHERE `is_active` = 1 AND (`image` IS NULL OR `image` = "")
                            ORDER BY `sort_order`');

require __DIR__ . '/includes/header.php';
?>

<?php if ($errors): ?>
<div class="alert alert--error" role="alert">
  <?= icon('alert') ?>
  <div>
    <strong>Upload failed:</strong>
    <ul style="margin-top:var(--s-2);display:grid;gap:var(--s-1);">
      <?php foreach ($errors as $err): ?><li>&bull; <?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<div class="alert alert--gold">
  <?= icon('sparkles') ?>
  <span>Photography carries this site more than anything else. Until real images are in
    place the layout falls back to designed gradients and line art &mdash; presentable,
    but not what will win bookings.</span>
</div>

<?php if ($vehicles_without): ?>
<div class="alert alert--info">
  <?= icon('car') ?>
  <span><strong><?= count($vehicles_without) ?> vehicle<?= count($vehicles_without) === 1 ? '' : 's' ?>
    still <?= count($vehicles_without) === 1 ? 'has' : 'have' ?> no photograph:</strong>
    <?php $links = [];
      foreach ($vehicles_without as $v) {
          $links[] = '<a href="vehicle-edit.php?id=' . (int)$v['id'] . '">' . e($v['name']) . '</a>';
      }
      echo implode(', ', $links); ?>.
  </span>
</div>
<?php endif; ?>

<div class="grid grid--2">
  <?php foreach ($slots as $key => $slot):
    $url = site_image_url($key); ?>
  <div class="panel" style="margin-bottom:0;">
    <div class="panel__head">
      <h2 class="panel__title"><?= e($slot['label']) ?></h2>
      <?php if ($url): ?>
      <span class="badge-status badge-completed">Set</span>
      <?php else: ?>
      <span class="badge-status badge-pending">Not set</span>
      <?php endif; ?>
    </div>

    <div class="img-preview" style="border-radius:0;border:0;border-bottom:1px solid var(--line);margin:0;">
      <?php if ($url): ?>
        <img src="<?= e('..' . $url) ?>" alt="<?= e($slot['label']) ?>">
      <?php else: ?>
        <div style="display:grid;place-items:center;gap:var(--s-3);color:var(--text-dim);padding:var(--s-6);text-align:center;">
          <?= icon('sparkles', '', 34) ?>
          <span style="font-size:var(--fs-sm);">Using the designed fallback</span>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel__body">
      <p class="text-muted mb-4" style="font-size:var(--fs-sm);"><?= e($slot['help']) ?></p>

      <div class="fieldset-group" style="margin-bottom:var(--s-5);">
        <legend>Recommended</legend>
        <p style="font-size:var(--fs-sm);color:var(--gold);"><?= e($slot['specs']) ?></p>
        <p class="text-muted mt-3" style="font-size:var(--fs-xs);"><?= e($slot['note']) ?></p>
      </div>

      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="slot" value="<?= e($key) ?>">

        <div class="field">
          <label class="field__label" for="img_<?= e($key) ?>">
            <?= $url ? 'Replace image' : 'Upload image' ?>
          </label>
          <input class="input" type="file" id="img_<?= e($key) ?>" name="image"
                 accept="image/jpeg,image/png,image/webp,image/gif"
                 style="padding:var(--s-3);" required>
        </div>

        <button type="submit" class="btn btn--gold btn--block">
          <?= icon('check') ?><span><?= $url ? 'Replace' : 'Upload' ?></span>
        </button>
      </form>

      <?php if ($url): ?>
      <form method="post" class="mt-4">
        <?= csrf_field() ?>
        <input type="hidden" name="slot" value="<?= e($key) ?>">
        <input type="hidden" name="action" value="remove">
        <button type="submit" class="btn btn--ghost btn--block" style="color:#fca5a5;"
                data-confirm="Remove the <?= e($slot['label']) ?> image and go back to the fallback?">
          <?= icon('trash') ?><span>Remove image</span>
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="panel mt-6">
  <div class="panel__head"><h2 class="panel__title">Where each image appears</h2></div>
  <div class="panel__body">
    <div class="dl-grid">
      <div class="dl-item">
        <dt>Home hero</dt>
        <dd>Full-bleed behind the headline and search bar on the home page.
          Without it, a gold-tinted gradient is used.</dd>
      </div>
      <div class="dl-item">
        <dt>About page</dt>
        <dd>The framed panel beside &ldquo;Punctual, discreet, immaculate&rdquo;.
          Without it, the logo is shown instead.</dd>
      </div>
      <div class="dl-item">
        <dt>Vehicle photographs</dt>
        <dd>Managed per vehicle under <a href="vehicles.php">Vehicles</a>. These appear on
          the home page, fleet page, rentals and the booking form.</dd>
      </div>
      <div class="dl-item">
        <dt>Logo</dt>
        <dd>Replace <code>assets/img/logo.png</code> on the server to change the header,
          footer, favicon and email logo together.</dd>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
