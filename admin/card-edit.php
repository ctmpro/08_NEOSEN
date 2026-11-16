<?php
/**
 * Création / modification d'une carte de visite + aperçu, fichiers d'impression et statistiques.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/card_pdf.php';
require_admin();
admin_csrf_guard();

$id = (int) ($_GET['id'] ?? 0);
$card = $id ? card_by_id($id) : null;
if ($id && !$card) {
    flash('error', 'Carte introuvable.');
    redirect(admin_url('cards.php'));
}

$errors = [];
if (is_post()) {
    if (post('action') === 'reset_stats' && $id) {
        db()->prepare('DELETE FROM card_scans WHERE card_id = ?')->execute([$id]);
        flash('success', 'Statistiques remises à zéro.');
        redirect(admin_url('card-edit.php', ['id' => $id]));
    }
    $str = fn(string $k, int $max) => mb_substr(trim(post($k)), 0, $max) ?: null;
    $data = [
        'label'         => $str('label', 120) ?? 'Carte de visite',
        'code'          => slugify(post('code')) ?: 'carte',
        'mode'          => post('mode') === 'redirect' ? 'redirect' : 'page',
        'redirect_url'  => $str('redirect_url', 255),
        'campaign'      => $str('campaign', 120),
        'person_name'   => $str('person_name', 120),
        'job_title'     => $str('job_title', 120),
        'company'       => $str('company', 120),
        'tagline'       => $str('tagline', 190),
        'intro'         => $str('intro', 2000),
        'phone'         => $str('phone', 40),
        'whatsapp'      => $str('whatsapp', 40),
        'email'         => mb_strtolower((string) $str('email', 190)) ?: null,
        'website'       => $str('website', 190),
        'address'       => $str('address', 190),
        'cta_label'     => $str('cta_label', 120),
        'theme'         => array_key_exists(post('theme'), card_themes()) ? post('theme') : 'nuit',
        'show_services' => post_bool('show_services'),
        'show_projects' => post_bool('show_projects'),
        'show_form'     => post_bool('show_form'),
        'is_active'     => post_bool('is_active'),
    ];
    $data['code'] = substr($data['code'], 0, 40);
    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse email invalide.';
    if ($data['mode'] === 'redirect' && !preg_match('#^https?://#', (string) $data['redirect_url'])) $errors[] = 'Indiquez une adresse de redirection complète (https://…).';
    $dup = db()->prepare('SELECT COUNT(*) FROM cards WHERE code = ? AND id <> ?');
    $dup->execute([$data['code'], $id]);
    if ($dup->fetchColumn()) $errors[] = 'Ce code est déjà utilisé par une autre carte.';
    $data['photo'] = handle_media_field('photo', $card['photo'] ?? null, 'cards', 'image', $errors);

    if (!$errors) {
        if ($id) {
            $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
            db()->prepare("UPDATE cards SET {$sets} WHERE id = ?")->execute([...array_values($data), $id]);
            if ($card['code'] !== $data['code']) {
                flash('warning', 'Code modifié : les cartes déjà imprimées avec l\'ancien QR code renvoient désormais vers l\'accueil du site.');
            }
            flash('success', 'Carte enregistrée. Le contenu est déjà à jour pour toutes les personnes qui scannent le QR code.');
        } else {
            db()->prepare('INSERT INTO cards (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')')->execute(array_values($data));
            $id = (int) db()->lastInsertId();
            flash('success', 'Carte créée.');
        }
        redirect(admin_url('card-edit.php', ['id' => $id]));
    }
    $card = array_merge($card ?? [], $data);
}

$c = $card ?? [
    'label' => 'Nouvelle carte', 'code' => '', 'mode' => 'page', 'theme' => 'nuit', 'company' => site_name(),
    'phone' => setting('contact_phone'), 'whatsapp' => setting('contact_whatsapp'), 'email' => setting('contact_email'),
    'website' => setting('site_domain'), 'tagline' => 'Web · Software · Data', 'cta_label' => 'Scannez pour découvrir nos réalisations',
    'show_services' => 1, 'show_projects' => 1, 'show_form' => 1, 'is_active' => 1,
];
$v = fn($k) => $c[$k] ?? '';
$saved = $id && $card && empty($errors);
$stats = $saved ? card_stats($id, 30) : null;
$fileUrl = fn(string $type, array $extra = []) => admin_url('card-file.php', ['id' => $id, 'type' => $type] + $extra);

$actions = [[icon('arrow-left', 'icon') . 'Toutes les cartes', admin_url('cards.php') . '?all=1', 'btn-ghost']];
if ($saved) array_unshift($actions, [icon('external', 'icon') . 'Voir la page', url('c/' . $card['code']) . '?preview=1', 'btn-ghost', true]);
admin_header($id ? 'Carte de visite : ' . $v('label') : 'Nouvelle carte de visite', 'cards', $actions);
?>
<?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<?php if ($saved): ?>
<div class="card-preview-wrap">
    <section class="card">
        <div class="card-head">
            <h2>Aperçu de la carte imprimée</h2>
            <p class="muted small">85 × 55 mm — style « <?= e(explode(' — ', card_themes()[$card['theme']] ?? '')[0]) ?> »</p>
        </div>
        <div class="bc-preview">
            <figure><div class="bc-face"><?= card_svg($card, 'recto') ?></div><figcaption>Recto</figcaption></figure>
            <figure><div class="bc-face"><?= card_svg($card, 'verso') ?></div><figcaption>Verso — le QR code ouvre <strong class="mono"><?= e(card_short_url($card)) ?></strong></figcaption></figure>
        </div>
        <div class="form-actions">
            <a class="btn btn-primary" href="<?= e($fileUrl('print')) ?>"><?= icon('upload', 'icon') ?> PDF pour l'imprimeur</a>
            <a class="btn btn-ghost" href="<?= e($fileUrl('sheet')) ?>"><?= icon('grid', 'icon') ?> Planche A4 (10 cartes)</a>
            <a class="btn btn-ghost" href="<?= e($fileUrl('qr-svg')) ?>">QR code SVG</a>
            <a class="btn btn-ghost" href="<?= e($fileUrl('qr-png')) ?>">QR code PNG</a>
        </div>
        <p class="help">PDF imprimeur : 2 pages (recto, verso) au format 91 × 61 mm, soit 85 × 55 mm + 3 mm de fond perdu, polices incorporées — accepté par les imprimeurs en ligne.
            Planche A4 : pour un tirage rapide au bureau, recto-verso « bord long », échelle 100 %, papier 300 g conseillé, puis découpe le long des traits.</p>
    </section>

    <section class="card">
        <div class="card-head">
            <h2>Statistiques — 30 derniers jours</h2>
            <form method="post" class="inline-form" data-confirm="Remettre les statistiques de cette carte à zéro ?"><?= csrf_field() ?><input type="hidden" name="action" value="reset_stats"><button class="link small">Remettre à zéro</button></form>
        </div>
        <?php $ev = $stats['events']; $clicks = ($ev['call'] ?? 0) + ($ev['whatsapp'] ?? 0) + ($ev['email'] ?? 0) + ($ev['site'] ?? 0); ?>
        <div class="stat-grid stat-grid--sm">
            <div class="stat-card"><span class="stat-label">Scans</span><strong class="stat-value"><?= (int) ($ev['scan'] ?? 0) ?></strong><span class="stat-sub"><?= (int) $stats['unique'] ?> personne(s) · <?= (int) $stats['total_scans'] ?> au total</span></div>
            <div class="stat-card"><span class="stat-label">Contact enregistré</span><strong class="stat-value"><?= (int) ($ev['vcard'] ?? 0) ?></strong><span class="stat-sub">fiches .vcf téléchargées</span></div>
            <div class="stat-card"><span class="stat-label">Clics</span><strong class="stat-value"><?= (int) $clicks ?></strong><span class="stat-sub"><?= (int) ($ev['call'] ?? 0) ?> appel(s) · <?= (int) ($ev['whatsapp'] ?? 0) ?> WhatsApp · <?= (int) ($ev['email'] ?? 0) ?> email</span></div>
            <a class="stat-card" href="<?= e(admin_url('clients.php', ['q' => 'Carte de visite'])) ?>"><span class="stat-label">Demandes de rappel</span><strong class="stat-value"><?= (int) ($ev['lead'] ?? 0) ?></strong><span class="stat-sub">ajoutées aux prospects</span></a>
        </div>
        <?php $max = max(1, max($stats['series'])); ?>
        <div class="chart-bars chart-bars--days" aria-label="Scans par jour">
            <?php foreach ($stats['series'] as $d => $n): ?>
            <div class="chart-col" title="<?= e(date('d/m', strtotime($d)) . ' : ' . $n . ' scan(s)') ?>">
                <span class="chart-val"><?= $n ?: '' ?></span>
                <span class="chart-bar" style="height:<?= max(2, round($n / $max * 100)) ?>%"></span>
                <span class="chart-label"><?= in_array((int) date('j', strtotime($d)), [1, 8, 15, 22], true) || $d === date('Y-m-d') ? e(date('d/m', strtotime($d))) : '' ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="grid-2 grid-2--wide-left" style="margin-top:20px">
            <div>
                <h3 class="small muted">Par événement / campagne (depuis le début)</h3>
                <?php if ($stats['campaigns']): ?>
                <table class="table table--compact"><thead><tr><th>Campagne</th><th class="num">Scans</th><th class="num">Rappels</th></tr></thead><tbody>
                <?php foreach ($stats['campaigns'] as $row): ?><tr><td><?= e($row['c']) ?></td><td class="num"><?= (int) $row['scans'] ?></td><td class="num"><?= (int) $row['leads'] ?></td></tr><?php endforeach; ?>
                </tbody></table>
                <?php else: ?><p class="muted small">Aucun scan pour l'instant.</p><?php endif; ?>
            </div>
            <div>
                <h3 class="small muted">Appareils</h3>
                <?php if ($stats['devices']): ?>
                <ul class="plain-list small"><?php foreach ($stats['devices'] as $dv => $n): ?><li><?= e($dv) ?> : <strong><?= (int) $n ?></strong></li><?php endforeach; ?></ul>
                <?php else: ?><p class="muted small">—</p><?php endif; ?>
            </div>
        </div>
    </section>
</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="edit-layout" data-dirty-check>
    <?= csrf_field() ?>
    <div class="edit-main">
        <section class="card">
            <div class="card-head"><h2>Identité imprimée sur la carte</h2></div>
            <div class="form-grid">
                <?= f_text('person_name', 'Nom et prénom', $v('person_name')) ?>
                <?= f_text('job_title', 'Fonction', $v('job_title'), ['placeholder' => 'CEO, Fondateur…']) ?>
                <?= f_text('company', 'Entreprise', $v('company')) ?>
                <?= f_text('tagline', 'Accroche (recto)', $v('tagline'), ['placeholder' => 'Web · Software · Data']) ?>
                <?= f_text('phone', 'Téléphone', $v('phone'), ['type' => 'tel']) ?>
                <?= f_text('whatsapp', 'WhatsApp', $v('whatsapp'), ['type' => 'tel', 'help' => 'Numéro international, ex. +33788405121.']) ?>
                <?= f_text('email', 'Email', $v('email'), ['type' => 'email']) ?>
                <?= f_text('website', 'Site web', $v('website'), ['placeholder' => 'neosen.tech']) ?>
                <?= f_text('address', 'Adresse (facultatif)', $v('address'), ['class' => 'span-2', 'help' => 'Laissez vide pour ne pas l\'afficher.']) ?>
                <?= f_text('cta_label', 'Phrase sous le QR code', $v('cta_label'), ['class' => 'span-2']) ?>
            </div>
        </section>
        <section class="card">
            <div class="card-head"><h2>Page ouverte par le QR code</h2><p class="muted small">Modifiable à tout moment, même après impression.</p></div>
            <?= f_media('photo', 'Photo de profil', $c['photo'] ?? null, ['help' => 'Portrait carré de préférence. Sans photo, vos initiales s\'affichent.']) ?>
            <?= f_textarea('intro', 'Présentation', $v('intro'), ['rows' => 3]) ?>
            <div class="form-grid">
                <?= f_toggle('show_services', 'Afficher les services', (bool) $v('show_services')) ?>
                <?= f_toggle('show_projects', 'Afficher les réalisations', (bool) $v('show_projects')) ?>
                <?= f_toggle('show_form', 'Formulaire « Être recontacté »', (bool) $v('show_form'), 'Chaque demande crée un prospect dans Clients & prospects.') ?>
            </div>
        </section>
    </div>
    <aside class="edit-side">
        <section class="card sticky">
            <div class="card-head"><h2>Réglages</h2></div>
            <?= f_text('label', 'Nom interne', $v('label'), ['required' => true]) ?>
            <?= f_select('theme', 'Style de la carte', card_themes(), $v('theme')) ?>
            <?= f_text('campaign', 'Événement en cours', $v('campaign'), ['placeholder' => 'Salon VivaTech 2027', 'help' => 'Changez-le avant chaque salon : les scans et contacts seront rattachés à cet événement.']) ?>
            <?= f_text('code', 'Code du QR code', $v('code') ?: 'neosen', ['help' => 'Adresse : ' . preg_replace('#^https?://#', '', absolute_url('c/', APP_DEFAULT_LANG)) . '… Ne le modifiez plus une fois les cartes imprimées.']) ?>
            <?= f_select('mode', 'Quand on scanne', ['page' => 'Afficher la carte numérique', 'redirect' => 'Rediriger vers une autre adresse'], $v('mode')) ?>
            <?= f_text('redirect_url', 'Adresse de redirection', $v('redirect_url'), ['placeholder' => 'https://', 'help' => 'Uniquement en mode redirection (ex. page d\'une offre salon).']) ?>
            <?= f_toggle('is_active', 'Carte active', (bool) $v('is_active'), 'Désactivée : le QR code renvoie vers l\'accueil du site.') ?>
            <button type="submit" class="btn btn-primary btn-block"><?= icon('check') ?> Enregistrer</button>
        </section>
    </aside>
</form>
<?php admin_footer(); ?>
