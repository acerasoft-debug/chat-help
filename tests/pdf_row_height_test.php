<?php
/* SATIR YUKSEKLIGI, SARILAN BUTUN BLOKLARI SAYAR (1 Eki 2026, INV-2026-1022 / VES-3507BF86).
 *
 * Belgenin kendisi gozle acilinca goruldu: numune kolisi faturasinda ("her renkten
 * bir parca", 11 kalem / 71 adet) renk sutunu dar (~84 pt) ve 8 pt'de satir basina
 * 10 pt ilerleyerek 4-6 satira sariliyordu; satir yuksekligi ise yalniz ACIKLAMA ve
 * SKU satirlarindan hesaplaniyor (30 pt) -- renkler alttaki kalemin ve "Goods total"
 * blogunun USTUNE basti. Uretim adiminin kendi olcumleri ("11/11 SKU cizili",
 * "toplam VAR", "odeme kutusu VAR") hepsi DOGRUYDU: hicbiri NEREYE cizildigini
 * sormuyordu. 28 Eyl'deki SKU sutunu kusurunun ayni sinifi.
 *
 * Siparis ozeti PDF'i ayni kusuru TASIYORDU: renk + beden tek alt satirda birlesiyor
 * ama yalniz TEK satirlik 10 pt ayriliyordu, alt blok 2-3 satira sarilinca alttaki
 * kalemin ustune biniyordu.
 *
 * Tutulan (koordinatlar icerik akisindan okunuyor, kaynak taramasi degil):
 *   1. FIXTURE gercekten sariliyor (yoksa test hicbir sey ispatlamaz).
 *   2. Fatura: bir kalemin EN ALT renk satiri, sonraki kalemin satir tabanindan en az
 *      10 pt YUKARIDA; son kalemde "Goods total" etiketinden.
 *   3. KONTROL: tek renkli kalemlerin satir yuksekligi ESKIYLE AYNI (21 pt) -- duzeltme
 *      var olan belgelerin duzenini kaydirmiyor.
 *   4. Siparis ozeti ayni iki iddia.
 *   5. Kablolama: iki cizici de sarilan satir sayisini yuksekliğe katiyor.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/pdf.php';
require_once $root . '/inc/money.php';
require_once $root . '/inc/products.php';
require_once $root . '/inc/invoice.php';
require_once $root . '/inc/orders.php';
if (!function_exists('t')) require_once $root . '/inc/i18n.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

/* Icerik akisindaki her metin parcasi: [font, boy, x, y, metin, akis sirasi]. */
$runs = function (string $pdf): array {
    $out = [];
    if (!preg_match_all('/BT \/(F\d) ([\d.]+) Tf ([\d.]+) ([\d.]+) Td \(((?:[^()\\\\]|\\\\.)*)\) Tj ET/s', $pdf, $m, PREG_SET_ORDER)) return [];
    foreach ($m as $i => $r) {
        $s = (string)preg_replace_callback('/\\\\([0-7]{1,3}|.)/s',
            fn($e) => ctype_digit($e[1][0]) ? chr(octdec($e[1]) & 0xFF) : $e[1], $r[5]);
        $out[] = [$r[1], (float)$r[2], (float)$r[3], (float)$r[4], (string)@iconv('CP1252', 'UTF-8//IGNORE', $s), $i];
    }
    return $out;
};
$xOf = function (array $runs, string $label): ?float {
    foreach ($runs as $r) if ($r[4] === $label) return $r[2];
    return null;
};
$yOf = function (array $runs, string $label): ?float {
    foreach ($runs as $r) if ($r[4] === $label) return $r[3];
    return null;
};

/* GERCEK renk adlari (katalogun kendi dagarcigi): sarilma uzunlugu adlarin genisligine
   bagli, "c1, c2" gibi kisa belirteclerle olcmek uretimdeki durumu temsil etmezdi. */
