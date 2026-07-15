<?php
/**
 * ChatHelp — dump-header-logo2 (SADECE OKUR) — favicon YOK dogrulandi, simdi
 *  uygulamanin GERCEKTEN gorunen ust bar/logo/marka metnini (JS ile render
 *  edilen kisim dahil) bulmak icin daha genis tarama. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-header-logo2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-header-logo2 — SADECE OKUR ===\n\n";
echo "boyut: ".strlen($src)." bayt\n\n";

function occ($label,$src,$needle,$pre=90,$len=220,$max=6){
  echo "[$label] '$needle':\n";
  $off=0;$n=0;
  while(($p=strpos($src,$needle,$off))!==false && $n<$max){
    $seg=substr($src,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  (yok)\n";
  echo "\n";
}

occ('1','ChatHelp<','ChatHelp<',60,180,10);
occ('2',"'ChatHelp'","'ChatHelp'",60,180,10);
occ('3','"ChatHelp"','"ChatHelp"',60,180,10);
occ('4','>ChatHelp','>ChatHelp',60,180,10);
occ('5','sb-head','sb-head');
occ('6','app-logo','app-logo');
occ('7','app-brand','app-brand');
occ('8','sidebar-logo','sidebar-logo');
occ('9','nav-brand','nav-brand');
occ('10','class="logo','class="logo');
occ('11','id="logo','id="logo');
occ('12','apple-touch-icon','apple-touch-icon');
occ('13','manifest.json','manifest.json');
occ('14','og:image','og:image');
occ('15','⚖️','⚖️',60,160,6);
occ('16','logoHTML','logoHTML');
occ('17','function chLogo','function chLogo');
occ('18','headerHTML','headerHTML');
occ('19','topHTML','topHTML');
occ('20','sb-title','sb-title');

echo "=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
