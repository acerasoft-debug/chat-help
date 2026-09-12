<?php
/* Vitrin sırası — vestra_shop_order().
 *
 * Operator, 12 Eyl 2026: "balenciaga ve lacostelar basta kalsin".
 *
 * IKI YONU DE tutuyor, cunku tek yon yazilsaydi test yesil kalir ve gercek kusur
 * gorunmezdi:
 *   - one cikmasi GEREKEN (Balenciaga, Lacoste) gercekten one cikiyor mu;
 *   - one cikmaMASI gereken (lead satici, lead markalar, geri kalan) YERINDE mi,
 *     ve her bolmenin ICINDE katalog sirasi korunuyor mu.
 *
 * MEKANIZMA ile SEVK EDILEN DEGERLER ayri ayri sinaniyor (offers_rounds_test'in
 * deseni): mekanizma testin kendi tanimladigi markalarla, sevk edilen liste ise
 * kaynaktan. Tek iddiada birlesseydi, listeye bir marka eklendigi gun mekanizmanin
 * testi de kirmizi donerdi -- olctugunu degil, yazimini koruyan bir iddia.
 */
$root = dirname(__DIR__);
require_once $root.'/vestra/inc/products.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
/* Urunu tek satirda kuran yardimci; siralamayi id dizisi olarak okuyoruz. */
$p = function (string $id, string $brand = '', array $x = []) {
    return $x + ['id' => $id, 'brand' => $brand];
};
$ids = fn(array $rows) => implode(',', array_column($rows, 'id'));

$FRONT   = ['ALFA', 'BETA'];          // test markalari: sevk edilen listeden bagimsiz
$LEAD    = ['GAMA', 'DELTA'];
$SELLERS = ['TEST SELLER'];
$UIDS    = ['uid-lead'];
$ord = fn(array $rows) => vestra_shop_order($rows, $FRONT, $LEAD, $SELLERS, $UIDS);

echo "\n== 1. Bolme sirasi: pinned > on markalar > lead satici > lead markalar > geri kalan ==\n";
$in = [
    $p('rest1', 'ZETA'),
    $p('lead-gama', 'GAMA'),
    $p('sel1', 'ZETA', ['seller' => 'Test Seller']),
    $p('beta1', 'BETA'),
    $p('pin1', 'ZETA', ['pinned' => 1]),
    $p('alfa1', 'ALFA'),
    $p('lead-delta', 'DELTA'),
    $p('sel2', 'ZETA', ['seller_uid' => 'uid-lead']),
];
$t('tam sira',
   $ids($ord($in)) === 'pin1,alfa1,beta1,sel1,sel2,lead-gama,lead-delta,rest1');
$t('hicbir urun kaybolmuyor',        count($ord($in)) === count($in));
$t('hicbir urun cogalmiyor',         count(array_unique(array_column($ord($in), 'id'))) === count($in));

echo "\n== 2. On markalar lead SATICIDAN once (asil talep) ==\n";
/* Lacoste ilanlarinin cogu lead saticinin. Satici once sorulsaydi o bolmeye
   duser ve icinde dagilirlardi -- yani "basta" olmazlardi. */
$in2 = [
    $p('sel-a', 'ZETA', ['seller_uid' => 'uid-lead']),
    $p('sel-b', 'ZETA', ['seller_uid' => 'uid-lead']),
    $p('beta-of-lead', 'BETA', ['seller_uid' => 'uid-lead']),
];
$t('lead saticinin ON MARKALI ilani en basta',
   $ids($ord($in2)) === 'beta-of-lead,sel-a,sel-b');
$t('lead saticinin diger ilanlari ARKADA (bilinen bedel)',
   array_search('beta-of-lead', array_column($ord($in2), 'id'), true) === 0);

echo "\n== 3. Liste sirasi = vitrin sirasi ==\n";
$in3 = [$p('b', 'BETA'), $p('a', 'ALFA')];
$t('katalogda BETA once olsa da ALFA one geciyor', $ids($ord($in3)) === 'a,b');
$t('lead markalarda da liste sirasi',
   $ids($ord([$p('d', 'DELTA'), $p('g', 'GAMA')])) === 'g,d');
/* Bir marka o bolmede hic urun vermezse kendinden sonraki kaymamali. */
$t('bos marka sirayi bozmuyor', $ids($ord([$p('b', 'BETA'), $p('r', 'ZETA')])) === 'b,r');

echo "\n== 4. Bolme ICINDE katalog sirasi AYNEN korunuyor ==\n";
$in4 = [];
foreach (['a1','a2','a3'] as $i) $in4[] = $p($i, 'ALFA');
foreach (['r1','r2','r3'] as $i) $in4[] = $p($i, 'ZETA');
$t('bir markayi one almak digerlerini yeniden DIZMIYOR',
   $ids($ord($in4)) === 'a1,a2,a3,r1,r2,r3');
