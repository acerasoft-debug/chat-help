<?php
/** ChatHelp — dump-jobsearch-prep: is-bulma ozelligi icin api.php dispatch/yardimcilar + index.php panel/menu.
 *  SADECE OKUR. SIL: rm dump-jobsearch-prep.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$api=@file_get_contents(__DIR__.'/api.php');
$idx=@file_get_contents(__DIR__.'/index.php');
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";if($s===false){echo "(dosya yok)\n";return;}$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '".substr($needle,0,50)."')\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}
function RALL($s,$needle,$b,$l,$lbl,$max=6){echo "\n──── $lbl (ilk $max) ────\n";if($s===false){echo "(dosya yok)\n";return;}$off=0;$n=0;while(($p=strpos($s,$needle,$off))!==false&&$n<$max){echo "\n[@$p #".($n+1)."]\n".substr($s,max(0,$p-$b),$l)."\n";$off=$p+strlen($needle);$n++;}if($n===0)echo "(BULUNAMADI)\n";}

echo "############ API.PHP ############\n";
R($api,'match($action)',-200,700,'action dispatch (jobsearch buraya eklenecek)');
R($api,'function respond',0,260,'respond() imzasi');
R($api,'STORE_DIR',-120,220,'STORE_DIR tanimi');
R($api,'function callClaude(',0,300,'callClaude imzasi');
R($api,'function callDeepSeek',0,200,'callDeepSeek imzasi (varsa)');
R($api,'file_get_contents(\'php://input\')',-200,300,'request body okuma');
R($api,'header(',-20,300,'ust header/CORS');
R($api,'function doHistory',0,300,'doHistory (basit action ornegi)');

echo "\n\n############ INDEX.PHP — MENU (sidebar nav) ############\n";
R($idx,'sb-nav',-40,900,'sidebar nav bloku (menu ogeleri)');
RALL($idx,'gP(',-60,90,'gP() cagrilari (panel gecis)',8);
R($idx,'function gP',0,600,'gP() tanimi (panel gosterme)');

echo "\n\n############ INDEX.PHP — PANEL yapisi (#pnl-ant ornegi) ############\n";
R($idx,'id="pnl-ant"',-120,500,'#pnl-ant panel HTML iskeleti');
R($idx,'id="pnl-konto"',-80,300,'#pnl-konto panel');
R($idx,'pnl-back',-60,300,'panel geri butonu');

echo "\n\n############ INDEX.PHP — API cagrisi ornegi (fetch) ############\n";
R($idx,"API+'?action=",-60,240,'API fetch ornegi (action)');
R($idx,'const API',-20,160,'API sabiti');

echo "\n\n=== BITTI. TAMAMINI yapistir. SIL: rm dump-jobsearch-prep.php ===\n";
