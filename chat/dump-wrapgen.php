<?php
/**
 * ChatHelp — dump-wrapgen — wrapGen() etrafindaki TAM baglam: loggedPlan(), credits(),
 *  useCredit() tanimlari + wrapGen()'in cagrildigi yer + writePlan/senkron fonksiyonlarin
 *  bu sirada araya girip girmedigini gormek icin periyodik plan-yazma noktalari (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-wrapgen.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-wrapgen (".strlen($src)." B) ==\n\n";

echo "=== [1] loggedPlan() tanimi ===\n";
$p=strpos($src,'function loggedPlan');
if($p!==false) echo substr($src,$p,300)."\n\n"; else echo "  yok (baska isimde olabilir)\n";

echo "=== [2] credits() tanimi ===\n";
$p=strpos($src,'function credits(');
if($p!==false) echo substr($src,$p,300)."\n\n"; else echo "  yok\n";

echo "=== [3] useCredit() tanimi ===\n";
$p=strpos($src,'function useCredit');
if($p!==false) echo substr($src,$p,400)."\n\n"; else echo "  yok\n";

echo "=== [4] wrapGen( cagrildigi yer + genDoc'un GERCEK tanimi (_g = orijinal) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'wrapGen(',$off))!==false && $n<6){
  echo "  [$n] @$p ...".substr($src,max(0,$p-100),200)."...\n"; $off=$p+8;$n++;
}
$p=strpos($src,'function genDoc(');
if($p!==false) echo "\n  -- genDoc TAM tanim ilk 900 --\n".substr($src,$p,900)."\n\n";
else { $p=strpos($src,'genDoc=function'); if($p!==false) echo "\n  -- genDoc=function ilk 900 --\n".substr($src,$p,900)."\n\n"; }

echo "=== [5] periyodik P.plan senkron/yazma (setInterval icinde plan yazan kod) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'P.plan',$off))!==false && $n<20){
  $seg=trim(substr($src,max(0,$p-70),150));
  echo "  [$n] @$p ...".$seg."...\n"; $off=$p+6;$n++;
}
echo "\n== BITTI ==\n";
