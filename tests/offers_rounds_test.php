<?php
define('VESTRA_OFFER_MAX_COUNTERS', 3);
define('VESTRA_OFFER_MIN_BUYER_PCT', 0.50);
$src=file_get_contents(__DIR__.'/../vestra/inc/offers.php');
$strip=fn($s)=>preg_replace("#require_once __DIR__\.'/[a-z_]+\.php';#",'',$s);
$JSON=[]; $NOTIF=[]; $MAIL=[]; $INV=[];
$CSV=[['ref'=>'OF-1','sku'=>'S1','product'=>'P','qty'=>100,'offer_unit'=>9.0,'offer_total'=>900.0,'email'=>'b@x.com','company'=>'C']];
function vestra_read_csv($f){ global $CSV; return $CSV; }
function vestra_read_json($f){ global $JSON; return $JSON; }
function vestra_write_json($f,$d){ global $JSON; $JSON=$d; return true; }
/* Fiyat kurallari icin: referans = en dusuk kademe. 20.00 secildi ki
   alici tabani 10.00 olsun ve asagidaki senaryolar (9.00 ilk teklif,
   10.00/11.50 karsi teklifler) kurallara UYSUN -- test kurallari
   atlatmasin, icinden gecsin. */
function vestra_from_price($p){ if(empty($p['tiers'])) return 0.0; $m=null; foreach($p['tiers'] as $t){ $m=($m===null)?$t['price']:min($m,$t['price']); } return $m; }
function vestra_listing_by_sku($s){ return ['id'=>'p1','sku'=>$s,'brand'=>'B','name'=>'N','seller_uid'=>'s1','tiers'=>[['min'=>1,'price'=>20.00]]]; }
function auth_find($e){ return ['id'=>'b1','name'=>'N']; }
function auth_accounts(){ return [['id'=>'s1','company'=>'S']]; }
function vestra_platform_seller(){ return ['id'=>'plat']; }
function vestra_user_lang($a){ return 'en'; }
function vestra_ensure_invoice($m,$i,$s,$f=false,$r=false){ global $INV; if($f) $INV[]=$i[0]; return ['pending'=>!$f,'no'=>$f?'INV-1':'']; }
function vestra_notify($s,$b){ global $NOTIF; $NOTIF[]=$s; }
function vestra_msg_post_system(...$x){}
function vestra_push_send(...$x){}
function vestra_send_mail(...$a){ global $MAIL; $MAIL[]=$a[1]; return true; }
function vestra_tpl_offer_response(...$a){ return ['counter-mail','b',[]]; }
function vestra_tpl_offer_counter_accepted(...$a){ return ['accepted-mail','b',[]]; }
function vestra_tpl_offer_buyer_countered(...$a){ return ['buyer-counter-mail','b',[]]; }
/* Fatura para birimi izin listesi (KURAL 5i). Gercek govde invoice.php'de ve
   test kosumu require'lari SILIYOR; teklif yuku onu cagiriyor. Stub gercegin
   AYNISI -- uydurma bir liste, olculen davranisi degistirirdi. */
if(!function_exists('vestra_invoice_currencies')) { function vestra_invoice_currencies(){ return ['EUR','USD']; } }
/* ODEME KUTUSU MUHAFAZASI (19 Eyl 2026) da invoice.php'de ve require'lar
   siliniyor. Bu test PAZARLIK TURLARINI olcuyor, odeme kutusunu degil: burada
   'sorun yok' donduruyor ki olculen davranis degismesin. Muhafazanin KENDISI
   kum havuzunda gercekten kesim deneyerek olculuyor --
   tests/invoice_payment_gap_test.php. */
if(!function_exists('vestra_invoice_payment_gap')) { function vestra_invoice_payment_gap($a,$c,$p){ return ''; } }
/* BOLGE VARSAYILANI (KURAL 5s) de invoice.php'de. Stub GUVENLI cunku bu
   dosyanin alici kaydinda ULKE HIC YOK ve gercek govde taninmayan ulkeyi
   bilerek Avrupa sayiyor (belirsizlikte bugunku davranis korunur), yani o da
   '' donuyor -- stub farkli bir cevabi ortmuyor. Varsayilanin kendisi gercek
   bir Amerikali alici ile olculuyor: tests/invoice_payment_gap_test.php. */
