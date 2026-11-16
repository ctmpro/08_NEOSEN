<?php
/**
 * Technologies maîtrisées (affichées par catégorie).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
admin_csrf_guard();

if (is_post()) {
    $id = post_int('id');
    $action = post('action');
    if ($action === 'save') {
        $name = post('name');
        $category = post('category') ?: post('category_new') ?: 'Web';
        $errors = [];
        if ($name === '') {
            flash('error', 'Le nom est obligatoire.');
        } else {
            $current = null;
            if ($id) {
                $stmt = db()->prepare('SELECT icon FROM technologies WHERE id = ?');
                $stmt->execute([$id]);
                $current = $stmt->fetchColumn() ?: null;
            }
            $iconPath = handle_media_field('icon', $current, 'technologies', 'image', $errors);
            if ($id) {
                db()->prepare('UPDATE technologies SET name = ?, category = ?, icon = ? WHERE id = ?')->execute([$name, $category, $iconPath, $id]);
            } else {
                $order = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM technologies')->fetchColumn();
                db()->prepare('INSERT INTO technologies (name, category, icon, sort_order) VALUES (?, ?, ?, ?)')->execute([$name, $category, $iconPath, $order]);
            }
            flash($errors ? 'warning' : 'success', $errors ? implode(' ', $errors) : 'Technologie enregistrée.');
        }
    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE technologies SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    } elseif ($action === 'delete') {
        $stmt = db()->prepare('SELECT icon FROM technologies WHERE id = ?');
        $stmt->execute([$id]);
        delete_media($stmt->fetchColumn() ?: null);
        db()->prepare('DELETE FROM technologies WHERE id = ?')->execute([$id]);
        flash('success', 'Technologie supprimée.');
    } elseif ($action === 'rename_category') {
        db()->prepare('UPDATE technologies SET category = ? WHERE category = ?')->execute([post('new_name'), post('old_name')]);
        flash('success', 'Catégorie renommée.');
    }
    redirect(admin_url('technologies.php'));
}

$rows = db()->query('SELECT * FROM technologies ORDER BY sort_order, id')->fetchAll();
$grouped = [];
foreach ($rows as $r) $grouped[$r['category']][] = $r;
$categories = array_keys($grouped);
$edit = (int) ($_GET['edit'] ?? 0);

admin_header('Technologies', 'technologies');
?>
<div class="grid-2 grid-2--wide-left">
    <div>
        <?php foreach ($grouped as $category => $items): ?>
        <section class="card">
            <div class="card-head">
                <form method="post" class="inline-edit inline-edit--title"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="rename_category"><input type="hidden" name="old_name" value="<?= e($category) ?>">
                    <input type="text" name="new_name" value="<?= e($category) ?>" aria-label="Nom de la catégorie" required>
                    <button class="icon-btn" title="Renommer la catégorie"><?= icon('check') ?></button>
                </form>
                <span class="muted small"><?= count($items) ?> technologie(s)</span>
            </div>
            <ul class="tech-admin" data-sortable="technologies">
                <?php foreach ($items as $t): ?>
                <li data-id="<?= (int) $t['id'] ?>" class="<?= $t['is_active'] ? '' : 'is-off' ?>">
                    <?php if ($edit === (int) $t['id']): ?>
                    <form method="post" enctype="multipart/form-data" class="inline-edit"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                        <input type="text" name="name" value="<?= e($t['name']) ?>" required aria-label="Nom">
                        <select name="category" aria-label="Catégorie"><?php foreach ($categories as $c): ?><option<?= $c === $t['category'] ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
                        <input type="file" name="icon" accept="image/*" aria-label="Logo">
                        <?php if ($t['icon']): ?><label class="check-inline"><input type="checkbox" name="icon_remove" value="1"> Retirer le logo</label><?php endif; ?>
                        <button class="btn btn-sm btn-primary">Enregistrer</button>
                        <a class="btn btn-sm btn-ghost" href="<?= e(admin_url('technologies.php')) ?>">Annuler</a>
                    </form>
                    <?php else: ?>
                    <span class="drag-handle"><?= icon('drag') ?></span>
                    <?php if ($t['icon']): ?><img src="<?= e(media_url($t['icon'])) ?>" alt="" width="20" height="20"><?php endif; ?>
                    <strong><?= e($t['name']) ?></strong>
                    <div class="actions">
                        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="toggle">
                            <button class="icon-btn" title="<?= $t['is_active'] ? 'Masquer' : 'Afficher' ?>"><?= icon($t['is_active'] ? 'eye' : 'eye-off') ?></button>
                        </form>
                        <a class="icon-btn" href="<?= e(admin_url('technologies.php', ['edit' => $t['id']])) ?>" title="Modifier"><?= icon('edit') ?></a>
                        <form method="post" class="inline-form" data-confirm="Supprimer « <?= e($t['name']) ?> » ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="delete">
                            <button class="icon-btn icon-btn--danger" title="Supprimer"><?= icon('trash') ?></button>
                        </form>
                    </div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endforeach; ?>
    </div>
    <section class="card sticky">
        <div class="card-head"><h2>Ajouter une technologie</h2></div>
        <form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <?= f_text('name', 'Nom', '', ['required' => true, 'placeholder' => 'Ex : Laravel']) ?>
            <?= f_select('category', 'Catégorie existante', array_combine($categories, $categories), '', ['empty' => '— Nouvelle catégorie —']) ?>
            <?= f_text('category_new', 'Ou nouvelle catégorie', '', ['placeholder' => 'Ex : Cloud']) ?>
            <?= f_media('icon', 'Logo (optionnel)', null) ?>
            <button class="btn btn-primary"><?= icon('plus') ?> Ajouter</button>
        </form>
    </section>
</div>
<?php admin_footer(); ?>
