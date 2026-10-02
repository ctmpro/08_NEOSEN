<?php
/**
 * Liste des réalisations : réordonner, masquer, mettre en avant, supprimer.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
admin_csrf_guard();
$lang = admin_lang();

if (is_post()) {
    $id = post_int('id');
    switch (post('action')) {
        case 'toggle_visible':
            db()->prepare('UPDATE projects SET is_visible = 1 - is_visible WHERE id = ?')->execute([$id]);
            flash('success', 'Visibilité mise à jour.');
            break;
        case 'toggle_featured':
            db()->prepare('UPDATE projects SET is_featured = 1 - is_featured WHERE id = ?')->execute([$id]);
            flash('success', 'Mise en avant mise à jour.');
            break;
        case 'toggle_status':
            db()->prepare('UPDATE projects SET status = IF(status = "published", "draft", "published") WHERE id = ?')->execute([$id]);
            flash('success', 'Statut mis à jour.');
            break;
        case 'delete':
            $stmt = db()->prepare('SELECT main_image FROM projects WHERE id = ?');
            $stmt->execute([$id]);
            $main = $stmt->fetchColumn();
            foreach (get_project_images($id) as $img) delete_media($img['path']);
            delete_media($main ?: null);
            db()->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
            flash('success', 'Réalisation supprimée.');
            break;
    }
    redirect(admin_url('projects.php'));
}

$stmt = db()->prepare('SELECT p.*, c.name AS category_name, (SELECT COUNT(*) FROM project_images i WHERE i.project_id = p.id) AS images_count
                       FROM projects p LEFT JOIN project_categories c ON c.id = p.category_id WHERE p.lang = ? ORDER BY p.sort_order, p.id DESC');
$stmt->execute([$lang]);
$projects = $stmt->fetchAll();

admin_header('Réalisations', 'projects', [[icon('plus', 'icon') . 'Ajouter une réalisation', admin_url('project-edit.php'), 'btn-primary']]);
?>
<?= admin_lang_switcher() ?>
<div class="card">
    <div class="card-head">
        <h2><?= count($projects) ?> réalisation(s)</h2>
        <p class="muted small"><?= icon('drag', 'icon icon-sm') ?> Glissez-déposez les lignes pour modifier l'ordre d'affichage sur le site.</p>
    </div>
    <?php if ($projects): ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th class="col-handle"></th><th>Projet</th><th class="hide-sm">Catégorie</th><th>Statut</th><th class="hide-sm">Mis en avant</th><th class="col-actions">Actions</th></tr></thead>
        <tbody data-sortable="projects">
        <?php foreach ($projects as $p): ?>
            <tr data-id="<?= (int) $p['id'] ?>">
                <td class="col-handle"><span class="drag-handle" title="Déplacer"><?= icon('drag') ?></span></td>
                <td>
                    <div class="cell-project">
                        <span class="thumb thumb--lg"><?php if ($p['main_image']): ?><img src="<?= e(media_url($p['main_image'])) ?>" alt="" loading="lazy"><?php endif; ?></span>
                        <span><a class="strong" href="<?= e(admin_url('project-edit.php', ['id' => $p['id']])) ?>"><?= e($p['name']) ?></a><small class="muted">/realisations/<?= e($p['slug']) ?> · <?= (int) $p['images_count'] + ($p['main_image'] ? 1 : 0) ?> image(s)</small></span>
                    </div>
                </td>
                <td class="hide-sm"><?= e($p['category_name'] ?: '—') ?><small class="muted d-block"><?= e($p['category_label']) ?></small></td>
                <td>
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="toggle_status">
                        <button class="pill pill--<?= $p['status'] === 'published' ? 'success' : 'muted' ?>" title="Basculer publié / brouillon"><?= $p['status'] === 'published' ? 'Publié' : 'Brouillon' ?></button>
                    </form>
                    <?php if (!$p['is_visible']): ?><span class="pill pill--warning">Masqué</span><?php endif; ?>
                </td>
                <td class="hide-sm">
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="toggle_featured">
                        <button class="icon-btn<?= $p['is_featured'] ? ' is-on' : '' ?>" title="Mettre en avant"><?= icon('star') ?></button>
                    </form>
                </td>
                <td class="col-actions">
                    <div class="actions">
                        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="toggle_visible">
                            <button class="icon-btn" title="<?= $p['is_visible'] ? 'Masquer' : 'Afficher' ?>"><?= icon($p['is_visible'] ? 'eye' : 'eye-off') ?></button>
                        </form>
                        <?php if ($p['status'] === 'published' && $p['is_visible']): ?>
                        <a class="icon-btn" href="<?= e(url('realisations/' . $p['slug'])) ?>" target="_blank" title="Voir sur le site"><?= icon('external') ?></a>
                        <?php endif; ?>
                        <a class="icon-btn" href="<?= e(admin_url('project-edit.php', ['id' => $p['id']])) ?>" title="Modifier"><?= icon('edit') ?></a>
                        <form method="post" class="inline-form" data-confirm="Supprimer définitivement « <?= e($p['name']) ?> » et ses images ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="delete">
                            <button class="icon-btn icon-btn--danger" title="Supprimer"><?= icon('trash') ?></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <p class="empty">Aucune réalisation. <a href="<?= e(admin_url('project-edit.php')) ?>">Ajouter la première</a>.</p>
    <?php endif; ?>
</div>
<?php admin_footer(); ?>
