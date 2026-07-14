<?php
/** ChatHelp — dump-tb (SADECE OKUR) — ust bar birlestirme + mikrofon.
 *  [1] ust bar butonlari HTML (@851100-852600): tb-auth-btn (Serdar) +
 *      tb-btn'ler (Meine Anträge / Konto / belge ikonu) — birlestirilecek/kaldirilacak
 *  [2] handleUserBtn TAM tanimi (Serdar butonu ne yapiyor)
 *  [3] mikrofon butonu: mic/rec/speech/SpeechRecognition/sbtn yani (calismiyor)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function fnBody($s,$decl,$cap=1400){
  $p=strpos($s,$decl); if($p===false) return "(YOK: $decl)";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,150);
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  return substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kesildi)":"");
}
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

echo "════ [1] ust bar butonlari HTML ════\n";
slice($idx,851180,1500,'topbar HTML @851180');

echo "\n════ [2] handleUserBtn tanimi ════\n";
echo fnBody($idx,'function handleUserBtn(',1200)."\n";

echo "\n════ [3] mikrofon butonu ════\n";
raw($idx,'sbtn',-40,90,'sbtn (send btn?)',4);
raw($idx,'id="mic"',-60,180,'mic id',4);
raw($idx,'mic',-30,80,'mic gecen yerler',10);
raw($idx,'SpeechRecognition',-60,160,'SpeechRecognition',5);
raw($idx,'webkitSpeechRecognition',-40,120,'webkitSpeechRecognition',4);
raw($idx,'recognition',-30,110,'recognition',6);
raw($idx,'getUserMedia',-40,120,'getUserMedia',3);
raw($idx,'🎤',-40,120,'mic emoji',5);
raw($idx,'onclick="startRec',-40,120,'startRec',3);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
