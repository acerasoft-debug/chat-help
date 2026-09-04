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
foreach (['KULOGLU_CATEGORIES'=>7, 'KULOGLU_COLORS'=>56, 'KULOGLU_WORDS'=>88] as $const => $expectN) {
    preg_match('/const '.$const.' = \[(.*?)\n\];/s', $src, $m);
    preg_match_all("/^\s*'([^']+)'\s*=>/m", $m[1], $mm);
    $keys = $mm[1];
    $t("$const: $expectN satir, hepsi essiz (yinelenen yok)", count($keys) === $expectN && count($keys) === count(array_unique($keys)));
}

echo "\n-- gercek vocab taramasindaki (4 Eyl 2026) her kategori/renk/kelime sozlukte var --\n";
$expectCategories = ['DESTEKSİZ SÜTYEN','BAYAN SÜTYEN','DESTEKLİ SÜTYEN','TOPARLAYICI SÜTYEN','LAZER SÜTYEN','EMZİRME SÜTYENİ','SİLİKON SÜTYEN'];
$expectColorsSample = ['SİYAH','BEYAZ','TEN','GÜL KURUSU','NEON YEŞİLİ','FÜME','BEBE MAVİSİ','LEOPAR']; // spot sample, not the full 56
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
