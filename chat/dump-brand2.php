<?php
/**
 * ChatHelp — dump-brand2 — header 'app-title' marka HTML + menu head TAM yapi (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-brand2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-brand2 (".strlen($src)." B) ==\n\n";

echo "=== [1] 'app-title' civari (±120, 360) ===\n";
$off=0;$n=0;
while(($p=stripos($src,'app-title',$off))!==false && $n<4){
  echo "  [$n] @$p ...".substr($src,max(0,$p-120),420)."...\n\n"; $off=$p+9;$n++;
}

echo "=== [2] header marka HTML — 'Chat' + 'Help' span (topbar) ===\n";
/* topbar icindeki brand: 'Chat' gecen ve yakininda 'Help' olan yer */
$off=0;$n=0;
while(($p=strpos($src,'>Chat',$off))!==false && $n<6){
  $seg=substr($src,max(0,$p-140),300);
  if(stripos($seg,'Help')!==false){ echo "  [$n] @$p ...".$seg."...\n\n"; $n++; }
  $off=$p+5;
}

echo "=== [3] menu head (sb-head / class=side) civari ===\n";
$off=0;$n=0;
while(($p=stripos($src,'sb-head',$off))!==false && $n<4){
  echo "  [$n] @$p ...".substr($src,max(0,$p-80),340)."...\n\n"; $off=$p+7;$n++;
}
$p=strpos($src,'class="side'); if($p!==false) echo "  {side} @$p ...".substr($src,max(0,$p-40),260)."...\n";

echo "\n== BITTI ==\n";
