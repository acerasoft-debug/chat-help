<?php
/**
 * ChatHelp - dump-ezfax (READ-ONLY) - Einzeldokument fax-free icin son cerceve.
 * Bilinen offsetlerden hassas kesitler + fax odeme yolu.
 * KULLANIM: /chat/pull2.php?key=...&files=dump-ezfax.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; return; }
echo "== dump-ezfax (".number_format(strlen($src))." B) ==\n\n";

echo "=== [A] showPaywall tam govde (755522 +2600) ===\n";
echo str_replace("\r",'',substr($src,755522,2600))."\n\n";

echo "=== [B] Senden(fax) kapisi tam onclick (1004050 +1350) ===\n";
echo str_replace("\r",'',substr($src,1004050,1350))."\n\n";

echo "=== [C] genDoc paid-credit blok (1161350 +900) ===\n";
echo str_replace("\r",'',substr($src,1161350,900))."\n\n";

echo "=== [D] fax fiyat/gonderim yolu ===\n";
echo "-- _priceFor / chFaxQuota / Gratis --\n";
foreach(array('function _priceFor','_priceFor(','chFaxQuota','Gratis-Fax','Versandkosten') as $pat){
  $off=0;$nn=0;$hit=false;
  while(($q=strpos($src,$pat,$off))!==false && $nn<2){
    $seg=str_replace(array("\r","\n"),array('',' '),substr($src,($q-40>0?$q-40:0),260));
    echo "  [".$pat." @".$q."]: ...".$seg."...\n";
    $off=$q+strlen($pat);$nn++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}
echo "\n-- send_fax handler tam kesit (2396600 +900) --\n";
echo str_replace("\r",'',substr($src,2396600,900))."\n\n";

echo "=== [E] send'de Stripe/odeme kapisi var mi? ===\n";
foreach(array('send_fax','send_letter','postversand','Versand bezahlen','pay_ship','shipCheckout','price_data') as $pat){
  $c=substr_count($src,$pat);
  echo "  ".$pat.": ".$c." kez\n";
}
/* gonderim aninda stripeCheckout cagriliyor mu? send bolgesi 2390000-2400000 icinde */
$seg2=substr($src,2390000,10000);
echo "  send bolgesinde 'stripeCheckout': ".(strpos($seg2,'stripeCheckout')!==false?"VAR":"yok")."\n";
echo "  send bolgesinde 'checkout.php': ".(strpos($seg2,'checkout.php')!==false?"VAR":"yok")."\n";

echo "\n== BITTI ==\n";
