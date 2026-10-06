<?php
/* send-outreach.yml uye kipi: member_spec brand_orders= (1 Eki 2026, operator:
 * "F.Perry siparis verenler haric hepsine gonder").
 *
 * Is akisindaki GERCEK bloklari cikarip sentetik siparis/teklif/ilan verisi
 * uzerinde kosturuyor -- kaynakta "brand_orders" kelimesini gormek olcum degil.
 *
 * IKI YONU DE tutuyor: Fred Perry SIPARISI verenler ELENIR; Fred Perry TEKLIFI
 * verip siparis vermeyen, baska marka siparis veren ve hic siparisi olmayan
 * GECER (tek yon yazilsaydi "herkesi eleyen" bir hata da yesil kalirdi). Ayrica
 * brand_orders VERILMEZSE davranis ONCEKININ AYNISI.
 */
$yml = file_get_contents(__DIR__.'/../.github/workflows/send-outreach.yml');
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };

/* Yukleyici blok: $BRAND_ORD = []; ... marka siparisi: ... echo satiri + kapanis. */
if (!preg_match('/(\$BRAND_ORD = \[\];.*?marka siparisi: ".*?\\\\n";\s*\n\s*\})/s', $yml, $lm)) { echo "HATA: yukleyici blok bulunamadi\n"; exit(1); }
$loader = $lm[1];
/* Dongudaki eleme satiri. */
if (!preg_match('/^\s*(if \(\$BRAND_ORD && isset\(\$ordBrand\[\$em\]\).*?continue; \})\s*$/m', $yml, $fm)) { echo "HATA: eleme satiri bulunamadi\n"; exit(1); }
$filter = $fm[1];

/* Sentetik veri: vestra_listings() ve vestra_read_csv() yerine gecen taslaklar. */
$GLOBALS['T_LISTINGS'] = [
    ['id' => 'fp-m3600-polo', 'sku' => 'M3600', 'brand' => 'Fred Perry'],
    ['id' => 'fp-m7535-sweat', 'sku' => 'M7535', 'brand' => 'Fred Perry'],
    ['id' => 'fp-old-id', 'sku' => '', 'brand' => 'FRED PERRY'],           // eski ilan: yalniz id, kucuk/buyuk harf farki
    ['id' => 'lac-monogram-polo', 'sku' => 'DH1417', 'brand' => 'Lacoste'],
    ['id' => 'fred-perry-kids', 'sku' => 'FPK1', 'brand' => 'Fred Perry Kids'], // TUZAK: alt dize marka
    ['id' => 'dsq-1', 'sku' => 'S74GD1399', 'brand' => 'DSQUARED2'],
];
$GLOBALS['T_CSV'] = [
    'orders.csv' => [
        ['email' => 'fp-order@x.test',   'items' => '104x TH6710 @19.9 | 60x M7535 @36 | 100x M3600 @35.5'],
        ['email' => 'FP-Case@X.test',    'items' => '20x m3600 @35.5'],                        // buyuk/kucuk harf
        ['email' => 'fp-oldid@x.test',   'items' => '10x fp-old-id @30.00'],                    // ilan id'si
        ['email' => 'lac-only@x.test',   'items' => '80x DH1417 @31.00'],                       // baska marka
        ['email' => 'kids-only@x.test',  'items' => '10x FPK1 @20.00'],                         // "Fred Perry Kids" != "Fred Perry"
        ['email' => 'dsq-only@x.test',   'items' => '20x S74GD1399 @35.00 | 20x G8OB1TG7B2M @60.00'],
        ['email' => '',                  'items' => '20x M3600 @35.5'],                         // adressiz satir
        ['email' => 'spaced@x.test',     'items' => '10x G9XH2Z G7D0E @120.00 | 10x M7535 @36'],// bosluklu SKU yaninda FP
    ],
    'offers.csv' => [
        ['email' => 'fp-offer@x.test',   'sku' => 'M3600'],                                     // yalniz TEKLIF
        ['email' => 'fp-order@x.test',   'sku' => 'M3600'],
    ],
];
function vestra_listings() { return $GLOBALS['T_LISTINGS']; }
function vestra_read_csv($n) { return $GLOBALS['T_CSV'][$n] ?? []; }

$run = function (string $spec, array $emails) use ($loader, $filter): array {
    $mspec = $spec === '' ? [] : ['brand_orders' => $spec];
    ob_start(); eval($loader); $loaderOut = ob_get_clean();
    $maskMail = fn($e) => preg_replace('/^(.).*(@.*)$/', '$1***$2', $e);
    $pass = []; $offOnly = 0;
    foreach ($emails as $em) {
        ob_start();
        $passed = eval('foreach ([0] as $_) { '.$filter.' return true; } return false;');
        ob_end_clean();
        if ($passed) { $pass[] = $em; if ($BRAND_ORD && isset($offBrand[$em])) $offOnly++; }
    }
    return [$pass, $brandOrdHit, $offOnly, $loaderOut, $ordBrand];
};

