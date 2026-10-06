<?php
/* Ana sayfanin "The edit" seckisi (operator, 6 Eki 2026: "ana sayfadaki
 * urunleri degistir" + "Avrupa'nin en estetik B2B sitesi").
 *
 * IKI YONU DE tutuyor:
 *  - her evden BIR parca, operatorun vitrin sirasinda; ikinci tur ancak her
 *    ev bir kez girdikten sonra,
 *  - satilmis, bolme disi (ayakkabi / ic camasiri) ve id'siz kayit HIC girmiyor.
 * Tek yon yazilsaydi "en yeni 12 ilani basan" bir kusur da yesil kalirdi
 * (bir partinin 68 ilani butun seridi doldururdu).
 */
$src = file_get_contents(__DIR__.'/../vestra/inc/products.php');
foreach (['vestra_home_edit_picks', 'vestra_is_sold_out', 'vestra_sections', 'vestra_product_section',
          'vestra_shop_front_brands', 'vestra_shop_lead_brands'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $src, $m)) { echo "HATA: $fn bulunamadi\n"; exit(1); }
    eval($m[0]);
}

$ok=0; $fail=0;
$t = function(string $n, bool $c) use (&$ok,&$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };

$NOW = strtotime('2026-10-06 12:00:00');
$d   = fn(int $days) => date('c', $NOW - $days*86400);
$mk  = fn(string $id, string $brand, ?string $added, array $extra = []) => array_filter(
        ['id'=>$id, 'brand'=>$brand, 'added_at'=>$added], fn($v) => $v !== null) + $extra;
$ids = fn(array $rows) => array_map(fn($r) => $r['id'], $rows);

/* Katalog sirasi bilerek karisik: operatorun on markalari EN SONDA, ve bir
   partinin alti ilani (D&G) art arda -- tek markanin seridi doldurmasi
   olculebilsin. */
$cat = [
    $mk('dg-1', 'Dolce & Gabbana', $d(1)),
    $mk('dg-2', 'Dolce & Gabbana', $d(1)),
    $mk('dg-3', 'Dolce & Gabbana', $d(1)),
    $mk('dg-4', 'Dolce & Gabbana', $d(2)),
    $mk('dg-5', 'Dolce & Gabbana', $d(2)),
    $mk('dg-6', 'Dolce & Gabbana', $d(2)),
    $mk('giv-1', 'Givenchy', $d(20)),
    $mk('bal-1', 'BALMAIN', $d(30)),
    $mk('bal-2', 'BALMAIN', $d(10)),      // daha yeni: ev icinde ONCE gelmeli
    $mk('ami-1', 'AMI Paris', $d(100)),
    $mk('lac-1', 'Lacoste', $d(200)),
    $mk('gd-1', 'Gallery Dept.', $d(25)),
    $mk('fp-1', 'Fred Perry', $d(300)),
];
$FRONT = ['GALLERY DEPT.', 'FRED PERRY', 'BALENCIAGA', 'LACOSTE', 'DOLCE & GABBANA', 'DSQUARED2'];
$LEAD  = ['GUCCI', 'GIVENCHY', 'BALMAIN'];

echo "\n== 1. Her evden BIR parca, operatorun sirasinda ==\n";
$r = vestra_home_edit_picks($cat, $FRONT, $LEAD, 12, $NOW);
$first = array_slice($ids($r), 0, 6);
$t('ilk tur: on markalar liste sirasinda, sonra lead, sonra kalan',
   $first === ['gd-1', 'fp-1', 'lac-1', 'dg-1', 'giv-1', 'bal-2']);
$t('katalogda olmayan on marka (Balenciaga) sirayi KAYDIRMIYOR', !in_array('', $first, true) && count($first) === 6);
$t('listede olmayan ev de giriyor (AMI Paris)', in_array('ami-1', $ids($r), true));
$t('ev icinde EN YENI once (bal-2, bal-1 degil)', array_search('bal-2', $ids($r), true) < array_search('bal-1', $ids($r), true));

