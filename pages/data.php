<?php
/**
 * Page Data & Business Intelligence.
 */
$meta['title'] = (string) setting('seo_data_title');
$meta['description'] = (string) setting('seo_data_desc');
$offers = get_blocks('data_offer');
$techs = get_technologies_grouped();
$dataTechs = [];
foreach ($techs as $cat => $items) {
    if (stripos($cat, 'data') !== false) $dataTechs = array_merge($dataTechs, $items);
}
$dataProjects = array_values(array_filter(get_projects(), fn($p) => ($p['category_slug'] ?? '') === 'data'));

require ROOT_PATH . '/includes/header.php';
?>

<section class="page-hero page-hero--dark">
    <div class="page-hero-bg" aria-hidden="true"><div class="grid-lines grid-lines--light"></div><div class="glow glow-1"></div><div class="glow glow-2"></div></div>
    <div class="container data-hero">
        <div>
            <nav class="breadcrumb" aria-label="Fil d'Ariane" data-reveal>
                <a href="<?= e(url()) ?>"><?= e(t('common.home')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t('nav.data')) ?></span>
            </nav>
            <p class="eyebrow eyebrow--light" data-reveal><?= e(setting('data_eyebrow')) ?></p>
            <h1 class="page-title" data-reveal><?= highlight(setting('data_title')) ?></h1>
            <p class="page-lead" data-reveal><?= e(setting('data_intro')) ?></p>
            <div class="btn-group" data-reveal>
                <a class="btn btn-light" href="<?= e(url('contact')) ?>?type=<?= e(rawurlencode('Projet Data / BI')) ?>#formulaire"><?= e(setting('data_cta_label')) ?><?= icon('arrow', 'icon btn-icon') ?></a>
            </div>
        </div>
        <div class="data-hero-visual" data-reveal style="--d:1">
            <?php if (setting('data_image')): ?>
                <?= img_tag(setting('data_image'), setting('data_eyebrow'), '', false) ?>
            <?php else: ?>
            <div class="dash" aria-hidden="true">
                <div class="dash-head"><span class="mono">Dashboard · Ventes</span><span class="dash-live"><i></i>Live</span></div>
                <div class="dash-kpis">
                    <div><small>CA</small><strong>84,2 M</strong><em>+12%</em></div>
                    <div><small>Marge</small><strong>31,6%</strong><em>+3 pts</em></div>
                    <div><small>Clients</small><strong>2 418</strong><em>+8%</em></div>
                </div>
                <div class="dash-chart">
                    <svg viewBox="0 0 300 110" preserveAspectRatio="none">
                        <defs><linearGradient id="dg" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".35"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>
                        <path class="dash-area" d="M0 90 L40 78 L80 82 L120 60 L160 66 L200 40 L240 46 L300 18 L300 110 L0 110Z" fill="url(#dg)"/>
                        <path class="dash-stroke" d="M0 90 L40 78 L80 82 L120 60 L160 66 L200 40 L240 46 L300 18"/>
                    </svg>
                </div>
                <div class="dash-bottom">
                    <div class="dash-donut"><svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.9" /><circle class="dash-donut-val" cx="18" cy="18" r="15.9" /></svg><span>68%</span></div>
                    <div class="dash-rows"><i style="--w:86%"></i><i style="--w:64%"></i><i style="--w:48%"></i><i style="--w:30%"></i></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php partial('section-head', ['eyebrow' => setting('data_eyebrow'), 'title' => setting('data_pipeline_title'), 'align' => 'center']); ?>
        <?php partial('data-pipeline'); ?>
    </div>
</section>

<?php if ($offers): ?>
<section class="section section--soft">
    <div class="container">
        <?php partial('section-head', ['title' => setting('data_offers_title'), 'text' => setting('data_offers_text')]); ?>
        <div class="offer-grid">
            <?php foreach ($offers as $i => $offer): ?>
            <article class="offer-card" data-reveal style="--d:<?= $i % 2 ?>">
                <div class="offer-head">
                    <span class="icon-box"><?= icon($offer['icon'] ?: 'data') ?></span>
                    <div>
                        <span class="mono"><?= sprintf('%02d', $i + 1) ?></span>
                        <h3><?= e($offer['title']) ?></h3>
                    </div>
                </div>
                <?php if ($offer['description']): ?><p><?= e($offer['description']) ?></p><?php endif; ?>
                <?php if ($items = lines($offer['items'])): ?>
                <ul class="tech-chips">
                    <?php foreach ($items as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($dataTechs): ?>
<section class="section">
    <div class="container">
        <?php partial('section-head', ['eyebrow' => setting('home_tech_eyebrow'), 'title' => setting('data_stack_title'), 'align' => 'center']); ?>
        <ul class="tech-cloud" data-reveal>
            <?php foreach ($dataTechs as $tech): ?><li><?= e($tech['name']) ?></li><?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<?php if ($dataProjects): ?>
<section class="section section--soft">
    <div class="container">
        <?php partial('section-head', ['eyebrow' => setting('home_projects_eyebrow'), 'title' => setting('home_projects_title')]); ?>
        <?php partial('projects-grid', ['projects' => $dataProjects, 'show_filters' => false]); ?>
    </div>
</section>
<?php endif; ?>

<?php partial('cta', ['cta_title' => setting('data_cta_title'), 'cta_text' => setting('data_cta_text')]); ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
