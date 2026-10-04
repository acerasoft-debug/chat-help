<?php
/** ChatHelp — dump-kontofix2 (SADECE OKUR) — Pläne butonu onclick, cat-toggle tam
 *  blok, Profil karti (k-name/konto-name doldurma + aktif profil). TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
function sl($s,$from,$len,$label){ echo "\n──────── $label (@$from,+$len) ────────\n".str_replace("\n","⏎",substr($s,max(0,$from),$len))."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [A] Pläne & Preise butonu (Konto) ════\n";
raw($idx,'Preise',-160,120,'Preise (buton HTML)',6);
raw($idx,'openPlans',-40,120,'openPlans cagrilari',6);
raw($idx,'k-plan-selector',-120,260,'k-plan-selector doldurma',3);
raw($idx,'renderKontoPlans',-30,200,'renderKontoPlans',3);

echo "\n════ [B] cat-toggle tam blok (@1108200,+1600) ════";
sl($idx,1108200,1600,'ch-cat-toggle blok');

echo "\n════ [C] Profil karti doldurma (k-name / aktif profil) ════\n";
raw($idx,'k-name',-40,120,'k-name',8);
raw($idx,'getElementById("k-name")',-40,120,'k-name JS',5);
raw($idx,"getElementById('k-name')",-40,120,'k-name JS tekli',5);
raw($idx,'function fillProfile',-20,200,'fillProfile',3);
raw($idx,'k-addr',-40,100,'k-addr',5);
raw($idx,'activeProfile',-40,160,'activeProfile',5);
raw($idx,'function actName',-20,180,'actName',3);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