$TEN = ['Black', 'White', 'Beige', 'Navy', 'Yellow', 'Pink', 'Light Blue', 'Green', 'Blue', 'Red'];
$FOURTEEN = array_merge($TEN, ['Bordeaux', 'Light Grey', 'Dark Green', 'Off White']);

/* Fixture: [sku, renkler, beden]. Siralama bilerek karisik. ILK kalem (14 renk + 5 beden)
   ORTADA bir kalem olarak sinaniyor: siparis ozetinde alt blok 3 satira sariliyor ve
   yalniz bir SONRAKI kalemin varligi bindirmeyi gosterir -- ilk yazimda en cok sarilan
   kalem SONDA idi ve eski (kusurlu) davranis siparis ozeti iddialarindan GECIYORDU:
   son kalemin altinda "Goods total"a kadar bol yer var. Son kalem toplam blogunu sinar. */
$FX = [
    ['ROWTEST-A', $FOURTEEN,                 ['S', 'M', 'L', 'XL', 'XXL']],
    ['ROWTEST-B', ['Black'],                 []],
    ['ROWTEST-C', ['White'],                 []],
    ['ROWTEST-D', $TEN,                      ['S', 'M', 'L', 'XL', 'XXL']],
    ['ROWTEST-E', $FOURTEEN,                 ['S', 'M', 'L', 'XL', 'XXL']],
];

/* Her kalemin: ilk SKU parcasinin akis sirasi + y'si ve ona ait renk/alt satir parcalari
   (AKIS SIRASIYLA atfedilir: kalemler sirayla cizilir; y'ye gore atfetmek, bindiginde
   satirlari yanlis kalemin sayardi ve kusuru gizlerdi). */
$attribute = function (array $R, float $skuX, float $skuSize, float $subX, float $subSize) use ($FX): array {
    $first = [];
    foreach ($FX as $i => [$sku]) {
        foreach ($R as $r) {
            if ($r[1] === $skuSize && abs($r[2] - $skuX) < 0.01 && $r[4] === $sku) { $first[$i] = $r; break; }
        }
    }
    $out = [];
    foreach ($FX as $i => $_) {
        if (!isset($first[$i])) { $out[$i] = null; continue; }
        $from = $first[$i][5];
        $to   = isset($first[$i + 1]) ? $first[$i + 1][5] : PHP_INT_MAX;
        $sub  = array_values(array_filter($R, fn($r) => $r[5] > $from && $r[5] < $to
                                                      && $r[1] === $subSize && abs($r[2] - $subX) < 0.01));
        $out[$i] = ['y' => $first[$i][3], 'sub' => $sub,
                    'lowest' => $sub ? min(array_map(fn($r) => $r[3], $sub)) : null, 'n' => count($sub)];
    }
    return $out;
};

echo "== 1. FATURA gercekten ciziliyor ==\n";
$meta = ['ref' => 'ROWH1', 'date' => '2026-10-01T10:00:00+00:00', 'shipping' => 30.0, 'discount' => 0.0,
         'buyer' => ['company' => 'Test Buyer', 'name' => 'T', 'country' => 'France', 'address' => '1 rue X, 75001 Paris', 'vat' => '', 'reg' => '']];
$items = [];
foreach ($FX as [$sku, $cols]) {
    $items[] = ['sku' => $sku, 'brand' => 'Lacoste', 'name' => 'Test Polo Shirt', 'colors' => $cols, 'qty' => count($cols), 'unit' => 30.0, 'line' => 30.0 * count($cols)];
}
$seller = ['id' => 's1', 'company' => 'Seller Co', 'country' => 'FR', 'address' => '1 Allee',
           'bank_iban' => 'DE89370400440532013000', 'bank_holder' => 'Seller Co'];
