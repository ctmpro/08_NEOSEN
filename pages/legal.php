<?php
$meta['title'] = t('footer.legal') . ' | ' . site_name();
$meta['description'] = t('footer.legal') . ' — ' . site_name();
require ROOT_PATH . '/includes/header.php';
partial('page-hero', ['title' => t('footer.legal'), 'breadcrumb' => [[t('footer.legal'), null]]]);
?>
<section class="section section--tight-top">
    <div class="container container--narrow prose" data-reveal><?= rich_text(setting('legal_mentions')) ?></div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
