<?php
/**
 * Liste des services / expertises.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
admin_csrf_guard();
$lang = admin_lang();

if (is_post()) {
    $id = post_int('id');
    if (post('action') === 'toggle') {
        db()->prepare('UPDATE services SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash('success', 'Statut mis à jour.');
    } elseif (post('action') === 'delete') {
        $stmt = db()->prepare('SELECT image FROM services WHERE id = ?');
        $stmt->execute([$id]);
        delete_media($stmt->fetchColumn() ?: null);
        db()->prepare('DELETE FROM services WHERE id = ?')->execute([$id]);
        flash('success', 'Service supprimé.');
    }
    redirect(admin_url('services.php'));
}

$stmt = db()->prepare('SELECT * FROM services WHERE lang = ? ORDER BY sort_order, id');
$stmt->execute([$lang]);
$services = $stmt->fetchAll();

admin_header('Services', 'services', [[icon('plus', 'icon') . 'Ajouter un service', admin_url('service-edit.php'), 'btn-primary']]);
?>
<?= admin_lang_switcher() ?>
<div class="card">
    <div class="card-head"><h2>Expertises affichées sur le site</h2><p class="muted small">Glissez-déposez pour changer l'ordre</p></div>
    <div class="service-cards" data-sortable="services">
        <?php foreach ($services as $s): ?>
        <article class="service-card<?= $s['is_active'] ? '' : ' is-off' ?>" data-id="<?= (int) $s['id'] ?>">
            <span class="drag-handle"><?= icon('drag') ?></span>
            <span class="stat-icon"><?= icon($s['icon'] ?: 'sparkles') ?></span>
            <div class="service-card-body">
                <small class="muted"><?= e($s['eyebrow']) ?></small>
                <h3><a href="<?= e(admin_url('service-edit.php', ['id' => $s['id']])) ?>"><?= e($s['name']) ?></a></h3>
                <p class="muted small"><?= e(excerpt($s['short_description'], 110)) ?></p>
                <p class="small"><?= count(lines($s['items'])) ?> prestation(s) · /services/<?= e($s['slug']) ?></p>
            </div>
            <div class="actions">
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="action" value="toggle">
                    <button class="icon-btn" title="<?= $s['is_active'] ? 'Désactiver' : 'Activer' ?>"><?= icon($s['is_active'] ? 'eye' : 'eye-off') ?></button>
                </form>
                <a class="icon-btn" href="<?= e(admin_url('service-edit.php', ['id' => $s['id']])) ?>" title="Modifier"><?= icon('edit') ?></a>
                <form method="post" class="inline-form" data-confirm="Supprimer le service « <?= e($s['name']) ?> » ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="action" value="delete">
                    <button class="icon-btn icon-btn--danger" title="Supprimer"><?= icon('trash') ?></button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</div>
<?php admin_footer(); ?>
