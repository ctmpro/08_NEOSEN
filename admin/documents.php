<?php
/**
 * Liste des devis, ou des factures et avoirs.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_crm_tables();

$type = ($_GET['type'] ?? 'invoice') === 'quote' ? 'quote' : 'invoice';
$status = (string) ($_GET['status'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$year = (int) ($_GET['year'] ?? 0);

$where = [$type === 'quote' ? 'd.doc_type = "quote"' : 'd.doc_type IN ("invoice","credit_note")'];
$params = [];
$today = date('Y-m-d');
$filters = $type === 'quote'
    ? ['' => 'Tous', 'draft' => 'Brouillons', 'sent' => 'En attente', 'accepted' => 'Acceptés', 'invoiced' => 'Facturés', 'declined' => 'Refusés', 'expired' => 'Expirés']
    : ['' => 'Toutes', 'draft' => 'Brouillons', 'unpaid' => 'À encaisser', 'late' => 'En retard', 'paid' => 'Payées', 'credit' => 'Avoirs'];
switch ($status) {
    case 'draft': case 'accepted': case 'invoiced': case 'declined': case 'paid':
        $where[] = 'd.status = ?'; $params[] = $status; break;
    case 'sent': $where[] = 'd.status = "sent" AND (d.due_date IS NULL OR d.due_date >= CURDATE())'; break;
    case 'expired': $where[] = 'd.status = "sent" AND d.due_date < CURDATE()'; break;
    case 'unpaid': $where[] = 'd.doc_type = "invoice" AND d.status IN ("issued","partial")'; break;
    case 'late': $where[] = 'd.doc_type = "invoice" AND d.status IN ("issued","partial") AND d.due_date < CURDATE()'; break;
    case 'credit': $where[] = 'd.doc_type = "credit_note"'; break;
}
if ($q !== '') {
    $where[] = '(d.number LIKE ? OR d.title LIKE ? OR c.company LIKE ? OR c.last_name LIKE ? OR c.first_name LIKE ?)';
    array_push($params, ...array_fill(0, 5, '%' . $q . '%'));
}
if ($year) {
    $where[] = 'YEAR(COALESCE(d.issue_date, d.created_at)) = ?';
    $params[] = $year;
}
$stmt = db()->prepare('SELECT d.*, c.company, c.first_name, c.last_name FROM crm_documents d JOIN crm_clients c ON c.id = d.client_id
                       WHERE ' . implode(' AND ', $where) . ' ORDER BY d.status = "draft" DESC, COALESCE(d.issue_date, d.created_at) DESC, d.id DESC');
$stmt->execute($params);
$docs = $stmt->fetchAll();
$years = db()->query('SELECT DISTINCT YEAR(COALESCE(issue_date, created_at)) FROM crm_documents ORDER BY 1 DESC')->fetchAll(PDO::FETCH_COLUMN);

// Totaux de la sélection, par devise
$totals = [];
foreach ($docs as $d) {
    if ($d['status'] === 'draft') continue;
    $sign = $d['doc_type'] === 'credit_note' ? -1 : 1;
    $totals[$d['currency']]['ht'] = ($totals[$d['currency']]['ht'] ?? 0) + $sign * ((float) $d['subtotal'] - (float) $d['discount_amount']);
    $totals[$d['currency']]['ttc'] = ($totals[$d['currency']]['ttc'] ?? 0) + $sign * (float) $d['total'];
    if ($d['doc_type'] === 'invoice') $totals[$d['currency']]['due'] = ($totals[$d['currency']]['due'] ?? 0) + crm_balance($d);
}

admin_header($type === 'quote' ? 'Devis' : 'Factures & avoirs', $type === 'quote' ? 'quotes' : 'invoices', [
    [icon('plus', 'icon') . ($type === 'quote' ? 'Nouveau devis' : 'Nouvelle facture'), admin_url('document-edit.php', ['type' => $type]), 'btn-primary'],
]);
?>
<div class="toolbar">
    <nav class="tabs tabs--inline">
        <?php foreach ($filters as $k => $label): ?>
        <a href="<?= e(admin_url('documents.php', array_filter(['type' => $type, 'status' => $k, 'year' => $year ?: null]))) ?>" class="<?= $status === (string) $k ? 'is-active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <form class="search search--row" method="get">
        <input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="status" value="<?= e($status) ?>">
        <select name="year" onchange="this.form.submit()" aria-label="Année"><option value="">Toutes les années</option><?php foreach ($years as $y): ?><option<?= (int) $y === $year ? ' selected' : '' ?>><?= (int) $y ?></option><?php endforeach; ?></select>
        <span class="search-field"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Numéro, client, objet…"></span>
    </form>
</div>

<?php if ($totals): ?>
<div class="totals-bar">
    <?php foreach ($totals as $cur => $t): ?>
    <span><small>Total HT</small><strong><?= e(money($t['ht'], $cur)) ?></strong></span>
    <span><small>Total TTC</small><strong><?= e(money($t['ttc'], $cur)) ?></strong></span>
    <?php if (isset($t['due'])): ?><span><small>Reste à encaisser</small><strong class="<?= $t['due'] > 0 ? 'text-danger' : '' ?>"><?= e(money($t['due'], $cur)) ?></strong></span><?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<section class="card card--flush">
    <?php if ($docs): ?>
    <div class="table-wrap table-wrap--flush">
    <table class="table">
        <thead><tr><th>Numéro</th><th>Client</th><th class="hide-sm">Objet</th><th>Date</th><th class="hide-sm"><?= $type === 'quote' ? 'Validité' : 'Échéance' ?></th><th class="num">TTC</th><?php if ($type === 'invoice'): ?><th class="num hide-sm">Reste dû</th><?php endif; ?><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($docs as $d): [$sl, $st] = crm_doc_status($d); ?>
        <tr class="row-link" data-href="<?= e(admin_url('document.php', ['id' => $d['id']])) ?>">
            <td><a class="strong" href="<?= e(admin_url('document.php', ['id' => $d['id']])) ?>"><?= e($d['number'] ?: 'Brouillon #' . $d['id']) ?></a><?php if ($d['doc_type'] === 'credit_note'): ?> <span class="pill pill--warning">Avoir</span><?php endif; ?></td>
            <td><?= e(crm_client_name($d)) ?></td>
            <td class="hide-sm muted"><?= e(excerpt($d['title'], 45)) ?></td>
            <td><?= e($d['issue_date'] ? date('d/m/Y', strtotime($d['issue_date'])) : '—') ?></td>
            <td class="hide-sm<?= $d['due_date'] && $d['due_date'] < $today && in_array($d['status'], ['issued', 'partial', 'sent'], true) ? ' text-danger' : '' ?>"><?= e($d['due_date'] ? date('d/m/Y', strtotime($d['due_date'])) : '—') ?></td>
            <td class="num"><?= $d['doc_type'] === 'credit_note' ? '− ' : '' ?><?= e(money((float) $d['total'], $d['currency'])) ?></td>
            <?php if ($type === 'invoice'): ?><td class="num hide-sm"><?= $d['doc_type'] === 'invoice' && crm_balance($d) > 0 && $d['status'] !== 'draft' ? e(money(crm_balance($d), $d['currency'])) : '<span class="muted">—</span>' ?></td><?php endif; ?>
            <td><span class="pill pill--<?= e($st) ?>"><?= e($sl) ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <p class="empty">Aucun document. <a href="<?= e(admin_url('document-edit.php', ['type' => $type])) ?>">Créer <?= $type === 'quote' ? 'un devis' : 'une facture' ?></a>.</p>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
