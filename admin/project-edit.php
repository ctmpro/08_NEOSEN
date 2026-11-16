<?php
/**
 * Création / modification d'une réalisation (avec galerie d'images).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
admin_csrf_guard();
$lang = admin_lang();

$id = (int) ($_GET['id'] ?? 0);
$project = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM projects WHERE id = ?');
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    if (!$project) {
        flash('error', 'Réalisation introuvable.');
        redirect(admin_url('projects.php'));
    }
}

$errors = [];

/* ---------- Actions sur la galerie ---------- */
if (is_post() && $id && post('gallery_action') !== '') {
    $imgId = post_int('image_id');
    $stmt = db()->prepare('SELECT * FROM project_images WHERE id = ? AND project_id = ?');
    $stmt->execute([$imgId, $id]);
    $image = $stmt->fetch();
    if ($image) {
        if (post('gallery_action') === 'delete') {
            delete_media($image['path']);
            db()->prepare('DELETE FROM project_images WHERE id = ?')->execute([$imgId]);
            flash('success', 'Image supprimée.');
        } elseif (post('gallery_action') === 'main') {
            // L'image choisie devient principale, l'ancienne principale rejoint la galerie
            db()->prepare('UPDATE projects SET main_image = ? WHERE id = ?')->execute([$image['path'], $id]);
            if ($project['main_image']) {
                db()->prepare('UPDATE project_images SET path = ? WHERE id = ?')->execute([$project['main_image'], $imgId]);
            } else {
                db()->prepare('DELETE FROM project_images WHERE id = ?')->execute([$imgId]);
            }
            flash('success', 'Image principale mise à jour.');
        }
    }
    redirect(admin_url('project-edit.php', ['id' => $id]) . '#galerie');
}

/* ---------- Enregistrement ---------- */
if (is_post()) {
    $data = [
        'name' => post('name'),
        'slug' => post('slug'),
        'category_id' => post_int('category_id') ?: null,
        'category_label' => post('category_label'),
        'client' => post('client'),
        'url' => post('url'),
        'year' => post_int('year') ?: null,
        'short_description' => post('short_description'),
        'long_description' => clean_html(post('long_description')),
        'need' => clean_html(post('need')),
        'solution' => clean_html(post('solution')),
        'features' => post('features'),
        'results' => clean_html(post('results')),
        'technologies' => implode(', ', csv_list(post('technologies'))),
        'is_featured' => post_bool('is_featured'),
        'is_visible' => post_bool('is_visible'),
        'status' => post('status') === 'published' ? 'published' : 'draft',
        'seo_title' => post('seo_title'),
        'seo_description' => post('seo_description'),
    ];
    if ($data['name'] === '') $errors[] = 'Le nom du projet est obligatoire.';
    if ($data['url'] !== '' && !filter_var($data['url'], FILTER_VALIDATE_URL)) $errors[] = 'L\'URL du projet est invalide (ex : https://exemple.com).';
    $data['slug'] = unique_slug('projects', $data['slug'] ?: $data['name'], $id, $lang);
    $data['main_image'] = handle_media_field('main_image', $project['main_image'] ?? null, 'projects/' . $data['slug'], 'image', $errors);

    if (!$errors) {
        if ($id) {
            $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
            db()->prepare("UPDATE projects SET {$sets} WHERE id = ?")->execute([...array_values($data), $id]);
        } else {
            $data['lang'] = $lang;
            $data['sort_order'] = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM projects')->fetchColumn();
            $cols = implode(', ', array_keys($data));
            $marks = implode(', ', array_fill(0, count($data), '?'));
            db()->prepare("INSERT INTO projects ({$cols}) VALUES ({$marks})")->execute(array_values($data));
            $id = (int) db()->lastInsertId();
        }

        // Nouvelles images de galerie
        $order = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) FROM project_images WHERE project_id = ' . $id)->fetchColumn();
        $insert = db()->prepare('INSERT INTO project_images (project_id, path, alt, sort_order) VALUES (?, ?, ?, ?)');
        foreach (normalize_files($_FILES['gallery'] ?? null) as $file) {
            if (!has_upload($file)) continue;
            $res = store_image($file, 'projects/' . $data['slug']);
            if (isset($res['error'])) {
                $errors[] = $file['name'] . ' : ' . $res['error'];
            } else {
                $insert->execute([$id, $res['path'], $data['name'], $order += 10]);
            }
        }
        // Textes alternatifs des images existantes
        foreach ((array) ($_POST['alt'] ?? []) as $imgId => $alt) {
            db()->prepare('UPDATE project_images SET alt = ? WHERE id = ? AND project_id = ?')->execute([mb_substr(trim((string) $alt), 0, 255), (int) $imgId, $id]);
        }

        flash($errors ? 'warning' : 'success', $errors ? 'Réalisation enregistrée, avec des erreurs : ' . implode(' ', $errors) : 'Réalisation enregistrée.');
        redirect(admin_url('project-edit.php', ['id' => $id]));
    }
    $project = array_merge($project ?? [], $data);
}

