<?php
/**
 * Gestion commerciale : clients / prospects, devis, factures, avoirs, paiements, relances.
 *
 * Règles appliquées (France) :
 *  - numérotation continue et chronologique, attribuée à l'émission (jamais réutilisée) ;
 *  - une facture émise n'est plus modifiable ni supprimable : on l'annule par un avoir ;
 *  - les coordonnées du client sont figées sur le document au moment de l'émission.
 */

/* ------------------------------------------------------------------
 * Libellés
 * ------------------------------------------------------------------ */

function crm_stages(): array {
    return [
        'new'         => ['Nouveau', '#6b7285'],
        'contacted'   => ['Contacté', '#3B4BFF'],
        'qualified'   => ['Qualifié', '#7c3aed'],
        'proposal'    => ['Devis envoyé', '#e19a00'],
        'negotiation' => ['Négociation', '#f97316'],
        'won'         => ['Gagné', '#12a150'],
        'lost'        => ['Perdu', '#e5484d'],
    ];
}

function crm_doc_types(): array {
    return [
        'quote'       => ['Devis', 'Devis', 'billing_quote_prefix'],
        'invoice'     => ['Facture', 'Factures', 'billing_invoice_prefix'],
        'credit_note' => ['Avoir', 'Avoirs', 'billing_credit_prefix'],
    ];
}

function crm_issued_message(string $type): string {
    return ['quote' => 'Devis émis.', 'invoice' => 'Facture émise.', 'credit_note' => 'Avoir émis.'][$type] ?? 'Document émis.';
}

function crm_doc_label(string $type): string {
    return crm_doc_types()[$type][0] ?? $type;
}

/**
 * Statut affiché (avec calcul « expiré » / « en retard »).
 */
function crm_doc_status(array $doc): array {
    $today = date('Y-m-d');
    $s = $doc['status'];
    if ($doc['doc_type'] === 'quote') {
        if ($s === 'sent' && $doc['due_date'] && $doc['due_date'] < $today) $s = 'expired';
        return [
            'draft' => ['Brouillon', 'muted'], 'sent' => ['Envoyé', 'info'], 'accepted' => ['Accepté', 'success'],
            'declined' => ['Refusé', 'danger'], 'expired' => ['Expiré', 'warning'], 'invoiced' => ['Facturé', 'success'],
        ][$s] ?? [$s, 'muted'];
    }
    if ($doc['doc_type'] === 'invoice' && in_array($s, ['issued', 'partial'], true) && $doc['due_date'] && $doc['due_date'] < $today) {
        return ['En retard', 'danger'];
    }
    return [
        'draft' => ['Brouillon', 'muted'], 'issued' => ['Émise', 'info'], 'partial' => ['Payée partiellement', 'warning'],
        'paid' => ['Payée', 'success'], 'cancelled' => ['Annulée', 'muted'],
    ][$s] ?? [$s, 'muted'];
}

function crm_activity_types(): array {
    return [
        'note' => ['Note', 'edit'], 'call' => ['Appel', 'phone'], 'email' => ['Email', 'mail'],
        'meeting' => ['Rendez-vous', 'users'], 'task' => ['Tâche', 'check-circle'], 'system' => ['Historique', 'clock'],
    ];
}

/* ------------------------------------------------------------------
 * Montants
 * ------------------------------------------------------------------ */

function currency_decimals(string $currency): int {
    return $currency === 'XOF' ? 0 : 2;
}

function money(float $amount, string $currency = 'EUR', bool $html = true): string {
    $sep = $html ? "\u{202F}" : ' ';
    $n = number_format($amount, currency_decimals($currency), ',', $sep);
    return $currency === 'XOF' ? $n . ' FCFA' : $n . ($html ? "\u{00A0}" : ' ') . '€';
}

function crm_round(float $v, string $currency): float {
    return round($v, currency_decimals($currency));
}

/**
 * Calcule les totaux d'un document à partir de ses lignes.
 * Retourne [lignes avec line_total, subtotal, discount_amount, tax_total, total, taxes par taux].
 */
