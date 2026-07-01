<?php
/** ChatHelp — dump-photo-all: TÜM foto/dosya yükleme akışını tarar (+ butonu, Fall eröffnen, backend) — SADECE OKUR */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
$api=@file_get_contents(__DIR__.'/api.php');
if($idx===false) exit("index.php yok\n");

function FX($s,$name,$lbl,$cap=5500){
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

echo "###### 1) '+' butonu (input yanındaki küçük ekleme butonu — ch-attach) ######\n";
foreach(['ch-attach','ch-attach-v2','attach-btn','function attachFile','function pickFile','id=\"attach'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}
WIN($idx,'ch-attach',-100,2500,'ch-attach TAM bağlam (buton + dosya seçme mantığı)');

echo "\n\n###### 2) Dosya okuma/base64 dönüştürme (FileReader) ######\n";
foreach(['FileReader','readAsDataURL','base64','_pendingPhoto','_attachedFile','_pendingFile'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,6)):'yok')."\n";
}

echo "\n\n###### 3) genDoc() içinde foto/dosya gönderimi var mı ######\n";
FX($idx,'function genDoc','genDoc TAM',6000);

echo "\n\n###### 4) Fall eröffnen (openFall) foto akışı — TAM ######\n";
FX($idx,'function openFall','openFall TAM',7000);

echo "\n\n###### 5) Boyut/format sınırı (sessizce reddediyor mu) ######\n";
foreach(['5*1024*1024','10*1024*1024','MAX_FILE','maxSize','image/jpeg','image/png','file.size','files[0].size'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}

echo "\n\n###### 6) api.php: fotoğrafların backend'de nasıl işlendiği ######\n";
if($api!==false){
  foreach(['photos','callClaudeVision','\$photos','base64'] as $k){
    $ps=ALL($api,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
  }
  WIN($api,'\$photos',-100,700,'\$photos parametresi alım bağlamı (doGenerate içinde)');
} else { echo "api.php okunamadı\n"; }

echo "\n\n###### 7) Foto-gate (pro/elit kısıtlaması) — foto engelleniyor olabilir mi ######\n";
WIN($idx,'ch-foto-gate',-150,1800,'ch-foto-gate bağlamı');

echo "\n=== BİTTİ. SİL: rm dump-photo-all.php ===\n";
