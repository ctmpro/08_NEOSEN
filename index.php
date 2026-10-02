<?php
/**
 * NEOSEN — Contrôleur frontal du site public.
 *
 * Toutes les URL propres (/services/creation-site-web, /realisations/badgel, …)
 * sont redirigées ici par le .htaccess, puis routées vers /pages.
 */

// Serveur PHP intégré (php -S localhost:8000 index.php) : servir les fichiers statiques directement
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file) && !str_ends_with($file, 'index.php')) {
        return false;
    }
}

require_once __DIR__ . '/config/bootstrap.php';

/* ---------------------------------------------------------------
 * Résolution du chemin demandé
 * --------------------------------------------------------------- */
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$base = base_path();
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = trim($path, '/');
if ($path === 'index.php') {
    $path = '';
}

// Préfixe de langue (/en/...) si la langue est activée
$segments = $path === '' ? [] : explode('/', $path);
if ($segments && $segments[0] !== APP_DEFAULT_LANG && in_array($segments[0], APP_LANGS, true)) {
    current_lang(array_shift($segments));
}
$path = implode('/', $segments);

/* ---------------------------------------------------------------
 * Table de routage
 * --------------------------------------------------------------- */
$routes = [
    ''                            => 'home',
    'a-propos'                    => 'about',
    'services'                    => 'services',
    'services/{slug}'             => 'service',
    'realisations'                => 'projects',
    'realisations/{slug}'         => 'project',
    'data'                        => 'data',
    'contact'                     => 'contact',
    'mentions-legales'            => 'legal',
    'politique-de-confidentialite'=> 'privacy',
    'sitemap.xml'                 => 'sitemap',
    'robots.txt'                  => 'robots',
];

$page   = null;
$params = [];
foreach ($routes as $pattern => $target) {
    $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[a-z0-9\-]+)', $pattern) . '$#';
    if (preg_match($regex, $path, $m)) {
        $page   = $target;
        $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
        break;
    }
}

// Variables communes disponibles dans les vues
$current_page = $page ?? '404';
$meta = [
    'title'       => site_name(),
    'description' => (string) setting('seo_home_desc'),
    'path'        => $path,
    'canonical'   => absolute_url($path),
    'image'       => (string) setting('og_image'),
    'type'        => 'website',
    'schema'      => [],
];

if ($page === null) {
    http_response_code(404);
    $page = '404';
}

require ROOT_PATH . '/pages/' . $page . '.php';
