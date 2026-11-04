<?php
/**
 * Carte de visite numérique — page ouverte par le QR code imprimé (/c/{code}).
 *
 * /c/{code}          page mobile (ou redirection selon le mode choisi)
 * /c/{code}/vcard    fiche contact .vcf
 * /c/{code}/go?t=…   suivi des clics (appel, WhatsApp, email, site) puis redirection
 * /c/{code}/lead     formulaire « Être recontacté » (POST)
 */
require_once ROOT_PATH . '/includes/cards.php';

$code   = (string) ($params['slug'] ?? '');
$action = (string) ($params['action'] ?? '');
$card   = null;
try {
    $card = card_by_code($code);
} catch (Throwable $e) {
    error_log('NEOSEN: table des cartes indisponible — ' . $e->getMessage());
}

// Carte inconnue ou désactivée : on n'affiche jamais d'erreur à quelqu'un qui scanne, on l'envoie sur le site.
if (!$card || !$card['is_active']) {
    redirect(url(''));
}
$preview = isset($_GET['preview']);

/* ------------------------------------------------------------------ vCard */
if ($action === 'vcard') {
    if (!$preview) card_track($card, 'vcard');
    $file = slugify($card['person_name'] ?: $card['company'] ?: 'contact') . '.vcf';
    header('Content-Type: text/vcard; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('X-Robots-Tag: noindex');
    echo card_vcard($card);
    exit;
}

/* ------------------------------------------------------------------ Clics suivis */
if ($action === 'go') {
    $t = (string) ($_GET['t'] ?? '');
    $targets = [
        'call'     => $card['phone'] ? tel_link($card['phone']) : null,
        'whatsapp' => $card['whatsapp'] ? 'https://wa.me/' . preg_replace('/\D/', '', $card['whatsapp']) . '?text=' . rawurlencode('Bonjour, nous nous sommes rencontrés — ' . ($card['campaign'] ?: site_name()) . '.') : null,
        'email'    => $card['email'] ? 'mailto:' . $card['email'] : null,
        'site'     => url(''),
    ];
    if (empty($targets[$t])) redirect(url('c/' . $card['code']));
    if (!$preview) card_track($card, $t);
    // Appel envoyé par navigator.sendBeacon : le lien s'ouvre directement côté navigateur
    if (isset($_GET['beacon'])) {
        http_response_code(204);
        exit;
    }
    redirect($targets[$t]);
}

/* ------------------------------------------------------------------ Formulaire « Être recontacté » */
$errors = [];
$old = [];
if ($action === 'lead') {
    if (!is_post() || !$card['show_form']) redirect(url('c/' . $card['code']));
    foreach (['name', 'company', 'phone', 'email', 'need'] as $f) {
        $old[$f] = trim((string) ($_POST[$f] ?? ''));
    }
    $old['email'] = mb_strtolower($old['email']);
    $back = url('c/' . $card['code']);
    $elapsed = time() - (int) ($_POST['form_ts'] ?? 0);

    if (!csrf_check()) {
        $errors['_form'] = 'La session a expiré, merci de renvoyer le formulaire.';
    } elseif (!empty($_POST['website']) || $elapsed < 3) {
        // Robot probable : on simule un succès sans rien enregistrer.
        redirect($back . '?merci=1#recontact', 303);
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) FROM card_scans WHERE event = "lead" AND ip_hash = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)');
        $stmt->execute([hash('sha256', client_ip() . '|' . env('APP_KEY', 'neosen'))]);
        if ((int) $stmt->fetchColumn() >= 3) {
            $errors['_form'] = 'Trop de demandes en peu de temps, merci de réessayer plus tard.';
        }
    }
    if (!$errors) {
        if ($old['name'] === '' || mb_strlen($old['name']) > 120) $errors['name'] = 'Indiquez votre nom.';
        if ($old['email'] === '' && $old['phone'] === '') $errors['phone'] = 'Indiquez au moins un téléphone ou un email.';
        if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Adresse email invalide.';
        if ($old['phone'] !== '' && !preg_match('/^[+()\d\s.\-]{6,40}$/', $old['phone'])) $errors['phone'] = 'Numéro de téléphone invalide.';
        if (mb_strlen($old['company']) > 150) $errors['company'] = 'Nom trop long.';
        if (mb_strlen($old['need']) > 2000) $errors['need'] = 'Message trop long.';
        if (preg_match_all('#https?://#i', $old['need']) > 2) $errors['need'] = 'Message refusé.';
        if (empty($_POST['consent'])) $errors['consent'] = 'Merci d\'accepter d\'être recontacté.';
    }
    if (!$errors) {
        $parts = preg_split('/\s+/', $old['name'], 2);
        $source = 'Carte de visite' . ($card['campaign'] && $card['campaign'] !== 'Carte de visite' ? ' — ' . $card['campaign'] : '');
        $clientId = null;
        try {
            db()->prepare('INSERT INTO crm_clients (kind, stage, is_company, company, first_name, last_name, email, phone, source, notes) VALUES ("prospect", "new", ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$old['company'] !== '' ? 1 : 0, $old['company'] ?: null, $parts[0], $parts[1] ?? null, $old['email'] ?: null, $old['phone'] ?: null, mb_substr($source, 0, 80), $old['need'] ?: null]);
            $clientId = (int) db()->lastInsertId();
            require_once ROOT_PATH . '/includes/crm.php';
            crm_log($clientId, 'Contact laissé via la carte de visite « ' . $card['label'] . ' »', 'system', null, $old['need'] ?: null);
        } catch (Throwable $e) {
            error_log('NEOSEN: prospect de carte non enregistré — ' . $e->getMessage());
        }
        card_track($card, 'lead');

        require_once ROOT_PATH . '/includes/mailer.php';
        $rows = ['Nom' => $old['name'], 'Entreprise' => $old['company'], 'Téléphone' => $old['phone'], 'Email' => $old['email'], 'Origine' => $source];
        $table = '<table cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px">';
        foreach ($rows as $label => $value) {
            if ($value !== '') $table .= '<tr><td style="border-bottom:1px solid #eef0f5;color:#6b7280;width:130px">' . e($label) . '</td><td style="border-bottom:1px solid #eef0f5"><strong>' . e($value) . '</strong></td></tr>';
        }
        $table .= '</table>';
        if ($old['need'] !== '') $table .= '<div style="margin-top:16px;background:#f8f9fc;border-radius:12px;padding:16px;font-size:14px;line-height:1.6">' . nl2br(e($old['need'])) . '</div>';
        if ($clientId) $table .= '<p style="margin-top:24px"><a href="' . e(absolute_url('', APP_DEFAULT_LANG) . 'admin/client.php?id=' . $clientId) . '" style="display:inline-block;background:' . e(setting('color_primary')) . ';color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none">Voir le prospect</a></p>';
        send_mail((string) setting('notification_email'), 'Nouveau contact via votre carte de visite — ' . $old['name'], mail_layout('Nouveau contact (carte de visite)', $table),
            $old['email'] !== '' ? ['reply_to' => [$old['email'], $old['name']]] : []);

        redirect($back . '?merci=1#recontact', 303);
    }
}

