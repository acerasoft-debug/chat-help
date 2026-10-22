<?php
/**
 * ChatHelp — dump-srvprof — SUNUCU tarafinda profil/adres kaydi var mi (OKUR):
 *  api.php + auth.php icinde profile/address save/get action'lari + kullanici tablosu
 *  alanlari. Gercek (cihazdan bagimsiz) kalicilik icin gereken uc var mi?
 * KULLANIM: pull2.php?key=...&files=dump-srvprof.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
function m($s){ $s=preg_replace('/(sk-[A-Za-z0-9_\-]{4})[A-Za-z0-9_\-]+/','$1***',(string)$s);
  return preg_replace('/([A-Za-z0-9_\-]{3})[A-Za-z0-9_\-]{18,}([A-Za-z0-9_\-]{3})/','$1***$2',$s); }
echo "== dump-srvprof ==\n\n";

foreach(['api.php','auth.php'] as $f){
  $s=(string)@file_get_contents("$D/$f");
  echo "=== $f (".number_format(strlen($s))." B) ===\n";
  if($s===''){ echo "  YOK\n\n"; continue; }
  /* profil/adres ile ilgili action ve alanlar */
  foreach(['profile','saveprofile','getprofile','save_profile','address','adresse','prof','f1','f3','update_user','set_profile','user_meta','JSON_'] as $pat){
    $c=substr_count(strtolower($s),strtolower($pat));
    if($c>0) echo "  '".$pat."': $c\n";
  }
  /* action listesi (action===' ... ') */
  echo "  --- action'lar ---\n";
  if(preg_match_all("/action\s*===?\s*['\"]([a-z_]+)['\"]/i",$s,$mm)){
    echo "  ".implode(', ',array_slice(array_unique($mm[1]),0,60))."\n";
  } else echo "  (action deseni bulunamadi)\n";
  echo "\n";
}

/* users tablosu semasi (auth.php CREATE TABLE) */
$au=(string)@file_get_contents("$D/auth.php");
echo "=== users tablo alanlari (CREATE TABLE) ===\n";
if(preg_match('/CREATE TABLE[^(]*users[^(]*\(([^;]+)\)/is',$au,$mt)){
  echo "  ".m(preg_replace('/\s+/',' ',$mt[1]))."\n";
} else {
  echo "  (users CREATE TABLE bulunamadi; alan referanslari:)\n";
  foreach(['name','email','plan','address','adresse','profile','prof_json','meta'] as $col){
    $c=substr_count(strtolower($au),strtolower($col)); if($c) echo "    '$col': $c\n";
  }
}
echo "\n== BITTI ==\n";
