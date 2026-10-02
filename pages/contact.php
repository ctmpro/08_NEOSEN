<?php
/**
 * Page Contact + formulaire de demande de projet.
 */
require_once ROOT_PATH . '/includes/recaptcha.php';

$meta['title'] = (string) setting('seo_contact_title');
$meta['description'] = (string) setting('seo_contact_desc');

$types   = setting_lines('contact_project_types');
$budgets = setting_lines('contact_budgets');

// Valeurs et erreurs renvoyées par l'API en cas d'envoi sans JavaScript
$old    = $_SESSION['contact_old'] ?? [];
$errors = $_SESSION['contact_errors'] ?? [];
$sent   = !empty($_SESSION['contact_sent']);
unset($_SESSION['contact_old'], $_SESSION['contact_errors'], $_SESSION['contact_sent']);

// Pré-remplissage via l'URL (?type=…, ?projet=…)
if (!$old) {
    $wanted = trim((string) ($_GET['type'] ?? ''));
    foreach ($types as $type) {
        if ($wanted !== '' && (mb_strtolower($type) === mb_strtolower($wanted) || str_contains(mb_strtolower($wanted), mb_strtolower($type)))) {
            $old['project_type'] = $type;
        }
    }
    if (!empty($_GET['projet'])) {
        $old['message'] = 'Bonjour, j\'ai un projet similaire à « ' . mb_substr(strip_tags((string) $_GET['projet']), 0, 80) . ' ». ';
    }
}
$v = fn($k) => e($old[$k] ?? '');
$err = fn($k) => isset($errors[$k]) ? '<p class="field-error" id="err-' . $k . '">' . e($errors[$k]) . '</p>' : '<p class="field-error" id="err-' . $k . '" hidden></p>';
$inv = fn($k) => isset($errors[$k]) ? ' aria-invalid="true"' : '';

$wa = whatsapp_link();
$extra_scripts = '';
if (recaptcha_enabled()) {
    $siteKey = e(env('RECAPTCHA_SITE_KEY'));
    $extra_scripts = recaptcha_version() === 'v3'
        ? '<script src="https://www.google.com/recaptcha/api.js?render=' . $siteKey . '" async defer></script>'
        : '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
}

require ROOT_PATH . '/includes/header.php';
partial('page-hero', ['eyebrow' => setting('contact_eyebrow'), 'title' => setting('contact_title'), 'text' => setting('contact_intro'), 'breadcrumb' => [[t('nav.contact'), null]]]);
?>

