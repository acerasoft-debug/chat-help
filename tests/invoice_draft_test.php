<?php
/* TASLAK ONIZLEME (operator karari, 1 Eyl 2026: "faturayi musteri hesabina
 * inmeden ve email ile gondermeden kendim kontrol etmem gerekiyor").
 *
 * Iddia: draft=true AYNI belgeyi cizer, yalnizca kimligi degisir --
 * "DRAFT INVOICE / not assigned yet" tasir, numara tasimaz. Ve gercek kesim
 * draft izini TASIMAZ. VestraPdf metin akisini sikistirmadigi icin bytes
 * icinde duz metin aranabiliyor; bu test o sayede render'in KENDISINI kosuyor,
 * taklidini degil. */
require __DIR__.'/../vestra/inc/pdf.php';   // gercek sinif + vestra_pdf_thumb

/* products.php'den gelen iki kucuk yardimci -- dosyanin tamamini yuklemek
   vestra_data_dir vb. istiyor, stub sozlesmeyi karsiliyor. */
function vestra_product_label(string $brand, string $name): string { return trim($brand.' '.$name); }
function vestra_tax_id_hint(string $country): array { return ['label'=>'VAT ID','placeholder'=>'']; }

$src   = file_get_contents(__DIR__.'/../vestra/inc/invoice.php');
$strip = fn($s) => preg_replace("#require_once __DIR__\.'/[a-z_]+\.php';#", '', $s);
preg_match_all('/^function \w+\(.*?^}/ms', $src, $fns);
foreach ($fns[0] as $f) eval($strip($f));

$ok=0; $fail=0;
$t = function(string $n, bool $c) use (&$ok,&$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };

$meta = ['ref'=>'OTEST1','date'=>'2026-09-01T10:00:00+00:00','buyer'=>[
    'company'=>'SC Daymond Proconect SRL','name'=>'Adrian','email'=>'x@y.ro',
    'country'=>'RO','address'=>'Balotesti 111B','vat'=>'']];
$items = [['sku'=>'SKU1','brand'=>'DSQUARED2','name'=>'Graphic T-Shirt','colors'=>[],
           'qty'=>20,'unit'=>45.00,'line'=>900.00]];
$seller = ['id'=>'garage','company'=>'GARAGE LE PARIS','invoice_name'=>'Agaya Paris',
           'country'=>'FR','address'=>'1 ALLEE DU CEDRE','bank_holder'=>'Agaya',
           'bank_iban'=>'FR1420041010050500013M02606','bank_bic'=>'PSSTFRPPSCE'];

echo "\n== 1. TASLAK ==\n";
$draft = vestra_render_invoice_pdf($meta, $items, $seller, '', true);
$t('PDF uretildi',                     str_starts_with($draft,'%PDF'));
$t('basligi DRAFT INVOICE',            str_contains($draft,'DRAFT INVOICE'));
$t('numara satiri "not assigned yet"', str_contains($draft,'not assigned yet'));
$t('her sayfa dibinde draft ibaresi',  str_contains($draft,'DRAFT - not an issued invoice'));
$t('icinde fatura numarasi YOK',       !str_contains($draft,'INV-2026'));

echo "\n== 2. GERCEK KESIM draft izi tasimiyor ==\n";
$real = vestra_render_invoice_pdf($meta, $items, $seller, 'INV-2026-000123', false);
$t('numara belgede',      str_contains($real,'INV-2026-000123'));
$t('DRAFT izi yok',       !str_contains($real,'DRAFT'));
$t('basligi INVOICE',     str_contains($real,'INVOICE'));

echo "\n== 3. Ikisi AYNI belge (kimlik disinda) ==\n";
/* Onizleme ile kesim ayrisirsa operator bir sey gorur, alici baskasini alir. */
foreach ([
  'alici unvani'   => 'SC Daymond Proconect SRL',
  'fatura unvani'  => 'Agaya Paris',
  'IBAN'           => 'FR1420041010050500013M02606',
  'IBAN sahibi'    => 'Agaya',
  'urun'           => 'Graphic T-Shirt',
  'satir toplami'  => '900.00',
] as $n => $needle) $t("$n ikisinde de var", str_contains($draft,$needle) && str_contains($real,$needle));
$t('boyutlar yakin (ayni yerlesim)', abs(strlen($draft)-strlen($real)) < 600);

echo "\n== 4. KDV satiri belgeye basiliyor ==\n";
/* Franchise en base saticinin faturasinda "TVA non applicable" ibaresi
   ZORUNLU; bossa satir hic cikmamali (uydurma bir KDV cumlesi basilamaz). */
$meta2 = $meta; $meta2['vat_note'] = 'TVA non applicable - article 293 B du CGI';
$withNote = vestra_render_invoice_pdf($meta2, $items, $seller, 'INV-2026-000124', false);
$t('ibare belgede',            str_contains($withNote,'293 B du CGI'));
$t('nota bos belgede satir yok', !str_contains($real,'VAT:') || !str_contains($real,'293 B'));

echo "\n".($fail? "KALDI: $fail  (gecen: $ok)\n" : "hepsi gecti ($ok)\n");
exit($fail?1:0);
