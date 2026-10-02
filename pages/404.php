<?php
$current_page = '404';
$meta['title'] = t('error.404.title') . ' | ' . site_name();
$meta['noindex'] = true;
require ROOT_PATH . '/includes/header.php';
?>
<section class="error-page">
    <div class="page-hero-bg" aria-hidden="true"><div class="grid-lines"></div><div class="glow glow-1"></div></div>
    <div class="container">
        <p class="error-code mono">404</p>
        <h1 class="page-title"><?= e(t('error.404.title')) ?></h1>
        <p class="page-lead"><?= e(t('error.404.text')) ?></p>
        <div class="btn-group" style="justify-content:center">
            <a class="btn btn-primary" href="<?= e(url()) ?>"><?= e(t('error.404.cta')) ?></a>
            <a class="btn btn-ghost" href="<?= e(url('realisations')) ?>"><?= e(t('nav.projects')) ?></a>
        </div>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
