<?php
/**
 * Cartes de visite : le QR code imprimé pointe vers /c/{code}, dont le contenu reste modifiable.
 */

function card_themes(): array {
    return [
        'nuit'    => 'Nuit — fond sombre, accents dégradés',
        'clair'   => 'Clair — fond blanc, bande dégradée',
        'degrade' => 'Dégradé — recto en dégradé bleu-turquoise',
        'epure'     => 'Épuré — blanc, typographie minimaliste',
        'tech'      => 'Tech — fond sombre quadrillé, esprit code',
        'diagonale' => 'Diagonale — coupe en dégradé, moderne',
    ];
}

function card_by_code(string $code): ?array {
    $stmt = db()->prepare('SELECT * FROM cards WHERE code = ?');
    $stmt->execute([$code]);
    return $stmt->fetch() ?: null;
}

function card_by_id(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM cards WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Adresse imprimée dans le QR code (ne doit jamais changer une fois la carte imprimée). */
function card_url(array $card): string {
    return absolute_url('c/' . $card['code'], APP_DEFAULT_LANG);
}

/** Adresse lisible imprimée sous le QR code (sans https://). */
function card_short_url(array $card): string {
    return preg_replace('#^https?://(www\.)?#', '', rtrim(card_url($card), '/'));
}

function card_device(): string {
    $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (preg_match('/bot|crawl|spider|preview|facebookexternalhit|whatsapp|slurp|curl|wget|python/', $ua)) return 'bot';
    if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad')) return 'iOS';
    if (str_contains($ua, 'android')) return 'Android';
    return 'Ordinateur';
}

/** Enregistre un événement (scan, enregistrement du contact, clic…), robots exclus. */
function card_track(array $card, string $event): void {
    $device = card_device();
    if ($device === 'bot' && $event === 'scan') return;
    try {
        db()->prepare('INSERT INTO card_scans (card_id, event, campaign, device, ip_hash) VALUES (?, ?, ?, ?, ?)')
            ->execute([$card['id'], $event, $card['campaign'], $device, hash('sha256', client_ip() . '|' . env('APP_KEY', 'neosen'))]);
    } catch (Throwable $e) {
        error_log('NEOSEN: suivi de carte impossible — ' . $e->getMessage());
    }
}

/** Échappement vCard (RFC 6350). */
function vcard_escape(?string $v): string {
    return str_replace(["\\", "\n", ',', ';'], ['\\\\', '\\n', '\\,', '\;'], trim((string) $v));
}

/**
 * Fiche contact .vcf (vCard 3.0, compatible iPhone et Android), photo incluse si disponible.
 */
function card_vcard(array $card): string {
    $name = trim((string) $card['person_name']);
    $parts = preg_split('/\s+/', $name);
    $last = count($parts) > 1 ? array_pop($parts) : $name;
    $first = implode(' ', $parts);
    $lines = [
        'BEGIN:VCARD', 'VERSION:3.0',
        'N:' . vcard_escape($last) . ';' . vcard_escape($first) . ';;;',
        'FN:' . vcard_escape($name ?: ($card['company'] ?: site_name())),
    ];
    if ($card['company']) $lines[] = 'ORG:' . vcard_escape($card['company']);
    if ($card['job_title']) $lines[] = 'TITLE:' . vcard_escape($card['job_title']);
    if ($card['phone']) $lines[] = 'TEL;TYPE=CELL,VOICE:' . preg_replace('/[^\d+]/', '', $card['phone']);
    if ($card['whatsapp'] && preg_replace('/\D/', '', $card['whatsapp']) !== preg_replace('/\D/', '', (string) $card['phone'])) $lines[] = 'TEL;TYPE=WORK:' . preg_replace('/[^\d+]/', '', $card['whatsapp']);
    if ($card['email']) $lines[] = 'EMAIL;TYPE=INTERNET,WORK:' . vcard_escape($card['email']);
    $site = $card['website'] ?: setting('site_domain');
    if ($site) $lines[] = 'URL:' . (preg_match('#^https?://#', $site) ? $site : 'https://' . $site);
    if ($card['address']) $lines[] = 'ADR;TYPE=WORK:;;' . vcard_escape($card['address']) . ';;;;';
    if ($card['tagline']) $lines[] = 'NOTE:' . vcard_escape($card['tagline']);
    // Photo (JPEG redimensionné, encodé en base64)
    if ($card['photo'] && is_file($file = ROOT_PATH . '/' . $card['photo']) && function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring((string) file_get_contents($file));
        if ($img) {
            $w = imagesx($img); $h = imagesy($img); $s = min($w, $h);
            $thumb = imagecreatetruecolor(240, 240);
            imagecopyresampled($thumb, $img, 0, 0, (int) (($w - $s) / 2), (int) (($h - $s) / 2), 240, 240, $s, $s);
            ob_start(); imagejpeg($thumb, null, 82); $jpg = (string) ob_get_clean();
            $lines[] = 'PHOTO;ENCODING=b;TYPE=JPEG:' . base64_encode($jpg);
        }
    }
    $lines[] = 'END:VCARD';
    // Lignes de 75 caractères maximum (repli RFC)
    $out = '';
    foreach ($lines as $l) {
        $out .= rtrim(chunk_split($l, 74, "\r\n "), " \r\n") . "\r\n";
    }
    return $out;
}

/**
 * Statistiques d'une carte sur N jours.
 */
function card_stats(int $cardId, int $days = 30): array {
    $q = function (string $sql, array $p) { $s = db()->prepare($sql); $s->execute($p); return $s; };
    $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
    $events = $q('SELECT event, COUNT(*) n FROM card_scans WHERE card_id = ? AND created_at >= ? GROUP BY event', [$cardId, $since])->fetchAll(PDO::FETCH_KEY_PAIR);
    $unique = (int) $q('SELECT COUNT(DISTINCT ip_hash) FROM card_scans WHERE card_id = ? AND event = "scan" AND created_at >= ?', [$cardId, $since])->fetchColumn();
    $daily = $q('SELECT DATE(created_at) d, COUNT(*) n FROM card_scans WHERE card_id = ? AND event = "scan" AND created_at >= ? GROUP BY DATE(created_at)', [$cardId, $since])->fetchAll(PDO::FETCH_KEY_PAIR);
    $devices = $q('SELECT device, COUNT(*) n FROM card_scans WHERE card_id = ? AND event = "scan" AND created_at >= ? GROUP BY device ORDER BY n DESC', [$cardId, $since])->fetchAll(PDO::FETCH_KEY_PAIR);
    $campaigns = $q('SELECT COALESCE(campaign, "—") c, SUM(event = "scan") scans, SUM(event = "lead") leads FROM card_scans WHERE card_id = ? GROUP BY campaign ORDER BY MAX(created_at) DESC', [$cardId])->fetchAll();
    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $series[$d] = (int) ($daily[$d] ?? 0);
    }
    $total = (int) $q('SELECT COUNT(*) FROM card_scans WHERE card_id = ? AND event = "scan"', [$cardId])->fetchColumn();
    return ['events' => array_map('intval', $events), 'unique' => $unique, 'series' => $series, 'devices' => array_map('intval', $devices), 'campaigns' => $campaigns, 'total_scans' => $total];
}
