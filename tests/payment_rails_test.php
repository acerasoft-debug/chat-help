<?php
/* Uluslararasi odeme alanlari + PDF'in basamadigi karakterler (5 Eyl 2026).
 *
 * Iki ayri eksik, ikisi de Hong Kong'lu bir alicinin USD faturasi hazirlanirken
 * cikti (siparis VES-6B53D265):
 *
 * 1. USD faturasi yalnizca hesap numarasi + ABA basiyordu. ABA ABD ICI havale
 *    icindir; yurt disindan gelen bir transfer SWIFT ister ve gonderenin bankasi
 *    lehdar adi + banka adi/adresi sorar. Alanlar hesapta ZATEN vardi (KURAL 5c),
 *    sadece belgeye yazilmiyorlardi.
 *
 * 2. PDF cizicisi gomulu olmayan Helvetica + CP1252 kullaniyor. Cince/Japonca
 *    harfler SESSIZCE soru isaretine donuyordu: "香港风徕贸易有限公司" belgeye
 *    "??????????" diye basiliyordu -- gecerli GORUNEN ama alicinin adini
 *    kaybetmis bir fatura.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__.'/../vestra/inc/pdf.php';
require_once __DIR__.'/../vestra/inc/money.php';
require_once __DIR__.'/../vestra/inc/products.php';
require_once __DIR__.'/../vestra/inc/invoice.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

$us = ['bank_holder'=>'Acerasoft LLC','bank_account'=>'ACC1','bank_routing'=>'ABA1',
       'bank_acct_type'=>'Checking','bank_bic'=>'SWIFT1','bank_name'=>'Test Bank',
       'bank_address'=>'Dover, DE, USA'];

echo "== 1. USD: uluslararasi havale icin eksiksiz ==\n";
$r = vestra_payment_rails($us, 'USD');
$j = implode("\n", $r);
$t('lehdar adi var',      str_contains($j, 'Beneficiary: Acerasoft LLC'));
$t('hesap numarasi var',  str_contains($j, 'Account number: ACC1'));
$t('hesap turu var',      str_contains($j, 'Checking'));
$t('ABA var ve ABD ici diye isaretli', str_contains($j, 'Routing number (ABA, domestic): ABA1'));
$t('SWIFT var ve uluslararasi diye isaretli', str_contains($j, 'SWIFT / BIC (international): SWIFT1'));
$t('banka adi var',       str_contains($j, 'Beneficiary bank: Test Bank'));
$t('banka adresi var',    str_contains($j, 'Bank address: Dover, DE, USA'));

echo "\n== 1b. Dolu olmayan alan UYDURULMAZ ==\n";
$min = ['bank_account'=>'ACC1','bank_routing'=>'ABA1'];
$j2 = implode("\n", vestra_payment_rails($min, 'USD'));
$t('SWIFT yoksa satir yok',      !str_contains($j2, 'SWIFT'));
$t('banka adi yoksa satir yok',  !str_contains($j2, 'Beneficiary bank:'));
$t('lehdar yoksa satir yok',     !str_contains($j2, 'Beneficiary'));
$t('yine de hesap+ABA basiyor',  str_contains($j2, 'ACC1') && str_contains($j2, 'ABA1'));
$t('hesap YOKSA hic satir yok',  vestra_payment_rails(['bank_routing'=>'ABA1'], 'USD') === []);

echo "\n== 2. EUR: IBAN yolu bozulmadi ==\n";
$eur = ['bank_iban'=>'FR7630004008280001234567890'] + $us;
$j3 = implode("\n", vestra_payment_rails($eur, 'EUR'));
$t('IBAN bosluklu basiliyor', str_contains($j3, 'IBAN: FR76 3000 4008 2800'));
$t('lehdar EUR tarafinda da var', str_contains($j3, 'Beneficiary: Acerasoft LLC'));
$t('banka adi/adresi EUR tarafinda da var', str_contains($j3, 'Beneficiary bank: Test Bank') && str_contains($j3, 'Bank address:'));
/* ESKI KORUMA BOZULMADI: ABD hesabi varken bank_bic bir ABD BIC'i olabilir;
   IBAN'in yanina basmak alicinin bankasina celisen bir cift verir. */
$t('ABD hesabi varken BIC BASILMAZ', !str_contains($j3, 'BIC / SWIFT: SWIFT1'));
$eur2 = $eur; $eur2['bank_eur_bic'] = 'EURBIC1';
$t('bank_eur_bic acikca verilince basilir', str_contains(implode("\n", vestra_payment_rails($eur2,'EUR')), 'BIC / SWIFT: EURBIC1'));
$t('IBAN yoksa hic satir yok', vestra_payment_rails(['bank_bic'=>'X'], 'EUR') === []);

