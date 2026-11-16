<?php
/**
 * Constantes de l'application.
 */

define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('UTIL_PATH', ROOT_PATH . '/utilitaires');

define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', (bool) env('APP_DEBUG', false));

// Langues : la première version est en français, l'anglais peut être activé via APP_LANGS=fr,en
define('APP_DEFAULT_LANG', env('APP_DEFAULT_LANG', 'fr'));
define('APP_LANGS', array_values(array_filter(array_map('trim', explode(',', (string) env('APP_LANGS', 'fr'))))));

// Tailles maximales d'upload (octets)
define('MAX_IMAGE_SIZE', 8 * 1024 * 1024);
define('MAX_VIDEO_SIZE', 60 * 1024 * 1024);
define('MAX_ATTACHMENT_SIZE', 5 * 1024 * 1024);

// Sécurité de l'authentification admin
define('LOGIN_MAX_ATTEMPTS', 5);     // tentatives échouées autorisées
define('LOGIN_LOCK_MINUTES', 15);    // fenêtre / durée de blocage
define('SESSION_LIFETIME', 7200);    // 2 h d'inactivité max (admin)
