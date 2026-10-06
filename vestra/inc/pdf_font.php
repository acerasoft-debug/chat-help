<?php
/**
 * VESTRA — TrueType alt kümeleme (PDF'e gömülen CJK yazı tipi).
 *
 * NEDEN VAR: Belgeler gömülü olmayan Helvetica + WinAnsi (CP1252) ile çiziliyor.
 * CP1252 Batı Avrupa alfabesidir; Çince, Japonca, Korece, Yunanca, Kiril ve
 * Arapça HİÇBİR karakteri yoktur. 5 Eylül 2026'da kaydolan alıcının unvanı
 * "香港风徕贸易有限公司" faturaya "??????????" diye basılıyordu — geçerli
 * GÖRÜNEN ama müşterinin adını kaybetmiş bir fatura (operatör, 7 Eyl 2026:
 * "çin karakterlerini faturaya yazamıyorum ... fatura çin adresi ile çıksın").
 *
 * NASIL: assets/fonts/vestra-cjk.ttf (WenQuanYi Zen Hei alt kümesi, GPL v2 +
 * font istisnası — yanındaki LICENSE dosyasına bakın) sunucuda durur; her belge
 * yalnızca O BELGEDE GEÇEN glifleri gömer. Tam yazı tipi 10 MB'tır; tipik bir
 * faturada 30-60 glif geçer, yani gömülen parça birkaç KB'dır.
 *
 * Gömme biçimi: Type0 / Identity-H + CIDFontType2, CID = yeni glif numarası
 * (/CIDToGIDMap /Identity). Metin akışına glif numaraları onaltılık yazılır;
 * kopyalanabilirlik ve pdftotext için ayrıca ToUnicode CMap üretilir — bir
 * faturadan adres kopyalanamıyorsa belge yarım demektir.
 *
 * Bilinçli sınır: yazı tipi kapsamı Latin + Yunanca + Kiril + kana + Hangul +
 * CJK (Ext A dahil). Emoji ve daha nadir düzlemler YOK; onlar hâlâ
 * vestra_pdf_unrenderable() ile ölçülür ve taslakta operatöre yazılır. Sessiz
 * kayıp bu depoda tekrar eden ders: ölçüp söylemek, sessizce kaybetmekten iyi.
 */

function vestra_cjk_font_path(): string { return dirname(__DIR__).'/assets/fonts/vestra-cjk.ttf'; }

final class VestraTtf {
    private $fh;
    private array $tables = [];          // etiket => ['off'=>, 'len'=>]
    private array $cmap = [];            // unicode => eski glif no
    private array $loca = [];            // glif no => bayt konumu
    private array $hmtx = [];            // glif no => ilerleme (font birimi)
    private int $upem = 1000, $numGlyphs = 0, $numHMetrics = 0;
    private array $head = [], $hhea = [], $maxp = [], $os2 = [];
    private array $glyphCache = [];

    private function __construct($fh) { $this->fh = $fh; }

    /** Paylasilan yazi tipi; dosya yoksa null (belge Helvetica ile cizilmeye devam eder). */
    public static function shared(): ?self {
        static $inst = null, $tried = false;
        if ($tried) return $inst;
        $tried = true;
        $p = vestra_cjk_font_path();
        if (!is_readable($p)) return null;
        $inst = self::open($p);
        return $inst;
    }

    public static function open(string $path): ?self {
        $fh = @fopen($path, 'rb');
        if (!$fh) return null;
        $f = new self($fh);
        return $f->readDirectory() ? $f : null;
    }

    private function at(int $off, int $len): string {
        if ($len <= 0) return '';
        fseek($this->fh, $off);
        return (string)fread($this->fh, $len);
    }
    private function table(string $tag): string {
        if (!isset($this->tables[$tag])) return '';
        return $this->at($this->tables[$tag]['off'], $this->tables[$tag]['len']);
    }
    private static function u16(string $s, int $o): int { return (ord($s[$o]) << 8) | ord($s[$o + 1]); }
    private static function s16(string $s, int $o): int { $v = self::u16($s, $o); return $v >= 0x8000 ? $v - 0x10000 : $v; }
    private static function u32(string $s, int $o): int {
        return (ord($s[$o]) << 24) | (ord($s[$o + 1]) << 16) | (ord($s[$o + 2]) << 8) | ord($s[$o + 3]);
    }

