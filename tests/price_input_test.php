<?php
/* vestra_price_input(): operatorun yazdigi para metni -> sayi.
   Bu fonksiyon fiyat belirliyor; yanlis olursa yanlis tutardan fatura
   kesilir ve panelde de mektupta da DOGRU gorunur. */
$src=file_get_contents(__DIR__.'/../vestra/inc/products.php');
preg_match('/^function vestra_price_input.*?^}/ms',$src,$m); eval($m[0]);

$cases = [
  // [girdi, beklenen]
  ['35',        35.00], ['35.00',   35.00], ['35,00',   35.00],
  ['35.5',      35.50], ['35,5',    35.50],
  ['35.50',     35.50], ['35,50',   35.50],   // <- eskiden 35.00 idi
  ['1.234,56', 1234.56], ['1,234.56',1234.56],
  ['1234.56',  1234.56], ['1234,56', 1234.56],
  ['1,234',     1234.0], ['1.234',    1234.0],
  [' 35 ',      35.00], ['35 EUR',   35.00], ['€35,90', 35.90],
  ['0,99',       0.99], ['0.05',      0.05],
  ['',           0.00], ['abc',       0.00],
  ['12.5',      12.50], ['12,5',     12.50],
  ['999999,99', 999999.99],
];
$bad=0;
foreach($cases as [$in,$want]){
  $got = vestra_price_input($in);
  $old = round((float)$in, 2);              // eski davranis
  $ok  = abs($got-$want) < 0.005;
  if(!$ok){ $bad++; printf("  HATA  %-12s beklenen %10.2f  gelen %10.2f\n", '"'.$in.'"', $want, $got); }
  else {
    $flag = abs($old-$want) < 0.005 ? '' : sprintf('   (eski kod: %.2f  KAYIP)', $old);
    printf("  ok    %-12s -> %10.2f%s\n", '"'.$in.'"', $got, $flag);
  }
}
printf("\n%d durum, %d yanlis\n", count($cases), $bad);
exit($bad?1:0);
