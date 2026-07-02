<?php
/** ChatHelp — dump-gendoc: belge üretimi ülke/hukuk bilgisini gerçekten backend'e gönderiyor mu? (SADECE OKUR) */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
$api=@file_get_contents(__DIR__.'/api.php');
if($idx===false) exit("index.php yok\n");

function FX($s,$name,$lbl,$cap=6000){
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

echo "###### 1) genDoc fonksiyonu (belge üretimini tetikleyen JS) — TAM ######\n";
FX($idx,'function genDoc','genDoc TAM',9000);
FX($idx,'async function genDoc','genDoc TAM (async)',9000);

echo "\n\n###### 2) fetch(API...) çağrıları — kaçı var, hangi body gönderiyorlar ######\n";
$ps=ALL($idx,'fetch(API'); echo "  'fetch(API' -> ".count($ps)." kez @".implode(',',array_slice($ps,0,10))."\n";
foreach(array_slice($ps,0,6) as $i=>$p){ echo "\n[fetch #".($i+1)." @$p]\n".substr($idx,max(0,$p-80),500)."\n"; }

echo "\n\n###### 3) 'country' kelimesi geçen TÜM yerler index.php'de (frontend'de nerede kullanılıyor) ######\n";
$ps=ALL($idx,'country'); echo "toplam ".count($ps)." kez\n";
foreach($ps as $p){ echo "\n[@$p]\n".substr($idx,max(0,$p-90),160)."\n"; }

echo "\n\n###### 4) 'action=generate' veya doGenerate çağrısını tetikleyen istek gövdesi ######\n";
WIN($idx,'action=generate',-300,700,"'action=generate' bağlamı");
WIN($idx,'action:',-200,300,"'action:' bağlamı (body objesi olabilir)");

if($api===false){ echo "\n\n(api.php okunamadi)\n"; exit; }

echo "\n\n###### 5) api.php: doGenerate/ana handler — \$country DEĞİŞKENİ NEREDEN GELİYOR ######\n";
$ps=ALL($api,'$country'); echo "  '\$country' -> ".count($ps)." kez @".implode(',',array_slice($ps,0,10))."\n";
foreach(array_slice($ps,0,3) as $i=>$p){ echo "\n[\$country #".($i+1)." @$p]\n".substr($api,max(0,$p-200),350)."\n"; }

echo "\n\n###### 6) api.php: POST/GET/JSON body okuma satırları (istek nasıl parse ediliyor) ######\n";
foreach(['$_POST','$_GET','json_decode(file_get_contents','php://input'] as $k){
  $ps=ALL($api,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,6)):'yok')."\n";
}
WIN($api,'php://input',-100,500,"php://input bağlamı");

echo "\n\n###### 7) lawSystems kullanım bağlamı (sysLaw ataması TAM) ######\n";
WIN($api,'$sysLaw = $lawSystems[$country]',-400,700,'sysLaw atama bağlamı');

echo "\n=== BİTTİ. SİL: rm dump-gendoc.php ===\n";