function crm_compute(array $lines, float $discountPct, string $currency): array {
    $subtotal = 0.0;
    $byRate = [];
    foreach ($lines as &$l) {
        if (($l['line_type'] ?? 'item') === 'section') {
            $l['line_total'] = 0;
            continue;
        }
        $l['line_total'] = crm_round((float) $l['quantity'] * (float) $l['unit_price'] * (1 - (float) ($l['discount_pct'] ?? 0) / 100), $currency);
        $subtotal += $l['line_total'];
        $rate = (string) (float) $l['vat_rate'];
        $byRate[$rate] = ($byRate[$rate] ?? 0) + $l['line_total'];
    }
    unset($l);
    $factor = 1 - $discountPct / 100;
    $taxes = [];
    $taxTotal = 0.0;
    foreach ($byRate as $rate => $base) {
        $base = $base * $factor;
        $tax = crm_round($base * (float) $rate / 100, $currency);
        $taxes[$rate] = ['base' => crm_round($base, $currency), 'tax' => $tax];
        $taxTotal += $tax;
    }
    ksort($taxes, SORT_NUMERIC);
    $discount = crm_round($subtotal * $discountPct / 100, $currency);
    $net = $subtotal - $discount;
    return [
        'lines' => $lines,
        'subtotal' => crm_round($subtotal, $currency),
        'discount_amount' => $discount,
        'tax_total' => crm_round($taxTotal, $currency),
        'total' => crm_round($net + $taxTotal, $currency),
        'taxes' => $taxes,
    ];
}

/* ------------------------------------------------------------------
 * Accès aux données
 * ------------------------------------------------------------------ */

