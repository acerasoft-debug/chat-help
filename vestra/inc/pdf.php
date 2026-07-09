<?php
/**
 * VestraPdf — minimal, dependency-free PDF 1.4 writer for one purpose:
 * generating invoice documents without a Composer package (no dompdf/tcpdf —
 * nothing to forget to `composer install` on the server). Hand-built PDF
 * objects; standard Helvetica / Helvetica-Bold fonts (Type1, never embedded —
 * every PDF reader ships them per spec); WinAnsi (CP1252) text encoding,
 * which covers the accented characters used in DE/FR/IT/ES business text.
 *
 * Deliberately NOT a general-purpose PDF library — just enough primitives
 * (absolute-positioned text, right-aligned text, lines, filled rectangles,
 * manual page breaks) to lay out an invoice. Callers own all layout/cursor
 * logic; this class only turns drawing calls into a valid PDF byte stream.
 */
class VestraPdf {
    const PAGE_W = 595; // A4 in points, 72dpi
    const PAGE_H = 842;

    /** @var string[] completed page content streams */
    private array $pages = [];
    private string $cur = '';

    public function addPage(): void {
        $this->pages[] = $this->cur;
        $this->cur = '';
    }

    private function esc(string $s): string {
        $conv = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
        if ($conv === false) $conv = preg_replace('/[^\x20-\x7E]/', '?', $s) ?? '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $conv);
    }

    /** Left-aligned text; ($x,$y) is the text baseline origin, PDF coordinates (origin bottom-left). */
    public function text(float $x, float $y, float $size, string $s, bool $bold = false): void {
        if ($s === '') return;
        $font = $bold ? 'F2' : 'F1';
        $this->cur .= sprintf("BT /%s %.1F Tf %.2F %.2F Td (%s) Tj ET\n", $font, $size, $x, $y, $this->esc($s));
    }

    /** Right-aligned text ending at $xRight (approximate Helvetica average glyph width — fine for labels/amounts). */
    public function textR(float $xRight, float $y, float $size, string $s, bool $bold = false): void {
        $this->text($xRight - $this->strWidth($s, $size, $bold), $y, $size, $s, $bold);
    }

    public function strWidth(string $s, float $size, bool $bold = false): float {
        return mb_strlen($s) * $size * ($bold ? 0.60 : 0.52);
    }

    /** Word-wrap plain text to fit $maxW; returns an array of lines. */
    public function wrap(string $s, float $maxW, float $size, bool $bold = false): array {
        $words = preg_split('/\s+/', trim($s));
        $lines = []; $cur = '';
        foreach ($words as $w) {
            if ($w === '') continue;
            $try = $cur === '' ? $w : $cur.' '.$w;
            if ($this->strWidth($try, $size, $bold) > $maxW && $cur !== '') { $lines[] = $cur; $cur = $w; }
            else $cur = $try;
        }
        if ($cur !== '') $lines[] = $cur;
        return $lines ?: [''];
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $w = 0.6, float $gray = 0.6): void {
        $this->cur .= sprintf("%.2F w %.2F G %.2F %.2F m %.2F %.2F l S\n", $w, $gray, $x1, $y1, $x2, $y2);
    }

    public function rectFill(float $x, float $y, float $w, float $h, float $gray = 0.93): void {
        $this->cur .= sprintf("%.2F g %.2F %.2F %.2F %.2F re f 0 g\n", $gray, $x, $y, $w, $h);
    }

    /** Serialize all pages into a complete PDF byte string. */
    public function output(): string {
        $pages = $this->pages;
        $pages[] = $this->cur;

        // Fixed numbering: 1=Catalog 2=Pages 3=Font(regular) 4=Font(bold), then Page/Content pairs from 5.
        $objs = [];
        $streams = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $id = 5; $kids = [];
        foreach ($pages as $content) {
            $pageId = $id++; $contentId = $id++;
            $kids[] = "$pageId 0 R";
            $objs[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_W.' '.self::PAGE_H.
                '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $streams[$contentId] = $content;
        }
        $objs[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';

        $maxId = $id - 1;
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        for ($i = 1; $i <= $maxId; $i++) {
            $offsets[$i] = strlen($out);
            if (isset($streams[$i])) {
                $out .= "$i 0 obj\n<< /Length ".strlen($streams[$i])." >>\nstream\n{$streams[$i]}\nendstream\nendobj\n";
            } else {
                $out .= "$i 0 obj\n{$objs[$i]}\nendobj\n";
            }
        }
        $xrefStart = strlen($out);
        $out .= "xref\n0 ".($maxId + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxId; $i++) $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        $out .= "trailer\n<< /Size ".($maxId + 1)." /Root 1 0 R >>\nstartxref\n{$xrefStart}\n%%EOF";
        return $out;
    }
}
