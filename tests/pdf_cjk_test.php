<?php
/* FATURA CIN ADRESIYLE CIKAR — gomulu CJK yazi tipi
 * (operator, 7 Eyl 2026: "cin karakterlerini faturaya yazamiyorum ... fatura
 * cin adresi ile ciksin"; alici 香港风徕贸易有限公司 / LINCHAOWEI, 5 Eyl 2026).
 *
 * Tutulan ilkeler:
 *   - Cince/Japonca/Korece/Yunanca/Kiril BELGEYE kendi harfleriyle girer;
 *     eskiden iconv sessizce soru isaretine ceviriyordu.
 *   - Belge KOPYALANABILIR: ToUnicode CMap olmadan belge dogru gorunur ama
 *     adres kopyalanamaz, pdftotext bos doker. Gumrukte adresi elle yeniden
 *     yazdirmak belgeyi yarim birakmak demek.
 *   - Yalnizca O BELGEDE gecen glifler gomulur (tam yazi tipi 10 MB).
 *   - LATIN BELGELER DEGISMEZ: yazi tipi nesnesi bile eklenmez.
 *   - Genislik TAHMIN DEGIL: Han karakteri tam genislik. Tek olcum yeri
 *     vestra_pdf_width(); fatura sarmalayicisi da onu cagirir.
 *   - Yazi tipinin de tasimadigi karakter (emoji, nadir duzlem) hala olculur
 *     ve TASLAKTA operatore yazilir.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/pdf.php';
require_once $root . '/inc/pdf_font.php';
require_once $root . '/inc/money.php';
require_once $root . '/inc/products.php';
require_once $root . '/inc/invoice.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$HK = '香港风徕贸易有限公司';

echo "== 1. Yazi tipi dosyasi yerinde ve okunuyor ==\n";
$t('assets/fonts/vestra-cjk.ttf var', is_readable(vestra_cjk_font_path()));
$font = VestraTtf::shared();
$t('acildi',                 $font instanceof VestraTtf);
$t('paylasilan ornek tek',   $font === VestraTtf::shared());
if (!$font) { printf("\n%d ok, %d hata\n", $ok, $fail + 1); exit(1); }
$t('Cince kapsaniyor',       $font->hasChar(0x9999) && $font->hasChar(0x98CE));   // 香 风
$t('Japonca kana kapsaniyor',$font->hasChar(0x30C6));                              // テ
$t('Hangul kapsaniyor',      $font->hasChar(0xD55C));                              // 한
$t('Kiril + Yunanca',        $font->hasChar(0x041C) && $font->hasChar(0x0395));
$t('Ext A kapsaniyor',       $font->hasChar(0x3435));
$t('emoji YOK (durust sinir)', !$font->hasChar(0x1F9F5));
/* Han karakteri tam genislik: yariya yakin bir olcu satiri kutudan tasirirdi. */
$t('Han ilerlemesi ~1 em',   abs($font->advance1000(0x9999) - 1000) < 60);
$t('Latin ilerlemesi dar',   $font->advance1000(ord('i')) < 500);

echo "\n== 2. Alt kume: yalniz gecen glifler ==\n";
$sub = $font->subset([0x9999, 0x6E2F]);                                            // 香 港
$t('TrueType imzasi',        str_starts_with($sub['data'], "\x00\x01\x00\x00"));
$t('kucuk (tam yazi tipi 10 MB)', strlen($sub['data']) < 60000);
$t('glif sayisi 3 (.notdef + 2)', $sub['glyphs'] === 3);
$t('genislikler CID basina',  ($sub['widths'][1] ?? 0) > 0 && ($sub['widths'][2] ?? 0) > 0);
/* Bilesik glif (Hangul heceleri, aksanli Latin) bilesenlerini de tasimali;
   tasimazsa glif BOS cizilir ve kimse fark etmez. */
$hang = $font->subset([0xD55C, 0xAD6D]);                                            // 한국
$t('bilesik glif bilesenleri de eklendi', $hang['glyphs'] > 3);
$t('bilesikli alt kume de kucuk', strlen($hang['data']) < 200000);

