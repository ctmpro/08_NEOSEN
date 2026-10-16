<?php
/**
 * Clients & prospects : liste filtrable et pipeline commercial.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_crm_tables();
admin_csrf_guard();

if (is_post() && post('action') === 'stage') {
    $stage = post('stage');
    if (array_key_exists($stage, crm_stages())) {
        $id = post_int('id');
        db()->prepare('UPDATE crm_clients SET stage = ?, kind = IF(? = "won", "client", kind) WHERE id = ?')->execute([$stage, $stage, $id]);
        crm_log($id, 'Étape du pipeline : ' . crm_stages()[$stage][0]);
        if (wants_json()) json_response(['success' => true]);
    }
    redirect(admin_url('clients.php', ['view' => 'pipeline']));
}

$view = ($_GET['view'] ?? '') === 'pipeline' ? 'pipeline' : 'list';
$filter = $_GET['filter'] ?? 'all';
$q = trim((string) ($_GET['q'] ?? ''));

$where = ['c.is_archived = ' . ($filter === 'archived' ? 1 : 0)];
$params = [];
if (in_array($filter, ['prospect', 'client'], true)) {
    $where[] = 'c.kind = ?';
    $params[] = $filter;
}
if ($q !== '') {
    $where[] = '(c.company LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.city LIKE ?)';
    array_push($params, ...array_fill(0, 5, '%' . $q . '%'));
}
if ($view === 'pipeline') {
    $where = ['c.is_archived = 0'];
    $params = [];
}
$sql = 'SELECT c.*,
            (SELECT COALESCE(SUM(subtotal - discount_amount), 0) FROM crm_documents d WHERE d.client_id = c.id AND d.doc_type = "invoice" AND d.status <> "draft" AND d.status <> "cancelled") AS invoiced,
            (SELECT COALESCE(SUM(total - amount_paid), 0) FROM crm_documents d WHERE d.client_id = c.id AND d.doc_type = "invoice" AND d.status IN ("issued","partial")) AS due,
            (SELECT COALESCE(SUM(total), 0) FROM crm_documents d WHERE d.client_id = c.id AND d.doc_type = "quote" AND d.status = "sent") AS pending_quotes
        FROM crm_clients c WHERE ' . implode(' AND ', $where) . ' ORDER BY COALESCE(c.updated_at, c.created_at) DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();
$counts = db()->query('SELECT kind, COUNT(*) FROM crm_clients WHERE is_archived = 0 GROUP BY kind')->fetchAll(PDO::FETCH_KEY_PAIR);
$stages = crm_stages();

admin_header('Clients & prospects', 'clients', [
    [icon($view === 'pipeline' ? 'grid' : 'flow', 'icon') . ($view === 'pipeline' ? 'Vue liste' : 'Pipeline'), admin_url('clients.php', $view === 'pipeline' ? [] : ['view' => 'pipeline']), 'btn-ghost'],
    [icon('plus', 'icon') . 'Nouveau', admin_url('client-edit.php'), 'btn-primary'],
]);
?>
<?php if ($view === 'pipeline'): ?>
<p class="muted small mb">Déplacez un prospect d'une colonne à l'autre (glisser-déposer) ou changez son étape depuis la carte.</p>
<div class="kanban" data-kanban>
    <?php foreach ($stages as $key => [$label, $color]):
        $col = array_filter($clients, fn($c) => $c['stage'] === $key);
        $sum = array_sum(array_map(fn($c) => (float) ($c['pending_quotes'] ?: $c['estimated_value']), $col)); ?>
    <section class="kanban-col" data-stage="<?= e($key) ?>">
        <header style="--c:<?= e($color) ?>"><span class="dot-c"></span><strong><?= e($label) ?></strong><span class="muted small"><?= count($col) ?><?= $sum > 0 ? ' · ' . e(money($sum, setting('billing_currency', 'EUR'))) : '' ?></span></header>
        <div class="kanban-list" data-kanban-list>
            <?php foreach ($col as $c): ?>
            <article class="kanban-card" draggable="true" data-id="<?= (int) $c['id'] ?>">
                <a href="<?= e(admin_url('client.php', ['id' => $c['id']])) ?>" class="strong"><?= e(crm_client_name($c)) ?></a>
                <?php if (crm_client_contact($c) && $c['company']): ?><small class="muted d-block"><?= e(crm_client_contact($c)) ?></small><?php endif; ?>
                <div class="kanban-meta">
                    <?php if ((float) $c['pending_quotes'] > 0): ?><span class="pill"><?= e(money((float) $c['pending_quotes'], $c['currency'])) ?></span>
                    <?php elseif ((float) $c['estimated_value'] > 0): ?><span class="pill pill--muted">~ <?= e(money((float) $c['estimated_value'], $c['currency'])) ?></span><?php endif; ?>
                    <?php if ($c['next_action_at']): ?><span class="small <?= $c['next_action_at'] <= date('Y-m-d') ? 'text-danger' : 'muted' ?>"><?= icon('clock', 'icon icon-sm') ?> <?= e(date('d/m', strtotime($c['next_action_at']))) ?></span><?php endif; ?>
                </div>
                <form method="post" class="kanban-move"><?= csrf_field() ?><input type="hidden" name="action" value="stage"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <select name="stage" onchange="this.form.submit()" aria-label="Étape"><?php foreach ($stages as $k => [$l]): ?><option value="<?= e($k) ?>"<?= $k === $c['stage'] ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
                </form>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="toolbar">
    <nav class="tabs tabs--inline">
        <?php foreach (['all' => 'Tous (' . array_sum($counts) . ')', 'prospect' => 'Prospects (' . ($counts['prospect'] ?? 0) . ')', 'client' => 'Clients (' . ($counts['client'] ?? 0) . ')', 'archived' => 'Archivés'] as $k => $label): ?>
        <a href="<?= e(admin_url('clients.php', ['filter' => $k])) ?>" class="<?= $filter === $k ? 'is-active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <form class="search" method="get"><input type="hidden" name="filter" value="<?= e($filter) ?>"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, entreprise, email, ville…"></form>
</div>
<section class="card card--flush">
    <?php if ($clients): ?>
    <div class="table-wrap table-wrap--flush">
    <table class="table">
        <thead><tr><th>Client / prospect</th><th class="hide-sm">Contact</th><th>Statut</th><th class="hide-sm num">Facturé HT</th><th class="num">Reste dû</th><th class="hide-sm">Prochaine action</th></tr></thead>
        <tbody>
        <?php foreach ($clients as $c): [$sl, $sc] = $stages[$c['stage']] ?? [$c['stage'], '#999']; ?>
            <tr class="row-link" data-href="<?= e(admin_url('client.php', ['id' => $c['id']])) ?>">
                <td><div class="cell-project"><span class="avatar avatar--soft"><?= e(mb_strtoupper(mb_substr(crm_client_name($c), 0, 1))) ?></span><span><a class="strong" href="<?= e(admin_url('client.php', ['id' => $c['id']])) ?>"><?= e(crm_client_name($c)) ?></a><small class="muted d-block"><?= e(trim(($c['city'] ?? '') . ($c['country'] && $c['country'] !== 'France' ? ', ' . $c['country'] : ''), ', ')) ?></small></span></div></td>
                <td class="hide-sm"><?= e(crm_client_contact($c)) ?><small class="muted d-block"><?= e($c['email']) ?></small></td>
                <td><span class="pill pill--<?= $c['kind'] === 'client' ? 'success' : 'muted' ?>"><?= $c['kind'] === 'client' ? 'Client' : 'Prospect' ?></span> <span class="stage-dot" style="--c:<?= e($sc) ?>" title="<?= e($sl) ?>"><?= e($sl) ?></span></td>
                <td class="hide-sm num"><?= (float) $c['invoiced'] > 0 ? e(money((float) $c['invoiced'], $c['currency'])) : '<span class="muted">—</span>' ?></td>
                <td class="num"><?= (float) $c['due'] > 0 ? '<strong>' . e(money((float) $c['due'], $c['currency'])) . '</strong>' : '<span class="muted">—</span>' ?></td>
                <td class="hide-sm"><?= $c['next_action_at'] ? '<span class="' . ($c['next_action_at'] <= date('Y-m-d') ? 'text-danger' : '') . '">' . e(format_date($c['next_action_at'])) . '</span>' : '<span class="muted">—</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <p class="empty">Aucun résultat. <a href="<?= e(admin_url('client-edit.php')) ?>">Ajouter un client ou un prospect</a>.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php admin_footer(); ?>
