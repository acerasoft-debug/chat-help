<?php
/** ChatHelp — dump-store (SADECE OKUR) — sunucu tarafi kanonik hafiza kurmak icin:
 *  [1] STORE_DIR / depolama sabiti + dizin olusturma (mkdir)
 *  [2] $sid nasil elde ediliyor (ch_sid cookie)
 *  [3] router satirlari: doAiChat / doPhoto / doGenerate ($sid geciyor mu)
 *  [4] doGenerate KUYRUGU (dilekce nasil uretilip respond ediliyor — hafizaya
 *      yazacagimiz nokta)
 *  [5] doAiChat KUYRUGU (saglayici cagrisi + respond — transkripte hafiza
 *      enjekte edecegimiz nokta)
 *  [6] mevcut dosya yaz/oku ornegi (doSaveDoc/doHistory) — desene uymak icin
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$api=(string)@file_get_contents(__DIR__.'/api.php');

function fnBody($s,$decl,$cap=1600){
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

echo "════ [1] STORE_DIR / depolama ════\n";
raw($api,'STORE_DIR',-10,120,'STORE_DIR',8);
raw($api,'@mkdir',-20,120,'mkdir',5);
raw($api,'sys_get_temp_dir',-20,120,'sys_get_temp_dir',3);

echo "\n════ [2] \$sid elde etme ════\n";
raw($api,'ch_sid',-40,120,'ch_sid',6);
raw($api,'$sid =',-4,140,'$sid atamasi',6);
raw($api,'$sid=',-4,140,'$sid= atamasi',4);

echo "\n════ [3] router satirlari ════\n";
raw($api,'=> doAiChat',-20,60,'doAiChat route',2);
raw($api,'=> doPhoto',-20,60,'doPhoto route',2);
raw($api,'=> doGenerate',-20,60,'doGenerate route',2);

echo "\n════ [4] doGenerate KUYRUGU (respond'a kadar son 1200) ════\n";
$p=strpos($api,'function doGenerate(');
if($p!==false){
  // fonksiyon govdesini bul, sonundan geriye 1300 bayt
  $b=strpos($api,'{',$p);$depth=0;$i=$b;$len=strlen($api);
  for(;$i<$len;$i++){ $c=$api[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){$i++;break;} } }
  echo substr($api,max($p,$i-1300),min(1300,$i-$p))."\n";
} else echo "(doGenerate YOK)\n";

echo "\n════ [5] doAiChat KUYRUGU (son 1400) ════\n";
$p=strpos($api,'function doAiChat(');
if($p!==false){
  $b=strpos($api,'{',$p);$depth=0;$i=$b;$len=strlen($api);
  for(;$i<$len;$i++){ $c=$api[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){$i++;break;} } }
  echo substr($api,max($p,$i-1400),min(1400,$i-$p))."\n";
} else echo "(doAiChat YOK)\n";

echo "\n════ [6] doSaveDoc (dosya yaz ornegi) ════\n";
echo fnBody($api,'function doSaveDoc(',900)."\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
