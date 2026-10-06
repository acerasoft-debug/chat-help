<?php
/* Ana sayfanin "yeni gelenler" seckisi (operator, 16 Eyl 2026: "ana sayfayi
 * yenile yeni urunler koy F.Perry urunlerini Polo ve Sweatshirt on planda
 * olsun Lacoste da").
 *
 * IKI YONU DE tutuyor:
 *  - one alinmasi gerekenler ONDE (ve YENI olmasalar bile -- Fred Perry'nin
 *    iki ilani aylardir katalogda; "yeni" suzgecine baglansaydi hic cikmazlardi),
 *  - one alinmayanlar YERINDE (bir markayi one almak digerlerini yeniden
 *    dizmemeli) ve eskiyen ilan hic girmemeli.
 * Tek yon yazilsaydi "her urunu basan" bir kusur da yesil kalirdi.
 */
$src = file_get_contents(__DIR__.'/../vestra/inc/products.php');
foreach (['vestra_home_featured_brands', 'vestra_home_featured_sections', 'vestra_home_new_picks',
          'vestra_product_is_new', 'vestra_is_sold_out', 'vestra_sections', 'vestra_product_section'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $src, $m)) { echo "HATA: $fn bulunamadi\n"; exit(1); }
    eval($m[0]);
}
if (!defined('VESTRA_SHOP_NEW_DAYS')) define('VESTRA_SHOP_NEW_DAYS', 7);
foreach (['VESTRA_HOME_FEATURED_MAX', 'VESTRA_HOME_SECTION_MAX'] as $cn) {
    if (!preg_match('/const '.$cn.'\s*=\s*(\d+)/', $src, $fm)) { echo "HATA: $cn bulunamadi\n"; exit(1); }
    define($cn, (int)$fm[1]);
}

$ok=0; $fail=0;
$t = function(string $n, bool $c) use (&$ok,&$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };

$NOW = strtotime('2026-09-16 12:00:00');
$d   = fn(int $days) => date('c', $NOW - $days*86400);
$mk  = fn(string $id, string $brand, ?string $added) => array_filter(
        ['id'=>$id, 'brand'=>$brand, 'added_at'=>$added], fn($v) => $v !== null);
$ids = fn(array $rows) => array_map(fn($r) => $r['id'], $rows);

/* Katalog sirasi bilerek karisik: one alinanlar EN SONDA duruyor, yani
   secici gercekten one cekiyor mu olculebilsin. */
$cat = [
    $mk('old-1',  'Balenciaga',  $d(90)),   // ne one alinmis ne yeni
    $mk('new-1',  'Dolce & Gabbana', $d(1)),// en taze
    $mk('new-2',  'DSQUARED2',   $d(3)),
    $mk('old-2',  'Gucci',       $d(40)),
    $mk('kids-1', 'Lacoste Kids',$d(2)),    // ADI benziyor, marka DEGIL
    $mk('lac-1',  'Lacoste',     $d(200)),  // one alinmis, cok eski
    $mk('fp-1',   'Fred Perry',  $d(300)),  // one alinmis, cok eski
    $mk('fp-2',   'Fred Perry',  $d(310)),
];

echo "\n== 1. One alinanlar ONDE, liste sirasinda ==\n";
$r = vestra_home_new_picks($cat, null, 12, $NOW);
$t('Fred Perry ilk iki sirada',  array_slice($ids($r),0,2) === ['fp-1','fp-2']);
$t('Lacoste hemen arkasinda',    ($ids($r)[2] ?? '') === 'lac-1');
/* ASIL OLGU: ikisi de 200+ gunluk, yani "yeni" degiller. Secici yalniz
   tazelige baksaydi operatorun adiyla istedigi iki marka hic cikmazdi. */
$t('one alinanlar YENI olmasa da var', !vestra_product_is_new($cat[6], $NOW) && in_array('fp-1', $ids($r), true));

echo "\n== 2. Arkasinda gercekten YENI olanlar, EN YENI once ==\n";
$rest = array_slice($ids($r), 3);
/* EN YENI ONCE, tam sira: 1 gun, 2 gun, 3 gun. Ilk yazimda 'new-1, new-2'
   bekledim ve kirmizi dondu -- arada 2 gunluk kids-1 var ve orada olmasi
   DOGRU. Kod haklı cikti, iddia yanlisti; iddia artik tam siraya bakiyor. */
$t('EN YENI once (1g, 2g, 3g)',  $rest === ['new-1','kids-1','new-2']);
$t('90 gunluk ilan HIC girmedi',  !in_array('old-1', $ids($r), true));
$t('40 gunluk ilan HIC girmedi',  !in_array('old-2', $ids($r), true));

