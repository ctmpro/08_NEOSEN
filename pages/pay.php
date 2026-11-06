<?php
/**
 * Page de paiement transmise au client : /payer/{jeton}
 *   POST              → démarre le paiement chez Stripe ou PayDunya
 *   /retour?tx=…      → retour du prestataire (vérification immédiate du paiement)
 *   ?annule=…         → paiement abandonné
 *   ?merci=…          → confirmation + reçu
 */
require_once ROOT_PATH . '/includes/payments.php';

$link = null;
try {
    $link = pay_link_by_token((string) ($params['slug'] ?? ''));
} catch (Throwable $e) {
    error_log('NEOSEN: liens de paiement indisponibles — ' . $e->getMessage());
}
if (!$link) {
    http_response_code(404);
    require ROOT_PATH . '/pages/404.php';
    return;
}
$action = (string) ($params['action'] ?? '');
$base = url('payer/' . $link['token']);
$error = null;
$notice = null;

/* ------------------------------------------------ Retour du prestataire */
if ($action === 'retour') {
    $tx = pay_tx((int) ($_GET['tx'] ?? 0));
    if (!$tx || (int) $tx['link_id'] !== (int) $link['id']) redirect($base);
    try {
        $status = pay_verify_tx($tx);
    } catch (Throwable $e) {
        error_log('NEOSEN: vérification du paiement ' . $tx['id'] . ' — ' . $e->getMessage());
        $status = 'pending';
    }
    redirect($base . ($status === 'completed' ? '?merci=' . $tx['id'] : '?attente=' . $tx['id']));
}
if ($action !== '') redirect($base);

/* ------------------------------------------------ Paiement abandonné */
if (isset($_GET['annule'])) {
    db()->prepare('UPDATE pay_transactions SET status = "cancelled" WHERE id = ? AND link_id = ? AND status = "pending"')->execute([(int) $_GET['annule'], $link['id']]);
    $notice = 'Paiement annulé : aucun montant n\'a été débité. Vous pouvez réessayer quand vous le souhaitez.';
}

/* ------------------------------------------------ Paiement en attente (mobile money) */
$waitingTx = null;
if (isset($_GET['attente'])) {
    $waitingTx = pay_tx((int) $_GET['attente']);
    if ($waitingTx && (int) $waitingTx['link_id'] === (int) $link['id']) {
        try {
            if (pay_verify_tx($waitingTx) === 'completed') redirect($base . '?merci=' . $waitingTx['id']);
        } catch (Throwable $e) {}
        $waitingTx = pay_tx((int) $waitingTx['id']);
    } else {
        $waitingTx = null;
    }
}

/* ------------------------------------------------ Confirmation */
$doneTx = null;
$receipt = null;
if (isset($_GET['merci'])) {
    $doneTx = pay_tx((int) $_GET['merci']);
    if ($doneTx && (int) $doneTx['link_id'] === (int) $link['id'] && $doneTx['status'] === 'completed') {
        $receipt = $doneTx['receipt_id'] ? receipt_get((int) $doneTx['receipt_id']) : null;
        $link = pay_link((int) $link['id']);
    } else {
        $doneTx = null;
    }
}

$doc = pay_link_doc($link);
[$state, $stateLabel] = pay_link_state($link);
$methods = pay_link_methods($link);
$due = pay_link_due($link);
$free = $link['amount_mode'] === 'free';
$canPay = $state === 'active' && $methods && ($free || $due > 0);
$cur = $link['currency'];

