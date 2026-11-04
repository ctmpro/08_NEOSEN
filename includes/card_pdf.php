<?php
/**
 * Cartes de visite imprimables — un seul dessin, deux sorties :
 *  - PDF (FPDF) prêt pour l'imprimeur : 85 × 55 mm + 3 mm de fond perdu, ou planche A4 de 10 cartes ;
 *  - SVG identique pour l'aperçu dans l'administration.
 */

require_once UTIL_PATH . '/FPDF/fpdf.php';
require_once ROOT_PATH . '/includes/crm_pdf.php';   // pdf_txt(), hex_rgb()
require_once ROOT_PATH . '/includes/qr.php';
require_once ROOT_PATH . '/includes/cards.php';

const CARD_W = 85.0;
const CARD_H = 55.0;
const CARD_BLEED = 3.0;

/** Métriques des polices Sora (fichiers FPDF) pour mesurer les textes. */
function card_font_file(string $weight): string {
    return ['R' => 'Sora-Regular.php', 'S' => 'Sora-SemiBold.php', 'B' => 'Sora-Bold.php'][$weight] ?? 'Sora-Regular.php';
}

function card_font_widths(string $weight): array {
    static $cache = [];
    if (!isset($cache[$weight])) {
        $cw = [];
        include UTIL_PATH . '/FPDF/font/' . card_font_file($weight);
        $cache[$weight] = $cw;
    }
    return $cache[$weight];
}

function card_mix(array $a, array $b, float $t): array {
    return [(int) round($a[0] + ($b[0] - $a[0]) * $t), (int) round($a[1] + ($b[1] - $a[1]) * $t), (int) round($a[2] + ($b[2] - $a[2]) * $t)];
}

/* =====================================================================
 * Surface de dessin (coordonnées en mm, origine en haut à gauche)
 * ===================================================================== */
abstract class CardCanvas {
    abstract function fillRect(float $x, float $y, float $w, float $h, array $rgb): void;
    abstract function fillRects(array $rects, array $rgb): void;
    abstract function fillPoly(array $pts, array $rgb): void;
    abstract function fillCircle(float $cx, float $cy, float $r, array $rgb): void;
    abstract function roundRect(float $x, float $y, float $w, float $h, float $r, ?array $fill, ?array $stroke = null, float $lw = 0.2): void;
    abstract function polyline(array $pts, array $rgb, float $lw): void;
    abstract function clip(float $x, float $y, float $w, float $h, float $r = 0): void;
    abstract function unclip(): void;
    abstract function text(float $x, float $y, string $s, string $weight, float $pt, array $rgb, float $spacing = 0): void;
    abstract function image(string $file, float $x, float $y, float $w, float $h): void;

