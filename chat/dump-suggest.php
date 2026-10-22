<?php
/** ChatHelp — dump-suggest (SADECE OKUR) — [[SUGGEST:doc]] ham sizinti bug'i:
 *  frontend marker parser ([[DOC]]/[[SUGGEST]]) + backend prompt (api.php)
 *  hangi formati uretmesi soyleniyor. TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
$api=(string)@file_get_contents(__DIR__.'/api.php');
function raw($src,$needle,$pre,$len,$label,$max=8){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════════ [1] index.php — frontend marker parse ════════\n";
raw($idx,'SUGGEST',-80,220,'SUGGEST (frontend)',12);
raw($idx,'[[DOC',-80,220,'[[DOC parse',8);
raw($idx,'[[',-40,120,'[[ marker genel',20);
raw($idx,'chAiChatTurn',-30,200,'chAiChatTurn',4);

echo "\n════════ [2] api.php — backend prompt (marker formati) ════════\n";
if($api===''){ echo "api.php OKUNAMADI\n"; }
else {
  raw($api,'SUGGEST',-100,240,'SUGGEST (prompt)',10);
  raw($api,'[[DOC',-100,200,'[[DOC (prompt)',6);
  raw($api,'CH_AIROUTE',-60,180,'CH_AIROUTE',4);
}
echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
