<?php
/**
 * ChatHelp — dump-brand — header marka yazisi + favicon/manifest/apple-touch + menu top
 *  anchor'larini DOGRULA (OKUR). Amac: yeni CHelp logosunu her yere koymak.
 * KULLANIM: pull2.php?key=...&files=dump-brand.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-brand (".strlen($src)." B) ==\n\n";
function ctx($src,$needle,$b,$l,$max=3){
  $off=0;$n=0;
  while(($p=stripos($src,$needle,$off))!==false && $n<$max){
    echo "  [$n] @$p ...".substr($src,max(0,$p-$b),$l)."...\n\n"; $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  '$needle' yok\n";
}

echo "=== [1] header marka yazisi (topbar brand) ===\n";
foreach(['SohbetYardım','ChatHelp','Chat<','tb-brand','topbar','app-title','brand'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
ctx($src,'tb-brand',60,260,2);
/* marka yazisi: 'Chat' 'Help' span'lari */
ctx($src,'>Help<',120,80,2);

echo "\n=== [2] favicon / apple-touch / manifest / icon linkleri ===\n";
foreach(['rel="icon"','rel="apple-touch-icon"','rel="manifest"','apple-touch','favicon','ch-icon','icon-','manifest.json','og:image'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
ctx($src,'rel="icon"',20,180,2);
ctx($src,'apple-touch-icon',20,160,2);
ctx($src,'ch-icon',30,120,3);

echo "\n=== [3] menu (sidebar) top / logo yeri ===\n";
foreach(['id="sidebar','class="side','sb-head','menu-head','id="menu','drawer'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}

echo "\n=== [4] mesajdaki 'CH' avatar ===\n";
ctx($src,'>CH<',60,60,3);

echo "\n== BITTI ==\n";
