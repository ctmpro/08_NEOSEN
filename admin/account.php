<?php
/**
 * Mon compte : profil et changement de mot de passe.
 */
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_admin();
admin_csrf_guard();

if (is_post()) {
    if (post('action') === 'profile') {
        $name = post('name');
        $email = mb_strtolower(post('email'));
        $exists = db()->prepare('SELECT COUNT(*) FROM admins WHERE email = ? AND id <> ?');
        $exists->execute([$email, $me['id']]);
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Nom et email valides obligatoires.');
        } elseif ((int) $exists->fetchColumn() > 0) {
            flash('error', 'Cet email est déjà utilisé par un autre compte.');
        } else {
            db()->prepare('UPDATE admins SET name = ?, email = ? WHERE id = ?')->execute([$name, $email, $me['id']]);
            flash('success', 'Profil mis à jour.');
        }
    } elseif (post('action') === 'password') {
        $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $stmt->execute([$me['id']]);
        $hash = (string) $stmt->fetchColumn();
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if (!password_verify($current, $hash)) {
            flash('error', 'Mot de passe actuel incorrect.');
        } elseif ($new !== $confirm) {
            flash('error', 'La confirmation ne correspond pas au nouveau mot de passe.');
        } elseif ($err = password_policy_error($new)) {
            flash('error', $err);
        } else {
            db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('success', 'Mot de passe modifié.');
        }
    }
    redirect(admin_url('account.php'));
}

admin_header('Mon compte', 'account');
?>
<div class="grid-2">
    <section class="card">
        <div class="card-head"><h2>Profil</h2></div>
        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">
            <?= f_text('name', 'Nom', $me['name'], ['required' => true]) ?>
            <?= f_text('email', 'Email de connexion', $me['email'], ['type' => 'email', 'required' => true]) ?>
            <button class="btn btn-primary"><?= icon('check') ?> Enregistrer</button>
        </form>
    </section>
    <section class="card">
        <div class="card-head"><h2>Changer le mot de passe</h2></div>
        <form method="post" autocomplete="off"><?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <?= f_text('current_password', 'Mot de passe actuel', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
            <?= f_text('new_password', 'Nouveau mot de passe', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'help' => '10 caractères min., avec majuscule, minuscule et chiffre.']) ?>
            <?= f_text('confirm_password', 'Confirmation', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
            <button class="btn btn-primary"><?= icon('shield') ?> Modifier le mot de passe</button>
        </form>
    </section>
</div>
<?php admin_footer(); ?>
