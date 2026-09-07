<?php
/**
 * VESTRA — TrueType alt kume cikarici (yalnizca PDF'e gomme icin).
 *
 * NEDEN VAR: fatura cizici gomulu olmayan Helvetica + CP1252 kullaniyor ve o
 * kodlamada Cince/Japonca/Korece/Yunanca/Kirilce harf HIC YOK. 5 Eyl 2026'ya
 * kadar bu karakterler sessizce SORU ISARETINE donuyordu: Hong Kong'lu alicinin
 * kayitli sirket adi faturada "??????????" cikiyordu -- bozuk bir dosya degil,
 * MUSTERININ ADI EKSIK ama gecerli gorunen bir belge, ki pahali olan bu.
 *
 * Cozum yazi tipini GOMMEK. Tam bir CJK yazi tipi 11 MB; her faturaya
 * gomulseydi belge okunamaz buyuklukte olurdu. Bu sinif yalnizca BELGEDE GECEN
 * karakterlerin gliflerini iceren kucuk bir yazi tipi uretir (tipik bir fatura
 * icin ~10 KB): tablo dizinini okur, unicode->glif eslemesini cozer, bilesik
 * gliflerin parcalarini da toplar, glifleri yeniden numaralandirir ve
 * glyf/loca/head/hhea/hmtx/maxp tablolarini yeniden yazar.
 *
 * Kapsam BILEREK dar: yalnizca PDF'in CIDFontType2 olarak gommesi icin gereken
 * tablolar uretiliyor. Dikey yazim (vmtx), OpenType yerlestirme (GSUB/GPOS) ve
 * glif adlari (post) bir faturanin cizimine hicbir sey katmiyor.
 */

final class VestraTtf
{
    private $fh;
    private array $tabs = [];          // etiket => [offset, length]
    private int $unitsPerEm = 1000;
    private int $numGlyphs = 0;
    private int $indexToLocFormat = 0;
    private int $numberOfHMetrics = 0;
    private array $loca = [];          // gid => [offset, length] (glyf icinde)

    private function __construct($fh) { $this->fh = $fh; }

    public function __destruct() { if (is_resource($this->fh)) fclose($this->fh); }

    /**
     * Yazi tipini ac. TTC koleksiyonu da kabul edilir (ilk yuz alinir).
     * Okunamayan/desteklenmeyen dosyada null doner -- cagiran taraf o zaman
     * eski davranisa duser, fatura uretimi durmaz.
     */
    public static function open(string $path): ?self
    {
        if ($path === '' || !is_readable($path)) return null;
        $fh = @fopen($path, 'rb');
        if (!$fh) return null;
        $f = new self($fh);
        return $f->readDirectory() ? $f : null;
    }

    private function at(int $off, int $len): string
    {
        if ($len <= 0) return '';
        fseek($this->fh, $off);
        return (string)fread($this->fh, $len);
    }

    private static function u16(string $s, int $o): int { return (ord($s[$o]) << 8) | ord($s[$o + 1]); }
    private static function s16(string $s, int $o): int { $v = self::u16($s, $o); return $v >= 0x8000 ? $v - 0x10000 : $v; }
    private static function u32(string $s, int $o): int
    {
        return (ord($s[$o]) << 24) | (ord($s[$o + 1]) << 16) | (ord($s[$o + 2]) << 8) | ord($s[$o + 3]);
    }

