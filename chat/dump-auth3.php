<?php
/** ChatHelp — dump-auth3: showForgotPassword() TAM gövde (hangi action gönderiliyor) + 8043 civarı fonksiyon adı + TÜM routing — SADECE OKUR */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
$auth=@file_get_contents(__DIR__.'/auth.php');
if($idx===false || $auth===false) exit("dosya okunamadi\n");

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
function ALL($s,$needle){$out=[];$o=0;while(($p=strpos($s,$needle,$o))!==false){$out[]=$p;$o=$p+1;}return $out;}

echo "###### 1) showForgotPassword — TAM gövde (index.php, hangi action POST ediliyor) ######\n";
FX($idx,'function showForgotPassword','showForgotPassword');

echo "\n\n###### 2) auth.php: 8043 civarındaki fonksiyonun TAM gövdesi (isim + UPDATE users reset_token) ######\n";
echo substr($auth, 7400, 1400) . "\n";

echo "\n\n###### 3) auth.php: TÜM routing/switch/match blokları (kaç tane var, hepsi) ######\n";
foreach(ALL($auth,"=> do") as $p){ echo "  ".trim(substr($auth,max(0,$p-40),90))."\n"; }
foreach(ALL($auth,"case '") as $p){ echo "  case: ".trim(substr($auth,$p,50))."\n"; }
foreach(ALL($auth,"match (") as $p){ echo "  match( @$p\n"; }
foreach(ALL($auth,"match(") as $p){ echo "  match( @$p\n"; }
foreach(ALL($auth,"\$action") as $p){ echo "  \$action @$p: ".trim(substr($auth,$p,60))."\n"; }

echo "\n=== BİTTİ. SİL: rm dump-auth3.php ===\n";
