<?php
/* FATURAYI KIM KESER.
 *
 * Operator ayni alicinin kabul ettigi teklifleri tek elden faturalandirmak
 * istiyor; sistem ise saticiyi ilanin seller_uid'inden turetiyordu ve bir
 * alis iki ayri saticiya bolunuyordu. Karar artik operatorun ve
 * offer_responses.json'da duruyor. Bu test o onceligi sabitliyor.
 *
 * Onemli: dosya adi satici anahtarindan turuyor (vestra_invoice_file), yani
 * "hangi satici" sorusu belgenin kimliginin parcasi. Sessizce degisirse ayni
 * teklife ikinci bir fatura numarasi yanar.
 */
$src   = file_get_contents(__DIR__.'/../vestra/inc/offers.php');
$strip = fn($s) => preg_replace("#require_once __DIR__\.'/[a-z_]+\.php';#", '', $s);

$JSON = []; $LISTING = null; $LISTING_MAP = [];
$CSV  = [['ref'=>'OF-1','sku'=>'SKU-1','product'=>'Tee','qty'=>10,'offer_unit'=>9.00,
          'offer_total'=>90.00,'email'=>'buyer@example.com','company'=>'Buyer Co',
          'timestamp'=>'2026-09-01T10:00:00+00:00']];

function vestra_read_csv($f){ global $CSV; return $CSV; }
function vestra_read_json($f){ global $JSON; return $JSON; }
function vestra_write_json($f,$d){ global $JSON; $JSON=$d; return true; }
function vestra_listing_by_sku($s){ global $LISTING,$LISTING_MAP; return $LISTING_MAP[$s] ?? $LISTING; }
function vestra_invoices_for_ref($r,$f=true){ global $INVOICED; return in_array($r,$INVOICED??[],true)?[['no'=>'INV-X']]:[]; }
function auth_find($e){ return ['id'=>'buy1','name'=>'Adrian','company'=>'Daymond','vat_id'=>'','country'=>'RO','address'=>'x']; }
function auth_accounts(){ return [
  ['id'=>'garage','type'=>'seller','company'=>'GARAGE LE PARIS','invoice_name'=>'Agaya Paris','bank_holder'=>'Agaya'],
  ['id'=>'tyrex', 'type'=>'seller','company'=>'TYREX INTERNATIONAL BV.'],
]; }
function vestra_platform_seller(){ return ['company'=>'Acerasoft LLC']; }
function vestra_from_price($p){ return 0.0; }

preg_match_all('/^function \w+\(.*?^}/ms', $src, $fns);
foreach ($fns[0] as $f) eval($strip($f));

$ok=0; $fail=0;
$t = function(string $n, bool $c) use (&$ok,&$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };
$who = fn($a) => (string)($a['company'] ?? '');

echo "\n== 1. Secim yokken ILANIN saticisi ==\n";
$LISTING = ['id'=>'l1','sku'=>'SKU-1','name'=>'Tee','seller_uid'=>'tyrex'];
$JSON = [];
$t('ilandan tyrex geldi', $who(vestra_offer_invoice_seller('OF-1',$LISTING))==='TYREX INTERNATIONAL BV.');

echo "\n== 2. OPERATOR SECIMI ilani EZER ==\n";
$JSON = ['OF-1'=>['status'=>'accept','invoice_seller_uid'=>'garage']];
$s = vestra_offer_invoice_seller('OF-1',$LISTING);
$t('garage secildi', $who($s)==='GARAGE LE PARIS');
$t('fatura adi Agaya Paris tasiniyor', ($s['invoice_name']??'')==='Agaya Paris');
$t('IBAN yanindaki isim Agaya', ($s['bank_holder']??'')==='Agaya');

echo "\n== 3. Ilanda satici YOKKEN de secim gecerli ==\n";
/* O6404A bu durumdaydi: seller_uid hic yoktu, fatura zorunlu olarak
   platformdan cikiyordu. Operator artik onu da yonlendirebilmeli. */
