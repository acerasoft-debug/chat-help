<?php
/* FATURADA CIN/JAPON/KORE KARAKTERLERI (operatör, 7 Eyl 2026: "faturaya cin
 * karakteri olmuyor halen").
 *
 * 5 Eyl 2026'da bu karakterler SESSIZCE soru isaretine donuyordu: Hong Kong'lu
 * alicinin kayitli sirket adi belgeye "??????????" diye basiliyordu -- bozuk bir
 * dosya degil, gecerli GORUNEN ama musterinin adi eksik bir fatura. O gun
 * yalnizca TASLAGA bir uyari kondu; karakterler hala basilmiyordu.
 *
 * Cozum: yalnizca BELGEDE GECEN gliflerden olusan bir alt kume yazi tipi gomuluyor
 * (VestraTtf). Bu test iki yonu de tutar:
 *   - CJK metin gercekten glif olarak giriyor ve KOPYALANABILIR kaliyor (ToUnicode),
 *   - Latin fatura DEGISMIYOR: tek bayt yazi tipi eklenmiyor.
 */
require __DIR__.'/../vestra/inc/pdf.php';            // VestraPdf + VestraTtf + yardimcilar
function vestra_product_label(string $b, string $n): string { return trim($b.' '.$n); }
function vestra_tax_id_hint(string $c): array { return ['label'=>'VAT ID','placeholder'=>'']; }
$src   = file_get_contents(__DIR__.'/../vestra/inc/invoice.php');
$strip = fn($s) => preg_replace("#require_once __DIR__\.'/[a-z_]+\.php';#", '', $s);
preg_match_all('/^function \w+\(.*?^}/ms', $src, $fns);
foreach ($fns[0] as $f) eval($strip($f));

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

echo "== 1. Gomulecek yazi tipi ==\n";
$path = vestra_pdf_embed_font_path();
$t('yazi tipi bulundu',         $path !== '' && is_readable($path));
$t('depoyla birlikte geliyor',  str_contains($path, 'assets/fonts/vestra-cjk.ttf'));
$font = VestraTtf::open($path);
$t('acilabiliyor', $font !== null);
if ($font === null) { echo "\nTOPLAM: {$ok} gecti, ".(++$fail)." kaldi\n"; exit(1); }

echo "\n== 2. Kapsam: dort yazi da glif buluyor ==\n";
foreach (['繁體 (HK/TW)'=>'香', '简体'=>'风', '日本語'=>'渋', '한글'=>'한',
          'Kiril'=>'Ж', 'Yunan'=>'Ω', 'Latin'=>'A'] as $lbl => $ch) {
    $t("{$lbl}: glif var", $font->gidFor(mb_ord($ch, 'UTF-8')) > 0);
}
$t('tanimsiz kod noktasi 0 doner', $font->gidFor(0x10FFFD) === 0);

echo "\n== 3. Alt kume ==\n";
$cps = [];
foreach (preg_split('//u', '香港時尚貿易有限公司 Aa1', -1, PREG_SPLIT_NO_EMPTY) as $ch) {
    $cps[] = $font->gidFor(mb_ord($ch, 'UTF-8'));
}
$sub = $font->subset(array_filter($cps));
$t('alt kume uretildi',            is_array($sub) && isset($sub['font'], $sub['map'], $sub['widths']));
$t('gecerli sfnt basligi',         substr($sub['font'], 0, 4) === "\x00\x01\x00\x00");
/* Tam yazi tipi 11 MB. Alt kume onun yaninda ihmal edilebilir olmali, yoksa her
   fatura okunamaz buyuklukte cikar -- gommenin butun anlami bu. */
$t('alt kume kaynaktan cok kucuk', strlen($sub['font']) < 60 * 1024);
$t('.notdef 0 numarada',           ($sub['map'][0] ?? null) === 0);
$t('her glifin genisligi var',     count($sub['widths']) === count($sub['map']));
$t('4 bayt hizali',                strlen($sub['font']) % 4 === 0);

echo "\n== 4. Fatura: CJK gercekten basiliyor ==\n";
$items  = [['sku'=>'SKU1','brand'=>'DSQUARED2','name'=>'Graphic T-Shirt','colors'=>[],'qty'=>20,'unit'=>45.00,'line'=>900.00]];
$seller = ['id'=>'g','company'=>'GARAGE LE PARIS','invoice_name'=>'Agaya Paris','country'=>'FR',
           'address'=>'1 ALLEE DU CEDRE','bank_holder'=>'Agaya',
           'bank_iban'=>'FR1420041010050500013M02606','bank_bic'=>'PSSTFRPPSCE'];
$mk = fn(array $buyer, bool $draft = false) => vestra_render_invoice_pdf(
    ['ref'=>'OX','date'=>'2026-09-07T10:00:00+00:00','buyer'=>$buyer], $items, $seller,
    $draft ? '' : 'INV-2026-1099', $draft);

