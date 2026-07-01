<?php
/** ChatHelp — dump-email-all: TÜM e-posta gönderim noktalarını tarar (belge, kayıt, iletişim) — SADECE OKUR */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);

function FX($s,$name,$lbl,$cap=4500){
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

$idx=@file_get_contents(__DIR__.'/index.php');
$api=@file_get_contents(__DIR__.'/api.php');
$auth=@file_get_contents(__DIR__.'/auth.php');
$sd=@file_get_contents(__DIR__.'/send-doc.php');

echo "###### 1) sendMail() gerçek tanımı + hata yönetimi ######\n";
foreach(['send-doc.php'=>$sd,'auth.php'=>$auth,'api.php'=>$api] as $fn=>$c){
  if($c===false) continue;
  $p=strpos($c,'function sendMail');
  if($p!==false){ echo "\n>>> sendMail() TANIMI $fn içinde:\n"; FX($c,'function sendMail','sendMail'); break; }
}

echo "\n\n###### 2) Belge e-postası (\"e-postama gönder\" butonu) — index.php ######\n";
foreach(['e-postama','E-Mail senden','emailDoc','sendDocEmail','function emailDocument','ch-doc-email','Per E-Mail'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,4)):'yok')."\n";
}
FX($idx,'function emailDoc','emailDoc (varsa)');
FX($idx,'function sendDocEmail','sendDocEmail (varsa)');
WIN($idx,'ch-doc-email',-100,1400,'ch-doc-email bağlamı');

echo "\n\n###### 3) send-doc.php: TAM action akışı ######\n";
if($sd!==false){
  foreach(ALL($sd,"case '") as $p){ echo "  ".trim(substr($sd,$p,50))."\n"; }
  FX($sd,'function doSendEmail','doSendEmail (varsa)');
  FX($sd,'function sendDocumentEmail','sendDocumentEmail (varsa)');
} else { echo "send-doc.php okunamadi/yok\n"; }

echo "\n\n###### 4) Kayıt doğrulama e-postası (sendVerificationEmail) ######\n";
if($auth!==false){ FX($auth,'function sendVerificationEmail','sendVerificationEmail'); }

echo "\n\n###### 5) İletişim/destek formu var mı ######\n";
foreach(['Kontakt','contact','support@','info@','function doContact'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez':'yok')."\n";
}

echo "\n\n###### 6) sendMail çağrılarının SONUCU kontrol ediliyor mu (sessiz başarısızlık riski) ######\n";
foreach(['send-doc.php'=>$sd,'auth.php'=>$auth] as $fn=>$c){
  if($c===false) continue;
  echo "\n--- $fn: sendMail(...) çağrı bağlamları ---\n";
  $ps=ALL($c,'sendMail(');
  foreach(array_slice($ps,0,6) as $p){ echo "\n[@$p]\n".substr($c,max(0,$p-30),220)."\n"; }
}

echo "\n=== BİTTİ. SİL: rm dump-email-all.php ===\n";
