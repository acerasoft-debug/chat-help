<?php
/** ChatHelp — dump-doc-output: üretilen dilekçe çıktısı (damga/marka, yazı tipi, sayfa/PDF düzeni) — SADECE OKUR */
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

echo "###### 1) 'chat-help' / 'ChatHelp' damgası nerede geçiyor (çıktı/export bağlamında) ######\n";
foreach(['chat-help','ChatHelp','erstellt mit','powered by','Erstellt von','watermark'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".count($ps)." kez\n";
}
$positions=ALL($idx,'chat-help');
foreach(array_slice($positions,0,12) as $p){
  echo "\n  [@$p context]\n  ".substr($idx,max(0,$p-90),190)."\n";
}

echo "\n\n###### 2) Dilekçe/belge çıktı render fonksiyonu (HTML/PDF şablonu) ######\n";
foreach(['function renderDoc','function showDoc','function displayDoc','doc-output','docout','doc-view','function printDoc','function exportDoc'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}
FX($idx,'function renderDoc','renderDoc (varsa)');
FX($idx,'function showDoc','showDoc (varsa)');
FX($idx,'function displayDoc','displayDoc (varsa)');

echo "\n\n###### 3) Yazı tipi / print CSS (estetik, kurumsal görünüm için) ######\n";
foreach(['@media print','page-break','font-family','.doc-page','.doc-paper','.pdf-page'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}
WIN($idx,'@media print',-40,1200,'@media print bloğu (varsa)');
WIN($idx,'.doc-page',-40,700,'.doc-page stil tanımı (varsa)');

echo "\n\n###### 4) PDF üretimi: sayfalama mantığı (tek cümle için ekstra sayfa nereden geliyor) ######\n";
foreach(['jsPDF','html2pdf','html2canvas','addPage','splitTextToSize'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}
WIN($idx,'addPage',-300,700,'addPage() çağrısı bağlamı (varsa)');
WIN($idx,'html2pdf',-100,700,'html2pdf kullanım bağlamı (varsa)');

if($api!==false){
  echo "\n\n###### 5) api.php: belge metnine damga/imza ekleniyor mu (backend tarafı) ######\n";
  foreach(['chat-help','ChatHelp','erstellt mit','Erstellt von','powered by'] as $k){
    $ps=ALL($api,$k); echo "  '$k' -> ".count($ps)." kez\n";
  }
  $ap=ALL($api,'chat-help');
  foreach(array_slice($ap,0,8) as $p){
    echo "\n  [@$p context]\n  ".substr($api,max(0,$p-90),190)."\n";
  }
}

echo "\n=== BİTTİ. SİL: rm dump-doc-output.php ===\n";
