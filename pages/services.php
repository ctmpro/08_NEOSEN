<?php
/**
 * Page Services (liste des expertises).
 */
$meta['title'] = (string) setting('seo_services_title');
$meta['description'] = (string) setting('seo_services_desc');
$services = get_services();
$techs = get_technologies_grouped();

require ROOT_PATH . '/includes/header.php';
partial('page-hero', ['eyebrow' => setting('services_eyebrow'), 'title' => setting('services_title'), 'text' => setting('services_intro'), 'breadcrumb' => [[t('nav.services'), null]]]);
?>

<section class="section">
    <div class="container services-list">
        <?php foreach ($services as $i => $service): ?>
        <article class="service-row<?= $i % 2 ? ' service-row--reverse' : '' ?>" id="<?= e($service['slug']) ?>">
            <div class="service-row-content" data-reveal>
                <span class="expertise-num mono"><?= e($service['eyebrow'] ?: sprintf('%02d', $i + 1)) ?></span>
                <h2 class="service-row-title"><?= e($service['name']) ?></h2>
                <p class="section-text"><?= e($service['short_description']) ?></p>
                <?php if ($items = lines($service['items'])): ?>
                <ul class="check-list check-list--2">
                    <?php foreach ($items as $item): ?><li><?= icon('check') ?><?= e($item) ?></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <div class="btn-group">
                    <a class="btn btn-primary" href="<?= e(service_url($service)) ?>"><?= e($service['cta_label'] ?: t('common.discover')) ?><?= icon('arrow', 'icon btn-icon') ?></a>
                    <a class="btn btn-ghost" href="<?= e(url('contact')) ?>?type=<?= e(rawurlencode($service['name'])) ?>#formulaire"><?= e(setting('cta_quote_label')) ?></a>
                </div>
            </div>
            <div class="service-row-media" data-reveal style="--d:1">
                <?php if ($service['image']): ?>
                    <?= img_tag($service['image'], $service['name']) ?>
                <?php else: ?>
                    <div class="service-art service-art--<?= $i % 3 ?>" aria-hidden="true">
                        <span class="service-art-icon"><?= icon($service['icon'] ?: 'sparkles') ?></span>
                        <span class="service-art-ring"></span><span class="service-art-ring service-art-ring--2"></span>
                    </div>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($techs): ?>
<section class="section section--soft">
    <div class="container">
        <?php partial('section-head', ['eyebrow' => setting('home_tech_eyebrow'), 'title' => setting('home_tech_title'), 'text' => setting('home_tech_text'), 'align' => 'center']); ?>
        <?php partial('tech-groups', ['techs' => $techs]); ?>
    </div>
</section>
<?php endif; ?>

<?php partial('cta'); ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