if(!function_exists('vestra_invoice_currency_default')) { function vestra_invoice_currency_default($s,$c,$b){ return ''; } }


/* NAVLUN TARIFESI (19 Eyl 2026) inc/orders.php'de; bu dosya offers.php'nin
   govdesini eval ediyor ve require'lari siliyor, yani tarife fonksiyonu
   tanimsiz kaliyor. Stub GUVENLI: bu dosyalarin alicisi Avrupa/ABD olarak
   TANINMAYAN (ya da ulkesiz) bir kayit, yani gercek govde de null donuyor --
   stub ile gerceklik ayni cevabi veriyor, farkli bir cevabi ortmuyor.
   Tarifenin KENDISI ayri bir dosyada, gercek satirlarla olculuyor:
   tests/shipping_tariff_test.php. */
if (!function_exists('vestra_shipping_schedule')) { function vestra_shipping_schedule($l,$c){ return null; } }
/* OTOMASYON ANAHTARI (KURAL 34, 19 Eyl 2026, "simdilik otomatik yapma pasif
   olsun"): vestra_offer_invoice_shipping() artik pure fonksiyonu degil,
   anahtara bakan vestra_shipping_auto_schedule() sarmalini cagiriyor. Stub
   AYNI GEREKCEYLE guvenli -- bu dosyanin iddialari navlun TUTARINI degil tur
   sayacini/fiyat kurallarini olcuyor. Anahtarin KENDISI ayri bir dosyada
   olculuyor: tests/shipping_tariff_test.php §11-12. */
if (!function_exists('vestra_shipping_auto_schedule')) { function vestra_shipping_auto_schedule($l,$c){ return null; } }

preg_match_all('/^function \w+\(.*?^}/ms',$src,$fns);
foreach($fns[0] as $f) eval($strip($f));

$ok=0;$fail=0;
$t=function($n,$c)use(&$ok,&$fail){ $c?($ok++.print("  ok   $n\n")):($fail++.print("  HATA $n\n")); };
$tok=fn()=>(string)($GLOBALS['JSON']['OF-1']['accept_token']??'');
$cnt=fn()=>vestra_offer_counter_count($GLOBALS['JSON']['OF-1']??null);
$turn=fn()=>vestra_offer_turn($GLOBALS['JSON']['OF-1']??null);

echo "\n== Pazarlik: satici -> alici -> satici, sonra 4. TUR ==\n";
$r=vestra_offer_respond('OF-1','counter',12.0,null,'V');
$t('TUR 1 satici 12.00 verdi', $r['ok'] && $cnt()===1);
$t('sira alicida', $turn()==='buyer');
$t('operator simdi karsi teklif VEREMEZ', !vestra_offer_respond('OF-1','counter',11.0,null,'V')['ok']);

$r=vestra_offer_counter_by_buyer('OF-1',$tok(),10.0);
$t('TUR 2 alici 10.00 verdi', $r['ok'] && $cnt()===2);
$t('sira saticida', $turn()==='seller');
$t('kalan tur 1', $r['left']===1);
$t('alicinin eski tokeni YANDI', $tok()==='');

$r=vestra_offer_respond('OF-1','counter',11.0,null,'V');
$t('TUR 3 satici 11.00 verdi', $r['ok'] && $cnt()===3);
$t('kalan tur 0', vestra_offer_counters_left($JSON['OF-1'])===0);

echo "\n== 4. TUR HER YOLDAN reddediliyor ==\n";
$t('alici karsi teklif VEREMEZ', !vestra_offer_counter_by_buyer('OF-1',$tok(),10.5)['ok']);
$t('tur sayisi 3te kaldi', $cnt()===3);
$saveTok=$tok();
$t('ALICI hala KABUL edebilir', vestra_offer_accept_counter('OF-1',$saveTok)['ok']);
$t('kabul fiyati 11.00 (son karsi teklif)', abs(vestra_offer_agreed_unit('OF-1')-11.0)<0.001);

