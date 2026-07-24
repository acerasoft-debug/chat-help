<?php
/**
 * ChatHelp - dump-einzelpay (READ-ONLY) - Einzeldokument (single-doc) payment UI + Senden gate.
 * Amac: fax'i tek-belge alicisi icin ucretsiz yapmak + odeme sayfasinda gostermek icin
 *       gereken CANLI capa (anchor) noktalarini cikarmak.
 * KULLANIM: pull2.php?key=...&files=dump-einzelpay.php
 * ASCII-only, cok savunmaci: her arama siki sinirli, cikti kucuk tutulur.
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@ini_set('display_errors','1');

$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-einzelpay (".number_format(strlen($src))." B) ==\n\n";

function ctx($src,$pat,$before,$len,$max){
  $off=0;$n=0;$hit=false;
  while(($p=strpos($src,$pat,$off))!==false && $n<$max){
    $seg=substr($src,($p-$before>0?$p-$before:0),$len);
    $seg=str_replace(array("\r","\n"),array('',' '),$seg);
    echo "  [".$pat." @".$p."]: ...".$seg."...\n";
    $off=$p+strlen($pat); $n++; $hit=true;
  }
  if(!$hit){ echo "  (".$pat.": yok)\n"; }
  echo "\n";
}

echo "=== [1] showPaywall govdesi (odeme karti) ===\n";
$p=strpos($src,'function showPaywall');
if($p===false){ $p=strpos($src,'showPaywall=function'); }
if($p===false){ $p=strpos($src,'showPaywall'); }
if($p!==false){
  $body=substr($src,$p,1200);
  $body=str_replace(array("\r"),array(''),$body);
  echo "  showPaywall @".$p.":\n  ".str_replace("\n","\n  ",$body)."\n\n";
} else { echo "  (showPaywall yok)\n\n"; }

echo "=== [2] Freischalten / devdoc unlock + stripeCheckout ===\n";
ctx($src,'Freischalten',120,300,3);
ctx($src,'devdoc',140,240,3);
ctx($src,'stripeCheckout(',60,200,3);

echo "=== [3] _tier (fiyat kademesi) ===\n";
ctx($src,'_tier',90,300,2);
ctx($src,'komplex',80,160,2);

echo "=== [4] kredi mekanizmasi ===\n";
ctx($src,'addCredit',60,180,2);
ctx($src,'useCredit',60,180,2);
ctx($src,'function credits',20,140,1);
ctx($src,'ch_credits',60,140,3);

echo "=== [5] paid-single-doc isareti ===\n";
ctx($src,'ch_paid',60,140,3);
ctx($src,'__paidDoc',60,160,3);
ctx($src,'ch_pending',60,140,2);

echo "=== [6] Senden(fax) kapisi tam baglam ===\n";
ctx($src,'_up4=P.plan',160,320,3);
ctx($src,'send_fax',120,200,2);
ctx($src,'ai-faxnr',120,200,2);

echo "=== [7] marker durumu ===\n";
$mk=array('CH_PAIDDOC','CH_EINZELFAX','CH_NOFREEFAX','CH_PLANFAX');
foreach($mk as $m){ echo "  ".$m.": ".((strpos($src,$m)!==false)?"VAR":"yok")."\n"; }
echo "\n== BITTI ==\n";
