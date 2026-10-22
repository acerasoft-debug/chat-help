<?php
/**
 * ChatHelp - dump-micflow (READ-ONLY) - mic tetikleme noktasi + App/WebView yolu + katman opener'lari.
 * KULLANIM: /chat/pull2.php?key=...&files=dump-micflow.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; return; }
echo "== dump-micflow (".number_format(strlen($src))." B) ==\n\n";

echo "=== [1] mic butonu (id=pb) ve onclick tetikleyici ===\n";
foreach(array("'pb'",'"pb"','id=pb','startVoice(','openMic','ch-mic\').style','getElementById(\'ch-mic\')') as $pat){
  $off=0;$nn=0;$hit=false;
  while(($q=strpos($src,$pat,$off))!==false && $nn<3){
    $seg=str_replace(array("\r","\n"),array('',' '),substr($src,($q-70>0?$q-70:0),200));
    echo "  [".$pat." @".$q."]: ...".$seg."...\n";
    $off=$q+strlen($pat);$nn++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}

echo "\n=== [2] CH_MIC_WVDIRECT (App/WebView dikte yolu) tam kesit ===\n";
$q=strpos($src,'CH_MIC_WVDIRECT');
if($q!==false){ echo str_replace("\r",'',substr($src,($q-40>0?$q-40:0),1600))."\n"; }
else echo "  (CH_MIC_WVDIRECT yok)\n";

echo "\n\n=== [3] CH_MIC_APPTO tam kesit ===\n";
$q=strpos($src,'CH_MIC_APPTO');
if($q!==false){ echo str_replace("\r",'',substr($src,($q-40>0?$q-40:0),1200))."\n"; }
else echo "  (CH_MIC_APPTO yok)\n";

echo "\n\n=== [4] CH_MIC_FIX opener/override tam kesit ===\n";
$q=strpos($src,'CH_MIC_FIX');
if($q!==false){ echo str_replace("\r",'',substr($src,($q-20>0?$q-20:0),1700))."\n"; }
else echo "  (CH_MIC_FIX yok)\n";

echo "\n\n=== [5] CH_MICPRO opener — nasil aciliyor? ===\n";
$q=strpos($src,'CH_MICPRO');
if($q!==false){ echo "  CH_MICPRO @".$q." — ilk 700:\n".str_replace("\r",'',substr($src,$q,700))."\n"; }
/* ch-mic overlay'i acan tetikleyici (open) */
foreach(array("display='block'","display='flex'",".style.display=") as $pat){
  $off=1990000;$nn=0;
  while(($q=strpos($src,$pat,$off))!==false && $q<2020000 && $nn<3){
    $seg=str_replace(array("\r","\n"),array('',' '),substr($src,($q-90>0?$q-90:0),180));
    echo "  [open? ".$pat." @".$q."]: ...".$seg."...\n";
    $off=$q+strlen($pat);$nn++;
  }
}

echo "\n=== [6] dil cozumleyiciler: UL / lang() / voiceLang / LOC ===\n";
foreach(array('function voiceLang','voiceLang=','var UL','let UL','const UL','UL=','function lang(','LOC=','LOC[') as $pat){
  $q=strpos($src,$pat);
  if($q!==false){ $seg=str_replace(array("\r","\n"),array('',' '),substr($src,($q-20>0?$q-20:0),160)); echo "  [".$pat." @".$q."]: ...".$seg."...\n"; }
  else echo "  (".$pat.": yok)\n";
}
echo "\n== BITTI ==\n";
