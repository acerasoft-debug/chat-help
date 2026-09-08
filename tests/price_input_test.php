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

/* ── Kablo: fiyat YAZAN yollar bu cozumleyiciden geciyor mu ────────────────
   Fonksiyonun dogru olmasi yetmiyor; cagrilmadigi bir yerde kural yok demek.
   set-prices.yml katalogun fiyatini DOGRUDAN yaziyor ve girdisini operator
   elle dolduruyor -- yani virgullu yazimin ilk gelecegi yer orasi. 8 Eyl
   2026'ya kadar cagirmiyordu: "79,90" is_numeric'e takilip "gecersiz fiyat"
   diye REDDEDILIYORDU (sessiz kayip degil, ama kullaniciya kendi dogru
   yazdigi rakami yanlis diye geri veren bir arac). */
$sp = (string)@file_get_contents(__DIR__.'/../.github/workflows/set-prices.yml');
$wire = [
  'set-prices: para alanlari icin cozumleyici var' => str_contains($sp, '$money = function (string $v): string {'),
  'set-prices: vestra_price_input cagriliyor'      => str_contains($sp, 'vestra_price_input($v)'),
  'set-prices: price gecirildi'                    => str_contains($sp, '$price   = $money($price);'),
  'set-prices: list_price gecirildi'               => str_contains($sp, '$listP   = $money($listP);'),
  'set-prices: discount_pct gecirildi'             => str_contains($sp, '$discPct = $money($discPct);'),
  /* moq/step ctype_digit ile dogrulaniyor: iki ondalik eklemek gecerli bir
     MOQ'yu gecersiz yapardi, o yuzden onlar BILEREK gecmiyor. */
  'set-prices: moq cozumleyiciden GECMIYOR'        => !str_contains($sp, '$moq   = $money($moq);'),
  'set-prices: step cozumleyiciden GECMIYOR'       => !str_contains($sp, '$step  = $money($step);'),
];
echo "\n== Kablo denetimi ==\n";
foreach ($wire as $n => $ok) { if ($ok) printf("  ok    %s\n", $n); else { $bad++; printf("  HATA  %s\n", $n); } }

exit($bad?1:0);
