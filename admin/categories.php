<?php
/**
 * Catégories de réalisations (utilisées par les filtres du site).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
admin_csrf_guard();
$lang = admin_lang();

if (is_post()) {
    $id = post_int('id');
    $action = post('action');
    if ($action === 'save') {
        $name = post('name');
        if ($name === '') {
            flash('error', 'Le nom est obligatoire.');
        } else {
            $slug = unique_slug('project_categories', post('slug') ?: $name, $id, $lang);
            if ($id) {
                db()->prepare('UPDATE project_categories SET name = ?, slug = ? WHERE id = ?')->execute([$name, $slug, $id]);
            } else {
                $order = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM project_categories')->fetchColumn();
                db()->prepare('INSERT INTO project_categories (lang, name, slug, sort_order) VALUES (?, ?, ?, ?)')->execute([$lang, $name, $slug, $order]);
            }
            flash('success', 'Catégorie enregistrée.');
        }
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM project_categories WHERE id = ?')->execute([$id]);
        flash('success', 'Catégorie supprimée (les réalisations associées n\'ont plus de catégorie).');
    }
    redirect(admin_url('categories.php'));
}

$stmt = db()->prepare('SELECT c.*, (SELECT COUNT(*) FROM projects p WHERE p.category_id = c.id) AS n FROM project_categories c WHERE c.lang = ? ORDER BY c.sort_order, c.name');
$stmt->execute([$lang]);
$categories = $stmt->fetchAll();

admin_header('Catégories de réalisations', 'categories');
?>
<?= admin_lang_switcher() ?>
<div class="grid-2 grid-2--wide-left">
    <section class="card">
        <div class="card-head"><h2>Catégories</h2><p class="muted small">Ordre des filtres : glissez-déposez</p></div>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th class="col-handle"></th><th>Nom</th><th>Slug</th><th>Projets</th><th class="col-actions">Actions</th></tr></thead>
            <tbody data-sortable="project_categories">
            <?php foreach ($categories as $c): ?>
                <tr data-id="<?= (int) $c['id'] ?>">
                    <td class="col-handle"><span class="drag-handle"><?= icon('drag') ?></span></td>
                    <td colspan="2">
                        <form method="post" class="inline-edit"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <input type="text" name="name" value="<?= e($c['name']) ?>" required aria-label="Nom">
                            <input type="text" name="slug" value="<?= e($c['slug']) ?>" aria-label="Slug">
                            <button class="icon-btn" title="Enregistrer"><?= icon('check') ?></button>
                        </form>
                    </td>
                    <td><?= (int) $c['n'] ?></td>
                    <td class="col-actions">
                        <form method="post" class="inline-form" data-confirm="Supprimer la catégorie « <?= e($c['name']) ?> » ?"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button class="icon-btn icon-btn--danger" title="Supprimer"><?= icon('trash') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
    <section class="card">
        <div class="card-head"><h2>Nouvelle catégorie</h2></div>
        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <?= f_text('name', 'Nom', '', ['required' => true, 'placeholder' => 'Ex : E-commerce']) ?>
            <?= f_text('slug', 'Slug', '', ['help' => 'Généré automatiquement si vide.']) ?>
            <button class="btn btn-primary"><?= icon('plus') ?> Ajouter</button>
        </form>
    </section>
</div>
<?php admin_footer(); ?>
