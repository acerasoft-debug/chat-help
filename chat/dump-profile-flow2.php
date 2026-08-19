<?php
/** ChatHelp — dump-profile-flow2: Konto 'Profil' bolumunun HAM HTML'i (satir IDleri).
 *  SADECE OKUR. SIL: rm dump-profile-flow2.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '".substr($needle,0,60)."')\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}
function RALL($s,$needle,$b,$l,$lbl,$max=8){echo "\n──── $lbl (ilk $max) ────\n";$off=0;$n=0;while(($p=strpos($s,$needle,$off))!==false&&$n<$max){echo "\n[@$p #".($n+1)."]\n".substr($s,max(0,$p-$b),$l)."\n";$off=$p+strlen($needle);$n++;}if($n===0)echo "(BULUNAMADI)\n";}

echo "############ KONTO PANELI HAM HTML (pnl-konto'dan itibaren genis pencere) ############\n";
$p=strpos($idx,'id="pnl-konto"');
if($p!==false){ echo "[pnl-konto @".$p."]\n".substr($idx,$p,5200)."\n"; } else echo "(pnl-konto bulunamadi)\n";

echo "\n\n############ UNICODE-ESCAPE VARYANTLARI ############\n";
R($idx,'Profil löschen',-600,900,'Profil löschen (escape)');
R($idx,'löschen',-400,600,'*löschen (escape, ilk)');
RALL($idx,'Bearbeiten',-250,400,'Bearbeiten butonlari',4);
RALL($idx,'prof-',-60,150,'prof-* IDler',8);
RALL($idx,'pf-',-60,150,'pf-* IDler',8);

echo "\n=== BITTI. TAMAMINI yapistir. SIL: rm dump-profile-flow2.php ===\n";