    private function readDirectory(): bool
    {
        $head = $this->at(0, 12);
        if (strlen($head) < 12) return false;
        $base = 0;
        if (substr($head, 0, 4) === 'ttcf') {                 // koleksiyon: ilk yuz
            $n = self::u32($head, 8);
            if ($n < 1) return false;
            $base = self::u32($this->at(12, 4), 0);
            $head = $this->at($base, 12);
            if (strlen($head) < 12) return false;
        }
        $sfnt = self::u32($head, 0);
        /* 'OTTO' = CFF (PostScript) anahatlar; bu cikarici yalniz glyf tabanli
           TrueType anlar. Yanlis anlamak bozuk bir yazi tipi gomerdi. */
        if ($sfnt !== 0x00010000 && $sfnt !== 0x74727565) return false;
        $n = self::u16($head, 4);
        $dir = $this->at($base + 12, 16 * $n);
        if (strlen($dir) < 16 * $n) return false;
        for ($i = 0; $i < $n; $i++) {
            $o = 16 * $i;
            $this->tabs[rtrim(substr($dir, $o, 4))] = [self::u32($dir, $o + 8), self::u32($dir, $o + 12)];
        }
        foreach (['head', 'hhea', 'maxp', 'hmtx', 'loca', 'glyf', 'cmap'] as $need) {
            if (!isset($this->tabs[$need])) return false;
        }
        $h = $this->table('head');
        $this->unitsPerEm       = self::u16($h, 18) ?: 1000;
        $this->indexToLocFormat = self::s16($h, 50);
        $this->numGlyphs        = self::u16($this->table('maxp'), 4);
        $this->numberOfHMetrics = self::u16($this->table('hhea'), 34);
        return $this->numGlyphs > 0 && $this->readLoca();
    }

    private function table(string $tag): string
    {
        if (!isset($this->tabs[$tag])) return '';
        [$off, $len] = $this->tabs[$tag];
        return $this->at($off, $len);
    }

    private function readLoca(): bool
    {
        $raw = $this->table('loca');
        $n   = $this->numGlyphs;
        $offs = [];
        if ($this->indexToLocFormat === 0) {
            if (strlen($raw) < 2 * ($n + 1)) return false;
            for ($i = 0; $i <= $n; $i++) $offs[$i] = self::u16($raw, 2 * $i) * 2;
        } else {
            if (strlen($raw) < 4 * ($n + 1)) return false;
            for ($i = 0; $i <= $n; $i++) $offs[$i] = self::u32($raw, 4 * $i);
        }
        for ($i = 0; $i < $n; $i++) $this->loca[$i] = [$offs[$i], max(0, $offs[$i + 1] - $offs[$i])];
        return true;
    }

    /** Unicode kod noktasi -> glif numarasi. Yoksa 0. */
    public function gidFor(int $cp): int
    {
        static $map = null;
        if ($map === null) $map = $this->readCmap();
        return $map[$cp] ?? 0;
    }

    /**
     * cmap alt tablosu secimi: once (3,10) format 12 -- BMP disini de tasir
     * (nadir Cince adlarda gerekiyor), sonra (3,1) format 4.
     */
    private function readCmap(): array
    {
        $c = $this->table('cmap');
        if (strlen($c) < 4) return [];
        $n = self::u16($c, 2);
        $best = -1; $bestScore = -1;
        for ($i = 0; $i < $n; $i++) {
            $o   = 4 + 8 * $i;
            if ($o + 8 > strlen($c)) break;
            $pid = self::u16($c, $o); $eid = self::u16($c, $o + 2); $sub = self::u32($c, $o + 4);
            $score = ($pid === 3 && $eid === 10) ? 3 : (($pid === 3 && $eid === 1) ? 2 : (($pid === 0) ? 1 : 0));
            if ($score > $bestScore) { $bestScore = $score; $best = $sub; }
        }
        if ($best < 0 || $best + 4 > strlen($c)) return [];
        $fmt = self::u16($c, $best);
        $map = [];
        if ($fmt === 4) {
            $segX2 = self::u16($c, $best + 6);
            $seg   = intdiv($segX2, 2);
            $endO  = $best + 14;
            $staO  = $endO + $segX2 + 2;
            $delO  = $staO + $segX2;
            $ranO  = $delO + $segX2;
            for ($s = 0; $s < $seg; $s++) {
                $end = self::u16($c, $endO + 2 * $s);
                $sta = self::u16($c, $staO + 2 * $s);
                $del = self::u16($c, $delO + 2 * $s);
                $ran = self::u16($c, $ranO + 2 * $s);
                if ($sta > $end) continue;
                for ($u = $sta; $u <= $end && $u !== 0xFFFF; $u++) {
                    if ($ran === 0) { $g = ($u + $del) & 0xFFFF; }
                    else {
                        $gi = $ranO + 2 * $s + $ran + 2 * ($u - $sta);
                        if ($gi + 1 >= strlen($c)) continue;
                        $g = self::u16($c, $gi);
                        if ($g !== 0) $g = ($g + $del) & 0xFFFF;
                    }
                    if ($g !== 0) $map[$u] = $g;
                }
            }
        } elseif ($fmt === 12) {
            $ng = self::u32($c, $best + 12);
            for ($g = 0; $g < $ng; $g++) {
                $o = $best + 16 + 12 * $g;
                if ($o + 12 > strlen($c)) break;
                $sta = self::u32($c, $o); $end = self::u32($c, $o + 4); $gid = self::u32($c, $o + 8);
                if ($end - $sta > 0x10000) $end = $sta + 0x10000;   // bozuk tabloya karsi tavan
                for ($u = $sta; $u <= $end; $u++) $map[$u] = $gid + ($u - $sta);
            }
        }
        return $map;
    }

