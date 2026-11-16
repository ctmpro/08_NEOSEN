<?php
/**
 * Reçus de paiement : liste, création manuelle (espèces, chèque, virement…), PDF et envoi par email.
 * Les paiements en ligne génèrent leur reçu automatiquement.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_crm_tables();
admin_csrf_guard();

// PDF
if (isset($_GET['pdf']) && ($r = receipt_get((int) $_GET['pdf']))) {
    receipt_pdf_response($r, !empty($_GET['dl']));
}

$num = fn(string $v) => (float) str_replace([',', ' ', "\u{202F}"], ['.', '', ''], $v);

if (is_post()) {
    $action = post('action');
    if ($action === 'create') {
        $docId = post_int('document_id');
        $doc = $docId ? crm_doc($docId) : null;
        $clientId = post_int('client_id') ?: ($doc['client_id'] ?? 0);
        $client = $clientId ? crm_client($clientId) : null;
        $amount = $num(post('amount'));
        $currency = $doc['currency'] ?? (post('currency') === 'XOF' ? 'XOF' : 'EUR');
        $payer = post('payer_name') ?: ($client ? crm_client_name($client) : '');
        $errors = [];
        if ($amount <= 0) $errors[] = 'Montant invalide.';
        if ($payer === '') $errors[] = 'Indiquez le nom du payeur.';
        if ($doc && post_bool('record_payment') && !in_array($doc['status'], ['issued', 'partial'], true)) $errors[] = 'Cette facture n\'attend plus de paiement.';
        if ($errors) {
            flash('error', implode(' ', $errors));
            $_SESSION['receipt_old'] = $_POST;
            redirect(admin_url('receipts.php', ['new' => 1]));
        }
        $paymentId = null;
        if ($doc && post_bool('record_payment')) {
            db()->prepare('INSERT INTO crm_payments (document_id, amount, paid_on, method, reference) VALUES (?, ?, ?, ?, ?)')
                ->execute([$doc['id'], crm_round($amount, $currency), post('paid_on') ?: date('Y-m-d'), post('method') ?: null, post('reference') ?: null]);
            $paymentId = (int) db()->lastInsertId();
            crm_refresh_invoice((int) $doc['id']);
            crm_log((int) $doc['client_id'], 'Paiement reçu : ' . money($amount, $currency, false) . ' (' . $doc['number'] . ')', 'system', (int) $doc['id']);
        }
        $rid = receipt_create([
            'client_id' => $clientId ?: null, 'document_id' => $doc['id'] ?? null, 'payment_id' => $paymentId,
            'payer_name' => $payer, 'payer_email' => mb_strtolower(post('payer_email')) ?: ($client['email'] ?? null), 'payer_phone' => post('payer_phone') ?: ($client['phone'] ?? null),
            'amount' => crm_round($amount, $currency), 'currency' => $currency, 'paid_on' => post('paid_on') ?: date('Y-m-d'),
            'method' => post('method') ?: null, 'reference' => post('reference') ?: null,
            'description' => post('description') ?: ($doc ? 'Règlement de la facture ' . $doc['number'] : null), 'notes' => post('notes') ?: null,
        ]);
        $r = receipt_get($rid);
        if ($clientId) crm_log($clientId, 'Reçu ' . $r['number'] . ' émis (' . money($amount, $currency, false) . ')', 'system', $doc['id'] ?? null);
        if (post_bool('send') && $r['payer_email']) {
            $sent = receipt_send($r, $r['payer_email']);
            flash($sent ? 'success' : 'error', $sent ? 'Reçu envoyé à ' . $r['payer_email'] . '.' : 'Le reçu n\'a pas pu être envoyé par email.');
        }
        flash('success', 'Reçu ' . $r['number'] . ' créé.');
        redirect(admin_url('receipts.php', ['id' => $rid]));
    }
    if ($action === 'send' && ($r = receipt_get(post_int('id')))) {
        $to = trim(post('to'));
        $ok = $to !== '' && receipt_send($r, $to, post('subject') ?: null, post('body') ?: null);
        flash($ok ? 'success' : 'error', $ok ? 'Reçu envoyé à ' . $to . '.' : 'L\'email n\'a pas pu être envoyé.');
        redirect(admin_url('receipts.php', ['id' => $r['id']]));
    }
    redirect(admin_url('receipts.php'));
}

/* ------------------------------------------------------------ Création */
if (isset($_GET['new'])) {
    $old = $_SESSION['receipt_old'] ?? [];
    unset($_SESSION['receipt_old']);
    $o = fn($k, $d = '') => $old[$k] ?? $d;
    $docId = (int) ($_GET['doc'] ?? $o('document_id', 0));
    $doc = $docId ? crm_doc($docId) : null;
    $invoices = db()->query('SELECT d.id, d.number, d.total, d.amount_paid, d.currency, d.status, c.company, c.first_name, c.last_name FROM crm_documents d JOIN crm_clients c ON c.id = d.client_id WHERE d.doc_type = "invoice" AND d.status IN ("issued","partial","paid") ORDER BY d.issue_date DESC LIMIT 200')->fetchAll();
    $clients = db()->query('SELECT id, company, first_name, last_name FROM crm_clients WHERE is_archived = 0 ORDER BY COALESCE(company, last_name)')->fetchAll();
    $methods = setting_lines('billing_payment_methods') ?: ['Espèces', 'Virement bancaire'];

    admin_header('Nouveau reçu', 'receipts', [[icon('arrow-left', 'icon') . 'Retour', admin_url('receipts.php'), 'btn-ghost']]);
    ?>
    <form method="post" class="edit-layout">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="edit-main">
            <section class="card">
                <div class="card-head"><h2>Paiement reçu</h2></div>
                <div class="form-grid">
                    <?= f_text('amount', 'Montant reçu', $o('amount', $doc ? (string) crm_balance($doc) : ''), ['required' => true]) ?>
                    <?= f_select('currency', 'Devise', ['EUR' => 'Euro (€)', 'XOF' => 'Franc CFA (FCFA)'], $doc['currency'] ?? $o('currency', setting('billing_currency', 'EUR'))) ?>
                    <?= f_text('paid_on', 'Date du paiement', $o('paid_on', date('Y-m-d')), ['type' => 'date']) ?>
                    <?= f_select('method', 'Moyen de paiement', array_combine($methods, $methods), $o('method')) ?>
                    <?= f_text('reference', 'Référence (n° de transaction, chèque…)', $o('reference')) ?>
                    <?= f_text('description', 'Objet', $o('description'), ['placeholder' => $doc ? 'Règlement de la facture ' . $doc['number'] : 'Ex : Acompte création site web']) ?>
                </div>
                <?= f_textarea('notes', 'Mention complémentaire (imprimée sur le reçu)', $o('notes'), ['rows' => 2]) ?>
            </section>
            <section class="card">
                <div class="card-head"><h2>Payeur</h2></div>
                <?= f_select('client_id', 'Fiche client (facultatif)', array_column(array_map(fn($c) => ['id' => $c['id'], 'l' => crm_client_name($c)], $clients), 'l', 'id'), $doc['client_id'] ?? $o('client_id'), ['empty' => '—']) ?>
                <div class="form-grid">
                    <?= f_text('payer_name', 'Nom du payeur', $o('payer_name'), ['help' => 'Vide = nom de la fiche client.']) ?>
                    <?= f_text('payer_email', 'Email', $o('payer_email'), ['type' => 'email']) ?>
                    <?= f_text('payer_phone', 'Téléphone', $o('payer_phone'), ['type' => 'tel']) ?>
                </div>
            </section>
        </div>
        <aside class="edit-side">
            <section class="card sticky">
                <div class="card-head"><h2>Facture liée</h2></div>
                <?= f_select('document_id', 'Facture (facultatif)', array_column(array_map(fn($i) => ['id' => $i['id'], 'l' => $i['number'] . ' — ' . crm_client_name($i) . ($i['status'] === 'paid' ? ' (réglée)' : ' — reste ' . money((float) $i['total'] - (float) $i['amount_paid'], $i['currency'], false))], $invoices), 'l', 'id'), $doc['id'] ?? $o('document_id'), ['empty' => 'Aucune']) ?>
                <?= f_toggle('record_payment', 'Enregistrer aussi le paiement sur la facture', (bool) $o('record_payment', $doc && $doc['status'] !== 'paid' ? 1 : 0), 'Met à jour le reste à payer et le statut de la facture.') ?>
                <?= f_toggle('send', 'Envoyer le reçu par email au payeur', (bool) $o('send', 1)) ?>
                <button class="btn btn-primary btn-block"><?= icon('check') ?> Créer le reçu</button>
            </section>
        </aside>
    </form>
    <?php
    admin_footer();
    return;
}

