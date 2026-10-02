<?php
/**
 * Sitemap XML généré dynamiquement depuis la base.
 */
header('Content-Type: application/xml; charset=utf-8');
$entries = [];
foreach (APP_LANGS as $lang) {
    current_lang($lang);
    foreach (['' => '1.0', 'a-propos' => '0.7', 'services' => '0.9', 'realisations' => '0.9', 'data' => '0.8', 'contact' => '0.8', 'mentions-legales' => '0.2', 'politique-de-confidentialite' => '0.2'] as $p => $prio) {
        $entries[] = [absolute_url($p, $lang), null, $prio];
    }
    foreach (get_services() as $s) {
        $entries[] = [absolute_url('services/' . $s['slug'], $lang), $s['updated_at'] ?: $s['created_at'], '0.8'];
    }
    foreach (get_projects() as $p) {
        $entries[] = [absolute_url('realisations/' . $p['slug'], $lang), $p['updated_at'] ?: $p['created_at'], '0.7'];
    }
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($entries as [$loc, $mod, $prio]): ?>
  <url>
    <loc><?= e($loc) ?></loc>
<?php if ($mod): ?>
    <lastmod><?= date('Y-m-d', strtotime($mod)) ?></lastmod>
<?php endif; ?>
    <priority><?= $prio ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
