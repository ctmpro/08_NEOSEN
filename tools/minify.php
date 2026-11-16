<?php
/**
 * Génère les versions minifiées des CSS/JS pour la production.
 * Usage : php tools/minify.php
 * Les fichiers *.min.css / *.min.js sont utilisés automatiquement quand APP_ENV=production.
 * À relancer après chaque modification de assets/css ou assets/js.
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
$root = dirname(__DIR__) . '/assets';

function minify_css(string $css): string {
    $css = preg_replace('!/\*.*?\*/!s', '', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/\s*([{};,>])\s*/', '$1', $css);
    $css = preg_replace('/:\s+/', ':', $css);
    return trim(str_replace(';}', '}', $css));
}

function minify_js(string $js): string {
    // Minification prudente : commentaires de bloc, commentaires de ligne en début de ligne, espaces de début/fin
    $js = preg_replace('!/\*.*?\*/!s', '', $js);
    $lines = array_map('trim', explode("\n", $js));
    $lines = array_filter($lines, fn($l) => $l !== '' && !str_starts_with($l, '//'));
    return implode("\n", $lines);
}

foreach (glob($root . '/css/*.css') as $file) {
    if (str_ends_with($file, '.min.css')) continue;
    $out = substr($file, 0, -4) . '.min.css';
    file_put_contents($out, minify_css(file_get_contents($file)));
    printf("%s → %s (%d → %d octets)\n", basename($file), basename($out), filesize($file), filesize($out));
}
foreach (glob($root . '/js/*.js') as $file) {
    if (str_ends_with($file, '.min.js')) continue;
    $out = substr($file, 0, -3) . '.min.js';
    file_put_contents($out, minify_js(file_get_contents($file)));
    printf("%s → %s (%d → %d octets)\n", basename($file), basename($out), filesize($file), filesize($out));
}