echo "\n== 2. Ikinci tur ancak HER ev bir kez girdikten sonra ==\n";
$pos = fn($id) => array_search($id, $ids($r), true);
$t('D&G ikinci parcasi butun evlerden SONRA', $pos('dg-2') > $pos('ami-1'));
$t('tek parti seridi DOLDURAMIYOR (ilk 7 kartta 7 ayri ev)',
   count(array_unique(array_map(fn($p) => strtoupper($p['brand']), array_slice($r, 0, 7)))) === 7);
$t('tavan: 12', count($r) === 12);
$t('tavan 0 -> bos', vestra_home_edit_picks($cat, $FRONT, $LEAD, 0, $NOW) === []);
$t('tavan 3 -> 3', count(vestra_home_edit_picks($cat, $FRONT, $LEAD, 3, $NOW)) === 3);

echo "\n== 3. TERS YON: girmemesi gerekenler ==\n";
$cat2 = $cat;
$cat2[] = $mk('sold-1', 'Gucci', $d(1), ['sold_out' => true]);
$cat2[] = $mk('shoe-1', 'Pili Perez', $d(1), ['section' => 'footwear']);
$cat2[] = $mk('uw-1', 'NBB', $d(1), ['section' => 'underwear']);
$cat2[] = ['brand' => 'Fendi', 'added_at' => $d(1)];               // id YOK
$cat2[] = $mk('nobrand', '', $d(1));                                 // marka YOK
$r2 = vestra_home_edit_picks($cat2, $FRONT, $LEAD, 20, $NOW);
$t('satilmis ilan YOK',        !in_array('sold-1', $ids($r2), true));
$t('ayakkabi bolmesi YOK',     !in_array('shoe-1', $ids($r2), true));
$t('ic camasiri bolmesi YOK',  !in_array('uw-1', $ids($r2), true));
$t('id siz kayit YOK',         !in_array('', $ids($r2), true));
$t('markasiz kayit YOK',       !in_array('nobrand', $ids($r2), true));
/* Bos dizge SATILDI degil (sold_out yazma dalinin bu depoda kayitli tuzagi). */
$r3 = vestra_home_edit_picks([$mk('x-1', 'Gucci', $d(1), ['sold_out' => ''])], $FRONT, $LEAD, 12, $NOW);
$t('bos dizge satilmis SAYILMIYOR', $ids($r3) === ['x-1']);

echo "\n== 4. Pinned ev icinde en basa ==\n";
$r4 = vestra_home_edit_picks([$mk('a', 'Gucci', $d(1)), $mk('b', 'Gucci', $d(50), ['pinned' => true])], $FRONT, $LEAD, 12, $NOW);
$t('pinned once, yeni olmasa da', $ids($r4) === ['b', 'a']);

echo "\n== 5. Ana sayfa kablolamasi ==\n";
$idx = file_get_contents(__DIR__.'/../vestra/index.php');
$t('secici cagriliyor',             str_contains($idx, 'vestra_home_edit_picks($edCand'));
$t('bolum id"si var',               str_contains($idx, 'id="the-edit"'));
$t('baslik sozlukten',              str_contains($idx, "t('The edit')"));
$t('diskte olmayan kare eleniyor',  str_contains($idx, 'is_file(__DIR__.$ei)'));
/* FIYAT YOK: ana sayfa girissiz aciliyor (KURAL 19). */
$edBlock = (function(string $s): string {
    $a = strpos($s, 'id="the-edit"'); $b = $a !== false ? strpos($s, 'id="new-arrivals"', $a) : false;
    return ($a !== false && $b !== false) ? substr($s, $a, $b-$a) : '';
})($idx);
$t('bolum kaynakta bulundu',        $edBlock !== '');
$t('fiyat basmiyor',                !str_contains($edBlock, 'vestra_money') && !str_contains($edBlock, 'vestra_unit_price'));

echo "\n== 6. Sozluk: yeni anahtarlar 8 dilde de var ==\n";
foreach (['de','fr','es','it','pt','ru','ar','ja'] as $lg) {
    $f = __DIR__.'/../vestra/inc/lang/'.$lg.'.php';
    $tr = is_file($f) ? include $f : [];
    foreach (['The edit', 'One piece from every house in stock.'] as $k) {
        $t("{$lg}: {$k}", is_array($tr) && trim((string)($tr[$k] ?? '')) !== '');
    }
}

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
