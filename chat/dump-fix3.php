<?php
/** ChatHelp — dump-fix3 (SADECE OKUR)
 *  A) Kalicilik zinciri: loadHistory + belge kaydi (doGenerate) + doKeep + doHistory + $sid + dispatch.
 *  B) MEVCUT nav yeniden-siralayici (@~1167873) + modOn + MODS listesi (nav id'leri).
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function W($src,$needle,$before,$len,$label){
    echo "──────── $label ────────\n";
    $p=strpos($src,$needle);
    if($p===false){ echo "  (yok: '$needle')\n\n"; return; }
    echo "[@$p]\n".substr($src,max(0,$p-$before),$len)."\n---\n\n";
}
$idx=@file_get_contents(__DIR__.'/index.php');
$api=@file_get_contents(__DIR__.'/api.php');

echo "###### A) KALICILIK ######\n";
if($idx!==false){
    W($idx,'function loadHistory',0,1500,"loadHistory() TAM");
}
if($api!==false){
    /* dispatch tam */
    W($api,'$action',-40,60,"\$action alis");
    W($api,"=> doGenerate",-260,600,"dispatch: generate/history/photo civari");
    W($api,'function doGenerate',0,2400,"doGenerate TAM (kayit/docId dahil)");
    W($api,'function doKeep',0,700,"doKeep()");
    W($api,'function doHistory',0,900,"doHistory() (varsa)");
    W($api,"'history'",-260,600,"history dispatch mapping");
    /* depolama: DB mi dosya mi */
    foreach(['INSERT','REPLACE INTO','file_put_contents','->prepare','history','docs.json','$sid','session_id'] as $k)
        echo "  api '$k' -> ".substr_count($api,$k)." kez\n";
    W($api,'file_put_contents',-120,300,"file_put_contents (dosya depolama, varsa)");
    W($api,'$sid',-160,260,"\$sid nasil turetiliyor (ilk gecis)");
}

echo "\n###### B) MEVCUT NAV SIRALAYICI + MODS ######\n";
if($idx!==false){
    W($idx,'getElementById("nav-verlauf"),',-600,1200,"MEVCUT yeniden-siralayici (@~1167873)");
    W($idx,'function modOn',0,400,"modOn()");
    W($idx,'k:"briefe"',-500,1400,"MODS listesi (modul nav id'leri)");
    W($idx,'nav-"+k',-260,400,"nav-<k> gorunurluk toggle");
    W($idx,'CH_NAV',-40,300,"CH_NAV marker civari (mevcut menu yamasi)");
}
echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-fix3.php ===\n";
