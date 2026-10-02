<?php
/**
 * Internationalisation.
 *
 * - Les libellés d'interface sont dans /lang/{code}.php
 * - Les contenus éditables (settings, services, réalisations, blocs) possèdent une colonne `lang`
 * - Le routeur détecte un préfixe de langue (/en/...) si la langue est activée dans APP_LANGS
 */

function current_lang(?string $set = null): string {
    static $lang = null;
    if ($set !== null && in_array($set, APP_LANGS, true)) {
        $lang = $set;
    }
    return $lang ?? APP_DEFAULT_LANG;
}

function t(string $key, array $replace = []): string {
    static $strings = [];
    $lang = current_lang();
    if (!isset($strings[$lang])) {
        $file = ROOT_PATH . '/lang/' . $lang . '.php';
        $strings[$lang] = is_file($file) ? require $file : [];
        if ($lang !== APP_DEFAULT_LANG) {
            $strings[$lang] += require ROOT_PATH . '/lang/' . APP_DEFAULT_LANG . '.php';
        }
    }
    $text = $strings[$lang][$key] ?? $key;
    foreach ($replace as $k => $v) {
        $text = str_replace(':' . $k, (string) $v, $text);
    }
    return $text;
}

/**
 * Langue utilisée pour lire le contenu en base (repli sur la langue par défaut).
 */
function content_lang(): string {
    return current_lang();
}
