<?php
/**
 * Paiement en ligne (Stripe, PayDunya), liens de paiement et reçus.
 *
 * Les clés des prestataires sont lues dans le fichier .env (jamais en base) :
 *   STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET
 *   PAYDUNYA_MODE (test|live), PAYDUNYA_MASTER_KEY, PAYDUNYA_PRIVATE_KEY, PAYDUNYA_TOKEN
 */

require_once ROOT_PATH . '/includes/crm.php';

/* ==================================================================
 * Configuration
 * ================================================================== */

function stripe_configured(): bool {
    return trim((string) env('STRIPE_SECRET_KEY', '')) !== '';
}

function paydunya_configured(): bool {
    return trim((string) env('PAYDUNYA_MASTER_KEY', '')) !== '' && trim((string) env('PAYDUNYA_PRIVATE_KEY', '')) !== '' && trim((string) env('PAYDUNYA_TOKEN', '')) !== '';
}

function stripe_test_mode(): bool {
    return str_starts_with(trim((string) env('STRIPE_SECRET_KEY', '')), 'sk_test_');
}

function paydunya_test_mode(): bool {
    return strtolower((string) env('PAYDUNYA_MODE', 'test')) !== 'live';
}

/** Prestataires : libellés, état de configuration et d'activation. */
function pay_providers(): array {
    return [
        'stripe' => [
            'name' => 'Stripe', 'label' => (string) setting('pay_stripe_label'), 'desc' => (string) setting('pay_stripe_desc'),
            'configured' => stripe_configured(), 'enabled' => setting_bool('pay_stripe_enabled'), 'test' => stripe_test_mode(),
            'method' => 'Carte bancaire (Stripe)', 'icon' => 'shield',
        ],
        'paydunya' => [
            'name' => 'PayDunya', 'label' => (string) setting('pay_paydunya_label'), 'desc' => (string) setting('pay_paydunya_desc'),
            'configured' => paydunya_configured(), 'enabled' => setting_bool('pay_paydunya_enabled'), 'test' => paydunya_test_mode(),
            'method' => 'Mobile Money (PayDunya)', 'icon' => 'phone',
        ],
    ];
}

/** Prestataires réellement utilisables (activés + clés présentes). */
function pay_ready_providers(): array {
    return array_filter(pay_providers(), fn($p) => $p['configured'] && $p['enabled']);
}

/** Moyens proposés sur un lien donné. */
function pay_link_methods(array $link): array {
    $wanted = array_filter(array_map('trim', explode(',', (string) $link['methods'])));
    return array_intersect_key(pay_ready_providers(), array_flip($wanted));
}

function pay_xof_rate(): float {
    $r = (float) str_replace(',', '.', (string) setting('billing_xof_rate', '655.957'));
    return $r > 0 ? $r : 655.957;
}

/** Montant débité chez un prestataire : PayDunya ne débite qu'en FCFA. */
function pay_charge_amount(string $provider, float $amount, string $currency): array {
    if ($provider === 'paydunya' && $currency !== 'XOF') {
        return [round($amount * pay_xof_rate()), 'XOF'];
    }
    return [crm_round($amount, $currency), $currency];
}

/* ==================================================================
 * Liens de paiement
 * ================================================================== */

