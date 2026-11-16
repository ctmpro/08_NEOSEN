<?php
/**
 * Génération de QR codes (bibliothèque chillerlan/php-qrcode dans /utilitaires/QRCode).
 */

require_once UTIL_PATH . '/QRCode/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;

/**
 * Matrice du QR code : tableau de lignes de booléens (true = module noir), sans marge.
 * Niveau de correction Q (25 %) : reste lisible même si la carte est un peu abîmée.
 */
function qr_matrix(string $data): array {
    $qr = new QRCode(new QROptions(['eccLevel' => EccLevel::Q, 'addQuietzone' => false]));
    $qr->addByteSegment($data);
    return $qr->getQRMatrix()->getMatrix(true);
}

/**
 * QR code en SVG vectoriel (idéal pour l'impression).
 */
function qr_svg(string $data, string $fg = '#0A0F1F', string $bg = '#FFFFFF', int $margin = 4): string {
    $m = qr_matrix($data);
    $n = count($m);
    $size = $n + 2 * $margin;
    $path = '';
    foreach ($m as $y => $row) {
        $x = 0;
        while ($x < $n) {
            if ($row[$x]) {
                $start = $x;
                while ($x < $n && $row[$x]) $x++;
                $path .= 'M' . ($start + $margin) . ' ' . ($y + $margin) . 'h' . ($x - $start) . 'v1h-' . ($x - $start) . 'z';
            } else {
                $x++;
            }
        }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" shape-rendering="crispEdges">'
        . ($bg !== '' ? '<rect width="100%" height="100%" fill="' . htmlspecialchars($bg) . '"/>' : '')
        . '<path fill="' . htmlspecialchars($fg) . '" d="' . $path . '"/></svg>';
}

/**
 * QR code en PNG (GD).
 */
function qr_png(string $data, int $scale = 16, int $margin = 4, array $fg = [10, 15, 31]): string {
    $m = qr_matrix($data);
    $n = count($m);
    $px = ($n + 2 * $margin) * $scale;
    $img = imagecreatetruecolor($px, $px);
    imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
    $c = imagecolorallocate($img, ...$fg);
    foreach ($m as $y => $row) {
        foreach ($row as $x => $on) {
            if ($on) {
                imagefilledrectangle($img, ($x + $margin) * $scale, ($y + $margin) * $scale, ($x + $margin + 1) * $scale - 1, ($y + $margin + 1) * $scale - 1, $c);
            }
        }
    }
    ob_start();
    imagepng($img);
    imagedestroy($img);
    return (string) ob_get_clean();
}
