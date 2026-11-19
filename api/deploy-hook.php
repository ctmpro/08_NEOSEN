<?php
/**
 * Appelé par GitHub Actions après chaque déploiement : applique les migrations de la base.
 * Protégé par le jeton DEPLOY_INSTALL_TOKEN (fichier .env du serveur = secret GitHub du même nom).
 * Sans jeton configuré, l'adresse n'existe pas (404).
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/includes/migrations.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

$expected = trim((string) env('DEPLOY_INSTALL_TOKEN', ''));
if ($expected === '') {
    http_response_code(404);
    exit(json_encode(['ok' => false]));
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'POST attendu']));
}
if (!hash_equals($expected, (string) ($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? ''))) {
    sleep(1);
    error_log('NEOSEN: appel refusé sur deploy-hook depuis ' . client_ip());
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'Jeton invalide']));
}

try {
    $pdo = db();
    $before = (int) $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'db_version' AND lang = 'fr'")->fetchColumn();
    if ($before < DB_VERSION) {
        db_migrate($pdo);
    }
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    echo json_encode(['ok' => true, 'db_version_before' => $before, 'db_version' => DB_VERSION]);
} catch (Throwable $e) {
    error_log('NEOSEN: migration après déploiement impossible — ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Migration impossible : consultez les journaux PHP du serveur.']);
}