$emails = ['fp-order@x.test', 'fp-case@x.test', 'fp-oldid@x.test', 'lac-only@x.test', 'kids-only@x.test',
           'dsq-only@x.test', 'spaced@x.test', 'fp-offer@x.test', 'never@x.test'];

echo "\n== 1. Fred Perry SIPARISI verenler elenir, digerleri GECER ==\n";
[$p, $hit, $off, $out] = $run('Fred Perry', $emails);
$t('SKU ile siparis veren elenir',                !in_array('fp-order@x.test', $p, true));
$t('SKU kucuk/buyuk harf farki elenir',           !in_array('fp-case@x.test', $p, true));
$t('ilan id\'si ile siparis veren elenir',        !in_array('fp-oldid@x.test', $p, true));
$t('bosluklu SKU yaninda FP olan elenir',         !in_array('spaced@x.test', $p, true));
$t('Lacoste siparisi veren GECER',                in_array('lac-only@x.test', $p, true));
$t('DSQUARED2 siparisi veren GECER',              in_array('dsq-only@x.test', $p, true));
$t('"Fred Perry Kids" siparisi GECER (alt dize degil)', in_array('kids-only@x.test', $p, true));
$t('hic siparisi olmayan GECER',                  in_array('never@x.test', $p, true));
$t('yalniz TEKLIF verip siparis vermeyen GECER',  in_array('fp-offer@x.test', $p, true));
$t('elenen sayisi 4',                             $hit === 4);
$t('gecen sayisi 5',                              count($p) === 5);
$t('teklif-yalniz sayaci 1 (elenmedi, sayildi)',  $off === 1);
$t('yukleyici ozet satirini yaziyor',             str_contains($out, 'marka siparisi: fred perry'));

echo "\n== 2. brand_orders VERILMEZSE davranis degismez ==\n";
[$p2, $hit2] = $run('', $emails);
$t('herkes gecer',                                count($p2) === count($emails));
$t('elenen 0',                                    $hit2 === 0);

echo "\n== 3. Birden fazla marka ve spec ayristirmasi ==\n";
[$p3, $hit3] = $run(' fred perry , Lacoste ,, ', $emails);
$t('Lacoste da verilince lac-only elenir',        !in_array('lac-only@x.test', $p3, true));
$t('Fred Perry yine elenir',                      !in_array('fp-order@x.test', $p3, true));
$t('DSQUARED2 yine GECER',                        in_array('dsq-only@x.test', $p3, true));
$t('bos parcalar yok sayilir (5 elenen)',         $hit3 === 5);

echo "\n== 4. Kablolama ==\n";
$loop  = strpos($yml, "foreach (\$accts as \$i => \$a) {");
$stamp = strpos($yml, "if (!empty(\$a[\$STAMP])) continue;", (int)$loop);
$flt   = strpos($yml, $filter, (int)$loop);
$pick  = strpos($yml, "\$cand[] = \$i;", (int)$loop);
$t('uye dongusu bulundu',                         $loop !== false);
$t('damga kontrolunden sonra',                    $stamp !== false && $flt !== false && $flt > $stamp);
$t('adaya eklenmeden once',                       $flt !== false && $pick !== false && $flt < $pick);
$t('yukleyici dongudan ONCE',                     strpos($yml, '$BRAND_ORD = [];') < $loop);
$t('kuru kosu ozeti elenenleri yaziyor',          str_contains($yml, 'marka siparisi {$brandOrdHit}'));
$t('kuru kosu teklif-yalniz sayisini yaziyor',    str_contains($yml, 'siparisi olmayan (ELENMEDI'));
$t('girdi aciklamasi brand_orders= anlatiyor',    (bool)preg_match('/member_spec:\s*\n\s*description: "[^"]*brand_orders=/', $yml));
/* Teklif sayaci, adaya EKLENDIKTEN sonra artiyor: sonradan elenenleri saymasin. */
$t('teklif-yalniz sayaci secimden sonra',         (bool)preg_match('/\$cand\[\] = \$i;\s*\n\s*if \(\$BRAND_ORD && isset\(\$offBrand\[\$em\]\)\) \$offOnlyHit\+\+;/', $yml));
/* Ham liste okunuyor (vestra_listings), yalniz canli ilanlar degil. */
$t('ham liste: vestra_listings',                  str_contains($loader, 'vestra_listings()'));
$t('canli-liste varsayimi yok (vestra_products)', !str_contains($loader, 'vestra_products('));

echo "\n$ok ok, $fail hata\n";
exit($fail ? 1 : 0);
