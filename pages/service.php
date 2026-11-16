<?php
/**
 * Fiche d'un service : /services/{slug}
 */
$service = get_service($params['slug'] ?? '');
if (!$service) {
    http_response_code(404);
    require ROOT_PATH . '/pages/404.php';
    return;
}

$meta['title'] = $service['seo_title'] ?: $service['name'] . ' | ' . site_name();
$meta['description'] = $service['seo_description'] ?: excerpt($service['short_description'], 160);
if ($service['image']) $meta['image'] = $service['image'];
$meta['schema'][] = [
    '@context' => 'https://schema.org', '@type' => 'Service',
    'name' => $service['name'], 'description' => excerpt($service['short_description'], 300),
    'provider' => ['@type' => 'Organization', 'name' => site_name()],
    'url' => absolute_url('services/' . $service['slug']),
];
$isData = str_contains($service['slug'], 'data');
$others = array_filter(get_services(), fn($s) => $s['id'] !== $service['id']);
$projects = get_projects(3, true);

require ROOT_PATH . '/includes/header.php';
partial('page-hero', ['eyebrow' => $service['eyebrow'], 'title' => $service['name'], 'text' => $service['short_description'], 'breadcrumb' => [[t('nav.services'), url('services')], [$service['name'], null]]]);
?>

<section class="section">
    <div class="container service-detail">
        <div class="prose" data-reveal>
            <?= rich_text($service['description']) ?>
            <div class="btn-group">
                <a class="btn btn-primary" href="<?= e(url('contact')) ?>?type=<?= e(rawurlencode($service['name'])) ?>#formulaire"><?= e(setting('cta_quote_label')) ?><?= icon('arrow', 'icon btn-icon') ?></a>
                <?php if ($isData): ?><a class="btn btn-ghost" href="<?= e(url('data')) ?>"><?= e(setting('home_data_cta')) ?></a><?php endif; ?>
            </div>
        </div>
        <aside class="service-aside" data-reveal style="--d:1">
            <?php if ($service['image']): ?><div class="service-aside-img"><?= img_tag($service['image'], $service['name']) ?></div><?php endif; ?>
            <?php if ($items = lines($service['items'])): ?>
            <div class="aside-card">
                <h2 class="aside-title"><?= e(t('service.offer')) ?></h2>
                <ul class="check-list">
                    <?php foreach ($items as $item): ?><li><?= icon('check') ?><?= e($item) ?></li><?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</section>

<?php if ($projects): ?>
<section class="section section--soft">
    <div class="container">
        <div class="section-head-row">
            <?php partial('section-head', ['eyebrow' => setting('home_projects_eyebrow'), 'title' => setting('home_projects_title')]); ?>
            <a class="btn btn-ghost" href="<?= e(url('realisations')) ?>" data-reveal><?= e(t('common.see_all_projects')) ?><?= icon('arrow', 'icon btn-icon') ?></a>
        </div>
        <?php partial('projects-grid', ['projects' => $projects, 'show_filters' => false]); ?>
    </div>
</section>
<?php endif; ?>

<?php if ($others): ?>
<section class="section">
    <div class="container">
        <?php partial('section-head', ['title' => t('service.other')]); ?>
        <div class="other-services">
            <?php foreach ($others as $i => $other): ?>
            <a class="other-service" href="<?= e(service_url($other)) ?>" data-reveal style="--d:<?= $i ?>">
                <span class="icon-box"><?= icon($other['icon'] ?: 'sparkles') ?></span>
                <span><span class="mono"><?= e($other['eyebrow']) ?></span><strong><?= e($other['name']) ?></strong></span>
                <?= icon('arrow') ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php partial('cta'); ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
