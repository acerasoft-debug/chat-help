<?php
/** ChatHelp — dump-tonegate (SADECE OKUR) — uslup(Ton) + strateji kapilari haritasi
 *  F2-6: uslup secicileri (sachlich/förmlich...) TUM kullanicilara acilacak,
 *  strateji onerisi yalniz paketlerde kalacak. Ikisinin plan-gate kodunu ham
 *  baytlarla verir (index frontend + fall-api/api backend). TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;

function raw($src,$needle,$pre,$len,$label,$max=3){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        $s=max(0,$p-$pre);
        echo "[@$p]\n".substr($src,$s,$len)."\n[/end]\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n";
    $tot=substr_count($src,$needle); if($tot>$max) echo "(toplam $tot)\n";
}

$idx=(string)@file_get_contents("$D/index.php");
echo "════════════════ index.php ════════════════\n";
raw($idx,'TONE SELECTOR',-40,1000,'TONE SELECTOR bloku (plan gate + injectTone)',1);
raw($idx,'_chTone',-40,90,'_chTone kullanimi',10);
raw($idx,'Strategie',-70,150,'Strategie gecisleri (frontend)',8);
raw($idx,"if(plan==='elite')",-20,160,"plan elite if bloklari",6);
raw($idx,"if(plan==='pro'",-20,160,"plan pro if bloklari",6);
raw($idx,"pl!=='free'",-30,120,"ucretli-plan kontrolu",6);

$fall=(string)@file_get_contents("$D/fall-api.php");
echo "\n════════════════ fall-api.php ════════════════\n";
raw($fall,'toneMap',-80,460,'toneMap (uslup->talimat) + cevresi',2);
raw($fall,'Schreibton',-50,180,'Schreibton (Pro/Elite yorumu)',3);

$api=(string)@file_get_contents("$D/api.php");
echo "\n════════════════ api.php ════════════════\n";
raw($api,'STRATEGIE',-30,260,'STRATEGIE ANALYSE (Elite)',2);
raw($api,'doStrateg',-20,120,'strateji fonksiyonu',3);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
