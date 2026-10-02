<?php
/**
 * Demandes de contact : liste, lecture, notes, archivage, suppression, export CSV.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
admin_csrf_guard();

$id = (int) ($_GET['id'] ?? 0);

/* ---------- Actions ---------- */
if (is_post()) {
    $mid = post_int('id');
    $back = admin_url('messages.php', array_filter(['id' => post('stay') ? $mid : null, 'filter' => $_GET['filter'] ?? null]));
    switch (post('action')) {
        case 'unread':
            db()->prepare('UPDATE contact_messages SET is_read = 0 WHERE id = ?')->execute([$mid]);
            flash('success', 'Marqué comme non lu.');
            $back = admin_url('messages.php');
            break;
        case 'archive':
            db()->prepare('UPDATE contact_messages SET is_archived = 1 - is_archived, is_read = 1 WHERE id = ?')->execute([$mid]);
            flash('success', 'Archivage mis à jour.');
            break;
        case 'notes':
            db()->prepare('UPDATE contact_messages SET admin_notes = ? WHERE id = ?')->execute([post('admin_notes'), $mid]);
            flash('success', 'Notes enregistrées.');
            break;
        case 'delete':
            $stmt = db()->prepare('SELECT attachment_path FROM contact_messages WHERE id = ?');
            $stmt->execute([$mid]);
            delete_media($stmt->fetchColumn() ?: null);
            db()->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$mid]);
            flash('success', 'Demande supprimée.');
            $back = admin_url('messages.php');
            break;
        case 'read_all':
            db()->exec('UPDATE contact_messages SET is_read = 1 WHERE is_read = 0');
            flash('success', 'Toutes les demandes sont marquées comme lues.');
            $back = admin_url('messages.php');
            break;
    }
    redirect($back);
}

/* ---------- Export CSV ---------- */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="demandes-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Date', 'Prénom', 'Nom', 'Entreprise', 'Email', 'Téléphone', 'Type de projet', 'Budget', 'Message', 'Lu', 'Archivé'], ';');
    foreach (db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC') as $r) {
        fputcsv($out, [$r['created_at'], $r['first_name'], $r['last_name'], $r['company'], $r['email'], $r['phone'], $r['project_type'], $r['budget'], $r['message'], $r['is_read'] ? 'oui' : 'non', $r['is_archived'] ? 'oui' : 'non'], ';');
    }
    exit;
}

/* ---------- Détail ---------- */
if ($id) {
    $stmt = db()->prepare('SELECT * FROM contact_messages WHERE id = ?');
    $stmt->execute([$id]);
    $m = $stmt->fetch();
    if (!$m) {
        flash('error', 'Demande introuvable.');
        redirect(admin_url('messages.php'));
    }
    if (!$m['is_read']) {
        db()->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?')->execute([$id]);
    }
    $wa = $m['phone'] ? 'https://wa.me/' . preg_replace('/\D+/', '', $m['phone']) : '';
    $reply = 'mailto:' . rawurlencode($m['email']) . '?subject=' . rawurlencode('Votre demande — ' . site_name());

    admin_header('Demande de ' . $m['first_name'] . ' ' . $m['last_name'], 'messages', [[icon('arrow-left', 'icon') . 'Toutes les demandes', admin_url('messages.php'), 'btn-ghost']]);
    ?>
    <div class="edit-layout">
        <div class="edit-main">
            <section class="card">
                <div class="message-head">
                    <span class="avatar avatar--lg"><?= e(mb_strtoupper(mb_substr($m['first_name'], 0, 1) . mb_substr($m['last_name'], 0, 1))) ?></span>
                    <div>
                        <h2><?= e($m['first_name'] . ' ' . $m['last_name']) ?></h2>
                        <p class="muted"><?= e($m['company'] ?: 'Particulier / entreprise non précisée') ?> · reçue le <?= e(format_date($m['created_at'], true)) ?></p>
                    </div>
                </div>
                <dl class="details">
                    <div><dt>Email</dt><dd><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a></dd></div>
                    <div><dt>Téléphone</dt><dd><?= $m['phone'] ? '<a href="' . e(tel_link($m['phone'])) . '">' . e($m['phone']) . '</a>' : '—' ?></dd></div>
                    <div><dt>Type de projet</dt><dd><span class="pill"><?= e($m['project_type'] ?: '—') ?></span></dd></div>
                    <div><dt>Budget</dt><dd><?= e($m['budget'] ?: '—') ?></dd></div>
                </dl>
                <h3 class="label">Message</h3>
                <div class="message-body"><?= nl2br(e($m['message'])) ?></div>
                <?php if ($m['attachment_path']): ?>
                <a class="attachment" href="<?= e(admin_url('download.php', ['id' => $m['id']])) ?>"><?= icon('paperclip') ?><span><?= e($m['attachment_name'] ?: 'Pièce jointe') ?></span><?= icon('arrow') ?></a>
                <?php endif; ?>
                <div class="form-actions">
                    <a class="btn btn-primary" href="<?= e($reply) ?>"><?= icon('mail') ?> Répondre par email</a>
                    <?php if ($wa): ?><a class="btn btn-ghost" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?> WhatsApp</a><?php endif; ?>
                </div>
            </section>
        </div>
        <aside class="edit-side">
            <section class="card">
                <div class="card-head"><h2>Suivi</h2></div>
                <form method="post"><?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="notes"><input type="hidden" name="stay" value="1">
                    <?= f_textarea('admin_notes', 'Notes internes', $m['admin_notes'], ['rows' => 5, 'placeholder' => 'Rappelé le…, devis envoyé…']) ?>
                    <button class="btn btn-sm btn-primary">Enregistrer les notes</button>
                </form>
                <hr>
                <div class="stack">
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="unread"><button class="btn btn-sm btn-ghost btn-block"><?= icon('mail') ?> Marquer comme non lu</button></form>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="stay" value="1"><button class="btn btn-sm btn-ghost btn-block"><?= icon('inbox') ?> <?= $m['is_archived'] ? 'Désarchiver' : 'Archiver' ?></button></form>
                    <form method="post" data-confirm="Supprimer définitivement cette demande ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-danger btn-block"><?= icon('trash') ?> Supprimer</button></form>
                </div>
                <p class="muted small meta-tech">Email de notification : <?= $m['mail_sent'] ? 'envoyé ✓' : 'non envoyé' ?><br>IP : <?= e($m['ip_address']) ?></p>
            </section>
        </aside>
    </div>
    <?php
    admin_footer();
    exit;
}

