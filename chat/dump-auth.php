<?php
/** ChatHelp — dump-auth: auth.php parola sıfırlama (forgot + reset_password) akışı — SADECE OKUR */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$auth=@file_get_contents(__DIR__.'/auth.php');
if($auth===false) exit("auth.php yok/okunamadi\n");

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

echo "###### auth.php: tüm action/case satırları ######\n";
foreach(ALL($auth,"case '") as $p){ echo "  ".trim(substr($auth,$p,60))."\n"; }
foreach(ALL($auth,"=='") as $p){ $seg=substr($auth,max(0,$p-18),50); if(stripos($seg,'action')!==false) echo "  ".trim($seg)."\n"; }

echo "\n\n###### forgot_password / benzeri fonksiyon TAM ######\n";
FX($auth,'function doForgotPassword','doForgotPassword (varsa)');
FX($auth,'function forgotPassword','forgotPassword (varsa)');
FX($auth,"'forgot_password'",'forgot_password case civarı (arama)',3000);

echo "\n\n###### reset_password fonksiyon TAM ######\n";
FX($auth,'function doResetPassword','doResetPassword (varsa)');
FX($auth,"'reset_password'",'reset_password case civarı',3000);

echo "\n\n###### Link/URL inşası (e-postadaki reset linki) ######\n";
foreach(['chat-help.com','?reset=','reset=','HTTP_HOST','$host','$link','$resetLink','$resetUrl'] as $k){
  $ps=ALL($auth,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}
WIN($auth,'?reset=',-200,400,'?reset= link inşası bağlamı');
WIN($auth,'chat-help.com',-200,400,'chat-help.com domain kullanımı');

echo "\n\n###### Token üretimi + veritabanı/kayıt yöntemi ######\n";
foreach(['bin2hex','random_bytes','md5(','uniqid(','token'] as $k){
  $ps=ALL($auth,$k); echo "  '$k' -> ".count($ps)." kez\n";
}

echo "\n=== BİTTİ. SİL: rm dump-auth.php ===\n";
