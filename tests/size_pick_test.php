<?php
/**
 * KURAL 21 — beden secimi + showroom ulke gizleme.
 *
 * Operator, 10 Eyl 2026: "ayrica nbb ürünlerinin beden secimlerinide koy" ve
 * "Basics · Turkey marca online dan bunu cikar" (kapsam: yalniz Marca Online).
 *
 * Bu testin AYIRT ETMESI gereken sey tek yonlu degil:
 *   - secilebilmesi GEREKEN ilanlar (NBB'nin duz beden listeleri) ve
 *   - secilememesi GEREKEN ilanlar (paket/acik seri satan giyim katalogu).
 * Tek yon yazilsaydi test yesil kalir, ya alici hicbir beden secemez ya da
 * 600'den fazla giyim ilaninda paketin icerigiyle celisen bir secim cikardi.
 *
 * Beden dizgeleri UYDURULMADI: 22'sinin hepsi 10 Eyl 2026'da canli sunucudan
 * olculdu (inspect-products.yml -> raw_scan, 42 NBB ilani). Bu depoda kendi
 * uydurdugum degerlerle calisan bir ayristirici (pack_qty) yerelde gecip
 * canlida yanlis metin uretmisti.
 */
require_once __DIR__.'/../vestra/inc/products.php';
require_once __DIR__.'/../vestra/inc/orders.php';

$T = 0; $F = 0;
function ok(bool $c, string $m): void {
    global $T, $F; $T++;
    if (!$c) { $F++; echo "  HATA: $m\n"; }
}
function eqArr(array $a, array $b, string $m): void { ok($a === $b, $m." (beklenen [".implode(', ',$b)."], gelen [".implode(', ',$a)."])"); }

/* Canli olculen 22 dizge -> beklenen SECILEBILIR liste. */
$LIVE = [
    'S · M · L · XL'                                        => ['S','M','L','XL'],
    'S · M · L'                                             => ['S','M','L'],
    'S · M'                                                 => ['S','M'],
    '75 · 80 · 85 · 90'                                     => ['75','80','85','90'],
    '80 · 85 · 90 · 95'                                     => ['80','85','90','95'],
    '75 · 85'                                               => ['75','85'],
    '100 · 105 · 110'                                       => ['100','105','110'],
    '80 · 85 · 95'                                          => ['80','85','95'],
    '75 · 85 · 90 · 95'                                     => ['75','85','90','95'],
    '75 · 80 · 85 · 90 · 95'                                => ['75','80','85','90','95'],
    '80 · 85 · 90 · 95 · 100'                               => ['80','85','90','95','100'],
    '80 · 90 · 95'                                          => ['80','90','95'],
    '75 · 80 · 85'                                          => ['75','80','85'],
    /* Bant + kap TEK beden. Eski ayristirici kap harfini DUSURUYOR ve bu 8
       secenekli sutyeni 4 secenege indiriyordu -- alici B ile C arasinda secim
       yapamiyordu (canli olcum). */
    '75 B · 75 C · 80 B · 80 C · 85 B · 85 C · 90 B · 90 C' => ['75 B','75 C','80 B','80 C','85 B','85 C','90 B','90 C'],
    /* PAKET: karisim sabit, secim YOK. */
    'One size · 12/pack'        => [],
    'One size · 24/pack'        => [],
    'S · L · XL · XXL · 3/pack' => [],
    '2 · 3 · 4 · 5 · 6/pack'    => [],
    /* Tek beden: secilecek bir sey yok. */
    'L'  => [], 'S' => [], '75' => [], '95' => [],
];

echo "== 1. Canli NBB beden dizgeleri (section=underwear) ==\n";
foreach ($LIVE as $s => $want) {
    eqArr(vestra_sizes_selectable(['sizes' => $s, 'section' => 'underwear']), $want, "sizes=\"$s\"");
}
ok(count($LIVE) === 22, 'canli olcumdeki 22 farkli dizgenin hepsi testte');

echo "== 2. Paket adedi BEDEN sayilmiyor ==\n";
/* Kalip '/'yi ayirac sayiyor: "3/pack" -> "3" + "pack", ve "3" sayisal beden
   gibi normalize oluyordu. Alici o ilanda hic olmayan bir bedeni secebilirdi. */
