<?php
/** ChatHelp — dump-konto-profil (SADECE OKUR)
 *  #29 plan bazli profiller icin: Konto panelindeki profil DUZENLEME formu
 *  (f1-f7 inputlari), kaydetme akisi, P objesinin yuklenmesi/persist edilmesi.
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php okunamadi\n");
function W($src,$needle,$before,$len,$label){
    echo "──────── $label ────────\n";
    $p=strpos($src,$needle);
    if($p===false){ echo "  (yok: '$needle')\n\n"; return; }
    echo "[@$p]\n".substr($src,max(0,$p-$before),$len)."\n---\n\n";
}
function ALLW($src,$needle,$before,$len,$label,$max=6){
    echo "──────── $label (ilk $max) ────────\n"; $off=0;$i=0;
    while(($p=strpos($src,$needle,$off))!==false && $i<$max){
        echo "[$i @$p] ".substr($src,max(0,$p-$before),$len)."\n....\n"; $off=$p+strlen($needle);$i++;
    }
    if($i===0) echo "  (yok)\n"; echo "\n";
}
/* P objesi: tanim + persist */
foreach(['let P=','var P=','const P=','P.f1','ch_prof_v3','ch_profiles','saveProfile','profil speichern','Profil speichern'] as $k)
    echo "  '$k' -> ".substr_count($idx,$k)." kez\n";
echo "\n";
W($idx,'let P=',-40,400,"P tanimi (let)");
W($idx,'var P=',-40,400,"P tanimi (var)");
ALLW($idx,"P.f1",-120,260,"P.f1 kullanim ornekleri",5);
/* Konto panel: f1..f7 edit inputlari */
ALLW($idx,'id="pf1"',-200,400,"pf1 input (varsa)",2);
ALLW($idx,'id="f1"',-200,400,"f1 input (varsa)",2);
W($idx,'Vorname',-300,700,"Konto 'Vorname' cevresi (edit formu)");
W($idx,'k-name',-500,900,"Konto k-name cevresi (panel yapisi)");
/* profil kaydetme handler */
W($idx,'saveProfile',0,700,"saveProfile (varsa)");
ALLW($idx,"ch_prof_v3",-160,300,"ch_prof_v3 gecisleri",6);
W($idx,'pnl-konto',-100,700,"pnl-konto panel govdesi basi");
echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-konto-profil.php ===\n";
