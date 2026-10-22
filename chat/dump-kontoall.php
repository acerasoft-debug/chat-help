<?php
/** ChatHelp — dump-kontoall (SADECE OKUR) — welcome prompt trigger + ch_profiles
 *  yapisi/switch/P-sync + Pläne buton HTML. Tek apply icin. TAMAMINI gonder. */
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
echo "════ [A] welcome prompt objesi + trigger ════";
sl($idx,103500,720,'welcome obje (@103500) — anahtar/degisken');
raw($idx,'welcomeMsg',-40,180,'welcomeMsg',5);
raw($idx,'wlcMsg',-40,180,'wlcMsg',5);
raw($idx,'introMsg',-40,180,'introMsg',5);
/* trigger: welcome objesinin degisken adini bul, kullanimini goster */
raw($idx,'geben Sie zuerst',-260,40,'welcome degisken adi (once)',3);

echo "\n════ [B] Absender-Profile: ch_profiles yapisi + switch + P sync ════";
sl($idx,1616600,2700,'Absender-Profile blok (@1616600)');

echo "\n════ [C] aktif profil secme + P.f1 atama ════\n";
raw($idx,'P.f1=',-60,120,'P.f1= atama',10);
raw($idx,'.f1=a',-40,90,'.f1=a (profil->P)',8);
raw($idx,'prof.f',-40,90,'prof.f',8);
raw($idx,'act.f',-40,90,'act.f',8);

echo "\n════ [D] Pläne & Preise buton (Konto HTML) ════\n";
raw($idx,'onclick="gP(\'konto\')',-40,120,"konto giris",4);
sl($idx,1042000,700,'Konto plan bolumu (@1042000)');

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
