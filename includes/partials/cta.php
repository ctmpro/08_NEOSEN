<?php
/** Grande section d'appel à l'action finale. Variables optionnelles : $cta_title, $cta_text */
$wa = whatsapp_link();
?>
<section class="section cta-section" id="contact-cta">
    <div class="container">
        <div class="cta-card" data-reveal>
            <div class="cta-bg" aria-hidden="true"><div class="grid-lines"></div><div class="glow glow-1"></div><div class="glow glow-2"></div></div>
            <div class="cta-content">
                <p class="eyebrow eyebrow--light"><?= e(setting('cta_eyebrow')) ?></p>
                <h2 class="cta-title"><?= highlight($cta_title ?? setting('cta_title')) ?></h2>
                <p class="cta-text"><?= e($cta_text ?? setting('cta_text')) ?></p>
                <div class="btn-group">
                    <a class="btn btn-light" href="<?= e(url('contact')) ?>?type=devis#formulaire"><?= e(setting('cta_quote_label')) ?><?= icon('arrow', 'icon btn-icon') ?></a>
                    <a class="btn btn-outline-light" href="<?= e(url('contact')) ?>"><?= e(setting('cta_contact_label')) ?></a>
                    <?php if ($wa): ?>
                    <a class="btn btn-outline-light" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 'icon') ?><?= e(setting('cta_whatsapp_label')) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
