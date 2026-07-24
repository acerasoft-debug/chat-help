<?php
/**
 * ChatHelp — dump-payflow — sonuc karti + fax rozeti + odeme-basari/kilit-acma (OKUR):
 *  [A] showResult (sablon sonuc karti) — metin/blur/pd/senden
 *  [B] chFaxQuota (Gratis-Fax rozeti) — free kullanicida 0 mi
 *  [C] stripeCheckout devdoc + odeme donusu (return/success) + ch_pending restore
 *  [D] plan tespiti (_hasPlan / P.plan) free kullanici icin
 * KULLANIM: pull2.php?key=...&files=dump-payflow.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-payflow (".number_format(strlen($src))." B) ==\n\n";

echo "=== [A] showResult tanimi (blur/pd/senden) ===\n";
$p=strpos($src,'function showResult');
if($p!==false){ $e=strpos($src,"\n  function ",$p+20); if($e===false||$e-$p>2600)$e=$p+2600; echo substr($src,$p,$e-$p)."\n\n"; }
else echo "  (yok)\n\n";

echo "=== [B] chFaxQuota tanimi ===\n";
$p=strpos($src,'chFaxQuota');
if($p!==false){ $q=strpos($src,'function',max(0,$p-60)); echo substr($src,max(0,$p-40),500)."\n\n"; }
else echo "  (yok)\n\n";

echo "=== [C] odeme donusu / ch_pending restore / success ===\n";
foreach(['ch_pending','_pendingDoc','payment_success','?paid','success','return_url','checkout.session'] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<2){
    echo "  [$pat @$p]: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-70),220))."...\n";
    $off=$p+strlen($pat); $n++;
  }
}

echo "\n=== [D] ch-doccard-locked (mevcut blur) CSS + kullanim ===\n";
foreach(['ch-doccard-locked','.ch-doccard-locked','doccard-locked'] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<3){
    echo "  @$p: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-40),200))."...\n";
    $off=$p+strlen($pat); $n++;
  }
}
echo "\n== BITTI ==\n";
