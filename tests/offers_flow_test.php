<?php
define('VESTRA_OFFER_MAX_COUNTERS', 3);
define('VESTRA_OFFER_MIN_BUYER_PCT', 0.50);
$src   = file_get_contents(__DIR__.'/../vestra/inc/offers.php');
$strip = fn($s) => preg_replace("#require_once __DIR__\.'/[a-z_]+\.php';#", '', $s);

$JSON=[]; $MAIL=[]; $NOTIF=[]; $INV=[]; $FORCED=[]; $LASTURL=null;
$CSV = [['ref'=>'OF-1','sku'=>'LEV-8820F','product'=>"Vintage Levi's 501",'qty'=>100,
         'offer_unit'=>9.00,'offer_total'=>900.00,'email'=>'buyer@example.com','company'=>'Buyer Co',
         'timestamp'=>'2026-08-30T10:00:00+00:00']];

function vestra_read_csv($f){ global $CSV; return $CSV; }
function vestra_read_json($f){ global $JSON; return $JSON; }
function vestra_write_json($f,$d){ global $JSON; $JSON=$d; return true; }
/* Fiyat kurallari icin: referans = en dusuk kademe. 20.00 secildi ki
   alici tabani 10.00 olsun ve asagidaki senaryolar (9.00 ilk teklif,
   10.00/11.50 karsi teklifler) kurallara UYSUN -- test kurallari
   atlatmasin, icinden gecsin. */
function vestra_from_price($p){ if(empty($p['tiers'])) return 0.0; $m=null; foreach($p['tiers'] as $t){ $m=($m===null)?$t['price']:min($m,$t['price']); } return $m; }
function vestra_listing_by_sku($s){ return ['id'=>'lev-501','sku'=>$s,'brand'=>"Levi's",'name'=>'Vintage 501','seller_uid'=>'613abb','tiers'=>[['min'=>1,'price'=>20.00]]]; }
function auth_find($e){ return ['id'=>'buy1','name'=>'Iwona','company'=>'Buyer Co','vat_id'=>'PL1','country'=>'PL','address'=>'x']; }
function auth_accounts(){ return [['id'=>'613abb','email'=>'s@x.com','company'=>'Erensthrift']]; }
function vestra_platform_seller(){ return ['id'=>'plat','company'=>'Acerasoft LLC']; }
function vestra_user_lang($a){ return 'en'; }
function vestra_ensure_invoice($m,$i,$s,$force=false,$rd=false){
  global $INV,$FORCED;
  if(!$force) return ['no'=>'','path'=>'','pending'=>true];       // onay bekliyor: DOSYA YOK
  $INV[]=$i[0]; $FORCED[]=$m['ref']; return ['no'=>'INV-1','path'=>'/x.pdf'];
}
function vestra_notify($s,$b){ global $NOTIF; $NOTIF[]=$s; }
function vestra_msg_post_system(...$x){}
function vestra_push_send(...$x){}
function vestra_send_mail($to,$s,$b,...$r){ global $MAIL; $MAIL[]=[$to,$s]; return true; }
function vestra_tpl_offer_response($l,$act,$bn,$p,$r,$cp,$url=null){ global $LASTURL; $LASTURL=$url; return ["subj-$act","body",[]]; }
function vestra_tpl_offer_counter_accepted($l,$bn,$p,$r,$u,$q){ return ["agreed","body",[]]; }

/* offers.php'deki BUTUN fonksiyonlari gercek govdeleriyle yukle */
preg_match_all('/^function \w+\(.*?^}/ms', $src, $fns);
foreach ($fns[0] as $f) eval($strip($f));

$ok=0; $fail=0;
$t = function(string $n, bool $c) use (&$ok,&$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };

echo "\n== 1. Karsi teklif: token + mektupta kabul linki ==\n";
$r = vestra_offer_respond('OF-1','counter',12.00,null,'VESTRA');
$tok = $JSON['OF-1']['accept_token'] ?? '';
$t('token uretildi', preg_match('/^[0-9a-f]{32}$/',$tok)===1);
$t('mektup kabul URL aldi', str_contains((string)$LASTURL,'offer-accept?ref=OF-1&token='.$tok));

echo "\n== 2. Yanlis token reddediliyor ==\n";
$t('kabul reddedildi',  !vestra_offer_accept_counter('OF-1', str_repeat('a',32))['ok']);
$t('RED de reddedildi', !vestra_offer_decline_counter('OF-1', str_repeat('a',32))['ok']);
$t('durum hala counter', ($JSON['OF-1']['status']??'')==='counter');

