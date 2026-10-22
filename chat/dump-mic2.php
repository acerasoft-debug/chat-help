<?php
/**
 * ChatHelp — dump-mic2 — App/WebView mikrofon kablolamasini gosterir (OKUR):
 *  mikrofon butonu handler'i, isWV/isApp tespiti, CH_MIC_* katmanlari,
 *  webkitSpeechRecognition kullanimi, composer input id.
 * KULLANIM: pull2.php?key=...&files=dump-mic2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-mic2 (".strlen($src)." B) ==\n\n";

echo "=== CH_MIC_* katmanlari ===\n";
preg_match_all('/CH_MIC_[A-Z0-9_]+/',$src,$m);
foreach(array_count_values($m[0]) as $k=>$c) echo "  $k x$c\n";
echo "\n";

foreach([
  'webkitSpeechRecognition'=>4,
  'SpeechRecognition'=>2,
  'getUserMedia'=>3,
  'function isWV'=>1,
  'function isApp'=>1,
  'isWV('=>3,
  'mic'=>0,
] as $pat=>$lim){
  echo "=== '$pat' ===\n";
  $off=0;$n=0;
  while(($p=stripos($src,$pat,$off))!==false && ($lim===0? $n<3 : $n<$lim)){
    echo "  @$p: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-120),300))."...\n";
    $off=$p+strlen($pat); $n++;
  }
  if(!$n) echo "  (yok)\n";
  echo "\n";
}

/* composer input alani */
echo "=== composer input (Schreiben Sie Ihr Anliegen) ===\n";
$p=strpos($src,'Anliegen');
if($p!==false) echo "  @$p: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-300),500))."...\n";
else echo "  (yok)\n";

echo "\n== BITTI ==\n";