function pay_link(int $id): ?array {
    $s = db()->prepare('SELECT * FROM pay_links WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function pay_link_by_token(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) return null;
    $s = db()->prepare('SELECT * FROM pay_links WHERE token = ?');
    $s->execute([$token]);
    return $s->fetch() ?: null;
}

function pay_link_url(array $link): string {
    return absolute_url('payer/' . $link['token'], APP_DEFAULT_LANG);
}

/** Facture rattachée à un lien (le cas échéant). */
function pay_link_doc(array $link): ?array {
    return $link['document_id'] ? crm_doc((int) $link['document_id']) : null;
}

/**
 * Montant à régler : pour une facture, jamais plus que le reste à payer.
 */
function pay_link_due(array $link): float {
    $amount = (float) $link['amount'];
    if ($doc = pay_link_doc($link)) {
        $balance = crm_balance($doc);
        $amount = $amount > 0 ? min($amount, $balance) : $balance;
    }
    return max(0, crm_round($amount, $link['currency']));
}

/** Statut affiché : [code, libellé, ton]. */
function pay_link_state(array $link): array {
    if ($link['status'] === 'cancelled') return ['cancelled', 'Annulé', 'muted'];
    if ($link['status'] === 'paid') return ['paid', 'Payé', 'success'];
    if ($doc = pay_link_doc($link)) {
        if ($doc['status'] === 'paid') return ['paid', 'Facture réglée', 'success'];
        if ($doc['status'] === 'cancelled') return ['cancelled', 'Facture annulée', 'muted'];
    }
    if ($link['expires_at'] && $link['expires_at'] < date('Y-m-d')) return ['expired', 'Expiré', 'warning'];
    if ($link['reusable'] && $link['payments_count'] > 0) return ['active', 'Actif · ' . (int) $link['payments_count'] . ' paiement(s)', 'info'];
    return ['active', 'En attente', 'info'];
}

/**
 * Crée un lien de paiement. $d : title, amount, currency, document_id, client_id, description,
 * amount_mode, min_amount, methods, reusable, payer_*, expires_at.
 */
function pay_create_link(array $d): int {
    $days = (int) setting('pay_link_validity_days', 30);
    $data = [
        'token' => bin2hex(random_bytes(20)),
        'document_id' => $d['document_id'] ?? null,
        'client_id' => $d['client_id'] ?? null,
        'title' => mb_substr(trim((string) ($d['title'] ?? 'Paiement')), 0, 190),
        'description' => $d['description'] ?? null,
        'amount' => (float) ($d['amount'] ?? 0),
        'currency' => ($d['currency'] ?? 'EUR') === 'XOF' ? 'XOF' : 'EUR',
        'amount_mode' => ($d['amount_mode'] ?? 'fixed') === 'free' ? 'free' : 'fixed',
        'min_amount' => isset($d['min_amount']) && $d['min_amount'] !== '' ? (float) $d['min_amount'] : null,
        'methods' => $d['methods'] ?? 'stripe,paydunya',
        'reusable' => !empty($d['reusable']) ? 1 : 0,
        'payer_name' => $d['payer_name'] ?? null,
        'payer_email' => $d['payer_email'] ?? null,
        'payer_phone' => $d['payer_phone'] ?? null,
        'expires_at' => array_key_exists('expires_at', $d) ? ($d['expires_at'] ?: null) : ($days > 0 ? date('Y-m-d', strtotime('+' . $days . ' days')) : null),
        'created_by' => $_SESSION['admin_id'] ?? null,
    ];
    db()->prepare('INSERT INTO pay_links (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')')->execute(array_values($data));
    return (int) db()->lastInsertId();
}

/**
 * Lien de paiement d'une facture (réutilise un lien actif existant, sinon en crée un).
 */
function pay_invoice_link(array $doc, bool $create = true): ?array {
    if ($doc['doc_type'] !== 'invoice' || !in_array($doc['status'], ['issued', 'partial'], true) || crm_balance($doc) <= 0) return null;
    try {
        $s = db()->prepare('SELECT * FROM pay_links WHERE document_id = ? AND status = "active" AND amount_mode = "fixed" AND amount = 0 AND (expires_at IS NULL OR expires_at >= CURDATE()) ORDER BY id DESC LIMIT 1');
        $s->execute([$doc['id']]);
        if ($link = $s->fetch()) return $link;
        if (!$create || !pay_ready_providers()) return null;
        $client = crm_doc_client($doc);
        $id = pay_create_link([
            'document_id' => $doc['id'], 'client_id' => $doc['client_id'], 'title' => 'Facture ' . $doc['number'],
            'description' => $doc['title'], 'amount' => 0, 'currency' => $doc['currency'],
            'payer_name' => $client['contact'] ?: $client['name'], 'payer_email' => $client['email'] ?? null, 'payer_phone' => $client['phone'] ?? null,
            'expires_at' => null,
        ]);
        return pay_link($id);
    } catch (Throwable $e) {
        error_log('NEOSEN: lien de paiement de facture impossible — ' . $e->getMessage());
        return null;
    }
}

/** URL de paiement d'une facture (variable {pay_link} des emails). */
function pay_invoice_link_url(array $doc): string {
    if (!setting_bool('pay_invoice_button')) return '';
    $link = pay_invoice_link($doc);
    return $link ? pay_link_url($link) : '';
}

/** Variables des messages (email, WhatsApp) d'un lien. */
function pay_link_vars(array $link): array {
    $due = $link['amount_mode'] === 'free' && !$link['document_id'] ? (float) $link['amount'] : pay_link_due($link);
    return [
        '{client}' => $link['payer_name'] ?: 'Madame, Monsieur',
        '{title}' => $link['title'],
        '{amount}' => $due > 0 ? money($due, $link['currency'], false) : 'montant libre',
        '{link}' => pay_link_url($link),
        '{expires}' => $link['expires_at'] ? "\nCe lien est valable jusqu'au " . date('d/m/Y', strtotime($link['expires_at'])) . '.' : '',
        '{company}' => site_name(),
    ];
}

function pay_whatsapp_url(array $link): string {
    $msg = strtr((string) setting('pay_whatsapp_message'), pay_link_vars($link));
    $phone = preg_replace('/\D/', '', (string) $link['payer_phone']);
    return 'https://wa.me/' . $phone . '?text=' . rawurlencode($msg);
}

/* ==================================================================
 * Appels HTTP
 * ================================================================== */

function pay_http(string $method, string $url, array $headers = [], ?string $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException('connexion impossible (' . $err . ')');
    return [$code, json_decode((string) $raw, true), (string) $raw];
}

/* ---------------------------------------------------------- Stripe */

function stripe_request(string $method, string $path, array $params = []): array {
    [$code, $json] = pay_http($method, 'https://api.stripe.com/v1/' . ltrim($path, '/'),
        ['Authorization: Bearer ' . trim((string) env('STRIPE_SECRET_KEY')), 'Content-Type: application/x-www-form-urlencoded', 'Stripe-Version: 2024-06-20'],
        $method === 'GET' ? null : http_build_query($params));
    if ($code >= 400 || !is_array($json)) {
        throw new RuntimeException('Stripe : ' . ($json['error']['message'] ?? 'réponse inattendue (HTTP ' . $code . ')'));
    }
    return $json;
}

function stripe_checkout(array $link, array $tx): array {
    $minor = $tx['charged_currency'] === 'XOF' ? (int) round($tx['charged_amount']) : (int) round($tx['charged_amount'] * 100);
    $base = pay_link_url($link);
    $params = [
        'mode' => 'payment',
        'locale' => 'fr',
        'client_reference_id' => 'tx-' . $tx['id'],
        'success_url' => $base . '/retour?tx=' . $tx['id'] . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $base . '?annule=' . $tx['id'],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => strtolower($tx['charged_currency']),
                'unit_amount' => $minor,
                'product_data' => array_filter(['name' => mb_substr($link['title'], 0, 250), 'description' => $link['description'] ? mb_substr(strip_tags($link['description']), 0, 300) : null]),
            ],
        ]],
        'metadata' => ['link' => $link['token'], 'tx' => (string) $tx['id']],
        'payment_intent_data' => ['description' => mb_substr(site_name() . ' — ' . $link['title'], 0, 300), 'metadata' => ['link' => $link['token'], 'tx' => (string) $tx['id']]],
    ];
    if ($tx['payer_email']) $params['customer_email'] = $tx['payer_email'];
    $session = stripe_request('POST', 'checkout/sessions', $params);
    return ['ref' => $session['id'], 'url' => $session['url']];
}