echo "\n== 3. TERS YON: benzer ad one alinmaz ==\n";
/* "Lacoste Kids" TAM esitlikte degil; alt dizeye gevsetilseydi one cikardi
   (mango/zara dersi). YENI oldugu icin listede olmali ama ON bolumde degil. */
$t('Lacoste Kids one CEKILMEDI', array_search('kids-1', $ids($r), true) > 2);
$t('ama yeni oldugu icin listede', in_array('kids-1', $ids($r), true));

echo "\n== 4. Tekillestirme, tavan, id'siz kayit ==\n";
$cat2 = $cat;
$cat2[] = $mk('fp-3', 'Fred Perry', $d(1));   // hem one alinmis hem taze
$r2 = vestra_home_new_picks($cat2, null, 12, $NOW);
$t('one alinmis TAZE ilan bir kez var', count(array_keys($ids($r2), 'fp-3')) === 1);
$t('ve ON bolumde',                     array_search('fp-3', $ids($r2), true) < 3);

$r3 = vestra_home_new_picks($cat, null, 2, $NOW);
$t('tavan uygulaniyor',           count($r3) === 2);
$r4 = vestra_home_new_picks($cat, null, 0, $NOW);
$t('tavan 0 -> bos',              $r4 === []);

$cat3 = array_merge([['brand'=>'Fred Perry','added_at'=>$d(1)]], $cat);  // id YOK
$t('id siz kayit atlaniyor',      !in_array('', $ids(vestra_home_new_picks($cat3, null, 12, $NOW)), true));

echo "\n== 5. Liste sirasi: bir marka bos olsa da kaymiyor ==\n";
$noFP = array_values(array_filter($cat, fn($p) => $p['brand'] !== 'Fred Perry'));
$r5 = vestra_home_new_picks($noFP, null, 12, $NOW);
$t('Fred Perry yokken Lacoste basta', ($ids($r5)[0] ?? '') === 'lac-1');

echo "\n== 6. Sevk edilen liste ==\n";
$fb = vestra_home_featured_brands();
$t('FRED PERRY one alinmis',  in_array('FRED PERRY', $fb, true));
$t('LACOSTE one alinmis',     in_array('LACOSTE', $fb, true));
$t('hepsi BUYUK harf',        $fb === array_map('strtoupper', $fb));

echo "\n== 7. Ana sayfa kablolamasi ==\n";
$idx = file_get_contents(__DIR__.'/../vestra/index.php');
$t('secici cagriliyor',        str_contains($idx, 'vestra_home_new_picks($npCand)'));
$t('bolum id"si var',          str_contains($idx, 'id="new-arrivals"'));
$t('baslik sozlukten',         str_contains($idx, "t('New arrivals')"));
/* Fotograf suzgeci SAYFADA: secici saf kalmali. */
$t('diskte olmayan kare eleniyor', str_contains($idx, 'is_file(__DIR__.$ni)'));
/* Kendi CSS'i olmali: .shoe-* bloku yalnizca ayakkabi seridi doluyken basiliyor. */
$t('kendi CSS sinifi',         str_contains($idx, '.new-card{'));
$t('ayakkabi sinifini kullanmiyor', !str_contains($idx, 'class="shoe-card" href="/product?id=<?= urlencode((string)$nc'));
/* FIYAT YOK: ana sayfa girissiz aciliyor (KURAL 19). */
$npBlock = (function(string $s): string {
    $a = strpos($s, 'YENI GELENLER'); $b = $a !== false ? strpos($s, 'id="footwear"', $a) : false;
    return ($a !== false && $b !== false) ? substr($s, $a, $b-$a) : '';
})($idx);
$t('bolum kaynakta bulundu',   $npBlock !== '');
$t('fiyat basmiyor',           !str_contains($npBlock, 'vestra_money') && !str_contains($npBlock, 'vestra_unit_price'));

echo "\n== 8. Sozluk: yeni anahtar 8 dilde de var ==\n";
foreach (['de','fr','es','it','pt','ru','ar','ja'] as $lg) {
    $f = __DIR__.'/../vestra/inc/lang/'.$lg.'.php';
    $tr = is_file($f) ? include $f : [];
    $t("{$lg}: New arrivals", is_array($tr) && trim((string)($tr['New arrivals'] ?? '')) !== '');
}

echo "\n== 9. One alinanlarin PAYI sinirli -- yeni ilanlar da giriyor ==\n";
/* CANLI OLCUMUN yakaladigi kusur: one alinan markalarda 15 aday var
   (Fred Perry 2 + Lacoste 13) ve tavansiz birakinca 12 kartin 12'sini de
   onlar dolduruyordu; "yeni urunler koy" talimati sessizce uygulanmiyordu. */