echo "\n== Ret yolu da acik kaliyor (ayri pazarlik) ==\n";
$JSON=[];
vestra_offer_respond('OF-1','counter',12.0,null,'V'); $k1=$tok();
vestra_offer_counter_by_buyer('OF-1',$k1,10.0);
vestra_offer_respond('OF-1','counter',11.0,null,'V'); $k2=$tok();
$t('3 tur doldu', $cnt()===3);
$t('alici REDDEDEBILIR', vestra_offer_decline_counter('OF-1',$k2)['ok']);
$t('durum decline', ($JSON['OF-1']['status']??'')==='decline');
$t('kapanmis pazarlikta sira KIMSEDE degil', $turn()==='');
$t('operator artik yanit veremez', !vestra_offer_respond('OF-1','accept',0,null,'V')['ok']);

echo "\n== Alici KENDI karsi teklifini kabul edemez ==\n";
$JSON=[];
vestra_offer_respond('OF-1','counter',12.0,null,'V'); $k=$tok();
vestra_offer_counter_by_buyer('OF-1',$k,10.0);
$t('kendi teklifini kabul edemez', !vestra_offer_accept_counter('OF-1',$k)['ok']);
$t('kendi teklifini reddedemez',  !vestra_offer_decline_counter('OF-1',$k)['ok']);

echo "\n== Operator alicinin karsi teklifini KABUL: fiyat alicininki ==\n";
$INV=[]; $r=vestra_offer_respond('OF-1','accept',0,null,'V');
$t('kabul ok', $r['ok']);
$t('uzlasilan 10.00 (alicinin teklifi)', abs(vestra_offer_agreed_unit('OF-1')-10.0)<0.001);
$t('ONAYSIZ fatura YOK', count($INV)===0);
vestra_offer_issue_invoice('OF-1', true);
$t('onayli fatura 10.00 / 1000.00', abs(($INV[0]['unit']??0)-10.0)<0.001 && abs(($INV[0]['line']??0)-1000.0)<0.001);

echo "\n== Eski kayit (counters dizisi yok) 1 tur sayilir ==\n";
$JSON=['OF-1'=>['status'=>'counter','counter_price'=>12.0,'accept_token'=>'abc']];
$t('sayaç 1', vestra_offer_counter_count($JSON['OF-1'])===1);
$t('kalan 2', vestra_offer_counters_left($JSON['OF-1'])===2);
$r=vestra_offer_counter_by_buyer('OF-1','abc',10.0);
$t('alici cevap verebilir', $r['ok']);
$t('gecmis tamamlandi: 2 tur', count($JSON['OF-1']['counters'])===2);

/* ── Alicinin KABULU/REDDI pazarlik gecmisini SILMEZ ──────────────────────
 * Iki yol da kaydi sifirdan kuruyordu ve 'counters' dusuyordu: alicinin
 * panelindeki tur cizelgesi bosaliyor, sayac da counter_price'a bakip 1
 * donuyordu -- yani her ret bir tur hakkini sessizce iade ediyordu. */
echo "\n== Kabul/ret pazarlik gecmisini KORUR ==\n";
$JSON=[];
vestra_offer_respond('OF-1','counter',12.0,null,'V'); $h1=$tok();
vestra_offer_counter_by_buyer('OF-1',$h1,10.0);
vestra_offer_respond('OF-1','counter',11.0,null,'V'); $h2=$tok();
$t('3 tur yazildi', $cnt()===3 && count($JSON['OF-1']['counters'])===3);
vestra_offer_decline_counter('OF-1',$h2);
$t('RET sonrasi 3 tur DURUYOR',        count($JSON['OF-1']['counters']??[])===3);
$t('RET sonrasi sayac hala 3',         $cnt()===3);
$t('RET sonrasi kalan tur 0',          vestra_offer_counters_left($JSON['OF-1'])===0);
$JSON=[];
vestra_offer_respond('OF-1','counter',12.0,null,'V'); $h3=$tok();
vestra_offer_counter_by_buyer('OF-1',$h3,10.0);
vestra_offer_respond('OF-1','counter',11.0,null,'V'); $h4=$tok();
vestra_offer_accept_counter('OF-1',$h4);
$t('KABUL sonrasi 3 tur DURUYOR',      count($JSON['OF-1']['counters']??[])===3);
$t('KABUL sonrasi kim ne verdi okunur',
   ($JSON['OF-1']['counters'][0]['by']??'')==='seller'
   && ($JSON['OF-1']['counters'][1]['by']??'')==='buyer'
   && abs((float)($JSON['OF-1']['counters'][1]['price']??0)-10.0)<0.001);

