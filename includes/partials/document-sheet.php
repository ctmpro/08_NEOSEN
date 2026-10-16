<?php
/**
 * Aperçu HTML fidèle d'un devis / d'une facture / d'un avoir (même contenu que le PDF).
 * Utilisé dans l'administration et sur la page client. Variable : $doc
 */
$seller = crm_doc_seller($doc);
$client = crm_doc_client($doc);
$cur = $doc['currency'];
$calc = crm_compute(crm_doc_lines((int) $doc['id']), (float) $doc['discount_pct'], $cur);
$isQuote = $doc['doc_type'] === 'quote';
$isInvoice = $doc['doc_type'] === 'invoice';
$title = $doc['doc_type'] === 'credit_note' ? 'Avoir' : crm_doc_label($doc['doc_type']);
$logo = (string) setting('logo');
$pct = fn($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
$meta = [['Date', $doc['issue_date'] ? date('d/m/Y', strtotime($doc['issue_date'])) : date('d/m/Y')]];
if ($isQuote && $doc['due_date']) $meta[] = ['Valable jusqu\'au', date('d/m/Y', strtotime($doc['due_date']))];
if ($isInvoice && $doc['due_date']) $meta[] = ['Échéance', date('d/m/Y', strtotime($doc['due_date']))];
if ($doc['service_date']) $meta[] = ['Exécution', $doc['service_date']];
$meta[] = ['Nature', crm_operation_types()[$doc['operation_type'] ?? 'services'] ?? 'Prestation de services'];
if ($doc['source_id'] && ($src = crm_doc((int) $doc['source_id'])) && $src['number']) {
    $meta[] = [$src['doc_type'] === 'quote' ? 'Devis' : 'Facture d\'origine', $src['number']];
}
$xof = (float) setting('billing_xof_rate');
$terms = crm_doc_terms($doc);
?>
<link rel="stylesheet" href="<?= asset('css/document.css') ?>">
<article class="sheet<?= $doc['status'] === 'draft' ? ' sheet--draft' : '' ?>" style="--sheet-primary:<?= e(setting('color_primary')) ?>">
    <?php if ($doc['status'] === 'draft'): ?><span class="sheet-watermark" aria-hidden="true">Brouillon</span><?php endif; ?>
    <header class="sheet-head">
        <div class="sheet-seller">
            <?php if ($logo): ?><img src="<?= e(media_url($logo)) ?>" alt="<?= e($seller['brand']) ?>" class="sheet-logo"><?php else: ?><strong class="sheet-brand"><?= e($seller['brand']) ?></strong><?php endif; ?>
            <p><strong><?= e($seller['name']) ?></strong><br><?= nl2br(e(implode("\n", array_filter([$seller['address'], $seller['email'], $seller['phone'], $seller['domain']])))) ?></p>
        </div>
        <div class="sheet-ref">
            <h2 class="sheet-title"><?= e(mb_strtoupper($title)) ?></h2>
            <p class="sheet-number"><?= e($doc['number'] ? 'N° ' . $doc['number'] : 'BROUILLON') ?></p>
            <dl class="sheet-meta">
                <?php foreach ($meta as [$k, $v]): ?><div><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd></div><?php endforeach; ?>
            </dl>
        </div>
    </header>

    <section class="sheet-client">
        <small><?= $isQuote ? 'Devis pour' : 'Facturé à' ?></small>
        <strong><?= e($client['name']) ?></strong>
        <p><?= nl2br(e(implode("\n", array_filter([
            $client['contact'] && $client['contact'] !== $client['name'] ? 'À l\'attention de ' . $client['contact'] : '',
            $client['address'] ?? '', trim(($client['postal_code'] ?? '') . ' ' . ($client['city'] ?? '')), $client['country'] ?? '',
            !empty($client['siren']) ? 'SIREN ' . $client['siren'] : '', !empty($client['vat_number']) ? 'TVA ' . $client['vat_number'] : '', $client['other_id'] ?? '',
        ])))) ?></p>
    </section>

    <?php if ($doc['title']): ?><h3 class="sheet-object">Objet : <?= e($doc['title']) ?></h3><?php endif; ?>
    <?php if ($doc['intro']): ?><p class="sheet-intro"><?= nl2br(e($doc['intro'])) ?></p><?php endif; ?>

    <table class="sheet-lines">
        <thead><tr><th>Désignation</th><th>Qté</th><th>PU HT</th><th>TVA</th><th>Total HT</th></tr></thead>
        <tbody>
        <?php foreach ($calc['lines'] as $l): ?>
            <?php if ($l['line_type'] === 'section'): ?>
            <tr class="sheet-section"><td colspan="5"><?= e($l['description']) ?></td></tr>
            <?php else: ?>
            <tr>
                <td data-label="Désignation"><strong><?= e($l['description']) ?></strong><?php if ($l['details']): ?><small><?= nl2br(e($l['details'])) ?></small><?php endif; ?></td>
                <td data-label="Qté"><?= e(rtrim(rtrim(number_format((float) $l['quantity'], 3, ',', ' '), '0'), ',')) ?> <?= e($l['unit']) ?></td>
                <td data-label="PU HT"><?= e(money((float) $l['unit_price'], $cur)) ?><?php if ((float) $l['discount_pct'] > 0): ?><small>remise <?= e($pct($l['discount_pct'])) ?> %</small><?php endif; ?></td>
                <td data-label="TVA"><?= e($pct($l['vat_rate'])) ?> %</td>
                <td data-label="Total HT"><?= e(money((float) $l['line_total'], $cur)) ?></td>
            </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="sheet-bottom">
        <div class="sheet-notes">
            <?php if ($doc['vat_mention']): ?><p class="sheet-vat"><?= e($doc['vat_mention']) ?></p><?php endif; ?>
            <?php if ($isInvoice && ($seller['iban'] || $seller['bank'])): ?>
            <p><strong>Règlement</strong><br><?= nl2br(e(implode("\n", array_filter([$seller['bank'] ? 'Banque : ' . $seller['bank'] : '', $seller['iban'] ? 'IBAN : ' . $seller['iban'] : '', $seller['bic'] ? 'BIC : ' . $seller['bic'] : '', 'Référence à indiquer : ' . ($doc['number'] ?: '')])))) ?></p>
            <?php endif; ?>
            <?php if ($isQuote && $doc['accepted_at']): ?>
            <p class="sheet-accepted">✓ Accepté le <?= e(date('d/m/Y', strtotime($doc['accepted_at']))) ?><?= $doc['accepted_by'] ? ' par ' . e($doc['accepted_by']) : '' ?></p>
            <?php elseif ($isQuote): ?>
            <div class="sheet-sign"><strong>Bon pour accord</strong><small>Date, signature et cachet</small></div>
            <?php endif; ?>
        </div>
        <dl class="sheet-totals">
            <div><dt>Total HT</dt><dd><?= e(money($calc['subtotal'], $cur)) ?></dd></div>
            <?php if ($calc['discount_amount'] > 0): ?>
            <div><dt>Remise <?= e($pct($doc['discount_pct'])) ?> %</dt><dd>− <?= e(money($calc['discount_amount'], $cur)) ?></dd></div>
            <div><dt>Total HT net</dt><dd><?= e(money($calc['subtotal'] - $calc['discount_amount'], $cur)) ?></dd></div>
            <?php endif; ?>
            <?php foreach ($calc['taxes'] as $rate => $t): if ((float) $rate <= 0) continue; ?>
            <div><dt>TVA <?= e($pct($rate)) ?> %</dt><dd><?= e(money($t['tax'], $cur)) ?></dd></div>
            <?php endforeach; ?>
            <?php if ($calc['tax_total'] == 0): ?><div><dt>TVA</dt><dd><?= e(money(0, $cur)) ?></dd></div><?php endif; ?>
            <div class="sheet-grand"><dt><?= $doc['doc_type'] === 'credit_note' ? 'Total de l\'avoir TTC' : 'Total TTC' ?></dt><dd><?= e(money($calc['total'], $cur)) ?></dd></div>
            <?php if ($xof > 0 && $cur === 'EUR'): ?><div class="sheet-small"><dt>Soit environ</dt><dd><?= e(money($calc['total'] * $xof, 'XOF')) ?></dd></div><?php endif; ?>
            <?php if ($xof > 0 && $cur === 'XOF'): ?><div class="sheet-small"><dt>Soit environ</dt><dd><?= e(money($calc['total'] / $xof, 'EUR')) ?></dd></div><?php endif; ?>
            <?php if ($isQuote && (float) $doc['deposit_pct'] > 0): ?><div class="sheet-small"><dt>Acompte à la commande (<?= e($pct($doc['deposit_pct'])) ?> %)</dt><dd><?= e(money($calc['total'] * (float) $doc['deposit_pct'] / 100, $cur)) ?></dd></div><?php endif; ?>
        </dl>
    </div>

    <?php if ($terms !== ''): ?>
    <section class="sheet-terms"><strong><?= $isQuote ? 'Conditions' : ($isInvoice ? 'Conditions de paiement' : 'Informations') ?></strong><p><?= nl2br(e($terms)) ?></p></section>
    <?php endif; ?>

    <footer class="sheet-foot"><?= e(implode(' · ', array_filter([
        $seller['name'] . ($seller['legal_form'] ? ', ' . $seller['legal_form'] : '') . ($seller['capital'] ? ' au capital de ' . $seller['capital'] : ''),
        $seller['address'], $seller['siret'] ? 'SIRET ' . $seller['siret'] : ($seller['siren'] ? 'SIREN ' . $seller['siren'] : ''), $seller['rcs'], $seller['vat'] ? 'TVA ' . $seller['vat'] : '',
    ]))) ?></footer>
</article>