    function textWidth(string $s, string $weight, float $pt, float $spacing = 0): float {
        $cw = card_font_widths($weight);
        $s = pdf_txt($s);
        $w = 0;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) $w += $cw[$s[$i]] ?? 600;
        return $w * $pt * 25.4 / 72 / 1000 + max(0, strlen($s) - 1) * $spacing;
    }

    /** Texte aligné (L, C, R) ; x = bord gauche, centre ou bord droit. */
    function textAt(float $x, float $y, string $s, string $weight, float $pt, array $rgb, string $align = 'L', float $spacing = 0): void {
        if ($align !== 'L') {
            $w = $this->textWidth($s, $weight, $pt, $spacing);
            $x -= $align === 'C' ? $w / 2 : $w;
        }
        $this->text($x, $y, $s, $weight, $pt, $rgb, $spacing);
    }

    /** Taille de police maximale (≤ $pt) pour que le texte tienne dans $maxW. */
    function fitSize(string $s, string $weight, float $pt, float $maxW, float $min = 6, float $spacing = 0): float {
        while ($pt > $min && $this->textWidth($s, $weight, $pt, $spacing) > $maxW) $pt -= 0.25;
        return $pt;
    }

    /** Découpe un texte en lignes de largeur maximale $maxW. */
    function wrap(string $s, string $weight, float $pt, float $maxW): array {
        $lines = [];
        $cur = '';
        foreach (preg_split('/\s+/', trim($s)) as $word) {
            $try = $cur === '' ? $word : $cur . ' ' . $word;
            if ($cur !== '' && $this->textWidth($try, $weight, $pt) > $maxW) {
                $lines[] = $cur;
                $cur = $word;
            } else {
                $cur = $try;
            }
        }
        if ($cur !== '') $lines[] = $cur;
        return $lines;
    }

    /** Dégradé diagonal (haut-gauche → bas-droite) par fines bandes. */
    function diagGradient(float $x, float $y, float $w, float $h, array $c1, array $c2, int $steps = 90): void {
        $total = $w + $h;
        $this->clip($x, $y, $w, $h);
        for ($i = 0; $i < $steps; $i++) {
            $a = $total * $i / $steps;
            $b = $total * ($i + 1) / $steps + 0.05;
            $this->fillPoly([[$x + $a, $y], [$x + $b, $y], [$x + $b - $h, $y + $h], [$x + $a - $h, $y + $h]], card_mix($c1, $c2, $i / ($steps - 1)));
        }
        $this->unclip();
    }

    /** Dégradé horizontal (gauche → droite). */
    function hGradient(float $x, float $y, float $w, float $h, array $c1, array $c2, int $steps = 80): void {
        for ($i = 0; $i < $steps; $i++) {
            $this->fillRect($x + $w * $i / $steps, $y, $w / $steps + 0.05, $h, card_mix($c1, $c2, $i / ($steps - 1)));
        }
    }

    /** Halo lumineux (dégradé radial simulé par cercles concentriques). */
    function glow(float $cx, float $cy, float $r, array $inner, array $outer, int $steps = 40): void {
        for ($i = 0; $i < $steps; $i++) {
            $t = $i / $steps;
            $this->fillCircle($cx, $cy, $r * (1 - $t), card_mix($outer, $inner, $t * $t));
        }
    }

    /** QR code vectoriel. */
    function qr(array $matrix, float $x, float $y, float $size, array $rgb): void {
        $n = count($matrix);
        $m = $size / $n;
        $rects = [];
        foreach ($matrix as $row => $cells) {
            $col = 0;
            while ($col < $n) {
                if ($cells[$col]) {
                    $start = $col;
                    while ($col < $n && $cells[$col]) $col++;
                    $rects[] = [$x + $start * $m, $y + $row * $m, ($col - $start) * $m, $m];
                } else {
                    $col++;
                }
            }
        }
        $this->fillRects($rects, $rgb);
    }
}

/* ---------------------------------------------------------------- PDF */
class CardPDF extends FPDF {
    function __construct(string $orientation, array|string $size) {
        parent::__construct($orientation, 'mm', $size);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(0, 0, 0);
        $this->AddFont('Sora', '', 'Sora-Regular.php');
        $this->AddFont('Sora', 'B', 'Sora-Bold.php');
        $this->AddFont('SoraSemi', '', 'Sora-SemiBold.php');
        $this->SetCreator(site_name());
    }
    function raw(string $s): void { $this->_out($s); }
    function GetK(): float { return $this->k; }
    function px(float $x): string { return sprintf('%.3F', $x * $this->k); }
    function py(float $y): string { return sprintf('%.3F', ($this->h - $y) * $this->k); }
}

class PdfCardCanvas extends CardCanvas {
    function __construct(public CardPDF $pdf) {}

