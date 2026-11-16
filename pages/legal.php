<?php
/**
 * Mentions légales : éditeur et hébergeur (paramètres « Entreprise & pages légales ») + contenu libre.
 */
$meta['title'] = t('footer.legal') . ' | ' . site_name();
$meta['description'] = t('footer.legal') . ' — ' . company_name();
$host = array_filter([setting('host_name'), setting('host_address'), setting('host_phone')], fn($v) => trim((string) $v) !== '');
require ROOT_PATH . '/includes/header.php';
partial('page-hero', ['title' => t('footer.legal'), 'breadcrumb' => [[t('footer.legal'), null]]]);
?>
<section class="section section--tight-top">
    <div class="container container--narrow prose" data-reveal>
        <h2>Éditeur du site</h2>
        <p>Le site <?= e(setting('site_domain')) ?> est édité par :</p>
        <?php partial('legal-table', ['rows' => company_legal_rows()]); ?>
        <h2>Hébergement</h2>
        <?php if ($host): ?>
            <p><?= implode('<br>', array_map('e', $host)) ?></p>
        <?php else: ?>
            <p>Les coordonnées de l'hébergeur seront communiquées prochainement.</p>
        <?php endif; ?>
        <?= rich_text(setting('legal_mentions')) ?>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
