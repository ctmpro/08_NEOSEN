<?php
/**
 * Chargement des variables d'environnement depuis le fichier .env (racine du projet).
 */

function loadEnvFile(): bool {
    $envFile = __DIR__ . '/../.env';
    
    if (!file_exists($envFile)) {
        error_log("NEOSEN: Fichier .env non trouvé dans " . dirname($envFile));
        return false;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Ignorer les commentaires
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Supprimer les guillemets autour de la valeur
            if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
    
    return true;
}

/**
 * Lecture d'une variable d'environnement avec valeur par défaut.
 */
function env(string $key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    switch (strtolower((string) $value)) {
        case 'true':  return true;
        case 'false': return false;
        case 'null':  return null;
    }
    return $value;
}
