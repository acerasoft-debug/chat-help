<?php
/** ChatHelp — dump-google-oauth: Google ile giriş (Sign in with Google) yapılandırmasını bulur (SADECE OKUR) */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
$api=@file_get_contents(__DIR__.'/api.php');
$cfg=@file_get_contents(__DIR__.'/config.php');
if($idx===false) exit("index.php yok\n");

function WIN($s,$needle,$b,$l,$lbl){echo "\n──────── $lbl ────────\n";$p=strpos($s,$needle);if($p===false){echo "(yok: $needle)\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}
function ALL($s,$needle){$out=[];$o=0;while(($p=strpos($s,$needle,$o))!==false){$out[]=$p;$o=$p+1;}return $out;}

echo "###### index.php: Google giriş yapılandırması ######\n";
foreach(['client_id','accounts.google.com','google-signin','GoogleAuth','gsi/client','g_id_signin','apis.google.com','googleusercontent.com'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}
WIN($idx,'client_id',-200,500,'client_id bağlamı 1');
WIN($idx,'accounts.google.com',-200,500,'accounts.google.com bağlamı');
WIN($idx,'g_id_signin',-100,600,'g_id_signin (Google Identity Services buton) bağlamı');
WIN($idx,'gsi/client',-150,400,'gsi/client script yükleme bağlamı');

echo "\n\n###### api.php: Google token doğrulama / callback ######\n";
if($api!==false){
  foreach(['client_id','google','GOOGLE_CLIENT','oauth2.googleapis','verifyIdToken','id_token'] as $k){
    $ps=ALL($api,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
  }
  WIN($api,'GOOGLE_CLIENT',-100,400,'GOOGLE_CLIENT_ID kullanımı (varsa)');
} else { echo "api.php okunamadı\n"; }

echo "\n\n###### config.php: Google anahtarları tanımlı mı (isim, DEĞER GÖSTERİLMEZ) ######\n";
if($cfg!==false){
  foreach(['GOOGLE_CLIENT_ID','GOOGLE_CLIENT_SECRET','GOOGLE_API_KEY'] as $k){
    $p=strpos($cfg,$k); echo "  '$k' -> ".($p===false?'TANIMLI DEĞİL':'TANIMLI (değer gizli)')."\n";
  }
} else { echo "config.php okunamadı (veya bu dizinde değil)\n"; }

echo "\n\n###### Alan adı / origin ipucu (hangi domain kayıtlı olmalı) ######\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'bilinmiyor') . "\n";
echo "Bu script'in çalıştığı gerçek origin: https://" . ($_SERVER['HTTP_HOST'] ?? 'chat-help.com') . "\n";

echo "\n=== BİTTİ. SİL: rm dump-google-oauth.php ===\n";
