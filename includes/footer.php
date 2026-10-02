<?php
/**
 * Pied de page commun + scripts.
 */
$siteName = site_name();
$logoLight = (string) (setting('logo_light') ?: setting('logo'));
$socials = social_links();
$wa = whatsapp_link();
?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="brand" href="<?= e(url()) ?>">
                    <?php if ($logoLight): ?>
                        <img src="<?= e(media_url($logoLight)) ?>" alt="<?= e($siteName) ?>" height="32" style="height:32px" loading="lazy">
                    <?php else: ?>
                        <span class="brand-text"><?= e($siteName) ?></span>
                    <?php endif; ?>
                </a>
                <p><?= e(setting('site_description')) ?></p>
                <?php if ($socials): ?>
                <ul class="socials" aria-label="<?= e(t('footer.follow')) ?>">
                    <?php foreach ($socials as $network => $link): ?>
                    <li><a href="<?= e($link) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($network)) ?>"><?= icon($network) ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <div>
                <h2 class="footer-title"><?= e(t('footer.navigation')) ?></h2>
                <ul class="footer-links">
                    <li><a href="<?= e(url()) ?>"><?= e(t('nav.home')) ?></a></li>
                    <li><a href="<?= e(url('a-propos')) ?>"><?= e(t('nav.about')) ?></a></li>
                    <li><a href="<?= e(url('services')) ?>"><?= e(t('nav.services')) ?></a></li>
                    <li><a href="<?= e(url('realisations')) ?>"><?= e(t('nav.projects')) ?></a></li>
                    <li><a href="<?= e(url('data')) ?>"><?= e(t('nav.data')) ?></a></li>
                    <li><a href="<?= e(url('contact')) ?>"><?= e(t('nav.contact')) ?></a></li>
                </ul>
            </div>

            <div>
                <h2 class="footer-title"><?= e(t('footer.services')) ?></h2>
                <ul class="footer-links">
                    <?php foreach (get_services() as $service): ?>
                    <li><a href="<?= e(service_url($service)) ?>"><?= e($service['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div>
                <h2 class="footer-title"><?= e(t('footer.contact')) ?></h2>
                <ul class="footer-contact">
                    <?php if ($email = setting('contact_email')): ?>
                    <li><?= icon('mail') ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
                    <?php endif; ?>
                    <?php if ($phone = setting('contact_phone')): ?>
                    <li><?= icon('phone') ?><a href="<?= e(tel_link($phone)) ?>"><?= e($phone) ?></a></li>
                    <?php endif; ?>
                    <?php if ($wa): ?>
                    <li><?= icon('whatsapp') ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a></li>
                    <?php endif; ?>
                    <?php if ($address = setting('contact_address')): ?>
                    <li><?= icon('pin') ?><span><?= e($address) ?></span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>© <?= e(copyright_text()) ?></p>
            <ul>
                <li><a href="<?= e(url('mentions-legales')) ?>"><?= e(t('footer.legal')) ?></a></li>
                <li><a href="<?= e(url('politique-de-confidentialite')) ?>"><?= e(t('footer.privacy')) ?></a></li>
            </ul>
        </div>
    </div>
</footer>

<?php if ($wa && setting_bool('show_whatsapp_float')): ?>
<a class="whatsapp-float" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= icon('whatsapp') ?></a>
<?php endif; ?>

<script src="<?= asset('js/main.js') ?>" defer></script>
<?php if (!empty($extra_scripts)) echo $extra_scripts; ?>
</body>
</html>