$inv = vestra_render_invoice_pdf($meta, $items, $seller, 'INV-TEST-ROWH', false);
$t('fatura uretildi', str_starts_with($inv, '%PDF'));
$R   = $runs($inv);
$skuX = $xOf($R, 'SKU');
$colX = $xOf($R, 'Colour(s)');
$t('baslik konumlari okundu (SKU / Colour(s))', $skuX !== null && $colX !== null && $colX > $skuX);
$A = $attribute($R, (float)$skuX, 8.0, (float)$colX, 8.0);
$allFound = !in_array(null, $A, true);
$t('bes kalemin hepsi belgede ('.count(array_filter($A)).'/5)', $allFound);

echo "\n== 2. FIXTURE gercekten sariliyor (yoksa test bos) ==\n";
$t('14 renkli ILK kalem >= 5 satira sarildi ('.($A[0]['n'] ?? 0).' satir)', ($A[0]['n'] ?? 0) >= 5);
$t('10 renkli ORTA kalem >= 4 satira sarildi ('.($A[3]['n'] ?? 0).' satir)', ($A[3]['n'] ?? 0) >= 4);
$t('14 renkli SON kalem >= 5 satira sarildi ('.($A[4]['n'] ?? 0).' satir)', ($A[4]['n'] ?? 0) >= 5);
$t('tek renkli kalem TEK satir',                  ($A[1]['n'] ?? 0) === 1 && ($A[2]['n'] ?? 0) === 1);

echo "\n== 3. Renkler alttaki kalemin / toplam blogunun USTUNE BINMIYOR ==\n";
$clear = 10.0;   // renk satir araligi: bir satirlik bosluk
foreach ([0, 1, 2, 3] as $i) {
    $gap = $A[$i]['lowest'] - $A[$i + 1]['y'];
    $t(sprintf('kalem %d: en alt renk satiri sonraki kalemin tabanindan %.1f pt yukarida (>= %.0f)', $i, $gap, $clear), $gap >= $clear);
}
$goodsY = $yOf($R, 'Goods total');
$t('"Goods total" etiketi bulundu', $goodsY !== null);
$gapLast = $A[4]['lowest'] - (float)$goodsY;
$t(sprintf('son kalem: en alt renk satiri "Goods total"dan %.1f pt yukarida (>= %.0f)', $gapLast, $clear), $goodsY !== null && $gapLast >= $clear);
/* Her renk parcasi belgede eksiksiz (sarma harf/renk kaybetmiyor). */
$drawn = vestra_pdf_drawn_text($inv);
$allColours = true;
/* Ayni satirda kalan "Light Blue" bosluklu, satir sonunda kirilan "Dark" + "Green" bitisik
   cikar (parcalar ayiracsiz birlesiyor): iki yazim da kabul. */
foreach ($FOURTEEN as $c)
    if (!str_contains($drawn, $c) && !str_contains($drawn, str_replace(' ', '', $c))) $allColours = false;
$t('14 renk adinin hepsi belgede cizili', $allColours);

echo "\n== 4. KONTROL: tek renkli kalemlerin satir yuksekligi ESKIYLE AYNI ==\n";
$h12 = $A[1]['y'] - $A[2]['y'];
$t(sprintf('tek renkli iki kalem arasi %.1f pt = 21 (eski formul: max(13, 11) + 8)', $h12), abs($h12 - 21.0) < 0.01);

echo "\n== 5. SIPARIS OZETI PDF'i gercekten ciziliyor ==\n";
$orderRow = ['ref' => 'VES-ROWH', 'timestamp' => '2026-10-01T10:00:00+00:00', 'company' => 'Test Buyer', 'name' => 'T',
             'email' => 't@example.com', 'country' => 'France', 'shipping' => '30', 'discount' => '0', 'total' => '999'];