    /** Glif ilerleme genisligi, 1000 birimlik em'e olceklenmis. */
    public function advance1000(int $gid): int
    {
        $hm = $this->table('hmtx');
        $i  = min($gid, max(0, $this->numberOfHMetrics - 1));
        if (4 * $i + 2 > strlen($hm)) return 0;
        return (int)round(self::u16($hm, 4 * $i) * 1000 / $this->unitsPerEm);
    }

    /** FontDescriptor icin olculer (hepsi 1000 birimlik em'de). */
    public function metrics(): array
    {
        $h = $this->table('head'); $hh = $this->table('hhea');
        $s = fn(int $v) => (int)round($v * 1000 / $this->unitsPerEm);
        return [
            'bbox'   => [$s(self::s16($h, 36)), $s(self::s16($h, 38)), $s(self::s16($h, 40)), $s(self::s16($h, 42))],
            'ascent' => $s(self::s16($hh, 4)),
            'descent'=> $s(self::s16($hh, 6)),
        ];
    }

    /**
     * Verilen glif numaralari icin alt kume yazi tipi uretir.
     * Doner: ['font'=>ikili, 'map'=>[eskiGid=>yeniGid], 'widths'=>[yeniGid=>1000'lik genislik]]
     */
    public function subset(array $gids): ?array
    {
        $need = [0 => true];                                   // .notdef her zaman
        foreach ($gids as $g) if ($g > 0 && $g < $this->numGlyphs) $need[$g] = true;

        /* Bilesik glifler baska gliflere ATIF yapar (aksanli harfler, bazi CJK
           radikaller). Parcalari almadan gomersek harf BOS cikar -- soru
           isaretinden farksiz bir sessiz kayip. */
        $queue = array_keys($need);
        while ($queue) {
            $g = array_pop($queue);
            foreach ($this->componentsOf($g) as $c) {
                if ($c < $this->numGlyphs && !isset($need[$c])) { $need[$c] = true; $queue[] = $c; }
            }
        }
        $old = array_keys($need);
        sort($old);
        $map = [];
        foreach ($old as $i => $g) $map[$g] = $i;

        $glyf = ''; $offsets = []; $widths = [];
        foreach ($old as $g) {
            $offsets[] = strlen($glyf);
            $data = $this->glyphData($g, $map);
            $glyf .= $data . str_repeat("\0", (4 - strlen($data) % 4) % 4);
            $widths[$map[$g]] = $this->advance1000($g);
        }
        $offsets[] = strlen($glyf);

        $n    = count($old);
        $loca = '';
        foreach ($offsets as $o) $loca .= pack('N', $o);

        $head = $this->table('head');
        if (strlen($head) < 54) return null;
        $head = substr_replace($head, pack('N', 0), 8, 4);      // checkSumAdjustment
        $head = substr_replace($head, pack('n', 1), 50, 2);     // indexToLocFormat = long

        $hhea = substr_replace($this->table('hhea'), pack('n', $n), 34, 2);
        $maxp = substr_replace($this->table('maxp'), pack('n', $n), 4, 2);

        $hmtx = '';
        $hmSrc = $this->table('hmtx');
        foreach ($old as $g) {
            $i  = min($g, max(0, $this->numberOfHMetrics - 1));
            $aw = (4 * $i + 2 <= strlen($hmSrc)) ? self::u16($hmSrc, 4 * $i) : 0;
            $lsb = (4 * $i + 4 <= strlen($hmSrc)) ? self::s16($hmSrc, 4 * $i + 2) : 0;
            $hmtx .= pack('nn', $aw, $lsb & 0xFFFF);
        }

        $out = ['head' => $head, 'hhea' => $hhea, 'maxp' => $maxp,
                'hmtx' => $hmtx, 'loca' => $loca, 'glyf' => $glyf];
        foreach (['cvt ', 'fpgm', 'prep'] as $t) {              // ipuclama tablolari, varsa
            $d = $this->table($t);
            if ($d !== '') $out[$t] = $d;
        }
        return ['font' => self::buildSfnt($out), 'map' => $map, 'widths' => $widths];
    }

