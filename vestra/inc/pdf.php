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
 * JPEG placement, manual page breaks) to lay out an invoice or an order
 * sheet. Callers own all layout/cursor logic; this class only turns drawing
 * calls into a valid PDF byte stream.
 */
class VestraPdf {
    const PAGE_W = 595; // A4 in points, 72dpi
    const PAGE_H = 842;

    /** @var string[] completed page content streams */
    private array $pages = [];
    private string $cur = '';
    /** @var array<string,array> embedded JPEGs, keyed by the /ImN name used in content streams */
    private array $imgs = [];

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

    /**
     * Place a JPEG inside the box whose bottom-left corner is ($x,$y), scaled to fit and
     * centred so photos of different crops still line up in a column.
     *
     * The bytes go into the file untouched (/DCTDecode is JPEG), so the caller must hand
     * over a *baseline* JPEG — progressive ones are legal JPEG but not legal DCTDecode, and
     * some readers show them as a grey box. Returns false rather than throwing when the
     * bytes cannot be described: one unreadable photo should cost that row's thumbnail,
     * not the whole document.
     */
    public function imageJpeg(string $jpeg, float $x, float $y, float $boxW, float $boxH): bool {
        $info = self::jpegInfo($jpeg);
        if (!$info || $info['w'] < 1 || $info['h'] < 1) return false;

        // The same photo can appear on several rows (one model, several colours). Embed once.
        $hash = md5($jpeg);
        $key  = '';
        foreach ($this->imgs as $k => $im) { if ($im['hash'] === $hash) { $key = $k; break; } }
        if ($key === '') {
            $key = 'Im'.(count($this->imgs) + 1);
            $this->imgs[$key] = $info + ['data' => $jpeg, 'hash' => $hash];
        }

        $s  = min($boxW / $info['w'], $boxH / $info['h']);
        $w  = $info['w'] * $s;  $h  = $info['h'] * $s;
        $ox = $x + ($boxW - $w) / 2;  $oy = $y + ($boxH - $h) / 2;
        $this->cur .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n", $w, $h, $ox, $oy, $key);
        return true;
    }

    /** Pixel size and component count from the JPEG's frame header; null if it isn't one. */
    private static function jpegInfo(string $d): ?array {
        $len = strlen($d);
        if ($len < 4 || substr($d, 0, 2) !== "\xFF\xD8") return null;
        $adobe = false;
        $i = 2;
        while ($i < $len) {
            if ($d[$i] !== "\xFF") { $i++; continue; }
            while ($i < $len && $d[$i] === "\xFF") $i++;   // fill bytes are legal before a marker
            if ($i >= $len) break;
            $m = ord($d[$i]); $i++;
            if ($m === 0x01 || ($m >= 0xD0 && $m <= 0xD8)) continue;   // standalone markers, no payload
            if ($m === 0xD9 || $m === 0xDA) break;                     // end of image / start of scan
            if ($i + 2 > $len) break;
            $seg = (ord($d[$i]) << 8) | ord($d[$i + 1]);
            if ($seg < 2) break;
            if ($m === 0xEE) $adobe = true;                            // APP14 "Adobe" — CMYK is stored inverted
            // SOF0..SOF15 carry the frame header; C4/C8/CC share the range but are not frames.
            if ($m >= 0xC0 && $m <= 0xCF && $m !== 0xC4 && $m !== 0xC8 && $m !== 0xCC) {
                if ($i + 8 > $len) break;
                return [
                    'h'     => (ord($d[$i + 3]) << 8) | ord($d[$i + 4]),
                    'w'     => (ord($d[$i + 5]) << 8) | ord($d[$i + 6]),
                    'nc'    => ord($d[$i + 7]),
                    'adobe' => $adobe,
                ];
            }
            $i += $seg;
        }
        return null;
    }

    /** Serialize all pages into a complete PDF byte string. */
    public function output(): string {
        $pages = $this->pages;
        $pages[] = $this->cur;

        // Fixed numbering: 1=Catalog 2=Pages 3=Font(regular) 4=Font(bold), then images, then
        // Page/Content pairs. Streams are [openingDict, bytes] — /Length is appended on write.
        $objs = [];
        $streams = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $id = 5;
        $xobj = '';
        foreach ($this->imgs as $name => $im) {
            $imgId = $id++;
            $cs = match ((int)$im['nc']) { 1 => '/DeviceGray', 4 => '/DeviceCMYK', default => '/DeviceRGB' };
            // Photoshop writes CMYK JPEGs inverted and flags it with APP14; without /Decode
            // the photo comes out as a colour negative.
            $decode = ((int)$im['nc'] === 4 && !empty($im['adobe'])) ? ' /Decode [1 0 1 0 1 0 1 0]' : '';
            $streams[$imgId] = ['<< /Type /XObject /Subtype /Image /Width '.(int)$im['w'].
                ' /Height '.(int)$im['h'].' /ColorSpace '.$cs.' /BitsPerComponent 8'.$decode.
                ' /Filter /DCTDecode', $im['data']];
            $xobj .= '/'.$name.' '.$imgId.' 0 R ';
        }
        $res = '/Font << /F1 3 0 R /F2 4 0 R >>'.($xobj !== '' ? ' /XObject << '.trim($xobj).' >>' : '');

        $kids = [];
        foreach ($pages as $content) {
            $pageId = $id++; $contentId = $id++;
            $kids[] = "$pageId 0 R";
            $objs[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_W.' '.self::PAGE_H.
                '] /Resources << '.$res.' >> /Contents '.$contentId.' 0 R >>';
            $streams[$contentId] = ['<<', $content];
        }
        $objs[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';

        $maxId = $id - 1;
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        for ($i = 1; $i <= $maxId; $i++) {
            $offsets[$i] = strlen($out);
            if (isset($streams[$i])) {
                [$dict, $data] = $streams[$i];
                $out .= "$i 0 obj\n{$dict} /Length ".strlen($data)." >>\nstream\n{$data}\nendstream\nendobj\n";
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