ok(!in_array('3', vestra_size_options(['sizes' => 'S · L · XL · XXL · 3/pack']), true), '"3/pack" -> 3 beden DEGIL');
ok(!in_array('6', vestra_size_options(['sizes' => '2 · 3 · 4 · 5 · 6/pack']), true), '"6/pack" -> 6 beden DEGIL');
eqArr(vestra_size_options(['sizes' => 'S · L · XL · XXL · 3/pack']), ['S','L','XL','XXL'], 'paket eki ayristirmadan once atiliyor');
eqArr(vestra_size_options(['sizes' => '2 · 3 · 4 · 5 · 6/pack']), ['2','3','4','5'], 'sayisal bedenler duruyor, paket adedi dusuyor');
ok(vestra_sizes_has_pack('… · 10 pcs/pack'), '"10 pcs/pack" paket sayiliyor');
ok(vestra_sizes_has_pack('12/seri'), '"12/seri" paket sayiliyor');
ok(!vestra_sizes_has_pack('S · M · L'), 'duz listede paket yok');

echo "== 3. Giyim katalogu: secici CIKMAMALI ==\n";
$APPAREL = [
    'XXS×1 · XS×3 · S×3 · M×2 · L×1 · 10/pack',
    'S×1 · M×3 · L×3 · XL×2 · XXL×1 · 10 pcs/pack',
    'S×2 · M×2 · L×2 · XL×2 · XXL×2',
];
foreach ($APPAREL as $s) {
    ok(vestra_sizes_has_run($s), "acik seri taniniyor: \"$s\"");
    eqArr(vestra_sizes_selectable(['sizes' => $s, 'section' => 'premium']), [], "giyim seciciSIZ: \"$s\"");
    /* Bolme opt-in olsa bile acik seri secilemez: karisim ilanin kendisinde yazili. */
    eqArr(vestra_sizes_selectable(['sizes' => $s, 'section' => 'underwear']), [], "acik seri, bolmeden BAGIMSIZ olarak secilemez");
}
/* Bolme opt-in: ayni duz liste ic camasirinda secilebilir, giyimde DEGIL.
   Operator bunu NBB icin istedi; 600+ giyim ilaninin akisini sessizce
   degistirmek istenenin disindaydi. */
eqArr(vestra_sizes_selectable(['sizes' => 'S · M · L', 'section' => 'underwear']), ['S','M','L'], 'ic camasiri: secilebilir');
eqArr(vestra_sizes_selectable(['sizes' => 'S · M · L', 'section' => 'premium']),   [], 'giyim: ayni dizge secilemez');
eqArr(vestra_sizes_selectable(['sizes' => 'S · M · L', 'section' => 'footwear']),  [], 'ayakkabi: ayni dizge secilemez');
ok(vestra_size_pick_sections() === ['underwear'], 'opt-in listesi tek yerde ve yalniz underwear');

echo "== 4. Siparis notu: yazilan geri OKUNUYOR ==\n";
/* Eski kalip '/^Colours — .../' idi ve order.php notlari HER ZAMAN
   "Payment: …" ile aciyor, yani CANLI hicbir siparise uymuyordu: secilen
   renkler CSV'ye yazilip hicbir belgede gorunmuyordu. Beden ayni yoldan
   gectigi icin once bu duzeltildi. */
$notes = 'Payment: Bank transfer. Deliver to: 12 Rue X, Paris. Colours — NBB-2029: White, Black. Sizes — NBB-2029: S, M. Lutfen hizli gonderin.';
$cn = vestra_order_notes_colors($notes);
eqArr($cn['colors']['NBB-2029'] ?? [], ['White','Black'], 'renkler gercek bir nottan okunuyor');
eqArr($cn['sizes']['NBB-2029'] ?? [],  ['S','M'],          'bedenler gercek bir nottan okunuyor');
ok(strpos($cn['notes'], 'Colours —') === false, 'renk parcasi serbest metinden cikarildi');
ok(strpos($cn['notes'], 'Sizes —') === false,   'beden parcasi serbest metinden cikarildi');
ok(strpos($cn['notes'], 'Lutfen hizli gonderin.') !== false, 'alicinin kendi metni korunuyor');
/* Teslimat adresi ayni notlardan hala dogru cikiyor (kalip sirasi bozulmadi). */
ok(vestra_order_delivery_address($notes) === '12 Rue X, Paris', 'teslimat adresi hala okunuyor');
/* Coklu SKU. */
$m2 = vestra_order_notes_colors('Payment: Bank transfer. Sizes — A1: S, M | B2: 75 B, 80 C. ');
eqArr($m2['sizes']['A1'] ?? [], ['S','M'],        'coklu SKU: ilk satir');
eqArr($m2['sizes']['B2'] ?? [], ['75 B','80 C'],  'coklu SKU: bant+kap degeri bozulmadan geciyor');
/* Beden parcasi hic yoksa harita bos, not aynen duruyor. */
$m3 = vestra_order_notes_colors('Payment: Bank transfer. Hicbir secim yok.');
ok($m3['sizes'] === [] && $m3['colors'] === [], 'parca yoksa harita bos');
ok(strpos($m3['notes'], 'Hicbir secim yok.') !== false, 'parca yokken metin bozulmuyor');

