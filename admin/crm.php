<?php
/**
 * Gestion — vue d'ensemble : chiffre d'affaires, encaissements, devis, pipeline, tâches.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_crm_tables();

$y = date('Y');
$mainCur = (string) setting('billing_currency', 'EUR');
$revMonth = crm_revenue(date('Y-m-01'), date('Y-m-t'));
$revYear = crm_revenue("$y-01-01", "$y-12-31");
$paidYear = db()->query("SELECT d.currency, SUM(p.amount) FROM crm_payments p JOIN crm_documents d ON d.id = p.document_id WHERE YEAR(p.paid_on) = $y GROUP BY d.currency")->fetchAll(PDO::FETCH_KEY_PAIR);
$open = db()->query('SELECT * FROM crm_documents WHERE doc_type = "invoice" AND status IN ("issued","partial")')->fetchAll();
$toCollect = $late = [];
foreach ($open as $inv) {
    $b = crm_balance($inv);
    $toCollect[$inv['currency']] = ($toCollect[$inv['currency']] ?? 0) + $b;
    if ($inv['due_date'] < date('Y-m-d')) $late[$inv['currency']] = ($late[$inv['currency']] ?? 0) + $b;
}
$pendingQ = db()->query('SELECT currency, COUNT(*) n, SUM(total) t FROM crm_documents WHERE doc_type = "quote" AND status = "sent" AND (due_date IS NULL OR due_date >= CURDATE()) GROUP BY currency')->fetchAll();
$qStats = db()->query("SELECT SUM(status IN ('accepted','invoiced')) won, SUM(status <> 'draft') total FROM crm_documents WHERE doc_type = 'quote' AND YEAR(issue_date) = $y")->fetch();
$conversion = (int) $qStats['total'] > 0 ? round($qStats['won'] / $qStats['total'] * 100) : null;

// CA mensuel sur 12 mois (devise principale)
$months = [];
for ($i = 11; $i >= 0; $i--) {
    $start = date('Y-m-01', strtotime("first day of -$i month"));
    $months[$start] = (float) (crm_revenue($start, date('Y-m-t', strtotime($start)))[$mainCur] ?? 0);
}
$maxMonth = max($months) ?: 1;
$monthNames = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

$pipeline = db()->query('SELECT stage, COUNT(*) n, SUM(COALESCE(estimated_value, 0)) v FROM crm_clients WHERE is_archived = 0 AND kind = "prospect" GROUP BY stage')->fetchAll(PDO::FETCH_UNIQUE);
$tasks = db()->query('SELECT a.*, c.company, c.first_name, c.last_name FROM crm_activities a JOIN crm_clients c ON c.id = a.client_id
                      WHERE a.type = "task" AND a.done_at IS NULL ORDER BY a.due_at IS NULL, a.due_at LIMIT 8')->fetchAll();
$recent = db()->query('SELECT a.*, c.company, c.first_name, c.last_name FROM crm_activities a JOIN crm_clients c ON c.id = a.client_id ORDER BY a.created_at DESC, a.id DESC LIMIT 8')->fetchAll();
$reminders = crm_due_reminders();
$nbClients = db()->query('SELECT kind, COUNT(*) FROM crm_clients WHERE is_archived = 0 GROUP BY kind')->fetchAll(PDO::FETCH_KEY_PAIR);

admin_header('Gestion — vue d\'ensemble', 'crm', [
    [icon('plus', 'icon') . 'Devis', admin_url('document-edit.php', ['type' => 'quote']), 'btn-ghost'],
    [icon('plus', 'icon') . 'Facture', admin_url('document-edit.php', ['type' => 'invoice']), 'btn-primary'],
]);
?>
<div class="stat-grid">
    <a class="stat-card" href="<?= e(admin_url('documents.php', ['type' => 'invoice', 'year' => $y])) ?>"><span class="stat-icon"><?= icon('chart') ?></span><span class="stat-label">CA HT <?= e($monthNames[(int) date('n') - 1]) ?></span><strong class="stat-value"><?= e(crm_money_list($revMonth)) ?></strong><span class="stat-sub">Année <?= $y ?> : <?= e(crm_money_list($revYear)) ?></span></a>
    <a class="stat-card" href="<?= e(admin_url('documents.php', ['type' => 'invoice', 'status' => 'paid'])) ?>"><span class="stat-icon"><?= icon('check-circle') ?></span><span class="stat-label">Encaissé en <?= $y ?></span><strong class="stat-value"><?= e(crm_money_list(array_map('floatval', $paidYear))) ?></strong><span class="stat-sub">paiements enregistrés</span></a>
    <a class="stat-card" href="<?= e(admin_url('documents.php', ['type' => 'invoice', 'status' => 'unpaid'])) ?>"><span class="stat-icon"><?= icon('clock') ?></span><span class="stat-label">À encaisser</span><strong class="stat-value"><?= e(crm_money_list($toCollect)) ?></strong><span class="stat-sub <?= $late ? 'text-danger' : '' ?>">dont en retard : <?= e(crm_money_list($late)) ?></span></a>
    <a class="stat-card" href="<?= e(admin_url('documents.php', ['type' => 'quote', 'status' => 'sent'])) ?>"><span class="stat-icon"><?= icon('edit') ?></span><span class="stat-label">Devis en attente</span><strong class="stat-value"><?= (int) array_sum(array_column($pendingQ, 'n')) ?></strong><span class="stat-sub"><?= e(crm_money_list(array_map('floatval', array_column($pendingQ, 't', 'currency')))) ?><?= $conversion !== null ? ' · transformation ' . $conversion . ' %' : '' ?></span></a>
</div>

<div class="grid-2 grid-2--wide-left">
    <section class="card">
        <div class="card-head"><h2>Chiffre d'affaires HT — 12 derniers mois</h2><span class="muted small"><?= $mainCur === 'XOF' ? 'FCFA' : 'Euros' ?></span></div>
        <div class="chart-bars">
            <?php foreach ($months as $m => $v): ?>
            <div class="chart-col" title="<?= e(money($v, $mainCur)) ?>">
                <span class="chart-val"><?= $v > 0 ? e(number_format($v / ($v >= 10000 ? 1000 : 1), $v >= 10000 ? 0 : 0, ',', ' ') . ($v >= 10000 ? 'k' : '')) : '' ?></span>
                <span class="chart-bar" style="height:<?= max(2, round($v / $maxMonth * 100)) ?>%"></span>
                <span class="chart-label"><?= e($monthNames[(int) date('n', strtotime($m)) - 1]) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="card">
        <div class="card-head"><h2>Pipeline prospects</h2><a class="link" href="<?= e(admin_url('clients.php', ['view' => 'pipeline'])) ?>">Ouvrir</a></div>
        <ul class="pipe-list">
            <?php foreach (crm_stages() as $k => [$label, $color]): if (in_array($k, ['won'], true)) continue; $row = $pipeline[$k] ?? ['n' => 0, 'v' => 0]; ?>
            <li><span class="stage-dot" style="--c:<?= e($color) ?>"><?= e($label) ?></span><span class="muted small"><?= (float) $row['v'] > 0 ? e(money((float) $row['v'], $mainCur)) : '' ?></span><strong><?= (int) $row['n'] ?></strong></li>
            <?php endforeach; ?>
        </ul>
        <p class="small muted mt"><?= (int) ($nbClients['client'] ?? 0) ?> client(s) · <?= (int) ($nbClients['prospect'] ?? 0) ?> prospect(s)</p>
    </section>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-head"><h2>À faire</h2><a class="link" href="<?= e(admin_url('reminders.php')) ?>">Relances</a></div>
        <ul class="list">
            <?php $nbDue = count(array_filter($reminders['invoices'], fn($i) => $i['is_due'])); if ($nbDue || $reminders['quotes']): ?>
            <li><a class="list-row" href="<?= e(admin_url('reminders.php')) ?>"><span class="stat-icon stat-icon--sm"><?= icon('clock') ?></span><span class="list-main"><strong><?= $nbDue ?> facture(s) à relancer · <?= count($reminders['quotes']) ?> devis sans réponse</strong><small>Préparer les relances</small></span></a></li>
            <?php endif; ?>
            <?php foreach ($tasks as $t): $lateT = $t['due_at'] && $t['due_at'] < date('Y-m-d H:i:s'); ?>
            <li><a class="list-row" href="<?= e(admin_url('client.php', ['id' => $t['client_id']])) ?>"><span class="stat-icon stat-icon--sm"><?= icon('check-circle') ?></span><span class="list-main"><strong><?= e($t['subject']) ?></strong><small><?= e(crm_client_name($t)) ?></small></span><span class="list-meta <?= $lateT ? 'text-danger' : '' ?>"><?= $t['due_at'] ? e(date('d/m', strtotime($t['due_at']))) : '' ?></span></a></li>
            <?php endforeach; ?>
            <?php if (!$tasks && !$nbDue && !$reminders['quotes']): ?><li class="empty">Rien d'urgent. Ajoutez des tâches depuis la fiche d'un client.</li><?php endif; ?>
        </ul>
    </section>
    <section class="card">
        <div class="card-head"><h2>Activité récente</h2></div>
        <ul class="list">
            <?php foreach ($recent as $a): [$tl, $ti] = crm_activity_types()[$a['type']] ?? ['', 'edit']; ?>
            <li><a class="list-row" href="<?= e(admin_url('client.php', ['id' => $a['client_id']])) ?>"><span class="stat-icon stat-icon--sm"><?= icon($ti) ?></span><span class="list-main"><strong><?= e($a['subject']) ?></strong><small><?= e(crm_client_name($a)) ?></small></span><span class="list-meta"><?= e(format_date($a['created_at'])) ?></span></a></li>
            <?php endforeach; ?>
            <?php if (!$recent): ?><li class="empty">Aucune activité. <a href="<?= e(admin_url('client-edit.php')) ?>">Créez votre premier client</a>.</li><?php endif; ?>
        </ul>
    </section>
</div>
<?php admin_footer(); ?>
