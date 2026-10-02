<?php
/** Titre de section : $eyebrow, $title, $text, $align ('center'|'left'), $tag (h1|h2) */
$align = $align ?? 'left';
$tag = $tag ?? 'h2';
?>
<div class="section-head section-head--<?= e($align) ?>" data-reveal>
    <?php if (!empty($eyebrow)): ?><p class="eyebrow"><?= e($eyebrow) ?></p><?php endif; ?>
    <<?= $tag ?> class="section-title"><?= highlight($title ?? '') ?></<?= $tag ?>>
    <?php if (!empty($text)): ?><p class="section-text"><?= e($text) ?></p><?php endif; ?>
</div>