/* ── REDDEDILMIS teklifi satici DAHA IYI fiyatla yeniden acabilir ─────────
 * (operator, 9 Eyl 2026: alici 100 EUR'yu reddetti, operator 90 EUR istedi.)
 * Testin tuttugu ASIL sey iki yon: yeniden acilabilmesi VE kabul edilmis bir
 * teklifin ASLA acilamamasi -- yasagin var olma sebebi o. */
echo "\n== REDDEDILMIS teklif: satici daha ucuza donebilir ==\n";
$JSON=[]; $MAIL=[];
vestra_offer_respond('OF-1','counter',12.0,null,'V'); $kd=$tok();
vestra_offer_decline_counter('OF-1',$kd);
$t('durum decline, sira kimsede degil', ($JSON['OF-1']['status']??'')==='decline' && $turn()==='');
$r=vestra_offer_respond('OF-1','counter',11.0,null,'V');
$t('satici DAHA UCUZ karsi teklifle donebilir', $r['ok']);
$t('durum yeniden counter',            ($JSON['OF-1']['status']??'')==='counter');
$t('sira yeniden alicida',             $turn()==='buyer');
$t('tur sayaci arttı (bedava tur yok)',$cnt()===2);
$t('TAZE token uretildi',              $tok()!=='' && $tok()!==$kd);
$t('aliciya mektup gitti',             count($MAIL)>0);

echo "\n== Yeniden acmada FIYAT KURALLARI aynen gecerli ==\n";
$kd2=$tok(); vestra_offer_decline_counter('OF-1',$kd2);
$t('AYNI fiyatla acilamaz',   !vestra_offer_respond('OF-1','counter',11.0,null,'V')['ok']);
$t('DAHA PAHALI acilamaz',    !vestra_offer_respond('OF-1','counter',12.5,null,'V')['ok']);
$t('urun fiyatinin USTU acilamaz', !vestra_offer_respond('OF-1','counter',25.0,null,'V')['ok']);
$t('tur sayaci reddedilenlerden artmadi', $cnt()===2);
$t('daha ucuz olan GECER',    vestra_offer_respond('OF-1','counter',10.0,null,'V')['ok'] && $cnt()===3);

echo "\n== KABUL EDILMIS teklif ASLA yeniden acilmaz ==\n";
$JSON=[];
vestra_offer_respond('OF-1','counter',12.0,null,'V'); $ka=$tok();
vestra_offer_accept_counter('OF-1',$ka);
$t('durum accept',                     ($JSON['OF-1']['status']??'')==='accept');
$t('kabul edilmise KARSI TEKLIF YOK',  !vestra_offer_respond('OF-1','counter',11.0,null,'V')['ok']);
$t('kabul edilmise RET YOK',           !vestra_offer_respond('OF-1','decline',0,null,'V')['ok']);
$t('uzlasilan fiyat degismedi',        abs(vestra_offer_agreed_unit('OF-1')-12.0)<0.001);

echo "\n== Tur hakki bittiyse yeniden acma da YOK ==\n";
$JSON=[];
vestra_offer_respond('OF-1','counter',12.0,null,'V'); $x1=$tok();
vestra_offer_counter_by_buyer('OF-1',$x1,10.0);
vestra_offer_respond('OF-1','counter',11.0,null,'V'); $x2=$tok();
vestra_offer_decline_counter('OF-1',$x2);
$t('3 tur dolu + reddedilmis',         $cnt()===3 && ($JSON['OF-1']['status']??'')==='decline');
$t('yeniden acilamaz (tur hakki bitti)', !vestra_offer_respond('OF-1','counter',10.5,null,'V')['ok']);

/* ── SATICI KENDI hala yanitlanmamis karsi teklifini DUZELTEBILIR ─────────
 * (operator, 24 Eyl 2026, O748EE: satici 52 -> alici 33 -> satici 50
 * gonderildi, sonra "50'yi sil, 44 gonder" dendi). Alicinin eline gecmis
 * bir mektuptaki rakami GERCEKTEN silmenin yolu yok; durust olan sey YENI
 * bir karsi teklif gondermek. $selfCorrect bunu acikca ister -- varsayilani
 * false, yani butun eski cagiran (panel, yukaridaki her assert) davranisi
 * AYNEN koruyor. */