/** Vérifie une session Stripe. Retourne ['paid' => bool, 'payment' => ref] ou null. */
function stripe_verify(string $sessionId): array {
    $s = stripe_request('GET', 'checkout/sessions/' . rawurlencode($sessionId));
    return ['paid' => ($s['payment_status'] ?? '') === 'paid', 'status' => $s['status'] ?? '', 'payment' => $s['payment_intent'] ?? $sessionId, 'raw' => $s];
}

/** Vérifie la signature d'un webhook Stripe (en-tête Stripe-Signature). */
function stripe_webhook_valid(string $payload, string $header, string $secret, int $tolerance = 300): bool {
    $parts = [];
    foreach (explode(',', $header) as $kv) {
        [$k, $v] = array_pad(explode('=', trim($kv), 2), 2, '');
        $parts[$k][] = $v;
    }
    $t = (int) ($parts['t'][0] ?? 0);
    if (!$t || abs(time() - $t) > $tolerance) return false;
    $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
    foreach ($parts['v1'] ?? [] as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}

/* -------------------------------------------------------- PayDunya */

function paydunya_api(string $path): string {
    return 'https://app.paydunya.com/' . (paydunya_test_mode() ? 'sandbox-api' : 'api') . '/v1/' . ltrim($path, '/');
}

function paydunya_headers(): array {
    return [
        'Content-Type: application/json',
        'PAYDUNYA-MASTER-KEY: ' . trim((string) env('PAYDUNYA_MASTER_KEY')),
        'PAYDUNYA-PRIVATE-KEY: ' . trim((string) env('PAYDUNYA_PRIVATE_KEY')),
        'PAYDUNYA-TOKEN: ' . trim((string) env('PAYDUNYA_TOKEN')),
    ];
}

function paydunya_checkout(array $link, array $tx): array {
    $base = pay_link_url($link);
    $amount = (int) round($tx['charged_amount']);
    $logo = (string) setting('billing_logo');
    $invoice = [
        'items' => ['item_0' => ['name' => mb_substr($link['title'], 0, 100), 'quantity' => 1, 'unit_price' => (string) $amount, 'total_price' => (string) $amount, 'description' => mb_substr((string) $link['description'], 0, 200)]],
        'total_amount' => $amount,
        'description' => mb_substr($link['title'] . ($tx['currency'] !== 'XOF' ? ' (' . money((float) $tx['amount'], $tx['currency'], false) . ')' : ''), 0, 250),
    ];
    if ($channels = setting_lines('pay_paydunya_channels')) $invoice['channels'] = array_values($channels);
    $body = [
        'invoice' => $invoice,
        'store' => array_filter([
            'name' => site_name(), 'tagline' => (string) setting('site_tagline'), 'phone' => (string) setting('contact_phone'),
            'postal_address' => (string) setting('company_address'), 'website_url' => absolute_url('', APP_DEFAULT_LANG),
            'logo_url' => $logo && preg_match('/\.(png|jpe?g)$/i', $logo) ? media_url($logo, true) : null,
        ]),
        'custom_data' => ['link' => $link['token'], 'tx' => (string) $tx['id']],
        'actions' => [
            'cancel_url' => $base . '?annule=' . $tx['id'],
            'return_url' => $base . '/retour?tx=' . $tx['id'],
            'callback_url' => absolute_url('', APP_DEFAULT_LANG) . 'api/paydunya-ipn.php',
        ],
    ];
    [$code, $json] = pay_http('POST', paydunya_api('checkout-invoice/create'), paydunya_headers(), json_encode($body, JSON_UNESCAPED_UNICODE));
    if (($json['response_code'] ?? '') !== '00' || empty($json['token'])) {
        throw new RuntimeException('PayDunya : ' . ($json['response_text'] ?? $json['description'] ?? 'réponse inattendue (HTTP ' . $code . ')'));
    }
    return ['ref' => $json['token'], 'url' => $json['response_text']];
}

function paydunya_verify(string $token): array {
    [$code, $json] = pay_http('GET', paydunya_api('checkout-invoice/confirm/' . rawurlencode($token)), paydunya_headers());
    if (!is_array($json) || ($json['response_code'] ?? '') !== '00') {
        throw new RuntimeException('PayDunya : ' . ($json['response_text'] ?? 'vérification impossible (HTTP ' . $code . ')'));
    }
    $status = (string) ($json['status'] ?? '');
    return [
        'paid' => $status === 'completed', 'status' => $status, 'payment' => $json['receipt_identifier'] ?? ($json['invoice']['token'] ?? $token),
        'amount' => (float) ($json['invoice']['total_amount'] ?? 0), 'customer' => $json['customer'] ?? [], 'raw' => $json,
    ];
}

/* ==================================================================
 * Transactions
 * ================================================================== */

function pay_tx(int $id): ?array {
    $s = db()->prepare('SELECT * FROM pay_transactions WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/**
 * Démarre un paiement : crée la transaction et retourne l'URL du prestataire.
 */
function pay_start(array $link, string $provider, float $amount, array $payer): string {
    [$charged, $chargedCur] = pay_charge_amount($provider, $amount, $link['currency']);
    db()->prepare('INSERT INTO pay_transactions (link_id, provider, amount, currency, charged_amount, charged_currency, payer_name, payer_email, payer_phone, ip_hash) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([$link['id'], $provider, $amount, $link['currency'], $charged, $chargedCur, $payer['name'] ?: null, $payer['email'] ?: null, $payer['phone'] ?: null, hash('sha256', client_ip() . '|' . env('APP_KEY', 'neosen'))]);
    $tx = pay_tx((int) db()->lastInsertId());
    try {
        $res = $provider === 'stripe' ? stripe_checkout($link, $tx) : paydunya_checkout($link, $tx);
    } catch (Throwable $e) {
        db()->prepare('UPDATE pay_transactions SET status = "failed", raw = ? WHERE id = ?')->execute([$e->getMessage(), $tx['id']]);
        throw $e;
    }
    db()->prepare('UPDATE pay_transactions SET provider_ref = ? WHERE id = ?')->execute([$res['ref'], $tx['id']]);
    return $res['url'];
}

/**
 * Interroge le prestataire et valide la transaction si elle est payée. Retourne le statut final.
 */
function pay_verify_tx(array $tx): string {
    if ($tx['status'] === 'completed' || !$tx['provider_ref']) return $tx['status'];
    $r = $tx['provider'] === 'stripe' ? stripe_verify($tx['provider_ref']) : paydunya_verify($tx['provider_ref']);
    if ($r['paid']) {
        if ($tx['provider'] === 'paydunya' && $r['amount'] > 0 && $r['amount'] + 0.5 < (float) $tx['charged_amount']) {
            error_log('NEOSEN: montant PayDunya incohérent pour la transaction ' . $tx['id']);
            return $tx['status'];
        }
        $payer = [];
        if (!empty($r['customer'])) $payer = ['name' => $r['customer']['name'] ?? null, 'email' => $r['customer']['email'] ?? null, 'phone' => $r['customer']['phone'] ?? null];
        pay_complete((int) $tx['id'], (string) $r['payment'], $r['raw'], $payer);
        return 'completed';
    }
    if (in_array($r['status'], ['cancelled', 'failed', 'expired'], true) && $tx['status'] === 'pending') {
        db()->prepare('UPDATE pay_transactions SET status = ? WHERE id = ? AND status = "pending"')->execute([$r['status'] === 'cancelled' ? 'cancelled' : 'failed', $tx['id']]);
        return $r['status'];
    }
    return $tx['status'];
}

/**
 * Valide un paiement (idempotent) : paiement sur la facture, reçu, emails.
 */
function pay_complete(int $txId, string $providerPayment, $raw = null, array $payer = []): bool {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $s = $pdo->prepare('SELECT * FROM pay_transactions WHERE id = ? FOR UPDATE');
        $s->execute([$txId]);
        $tx = $s->fetch();
        if (!$tx || $tx['status'] === 'completed') {
            $pdo->commit();
            return false;
        }
        $link = pay_link((int) $tx['link_id']);
        $prov = pay_providers()[$tx['provider']] ?? ['method' => $tx['provider']];
        $name = $tx['payer_name'] ?: ($payer['name'] ?? null) ?: $link['payer_name'] ?: 'Client';
        $email = $tx['payer_email'] ?: ($payer['email'] ?? null) ?: $link['payer_email'];
        $phone = $tx['payer_phone'] ?: ($payer['phone'] ?? null) ?: $link['payer_phone'];
        $ref = $providerPayment . ($tx['charged_currency'] !== $tx['currency'] ? ' — débité ' . money((float) $tx['charged_amount'], $tx['charged_currency'], false) : '');

        $paymentId = null;
        $doc = $link['document_id'] ? crm_doc((int) $link['document_id']) : null;
        if ($doc && in_array($doc['status'], ['issued', 'partial', 'paid'], true)) {
            $pdo->prepare('INSERT INTO crm_payments (document_id, amount, paid_on, method, reference, notes) VALUES (?, ?, CURDATE(), ?, ?, ?)')
                ->execute([$doc['id'], $tx['amount'], $prov['method'], mb_substr($ref, 0, 120), 'Paiement en ligne — lien ' . substr($link['token'], 0, 8)]);
            $paymentId = (int) $pdo->lastInsertId();
        }
        $receiptId = receipt_create([
            'client_id' => $link['client_id'] ?: ($doc['client_id'] ?? null), 'document_id' => $doc['id'] ?? null, 'payment_id' => $paymentId,
            'link_id' => $link['id'], 'transaction_id' => $tx['id'], 'payer_name' => $name, 'payer_email' => $email, 'payer_phone' => $phone,
            'amount' => $tx['amount'], 'currency' => $tx['currency'], 'paid_on' => date('Y-m-d'), 'method' => $prov['method'],
            'reference' => $ref, 'description' => $link['title'],
        ]);
        $pdo->prepare('UPDATE pay_transactions SET status = "completed", completed_at = NOW(), provider_payment = ?, receipt_id = ?, payment_id = ?, payer_name = ?, payer_email = ?, payer_phone = ?, raw = ? WHERE id = ?')
            ->execute([mb_substr($providerPayment, 0, 190), $receiptId, $paymentId, $name, $email, $phone, $raw ? json_encode($raw) : null, $txId]);
        $pdo->prepare('UPDATE pay_links SET paid_total = paid_total + ?, payments_count = payments_count + 1, paid_at = NOW(), status = IF(reusable = 1 OR (document_id IS NOT NULL AND amount = 0), status, "paid") WHERE id = ?')
            ->execute([$tx['amount'], $link['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('NEOSEN: validation du paiement ' . $txId . ' impossible — ' . $e->getMessage());
        throw $e;
    }

    // Hors transaction : statut de la facture, historique, emails
    if ($doc) {
        crm_refresh_invoice((int) $doc['id']);
        crm_log((int) $doc['client_id'], 'Paiement en ligne reçu : ' . money((float) $tx['amount'], $tx['currency'], false) . ' (' . $doc['number'] . ', ' . $prov['method'] . ')', 'system', (int) $doc['id']);
    }
    $receipt = receipt_get($receiptId);
    if ($receipt && $receipt['payer_email'] && setting_bool('receipt_auto_send')) {
        receipt_send($receipt, $receipt['payer_email']);
    }
    if (setting_bool('pay_notify_admin')) {
        require_once ROOT_PATH . '/includes/mailer.php';
        $admin = absolute_url('', APP_DEFAULT_LANG) . 'admin/';
        send_mail((string) setting('notification_email'), 'Paiement reçu : ' . money((float) $tx['amount'], $tx['currency'], false) . ' — ' . $link['title'],
            mail_layout('Nouveau paiement en ligne 💳', '<p><strong>' . e($name) . '</strong> vient de régler <strong>' . e(money((float) $tx['amount'], $tx['currency'])) . '</strong> pour « ' . e($link['title']) . ' » (' . e($prov['method']) . ').</p>'
                . '<p style="color:#6b7280;font-size:13px">Référence : ' . e($ref) . '<br>Reçu : ' . e($receipt['number'] ?? '') . '</p>'
                . '<p><a href="' . e($admin . ($doc ? 'document.php?id=' . $doc['id'] : 'payment-links.php?id=' . $link['id'])) . '">Voir dans l\'administration</a></p>'));
    }
    return true;
}

/* ==================================================================
 * Reçus
 * ================================================================== */

function receipt_get(int $id): ?array {
    $s = db()->prepare('SELECT * FROM crm_receipts WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function receipt_by_token(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) return null;
    $s = db()->prepare('SELECT * FROM crm_receipts WHERE token = ?');
    $s->execute([$token]);
    return $s->fetch() ?: null;
}

function receipt_url(array $r): string {
    return absolute_url('recu/' . $r['token'], APP_DEFAULT_LANG);
}

/** Crée un reçu numéroté (REC-2026-0001). Utilisable dans une transaction en cours. */
function receipt_create(array $d): int {
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        $date = $d['paid_on'] ?? date('Y-m-d');
        $number = crm_next_number('receipt', (int) substr($date, 0, 4));
        $pdo->prepare('INSERT INTO crm_receipts (number, token, client_id, document_id, payment_id, link_id, transaction_id, payer_name, payer_email, payer_phone, amount, currency, paid_on, method, reference, description, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$number, bin2hex(random_bytes(20)), $d['client_id'] ?? null, $d['document_id'] ?? null, $d['payment_id'] ?? null, $d['link_id'] ?? null, $d['transaction_id'] ?? null,
                mb_substr((string) $d['payer_name'], 0, 190), $d['payer_email'] ?? null, $d['payer_phone'] ?? null, (float) $d['amount'], ($d['currency'] ?? 'EUR') === 'XOF' ? 'XOF' : 'EUR',
                $date, $d['method'] ?? null, isset($d['reference']) ? mb_substr((string) $d['reference'], 0, 190) : null, isset($d['description']) ? mb_substr((string) $d['description'], 0, 255) : null,
                $d['notes'] ?? null, $_SESSION['admin_id'] ?? null]);
        $id = (int) $pdo->lastInsertId();
        if ($own) $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($own) $pdo->rollBack();
        throw $e;
    }
}

/** PDF d'un reçu. */
function receipt_pdf(array $r): string {
    require_once ROOT_PATH . '/includes/crm_pdf.php';
    $seller = crm_seller_info();
    $primary = hex_rgb((string) setting('color_primary', '#3B4BFF'));
    $dark = [15, 20, 36];
    $muted = [110, 117, 135];
    $doc = $r['document_id'] ? crm_doc((int) $r['document_id']) : null;

    $pdf = new NeosenPDF('P', 'mm', 'A4');
    $pdf->primary = $primary;
    $pdf->SetTitle(pdf_txt(setting('receipt_title') . ' ' . $r['number']));
    $pdf->SetAuthor(pdf_txt($seller['name']));
    $pdf->footerText = pdf_txt(implode(' - ', array_filter([
        $seller['name'] . ($seller['legal_form'] ? ', ' . $seller['legal_form'] : ''), $seller['address'],
        $seller['siret'] ? 'SIRET ' . $seller['siret'] : '', $seller['rcs'], $seller['vat'] ? 'TVA ' . $seller['vat'] : '',
    ])));
    $pdf->AliasNbPages();
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 24);
    $pdf->AddPage();

    // Émetteur
    $y = 27;
    $logoOk = false;
    if ($logo = pdf_logo_path()) {
        try { $pdf->Image($logo, 15, 14, 0, 11); $logoOk = true; $y = 29; } catch (Throwable $e) {}
    }
    if (!$logoOk) {
        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->SetTextColor(...$dark);
        $pdf->SetXY(15, 14);
        $pdf->Cell(90, 10, pdf_txt($seller['brand']));
    }
    $pdf->SetXY(15, $y);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(...$dark);
    $pdf->Cell(90, 4.5, pdf_txt($seller['name']), 0, 2);
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(...$muted);
    foreach (array_filter([$seller['address'], $seller['email'], $seller['phone'], $seller['domain']]) as $info) {
        $pdf->Cell(90, 4.2, pdf_txt($info), 0, 2);
    }

    // Titre
    $pdf->SetXY(100, 14);
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->SetTextColor(...$primary);
    $pdf->Cell(95, 10, pdf_txt(mb_strtoupper((string) setting('receipt_title', 'Reçu de paiement'))), 0, 2, 'R');
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(...$dark);
    $pdf->Cell(95, 6, pdf_txt('N° ' . $r['number']), 0, 2, 'R');
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(...$muted);
    $pdf->Cell(95, 4.6, pdf_txt('Date du paiement : ' . date('d/m/Y', strtotime($r['paid_on']))), 0, 2, 'R');

    // Bloc montant
    $by = 64;
    $pdf->SetFillColor(...$primary);
    $pdf->Rect(15, $by, 180, 30, 'F');
    $pdf->SetXY(22, $by + 6);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(110, 5, pdf_txt('Reçu de ' . $r['payer_name']), 0, 2);
    $pdf->SetFont('Helvetica', 'B', 22);
    $pdf->Cell(110, 12, pdf_txt(money((float) $r['amount'], $r['currency'], false)), 0, 2);
    $pdf->SetXY(120, $by + 9);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(68, 6, pdf_txt('PAYÉ'), 0, 2, 'R');
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->Cell(68, 5, pdf_txt((string) $r['method']), 0, 2, 'R');

    // Détails
    $rows = array_filter([
        ['Payé par', implode(' · ', array_filter([$r['payer_name'], $r['payer_email'], $r['payer_phone']]))],
        ['Objet', (string) $r['description']],
        ['Montant reçu', money((float) $r['amount'], $r['currency'], false) . ($r['currency'] === 'EUR' ? '  (soit ' . money(round((float) $r['amount'] * pay_xof_rate()), 'XOF', false) . ')' : '')],
        ['Moyen de paiement', (string) $r['method']],
        ['Référence de transaction', (string) $r['reference']],
        $doc ? ['Facture concernée', $doc['number'] . ' du ' . date('d/m/Y', strtotime($doc['issue_date'])) . ' — total ' . money((float) $doc['total'], $doc['currency'], false)] : null,
        $doc ? ['Reste à payer sur la facture', money(crm_balance($doc), $doc['currency'], false)] : null,
    ], fn($row) => $row && $row[1] !== '');
    $pdf->SetY($by + 40);
    foreach ($rows as [$k, $v]) {
        $yy = $pdf->GetY();
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(...$muted);
        $pdf->SetXY(15, $yy);
        $pdf->Cell(55, 7, pdf_txt($k));
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(...$dark);
        $pdf->SetXY(70, $yy + 1.2);
        $pdf->MultiCell(125, 4.8, pdf_txt($v));
        $pdf->SetY(max($pdf->GetY() + 1.2, $yy + 7));
        $pdf->SetDrawColor(230, 232, 240);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    }
    if ($r['notes']) {
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(70, 76, 92);
        $pdf->MultiCell(180, 4.8, pdf_txt($r['notes']));
    }
    $pdf->Ln(8);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(...$muted);
    $pdf->MultiCell(180, 4.2, pdf_txt((string) setting('receipt_footer')));
    $pdf->Ln(4);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(...$dark);
    $pdf->Cell(180, 5, pdf_txt('Merci pour votre confiance — ' . $seller['brand']), 0, 1);
    return $pdf->Output('S');
}

function receipt_filename(array $r): string {
    return 'recu-' . slugify($r['number']) . '.pdf';
}

function receipt_pdf_response(array $r, bool $download = false): void {
    while (ob_get_level() > 0) ob_end_clean();
    try {
        $bytes = receipt_pdf($r);
    } catch (Throwable $e) {
        error_log('NEOSEN: reçu PDF impossible — ' . $e->getMessage());
        http_response_code(500);
        exit('Le reçu n\'a pas pu être généré.');
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . receipt_filename($r) . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, no-cache');
    header('X-Robots-Tag: noindex');
    echo $bytes;
    exit;
}

/** Envoie un reçu par email (PDF joint). */
function receipt_send(array $r, string $to, ?string $subject = null, ?string $body = null): bool {
    require_once ROOT_PATH . '/includes/mailer.php';
    $vars = [
        '{client}' => $r['payer_name'], '{number}' => $r['number'], '{amount}' => money((float) $r['amount'], $r['currency'], false),
        '{method}' => (string) $r['method'], '{date}' => date('d/m/Y', strtotime($r['paid_on'])), '{company}' => site_name(),
    ];
    $subject = $subject ?? strtr((string) setting('receipt_email_subject'), $vars);
    $body = $body ?? strtr((string) setting('receipt_email_body'), $vars);
    $file = crm_storage_dir('tmp') . '/' . bin2hex(random_bytes(8)) . '.pdf';
    try {
        file_put_contents($file, receipt_pdf($r));
    } catch (Throwable $e) {
        error_log('NEOSEN: reçu non joint — ' . $e->getMessage());
        $file = '';
    }
    $ok = send_mail($to, $subject, mail_layout($subject, '<div style="font-size:15px;line-height:1.7">' . nl2br(e($body)) . '</div>'
        . '<p style="margin-top:24px"><a href="' . e(receipt_url($r)) . '" style="display:inline-block;background:' . e(setting('color_primary')) . ';color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none">Télécharger le reçu</a></p>'),
        ['text' => $body, 'reply_to' => [setting('contact_email'), site_name()], 'attachments' => $file ? [[$file, receipt_filename($r)]] : []]);
    if ($file) @unlink($file);
    if ($ok) db()->prepare('UPDATE crm_receipts SET sent_at = NOW() WHERE id = ?')->execute([$r['id']]);
    return $ok;
}
