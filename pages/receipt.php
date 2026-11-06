<?php
/**
 * Reçu de paiement (PDF) accessible au client via un lien sécurisé : /recu/{jeton}
 */
require_once ROOT_PATH . '/includes/payments.php';

$receipt = null;
try {
    $receipt = receipt_by_token((string) ($params['slug'] ?? ''));
} catch (Throwable $e) {
    $receipt = null;
}
if (!$receipt) {
    http_response_code(404);
    require ROOT_PATH . '/pages/404.php';
    return;
}
receipt_pdf_response($receipt, !empty($_GET['dl']));
