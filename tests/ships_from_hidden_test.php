<?php
/* Gonderim yeri satiri MUSTERIYE gosterilmiyor — operator karari, 10 Eyl 2026:
 * "underwear urunlerini turkiyeden gonderiliyor ibaresini kaldir, marca online
 * saticisi da belli olmasin turkiyeden geldigi".
 *
 * Bu test iki seyi birden tutuyor ve IKISI DE gerekli:
 *
 *   1) GIZLENMELI — Marca Online'in ilanlarinda satir basilmamali. Bu, isteg-
 *      in kendisi.
 *   2) GORUNMELI — baska bir saticinin ilaninda, ULKESI AYNI OLSA BILE, satir
 *      durmali. Tek yon yazilsaydi test yesil kalir ve bir gun bolme ya da ulke
 *      uzerinden gevsetilen bir kural, ilgisiz bir tedarikcinin gercek cikis
 *      yerini de sessizce silerdi. Bu depoda ayni ders mango/zara ile bir kez
 *      ogrenildi: sessiz eleme, yanlis basimdan pahali -- kimse fark etmiyor.
 *
 * Ucuncu ve en onemli iddia ALAN ile SATIR arasindaki ayrim: kayittan
 * 'ships_from' silinseydi vestra_ships_from() platform varsayilanina duser ve
 * sayfa "Ships from EU" YAZARDI -- alicinin gumruk icin okudugu satirda dogru
 * bir ifadeyi yanlis bir ifadeyle degistirmek. O yuzden kayit yerinde duruyor
 * ve yalnizca basim susuyor; test ikisini ayri ayri dogruluyor.
 */

require_once __DIR__ . '/../vestra/inc/products.php';

$fail = 0; $n = 0;
function ok(bool $c, string $m): void {
    global $fail, $n; $n++;
    if (!$c) { $fail++; echo "  KALDI: {$m}\n"; }
}

const MARCA_ONLINE = '0cb79eb883f2a0fa';
const OTHER_SELLER = '7ab30f26afedd840';   // GARAGE LE PARIS

/* ── 1) Karar: kim gizli, kim degil ──────────────────────────────────────── */
$uw   = ['seller_uid' => MARCA_ONLINE, 'ships_from' => 'Turkey', 'section' => 'underwear'];
$same = ['seller_uid' => OTHER_SELLER, 'ships_from' => 'Turkey'];   // AYNI ULKE, baska satici
$it   = ['seller_uid' => OTHER_SELLER, 'ships_from' => 'Italy'];

ok(vestra_hides_ships_from($uw)      === true,  'Marca Online ilani gizli');
ok(vestra_hides_ships_from($same)    === false, 'ayni ulke ama baska satici: GORUNUR');
ok(vestra_hides_ships_from($it)      === false, 'Italya cikisli ilan: GORUNUR');
ok(vestra_hides_ships_from([])       === false, 'seller_uid yok: GORUNUR');
ok(vestra_hides_ships_from(['seller_uid' => '']) === false, 'bos seller_uid: GORUNUR');

/* Olcut BOLME degil SATICI. Bolmeye baglansaydi yarinki Ispanyol bir ic
   camasiri tedarikcisinin gercek cikis yeri de silinirdi. */
ok(vestra_hides_ships_from(['seller_uid' => OTHER_SELLER, 'section' => 'underwear']) === false,
   'underwear bolmesi TEK BASINA gizlemiyor — olcut satici');
ok(vestra_hides_ships_from(['seller_uid' => MARCA_ONLINE, 'section' => 'premium']) === true,
   'Marca Online baska bolmede de gizli');

/* ── 2) ALAN duruyor, yalnizca SATIR susuyor ────────────────────────────── */
ok(vestra_ships_from($uw) === 'Turkey',
   'kayit okunmaya devam ediyor (fatura/sevkiyat tarafi icin)');
ok(vestra_ships_from([]) === 'EU',
   'bos alan platform varsayilanina duser — alani SILMEK satiri kaldirmaz, YANLIS yazar');
ok(vestra_ships_from_label($uw) === 'Ships from Turkey',
   'etiket kurucusu degismedi; degisen tek sey onu CAGIRIP CAGIRMAMAK');

/* ── 3) Kablolama: satiri basan HER musteri yolu karari soruyor ─────────── */
$wired = [
    'vestra/product.php'         => 'urun sayfasi',
    'vestra/shop.php'            => 'katalog karti',
    'vestra/dropship.php'        => 'dropship sayfasi',
    'vestra/inc/journal_auto.php'=> 'gunluk journal yazisi',
];
foreach ($wired as $file => $what) {
    $src = (string)file_get_contents(__DIR__ . '/../' . $file);
    /* Basim var mi ve karar soruluyor mu -- ikisi birlikte. Yalnizca ikincisini
       aramak, basim tumden silinse de yesil kalirdi. */
    ok(str_contains($src, 'vestra_ships_from_label') || str_contains($src, 'vestra_ships_from('),
       "{$what}: gonderim yerini hala basiyor/okuyor ({$file})");
    ok(str_contains($src, 'vestra_hides_ships_from'),
       "{$what}: karari SORUYOR ({$file})");
}

/* Operator paneli TERSI: gercegi basmaya devam etmeli, yoksa operator
   musterinin gordugunu sanip yanlis karar verir. */
$admin = (string)file_get_contents(__DIR__ . '/../vestra/admin.php');
ok(str_contains($admin, 'vestra_ships_from_flag'), 'panel gonderim yerini basmaya DEVAM ediyor');
ok(str_contains($admin, 'vestra_hides_ships_from'), 'panel "alicida gizli" diye isaretliyor');

/* ── 4) Iki kayit birbirini tutuyor mu ───────────────────────────────────── */
$prod = (string)file_get_contents(__DIR__ . '/../vestra/inc/products.php');
ok(!preg_match('/ships_from=Turkey.*?bayragiyla DURUYOR/su', $prod),
   'eski "ships_from DURUYOR" notu guncellendi (celiskiyi kayitta birakma)');

echo $fail
    ? "\nships_from_hidden_test: {$fail}/{$n} KALDI\n"
    : "ships_from_hidden_test: {$n} iddia gecti\n";
exit($fail ? 1 : 0);