    private function readDirectory(): bool {
        $h = $this->at(0, 12);
        if (strlen($h) < 12) return false;
        $tag = substr($h, 0, 4);
        /* .ttc (koleksiyon) bilerek DESTEKLENMIYOR: gonderdigimiz dosya tek
           yuzlu bir .ttf. Sessizce yanlis yuzu okumaktansa hic acmamak iyi. */
        if ($tag !== "\x00\x01\x00\x00" && $tag !== 'true') return false;
        $n = self::u16($h, 4);
        $dir = $this->at(12, $n * 16);
        if (strlen($dir) < $n * 16) return false;
        for ($i = 0; $i < $n; $i++) {
            $rec = substr($dir, $i * 16, 16);
            $this->tables[substr($rec, 0, 4)] = ['off' => self::u32($rec, 8), 'len' => self::u32($rec, 12)];
        }
        $head = $this->table('head');
        $hhea = $this->table('hhea');
        $maxp = $this->table('maxp');
        if (strlen($head) < 54 || strlen($hhea) < 36 || strlen($maxp) < 6) return false;
        $this->upem        = self::u16($head, 18) ?: 1000;
        $this->numGlyphs   = self::u16($maxp, 4);
        $this->numHMetrics = self::u16($hhea, 34);
        $this->head = ['raw' => $head, 'indexToLocFormat' => self::s16($head, 50),
                       'xMin' => self::s16($head, 36), 'yMin' => self::s16($head, 38),
                       'xMax' => self::s16($head, 40), 'yMax' => self::s16($head, 42)];
        $this->hhea = ['raw' => $hhea, 'ascent' => self::s16($hhea, 4), 'descent' => self::s16($hhea, 6)];
        $this->maxp = ['raw' => $maxp];
        $this->os2  = ['raw' => $this->table('OS/2')];
        return $this->numGlyphs > 0 && isset($this->tables['glyf'], $this->tables['loca']);
    }

    /* ── cmap: format 4 (BMP) ve format 12 (tam) ─────────────────────────── */
    private function loadCmap(): void {
        if ($this->cmap) return;
        $c = $this->table('cmap');
        if ($c === '') { $this->cmap = [0 => 0]; return; }
        $n = self::u16($c, 2);
        $best = -1; $bestScore = -1;
        for ($i = 0; $i < $n; $i++) {
            $pid = self::u16($c, 4 + $i * 8); $eid = self::u16($c, 6 + $i * 8);
            $off = self::u32($c, 8 + $i * 8);
            if ($off + 2 > strlen($c)) continue;
            $fmt = self::u16($c, $off);
            /* Sıra: (3,10) fmt12 > (3,1) fmt4 > (0,x). Yalnizca bu ikisi okunuyor. */
            $score = ($pid === 3 && $eid === 10 && $fmt === 12) ? 3
                   : (($pid === 3 && $eid === 1 && $fmt === 4) ? 2
                   : (($pid === 0 && ($fmt === 4 || $fmt === 12)) ? 1 : -1));
            if ($score > $bestScore) { $bestScore = $score; $best = $off; }
        }
        if ($best < 0) { $this->cmap = [0 => 0]; return; }
        $fmt = self::u16($c, $best);
        $map = [];
        if ($fmt === 4) {
            $segX2 = self::u16($c, $best + 6);
            $seg = intdiv($segX2, 2);
            $endO = $best + 14; $startO = $endO + $segX2 + 2; $deltaO = $startO + $segX2; $rangeO = $deltaO + $segX2;
            for ($s = 0; $s < $seg; $s++) {
                $end = self::u16($c, $endO + $s * 2);
                $sta = self::u16($c, $startO + $s * 2);
                $del = self::u16($c, $deltaO + $s * 2);
                $ro  = self::u16($c, $rangeO + $s * 2);
                if ($sta > $end) continue;
                for ($u = $sta; $u <= $end && $u !== 0xFFFF; $u++) {
                    if ($ro === 0) { $g = ($u + $del) & 0xFFFF; }
                    else {
                        $gi = $rangeO + $s * 2 + $ro + ($u - $sta) * 2;
                        if ($gi + 1 >= strlen($c)) continue;
                        $g = self::u16($c, $gi);
                        if ($g !== 0) $g = ($g + $del) & 0xFFFF;
                    }
                    if ($g) $map[$u] = $g;
                }
            }
        } elseif ($fmt === 12) {
            $ng = self::u32($c, $best + 12);
            for ($i = 0; $i < $ng; $i++) {
                $o = $best + 16 + $i * 12;
                if ($o + 12 > strlen($c)) break;
                $sc = self::u32($c, $o); $ec = self::u32($c, $o + 4); $sg = self::u32($c, $o + 8);
                if ($ec - $sc > 0x10FFFF) break;
                for ($u = $sc; $u <= $ec; $u++) $map[$u] = $sg + ($u - $sc);
            }
        }
        $this->cmap = $map ?: [0 => 0];
    }

