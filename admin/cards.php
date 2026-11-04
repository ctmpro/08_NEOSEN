<?php
/**
 * Cartes de visite : liste (une carte = un QR code imprimé, contenu modifiable à tout moment).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/cards.php';
require_admin();
admin_csrf_guard();

if (is_post() && post('action') === 'delete') {
    db()->prepare('DELETE FROM cards WHERE id = ?')->execute([post_int('id')]);
    flash('success', 'Carte supprimée. Le QR code imprimé renverra désormais vers la page d\'accueil.');
    redirect(admin_url('cards.php'));
}

$cards = db()->query('SELECT c.*,
        (SELECT COUNT(*) FROM card_scans s WHERE s.card_id = c.id AND s.event = "scan") AS scans,
        (SELECT COUNT(*) FROM card_scans s WHERE s.card_id = c.id AND s.event = "lead") AS leads,
        (SELECT MAX(created_at) FROM card_scans s WHERE s.card_id = c.id AND s.event = "scan") AS last_scan
    FROM cards c ORDER BY c.id')->fetchAll();

if (count($cards) === 1 && !isset($_GET['all'])) {
    redirect(admin_url('card-edit.php', ['id' => $cards[0]['id']]));
}

admin_header('Cartes de visite', 'cards', [[icon('plus', 'icon') . 'Nouvelle carte', admin_url('card-edit.php'), 'btn-primary']]);
?>
<section class="card">
    <div class="card-head"><h2><?= count($cards) ?> carte(s)</h2><p class="muted small">Chaque carte possède son QR code. Le contenu de la page reste modifiable après impression.</p></div>
    <?php if ($cards): ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Carte</th><th class="hide-sm">Adresse du QR code</th><th class="num">Scans</th><th class="num hide-sm">Contacts</th><th class="hide-sm">Dernier scan</th><th class="col-actions"></th></tr></thead>
        <tbody>
        <?php foreach ($cards as $c): ?>
        <tr class="<?= $c['is_active'] ? '' : 'is-off' ?>">
            <td><a href="<?= e(admin_url('card-edit.php', ['id' => $c['id']])) ?>"><strong><?= e($c['label']) ?></strong></a><small class="muted d-block"><?= e(implode(' · ', array_filter([$c['person_name'], $c['campaign']]))) ?></small></td>
            <td class="hide-sm mono small"><?= e(card_short_url($c)) ?></td>
            <td class="num"><?= (int) $c['scans'] ?></td>
            <td class="num hide-sm"><?= (int) $c['leads'] ?></td>
            <td class="hide-sm"><?= $c['last_scan'] ? e(format_date($c['last_scan'], true)) : '—' ?></td>
            <td class="col-actions"><div class="actions">
                <a class="icon-btn" href="<?= e(admin_url('card-edit.php', ['id' => $c['id']])) ?>" title="Modifier"><?= icon('edit') ?></a>
                <form method="post" class="inline-form" data-confirm="Supprimer cette carte ? Les QR codes déjà imprimés renverront vers l'accueil du site."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="icon-btn icon-btn--danger" title="Supprimer"><?= icon('trash') ?></button></form>
            </div></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <p class="empty">Aucune carte pour le moment. <a href="<?= e(admin_url('card-edit.php')) ?>">Créer la première</a>.</p>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
