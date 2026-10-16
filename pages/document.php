<?php
/**
 * Consultation d'un devis / d'une facture par le client via un lien sécurisé (jeton aléatoire).
 * Permet de télécharger le PDF et d'accepter un devis en ligne.
 */
require_once ROOT_PATH . '/includes/crm.php';

$doc = null;
try {
    $doc = crm_doc_by_token($params['slug'] ?? '');
} catch (Throwable $e) {
    $doc = null;
}
if (!$doc) {
    http_response_code(404);
    require ROOT_PATH . '/pages/404.php';
    return;
}

// Téléchargement du PDF
if (str_ends_with(rtrim($path, '/'), '/pdf')) {
    crm_pdf_response($doc, !empty($_GET['dl']));
    return;
}

$isQuote = $doc['doc_type'] === 'quote';
$canAccept = $isQuote && $doc['status'] === 'sent' && (!$doc['due_date'] || $doc['due_date'] >= date('Y-m-d'));
$error = null;

// Acceptation en ligne du devis
if ($canAccept && is_post()) {
    $name = trim((string) ($_POST['accepted_by'] ?? ''));
    if (!csrf_check()) {
        $error = t('form.error.csrf');
    } elseif ($name === '' || empty($_POST['agree'])) {
        $error = 'Merci d\'indiquer votre nom et de cocher la case d\'acceptation.';
    } else {
        $by = mb_substr($name, 0, 150) . ' (en ligne, IP ' . client_ip() . ')';
        db()->prepare('UPDATE crm_documents SET status = "accepted", accepted_at = NOW(), accepted_by = ? WHERE id = ? AND status = "sent"')->execute([$by, $doc['id']]);
        db()->prepare('UPDATE crm_clients SET stage = "won", kind = "client" WHERE id = ?')->execute([$doc['client_id']]);
        crm_log((int) $doc['client_id'], 'Devis ' . $doc['number'] . ' accepté en ligne par ' . $name, 'system', (int) $doc['id']);
        require_once ROOT_PATH . '/includes/mailer.php';
        send_mail((string) setting('notification_email'), 'Devis ' . $doc['number'] . ' accepté par ' . crm_client_name($doc),
            mail_layout('Devis accepté 🎉', '<p>Le devis <strong>' . e($doc['number']) . '</strong> (' . e(money((float) $doc['total'], $doc['currency'])) . ') vient d\'être accepté en ligne par <strong>' . e($name) . '</strong>.</p><p><a href="' . e(absolute_url('', APP_DEFAULT_LANG) . 'admin/document.php?id=' . $doc['id']) . '">Ouvrir dans l\'administration</a></p>'));
        redirect(url('document/' . $doc['public_token']) . '?accepted=1');
    }
}

$client = crm_doc_client($doc);
[$statusLabel, $statusTone] = crm_doc_status($doc);
$balance = crm_balance($doc);
$docLabel = crm_doc_label($doc['doc_type']);

$current_page = 'document';
$meta['title'] = $docLabel . ' ' . $doc['number'] . ' — ' . site_name();
$meta['description'] = $docLabel . ' ' . $doc['number'];
$meta['noindex'] = true;
require ROOT_PATH . '/includes/header.php';
?>
<section class="page-hero page-hero--doc">
    <div class="page-hero-bg" aria-hidden="true"><div class="grid-lines"></div><div class="glow glow-1"></div></div>
    <div class="container">
        <p class="eyebrow"><?= e($docLabel) ?> · <?= e(company_name()) ?></p>
        <h1 class="page-title"><?= e($docLabel . ' ' . $doc['number']) ?></h1>
        <p class="page-lead"><?= e($client['name']) ?><?= $doc['title'] ? ' — ' . e($doc['title']) : '' ?></p>
    </div>
</section>

