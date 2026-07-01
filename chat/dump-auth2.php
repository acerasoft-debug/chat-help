<?php
/** ChatHelp — dump-auth2: sendResetEmail() TAM gövde + nereden çağrılıyor (aktif mi ölü kod mu) — SADECE OKUR */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$auth=@file_get_contents(__DIR__.'/auth.php');
$idx=@file_get_contents(__DIR__.'/index.php');
$api=@file_get_contents(__DIR__.'/api.php');
if($auth===false) exit("auth.php yok\n");

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
function ALL($s,$needle){$out=[];$o=0;while(($p=strpos($s,$needle,$o))!==false){$out[]=$p;$o=$p+1;}return $out;}

echo "###### sendResetEmail — TAM gövde ######\n";
FX($auth,'function sendResetEmail','sendResetEmail');

echo "\n\n###### sendResetEmail( ÇAĞRILARI (tanım hariç, gerçekten çağrılıyor mu) ######\n";
foreach(['auth.php'=>$auth,'index.php'=>$idx,'api.php'=>$api] as $fn=>$c){
  if($c===false) continue;
  $ps=ALL($c,'sendResetEmail(');
  echo "\n$fn -> ".count($ps)." geçiş\n";
  foreach($ps as $p){
    $ctx=substr($c,max(0,$p-150),260);
    if(strpos($ctx,'function sendResetEmail')!==false) echo "  [@$p] (TANIM satırı, çağrı değil)\n";
    else echo "  [@$p] ÇAĞRI:\n".$ctx."\n";
  }
}

echo "\n\n###### doForgotPassword TAM gövde (tekrar, karşılaştırma için) ######\n";
FX($auth,'function doForgotPassword','doForgotPassword');

echo "\n=== BİTTİ. SİL: rm dump-auth2.php ===\n";