$p = $project ?? ['status' => 'draft', 'is_visible' => 1, 'is_featured' => 0, 'year' => date('Y')];
$v = fn($k) => $p[$k] ?? '';
$categories = db()->prepare('SELECT id, name FROM project_categories WHERE lang = ? ORDER BY sort_order, name');
$categories->execute([$lang]);
$categoryOptions = $categories->fetchAll(PDO::FETCH_KEY_PAIR);
$images = $id ? get_project_images($id) : [];
$techSuggestions = db()->query('SELECT DISTINCT name FROM technologies ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

admin_header($id ? 'Modifier : ' . $v('name') : 'Nouvelle réalisation', 'projects', array_filter([
    [icon('arrow-left', 'icon') . 'Retour', admin_url('projects.php'), 'btn-ghost'],
    $id && $v('status') === 'published' ? [icon('external', 'icon') . 'Voir', url('realisations/' . $v('slug')), 'btn-ghost'] : null,
]));
?>
<?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="edit-layout" data-dirty-check>
    <?= csrf_field() ?>
    <div class="edit-main">
        <section class="card">
            <div class="card-head"><h2>Informations</h2></div>
            <div class="form-grid">
                <?= f_text('name', 'Nom du projet', $v('name'), ['required' => true, 'slug_source' => 'slug']) ?>
                <?= f_text('slug', 'Slug (URL)', $v('slug'), ['help' => 'Ex : badgel → /realisations/badgel. Généré automatiquement si vide.', 'slug_target' => '1']) ?>
                <?= f_select('category_id', 'Catégorie (filtre)', $categoryOptions, $v('category_id'), ['empty' => '— Aucune —', 'help' => 'Utilisée pour les filtres Tous / Web / Application…']) ?>
                <?= f_text('category_label', 'Libellé de catégorie affiché', $v('category_label'), ['placeholder' => 'Logiciel / SaaS / SIRH']) ?>
                <?= f_text('client', 'Client', $v('client')) ?>
                <?= f_text('url', 'URL du site', $v('url'), ['type' => 'url', 'placeholder' => 'https://']) ?>
                <?= f_text('year', 'Année', $v('year'), ['type' => 'number', 'min' => 2000, 'max' => 2100]) ?>
                <div class="field">
                    <label for="f-technologies">Technologies</label>
                    <input type="text" id="f-technologies" name="technologies" value="<?= e($v('technologies')) ?>" placeholder="PHP, MySQL, JavaScript" data-tech-input>
                    <div class="chip-suggest" data-tech-suggest>
                        <?php foreach ($techSuggestions as $t): ?><button type="button" class="chip-btn" data-value="<?= e($t) ?>">+ <?= e($t) ?></button><?php endforeach; ?>
                    </div>
                </div>
                <?= f_textarea('short_description', 'Description courte', $v('short_description'), ['rows' => 3, 'class' => 'span-2', 'help' => 'Affichée sur les cartes (150 caractères conseillés).']) ?>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h2>Fiche détaillée</h2></div>
            <?= f_textarea('long_description', 'Présentation (description longue)', $v('long_description'), ['rows' => 8, 'rich' => true]) ?>
            <div class="form-grid">
                <?= f_textarea('need', 'Le besoin', $v('need'), ['rows' => 6, 'rich' => true]) ?>
                <?= f_textarea('solution', 'Notre solution', $v('solution'), ['rows' => 6, 'rich' => true]) ?>
            </div>
            <?= f_textarea('features', 'Fonctionnalités', $v('features'), ['rows' => 7, 'help' => 'Une fonctionnalité par ligne.']) ?>
            <?= f_textarea('results', 'Résultat', $v('results'), ['rows' => 4, 'rich' => true]) ?>
        </section>

        <section class="card" id="galerie">
            <div class="card-head"><h2>Galerie</h2><p class="muted small">Glissez pour réordonner · cliquez sur ★ pour définir l'image principale</p></div>
            <?php if ($images): ?>
            <div class="gallery-admin" data-sortable="project_images">
                <?php foreach ($images as $img): ?>
                <div class="gallery-tile" data-id="<?= (int) $img['id'] ?>">
                    <span class="drag-handle"><?= icon('drag') ?></span>
                    <img src="<?= e(media_url($img['path'])) ?>" alt="" loading="lazy">
                    <input type="text" name="alt[<?= (int) $img['id'] ?>]" value="<?= e($img['alt']) ?>" placeholder="Texte alternatif (SEO)">
                    <div class="gallery-tile-actions">
                        <button type="submit" class="icon-btn" name="gallery_action" value="main" formnovalidate onclick="this.form.image_id.value=<?= (int) $img['id'] ?>" title="Définir comme image principale"><?= icon('star') ?></button>
                        <button type="submit" class="icon-btn icon-btn--danger" name="gallery_action" value="delete" formnovalidate onclick="if(!confirm('Supprimer cette image ?'))return false;this.form.image_id.value=<?= (int) $img['id'] ?>" title="Supprimer"><?= icon('trash') ?></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <input type="hidden" name="image_id" value="">
            <label class="dropzone" data-dropzone>
                <?= icon('upload') ?>
                <strong>Ajouter des images</strong>
                <span class="muted small">Sélection multiple possible · JPG, PNG, WebP — converties en WebP</span>
                <input type="file" name="gallery[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,image/avif">
                <span class="dropzone-files" data-dropzone-files></span>
            </label>
        </section>

        <section class="card">
            <div class="card-head"><h2>Référencement (SEO)</h2></div>
            <?= f_text('seo_title', 'Balise title', $v('seo_title'), ['maxlength' => 190, 'help' => 'Laisser vide pour : « Nom — Catégorie | Site ».']) ?>
            <?= f_textarea('seo_description', 'Meta description', $v('seo_description'), ['rows' => 2, 'help' => '155 caractères environ. Vide = description courte.']) ?>
        </section>
    </div>

    <aside class="edit-side">
        <section class="card sticky">
            <div class="card-head"><h2>Publication</h2></div>
            <?= f_select('status', 'Statut', ['published' => 'Publié', 'draft' => 'Brouillon'], $v('status')) ?>
            <?= f_toggle('is_visible', 'Visible sur le site', (int) $v('is_visible') === 1) ?>
            <?= f_toggle('is_featured', 'Projet mis en avant', (int) $v('is_featured') === 1, 'Affiché en priorité sur l\'accueil.') ?>
            <?= f_media('main_image', 'Image principale', $v('main_image') ?: null) ?>
            <button type="submit" class="btn btn-primary btn-block"><?= icon('check') ?> Enregistrer</button>
        </section>
    </aside>
</form>
<?php admin_footer(); ?>