    private function rgb(array $c, string $op = 'rg'): string {
        return sprintf('%.3F %.3F %.3F %s', $c[0] / 255, $c[1] / 255, $c[2] / 255, $op);
    }
    private function rectPath(float $x, float $y, float $w, float $h): string {
        $p = $this->pdf;
        return $p->px($x) . ' ' . $p->py($y) . ' ' . sprintf('%.3F %.3F', $w * $p->GetK(), -$h * $p->GetK()) . ' re';
    }
    private function roundPath(float $x, float $y, float $w, float $h, float $r): string {
        $p = $this->pdf;
        $k = 0.5523 * $r;
        $o = [];
        $o[] = $p->px($x + $r) . ' ' . $p->py($y) . ' m';
        $o[] = $p->px($x + $w - $r) . ' ' . $p->py($y) . ' l';
        $o[] = $p->px($x + $w - $r + $k) . ' ' . $p->py($y) . ' ' . $p->px($x + $w) . ' ' . $p->py($y + $r - $k) . ' ' . $p->px($x + $w) . ' ' . $p->py($y + $r) . ' c';
        $o[] = $p->px($x + $w) . ' ' . $p->py($y + $h - $r) . ' l';
        $o[] = $p->px($x + $w) . ' ' . $p->py($y + $h - $r + $k) . ' ' . $p->px($x + $w - $r + $k) . ' ' . $p->py($y + $h) . ' ' . $p->px($x + $w - $r) . ' ' . $p->py($y + $h) . ' c';
        $o[] = $p->px($x + $r) . ' ' . $p->py($y + $h) . ' l';
        $o[] = $p->px($x + $r - $k) . ' ' . $p->py($y + $h) . ' ' . $p->px($x) . ' ' . $p->py($y + $h - $r + $k) . ' ' . $p->px($x) . ' ' . $p->py($y + $h - $r) . ' c';
        $o[] = $p->px($x) . ' ' . $p->py($y + $r) . ' l';
        $o[] = $p->px($x) . ' ' . $p->py($y + $r - $k) . ' ' . $p->px($x + $r - $k) . ' ' . $p->py($y) . ' ' . $p->px($x + $r) . ' ' . $p->py($y) . ' c h';
        return implode(' ', $o);
    }

    function fillRect(float $x, float $y, float $w, float $h, array $rgb): void {
        $this->pdf->raw('q ' . $this->rgb($rgb) . ' ' . $this->rectPath($x, $y, $w, $h) . ' f Q');
    }
    function fillRects(array $rects, array $rgb): void {
        $path = '';
        foreach ($rects as [$x, $y, $w, $h]) $path .= $this->rectPath($x, $y, $w, $h) . ' ';
        $this->pdf->raw('q ' . $this->rgb($rgb) . ' ' . $path . 'f Q');
    }
    function fillPoly(array $pts, array $rgb): void {
        $p = $this->pdf;
        $s = '';
        foreach ($pts as $i => [$x, $y]) $s .= $p->px($x) . ' ' . $p->py($y) . ($i ? ' l ' : ' m ');
        $p->raw('q ' . $this->rgb($rgb) . ' ' . $s . 'h f Q');
    }
    function fillCircle(float $cx, float $cy, float $r, array $rgb): void {
        $this->pdf->raw('q ' . $this->rgb($rgb) . ' ' . $this->roundPath($cx - $r, $cy - $r, 2 * $r, 2 * $r, $r) . ' f Q');
    }
    function roundRect(float $x, float $y, float $w, float $h, float $r, ?array $fill, ?array $stroke = null, float $lw = 0.2): void {
        $op = $fill && $stroke ? 'B' : ($fill ? 'f' : 'S');
        $s = 'q ' . ($fill ? $this->rgb($fill) . ' ' : '') . ($stroke ? $this->rgb($stroke, 'RG') . ' ' . sprintf('%.3F w ', $lw * $this->pdf->GetK()) : '');
        $this->pdf->raw($s . $this->roundPath($x, $y, $w, $h, $r) . ' ' . $op . ' Q');
    }
    function polyline(array $pts, array $rgb, float $lw): void {
        $p = $this->pdf;
        $s = '';
        foreach ($pts as $i => [$x, $y]) $s .= $p->px($x) . ' ' . $p->py($y) . ($i ? ' l ' : ' m ');
        $p->raw('q 1 J 1 j ' . $this->rgb($rgb, 'RG') . sprintf(' %.3F w ', $lw * $p->GetK()) . $s . 'S Q');
    }
    function clip(float $x, float $y, float $w, float $h, float $r = 0): void {
        $this->pdf->raw('q ' . ($r > 0 ? $this->roundPath($x, $y, $w, $h, $r) : $this->rectPath($x, $y, $w, $h)) . ' W n');
    }
    function unclip(): void {
        $this->pdf->raw('Q');
    }
    function text(float $x, float $y, string $s, string $weight, float $pt, array $rgb, float $spacing = 0): void {
        $p = $this->pdf;
        [$family, $style] = ['R' => ['Sora', ''], 'S' => ['SoraSemi', ''], 'B' => ['Sora', 'B']][$weight] ?? ['Sora', ''];
        $p->SetFont($family, $style, $pt);
        $p->SetTextColor(...$rgb);
        $p->raw('q' . ($spacing ? sprintf(' %.3F Tc', $spacing * $p->GetK()) : ''));
        $p->Text($x, $y, pdf_txt($s));
        $p->raw('Q');
    }
    function image(string $file, float $x, float $y, float $w, float $h): void {
        $this->pdf->Image($file, $x, $y, $w, $h);
    }
}

