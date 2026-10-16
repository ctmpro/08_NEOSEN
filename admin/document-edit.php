<?php
/**
 * Création / modification d'un devis, d'une facture ou d'un avoir (brouillons uniquement).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_crm_tables();
admin_csrf_guard();

$id = (int) ($_GET['id'] ?? 0);
$doc = $id ? crm_doc($id) : null;
if ($id && !$doc) {
    flash('error', 'Document introuvable.');
    redirect(admin_url('documents.php'));
}
if ($doc && $doc['status'] !== 'draft') {
    flash('info', 'Un document émis ne peut plus être modifié.' . ($doc['doc_type'] === 'invoice' ? ' Pour le corriger, créez un avoir.' : ''));
    redirect(admin_url('document.php', ['id' => $id]));
}
$type = $doc['doc_type'] ?? (in_array($_GET['type'] ?? '', ['quote', 'invoice'], true) ? $_GET['type'] : 'quote');
$errors = [];

if (is_post()) {
    $clientId = post_int('client_id');
    $client = crm_client($clientId);
    $currency = post('currency') === 'XOF' ? 'XOF' : 'EUR';
    $discount = max(0, min(100, (float) str_replace(',', '.', post('discount_pct', '0'))));
    $data = [
        'client_id' => $clientId,
        'title' => post('title') ?: null,
        'intro' => post('intro') ?: null,
        'notes' => post('notes') ?: null,
        'issue_date' => post('issue_date') ?: date('Y-m-d'),
        'due_date' => post('due_date') ?: null,
        'service_date' => post('service_date') ?: null,
        'currency' => $currency,
        'discount_pct' => $discount,
        'deposit_pct' => $type === 'quote' ? max(0, min(100, (float) str_replace(',', '.', post('deposit_pct', '0')))) : 0,
        'vat_mention' => post('vat_mention') ?: null,
    ];
    if (!$client) $errors[] = 'Choisissez un client.';
    $lines = array_values((array) ($_POST['lines'] ?? []));
    if (!array_filter($lines, fn($l) => trim((string) ($l['description'] ?? '')) !== '')) $errors[] = 'Ajoutez au moins une ligne.';

    if (!$errors) {
        if ($id) {
            $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
            db()->prepare("UPDATE crm_documents SET {$sets} WHERE id = ?")->execute([...array_values($data), $id]);
        } else {
            $data['doc_type'] = $type;
            $data['status'] = 'draft';
            $data['created_by'] = $_SESSION['admin_id'];
            db()->prepare('INSERT INTO crm_documents (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')')->execute(array_values($data));
            $id = (int) db()->lastInsertId();
            crm_log($clientId, crm_doc_label($type) . ($type === 'invoice' ? ' créée' : ' créé') . ' en brouillon', 'system', $id);
        }
        crm_save_lines($id, $lines, $discount, $currency);
        $next = post('then');
        if (in_array($next, ['issue', 'send'], true)) {
            if ($err = crm_issue($id)) {
                flash('error', $err);
                redirect(admin_url('document-edit.php', ['id' => $id]));
            }
            flash('success', crm_issued_message($type));
            redirect(admin_url('document.php', $next === 'send' ? ['id' => $id, 'send' => 1] : ['id' => $id]));
        }
        flash('success', 'Brouillon enregistré.');
        redirect(admin_url($next === 'stay' ? 'document-edit.php' : 'document.php', ['id' => $id]));
    }
    $doc = array_merge($doc ?? [], $data, ['doc_type' => $type]);
    $formLines = $lines;
}

$clientId = (int) ($doc['client_id'] ?? ($_GET['client'] ?? 0));
$preClient = $clientId ? crm_client($clientId) : null;
$d = $doc ?? [
    'client_id' => $clientId,
    'currency' => $preClient['currency'] ?? setting('billing_currency', 'EUR'),
    'issue_date' => date('Y-m-d'),
    'due_date' => crm_default_due($type, $clientId),
    'discount_pct' => 0,
    'deposit_pct' => $type === 'quote' ? (float) setting('billing_deposit_pct', 30) : 0,
];
$v = fn($k) => $d[$k] ?? '';
$formLines = $formLines ?? ($id ? crm_doc_lines($id) : [['line_type' => 'item', 'description' => '', 'details' => '', 'quantity' => 1, 'unit' => 'forfait', 'unit_price' => '', 'vat_rate' => setting('billing_default_vat', 20), 'discount_pct' => 0]]);

$clients = db()->query('SELECT id, company, first_name, last_name, kind, currency FROM crm_clients WHERE is_archived = 0 ORDER BY COALESCE(company, last_name)')->fetchAll();
$items = db()->query('SELECT * FROM crm_items WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
$vatRates = setting_lines('billing_vat_rates') ?: ['20', '0'];
$vatMentions = setting_lines('billing_vat_mentions');
$label = crm_doc_label($type);

admin_header($id ? 'Modifier le brouillon — ' . $label : ($type === 'invoice' ? 'Nouvelle facture' : 'Nouveau ' . mb_strtolower($label)), $type === 'quote' ? 'quotes' : 'invoices', [
    [icon('arrow-left', 'icon') . 'Retour', $id ? admin_url('document.php', ['id' => $id]) : admin_url('documents.php', ['type' => $type === 'credit_note' ? 'invoice' : $type]), 'btn-ghost'],
]);
?>
<?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<?php if (!$clients): ?>
<div class="card"><p>Créez d'abord un client ou un prospect. <a class="link" href="<?= e(admin_url('client-edit.php')) ?>">Nouveau client</a></p></div>
<?php else: ?>
<form method="post" class="edit-layout" data-dirty-check data-doc-form data-default-vat="<?= e(setting('billing_default_vat', '20')) ?>">
    <?= csrf_field() ?>
    <div class="edit-main">
        <section class="card">
            <div class="card-head"><h2><?= e($label) ?></h2><?php if ($type === 'credit_note' && !empty($d['source_id']) && ($src = crm_doc((int) $d['source_id']))): ?><span class="pill">Sur facture <?= e($src['number']) ?></span><?php endif; ?></div>
            <div class="form-grid">
                <div class="field">
                    <label for="f-client_id">Client <span class="req">*</span></label>
                    <select id="f-client_id" name="client_id" required data-client-select>
                        <option value="">— Choisir —</option>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" data-currency="<?= e($c['currency']) ?>"<?= (int) $c['id'] === (int) $v('client_id') ? ' selected' : '' ?>><?= e(crm_client_name($c)) ?><?= $c['kind'] === 'prospect' ? ' (prospect)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="help"><a class="link" href="<?= e(admin_url('client-edit.php')) ?>" target="_blank">+ Nouveau client</a></p>
                </div>
                <?= f_text('title', 'Objet', $v('title'), ['placeholder' => 'Ex : Création du site vitrine']) ?>
                <?= f_text('issue_date', 'Date', $v('issue_date'), ['type' => 'date']) ?>
                <?= f_text('due_date', $type === 'quote' ? 'Valable jusqu\'au' : 'Date d\'échéance', $v('due_date'), ['type' => 'date']) ?>
                <?= f_text('service_date', 'Date ou période d\'exécution', $v('service_date'), ['placeholder' => 'Ex : octobre 2026, du 01/10 au 15/11/2026']) ?>
                <?= f_select('currency', 'Devise', ['EUR' => 'Euro (€)', 'XOF' => 'Franc CFA (FCFA)'], $v('currency')) ?>
            </div>
            <?= f_textarea('intro', 'Texte d\'introduction (optionnel)', $v('intro'), ['rows' => 2]) ?>
        </section>

        <section class="card">
            <div class="card-head"><h2>Lignes</h2>
                <?php if ($items): ?>
                <select class="catalog-pick" data-catalog aria-label="Ajouter depuis le catalogue">
                    <option value="">+ Depuis le catalogue…</option>
                    <?php foreach ($items as $it): ?>
                    <option value="<?= (int) $it['id'] ?>" data-item='<?= e(json_encode(['description' => $it['name'], 'details' => $it['description'], 'unit' => $it['unit'], 'unit_price' => (float) $it['unit_price'], 'vat_rate' => (float) $it['vat_rate']], JSON_UNESCAPED_UNICODE)) ?>'><?= e($it['name']) ?> — <?= e(money((float) $it['unit_price'], $it['currency'])) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
            <div class="lines-editor" data-lines-editor data-vat-rates='<?= e(json_encode(array_values($vatRates))) ?>'>
                <div class="lines-head"><span></span><span>Désignation</span><span>Qté</span><span>Unité</span><span>PU HT</span><span>TVA %</span><span>Remise %</span><span class="num">Total HT</span><span></span></div>
                <div class="lines-body" data-lines>
                    <?php foreach ($formLines as $i => $l): $sec = ($l['line_type'] ?? 'item') === 'section'; ?>
                    <div class="line<?= $sec ? ' line--section' : '' ?>" data-line>
                        <span class="line-move"><button type="button" class="icon-btn" data-up title="Monter">↑</button><button type="button" class="icon-btn" data-down title="Descendre">↓</button></span>
                        <input type="hidden" name="lines[<?= $i ?>][line_type]" value="<?= $sec ? 'section' : 'item' ?>" data-f="line_type">
                        <span class="line-desc">
                            <input type="text" name="lines[<?= $i ?>][description]" value="<?= e($l['description']) ?>" placeholder="<?= $sec ? 'Titre de section' : 'Désignation' ?>" data-f="description" maxlength="255">
                            <?php if (!$sec): ?><textarea name="lines[<?= $i ?>][details]" rows="1" placeholder="Détails (optionnel)" data-f="details"><?= e($l['details'] ?? '') ?></textarea><?php endif; ?>
                        </span>
                        <?php if (!$sec): ?>
                        <input type="text" inputmode="decimal" name="lines[<?= $i ?>][quantity]" value="<?= e(rtrim(rtrim((string) $l['quantity'], '0'), '.')) ?>" data-f="quantity" aria-label="Quantité">
                        <input type="text" name="lines[<?= $i ?>][unit]" value="<?= e($l['unit'] ?? '') ?>" data-f="unit" aria-label="Unité" list="units">
                        <input type="text" inputmode="decimal" name="lines[<?= $i ?>][unit_price]" value="<?= e($l['unit_price'] === '' ? '' : (float) $l['unit_price']) ?>" data-f="unit_price" aria-label="Prix unitaire HT">
                        <select name="lines[<?= $i ?>][vat_rate]" data-f="vat_rate" aria-label="TVA"><?php $cur = (float) $l['vat_rate']; $rates = array_unique(array_merge($vatRates, [(string) $cur])); foreach ($rates as $r): ?><option value="<?= e($r) ?>"<?= (float) $r === $cur ? ' selected' : '' ?>><?= e(str_replace('.', ',', (string) (float) $r)) ?></option><?php endforeach; ?></select>
                        <input type="text" inputmode="decimal" name="lines[<?= $i ?>][discount_pct]" value="<?= e((float) ($l['discount_pct'] ?? 0) ?: '') ?>" data-f="discount_pct" aria-label="Remise %" placeholder="0">
                        <span class="line-total num" data-total>—</span>
                        <?php else: ?><span class="line-section-fill"></span><?php endif; ?>
                        <button type="button" class="icon-btn icon-btn--danger" data-remove title="Supprimer la ligne"><?= icon('trash') ?></button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <datalist id="units"><option value="forfait"><option value="jour"><option value="heure"><option value="mois"><option value="an"><option value="unité"><option value="page"></datalist>
                <div class="lines-actions">
                    <button type="button" class="btn btn-sm btn-ghost" data-add-line><?= icon('plus') ?> Ajouter une ligne</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-add-section><?= icon('plus') ?> Ajouter une section</button>
                </div>
                <div class="doc-totals" data-totals>
                    <div><span>Total HT</span><strong data-t="subtotal">—</strong></div>
                    <div class="field-inline"><span>Remise globale (%)</span><input type="text" inputmode="decimal" name="discount_pct" value="<?= e((float) $v('discount_pct') ?: '') ?>" placeholder="0" data-discount></div>
                    <div data-row-discount hidden><span>Remise</span><strong data-t="discount">—</strong></div>
                    <div><span>TVA</span><strong data-t="tax">—</strong></div>
                    <div class="grand"><span>Total TTC</span><strong data-t="total">—</strong></div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h2>Mentions & conditions</h2></div>
            <div class="field">
                <label for="f-vat_mention">Mention de TVA</label>
                <input type="text" id="f-vat_mention" name="vat_mention" value="<?= e($v('vat_mention')) ?>" list="vat-mentions" placeholder="Obligatoire si TVA à 0 % (ex : client hors UE)">
                <datalist id="vat-mentions"><?php foreach ($vatMentions as $m): ?><option value="<?= e($m) ?>"><?php endforeach; ?></datalist>
                <p class="help">Exemples proposés : prestation à un client hors UE (Sénégal…), autoliquidation UE, franchise en base.</p>
            </div>
            <?= f_textarea('notes', 'Notes / conditions particulières', $v('notes'), ['rows' => 3, 'help' => 'Ajoutées avant les conditions générales définies dans les réglages (Gestion — Devis & factures).']) ?>
        </section>
    </div>

    <aside class="edit-side">
        <section class="card sticky">
            <div class="card-head"><h2>Enregistrer</h2></div>
            <?php if ($type === 'quote'): ?>
            <?= f_text('deposit_pct', 'Acompte demandé (%)', $v('deposit_pct'), ['help' => 'Affiché sur le devis. 0 = pas d\'acompte.']) ?>
            <?php endif; ?>
            <div class="stack">
                <button type="submit" class="btn btn-ghost btn-block" name="then" value="stay"><?= icon('check') ?> Enregistrer le brouillon</button>
                <button type="submit" class="btn btn-ghost btn-block" name="then" value="issue"><?= icon('check-circle') ?> Enregistrer et émettre</button>
                <button type="submit" class="btn btn-primary btn-block" name="then" value="send"><?= icon('mail') ?> Émettre et envoyer</button>
            </div>
            <p class="help mt">L'émission attribue le numéro définitif<?= $type === 'quote' ? '' : ' et verrouille le document (obligation légale)' ?>.</p>
        </section>
    </aside>
</form>
<?php endif; ?>
<?php admin_footer(); ?>
