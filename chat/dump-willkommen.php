<?php
/** ChatHelp — dump-willkommen (SADECE OKUR) — "Willkommen! Bitte geben Sie zuerst
 *  Ihre Daten ein" prompt'unun tetikleyicisi + kime gosterildigi (kayitli/yeni gate).
 *  TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ Willkommen prompt tetikleyici ════\n";
raw($idx,'Willkommen',-200,220,'Willkommen',8);
raw($idx,'Bitte geben Sie zuerst',-200,180,'Bitte geben Sie zuerst',5);
raw($idx,'auf genau diesen Namen',-260,140,'auf genau diesen Namen',4);
raw($idx,'showProfileForm',-40,200,'showProfileForm',8);
raw($idx,'showProfile',-40,160,'showProfile*',10);
raw($idx,'!P.f1',-80,180,'!P.f1 (profil bos kontrolu)',10);
raw($idx,'zuerst Ihre',-200,140,'zuerst Ihre',5);
echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
