<?php
/**
 * Liens de paiement : création, partage (lien, QR code, WhatsApp, email) et suivi des paiements Stripe / PayDunya.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/qr.php';
require_admin();
require_crm_tables();
admin_csrf_guard();

$id = (int) ($_GET['id'] ?? 0);
$link = $id ? pay_link($id) : null;
if ($id && !$link) {
    flash('error', 'Lien introuvable.');
    redirect(admin_url('payment-links.php'));
}
$num = fn(string $v) => (float) str_replace([',', ' ', "\u{202F}"], ['.', '', ''], $v);

/* ------------------------------------------------------------ Actions */
if (is_post()) {
    $action = post('action');
    if ($action === 'create') {
        $docId = post_int('document_id');
        $doc = $docId ? crm_doc($docId) : null;
        $methods = array_values(array_intersect(array_keys(pay_providers()), (array) ($_POST['methods'] ?? [])));
        $data = [
            'document_id' => $doc['id'] ?? null,
            'client_id' => post_int('client_id') ?: ($doc['client_id'] ?? null),
            'title' => post('title') ?: ($doc ? 'Facture ' . $doc['number'] : ''),
            'description' => post('description') ?: null,
            'amount' => $num(post('amount', '0')),
            'currency' => $doc['currency'] ?? (post('currency') === 'XOF' ? 'XOF' : 'EUR'),
            'amount_mode' => post('amount_mode') === 'free' ? 'free' : 'fixed',
            'min_amount' => post('min_amount') !== '' ? $num(post('min_amount')) : null,
            'methods' => implode(',', $methods),
            'reusable' => post_bool('reusable'),
            'payer_name' => post('payer_name') ?: null,
            'payer_email' => mb_strtolower(post('payer_email')) ?: null,
            'payer_phone' => post('payer_phone') ?: null,
            'expires_at' => post('expires_at') ?: null,
        ];
        $errors = [];
        if ($data['title'] === '') $errors[] = 'Indiquez un intitulé.';
        if (!$methods) $errors[] = 'Choisissez au moins un moyen de paiement.';
        if (!$doc && $data['amount_mode'] === 'fixed' && $data['amount'] <= 0) $errors[] = 'Indiquez un montant (ou choisissez « montant libre »).';
        if ($doc && !in_array($doc['status'], ['issued', 'partial'], true)) $errors[] = 'Cette facture n\'est pas en attente de paiement.';
        if ($doc && $data['amount'] > crm_balance($doc) + 0.001) $errors[] = 'Le montant dépasse le reste à payer de la facture (' . money(crm_balance($doc), $doc['currency'], false) . ').';
        if ($data['payer_email'] && !filter_var($data['payer_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email du client invalide.';
        if ($errors) {
            flash('error', implode(' ', $errors));
            $_SESSION['paylink_old'] = $_POST;
            redirect(admin_url('payment-links.php', ['new' => 1] + ($docId ? ['doc' => $docId] : [])));
        }
        $newId = pay_create_link($data);
        if ($doc) crm_log((int) $doc['client_id'], 'Lien de paiement créé pour la facture ' . $doc['number'], 'system', (int) $doc['id']);
        flash('success', 'Lien de paiement créé. Copiez-le ou envoyez-le au client.');
        redirect(admin_url('payment-links.php', ['id' => $newId]));
    }
    if ($link) {
        switch ($action) {
            case 'send':
                $to = trim(post('to'));
                if (!$to) { flash('error', 'Indiquez un destinataire.'); break; }
                require_once ROOT_PATH . '/includes/mailer.php';
                $subject = post('subject');
                $body = post('body');
                $html = mail_layout($subject, '<div style="font-size:15px;line-height:1.7">' . nl2br(e($body)) . '</div>'
                    . '<p style="margin-top:24px"><a href="' . e(pay_link_url($link)) . '" style="display:inline-block;background:' . e(setting('color_primary')) . ';color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:600">Payer en ligne</a></p>');
                if (send_mail($to, $subject, $html, ['text' => $body, 'reply_to' => [setting('contact_email'), site_name()]])) {
                    db()->prepare('UPDATE pay_links SET sent_at = NOW() WHERE id = ?')->execute([$id]);
                    if ($link['client_id']) crm_log((int) $link['client_id'], 'Lien de paiement envoyé à ' . $to . ' (' . $link['title'] . ')', 'email', $link['document_id'] ? (int) $link['document_id'] : null);
                    flash('success', 'Lien envoyé à ' . $to . '.');
                } else {
                    flash('error', 'L\'email n\'a pas pu être envoyé. Vérifiez la configuration SMTP du fichier .env.');
                }
                break;
            case 'cancel':
                db()->prepare('UPDATE pay_links SET status = "cancelled" WHERE id = ?')->execute([$id]);
                flash('success', 'Lien désactivé : il ne permet plus de payer.');
                break;
            case 'reactivate':
                db()->prepare('UPDATE pay_links SET status = "active", expires_at = IF(expires_at < CURDATE(), NULL, expires_at) WHERE id = ?')->execute([$id]);
                flash('success', 'Lien réactivé.');
                break;
            case 'extend':
                db()->prepare('UPDATE pay_links SET expires_at = ? WHERE id = ?')->execute([post('expires_at') ?: null, $id]);
                flash('success', 'Date de validité mise à jour.');
                break;
            case 'check':
                $done = 0;
                $s = db()->prepare('SELECT * FROM pay_transactions WHERE link_id = ? AND status = "pending" AND provider_ref IS NOT NULL');
                $s->execute([$id]);
                foreach ($s->fetchAll() as $tx) {
                    try { if (pay_verify_tx($tx) === 'completed') $done++; } catch (Throwable $e) { flash('error', $e->getMessage()); }
                }
                flash('success', $done ? $done . ' paiement(s) confirmé(s).' : 'Aucun nouveau paiement confirmé.');
                break;
            case 'delete':
                $has = db()->prepare('SELECT COUNT(*) FROM pay_transactions WHERE link_id = ? AND status = "completed"');
                $has->execute([$id]);
                if ((int) $has->fetchColumn() > 0) {
                    flash('error', 'Ce lien a reçu des paiements : désactivez-le plutôt que de le supprimer.');
                    break;
                }
                db()->prepare('DELETE FROM pay_links WHERE id = ?')->execute([$id]);
                flash('success', 'Lien supprimé.');
                redirect(admin_url('payment-links.php'));
        }
        redirect(admin_url('payment-links.php', ['id' => $id]));
    }
    redirect(admin_url('payment-links.php'));
}

$providers = pay_providers();
$configBox = function () use ($providers) { ?>
    <section class="card">
        <div class="card-head"><h2>Prestataires de paiement</h2><a class="link" href="<?= e(admin_url('settings.php', ['tab' => 'payments'])) ?>">Réglages</a></div>
        <ul class="provider-list">
            <?php foreach ($providers as $key => $p): ?>
            <li>
                <span class="provider-dot provider-dot--<?= $p['configured'] && $p['enabled'] ? 'on' : 'off' ?>"></span>
                <span><strong><?= e($p['name']) ?></strong>
                    <small class="muted d-block"><?php
                        if (!$p['configured']) echo $key === 'stripe' ? 'Clé STRIPE_SECRET_KEY absente du fichier .env' : 'Clés PAYDUNYA_* absentes du fichier .env';
                        elseif (!$p['enabled']) echo 'Désactivé dans les réglages';
                        else echo 'Opérationnel — ' . ($p['test'] ? 'mode TEST' : 'mode PRODUCTION');
                    ?></small></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <details class="help-details">
            <summary>Adresses à déclarer chez les prestataires</summary>
            <p class="help">Webhook Stripe (événements <code>checkout.session.completed</code> et <code>checkout.session.async_payment_succeeded</code>), secret dans <code>STRIPE_WEBHOOK_SECRET</code> :</p>
            <div class="copy-field"><input type="text" readonly value="<?= e(absolute_url('', APP_DEFAULT_LANG) . 'api/stripe-webhook.php') ?>"><button type="button" class="btn btn-sm btn-ghost" data-copy>Copier</button></div>
            <p class="help">PayDunya (IPN, transmis automatiquement à chaque paiement) :</p>
            <div class="copy-field"><input type="text" readonly value="<?= e(absolute_url('', APP_DEFAULT_LANG) . 'api/paydunya-ipn.php') ?>"><button type="button" class="btn btn-sm btn-ghost" data-copy>Copier</button></div>
        </details>
    </section>
<?php };

/* ------------------------------------------------------------ Création */
if (isset($_GET['new'])) {
    $old = $_SESSION['paylink_old'] ?? [];
    unset($_SESSION['paylink_old']);
    $docId = (int) ($_GET['doc'] ?? ($old['document_id'] ?? 0));
    $doc = $docId ? crm_doc($docId) : null;
    $client = $doc ? crm_doc_client($doc) : null;
    $invoices = db()->query('SELECT d.id, d.number, d.total, d.amount_paid, d.currency, c.company, c.first_name, c.last_name FROM crm_documents d JOIN crm_clients c ON c.id = d.client_id WHERE d.doc_type = "invoice" AND d.status IN ("issued","partial") ORDER BY d.issue_date DESC LIMIT 200')->fetchAll();
    $clients = db()->query('SELECT id, company, first_name, last_name, email, phone FROM crm_clients WHERE is_archived = 0 ORDER BY COALESCE(company, last_name)')->fetchAll();
    $o = fn($k, $d = '') => $old[$k] ?? $d;
    $days = (int) setting('pay_link_validity_days', 30);
    $readyKeys = array_keys(pay_ready_providers());

    admin_header('Nouveau lien de paiement', 'paylinks', [[icon('arrow-left', 'icon') . 'Retour', admin_url('payment-links.php'), 'btn-ghost']]);
    ?>
    <form method="post" class="edit-layout" data-paylink-form>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="edit-main">
            <section class="card">
                <div class="card-head"><h2>Que doit régler le client ?</h2></div>
                <?= f_select('document_id', 'Facture à régler', array_column(array_map(fn($i) => ['id' => $i['id'], 'l' => $i['number'] . ' — ' . crm_client_name($i) . ' — reste ' . money((float) $i['total'] - (float) $i['amount_paid'], $i['currency'], false)], $invoices), 'l', 'id'), $doc['id'] ?? $o('document_id'), ['empty' => 'Aucune — paiement libre (acompte, prestation, produit…)', 'help' => 'Avec une facture, le paiement est enregistré automatiquement sur la facture et son statut mis à jour.']) ?>
                <div class="form-grid">
                    <?= f_text('title', 'Intitulé affiché au client', $o('title', $doc ? 'Facture ' . $doc['number'] : ''), ['placeholder' => 'Ex : Acompte site web — 30 %', 'help' => $doc ? '' : 'Obligatoire pour un paiement libre.']) ?>
                    <?= f_select('currency', 'Devise', ['EUR' => 'Euro (€)', 'XOF' => 'Franc CFA (FCFA)'], $doc['currency'] ?? $o('currency', setting('billing_currency', 'EUR')), ['help' => $doc ? 'Celle de la facture.' : 'PayDunya débite toujours en FCFA (conversion automatique).']) ?>
                    <?= f_select('amount_mode', 'Montant', ['fixed' => 'Montant fixe', 'free' => 'Montant libre (saisi par le client)'], $o('amount_mode', 'fixed')) ?>
                    <?= f_text('amount', $doc ? 'Montant (vide = reste à payer)' : 'Montant', $o('amount', ''), ['placeholder' => $doc ? money(crm_balance($doc), $doc['currency'], false) : '0', 'help' => $doc ? 'Indiquez un montant inférieur pour un paiement partiel ou un acompte.' : 'En montant libre : montant suggéré (facultatif).']) ?>
                    <?= f_text('min_amount', 'Montant minimum (montant libre)', $o('min_amount', ''), ['placeholder' => 'Facultatif']) ?>
                    <?= f_text('expires_at', 'Valable jusqu\'au', $o('expires_at', $days > 0 ? date('Y-m-d', strtotime('+' . $days . ' days')) : ''), ['type' => 'date', 'help' => 'Vide = sans date limite.']) ?>
                </div>
                <?= f_textarea('description', 'Description (facultatif)', $o('description', $doc['title'] ?? ''), ['rows' => 3]) ?>
            </section>
            <section class="card">
                <div class="card-head"><h2>Client</h2><p class="muted small">Pré-remplit la page de paiement et l'envoi du reçu.</p></div>
                <?= f_select('client_id', 'Fiche client (facultatif)', array_column(array_map(fn($c) => ['id' => $c['id'], 'l' => crm_client_name($c)], $clients), 'l', 'id'), $doc['client_id'] ?? $o('client_id'), ['empty' => '—']) ?>
                <div class="form-grid">
                    <?= f_text('payer_name', 'Nom du client', $o('payer_name', $client ? ($client['contact'] ?: $client['name']) : '')) ?>
                    <?= f_text('payer_email', 'Email', $o('payer_email', $client['email'] ?? ''), ['type' => 'email']) ?>
                    <?= f_text('payer_phone', 'Téléphone / WhatsApp', $o('payer_phone', $client['phone'] ?? ''), ['type' => 'tel']) ?>
                </div>
                <script type="application/json" data-clients><?= json_encode(array_column(array_map(fn($c) => ['id' => $c['id'], 'name' => trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: $c['company'], 'email' => $c['email'], 'phone' => $c['phone']], $clients), null, 'id'), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?></script>
            </section>
        </div>
        <aside class="edit-side">
            <section class="card sticky">
                <div class="card-head"><h2>Moyens de paiement</h2></div>
                <?php foreach ($providers as $key => $p): $wanted = $old ? in_array($key, (array) ($old['methods'] ?? []), true) : in_array($key, $readyKeys, true); ?>
                <label class="check-card<?= $p['configured'] && $p['enabled'] ? '' : ' is-off' ?>"><input type="checkbox" name="methods[]" value="<?= e($key) ?>" <?= $wanted ? 'checked' : '' ?>>
                    <span><strong><?= e($key === 'stripe' ? 'Carte bancaire — Stripe' : 'Mobile Money — PayDunya') ?></strong><small class="muted d-block"><?= $p['configured'] ? ($p['enabled'] ? ($p['test'] ? 'Mode test' : 'Production') : 'Désactivé dans les réglages') : 'Clés absentes du .env' ?></small></span></label>
                <?php endforeach; ?>
                <?= f_toggle('reusable', 'Lien permanent (plusieurs paiements)', (bool) $o('reusable', 0), 'Ex : lien affiché sur un devis, un salon ou un réseau social. Sinon, le lien se ferme après le paiement.') ?>
                <button class="btn btn-primary btn-block"><?= icon('check') ?> Créer le lien</button>
            </section>
            <?php $configBox(); ?>
        </aside>
    </form>
    <script>
    (function () {
        var form = document.querySelector('[data-paylink-form]');
        var data = JSON.parse(form.querySelector('[data-clients]').textContent || '{}');
        form.querySelector('[name=client_id]').addEventListener('change', function () {
            var c = data[this.value]; if (!c) return;
            ['name', 'email', 'phone'].forEach(function (k) { var f = form.querySelector('[name=payer_' + k + ']'); if (f && !f.value) f.value = c[k] || ''; });
        });
        form.querySelector('[name=document_id]').addEventListener('change', function () {
            if (this.value) window.location = <?= json_encode(admin_url('payment-links.php', ['new' => 1])) ?> + '&doc=' + this.value;
        });
    })();
    </script>
    <?php
    admin_footer();
    return;
}

/* ------------------------------------------------------------ Détail */
if ($link) {
    [$state, $stateLabel, $tone] = pay_link_state($link);
    $doc = pay_link_doc($link);
    $url = pay_link_url($link);
    $vars = pay_link_vars($link);
    $txs = db()->prepare('SELECT t.*, r.number AS receipt_number, r.token AS receipt_token FROM pay_transactions t LEFT JOIN crm_receipts r ON r.id = t.receipt_id WHERE t.link_id = ? ORDER BY t.id DESC');
    $txs->execute([$id]);
    $txs = $txs->fetchAll();
    $txLabels = ['pending' => ['En cours', 'info'], 'completed' => ['Payé', 'success'], 'failed' => ['Échec', 'danger'], 'cancelled' => ['Abandonné', 'muted']];

    admin_header($link['title'], 'paylinks', [
        [icon('arrow-left', 'icon') . 'Tous les liens', admin_url('payment-links.php'), 'btn-ghost'],
        [icon('external', 'icon') . 'Ouvrir la page', $url, 'btn-ghost', true],
    ]);
    ?>
    <div class="grid-2 grid-2--wide-left">
        <div>
            <section class="card">
                <div class="paylink-head">
                    <div>
                        <span class="pill pill--<?= e($tone) ?> pill--lg"><?= e($stateLabel) ?></span>
                        <strong class="doc-amount"><?= $link['amount_mode'] === 'free' && !$doc ? 'Montant libre' : e(money(pay_link_due($link), $link['currency'])) ?></strong>
                        <span class="muted small"><?= (int) $link['views'] ?> vue(s) · encaissé : <?= e(money((float) $link['paid_total'], $link['currency'])) ?></span>
                    </div>
                    <div class="paylink-qr" title="QR code du lien"><?= qr_svg($url) ?></div>
                </div>
                <div class="copy-field"><input type="text" readonly value="<?= e($url) ?>" aria-label="Lien de paiement"><button type="button" class="btn btn-sm btn-primary" data-copy>Copier le lien</button></div>
                <div class="form-actions">
                    <a class="btn btn-ghost" href="<?= e(pay_whatsapp_url($link)) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?> Envoyer par WhatsApp</a>
                    <a class="btn btn-ghost" href="<?= e('data:image/svg+xml;base64,' . base64_encode(qr_svg($url))) ?>" download="qr-paiement-<?= e(slugify($link['title'])) ?>.svg">QR code (SVG)</a>
                    <?php if ($state === 'active'): ?>
                    <form method="post" class="inline-form" data-confirm="Désactiver ce lien ? Le client ne pourra plus payer avec."><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="btn btn-ghost">Désactiver</button></form>
                    <?php elseif (in_array($state, ['cancelled', 'expired'], true)): ?>
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="reactivate"><button class="btn btn-ghost">Réactiver</button></form>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
                <div class="card-head"><h2>Paiements reçus et tentatives</h2>
                    <?php if (array_filter($txs, fn($t) => $t['status'] === 'pending')): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="check"><button class="link small">Vérifier les paiements en cours</button></form><?php endif; ?>
                </div>
                <?php if ($txs): ?>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Date</th><th>Client</th><th>Moyen</th><th class="num">Montant</th><th>Statut</th><th>Reçu</th></tr></thead>
                    <tbody>
                    <?php foreach ($txs as $t): [$tl, $tt] = $txLabels[$t['status']] ?? [$t['status'], 'muted']; ?>
                    <tr>
                        <td><?= e(format_date($t['completed_at'] ?: $t['created_at'], true)) ?></td>
                        <td><?= e($t['payer_name'] ?: '—') ?><small class="muted d-block"><?= e($t['payer_email'] ?: $t['payer_phone'] ?: '') ?></small></td>
                        <td><?= e($providers[$t['provider']]['name'] ?? $t['provider']) ?><small class="muted d-block mono"><?= e(mb_strimwidth((string) ($t['provider_payment'] ?: $t['provider_ref']), 0, 28, '…')) ?></small></td>
                        <td class="num"><?= e(money((float) $t['amount'], $t['currency'])) ?><?php if ($t['charged_currency'] !== $t['currency']): ?><small class="muted d-block"><?= e(money((float) $t['charged_amount'], $t['charged_currency'])) ?></small><?php endif; ?></td>
                        <td><span class="pill pill--<?= e($tt) ?>"><?= e($tl) ?></span></td>
                        <td><?php if ($t['receipt_number']): ?><a class="link" href="<?= e(admin_url('receipts.php', ['pdf' => $t['receipt_id']])) ?>" target="_blank" rel="noopener"><?= e($t['receipt_number']) ?></a><?php else: ?>—<?php endif; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php else: ?><p class="muted small">Aucune tentative de paiement pour l'instant.</p><?php endif; ?>
            </section>
        </div>

        <aside>
            <section class="card">
                <div class="card-head"><h2>Détails</h2></div>
                <dl class="mini-dl">
                    <?php if ($doc): ?><div><dt>Facture</dt><dd><a class="link" href="<?= e(admin_url('document.php', ['id' => $doc['id']])) ?>"><?= e($doc['number']) ?></a> · reste <?= e(money(crm_balance($doc), $doc['currency'])) ?></dd></div><?php endif; ?>
                    <?php if ($link['payer_name'] || $link['payer_email']): ?><div><dt>Client</dt><dd><?= e(implode(' · ', array_filter([$link['payer_name'], $link['payer_email'], $link['payer_phone']]))) ?></dd></div><?php endif; ?>
                    <div><dt>Moyens</dt><dd><?= e(implode(', ', array_map(fn($k) => $providers[$k]['name'] ?? $k, array_filter(explode(',', $link['methods']))))) ?></dd></div>
                    <div><dt>Type</dt><dd><?= $link['reusable'] ? 'Lien permanent' : 'Paiement unique' ?><?= $link['amount_mode'] === 'free' ? ' · montant libre' : '' ?></dd></div>
                    <div><dt>Créé le</dt><dd><?= e(format_date($link['created_at'], true)) ?></dd></div>
                    <div><dt>Envoyé</dt><dd><?= $link['sent_at'] ? e(format_date($link['sent_at'], true)) : '<span class="muted">Non</span>' ?></dd></div>
                </dl>
                <form method="post" class="inline-accept"><?= csrf_field() ?><input type="hidden" name="action" value="extend"><input type="date" name="expires_at" value="<?= e($link['expires_at']) ?>" aria-label="Valable jusqu'au"><button class="btn btn-sm btn-ghost">Modifier la validité</button></form>
            </section>
            <section class="card">
                <details class="send-box"<?= $link['sent_at'] ? '' : ' open' ?>>
                    <summary class="btn btn-primary btn-block"><?= icon('mail') ?> Envoyer par email</summary>
                    <form method="post" class="send-form"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="send">
                        <?= f_text('to', 'Destinataire', $link['payer_email'] ?? '') ?>
                        <?= f_text('subject', 'Objet', strtr((string) setting('pay_link_email_subject'), $vars)) ?>
                        <?= f_textarea('body', 'Message', strtr((string) setting('pay_link_email_body'), $vars), ['rows' => 9]) ?>
                        <button class="btn btn-primary btn-block"><?= icon('mail') ?> Envoyer</button>
                    </form>
                </details>
            </section>
            <?php if (!array_filter($txs, fn($t) => $t['status'] === 'completed')): ?>
            <form method="post" data-confirm="Supprimer ce lien ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm btn-block"><?= icon('trash') ?> Supprimer le lien</button></form>
            <?php endif; ?>
        </aside>
    </div>
    <?php
    admin_footer();
    return;
}

/* ------------------------------------------------------------ Liste */
$filter = (string) ($_GET['status'] ?? '');
$links = db()->query('SELECT l.*, d.number AS doc_number FROM pay_links l LEFT JOIN crm_documents d ON d.id = l.document_id ORDER BY l.id DESC LIMIT 300')->fetchAll();
if ($filter !== '') $links = array_filter($links, fn($l) => pay_link_state($l)[0] === $filter);
$month = db()->query('SELECT currency, SUM(amount) FROM pay_transactions WHERE status = "completed" AND completed_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01") GROUP BY currency')->fetchAll(PDO::FETCH_KEY_PAIR);
$pendingCount = (int) db()->query('SELECT COUNT(*) FROM pay_links WHERE status = "active" AND (expires_at IS NULL OR expires_at >= CURDATE())')->fetchColumn();
$countTx = (int) db()->query('SELECT COUNT(*) FROM pay_transactions WHERE status = "completed"')->fetchColumn();

admin_header('Liens de paiement', 'paylinks', [[icon('plus', 'icon') . 'Nouveau lien', admin_url('payment-links.php', ['new' => 1]), 'btn-primary']]);
?>
<div class="stat-grid stat-grid--sm">
    <div class="stat-card"><span class="stat-label">Encaissé en ligne ce mois</span><strong class="stat-value"><?= e(crm_money_list(array_map('floatval', $month)) ?: '0') ?></strong></div>
    <div class="stat-card"><span class="stat-label">Liens actifs</span><strong class="stat-value"><?= $pendingCount ?></strong></div>
    <div class="stat-card"><span class="stat-label">Paiements en ligne</span><strong class="stat-value"><?= $countTx ?></strong><span class="stat-sub">depuis le début</span></div>
</div>
<div class="grid-2 grid-2--wide-left">
    <section class="card">
        <div class="card-head">
            <h2><?= count($links) ?> lien(s)</h2>
            <div class="chips">
                <?php foreach (['' => 'Tous', 'active' => 'En attente', 'paid' => 'Payés', 'expired' => 'Expirés', 'cancelled' => 'Désactivés'] as $k => $l): ?>
                <a class="chip<?= $filter === $k ? ' is-active' : '' ?>" href="<?= e(admin_url('payment-links.php', $k !== '' ? ['status' => $k] : [])) ?>"><?= e($l) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($links): ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Lien</th><th class="num">Montant</th><th class="hide-sm">Créé le</th><th>Statut</th></tr></thead>
            <tbody>
            <?php foreach ($links as $l): [$s, $sl, $st] = pay_link_state($l); ?>
            <tr>
                <td><a href="<?= e(admin_url('payment-links.php', ['id' => $l['id']])) ?>"><strong><?= e($l['title']) ?></strong></a><small class="muted d-block"><?= e(implode(' · ', array_filter([$l['payer_name'], $l['doc_number'] ? 'Facture ' . $l['doc_number'] : null, $l['reusable'] ? 'Permanent' : null]))) ?></small></td>
                <td class="num"><?= $l['amount_mode'] === 'free' && !$l['document_id'] ? 'Libre' : e(money($l['document_id'] && (float) $l['amount'] == 0 ? pay_link_due($l) : (float) $l['amount'], $l['currency'])) ?><?php if ((float) $l['paid_total'] > 0): ?><small class="muted d-block">reçu <?= e(money((float) $l['paid_total'], $l['currency'])) ?></small><?php endif; ?></td>
                <td class="hide-sm"><?= e(format_date($l['created_at'])) ?></td>
                <td><span class="pill pill--<?= e($st) ?>"><?= e($sl) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php else: ?>
        <p class="empty">Aucun lien. <a href="<?= e(admin_url('payment-links.php', ['new' => 1])) ?>">Créer un lien de paiement</a> ou utilisez le bouton « Lien de paiement » sur une facture.</p>
        <?php endif; ?>
    </section>
    <aside><?php $configBox(); ?></aside>
</div>
<?php admin_footer(); ?>
