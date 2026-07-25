<?php
/* ChatHelp - dump-dl (READ-ONLY, maskeli) - dl.php sozlesmesi: hangi metod, GET/POST, token, Content-Type/Disposition.
 * KULLANIM: /chat/pull2.php?key=...&files=dump-dl.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ $s=preg_replace('/\b(sk|pk|rk)_(live|test)_[A-Za-z0-9]+/','$1_$2_***',$s); return $s; }
$dl=@file_get_contents(__DIR__.'/dl.php');
if($dl===false){ echo "dl.php YOK\n"; return; }
echo "== dump-dl (".strlen($dl)." B) ==\n\n";

echo "=== [1] Ilk 60 satir (yapinin basi) ===\n";
$lines=preg_split('/\r\n|\n/',$dl);
for($i=0;$i<min(60,count($lines));$i++){ echo sprintf("%3d| %s\n",$i+1,mask($lines[$i])); }

echo "\n=== [2] Anahtar isaretler ===\n";
foreach(array('$_POST','$_GET','$_REQUEST','Content-Disposition','Content-Type','attachment','inline',
              'application/pdf','text/html','token','stash','tempnam','file_put_contents','session',
              'REQUEST_METHOD','html','name','base64') as $pat){
  $c=substr_count($dl,$pat);
  if($c){ $q=strpos($dl,$pat); $ctx=str_replace(array("\r","\n"),' ',substr($dl,($q-40>0?$q-40:0),140)); echo "  '".$pat."': $c kez  ilk: ...".mask($ctx)."...\n"; }
  else echo "  '".$pat."': yok\n";
}

echo "\n=== [3] header(...) cagrilari ===\n";
if(preg_match_all('/header\s*\([^;]*\);/i',$dl,$m)){
  foreach(array_slice($m[0],0,20) as $h) echo "  ".mask($h)."\n";
}

echo "\n=== [4] fonksiyon/aksiyon dagitimi ===\n";
if(preg_match_all('/(if|elseif)\s*\([^)]*(action|method|GET|POST)[^)]*\)/i',$dl,$m2)){
  foreach(array_slice($m2[0],0,15) as $h) echo "  ".str_replace(array("\r","\n"),' ',$h)."\n";
}
echo "\n== BITTI ==\n";