/* ------------------------------------------------------------------ Mode redirection */
if ($card['mode'] === 'redirect' && $action === '') {
    if (!$preview) card_track($card, 'scan');
    redirect($card['redirect_url'] ?: url(''));
}

if ($action === '' && !$preview && !isset($_GET['merci'])) {
    card_track($card, 'scan');
}

/* ------------------------------------------------------------------ Page */
$base     = url('c/' . $card['code']);
$waLink   = $card['whatsapp'] ? 'https://wa.me/' . preg_replace('/\D/', '', $card['whatsapp']) . '?text=' . rawurlencode('Bonjour, nous nous sommes rencontrés — ' . ($card['campaign'] ?: site_name()) . '.') : '';
$q        = $preview ? '&preview=1' : '';
$name     = $card['person_name'] ?: ($card['company'] ?: site_name());
$initials = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($name)), 0, 2))));
$services = $card['show_services'] ? get_services() : [];
$projects = $card['show_projects'] ? get_projects(8, true) : [];
$sent     = isset($_GET['merci']);
$siteHost = preg_replace('#^https?://(www\.)?#', '', rtrim($card['website'] ?: setting('site_domain') ?: site_url(), '/'));
$logo     = (string) (setting('logo_light') ?: setting('logo'));
$theme    = array_key_exists($card['theme'], card_themes()) ? $card['theme'] : 'nuit';
$theme    = ['epure' => 'clair', 'tech' => 'nuit', 'diagonale' => 'degrade'][$theme] ?? $theme; // la page mobile reprend l'ambiance de la carte
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="<?= $theme === 'clair' ? '#ffffff' : e(setting('color_dark') ?: '#0A0F1F') ?>">
    <title><?= e($name . ($card['company'] && $card['company'] !== $name ? ' — ' . $card['company'] : '')) ?></title>
    <meta name="description" content="<?= e(excerpt($card['intro'] ?: $card['tagline'], 160)) ?>">
    <meta property="og:title" content="<?= e($name . ' — ' . ($card['company'] ?: site_name())) ?>">
    <meta property="og:description" content="<?= e(excerpt($card['tagline'] ?: $card['intro'], 160)) ?>">
    <meta property="og:image" content="<?= e(media_url($card['photo'] ?: setting('og_image'), true)) ?>">
    <link rel="icon" href="<?= e(media_url(setting('favicon'))) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Sora:wght@500;600;700&display=swap">
    <link rel="stylesheet" href="<?= asset('css/card.css') ?>">
    <style>:root{--primary:<?= e(setting('color_primary') ?: '#3B4BFF') ?>;--accent:<?= e(setting('color_accent') ?: '#14C8B4') ?>;--dark:<?= e(setting('color_dark') ?: '#0A0F1F') ?>}</style>