<section class="section section--tight-top" id="formulaire">
    <div class="container contact-grid">
        <aside class="contact-aside" data-reveal>
            <div class="contact-card">
                <ul class="contact-list">
                    <?php if ($email = setting('contact_email')): ?>
                    <li><span class="icon-box icon-box--sm"><?= icon('mail') ?></span><div><small><?= e(t('form.email')) ?></small><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></div></li>
                    <?php endif; ?>
                    <?php if ($phone = setting('contact_phone')): ?>
                    <li><span class="icon-box icon-box--sm"><?= icon('phone') ?></span><div><small><?= e(t('form.phone')) ?></small><a href="<?= e(tel_link($phone)) ?>"><?= e($phone) ?></a></div></li>
                    <?php endif; ?>
                    <?php if ($wa): ?>
                    <li><span class="icon-box icon-box--sm"><?= icon('whatsapp') ?></span><div><small>WhatsApp</small><a href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= e(setting('contact_whatsapp')) ?></a></div></li>
                    <?php endif; ?>
                    <?php if ($address = setting('contact_address')): ?>
                    <li><span class="icon-box icon-box--sm"><?= icon('pin') ?></span><div><small>Localisation</small><span><?= e($address) ?></span></div></li>
                    <?php endif; ?>
                    <?php if ($hours = setting('contact_hours')): ?>
                    <li><span class="icon-box icon-box--sm"><?= icon('clock') ?></span><div><small>Horaires</small><span><?= e($hours) ?></span></div></li>
                    <?php endif; ?>
                </ul>
            </div>

            <?php if ($steps = setting_lines('contact_steps')): ?>
            <div class="contact-card contact-card--dark">
                <h2 class="aside-title">Et ensuite ?</h2>
                <ol class="steps-list">
                    <?php foreach ($steps as $i => $step): ?><li><span class="mono"><?= sprintf('%02d', $i + 1) ?></span><?= e($step) ?></li><?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>

            <?php if ($wa): ?>
            <a class="btn btn-whatsapp btn-block" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 'icon') ?>Discuter sur WhatsApp</a>
            <?php endif; ?>
        </aside>

        <div class="form-card" data-reveal style="--d:1">
            <div class="form-success" data-form-success <?= $sent ? '' : 'hidden' ?> role="status" aria-live="polite">
                <span class="success-icon"><?= icon('check') ?></span>
                <h2>Demande envoyée</h2>
                <p data-success-text><?= e(setting('contact_success')) ?></p>
                <a class="btn btn-ghost" href="<?= e(url()) ?>"><?= e(t('error.404.cta')) ?></a>
            </div>

            <form class="contact-form" action="<?= e(base_path()) ?>/api/contact.php" method="post" enctype="multipart/form-data" novalidate data-contact-form
                  data-recaptcha="<?= recaptcha_enabled() ? e(recaptcha_version()) : '' ?>" data-sitekey="<?= e(env('RECAPTCHA_SITE_KEY', '')) ?>" <?= $sent ? 'hidden' : '' ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="lang" value="<?= e(current_lang()) ?>">
                <input type="hidden" name="form_ts" value="<?= time() ?>">
                <input type="hidden" name="recaptcha_token" value="">
                <!-- Pot de miel anti-spam : doit rester vide -->
                <div class="hp" aria-hidden="true"><label for="website">Site web</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>

                <div class="form-alert" data-form-alert <?= $errors ? '' : 'hidden' ?> role="alert"><?= e($errors['_form'] ?? t('form.fix_errors')) ?></div>

                <div class="form-grid">
                    <div class="field">
                        <label for="last_name"><?= e(t('form.last_name')) ?> <span class="req">*</span></label>
                        <input type="text" id="last_name" name="last_name" value="<?= $v('last_name') ?>" required maxlength="100" autocomplete="family-name"<?= $inv('last_name') ?> aria-describedby="err-last_name">
                        <?= $err('last_name') ?>
                    </div>
                    <div class="field">
                        <label for="first_name"><?= e(t('form.first_name')) ?> <span class="req">*</span></label>
                        <input type="text" id="first_name" name="first_name" value="<?= $v('first_name') ?>" required maxlength="100" autocomplete="given-name"<?= $inv('first_name') ?> aria-describedby="err-first_name">
                        <?= $err('first_name') ?>
                    </div>
                    <div class="field">
                        <label for="company"><?= e(t('form.company')) ?></label>
                        <input type="text" id="company" name="company" value="<?= $v('company') ?>" maxlength="150" autocomplete="organization"<?= $inv('company') ?> aria-describedby="err-company">
                        <?= $err('company') ?>
                    </div>
                    <div class="field">
                        <label for="email"><?= e(t('form.email')) ?> <span class="req">*</span></label>
                        <input type="email" id="email" name="email" value="<?= $v('email') ?>" required maxlength="190" autocomplete="email"<?= $inv('email') ?> aria-describedby="err-email">
                        <?= $err('email') ?>
                    </div>
                    <div class="field">
                        <label for="phone"><?= e(t('form.phone')) ?></label>
                        <input type="tel" id="phone" name="phone" value="<?= $v('phone') ?>" maxlength="40" autocomplete="tel"<?= $inv('phone') ?> aria-describedby="err-phone">
                        <?= $err('phone') ?>
                    </div>
                    <div class="field">
                        <label for="project_type"><?= e(t('form.project_type')) ?> <span class="req">*</span></label>
                        <select id="project_type" name="project_type" required<?= $inv('project_type') ?> aria-describedby="err-project_type">
                            <option value=""><?= e(t('form.choose')) ?></option>
                            <?php foreach ($types as $type): ?>
                            <option value="<?= e($type) ?>"<?= ($old['project_type'] ?? '') === $type ? ' selected' : '' ?>><?= e($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('project_type') ?>
                    </div>
                    <div class="field field--full">
                        <span class="label"><?= e(t('form.budget')) ?></span>
                        <div class="chips-select" role="radiogroup" aria-label="<?= e(t('form.budget')) ?>">
                            <?php foreach ($budgets as $i => $budget): ?>
                            <label class="chip-option">
                                <input type="radio" name="budget" value="<?= e($budget) ?>"<?= ($old['budget'] ?? '') === $budget ? ' checked' : '' ?>>
                                <span><?= e($budget) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?= $err('budget') ?>
                    </div>
                    <div class="field field--full">
                        <label for="message"><?= e(t('form.message')) ?> <span class="req">*</span></label>
                        <textarea id="message" name="message" rows="6" required maxlength="5000"<?= $inv('message') ?> aria-describedby="err-message" placeholder="Objectifs, fonctionnalités attendues, délais…"><?= $v('message') ?></textarea>
                        <?= $err('message') ?>
                    </div>
                    <div class="field field--full">
                        <label class="file-drop" for="attachment" data-file-drop>
                            <?= icon('paperclip') ?>
                            <span><strong><?= e(t('form.attachment')) ?></strong><small data-file-name><?= e(t('form.attachment_help')) ?></small></span>
                            <input type="file" id="attachment" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp,.zip,.txt" aria-describedby="err-attachment">
                        </label>
                        <?= $err('attachment') ?>
                    </div>
                    <div class="field field--full">
                        <label class="checkbox">
                            <input type="checkbox" name="consent" value="1" required<?= !empty($old['consent']) ? ' checked' : '' ?> aria-describedby="err-consent">
                            <span><?= e(t('form.consent')) ?> <a href="<?= e(url('politique-de-confidentialite')) ?>" target="_blank"><?= e(t('footer.privacy')) ?></a></span>
                        </label>
                        <?= $err('consent') ?>
                    </div>
                    <?php if (recaptcha_enabled() && recaptcha_version() === 'v2'): ?>
                    <div class="field field--full"><div class="g-recaptcha" data-sitekey="<?= e(env('RECAPTCHA_SITE_KEY')) ?>"></div></div>
                    <?php endif; ?>
                </div>

                <div class="form-footer">
                    <p class="form-note"><span class="req">*</span> <?= e(t('form.required')) ?><?php if (recaptcha_enabled()): ?> · <?= e(t('form.recaptcha')) ?><?php endif; ?></p>
                    <button class="btn btn-primary btn-lg" type="submit" data-submit>
                        <span data-submit-label><?= e(t('form.submit')) ?></span><?= icon('arrow', 'icon btn-icon') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php if ($map = setting('contact_map_embed')): ?>
<section class="map-section">
    <iframe src="<?= e($map) ?>" title="Localisation" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
</section>
<?php endif; ?>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
