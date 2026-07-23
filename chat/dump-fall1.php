<?php
/**
 * ChatHelp — dump-fall1 — [1] chat [[DOC]] kartinin PDF butonu handler'i
 *  [2] Meine Fälle modulu (acilis/render fonksiyonlari + interval'lar)
 *  [3] Meine Themen render dongusu (titreme kaynagi) (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-fall1.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-fall1 (".strlen($src)." B) ==\n\n";

echo "=== [1] chat doc karti + PDF butonu ===\n";
foreach(['ch-doccard','doccard'] as $pat){
  echo "  '$pat' sayisi: ".substr_count($src,$pat)."\n";
}
$off=0;$n=0;
while(($p=strpos($src,'ch-doccard',$off))!==false && $n<4){
  $seg=substr($src,max(0,$p-100),600);
  if(stripos($seg,'pdf')!==false || stripos($seg,'button')!==false){ echo "  [#$n @$p] ...".$seg."...\n\n"; }
  $off=$p+10;$n++;
}

echo "=== [2] Meine Fälle modulu ===\n";
foreach(['function openFall','function renderFalls','function falls(){','fall-body','fall-list','newFall','addFall'] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<2){
    echo "  [$pat @$p] ...".substr($src,max(0,$p-60),500)."...\n\n"; $off=$p+strlen($pat);$n++;
  }
}

echo "=== [3] Meine Themen render (ch_topics) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'ch_topics',$off))!==false && $n<8){
  $seg=substr($src,max(0,$p-120),320);
  if(strpos($seg,'setInterval')!==false || strpos($seg,'render')!==false || strpos($seg,'innerHTML')!==false){
    echo "  [#$n @$p] ...".$seg."...\n\n";
  }
  $off=$p+9;$n++;
}
echo "  'ch_topics' toplam: ".substr_count($src,'ch_topics')."\n";

echo "\n=== [4] Fälle/Themen paneli icindeki setInterval'lar ===\n";
/* fall/themen bolgelerinde interval taramasi: FK='ch_falls_v1' bloklari cevresi */
$off=0;$n=0;
while(($p=strpos($src,"ch_falls_v1",$off))!==false && $n<4){
  $win=substr($src,$p,4000);
  $iv=substr_count($win,'setInterval');
  echo "  [ch_falls_v1 #$n @$p] yakin 4KB icinde setInterval: $iv\n";
  if($iv>0){ $ip=strpos($win,'setInterval'); echo "    ...".substr($win,max(0,$ip-150),350)."...\n"; }
  $off=$p+10;$n++;
}

echo "\n== BITTI ==\n";