$hk = ['company'=>'香港時尚貿易有限公司','name'=>'陳大文','email'=>'a@b.hk','country'=>'HK',
       'address'=>'香港九龍尖沙咀廣東道 5 號 12 樓','vat'=>'','reg'=>'123'];
$cjk = $mk($hk);
$t('PDF uretildi',                str_starts_with($cjk, '%PDF'));
$t('gomulu yazi tipi dosyasi',    str_contains($cjk, '/FontFile2'));
$t('Type0 / Identity-H',          str_contains($cjk, '/Subtype /Type0') && str_contains($cjk, '/Encoding /Identity-H'));
$t('CIDFontType2 tanimlayici',    str_contains($cjk, '/Subtype /CIDFontType2'));
/* ToUnicode olmadan belge dogru GORUNUR ama metin kopyalanamaz/aranamaz --
   gumrukte ve muhasebede faturadan ad kopyalamak siradan bir is. */
$t('ToUnicode haritasi var',      str_contains($cjk, '/ToUnicode') && str_contains($cjk, 'beginbfchar'));
$t('glif dizisi yazilmis',        preg_match('/<[0-9A-F]{8,}> Tj/', $cjk) === 1 || preg_match_all('/<[0-9A-F]{4,}> Tj/', $cjk) >= 3);
$t('yer tutucu kalmadi',          preg_match('/\x01\d+\x01 Tj/', $cjk) === 0);
$t('bos glif dizisi yok',         !str_contains($cjk, '<> Tj'));
$t('soru isaretine dusmedi',      !str_contains($cjk, '(??'));

echo "\n== 5. Latin fatura DEGISMIYOR ==\n";
$latin = $mk(['company'=>'SC Daymond Proconect SRL','name'=>'Adrian','email'=>'a@b.ro',
              'country'=>'RO','address'=>'Balotesti 111B','vat'=>'','reg'=>'260']);
$t('yazi tipi gomulmedi',   !str_contains($latin, '/FontFile2'));
$t('Type0 eklenmedi',       !str_contains($latin, '/Type0'));
$t('kaynaklarda F3 yok',    !str_contains($latin, '/F3 '));
/* Aksanli Bati Avrupa metni CP1252'de zaten var: gomme yoluna girmemeli,
   yoksa her Fransizca fatura bosuna yazi tipi tasirdi. */
$acc = $mk(['company'=>'Café Zürich Éclaireur','name'=>'Amélie','email'=>'a@b.fr',
            'country'=>'FR','address'=>'12 rue de la Paix','vat'=>'','reg'=>'9']);
$t('aksanli Latin de gommuyor', !str_contains($acc, '/FontFile2'));

echo "\n== 6. Olcum ve sarma ==\n";
$pdf = new VestraPdf();
$w1 = $pdf->strWidth('香', 10.0);
$t('CJK genisligi gercek ilerlemeden (~1 em)', $w1 > 8.5 && $w1 < 11.5);
$t('Latin genisligi degismedi', abs($pdf->strWidth('A', 10.0) - 5.2) < 0.01);
/* CJK'de kelime arasi bosluk yok: bosluga gore bolen sarmalayici bir Cince
   adresi tek satirda birakir ve sayfadan tasar. */
$lines = $pdf->wrap('香港九龍尖沙咀廣東道五號十二樓', 30.0, 10.0);
$t('CJK karakter karakter sariliyor', count($lines) >= 4);
$t('Latin sarmasi kelime bazli kaldi', $pdf->wrap('alpha beta gamma', 1000.0, 10.0) === ['alpha beta gamma']);

echo "\n== 7. Taslak uyarisi artik yalniz GERCEK kayipta ==\n";
/* 5 Eyl 2026'da uyari her CJK karakter icin cikiyordu; o metin artik basiliyor.
   Basilabilen bir ad icin "Latin harfli ad verin" demek, operatoru olmayan bir
   ise yollamak olurdu. */
$t('Cince artik kayip degil',      vestra_pdf_missing_glyphs('香港時尚貿易有限公司') === []);
$t('Japonca artik kayip degil',    vestra_pdf_missing_glyphs('東京都渋谷区') === []);
$t('Kiril/Yunan artik kayip degil',vestra_pdf_missing_glyphs('Москва Ελλάδα') === []);
$t('gercekten glifi olmayan bildiriliyor', vestra_pdf_missing_glyphs("\u{10FFFD}") === ["\u{10FFFD}"]);
$t('CP1252 metni hic bakilmadan gecer',    vestra_pdf_missing_glyphs('Cafe Zurich') === []);
$t('taslakta CJK uyarisi CIKMIYOR', !str_contains($mk($hk, true), 'cannot be printed'));

echo "\nTOPLAM: {$ok} gecti, {$fail} kaldi\n";
exit($fail === 0 ? 0 : 1);
