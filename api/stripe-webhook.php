<?php
/**
 * Webhook Stripe : confirme les paiements même si le client ferme son navigateur avant le retour sur le site.
 * À déclarer dans Stripe > Développeurs > Webhooks : https://votre-domaine/api/stripe-webhook.php
 * Événements : checkout.session.completed, checkout.session.async_payment_succeeded
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/includes/payments.php';

$payload = (string) file_get_contents('php://input');
$secret = trim((string) env('STRIPE_WEBHOOK_SECRET', ''));
if ($secret === '' || !stripe_webhook_valid($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), $secret)) {
    http_response_code(400);
    exit('Signature invalide');
}
$event = json_decode($payload, true);
$type = (string) ($event['type'] ?? '');
$session = $event['data']['object'] ?? [];

if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) && ($session['payment_status'] ?? '') === 'paid') {
    $s = db()->prepare('SELECT * FROM pay_transactions WHERE provider = "stripe" AND provider_ref = ?');
    $s->execute([(string) ($session['id'] ?? '')]);
    if ($tx = $s->fetch()) {
        $expected = $tx['charged_currency'] === 'XOF' ? (int) round($tx['charged_amount']) : (int) round($tx['charged_amount'] * 100);
        if ((int) ($session['amount_total'] ?? 0) >= $expected) {
            try {
                pay_complete((int) $tx['id'], (string) ($session['payment_intent'] ?? $session['id']), $session, [
                    'name' => $session['customer_details']['name'] ?? null, 'email' => $session['customer_details']['email'] ?? null, 'phone' => $session['customer_details']['phone'] ?? null,
                ]);
            } catch (Throwable $e) {
                http_response_code(500);
                exit('Erreur');
            }
        } else {
            error_log('NEOSEN: montant Stripe incohérent pour la transaction ' . $tx['id']);
        }
    }
} elseif (in_array($type, ['checkout.session.expired', 'checkout.session.async_payment_failed'], true)) {
    db()->prepare('UPDATE pay_transactions SET status = ? WHERE provider = "stripe" AND provider_ref = ? AND status = "pending"')
        ->execute([$type === 'checkout.session.expired' ? 'cancelled' : 'failed', (string) ($session['id'] ?? '')]);
}
http_response_code(200);
echo 'ok';
