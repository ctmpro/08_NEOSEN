<?php
/**
 * Blocs de contenu : méthodologie, arguments, chiffres clés, prestations Data, valeurs.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
admin_csrf_guard();
$lang = admin_lang();

$sections = block_sections();
$section = array_key_exists($_GET['section'] ?? '', $sections) ? $_GET['section'] : array_key_first($sections);

// Aide contextuelle par section
$hints = [
    'approach'     => ['title' => 'Titre de l\'étape', 'subtitle' => 'Numéro (ex : 01)', 'description' => 'Description', 'items' => false, 'icon' => true],
    'why'          => ['title' => 'Argument', 'subtitle' => false, 'description' => 'Explication', 'items' => false, 'icon' => true],
    'stats'        => ['title' => 'Valeur (ex : 5+, 100%, 48h)', 'subtitle' => 'Libellé', 'description' => false, 'items' => false, 'icon' => false],
    'data_offer'   => ['title' => 'Prestation', 'subtitle' => false, 'description' => 'Description', 'items' => 'Éléments (un par ligne)', 'icon' => true],
    'about_values' => ['title' => 'Valeur', 'subtitle' => false, 'description' => 'Description', 'items' => false, 'icon' => true],
];
$h = $hints[$section];

if (is_post()) {
    $id = post_int('id');
    $action = post('action');
    if ($action === 'delete') {
        $stmt = db()->prepare('SELECT image FROM content_blocks WHERE id = ?');
        $stmt->execute([$id]);
        delete_media($stmt->fetchColumn() ?: null);
        db()->prepare('DELETE FROM content_blocks WHERE id = ?')->execute([$id]);
        flash('success', 'Bloc supprimé.');
    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE content_blocks SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash('success', 'Statut mis à jour.');
    } elseif ($action === 'save') {
        $title = post('title');
        if ($title === '') {
            flash('error', 'Le titre est obligatoire.');
        } else {
            $fields = [$title, post('subtitle') ?: null, post('description') ?: null, post('icon') ?: null, post('items') ?: null];
            if ($id) {
                db()->prepare('UPDATE content_blocks SET title = ?, subtitle = ?, description = ?, icon = ?, items = ? WHERE id = ?')->execute([...$fields, $id]);
            } else {
                $order = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM content_blocks WHERE section = ?');
                $order->execute([$section]);
                db()->prepare('INSERT INTO content_blocks (lang, section, title, subtitle, description, icon, items, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$lang, $section, ...$fields, (int) $order->fetchColumn()]);
            }
            flash('success', 'Bloc enregistré.');
        }
    }
    redirect(admin_url('blocks.php', ['section' => $section]));
}

$stmt = db()->prepare('SELECT * FROM content_blocks WHERE lang = ? AND section = ? ORDER BY sort_order, id');
$stmt->execute([$lang, $section]);
$blocks = $stmt->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);

$blockForm = function (?array $b) use ($h, $section): string {
    ob_start(); ?>
    <form method="post" class="block-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($b['id'] ?? 0) ?>">
        <div class="form-grid">
            <?= f_text('title', $h['title'], $b['title'] ?? '', ['required' => true]) ?>
            <?php if ($h['subtitle']): ?><?= f_text('subtitle', $h['subtitle'], $b['subtitle'] ?? '') ?><?php endif; ?>
        </div>
        <?php if ($h['description']): ?><?= f_textarea('description', $h['description'], $b['description'] ?? '', ['rows' => 3]) ?><?php endif; ?>
        <?php if ($h['items']): ?><?= f_textarea('items', $h['items'], $b['items'] ?? '', ['rows' => 5]) ?><?php endif; ?>
        <?php if ($h['icon']): ?><?= f_icon('icon', 'Icône', $b['icon'] ?? '') ?><?php endif; ?>
        <div class="form-actions">
            <button class="btn btn-primary"><?= icon('check') ?> Enregistrer</button>
            <?php if ($b): ?><a class="btn btn-ghost" href="<?= e(admin_url('blocks.php', ['section' => $section])) ?>">Annuler</a><?php endif; ?>
        </div>
    </form>
    <?php return ob_get_clean();
};

admin_header('Blocs de contenu', 'blocks');
?>
<?= admin_lang_switcher() ?>
<nav class="tabs" aria-label="Sections">
    <?php foreach ($sections as $key => $label): ?>
    <a href="<?= e(admin_url('blocks.php', ['section' => $key])) ?>" class="<?= $key === $section ? 'is-active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<div class="grid-2 grid-2--wide-left">
    <section class="card">
        <div class="card-head"><h2><?= e($sections[$section]) ?></h2><p class="muted small">Glissez-déposez pour réordonner</p></div>
        <?php if ($blocks): ?>
        <ul class="block-list" data-sortable="content_blocks">
            <?php foreach ($blocks as $b): ?>
            <li data-id="<?= (int) $b['id'] ?>" class="<?= $b['is_active'] ? '' : 'is-off' ?>">
                <?php if ($editId === (int) $b['id']): ?>
                    <?= $blockForm($b) ?>
                <?php else: ?>
                <div class="block-row">
                    <span class="drag-handle"><?= icon('drag') ?></span>
                    <?php if ($h['icon']): ?><span class="stat-icon stat-icon--sm"><?= icon($b['icon'] ?: 'sparkles') ?></span><?php endif; ?>
                    <div class="block-row-body">
                        <strong><?= e($b['subtitle'] && $section === 'approach' ? $b['subtitle'] . ' · ' : '') ?><?= e($b['title']) ?></strong>
                        <small class="muted"><?= e(excerpt($b['description'] ?: ($b['subtitle'] ?: str_replace("\n", ' · ', (string) $b['items'])), 120)) ?></small>
                    </div>
                    <div class="actions">
                        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><input type="hidden" name="action" value="toggle">
                            <button class="icon-btn" title="<?= $b['is_active'] ? 'Masquer' : 'Afficher' ?>"><?= icon($b['is_active'] ? 'eye' : 'eye-off') ?></button>
                        </form>
                        <a class="icon-btn" href="<?= e(admin_url('blocks.php', ['section' => $section, 'edit' => $b['id']])) ?>" title="Modifier"><?= icon('edit') ?></a>
                        <form method="post" class="inline-form" data-confirm="Supprimer ce bloc ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><input type="hidden" name="action" value="delete">
                            <button class="icon-btn icon-btn--danger" title="Supprimer"><?= icon('trash') ?></button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?><p class="empty">Aucun bloc dans cette section.</p><?php endif; ?>
    </section>
    <section class="card">
        <div class="card-head"><h2>Ajouter un bloc</h2></div>
        <?= $blockForm(null) ?>
    </section>
</div>
<?php admin_footer(); ?>
