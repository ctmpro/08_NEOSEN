<?php
/**
 * Relances : factures en retard et devis sans réponse.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_crm_tables();
admin_csrf_guard();

if (is_post()) {
    $doc = crm_doc(post_int('id'));
    if ($doc && $doc['status'] !== 'draft') {
        $level = max(1, min(3, post_int('level', 1)));
        if (post('action') === 'manual') {
            crm_record_reminder($doc, $level, '', 'Relance hors email', post('note'), true);
            flash('success', 'Relance enregistrée.');
        } else {
            $to = implode(',', array_filter(csv_list(post('to')), fn($m) => filter_var($m, FILTER_VALIDATE_EMAIL)));
            if (!$to) {
                flash('error', 'Adresse email invalide.');
            } else {
                $ok = crm_send_email($doc, $to, post('subject'), post('body'));
                crm_record_reminder($doc, $level, $to, post('subject'), post('body'), $ok);
                $ok ? flash('success', 'Relance envoyée à ' . $to . '.') : flash('error', 'Échec de l\'envoi (configuration SMTP ?). La relance a été notée comme non envoyée.');
            }
        }
    }
    redirect(admin_url('reminders.php'));
}

$focus = (int) ($_GET['doc'] ?? 0);
$due = crm_due_reminders();
// Document ciblé depuis sa fiche, même si la relance n'est pas encore « due »
if ($focus && ($fd = crm_doc($focus)) && !in_array($focus, array_map('intval', array_column(array_merge($due['invoices'], $due['quotes']), 'id')), true)) {
    $fd['days_late'] = $fd['due_date'] ? (int) floor((time() - strtotime($fd['due_date'])) / 86400) : 0;
    $fd['suggested_level'] = min(3, (int) $fd['reminder_count'] + 1);
    $fd['is_due'] = true;
    $fd['doc_type'] === 'quote' ? array_unshift($due['quotes'], $fd) : array_unshift($due['invoices'], $fd);
}
$totalLate = [];
foreach ($due['invoices'] as $inv) $totalLate[$inv['currency']] = ($totalLate[$inv['currency']] ?? 0) + crm_balance($inv);

$card = function (array $d) use ($focus): string {
    $isQuote = $d['doc_type'] === 'quote';
    $level = (int) ($d['suggested_level'] ?? 1);
    [$subject, $body] = crm_reminder_template($d, $level);
    ob_start(); ?>
    <article class="reminder<?= !empty($d['is_due']) ? ' is-due' : '' ?>">
        <div class="reminder-main">
            <div>
                <a class="strong" href="<?= e(admin_url('document.php', ['id' => $d['id']])) ?>"><?= e(crm_doc_title($d)) ?></a>
                <span class="muted"> · <?= e(crm_client_name($d)) ?></span>
                <p class="small muted">
                    <?php if ($isQuote): ?>Envoyé le <?= e(date('d/m/Y', strtotime($d['sent_at'] ?: $d['issue_date']))) ?> · valable jusqu'au <?= e($d['due_date'] ? date('d/m/Y', strtotime($d['due_date'])) : '—') ?>
                    <?php else: ?>Échéance <?= e(date('d/m/Y', strtotime($d['due_date']))) ?> · <strong class="text-danger"><?= (int) $d['days_late'] ?> j de retard</strong><?php endif; ?>
                    · <?= (int) $d['reminder_count'] ?> relance(s)<?= $d['last_reminder_at'] ? ', dernière le ' . e(date('d/m/Y', strtotime($d['last_reminder_at']))) : '' ?>
                </p>
            </div>
            <div class="reminder-amount"><strong><?= e(money($isQuote ? (float) $d['total'] : crm_balance($d), $d['currency'])) ?></strong><small class="muted"><?= $isQuote ? 'TTC' : 'restant dû' ?></small></div>
        </div>
        <details<?= $focus === (int) $d['id'] ? ' open' : '' ?>>
            <summary class="btn btn-sm btn-<?= !empty($d['is_due']) ? 'primary' : 'ghost' ?>"><?= icon('mail') ?> <?= $isQuote ? 'Relancer le devis' : 'Préparer la relance n°' . $level ?></summary>
            <form method="post" class="send-form"><?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                <div class="form-grid">
                    <?= f_text('to', 'Destinataire', $d['client_email'] ?? '') ?>
                    <?php if (!$isQuote): ?><?= f_select('level', 'Niveau', [1 => '1 — Rappel courtois', 2 => '2 — Seconde relance', 3 => '3 — Mise en demeure'], $level) ?><?php else: ?><input type="hidden" name="level" value="1"><?php endif; ?>
                </div>
                <?= f_text('subject', 'Objet', $subject) ?>
                <?= f_textarea('body', 'Message', $body, ['rows' => 8]) ?>
                <div class="form-actions">
                    <button class="btn btn-primary btn-sm" name="action" value="send"><?= icon('mail') ?> Envoyer (PDF joint)</button>
                    <button class="btn btn-ghost btn-sm" name="action" value="manual" formnovalidate>Noter une relance faite par téléphone</button>
                </div>
            </form>
        </details>
    </article>
    <?php return ob_get_clean();
};

admin_header('Relances', 'reminders');
?>
<div class="stat-grid stat-grid--sm">
    <div class="stat-card"><span class="stat-label">Factures en retard</span><strong class="stat-value"><?= count($due['invoices']) ?></strong><span class="stat-sub"><?= e(crm_money_list($totalLate)) ?></span></div>
    <div class="stat-card"><span class="stat-label">Relances à faire</span><strong class="stat-value"><?= count(array_filter($due['invoices'], fn($i) => $i['is_due'])) ?></strong><span class="stat-sub">selon les délais réglés (<?= e(setting('billing_reminder_delays')) ?> j)</span></div>
    <div class="stat-card"><span class="stat-label">Devis sans réponse</span><strong class="stat-value"><?= count($due['quotes']) ?></strong><span class="stat-sub">depuis plus de <?= (int) setting('billing_quote_followup_days', 7) ?> jours</span></div>
</div>

<section class="card">
    <div class="card-head"><h2>Factures impayées en retard</h2><a class="link" href="<?= e(admin_url('settings.php', ['tab' => 'billing'])) ?>">Modèles de relance</a></div>
    <?php if ($due['invoices']): foreach ($due['invoices'] as $inv) echo $card($inv); else: ?><p class="empty">Aucune facture en retard. 🎉</p><?php endif; ?>
</section>
<section class="card">
    <div class="card-head"><h2>Devis à relancer</h2></div>
    <?php if ($due['quotes']): foreach ($due['quotes'] as $q) echo $card($q); else: ?><p class="empty">Aucun devis en attente de relance.</p><?php endif; ?>
</section>
<?php admin_footer(); ?>
