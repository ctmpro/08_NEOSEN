<?php
/**
 * Amorçage commun au site public, à l'API et à l'administration.
 */

require_once __DIR__ . '/env.php';
loadEnvFile();

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/database.php';

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set(env('APP_TIMEZONE', 'Africa/Dakar'));
mb_internal_encoding('UTF-8');

require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/i18n.php';
require_once ROOT_PATH . '/includes/settings.php';
require_once ROOT_PATH . '/includes/repository.php';
require_once ROOT_PATH . '/includes/icons.php';
require_once ROOT_PATH . '/includes/uploads.php';

start_secure_session();
