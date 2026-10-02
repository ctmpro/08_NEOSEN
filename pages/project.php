<?php
/**
 * Fiche détaillée d'une réalisation : /realisations/{slug}
 */
$project = get_project($params['slug'] ?? '');
if (!$project) {
    http_response_code(404);
    require ROOT_PATH . '/pages/404.php';
    return;
}

$images   = get_project_images((int) $project['id']);
$features = lines($project['features']);
$techs    = csv_list($project['technologies']);
$category = $project['category_label'] ?: ($project['category_name'] ?? '');

$meta['title'] = $project['seo_title'] ?: $project['name'] . ' — ' . $category . ' | ' . site_name();
$meta['description'] = $project['seo_description'] ?: excerpt($project['short_description'], 160);
$meta['type'] = 'article';
if ($project['main_image']) $meta['image'] = $project['main_image'];
$meta['schema'][] = [
    '@context' => 'https://schema.org', '@type' => 'CreativeWork',
    'name' => $project['name'], 'description' => excerpt($project['short_description'], 300),
    'url' => absolute_url('realisations/' . $project['slug']),
    'image' => $project['main_image'] ? media_url($project['main_image'], true) : null,
    'dateCreated' => $project['year'] ? (string) $project['year'] : null,
    'creator' => ['@type' => 'Organization', 'name' => site_name()],
    'keywords' => implode(', ', $techs),
];
$meta['schema'][] = [
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => t('common.home'), 'item' => absolute_url('')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => t('nav.projects'), 'item' => absolute_url('realisations')],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $project['name']],
    ],
];

// Projet suivant (ordre d'affichage)
$all = get_projects();
$next = null;
foreach ($all as $k => $p) {
    if ((int) $p['id'] === (int) $project['id']) {
        $next = $all[($k + 1) % count($all)] ?? null;
        break;
    }
}
if ($next && (int) $next['id'] === (int) $project['id']) $next = null;
$host = $project['url'] ? preg_replace('#^https?://(www\.)?#', '', rtrim($project['url'], '/')) : '';

require ROOT_PATH . '/includes/header.php';
?>

<section class="page-hero page-hero--project">
    <div class="page-hero-bg" aria-hidden="true"><div class="grid-lines"></div><div class="glow glow-1"></div><div class="glow glow-2"></div></div>
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane" data-reveal>
            <a href="<?= e(url()) ?>"><?= e(t('common.home')) ?></a><span aria-hidden="true">/</span>
            <a href="<?= e(url('realisations')) ?>"><?= e(t('nav.projects')) ?></a><span aria-hidden="true">/</span>
            <span aria-current="page"><?= e($project['name']) ?></span>
        </nav>
        <div class="project-hero">
            <div>
                <p class="eyebrow" data-reveal><?= e($category) ?></p>
                <h1 class="page-title" data-reveal><?= e($project['name']) ?></h1>
                <p class="page-lead" data-reveal><?= e($project['short_description']) ?></p>
                <?php if ($project['url']): ?>
                <div class="btn-group" data-reveal>
                    <a class="btn btn-primary" href="<?= e($project['url']) ?>" target="_blank" rel="noopener"><?= e(t('common.visit')) ?><?= icon('external', 'icon btn-icon') ?></a>
                </div>
                <?php endif; ?>
            </div>
            <dl class="project-facts" data-reveal style="--d:1">
                <?php if ($project['client']): ?><div><dt><?= e(t('common.client')) ?></dt><dd><?= e($project['client']) ?></dd></div><?php endif; ?>
                <?php if ($category): ?><div><dt><?= e(t('common.category')) ?></dt><dd><?= e($category) ?></dd></div><?php endif; ?>
                <?php if ($project['year']): ?><div><dt><?= e(t('common.year')) ?></dt><dd><?= e($project['year']) ?></dd></div><?php endif; ?>
                <?php if ($host): ?><div><dt><?= e(t('common.website')) ?></dt><dd><a href="<?= e($project['url']) ?>" target="_blank" rel="noopener"><?= e($host) ?></a></dd></div><?php endif; ?>
            </dl>
        </div>
    </div>
</section>

