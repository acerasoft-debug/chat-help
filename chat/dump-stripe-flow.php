<?php
/** ChatHelp — dump-stripe-flow (SADECE OKUR) — 2,99 postala odemesini
 *  baglamak icin: stripeCheckout govdesi + DOC_PRICES + odeme sonrasi
 *  donusteki restore/success akisi (session_id ile). Gizli maskeli. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$s=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-stripe-flow — SADECE OKUR ===\n\n";
function occ($l,$s,$n,$pre=90,$len=420,$max=4){
  echo "[$l] '$n' (".substr_count($s,$n)."):\n"; $o=0;$c=0;
  while(($p=strpos($s,$n,$o))!==false && $c<$max){ echo "  @$p …".preg_replace('/\s+/',' ',substr($s,max(0,$p-$pre),$len))."…\n"; $o=$p+strlen($n);$c++; }
  if(!$c) echo "  (yok)\n"; echo "\n";
}
$p=strpos($s,'async function stripeCheckout');
if($p!==false) echo "[1] stripeCheckout govde (1400 kr):\n".preg_replace('/\s+/',' ',substr($s,$p,1400))."\n\n";
occ('2',$s,'DOC_PRICES',80,300,4);
occ('3',$s,'session_id',80,260,6);
occ('4',$s,'ch_pending',70,240,5);
occ('5',$s,'restoreAfterPay',60,320,3);
occ('6',$s,'stripe-checkout.php',60,220,4);
/* stripe-checkout.php backend var mi */
$sc=(string)@file_get_contents(__DIR__.'/stripe-checkout.php');
echo "[7] stripe-checkout.php: ".strlen($sc)." B; 'price'/'amount' gecis: ".(substr_count($sc,'price')+substr_count($sc,'amount'))."\n";
$q=stripos($sc,'function'); if($q!==false) echo "  ilk 400:\n  ".preg_replace('/\s+/',' ',substr($sc,0,400))."\n";
echo "\n=== BITTI. Ciktinin TAMAMINI gonder. ===\n";
