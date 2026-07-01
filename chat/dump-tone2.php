<?php
/** ChatHelp — dump-tone2: ton sistemi TAM detay (gating, genDoc gönderimi, iki UI'nin farkı) — SADECE OKUR */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
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

echo "###### 1) Ton bölgesi GENİŞ pencere (16000-21200 arası — tone-bar + gating + genDoc gönderimi) ######\n";
echo substr($idx, 16000, 5200) . "\n";

echo "\n\n###### 2) tone-modal bölgesi (469000-472600 arası — ayrı modal UI) ######\n";
echo substr($idx, 469000, 3600) . "\n";

echo "\n\n###### 3) genDoc fonksiyonu TAM (tone parametresi gerçekten gönderiliyor mu) ######\n";
FX($idx,'function genDoc','genDoc tam',5000);

echo "\n=== BİTTİ. SİL: rm dump-tone2.php ===\n";