    private function loadLoca(): void {
        if ($this->loca) return;
        $raw = $this->table('loca');
        $long = $this->head['indexToLocFormat'] === 1;
        $n = $this->numGlyphs + 1;
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[$i] = $long
                ? (($i * 4 + 4 <= strlen($raw)) ? self::u32($raw, $i * 4) : 0)
                : ((($i * 2 + 2 <= strlen($raw)) ? self::u16($raw, $i * 2) : 0) * 2);
        }
        $this->loca = $out;
    }

    private function loadHmtx(): void {
        if ($this->hmtx) return;
        $raw = $this->table('hmtx');
        $adv = []; $last = 0;
        for ($i = 0; $i < $this->numGlyphs; $i++) {
            if ($i < $this->numHMetrics) {
                $o = $i * 4;
                $last = ($o + 2 <= strlen($raw)) ? self::u16($raw, $o) : $last;
                $lsb  = ($o + 4 <= strlen($raw)) ? self::s16($raw, $o + 2) : 0;
            } else {
                $o = $this->numHMetrics * 4 + ($i - $this->numHMetrics) * 2;
                $lsb = ($o + 2 <= strlen($raw)) ? self::s16($raw, $o) : 0;
            }
            $adv[$i] = [$last, $lsb];
        }
        $this->hmtx = $adv;
    }

    private function glyphData(int $gid): string {
        if (isset($this->glyphCache[$gid])) return $this->glyphCache[$gid];
        $this->loadLoca();
        if ($gid < 0 || $gid >= $this->numGlyphs) return '';
        $off = $this->loca[$gid]; $end = $this->loca[$gid + 1];
        $d = ($end > $off) ? $this->at($this->tables['glyf']['off'] + $off, $end - $off) : '';
        if (count($this->glyphCache) < 4000) $this->glyphCache[$gid] = $d;
        return $d;
    }

    /* Bileşik glifin başvurduğu glif numaraları (Hangul hecelerinin tamamı ve
       aksanlı Latin harfler bileşiktir — bileşen atlanırsa glif BOŞ çizilir). */
    private function components(string $g): array {
        if (strlen($g) < 10 || self::s16($g, 0) >= 0) return [];
        $out = []; $o = 10;
        while ($o + 4 <= strlen($g)) {
            $flags = self::u16($g, $o); $idx = self::u16($g, $o + 2);
            $out[] = [$o + 2, $idx];
            $o += 4;
            $o += ($flags & 0x0001) ? 4 : 2;                       // ARG_1_AND_2_ARE_WORDS
            if ($flags & 0x0008)      $o += 2;                     // WE_HAVE_A_SCALE
            elseif ($flags & 0x0040)  $o += 4;                     // X_AND_Y_SCALE
            elseif ($flags & 0x0080)  $o += 8;                     // TWO_BY_TWO
            if (!($flags & 0x0020)) break;                         // MORE_COMPONENTS
        }
        return $out;
    }

    public function unitsPerEm(): int { return $this->upem; }
    public function gidFor(int $u): int { $this->loadCmap(); return $this->cmap[$u] ?? 0; }
    public function hasChar(int $u): bool { return $this->gidFor($u) > 0; }

    /** Karakterin ilerlemesi, 1000 birimlik PDF metin uzayinda. */
    public function advance1000(int $u): int {
        $this->loadHmtx();
        $g = $this->gidFor($u);
        $a = $this->hmtx[$g][0] ?? 0;
        return (int)round($a * 1000 / $this->upem);
    }

    public function descriptor(): array {
        $s = 1000 / $this->upem;
        return [
            'bbox'   => [(int)round($this->head['xMin'] * $s), (int)round($this->head['yMin'] * $s),
                         (int)round($this->head['xMax'] * $s), (int)round($this->head['yMax'] * $s)],
            'ascent' => (int)round($this->hhea['ascent'] * $s),
            'descent'=> (int)round($this->hhea['descent'] * $s),
        ];
    }

    /**
     * $unicodes: CID sırasıyla (CID 1 = ilk karakter). Dönen 'data' gömülmeye
     * hazır bir TrueType; CID = yeni glif numarası, yani /CIDToGIDMap /Identity.
     *
     * @return array{data:string,widths:array<int,int>,glyphs:int}
     */
    public function subset(array $unicodes): array {
        $this->loadLoca(); $this->loadHmtx(); $this->loadCmap();

        /* 1) Yeni glif sırası: 0 = .notdef, sonra istenen karakterler. */
        $order = [0];                     // yeni glif no => eski glif no
        $seen  = [0 => 0];                // eski => yeni
        $widths = [];
        foreach ($unicodes as $i => $u) {
            $g = $this->gidFor($u);
            /* AYNI eski glife düşen iki karakter bile AYRI CID alır: CID sırası
               çağıranın metin akışında yazdığı sıradır, burada birleştirmek
               akışı kaydırırdı. */
            $order[] = $g;
            $cid = $i + 1;
            $widths[$cid] = (int)round(($this->hmtx[$g][0] ?? 0) * 1000 / $this->upem);
            if (!isset($seen[$g])) $seen[$g] = $cid;
        }

        /* 2) Bileşenlerin kapanışı: eksik bileşen = boş çizilen glif. */
        for ($i = 0; $i < count($order); $i++) {
            foreach ($this->components($this->glyphData($order[$i])) as [, $idx]) {
                if (!isset($seen[$idx])) { $seen[$idx] = count($order); $order[] = $idx; }
            }
        }

        /* 3) glyf + loca: bileşiklerin bileşen numaraları YENİ numaralara yazılır. */
        $glyf = ''; $loca = [0];
        foreach ($order as $newGid => $oldGid) {
            $d = $this->glyphData($oldGid);
            if ($d !== '' && self::s16($d, 0) < 0) {
                foreach ($this->components($d) as [$pos, $idx]) {
                    $new = $seen[$idx] ?? 0;
                    $d[$pos]     = chr(($new >> 8) & 0xFF);
                    $d[$pos + 1] = chr($new & 0xFF);
                }
            }
            if (strlen($d) % 4) $d .= str_repeat("\0", 4 - strlen($d) % 4);
            $glyf .= $d;
            $loca[] = strlen($glyf);
        }
        $n = count($order);

        $locaT = '';
        foreach ($loca as $o) $locaT .= pack('N', $o);

        /* 4) hmtx: her glif için tam ölçü (numberOfHMetrics = glif sayısı). */
        $hmtx = '';
        foreach ($order as $oldGid) {
            [$adv, $lsb] = $this->hmtx[$oldGid] ?? [0, 0];
            $hmtx .= pack('n', $adv).pack('n', $lsb < 0 ? $lsb + 0x10000 : $lsb);
        }

        $head = $this->head['raw'];
        $head = substr($head, 0, 8)."\0\0\0\0".substr($head, 12);          // checkSumAdjustment = 0
        $head = substr($head, 0, 50).pack('n', 1).substr($head, 52);       // indexToLocFormat = uzun

        $hhea = $this->hhea['raw'];
        $hhea = substr($hhea, 0, 34).pack('n', $n).substr($hhea, 36);      // numberOfHMetrics

        $maxp = $this->maxp['raw'];
        $maxp = substr($maxp, 0, 4).pack('n', $n).substr($maxp, 6);        // numGlyphs

        $tabs = ['glyf' => $glyf, 'head' => $head, 'hhea' => $hhea, 'hmtx' => $hmtx, 'loca' => $locaT, 'maxp' => $maxp];
        return ['data' => self::buildSfnt($tabs), 'widths' => $widths, 'glyphs' => $n];
    }

    private static function checksum(string $t): int {
        if (strlen($t) % 4) $t .= str_repeat("\0", 4 - strlen($t) % 4);
        $sum = 0;
        for ($i = 0, $l = strlen($t); $i < $l; $i += 4) $sum = ($sum + self::u32($t, $i)) & 0xFFFFFFFF;
        return $sum;
    }

    private static function buildSfnt(array $tabs): string {
        ksort($tabs);
        $n = count($tabs);
        $sr = 1; $es = 0;
        while ($sr * 2 <= $n) { $sr *= 2; $es++; }
        $sr *= 16;
        $out = pack('N', 0x00010000).pack('nnnn', $n, $sr, $es, $n * 16 - $sr);
        $off = 12 + $n * 16;
        $dir = ''; $body = '';
        foreach ($tabs as $tag => $data) {
            $pad = (strlen($data) % 4) ? str_repeat("\0", 4 - strlen($data) % 4) : '';
            $dir .= $tag.pack('N', self::checksum($data)).pack('N', $off).pack('N', strlen($data));
            $body .= $data.$pad;
            $off += strlen($data) + strlen($pad);
        }
        return $out.$dir.$body;
    }
}
