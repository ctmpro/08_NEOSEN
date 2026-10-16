<?php
/**
 * Politique de confidentialité : responsable du traitement (paramètres) + contenu libre.
 */
$meta['title'] = t('footer.privacy') . ' | ' . site_name();
$meta['description'] = t('footer.privacy') . ' — ' . company_name();
require ROOT_PATH . '/includes/header.php';
partial('page-hero', ['title' => t('footer.privacy'), 'breadcrumb' => [[t('footer.privacy'), null]]]);
?>
<section class="section section--tight-top">
    <div class="container container--narrow prose" data-reveal>
        <h2>Responsable du traitement</h2>
        <p>
            <?= e(company_name()) ?>, <?= e(setting('company_legal_form')) ?><br>
            <?= e(setting('company_address')) ?><br>
            <?php if (setting('company_rcs')): ?><?= e(setting('company_rcs')) ?><br><?php endif; ?>
            Contact : <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a>
        </p>
        <?= rich_text(setting('privacy_policy')) ?>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
