<?php
/**
 * ChatHelp - dump-ezpay2 (READ-ONLY) - Einzeldokument odeme UI + Senden gate anchorlari.
 * dump-faxpolicy tarzi: HIC fonksiyon tanimlamaz (redeclare fatal olmasin), tamamen satir-ici.
 * KULLANIM: pull2.php?key=...&files=dump-ezpay2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; return; }
echo "== dump-ezpay2 (".number_format(strlen($src))." B) ==\n\n";

/* [1] showPaywall govdesi */
echo "=== [1] showPaywall govdesi (odeme karti) ===\n";
$p=strpos($src,'function showPaywall');
if($p===false){ $p=strpos($src,'showPaywall=function'); }
if($p===false){ $p=strpos($src,'showPaywall'); }
if($p!==false){
  $body=str_replace("\r",'',substr($src,$p,1200));
  echo "  showPaywall @".$p.":\n  ".str_replace("\n","\n  ",$body)."\n\n";
} else { echo "  (showPaywall yok)\n\n"; }

/* [2..6] satir-ici baglam taramasi */
$scan=array(
  'Freischalten'=>array(120,300,3),
  'devdoc'=>array(140,240,3),
  'stripeCheckout('=>array(60,200,3),
  '_tier'=>array(90,300,2),
  'komplex'=>array(80,160,2),
  'addCredit'=>array(60,180,2),
  'useCredit'=>array(60,180,2),
  'ch_credits'=>array(60,140,3),
  'ch_paid'=>array(60,140,3),
  '__paidDoc'=>array(60,160,3),
  'ch_pending'=>array(60,140,2),
  '_up4=P.plan'=>array(160,320,3),
  'send_fax'=>array(120,200,2),
  'ai-faxnr'=>array(120,200,2),
);
echo "=== [2-6] baglam taramasi ===\n";
foreach($scan as $pat=>$cfg){
  $before=$cfg[0]; $len=$cfg[1]; $max=$cfg[2];
  $off=0; $nn=0; $hit=false;
  while(($q=strpos($src,$pat,$off))!==false && $nn<$max){
    $seg=str_replace(array("\r","\n"),array('',' '),substr($src,($q-$before>0?$q-$before:0),$len));
    echo "  [".$pat." @".$q."]: ...".$seg."...\n";
    $off=$q+strlen($pat); $nn++; $hit=true;
  }
  if(!$hit){ echo "  (".$pat.": yok)\n"; }
}

/* [7] markerlar */
echo "\n=== [7] marker durumu ===\n";
$mk=array('CH_PAIDDOC','CH_EINZELFAX','CH_NOFREEFAX','CH_PLANFAX');
foreach($mk as $m){ echo "  ".$m.": ".((strpos($src,$m)!==false)?"VAR":"yok")."\n"; }
echo "\n== BITTI ==\n";
