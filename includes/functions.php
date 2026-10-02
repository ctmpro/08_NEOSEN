<?php
/**
 * Fonctions utilitaires communes.
 */

/* ------------------------------------------------------------------
 * Sessions & sécurité
 * ------------------------------------------------------------------ */

function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name(env('SESSION_NAME', 'neosen_sess'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() ?: '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(?string $token = null): bool {
    $token = $token ?? ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return is_string($token) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}

function client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/* ------------------------------------------------------------------
 * Échappement & texte
 * ------------------------------------------------------------------ */

function e($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Nettoie le HTML saisi dans l'administration (liste blanche de balises).
 */
function clean_html(?string $html): string {
    $html = (string) $html;
    if ($html === '') {
        return '';
    }
    $allowed = '<p><br><strong><b><em><i><u><a><ul><ol><li><h2><h3><h4><blockquote><span><hr><img><figure><figcaption><table><thead><tbody><tr><th><td><code><pre>';
    $html = strip_tags($html, $allowed);
    // Supprime les attributs d'événements et les URL javascript:
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*([\'"])\s*(javascript|vbscript|data):[^\'"]*\2/i', '$1="#"', $html);
    $html = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html);
    return $html;
}

/**
 * Affiche un texte riche : HTML nettoyé s'il contient des balises, sinon paragraphes automatiques.
 */
function rich_text(?string $text): string {
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    if ($text !== strip_tags($text)) {
        return clean_html($text);
    }
    $paragraphs = preg_split('/\n\s*\n/', $text);
    return implode("\n", array_map(fn($p) => '<p>' . nl2br(e(trim($p))) . '</p>', $paragraphs));
}

/**
 * Transforme un texte multiligne en tableau (une entrée par ligne non vide).
 */
function lines(?string $text): array {
    if ($text === null || trim($text) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text)), 'strlen'));
}

function csv_list(?string $text): array {
    if ($text === null || trim($text) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $text)), 'strlen'));
}

function slugify(string $text): string {
    $text = trim($text);
    if (function_exists('transliterator_transliterate')) {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
    } else {
        $text = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text));
    }
    $text = preg_replace('/[^a-z0-9]+/', '-', strtolower($text));
    return trim($text, '-') ?: 'element';
}

function excerpt(?string $text, int $length = 160): string {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length - 1)) . '…';
}

/* ------------------------------------------------------------------
 * URL
 * ------------------------------------------------------------------ */

/**
 * URL publique du site (paramétrable dans l'admin, sinon APP_URL du .env).
 */
function site_url(): string {
    static $url = null;
    if ($url === null) {
        $url = rtrim((string) env('APP_URL', ''), '/');
        if ($url === '' && !empty($_SERVER['HTTP_HOST'])) {
            $url = (is_https() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
        }
    }
    return $url;
}

/**
 * Chemin de base si le site est installé dans un sous-dossier (ex : /neosensite).
 */
function base_path(): string {
    static $path = null;
    if ($path === null) {
        $path = rtrim((string) parse_url((string) env('APP_URL', ''), PHP_URL_PATH), '/');
    }
    return $path;
}

/**
 * URL interne relative à la racine, avec préfixe de langue si nécessaire.
 */
function url(string $path = '', ?string $lang = null): string {
    $lang = $lang ?? current_lang();
    $prefix = ($lang !== APP_DEFAULT_LANG) ? '/' . $lang : '';
    $path = ltrim($path, '/');
    return base_path() . $prefix . '/' . $path;
}

function absolute_url(string $path = '', ?string $lang = null): string {
    $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', site_url());
    return $origin . url($path, $lang);
}

/**
 * Lien vers un asset statique, avec version pour le cache et version minifiée en production.
 */
function asset(string $path): string {
    $path = ltrim($path, '/');
    if (APP_ENV === 'production' && preg_match('/\.(css|js)$/', $path)) {
        $min = preg_replace('/\.(css|js)$/', '.min.$1', $path);
        if (is_file(ROOT_PATH . '/assets/' . $min)) {
            $path = $min;
        }
    }
    $file = ROOT_PATH . '/assets/' . $path;
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return base_path() . '/assets/' . $path . '?v=' . $v;
}

/**
 * URL d'un fichier uploadé (ou d'une URL externe / d'un asset).
 */
function media_url(?string $path, bool $absolute = false): string {
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#', $path)) {
        return $path;
    }
    $rel = base_path() . '/' . ltrim($path, '/');
    if ($absolute) {
        return preg_replace('#^(https?://[^/]+).*$#', '$1', site_url()) . $rel;
    }
    return $rel;
}

function redirect(string $to, int $code = 302): void {
    header('Location: ' . $to, true, $code);
    exit;
}

function is_post(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function wants_json(): bool {
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ------------------------------------------------------------------
 * Messages flash
 * ------------------------------------------------------------------ */

function flash(string $type, string $message): void {
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/* ------------------------------------------------------------------
 * Liens externes paramétrables
 * ------------------------------------------------------------------ */

function whatsapp_link(?string $message = null): string {
    $number = preg_replace('/\D+/', '', (string) setting('contact_whatsapp'));
    if ($number === '') {
        return '';
    }
    $message = $message ?? (string) setting('contact_whatsapp_message');
    return 'https://wa.me/' . $number . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

function tel_link(?string $phone): string {
    return 'tel:' . preg_replace('/[^\d+]/', '', (string) $phone);
}

/**
 * Résout un lien saisi dans l'admin : URL absolue, ancre, mailto/tel ou chemin interne.
 */
function link_to(?string $target): string {
    $target = trim((string) $target);
    if ($target === '') {
        return url();
    }
    if (preg_match('#^(https?:|mailto:|tel:|\#)#i', $target)) {
        return $target;
    }
    if ($target === 'whatsapp') {
        return whatsapp_link() ?: url('contact');
    }
    return url($target);
}

function is_external(string $href): bool {
    return (bool) preg_match('#^https?://#i', $href) && !str_starts_with($href, site_url());
}

/* ------------------------------------------------------------------
 * Vues
 * ------------------------------------------------------------------ */

function partial(string $name, array $vars = []): void {
    extract($vars, EXTR_SKIP);
    include ROOT_PATH . '/includes/partials/' . $name . '.php';
}

/**
 * Formate une date en français (ex : 12 mars 2026).
 */
function format_date(?string $date, bool $withTime = false): string {
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $out = date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    return $withTime ? $out . ' · ' . date('H:i', $ts) : $out;
}