/* ------------------------------------------------------------ Liste */
$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT r.*, d.number AS doc_number FROM crm_receipts r LEFT JOIN crm_documents d ON d.id = r.document_id';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE r.number LIKE ? OR r.payer_name LIKE ? OR r.payer_email LIKE ? OR r.reference LIKE ? OR d.number LIKE ?';
    $params = array_fill(0, 5, '%' . $q . '%');
}
$stmt = db()->prepare($sql . ' ORDER BY r.id DESC LIMIT 300');
$stmt->execute($params);
$receipts = $stmt->fetchAll();
$open = (int) ($_GET['id'] ?? 0);
$monthTotal = db()->query('SELECT currency, SUM(amount) FROM crm_receipts WHERE paid_on >= DATE_FORMAT(CURDATE(), "%Y-%m-01") GROUP BY currency')->fetchAll(PDO::FETCH_KEY_PAIR);

admin_header('Reçus', 'receipts', [[icon('plus', 'icon') . 'Nouveau reçu', admin_url('receipts.php', ['new' => 1]), 'btn-primary']]);
?>
<div class="stat-grid stat-grid--sm">
    <div class="stat-card"><span class="stat-label">Reçus ce mois</span><strong class="stat-value"><?= e(crm_money_list(array_map('floatval', $monthTotal)) ?: '0') ?></strong></div>
    <div class="stat-card"><span class="stat-label">Nombre de reçus</span><strong class="stat-value"><?= count($receipts) ?></strong><span class="stat-sub">numérotation continue (<?= e(setting('billing_receipt_prefix', 'REC')) ?>-<?= date('Y') ?>-0001…)</span></div>
