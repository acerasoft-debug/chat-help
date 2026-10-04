<?php
/** ChatHelp — dump-vst-xsets (SADECE OKUR) — modul ulke-takas: syncX + XSETS +
 *  mode hesabi + ccNow + AT/CH su an nasil davraniyor. AT/CH seti eklemek icin.
 *  TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
function sl($s,$from,$len,$label){ echo "\n──────── $label (@$from,+$len) ────────\n".str_replace("\n","⏎",substr($s,max(0,$from),$len))."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=8){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [A] syncX + XSETS + mode (@1411400-1447600) ════";
sl($idx,1411400,1500,'CH_VST_X baslangic (XSETS + mode)');
sl($idx,1446200,1500,'syncX fonksiyonu + merge/delete');

echo "\n════ [B] XSETS ulke anahtarlari + ornek ════\n";
raw($idx,'XSETS',-20,80,'XSETS gecisler',16);
raw($idx,'XSETS={',-10,300,'XSETS tanim',3);
raw($idx,'mode=',-40,120,'mode= hesabi',10);
raw($idx,'ccNow',-20,140,'ccNow',8);

echo "\n════ [C] AT/CH modul handling var mi ════\n";
raw($idx,'"AT"',-60,90,'AT string (modul)',10);
raw($idx,'"CH"',-60,90,'CH string (modul)',10);
raw($idx,'at_vertraege',-40,70,'at_vertraege',6);
raw($idx,'ch_vertraege',-40,70,'ch_vertraege',6);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
