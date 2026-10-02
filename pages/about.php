<?php
/**
 * Page À propos.
 */
$meta['title'] = (string) setting('seo_about_title');
$meta['description'] = (string) setting('seo_about_desc');
$values = get_blocks('about_values');
$stats  = get_blocks('stats');
$approach = get_blocks('approach');

require ROOT_PATH . '/includes/header.php';
partial('page-hero', ['eyebrow' => setting('about_eyebrow'), 'title' => setting('about_title'), 'text' => setting('about_intro'), 'breadcrumb' => [[t('nav.about'), null]]]);
?>

<section class="section">
    <div class="container about-grid">
        <div class="prose" data-reveal><?= rich_text(setting('about_body')) ?></div>
        <div class="about-media" data-reveal style="--d:1">
            <?php if (setting('about_video')): ?>
                <video controls playsinline preload="metadata" <?= setting('about_image') ? 'poster="' . e(media_url(setting('about_image'))) . '"' : '' ?>>
                    <source src="<?= e(media_url(setting('about_video'))) ?>">
                </video>
            <?php elseif (setting('about_image')): ?>
                <?= img_tag(setting('about_image'), site_name()) ?>
            <?php else: ?>
                <div class="about-trio" aria-hidden="true">
                    <?php foreach (get_services() as $i => $service): ?>
                    <div class="about-trio-item" style="--d:<?= $i ?>">
                        <span class="icon-box"><?= icon($service['icon'] ?: 'sparkles') ?></span>
                        <span class="mono"><?= e($service['eyebrow']) ?></span>
                        <strong><?= e($service['name']) ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section section--soft">
    <div class="container mv-grid">
        <article class="mv-card" data-reveal>
            <span class="icon-box"><?= icon('target') ?></span>
            <h2><?= e(setting('about_mission_title')) ?></h2>
            <p><?= e(setting('about_mission')) ?></p>
        </article>
        <article class="mv-card" data-reveal style="--d:1">
            <span class="icon-box"><?= icon('compass') ?></span>
            <h2><?= e(setting('about_vision_title')) ?></h2>
            <p><?= e(setting('about_vision')) ?></p>
        </article>
    </div>
</section>

<?php if ($values): ?>
<section class="section">
    <div class="container">
        <?php partial('section-head', ['title' => setting('about_values_title'), 'align' => 'center']); ?>
        <div class="why-grid">
            <?php foreach ($values as $i => $value): ?>
            <article class="why-card" data-reveal style="--d:<?= $i % 3 ?>">
                <span class="icon-box icon-box--soft"><?= icon($value['icon'] ?: 'sparkles') ?></span>
                <h3><?= e($value['title']) ?></h3>
                <p><?= e($value['description']) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
        <?php if ($stats): ?>
        <div class="stats" data-reveal>
            <?php foreach ($stats as $stat): ?>
            <div class="stat"><span class="stat-value" data-count="<?= e($stat['title']) ?>"><?= e($stat['title']) ?></span><span class="stat-label"><?= e($stat['subtitle'] ?: $stat['description']) ?></span></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($approach): ?>
<section class="section section--soft">
    <div class="container">
        <?php partial('section-head', ['eyebrow' => setting('home_approach_eyebrow'), 'title' => setting('home_approach_title'), 'text' => setting('home_approach_text'), 'align' => 'center']); ?>
        <ol class="timeline" data-timeline>
            <span class="timeline-progress" aria-hidden="true"></span>
            <?php foreach ($approach as $i => $step): ?>
            <li class="timeline-step" data-reveal style="--d:<?= $i % 3 ?>">
                <span class="timeline-dot"><?= icon($step['icon'] ?: 'sparkles') ?></span>
                <div class="timeline-body">
                    <span class="timeline-num mono"><?= e($step['subtitle'] ?: sprintf('%02d', $i + 1)) ?></span>
                    <h3><?= e($step['title']) ?></h3>
                    <p><?= e($step['description']) ?></p>
                </div>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
<?php endif; ?>

<?php partial('cta'); ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
