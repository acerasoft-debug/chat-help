<?php
/** ChatHelp — dump-composer2 (SADECE OKUR) — composer HTML markup: input bar,
 *  sbtn (gonder), pb (mic), .ia-btns, ana foto file input. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");
function slice($s,$from,$len,$label){ echo "\n──────── $label (@$from) ────────\n".str_replace("\n","⏎",substr($s,max(0,$from),$len))."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=8){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [A] composer input bar (.ia-btns civari @867300, 2600 bayt) ════\n";
slice($idx,867300,2600,'ia-btns bolgesi');

echo "\n════ [B] gonder butonu markup ════\n";
raw($idx,'id="sbtn"',-120,260,'id=sbtn markup',4);
raw($idx,"id='sbtn'",-120,260,"id=sbtn tekli tirnak",4);
raw($idx,'sbtn',-40,120,'sbtn tum gecisler',20);

echo "\n════ [C] ana foto/attach butonu markup ════\n";
raw($idx,'id="pb"',-60,300,'id=pb (mic) markup',3);
raw($idx,'onclick="startVoice',-80,200,'startVoice buton',3);
raw($idx,'id="inp"',-200,400,'id=inp input markup',3);
raw($idx,"getElementById('inp')",-40,80,'inp erisim',12);

echo "\n════ [D] ana foto file input (chat composer) ════\n";
raw($idx,'onchange="chPhotoPick',-60,200,'chPhotoPick',4);
raw($idx,'accept="image',-160,320,'accept=image file input',8);
raw($idx,'id="mi"',-160,320,'id=mi (foto)',4);
raw($idx,"getElementById('mi')",-60,200,'mi erisim',6);
raw($idx,'window.chPhoto',-40,180,'window.chPhoto*',10);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
