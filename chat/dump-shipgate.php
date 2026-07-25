<?php
/**
 * ChatHelp - dump-shipgate (READ-ONLY, sir maskeli) - tahsilat kapisi icin birebir kaynaklar.
 *  [1] stripe-checkout.php TAM icerik (maskeli)
 *  [2] index.php: wet doHome tam govde (2391300..2392900)
 *  [3] index.php: odeme-donus handler (1162200..1163800)
 *  [4] PVURL tanimi + plan() fonksiyonu (fiyat icin)
 * KULLANIM: /chat/pull2.php?key=...&files=dump-shipgate.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){
  $s=preg_replace('/\b(sk|pk|rk)_(live|test)_[A-Za-z0-9]+/','$1_$2_***',$s);
  $s=preg_replace('/\bwhsec_[A-Za-z0-9]+/','whsec_***',$s);
  $s=preg_replace('/\bprice_[A-Za-z0-9]{8,}/','price_***KEEP***',$s);
  return $s;
}
echo "== dump-shipgate ==\n\n";

echo "=== [1] stripe-checkout.php TAM (maskeli) ===\n";
$sc=(string)@file_get_contents(__DIR__.'/stripe-checkout.php');
echo mask($sc)."\n\n";

$idx=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== [2] wet doHome govdesi ===\n";
$q=strpos($idx,"action=send_letter'",2390000);
if($q===false)$q=strpos($idx,'action=send_letter',2390000);
if($q!==false){
  $s=($q-1600>0?$q-1600:0);
  echo str_replace("\r",'',substr($idx,$s,2600))."\n\n";
} else echo "  (2390000 sonrasi send_letter yok)\n\n";

echo "=== [3] odeme-donus handler ===\n";
$q=strpos($idx,"ch_paid_");
if($q!==false){
  $s=($q-900>0?$q-900:0);
  echo str_replace("\r",'',substr($idx,$s,2400))."\n\n";
} else echo "  (ch_paid_ yok)\n\n";

echo "=== [4] PVURL + plan() ===\n";
foreach(array('function PVURL','PVURL=','function plan(){') as $pat){
  $off=0;$n=0;
  while(($q=strpos($idx,$pat,$off))!==false && $n<2){
    echo "  [".$pat." @".$q."]: ".str_replace(array("\r","\n"),' ',substr($idx,$q,220))."\n";
    $off=$q+strlen($pat);$n++;
  }
}
echo "\n== BITTI ==\n";
