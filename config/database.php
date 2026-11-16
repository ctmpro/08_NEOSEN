<?php
/**
 * Connexion PDO à MySQL (instance unique).
 */

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host    = env('DB_HOST', 'localhost');
    $port    = env('DB_PORT', '3306');
    $name    = env('DB_NAME', 'neosen');
    $charset = env('DB_CHARSET', 'utf8mb4');
    $dsn     = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

    try {
        $pdo = new PDO($dsn, env('DB_USER', 'root'), (string) env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('NEOSEN: connexion MySQL impossible — ' . $e->getMessage());
        http_response_code(503);
        if (env('APP_DEBUG', false)) {
            exit('Erreur de connexion à la base de données : ' . htmlspecialchars($e->getMessage()));
        }
        exit('Service momentanément indisponible. Merci de réessayer dans quelques instants.');
    }

    return $pdo;
}
