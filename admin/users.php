<?php
/**
 * Gestion des comptes administrateurs (réservé aux super-administrateurs).
 */
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_admin('superadmin');
admin_csrf_guard();

$roles = ['superadmin' => 'Super-administrateur', 'admin' => 'Administrateur', 'editor' => 'Éditeur'];

if (is_post()) {
    $id = post_int('id');
    $action = post('action');
    $superCount = (int) db()->query('SELECT COUNT(*) FROM admins WHERE role = "superadmin" AND is_active = 1')->fetchColumn();
    $stmt = db()->prepare('SELECT * FROM admins WHERE id = ?');
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if ($action === 'create') {
        $name = post('name');
        $email = mb_strtolower(post('email'));
        $role = array_key_exists(post('role'), $roles) ? post('role') : 'admin';
        $password = (string) ($_POST['password'] ?? '');
        $exists = db()->prepare('SELECT COUNT(*) FROM admins WHERE email = ?');
        $exists->execute([$email]);
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Nom et email valides obligatoires.');
        } elseif ((int) $exists->fetchColumn() > 0) {
            flash('error', 'Un compte existe déjà avec cet email.');
        } elseif ($err = password_policy_error($password)) {
            flash('error', $err);
        } else {
            db()->prepare('INSERT INTO admins (name, email, password_hash, role) VALUES (?, ?, ?, ?)')->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            flash('success', "Compte créé pour {$email}.");
        }
    } elseif ($target && (int) $target['id'] === (int) $me['id'] && in_array($action, ['toggle', 'delete', 'role'], true)) {
        flash('error', 'Vous ne pouvez pas modifier votre propre statut ici (utilisez « Mon compte »).');
    } elseif ($target && $action === 'toggle') {
        if ($target['role'] === 'superadmin' && $target['is_active'] && $superCount <= 1) {
            flash('error', 'Impossible de désactiver le dernier super-administrateur.');
        } else {
            db()->prepare('UPDATE admins SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            flash('success', 'Statut mis à jour.');
        }
    } elseif ($target && $action === 'role') {
        $role = array_key_exists(post('role'), $roles) ? post('role') : 'admin';
        db()->prepare('UPDATE admins SET role = ? WHERE id = ?')->execute([$role, $id]);
        flash('success', 'Rôle mis à jour.');
    } elseif ($target && $action === 'password') {
        $password = (string) ($_POST['password'] ?? '');
        if ($err = password_policy_error($password)) {
            flash('error', $err);
        } else {
            db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            flash('success', 'Mot de passe réinitialisé pour ' . $target['email'] . '.');
        }
    } elseif ($target && $action === 'delete') {
        db()->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
        flash('success', 'Compte supprimé.');
    }
    redirect(admin_url('users.php'));
}

$admins = db()->query('SELECT * FROM admins ORDER BY role = "superadmin" DESC, name')->fetchAll();
admin_header('Administrateurs', 'users');
?>
<div class="grid-2 grid-2--wide-left">
    <section class="card">
        <div class="card-head"><h2><?= count($admins) ?> compte(s)</h2></div>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Utilisateur</th><th>Rôle</th><th class="hide-sm">Dernière connexion</th><th class="col-actions">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($admins as $a): $self = (int) $a['id'] === (int) $me['id']; ?>
            <tr class="<?= $a['is_active'] ? '' : 'is-off' ?>">
                <td><div class="cell-project"><span class="avatar avatar--soft"><?= e(mb_strtoupper(mb_substr($a['name'], 0, 1))) ?></span><span><strong><?= e($a['name']) ?><?= $self ? ' (vous)' : '' ?></strong><small class="muted d-block"><?= e($a['email']) ?></small></span></div></td>
                <td>
                    <?php if ($self): ?><span class="pill"><?= e($roles[$a['role']]) ?></span><?php else: ?>
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="action" value="role">
                        <select name="role" onchange="this.form.submit()" aria-label="Rôle"><?php foreach ($roles as $k => $l): ?><option value="<?= $k ?>"<?= $a['role'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
                    </form>
                    <?php endif; ?>
                </td>
                <td class="hide-sm"><?= $a['last_login_at'] ? e(format_date($a['last_login_at'], true)) : '<span class="muted">Jamais</span>' ?></td>
                <td class="col-actions">
                    <div class="actions">
                        <details class="dropdown">
                            <summary class="icon-btn" title="Réinitialiser le mot de passe"><?= icon('shield') ?></summary>
                            <form method="post" class="dropdown-panel"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="action" value="password">
                                <label class="small">Nouveau mot de passe</label>
                                <input type="password" name="password" required minlength="10" autocomplete="new-password">
                                <button class="btn btn-sm btn-primary">Réinitialiser</button>
                            </form>
                        </details>
                        <?php if (!$self): ?>
                        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="action" value="toggle">
                            <button class="icon-btn" title="<?= $a['is_active'] ? 'Désactiver' : 'Activer' ?>"><?= icon($a['is_active'] ? 'eye' : 'eye-off') ?></button>
                        </form>
                        <form method="post" class="inline-form" data-confirm="Supprimer le compte de <?= e($a['email']) ?> ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="action" value="delete">
                            <button class="icon-btn icon-btn--danger" title="Supprimer"><?= icon('trash') ?></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
    <section class="card">
        <div class="card-head"><h2>Nouveau compte</h2></div>
        <form method="post" autocomplete="off"><?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <?= f_text('name', 'Nom', '', ['required' => true]) ?>
            <?= f_text('email', 'Email', '', ['type' => 'email', 'required' => true]) ?>
            <?= f_select('role', 'Rôle', $roles, 'admin', ['help' => 'Seuls les super-administrateurs gèrent les comptes.']) ?>
            <?= f_text('password', 'Mot de passe', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'help' => '10 caractères min., avec majuscule, minuscule et chiffre.']) ?>
            <button class="btn btn-primary"><?= icon('plus') ?> Créer le compte</button>
        </form>
    </section>
</div>
<?php admin_footer(); ?>
