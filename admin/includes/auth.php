<?php
/**
 * Authentification des administrateurs.
 *
 * - Mots de passe hashés (password_hash / password_verify, rehash automatique)
 * - Régénération de l'ID de session à la connexion
 * - Empreinte de session (user-agent) et expiration après inactivité
 * - Blocage temporaire après plusieurs échecs (par IP et par email)
 */

function current_admin(): ?array {
    static $admin = false;
    if ($admin !== false) {
        return $admin;
    }
    $admin = null;
    $id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    // Expiration après inactivité
    if (time() - (int) ($_SESSION['admin_last_seen'] ?? 0) > SESSION_LIFETIME) {
        admin_logout();
        flash('info', 'Votre session a expiré, merci de vous reconnecter.');
        return null;
    }
    // Empreinte du navigateur
    if (($_SESSION['admin_fp'] ?? '') !== session_fingerprint()) {
        admin_logout();
        return null;
    }
    $stmt = db()->prepare('SELECT id, name, email, role, last_login_at FROM admins WHERE id = ? AND is_active = 1');
    $stmt->execute([$id]);
    $admin = $stmt->fetch() ?: null;
    if ($admin) {
        $_SESSION['admin_last_seen'] = time();
    } else {
        admin_logout();
    }
    return $admin;
}

function session_fingerprint(): string {
    return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . env('APP_KEY', 'neosen'));
}

function require_admin(?string $role = null): array {
    $admin = current_admin();
    if (!$admin) {
        $_SESSION['admin_redirect'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect(admin_url('login.php'));
    }
    if ($role === 'superadmin' && $admin['role'] !== 'superadmin') {
        flash('error', 'Accès réservé aux super-administrateurs.');
        redirect(admin_url());
    }
    return $admin;
}

function is_superadmin(): bool {
    return (current_admin()['role'] ?? '') === 'superadmin';
}

/**
 * Nombre d'échecs récents pour cette IP ou cet email.
 */
function login_failures(string $email): int {
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE success = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE) AND (ip_address = ? OR (email <> "" AND email = ?))'
    );
    $stmt->execute([LOGIN_LOCK_MINUTES, client_ip(), $email]);
    return (int) $stmt->fetchColumn();
}

function record_login_attempt(string $email, bool $success): void {
    db()->prepare('INSERT INTO login_attempts (ip_address, email, success) VALUES (?, ?, ?)')
        ->execute([client_ip(), $email, $success ? 1 : 0]);
    // Nettoyage des tentatives anciennes
    if (random_int(1, 20) === 1) {
        db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 30 DAY)');
    }
}

/**
 * Tente une connexion. Retourne null si OK, sinon un message d'erreur.
 */
function admin_attempt_login(string $email, string $password): ?string {
    $email = mb_strtolower(trim($email));
    if (login_failures($email) >= LOGIN_MAX_ATTEMPTS) {
        return 'Trop de tentatives échouées. Réessayez dans ' . LOGIN_LOCK_MINUTES . ' minutes.';
    }
    $stmt = db()->prepare('SELECT * FROM admins WHERE email = ? AND is_active = 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    // Vérification à temps constant même si le compte n'existe pas
    $hash = $admin['password_hash'] ?? password_hash('neosen-dummy-password', PASSWORD_DEFAULT);
    if (!password_verify($password, $hash) || !$admin) {
        record_login_attempt($email, false);
        usleep(random_int(200000, 500000));
        $left = LOGIN_MAX_ATTEMPTS - login_failures($email);
        return 'Identifiants incorrects.' . ($left > 0 && $left <= 2 ? " Il vous reste {$left} tentative(s)." : '');
    }

    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
    }
    record_login_attempt($email, true);
    db()->prepare('UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?')->execute([client_ip(), $admin['id']]);

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_last_seen'] = time();
    $_SESSION['admin_fp'] = session_fingerprint();
    unset($_SESSION['_csrf']);
    return null;
}

function admin_logout(): void {
    unset($_SESSION['admin_id'], $_SESSION['admin_last_seen'], $_SESSION['admin_fp']);
    session_regenerate_id(true);
}

/**
 * Règles de robustesse du mot de passe. Retourne un message d'erreur ou null.
 */
function password_policy_error(string $password): ?string {
    if (mb_strlen($password) < 10) {
        return 'Le mot de passe doit contenir au moins 10 caractères.';
    }
    if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre.';
    }
    return null;
}
