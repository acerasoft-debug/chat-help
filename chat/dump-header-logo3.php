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

occ('1',$src,'ChatHelp<',60,180,10);
occ('2',$src,"'ChatHelp'",60,180,10);
occ('3',$src,'"ChatHelp"',60,180,10);
occ('4',$src,'>ChatHelp',60,180,10);
occ('5',$src,'sb-head');
occ('6',$src,'app-logo');
occ('7',$src,'app-brand');
occ('8',$src,'sidebar-logo');
occ('9',$src,'nav-brand');
occ('10',$src,'class="logo');
occ('11',$src,'id="logo');
occ('12',$src,'apple-touch-icon');
occ('13',$src,'manifest.json');
occ('14',$src,'og:image');
occ('15',$src,'⚖️',60,160,6);
occ('16',$src,'logoHTML');
occ('17',$src,'function chLogo');
occ('18',$src,'headerHTML');
occ('19',$src,'topHTML');
occ('20',$src,'sb-title');

echo "=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