$LISTING = ['id'=>'l2','sku'=>'SKU-1','name'=>'Tee'];
$t('secim uygulandi', $who(vestra_offer_invoice_seller('OF-1',$LISTING))==='GARAGE LE PARIS');
$JSON = [];
$t('secim yoksa platform', $who(vestra_offer_invoice_seller('OF-1',$LISTING))==='Acerasoft LLC');

echo "\n== 4. 'vestra' ACIK bir secim -- ilana GERI DONMEZ ==\n";
$LISTING = ['id'=>'l1','sku'=>'SKU-1','name'=>'Tee','seller_uid'=>'tyrex'];
$JSON = ['OF-1'=>['status'=>'accept','invoice_seller_uid'=>'vestra']];
$t('platformdan kesiliyor', $who(vestra_offer_invoice_seller('OF-1',$LISTING))==='Acerasoft LLC');

echo "\n== 5. Var olmayan hesap platforma duser (panelde ONCE dogrulanir) ==\n";
$JSON = ['OF-1'=>['status'=>'accept','invoice_seller_uid'=>'silinmis-hesap']];
$t('platforma dustu', $who(vestra_offer_invoice_seller('OF-1',$LISTING))==='Acerasoft LLC');

echo "\n== 6. Fatura yuku ayni saticiyi tasiyor ==\n";
/* Ekranda gosterilen ile belgeye basilan AYNI cozucuden cikmali. */
$JSON = ['OF-1'=>['status'=>'accept','invoice_seller_uid'=>'garage']];
$p = vestra_offer_invoice_payload('OF-1');
$t('payload garage', $who($p['seller'])==='GARAGE LE PARIS');
$t('miktar korunuyor', (int)$p['qty']===10);
$t('birim anlasilan fiyat', abs($p['unit']-9.00)<0.001);

echo "\n== 7. ONIZLEME gecersiz kilmasi KALICI DEGIL ==\n";
/* Taslak, formda SECILI duran saticiyla cizilir ama secim ancak Approve'da
   kayda gecer. Onizlemenin kendisi kayit yazsaydi, operator taslaga bakip
   vazgectiginde secim sessizce kalmis olurdu. */
$JSON = ['OF-1'=>['status'=>'accept']];
$p = vestra_offer_invoice_payload('OF-1','garage');
$t('taslak secilen saticiyla', $who($p['seller'])==='GARAGE LE PARIS');
$t('kayda HICBIR SEY yazilmadi', !isset($JSON['OF-1']['invoice_seller_uid']));
$JSON = ['OF-1'=>['status'=>'accept','invoice_seller_uid'=>'tyrex']];
$p = vestra_offer_invoice_payload('OF-1','garage');
$t('gecersiz kilma kayitli secimi de EZER (o anki liste ne diyorsa o)', $who($p['seller'])==='GARAGE LE PARIS');
$t('kayitli secim yerinde durdu', ($JSON['OF-1']['invoice_seller_uid']??'')==='tyrex');

echo "\n== 8. KDV satiri (vat_note) ==\n";
/* KDV'siz kesilen faturada gerekcesi belgenin ustunde yazmali; teklif
   yolunun bunu tasiyacak yeri yoktu. Kayittan okunur, onizlemede formdaki
   deger gecersiz kilar, '' acikca "satir yok" demektir. */
$JSON = ['OF-1'=>['status'=>'accept','invoice_vat_note'=>'TVA non applicable - article 293 B du CGI']];
$p = vestra_offer_invoice_payload('OF-1');
$t('kayitli not metaya girdi', ($p['meta']['vat_note']??'')==='TVA non applicable - article 293 B du CGI');
$p = vestra_offer_invoice_payload('OF-1','','Intra-Community supply - reverse charge');
$t('onizleme gecersiz kilmasi', ($p['meta']['vat_note']??'')==='Intra-Community supply - reverse charge');
$t('kayit yerinde durdu', ($JSON['OF-1']['invoice_vat_note']??'')==='TVA non applicable - article 293 B du CGI');
$p = vestra_offer_invoice_payload('OF-1','','');
$t("'' acik silme: satir bos", ($p['meta']['vat_note']??'x')==='');
$JSON = ['OF-1'=>['status'=>'accept']];
$p = vestra_offer_invoice_payload('OF-1');
$t('not yoksa bos (satir basilmaz)', ($p['meta']['vat_note']??'x')==='');

