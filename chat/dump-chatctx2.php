<?php
/** ChatHelp — dump-chatctx2 (SADECE OKUR) — baglam duzeltmesi icin son parcalar.
 *  [1] api.php doAiChat KALANI (transcript sonrasi saglayici cagrisi + donus)
 *  [2] index.php 'aichat' gonderimi (history: ne gonderiliyor)
 *  [3] index.php mesaj dizisi: user mesaji + AI cevabi nasil ekleniyor
 *      (updateCur / x.messages / addMsg)
 *  [4] index.php __chDocPdf (extract/restore/card) — uretilen dilekce messages'a
 *      ekleniyor mu
 *  [5] index.php foto analiz fonksiyonlari TAM (chat + homepage) — sonucu
 *      messages'a ekleyecegimiz yerleri bulmak
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$api=(string)@file_get_contents("$D/api.php");
$idx=(string)@file_get_contents("$D/index.php");

function fnBody($s,$decl,$cap=2000){
  $p=strpos($s,$decl); if($p===false) return "(YOK: $decl)";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,150);
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  return substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kesildi)":"");
}
function slice($s,$from,$len,$label){ echo "\n──────── $label (@$from) ────────\n".substr($s,$from,$len)."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] doAiChat KALANI (transcript sonrasi) ════\n";
$p=strpos($api,'function doAiChat(');
if($p!==false){ $t=strpos($api,'$transcript .= "User:',$p); echo $t!==false? substr($api,$t,1700)."\n" : "(transcript sonu bulunamadi)\n"; }

echo "\n════ [2] index.php aichat gonderimi ════\n";
raw($idx,'aichat',-120,240,'aichat',6);

echo "\n════ [3] mesaj dizisi ekleme (updateCur/messages/addMsg) ════\n";
raw($idx,'x.messages',-40,110,'x.messages',12);
raw($idx,'function addMsg',0,200,'addMsg tanimi',2);
raw($idx,'history:',-30,160,'history: payload',8);

echo "\n════ [4] __chDocPdf (dilekce messages'a ekleniyor mu) ════\n";
$p=strpos($idx,'window.__chDocPdf=');
echo $p!==false ? substr($idx,$p,1800)."\n[/end]\n" : "(window.__chDocPdf= YOK)\n";
raw($idx,'__chDocPdf.card',-20,80,'card cagrisi',4);

echo "\n════ [5] foto analiz fonksiyonlari (chat + homepage) ════\n";
raw($idx,'async function analyze',0,120,'analyze tanimlari',4);
raw($idx,'function analyze',0,120,'analyze (sync) tanimlari',4);
$p=strpos($idx,'analyze_photo'); if($p!==false) slice($idx,max(0,$p-700),1500,'analyze_photo cevresi #1');
$p2=strpos($idx,'analyze_photo',$p+10); if($p2!==false) slice($idx,max(0,$p2-700),1500,'analyze_photo cevresi #2');

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
