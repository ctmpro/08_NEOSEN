<?php
/** Technologies groupées par catégorie : $techs */
$catIcons = ['web' => 'web', 'mobile' => 'app', 'data' => 'data', 'outils' => 'settings', 'cloud' => 'layers', 'design' => 'pen'];
?>
<div class="tech-groups">
    <?php $i = 0; foreach ($techs as $category => $items): ?>
    <div class="tech-group" data-reveal style="--d:<?= $i++ % 3 ?>">
        <div class="tech-group-head">
            <span class="icon-box icon-box--sm"><?= icon($catIcons[strtolower(slugify($category))] ?? 'code') ?></span>
            <h3><?= e($category) ?></h3>
        </div>
        <ul class="tech-list">
            <?php foreach ($items as $tech): ?>
            <li>
                <?php if (!empty($tech['icon'])): ?><img src="<?= e(media_url($tech['icon'])) ?>" alt="" width="20" height="20" loading="lazy"><?php endif; ?>
                <?= e($tech['name']) ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>
</div>
