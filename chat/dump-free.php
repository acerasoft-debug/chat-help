<?php
/**
 * ChatHelp — dump-free — 1 ucretsiz dilekce kotasi mantigi (OKUR):
 *  api.php icinde free/quota action + IP mi email mi ile kilitliyor + nerede saklaniyor
 *  (data/ dosyalari). Email degistirilerek ayni IP'den tekrar alinabiliyor mu?
 * KULLANIM: pull2.php?key=...&files=dump-free.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$s=(string)@file_get_contents("$D/api.php");
echo "== dump-free (api.php ".number_format(strlen($s))." B) ==\n\n";
if($s===''){ echo "api.php YOK\n"; exit; }

echo "=== [A] free/quota/credit anahtar kelimeleri ===\n";
foreach(['free','quota','credit','CH_FREE','trial','gratis','kostenlos','REMOTE_ADDR','X-Forwarded','ip_','_ip','device','fingerprint'] as $pat){
  $c=substr_count($s,$pat); if($c) echo "  '".$pat."': $c\n";
}

echo "\n=== [B] free/quota ile ilgili action bloklari ===\n";
foreach(['free','quota','credit'] as $key){
  $off=0;$n=0;
  while(($p=stripos($s,$key,$off))!==false && $n<6){
    $win=substr($s,max(0,$p-60),240);
    if(preg_match('/action|REMOTE_ADDR|file_|json|===|allow|deny|used/i',$win)){
      echo "  [$key @$p]: ...".str_replace(["\n","\r"],' ',$win)."...\n";
      $n++;
    }
    $off=$p+strlen($key);
  }
  echo "\n";
}

echo "=== [C] REMOTE_ADDR / IP kullanimi (tam baglam) ===\n";
$off=0;$n=0;
while(($p=strpos($s,'REMOTE_ADDR',$off))!==false && $n<6){
  echo "  @$p: ...".str_replace(["\n","\r"],' ',substr($s,max(0,$p-120),300))."...\n\n";
  $off=$p+11; $n++;
}
if($n===0) echo "  (REMOTE_ADDR HIC kullanilmiyor — IP kilidi YOK, email degisince bypass!)\n";

echo "=== [D] data/ dizini (kota dosyalari) ===\n";
$dd="$D/data";
if(is_dir($dd)){
  foreach(array_slice((array)@scandir($dd),0,40) as $e){ if($e==='.'||$e==='..')continue;
    $p="$dd/$e"; echo "  ".$e.(is_file($p)?' ('.number_format(filesize($p)).' B)':'/')."\n"; }
} else echo "  data/ yok\n";

echo "\n== BITTI ==\n";
