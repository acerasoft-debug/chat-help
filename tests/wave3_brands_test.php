<?php
/**
 * UCUNCU MEKTUP — ADIYLA SAYILAN EVLER (operator, 18 Eyl 2026: *"herkese
 * bastan 3. email gonder ve gece yarisi devam et ... yeni urunler ile Galerry
 * markasi ve F.Perry , Gucci , Dsq2"*).
 *
 * AD KARISIKLIGI, bilerek yaziliyor: bu depoda zaten `wave3_letter_test.php`
 * var ve o BASKA bir seyi olcuyor -- ucuncu PARTI'yi, yani IKINCI mektubun
 * ayakkabi/ic giyim surumunu. Bu dosya ucuncu MEKTUBU olcuyor
 * (vestra_tpl_wave3_brands + workflow'un wave3 secimi). Ikisi ayri damga
 * tasiyor: last_newcollection_at ve last_wave3_at.
 *
 * IKI YON DE TUTULUYOR:
 *   - lead surumu "size iki kez yazmistik" + kayit cagrisi TASIMALI,
 *   - uye surumu ikisini de TASIMAMALI (uye zaten kayitli -- KURAL 2b),
 *   - rakamlar parametreden gelmeli (metne gomulu olsaydi bolum degistigi gun
 *     mektup sessizce yalan olurdu),
 *   - sifir artikelli ev ne madde isaretinde ne konuda GORUNMEMELI,
 *   - ama dolu evler HER IKISINDE de gorunmeli.
 */
$root = dirname(__DIR__).'/vestra';
require_once $root.'/inc/email_templates.php';

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $bad++; echo "  HATA $n\n"; }
};

$F = ['houses' => [
    ['name' => 'Gallery Dept.', 'n' => 9],
    ['name' => 'Fred Perry',    'n' => 2],
    ['name' => 'Gucci',         'n' => 15],
    ['name' => 'DSQUARED2',     'n' => 64],
]];

echo "== 1. Rakamlar PARAMETREDEN ==\n";
[$s1, $b1] = vestra_tpl_wave3_brands('en', 'Base Blu', $F);
$t('Gallery Dept. adedi govdede',   str_contains($b1, 'Gallery Dept. — 9 articles'));
$t('DSQUARED2 adedi govdede',       str_contains($b1, 'DSQUARED2 — 64 articles'));
$t('firma adiyla hitap',            str_contains($b1, 'Hello Base Blu,'));
/* Baska rakamla cagirinca metin DEGISMELI: sabit yazilmis olsaydi bu iddia
   dusmez ve "canli kayittan sayiliyor" cumlesi bos bir iddia olurdu. */
[, $b1b] = vestra_tpl_wave3_brands('en', 'X', ['houses' => [['name'=>'Gucci','n'=>3]]]);
$t('rakam gercekten degisiyor',     str_contains($b1b, 'Gucci — 3 articles') && !str_contains($b1b, '64'));
$t('yalniz istenen ev yazildi',     !str_contains($b1b, 'DSQUARED2'));

echo "\n== 2. SIFIR artikelli ev hicbir yerde gorunmez ==\n";
$F0 = ['houses' => [
    ['name' => 'Gucci',      'n' => 15],
    ['name' => 'Fred Perry', 'n' => 0],     // stokta yok
    ['name' => '',           'n' => 9],     // adsiz kayit
]];
[$s0, $b0] = vestra_tpl_wave3_brands('en', 'X', $F0);
$t('sifirli ev govdede YOK',        !str_contains($b0, 'Fred Perry'));
$t('sifirli ev KONUDA da YOK',      !str_contains($s0, 'Fred Perry'));
$t('adsiz kayit basilmadi',         !str_contains($b0, '— 9 articles'));
$t('dolu ev duruyor',               str_contains($b0, 'Gucci — 15 articles'));

echo "\n== 3. Marka adi CEVRILMEZ, katalogun yazimiyla ==\n";
foreach (['en','de','fr','it','es','nl','pt','pl','cs','el','ja','ko'] as $lg) {
    [$sx, $bx] = vestra_tpl_wave3_brands($lg, 'X', $F);
    $t("{$lg}: Gallery Dept. NOKTASIYLA",  str_contains($bx, 'Gallery Dept.'));
    $t("{$lg}: DSQUARED2 aynen",           str_contains($bx, 'DSQUARED2'));
    $t("{$lg}: yer tutucu kalmadi",        !str_contains($bx.$sx, '%HOUSES%') && !str_contains($bx.$sx, '%NAMES%')
                                            && !str_contains($bx, '%1$s') && !str_contains($bx, '%2$d'));
    $t("{$lg}: dort evin dordu de govdede", substr_count($bx, '•') === 4);
    $t("{$lg}: Turkce karakter sizmadi",   !preg_match('/[şğıİÇĞŞ]/u', $bx.$sx));
    $t("{$lg}: konu bos degil",            trim($sx) !== '');
}

echo "\n== 4. Konuda en cok UC ad, govdede hepsi ==\n";
$t('konuda ilk uc ad',      str_contains($s1, 'Gallery Dept., Fred Perry, Gucci'));
$t('konuda dorduncu YOK',   !str_contains($s1, 'DSQUARED2'));
$t('govdede dorduncu VAR',  str_contains($b1, 'DSQUARED2'));

