<?php
/** ChatHelp — dump-tone: mevcut ton ayarlama sistemini araştırır (SADECE OKUR) */
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

echo "###### 1) index.php: ton seçici UI + değişkeni ######\n";
foreach(['tone','Ton anpassen','Tonalität','sachlich','freundlich','bestimmt','streng','Ton:','let tone','var tone','tone=','ch-tone'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}
FX($idx,'function setTone','setTone (varsa)');
FX($idx,'function selectTone','selectTone (varsa)');
WIN($idx,'ch-tone',-200,900,'ch-tone bağlamı (varsa, UI)');
WIN($idx,"tone:'standard'",-150,300,'tone default ataması (varsa)');
WIN($idx,'tone,',-150,350,'tone parametresi genDoc gönderiminde');

echo "\n\n###### 2) index.php: pro/elit erişim kontrolü (paywall/plan gate) ######\n";
foreach(['plan===\'pro\'','plan===\'elite\'','plan==\'pro\'','plan==\'elite\'',"plan === 'pro'","plan === 'elite'",'ab Pro','ab Elite'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}
WIN($idx,'ab Pro',-250,500,'\"ab Pro\" bağlamı (varsa)');

echo "\n\n###### 3) api.php: tone parametresi nasıl işleniyor, sistem promptuna nasıl giriyor ######\n";
if($api!==false){
  foreach(['tone','toneMap',"\$tone"] as $k){
    $ps=ALL($api,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,6)):'yok')."\n";
  }
  WIN($api,'toneMap',-60,1200,'toneMap tanımı (varsa)');
  WIN($api,"\$tone = \$b",-60,400,'tone parametre alımı');
} else { echo "api.php okunamadı\n"; }

echo "\n\n###### 4) DOC_TIER / fiyat kademeleri (1,99€ = einfach mi?) ######\n";
WIN($idx,'DOC_TIER=',-20,400,'DOC_TIER tanımı başlangıcı');
WIN($idx,"einfach:1",-100,300,'einfach fiyatı (1,99€ eşleşmesi)');
foreach(['DOC_PRICES','einfach','standard','komplex'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".count($ps)." kez\n";
}

echo "\n=== BİTTİ. SİL: rm dump-tone.php ===\n";
