<?php
/**
 * XML sitemap, generated from live data so new vehicles appear automatically.
 * Served at /sitemap.xml via the rewrite rule in .htaccess.
 */
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$base = rtrim(SITE_URL, '/');
$today = date('Y-m-d');

/** Last modified date of a file, or today. */
function page_date(string $file): string
{
    $t = @filemtime(ROOT_PATH . '/' . $file);
    return $t ? date('Y-m-d', $t) : date('Y-m-d');
}

$pages = [
    ['',              '1.0', 'weekly',  page_date('index.php')],
    ['about',         '0.7', 'monthly', page_date('about.php')],
    ['services',      '0.9', 'monthly', page_date('services.php')],
    ['fleet',         '0.9', 'weekly',  page_date('fleet.php')],
    ['rates',         '0.9', 'weekly',  page_date('rates.php')],
    ['rentals',       '0.8', 'weekly',  page_date('rentals.php')],
    ['booking',       '0.9', 'monthly', page_date('booking.php')],
    ['contact',       '0.7', 'monthly', page_date('contact.php')],
    ['privacy',       '0.3', 'yearly',  page_date('privacy.php')],
    ['terms',         '0.3', 'yearly',  page_date('terms.php')],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($pages as [$path, $priority, $freq, $mod]): ?>
  <url>
    <loc><?= e($base . ($path === '' ? '/' : '/' . $path)) ?></loc>
    <lastmod><?= e($mod) ?></lastmod>
    <changefreq><?= e($freq) ?></changefreq>
    <priority><?= e($priority) ?></priority>
  </url>
<?php endforeach; ?>
<?php
// Per-vehicle anchors on the fleet page help long-tail search.
try {
    foreach (get_vehicles() as $v):
        $mod = $v['updated_at'] ? date('Y-m-d', strtotime((string)$v['updated_at'])) : $today; ?>
  <url>
    <loc><?= e($base . '/fleet#' . $v['slug']) ?></loc>
    <lastmod><?= e($mod) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.6</priority>
  </url>
<?php endforeach;
} catch (Throwable $ex) {
    // A database problem must never break the sitemap.
}
?>
</urlset>
