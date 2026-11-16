<?php
/** Illustration animée de la chaîne de valeur Data (étapes paramétrables : data_pipeline). */
$steps = [];
foreach (setting_lines('data_pipeline') as $line) {
    [$title, $desc] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
    $steps[] = [$title, $desc];
}
$icons = ['database', 'flow', 'cube', 'chart', 'gauge', 'target'];
?>
<?php if ($steps): ?>
<div class="pipeline" data-reveal>
    <?php foreach ($steps as $i => [$title, $desc]): ?>
    <div class="pipeline-step" style="--d:<?= $i ?>">
        <span class="pipeline-icon"><?= icon($icons[$i % count($icons)]) ?></span>
        <div>
            <span class="pipeline-num mono"><?= sprintf('%02d', $i + 1) ?></span>
            <strong><?= e($title) ?></strong>
            <?php if ($desc): ?><span class="pipeline-desc"><?= e($desc) ?></span><?php endif; ?>
        </div>
    </div>
    <?php if ($i < count($steps) - 1): ?><span class="pipeline-link" aria-hidden="true"><i></i></span><?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
