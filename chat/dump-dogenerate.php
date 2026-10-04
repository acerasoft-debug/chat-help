<?php
/** ChatHelp — dump-dogenerate: action=generate'i işleyen backend fonksiyonu + TAM match/switch listesi (SADECE OKUR) */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$api=@file_get_contents(__DIR__.'/api.php');
if($api===false) exit("api.php yok\n");

function FX($s,$name,$lbl,$cap=9000){
  echo "\n──────── $lbl ────────\n";
  $p=strpos($s,$name);
  if($p===false){ echo "(BULUNAMADI: $name)\n"; return; }
  $b=strpos($s,'{',$p); if($b===false){ echo substr($s,$p,300)."\n"; return; }
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  $body=substr($s,$p,min($i-$p,$cap));
  echo $body.(($i-$p)>$cap?"\n…(kırpıldı)":"")."\n";
}
function WIN($s,$needle,$b,$l,$lbl){echo "\n──────── $lbl ────────\n";$p=strpos($s,$needle);if($p===false){echo "(yok: $needle)\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}
function ALL($s,$needle){$out=[];$o=0;while(($p=strpos($s,$needle,$o))!==false){$out[]=$p;$o=$p+1;}return $out;}

echo "###### 1) 'generate' kelimesi geçen TÜM yerler (kaç kez, nerede) ######\n";
$ps=ALL($api,'generate'); echo "toplam ".count($ps)." kez @".implode(',',$ps)."\n";
foreach($ps as $i=>$p){ echo "\n[generate #".($i+1)." @$p]\n".substr($api,max(0,$p-150),300)."\n"; }

echo "\n\n###### 2) match/switch TAM bloğu (kırpılmadan — hangi action'lar var) ######\n";
WIN($api,'match (',-50,2500,'match(...) bloğu (tam)');
WIN($api,'switch (',-50,2500,'switch(...) bloğu (tam, varsa)');
WIN($api,'switch($action)',-50,2500,"switch(\$action) bloğu (bosluksuz varyant)");

echo "\n\n###### 3) doGenerate fonksiyonu — TAM GÖVDE ######\n";
FX($api,'function doGenerate','doGenerate TAM',12000);

echo "\n\n###### 4) 'callClaude(' çağrıları — kaçı var, generate'de hangisi kullanılıyor ######\n";
$ps=ALL($api,'callClaude('); echo "toplam ".count($ps)." kez @".implode(',',$ps)."\n";

echo "\n\n###### 5) \$sysLaw TÜM atama/kullanım yerleri (kaçı var, hangisi generate'de) ######\n";
$ps=ALL($api,'$sysLaw'); echo "toplam ".count($ps)." kez @".implode(',',$ps)."\n";
foreach($ps as $i=>$p){ echo "\n[\$sysLaw #".($i+1)." @$p]\n".substr($api,max(0,$p-80),240)."\n"; }

echo "\n=== BİTTİ. SİL: rm dump-dogenerate.php ===\n";
