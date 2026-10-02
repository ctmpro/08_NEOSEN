<?php
/**
 * Connexion au back-office.
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (current_admin()) {
    redirect(admin_url());
}

$error = null;
$email = '';
if (is_post()) {
    $email = post('email');
    if (!csrf_check()) {
        $error = 'Session expirée, merci de réessayer.';
    } elseif ($email === '' || post('password') === '') {
        $error = 'Merci de renseigner votre email et votre mot de passe.';
    } else {
        $error = admin_attempt_login($email, (string) ($_POST['password'] ?? ''));
        if ($error === null) {
            $to = $_SESSION['admin_redirect'] ?? '';
            unset($_SESSION['admin_redirect']);
            // Redirection interne uniquement
            redirect(str_starts_with($to, base_path() . '/admin/') ? $to : admin_url());
        }
    }
}
$flashes = get_flashes();
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Connexion — Administration <?= e(site_name()) ?></title>
    <link rel="icon" href="<?= e(media_url(setting('favicon'))) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Sora:wght@600&display=swap">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
    <style>:root{--primary:<?= e(setting('color_primary')) ?>;--accent:<?= e(setting('color_accent')) ?>;--dark:<?= e(setting('color_dark')) ?>}</style>
</head>
<body class="login-page">
    <div class="login-visual" aria-hidden="true">
        <div class="login-grid"></div>
        <div class="login-glow"></div>
        <div class="login-quote">
            <?php if ($logo = setting('logo_light')): ?><img src="<?= e(media_url($logo)) ?>" alt=""><?php endif; ?>
            <p><?= e(setting('site_tagline')) ?></p>
        </div>
    </div>
    <main class="login-box">
        <form method="post" class="login-form" autocomplete="on">
            <h1>Connexion</h1>
            <p class="muted">Accédez à l'administration de <?= e(site_name()) ?>.</p>
            <?php foreach ($flashes as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
            <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <?= csrf_field() ?>
            <?= f_text('email', 'Email', $email, ['type' => 'email', 'required' => true, 'autocomplete' => 'username']) ?>
            <div class="field">
                <label for="f-password">Mot de passe <span class="req">*</span></label>
                <div class="password-field">
                    <input type="password" id="f-password" name="password" required autocomplete="current-password">
                    <button type="button" class="pw-toggle" data-pw-toggle aria-label="Afficher le mot de passe"><?= icon('eye') ?></button>
                </div>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Se connecter <?= icon('arrow') ?></button>
            <a class="back-link" href="<?= e(url()) ?>"><?= icon('arrow-left') ?> Retour au site</a>
        </form>
    </main>
    <script src="<?= asset('js/admin.js') ?>" defer></script>
</body>
</html>