echo "\n== 9. BIRLESIK FATURA (secilen teklifler -> tek belge) ==\n";
/* "urunler secilip tek saticiya tek fatura kesilebilmeli". Kurucu ya tam
   yuk ya ['error'=>...] doner; yarim liste sessizce kesilmez. */
$CSV = [
  ['ref'=>'OF-1','sku'=>'SKU-1','product'=>'Tee','qty'=>10,'offer_unit'=>9.00,'email'=>'buyer@example.com','company'=>'Buyer Co','timestamp'=>'2026-08-30T10:00:00+00:00'],
  ['ref'=>'OF-2','sku'=>'SKU-2','product'=>'Polo','qty'=>20,'offer_unit'=>25.00,'email'=>'buyer@example.com','company'=>'Buyer Co','timestamp'=>'2026-08-31T10:00:00+00:00'],
  ['ref'=>'OF-3','sku'=>'SKU-3','product'=>'Cap','qty'=>5,'offer_unit'=>7.00,'email'=>'other@example.com','company'=>'Other Co','timestamp'=>'2026-09-01T10:00:00+00:00'],
];
$LISTING_MAP = [
  'SKU-1'=>['id'=>'l1','sku'=>'SKU-1','brand'=>'B','name'=>'Tee','seller_uid'=>'garage'],
  'SKU-2'=>['id'=>'l2','sku'=>'SKU-2','brand'=>'B','name'=>'Polo','seller_uid'=>'tyrex'],
  'SKU-3'=>['id'=>'l3','sku'=>'SKU-3','brand'=>'B','name'=>'Cap','seller_uid'=>'garage'],
];
$INVOICED = [];
$JSON = ['OF-1'=>['status'=>'accept','counter_price'=>10.00,'agreed_unit'=>10.00],
         'OF-2'=>['status'=>'accept'],
         'OF-3'=>['status'=>'accept']];

$p = vestra_offers_combined_invoice_payload(['OF-1','OF-2'],'garage');
$t('iki satir', empty($p['error']) && count($p['items'])===2);
$t('satir 1 ANLASILAN fiyat (10, ilk teklif 9 degil)', abs($p['items'][0]['unit']-10.00)<0.001);
$t('toplam 10*10+25*20=600', abs($p['total']-600.00)<0.001);
$t('satir adinda ref var', str_contains($p['items'][1]['name'],'OF-2'));
$t('birincil ref ilk secilen', ($p['meta']['ref']??'')==='OF-1');
$t('satici operatorun sectigi', $who($p['seller'])==='GARAGE LE PARIS');

$p = vestra_offers_combined_invoice_payload(['OF-1','OF-3']);
$t('farkli ALICI reddedildi', !empty($p['error']) && str_contains($p['error'],'aynı alıcıya ait değil'));

$p = vestra_offers_combined_invoice_payload(['OF-1','OF-2']);
$t('ilanlar farkli saticida + secim yok -> red', !empty($p['error']) && str_contains($p['error'],'farklı satıcılara'));

$JSON['OF-2']['status']='counter';
$p = vestra_offers_combined_invoice_payload(['OF-1','OF-2'],'garage');
$t('kabul edilmemis teklif reddedildi', !empty($p['error']) && str_contains($p['error'],'kabul edilmiş değil'));
$JSON['OF-2']['status']='accept';

$INVOICED = ['OF-2'];
$p = vestra_offers_combined_invoice_payload(['OF-1','OF-2'],'garage');
$t('faturasi kesilmis teklif reddedildi', !empty($p['error']) && str_contains($p['error'],'zaten kesilmiş'));
$INVOICED = [];