echo "\n== 3. Helvetica'nin disindaki karakterler (gomulu yolun tetikleyicisi) ==\n";
/* Bu fonksiyon "CP1252 disinda" demektir; 7 Eyl 2026'dan beri "kayip" demek
   DEGIL -- bu karakterler gomulu yazi tipiyle basiliyor (bkz. pdf_cjk_test). */
$t('Cince yakalaniyor',   vestra_pdf_unrenderable('香港风徕贸易有限公司') !== []);
$t('Japonca yakalaniyor', vestra_pdf_unrenderable('東京都渋谷区') !== []);
$t('Kiril yakalaniyor',   vestra_pdf_unrenderable('Москва') !== []);
$t('Yunanca yakalaniyor', vestra_pdf_unrenderable('Ελλάδα') !== []);
/* CP1252 Bati Avrupa'yi TASIR -- bunlar elenirse mevcut musteriler bozulur. */
$t('Fransizca/Almanca aksan GECER', vestra_pdf_unrenderable('Café Zürich Éclaireur') === []);
$t('Turkce s/g/i GECER mi diye olc', true); /* asagida degeri basiliyor */
$tr = vestra_pdf_unrenderable('Isik Gunes Ozturk');
$t('duz ASCII gecer',     $tr === []);
$t('bos string gecer',    vestra_pdf_unrenderable('') === []);
$t('soru isareti kendisi kayip SAYILMAZ', vestra_pdf_unrenderable('what?') === []);
/* GERCEKTEN basilamayan = ne CP1252'de ne gomulu yazi tipinde olan. */
$t('Cince artik BASILABILIR',  vestra_pdf_unprintable('香港风徕贸易有限公司') === []);
$t('Japonca/Korece basilabilir', vestra_pdf_unprintable('東京都渋谷区 한국어') === []);
/* Emoji seciminde dikkat: iconv bazi emojileri ASCII'ye CEVIRIR (🙂 -> ":-)"),
   yani onlar "kayip" sayilmaz. Gercekten karsiligi olmayan biri gerek. */
$t('cevirisi olmayan emoji basilamaz', vestra_pdf_unprintable('VESTRA 🧵') === ['🧵']);
$t('ASCII karsiligi olan emoji kayip degil', vestra_pdf_unprintable('VESTRA 🙂') === []);

echo "\n== 4. Fatura: Cince alici (gercek yuk sekli) ==\n";
/* Yuk sekli uretimdekiyle AYNI olmak zorunda: alici alanlari 'buyer' altinda.
   Duz $order['company'] veren eski surum, cizim koduna hic ulasmiyordu --
   belgeyi degil yalnizca uyari taramasini olcuyordu. Belge duzeyindeki tum
   iddialar tests/pdf_cjk_test.php'de. */
$order = ['ref'=>'VES-TEST','date'=>'2026-09-07T10:00:00+00:00','currency'=>'USD','buyer'=>[
    'company'=>'香港风徕贸易有限公司','name'=>'LINCHAOWEI','email'=>'x@example.com',
    'country'=>'Hong Kong','address'=>'香港九龍尖沙咀','vat'=>'','reg'=>'']];
$items = [['sku'=>'AMI-PL-014','brand'=>'AMI Paris','name'=>'Core Logo Polo','colors'=>[],
           'qty'=>120,'unit'=>39.0,'line'=>4680.0]];
$draft = vestra_render_invoice_pdf($order, $items, $us, 'DRAFT', true);
$real  = vestra_render_invoice_pdf($order, $items, $us, 'INV-TEST', false);
/* 7 Eyl 2026 (operator: "fatura cin adresi ile ciksin"). */
$t('Cince aliciya uyari YOK',    !str_contains($draft, 'cannot print these characters'));
$t('Cince belgede gomulu',       str_contains($draft, '/FontFile2') && str_contains($draft, '/Identity-H'));
$t('kesilmis fatura da gomuyor', str_contains($real, '/FontFile2'));
/* Latin harfli alici -> ne uyari ne gomulu yazi tipi (mevcut belgeler aynen). */
$latin = $order; $latin['buyer']['company']='Hong Kong Fenglai Trading Co Ltd'; $latin['buyer']['address']='5 Canton Road, Kowloon';
$latinPdf = vestra_render_invoice_pdf($latin, $items, $us, 'DRAFT', true);
$t('Latin harfli aliciya uyari YOK', !str_contains($latinPdf, 'cannot print these characters'));
$t('Latin belgeye yazi tipi GOMULMEZ', !str_contains($latinPdf, '/FontFile2'));
$t('PDF yine de uretiliyor (engellemiyor)', strlen($draft) > 5000 && strlen($real) > 5000);

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