/* ------------------------------------------------ Démarrage du paiement */
$old = ['name' => $link['payer_name'], 'email' => $link['payer_email'], 'phone' => $link['payer_phone'], 'amount' => $free ? ($link['amount'] > 0 ? (string) (float) $link['amount'] : ($doc ? (string) $due : '')) : ''];
if (is_post() && $canPay) {
    $provider = (string) ($_POST['provider'] ?? '');
    $old = ['name' => trim((string) ($_POST['name'] ?? '')), 'email' => mb_strtolower(trim((string) ($_POST['email'] ?? ''))), 'phone' => trim((string) ($_POST['phone'] ?? '')), 'amount' => trim((string) ($_POST['amount'] ?? ''))];
    $amount = $due;
    if ($free) {
        $amount = crm_round((float) str_replace([',', ' ', "\u{202F}"], ['.', '', ''], $old['amount']), $cur);
        $min = max((float) ($link['min_amount'] ?? 0), $cur === 'XOF' ? 200 : 1);
        if ($amount < $min) $error = 'Le montant minimum est de ' . money($min, $cur, false) . '.';
        elseif ($doc && $amount > $due) $error = 'Le montant ne peut pas dépasser le reste à payer (' . money($due, $cur, false) . ').';
    }
    $recent = db()->prepare('SELECT COUNT(*) FROM pay_transactions WHERE link_id = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)');
    $recent->execute([$link['id']]);
    if (!csrf_check()) $error = t('form.error.csrf');
    elseif (!isset($methods[$provider])) $error = 'Moyen de paiement indisponible.';
    elseif ($old['name'] === '' || mb_strlen($old['name']) > 150) $error = 'Merci d\'indiquer votre nom.';
    elseif ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $error = 'Adresse email invalide.';
    elseif ($old['phone'] !== '' && !preg_match('/^[+()\d\s.\-]{6,40}$/', $old['phone'])) $error = 'Numéro de téléphone invalide.';
    elseif ((int) $recent->fetchColumn() >= 15) $error = 'Trop de tentatives, merci de réessayer dans quelques minutes.';

    if (!$error) {
        try {
            redirect(pay_start($link, $provider, $amount, $old), 303);
        } catch (Throwable $e) {
            error_log('NEOSEN: démarrage du paiement — ' . $e->getMessage());
            $error = 'Le paiement n\'a pas pu être initialisé. Merci de réessayer ou de choisir un autre moyen de paiement.' . (APP_DEBUG ? ' (' . $e->getMessage() . ')' : '');
        }
    }
}

if (!is_post() && !$doneTx && !$waitingTx) {
    db()->prepare('UPDATE pay_links SET views = views + 1 WHERE id = ?')->execute([$link['id']]);
}

$seller = crm_seller_info();
$current_page = 'pay';
$meta['title'] = setting('pay_page_title') . ' — ' . $link['title'] . ' | ' . site_name();
$meta['description'] = $link['title'];
$meta['noindex'] = true;
require ROOT_PATH . '/includes/header.php';
?>
<section class="page-hero page-hero--doc page-hero--pay">
    <div class="page-hero-bg" aria-hidden="true"><div class="grid-lines"></div><div class="glow glow-1"></div></div>
    <div class="container">
        <p class="eyebrow"><?= icon('shield', 'icon') ?> <?= e(setting('pay_page_title')) ?> · <?= e(site_name()) ?></p>
        <h1 class="page-title"><?= e($link['title']) ?></h1>
        <?php if ($link['payer_name']): ?><p class="page-lead"><?= e($link['payer_name']) ?></p><?php endif; ?>
    </div>
</section>