/* Ayni satici tum ilanlarda -> secimsiz de kesilebilir */
$LISTING_MAP['SKU-2']['seller_uid']='garage';
$p = vestra_offers_combined_invoice_payload(['OF-1','OF-2']);
$t('ortak ilan saticisina otomatik dusme', empty($p['error']) && $who($p['seller'])==='GARAGE LE PARIS');

echo "\n== 10. KARGO + REDRAFT ==\n";
/* "faturayi kestik fakat 50 eur shipping ... tekrar yap". Kargo kayittan
   okunur, override onizleme/redraft icindir; redraft yukleyicisi birlesik
   kesilmis belgeyi uyelerinden yeniden kurar ve faturali-olma kontrolunu
   BILEREK atlar (belge zaten var, numara korunacak). */
$LISTING_MAP['SKU-2']['seller_uid']='garage';
$JSON = ['OF-1'=>['status'=>'accept','invoice_seller_uid'=>'garage','invoice_shipping'=>50.0,
                  'invoice_members'=>['OF-1','OF-2']],
         'OF-2'=>['status'=>'accept','invoice_group_ref'=>'OF-1']];
$INVOICED = [];
$p = vestra_offer_invoice_payload('OF-1');
$t('kargo kayittan metaya girdi (50)', abs(($p['meta']['shipping']??0)-50.0)<0.001);
$p = vestra_offer_invoice_payload('OF-1','',null,0.0);
$t('override 0 = kargosuz onizleme', abs(($p['meta']['shipping']??-1)-0.0)<0.001);

$p = vestra_offer_invoice_redraft_payload('OF-1', 50.0);
$t('faturasiz ref redraft REDDEDILIR', !empty($p['error']));
$INVOICED = ['OF-1'];
$p = vestra_offer_invoice_redraft_payload('OF-1', 50.0);
$t('redraft yuku kuruldu', empty($p['error']));
$t('birlesik: iki uye satiri', count($p['items']??[])===2);
$t('kargo 50 belgeye gidiyor', abs(($p['meta']['shipping']??0)-50.0)<0.001);
$t('satici kayitli secimden (garage)', $who($p['seller'])==='GARAGE LE PARIS');
$t('birincil ref korunuyor', ($p['meta']['ref']??'')==='OF-1');
$INVOICED = [];

echo "\n== 10b. REDRAFT'ta UYE LISTESI DEGISTIRILEBILIR ==\n";
/* Alici bir kalemi iptal ettirdiginde (Daymond, 1 Eyl 2026) o satir
   belgeden cikmali; kalan kalemler eklenebilmeli. Birincil ref KALMAK
   ZORUNDA -- numara ve dosya adi ona bagli. */
$LISTING_MAP['SKU-3']['seller_uid']='garage';
$CSV[2]['email']='buyer@example.com';           // OF-3 ayni aliciya alinsin
$JSON = ['OF-1'=>['status'=>'accept','invoice_seller_uid'=>'garage','invoice_members'=>['OF-1','OF-2'],
                  'counter_price'=>10.00,'agreed_unit'=>10.00],
         'OF-2'=>['status'=>'accept','invoice_group_ref'=>'OF-1'],
         'OF-3'=>['status'=>'accept']];
$INVOICED = ['OF-1'];
$p = vestra_offer_invoice_redraft_payload('OF-1', 50.0, ['OF-1','OF-3']);
$t('uye degistirildi: iki satir', empty($p['error']) && count($p['items'])===2);
$t('CIKARILAN OF-2 belgede yok', empty($p['error']) && !str_contains(json_encode($p['items']),'OF-2'));
$t('EKLENEN OF-3 belgede var',   empty($p['error']) && str_contains(json_encode($p['items']),'OF-3'));
$p = vestra_offer_invoice_redraft_payload('OF-1', 50.0, ['OF-2','OF-3']);
$t('birincil ref cikarilamaz', !empty($p['error']) && str_contains($p['error'],'listede kalmalı'));
$p = vestra_offer_invoice_redraft_payload('OF-1', 50.0, []);
$t('bos liste reddedilir', !empty($p['error']));
$INVOICED = [];

