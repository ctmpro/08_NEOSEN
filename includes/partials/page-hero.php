<?php
/** Bandeau de titre des pages intérieures : $eyebrow, $title, $text, $breadcrumb [[label, href], …] */
?>
<section class="page-hero">
    <div class="page-hero-bg" aria-hidden="true"><div class="grid-lines"></div><div class="glow glow-1"></div><div class="glow glow-2"></div></div>
    <div class="container">
        <?php if (!empty($breadcrumb)): ?>
        <nav class="breadcrumb" aria-label="Fil d'Ariane" data-reveal>
            <a href="<?= e(url()) ?>"><?= e(t('common.home')) ?></a>
            <?php foreach ($breadcrumb as [$label, $href]): ?>
                <span aria-hidden="true">/</span>
                <?php if ($href): ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?php else: ?><span aria-current="page"><?= e($label) ?></span><?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
        <?php if (!empty($eyebrow)): ?><p class="eyebrow" data-reveal><?= e($eyebrow) ?></p><?php endif; ?>
        <h1 class="page-title" data-reveal><?= highlight($title ?? '') ?></h1>
        <?php if (!empty($text)): ?><p class="page-lead" data-reveal><?= e($text) ?></p><?php endif; ?>
    </div>
</section>
