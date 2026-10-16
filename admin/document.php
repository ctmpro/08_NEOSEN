<?php
/**
 * Fiche d'un document : aperçu PDF, émission, envoi, acceptation, conversion, paiements, avoirs.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_crm_tables();
admin_csrf_guard();

$id = (int) ($_GET['id'] ?? 0);
$doc = crm_doc($id);
if (!$doc) {
    flash('error', 'Document introuvable.');
    redirect(admin_url('documents.php'));
}
$back = admin_url('document.php', ['id' => $id]);
$type = $doc['doc_type'];
$cur = $doc['currency'];

if (is_post()) {
    switch (post('action')) {
        case 'issue':
            ($err = crm_issue($id)) ? flash('error', $err) : flash('success', crm_issued_message($type));
            break;

        case 'send':
            if ($doc['status'] === 'draft' && ($err = crm_issue($id))) {
                flash('error', $err);
                break;
            }
            $doc = crm_doc($id);
            $to = post('to');
            $recipients = array_filter(csv_list($to), fn($m) => filter_var($m, FILTER_VALIDATE_EMAIL));
            if (!$recipients) {
                flash('error', 'Adresse email du destinataire invalide.');
                break;
            }
            $ok = crm_send_email($doc, implode(',', $recipients), post('subject'), post('body'));
            if ($ok) {
                db()->prepare('UPDATE crm_documents SET sent_at = NOW() WHERE id = ?')->execute([$id]);
                crm_log((int) $doc['client_id'], crm_doc_title($doc) . ($type === 'invoice' ? ' envoyée à ' : ' envoyé à ') . implode(', ', $recipients), 'email', $id);
                flash('success', 'Document envoyé par email.');
            } else {
                flash('error', 'L\'email n\'a pas pu être envoyé (vérifiez la configuration SMTP dans le .env). Vous pouvez télécharger le PDF et l\'envoyer manuellement.');
            }
            break;

        case 'mark_sent':
            db()->prepare('UPDATE crm_documents SET sent_at = COALESCE(sent_at, NOW()) WHERE id = ?')->execute([$id]);
            crm_log((int) $doc['client_id'], crm_doc_title($doc) . ($type === 'invoice' ? ' marquée comme envoyée' : ' marqué comme envoyé'), 'system', $id);
            break;

        case 'accept':
        case 'decline':
            if ($type === 'quote' && in_array($doc['status'], ['sent', 'declined', 'accepted'], true)) {
                $accept = post('action') === 'accept';
                db()->prepare('UPDATE crm_documents SET status = ?, accepted_at = ?, accepted_by = ? WHERE id = ?')
                    ->execute([$accept ? 'accepted' : 'declined', $accept ? date('Y-m-d H:i:s') : null, $accept ? (post('accepted_by') ?: 'validation interne') : null, $id]);
                db()->prepare('UPDATE crm_clients SET stage = ?, kind = IF(? = "won", "client", kind) WHERE id = ?')
                    ->execute([$accept ? 'won' : 'lost', $accept ? 'won' : 'lost', $doc['client_id']]);
                crm_log((int) $doc['client_id'], 'Devis ' . $doc['number'] . ($accept ? ' accepté' : ' refusé'), 'system', $id);
                flash('success', $accept ? 'Devis accepté.' : 'Devis marqué comme refusé.');
            }
            break;

        case 'reopen':
            if ($type === 'quote' && in_array($doc['status'], ['accepted', 'declined'], true)) {
                db()->prepare('UPDATE crm_documents SET status = "sent", accepted_at = NULL, accepted_by = NULL WHERE id = ?')->execute([$id]);
            }
            break;

        case 'convert':
            if ($type === 'quote' && $doc['status'] !== 'draft') {
                $newId = crm_copy_document($id, 'invoice', ['intro' => $doc['intro']]);
                // Déduction des factures d'acompte déjà émises sur ce devis
                $deps = db()->prepare('SELECT id, number FROM crm_documents WHERE source_id = ? AND is_deposit = 1 AND doc_type = "invoice" AND status NOT IN ("draft","cancelled")');
                $deps->execute([$id]);
                $lines = crm_doc_lines($newId);
                $factor = 1 - (float) $doc['discount_pct'] / 100 ?: 1; // la remise globale s'applique aussi à la déduction
                foreach ($deps->fetchAll() as $dep) {
                    foreach (crm_doc_lines((int) $dep['id']) as $dl) {
                        $lines[] = ['description' => 'Déduction de l\'acompte — facture ' . $dep['number'], 'quantity' => 1, 'unit' => 'forfait', 'unit_price' => -1 * round((float) $dl['line_total'] / $factor, 2), 'vat_rate' => $dl['vat_rate']];
                    }
                }
                crm_save_lines($newId, $lines, (float) $doc['discount_pct'], $cur);
                db()->prepare('UPDATE crm_documents SET status = "invoiced" WHERE id = ?')->execute([$id]);
                crm_log((int) $doc['client_id'], 'Devis ' . $doc['number'] . ' converti en facture', 'system', $newId);
                flash('success', 'Facture créée en brouillon à partir du devis. Vérifiez-la puis émettez-la.');
                redirect(admin_url('document-edit.php', ['id' => $newId]));
            }
            break;

        case 'deposit':
            if ($type === 'quote' && $doc['status'] !== 'draft') {
                $pct = max(1, min(100, (float) str_replace(',', '.', post('pct', (string) $doc['deposit_pct']))));
                $newId = crm_copy_document($id, 'invoice', ['title' => 'Acompte de ' . str_replace('.', ',', (string) $pct) . ' % — ' . ($doc['title'] ?: 'devis ' . $doc['number']), 'discount_pct' => 0, 'is_deposit' => 1]);
                // Une ligne d'acompte par taux de TVA du devis
                $calc = crm_compute(crm_doc_lines($id), (float) $doc['discount_pct'], $cur);
                $lines = [];
                foreach ($calc['taxes'] as $rate => $t) {
                    $lines[] = ['description' => 'Acompte de ' . str_replace('.', ',', (string) $pct) . ' % sur le devis ' . $doc['number'], 'details' => $doc['title'], 'quantity' => 1, 'unit' => 'forfait', 'unit_price' => crm_round($t['base'] * $pct / 100, $cur), 'vat_rate' => $rate];
                }
                crm_save_lines($newId, $lines, 0, $cur);
                crm_log((int) $doc['client_id'], 'Facture d\'acompte créée depuis le devis ' . $doc['number'], 'system', $newId);
                flash('success', 'Facture d\'acompte créée en brouillon.');
                redirect(admin_url('document-edit.php', ['id' => $newId]));
            }
            break;

        case 'credit_note':
            if ($type === 'invoice' && !in_array($doc['status'], ['draft', 'cancelled'], true)) {
                $newId = crm_copy_document($id, 'credit_note', ['title' => 'Avoir sur facture ' . $doc['number'], 'intro' => null, 'due_date' => null]);
                flash('success', 'Avoir créé en brouillon (montant total de la facture). Ajustez les lignes pour un avoir partiel, puis émettez-le.');
                redirect(admin_url('document-edit.php', ['id' => $newId]));
            }
            break;

        case 'duplicate':
            $newType = $type === 'credit_note' ? 'invoice' : $type;
            $newId = crm_copy_document($id, $newType, ['source_id' => null]);
            flash('success', 'Copie créée en brouillon.');
            redirect(admin_url('document-edit.php', ['id' => $newId]));

        case 'payment':
            if ($type === 'invoice' && in_array($doc['status'], ['issued', 'partial'], true)) {
                $amount = (float) str_replace([',', ' '], ['.', ''], post('amount'));
                if ($amount <= 0) {
                    flash('error', 'Montant invalide.');
                    break;
                }
                db()->prepare('INSERT INTO crm_payments (document_id, amount, paid_on, method, reference) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$id, crm_round($amount, $cur), post('paid_on') ?: date('Y-m-d'), post('method') ?: null, post('reference') ?: null]);
                crm_refresh_invoice($id);
                crm_log((int) $doc['client_id'], 'Paiement reçu : ' . money($amount, $cur, false) . ' (' . $doc['number'] . ')', 'system', $id);
                flash('success', 'Paiement enregistré.');
            }
            break;

        case 'delete_payment':
            db()->prepare('DELETE FROM crm_payments WHERE id = ? AND document_id = ?')->execute([post_int('payment_id'), $id]);
            crm_refresh_invoice($id);
            flash('success', 'Paiement supprimé.');
            break;

        case 'delete':
            if ($doc['status'] === 'draft' || ($type === 'quote' && $doc['status'] !== 'invoiced')) {
                db()->prepare('DELETE FROM crm_documents WHERE id = ?')->execute([$id]);
                crm_log((int) $doc['client_id'], crm_doc_title($doc) . ($type === 'invoice' ? ' supprimée' : ' supprimé'));
                flash('success', 'Document supprimé.');
                redirect(admin_url('documents.php', ['type' => $type === 'quote' ? 'quote' : 'invoice']));
            }
            flash('error', 'Une facture ou un avoir émis ne peut pas être supprimé : créez un avoir pour l\'annuler.');
            break;
    }
    redirect($back);
}

$doc = crm_doc($id);
[$sl, $st] = crm_doc_status($doc);
$client = crm_client((int) $doc['client_id']);
$payments = $type === 'invoice' ? crm_doc_payments($id) : [];
$balance = crm_balance($doc);
$related = db()->prepare('SELECT * FROM crm_documents WHERE source_id = ? OR id = ? ORDER BY id');
$related->execute([$id, (int) $doc['source_id']]);
$related = array_filter($related->fetchAll(), fn($r) => (int) $r['id'] !== $id);
$reminders = db()->prepare('SELECT * FROM crm_reminders WHERE document_id = ? ORDER BY sent_at DESC');
$reminders->execute([$id]);
$reminders = $reminders->fetchAll();
$isDraft = $doc['status'] === 'draft';
$warnings = $type === 'credit_note' ? [] : crm_client_warnings($client, $type);

// Email d'envoi pré-rempli
$tplKey = $type === 'quote' ? 'billing_send_quote' : 'billing_send_invoice';
$vars = crm_template_vars($doc);
$mailSubject = strtr((string) setting($tplKey . '_subject'), $vars);
$mailBody = strtr((string) setting($tplKey . '_body'), $vars);
if ($type === 'credit_note') {
    $mailSubject = 'Avoir ' . ($doc['number'] ?: '') . ' — ' . site_name();
    $mailBody = "Bonjour {$vars['{client}']},\n\nVeuillez trouver ci-joint l'avoir {$vars['{number}']} d'un montant de {$vars['{amount}']}.\n\nBien cordialement,\nL'équipe " . site_name();
}

admin_header(crm_doc_title($doc), $type === 'quote' ? 'quotes' : 'invoices', array_filter([
    [icon('arrow-left', 'icon') . 'Retour', admin_url('documents.php', ['type' => $type === 'quote' ? 'quote' : 'invoice']), 'btn-ghost'],
    $isDraft ? [icon('edit', 'icon') . 'Modifier', admin_url('document-edit.php', ['id' => $id]), 'btn-ghost'] : null,
]));
?>
<div class="doc-layout">
    <div class="doc-preview-wrap">
        <?php if ($warnings): ?>
        <div class="alert alert-warning"><strong>À vérifier avant <?= $isDraft ? 'émission' : 'le prochain document' ?> :</strong><ul class="warn-list"><?php foreach ($warnings as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul><a class="link" href="<?= e(admin_url('client-edit.php', ['id' => $client['id']])) ?>">Compléter la fiche client</a></div>
        <?php endif; ?>
        <div class="doc-toolbar">
            <a class="btn btn-sm btn-ghost" href="<?= e(admin_url('document-pdf.php', ['id' => $id])) ?>" target="_blank" rel="noopener"><?= icon('external') ?> Ouvrir le PDF</a>
            <a class="btn btn-sm btn-ghost" href="<?= e(admin_url('document-pdf.php', ['id' => $id, 'download' => 1])) ?>"><?= icon('upload') ?> Télécharger</a>
            <button type="button" class="btn btn-sm btn-ghost" onclick="window.print()"><?= icon('edit') ?> Imprimer</button>
        </div>
        <?php partial('document-sheet', ['doc' => $doc]); ?>
    </div>

    <aside class="doc-side">
        <section class="card">
            <div class="doc-status">
                <span class="pill pill--<?= e($st) ?> pill--lg"><?= e($sl) ?></span>
                <strong class="doc-amount"><?= $type === 'credit_note' ? '− ' : '' ?><?= e(money((float) $doc['total'], $cur)) ?></strong>
                <span class="muted small">TTC · <?= e(money((float) $doc['subtotal'] - (float) $doc['discount_amount'], $cur)) ?> HT</span>
            </div>
            <dl class="mini-dl">
                <div><dt>Client</dt><dd><a class="link" href="<?= e(admin_url('client.php', ['id' => $client['id']])) ?>"><?= e(crm_client_name($client)) ?></a></dd></div>
                <?php if ($doc['title']): ?><div><dt>Objet</dt><dd><?= e($doc['title']) ?></dd></div><?php endif; ?>
                <div><dt>Date</dt><dd><?= e($doc['issue_date'] ? date('d/m/Y', strtotime($doc['issue_date'])) : '—') ?></dd></div>
                <?php if ($doc['due_date']): ?><div><dt><?= $type === 'quote' ? 'Validité' : 'Échéance' ?></dt><dd><?= e(date('d/m/Y', strtotime($doc['due_date']))) ?></dd></div><?php endif; ?>
                <?php if ($type === 'invoice' && !$isDraft): ?><div><dt>Reste à payer</dt><dd class="<?= $balance > 0 ? 'text-danger strong' : '' ?>"><?= e(money($balance, $cur)) ?></dd></div><?php endif; ?>
                <div><dt>Envoi</dt><dd><?= $doc['sent_at'] ? 'Envoyé le ' . e(date('d/m/Y', strtotime($doc['sent_at']))) : '<span class="muted">Non envoyé</span>' ?></dd></div>
                <?php if ($doc['accepted_at']): ?><div><dt>Accepté</dt><dd><?= e(date('d/m/Y', strtotime($doc['accepted_at']))) ?><?= $doc['accepted_by'] ? ' — ' . e($doc['accepted_by']) : '' ?></dd></div><?php endif; ?>
            </dl>
            <?php if (!empty($doc['pdf_hash'])): ?>
            <p class="archive-note"><?= icon('shield') ?> PDF définitif archivé le <?= e(date('d/m/Y', strtotime($doc['issue_date']))) ?> — empreinte <code title="SHA-256 : <?= e($doc['pdf_hash']) ?>"><?= e(substr($doc['pdf_hash'], 0, 16)) ?>…</code></p>
            <?php endif; ?>
            <?php if ($doc['public_token']): ?>
            <div class="copy-field"><input type="text" readonly value="<?= e(crm_public_url($doc)) ?>" aria-label="Lien client"><button type="button" class="btn btn-sm btn-ghost" data-copy>Copier</button></div>
            <p class="help">Lien de consultation<?= $type === 'quote' ? ' et d\'acceptation en ligne' : '' ?> pour le client.</p>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card-head"><h2>Actions</h2></div>
            <div class="stack">
                <?php if ($isDraft): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="issue"><button class="btn btn-primary btn-block"><?= icon('check-circle') ?> Émettre (numéro définitif)</button></form>
                <?php endif; ?>

                <details class="send-box"<?= isset($_GET['send']) ? ' open' : '' ?>>
                    <summary class="btn btn-<?= $isDraft ? 'ghost' : 'primary' ?> btn-block"><?= icon('mail') ?> <?= $isDraft ? 'Émettre et envoyer' : 'Envoyer par email' ?></summary>
                    <form method="post" class="send-form"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="send">
                        <?= f_text('to', 'Destinataire(s)', $client['email'] ?? '', ['help' => 'Plusieurs adresses séparées par des virgules.']) ?>
                        <?= f_text('subject', 'Objet', $mailSubject) ?>
                        <?= f_textarea('body', 'Message', $mailBody, ['rows' => 8]) ?>
                        <p class="help">Le PDF est joint automatiquement.</p>
                        <button class="btn btn-primary btn-block"><?= icon('mail') ?> Envoyer</button>
                    </form>
                </details>

                <?php if (!$isDraft && !$doc['sent_at']): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_sent"><button class="btn btn-ghost btn-sm btn-block">Marquer comme envoyé (hors email)</button></form>
                <?php endif; ?>

                <?php if ($type === 'quote' && !$isDraft): ?>
                    <?php if (in_array($doc['status'], ['sent'], true)): ?>
                    <form method="post" class="inline-accept"><?= csrf_field() ?><input type="hidden" name="action" value="accept"><input type="text" name="accepted_by" placeholder="Accepté par (nom)"><button class="btn btn-sm btn-ghost"><?= icon('check') ?> Accepté</button></form>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="decline"><button class="btn btn-ghost btn-sm btn-block"><?= icon('close') ?> Marquer comme refusé</button></form>
                    <?php elseif (in_array($doc['status'], ['accepted', 'declined'], true)): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reopen"><button class="btn btn-ghost btn-sm btn-block">Remettre en attente</button></form>
                    <?php endif; ?>
                    <?php if ($doc['status'] !== 'invoiced' && $doc['status'] !== 'declined'): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="convert"><button class="btn btn-ghost btn-block"><?= icon('arrow') ?> Convertir en facture</button></form>
                    <form method="post" class="inline-accept"><?= csrf_field() ?><input type="hidden" name="action" value="deposit"><input type="text" name="pct" value="<?= e((float) $doc['deposit_pct'] ?: 30) ?>" aria-label="Pourcentage d'acompte" style="max-width:70px"><button class="btn btn-sm btn-ghost">% — Facture d'acompte</button></form>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($type === 'invoice' && in_array($doc['status'], ['issued', 'partial'], true)): ?>
                <a class="btn btn-ghost btn-block" href="<?= e(admin_url('reminders.php', ['doc' => $id])) ?>"><?= icon('clock') ?> Relancer le client</a>
                <?php endif; ?>
                <?php if ($type === 'invoice' && !in_array($doc['status'], ['draft', 'cancelled'], true)): ?>
                <form method="post" data-confirm="Créer un avoir pour annuler (tout ou partie de) cette facture ?"><?= csrf_field() ?><input type="hidden" name="action" value="credit_note"><button class="btn btn-ghost btn-sm btn-block">Créer un avoir</button></form>
                <?php endif; ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><button class="btn btn-ghost btn-sm btn-block">Dupliquer</button></form>
                <?php if ($isDraft || ($type === 'quote' && $doc['status'] !== 'invoiced')): ?>
                <form method="post" data-confirm="Supprimer définitivement ce document ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm btn-block"><?= icon('trash') ?> Supprimer</button></form>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($type === 'invoice' && !$isDraft): ?>
        <section class="card">
            <div class="card-head"><h2>Paiements</h2></div>
            <?php if ($payments): ?>
            <ul class="pay-list">
                <?php foreach ($payments as $p): ?>
                <li>
                    <span><strong><?= e(money((float) $p['amount'], $cur)) ?></strong><small class="muted d-block"><?= e(date('d/m/Y', strtotime($p['paid_on']))) ?><?= $p['method'] ? ' · ' . e($p['method']) : '' ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></small></span>
                    <form method="post" class="inline-form" data-confirm="Supprimer ce paiement ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_payment"><input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>"><button class="icon-btn icon-btn--danger" title="Supprimer"><?= icon('trash') ?></button></form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?><p class="muted small">Aucun paiement enregistré.</p><?php endif; ?>
            <?php if (in_array($doc['status'], ['issued', 'partial'], true)): ?>
            <form method="post" class="pay-form"><?= csrf_field() ?>
                <input type="hidden" name="action" value="payment">
                <div class="form-grid">
                    <?= f_text('amount', 'Montant', $balance > 0 ? (string) $balance : '', ['required' => true]) ?>
                    <?= f_text('paid_on', 'Date', date('Y-m-d'), ['type' => 'date']) ?>
                    <?= f_select('method', 'Moyen', array_combine(setting_lines('billing_payment_methods'), setting_lines('billing_payment_methods')) ?: ['Virement bancaire' => 'Virement bancaire']) ?>
                    <?= f_text('reference', 'Référence', '') ?>
                </div>
                <button class="btn btn-sm btn-primary"><?= icon('plus') ?> Enregistrer le paiement</button>
            </form>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($related): ?>
        <section class="card">
            <div class="card-head"><h2>Documents liés</h2></div>
            <ul class="list">
                <?php foreach ($related as $r): [$rl, $rt] = crm_doc_status($r); ?>
                <li><a class="list-row" href="<?= e(admin_url('document.php', ['id' => $r['id']])) ?>"><span class="list-main"><strong><?= e(crm_doc_title($r)) ?></strong><small><?= e(money((float) $r['total'], $r['currency'])) ?></small></span><span class="pill pill--<?= e($rt) ?>"><?= e($rl) ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if ($reminders): ?>
        <section class="card">
            <div class="card-head"><h2>Relances</h2></div>
            <ul class="pay-list">
                <?php foreach ($reminders as $r): ?>
                <li><span><strong><?= $type === 'quote' ? 'Relance' : 'Niveau ' . (int) $r['level'] ?></strong><small class="muted d-block"><?= e(format_date($r['sent_at'], true)) ?> · <?= e($r['recipient'] ?: 'hors email') ?><?= $r['sent_ok'] ? '' : ' · non envoyé' ?></small></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </aside>
</div>
<?php admin_footer(); ?>