echo "\n== 10c. REDRAFT siparis satirini da GUNCELLER ==\n";
/* Belge degistiyse siparis de degismeli. Daymond dosyasinda bu eksikti:
   fatura 4 kalem / 3.950 EUR derken siparis 2 kalem / 1.600 EUR'da kalmis,
   ayni satis alicinin My orders sayfasinda faturadan BASKA rakam
   gosteriyordu. Fonksiyonun $update bayragini tasidigini dogruluyoruz --
   dosya yazimi sunucuda, burada sozlesme sinaniyor. */
$rf = new ReflectionFunction('vestra_offer_order_ensure');
$ps = $rf->getParameters();
$t('vestra_offer_order_ensure($p, $update) imzasi', count($ps) === 2 && $ps[1]->getName() === 'update');
$t('varsayilan HALA idempotent (ikinci satir uretmez)', $ps[1]->isDefaultValueAvailable() && $ps[1]->getDefaultValue() === false);

echo "\n== 10d. REDRAFT meta TUTARINI da tazeler ==\n";
/* Paneller ve alicinin fatura satiri meta'daki 'total'i okuyor. Redraft
   yalnizca PDF'i yeniden yaziyordu; belge 3.950 EUR derken ekran
   "INV-2026-1001 · 6.300,00 EUR" gosteriyordu -- ayni faturanin iki rakami. */
$src2 = file_get_contents(__DIR__.'/../vestra/inc/invoice.php');
preg_match('/^function vestra_ensure_invoice\(.*?^}/ms', $src2, $mE);
$t('redraft dalinda total yeniden hesaplaniyor',
   isset($mE[0]) && str_contains($mE[0], "\$meta['total']    = round(\$goodsR"));
$t('redraft dalinda currency tazeleniyor',
   isset($mE[0]) && str_contains($mE[0], "\$meta['currency'] ="));

echo "\n== 11. FATURALANAN TEKLIF -> ORDERS SATIRI ==\n";
/* "order bolumune de gitmeli". Satir checkout'un KENDI semasina yazilir ve
   items dizgisi vestra_parse_order_items'in regex'iyle geri okunabilmeli --
   okunamazsa siparis dosyasi kalemleri 'cozulemedi' gosterir. Toplam,
   faturanin genel toplamiyla AYNI olmali (mal+kargo); farkli olsaydi ayni
   satisin iki belgesi iki rakam soylerdi. */
$ORDERS_TMP = sys_get_temp_dir().'/vestra_test_orders_'.getmypid();
@mkdir($ORDERS_TMP.'/data', 0775, true);
/* offers.php dirname(__DIR__)'i kendi konumundan turetir; test icin yazilan
   satiri dogrudan dosyadan okuyup dogrulayacagiz. Yol carpismasin diye
   fonksiyonun yazacagi gercek dosyayi gecici dizine yonlendiremeyiz --
   bunun yerine SATIRI kuran mantigi ayni girdiyle dogrudan sinariz. */
$JSON = ['OF-1'=>['status'=>'accept','invoice_seller_uid'=>'garage','invoice_shipping'=>50.0,
                  'counter_price'=>10.00,'agreed_unit'=>10.00],
         'OF-2'=>['status'=>'accept']];
