<?php
/**
 * ChatHelp — dump-authserver — auth.php'nin plan belirleme mantigi + olasi webhook/billing
 *  dosyalarinin listesi (OKUR). acerasoft@gmail.com plani Basic'e donuyor — sunucu
 *  tarafinda (DB/Stripe) mi, yoksa baska bir yerde mi?
 * KULLANIM: pull2.php?key=...&files=dump-authserver.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
echo "== dump-authserver ==\n\n";

echo "=== [1] Dizindeki auth/billing/webhook/stripe dosyalari ===\n";
$files=@scandir($D);
if($files){
  foreach($files as $fn){
    if(preg_match('/auth|webhook|billing|stripe|subscri/i',$fn) && is_file("$D/$fn")){
      echo "  $fn  (".filesize("$D/$fn")." B, degisim: ".date('Y-m-d H:i',filemtime("$D/$fn")).")\n";
    }
  }
}

echo "\n=== [2] auth.php: 'plan' ile ilgili TUM satirlar ===\n";
$auth=@file_get_contents("$D/auth.php");
if($auth!==false){
  echo "  (dosya boyutu: ".strlen($auth)." B)\n";
  $lines=explode("\n",$auth);
  foreach($lines as $i=>$ln){
    if(stripos($ln,'plan')!==false){ echo "  L".($i+1).": ".trim($ln)."\n"; }
  }
} else echo "  auth.php YOK/erisilemedi\n";

echo "\n=== [3] acerasoft@gmail.com ozel-durum kodu (auth.php icinde) ===\n";
if($auth!==false){
  $p=stripos($auth,'acerasoft');
  if($p!==false) echo "  ...".substr($auth,max(0,$p-200),500)."...\n";
  else echo "  'acerasoft' auth.php'de yok\n";
}

echo "\n== BITTI ==\n";
