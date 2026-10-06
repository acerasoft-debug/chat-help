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
/* 'short' ALANI SART: platform kutusu vergi kimligini "EIN: …" diye etiketliyor
   ve stub onu tasimayinca PHP uyarisi verip etiketi bos birakti -- eksik bir stub,
   olcumu sessizce degistirir. Gercek imza products.php:1104. */
function vestra_tax_id_hint(string $country): array {
    $c = strtoupper(trim($country));
    return in_array($c, ['US','USA','UNITED STATES'], true)
        ? ['label'=>'EIN (Federal Tax ID)','placeholder'=>'12-3456789','short'=>'EIN']
        : ['label'=>'VAT ID','placeholder'=>'','short'=>'VAT ID'];
}
/* vestra_platform_seller() buradan okuyor. Bos bir dizine bakiyor: BANKA ALANI
   OLMAYAN platform kaydi, yani odeme kutusunun cikmadigi hal -- olcmek istedigimiz
   durum tam olarak bu. Canli dosyayi okumak testi sunucunun o anki verisine
   baglardi ve bir gun IBAN girilince iddia sessizce anlamsizlasirdi. */
function vestra_data_dir(): string { return sys_get_temp_dir().'/vestra_draft_test_nodata'; }

$src   = file_get_contents(__DIR__.'/../vestra/inc/invoice.php');
$strip = fn($s) => preg_replace("#require_once __DIR__\.'/[a-z_]+\.php';#", '', $s);
preg_match_all('/^function \w+\(.*?^}/ms', $src, $fns);
foreach ($fns[0] as $f) eval($strip($f));

$ok=0; $fail=0;
$t = function(string $n, bool $c) use (&$ok,&$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };

$meta = ['ref'=>'OTEST1','date'=>'2026-09-01T10:00:00+00:00','buyer'=>[
    'company'=>'SC Daymond Proconect SRL','name'=>'Adrian','email'=>'x@y.ro',
    'country'=>'RO','address'=>'Balotesti 111B','vat'=>'','reg'=>'26088643']];
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
  /* IBAN belgeye 4'lu gruplarla basilir (operator istegi, 1 Eyl 2026) --
     bitisik 27 hane insan gozuyle dogrulanamiyor. */
  'IBAN (4lu gruplar)' => 'FR14 2004 1010 0505 0001 3M02 606',
  'IBAN sahibi'    => 'Agaya',
  /* "Seller of record" beyani belgenin USTUNDEKI adla ayni olmali; duz
     'company' okunuyordu ve ayni belge ustte "Agaya Paris", altta
     "GARAGE LE PARIS" diyordu (operator karari, 1 Eyl 2026). */
  'Seller of record beyani' => 'Seller of record',
  /* Alicinin sicil numarasi (operator istegi, 1 Eyl 2026) -- VAT'siz alicida
     sirketi belgeye baglayan tek resmi numara. */
  'alici Reg. no'  => 'Reg. no: 26088643',
  'urun'           => 'Graphic T-Shirt',
  'satir toplami'  => '900.00',
] as $n => $needle) $t("$n ikisinde de var", str_contains($draft,$needle) && str_contains($real,$needle));
$t('boyutlar yakin (ayni yerlesim)', abs(strlen($draft)-strlen($real)) < 600);
/* Vitrin adi (company) belgeye HIC girmemeli: satici 'invoice_name' verdiyse
   fatura bastan sona o adi tasir. Ayni belgede iki tuzel kisi adi gumrukte
   ve ihtilafta belgeyi zayiflatir. */
$t('vitrin adi (GARAGE LE PARIS) belgede YOK', !str_contains($real,'GARAGE LE PARIS'));
$t('fatura unvani belgede',                    str_contains($real,'Agaya Paris'));

echo "\n== 4. KDV satiri belgeye basiliyor ==\n";
/* Franchise en base saticinin faturasinda "TVA non applicable" ibaresi
   ZORUNLU; bossa satir hic cikmamali (uydurma bir KDV cumlesi basilamaz). */
$meta2 = $meta; $meta2['vat_note'] = 'TVA non applicable - article 293 B du CGI';
$withNote = vestra_render_invoice_pdf($meta2, $items, $seller, 'INV-2026-000124', false);
$t('ibare belgede',            str_contains($withNote,'293 B du CGI'));
$t('nota bos belgede satir yok', !str_contains($real,'VAT:') || !str_contains($real,'293 B'));
/* Hazir sablonlar (datalist) AKSAN ve UZUN TIRE tasiyor; PDF metni CP1252'ye
   cevirerek basiyor. Sablonun oldugu gibi kullanilabilir oldugunun kaniti:
   ASCII kismi duz aranir, aksanli kelime CP1252 karsiligiyla aranir. */
$meta3 = $meta; $meta3['vat_note'] = 'Exonération de TVA — article 262 ter I du CGI (livraison intracommunautaire)';
$b3 = vestra_render_invoice_pdf($meta3, $items, $seller, 'INV-2026-000125', false);
$t('hazir sablonun ASCII kismi belgede', str_contains($b3,'article 262 ter I du CGI'));
$t('aksanli kelime CP1252 olarak belgede', str_contains($b3, iconv('UTF-8','CP1252//TRANSLIT//IGNORE','Exonération de TVA —')));

