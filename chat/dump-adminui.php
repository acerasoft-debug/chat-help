<?php
/* ChatHelp - dump-adminui (READ-ONLY, maskeli) - admin.php'ye 'Analyse' linki icin enjeksiyon capasi */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){
  $s=preg_replace('/\b(sk|pk|rk)_(live|test)_[A-Za-z0-9]+/','$1_$2_***',$s);
  $s=preg_replace('/\bwhsec_[A-Za-z0-9]+/','whsec_***',$s);
  $s=preg_replace('/([\'"]?(?:pass|password|secret|api_?key|token)[\'"]?\s*(?:=>|=|:)\s*)([\'"]).*?\2/i','$1$2***$2',$s);
  return $s;
}
$src=(string)@file_get_contents(__DIR__.'/admin.php');
if($src===''){ echo "admin.php yok\n"; return; }
echo "== admin.php (".number_format(strlen($src))." B) ==\n\n";

echo "=== [1] logged-in HTML basi (body/h1/nav cevresi) ===\n";
foreach(array('<body','<h1','<header','class="topbar"','class="nav"','<title','Abmelden','logout','Admin Panel','<h2') as $pat){
  $q=stripos($src,$pat);
  if($q!==false){ echo "  [".$pat." @".$q."]: ".mask(str_replace(array("\n","\r"),' ',substr($src,($q-30>0?$q-30:0),170)))."\n"; }
}

echo "\n=== [2] ilk <body...> tam satiri (link buraya) ===\n";
$q=stripos($src,'<body');
if($q!==false){ echo "  ".mask(str_replace(array("\r"),'',substr($src,$q,360)))."\n"; }

echo "\n=== [3] auth kapisi sonrasi HTML basliyor mu? (echo/?> gecisleri) ===\n";
foreach(array('?>','<!doctype','<!DOCTYPE','<html') as $pat){
  $q=stripos($src,$pat);
  echo "  ".$pat.": ".($q!==false?("@".$q):"yok")."\n";
}
echo "\n== BITTI ==\n";