$INVOICED = [];
$p = vestra_offers_combined_invoice_payload(['OF-1','OF-2'],'garage',null,50.0);
$t('yuk kuruldu', empty($p['error']));
$goods = array_sum(array_column($p['items'],'line'));
$t('mal toplami 600', abs($goods-600.0)<0.001);
$segsOk = true;
foreach ($p['items'] as $it) {
    $seg = (int)$it['qty'].'x '.$it['sku'].' @'.number_format((float)$it['unit'],2,'.','');
    if (!preg_match('/^(\d+)x\s+(.+)\s+@([\d.]+)$/', $seg, $m)) { $segsOk = false; break; }
    if ((int)$m[1] !== (int)$it['qty'] || trim($m[2]) !== $it['sku'] || abs((float)$m[3]-(float)$it['unit'])>0.001) { $segsOk = false; break; }
}
$t('items dizgisi cozucuyle geri okunuyor', $segsOk);
$t('siparis toplami = fatura genel toplami (650)', abs(($goods + (float)$p['meta']['shipping']) - 650.0) < 0.001);


/* ── SIPARIS tarafinda satici secimi (5 Eyl 2026) ────────────────────────────
 * Operator: "yeni siparislerde satici secme opsiyonu olmasi gerekiyordu".
 * Tekliflerde bu secim vardi (yukaridaki bolumler), SIPARISLERDE yoktu: satici
 * yalniz ilanin seller_uid'inden geliyordu. VES-6B53D265'te ilan GARAGE LE
 * PARIS'indi ama fatura VESTRA'dan kesilecekti ve degistirme yolu hic yoktu. */
echo "\n== siparis: operator satici secimi ==\n";
$osrc = file_get_contents(__DIR__.'/../vestra/inc/invoice.php');
foreach (['vestra_order_invoice_seller_pick','vestra_order_set_invoice_seller'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $osrc, $m)) { echo "  HATA $fn bulunamadi\n"; $fail++; continue; }
    eval($strip($m[0]));
}
$JSON = [];
$t('secim yokken bos doner', vestra_order_invoice_seller_pick('VES-1') === '');
/* 'vestra' ACIK bir secim: platform kessin demek, ilana geri donmez. */
$t('vestra secilebilir',     vestra_order_set_invoice_seller('VES-1','vestra') === true);
$t('kaydedilen geri okunur', vestra_order_invoice_seller_pick('VES-1') === 'vestra');
$t('kayda yazildi', ($JSON['VES-1']['invoice_seller_uid'] ?? '') === 'vestra');
/* Gecerli satici hesabi. */
$t('gercek satici uid kabul', vestra_order_set_invoice_seller('VES-1','garage') === true);
$t('secim guncellendi',       vestra_order_invoice_seller_pick('VES-1') === 'garage');
/* BULUNAMAYAN HESAP KAYDEDILMEZ -- sessizce platforma dusmek, operatorun
   secmedigi tuzel kisiden belge cikarmak olurdu (KURAL 5b'nin kendi notu). */
$t('olmayan hesap REDDEDILIR', vestra_order_set_invoice_seller('VES-1','hayali-uid') === false);
$t('reddedilince ESKI secim durur', vestra_order_invoice_seller_pick('VES-1') === 'garage');
/* Bos string secimi KALDIRIR: ilanin saticisina geri don. */
$t('bos string secimi siler', vestra_order_set_invoice_seller('VES-1','') === true);
$t('silindikten sonra bos',   vestra_order_invoice_seller_pick('VES-1') === '');
$t('anahtar da kalkti',       !isset($JSON['VES-1']['invoice_seller_uid']));
/* Diger siparis durumu alanlarina DOKUNMAZ. */
$JSON['VES-2'] = ['status'=>'pending','tracking'=>'1Z9','payment_grace_start'=>123];
vestra_order_set_invoice_seller('VES-2','vestra');
$t('status korunur',   ($JSON['VES-2']['status'] ?? '') === 'pending');
$t('tracking korunur', ($JSON['VES-2']['tracking'] ?? '') === '1Z9');
$t('odeme saati korunur', ($JSON['VES-2']['payment_grace_start'] ?? 0) === 123);
$t('ref temizlenir (yol gecisi yok)', vestra_order_set_invoice_seller('../../etc/passwd','vestra') === true && isset($JSON['etcpasswd']));

echo "\n".($fail? "KALDI: $fail  (gecen: $ok)\n" : "hepsi gecti ($ok)\n");
exit($fail?1:0);
