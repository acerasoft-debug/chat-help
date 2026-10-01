<?php
/* SKU SUTUNU KOMSU SUTUNA BASMAZ (28 Eyl 2026, INV-2026-1016 / VES-60594A18).
 *
 * Belgenin kendisinden olculdu: faturada "TENNIS-CLUB-ICON-WH" 8 pt'de x=54..146
 * cizildi, "Description" sutunu x=142'de basliyor -- 4 pt UST USTE. Sebep olcu:
 * vestra_pdf_width() Helvetica icin 0.52 em ORTALAMA kullaniyor, buyuk harf +
 * rakam + tireden olusan bir model kodunda gercek genislik ~0.605 em. Sarma
 * "sigdi" sanip satiri kirmiyordu. Siparis PDF'i SKU'yu hic sarmiyordu bile.
 *
 * Tutulan:
 *   1. vestra_pdf_width_afm() Adobe AFM'in kendisi (tablo PyMuPDF Base-14'e
 *      karsi 95/95 dogrulandi; burada bilinen degerlerle sabitleniyor).
 *   2. KONTROL GRUBU: eski olcu ayni dizgeyi gercekten dar olcuyor -- yoksa
 *      bu test hicbir seyi ispatlamaz.
 *   3. Sarici exact kipte her satiri sutuna sigdiriyor, harf kaybetmiyor.
 *   4. BELGELER GERCEKTEN CIZILIYOR ve her SKU parcasinin SAG KENARI komsu
 *      sutunun basindan once bitiyor (fatura + siparis PDF'i) -- koordinat
 *      icerik akisindan okunuyor, kaynak taramasi degil.
 *   5. Genel olcu (vestra_pdf_width) DEGISMEDI: fiyat listesi ve kur notu
 *      sarmalari ona gore ayarli.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/pdf.php';
require_once $root . '/inc/money.php';
require_once $root . '/inc/products.php';
require_once $root . '/inc/invoice.php';
require_once $root . '/inc/orders.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$near = fn(float $a, float $b, float $eps = 0.01): bool => abs($a - $b) < $eps;

echo "== 1. Gercek Helvetica olcusu (AFM) ==\n";
$t('TENNIS-CLUB-ICON-WH @8 = 91.992 (belgede olculen 92.0)', $near(vestra_pdf_width_afm('TENNIS-CLUB-ICON-WH', 8), 91.992));
$t('rakamlar 556/1000',         $near(vestra_pdf_width_afm('0123456789', 10), 55.6));
$t('W 944/1000',                $near(vestra_pdf_width_afm('W', 10), 9.44));
$t('i 222/1000 (dar harf)',     $near(vestra_pdf_width_afm('i', 10), 2.22));
$t('bosluk 278/1000',           $near(vestra_pdf_width_afm(' ', 10), 2.78));
$t('kalin b 611 != normal b 556', $near(vestra_pdf_width_afm('b', 10, true), 6.11) && $near(vestra_pdf_width_afm('b', 10), 5.56));
$t('kalin ! 333 (normal 278)',  $near(vestra_pdf_width_afm('!', 10, true), 3.33) && $near(vestra_pdf_width_afm('!', 10), 2.78));
$t('ASCII disi -> genel olcuye duser (×)', $near(vestra_pdf_width_afm('×', 10), vestra_pdf_width('×', 10)));
$t('CJK -> gomulu yazi tipi olcusu (香)',  $near(vestra_pdf_width_afm('香', 10), vestra_pdf_width('香', 10)));
$t('karisik: ASCII tablodan + ASCII disi genelden',
   $near(vestra_pdf_width_afm('A×', 10), 6.67 + vestra_pdf_width('×', 10)));
$t('bos dizge 0',               vestra_pdf_width_afm('', 10) === 0.0);

echo "\n== 2. KONTROL GRUBU: eski olcu bu dizgede gercekten dar ==\n";
$old = vestra_pdf_width('TENNIS-CLUB-ICON-WH', 8);
$t('eski olcu 80 pt sutuna "sigar" diyor ('.round($old, 1).')', $old <= 80.0);
$t('gercek genislik 80 pt sutunu ASIYOR',                           vestra_pdf_width_afm('TENNIS-CLUB-ICON-WH', 8) > 80.0);
/* Genel olcu BILEREK degismedi -- degisseydi her PDF'in duzeni kayardi. */
$t('genel olcu hala 0.52 em ortalama', $near(vestra_pdf_width('abcdefghij', 10), 52.0));