</div>
<section class="card">
    <div class="card-head">
        <h2>Reçus de paiement</h2>
        <form method="get" class="inline-form"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher (n°, client, référence…)" class="search-input"></form>
    </div>
    <?php if ($receipts): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>N°</th><th>Payeur</th><th class="hide-sm">Moyen</th><th class="num">Montant</th><th class="hide-sm">Date</th><th class="col-actions"></th></tr></thead>
        <tbody>
        <?php foreach ($receipts as $r): ?>
        <tr<?= $open === (int) $r['id'] ? ' class="is-highlight"' : '' ?>>
            <td><strong class="mono"><?= e($r['number']) ?></strong><?php if ($r['doc_number']): ?><small class="muted d-block">Facture <?= e($r['doc_number']) ?></small><?php endif; ?></td>
            <td><?= e($r['payer_name']) ?><small class="muted d-block"><?= e($r['description'] ?: '') ?></small></td>
            <td class="hide-sm"><?= e($r['method'] ?: '—') ?><?php if ($r['sent_at']): ?><small class="muted d-block">Envoyé le <?= e(date('d/m/Y', strtotime($r['sent_at']))) ?></small><?php endif; ?></td>
            <td class="num"><strong><?= e(money((float) $r['amount'], $r['currency'])) ?></strong></td>
            <td class="hide-sm"><?= e(date('d/m/Y', strtotime($r['paid_on']))) ?></td>
            <td class="col-actions"><div class="receipt-row-actions">
                <a class="icon-btn" href="<?= e(admin_url('receipts.php', ['pdf' => $r['id']])) ?>" target="_blank" rel="noopener" title="Voir le PDF"><?= icon('external') ?></a>
                <a class="icon-btn" href="<?= e(admin_url('receipts.php', ['pdf' => $r['id'], 'dl' => 1])) ?>" title="Télécharger"><?= icon('upload') ?></a>
                <a class="icon-btn" href="<?= e(admin_url('receipts.php', ['id' => $r['id']])) ?>#send-<?= (int) $r['id'] ?>" title="Envoyer par email"><?= icon('mail') ?></a>
            </div></td>
        </tr>
        <?php if ($open === (int) $r['id']): ?>
        <tr id="send-<?= (int) $r['id'] ?>"><td colspan="6">
            <form method="post" class="send-form send-form--inline"><?= csrf_field() ?>
                <input type="hidden" name="action" value="send"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <div class="form-grid">
                    <?= f_text('to', 'Envoyer à', $r['payer_email'] ?? '') ?>
                    <div class="field"><label>Lien client</label><div class="copy-field"><input type="text" readonly value="<?= e(receipt_url($r)) ?>"><button type="button" class="btn btn-sm btn-ghost" data-copy>Copier</button></div></div>
                </div>
                <button class="btn btn-sm btn-primary"><?= icon('mail') ?> Envoyer le reçu</button>
            </form>
        </td></tr>
        <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php else: ?>
    <p class="empty">Aucun reçu. Les paiements en ligne créent leur reçu automatiquement ; vous pouvez aussi <a href="<?= e(admin_url('receipts.php', ['new' => 1])) ?>">créer un reçu</a> pour un paiement en espèces, chèque ou virement.</p>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