$many = [];
for ($i = 0; $i < 13; $i++) $many[] = $mk('lac-'.$i, 'Lacoste', $d(200));
$many[] = $mk('fpx-1', 'Fred Perry', $d(300));
$many[] = $mk('fpx-2', 'Fred Perry', $d(300));
for ($i = 0; $i < 6; $i++) $many[] = $mk('fresh-'.$i, 'Gucci', $d($i + 1));
$r9 = vestra_home_new_picks($many, null, 12, $NOW);
$isFeat = fn($x) => str_starts_with($x,'lac-') || str_starts_with($x,'fpx-');
$nNew  = count(array_filter($ids($r9), fn($x) => str_starts_with($x,'fresh-')));
/* OLCUT: yenilerin ONUNDE en fazla tavan kadar one alinan. Ilk yazim toplam
   sayiyi tavana esitliyordu ve bu yalnizca fikstur tesaduf eseri tuttugu icin
   dogruydu (6 yeni + tavan 6 = 12). 25 Eyl 2026'da tavan 3'e inince izgarada
   3 bos slot kaldi ve TASARIM GEREGI one alinanlarin geri kalani onlari
   doldurdu -- yenilerin ARKASINDA. Asil olgu sira, toplam degil. */
$freshPos = array_keys(array_filter($ids($r9), fn($x) => str_starts_with($x,'fresh-')));
$featAhead = $freshPos ? count(array_filter(array_slice($ids($r9), 0, max($freshPos)), $isFeat)) : PHP_INT_MAX;
$t('one alinanlar tavani asmiyor (yenilerin onunde)', $featAhead === VESTRA_HOME_FEATURED_MAX);
$t('butun GERCEKTEN YENI ilanlar girdi',   $nNew === 6);
$t('izgara doluyor',                 count($r9) === 12);
/* Yeni ilan izgarayi doldurmaya yetiyorsa one alinanlar TAM tavanda kalir. */
$manyFresh = array_slice($many, 0, 15);
for ($i = 0; $i < 12; $i++) $manyFresh[] = $mk('fresh2-'.$i, 'Gucci', $d(($i % 6) + 1));
$r9b = vestra_home_new_picks($manyFresh, null, 12, $NOW);
$t('yeni ilan boldayken marka tam tavanda', count(array_filter($ids($r9b), $isFeat)) === VESTRA_HOME_FEATURED_MAX);
/* Ters yon: yeni ilan YOKSA bos slotlar one alinanlarla dolmali -- yarim
   dolu bir izgara, dolu bir izgaradan kotu gorunur. */
$r10 = vestra_home_new_picks(array_slice($many, 0, 15), null, 12, $NOW);
$t('yeni yokken izgara yine doluyor', count($r10) === 12);

echo "\n== 10. SATILMIS mal seride giremez ==\n";
/* Serit "In stock now" rozetiyle aciliyor; alinamayan bir urun o rozeti
   yalanlar. Canli olcumde tam bu cikti. */
$soldCat = [
    $mk('fp-sold', 'Fred Perry', $d(300)) + ['sold_out' => true],
    $mk('fp-ok',   'Fred Perry', $d(300)),
    $mk('new-sold','Gucci',      $d(1))   + ['sold_out' => true],
    $mk('new-ok',  'Gucci',      $d(2)),
];
$r11 = vestra_home_new_picks($soldCat, null, 12, $NOW);
$t('satilmis one alinan YOK',  !in_array('fp-sold', $ids($r11), true));
$t('satilmis yeni ilan YOK',   !in_array('new-sold', $ids($r11), true));
$t('satista olanlar VAR',      $ids($r11) === ['fp-ok','new-ok']);
/* Bos dizge SATILDI degil (sold_out yazma dalinin bu depoda kayitli tuzagi). */
$r12 = vestra_home_new_picks([$mk('fp-str','Fred Perry',$d(300)) + ['sold_out' => '']], null, 12, $NOW);
$t('bos dizge satilmis SAYILMIYOR', $ids($r12) === ['fp-str']);

echo "\n== 11. Ic camasiri bolmesi SERIDIN BASINDA (operator, 25 Eyl 2026) ==\n";
/* "ozellikle New arrivals bolumune ic camasiri bolumunu koy" + "diger luks
   markalari azalt". Iki yon: bolme ONDE ve payi sinirli, one alinan markalar
   GERIDE ve yarim payla, ve gercekten yeni ilanlar HALA giriyor. Katalog
   sirasi bilerek ters: ic camasiri EN SONDA duruyor. */
