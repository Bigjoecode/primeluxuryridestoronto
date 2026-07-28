<?php
/** Contact / quote enquiries inbox. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/customer.php';
$admin = require_admin();

$admin_page  = 'enquiries.php';
$admin_title = 'Enquiries';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('enquiries.php');

    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'read' && $id > 0) {
        db_exec('UPDATE `enquiries` SET `is_read` = 1 - `is_read` WHERE `id` = ?', [$id]);
    } elseif ($action === 'delete' && $id > 0) {
        db_exec('DELETE FROM `enquiries` WHERE `id` = ?', [$id]);
        flash('success', 'Enquiry deleted.');
    } elseif ($action === 'grant' && $id > 0) {
        // Approve a membership application straight from the inbox.
        $q = db_one('SELECT * FROM `enquiries` WHERE `id` = ? LIMIT 1', [$id]);
        $tier = (string)($_POST['tier'] ?? '');

        if (!$q || !in_array($tier, ['none', 'elite', 'vip'], true)) {
            flash('error', 'That application could not be found.');
        } else {
            // Prefer the linked account; fall back to matching on email.
            $target = !empty($q['customer_id'])
                ? db_one('SELECT * FROM `customers` WHERE `id` = ? LIMIT 1', [(int)$q['customer_id']])
                : db_one('SELECT * FROM `customers` WHERE `email` = ? LIMIT 1', [(string)$q['email']]);

            if (!$target) {
                flash('error', 'No customer account exists for ' . $q['email']
                    . '. Ask them to create one first, then grant the tier from Customers.');
            } else {
                db_exec('UPDATE `customers` SET `membership_tier` = ? WHERE `id` = ?',
                        [$tier, (int)$target['id']]);
                db_exec('UPDATE `enquiries` SET `is_read` = 1 WHERE `id` = ?', [$id]);

                app_log('admin.log', sprintf('%s granted %s to %s',
                        $admin['email'], $tier, $target['email']));

                flash('success', $target['full_name'] . ' is now '
                    . membership_label($tier) . '. The discount applies to their next booking.');
            }
        }
    } elseif ($action === 'read_all') {
        db_exec('UPDATE `enquiries` SET `is_read` = 1 WHERE `is_read` = 0');
        flash('success', 'All enquiries marked as read.');
    }
    header('Location: enquiries.php');
    exit;
}

$filter = (string)($_GET['filter'] ?? '');
$where  = '';
if ($filter === 'unread')     { $where = 'WHERE `is_read` = 0'; }
if ($filter === 'membership') { $where = "WHERE `kind` = 'membership'"; }
$rows   = db_all("SELECT * FROM `enquiries` $where ORDER BY `created_at` DESC LIMIT 200");
$unread = (int)(db_one('SELECT COUNT(*) n FROM `enquiries` WHERE `is_read` = 0')['n'] ?? 0);

$admin_sub = $unread > 0
    ? $unread . ' unread of ' . count($rows) . ' shown'
    : count($rows) . ' enquir' . (count($rows) === 1 ? 'y' : 'ies');

require __DIR__ . '/includes/header.php';
?>

<div class="rate-tabs mb-6">
  <a class="rate-tab" href="enquiries.php" aria-selected="<?= $filter === '' ? 'true' : 'false' ?>">
    All enquiries
  </a>
  <a class="rate-tab" href="enquiries.php?filter=unread" aria-selected="<?= $filter === 'unread' ? 'true' : 'false' ?>">
    Unread<?= $unread > 0 ? ' (' . $unread . ')' : '' ?>
  </a>
  <?php $n_member = (int)(db_one("SELECT COUNT(*) n FROM `enquiries` WHERE `kind` = 'membership'")['n'] ?? 0); ?>
  <a class="rate-tab" href="enquiries.php?filter=membership" aria-selected="<?= $filter === 'membership' ? 'true' : 'false' ?>">
    Membership<?= $n_member > 0 ? ' (' . $n_member . ')' : '' ?>
  </a>
  <?php if ($unread > 0): ?>
  <form method="post" style="display:inline;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="read_all">
    <button type="submit" class="rate-tab"><?= icon('check') ?> Mark all read</button>
  </form>
  <?php endif; ?>
</div>

<?php if (!$rows): ?>
<div class="panel">
  <div class="empty-state">
    <div class="empty-state__icon"><?= icon('inbox') ?></div>
    <h3><?= $filter === 'unread' ? 'Nothing unread' : 'No enquiries yet' ?></h3>
    <p><?= $filter === 'unread'
          ? 'You’re all caught up.'
          : 'Messages sent through the contact form will appear here, and you’ll be emailed a copy.' ?></p>
  </div>
</div>
<?php else: ?>

<div style="display:grid;gap:var(--s-4);">
  <?php foreach ($rows as $q): ?>
  <div class="panel" style="margin-bottom:0;<?= (int)$q['is_read'] === 0 ? 'border-color:var(--gold-line);' : '' ?>">
    <div class="panel__head">
      <div style="min-width:0;">
        <h2 class="panel__title" style="font-size:var(--fs-lg);">
          <?= e((string)($q['subject'] ?: 'Enquiry')) ?>
          <?php if ((int)$q['is_read'] === 0): ?>
          <span class="badge-status badge-pending" style="margin-left:var(--s-3);">New</span>
          <?php endif; ?>
        </h2>
        <p class="muted" style="font-size:var(--fs-sm);">
          <?= e($q['full_name']) ?> &middot; <?= e(fmt_datetime($q['created_at'])) ?>
        </p>
      </div>
    </div>

    <div class="panel__body">
      <div class="dl-grid mb-5">
        <div class="dl-item"><dt>Email</dt>
          <dd><a class="text-gold" href="mailto:<?= e($q['email']) ?>"><?= e($q['email']) ?></a></dd></div>
        <div class="dl-item"><dt>Phone</dt>
          <dd><?= $q['phone']
                 ? '<a class="text-gold" href="tel:' . e(preg_replace('/[^\d+]/', '', (string)$q['phone'])) . '">' . e($q['phone']) . '</a>'
                 : '—' ?></dd></div>
      </div>

      <div class="fieldset-group" style="margin-bottom:var(--s-5);">
        <legend>Message</legend>
        <p class="text-muted" style="font-size:var(--fs-sm);"><?= e_nl($q['message']) ?></p>
      </div>

      <?php if ($q['kind'] === 'membership'): ?>
      <div class="fieldset-group" style="border-color:var(--gold-line);margin-bottom:var(--s-5);">
        <legend>Membership application</legend>
        <p class="text-muted mb-4" style="font-size:var(--fs-sm);">
          Requested <strong class="text-gold"><?= e(membership_label((string)($q['requested_tier'] ?: 'elite'))) ?></strong>.
          Granting sets the tier on their account so the discount applies automatically.
        </p>
        <form method="post" style="display:flex;gap:var(--s-3);flex-wrap:wrap;align-items:center;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="grant">
          <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
          <select class="select" name="tier" style="min-height:44px;flex:1;min-width:160px;">
            <?php foreach (['elite' => 'Elite Member', 'vip' => 'VIP Member', 'none' => 'Decline (Standard)'] as $v => $l): ?>
            <option value="<?= e($v) ?>" <?= ($q['requested_tier'] ?? '') === $v ? 'selected' : '' ?>>
              <?= e($l) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn--gold btn--sm"
                  data-confirm="Set this membership tier on <?= e($q['email']) ?>?">
            <?= icon('crown') ?><span>Apply tier</span>
          </button>
        </form>
      </div>
      <?php endif; ?>

      <div style="display:flex;gap:var(--s-3);flex-wrap:wrap;">
        <a class="btn btn--gold btn--sm"
           href="mailto:<?= e($q['email']) ?>?subject=<?= rawurlencode('Re: ' . (string)($q['subject'] ?: 'Your enquiry') . ' — ' . SITE_NAME) ?>">
          <?= icon('mail') ?><span>Reply</span>
        </a>

        <?php if ($q['phone']): ?>
        <a class="btn btn--outline btn--sm" target="_blank" rel="noopener"
           href="https://wa.me/<?= e(preg_replace('/\D+/', '', (string)$q['phone'])) ?>">
          <?= icon('whatsapp') ?><span>WhatsApp</span>
        </a>
        <?php endif; ?>

        <form method="post" style="display:contents;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="read">
          <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
          <button type="submit" class="btn btn--outline btn--sm">
            <?= icon('check') ?><span>Mark <?= (int)$q['is_read'] === 0 ? 'read' : 'unread' ?></span>
          </button>
        </form>

        <form method="post" style="display:contents;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
          <button type="submit" class="btn btn--ghost btn--sm"
                  style="color:#fca5a5;margin-left:auto;"
                  data-confirm="Delete this enquiry from <?= e($q['full_name']) ?>?">
            <?= icon('trash') ?><span>Delete</span>
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