echo "\n== 3. Belge: cizim, gomme, kopyalanabilirlik ==\n";
$p = new VestraPdf();
$p->text(60, 700, 12, $HK);
$p->text(60, 680, 12, 'Latin line');
$pdf = $p->output();
$t('Type0/Identity-H gomulu',   str_contains($pdf, '/Subtype /Type0') && str_contains($pdf, '/Encoding /Identity-H'));
$t('CIDFontType2 + Identity harita', str_contains($pdf, '/CIDFontType2') && str_contains($pdf, '/CIDToGIDMap /Identity'));
$t('yazi tipi dosyasi gomulu',  str_contains($pdf, '/FontFile2') && str_contains($pdf, '/Length1 '));
$t('ToUnicode var (kopyalanabilir)', str_contains($pdf, '/ToUnicode') && str_contains($pdf, 'beginbfchar'));
$t('genislik dizisi var',       str_contains($pdf, '/W [1 ['));
$t('kaynak sozlugunde F3',      str_contains($pdf, '/F3 '));
/* ToUnicode gercekten DOGRU kod noktasini gosteriyor mu: 香 = U+9999. */
$t('ToUnicode 香 -> 9999',      preg_match('~<0001> <9999>~', $pdf) === 1);
$t('belge kucuk kaldi',         strlen($pdf) < 120000);
/* Ayni karakter iki kez gecince IKINCI bir CID acilmaz. */
$p2 = new VestraPdf(); $p2->text(10, 10, 9, $HK); $p2->text(10, 20, 9, $HK);
$t('tekrar eden karakter tek CID', substr_count($p2->output(), 'beginbfchar') === 1
   && preg_match('~(\d+) beginbfchar~', $p2->output(), $m) && (int)$m[1] === 10);

echo "\n== 4. Latin belgeler AYNEN kaliyor ==\n";
$a = new VestraPdf(); $a->text(60, 700, 12, 'Hong Kong Fenglai Trading Co Ltd');
$latin = $a->output();
$t('yazi tipi gomulmedi',   !str_contains($latin, '/FontFile2'));
$t('F3 kaynagi bile yok',   !str_contains($latin, '/F3 '));
$t('Helvetica yolu',        str_contains($latin, '/BaseFont /Helvetica'));
/* CP1252 Bati Avrupa'yi tasir: bunlar gomulu yola DUSMEMELI. */
$b = new VestraPdf(); $b->text(60, 700, 12, 'Café Zürich — Éclaireur');
$t('aksanli Latin hala Helvetica', !str_contains($b->output(), '/FontFile2'));

echo "\n== 5. Olcu ve sarma ==\n";
$w = new VestraPdf();
/* Han karakteri TAM GENISLIK (1 em). Helvetica tahmini her harfe 0.52 em verir;
   bir Cince unvani o olcuyle yariya yakin gorunur ve saga yaslanan satir
   sayfanin disina tasar. */
$t('Han genisligi ~1 em', abs($w->strWidth('香', 10) - 10.0) < 0.6);
$t('Han, Helvetica tahmininden genis', $w->strWidth('香', 10) > $w->strWidth('W', 10));
$t('tek olcum yeri (vestra_pdf_width)', abs($w->strWidth($HK, 10) - vestra_pdf_width($HK, 10)) < 0.01);
/* Cincede BOSLUK YOK: sarma karakter karakter kirmazsa satir kutudan tasar. */
$addr = '香港特别行政区九龍尖沙咀彌敦道一二三號五樓A室收貨部門';
$lines = $w->wrap($addr, 120, 10);
$t('bosluksuz adres birden fazla satira bolundu', count($lines) > 1);
$tooWide = false;
foreach ($lines as $l) if ($w->strWidth($l, 10) > 120.5) $tooWide = true;
$t('hicbir satir kutuyu asmiyor', !$tooWide);
$t('metin kaybolmadi', preg_replace('/\s+/', '', implode('', $lines)) === $addr);
/* Fatura kutusunun kendi sarmalayicisi da AYNI olcuyu kullanmali; ayrisirsa
   alici blogu satici blogunun uzerine biner. */

$iw = vestra_invoice_wrap($addr, 120, 10);
$bad = false;
foreach ($iw as $l) if (vestra_pdf_width($l, 10) > 120.5) $bad = true;
$t('vestra_invoice_wrap da tasirmiyor', !$bad && count($iw) > 1);
unset($lines, $iw);
$t('fatura sarmalayicisi ayni olcuyu cagiriyor',
   str_contains((string)file_get_contents($root.'/inc/invoice.php'), 'vestra_pdf_width($t, $size, $bold)'));

echo "\n== 6. Gercek fatura yukuyle (buyer ALTINDA) ==\n";
/* Yuk sekli uretimdekiyle ayni: vestra_invoice_buyer() 'buyer' altina yazar.
   Taslak uyarisi uzun sure duz $order['company'] okuyordu ve canli faturada
   HIC calismadi -- bu bolum o seklin uzerinden gidiyor. */
$meta = ['ref'=>'OCJK1','date'=>'2026-09-07T10:00:00+00:00','buyer'=>[
    'company'=>$HK, 'name'=>'LINCHAOWEI', 'email'=>'x@example.test',
    'country'=>'Hong Kong', 'address'=>'香港九龍尖沙咀彌敦道123號5樓A室', 'vat'=>'', 'reg'=>'']];
