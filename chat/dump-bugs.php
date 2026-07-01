<?php
/** ChatHelp — dump-bugs: parola sıfırlama linki + ödeme iptal/donma sorunlarını araştırır (SADECE OKUR) */
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

echo "###################### 1) PAROLA SIFIRLAMA ######################\n";

echo "\n### index.php içinde ilgili anahtar kelimeler ###\n";
foreach(['Passwort vergessen','forgot','reset_token','resetToken','resetPassword','reset_password','pwtoken','?reset','#reset','neues Passwort','Neues Passwort'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,4)):'yok')."\n";
}
FX($idx,'function forgotPassword','forgotPassword (varsa)');
FX($idx,'function doForgot','doForgot (varsa)');
WIN($idx,'Passwort vergessen',-100,700,'\"Passwort vergessen\" bağlamı (buton/link)');

echo "\n### index.php: sayfa yüklenince URL parametresi okunuyor mu (token/reset) ###\n";
foreach(['location.search','location.hash','URLSearchParams','getParameter','get(\'token\')','get(\'reset\')'] as $k){
  $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
}

if($api!==false){
  echo "\n### api.php içinde ilgili action/fonksiyonlar ###\n";
  foreach(['forgot','reset_password','resetPassword','reset_token','pwtoken','sendResetMail','password_reset'] as $k){
    $ps=ALL($api,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,4)):'yok')."\n";
  }
  FX($api,'function doForgotPassword','doForgotPassword (varsa)');
  FX($api,'function doResetPassword','doResetPassword (varsa)');
  FX($api,"case 'forgot",'case forgot* (varsa, kırpılmış arama)',1200);
  WIN($api,'chat-help.com/chat',-200,500,'sıfırlama e-postasındaki link inşası (varsa)');
  WIN($api,'reset',-150,400,'reset kelimesi ilk geçiş (api.php)');
} else { echo "\napi.php okunamadı\n"; }

echo "\n\n###################### 2) ÖDEME / PAYWALL DONMASI ######################\n";

echo "\n### index.php: paywall/checkout tetikleyicileri ###\n";
foreach(['stripe-checkout.php','paywall','Bezahlen','kaufen','checkout','pay-overlay','ch-loading','Zahlung','Weiter zur Zahlung'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,4)):'yok')."\n";
}
FX($idx,'function showPaywall','showPaywall (varsa)');
FX($idx,'function openPaywall','openPaywall (varsa)');
FX($idx,'function startCheckout','startCheckout (varsa)');
FX($idx,'function goCheckout','goCheckout (varsa)');
WIN($idx,'stripe-checkout.php',-400,900,'stripe-checkout.php çağrısı bağlamı');

echo "\n### İptal / geri dönüş / görünürlük değişimi dinleniyor mu (donmayı önleyecek kod) ###\n";
foreach(['visibilitychange','popstate','pageshow','focus\',','window.onfocus','cancel_url','success_url'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,4)):'yok')."\n";
}

echo "\n### stripe-checkout.php: cancel_url / success_url nereye gidiyor ###\n";
$sc=@file_get_contents(__DIR__.'/stripe-checkout.php');
if($sc!==false){
  WIN($sc,'cancel_url',-150,350,'cancel_url');
  WIN($sc,'success_url',-150,350,'success_url');
} else { echo "stripe-checkout.php okunamadı\n"; }

echo "\n=== BİTTİ. SİL: rm dump-bugs.php ===\n";
