<?php
/**
 * ChatHelp - dump-datalayer (READ-ONLY, SIR MASKELI) - analiz sayfasi icin veri semasi.
 *  db.php / auth.php / admin.php / stripe-webhook.php'den SADECE yapi cikarir:
 *  tablo adlari, kolon kullanimlari, DB katmani, admin-auth mekanizmasi.
 *  TUM cikti sir-maskesinden gecer (sk_/whsec_/pk_, pass/secret/key degerleri -> ***).
 * KULLANIM: /chat/pull2.php?key=...&files=dump-datalayer.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);

function mask($s){
  $s=preg_replace('/\b(sk|pk|rk)_(live|test)_[A-Za-z0-9]+/','$1_$2_***',$s);
  $s=preg_replace('/\bwhsec_[A-Za-z0-9]+/','whsec_***',$s);
  $s=preg_replace('/([\'"]?(?:pass|password|passwd|pwd|secret|api_?key|token|db_?pass|admin_?pass)[\'"]?\s*(?:=>|=|:)\s*)([\'"]).*?\2/i','$1$2***$2',$s);
  $s=preg_replace('/(define\s*\(\s*[\'"][^\'"]*(?:PASS|SECRET|KEY|TOKEN)[^\'"]*[\'"]\s*,\s*)([\'"]).*?\2/i','$1$2***$2',$s);
  return $s;
}
function tables($src){
  $t=array();
  if(preg_match_all('/\b(?:FROM|INTO|UPDATE|JOIN)\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?/i',$src,$m)){
    foreach($m[1] as $x){ $x=strtolower($x); if(!isset($t[$x])) $t[$x]=0; $t[$x]++; }
  }
  arsort($t); return $t;
}
function cols($src){
  $c=array();
  if(preg_match_all('/`([a-z_][a-z0-9_]*)`/i',$src,$m)){
    foreach($m[1] as $x){ $x=strtolower($x); if(!isset($c[$x])) $c[$x]=0; $c[$x]++; }
  }
  arsort($c); return $c;
}

$files=array('db.php','auth.php','admin.php','stripe-webhook.php','stripe-checkout.php','freequota.php');
foreach($files as $f){
  $p=__DIR__.'/'.$f;
  echo "==================== $f ====================\n";
  if(!is_file($p)){ echo "  (yok)\n\n"; continue; }
  $src=(string)@file_get_contents($p);
  echo "  boyut: ".number_format(strlen($src))." B\n";
  echo "  DB katmani: ".(strpos($src,'PDO')!==false?'PDO ':'').(strpos($src,'mysqli')!==false?'mysqli ':'').(strpos($src,'new mysqli')!==false?'(new mysqli) ':'')."\n";
  $t=tables($src);
  if($t){ echo "  Tablolar: "; $i=0; foreach($t as $n=>$cnt){ echo $n."(".$cnt.") "; if(++$i>=12) break; } echo "\n"; }
  $c=cols($src);
  if($c){ echo "  Kolonlar(backtick): "; $i=0; foreach($c as $n=>$cnt){ echo $n." "; if(++$i>=30) break; } echo "\n"; }
  /* CREATE TABLE varsa sema */
  if(preg_match_all('/CREATE TABLE[^;]+;/i',$src,$mm)){
    foreach(array_slice($mm[0],0,4) as $ct){ echo "  --- CREATE ---\n  ".mask(str_replace("\n","\n  ",substr($ct,0,600)))."\n"; }
  }
  echo "\n";
}

/* admin auth mekanizmasi (deger maskeli) */
echo "==================== admin-auth (maskeli) ====================\n";
$ap=__DIR__.'/admin.php';
if(is_file($ap)){
  $src=(string)@file_get_contents($ap);
  foreach(array('password','ADMIN','hash_equals','$_POST','session','login','auth') as $pat){
    $q=stripos($src,$pat);
    if($q!==false){ echo "  [".$pat."]: ".mask(str_replace(array("\n","\r"),' ',substr($src,($q-20>0?$q-20:0),140)))."\n"; }
  }
}
echo "\n== BITTI ==\n";
