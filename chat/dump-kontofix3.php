<?php
/** ChatHelp — dump-kontofix3 (SADECE OKUR) — Absender-Profile (ch_profiles yapisi +
 *  switch + P sync), homepage Kategorien toggle HTML, Pläne&Preise buton HTML.
 *  TAMAMINI gonder. */
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
echo "════ [A] Absender-Profile sistemi (ch_profiles yapisi) ════";
sl($idx,1616600,2600,'Absender-Profile blok bas (LSK/LSA/F/render)');
raw($idx,'ch_profiles',-30,180,'ch_profiles kullanimi',10);
raw($idx,'.f1=',-40,90,'.f1= atamalari (P sync)',12);
raw($idx,'setActive',-40,180,'setActive',6);
raw($idx,'function pick',-20,180,'pick/select profil',5);

echo "\n════ [B] homepage Kategorien toggle (gorunur bar) ════\n";
raw($idx,'Kategorien',-120,140,'Kategorien (HTML gorunur)',10);
raw($idx,'cgrid',-60,120,'cgrid',6);

echo "\n════ [C] Pläne & Preise buton HTML (Konto) ════\n";
raw($idx,'pc-tier',-200,80,'pc-tier oncesi (plan buton civari)',3);
raw($idx,'gP(\'konto\'',-40,120,"gP konto",4);
raw($idx,'k-plan-name',-160,100,'k-plan-name (buton civari)',4);
raw($idx,'Preise</',-200,60,'Preise</ (buton kapanis)',4);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