<section class="section section--tight-top">
    <div class="container doc-public">
        <div class="doc-public-main">
            <?php if (isset($_GET['accepted'])): ?>
            <div class="doc-alert doc-alert--success" role="status"><?= icon('check-circle') ?> Merci ! Votre acceptation a bien été enregistrée. Nous revenons vers vous très rapidement pour lancer le projet.</div>
            <?php endif; ?>
            <?php partial('document-sheet', ['doc' => $doc]); ?>
        </div>

        <aside class="doc-public-side">
            <div class="contact-card">
                <dl class="doc-facts">
                    <div><dt>Statut</dt><dd><span class="doc-status doc-status--<?= e($statusTone) ?>"><?= e($statusLabel) ?></span></dd></div>
                    <div><dt>Montant TTC</dt><dd class="doc-total"><?= e(money((float) $doc['total'], $doc['currency'])) ?></dd></div>
                    <?php if ($doc['doc_type'] === 'invoice' && $balance > 0 && $balance < (float) $doc['total']): ?><div><dt>Reste à payer</dt><dd><?= e(money($balance, $doc['currency'])) ?></dd></div><?php endif; ?>
                    <div><dt>Date</dt><dd><?= e(date('d/m/Y', strtotime($doc['issue_date']))) ?></dd></div>
                    <?php if ($doc['due_date']): ?><div><dt><?= $isQuote ? 'Valable jusqu\'au' : 'Échéance' ?></dt><dd><?= e(date('d/m/Y', strtotime($doc['due_date']))) ?></dd></div><?php endif; ?>
                </dl>
                <a class="btn btn-primary btn-block" href="<?= e(url('document/' . $doc['public_token'] . '/pdf')) ?>?dl=1"><?= icon('upload', 'icon') ?>Télécharger le PDF</a>
            </div>

            <?php if ($canAccept): ?>
            <form method="post" class="contact-card accept-card">
                <h2 class="aside-title">Accepter ce devis</h2>
                <?php if ($error): ?><p class="form-alert"><?= e($error) ?></p><?php endif; ?>
                <?= csrf_field() ?>
                <div class="field"><label for="accepted_by">Nom et fonction du signataire</label><input type="text" id="accepted_by" name="accepted_by" required maxlength="150" placeholder="Ex : Awa Diop, gérante"></div>
                <label class="checkbox"><input type="checkbox" name="agree" value="1" required><span>Je donne mon « bon pour accord » sur ce devis et j'accepte ses conditions.</span></label>
                <button class="btn btn-primary btn-block" type="submit"><?= icon('check', 'icon') ?>Accepter le devis</button>
            </form>
            <?php elseif ($isQuote && $doc['accepted_at']): ?>
            <div class="contact-card"><p><?= icon('check-circle', 'icon text-success') ?> Devis accepté le <?= e(date('d/m/Y', strtotime($doc['accepted_at']))) ?>.</p></div>
            <?php endif; ?>

            <?php if ($doc['doc_type'] === 'invoice' && $balance > 0 && setting('billing_iban')): ?>
            <div class="contact-card">
                <h2 class="aside-title">Règlement par virement</h2>
                <dl class="doc-facts">
                    <?php if (setting('billing_bank_name')): ?><div><dt>Banque</dt><dd><?= e(setting('billing_bank_name')) ?></dd></div><?php endif; ?>
                    <div><dt>IBAN</dt><dd class="mono"><?= e(setting('billing_iban')) ?></dd></div>
                    <?php if (setting('billing_bic')): ?><div><dt>BIC</dt><dd class="mono"><?= e(setting('billing_bic')) ?></dd></div><?php endif; ?>
                    <div><dt>Référence</dt><dd><?= e($doc['number']) ?></dd></div>
                </dl>
            </div>
            <?php endif; ?>

            <div class="contact-card">
                <h2 class="aside-title">Une question ?</h2>
                <ul class="contact-list">
                    <li><span class="icon-box icon-box--sm"><?= icon('mail') ?></span><div><a href="mailto:<?= e(setting('contact_email')) ?>?subject=<?= e(rawurlencode($docLabel . ' ' . $doc['number'])) ?>"><?= e(setting('contact_email')) ?></a></div></li>
                    <li><span class="icon-box icon-box--sm"><?= icon('phone') ?></span><div><a href="<?= e(tel_link(setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></div></li>
                </ul>
            </div>
        </aside>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
