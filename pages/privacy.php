<?php
$meta['title'] = t('footer.privacy') . ' | ' . site_name();
$meta['description'] = t('footer.privacy') . ' — ' . site_name();
require ROOT_PATH . '/includes/header.php';
partial('page-hero', ['title' => t('footer.privacy'), 'breadcrumb' => [[t('footer.privacy'), null]]]);
?>
<section class="section section--tight-top">
    <div class="container container--narrow prose" data-reveal><?= rich_text(setting('privacy_policy')) ?></div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