</head>
<body class="vcard-page theme-<?= e($theme) ?>">
<main class="vc">
    <header class="vc-hero">
        <div class="vc-glow" aria-hidden="true"></div>
        <a class="vc-brand" href="<?= e($base . '/go?t=site' . $q) ?>">
            <?php if ($logo): ?><img src="<?= e(media_url($theme === 'clair' ? (setting('logo') ?: $logo) : $logo)) ?>" alt="<?= e(site_name()) ?>" height="28"><?php else: ?><strong><?= e(site_name()) ?></strong><?php endif; ?>
        </a>
        <div class="vc-avatar">
            <?php if ($card['photo']): ?><img src="<?= e(media_url($card['photo'])) ?>" alt="<?= e($name) ?>" width="112" height="112"><?php else: ?><span><?= e($initials) ?></span><?php endif; ?>
        </div>
        <h1 class="vc-name"><?= e($name) ?></h1>
        <?php if ($card['job_title'] || $card['company']): ?>
        <p class="vc-role"><?= e(implode(' · ', array_filter([$card['job_title'], $card['company']]))) ?></p>
        <?php endif; ?>
        <?php if ($card['tagline']): ?><p class="vc-tagline"><?= e($card['tagline']) ?></p><?php endif; ?>

        <nav class="vc-actions" aria-label="Me contacter">
            <?php if ($card['phone']): ?><a href="<?= e(tel_link($card['phone'])) ?>" data-track="call"><?= icon('phone') ?><span>Appeler</span></a><?php endif; ?>
            <?php if ($card['whatsapp']): ?><a href="<?= e($waLink) ?>" data-track="whatsapp" target="_blank" rel="noopener"><?= icon('whatsapp') ?><span>WhatsApp</span></a><?php endif; ?>
            <?php if ($card['email']): ?><a href="<?= e('mailto:' . $card['email']) ?>" data-track="email"><?= icon('mail') ?><span>Email</span></a><?php endif; ?>
            <a href="<?= e($base . '/go?t=site' . $q) ?>"><?= icon('web') ?><span>Site web</span></a>
        </nav>
        <a class="vc-save" href="<?= e($base . '/vcard' . ($preview ? '?preview=1' : '')) ?>"><?= icon('user') ?> Enregistrer le contact</a>
    </header>

    <?php if ($card['intro']): ?>
    <section class="vc-section">
        <p class="vc-intro"><?= nl2br(e($card['intro'])) ?></p>
    </section>
    <?php endif; ?>

    <?php if ($services): ?>
    <section class="vc-section">
        <h2 class="vc-title">Ce que nous faisons</h2>
        <ul class="vc-services">
            <?php foreach ($services as $s): ?>
            <li><a href="<?= e(service_url($s)) ?>">
                <span class="vc-ico"><?= icon($s['icon'] ?: 'sparkles') ?></span>
                <span><strong><?= e($s['name']) ?></strong><small><?= e(excerpt($s['short_description'], 90)) ?></small></span>
            </a></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if ($projects): ?>
    <section class="vc-section vc-section--wide">
        <div class="vc-title-row"><h2 class="vc-title">Nos réalisations</h2><a href="<?= e(url('realisations')) ?>">Tout voir</a></div>
        <div class="vc-projects">
            <?php foreach ($projects as $p): ?>
            <a class="vc-project" href="<?= e(project_url($p)) ?>">
                <div class="vc-project-img"><?php if ($p['main_image']): ?><?= img_tag($p['main_image'], $p['name']) ?><?php else: ?><span><?= e(mb_substr($p['name'], 0, 1)) ?></span><?php endif; ?></div>
                <strong><?= e($p['name']) ?></strong>
                <small><?= e($p['category_label'] ?: ($p['category_name'] ?? '')) ?></small>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($card['show_form']): ?>
    <section class="vc-section" id="recontact">
        <h2 class="vc-title">Être recontacté</h2>
        <?php if ($sent): ?>
            <div class="vc-success"><?= icon('check-circle') ?><p><strong>Merci, c'est noté !</strong><br>Nous revenons vers vous très rapidement.</p></div>
        <?php else: ?>
        <p class="vc-muted">Laissez vos coordonnées, nous vous rappelons pour parler de votre projet.</p>
        <?php if (!empty($errors['_form'])): ?><p class="vc-error"><?= e($errors['_form']) ?></p><?php endif; ?>
        <form class="vc-form" method="post" action="<?= e($base . '/lead') ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="form_ts" value="<?= time() ?>">
            <div class="vc-hp" aria-hidden="true"><label>Site web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <?php
            $field = function (string $n, string $label, string $type = 'text', string $auto = '') use ($old, $errors) {
                echo '<label class="vc-field' . (isset($errors[$n]) ? ' has-error' : '') . '"><span>' . e($label) . '</span>'
                    . '<input type="' . $type . '" name="' . $n . '" value="' . e($old[$n] ?? '') . '"' . ($auto ? ' autocomplete="' . $auto . '"' : '') . '>'
                    . (isset($errors[$n]) ? '<em>' . e($errors[$n]) . '</em>' : '') . '</label>';
            };
            $field('name', 'Nom et prénom *', 'text', 'name');
            $field('company', 'Entreprise', 'text', 'organization');
            $field('phone', 'Téléphone', 'tel', 'tel');
            $field('email', 'Email', 'email', 'email');
            ?>
            <label class="vc-field<?= isset($errors['need']) ? ' has-error' : '' ?>"><span>Votre besoin (facultatif)</span>
                <textarea name="need" rows="3" placeholder="Site web, application, tableau de bord…"><?= e($old['need'] ?? '') ?></textarea>
                <?php if (isset($errors['need'])): ?><em><?= e($errors['need']) ?></em><?php endif; ?>
            </label>
            <label class="vc-consent<?= isset($errors['consent']) ? ' has-error' : '' ?>"><input type="checkbox" name="consent" value="1" <?= !empty($_POST['consent']) ? 'checked' : '' ?>>
                <span>J'accepte que <?= e(site_name()) ?> me recontacte au sujet de ma demande. <a href="<?= e(url('politique-de-confidentialite')) ?>">Confidentialité</a></span></label>
            <?php if (isset($errors['consent'])): ?><em class="vc-error-inline"><?= e($errors['consent']) ?></em><?php endif; ?>
            <button class="vc-submit" type="submit">Envoyer<?= icon('arrow') ?></button>
        </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <footer class="vc-footer">
        <a href="<?= e($base . '/go?t=site' . $q) ?>"><?= e($siteHost) ?></a>
        <?php if ($card['address']): ?><span><?= icon('pin') ?><?= e($card['address']) ?></span><?php endif; ?>
        <small><?= e(copyright_text()) ?></small>
    </footer>
</main>
<?php if (!$preview): ?>
<script>
document.querySelectorAll('[data-track]').forEach(function (a) {
    a.addEventListener('click', function () {
        var u = <?= json_encode($base . '/go?beacon=1&t=') ?> + a.dataset.track;
        if (navigator.sendBeacon) { navigator.sendBeacon(u); } else { fetch(u, {keepalive: true}); }
    });
});
</script>
<?php endif; ?>
</body>
</html>
