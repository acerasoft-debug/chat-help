<?php
/** ChatHelp — dump-antraege: uretilen belge NEREYE kaydediliyor + Antrage paneli NASIL listeliyor (#8).
 *  SADECE OKUR. SIL: rm dump-antraege.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '".substr($needle,0,50)."')\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}
function RALL($s,$needle,$b,$l,$lbl,$max=6){echo "\n──── $lbl (ilk $max) ────\n";$off=0;$n=0;while(($p=strpos($s,$needle,$off))!==false&&$n<$max){echo "\n[@$p #".($n+1)."]\n".substr($s,max(0,$p-$b),$l)."\n";$off=$p+strlen($needle);$n++;}if($n===0)echo "(BULUNAMADI)\n";}

echo "############ 1) URETILEN BELGE KAYDI (genDoc sonu / kaydetme) ############\n";
foreach(['saveDoc','ch_docs','ch_recent','recentDocs','savedDocs','localStorage.setItem','_lastGenAns','docHistory','ch_history','ch_antr'] as $k){
  $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':'VAR @'.$p)."\n";
}
R($idx,'function loadRecentDocs',0,1200,'loadRecentDocs() TAM govde');
RALL($idx,'setItem(',-40,120,'localStorage.setItem cagrilari (anahtar isimleri)',20);

echo "\n\n############ 2) ANTRAGE PANELI (#pnl-ant) — LISTE RENDER ############\n";
R($idx,'pnl-ant',-60,300,'#pnl-ant panel HTML');
R($idx,'ant-item',-100,300,'.ant-item render (uretici fonksiyon)');
R($idx,'ant-empty',-100,300,'bos durum');
RALL($idx,'function ',-0,60,'genDoc civari fonksiyon adlari (belge sonrasi)',0); // placeholder

echo "\n\n############ 3) genDoc SONU — belge gosterildikten SONRA ne oluyor ############\n";
R($idx,'async function genDoc',0,2600,'genDoc TAM govde (kayit var mi)');

echo "\n\n############ 4) BEHALTEN / KAYDET-KALICI butonu (varsa) ############\n";
RALL($idx,'behalten',-120,220,'behalten',6);
RALL($idx,'Behalten',-120,220,'Behalten',4);
R($idx,'Meine Anträge',-200,300,'Meine Antrage menu girisi');
R($idx,'openAntr',-100,300,'Antrage panel acma');

echo "\n\n=== BITTI. TAMAMINI yapistir. SIL: rm dump-antraege.php ===\n";
