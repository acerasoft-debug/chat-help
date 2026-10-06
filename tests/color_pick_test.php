<?php
/* RENK SECIMI — operator, 10 Eyl 2026: *"underwear varyasyonlarinda tek
 * varyasyon secilebilir, biri secilirken yoksa anlami kalmaz"*.
 *
 * OLCULEN KUSUR: 146 ic camasiri ilaninda beden secici ciziliyor, RENK secici
 * hic cizilmiyordu -- alici renkleri yalnizca spec alanindaki noktalar olarak
 * goruyor, siparis ederken secemiyordu. Yani iki varyasyondan yalnizca biri
 * secilebiliyordu ve tek basina secilen beden, satiliciya hangi rengi
 * gonderecegini soylemiyordu.
 *
 * SEBEP bir KISIT alaninin ANAHTAR gibi kullanilmasiydi: kutu
 * `!empty($p['min_colors'])` ile aciliyordu, oysa o alan "en az kac renk"
 * demek -- bir SINIR. Rengi olup minimumu olmayan ilan (ku_build_rows bu alani
 * hic yazmiyor) "renk secilemez" muamelesi goruyordu: "minimum yok" ile "secim
 * yok" ayni sey sanilmisti.
 *
 * Test IKI YONU birden tutuyor ve ikisi de gerekli:
 *   1) ACILMALI  — ic camasiri ilaninda (>=2 renk) kutu cikmali.
 *   2) ACILMAMALI — giyim/ayakkabi ilaninda, RENGI OLSA BILE, cikmamali:
 *      680 mevcut ilanin satin alma akisini sessizce degistirmek istenenin
 *      disindaydi (bedendeki bolme opt-in kararinin aynisi). Tek yon
 *      yazilsaydi test yesil kalir, katalogun geri kalani sessizce degisirdi.
 */

require_once __DIR__ . '/../vestra/inc/products.php';

$fail = 0; $n = 0;
function ok(bool $c, string $m): void {
    global $fail, $n; $n++;
    if (!$c) { $fail++; echo "  KALDI: {$m}\n"; }
}
$src = fn(string $f) => (string)file_get_contents(__DIR__ . '/../vestra/' . $f);

/* ── 1) Karar: kimde secilebilir ────────────────────────────────────────── */
$uw = ['section' => 'underwear', 'colors' => ['White', 'Black', 'Nude']];
ok(count(vestra_colors_selectable($uw)) === 3,        'ic camasiri, 3 renk: secilebilir');
ok(vestra_colors_selectable(['section'=>'underwear','colors'=>['White']]) === [],
   'tek renk secim degil, bilgi (bedendeki count<2 kuralinin aynisi)');
ok(vestra_colors_selectable(['section'=>'underwear','colors'=>[]]) === [],
   'rengi olmayan ilan');
ok(vestra_colors_selectable(['section'=>'underwear','colors'=>['White','White','Black']]) === ['White','Black'],
   'tekrar eden renk bir kez');
ok(vestra_colors_selectable(['section'=>'underwear','colors'=>['White','   ','Black']]) === ['White','Black'],
   'bos dizge atiliyor');

/* TERS YON: bolme opt-in degilse renk secimi ACILMAZ. */
ok(vestra_colors_selectable(['section'=>'premium','colors'=>['A','B','C']]) === [],
   'giyim ilani, renkli ama opt-in degil: ACILMAZ');
ok(vestra_colors_selectable(['section'=>'footwear','colors'=>['A','B','C','D']]) === [],
   'ayakkabi ilani: ACILMAZ');
ok(vestra_colors_selectable(['colors'=>['A','B']]) === [],
   'bolmesiz ilan (premium varsayilani): ACILMAZ');

/* min_colors YAZILI ilanlarin davranisi DEGISMEDI -- bu dal olmasaydi bugun
   kutusu cikan bir kisim ilandan kutu sessizce kalkardi. */
ok(count(vestra_colors_selectable(['section'=>'premium','colors'=>['A','B','C'],'min_colors'=>2])) === 3,
   'min_colors yazili giyim ilani: eskisi gibi ACILIR');
ok(count(vestra_colors_selectable(['section'=>'premium','colors'=>['A'],'min_colors'=>1])) === 1,
   'min_colors yazili TEK renkli ilan: eskisi gibi ACILIR (davranis korunuyor)');

/* ── 2) Kablolama: sayfa da, /order da AYNI fonksiyonu soruyor ──────────── */
$prod = $src('product.php'); $ord = $src('order.php');
ok(str_contains($prod, 'vestra_colors_selectable('), 'urun sayfasi karari soruyor');
ok(str_contains($ord,  'vestra_colors_selectable('), '/order karari soruyor (kutuyu cizmemek kapi degil)');
/* Eski anahtar-gibi-kullanim geri gelmesin: kutunun cizdigi liste artik karar
   fonksiyonundan geliyor, ham `$p['colors']`'tan degil. Iddia YAZIMI degil
   OLGUYU olcuyor -- ilk yazimda blogun tam metnini sabitlemistim ve bu, bu
   deponun `invoice_vat_test`'te bir kez odedigi bedelin aynisiydi. */
ok((bool)preg_match('/foreach\s*\(\s*\$pickColors\s+as\s+\$cn\s*\)/', $prod),
   'siparis kutusu $pickColors uzerinde donuyor (ham $p[colors] degil)');
ok(str_contains($prod, 'needColors()'), 'istemci tarafi "en az bir renk" kontrolu var');
ok(str_contains($prod, 'clwarn'),       'secim yokken uyari alani basiliyor');

/* Teklif formlarina EKLENMEDI, bilerek: ic camasirinda teklif kapali
   (mode=fixed) ve teklif kaydi renk/beden sutunu tasimiyor -- yarim
   baglanmis bir alan, degeri dusen bir kutu olurdu (KURAL 21b'nin karari). */
ok(substr_count($prod, 'vestra_colors_selectable(') === 1,
   'karar YALNIZCA siparis kutusunda soruluyor, teklif formlarinda degil');

/* ── 3) Metin 8 dilde birden (KURAL 10) ─────────────────────────────────── */
$missing = [];
foreach (glob(__DIR__ . '/../vestra/inc/lang/*.php') as $f) {
    if (!str_contains((string)file_get_contents($f), "'Choose at least one colour.'")) {
        $missing[] = basename($f, '.php');
    }
}
ok($missing === [], 'yeni metin 8 sozlukte de var, eksik: ' . implode(',', $missing));
/* Kardesi zaten oradaydi: yeni anahtar onun YANINA konuldu ki ikisi birlikte
   okunsun ve birlikte guncellensin. */
ok(count(glob(__DIR__ . '/../vestra/inc/lang/*.php')) === 8, '8 sozluk taraniyor');

echo $fail
    ? "\ncolor_pick_test: {$fail}/{$n} KALDI\n"
    : "color_pick_test: {$n} iddia gecti\n";
exit($fail ? 1 : 0);
