<?php /** Tableau d'informations légales : $rows [libellé => valeur] */ ?>
<dl class="legal-table">
    <?php foreach ($rows as $label => $value): ?>
    <div><dt><?= e($label) ?></dt><dd><?= $label === 'Email' ? '<a href="mailto:' . e($value) . '">' . e($value) . '</a>' : e($value) ?></dd></div>
    <?php endforeach; ?>
</dl>
