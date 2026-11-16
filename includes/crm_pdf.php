<?php
/**
 * Génération PDF des devis, factures et avoirs (bibliothèque FPDF dans /utilitaires/FPDF).
 */

require_once UTIL_PATH . '/FPDF/fpdf.php';

/** Conversion UTF-8 → Windows-1252 (encodage des polices standard PDF, € inclus). */
function pdf_txt(?string $s): string {
    $s = str_replace(["\u{202F}", "\u{00A0}", '’', '–', '—', '…', 'œ', 'Œ'], [' ', ' ', "'", '-', '-', '...', 'oe', 'OE'], (string) $s);
    $out = @iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
    return $out === false ? mb_convert_encoding($s, 'Windows-1252', 'UTF-8') : $out;
}

function pdf_money(float $v, string $cur): string {
    return pdf_txt(money($v, $cur, false));
}

function hex_rgb(string $hex): array {
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/**
 * Chemin d'un logo utilisable par FPDF (PNG/JPG ; WebP converti à la volée).
 */
function pdf_logo_path(): ?string {
    foreach ([setting('billing_logo'), 'assets/images/logo-pdf.png'] as $path) {
        $full = ROOT_PATH . '/' . ltrim((string) $path, '/');
        if (!$path || !is_file($full)) continue;
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg'], true)) return $full;
        if ($ext === 'webp' && function_exists('imagecreatefromwebp') && ($img = @imagecreatefromwebp($full))) {
            $tmp = crm_storage_dir('cache') . '/logo-' . md5($full . filemtime($full)) . '.png';
            if (!is_file($tmp)) {
                imagesavealpha($img, true);
                @imagepng($img, $tmp);
            }
            if (is_file($tmp)) return $tmp;
        }
    }
    return null;
}

class NeosenPDF extends FPDF {
    public array $primary = [59, 75, 255];
    public string $footerText = '';

    function Footer(): void {
        $this->SetY(-16);
        $this->SetDrawColor(230, 232, 240);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(2);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(130, 136, 152);
        $this->MultiCell(150, 3.4, $this->footerText, 0, 'L');
        $this->SetXY(165, -14);
        $this->Cell(30, 4, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    /** Nombre de lignes qu'occupera un texte dans une MultiCell de largeur $w. */
    function NbLines(float $w, string $txt): int {
        $cw = $this->CurrentFont['cw'];
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] === "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c === ' ') $sep = $i;
            $l += $cw[$c] ?? 500;
            if ($l > $wmax) {
                if ($sep === -1) { if ($i === $j) $i++; } else { $i = $sep + 1; }
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}

/**
 * Retourne le contenu binaire du PDF d'un document.
 */
function crm_pdf(int $docId, bool $final = false): string {
    $doc = crm_doc($docId);
    $seller = crm_doc_seller($doc);
    $lines = crm_doc_lines($docId);
    $client = crm_doc_client($doc);
    $cur = $doc['currency'];
    $calc = crm_compute($lines, (float) $doc['discount_pct'], $cur);
    $isQuote = $doc['doc_type'] === 'quote';
    $isInvoice = $doc['doc_type'] === 'invoice';
    $primary = hex_rgb((string) setting('color_primary', '#3B4BFF'));
    $dark = [15, 20, 36];
    $muted = [110, 117, 135];

    $pdf = new NeosenPDF('P', 'mm', 'A4');
    $pdf->primary = $primary;
    $pdf->SetTitle(pdf_txt(crm_doc_title($doc)));
    $pdf->SetAuthor(pdf_txt($seller['name']));
    $pdf->SetCreator(pdf_txt($seller['brand']));
    $pdf->footerText = pdf_txt(implode(' - ', array_filter([
        $seller['name'] . ($seller['legal_form'] ? ', ' . $seller['legal_form'] : '') . ($seller['capital'] ? ' au capital de ' . $seller['capital'] : ''),
        $seller['address'],
        $seller['siret'] ? 'SIRET ' . $seller['siret'] : ($seller['siren'] ? 'SIREN ' . $seller['siren'] : ''),
        $seller['rcs'],
        $seller['vat'] ? 'TVA ' . $seller['vat'] : '',
    ])));
    $pdf->AliasNbPages();
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 24);
    $pdf->AddPage();

    /* ---------- En-tête : émetteur ---------- */
    $logo = pdf_logo_path();
    $logoOk = false;
    if ($logo) {
        try {
            $pdf->Image($logo, 15, 14, 0, 11);
            $logoOk = true;
        } catch (Throwable $e) {
            error_log('NEOSEN: logo PDF ignoré — ' . $e->getMessage());
        }
    }
    if ($logoOk) {
        $y = 29;
    } else {
        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->SetTextColor(...$dark);
        $pdf->SetXY(15, 14);
        $pdf->Cell(90, 10, pdf_txt($seller['brand']));
        $y = 27;
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

    /* ---------- Titre et références ---------- */
    $title = $doc['doc_type'] === 'credit_note' ? 'AVOIR' : mb_strtoupper(crm_doc_label($doc['doc_type']));
    $pdf->SetXY(110, 14);
    $pdf->SetFont('Helvetica', 'B', 22);
    $pdf->SetTextColor(...$primary);
    $pdf->Cell(85, 10, pdf_txt($title), 0, 2, 'R');
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(...$dark);
    $pdf->Cell(85, 6, pdf_txt($doc['number'] ? 'N° ' . $doc['number'] : 'BROUILLON'), 0, 2, 'R');
    $pdf->Ln(1);
    $meta = [['Date', $doc['issue_date'] ? date('d/m/Y', strtotime($doc['issue_date'])) : date('d/m/Y')]];
    if ($isQuote && $doc['due_date']) $meta[] = ['Valable jusqu\'au', date('d/m/Y', strtotime($doc['due_date']))];
    if ($isInvoice && $doc['due_date']) $meta[] = ['Échéance', date('d/m/Y', strtotime($doc['due_date']))];
    if ($doc['service_date']) $meta[] = ['Exécution', $doc['service_date']];
    $meta[] = ['Nature', crm_operation_types()[$doc['operation_type'] ?? 'services'] ?? 'Prestation de services'];
    if ($doc['source_id'] && ($src = crm_doc((int) $doc['source_id'])) && $src['number']) {
        $meta[] = [$src['doc_type'] === 'quote' ? 'Devis' : 'Facture d\'origine', $src['number']];
    }
    $pdf->SetFont('Helvetica', '', 8.5);
    foreach ($meta as [$k, $v]) {
        $pdf->SetX(110);
        $pdf->SetTextColor(...$muted);
        $pdf->Cell(45, 4.6, pdf_txt($k), 0, 0, 'R');
        $pdf->SetTextColor(...$dark);
        $pdf->Cell(40, 4.6, pdf_txt($v), 0, 1, 'R');
    }

    /* ---------- Destinataire ---------- */
    $by = max($pdf->GetY(), 58) + 6;
    $clientLines = array_filter([
        $client['contact'] && $client['contact'] !== $client['name'] ? 'À l\'attention de ' . $client['contact'] : '',
        $client['address'] ?? '',
        trim(($client['postal_code'] ?? '') . ' ' . ($client['city'] ?? '')),
        $client['country'] ?? '',
        !empty($client['siren']) ? 'SIREN ' . $client['siren'] : '',
        !empty($client['vat_number']) ? 'TVA ' . $client['vat_number'] : '',
        !empty($client['other_id']) ? $client['other_id'] : '',
    ]);
    $boxH = 12 + count($clientLines) * 4.4;
    $pdf->SetFillColor(246, 247, 251);
    $pdf->Rect(110, $by, 85, $boxH, 'F');
    $pdf->SetXY(114, $by + 3);
    $pdf->SetFont('Helvetica', '', 7.5);
    $pdf->SetTextColor(...$muted);
    $pdf->Cell(77, 4, pdf_txt($isQuote ? 'DEVIS POUR' : 'FACTURÉ À'), 0, 2);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(...$dark);
    $pdf->Cell(77, 5.5, pdf_txt($client['name']), 0, 2);
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(70, 76, 92);
    foreach ($clientLines as $cl) {
        $pdf->Cell(77, 4.4, pdf_txt($cl), 0, 2);
    }
    $pdf->SetY($by + $boxH + 8);

    /* ---------- Objet & introduction ---------- */
    if ($doc['title']) {
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(...$dark);
        $pdf->MultiCell(180, 6, pdf_txt('Objet : ' . $doc['title']));
        $pdf->Ln(1);
    }
    if ($doc['intro']) {
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(70, 76, 92);
        $pdf->MultiCell(180, 4.6, pdf_txt($doc['intro']));
        $pdf->Ln(2);
    }

    /* ---------- Tableau des lignes ---------- */
    $w = [92, 16, 26, 16, 30];
    $head = function () use ($pdf, $w, $primary) {
        $pdf->SetFillColor(...$primary);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 8);
        foreach ([['Désignation', 'L'], ['Qté', 'R'], ['PU HT', 'R'], ['TVA', 'R'], ['Total HT', 'R']] as $i => [$label, $align]) {
            $pdf->Cell($w[$i], 8, pdf_txt($label), 0, 0, $align, true);
        }
        $pdf->Ln();
    };
    $head();
    $fill = false;
    foreach ($calc['lines'] as $l) {
        if ($l['line_type'] === 'section') {
            if ($pdf->GetY() > 255) { $pdf->AddPage(); $head(); }
            $pdf->Ln(1);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetTextColor(...$primary);
            $pdf->Cell(180, 7, pdf_txt($l['description']), 'B', 1);
            continue;
        }
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $descTxt = pdf_txt($l['description']);
        $nDesc = $pdf->NbLines($w[0], $descTxt);
        $detailsTxt = $l['details'] ? pdf_txt($l['details']) : '';
        $pdf->SetFont('Helvetica', '', 7.8);
        $nDet = $detailsTxt !== '' ? $pdf->NbLines($w[0], $detailsTxt) : 0;
        $h = max($nDesc * 4.6 + $nDet * 3.8, (float) $l['discount_pct'] > 0 ? 8.6 : 0) + 3.4;
        if ($pdf->GetY() + $h > 270) { $pdf->AddPage(); $head(); }
        $x = $pdf->GetX(); $y = $pdf->GetY();
        if ($fill) { $pdf->SetFillColor(248, 249, 252); $pdf->Rect($x, $y, 180, $h, 'F'); }
        $pdf->SetXY($x, $y + 1.7);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(...$dark);
        $pdf->MultiCell($w[0], 4.6, $descTxt);
        if ($detailsTxt !== '') {
            $pdf->SetX($x);
            $pdf->SetFont('Helvetica', '', 7.8);
            $pdf->SetTextColor(...$muted);
            $pdf->MultiCell($w[0], 3.8, $detailsTxt);
        }
        $qty = rtrim(rtrim(number_format((float) $l['quantity'], 3, ',', ' '), '0'), ',');
        $cells = [
            $qty . ($l['unit'] ? ' ' . $l['unit'] : ''),
            money((float) $l['unit_price'], $cur, false),
            rtrim(rtrim(number_format((float) $l['vat_rate'], 2, ',', ''), '0'), ',') . ' %',
            money((float) $l['line_total'], $cur, false),
        ];
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(...$dark);
        $cx = $x + $w[0];
        foreach ($cells as $i => $val) {
            $pdf->SetXY($cx, $y + 1.7);
            $pdf->Cell($w[$i + 1], 4.6, pdf_txt($val), 0, 0, 'R');
            $cx += $w[$i + 1];
        }
        if ((float) $l['discount_pct'] > 0) {
            $pdf->SetXY($x + $w[0], $y + 6);
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->SetTextColor(...$muted);
            $pdf->Cell($w[1] + $w[2], 3.5, pdf_txt('remise ' . rtrim(rtrim(number_format((float) $l['discount_pct'], 2, ',', ''), '0'), ',') . ' %'), 0, 0, 'R');
        }
        $pdf->SetXY($x, $y + $h);
        $pdf->SetDrawColor(235, 237, 243);
        $pdf->Line($x, $y + $h, $x + 180, $y + $h);
        $fill = !$fill;
    }

    /* ---------- Totaux ---------- */
    $pdf->Ln(4);
    $rows = [['Total HT', $calc['subtotal']]];
    if ($calc['discount_amount'] > 0) {
        $rows[] = ['Remise ' . rtrim(rtrim(number_format((float) $doc['discount_pct'], 2, ',', ''), '0'), ',') . ' %', -$calc['discount_amount']];
        $rows[] = ['Total HT net', $calc['subtotal'] - $calc['discount_amount']];
    }
    foreach ($calc['taxes'] as $rate => $t) {
        if ((float) $rate > 0) $rows[] = ['TVA ' . str_replace('.', ',', (string) (float) $rate) . ' % (base ' . money($t['base'], $cur, false) . ')', $t['tax']];
    }
    if ($calc['tax_total'] == 0) $rows[] = ['TVA', 0];
    $needed = count($rows) * 6 + 30;
    if ($pdf->GetY() + $needed > 270) $pdf->AddPage();
    $ty = $pdf->GetY();
    foreach ($rows as [$label, $val]) {
        $pdf->SetX(105);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(...$muted);
        $pdf->Cell(60, 6, pdf_txt($label), 0, 0, 'R');
        $pdf->SetTextColor(...$dark);
        $pdf->Cell(30, 6, pdf_money((float) $val, $cur), 0, 1, 'R');
    }
    $pdf->SetX(105);
    $pdf->SetFillColor(...$primary);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 10.5);
    $pdf->Cell(60, 9, pdf_txt($doc['doc_type'] === 'credit_note' ? 'Total de l\'avoir TTC' : 'Total TTC'), 0, 0, 'R', true);
    $pdf->Cell(30, 9, pdf_money($calc['total'], $cur), 0, 1, 'R', true);
    $pdf->SetFont('Helvetica', '', 8.5);
    $extra = [];
    if ($cur === 'EUR' && (float) setting('billing_xof_rate') > 0) $extra[] = ['Soit environ', money($calc['total'] * (float) setting('billing_xof_rate'), 'XOF', false)];
    if ($cur === 'XOF' && (float) setting('billing_xof_rate') > 0) $extra[] = ['Soit environ', money($calc['total'] / (float) setting('billing_xof_rate'), 'EUR', false)];
    if ($isQuote && (float) $doc['deposit_pct'] > 0) $extra[] = ['Acompte à la commande (' . str_replace('.', ',', (string) (float) $doc['deposit_pct']) . ' %)', money($calc['total'] * (float) $doc['deposit_pct'] / 100, $cur, false)];
    // Le PDF archivé reproduit la facture telle qu'émise (sans les règlements ultérieurs)
    if ($isInvoice && !$final && (float) $doc['amount_paid'] > 0) $extra[] = ['Déjà réglé', '- ' . money((float) $doc['amount_paid'], $cur, false)];
    if ($isInvoice && !$final && ($cr = crm_credited((int) $doc['id'])) > 0) $extra[] = ['Avoirs', '- ' . money($cr, $cur, false)];
    if ($isInvoice && !$final && ((float) $doc['amount_paid'] > 0 || crm_credited((int) $doc['id']) > 0)) $extra[] = ['Reste à payer', money(crm_balance($doc), $cur, false)];
    foreach ($extra as [$k, $v]) {
        $pdf->SetX(105);
        $pdf->SetTextColor(...$muted);
        $pdf->Cell(60, 5.5, pdf_txt($k), 0, 0, 'R');
        $pdf->SetTextColor(...$dark);
        $pdf->Cell(30, 5.5, pdf_txt($v), 0, 1, 'R');
    }
    $endTotals = $pdf->GetY();

    /* ---------- Mentions, paiement, conditions ---------- */
    $pdf->SetY($ty);
    $pdf->SetX(15);
    $leftW = 85;
    if ($doc['vat_mention']) {
        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->SetTextColor(...$muted);
        $pdf->MultiCell($leftW, 4, pdf_txt($doc['vat_mention']));
        $pdf->Ln(2);
    }
    if ($isInvoice && ($seller['iban'] || setting('billing_payment_methods'))) {
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(...$dark);
        $pdf->Cell($leftW, 5, pdf_txt('Règlement'), 0, 2);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(70, 76, 92);
        $pay = array_filter([
            $seller['bank'] ? 'Banque : ' . $seller['bank'] : '',
            $seller['iban'] ? 'IBAN : ' . $seller['iban'] : '',
            $seller['bic'] ? 'BIC : ' . $seller['bic'] : '',
            'Référence à indiquer : ' . ($doc['number'] ?: ''),
        ]);
        foreach ($pay as $p) $pdf->Cell($leftW, 4.2, pdf_txt($p), 0, 2);
    }
    if ($isQuote && $doc['status'] !== 'accepted') {
        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(...$dark);
        $pdf->Cell($leftW, 5, pdf_txt('Bon pour accord'), 0, 2);
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetTextColor(...$muted);
        $pdf->MultiCell($leftW, 3.8, pdf_txt('Date, signature et cachet, précédés de la mention « Bon pour accord »'));
        $pdf->SetDrawColor(220, 223, 232);
        $pdf->Rect(15, $pdf->GetY() + 1, $leftW, 18);
        $pdf->SetY($pdf->GetY() + 20);
    } elseif ($isQuote && $doc['accepted_at']) {
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(18, 161, 80);
        $pdf->MultiCell($leftW, 4.4, pdf_txt('Accepté le ' . date('d/m/Y', strtotime($doc['accepted_at'])) . ($doc['accepted_by'] ? ' par ' . $doc['accepted_by'] : '')));
    }
    $pdf->SetY(max($pdf->GetY(), $endTotals) + 6);

    $terms = crm_doc_terms($doc);
    if ($terms !== '') {
        if ($pdf->GetY() > 245) $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(...$dark);
        $pdf->Cell(180, 5, pdf_txt($isQuote ? 'Conditions' : ($isInvoice ? 'Conditions de paiement' : 'Informations')), 0, 1);
        $pdf->SetFont('Helvetica', '', 7.8);
        $pdf->SetTextColor(...$muted);
        $pdf->MultiCell(180, 3.9, pdf_txt($terms));
    }
    return $pdf->Output('S');
}