/* ---------------------------------------------------------------- SVG */
class SvgCardCanvas extends CardCanvas {
    public string $out = '';
    private int $clipId = 0;
    private static int $uid = 0;

    private function c(array $rgb): string { return sprintf('#%02x%02x%02x', ...$rgb); }
    private function n(float $v): string { return rtrim(rtrim(sprintf('%.3F', $v), '0'), '.'); }

    function fillRect(float $x, float $y, float $w, float $h, array $rgb): void {
        $this->out .= '<rect x="' . $this->n($x) . '" y="' . $this->n($y) . '" width="' . $this->n($w) . '" height="' . $this->n($h) . '" fill="' . $this->c($rgb) . '"/>';
    }
    function fillRects(array $rects, array $rgb): void {
        $d = '';
        foreach ($rects as [$x, $y, $w, $h]) $d .= 'M' . $this->n($x) . ' ' . $this->n($y) . 'h' . $this->n($w) . 'v' . $this->n($h) . 'h' . $this->n(-$w) . 'z';
        $this->out .= '<path fill="' . $this->c($rgb) . '" d="' . $d . '"/>';
    }
    function fillPoly(array $pts, array $rgb): void {
        $this->out .= '<polygon fill="' . $this->c($rgb) . '" points="' . implode(' ', array_map(fn($p) => $this->n($p[0]) . ',' . $this->n($p[1]), $pts)) . '"/>';
    }
    function fillCircle(float $cx, float $cy, float $r, array $rgb): void {
        $this->out .= '<circle cx="' . $this->n($cx) . '" cy="' . $this->n($cy) . '" r="' . $this->n($r) . '" fill="' . $this->c($rgb) . '"/>';
    }
    function roundRect(float $x, float $y, float $w, float $h, float $r, ?array $fill, ?array $stroke = null, float $lw = 0.2): void {
        $this->out .= '<rect x="' . $this->n($x) . '" y="' . $this->n($y) . '" width="' . $this->n($w) . '" height="' . $this->n($h) . '" rx="' . $this->n($r) . '" fill="' . ($fill ? $this->c($fill) : 'none') . '"'
            . ($stroke ? ' stroke="' . $this->c($stroke) . '" stroke-width="' . $this->n($lw) . '"' : '') . '/>';
    }
    function polyline(array $pts, array $rgb, float $lw): void {
        $this->out .= '<polyline fill="none" stroke="' . $this->c($rgb) . '" stroke-width="' . $this->n($lw) . '" stroke-linecap="round" stroke-linejoin="round" points="' . implode(' ', array_map(fn($p) => $this->n($p[0]) . ',' . $this->n($p[1]), $pts)) . '"/>';
    }
    function clip(float $x, float $y, float $w, float $h, float $r = 0): void {
        $id = 'cc' . (++self::$uid);
        $this->out .= '<clipPath id="' . $id . '"><rect x="' . $this->n($x) . '" y="' . $this->n($y) . '" width="' . $this->n($w) . '" height="' . $this->n($h) . '" rx="' . $this->n($r) . '"/></clipPath><g clip-path="url(#' . $id . ')">';
    }
    function unclip(): void {
        $this->out .= '</g>';
    }
    function text(float $x, float $y, string $s, string $weight, float $pt, array $rgb, float $spacing = 0): void {
        $fw = ['R' => 400, 'S' => 600, 'B' => 700][$weight] ?? 400;
        $this->out .= '<text x="' . $this->n($x) . '" y="' . $this->n($y) . '" font-family="Sora, sans-serif" font-weight="' . $fw . '" font-size="' . $this->n($pt * 25.4 / 72) . '"'
            . ($spacing ? ' letter-spacing="' . $this->n($spacing) . '"' : '') . ' fill="' . $this->c($rgb) . '">' . htmlspecialchars($s, ENT_XML1) . '</text>';
    }
    function image(string $file, float $x, float $y, float $w, float $h): void {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
        $this->out .= '<image x="' . $this->n($x) . '" y="' . $this->n($y) . '" width="' . $this->n($w) . '" height="' . $this->n($h) . '" href="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file)) . '"/>';
    }
}