<section class="section section--tight-top">
    <div class="container pay-layout">
        <div class="pay-main">
            <?php if ($doneTx): ?>
            <div class="pay-card pay-card--success">
                <span class="pay-check"><?= icon('check-circle') ?></span>
                <h2>Paiement confirmé</h2>
                <p class="pay-amount-lg"><?= e(money((float) $doneTx['amount'], $doneTx['currency'])) ?></p>
                <p><?= e(setting('pay_success_text')) ?></p>
                <?php if ($receipt): ?>
                <div class="pay-actions">
                    <a class="btn btn-primary" href="<?= e(url('recu/' . $receipt['token'])) ?>?dl=1"><?= icon('upload', 'icon') ?>Télécharger le reçu <?= e($receipt['number']) ?></a>
                    <a class="btn btn-ghost" href="<?= e(url('recu/' . $receipt['token'])) ?>" target="_blank" rel="noopener">Voir le reçu</a>
                </div>
                <?php endif; ?>
            </div>
            <?php elseif ($waitingTx): ?>
            <div class="pay-card">
                <span class="pay-check pay-check--wait"><?= icon('clock') ?></span>
                <h2>Paiement en cours de confirmation</h2>
                <p>Si vous avez validé le paiement sur votre téléphone, la confirmation arrive en général en quelques secondes. Cette page se met à jour automatiquement.</p>
                <p class="pay-actions"><a class="btn btn-ghost" href="<?= e($base . '?attente=' . $waitingTx['id']) ?>"><?= icon('refresh', 'icon') ?>Vérifier maintenant</a><a class="btn btn-ghost" href="<?= e($base) ?>">Recommencer</a></p>
                <meta http-equiv="refresh" content="8;url=<?= e($base . '?attente=' . $waitingTx['id']) ?>">
            </div>
            <?php elseif ($state === 'paid'): ?>
            <div class="pay-card pay-card--success"><span class="pay-check"><?= icon('check-circle') ?></span><h2>Ce paiement a déjà été effectué</h2><p>Merci ! Aucun montant n'est plus dû. Pour toute question, contactez-nous.</p></div>
            <?php elseif ($state === 'expired'): ?>
            <div class="pay-card"><h2>Ce lien de paiement a expiré</h2><p>Contactez-nous pour recevoir un nouveau lien.</p></div>
            <?php elseif ($state === 'cancelled'): ?>
            <div class="pay-card"><h2>Ce lien de paiement n'est plus actif</h2><p>Contactez-nous si vous pensez qu'il s'agit d'une erreur.</p></div>
            <?php elseif (!$methods): ?>
            <div class="pay-card"><h2>Paiement en ligne momentanément indisponible</h2><p>Merci de nous contacter pour convenir d'un autre moyen de règlement.</p></div>
            <?php else: ?>
            <form method="post" class="pay-card pay-form" novalidate>
                <?= csrf_field() ?>
                <h2>Vos informations</h2>
                <p class="pay-muted"><?= e(setting('pay_page_intro')) ?></p>
                <?php if ($notice): ?><p class="form-alert form-alert--info"><?= e($notice) ?></p><?php endif; ?>
                <?php if ($error): ?><p class="form-alert"><?= e($error) ?></p><?php endif; ?>
                <div class="pay-fields">
                    <div class="field"><label for="p-name">Nom et prénom / société *</label><input id="p-name" type="text" name="name" required maxlength="150" value="<?= e($old['name']) ?>" autocomplete="name"></div>
                    <div class="field"><label for="p-email">Email (pour recevoir le reçu)</label><input id="p-email" type="email" name="email" value="<?= e($old['email']) ?>" autocomplete="email"></div>
                    <div class="field"><label for="p-phone">Téléphone</label><input id="p-phone" type="tel" name="phone" value="<?= e($old['phone']) ?>" autocomplete="tel"></div>
                    <?php if ($free): ?>
                    <div class="field"><label for="p-amount">Montant à régler (<?= $cur === 'XOF' ? 'FCFA' : '€' ?>) *</label><input id="p-amount" type="text" name="amount" inputmode="decimal" required value="<?= e($old['amount']) ?>" placeholder="<?= $link['min_amount'] ? 'Minimum ' . e(money((float) $link['min_amount'], $cur, false)) : '' ?>"></div>
                    <?php endif; ?>
                </div>
                <h2 class="pay-subtitle">Moyen de paiement</h2>
                <div class="pay-methods">
                    <?php foreach ($methods as $key => $m):
                        [$ca, $cc] = pay_charge_amount($key, $due, $cur); ?>
                    <button type="submit" name="provider" value="<?= e($key) ?>" class="pay-method pay-method--<?= e($key) ?>">
                        <span class="pay-method-ico"><?= icon($key === 'stripe' ? 'shield' : 'phone') ?></span>
                        <span class="pay-method-text"><strong><?= e($m['label']) ?></strong><small><?= e($m['desc']) ?></small>
                            <?php if (!$free && $cc !== $cur): ?><small class="pay-conv">Montant débité : <?= e(money($ca, $cc)) ?></small><?php endif; ?>
                            <?php if ($m['test']): ?><small class="pay-test">Mode test</small><?php endif; ?>
                        </span>
                        <span class="pay-method-go"><?= icon('arrow') ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <p class="pay-secure"><?= icon('shield', 'icon') ?> Vous allez être redirigé vers la page sécurisée de notre prestataire de paiement. Nous ne voyons ni ne stockons vos données bancaires.</p>
            </form>
            <?php endif; ?>
        </div>

        <aside class="pay-side">
            <div class="contact-card">
                <p class="pay-label">Montant à régler</p>
                <p class="pay-total"><?= $free && !$doc ? ($link['amount'] > 0 ? e(money((float) $link['amount'], $cur)) . ' <small>suggéré</small>' : 'Montant libre') : e(money($due, $cur)) ?></p>
                <?php if ($cur === 'EUR' && $due > 0): ?><p class="pay-muted small">≈ <?= e(money(round($due * pay_xof_rate()), 'XOF')) ?></p><?php endif; ?>
                <dl class="doc-facts">
                    <?php if ($doc): ?>
                    <div><dt>Facture</dt><dd><?= e($doc['number']) ?></dd></div>
                    <div><dt>Date</dt><dd><?= e(date('d/m/Y', strtotime($doc['issue_date']))) ?></dd></div>
                    <div><dt>Total TTC</dt><dd><?= e(money((float) $doc['total'], $doc['currency'])) ?></dd></div>
                    <?php if ((float) $doc['amount_paid'] > 0): ?><div><dt>Déjà réglé</dt><dd><?= e(money((float) $doc['amount_paid'], $doc['currency'])) ?></dd></div><?php endif; ?>
                    <?php if ($doc['due_date']): ?><div><dt>Échéance</dt><dd><?= e(date('d/m/Y', strtotime($doc['due_date']))) ?></dd></div><?php endif; ?>
                    <?php endif; ?>
                    <?php if ($link['expires_at'] && $state === 'active'): ?><div><dt>Lien valable jusqu'au</dt><dd><?= e(date('d/m/Y', strtotime($link['expires_at']))) ?></dd></div><?php endif; ?>
                    <div><dt>Statut</dt><dd><?= e($stateLabel) ?></dd></div>
                </dl>
                <?php if ($link['description']): ?><p class="pay-desc"><?= nl2br(e($link['description'])) ?></p><?php endif; ?>
                <?php if ($doc && $doc['public_token']): ?>
                <a class="btn btn-ghost btn-block" href="<?= e(url('document/' . $doc['public_token'])) ?>"><?= icon('external', 'icon') ?>Voir la facture</a>
                <?php endif; ?>
            </div>
            <div class="contact-card">
                <h2 class="aside-title">Bénéficiaire</h2>
                <p class="pay-seller"><strong><?= e($seller['name']) ?></strong><br><?= e($seller['address']) ?><?php if ($seller['siret']): ?><br><span class="pay-muted small">SIRET <?= e($seller['siret']) ?></span><?php endif; ?></p>
                <ul class="contact-list">
                    <li><span class="icon-box icon-box--sm"><?= icon('mail') ?></span><div><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></div></li>
                    <li><span class="icon-box icon-box--sm"><?= icon('phone') ?></span><div><a href="<?= e(tel_link(setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></div></li>
                </ul>
            </div>
        </aside>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
