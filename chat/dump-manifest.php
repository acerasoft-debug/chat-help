<?php
/**
 * ChatHelp — dump-manifest — mevcut manifest.json + apple PWA head etiketlerini DOGRULA (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-manifest.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
echo "== dump-manifest ==\n\n";

echo "=== [1] manifest.json (mevcut) ===\n";
$mf=@file_get_contents("$D/manifest.json");
if($mf===false) echo "  manifest.json YOK\n"; else echo $mf."\n";

echo "\n=== [2] index.php apple/PWA head etiketleri ===\n";
$src=@file_get_contents("$D/index.php");
if($src!==false){
  foreach(['apple-mobile-web-app-capable','apple-mobile-web-app-status-bar','apple-mobile-web-app-title','rel="manifest"','theme-color','apple-touch-icon','rel="apple-touch-startup'] as $kw){
    $p=stripos($src,$kw);
    if($p!==false) echo "  [$kw] ...".substr($src,max(0,$p-30),160)."...\n";
    else echo "  [$kw] YOK\n";
  }
}
echo "\n=== [3] mevcut ikon dosyalari ===\n";
foreach(['icon-16.png','icon-32.png','icon-64.png','icon-180.png','icon-192.png','icon-512.png','ch-logo-512.png','apple-touch-icon.png'] as $n){
  $pp="$D/$n"; echo "  $n : ".(is_file($pp)?(filesize($pp)." B"):"YOK")."\n";
}
echo "\n=== [4] sw.js ilk satir ===\n";
$sw=@file_get_contents("$D/sw.js"); if($sw!==false) echo "  ".substr($sw,0,120)."\n"; else echo "  sw.js YOK\n";
echo "\n== BITTI ==\n";
