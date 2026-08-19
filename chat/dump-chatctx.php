<?php
/** ChatHelp — dump-chatctx (SADECE OKUR) — konusma BAGLAMI iki AI arasinda
 *  paylasilsin + PDF/foto sonrasi kopma olmasin. Gormem gerekenler:
 *  [1] api.php doAiChat TAM (mesajlar nasil toplaniyor, gecmis ekleniyor mu)
 *  [2] api.php pickProvider TAM (DeepSeek/Claude secimi)
 *  [3] api.php callClaude + callDeepSeek imza/govde (messages nasil gonderiliyor)
 *  [4] api.php doPhoto — foto analizi gecmise yaziliyor mu / client'a nasil donuyor
 *  [5] api.php doFollowup — dilekce sonrasi takip
 *  [6] index.php: aichat CAGRISI (payload'da messages/history var mi),
 *      foto sonucu + [[DOC]] mesaj dizisine ekleniyor mu (getCur().m)
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
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] api.php doAiChat ════\n";
echo fnBody($api,'function doAiChat(',2400)."\n";

echo "\n════ [2] api.php pickProvider ════\n";
echo fnBody($api,'function pickProvider(',1400)."\n";

echo "\n════ [3] api.php callClaude + callDeepSeek ════\n";
echo "-- callClaude --\n".fnBody($api,'function callClaude(',1200)."\n";
echo "\n-- callDeepSeek --\n".fnBody($api,'function callDeepSeek(',1200)."\n";
raw($api,"'messages'",-20,80,"messages payload (api)",8);

echo "\n════ [4] api.php doPhoto ════\n";
echo fnBody($api,'function doPhoto(',2000)."\n";

echo "\n════ [5] api.php doFollowup ════\n";
echo fnBody($api,'function doFollowup(',1400)."\n";

echo "\n════ [6] index.php aichat cagrisi + mesaj dizisi ════\n";
raw($idx,"action:'aichat'",-40,260,"aichat payload",4);
raw($idx,'action:"aichat"',-40,260,'aichat payload (cift)',4);
raw($idx,'getCur().m',-30,120,'getCur().m (mesaj dizisi)',10);
raw($idx,'analyze_photo',-30,220,'analyze_photo cagrisi',4);
raw($idx,'.m.push',-40,120,'mesaj dizisine push',12);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
