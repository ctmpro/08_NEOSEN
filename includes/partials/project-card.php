<?php
/** Carte réalisation : $project, $index */
$cat = $project['category_slug'] ?? '';
$techs = array_slice(csv_list($project['technologies'] ?? ''), 0, 4);
?>
<article class="project-card" data-category="<?= e($cat) ?>" data-reveal style="--d:<?= (int) (($index ?? 0) % 3) ?>">
    <a class="project-card-link" href="<?= e(project_url($project)) ?>" aria-label="<?= e(t('common.see_project') . ' : ' . $project['name']) ?>">
        <div class="project-media">
            <?php if (!empty($project['main_image'])): ?>
                <?= img_tag($project['main_image'], $project['name'] . ' — ' . ($project['category_label'] ?? ''), 'project-img', true, '(max-width: 768px) 100vw, 50vw') ?>
            <?php else: ?>
                <div class="project-placeholder"><span><?= e(mb_substr($project['name'], 0, 1)) ?></span></div>
            <?php endif; ?>
            <div class="project-overlay">
                <span class="btn btn-light btn-sm"><?= e(t('common.see_project')) ?><?= icon('arrow-up-right', 'icon btn-icon') ?></span>
            </div>
        </div>
        <div class="project-body">
            <div class="project-meta">
                <span class="tag"><?= e($project['category_label'] ?: ($project['category_name'] ?? '')) ?></span>
                <?php if (!empty($project['year'])): ?><span class="project-year mono"><?= e($project['year']) ?></span><?php endif; ?>
            </div>
            <h3 class="project-title"><?= e($project['name']) ?></h3>
            <p class="project-desc"><?= e(excerpt($project['short_description'], 150)) ?></p>
            <?php if ($techs): ?>
            <ul class="tech-chips">
                <?php foreach ($techs as $tech): ?><li><?= e($tech) ?></li><?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </a>
</article>
