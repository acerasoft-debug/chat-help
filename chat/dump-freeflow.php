<?php
/**
 * ChatHelp — dump-freeflow — istemci ucretsiz-belge karar akisi (OKUR):
 *  CH_FREE_AVAIL / showPaywall / free-vs-odeme karari / stripeCheckout('devdoc').
 *  IP-bazli sunucu kotasini dogru noktaya baglamak icin.
 * KULLANIM: pull2.php?key=...&files=dump-freeflow.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-freeflow (".number_format(strlen($src))." B) ==\n\n";

foreach(['CH_FREE_AVAIL','function showPaywall','showPaywall(','function chFree','freeUsed','freeLeft','ch_free','stripeCheckout','devdoc','canFree','useFree','ch_credits'] as $pat){
  $off=0;$n=0;
  echo "=== '$pat' ===\n";
  while(($p=strpos($src,$pat,$off))!==false && $n<4){
    echo "  @$p: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-90),240))."...\n";
    $off=$p+strlen($pat); $n++;
  }
  if($n===0) echo "  (yok)\n";
  echo "\n";
}

/* free karar bloklarinin genis baglami: showPaywall tanimi */
echo "=== showPaywall tanimi (genis) ===\n";
$p=strpos($src,'function showPaywall');
if($p!==false) echo substr($src,$p,700)."\n";
else { $p=strpos($src,'showPaywall='); if($p!==false) echo substr($src,max(0,$p-40),700)."\n"; }

echo "\n== BITTI ==\n";
