<?php
/**
 * Vérification Google reCAPTCHA (v3 par défaut, v2 possible).
 *
 * Utilise la bibliothèque officielle placée dans /utilitaires/ReCaptcha si elle est présente,
 * sinon appelle directement l'API siteverify. Désactivé si aucune clé n'est renseignée dans le .env.
 */

function recaptcha_enabled(): bool {
    return (bool) env('RECAPTCHA_SITE_KEY') && (bool) env('RECAPTCHA_SECRET_KEY');
}

function recaptcha_version(): string {
    return env('RECAPTCHA_VERSION', 'v3') === 'v2' ? 'v2' : 'v3';
}

/**
 * Charge la bibliothèque google/recaptcha depuis /utilitaires (plusieurs structures acceptées).
 */
function load_recaptcha_library(): bool {
    if (class_exists('\\ReCaptcha\\ReCaptcha')) {
        return true;
    }
    foreach ([UTIL_PATH . '/ReCaptcha/src/autoload.php', UTIL_PATH . '/ReCaptcha/autoload.php', UTIL_PATH . '/recaptcha/src/autoload.php'] as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;
            return class_exists('\\ReCaptcha\\ReCaptcha');
        }
    }
    // Dossier contenant directement ReCaptcha.php (racine du namespace ReCaptcha\)
    foreach ([UTIL_PATH . '/ReCaptcha', UTIL_PATH . '/ReCaptcha/src/ReCaptcha', UTIL_PATH . '/recaptcha/src/ReCaptcha'] as $dir) {
        if (is_file($dir . '/ReCaptcha.php')) {
            spl_autoload_register(function ($class) use ($dir) {
                if (str_starts_with($class, 'ReCaptcha\\')) {
                    $file = $dir . '/' . str_replace('\\', '/', substr($class, 10)) . '.php';
                    if (is_file($file)) {
                        require_once $file;
                    }
                }
            });
            return class_exists('\\ReCaptcha\\ReCaptcha');
        }
    }
    return false;
}

/**
 * Vérifie le jeton envoyé par le formulaire.
 */
function recaptcha_verify(?string $token, string $action = 'contact'): bool {
    if (!recaptcha_enabled()) {
        return true;
    }
    if (!$token) {
        return false;
    }
    $secret   = (string) env('RECAPTCHA_SECRET_KEY');
    $minScore = (float) env('RECAPTCHA_MIN_SCORE', 0.5);
    $isV3     = recaptcha_version() === 'v3';

    if (load_recaptcha_library()) {
        $recaptcha = new \ReCaptcha\ReCaptcha($secret);
        if ($isV3) {
            $recaptcha->setExpectedAction($action)->setScoreThreshold($minScore);
        }
        $resp = $recaptcha->verify($token, client_ip());
        if (!$resp->isSuccess()) {
            error_log('NEOSEN: reCAPTCHA refusé — ' . implode(', ', $resp->getErrorCodes()));
        }
        return $resp->isSuccess();
    }

    // Repli : appel direct de l'API
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => client_ip()]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $data = json_decode((string) $raw, true);
    if (empty($data['success'])) {
        return false;
    }
    if ($isV3) {
        return ($data['action'] ?? '') === $action && (float) ($data['score'] ?? 0) >= $minScore;
    }
    return true;
}