function crm_client(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM crm_clients WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function crm_client_name(array $c): string {
    $person = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
    return trim((string) ($c['company'] ?? '')) !== '' ? $c['company'] : ($person ?: 'Sans nom');
}

function crm_client_contact(array $c): string {
    return trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
}

/**
 * Coordonnées figées sur un document.
 */
function crm_client_snapshot(array $c): array {
    return [
        'name' => crm_client_name($c),
        'contact' => crm_client_contact($c),
        'email' => $c['email'], 'phone' => $c['phone'],
        'address' => $c['address'], 'postal_code' => $c['postal_code'], 'city' => $c['city'], 'country' => $c['country'],
        'siren' => $c['siren'], 'vat_number' => $c['vat_number'], 'other_id' => $c['other_id'],
    ];
}

function crm_doc(int $id): ?array {
    $stmt = db()->prepare('SELECT d.*, c.company, c.first_name, c.last_name, c.email AS client_email
                           FROM crm_documents d JOIN crm_clients c ON c.id = d.client_id WHERE d.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function crm_doc_by_token(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) return null;
    $stmt = db()->prepare('SELECT id FROM crm_documents WHERE public_token = ? AND status <> "draft"');
    $stmt->execute([$token]);
    $id = $stmt->fetchColumn();
    return $id ? crm_doc((int) $id) : null;
}

function crm_doc_lines(int $docId): array {
    $stmt = db()->prepare('SELECT * FROM crm_document_lines WHERE document_id = ? ORDER BY position, id');
    $stmt->execute([$docId]);
    return $stmt->fetchAll();
}

function crm_doc_payments(int $docId): array {
    $stmt = db()->prepare('SELECT * FROM crm_payments WHERE document_id = ? ORDER BY paid_on, id');
    $stmt->execute([$docId]);
    return $stmt->fetchAll();
}

/**
 * Coordonnées du client pour l'affichage : figées si émis, sinon fiche actuelle.
 */
function crm_doc_client(array $doc): array {
    if ($doc['client_snapshot']) {
        $snap = json_decode($doc['client_snapshot'], true);
        if (is_array($snap)) return $snap;
    }
    return crm_client_snapshot(crm_client((int) $doc['client_id']) ?? []);
}

/** Montant total des avoirs émis sur une facture. */
function crm_credited(int $invoiceId): float {
    $stmt = db()->prepare('SELECT COALESCE(SUM(total), 0) FROM crm_documents WHERE doc_type = "credit_note" AND source_id = ? AND status = "issued"');
    $stmt->execute([$invoiceId]);
    return (float) $stmt->fetchColumn();
}

/** Reste à payer d'une facture. */
function crm_balance(array $doc): float {
    if ($doc['doc_type'] !== 'invoice') return 0.0;
    return max(0, crm_round((float) $doc['total'] - (float) $doc['amount_paid'] - crm_credited((int) $doc['id']), $doc['currency']));
}

function crm_doc_title(array $doc): string {
    return crm_doc_label($doc['doc_type']) . ' ' . ($doc['number'] ?: '(brouillon)');
}

function crm_public_url(array $doc, bool $pdf = false): string {
    return $doc['public_token'] ? absolute_url('document/' . $doc['public_token'] . ($pdf ? '/pdf' : ''), APP_DEFAULT_LANG) : '';
}

/* ------------------------------------------------------------------
 * Écriture
 * ------------------------------------------------------------------ */

function crm_log(int $clientId, string $subject, string $type = 'system', ?int $docId = null, ?string $content = null): void {
    db()->prepare('INSERT INTO crm_activities (client_id, document_id, type, subject, content, admin_id, done_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$clientId, $docId, $type, mb_substr($subject, 0, 190), $content, $_SESSION['admin_id'] ?? null]);
}

/**
 * Enregistre les lignes d'un document et recalcule ses totaux.
 */
function crm_save_lines(int $docId, array $rawLines, float $discountPct, string $currency): void {
    $lines = [];
    foreach ($rawLines as $l) {
        $desc = trim((string) ($l['description'] ?? ''));
        if ($desc === '') continue;
        $lines[] = [
            'line_type' => ($l['line_type'] ?? 'item') === 'section' ? 'section' : 'item',
            'description' => mb_substr($desc, 0, 255),
            'details' => trim((string) ($l['details'] ?? '')) ?: null,
            'quantity' => (float) str_replace(',', '.', (string) ($l['quantity'] ?? 1)),
            'unit' => trim((string) ($l['unit'] ?? '')) ?: null,
            'unit_price' => (float) str_replace([',', ' ', "\u{202F}"], ['.', '', ''], (string) ($l['unit_price'] ?? 0)),
            'vat_rate' => (float) str_replace(',', '.', (string) ($l['vat_rate'] ?? 0)),
            'discount_pct' => (float) str_replace(',', '.', (string) ($l['discount_pct'] ?? 0)),
        ];
    }
    $calc = crm_compute($lines, $discountPct, $currency);
    db()->prepare('DELETE FROM crm_document_lines WHERE document_id = ?')->execute([$docId]);
    $ins = db()->prepare('INSERT INTO crm_document_lines (document_id, position, line_type, description, details, quantity, unit, unit_price, vat_rate, discount_pct, line_total) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($calc['lines'] as $i => $l) {
        $ins->execute([$docId, ($i + 1) * 10, $l['line_type'], $l['description'], $l['details'], $l['quantity'], $l['unit'], $l['unit_price'], $l['vat_rate'], $l['discount_pct'], $l['line_total']]);
    }
    db()->prepare('UPDATE crm_documents SET subtotal = ?, discount_amount = ?, tax_total = ?, total = ? WHERE id = ?')
        ->execute([$calc['subtotal'], $calc['discount_amount'], $calc['tax_total'], $calc['total'], $docId]);
}

/**
 * Numéro suivant (continu par type et par année), sous verrou.
 */
function crm_next_number(string $type, int $year): string {
    $pdo = db();
    $pdo->prepare('INSERT IGNORE INTO crm_sequences (doc_type, year, last_number) VALUES (?, ?, 0)')->execute([$type, $year]);
    $stmt = $pdo->prepare('SELECT last_number FROM crm_sequences WHERE doc_type = ? AND year = ? FOR UPDATE');
    $stmt->execute([$type, $year]);
    $n = (int) $stmt->fetchColumn() + 1;
    $pdo->prepare('UPDATE crm_sequences SET last_number = ? WHERE doc_type = ? AND year = ?')->execute([$n, $type, $year]);
    $prefix = trim((string) setting(crm_doc_types()[$type][2], strtoupper(substr($type, 0, 3))));
    return ($prefix !== '' ? $prefix . '-' : '') . $year . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
}

/**
 * Émet un brouillon : numéro définitif, date, coordonnées figées, lien public.
 */
function crm_issue(int $docId): ?string {
    $doc = crm_doc($docId);
    if (!$doc || $doc['status'] !== 'draft') return 'Ce document a déjà été émis.';
    if (!crm_doc_lines($docId)) return 'Ajoutez au moins une ligne avant d\'émettre le document.';
    $client = crm_client((int) $doc['client_id']);
    if ($doc['doc_type'] === 'invoice' && trim((string) ($client['address'] ?? '')) === '') {
        return 'L\'adresse du client est obligatoire sur une facture : complétez sa fiche.';
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $issue = $doc['issue_date'] ?: date('Y-m-d');
        // La date d'émission ne peut pas être antérieure à la dernière facture émise (chronologie)
        if ($doc['doc_type'] !== 'quote') {
            $last = $pdo->prepare('SELECT MAX(issue_date) FROM crm_documents WHERE doc_type = ? AND status <> "draft"');
            $last->execute([$doc['doc_type']]);
            $lastDate = $last->fetchColumn();
            if ($lastDate && $issue < $lastDate) $issue = date('Y-m-d');
        }
        $number = crm_next_number($doc['doc_type'], (int) substr($issue, 0, 4));
        $status = $doc['doc_type'] === 'quote' ? 'sent' : 'issued';
        $pdo->prepare('UPDATE crm_documents SET number = ?, status = ?, issue_date = ?, client_snapshot = ?, public_token = COALESCE(public_token, ?) WHERE id = ?')
            ->execute([$number, $status, $issue, json_encode(crm_client_snapshot($client), JSON_UNESCAPED_UNICODE), bin2hex(random_bytes(20)), $docId]);

        if ($doc['doc_type'] === 'credit_note' && $doc['source_id']) {
            crm_refresh_invoice((int) $doc['source_id']);
        }
        if ($doc['doc_type'] === 'invoice' && $client['kind'] === 'prospect') {
            $pdo->prepare('UPDATE crm_clients SET kind = "client", stage = "won" WHERE id = ?')->execute([$client['id']]);
        }
        if ($doc['doc_type'] === 'quote' && in_array($client['stage'], ['new', 'contacted', 'qualified'], true)) {
            $pdo->prepare('UPDATE crm_clients SET stage = "proposal" WHERE id = ?')->execute([$client['id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('NEOSEN: émission impossible — ' . $e->getMessage());
        return 'Erreur lors de l\'émission du document.';
    }
    crm_log((int) $doc['client_id'], crm_doc_label($doc['doc_type']) . ' ' . $number . ($doc['doc_type'] === 'invoice' ? ' émise' : ' émis') . ' — ' . money((float) $doc['total'], $doc['currency'], false), 'system', $docId);
    return null;
}

/**
 * Recalcule montant payé et statut d'une facture.
 */
function crm_refresh_invoice(int $invoiceId): void {
    $doc = crm_doc($invoiceId);
    if (!$doc || $doc['doc_type'] !== 'invoice' || $doc['status'] === 'draft') return;
    $stmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM crm_payments WHERE document_id = ?');
    $stmt->execute([$invoiceId]);
    $paid = crm_round((float) $stmt->fetchColumn(), $doc['currency']);
    $credited = crm_credited($invoiceId);
    $total = (float) $doc['total'];
    if ($credited >= $total - 0.005) {
        $status = 'cancelled';
    } elseif ($paid + $credited >= $total - 0.005) {
        $status = 'paid';
    } elseif ($paid > 0) {
        $status = 'partial';
    } else {
        $status = 'issued';
    }
    db()->prepare('UPDATE crm_documents SET amount_paid = ?, status = ?, paid_at = IF(? = "paid", COALESCE(paid_at, NOW()), NULL) WHERE id = ?')
        ->execute([$paid, $status, $status, $invoiceId]);
}

/**
 * Crée un nouveau document (brouillon) à partir d'un autre (devis → facture, facture → avoir, duplication).
 */
function crm_copy_document(int $sourceId, string $newType, array $override = []): int {
    $src = crm_doc($sourceId);
    $currency = $src['currency'];
    $data = array_merge([
        'doc_type' => $newType, 'status' => 'draft', 'client_id' => $src['client_id'], 'title' => $src['title'],
        'intro' => $src['intro'], 'notes' => null, 'issue_date' => date('Y-m-d'), 'service_date' => $src['service_date'],
        'currency' => $currency, 'discount_pct' => $src['discount_pct'], 'vat_mention' => $src['vat_mention'],
        'source_id' => $sourceId, 'created_by' => $_SESSION['admin_id'] ?? null, 'is_deposit' => 0,
    ], $override);
    if (!array_key_exists('due_date', $override)) {
        $data['due_date'] = crm_default_due($newType, (int) $src['client_id']);
    }
    $cols = implode(', ', array_keys($data));
    db()->prepare("INSERT INTO crm_documents ({$cols}) VALUES (" . implode(', ', array_fill(0, count($data), '?')) . ')')->execute(array_values($data));
    $newId = (int) db()->lastInsertId();
    crm_save_lines($newId, crm_doc_lines($sourceId), (float) $data['discount_pct'], $currency);
    return $newId;
}

/**
 * Échéance par défaut : validité (devis) ou délai de paiement (facture).
 */
function crm_default_due(string $type, int $clientId = 0): ?string {
    if ($type === 'quote') {
        return date('Y-m-d', strtotime('+' . max(1, (int) setting('billing_quote_validity', 30)) . ' days'));
    }
    if ($type === 'invoice') {
        $days = (int) setting('billing_payment_terms', 30);
        if ($clientId && ($c = crm_client($clientId)) && $c['payment_terms_days'] !== null) {
            $days = (int) $c['payment_terms_days'];
        }
        return date('Y-m-d', strtotime('+' . $days . ' days'));
    }
    return null;
}

/* ------------------------------------------------------------------
 * Emails : envoi des documents et relances
 * ------------------------------------------------------------------ */

function crm_template_vars(array $doc): array {
    $client = crm_doc_client($doc);
    $days = $doc['due_date'] ? (int) floor((time() - strtotime($doc['due_date'])) / 86400) : 0;
    return [
        '{client}' => $client['contact'] ?: $client['name'],
        '{company}' => site_name(),
        '{number}' => $doc['number'] ?: '',
        '{amount}' => money((float) $doc['total'], $doc['currency'], false),
        '{balance}' => money(crm_balance($doc), $doc['currency'], false),
        '{due_date}' => $doc['due_date'] ? date('d/m/Y', strtotime($doc['due_date'])) : '',
        '{days_late}' => (string) max(0, $days),
        '{link}' => crm_public_url($doc),
    ];
}

/**
 * Envoie un email au client, avec le PDF du document en pièce jointe.
 */
function crm_send_email(array $doc, string $to, string $subject, string $body): bool {
    require_once ROOT_PATH . '/includes/mailer.php';
    require_once ROOT_PATH . '/includes/crm_pdf.php';
    $tmp = tempnam(sys_get_temp_dir(), 'neo');
    $pdfPath = $tmp . '.pdf';
    @rename($tmp, $pdfPath);
    file_put_contents($pdfPath, crm_pdf((int) $doc['id']));
    $html = mail_layout($subject, '<div style="font-size:15px;line-height:1.7">' . nl2br(e($body)) . '</div>'
        . ($doc['public_token'] ? '<p style="margin-top:24px"><a href="' . e(crm_public_url($doc)) . '" style="display:inline-block;background:' . e(setting('color_primary')) . ';color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none">Consulter le document</a></p>' : ''));
    $ok = send_mail($to, $subject, $html, [
        'text' => $body,
        'reply_to' => [setting('contact_email'), site_name()],
        'attachments' => [[$pdfPath, crm_pdf_filename($doc)]],
    ]);
    @unlink($pdfPath);
    return $ok;
}

/**
 * Factures à relancer et devis sans réponse.
 */
function crm_due_reminders(): array {
    $delays = array_map('intval', array_filter(array_map('trim', explode(',', (string) setting('billing_reminder_delays', '3, 15, 30'))), 'strlen'));
    $invoices = db()->query('SELECT d.*, c.company, c.first_name, c.last_name, c.email AS client_email FROM crm_documents d JOIN crm_clients c ON c.id = d.client_id
                             WHERE d.doc_type = "invoice" AND d.status IN ("issued","partial") AND d.due_date < CURDATE() ORDER BY d.due_date')->fetchAll();
    foreach ($invoices as &$inv) {
        $inv['days_late'] = (int) floor((time() - strtotime($inv['due_date'])) / 86400);
        $level = 0;
        foreach ($delays as $i => $d) {
            if ($inv['days_late'] >= $d) $level = $i + 1;
        }
        $inv['suggested_level'] = min(3, max(1, $level, (int) $inv['reminder_count'] + 1));
        $inv['is_due'] = $level > (int) $inv['reminder_count'];
    }
    unset($inv);
    $days = max(1, (int) setting('billing_quote_followup_days', 7));
    $stmt = db()->prepare('SELECT d.*, c.company, c.first_name, c.last_name, c.email AS client_email FROM crm_documents d JOIN crm_clients c ON c.id = d.client_id
                           WHERE d.doc_type = "quote" AND d.status = "sent" AND (d.due_date IS NULL OR d.due_date >= CURDATE())
                           AND COALESCE(d.last_reminder_at, d.sent_at, d.issue_date) <= (NOW() - INTERVAL ? DAY) ORDER BY d.issue_date');
    $stmt->execute([$days]);
    return ['invoices' => $invoices, 'quotes' => $stmt->fetchAll()];
}

function crm_reminder_template(array $doc, int $level): array {
    $key = $doc['doc_type'] === 'quote' ? 'billing_quote_followup' : 'billing_reminder_' . max(1, min(3, $level));
    $vars = crm_template_vars($doc);
    return [strtr((string) setting($key . '_subject'), $vars), strtr((string) setting($key . '_body'), $vars)];
}

function crm_record_reminder(array $doc, int $level, string $to, string $subject, string $body, bool $ok): void {
    db()->prepare('INSERT INTO crm_reminders (document_id, level, recipient, subject, body, sent_ok, admin_id) VALUES (?,?,?,?,?,?,?)')
        ->execute([$doc['id'], $level, $to, $subject, $body, $ok ? 1 : 0, $_SESSION['admin_id'] ?? null]);
    db()->prepare('UPDATE crm_documents SET last_reminder_at = NOW(), reminder_count = GREATEST(reminder_count, ?) WHERE id = ?')->execute([$level, $doc['id']]);
    crm_log((int) $doc['client_id'], 'Relance ' . ($doc['doc_type'] === 'quote' ? 'devis' : 'n°' . $level) . ' — ' . crm_doc_title($doc) . ($to ? ' (' . $to . ')' : ' (hors email)'), 'email', (int) $doc['id']);
}

/* ------------------------------------------------------------------
 * Statistiques
 * ------------------------------------------------------------------ */

/**
 * Chiffre d'affaires HT facturé (factures émises − avoirs) par devise sur une période.
 */
function crm_revenue(string $from, string $to): array {
    $stmt = db()->prepare('SELECT currency, SUM(CASE WHEN doc_type = "invoice" THEN subtotal - discount_amount ELSE -(subtotal - discount_amount) END) AS ht
                           FROM crm_documents WHERE doc_type IN ("invoice","credit_note") AND status <> "draft" AND issue_date BETWEEN ? AND ? GROUP BY currency');
    $stmt->execute([$from, $to]);
    return array_map('floatval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
}

function crm_money_list(array $byCurrency): string {
    if (!$byCurrency) return money(0, (string) setting('billing_currency', 'EUR'));
    $out = [];
    foreach ($byCurrency as $cur => $v) $out[] = money((float) $v, $cur);
    return implode(' · ', $out);
}