/* =====================================================================
 * Dessin des faces
 * ===================================================================== */

/** Palette de la carte (couleurs de la charte définies dans les réglages). */
function card_palette(): array {
    return [
        'primary' => hex_rgb((string) (setting('color_primary') ?: '#3B4BFF')),
        'accent'  => hex_rgb((string) (setting('color_accent') ?: '#14C8B4')),
        'dark'    => hex_rgb((string) (setting('color_dark') ?: '#0A0F1F')),
        'white'   => [255, 255, 255],
        'muted'   => [154, 163, 188],
        'ink'     => [10, 15, 31],
        'grey'    => [91, 100, 120],
        'line'    => [228, 232, 241],
    ];
}

/** Emblème : image personnalisée (favicon PNG/JPG) ou monogramme vectoriel en dégradé. */
function card_mark(CardCanvas $c, float $x, float $y, float $s, array $pal, bool $onGradient = false): void {
    $fav = (string) setting('favicon');
    $file = $fav !== '' ? ROOT_PATH . '/' . ltrim($fav, '/') : '';
    if ($file && is_file($file) && preg_match('/\.(png|jpe?g)$/i', $file)) {
        $c->image($file, $x, $y, $s, $s);
        return;
    }
    $u = $s / 36;
    if ($onGradient) {
        $c->roundRect($x, $y, $s, $s, 10 * $u, $pal['white']);
        $stroke = $pal['primary'];
    } else {
        $c->clip($x, $y, $s, $s, 10 * $u);
        $c->diagGradient($x, $y, $s, $s, $pal['primary'], $pal['accent'], 30);
        $c->unclip();
        $stroke = $pal['white'];
    }
    $c->polyline([[$x + 11 * $u, $y + 25 * $u], [$x + 11 * $u, $y + 11 * $u], [$x + 25 * $u, $y + 25 * $u], [$x + 25 * $u, $y + 11 * $u]], $stroke, 3 * $u);
}

/**
 * Dessine une face de carte. ($x, $y) = coin haut-gauche du format fini ; $bleed = débord à peindre.
 */
