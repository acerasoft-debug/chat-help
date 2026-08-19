<?php
/** ChatHelp — dump-composer (SADECE OKUR) — chat giris alani (composer):
 *  gonder butonu, mikrofon, foto/attach yukleme. Premiumlastirma icin.
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");
function raw($src,$needle,$pre,$len,$label,$max=8){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [1] gonder butonu ════\n";
raw($idx,'startVoice',-80,200,'startVoice cagrilari',10);
raw($idx,'sendMsg',-60,160,'sendMsg',6);
raw($idx,'onSend',-60,160,'onSend',6);
raw($idx,'id="send',-40,220,'id=send*',6);
raw($idx,'Senden',-80,120,'Senden metni',6);
raw($idx,'Gönder',-80,120,'Gonder metni',6);
raw($idx,'class="mic',-60,180,'class=mic',6);
raw($idx,'chSend',-60,180,'chSend',8);

echo "\n════ [2] composer / textarea ════\n";
raw($idx,'id="chat-input',-40,260,'chat-input',4);
raw($idx,'id="ci"',-40,220,'id=ci',4);
raw($idx,'class="composer',-60,300,'composer',4);
raw($idx,'placeholder=',-120,120,'placeholder',8);
raw($idx,'textarea',-80,220,'textarea',8);

echo "\n════ [3] foto / attach yukleme ════\n";
raw($idx,'type="file"',-140,260,'file input',8);
raw($idx,'analyze_photo',-80,180,'analyze_photo',6);
raw($idx,'doPhoto',-60,160,'doPhoto',5);
raw($idx,'chPhoto',-80,200,'chPhoto',8);
raw($idx,'multiple',-100,120,'multiple attr',8);
raw($idx,'accept=',-80,140,'accept',8);
raw($idx,'files.length',-100,160,'files.length',10);
raw($idx,'.files[',-100,140,'.files[',10);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
