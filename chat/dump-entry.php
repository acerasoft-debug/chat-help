<?php
/**
 * ChatHelp — dump-entry — App'in HANGI dosyayi actigini bulmak icin giris
 *  noktasi envanteri: kok + /chat/ altindaki index/htaccess/manifest/sw
 *  dosyalari, boyut+tarih, yonlendirmeler, manifest start_url, sw.js kapsami.
 *  Ayrica CH_LOADLOG damgasinin hangi dosyalarda oldugunu gosterir.
 * KULLANIM: pull2.php?key=...&files=dump-entry.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
$chat=__DIR__; $root=dirname($chat);
echo "== dump-entry ==\n";
echo "chat dizini: $chat\nkok dizini : $root\n\n";

echo "=== [1] Aday giris dosyalari ===\n";
$cands=[
  $root.'/index.php', $root.'/index.html', $root.'/app.php',
  $chat.'/index.php', $chat.'/index.html', $chat.'/app.php',
];
foreach($cands as $f){
  if(is_file($f)) printf("  VAR  %-46s %9d B  %s\n", str_replace($root,'',$f), filesize($f), date('Y-m-d H:i',filemtime($f)));
  else            printf("  yok  %s\n", str_replace($root,'',$f));
}

echo "\n=== [2] CH_LOADLOG / damga hangi dosyada? ===\n";
foreach($cands as $f){
  if(!is_file($f)) continue;
  $s=(string)@file_get_contents($f);
  $has=substr_count($s,'CH_LOADLOG');
  $stamp='-'; if(preg_match('/LOAD stamp=(\d+)/',$s,$m)) $stamp=$m[1];
  printf("  %-46s CH_LOADLOG=%d  damga=%s\n", str_replace($root,'',$f), $has, $stamp);
}

echo "\n=== [3] Kok index.php ilk 600 karakter (yonlendirme var mi?) ===\n";
if(is_file($root.'/index.php')) echo substr((string)@file_get_contents($root.'/index.php'),0,600)."\n";
else echo "  kok index.php YOK\n";

echo "\n=== [4] .htaccess (kok + chat) yonlendirme satirlari ===\n";
foreach([$root.'/.htaccess',$chat.'/.htaccess'] as $h){
  echo "-- ".str_replace($root,'',$h)." --\n";
  if(!is_file($h)){ echo "  yok\n"; continue; }
  foreach(explode("\n",(string)@file_get_contents($h)) as $ln){
    if(preg_match('/Redirect|RewriteRule|RewriteCond|DirectoryIndex/i',$ln)) echo "  ".trim($ln)."\n";
  }
}

echo "\n=== [5] manifest.json start_url / scope ===\n";
foreach([$root.'/manifest.json',$chat.'/manifest.json',$chat.'/manifest.webmanifest'] as $mf){
  if(!is_file($mf)) continue;
  echo "-- ".str_replace($root,'',$mf)." --\n";
  $j=(string)@file_get_contents($mf);
  foreach(['start_url','scope','id','name'] as $k){
    if(preg_match('/"'.$k.'"\s*:\s*"([^"]*)"/',$j,$m)) echo "  $k = ".$m[1]."\n";
  }
}

echo "\n=== [6] sw.js var mi + ilk 400 karakter ===\n";
foreach([$root.'/sw.js',$chat.'/sw.js'] as $sw){
  echo "-- ".str_replace($root,'',$sw)." --\n";
  if(!is_file($sw)){ echo "  yok\n"; continue; }
  echo "  boyut=".filesize($sw)." B  tarih=".date('Y-m-d H:i',filemtime($sw))."\n";
  echo substr((string)@file_get_contents($sw),0,400)."\n";
}

echo "\n=== [7] logger dosyalari ===\n";
foreach(['chlog9k1.php','chlog9k1.txt','chlog.php','chlog.txt'] as $x){
  $f=$chat.'/'.$x;
  printf("  %-16s %s\n", $x, is_file($f) ? filesize($f)." B  ".date('H:i:s',filemtime($f)) : 'yok');
}
echo "  dizin yazilabilir mi: ".(is_writable($chat)?'EVET':'HAYIR')."\n";

echo "\n== BITTI ==\n";