echo "\n== SATICI kendi bekleyen karsi teklifini duzeltebilir (selfCorrect) ==\n";
$JSON=[]; $MAIL=[];
$r=vestra_offer_respond('OF-1','counter',12.0,null,'V'); $firstTok=$tok();
$t('TUR 1 satici 12.00, sira alicida', $r['ok'] && $cnt()===1 && $turn()==='buyer');
$t('selfCorrect OLMADAN ikinci satici hamlesi HALA reddedilir',
   !vestra_offer_respond('OF-1','counter',11.0,null,'V')['ok']);
$r=vestra_offer_respond('OF-1','counter',11.0,null,'V',true,true);
$t('selfCorrect ILE gecerli', $r['ok']);
$t('durum hala counter, sira HALA alicida', ($JSON['OF-1']['status']??'')==='counter' && $turn()==='buyer');
$t('YENI bir tur EKLENDI, 12 gecmisten SILINMEDI',
   $cnt()===2 && count($JSON['OF-1']['counters'])===2
   && abs((float)($JSON['OF-1']['counters'][0]['price']??0)-12.0)<0.001
   && abs((float)($JSON['OF-1']['counters'][1]['price']??0)-11.0)<0.001);
$t('TAZE token uretildi, eskisi artik calismaz', $tok()!=='' && $tok()!==$firstTok);
$t('aliciya YENI mektup gitti', count($MAIL)>0);

echo "\n== selfCorrect FIYAT KURALLARINI ve TUR SINIRINI ATLATAMAZ ==\n";
$t('kendi son rakamindan PAHALI reddedilir', !vestra_offer_respond('OF-1','counter',11.5,null,'V',true,true)['ok']);
$t('urun fiyatinin USTU reddedilir', !vestra_offer_respond('OF-1','counter',25.0,null,'V',true,true)['ok']);
$t('reddedilenlerden tur artmadi', $cnt()===2);
$t('daha ucuz GECER ve gercek tur harciyor', vestra_offer_respond('OF-1','counter',10.0,null,'V',true,true)['ok'] && $cnt()===3);
$t('tur hakki bitince selfCorrect de calismaz', !vestra_offer_respond('OF-1','counter',9.0,null,'V',true,true)['ok']);

echo "\n== Eski cagrilar (7. parametre verilmeden) davranis BIREBIR ayni ==\n";
$JSON=[]; $MAIL=[];
vestra_offer_respond('OF-1','counter',12.0,null,'V');
$t('varsayilan false: ikinci satici hamlesi reddedilir',
   !vestra_offer_respond('OF-1','counter',11.0,null,'V')['ok']);
$t('tur hala 1, gecmis bozulmadi', $cnt()===1);

/* ── Sabitin KENDISI: operator 9 Eyl 2026'da 5 dedi ──────────────────────
 * Bu dosya kendi basina 3 tanimliyor (mekanizmayi sinamak icin), o yuzden
 * SEVK EDILEN deger ancak kaynaktan okunarak dogrulanabilir. Escrow tavani
 * bu depoda tam bu yuzden bes gun kod ile metin arasinda ayri kalmisti. */
echo "\n== Sevk edilen sabit ==\n";
$offSrc = file_get_contents(__DIR__.'/../vestra/inc/offers.php');
$t('VESTRA_OFFER_MAX_COUNTERS = 5',
   preg_match("/define\('VESTRA_OFFER_MAX_COUNTERS',\s*5\)/", $offSrc) === 1);
$t('yeniden acma YALNIZ decline ile',
   preg_match("/\\\$reopen\s*=\s*\\\$action\s*===\s*'counter'\s*&&[^\n]*'decline'/", $offSrc) === 1);
foreach (['vestra/buyer.php','vestra/admin.php'] as $rel) {
    $s = file_get_contents(__DIR__.'/../'.$rel);
    /* Rakam metne gomulmemeli: sayfalar sabiti BASMALI. */
    $t(basename($rel).' sabiti okuyor', str_contains($s, 'VESTRA_OFFER_MAX_COUNTERS'));
}

printf("\n=========== %d gecti, %d KALDI ===========\n",$ok,$fail);
exit($fail?1:0);
