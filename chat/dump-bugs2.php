<?php
/** ChatHelp — dump-bugs2: ödeme donması + parola sıfırlama TAM detay (SADECE OKUR) */
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
function WIN($s,$needle,$b,$l,$lbl){echo "\n──────── $lbl ────────\n";$p=strpos($s,$needle);if($p===false){echo "(yok: $needle)\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}
function ALL($s,$needle){$out=[];$o=0;while(($p=strpos($s,$needle,$o))!==false){$out[]=$p;$o=$p+1;}return $out;}

echo "###### 1) stripeCheckout — TAM gövde ######\n";
FX($idx,'function stripeCheckout','stripeCheckout tam',5000);

echo "\n\n###### 2) _pendingDoc — her geçiş (tanım + kullanım) ######\n";
$ps=ALL($idx,'_pendingDoc');
echo "toplam ".count($ps)." geçiş\n";
foreach($ps as $p){ echo "\n[@$p]\n".substr($idx,max(0,$p-200),450)."\n"; }

echo "\n\n###### 3) Parola sıfırlama bölümü — GENİŞ pencere (422000-425500 arası) ######\n";
echo substr($idx, 422000, 3500) . "\n";

echo "\n\n###### 4) Olası ayrı reset/forgot dosyaları var mı ######\n";
foreach(['forgot-password.php','reset-password.php','send-reset.php','forgot.php','password-reset.php','reset.php'] as $f){
  echo "  $f -> " . (file_exists(__DIR__.'/'.$f) ? "VAR (".filesize(__DIR__.'/'.$f)." bytes)" : "yok") . "\n";
}

echo "\n\n###### 5) send-doc.php içinde parola sıfırlama e-postası gönderimi var mı ######\n";
$sd=@file_get_contents(__DIR__.'/send-doc.php');
if($sd!==false){
  foreach(['Passwort','reset','forgot'] as $k){
    $p=strpos($sd,$k); echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
  }
} else { echo "send-doc.php okunamadı/yok\n"; }

echo "\n\n###### 6) 'loading'/'Warten'/spinner gösterimi stripeCheckout etrafında ######\n";
WIN($idx,'function stripeCheckout',-50,300,'stripeCheckout öncesi bağlam (üstündeki fonksiyon neydi)');

echo "\n=== BİTTİ. SİL: rm dump-bugs2.php ===\n";