echo "\n== 3. Sarici exact kipte sutuna sigdiriyor ==\n";
$skus = ['TENNIS-CLUB-ICON-WHITE', 'LARCHE-COLORE-PRINTED-BLACK', 'WWWWWWWWWWWWWWWW', 'G9OW6Z DARKBLUE', 'AMI-PL-014'];
foreach ($skus as $s) {
    $ln  = vestra_invoice_wrap($s, 80, 8, false, true);
    $max = max(array_map(fn($x) => vestra_pdf_width_afm($x, 8), $ln));
    $t("{$s}: her satir <= 80 pt (en genis ".round($max, 1).")", $max <= 80.0 + 1e-9);
    $t("{$s}: harf kaybolmuyor", str_replace(' ', '', implode('', $ln)) === str_replace(' ', '', $s));
}
/* Kod KENDI ayiracindan kirilir -- ikinci satir tireyle BASLAMAZ. */
$t('9 pt / 88: "TENNIS-CLUB-" + "ICON-WHITE"', vestra_invoice_wrap('TENNIS-CLUB-ICON-WHITE', 88, 9, false, true) === ['TENNIS-CLUB-', 'ICON-WHITE']);
$t('8 pt / 80: "TENNIS-CLUB-ICON-" + "WHITE"', vestra_invoice_wrap('TENNIS-CLUB-ICON-WHITE', 80, 8, false, true) === ['TENNIS-CLUB-ICON-', 'WHITE']);
$dashStart = [];
foreach ([[80, 8], [88, 9], [60, 8]] as [$mw, $sz])
    foreach ($skus as $s)
        foreach (vestra_invoice_wrap($s, $mw, $sz, false, true) as $i => $l)
            if ($i > 0 && str_starts_with($l, '-')) $dashStart[] = "{$s}@{$sz}";
$t('hicbir devam satiri tireyle baslamiyor'.($dashStart ? ' ('.implode(', ', $dashStart).')' : ''), $dashStart === []);
$t('ayiracsiz kod yine harf harf kiriliyor (W…W)', count(vestra_invoice_wrap('WWWWWWWWWWWWWWWW', 80, 8, false, true)) === 2);
$legacy = vestra_invoice_wrap('TENNIS-CLUB-ICON-WHITE', 80, 8);
$t('KONTROL: eski kipte bir satir 80 pt\'yi asiyor', max(array_map(fn($x) => vestra_pdf_width_afm($x, 8), $legacy)) > 80.0);
$t('kisa kod tek satir kaliyor', count(vestra_invoice_wrap('AMI-PL-014', 80, 8, false, true)) === 1);

/* Icerik akisindaki her metin parcasi: [font, boy, x, y, metin]. */
$runs = function (string $pdf): array {
    $out = [];
    if (!preg_match_all('/BT \/(F\d) ([\d.]+) Tf ([\d.]+) ([\d.]+) Td \(((?:[^()\\\\]|\\\\.)*)\) Tj ET/s', $pdf, $m, PREG_SET_ORDER)) return [];
    foreach ($m as $r) {
        $s = (string)preg_replace_callback('/\\\\([0-7]{1,3}|.)/s',
            fn($e) => ctype_digit($e[1][0]) ? chr(octdec($e[1]) & 0xFF) : $e[1], $r[5]);
        $out[] = [$r[1], (float)$r[2], (float)$r[3], (float)$r[4], (string)@iconv('CP1252', 'UTF-8//IGNORE', $s)];
    }
    return $out;
};
$xOf = function (array $runs, string $label): ?float {
    foreach ($runs as $r) if ($r[4] === $label) return $r[2];
    return null;
};

echo "\n== 4. FATURA gercekten ciziliyor: SKU aciklamaya BASMIYOR ==\n";
$meta  = ['ref'=>'SKUCOL1','date'=>'2026-09-28T10:00:00+00:00','shipping'=>20.0,'discount'=>0.0,
          'buyer'=>['company'=>'Test Buyer','name'=>'T','country'=>'France','address'=>'1 rue X, 75001 Paris','vat'=>'','reg'=>'']];
$items = [];
foreach ($skus as $i => $s) $items[] = ['sku'=>$s,'brand'=>'Casablanca','name'=>'Tennis Club Icon T-Shirt — White','colors'=>['White'],'qty'=>10,'unit'=>50.0,'line'=>500.0];
$seller = ['id'=>'s1','company'=>'Seller Co','country'=>'FR','address'=>'1 Allee','bank_iban'=>'DE89370400440532013000','bank_holder'=>'Seller Co'];
$inv = vestra_render_invoice_pdf($meta, $items, $seller, 'INV-TEST-SKU', false);
$t('fatura uretildi', str_starts_with($inv, '%PDF'));
$R = $runs($inv);
$skuX  = $xOf($R, 'SKU');
$descX = $xOf($R, 'Description');
$t('baslik konumlari okundu (SKU / Description)', $skuX !== null && $descX !== null && $descX > $skuX);
/* Yalniz GERCEK SKU parcalari: ayni x'te baska metin de olabilir (siparis
   PDF'inde alici blogu SKU sutunuyla ayni x'ten basliyor). */
