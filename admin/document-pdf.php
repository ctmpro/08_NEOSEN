<?php
/**
 * PDF d'un document (affichage ou téléchargement).
 * Factures et avoirs émis : le fichier archivé à l'émission est renvoyé tel quel.
 */
ob_start();
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$doc = crm_doc((int) ($_GET['id'] ?? 0));
if (!$doc) {
    ob_end_clean();
    http_response_code(404);
    exit('Document introuvable.');
}
crm_pdf_response($doc, !empty($_GET['download']), true);