$items = [['sku'=>'AMI-PL-014','brand'=>'AMI Paris','name'=>'Core Logo Polo','colors'=>[],
           'qty'=>120,'unit'=>39.0,'line'=>4680.0]];
$seller = ['id'=>'garage','company'=>'GARAGE LE PARIS','country'=>'FR','address'=>'1 ALLEE DU CEDRE',
           'bank_holder'=>'Agaya','bank_iban'=>'FR1420041010050500013M02606'];
$draft = vestra_render_invoice_pdf($meta, $items, $seller, '', true);
$real  = vestra_render_invoice_pdf($meta, $items, $seller, 'INV-2026-000900', false);
$t('taslakta yazi tipi gomulu',  str_contains($draft, '/FontFile2'));
$t('kesilmis faturada da gomulu', str_contains($real, '/FontFile2'));
$t('Cince aliciya uyari YOK',     !str_contains($draft, 'cannot print these characters'));
/* Yazi tipinin tasimadigi karakter: uyari geri gelir, ama yalniz taslakta. */
$emo = $meta; $emo['buyer']['company'] = 'Fenglai Trading 🧵';
$t('basilamayan karakter taslakta bildirilir',
   str_contains(vestra_render_invoice_pdf($emo, $items, $seller, '', true), 'cannot print these characters'));
$t('musteriye giden belgede ic uyari YOK',
   !str_contains(vestra_render_invoice_pdf($emo, $items, $seller, 'INV-2026-000901', false), 'cannot print these characters'));
/* Uyari GERCEK yuk seklinde de calisiyor mu: 'buyer' altindaki alan taraniyor. */
$t('uyari buyer[] altini tariyor',
   str_contains((string)file_get_contents($root.'/inc/invoice.php'), "\$b = is_array(\$order['buyer'] ?? null)"));
$t('Latin alici belgesine yazi tipi gomulmez', (function () use ($meta, $items, $seller) {
    $m = $meta; $m['buyer']['company'] = 'Hong Kong Fenglai Trading Co Ltd';
    $m['buyer']['address'] = '5 Canton Road, Kowloon';
    return !str_contains(vestra_render_invoice_pdf($m, $items, $seller, '', true), '/FontFile2');
})());

echo "\n== 6b. Belgeye alinmayan alanlar taslakta soylenir ==\n";
/* 7 Eyl 2026, CANLI onizleme (VES-6B53D265): alici vergi alanina bolgenin
   ADINI yazmisti ve fatura "VAT ID: 中国香港特别行政区" basiyordu; ayrica
   hesapta sokak adresi hic yoktu. Ilki bankaya/gumruge giden belgede yanlis
   bilgi, ikincisi eksik bilgi. Rakamsiz deger basilmaz, ikisi de TASLAKTA
   yazilir; musteriye giden belgeye ic not girmez. */
$badVat = $meta; $badVat['buyer']['vat'] = '中国香港特别行政区'; $badVat['buyer']['address'] = '';
$dv = vestra_render_invoice_pdf($badVat, $items, $seller, '', true);
$rv = vestra_render_invoice_pdf($badVat, $items, $seller, 'INV-2026-000902', false);
$t('rakamsiz vergi alani taslakta bildirilir', str_contains($dv, 'holds no digits'));
$t('adressiz alici taslakta bildirilir',       str_contains($dv, 'no street address'));
$t('kesilmis faturada ic not YOK',             !str_contains($rv, 'holds no digits') && !str_contains($rv, 'no street address'));
/* Rakam iceren gercek numara basilmaya devam ediyor -- yanlis pozitif yok. */
$okVat = $meta; $okVat['buyer']['vat'] = 'HK12345678';
$t('gercek numarada uyari YOK', !str_contains(vestra_render_invoice_pdf($okVat, $items, $seller, '', true), 'holds no digits'));
$t('adres varsa uyari YOK',     !str_contains(vestra_render_invoice_pdf($okVat, $items, $seller, '', true), 'no street address'));

echo "\n== 7. Yazi tipi dosyasi YOKSA belge yine cikar ==\n";
/* Dosya sunucuya gitmezse fatura kesilmemeli degil: eski davranisa (soru
   isareti) doner ve taslak uyarisi bunu SOYLER. Sessiz kayip olmaz. */
$t('yol fonksiyonu tek yerde', str_contains((string)file_get_contents($root.'/inc/pdf_font.php'), 'function vestra_cjk_font_path'));
$t('acilamayan dosyada null',  VestraTtf::open('/yok/boyle/bir.ttf') === null);
$t('bozuk dosyada null',       (function () {
    $f = tempnam(sys_get_temp_dir(), 'ttf'); file_put_contents($f, str_repeat('x', 200));
    $r = VestraTtf::open($f); @unlink($f); return $r === null;
})());

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