echo "\n== 5. KARGO belgeye ayri satir olarak giriyor ==\n";
/* meta['shipping'] > 0 iken: Goods total + Shipping + genel toplam. */
$meta4 = $meta; $meta4['shipping'] = 50.0;
$b4 = vestra_render_invoice_pdf($meta4, $items, $seller, 'INV-2026-000126', false);
$t('Shipping satiri var',      str_contains($b4,'Shipping'));
$t('Goods total ayristi',      str_contains($b4,'Goods total'));
$t('genel toplam 950.00',      str_contains($b4,'950.00'));
$t('kargosuz belgede Shipping satiri yok', !str_contains($real,'Shipping'));

echo "\n== 6. PLATFORM KESERKEN: teklif ve siparis AYNI belgeyi vermeli ==\n";
/* Platform renderer'a IKI AYRI SEKILDE geliyor ve bu fark canliya sizdi.
   Kurasyonlu bir ilanin (seller_uid bos) SIPARIS dilimi null geciyor
   (vestra_order_invoice_payloads), ayni ilana verilen TEKLIF ise platformun
   KAYDINI geciriyor (vestra_offer_invoice_seller hicbir zaman null donmez).
   `$sellerAcc === null` diye yazilmis her kontrol, ayni kesen taraf icin
   siparis faturasinda dogru, teklif faturasinda YANLIS cevap veriyordu. */
$platRec = ['company'=>'Acerasoft LLC','address'=>'8 The Green, Suite B, Dover, Delaware 19901',
            'country'=>'US','vat_id'=>'61-2070643'];   // id YOK -- ayirt edici tam olarak bu
$acctRec = ['id'=>'garage','company'=>'GARAGE LE PARIS','address'=>'Paris','country'=>'FR'];

$t('platform kaydi (id yok) platform sayilir', vestra_invoice_is_platform_issuer($platRec));
$t('null da platform sayilir',                 vestra_invoice_is_platform_issuer(null));
$t('gercek hesap platform SAYILMAZ',          !vestra_invoice_is_platform_issuer($acctRec));
/* Ad testi BILEREK yok: operator bir gun gercek bir Acerasoft SATICI hesabi
   acarsa o hesabin banka bilgisi de Admin > Users'ta durur, platform
   dosyasinda degil. Ad ile ayirmak onu yanlis sayfaya yollardi. */
$t('adinda acerasoft gecen HESAP platform sayilmaz',
   !vestra_invoice_is_platform_issuer(['id'=>'acc9','company'=>'Acerasoft LLC']));

/* TASLAK NOTU: odeme kutusu bos kalinca operatoru DOGRU sayfaya yollamali.
   Platformun banka alanlari Admin > Orders'ta; hesaplarinki Admin > Users'ta.
   Bu iddia duzeltmeden ONCE dusuyor: teklif yolunda not "Users" diyordu. */
$nPlat = vestra_invoice_draft_notes($meta, $items, $platRec, 'EUR')['notes'];
$nPlat = implode(' | ', $nPlat);
$t('platform notu Admin > Orders diyor',  str_contains($nPlat,'Admin > Orders'));
$t('platform notu Users demiyor',        !str_contains($nPlat,'Admin > Users'));
$nAcct = implode(' | ', vestra_invoice_draft_notes($meta, $items, $acctRec, 'EUR')['notes']);
$t('hesap notu Admin > Users diyor',      str_contains($nAcct,'Admin > Users'));
$t('hesap notu Orders demiyor',          !str_contains($nAcct,'Admin > Orders'));
/* IBAN'i olan bir hesapta hicbir odeme uyarisi cikmamali -- olmayan bir
   eksigi bildiren uyari, okunmamayi ogretir (KURAL 2c). */
$nFull = implode(' | ', vestra_invoice_draft_notes($meta, $items, $seller, 'EUR')['notes']);
$t('IBANi olan hesapta odeme uyarisi yok', !str_contains($nFull,'payment box'));

/* SATICI KUTUSU: ayni kesen taraf, ayni kunye. Platform dali belgeye
   support@vestrasales.com yaziyor; hesap dali (dogru olarak) yazmiyor --
   oradaki adres bir GIRIS bilgisi. Duzeltmeden once teklif faturasi hesap
   dalindan cikiyordu, yani ayni satis siparis olarak farkli bir satici
   kutusu tasiyordu. */
$bPlat = vestra_render_invoice_pdf($meta, $items, $platRec, 'INV-2026-000130', false);
$t('platform kutusunda support adresi var', str_contains($bPlat,'support@vestrasales.com'));
$t('platform kutusunda EIN var',            str_contains($bPlat,'61-2070643'));
$t('hesap kutusunda support adresi YOK',   !str_contains($real,'support@vestrasales.com'));
/* Feragat cumlesi: platform kendi adina satarken belge kendini yalanlamamali.
   Sarma yuzunden bitisik gecmeyen bir parca araniyor (KURAL 5j: hic
   dusemeyen bir iddia, iddia degildir). */
$t('platform belgesinde feragat cumlesi YOK', !str_contains($bPlat,'operates the marketplace'));
$t('satici belgesinde feragat cumlesi VAR',    str_contains($real,'operates the marketplace'));
/* Ad arm'i hala geciyor: gercek bir Acerasoft hesabi da uclu satis degil. */
$bAcer = vestra_render_invoice_pdf($meta, $items,
          ['id'=>'acc9','company'=>'Acerasoft LLC','country'=>'US'], 'INV-2026-000131', false);
$t('Acerasoft HESABINDA da feragat yok',      !str_contains($bAcer,'operates the marketplace'));

echo "\n".($fail? "KALDI: $fail  (gecen: $ok)\n" : "hepsi gecti ($ok)\n");
exit($fail?1:0);