echo "\n== 5. UYE surumu: lead cumleleri UYEYE gitmez ==\n";
[$sm, $bm] = vestra_tpl_wave3_brands('en', 'Base Blu', $F, true);
$t('uye: "iki kez yazmistik" YOK',   !str_contains($bm, 'written to you twice'));
$t('uye: kayit cagrisi YOK',         !str_contains($bm, 'registration is free'));
$t('uye: ticari kayit istegi YOK',   stripos($bm, 'trade licence') === false);
$t('uye: fiyat NEREDE denmiyor',     !str_contains($bm, 'price list') && !str_contains($bm, 'your account'));
$t('uye: evler AYNEN duruyor',       str_contains($bm, 'Gallery Dept. — 9 articles') && str_contains($bm, 'DSQUARED2 — 64 articles'));
$t('uye: konu lead ile AYNI',        $sm === $s1);
/* TERS YON: lead surumu o cumleleri TASIMALI. Tek yon yazilsaydi iki surumu
   birbirine esitleyen bir hata da yesil kalirdi. */
$t('lead: "iki kez yazmistik" VAR',  str_contains($b1, 'written to you twice'));
$t('lead: kayit cagrisi VAR',        str_contains($b1, 'registration is free'));
$t('iki surum gercekten FARKLI',     $bm !== $b1);
/* Almanca da ayni ayrimi tasimali: tek dilde yapilan bir duzeltme, otekilerde
   sessizce eksik kalir (bu depoda cok kez kayitli). */
[, $bmDe] = vestra_tpl_wave3_brands('de', 'X', $F, true);
[, $blDe] = vestra_tpl_wave3_brands('de', 'X', $F, false);
$t('de: uye surumunde Gewerbeanmeldung YOK', !str_contains($bmDe, 'Gewerbeanmeldung'));
$t('de: lead surumunde VAR',                 str_contains($blDe, 'Gewerbeanmeldung'));
$t('de: iki surum farkli',                   $bmDe !== $blDe);

echo "\n== 6. FIYAT yok, CIKIS yolu var ==\n";
foreach ([['lead',$b1], ['uye',$bm]] as [$who, $bb]) {
    $t("{$who}: rakam+para birimi yok",  !preg_match('/(EUR|€|USD|\$)\s*\d/u', $bb));
    $t("{$who}: cikis cumlesi var",      stripos($bb, 'we will stop') !== false || stripos($bb, 'not write again') !== false);
    $t("{$who}: imza blogu var",         str_contains($bb, 'support@vestrasales.com'));
}
[, , $o1] = vestra_tpl_wave3_brands('en', 'X', $F);
$t('dugme price-list e gidiyor',     ($o1['button']['url'] ?? '') === 'https://vestrasales.com/price-list');

echo "\n== 7. Workflow kablolamasi (send-outreach.yml) ==\n";
$wf = (string)@file_get_contents(dirname(__DIR__).'/.github/workflows/send-outreach.yml');
$t('wf okundu',                      strlen($wf) > 10000);
$t('wave3 gecerli metin',            str_contains($wf, "['winter','shoes','wave3']"));
$t('taninmayan metin DURDURUR',      str_contains($wf, 'newcoll_letter gecersiz'));
$t('kendi damgasi last_wave3_at',    str_contains($wf, "\$NC_STAMP = \$IS_WAVE3 ? 'last_wave3_at'"));
$t('yas olcusu onceki mektup',       str_contains($wf, "\$NC_PREV  = \$IS_WAVE3 ? 'last_newcollection_at'"));
$t('yas NC_PREV ile olculuyor',      str_contains($wf, "\$ncTs = strtotime((string)(\$l[\$NC_PREV] ?? ''));"));
$t('secim NC_STAMP ile eliyor',      str_contains($wf, "if (trim((string)(\$l[\$NC_STAMP] ?? '')) !== '')"));
$t('firma haritasi NC_STAMP ile',    str_contains($wf, "if (trim((string)(\$l0[\$NC_STAMP] ?? '')) === '') continue;"));
$t('wave3 ikinci mektubu sart kosar',str_contains($wf, "if (\$IS_WAVE3 && trim((string)(\$l[\$NC_PREV] ?? '')) === '')"));
$t('damga NC_STAMP e yaziliyor',     str_contains($wf, "\$leads[\$i][\$NC_STAMP] = date('c');"));
$t('lead sablonu cagriliyor',        str_contains($wf, 'vestra_tpl_wave3_brands($lang, $company, $NC_FACTS)'));
$t('uye sablonu UYE bayragiyla',     str_contains($wf, 'vestra_tpl_wave3_brands($lang, $who, $M_FACTS, true)'));
$t('uye damgasi wave3_at',           str_contains($wf, "'wave3' => 'wave3_at'"));
$t('uye capraz damgasi',             str_contains($wf, "'wave3' => 'last_wave3_at'"));
$t('evler CANLI kayittan sayiliyor', str_contains($wf, 'foreach (vestra_products() as $wp)'));
$t('eslesme TAM esitlik',            str_contains($wf, 'strcasecmp(trim((string)$b), trim($want)) === 0'));
$t('eslesmeyen ev DURDURUR',         str_contains($wf, 'KATALOGDA ESLESMEYEN EV'));
$t('dort ev adiyla yazili',          str_contains($wf, "\$W3_WANT = ['Gallery Dept.', 'Fred Perry', 'Gucci', 'DSQUARED2'];"));
/* Uye dalinda gunler icinde ikinci kampanya mektubu: 17 Eyl'de elle yapilmisti. */
$t('uye: yakin kampanya elemesi',    str_contains($wf, 'baska bir kampanya mektubu aldi'));
/* 25 girdi siniri: yeni girdi EKLENMEDI. */
$t('girdi sayisi 25 i asmiyor',      preg_match_all('/^      [a-z_]+:$/m', $wf) <= 25);

echo "\n---- ".($bad === 0 ? 'HEPSI GECTI' : "{$bad} KIRMIZI")." | ok={$ok} hata={$bad} ----\n";
exit($bad === 0 ? 0 : 1);
