<?php
/* Kart escrow tavani TEK yerde yasar: VESTRA_ESCROW_MAX (inc/products.php).
 * 2 Eyl 2026'da olculen ayrisma: sabit 3500 iken fiyat listesi sayfalari, Excel
 * ve kampanya mektuplari "EUR 3,000" diyordu -- musteriye soylenen ile sepetin
 * kabul ettigi farkliydi. Operator karari: 3000. Bu test:
 *   1. sabitin 3000 oldugunu,
 *   2. escrow'dan soz eden HER acik metindeki rakamin sabitle ayni oldugunu
 *      (sayfalar, Excel/PDF uretecleri, kampanya mektuplari, ceviri dosyalari),
 *   3. escrow_info mektubunun rakami parametreden bastigini (metne gomulu degil)
 * tutar. Rakam degisecekse once sabit, sonra bu testin gosterdigi yerler.
 */
$root = dirname(__DIR__);
$src  = file_get_contents($root.'/vestra/inc/products.php');
if (!preg_match("/define\\('VESTRA_ESCROW_MAX',\\s*([0-9.]+)\\)/", $src, $m)) { echo "HATA: VESTRA_ESCROW_MAX bulunamadi\n"; exit(1); }
$cap = (float)$m[1];
if (!preg_match("/define\\('VESTRA_ESCROW_FEE_BUYER',\\s*([0-9.]+)\\)/", $src, $m)) { echo "HATA: VESTRA_ESCROW_FEE_BUYER bulunamadi\n"; exit(1); }
$fee = (float)$m[1];

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

echo "-- sabit --\n";
$t('VESTRA_ESCROW_MAX = 3000 (operator karari, 2 Eyl 2026)', $cap === 3000.0);

echo "-- acik metinler sabitle ayni --\n";
/* Rakam, "escrow" gecen satirin icinde aranir: "EUR 3,000", "€3,000", "3.000 €",
   "3 000 EUR" hepsi ayni sayi. Ayiraclar atilip karsilastirilir. */
$files = array_merge(
  [$root.'/vestra/price-list.php', $root.'/vestra/price-lists.php', $root.'/vestra/wholesale-list.php',
   $root.'/vestra/wholesale-xlsx.php', $root.'/.github/workflows/send-campaign-preview.yml',
   $root.'/vestra/inc/notify.php', $root.'/vestra/inc/email_templates.php'],
  glob($root.'/vestra/inc/lang/*.php') ?: []
);
$want = (string)(int)$cap;
$found = 0;
foreach ($files as $f) {
    if (!is_file($f)) continue;
    foreach (file($f) as $no => $line) {
        if (stripos($line, 'escrow') === false) continue;
        if (!preg_match_all('/(?:EUR|€)\s?(\d[\d.,\s]{2,})|(\d[\d.,\s]{2,}\d)\s?(?:EUR|€)/u', $line, $mm, PREG_SET_ORDER)) continue;
        foreach ($mm as $x) {
            $num = preg_replace('/\D/', '', $x[1] !== '' ? $x[1] : $x[2]);
            if ($num === '' || strlen($num) < 4) continue;   // "3.8%" gibi ucret rakamlari degil
            $found++;
            $t(basename($f).':'.($no+1).' -> '.$num, $num === $want);
        }
    }
}
$t('en az bir acik metin tarandi ('.$found.')', $found >= 4);

echo "-- escrow_info sablonu --\n";
$tsrc = file_get_contents($root.'/vestra/inc/email_templates.php');
if (!preg_match('/^function vestra_tpl_escrow_info\(.*?^}/ms', $tsrc, $m)) { echo "HATA: vestra_tpl_escrow_info bulunamadi\n"; exit(1); }
eval($m[0]);
[$s, $b, $o] = vestra_tpl_escrow_info('Dear Test Buyer', $cap, $fee, 'Marco Bellini');
$t('konu tavani sabitten basar',                 str_contains($s, '€3,000'));
$t('govde tavani sabitten basar',                substr_count($b, '€3,000') >= 2);
$t('ucret sabitten (%3.8)',                       str_contains($b, '3.8%'));
$t('sablonda gomulu rakam yok',                   !preg_match('/\b3[,.]?[05]00\b/', $m[0]));
$t('kapi/gate metni: giris linki',                str_contains($b, 'https://vestrasales.com/login'));
$t('persona imzalar',                             str_contains($b, "Marco Bellini\nVESTRA"));
$t('farkli tavan -> farkli metin (parametre)',    str_contains(vestra_tpl_escrow_info('Dear X', 2500.0, $fee)[1], '€2,500'));
$t('Turkce karakter yok',                         !preg_match('/[şğıİçöüŞĞÇÖÜ]/u', $s.$b));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