/* ---------- Liste ---------- */
$filter = $_GET['filter'] ?? 'inbox';
$q = trim((string) ($_GET['q'] ?? ''));
$where = match ($filter) {
    'unread'   => 'is_read = 0 AND is_archived = 0',
    'archived' => 'is_archived = 1',
    'all'      => '1 = 1',
    default    => 'is_archived = 0',
};
$params = [];
if ($q !== '') {
    $where .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ? OR message LIKE ?)';
    $params = array_fill(0, 5, '%' . $q . '%');
}
$perPage = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$countStmt = db()->prepare("SELECT COUNT(*) FROM contact_messages WHERE {$where}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$stmt = db()->prepare("SELECT * FROM contact_messages WHERE {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage));
$stmt->execute($params);
$messages = $stmt->fetchAll();
$unread = (int) db()->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0 AND is_archived = 0')->fetchColumn();

admin_header('Demandes de contact', 'messages', [[icon('upload', 'icon') . 'Export CSV', admin_url('messages.php', ['export' => 'csv']), 'btn-ghost']]);
?>
<div class="toolbar">
    <nav class="tabs tabs--inline">
        <?php foreach (['inbox' => 'Boîte de réception', 'unread' => 'Non lus (' . $unread . ')', 'archived' => 'Archivés', 'all' => 'Toutes'] as $k => $label): ?>
        <a href="<?= e(admin_url('messages.php', ['filter' => $k])) ?>" class="<?= $filter === $k ? 'is-active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <form class="search" method="get">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher un nom, un email…">
    </form>
</div>

<section class="card card--flush">
    <?php if ($messages): ?>
    <ul class="inbox">
        <?php foreach ($messages as $m): ?>
        <li>
            <a href="<?= e(admin_url('messages.php', ['id' => $m['id']])) ?>" class="inbox-row<?= $m['is_read'] ? '' : ' is-unread' ?>">
                <span class="dot" aria-hidden="true"></span>
                <span class="avatar avatar--soft"><?= e(mb_strtoupper(mb_substr($m['first_name'], 0, 1))) ?></span>
                <span class="inbox-who"><strong><?= e($m['first_name'] . ' ' . $m['last_name']) ?></strong><small><?= e($m['company'] ?: $m['email']) ?></small></span>
                <span class="inbox-preview"><span class="pill"><?= e($m['project_type']) ?></span> <?= e(excerpt($m['message'], 90)) ?></span>
                <span class="inbox-meta"><?= $m['attachment_path'] ? icon('paperclip', 'icon icon-sm') : '' ?><?= e(format_date($m['created_at'])) ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($pages > 1): ?>
    <nav class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="<?= e(admin_url('messages.php', ['filter' => $filter, 'q' => $q, 'page' => $i])) ?>" class="<?= $i === $page ? 'is-active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
    <?php endif; ?>
    <?php else: ?>
    <p class="empty">Aucune demande<?= $q ? ' pour « ' . e($q) . ' »' : '' ?>.</p>
    <?php endif; ?>
</section>
<?php if ($unread): ?>
<form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-sm btn-ghost"><?= icon('check') ?> Tout marquer comme lu</button></form>
<?php endif; ?>
<?php admin_footer(); ?>
