<?php
/**
 * Notification instantanée de paiement (IPN) PayDunya.
 * L'adresse est transmise automatiquement à chaque paiement (callback_url) : rien à configurer.
 * Par sécurité, le statut est toujours revérifié auprès de l'API PayDunya.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/includes/payments.php';

$data = $_POST['data'] ?? null;
if (!is_array($data)) {
    $json = json_decode((string) file_get_contents('php://input'), true);
    $data = $json['data'] ?? $json;
}
if (!is_array($data)) {
    http_response_code(400);
    exit('Requête invalide');
}
if (isset($data['hash']) && !hash_equals(hash('sha512', trim((string) env('PAYDUNYA_MASTER_KEY'))), (string) $data['hash'])) {
    http_response_code(403);
    exit('Signature invalide');
}
$token = (string) ($data['invoice']['token'] ?? ($data['token'] ?? ''));
$s = db()->prepare('SELECT * FROM pay_transactions WHERE provider = "paydunya" AND provider_ref = ?');
$s->execute([$token]);
if ($tx = $s->fetch()) {
    try {
        pay_verify_tx($tx);
    } catch (Throwable $e) {
        error_log('NEOSEN: IPN PayDunya — ' . $e->getMessage());
        http_response_code(500);
        exit('Erreur');
    }
}
http_response_code(200);
echo 'ok';