    /** Bilesik glifin dogrudan bilesenleri (yalnizca bir seviye; cagiran taraf kuyrukluyor). */
    private function componentsOf(int $gid): array
    {
        [$off, $len] = $this->loca[$gid] ?? [0, 0];
        if ($len < 10) return [];
        $d = $this->at($this->tabs['glyf'][0] + $off, $len);
        if (strlen($d) < 10 || self::s16($d, 0) >= 0) return [];   // basit glif
        $comps = []; $p = 10;
        while ($p + 4 <= strlen($d)) {
            $flags = self::u16($d, $p); $comps[] = self::u16($d, $p + 2);
            $p += 4;
            $p += ($flags & 0x0001) ? 4 : 2;                        // ARG_1_AND_2_ARE_WORDS
            if ($flags & 0x0008)      $p += 2;                      // WE_HAVE_A_SCALE
            elseif ($flags & 0x0040)  $p += 4;                      // X_AND_Y_SCALE
            elseif ($flags & 0x0080)  $p += 8;                      // TWO_BY_TWO
            if (!($flags & 0x0020)) break;                          // MORE_COMPONENTS
        }
        return $comps;
    }

    /** Glifin ham baytlari; bilesikse bilesen numaralari YENI numaralara cevrilir. */
    private function glyphData(int $gid, array $map): string
    {
        [$off, $len] = $this->loca[$gid] ?? [0, 0];
        if ($len <= 0) return '';
        $d = $this->at($this->tabs['glyf'][0] + $off, $len);
        if (strlen($d) < 10 || self::s16($d, 0) >= 0) return $d;
        $p = 10;
        while ($p + 4 <= strlen($d)) {
            $flags = self::u16($d, $p);
            $comp  = self::u16($d, $p + 2);
            $d = substr_replace($d, pack('n', $map[$comp] ?? 0), $p + 2, 2);
            $p += 4;
            $p += ($flags & 0x0001) ? 4 : 2;
            if ($flags & 0x0008)      $p += 2;
            elseif ($flags & 0x0040)  $p += 4;
            elseif ($flags & 0x0080)  $p += 8;
            if (!($flags & 0x0020)) break;
        }
        return $d;
    }

    /** Tablolari gecerli bir sfnt dosyasina dizer (dizin alfabetik, govde 4'e hizali). */
    private static function buildSfnt(array $tables): string
    {
        ksort($tables);
        $n  = count($tables);
        $sr = 1; while ($sr * 2 <= $n) $sr *= 2;
        $hdr = pack('Nnnnn', 0x00010000, $n, $sr * 16, (int)log($sr, 2), $n * 16 - $sr * 16);
        $off = 12 + 16 * $n;
        $dir = ''; $body = '';
        foreach ($tables as $tag => $data) {
            $pad = (4 - strlen($data) % 4) % 4;   // PHP modulosu negatif doner; sarmalanmali
            $dir .= pack('a4NNN', str_pad($tag, 4), self::checksum($data . str_repeat("\0", $pad)), $off, strlen($data));
            $body .= $data . str_repeat("\0", $pad);
            $off  += strlen($data) + $pad;
        }
        return $hdr . $dir . $body;
    }

    private static function checksum(string $d): int
    {
        $sum = 0;
        for ($i = 0, $n = strlen($d); $i < $n; $i += 4) $sum = ($sum + self::u32($d, $i)) & 0xFFFFFFFF;
        return $sum;
    }
}
