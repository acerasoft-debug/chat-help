<?php
/** ChatHelp — dump-sendmail: sendMail() fonksiyonunun tanımı (HTML destekliyor mu?) — SADECE OKUR */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);

function FX($s,$name,$lbl,$cap=4000){
  echo "\n──────── $lbl ────────\n";
  $p=strpos($s,$name);
  if($p===false){ echo "(BULUNAMADI: $name)\n"; return; }
  $b=strpos($s,'{',$p); if($b===false){ echo substr($s,$p,300)."\n"; return; }
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  $body=substr($s,$p,min($i-$p,$cap));
  echo $body.(($i-$p)>$cap?"\n…(kırpıldı)":"")."\n";
}

foreach(['auth.php','api.php','send-doc.php','config.php','mail.php','mailer.php'] as $f){
  $p = __DIR__.'/'.$f;
  if(!file_exists($p)){ echo "$f -> yok\n"; continue; }
  $c = file_get_contents($p);
  $pos = strpos($c,'function sendMail');
  echo "$f -> ".($pos===false?'sendMail tanımı yok':'sendMail TANIMLI @'.$pos)."\n";
  if($pos!==false){ FX($c,'function sendMail',"sendMail tanımı ($f)"); }
}

echo "\n=== BİTTİ. SİL: rm dump-sendmail.php ===\n";
