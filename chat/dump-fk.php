<?php
/** ChatHelp — dump-fk (SADECE OKUR) — "Fälle'ye aktar" butonu icin:
 *  [1] FK localStorage anahtari (var FK=) + fall obje yapisi
 *  [2] fall OLUSTURMA global fonksiyonu (window.fall... / fallNew / fallCreate)
 *  [3] pnl-ant history render (@994100-995400) — belge ogeleri nasil ciziliyor
 *      (transfer butonu ekleyecegimiz yer + belge metnine erisim)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function slice($s,$from,$len,$label){ echo "\n──────── $label (@$from) ────────\n".substr($s,max(0,$from),$len)."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=8){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] FK anahtari + fall obje ════\n";
raw($idx,'FK=',-20,80,'FK tanimi',5);
raw($idx,"var FK",-10,90,'var FK',4);
raw($idx,'const FK',-10,90,'const FK',4);
raw($idx,'createdAt:Date.now()',-160,60,'fall obje olusturma',4);

echo "\n════ [2] fall olusturma global fonksiyon ════\n";
raw($idx,'window.fall',-10,90,'window.fall*',12);
raw($idx,'fallNew',-30,120,'fallNew',4);
raw($idx,'all.push({',-30,180,'all.push fall',4);

echo "\n════ [3] pnl-ant history render ════\n";
slice($idx,994100,1400,'ant history render @994100');

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