function card_draw(CardCanvas $c, array $card, string $face, float $x, float $y, float $bleed): void {
    $pal = card_palette();
    $theme = array_key_exists($card['theme'] ?? '', card_themes()) ? $card['theme'] : 'nuit';
    $W = CARD_W; $H = CARD_H;
    $bx = $x - $bleed; $by = $y - $bleed; $bw = $W + 2 * $bleed; $bh = $H + 2 * $bleed;
    $brand = mb_strtoupper(site_name());
    $tagline = (string) ($card['tagline'] ?? '');

    $c->clip($bx, $by, $bw, $bh);

    if ($face === 'recto') {
        if ($theme === 'degrade') {
            $c->diagGradient($bx, $by, $bw, $bh, $pal['primary'], $pal['accent'], 140);
            $fg = $pal['white']; $sub = card_mix($pal['white'], $pal['primary'], 0.15);
        } elseif ($theme === 'clair') {
            $c->fillRect($bx, $by, $bw, $bh, $pal['white']);
            $c->glow($x + $W + 4, $y - 6, 36, card_mix($pal['accent'], $pal['white'], 0.82), $pal['white'], 36);
            $fg = $pal['ink']; $sub = $pal['grey'];
        } else {
            $c->fillRect($bx, $by, $bw, $bh, $pal['dark']);
            $c->glow($x + $W * 0.88, $y - 2, 46, card_mix($pal['primary'], $pal['dark'], 0.35), $pal['dark'], 44);
            $c->glow($x + 4, $y + $H + 6, 30, card_mix($pal['accent'], $pal['dark'], 0.62), $pal['dark'], 30);
            $fg = $pal['white']; $sub = $pal['muted'];
        }
        // Emblème + nom de marque centrés
        $ms = 13;
        card_mark($c, $x + ($W - $ms) / 2, $y + 11.5, $ms, $pal, $theme === 'degrade');
        $pt = $c->fitSize($brand, 'B', 17, $W - 16, 9, 1.1);
        $c->textAt($x + $W / 2, $y + 34, $brand, 'B', $pt, $fg, 'C', 1.1);
        if ($tagline !== '') {
            $c->textAt($x + $W / 2, $y + 40.5, $tagline, 'R', $c->fitSize($tagline, 'R', 7, $W - 16, 5, 0.35), $sub, 'C', 0.35);
        }
        // Filet dégradé en bas (fond perdu inclus)
        if ($theme !== 'degrade') {
            $c->hGradient($bx, $y + $H - 1.6, $bw, 1.6 + $bleed, $pal['primary'], $pal['accent']);
        }
    } else {
        $dark = $theme === 'nuit';
        if ($dark) {
            $c->fillRect($bx, $by, $bw, $bh, $pal['dark']);
            $c->glow($x - 6, $y - 8, 34, card_mix($pal['primary'], $pal['dark'], 0.55), $pal['dark'], 34);
            $fg = $pal['white']; $sub = $pal['muted']; $hi = $pal['accent'];
        } else {
            $c->fillRect($bx, $by, $bw, $bh, $pal['white']);
            $fg = $pal['ink']; $sub = $pal['grey']; $hi = $pal['primary'];
            // Bande dégradée sur le bord gauche
            $c->diagGradient($bx, $by, 2.2 + $bleed, $bh, $pal['primary'], $pal['accent'], 40);
        }
        $left = $x + ($dark ? 6.5 : 8);
        $tile = 25.5;
        $tx = $x + $W - 6 - $tile;
        $ty = $y + 7;
        $colW = $tx - $left - 4;

        // Nom (taille ajustée, ou deux lignes si nécessaire)
        $name = trim((string) ($card['person_name'] ?: $card['company'] ?: site_name()));
        $pt = $c->fitSize($name, 'B', 11, $colW, 8.5);
        $lineY = $y + 11.5;
        if ($c->textWidth($name, 'B', $pt) > $colW) {
            foreach (array_slice($c->wrap($name, 'B', 9.5, $colW), 0, 2) as $l) {
                $c->text($left, $lineY, $l, 'B', 9.5, $fg);
                $lineY += 4.2;
            }
            $lineY -= 4.2;
        } else {
            $c->text($left, $lineY, $name, 'B', $pt, $fg);
        }
        $role = implode(' · ', array_filter([(string) $card['job_title'], (string) $card['company']]));
        if ($role !== '') {
            $c->text($left, $lineY + 4.4, mb_strtoupper($role), 'S', $c->fitSize(mb_strtoupper($role), 'S', 6.2, $colW, 4.5, 0.3), $hi, 0.3);
        }
        $c->hGradient($left, $lineY + 7.6, 9, 0.7, $pal['primary'], $pal['accent'], 20);

        // Coordonnées
        $site = preg_replace('#^https?://(www\.)?#', '', rtrim((string) ($card['website'] ?: setting('site_domain')), '/'));
        $rows = array_values(array_filter([
            ['T', (string) $card['phone']],
            ['@', (string) $card['email']],
            ['W', (string) $site],
            ['A', (string) ($card['address'] ?? '')],
        ], fn($r) => $r[1] !== ''));
        $ry = $y + $H - 7 - (count($rows) - 1) * 4.6;
        foreach ($rows as [$k, $val]) {
            $c->fillCircle($left + 0.9, $ry - 1.05, 0.9, $hi);
            $c->text($left + 3.4, $ry, $val, 'R', $c->fitSize($val, 'R', 6.6, $colW - 3.4, 4.8), $fg);
            $ry += 4.6;
        }

        // QR code sur pavé blanc
        $c->roundRect($tx, $ty, $tile, $tile, 2.2, $pal['white'], $dark ? null : $pal['line'], 0.25);
        $pad = 2.1;
        $c->qr(qr_matrix(card_url($card)), $tx + $pad, $ty + $pad, $tile - 2 * $pad, $pal['ink']);
        $cta = (string) ($card['cta_label'] ?? '');
        if ($cta !== '') {
            $cy = $ty + $tile + 3.6;
            foreach (array_slice($c->wrap($cta, 'R', 5.4, $tile + 4), 0, 2) as $l) {
                $c->textAt($tx + $tile / 2, $cy, $l, 'R', 5.4, $sub, 'C');
                $cy += 2.6;
            }
        }
        $c->textAt($tx + $tile / 2, $y + $H - 4.2, card_short_url($card), 'S', 5.2, $hi, 'C');
    }
    $c->unclip();
}

