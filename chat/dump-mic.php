<?php
/** ChatHelp — dump-mic (SADECE OKUR) — mikrofon calismiyor, cok dilli olsun.
 *  [1] SpeechRecognition fonksiyonu TAM (@869000-870600) — govde + dil + hata
 *  [2] mikrofon butonu HTML + onclick (input satirindaki mic)
 *  [3] fonksiyon adi nasil cagriliyor (buton -> fonksiyon)
 *  [4] 'pb' (kayit gostergesi) + input alani id (transkript nereye yaziliyor)
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

echo "════ [1] SpeechRecognition fonksiyonu (@868900-870600) ════\n";
slice($idx,868900,1750,'speech fn @868900');

echo "\n════ [2] mikrofon butonu HTML + onclick ════\n";
raw($idx,'class="ia-mic"',-100,180,'ia-mic',3);
raw($idx,'ia-mic',-80,140,'ia-mic gen',5);
raw($idx,'onclick="startVoice',-30,80,'startVoice',3);
raw($idx,'Voice',-40,90,'Voice',8);
raw($idx,'micBtn',-40,90,'micBtn',5);
/* input satiri butonlari */
raw($idx,'ia-btns',-30,200,'ia-btns (input butonlari)',3);

echo "\n════ [3] fonksiyon adi / cagri ════\n";
/* SpeechRecognition oncesi fonksiyon basligi */
$p=strpos($idx,"SpeechRecognition' in window");
if($p!==false){ $s=strrpos(substr($idx,0,$p),'function'); if($s!==false) echo "  fn baslik: ...".substr($idx,$s,80)."\n"; }
raw($idx,'startRec',-40,90,'startRec',4);
raw($idx,'toggleRec',-40,90,'toggleRec',4);

echo "\n════ [4] pb gostergesi + input id ════\n";
raw($idx,"getElementById('pb')",-60,120,'pb',4);
raw($idx,"getElementById('inp')",-20,90,'inp input',5);
raw($idx,'id="inp"',-40,120,'inp html',3);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
