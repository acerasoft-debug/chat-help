<?php
/**
 * ChatHelp — dump-stripe-keyname (SADECE OKUR) — backfill icin Stripe gizli
 *  anahtarinin NEREDE ve HANGI ADLA durdugunu bulur. DEGERLERI ASLA GOSTERMEZ
 *  — sadece tanim adlari ve hangi dosyada oldugu. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-stripe-keyname.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
echo "=== dump-stripe-keyname — SADECE OKUR (deger gostermez) ===\n\n";

echo "[1] config.php'de tanimli SABIT ADLARI (sadece isim):\n";
$cfg=(string)@file_get_contents("$D/config.php");
if(preg_match_all('/define\s*\(\s*[\'"]([A-Z0-9_]+)[\'"]/',$cfg,$m)){
  foreach($m[1] as $name) echo "  - $name\n";
} else echo "  (define bulunamadi)\n";

echo "\n[2] stripe iceren dosyalarda 'sk_' anahtarinin gecis SEKLI (deger maskeli):\n";
foreach(['config.php','stripe-checkout.php','stripe-webhook.php','auth.php'] as $f){
  $s=(string)@file_get_contents("$D/$f");
  if($s==='') { echo "  $f: (okunamadi/bos)\n"; continue; }
  $found=[];
  if(preg_match_all('/(\$[A-Za-z_][A-Za-z0-9_]*|define\s*\(\s*[\'"][A-Z0-9_]+[\'"]|const\s+[A-Z0-9_]+)\s*[=,]\s*[\'"]sk_(live|test)_/',$s,$mm)){
    foreach($mm[1] as $x) $found[]=trim($x);
  }
  if(preg_match_all('/getenv\s*\(\s*[\'"]([A-Z0-9_]+)[\'"]\s*\)/',$s,$me)){
    foreach($me[1] as $x) $found[]='getenv('.$x.')';
  }
  if(preg_match_all('/(STRIPE[A-Z0-9_]*)/',$s,$mc)){
    foreach(array_unique($mc[1]) as $x) $found[]='sabit/kelime: '.$x;
  }
  $found=array_unique($found);
  echo "  $f:\n";
  if($found){ foreach($found as $x) echo "    - $x\n"; } else echo "    (sk_/STRIPE izi yok)\n";
}

echo "\n[3] stripe-checkout.php'nin anahtari nasil yukledigi (ilk 300 karakter, maskeli):\n";
$sc=(string)@file_get_contents("$D/stripe-checkout.php");
$head=substr($sc,0,700);
$head=preg_replace('/sk_(live|test)_[A-Za-z0-9]+/','sk_***MASKED***',$head);
$head=preg_replace('/whsec_[A-Za-z0-9]+/','whsec_***MASKED***',$head);
echo $head."\n";

echo "\n=== BITTI (salt-okunur, degerler maskeli). Ciktinin TAMAMINI gonder. ===\n";
