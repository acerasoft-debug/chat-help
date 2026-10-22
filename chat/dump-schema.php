<?php
/**
 * ChatHelp - dump-schema (READ-ONLY, SIR MASKELI) - analyse.php icin kolon adlari + $pdo erisimi.
 *  db.php: baglanti nasil sunuluyor ($pdo/fonksiyon?). auth.php: users/user_plans/user_documents
 *  kolonlari (INSERT/SELECT/UPDATE listelerinden). config.php: ADMIN_PASS var mi (deger maskeli).
 * KULLANIM: /chat/pull2.php?key=...&files=dump-schema.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);

function mask($s){
  $s=preg_replace('/\b(sk|pk|rk)_(live|test)_[A-Za-z0-9]+/','$1_$2_***',$s);
  $s=preg_replace('/\bwhsec_[A-Za-z0-9]+/','whsec_***',$s);
  $s=preg_replace('/([\'"]?(?:pass|password|passwd|pwd|secret|api_?key|token|db_?pass|dbpass|admin_?pass|user)[\'"]?\s*(?:=>|=|:)\s*)([\'"]).*?\2/i','$1$2***$2',$s);
  $s=preg_replace('/(define\s*\(\s*[\'"][^\'"]*(?:PASS|SECRET|KEY|TOKEN|USER|DB)[^\'"]*[\'"]\s*,\s*)([\'"]).*?\2/i','$1$2***$2',$s);
  /* mysql DSN icindeki pass */
  $s=preg_replace('/(dbname=[^;\'"]+)/i','$1',$s);
  return $s;
}

echo "==================== db.php (baglanti sunumu, maskeli) ====================\n";
$p=__DIR__.'/db.php'; $src=(string)@file_get_contents($p);
/* $pdo / function / new PDO satirlari */
foreach(array('new PDO','$pdo','function ','return ','global ','$GLOBALS') as $pat){
  $off=0;$n=0;
  while(($q=strpos($src,$pat,$off))!==false && $n<3){
    echo "  [".$pat."]: ".mask(trim(str_replace(array("\n","\r"),' ',substr($src,($q-4>0?$q-4:0),140))))."\n";
    $off=$q+strlen($pat);$n++;
  }
}

echo "\n==================== users / user_plans / user_documents kolonlari ====================\n";
$asrc=(string)@file_get_contents(__DIR__.'/auth.php');
$wsrc=(string)@file_get_contents(__DIR__.'/stripe-webhook.php');
$all=$asrc."\n".$wsrc;
/* INSERT INTO x (col,col,...) */
if(preg_match_all('/INSERT\s+INTO\s+`?([a-z_]+)`?\s*\(([^)]{0,400})\)/i',$all,$m)){
  for($i=0;$i<count($m[0]);$i++){ echo "  INSERT ".$m[1][$i]." -> (".mask(preg_replace('/\s+/',' ',$m[2][$i])).")\n"; }
}
/* UPDATE x SET a=?, b=? */
if(preg_match_all('/UPDATE\s+`?([a-z_]+)`?\s+SET\s+([^;]{0,220})/i',$all,$m)){
  for($i=0;$i<count($m[0]) && $i<8;$i++){ echo "  UPDATE ".$m[1][$i]." SET ".mask(preg_replace('/\s+/',' ',$m[2][$i]))."\n"; }
}
/* SELECT ... FROM x */
if(preg_match_all('/SELECT\s+(.{0,180}?)\s+FROM\s+`?([a-z_]+)`?/is',$all,$m)){
  for($i=0;$i<count($m[0]) && $i<14;$i++){ echo "  SELECT [".mask(preg_replace('/\s+/',' ',$m[1][$i]))."] FROM ".$m[2][$i]."\n"; }
}

echo "\n==================== config.php: ADMIN_PASS + DB sabitleri (maskeli) ====================\n";
$c=__DIR__.'/config.php';
if(is_file($c)){
  $csrc=(string)@file_get_contents($c);
  if(preg_match_all('/define\s*\(\s*[\'"]([A-Z_]+)[\'"]/',$csrc,$m)){
    echo "  define'lar: ".implode(', ',array_unique($m[1]))."\n";
  }
  /* ADMIN_PASS satiri maskeli */
  $q=strpos($csrc,'ADMIN_PASS');
  if($q!==false) echo "  [ADMIN_PASS]: ".mask(trim(substr($csrc,($q-10>0?$q-10:0),80)))."\n";
} else echo "  (config.php yok — ADMIN_PASS baska yerde)\n";

echo "\n==================== admin.php: require/config nasil cekiyor ====================\n";
$adm=(string)@file_get_contents(__DIR__.'/admin.php');
foreach(array('require','include','config.php','db.php','ADMIN_PASS') as $pat){
  $q=strpos($adm,$pat);
  if($q!==false) echo "  [".$pat."]: ".mask(trim(str_replace(array("\n","\r"),' ',substr($adm,($q-6>0?$q-6:0),90))))."\n";
}
echo "\n== BITTI ==\n";
