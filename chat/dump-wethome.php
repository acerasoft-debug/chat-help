<?php
/* ChatHelp - dump-wethome (READ-ONLY) - wethome tiklama handler'i + 2,99€ varyantinin odeme yolu */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; return; }
echo "== dump-wethome (".number_format(strlen($src))." B) ==\n\n";

echo "=== [1] 'wethome' tum kullanim yerleri (etiket + handler) ===\n";
$off=0;$n=0;
while(($q=strpos($src,'wethome',$off))!==false && $n<8){
  echo "  [@".$q."]: ...".str_replace(array("\r","\n"),' ',substr($src,($q-150>0?$q-150:0),480))."...\n\n";
  $off=$q+7;$n++;
}

echo "=== [2] '2,99' iceren send-ilgili yerler ===\n";
$off=0;$n=0;
while(($q=strpos($src,'2,99',$off))!==false && $n<10){
  $ctx=substr($src,($q-140>0?$q-140:0),420);
  if(preg_match('/Haus|mail|orig|send|post|letter|Adress/i',$ctx)){
    echo "  [@".$q."]: ...".str_replace(array("\r","\n"),' ',$ctx)."...\n\n";
  }
  $off=$q+4;$n++;
}

echo "=== [3] 'nach Hause senden' handler ===\n";
$off=0;$n=0;
while(($q=strpos($src,'nach Hause',$off))!==false && $n<4){
  echo "  [@".$q."]: ...".str_replace(array("\r","\n"),' ',substr($src,($q-120>0?$q-120:0),450))."...\n\n";
  $off=$q+10;$n++;
}
echo "== BITTI ==\n";