$lines = [];
foreach ($FX as [$sku, $cols, $sizes]) {
    $lines[] = ['sku' => $sku, 'brand' => 'Lacoste', 'name' => 'Test Polo Shirt', 'colors' => $cols, 'sizes' => $sizes,
                'qty' => count($cols), 'unit' => 30.0, 'line' => 30.0 * count($cols)];
}
$op = vestra_render_order_pdf($orderRow, $lines, 'Pending');
$t('siparis PDF uretildi', str_starts_with($op, '%PDF'));
$O = $runs($op);
$oSkuX  = $xOf($O, 'Model / SKU');
$oProdX = $xOf($O, 'Product');
$t('baslik konumlari okundu (Model / SKU / Product)', $oSkuX !== null && $oProdX !== null && $oProdX > $oSkuX);
$B = $attribute($O, (float)$oSkuX, 9.0, (float)$oProdX, 8.0);
$t('bes kalemin hepsi belgede ('.count(array_filter($B)).'/5)', !in_array(null, $B, true));
$t('renk + beden alt blogu ILK (ortadaki) kalemde >= 3 satira sarildi ('.($B[0]['n'] ?? 0).' satir)', ($B[0]['n'] ?? 0) >= 3);
$t('renk + beden alt blogu SON kalemde >= 3 satira sarildi ('.($B[4]['n'] ?? 0).' satir)', ($B[4]['n'] ?? 0) >= 3);
$t('alt blogu olmayan kalem alt satir basmiyor', ($B[1]['n'] ?? -1) === 1 || ($B[1]['n'] ?? -1) === 0);
foreach ([0, 1, 2, 3] as $i) {
    $gap = $B[$i]['lowest'] !== null ? $B[$i]['lowest'] - $B[$i + 1]['y'] : 99.0;
    $t(sprintf('kalem %d: en alt alt-satir sonraki kalemin tabanindan %.1f pt yukarida (>= %.0f)', $i, $gap, $clear), $gap >= $clear);
}
$oGoodsY = $yOf($O, 'Goods total');
$t('siparis ozeti: "Goods total" bulundu', $oGoodsY !== null);
$oGapLast = ($B[4]['lowest'] ?? 0) - (float)$oGoodsY;
$t(sprintf('siparis ozeti: son kalemin en alt satiri "Goods total"dan %.1f pt yukarida', $oGapLast), $oGoodsY !== null && $oGapLast >= $clear);
/* KONTROL: tek satirlik alt blok eski davranisla ayni (satir + 10 pt). */
$t('KONTROL: tek renkli kalemin alt blogu TEK satir', ($B[2]['n'] ?? 0) === 1);
$h = $B[1]['y'] - $B[2]['y'];
$t(sprintf('KONTROL: tek satirlik alt blok = 21 + 10 = 31 pt (%.1f)', $h), abs($h - 31.0) < 0.01);

echo "\n== 6. Kablolama ==\n";
$src = fn(string $f) => (string)@file_get_contents($root . '/' . $f);
$inv_src = $src('inc/invoice.php');
$ord_src = $src('inc/orders.php');
$t('fatura: satir yuksekligi renk satirlarini sayiyor',
   (bool)preg_match('/\$rowH\s*=\s*max\(13,\s*max\(count\(\$descLines\),\s*count\(\$skuLines\),\s*count\(\$colLines\)\)\s*\*\s*11\)\s*\+\s*8;/', $inv_src));
$t('fatura: renk satirlari ayni $colLines dizisinden cizilir (ikinci sarma yok)',
   substr_count($inv_src, "vestra_invoice_wrap(implode(', ', (array)\$it['colors'])") === 1);
$t('siparis ozeti: alt blok sarilan SATIR SAYISI kadar yer ayiriyor',
   (bool)preg_match('/\$subH\s*=\s*count\(\$subLines\)\s*\*\s*10;/', $ord_src)
   && (bool)preg_match('/\$y\s*-=\s*\$rowH\s*\+\s*\$subH;/', $ord_src));
$t('siparis ozeti: sayfa sonu kontrolu alt blogu da sayiyor', (bool)preg_match('/\$need\(\$rowH\s*\+\s*\$subH\);/', $ord_src));

echo "\nsonuc: {$ok} ok, {$fail} HATA\n";
exit($fail ? 1 : 0);
