<?php
/**
 * Téléchargements d'une carte : PDF imprimeur, planche A4, QR code (SVG/PNG), faces en SVG.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/card_pdf.php';
require_admin();

$card = card_by_id((int) ($_GET['id'] ?? 0));
if (!$card) {
    http_response_code(404);
    exit('Carte introuvable.');
}
$type = (string) ($_GET['type'] ?? 'print');
$base = 'carte-' . slugify($card['code']);
$inline = isset($_GET['inline']);
$send = function (string $body, string $mime, string $name) use ($inline) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: private, no-store');
    echo $body;
    exit;
};

try {
    match ($type) {
        'print'  => $send(card_pdf($card, 'print'), 'application/pdf', $base . '-imprimeur-85x55-fond-perdu.pdf'),
        'sheet'  => $send(card_pdf($card, 'sheet'), 'application/pdf', $base . '-planche-A4.pdf'),
        'qr-svg' => $send(qr_svg(card_url($card)), 'image/svg+xml', $base . '-qrcode.svg'),
        'qr-png' => $send(qr_png(card_url($card), 24), 'image/png', $base . '-qrcode.png'),
        'recto', 'verso' => $send(card_svg($card, $type, isset($_GET['bleed'])), 'image/svg+xml', $base . '-' . $type . '.svg'),
        default  => throw new InvalidArgumentException('Type inconnu'),
    };
} catch (Throwable $e) {
    error_log('NEOSEN: fichier de carte — ' . $e->getMessage());
    http_response_code(500);
    exit('Impossible de générer le fichier : ' . e($e->getMessage()));
}