echo "\n== 3. FATURA ONAYSIZ KESILMIYOR ==\n";
$r = vestra_offer_accept_counter('OF-1', $tok);
$t('kabul ok', $r['ok']);
$t('fatura PENDING dondu', !empty($r['invoice']['pending']));
$t('PDF URETILMEDI', count($INV)===0 && count($FORCED)===0);
$t('birim 12.00 (9.00 DEGIL)', abs($r['unit']-12.00)<0.001);
$t('accepted_by=buyer', ($JSON['OF-1']['accepted_by']??'')==='buyer');
$t('operatore bildirim', count($NOTIF)===1 && str_contains($NOTIF[0],'ACCEPTED by buyer'));

echo "\n== 4. Operator ONAYLAYINCA fatura kesiliyor, dogru tutardan ==\n";
$iv = vestra_offer_issue_invoice('OF-1', true);
$t('numara yakildi', ($iv['no']??'')==='INV-1');
$t('fatura birim 12.00', abs(($INV[0]['unit']??0)-12.00)<0.001);
$t('fatura toplam 1200.00 (900 DEGIL)', abs(($INV[0]['line']??0)-1200.00)<0.001);
$t('alici blogu dolu', ($INV[0]['sku']??'')==='LEV-8820F');

echo "\n== 5. Tek kullanimlik ==\n";
$n0=count($NOTIF);
$t('ikinci kabul reddedildi', !vestra_offer_accept_counter('OF-1',$tok)['ok']);
$t('ikinci bildirim YOK', count($NOTIF)===$n0);

echo "\n== 6. ALICI KARSI TEKLIFI REDDEDIYOR ==\n";
$JSON=[]; $NOTIF=[];
vestra_offer_respond('OF-1','counter',12.00,null,'V'); $tok2=$JSON['OF-1']['accept_token'];
$r = vestra_offer_decline_counter('OF-1',$tok2);
$t('red ok', $r['ok']);
$t('durum decline', ($JSON['OF-1']['status']??'')==='decline');
$t('declined_by=buyer', ($JSON['OF-1']['declined_by']??'')==='buyer');
$t('operatore bildirim', count($NOTIF)===1 && str_contains($NOTIF[0],'DECLINED by buyer'));
$t('redden sonra kabul EDILEMEZ', !vestra_offer_accept_counter('OF-1',$tok2)['ok']);

echo "\n== 7. Uzlasilan fiyat: dort senaryo ==\n";
$JSON=[];
$t('yanit yok -> ilk teklif 9.00', abs(vestra_offer_agreed_unit('OF-1')-9.00)<0.001);
vestra_offer_respond('OF-1','accept',0,null,'V');
$t('karsi teklifsiz kabul -> 9.00', abs(vestra_offer_agreed_unit('OF-1')-9.00)<0.001);
/* ESKI DAVRANIS HATALIYDI: satici 12 karsi teklif verdikten sonra kendisi
   'accept' diyebiliyordu ve fatura 12.00'dan kesiliyordu -- alicinin HIC
   kabul etmedigi bir fiyat. Artik sira alicida oldugu icin engelleniyor. */
$JSON=[]; vestra_offer_respond('OF-1','counter',12.00,null,'V');
$r2=vestra_offer_respond('OF-1','accept',0,null,'V');
$t('satici KENDI karsi teklifini kabul edemez', !$r2['ok']);
$t('durum counter olarak kaldi', ($JSON['OF-1']['status']??'')==='counter');
vestra_offer_accept_counter('OF-1',$JSON['OF-1']['accept_token']);
$t('ALICI kabul edince 12.00 baglanir', abs(vestra_offer_agreed_unit('OF-1')-12.00)<0.001);
$JSON=[]; vestra_offer_respond('OF-1','counter',11.50,null,'V');
vestra_offer_accept_counter('OF-1',$JSON['OF-1']['accept_token']);
$t('alici kabulu -> 11.50', abs(vestra_offer_agreed_unit('OF-1')-11.50)<0.001);
$INV=[]; vestra_offer_issue_invoice('OF-1', true);
$t('faturasi da 11.50 / 1150.00', abs(($INV[0]['unit']??0)-11.50)<0.001 && abs(($INV[0]['line']??0)-1150.00)<0.001);

printf("\n=========== %d gecti, %d KALDI ===========\n", $ok, $fail);
exit($fail ? 1 : 0);
