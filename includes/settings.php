<?php
/**
 * Paramètres éditables (table `settings` + valeurs par défaut du schéma).
 */

function settings_schema(): array {
    static $schema = null;
    if ($schema === null) {
        $schema = require ROOT_PATH . '/config/settings_schema.php';
    }
    return $schema;
}

/**
 * Définition d'un champ du schéma (ou null).
 */
function setting_field(string $key): ?array {
    foreach (settings_schema() as $group) {
        if (isset($group['fields'][$key])) {
            return $group['fields'][$key];
        }
    }
    return null;
}

/**
 * Toutes les valeurs enregistrées pour une langue (avec repli sur la langue par défaut).
 */
function settings_all(?string $lang = null): array {
    static $cache = [];
    $lang = $lang ?? content_lang();
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }
    $values = [];
    try {
        $stmt = db()->prepare('SELECT setting_key, lang, setting_value FROM settings WHERE lang IN (?, ?)');
        $stmt->execute([APP_DEFAULT_LANG, $lang]);
        foreach ($stmt->fetchAll() as $row) {
            // La langue demandée est prioritaire sur la langue par défaut
            if ($row['lang'] === $lang || !array_key_exists($row['setting_key'], $values)) {
                $values[$row['setting_key']] = $row['setting_value'];
            }
        }
    } catch (Throwable $e) {
        error_log('NEOSEN: lecture des paramètres impossible — ' . $e->getMessage());
    }
    return $cache[$lang] = $values;
}

/**
 * Lit un paramètre. Valeur en base > défaut du schéma > $default.
 */
function setting(string $key, $default = null) {
    $all = settings_all();
    if (array_key_exists($key, $all) && $all[$key] !== null) {
        return $all[$key];
    }
    $field = setting_field($key);
    return $field['default'] ?? $default;
}

function setting_bool(string $key): bool {
    return (string) setting($key, '0') === '1';
}

function setting_lines(string $key): array {
    return lines((string) setting($key, ''));
}

/**
 * Titre avec mise en valeur : *mot* devient <span class="text-gradient">mot</span>.
 */
function highlight(?string $text): string {
    return preg_replace('/\*(.+?)\*/u', '<span class="text-gradient">$1</span>', e($text));
}

function save_setting(string $key, ?string $value, ?string $lang = null): void {
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, lang, setting_value) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $lang ?? APP_DEFAULT_LANG, $value]);
}

function site_name(): string {
    return (string) setting('site_name', 'NEOSEN');
}

function copyright_text(): string {
    return strtr((string) setting('copyright'), ['{year}' => date('Y'), '{site}' => site_name()]);
}

/**
 * Liens réseaux sociaux renseignés : ['linkedin' => 'https://…', …]
 */
function social_links(): array {
    $out = [];
    foreach (['linkedin', 'instagram', 'facebook', 'github', 'x', 'youtube'] as $network) {
        $link = trim((string) setting('social_' . $network));
        if ($link !== '') {
            $out[$network] = $link;
        }
    }
    return $out;
}
