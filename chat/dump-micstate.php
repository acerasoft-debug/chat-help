<?php
/**
 * ChatHelp - dump-micstate (READ-ONLY) - canli mikrofon kodunun tam durumu.
 *  Web STT (webkitSpeechRecognition), mic butonu handler, App/WebView yolu, markerlar.
 * KULLANIM: /chat/pull2.php?key=...&files=dump-micstate.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; return; }
echo "== dump-micstate (".number_format(strlen($src))." B) ==\n\n";

echo "=== [1] SpeechRecognition kurulum ===\n";
foreach(array('webkitSpeechRecognition','SpeechRecognition','.onresult','.onerror','.onend','interimResults','.continuous','rec.lang','.lang=') as $pat){
  $off=0;$nn=0;$hit=false;
  while(($q=strpos($src,$pat,$off))!==false && $nn<3){
    $seg=str_replace(array("\r","\n"),array('',' '),substr($src,($q-70>0?$q-70:0),210));
    echo "  [".$pat." @".$q."]: ...".$seg."...\n";
    $off=$q+strlen($pat);$nn++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}

echo "\n=== [2] mic butonu / handler ===\n";
foreach(array('micBtn','mic-btn','ch-mic','startMic','toggleMic','recognizing','__mic','micActive') as $pat){
  $off=0;$nn=0;$hit=false;
  while(($q=strpos($src,$pat,$off))!==false && $nn<2){
    $seg=str_replace(array("\r","\n"),array('',' '),substr($src,($q-60>0?$q-60:0),190));
    echo "  [".$pat." @".$q."]: ...".$seg."...\n";
    $off=$q+strlen($pat);$nn++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}

echo "\n=== [3] App/WebView tespiti ===\n";
foreach(array('AndroidBridge','ChatHelpApp','window.Android','isApp','__APP__','ch_app','WebView','standalone','ChatHelp/') as $pat){
  $off=0;$nn=0;$hit=false;
  while(($q=strpos($src,$pat,$off))!==false && $nn<2){
    $seg=str_replace(array("\r","\n"),array('',' '),substr($src,($q-45>0?$q-45:0),150));
    echo "  [".$pat." @".$q."]: ...".$seg."...\n";
    $off=$q+strlen($pat);$nn++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}

echo "\n=== [4] mikrofon markerlari ===\n";
foreach(array('CH_MIC_APPTO','CH_MIC_WVDIRECT','CH_MIC','CH_STT','CH_VOICE','CH_COMPOSER') as $m){
  echo "  ".$m.": ".(strpos($src,$m)!==false?"VAR":"yok")."\n";
}

echo "\n=== [5] getUserMedia / izin ===\n";
foreach(array('getUserMedia','mediaDevices','NotAllowed','not-allowed','no-speech','audio-capture') as $pat){
  echo "  ".$pat.": ".substr_count($src,$pat)." kez\n";
}

echo "\n=== [6] mic buton tanimi tam kesit ===\n";
$anchor=false;
foreach(array('micBtn','mic-btn','__mic','startMic') as $a){ $q=strpos($src,$a); if($q!==false){ $anchor=$q; break; } }
if($anchor!==false){
  echo "  @".$anchor.":\n";
  echo "  ".str_replace("\r",'',substr($src,($anchor-120>0?$anchor-120:0),1100))."\n";
} else { echo "  (mic buton capasi bulunamadi)\n"; }
echo "\n== BITTI ==\n";
