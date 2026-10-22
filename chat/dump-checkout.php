<?php
/* dump-checkout (READ-ONLY) — stripe-checkout.php tek-odeme (mode=payment) akisi:
   price_data / amount / action'lar + window.stripeCheckout imzasi. Fax over-kota
   odemesini buna baglamak icin.
   KULLANIM: pull2.php?key=...&files=dump-checkout.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "dump-checkout (READ-ONLY)\n\n";
$D=__DIR__;
$sc=@file_get_contents("$D/stripe-checkout.php");
echo "=== stripe-checkout.php ".($sc!==false?strlen($sc)." B":"(YOK)")." ===\n";
if($sc!==false){
  /* action'lar + price/amount + mode */
  foreach(['action','mode','price_data','unit_amount','line_items','currency','payment','subscription','verify','1.99','199','create'] as $k){
    if(preg_match_all('/.{0,50}'.preg_quote($k,'/').'.{0,70}/i',$sc,$m)){ echo "  ~".$k." (".count($m[0]).")\n"; $i=0; foreach($m[0] as $x){ if($i++>=2)break; echo "      ".trim(preg_replace('/\s+/',' ',$x))."\n"; } }
  }
}
echo "\n=== window.stripeCheckout tanimi (index) ===\n";
$s=@file_get_contents("$D/index.php");
$p=strpos($s,'stripeCheckout=function'); if($p===false)$p=strpos($s,'function stripeCheckout');
if($p!==false){ echo "  ".trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-20),420)))."\n"; }
echo "\n=== fax gonderim (reallySend) send_fax nerede tetikleniyor (index) ===\n";
$p=strpos($s,'send_fax');
if($p!==false){ echo "  [".$p."] ".trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-180),260)))."\n"; }
echo "\nBITTI.\n";
