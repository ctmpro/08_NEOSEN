<?php
/**
 * Création / modification d'un client ou prospect.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_crm_tables();
admin_csrf_guard();

$id = (int) ($_GET['id'] ?? 0);
$client = $id ? crm_client($id) : null;
if ($id && !$client) {
    flash('error', 'Fiche introuvable.');
    redirect(admin_url('clients.php'));
}

// Création depuis une demande de contact du site
$fromMessage = (int) ($_GET['from_message'] ?? 0);
if (!$client && $fromMessage) {
    $stmt = db()->prepare('SELECT * FROM contact_messages WHERE id = ?');
    $stmt->execute([$fromMessage]);
    if ($m = $stmt->fetch()) {
        $client = ['kind' => 'prospect', 'stage' => 'new', 'is_company' => $m['company'] ? 1 : 0, 'company' => $m['company'], 'first_name' => $m['first_name'],
            'last_name' => $m['last_name'], 'email' => $m['email'], 'phone' => $m['phone'], 'source' => 'Formulaire du site',
            'notes' => 'Demande du ' . date('d/m/Y', strtotime($m['created_at'])) . ' — ' . $m['project_type'] . ($m['budget'] ? ' — budget : ' . $m['budget'] : '') . "\n\n" . $m['message'],
            'contact_message_id' => $m['id'], 'currency' => setting('billing_currency', 'EUR')];
    }
}

$errors = [];
if (is_post()) {
    $data = [
        'kind' => post('kind') === 'client' ? 'client' : 'prospect',
        'stage' => array_key_exists(post('stage'), crm_stages()) ? post('stage') : 'new',
        'is_company' => post_bool('is_company'),
        'company' => post('company') ?: null,
        'first_name' => post('first_name') ?: null,
        'last_name' => post('last_name') ?: null,
        'job_title' => post('job_title') ?: null,
        'email' => mb_strtolower(post('email')) ?: null,
        'phone' => post('phone') ?: null,
        'website' => post('website') ?: null,
        'address' => post('address') ?: null,
        'postal_code' => post('postal_code') ?: null,
        'city' => post('city') ?: null,
        'country' => post('country') ?: null,
        'siren' => post('siren') ?: null,
        'vat_number' => strtoupper(str_replace(' ', '', post('vat_number'))) ?: null,
        'other_id' => post('other_id') ?: null,
        'currency' => post('currency') === 'XOF' ? 'XOF' : 'EUR',
        'payment_terms_days' => post('payment_terms_days') !== '' ? post_int('payment_terms_days') : null,
        'source' => post('source') ?: null,
        'estimated_value' => post('estimated_value') !== '' ? (float) str_replace([',', ' '], ['.', ''], post('estimated_value')) : null,
        'next_action_at' => post('next_action_at') ?: null,
        'notes' => post('notes') ?: null,
    ];
    if (!$data['company'] && !$data['last_name'] && !$data['first_name']) $errors[] = 'Indiquez au moins une entreprise ou un nom.';
    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse email invalide.';
    if ($data['stage'] === 'won') $data['kind'] = 'client';

    if (!$errors) {
        if ($id) {
            $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
            db()->prepare("UPDATE crm_clients SET {$sets} WHERE id = ?")->execute([...array_values($data), $id]);
            flash('success', 'Fiche mise à jour.');
        } else {
            if ($fromMessage) $data['contact_message_id'] = $fromMessage;
            db()->prepare('INSERT INTO crm_clients (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')')->execute(array_values($data));
            $id = (int) db()->lastInsertId();
            crm_log($id, ($data['kind'] === 'client' ? 'Client' : 'Prospect') . ' créé' . ($fromMessage ? ' depuis une demande du site' : ''));
            flash('success', 'Fiche créée.');
        }
        redirect(admin_url('client.php', ['id' => $id]));
    }
    $client = $data;
}

$c = $client ?? ['kind' => 'prospect', 'stage' => 'new', 'is_company' => 1, 'country' => 'France', 'currency' => setting('billing_currency', 'EUR')];
$v = fn($k) => $c[$k] ?? '';
$stageOptions = array_map(fn($s) => $s[0], crm_stages());

admin_header($id ? 'Modifier : ' . crm_client_name($c) : 'Nouveau client / prospect', 'clients', [[icon('arrow-left', 'icon') . 'Retour', $id ? admin_url('client.php', ['id' => $id]) : admin_url('clients.php'), 'btn-ghost']]);
?>
<?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="edit-layout" data-dirty-check>
    <?= csrf_field() ?>
    <div class="edit-main">
        <section class="card">
            <div class="card-head"><h2>Identité</h2></div>
            <div class="form-grid">
                <?= f_text('company', 'Entreprise / organisation', $v('company')) ?>
                <?= f_text('job_title', 'Fonction du contact', $v('job_title')) ?>
                <?= f_text('first_name', 'Prénom', $v('first_name')) ?>
                <?= f_text('last_name', 'Nom', $v('last_name')) ?>
                <?= f_text('email', 'Email', $v('email'), ['type' => 'email']) ?>
                <?= f_text('phone', 'Téléphone', $v('phone'), ['type' => 'tel']) ?>
                <?= f_text('website', 'Site web', $v('website'), ['placeholder' => 'https://']) ?>
                <?= f_text('source', 'Origine', $v('source'), ['placeholder' => 'Site web, recommandation, LinkedIn…']) ?>
            </div>
        </section>
        <section class="card">
            <div class="card-head"><h2>Adresse & informations légales</h2><p class="muted small">L'adresse est obligatoire pour émettre une facture.</p></div>
            <div class="form-grid">
                <?= f_text('address', 'Adresse', $v('address'), ['class' => 'span-2']) ?>
                <?= f_text('postal_code', 'Code postal', $v('postal_code')) ?>
                <?= f_text('city', 'Ville', $v('city')) ?>
                <?= f_text('country', 'Pays', $v('country')) ?>
                <?= f_text('siren', 'SIREN / SIRET', $v('siren')) ?>
                <?= f_text('vat_number', 'N° TVA intracommunautaire', $v('vat_number')) ?>
                <?= f_text('other_id', 'Autre identifiant', $v('other_id'), ['placeholder' => 'NINEA, RCCM…']) ?>
            </div>
        </section>
        <section class="card">
            <div class="card-head"><h2>Notes</h2></div>
            <?= f_textarea('notes', 'Notes internes', $v('notes'), ['rows' => 6]) ?>
        </section>
    </div>
    <aside class="edit-side">
        <section class="card sticky">
            <div class="card-head"><h2>Relation commerciale</h2></div>
            <?= f_select('kind', 'Type', ['prospect' => 'Prospect', 'client' => 'Client'], $v('kind')) ?>
            <?= f_select('stage', 'Étape du pipeline', $stageOptions, $v('stage')) ?>
            <?= f_text('estimated_value', 'Valeur estimée du projet', $v('estimated_value'), ['placeholder' => '0']) ?>
            <?= f_text('next_action_at', 'Prochaine action le', $v('next_action_at'), ['type' => 'date']) ?>
            <?= f_select('currency', 'Devise de facturation', ['EUR' => 'Euro (€)', 'XOF' => 'Franc CFA (FCFA)'], $v('currency')) ?>
            <?= f_text('payment_terms_days', 'Délai de paiement (jours)', $v('payment_terms_days'), ['type' => 'number', 'min' => 0, 'help' => 'Vide = valeur par défaut (' . (int) setting('billing_payment_terms', 30) . ' jours).']) ?>
            <input type="hidden" name="is_company" value="1">
            <button type="submit" class="btn btn-primary btn-block"><?= icon('check') ?> Enregistrer</button>
        </section>
    </aside>
</form>
<?php admin_footer(); ?>
