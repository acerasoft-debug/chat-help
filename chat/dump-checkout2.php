<?php
/* dump-checkout2 (READ-ONLY) — temel window.stripeCheckout fonksiyonu (price map)
   + tek-odeme (mode=payment) price ID/key. Fax over-kota odemesi icin.
   KULLANIM: pull2.php?key=...&files=dump-checkout2.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "dump-checkout2 (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php'); if($s===false) exit("okunamadi\n");
function W($s,$p,$b,$a){ return trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-$b),$b+$a))); }

echo "=== [1] TEMEL stripeCheckout (ilk tanim, price map) ===\n";
/* ilk 'stripeCheckout' tanimini bul (einzelkauf sarmasindan onceki) */
$off=0; $found=false;
while(($p=strpos($s,'stripeCheckout',$off))!==false){
  $ctx=substr($s,$p,40);
  if(strpos($ctx,'stripeCheckout=function')!==false || strpos($ctx,'stripeCheckout =function')!==false){
    /* einzelkauf sarma _s.apply iceriyor -> onu atla, price/priceId iceren gercek olani bul */
    $blk=substr($s,$p,700);
    if(strpos($blk,'price')!==false || strpos($blk,'checkout')!==false){ echo "  [".$p."] ".W($s,$p,10,650)."\n\n"; $found=true; }
  }
  $off=$p+14;
  if($found && $off>strpos($s,'stripeCheckout')+3000) break;
}
if(!$found) echo "  (price map bulunamadi — asagidaki price_ ID'lere bak)\n";

echo "\n=== [2] tum price_ ID'ler (index) ===\n";
if(preg_match_all('/price_[A-Za-z0-9]{12,}/',$s,$m)){ foreach(array_unique($m[0]) as $id) echo "  ".$id."\n"; }

echo "\n=== [3] tek-odeme (payment/einzel/1,99) baglami ===\n";
foreach(['type:\'payment\'','type:"payment"','\'payment\'','einzel','doc_price','1,99','1.99','199'] as $k){
  $off=0;$c=0; while(($p=strpos($s,$k,$off))!==false && $c<2){ echo "  '".$k."' [".$p."] …".W($s,$p,90,60)."…\n"; $off=$p+strlen($k);$c++; }
}
echo "\nBITTI.\n";
