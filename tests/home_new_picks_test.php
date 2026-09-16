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
foreach (['vestra_home_featured_brands', 'vestra_home_new_picks', 'vestra_product_is_new', 'vestra_is_sold_out'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $src, $m)) { echo "HATA: $fn bulunamadi\n"; exit(1); }
    eval($m[0]);
}
if (!defined('VESTRA_SHOP_NEW_DAYS')) define('VESTRA_SHOP_NEW_DAYS', 7);
preg_match('/const VESTRA_HOME_FEATURED_MAX\s*=\s*(\d+)/', $src, $fm);
if (!$fm) { echo "HATA: VESTRA_HOME_FEATURED_MAX bulunamadi\n"; exit(1); }
define('VESTRA_HOME_FEATURED_MAX', (int)$fm[1]);

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
$nFeat = count(array_filter($ids($r9), fn($x) => str_starts_with($x,'lac-') || str_starts_with($x,'fpx-')));
$nNew  = count(array_filter($ids($r9), fn($x) => str_starts_with($x,'fresh-')));
$t('one alinanlar tavani asmiyor',   $nFeat === VESTRA_HOME_FEATURED_MAX);
$t('GERCEKTEN YENI ilanlar da var',  $nNew > 0);
$t('izgara doluyor',                 count($r9) === 12);
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

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
