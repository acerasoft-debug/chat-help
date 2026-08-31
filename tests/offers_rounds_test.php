<?php
define('VESTRA_OFFER_MAX_COUNTERS', 3);
$src=file_get_contents(__DIR__.'/../vestra/inc/offers.php');
$strip=fn($s)=>preg_replace("#require_once __DIR__\.'/[a-z_]+\.php';#",'',$s);
$JSON=[]; $NOTIF=[]; $MAIL=[]; $INV=[];
$CSV=[['ref'=>'OF-1','sku'=>'S1','product'=>'P','qty'=>100,'offer_unit'=>9.0,'offer_total'=>900.0,'email'=>'b@x.com','company'=>'C']];
function vestra_read_csv($f){ global $CSV; return $CSV; }
function vestra_read_json($f){ global $JSON; return $JSON; }
function vestra_write_json($f,$d){ global $JSON; $JSON=$d; return true; }
function vestra_listing_by_sku($s){ return ['id'=>'p1','sku'=>$s,'brand'=>'B','name'=>'N','seller_uid'=>'s1']; }
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

printf("\n=========== %d gecti, %d KALDI ===========\n",$ok,$fail);
exit($fail?1:0);
