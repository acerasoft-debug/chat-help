<?php
/** ChatHelp — dump-photo (SADECE OKUR) — ana chat foto/ek picker'i:
 *  .chp[data-ctx=main] enjeksiyonu, file input markup, change handler,
 *  5MB/tek-dosya limiti, _chDocFiles/_chPhotos. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");
function slice($s,$from,$len,$label){ echo "\n──────── $label (@$from) ────────\n".str_replace("\n","⏎",substr($s,max(0,$from),$len))."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [A] ana foto picker enjeksiyonu (.chp data-ctx=main) ════\n";
raw($idx,'data-ctx="main"',-400,900,'chp main enjekte',3);
raw($idx,'.chp[data-ctx',-200,300,'chp selector',6);
raw($idx,"class='chp'",-100,300,'chp class tekli',4);
raw($idx,'"chp"',-160,360,'chp class cift',6);

echo "\n════ [B] foto input olusturma + change (createElement input) ════\n";
raw($idx,"createElement('input')",-60,340,'input olustur',10);
raw($idx,'.accept',-40,120,'.accept ataması',12);
raw($idx,'5*1024*1024',-260,200,'5MB limit',6);
raw($idx,'zu groß',-320,140,'zu gross uyari',4);

echo "\n════ [C] _chDocFiles / _chPhotos limitleri ════\n";
raw($idx,'_chDocFiles',-120,240,'_chDocFiles',12);
raw($idx,'_chPhotos',-80,160,'_chPhotos',14);
raw($idx,'syncDocPhotos',-40,260,'syncDocPhotos',5);

echo "\n════ [D] cha2 doc attach (coklu, @1171800 civari) ════\n";
slice($idx,1171700,1700,'cha2 doc attach bolgesi');

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
