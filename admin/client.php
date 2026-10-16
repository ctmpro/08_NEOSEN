<?php
/**
 * Fiche client / prospect : informations, documents, suivi (notes, appels, tâches…).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_crm_tables();
admin_csrf_guard();

$id = (int) ($_GET['id'] ?? 0);
$c = crm_client($id);
if (!$c) {
    flash('error', 'Fiche introuvable.');
    redirect(admin_url('clients.php'));
}

if (is_post()) {
    switch (post('action')) {
        case 'activity':
            $type = array_key_exists(post('type'), crm_activity_types()) ? post('type') : 'note';
            if (post('subject') === '') {
                flash('error', 'Indiquez un titre.');
                break;
            }
            $due = post('due_at') ? post('due_at') . ' 09:00:00' : null;
            db()->prepare('INSERT INTO crm_activities (client_id, type, subject, content, due_at, done_at, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$id, $type, mb_substr(post('subject'), 0, 190), post('content') ?: null, $due, $type === 'task' ? null : date('Y-m-d H:i:s'), $_SESSION['admin_id']]);
            if ($type === 'task' && $due) {
                db()->prepare('UPDATE crm_clients SET next_action_at = LEAST(COALESCE(next_action_at, ?), ?) WHERE id = ?')->execute([substr($due, 0, 10), substr($due, 0, 10), $id]);
            }
            if (in_array($type, ['call', 'email', 'meeting'], true) && $c['stage'] === 'new') {
                db()->prepare('UPDATE crm_clients SET stage = "contacted" WHERE id = ?')->execute([$id]);
            }
            flash('success', 'Ajouté au suivi.');
            break;
        case 'task_done':
            db()->prepare('UPDATE crm_activities SET done_at = IF(done_at IS NULL, NOW(), NULL) WHERE id = ? AND client_id = ?')->execute([post_int('activity_id'), $id]);
            $next = db()->prepare('SELECT MIN(DATE(due_at)) FROM crm_activities WHERE client_id = ? AND type = "task" AND done_at IS NULL');
            $next->execute([$id]);
            db()->prepare('UPDATE crm_clients SET next_action_at = ? WHERE id = ?')->execute([$next->fetchColumn() ?: null, $id]);
            break;
        case 'delete_activity':
            db()->prepare('DELETE FROM crm_activities WHERE id = ? AND client_id = ? AND type <> "system"')->execute([post_int('activity_id'), $id]);
            break;
        case 'convert':
            db()->prepare('UPDATE crm_clients SET kind = "client", stage = "won" WHERE id = ?')->execute([$id]);
            crm_log($id, 'Prospect converti en client');
            flash('success', 'Le prospect est maintenant un client.');
            break;
        case 'archive':
            db()->prepare('UPDATE crm_clients SET is_archived = 1 - is_archived WHERE id = ?')->execute([$id]);
            flash('success', $c['is_archived'] ? 'Fiche désarchivée.' : 'Fiche archivée.');
            break;
        case 'delete':
            $n = db()->prepare('SELECT COUNT(*) FROM crm_documents WHERE client_id = ? AND status <> "draft"');
            $n->execute([$id]);
            if ((int) $n->fetchColumn() > 0) {
                flash('error', 'Cette fiche a des documents émis : archivez-la plutôt que de la supprimer.');
                break;
            }
            db()->prepare('DELETE FROM crm_documents WHERE client_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM crm_clients WHERE id = ?')->execute([$id]);
            flash('success', 'Fiche supprimée.');
            redirect(admin_url('clients.php'));
    }
    redirect(admin_url('client.php', ['id' => $id]));
}

$docs = db()->prepare('SELECT * FROM crm_documents WHERE client_id = ? ORDER BY COALESCE(issue_date, created_at) DESC, id DESC');
$docs->execute([$id]);
$docs = $docs->fetchAll();
$acts = db()->prepare('SELECT a.*, ad.name AS admin_name FROM crm_activities a LEFT JOIN admins ad ON ad.id = a.admin_id WHERE a.client_id = ? ORDER BY (a.type = "task" AND a.done_at IS NULL) DESC, COALESCE(a.due_at, a.created_at) DESC, a.id DESC');
$acts->execute([$id]);
$acts = $acts->fetchAll();

$invoiced = $paid = $due = $pending = 0.0;
foreach ($docs as $d) {
    if ($d['doc_type'] === 'invoice' && !in_array($d['status'], ['draft', 'cancelled'], true)) {
        $invoiced += (float) $d['subtotal'] - (float) $d['discount_amount'];
        $paid += (float) $d['amount_paid'];
        $due += crm_balance($d);
    }
    if ($d['doc_type'] === 'quote' && $d['status'] === 'sent') $pending += (float) $d['total'];
}
$cur = $c['currency'];
[$stageLabel, $stageColor] = crm_stages()[$c['stage']] ?? [$c['stage'], '#999'];
$types = crm_activity_types();

admin_header(crm_client_name($c), 'clients', [
    [icon('arrow-left', 'icon') . 'Clients', admin_url('clients.php'), 'btn-ghost'],
    [icon('edit', 'icon') . 'Modifier', admin_url('client-edit.php', ['id' => $id]), 'btn-ghost'],
]);
?>
<section class="client-hero card">
    <div class="client-id">
        <span class="avatar avatar--lg"><?= e(mb_strtoupper(mb_substr(crm_client_name($c), 0, 1))) ?></span>
        <div>
            <h2><?= e(crm_client_name($c)) ?></h2>
            <p class="muted"><?= e(implode(' · ', array_filter([crm_client_contact($c), $c['job_title'], $c['city']]))) ?></p>
            <p class="badges">
                <span class="pill pill--<?= $c['kind'] === 'client' ? 'success' : 'muted' ?>"><?= $c['kind'] === 'client' ? 'Client' : 'Prospect' ?></span>
                <span class="stage-dot" style="--c:<?= e($stageColor) ?>"><?= e($stageLabel) ?></span>
                <?php if ($c['is_archived']): ?><span class="pill pill--warning">Archivé</span><?php endif; ?>
            </p>
        </div>
    </div>
    <div class="client-actions">
        <a class="btn btn-primary btn-sm" href="<?= e(admin_url('document-edit.php', ['type' => 'quote', 'client' => $id])) ?>"><?= icon('plus') ?> Devis</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('document-edit.php', ['type' => 'invoice', 'client' => $id])) ?>"><?= icon('plus') ?> Facture</a>
        <?php if ($c['email']): ?><a class="btn btn-ghost btn-sm" href="mailto:<?= e($c['email']) ?>"><?= icon('mail') ?> Email</a><?php endif; ?>
        <?php if ($c['phone']): ?><a class="btn btn-ghost btn-sm" href="<?= e(tel_link($c['phone'])) ?>"><?= icon('phone') ?> Appeler</a>
        <a class="btn btn-ghost btn-sm" href="https://wa.me/<?= e(preg_replace('/\D+/', '', $c['phone'])) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?></a><?php endif; ?>
    </div>
</section>

<div class="stat-grid stat-grid--sm">
    <div class="stat-card"><span class="stat-label">Facturé HT</span><strong class="stat-value"><?= e(money($invoiced, $cur)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Encaissé</span><strong class="stat-value"><?= e(money($paid, $cur)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Reste à encaisser</span><strong class="stat-value<?= $due > 0 ? ' text-danger' : '' ?>"><?= e(money($due, $cur)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Devis en attente</span><strong class="stat-value"><?= e(money($pending, $cur)) ?></strong></div>
</div>

<div class="edit-layout">
    <div class="edit-main">
        <section class="card">
            <div class="card-head"><h2>Documents</h2></div>
            <?php if ($docs): ?>
            <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Document</th><th class="hide-sm">Objet</th><th>Date</th><th class="num">Montant TTC</th><th>Statut</th></tr></thead>
                <tbody>
                <?php foreach ($docs as $d): [$sl, $st] = crm_doc_status($d); ?>
                <tr class="row-link" data-href="<?= e(admin_url('document.php', ['id' => $d['id']])) ?>">
                    <td><a class="strong" href="<?= e(admin_url('document.php', ['id' => $d['id']])) ?>"><?= e(crm_doc_title($d)) ?></a></td>
                    <td class="hide-sm muted"><?= e(excerpt($d['title'], 50)) ?></td>
                    <td><?= e($d['issue_date'] ? date('d/m/Y', strtotime($d['issue_date'])) : '—') ?></td>
                    <td class="num"><?= e(money((float) $d['total'], $d['currency'])) ?></td>
                    <td><span class="pill pill--<?= e($st) ?>"><?= e($sl) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php else: ?><p class="empty">Aucun document pour le moment.</p><?php endif; ?>
        </section>

        <section class="card">
            <div class="card-head"><h2>Suivi</h2></div>
            <form method="post" class="activity-form"><?= csrf_field() ?>
                <input type="hidden" name="action" value="activity">
                <div class="seg" role="radiogroup">
                    <?php foreach ($types as $k => [$l, $ico]): if ($k === 'system') continue; ?>
                    <label><input type="radio" name="type" value="<?= e($k) ?>"<?= $k === 'note' ? ' checked' : '' ?>><span><?= icon($ico) ?><?= e($l) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="form-grid">
                    <?= f_text('subject', 'Titre', '', ['required' => true, 'placeholder' => 'Ex : Appel de découverte, relancer pour le devis…']) ?>
                    <?= f_text('due_at', 'Échéance (pour une tâche)', '', ['type' => 'date']) ?>
                </div>
                <?= f_textarea('content', 'Détails', '', ['rows' => 3]) ?>
                <button class="btn btn-sm btn-primary"><?= icon('plus') ?> Ajouter</button>
            </form>
            <ol class="timeline-admin">
                <?php foreach ($acts as $a): [$tl, $ti] = $types[$a['type']] ?? ['Note', 'edit'];
                    $isTask = $a['type'] === 'task'; $late = $isTask && !$a['done_at'] && $a['due_at'] && $a['due_at'] < date('Y-m-d H:i:s'); ?>
                <li class="tl-item tl-<?= e($a['type']) ?><?= $isTask && $a['done_at'] ? ' is-done' : '' ?>">
                    <span class="tl-icon"><?= icon($ti) ?></span>
                    <div class="tl-body">
                        <div class="tl-head">
                            <strong><?= e($a['subject']) ?></strong>
                            <?php if ($isTask): ?><span class="pill pill--<?= $a['done_at'] ? 'success' : ($late ? 'danger' : 'warning') ?>"><?= $a['done_at'] ? 'Faite' : ($a['due_at'] ? 'Pour le ' . date('d/m', strtotime($a['due_at'])) : 'À faire') ?></span><?php endif; ?>
                        </div>
                        <?php if ($a['content']): ?><p class="tl-text"><?= nl2br(e($a['content'])) ?></p><?php endif; ?>
                        <small class="muted"><?= e($tl) ?> · <?= e(format_date($a['created_at'], true)) ?><?= $a['admin_name'] ? ' · ' . e($a['admin_name']) : '' ?></small>
                    </div>
                    <?php if ($a['type'] !== 'system'): ?>
                    <div class="actions">
                        <?php if ($isTask): ?>
                        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="task_done"><input type="hidden" name="activity_id" value="<?= (int) $a['id'] ?>"><button class="icon-btn" title="<?= $a['done_at'] ? 'Rouvrir' : 'Marquer comme faite' ?>"><?= icon('check') ?></button></form>
                        <?php endif; ?>
                        <form method="post" class="inline-form" data-confirm="Supprimer cette entrée ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_activity"><input type="hidden" name="activity_id" value="<?= (int) $a['id'] ?>"><button class="icon-btn icon-btn--danger" title="Supprimer"><?= icon('trash') ?></button></form>
                    </div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>

    <aside class="edit-side">
        <section class="card">
            <div class="card-head"><h2>Coordonnées</h2><a class="link" href="<?= e(admin_url('client-edit.php', ['id' => $id])) ?>">Modifier</a></div>
            <dl class="mini-dl">
                <?php foreach ([
                    'Email' => $c['email'] ? '<a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '',
                    'Téléphone' => $c['phone'] ? '<a href="' . e(tel_link($c['phone'])) . '">' . e($c['phone']) . '</a>' : '',
                    'Site web' => $c['website'] ? '<a href="' . e($c['website']) . '" target="_blank" rel="noopener">' . e(preg_replace('#^https?://#', '', $c['website'])) . '</a>' : '',
                    'Adresse' => e(implode(', ', array_filter([$c['address'], trim($c['postal_code'] . ' ' . $c['city']), $c['country']]))),
                    'SIREN / SIRET' => e($c['siren']),
                    'TVA' => e($c['vat_number']),
                    'Autre identifiant' => e($c['other_id']),
                    'Devise' => $c['currency'] === 'XOF' ? 'Franc CFA' : 'Euro',
                    'Délai de paiement' => $c['payment_terms_days'] !== null ? (int) $c['payment_terms_days'] . ' jours' : '',
                    'Origine' => e($c['source']),
                    'Valeur estimée' => $c['estimated_value'] ? e(money((float) $c['estimated_value'], $cur)) : '',
                    'Client depuis' => e(format_date($c['created_at'])),
                ] as $k => $val): if ($val === '' || $val === null) continue; ?>
                <div><dt><?= e($k) ?></dt><dd><?= $val ?></dd></div>
                <?php endforeach; ?>
            </dl>
            <?php if ($c['notes']): ?><h3 class="label mt">Notes</h3><p class="small note-box"><?= nl2br(e($c['notes'])) ?></p><?php endif; ?>
            <?php if ($c['contact_message_id']): ?><p class="small mt"><a class="link" href="<?= e(admin_url('messages.php', ['id' => $c['contact_message_id']])) ?>">Voir la demande d'origine</a></p><?php endif; ?>
        </section>
        <section class="card">
            <div class="stack">
                <?php if ($c['kind'] === 'prospect'): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="convert"><button class="btn btn-sm btn-ghost btn-block"><?= icon('check-circle') ?> Convertir en client</button></form>
                <?php endif; ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><button class="btn btn-sm btn-ghost btn-block"><?= icon('inbox') ?> <?= $c['is_archived'] ? 'Désarchiver' : 'Archiver' ?></button></form>
                <form method="post" data-confirm="Supprimer définitivement cette fiche et ses brouillons ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-danger btn-block"><?= icon('trash') ?> Supprimer</button></form>
            </div>
        </section>
    </aside>
</div>
<?php admin_footer(); ?>