$in4b = [$p('r1','ZETA'), $p('a1','ALFA'), $p('r2','ZETA'), $p('a2','ALFA')];
$t('arada duran urunler goreli sirasini koruyor', $ids($ord($in4b)) === 'a1,a2,r1,r2');

echo "\n== 5. Eslesme TAM, alt dize DEGIL (mango/zara dersi) ==\n";
$t('"ALFA KIDS" on markaya girMIYOR',
   $ids($ord([$p('x', 'ALFA KIDS'), $p('a', 'ALFA')])) === 'a,x');
$t('"ALF" on markaya girMIYOR',
   $ids($ord([$p('x', 'ALF'), $p('a', 'ALFA')])) === 'a,x');
$t('bosluk ve buyuk/kucuk harf onemsiz (" alfa ")',
   $ids($ord([$p('r', 'ZETA'), $p('a', ' alfa ')])) === 'a,r');
$t('bos marka on markaya girMIYOR',
   $ids($ord([$p('r', ''), $p('a', 'ALFA')])) === 'a,r');
$t('satici adi TAM eslesiyor ("Test Sellers" degil)',
   $ids($ord([$p('x', 'ZETA', ['seller' => 'Test Sellers']), $p('s', 'ZETA', ['seller' => 'Test Seller'])])) === 's,x');
$t('satici kimligi TAM eslesiyor',
   $ids($ord([$p('x', 'ZETA', ['seller_uid' => 'uid-lead-2']), $p('s', 'ZETA', ['seller_uid' => 'uid-lead'])])) === 's,x');

echo "\n== 6. Sinir durumlari ==\n";
$t('bos katalog',                    vestra_shop_order([]) === []);
$t('markasiz katalog aynen donuyor',
   $ids($ord([$p('r1',''), $p('r2','')])) === 'r1,r2');
$t('pinned her seyin onunde, on markanin bile',
   $ids($ord([$p('a','ALFA'), $p('pin','ZETA',['pinned'=>1])])) === 'pin,a');
$t('pinned on markali olsa da yalniz BIR kez cikiyor',
   $ids($ord([$p('r','ZETA'), $p('pa','ALFA',['pinned'=>1])])) === 'pa,r');
/* _ord alani siralamadan ONCE yaziliyor ("newest" sorti icin); siralama onu
   tasimali, yoksa one cekilen markalar sayfada en eski stok gibi gorunur. */
$rowsOrd = $ord([$p('r','ZETA',['_ord'=>0]), $p('a','ALFA',['_ord'=>1])]);
$t('_ord alani korunuyor', ($rowsOrd[0]['_ord'] ?? null) === 1 && ($rowsOrd[1]['_ord'] ?? null) === 0);

echo "\n== 7. SEVK EDILEN degerler (kaynaktan) ==\n";
$front = vestra_shop_front_brands();
$t('on markalar: Balenciaga, Lacoste',   $front === ['BALENCIAGA', 'LACOSTE']);
$t('Balenciaga once (operatorun sirasi)', ($front[0] ?? '') === 'BALENCIAGA');
$t('hepsi BUYUK harf (esitlik tam)',      $front === array_map('strtoupper', $front));
$lead = vestra_shop_lead_brands();
$t('Gucci ve Givenchy listeden CIKARILMADI',
   in_array('GUCCI', $lead, true) && in_array('GIVENCHY', $lead, true));
/* Olu giris kalmasin: bir marka hem on hem lead listesinde olsaydi lead satiri
   hicbir zaman calismazdi ve okuyan yanlis bir sira bekler. */
$t('on marka lead listesinde TEKRARLAMIYOR', array_intersect($front, $lead) === []);
$t('lead satici kimligi kayitli',
   in_array('7ab30f26afedd840', vestra_shop_lead_seller_uids(), true));
$t('satici adinin iki yazimi da kabul',
   count(vestra_shop_lead_sellers()) >= 2);

echo "\n== 8. shop.php kablolamasi ==\n";
$src = file_get_contents($root.'/vestra/shop.php');
$t('shop.php vestra_shop_order() cagiriyor', strpos($src, 'vestra_shop_order(') !== false);
/* Ikinci bir kopya dogmasin: sira artik yalnizca fonksiyonda yazili. */
$t('shop.php kendi bolme dongusunu TASIMIYOR',
   strpos($src, '$leadBrands') === false && strpos($src, '$leadSellerUids') === false);

echo "\nTOPLAM: $ok ok, $fail hata\n";
exit($fail ? 1 : 0);
