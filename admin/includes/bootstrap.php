<?php
/**
 * Amorçage du back-office : configuration commune + authentification.
 * Chaque page admin (hors login) appelle require_admin() après ce fichier.
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/form.php';
require_once __DIR__ . '/layout.php';

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-store, no-cache, must-revalidate');

/** URL d'une page du back-office. */
function admin_url(string $page = 'dashboard.php', array $query = []): string {
    return base_path() . '/admin/' . $page . ($query ? '?' . http_build_query($query) : '');
}

/** Vérifie le jeton CSRF des requêtes POST du back-office. */
function admin_csrf_guard(): void {
    if (is_post() && !csrf_check()) {
        flash('error', 'Session expirée, merci de recommencer.');
        redirect($_SERVER['REQUEST_URI'] ?? admin_url());
    }
}

/** Champs POST nettoyés. */
function post(string $key, string $default = ''): string {
    return trim((string) ($_POST[$key] ?? $default));
}

function post_int(string $key, int $default = 0): int {
    return isset($_POST[$key]) && $_POST[$key] !== '' ? (int) $_POST[$key] : $default;
}

function post_bool(string $key): int {
    return !empty($_POST[$key]) ? 1 : 0;
}

/** Génère un slug unique dans une table. */
function unique_slug(string $table, string $slug, int $ignoreId = 0, string $lang = 'fr'): string {
    $base = slugify($slug);
    $candidate = $base;
    $i = 2;
    $stmt = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = ? AND lang = ? AND id <> ?");
    while (true) {
        $stmt->execute([$candidate, $lang, $ignoreId]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $candidate;
        }
        $candidate = $base . '-' . $i++;
    }
}

/** Langue de contenu éditée (sélecteur dans l'admin si plusieurs langues). */
function admin_lang(): string {
    if (isset($_GET['lang']) && in_array($_GET['lang'], APP_LANGS, true)) {
        $_SESSION['admin_lang'] = $_GET['lang'];
    }
    $lang = $_SESSION['admin_lang'] ?? APP_DEFAULT_LANG;
    current_lang($lang);
    return $lang;
}