$isSku = fn(string $txt): bool => $txt !== '' && (bool)array_filter($skus, fn($s) => str_contains($s, $txt));
$pieces = array_values(array_filter($R, fn($r) => $skuX !== null && $r[1] === 8.0 && abs($r[2] - $skuX) < 0.01 && $isSku($r[4])));
$t('SKU parcalari bulundu ('.count($pieces).')', count($pieces) >= count($skus));
$over = [];
foreach ($pieces as $p) if ($p[2] + vestra_pdf_width_afm($p[4], 8) > $descX - 1.0) $over[] = $p[4];
$t('hicbir SKU parcasi aciklama sutununa tasmiyor'.($over ? ' ('.implode(', ', $over).')' : ''), $over === [] && $descX !== null);
$drawn = vestra_pdf_drawn_text($inv);
foreach ($skus as $s) $t("belgede tam SKU: {$s}", str_contains($drawn, str_replace(' ', '', $s)) || str_contains($drawn, $s));

echo "\n== 5. SIPARIS PDF'i gercekten ciziliyor: SKU urun adina BASMIYOR ==\n";
$orderRow = ['ref'=>'VES-SKUCOL','timestamp'=>'2026-09-28T10:00:00+00:00','company'=>'Test Buyer','name'=>'T',
             'email'=>'t@example.com','country'=>'France','shipping'=>'20','discount'=>'0','total'=>'2520'];
$lines = [];
foreach ($skus as $s) $lines[] = ['sku'=>$s,'brand'=>'Casablanca','name'=>'Tennis Club Icon T-Shirt — White','colors'=>[],'sizes'=>[],'qty'=>10,'unit'=>50.0,'line'=>500.0];
$op = vestra_render_order_pdf($orderRow, $lines, 'Pending');
$t('siparis PDF uretildi', str_starts_with($op, '%PDF'));
$O = $runs($op);
$oSkuX  = $xOf($O, 'Model / SKU');
$oProdX = $xOf($O, 'Product');
$t('baslik konumlari okundu (Model / SKU / Product)', $oSkuX !== null && $oProdX !== null && $oProdX > $oSkuX);
$oPieces = array_values(array_filter($O, fn($r) => $oSkuX !== null && $r[1] === 9.0 && abs($r[2] - $oSkuX) < 0.01
                                             && $r[0] === 'F1' && $isSku($r[4])));
$oOver = [];
foreach ($oPieces as $p) if ($p[2] + vestra_pdf_width_afm($p[4], 9) > $oProdX - 1.0) $oOver[] = $p[4];
$t('SKU parcalari bulundu ('.count($oPieces).')', count($oPieces) >= count($skus));
$t('hicbir SKU parcasi urun sutununa tasmiyor'.($oOver ? ' ('.implode(', ', $oOver).')' : ''), $oOver === [] && $oProdX !== null);
$oDrawn = vestra_pdf_drawn_text($op);
foreach ($skus as $s) $t("siparis PDF'inde tam SKU: {$s}", str_contains($oDrawn, str_replace(' ', '', $s)) || str_contains($oDrawn, $s));

echo "\n== 6. Kablolama ==\n";
$src = fn(string $f) => (string)@file_get_contents($root . '/' . $f);
$t('fatura SKU sarmasi exact kipte',
   (bool)preg_match("/skuLines\s*=\s*vestra_invoice_wrap\(\(string\)\(\\\$it\['sku'\] \?\? ''\), [^;]*, 8, false, true\)/", $src('inc/invoice.php')));
$t('siparis PDF SKU sarmasi exact kipte',
   (bool)preg_match("/skuLines\s*=\s*vestra_invoice_wrap\(\(string\)\(\\\$l\['sku'\] \?\? ''\), [^;]*, 9, false, true\)/", $src('inc/orders.php')));
$t('siparis PDF invoice.php\'yi KENDISI yukluyor (KURAL 15)',
   (bool)preg_match("/function vestra_render_order_pdf\(.*?require_once __DIR__\.'\/invoice\.php';.*?vestra_invoice_wrap\(/s", $src('inc/orders.php')));

echo "\nsonuc: {$ok} ok, {$fail} HATA\n";
exit($fail ? 1 : 0);
