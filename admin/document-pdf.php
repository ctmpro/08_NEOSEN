<?php
/**
 * PDF d'un document (affichage ou téléchargement).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_once ROOT_PATH . '/includes/crm_pdf.php';

$doc = crm_doc((int) ($_GET['id'] ?? 0));
if (!$doc) {
    http_response_code(404);
    exit('Document introuvable.');
}
$pdf = crm_pdf((int) $doc['id']);
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . crm_pdf_filename($doc) . '"');
header('Content-Length: ' . strlen($pdf));
header('X-Frame-Options: SAMEORIGIN');
echo $pdf;