echo "== 5. Showroom: kayitli ULKE yalniz secilen hesapta gizleniyor ==\n";
$marca = ['id' => '0cb79eb883f2a0fa', 'company' => 'Marca Online', 'country' => 'Turkey'];
ok(vestra_showroom_hides_country($marca), 'Marca Online: ulke gizli');
ok(!vestra_showroom_hides_country(['id' => '7ab30f26afedd840', 'country' => 'France']), 'baska satici: ulke gorunur');
ok(!vestra_showroom_hides_country(['id' => '', 'country' => 'Italy']), 'id yoksa gizlenmiyor');
/* Olcut AD degil ID: ayni adi tasiyan baska bir hesap kendiliginden gizlenmez. */
ok(!vestra_showroom_hides_country(['id' => 'deadbeefdeadbeef', 'company' => 'Marca Online', 'country' => 'Turkey']),
   'olcut hesap ID (ad degil)');
/* Hesap bayragi kodun varsayilanini EZIYOR, iki yonde de. */
ok(!vestra_showroom_hides_country($marca + ['showroom_hide_country' => false]), 'hesap bayragi false: varsayilani eziyor');
ok(vestra_showroom_hides_country(['id' => 'x', 'showroom_hide_country' => true]), 'hesap bayragi true: varsayilani eziyor');
/* ships_from AYRI bir olgu ve gizlenMIYOR -- KURAL 3 onu zorunlu tutuyor. */
ok(vestra_ships_from(['ships_from' => 'Turkey']) === 'Turkey', 'ilanin ships_from degeri dokunulmadan duruyor');

echo "== 6. Kablolama: kutu ile kapi AYNI fonksiyonu cagiriyor ==\n";
$prod = file_get_contents(__DIR__.'/../vestra/product.php');
$ord  = file_get_contents(__DIR__.'/../vestra/order.php');
$show = file_get_contents(__DIR__.'/../vestra/showroom.php');
ok(strpos($prod, 'vestra_sizes_selectable($p)') !== false, 'urun sayfasi kutuyu tek fonksiyondan ciziyor');
ok(strpos($ord,  'vestra_sizes_selectable($p)') !== false, '/order kapiyi ayni fonksiyondan soruyor');
ok(strpos($ord,  "'sizes'=>\$sizes") !== false,            'siparis satiri bedeni tasiyor');
ok(strpos($ord,  "'Sizes — '") !== false,                  'siparis notu beden parcasini yaziyor');
ok(strpos($show, 'vestra_showroom_hides_country(') !== false, 'showroom kurali tek fonksiyondan okuyor');
/* Dokuz sozlukte de anahtarlar dolu olmali: eksik anahtar sessizce Ingilizceye
   duser ve yarisi cevrilmis bir sayfa hic cevrilmemisten kotu gorunur. */
foreach (['de','fr','es','it','pt','ru','ar','ja'] as $lg) {
    $d = require __DIR__."/../vestra/inc/lang/$lg.php";
    foreach (['Sizes','Choose your sizes','at least one','Choose at least one size.'] as $k) {
        ok(isset($d[$k]) && trim((string)$d[$k]) !== '', "$lg: \"$k\" cevrili");
    }
}

printf("\n%d iddia, %d hata\n", $T, $F);
exit($F ? 1 : 0);
