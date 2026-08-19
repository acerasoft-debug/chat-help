<?php
/* dump-sendungen (READ-ONLY) — Meine Sendungen (CH_SENDUNGEN) sayfa yapisi:
   panel HTML, liste ogesi/kart siniflari, bos durum, render fonksiyonu.
   Premium estetik icin.
   KULLANIM: pull2.php?key=...&files=dump-sendungen.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "dump-sendungen (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php'); if($s===false) exit("okunamadi\n");
function W($s,$p,$b,$a){ return trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-$b),$b+$a))); }

echo "=== [1] CH_SENDUNGEN script sinirlari + bas ===\n";
$p=strpos($s,'CH_SENDUNGEN');
if($p!==false){ $so=strrpos(substr($s,0,$p),'<script'); $eo=strpos($s,'</script>',$p);
  echo "  [script @".$so." → ".$eo.", uzunluk ".($eo-$so).")]\n"; }

echo "\n=== [2] pnl-sendungen / render fonksiyonu ===\n";
foreach(['pnl-sendungen','renderSendungen','nav-sendungen','ch_sendungen','snd-card','snd-item','snd-list','function renderSnd'] as $k){
  $p=strpos($s,$k); if($p!==false) echo "  '".$k."' [".$p."] ".W($s,$p,10,120)."\n";
}

echo "\n=== [3] render govdesi (liste ogesi HTML) ===\n";
$p=strpos($s,'renderSendungen'); if($p===false)$p=strpos($s,'function renderSnd');
if($p!==false){ $p2=strpos($s,'{',$p); echo substr($s,$p, 1400)."\n"; }

echo "\n=== [4] Sendungen ile ilgili CSS (snd-/sendung) ===\n";
$off=0;$c=0; while(($p=strpos($s,'.snd-',$off))!==false && $c<10){ echo "  [".$p."] ".W($s,$p,0,90)."\n"; $off=$p+4;$c++; }
if($c===0) echo "  (.snd- CSS yok — inline stil olabilir)\n";

echo "\n=== [5] pnl-sendungen HTML iskeleti ===\n";
$p=strpos($s,'"pnl-sendungen"'); if($p===false)$p=strpos($s,'pnl-sendungen');
if($p!==false){ $ds=strrpos(substr($s,0,$p),'<div'); if($ds!==false&&$p-$ds<400) echo substr($s,$ds,900)."\n"; else echo "  civar: ".W($s,$p,60,700)."\n"; }
echo "\nBITTI.\n";
