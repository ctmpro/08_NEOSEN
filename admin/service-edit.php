<?php
/**
 * Création / modification d'un service.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
admin_csrf_guard();
$lang = admin_lang();

$id = (int) ($_GET['id'] ?? 0);
$service = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([$id]);
    $service = $stmt->fetch() ?: null;
    if (!$service) {
        flash('error', 'Service introuvable.');
        redirect(admin_url('services.php'));
    }
}

$errors = [];
if (is_post()) {
    $data = [
        'name' => post('name'),
        'slug' => post('slug'),
        'eyebrow' => post('eyebrow'),
        'short_description' => post('short_description'),
        'description' => clean_html(post('description')),
        'icon' => post('icon') ?: 'sparkles',
        'items' => post('items'),
        'cta_label' => post('cta_label'),
        'link_url' => post('link_url'),
        'seo_title' => post('seo_title'),
        'seo_description' => post('seo_description'),
        'is_active' => post_bool('is_active'),
    ];
    if ($data['name'] === '') $errors[] = 'Le nom est obligatoire.';
    $data['slug'] = unique_slug('services', $data['slug'] ?: $data['name'], $id, $lang);
    $data['image'] = handle_media_field('image', $service['image'] ?? null, 'services', 'image', $errors);

    if (!$errors) {
        if ($id) {
            $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
            db()->prepare("UPDATE services SET {$sets} WHERE id = ?")->execute([...array_values($data), $id]);
        } else {
            $data['lang'] = $lang;
            $data['sort_order'] = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM services')->fetchColumn();
            db()->prepare('INSERT INTO services (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')')->execute(array_values($data));
            $id = (int) db()->lastInsertId();
        }
        flash('success', 'Service enregistré.');
        redirect(admin_url('service-edit.php', ['id' => $id]));
    }
    $service = array_merge($service ?? [], $data);
}

$s = $service ?? ['is_active' => 1, 'icon' => 'sparkles', 'cta_label' => 'Découvrir'];
$v = fn($k) => $s[$k] ?? '';

admin_header($id ? 'Modifier : ' . $v('name') : 'Nouveau service', 'services', [[icon('arrow-left', 'icon') . 'Retour', admin_url('services.php'), 'btn-ghost']]);
?>
<?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="edit-layout" data-dirty-check>
    <?= csrf_field() ?>
    <div class="edit-main">
        <section class="card">
            <div class="card-head"><h2>Contenu</h2></div>
            <div class="form-grid">
                <?= f_text('name', 'Nom', $v('name'), ['required' => true, 'slug_source' => 'slug']) ?>
                <?= f_text('slug', 'Slug (URL)', $v('slug'), ['help' => 'Ex : creation-site-web → /services/creation-site-web', 'slug_target' => '1']) ?>
                <?= f_text('eyebrow', 'Sur-titre / numéro', $v('eyebrow'), ['placeholder' => '01 — Web']) ?>
                <?= f_text('cta_label', 'Libellé du bouton', $v('cta_label'), ['placeholder' => 'Découvrir']) ?>
            </div>
            <?= f_textarea('short_description', 'Description courte', $v('short_description'), ['rows' => 3, 'help' => 'Affichée sur les cartes de l\'accueil.']) ?>
            <?= f_textarea('description', 'Description complète (page du service)', $v('description'), ['rows' => 10, 'rich' => true]) ?>
            <?= f_textarea('items', 'Liste des prestations', $v('items'), ['rows' => 8, 'help' => 'Une prestation par ligne.']) ?>
            <?= f_text('link_url', 'Lien alternatif du bouton (optionnel)', $v('link_url'), ['help' => 'Ex : data → la carte renvoie vers la page Data au lieu de la fiche service.']) ?>
        </section>
        <section class="card">
            <div class="card-head"><h2>Icône</h2></div>
            <?= f_icon('icon', 'Icône affichée', $v('icon')) ?>
        </section>
        <section class="card">
            <div class="card-head"><h2>Référencement (SEO)</h2></div>
            <?= f_text('seo_title', 'Balise title', $v('seo_title')) ?>
            <?= f_textarea('seo_description', 'Meta description', $v('seo_description'), ['rows' => 2]) ?>
        </section>
    </div>
    <aside class="edit-side">
        <section class="card sticky">
            <div class="card-head"><h2>Publication</h2></div>
            <?= f_toggle('is_active', 'Service actif (affiché)', (int) $v('is_active') === 1) ?>
            <?= f_media('image', 'Image (optionnelle)', $v('image') ?: null, ['help' => 'Remplace l\'illustration par défaut sur la page Services.']) ?>
            <button type="submit" class="btn btn-primary btn-block"><?= icon('check') ?> Enregistrer</button>
        </section>
    </aside>
</form>
<?php admin_footer(); ?>
