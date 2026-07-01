<?php
/** ChatHelp — dump-plan-limits: mevcut plan/pro-elite kontrol + günlük kullanım sınırı mekanizması (SADECE OKUR) */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
$api=@file_get_contents(__DIR__.'/api.php');
if($idx===false) exit("index.php yok\n");

function FX($s,$name,$lbl,$cap=5000){
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

echo "###### 1) Foto-gate (mevcut pro/elit erişim örneği — aynı desen kullanılacak) ######\n";
foreach(['ch-foto-gate','plan===\'elite\'','plan==\'elite\'',"plan === 'elite'","plan==='elite'"] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}
WIN($idx,'ch-foto-gate',-100,1500,'ch-foto-gate bağlamı (mevcut pro/elit paywall örneği)');

echo "\n\n###### 2) Günlük kullanım sınırı / rate-limit mekanizması var mı ######\n";
foreach(['dailyLimit','günlük','gunluk','rate_limit','usage_count','ch_daily','localStorage.getItem(\'ch_'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}

echo "\n\n###### 3) plan değişkeni nasıl belirleniyor (localStorage mi, backend mi) ######\n";
foreach(['let plan','var plan','const plan','plan=','function getPlan'] as $k){
  $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
}
WIN($idx,'let plan',-40,300,'plan değişkeni tanımı');
WIN($idx,'function getPlan',-20,500,'getPlan (varsa)');

echo "\n\n###### 4) api.php: backend'de kullanım sayacı / plan doğrulaması var mı ######\n";
if($api!==false){
  foreach(['usage_count','daily_limit','rate_limit',"\$plan",'user_usage'] as $k){
    $ps=ALL($api,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
  }
  WIN($api,"\$plan",-60,400,'$plan kullanımı (backend doğrulama var mı)');
} else { echo "api.php okunamadı\n"; }

echo "\n\n###### 5) ZIP indirme altyapısı var mı (JSZip vb.) ######\n";
foreach(['JSZip','zip.js','function downloadZip','function exportZip'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez':'yok')."\n";
}

echo "\n=== BİTTİ. SİL: rm dump-plan-limits.php ===\n";
