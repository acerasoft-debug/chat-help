<?php
/**
 * ChatHelp — dump-checkout3 — client odeme akisi (OKUR):
 *  [A] stripeCheckout(...) TAM
 *  [B] '?payment=success' / verify donus isleyicisi
 *  [C] DOC_PRICES tanimi
 *  [D] chEmailDoc (otomatik e-posta altyapisi)
 * KULLANIM: pull2.php?key=...&files=dump-checkout3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-checkout3 (".number_format(strlen($src))." B) ==\n\n";

echo "=== [A] stripeCheckout TAM ===\n";
$p=strpos($src,'async function stripeCheckout');
if($p!==false){ $e=strpos($src,"\n  function ",$p+20); if($e===false||$e-$p>2400)$e=$p+2400; echo substr($src,$p,$e-$p)."\n\n"; }
else echo "  (yok)\n\n";

echo "=== [B] payment=success / verify donus isleyicisi ===\n";
$pats=['payment=success','?payment','verify','stripe-checkout.php',"get('payment')","payment']"];
foreach($pats as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<3){
    echo "  [".$pat." @$p]: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-90),240))."...\n";
    $off=$p+strlen($pat); $n++;
  }
  echo "";
}

echo "\n=== [C] DOC_PRICES tanimi ===\n";
$p=strpos($src,'DOC_PRICES');
if($p!==false) echo "  @$p: ".str_replace(["\n","\r"],' ',substr($src,max(0,$p-20),500))."\n\n";
else echo "  (yok)\n\n";

echo "=== [D] chEmailDoc (otomatik e-posta) ===\n";
$p=strpos($src,'function chEmailDoc');
if($p===false)$p=strpos($src,'chEmailDoc=');
if($p===false)$p=strpos($src,'chEmailDoc');
if($p!==false){ echo "  @$p: ".str_replace(["\n","\r"],' ',substr($src,max(0,$p-30),600))."\n"; }
else echo "  (yok — e-posta ucu eklenecek)\n";

echo "\n== BITTI ==\n";