<?php if ($project['main_image']): ?>
<section class="project-showcase">
    <div class="container">
        <figure class="browser-frame" data-reveal>
            <div class="browser-bar" aria-hidden="true"><i></i><i></i><i></i><span><?= e($host ?: $project['name']) ?></span></div>
            <?= img_tag($project['main_image'], $project['name'] . ' — ' . t('project.presentation'), '', false, '(max-width: 1280px) 100vw, 1200px') ?>
        </figure>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container project-content">
        <?php if ($project['long_description']): ?>
        <div class="project-block" data-reveal>
            <h2 class="block-title"><span class="mono">01</span><?= e(t('project.presentation')) ?></h2>
            <div class="prose"><?= rich_text($project['long_description']) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($project['need'] || $project['solution']): ?>
        <div class="need-solution">
            <?php if ($project['need']): ?>
            <div class="ns-card" data-reveal>
                <span class="icon-box icon-box--soft"><?= icon('search') ?></span>
                <h2><?= e(t('project.need')) ?></h2>
                <div class="prose"><?= rich_text($project['need']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($project['solution']): ?>
            <div class="ns-card ns-card--accent" data-reveal style="--d:1">
                <span class="icon-box"><?= icon('sparkles') ?></span>
                <h2><?= e(t('project.solution')) ?></h2>
                <div class="prose"><?= rich_text($project['solution']) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($features): ?>
        <div class="project-block" data-reveal>
            <h2 class="block-title"><span class="mono">02</span><?= e(t('project.features')) ?></h2>
            <ul class="feature-grid">
                <?php foreach ($features as $i => $feature): ?>
                <li data-reveal style="--d:<?= $i % 3 ?>"><?= icon('check-circle') ?><span><?= e($feature) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($techs): ?>
        <div class="project-block" data-reveal>
            <h2 class="block-title"><span class="mono">03</span><?= e(t('project.technologies')) ?></h2>
            <ul class="tech-chips tech-chips--lg">
                <?php foreach ($techs as $tech): ?><li><?= e($tech) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($images): ?>
<section class="section section--soft">
    <div class="container">
        <h2 class="block-title" data-reveal><span class="mono">04</span><?= e(t('project.gallery')) ?></h2>
        <div class="gallery" data-gallery>
            <?php foreach ($images as $i => $image): ?>
            <a class="gallery-item" href="<?= e(media_url($image['path'])) ?>" data-reveal style="--d:<?= $i % 3 ?>" data-lightbox>
                <?= img_tag($image['path'], $image['alt'] ?: $project['name'] . ' — capture ' . ($i + 1), '', true, '(max-width: 768px) 100vw, 33vw') ?>
                <span class="gallery-zoom" aria-hidden="true"><?= icon('plus') ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($project['results']): ?>
<section class="section">
    <div class="container">
        <div class="result-card" data-reveal>
            <div>
                <p class="eyebrow"><?= e(t('project.results')) ?></p>
                <div class="prose prose--lg"><?= rich_text($project['results']) ?></div>
            </div>
            <?php if ($project['url']): ?>
            <a class="btn btn-ghost" href="<?= e($project['url']) ?>" target="_blank" rel="noopener"><?= e(t('common.visit')) ?><?= icon('external', 'icon btn-icon') ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($next): ?>
<section class="next-project">
    <div class="container">
        <a class="next-project-link" href="<?= e(project_url($next)) ?>" data-reveal>
            <span class="mono"><?= e(t('project.next')) ?></span>
            <strong><?= e($next['name']) ?></strong>
            <span class="next-project-cat"><?= e($next['category_label']) ?></span>
            <span class="next-project-arrow"><?= icon('arrow') ?></span>
        </a>
    </div>
</section>
<?php endif; ?>

<section class="section cta-section">
    <div class="container">
        <div class="cta-card cta-card--compact" data-reveal>
            <div class="cta-bg" aria-hidden="true"><div class="grid-lines"></div><div class="glow glow-1"></div></div>
            <div class="cta-content">
                <h2 class="cta-title"><?= e(t('project.similar')) ?></h2>
                <p class="cta-text"><?= e(t('project.similar_text')) ?></p>
                <div class="btn-group">
                    <a class="btn btn-light" href="<?= e(url('contact')) ?>?projet=<?= e(rawurlencode($project['name'])) ?>#formulaire"><?= e(t('project.cta')) ?><?= icon('arrow', 'icon btn-icon') ?></a>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="lightbox" data-lightbox-modal hidden>
    <button type="button" class="lightbox-close" aria-label="<?= e(t('nav.close')) ?>" data-lightbox-close><?= icon('close') ?></button>
    <button type="button" class="lightbox-nav lightbox-prev" aria-label="Précédent" data-lightbox-prev><?= icon('arrow-left') ?></button>
    <img alt="" data-lightbox-img>
    <button type="button" class="lightbox-nav lightbox-next" aria-label="Suivant" data-lightbox-next><?= icon('arrow') ?></button>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
