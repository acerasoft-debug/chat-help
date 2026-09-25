<?php
/* send-outreach.yml uye kipi: member_spec country= suzgeci (25 Eyl 2026,
 * operator: "3 email almanyanlara sistemden kampanya gonder").
 *
 * Lead yolunun country_filter'i uye kipine hic ugramiyordu. Bu test is
 * akisindaki GERCEK satirlari cikarip sentetik hesaplar uzerinde kosturuyor --
 * kaynakta "country" kelimesini gormek olcum degil.
 *
 * IKI YONU DE tutuyor: Alman hesaplar GECER, digerleri ve belirsizler ("Ge")
 * ELENIR, ve suzgec verilmezse davranis ONCEKININ AYNISI (herkes gecer).
 */
$yml = file_get_contents(__DIR__.'/../.github/workflows/send-outreach.yml');
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };

/* Ayristirma blogu: $MCOUNTRIES = []; ... foreach ... } */
if (!preg_match('/(\$MCOUNTRIES = \[\];\s*foreach \(explode\(.*?\n\s*\})/s', $yml, $pm)) { echo "HATA: ayristirma blogu bulunamadi\n"; exit(1); }
$parse = $pm[1];
/* Suzgec satiri: uye dongusundeki tek satir. */
if (!preg_match('/^\s*(if \(\$MCOUNTRIES && .*?continue; \})\s*$/m', $yml, $fm)) { echo "HATA: suzgec satiri bulunamadi\n"; exit(1); }
$filter = $fm[1];

$run = function (string $spec, array $accts) use ($parse, $filter): array {
    $mspec = $spec === '' ? [] : ['country' => $spec];
    eval($parse);
    $pass = []; $countryOut = 0;
    foreach ($accts as $a) {
        /* Satirdaki `continue` eval'in DISINDAKI donguye ulasamaz; donguyu
           eval'in kendi icine koyuyoruz: continue = elendi, return true = gecti. */
        $passed = eval('foreach ([0] as $_) { '.$filter.' return true; } return false;');
        if ($passed) $pass[] = $a['id'];
    }
    return [$pass, $countryOut];
};

$acc = [
    ['id' => 'de-1',   'country' => 'Deutschland'],
    ['id' => 'de-2',   'country' => 'DE'],
    ['id' => 'de-3',   'country' => ' deutschland '],   // bosluk + kucuk harf
    ['id' => 'de-4',   'country' => 'Germany'],
    ['id' => 'ge-1',   'country' => 'Ge'],              // Gurcistan olabilir -- tahmin edilmez
    ['id' => 'dk-1',   'country' => 'Denmark'],         // 'de' alt dizesi: TUZAK
    ['id' => 'fr-1',   'country' => 'France'],
    ['id' => 'none-1', 'country' => ''],
    ['id' => 'nokey'],                                  // alan hic yok
];

echo "\n== 1. Suzgec verildiginde yalniz TAM esleme gecer ==\n";
[$p, $out] = $run('Deutschland,Germany,DE', $acc);
$t('Deutschland gecer',               in_array('de-1', $p, true));
$t('DE gecer',                        in_array('de-2', $p, true));
$t('bosluk/kucuk harf gecer',         in_array('de-3', $p, true));
$t('Germany gecer',                   in_array('de-4', $p, true));
$t('"Ge" ELENIR (belirsiz)',          !in_array('ge-1', $p, true));
$t('Denmark ELENIR (alt dize degil)', !in_array('dk-1', $p, true));
$t('France ELENIR',                   !in_array('fr-1', $p, true));
$t('bos ulke ELENIR',                 !in_array('none-1', $p, true));
$t('alani olmayan ELENIR',            !in_array('nokey', $p, true));
$t('elenen sayisi yaziliyor (5)',     $out === 5);

echo "\n== 2. Suzgec VERILMEZSE davranis degismez ==\n";
[$p2, $out2] = $run('', $acc);
$t('herkes gecer',                    count($p2) === count($acc));
$t('elenen 0',                        $out2 === 0);

echo "\n== 3. Spec ayristirmasi ==\n";
[$p3] = $run(' , DE ,, ', $acc);
$t('bos parcalar yok sayilir, DE yine calisir', $p3 === ['de-2']);

echo "\n== 4. Kablolama: uye dongusunde, damgadan SONRA, secimden ONCE ==\n";
$loopA = strpos($yml, "foreach (\$accts as \$i => \$a) {");
$stamp = strpos($yml, "if (!empty(\$a[\$STAMP])) continue;", (int)$loopA);
$flt   = strpos($yml, $filter, (int)$loopA);
$pick  = strpos($yml, "\$cand[] = \$i;", (int)$loopA);
$t('uye dongusu bulundu',             $loopA !== false);
$t('damga kontrolunden sonra',        $stamp !== false && $flt !== false && $flt > $stamp);
$t('adaya eklenmeden once',           $flt !== false && $pick !== false && $flt < $pick);
$t('kuru kosu ozeti elenenleri yaziyor', str_contains($yml, 'ulke suzgeci disi {$countryOut}'));
$t('girdi aciklamasi country= anlatiyor', (bool)preg_match('/member_spec:\s*\n\s*description: "[^"]*country=/', $yml));
/* Lead yolunun suzgeci yerinde kalmali -- bu is ona dokunmadi. */
$t('lead country_filter hala var',    str_contains($yml, "if (\$COUNTRIES && !isset(\$COUNTRIES[strtolower(trim((string)(\$l['country'] ?? '')))])) continue;"));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
