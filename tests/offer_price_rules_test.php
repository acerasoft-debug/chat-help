<?php
/* Pazarlik fiyat kurallari. Bu kapilar PARA belirliyor ve reddedilen her
   durumda kullaniciya GEREKCE gitmeli -- "bir hata oldu" musteriyi hicbir
   sey yapamaz halde birakir. */
define('VESTRA_OFFER_MAX_COUNTERS',3);
function vestra_from_price($p){ if(empty($p['tiers'])) return 0.0; $m=null; foreach($p['tiers'] as $t){ $m=($m===null)?$t['price']:min($m,$t['price']); } return $m; }
$src=file_get_contents(__DIR__.'/../vestra/inc/offers.php');
foreach(['vestra_offer_ref_price','vestra_offer_last_price','vestra_offer_price_error'] as $fn){
  preg_match('/^function '.$fn.'\(.*?^}/ms',$src,$m); eval($m[0]);
}
if(!defined('VESTRA_OFFER_MIN_BUYER_PCT')) define('VESTRA_OFFER_MIN_BUYER_PCT',0.50);

$P = ['tiers'=>[['min'=>50,'price'=>20.00],['min'=>200,'price'=>18.00]]];  // referans = 18.00
$ok=0;$bad=0;
$t=function($n,$c)use(&$ok,&$fail,&$bad){ $c?($ok++.print("  ok   $n\n")):($bad++.print("  HATA $n\n")); };
$err=fn($side,$price,$prev=null)=>vestra_offer_price_error($P,$side,$price,$prev);

echo "\n== referans fiyat = en dusuk kademe ==\n";
$t('ref 18.00', abs(vestra_offer_ref_price($P)-18.00)<0.001);

echo "\n== ALICI: yarisindan az olamaz (taban 9.00) ==\n";
$t('9.00 gecer',      $err('buyer',9.00)===null);
$t('9.01 gecer',      $err('buyer',9.01)===null);
$t('8.99 REDDEDILIR', $err('buyer',8.99)!==null);
$t('sebep tabani yaziyor', str_contains((string)$err('buyer',5.00),'9.00'));
$t('sebep anlasilir', str_contains((string)$err('buyer',5.00),'below half'));

echo "\n== SATICI: normal fiyattan fazla olamaz (tavan 18.00) ==\n";
$t('18.00 gecer',      $err('seller',18.00)===null);
$t('18.01 REDDEDILIR', $err('seller',18.01)!==null);
$t('sebep tavani yaziyor', str_contains((string)$err('seller',25.00),'18.00'));

echo "\n== ALICI her turda YUKSELMELI ==\n";
$t('onceki 10 -> 11 gecer',      $err('buyer',11.00,10.00)===null);
$t('onceki 10 -> 10 REDDEDILIR', $err('buyer',10.00,10.00)!==null);
$t('onceki 10 -> 9.50 REDDEDILIR',$err('buyer',9.50,10.00)!==null);
$t('sebep oncekini yaziyor', str_contains((string)$err('buyer',10.00,10.00),'10.00'));
$t('sebep yonu soyluyor',   str_contains((string)$err('buyer',10.00,10.00),'higher'));

echo "\n== SATICI her turda DUSMELI ==\n";
$t('onceki 16 -> 15 gecer',      $err('seller',15.00,16.00)===null);
$t('onceki 16 -> 16 REDDEDILIR', $err('seller',16.00,16.00)!==null);
$t('onceki 16 -> 17 REDDEDILIR', $err('seller',17.00,16.00)!==null);
$t('sebep yonu soyluyor', str_contains((string)$err('seller',17.00,16.00),'lower'));

echo "\n== Iki kural birden ==\n";
$t('alici: yuksek ama tabanin ALTINDA -> red', $err('buyer',8.00,7.00)!==null);
$t('satici: dusuk ama tavanin USTUNDE -> red', $err('seller',19.00,20.00)!==null);

echo "\n== Fiyati bilinmeyen urun: sinir UYGULANMAZ ==\n";
$NP=['tiers'=>[]];
$t('taban yok',  vestra_offer_price_error($NP,'buyer',0.01)===null);
$t('tavan yok',  vestra_offer_price_error($NP,'seller',9999.0)===null);
$t('ama yon kurali DURUYOR', vestra_offer_price_error($NP,'buyer',5.0,6.0)!==null);
$t('sifir/negatif hep red', $err('buyer',0)!==null && $err('seller',-5)!==null);

echo "\n== Son rakam kimin: gecmisten okunuyor ==\n";
$R=['counters'=>[['by'=>'seller','price'=>16.0],['by'=>'buyer','price'=>11.0],['by'=>'seller','price'=>14.0]]];
$t('saticinin sonu 14.00', abs(vestra_offer_last_price($R,'seller')-14.00)<0.001);
$t('alicinin sonu 11.00',  abs(vestra_offer_last_price($R,'buyer')-11.00)<0.001);
$t('alici gecmisi yoksa ILK teklif', abs(vestra_offer_last_price(['counters'=>[]],'buyer',['offer_unit'=>9.5])-9.50)<0.001);
$t('satici gecmisi yoksa null', vestra_offer_last_price(['counters'=>[]],'seller')===null);
$t('eski kayit (counters yok) saticinin rakamini bulur',
   abs(vestra_offer_last_price(['counter_by'=>'seller','counter_price'=>12.0],'seller')-12.00)<0.001);

printf("\n%d gecti, %d KALDI\n",$ok,$bad);
exit($bad?1:0);