$uw = fn(string $id, string $brand, int $age) => $mk($id, $brand, $d($age)) + ['section' => 'underwear'];
$mix = [];
for ($i = 0; $i < 13; $i++) $mix[] = $mk('lacm-'.$i, 'Lacoste', $d(200));
$mix[] = $mk('fpm-1', 'Fred Perry', $d(300));
for ($i = 0; $i < 6; $i++) $mix[] = $mk('freshm-'.$i, 'Gucci', $d($i + 1));
for ($i = 0; $i < 9; $i++) $mix[] = $uw('uw-'.$i, 'NBB', 120);   // eski ama bolme
$r13 = vestra_home_new_picks($mix, null, 12, $NOW);
$i13 = $ids($r13);
$nUw   = count(array_filter($i13, fn($x) => str_starts_with($x,'uw-')));
$nFm   = count(array_filter($i13, fn($x) => str_starts_with($x,'lacm-') || str_starts_with($x,'fpm-')));
$nFr   = count(array_filter($i13, fn($x) => str_starts_with($x,'freshm-')));
$t('ilk kart ic camasiri',                    str_starts_with($i13[0] ?? '', 'uw-'));
$t('ilk '.VESTRA_HOME_SECTION_MAX.' kart ic camasiri',
   array_slice($i13, 0, VESTRA_HOME_SECTION_MAX) === array_map(fn($k) => 'uw-'.$k, range(0, VESTRA_HOME_SECTION_MAX - 1)));
$t('bolme tavani asilmiyor',                  $nUw === VESTRA_HOME_SECTION_MAX);
$t('one alinan markalar tavaninda',           $nFm === VESTRA_HOME_FEATURED_MAX);
$t('markalar bolmenin ARKASINDA',             array_search('fpm-1', $i13, true) === false || array_search('fpm-1', $i13, true) >= VESTRA_HOME_SECTION_MAX);
$t('GERCEKTEN YENI ilanlar hala giriyor',     $nFr > 0);
$t('izgara dolu',                             count($r13) === 12);
/* Ic camasiri YENI olmasa da giriyor: 120 gunluk. Bolme tazelige bagli olsaydi
   operatorun adiyla istedigi bolum seride hic girmezdi. */
$t('bolme YENI olmasa da giriyor',            !vestra_product_is_new($uw('x','NBB',120), $NOW) && $nUw > 0);

/* TERS YON: bolme bossa davranis ONCEKININ AYNISI -- ayni katalogdan
   ic camasiri cikarilinca ilk kart one alinan marka. */
$noUw = array_values(array_filter($mix, fn($p) => ($p['section'] ?? '') !== 'underwear'));
$r14  = vestra_home_new_picks($noUw, null, 12, $NOW);
$t('bolme yokken marka basta',                str_starts_with($ids($r14)[0] ?? '', 'lacm-') || str_starts_with($ids($r14)[0] ?? '', 'fpm-'));

/* Satilmis ic camasiri seride giremez -- ayni rozet kurali. */
$r15 = vestra_home_new_picks([$uw('uw-sold','NBB',30) + ['sold_out' => true], $uw('uw-ok','NBB',30)], null, 12, $NOW);
$t('satilmis ic camasiri YOK',                $ids($r15) === ['uw-ok']);

/* Bilinmeyen bolme adi 'premium'a duser (vestra_product_section) -- yazim
   hatasi bolmeyi one cekmez. Tam esitlik, alt dize degil. */
$r16 = vestra_home_new_picks([$mk('odd','X',$d(200)) + ['section' => 'underwearx']], null, 12, $NOW);
$t('yanlis yazilmis bolme ONE CEKILMEDI',     $ids($r16) === []);

/* Bolme tavani ayri: bolmede fazla aday olsa da bos slot kalirsa geri kalan
   doldurur (yarim dolu izgara kotu gorunur). */
$onlyUw = []; for ($i = 0; $i < 10; $i++) $onlyUw[] = $uw('uwo-'.$i, 'NBB', 60);
$t('yalniz bolme varken izgara yine doluyor', count(vestra_home_new_picks($onlyUw, null, 12, $NOW)) === 10);

echo "\n== 12. Sevk edilen degerler ==\n";
$t('underwear sevk edilen bolme',             vestra_home_featured_sections() === ['underwear']);
$t('underwear gercek bir bolme anahtari',     isset(vestra_sections()['underwear']));
$t('marka payi yariya indi (3)',              VESTRA_HOME_FEATURED_MAX === 3);
$t('bolme payi 6',                            VESTRA_HOME_SECTION_MAX === 6);
$t('iki pay birlikte yeni ilana yer birakiyor', VESTRA_HOME_FEATURED_MAX + VESTRA_HOME_SECTION_MAX < 12);

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
