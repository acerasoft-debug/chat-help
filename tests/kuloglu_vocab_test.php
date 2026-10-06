<?php
/* Kuloğlu import: Turkish -> 8-language vocabulary dictionary
 * (product-batches/kuloglu-vocab.php). Not 638 unique translation jobs —
 * titles are formulaic (brand + model number + recurring descriptive words)
 * and there's no free-text description field, so the whole translation
 * surface is this finite vocabulary, built from a real `kuloglu: "vocab"`
 * scan (4 Sep 2026) of all 638 crawled Bayan Sütyen records.
 *
 * Two things a manually-written 152-entry x 8-language table can silently
 * get wrong, same class of mistake as every t()-completeness check
 * elsewhere in this suite:
 *   1. A missing language column on one row falls back to the Turkish key
 *      itself at render time — same silent-English(here: silent-Turkish)-
 *      fallback risk seo_landing_test.php already guards for site UI strings.
 *   2. A duplicate PHP array key silently keeps only the LAST occurrence —
 *      counting entries wouldn't catch this (both duplicates would just
 *      resolve to the same final value), so this reads the raw source text.
 */
$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

$data = require __DIR__.'/../product-batches/kuloglu-vocab.php';
$LANGS = ['en','fr','es','it','de','pt','ru','ar'];

// vestra_colors() itself, so the palette_map cross-check below is against the
// REAL live palette, not a hand-copied list that could drift out of sync with it.
$psrc = file_get_contents(__DIR__.'/../vestra/inc/products.php');
if (!preg_match('/^function vestra_colors\(\).*?^}/ms', $psrc, $pm)) { echo "HATA: vestra_colors bulunamadi\n"; exit(1); }
eval($pm[0]);
$PALETTE_KEYS = array_keys(vestra_colors());

echo "-- her tablo her satirda 8 dilin hepsi dolu --\n";
foreach (['categories','colors','words'] as $table) {
    $missing = 0;
    foreach ($data[$table] as $key => $row) {
        foreach ($LANGS as $l) if (empty($row[$l])) $missing++;
    }
    $t("$table: eksik dil alani yok (0 bos)", $missing === 0);
}

echo "\n-- kategori tablosunda vestra_cat (Bras|Shapewear) her satirda var --\n";
$missingCat = 0;
foreach ($data['categories'] as $key => $row) {
    if (empty($row['vestra_cat']) || !in_array($row['vestra_cat'], ['Bras','Shapewear'], true)) $missingCat++;
}
$t('vestra_cat her satirda Bras/Shapewear', $missingCat === 0);

echo "\n-- kaynak dosyada yinelenen anahtar yok (PHP sessizce SONUNCUYU tutar) --\n";
$src = file_get_contents(__DIR__.'/../product-batches/kuloglu-vocab.php');
// KULOGLU_CATEGORIES/COLORS/WORDS: one entry per LINE, each value itself a
// ['en'=>..,'fr'=>..] array -- the line-start anchor is what keeps those
// nested language keys from being counted as if they were top-level entries.
// KULOGLU_COLOR_PALETTE_MAP: several flat 'key' => 'value' pairs PER line
// (no nested array inside a value to accidentally match), so it needs no
// anchor instead -- an anchored regex would silently undercount it by only
// checking the first pair on each line.
foreach ([
    'KULOGLU_CATEGORIES' => ['n'=>7, 'anchored'=>true],
    'KULOGLU_COLORS' => ['n'=>57, 'anchored'=>true],
    'KULOGLU_WORDS' => ['n'=>88, 'anchored'=>true],
    'KULOGLU_COLOR_PALETTE_MAP' => ['n'=>57, 'anchored'=>false],
] as $const => $spec) {
    preg_match('/const '.$const.' = \[(.*?)\n\];/s', $src, $m);
    $pattern = $spec['anchored'] ? "/^\s*'([^']+)'\s*=>/m" : "/'([^']+)'\s*=>/";
    preg_match_all($pattern, $m[1], $mm);
    $keys = $mm[1];
    $t("$const: {$spec['n']} satir, hepsi essiz (yinelenen yok)", count($keys) === $spec['n'] && count($keys) === count(array_unique($keys)));
}

echo "\n-- KULOGLU_COLOR_PALETTE_MAP: her deger GERCEK vestra_colors() anahtari --\n";
$badTarget = [];
foreach ($data['palette_map'] as $tr => $en) { if (!in_array($en, $PALETTE_KEYS, true)) $badTarget[$tr] = $en; }
$t('her hedef gercek palette anahtari', count($badTarget) === 0);

echo "\n-- her KULOGLU_COLORS anahtari icin palette_map'te bir karsilik var --\n";
$noMap = array_filter(array_keys($data['colors']), fn($k) => !isset($data['palette_map'][$k]));
$t('KULOGLU_COLORS \ palette_map = bos kume', count($noMap) === 0);

echo "\n-- yeni eklenen 5 renk gercekten palette'te (var olanlari BOZMADAN eklendi) --\n";
$t('Nude/Mink/Purple/Plum/Salmon palette\'te', count(array_intersect(['Nude','Mink','Purple','Plum','Salmon'], $PALETTE_KEYS)) === 5);
$t('eski 18 renk hala orada (Black..Fuchsia)', count(array_intersect(['Black','Navy','Blue','Light Blue','White','Grey','Dark Grey','Red','Bordeaux','Green','Beige','Pink','Yellow','Orange','Brown','Cream','Khaki','Fuchsia'], $PALETTE_KEYS)) === 18);

echo "\n-- gercek vocab taramasindaki (4 Eyl 2026) her kategori/renk/kelime sozlukte var --\n";
$expectCategories = ['DESTEKSİZ SÜTYEN','BAYAN SÜTYEN','DESTEKLİ SÜTYEN','TOPARLAYICI SÜTYEN','LAZER SÜTYEN','EMZİRME SÜTYENİ','SİLİKON SÜTYEN'];
$expectColorsSample = ['SİYAH','BEYAZ','TEN','GÜL KURUSU','NEON YEŞİLİ','FÜME','BEBE MAVİSİ','LEOPAR','MİNT']; // spot sample, not the full 57
$expectWordsSample = ['SÜTYEN','DESTEKSİZ','TOPARLAYICI','BÜSTİYER','KAŞKORSE','BALENSİZ','HAYALET','YIKAMA']; // spot sample, not the full 88

$missing = array_filter($expectCategories, fn($k) => !isset($data['categories'][$k]));
$t('7 kategorinin hepsi sozlukte', count($missing) === 0);
$missing = array_filter($expectColorsSample, fn($k) => !isset($data['colors'][$k]));
$t('renk orneklemesi sozlukte', count($missing) === 0);
$missing = array_filter($expectWordsSample, fn($k) => !isset($data['words'][$k]));
$t('kelime orneklemesi sozlukte', count($missing) === 0);

echo "\n-- bilerek CEVRILMEYEN kelimeler sozlukte YOK (marka parcasi/tekil harf/ozel isim) --\n";
$notExpected = ['LE','JARDİN','BEST','R.Y','LOTUS','BELLA','MASSIMA','C','B','D'];
$found = array_filter($notExpected, fn($k) => isset($data['words'][$k]) || isset($data['colors'][$k]));
$t('kasitli disarida birakilanlar gercekten yok', count($found) === 0);

echo "\n" . $ok . " ok, " . $fail . " hata\n";
exit($fail > 0 ? 1 : 0);