/* =====================================================================
 * Sorties
 * ===================================================================== */

/** Aperçu SVG d'une face (format fini, sans fond perdu). */
function card_svg(array $card, string $face, bool $withBleed = false): string {
    $c = new SvgCardCanvas();
    $b = $withBleed ? CARD_BLEED : 0;
    card_draw($c, $card, $face, $b, $b, $b);
    $w = CARD_W + 2 * $b; $h = CARD_H + 2 * $b;
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . 'mm" height="' . $h . 'mm">' . $c->out . '</svg>';
}

/**
 * PDF : 'print' = fichier imprimeur (2 pages 91 × 61 mm, recto puis verso, fond perdu 3 mm) ;
 *       'sheet' = planche A4 de 10 cartes avec traits de coupe (recto puis verso, miroir pour le recto-verso).
 */
function card_pdf(array $card, string $mode = 'print'): string {
    if ($mode === 'sheet') {
        $pdf = new CardPDF('P', 'A4');
        $c = new PdfCardCanvas($pdf);
        $cols = 2; $rowsN = 5;
        $ox = (210 - $cols * CARD_W) / 2;
        $oy = (297 - $rowsN * CARD_H) / 2;
        foreach (['recto', 'verso'] as $face) {
            $pdf->AddPage();
            for ($r = 0; $r < $rowsN; $r++) {
                for ($col = 0; $col < $cols; $col++) {
                    // Au verso, les colonnes sont inversées pour tomber pile en impression recto-verso (bord long)
                    $cc = $face === 'verso' ? $cols - 1 - $col : $col;
                    card_draw($c, $card, $face, $ox + $cc * CARD_W, $oy + $r * CARD_H, 0);
                }
            }
            // Traits de coupe
            $pdf->SetDrawColor(120, 120, 120);
            $pdf->SetLineWidth(0.15);
            for ($i = 0; $i <= $cols; $i++) {
                $lx = $ox + $i * CARD_W;
                $pdf->Line($lx, $oy - 7, $lx, $oy - 2);
                $pdf->Line($lx, $oy + $rowsN * CARD_H + 2, $lx, $oy + $rowsN * CARD_H + 7);
            }
            for ($i = 0; $i <= $rowsN; $i++) {
                $ly = $oy + $i * CARD_H;
                $pdf->Line($ox - 7, $ly, $ox - 2, $ly);
                $pdf->Line($ox + $cols * CARD_W + 2, $ly, $ox + $cols * CARD_W + 7, $ly);
            }
            $pdf->SetFont('Sora', '', 6);
            $pdf->SetTextColor(140, 140, 140);
            $pdf->Text($ox, 297 - 3.5, pdf_txt(site_name() . ' — ' . ($face === 'recto' ? 'Recto' : 'Verso') . ' — imprimer en recto-verso (retournement bord long), échelle 100 %'));
        }
        $pdf->SetTitle(pdf_txt('Planche cartes de visite — ' . $card['label']));
        return $pdf->Output('S');
    }

    $pw = CARD_W + 2 * CARD_BLEED;
    $ph = CARD_H + 2 * CARD_BLEED;
    $pdf = new CardPDF('L', [$pw, $ph]);
    $c = new PdfCardCanvas($pdf);
    foreach (['recto', 'verso'] as $face) {
        $pdf->AddPage('L', [$pw, $ph]);
        card_draw($c, $card, $face, CARD_BLEED, CARD_BLEED, CARD_BLEED);
    }
    $pdf->SetTitle(pdf_txt('Carte de visite — ' . $card['label']));
    return $pdf->Output('S');
}
