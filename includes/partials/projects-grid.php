<?php
/** Grille filtrable de réalisations : $projects, $show_filters */
$usedCats = array_unique(array_filter(array_column($projects, 'category_slug')));
$filters = array_filter(get_categories(), fn($c) => in_array($c['slug'], $usedCats, true));
?>
<?php if (!empty($show_filters) && $filters): ?>
<div class="filters" role="tablist" aria-label="Filtrer les réalisations" data-filters data-reveal>
    <button type="button" class="filter is-active" data-filter="*" role="tab" aria-selected="true"><?= e(t('common.all')) ?></button>
    <?php foreach ($filters as $cat): ?>
    <button type="button" class="filter" data-filter="<?= e($cat['slug']) ?>" role="tab" aria-selected="false"><?= e($cat['name']) ?></button>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($projects): ?>
<div class="projects-grid" data-projects-grid>
    <?php foreach ($projects as $i => $project): ?>
        <?php partial('project-card', ['project' => $project, 'index' => $i]); ?>
    <?php endforeach; ?>
</div>
<?php else: ?>
<p class="empty-state"><?= e(t('project.none')) ?></p>
<?php endif; ?>
