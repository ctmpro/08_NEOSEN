<?php
/**
 * Catalogue de prestations (réutilisables dans les devis et factures).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_crm_tables();
admin_csrf_guard();

if (is_post()) {
    $id = post_int('id');
    if (post('action') === 'save') {
        $data = [post('name'), post('description') ?: null, post('unit') ?: 'forfait', (float) str_replace([',', ' '], ['.', ''], post('unit_price', '0')), (float) str_replace(',', '.', post('vat_rate', '20')), post('currency') === 'XOF' ? 'XOF' : 'EUR'];
        if ($data[0] === '') {
            flash('error', 'Le nom est obligatoire.');
        } elseif ($id) {
            db()->prepare('UPDATE crm_items SET name = ?, description = ?, unit = ?, unit_price = ?, vat_rate = ?, currency = ? WHERE id = ?')->execute([...$data, $id]);
            flash('success', 'Prestation mise à jour.');
        } else {
            db()->prepare('INSERT INTO crm_items (name, description, unit, unit_price, vat_rate, currency, sort_order) VALUES (?, ?, ?, ?, ?, ?, (SELECT COALESCE(MAX(s.sort_order), 0) + 10 FROM crm_items s))')->execute($data);
            flash('success', 'Prestation ajoutée.');
        }
    } elseif (post('action') === 'toggle') {
        db()->prepare('UPDATE crm_items SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    } elseif (post('action') === 'delete') {
        db()->prepare('DELETE FROM crm_items WHERE id = ?')->execute([$id]);
        flash('success', 'Prestation supprimée.');
    }
    redirect(admin_url('items.php'));
}

$items = db()->query('SELECT * FROM crm_items ORDER BY sort_order, name')->fetchAll();
$edit = (int) ($_GET['edit'] ?? 0);
$editing = $edit ? (array_values(array_filter($items, fn($i) => (int) $i['id'] === $edit))[0] ?? null) : null;
$vat = setting_lines('billing_vat_rates') ?: ['20', '0'];

admin_header('Catalogue de prestations', 'items');
?>
<div class="grid-2 grid-2--wide-left">
    <section class="card">
        <div class="card-head"><h2><?= count($items) ?> prestation(s)</h2><p class="muted small">Glissez-déposez pour réordonner</p></div>
        <?php if ($items): ?>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th class="col-handle"></th><th>Prestation</th><th class="num">Prix HT</th><th class="hide-sm">TVA</th><th class="col-actions"></th></tr></thead>
            <tbody data-sortable="crm_items">
            <?php foreach ($items as $it): ?>
            <tr data-id="<?= (int) $it['id'] ?>" class="<?= $it['is_active'] ? '' : 'is-off' ?>">
                <td class="col-handle"><span class="drag-handle"><?= icon('drag') ?></span></td>
                <td><strong><?= e($it['name']) ?></strong><small class="muted d-block"><?= e(excerpt($it['description'], 80)) ?></small></td>
                <td class="num"><?= e(money((float) $it['unit_price'], $it['currency'])) ?><small class="muted d-block">/ <?= e($it['unit']) ?></small></td>
                <td class="hide-sm"><?= e(str_replace('.', ',', (string) (float) $it['vat_rate'])) ?> %</td>
                <td class="col-actions"><div class="actions">
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>"><button class="icon-btn" title="<?= $it['is_active'] ? 'Masquer' : 'Afficher' ?>"><?= icon($it['is_active'] ? 'eye' : 'eye-off') ?></button></form>
                    <a class="icon-btn" href="<?= e(admin_url('items.php', ['edit' => $it['id']])) ?>" title="Modifier"><?= icon('edit') ?></a>
                    <form method="post" class="inline-form" data-confirm="Supprimer cette prestation ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>"><button class="icon-btn icon-btn--danger" title="Supprimer"><?= icon('trash') ?></button></form>
                </div></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?><p class="empty">Ajoutez vos prestations types (site vitrine, maintenance mensuelle, jour de développement…) pour remplir vos devis en un clic.</p><?php endif; ?>
    </section>
    <section class="card sticky">
        <div class="card-head"><h2><?= $editing ? 'Modifier' : 'Nouvelle prestation' ?></h2></div>
        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <?= f_text('name', 'Nom', $editing['name'] ?? '', ['required' => true, 'placeholder' => 'Ex : Site vitrine (jusqu\'à 6 pages)']) ?>
            <?= f_textarea('description', 'Description (affichée sous la ligne)', $editing['description'] ?? '', ['rows' => 3]) ?>
            <div class="form-grid">
                <?= f_text('unit_price', 'Prix unitaire HT', isset($editing['unit_price']) ? (string) (float) $editing['unit_price'] : '', ['required' => true]) ?>
                <?= f_text('unit', 'Unité', $editing['unit'] ?? 'forfait') ?>
                <?= f_select('vat_rate', 'TVA (%)', array_combine($vat, $vat), isset($editing['vat_rate']) ? (string) (float) $editing['vat_rate'] : setting('billing_default_vat', '20')) ?>
                <?= f_select('currency', 'Devise', ['EUR' => 'Euro', 'XOF' => 'FCFA'], $editing['currency'] ?? setting('billing_currency', 'EUR')) ?>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary"><?= icon('check') ?> Enregistrer</button>
                <?php if ($editing): ?><a class="btn btn-ghost" href="<?= e(admin_url('items.php')) ?>">Annuler</a><?php endif; ?>
            </div>
        </form>
    </section>
</div>
<?php admin_footer(); ?>
