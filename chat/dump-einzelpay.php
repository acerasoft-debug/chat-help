<?php
/**
 * ChatHelp — dump-einzelpay (SADECE OKUR) — Einzeldokument (tek-belge) odeme UI + Senden kapisi.
 * Amac: "fax'i tek-belge alicisi icin ucretsiz yap + odeme sayfasinda kisaca goster"
 *       icin gereken CANLI capa noktalarini cikar.
 *  [1] showPaywall(...) govdesi — odeme karti/metni (buraya 'ucretsiz fax dahil' satiri gelecek)
 *  [2] Freischalten / Freischalten & PDF / devdoc unlock butonu + stripeCheckout('devdoc'...)
 *  [3] _tier (einfach/standard/komplex + fiyat) hesaplama
 *  [4] kredi mekanizmasi: addCredit / useCredit / credits / ch_credits
 *  [5] paid-single-doc isareti: ch_paid_ / __paidDoc / ch_pending
 *  [6] Senden(fax) kapisi tam baglam (_up4 / _tk4 / showPaywall)
 *  [7] marker durumu: CH_PAIDDOC / CH_EINZELFAX / CH_NOFREEFAX
 * KULLANIM: pull2.php?key=...&files=dump-einzelpay.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-einzelpay (".number_format(strlen($src))." B) ==\n\n";

function ctx($src,$pat,$before,$len,$max=2){
  $off=0;$n=0;$hit=false;
  while(($p=strpos($src,$pat,$off))!==false && $n<$max){
    echo "  [$pat @$p]:\n    …".str_replace(["\n","\r"],['⏎',''],substr($src,max(0,$p-$before),$len))."…\n\n";
    $off=$p+strlen($pat); $n++; $hit=true;
  }
  if(!$hit) echo "  ($pat: yok)\n\n";
}

echo "=== [1] showPaywall govdesi (odeme karti) ===\n";
$p=strpos($src,'function showPaywall');
if($p===false) $p=strpos($src,'showPaywall=function');
if($p===false) $p=strpos($src,'showPaywall');
if($p!==false){
  echo "  showPaywall @$p — ilk 1400 char:\n";
  echo "    ".str_replace(["\n"],"\n    ",substr($src,$p,1400))."\n\n";
} else echo "  (showPaywall yok)\n\n";

echo "=== [2] Freischalten / devdoc unlock butonu + stripeCheckout('devdoc') ===\n";
ctx($src,'Freischalten',120,320,3);
ctx($src,'devdoc',140,260,3);
ctx($src,'stripeCheckout(',60,220,3);

echo "=== [3] _tier (fiyat kademesi) ===\n";
ctx($src,'_tier',90,320,2);
ctx($src,'komplex',80,180,2);

echo "=== [4] kredi mekanizmasi ===\n";
ctx($src,'addCredit',60,200,2);
ctx($src,'useCredit',60,200,2);
ctx($src,'function credits',20,160,1);
ctx($src,'ch_credits',60,160,3);

echo "=== [5] paid-single-doc isareti ===\n";
ctx($src,'ch_paid',60,160,3);
ctx($src,'__paidDoc',60,180,3);
ctx($src,'ch_pending',60,160,2);

echo "=== [6] Senden(fax) kapisi tam baglam ===\n";
ctx($src,'_up4=P.plan',160,340,3);
ctx($src,'send_fax',120,220,2);
ctx($src,'ai-faxnr',120,220,2);

echo "=== [7] marker durumu ===\n";
foreach(['CH_PAIDDOC','CH_EINZELFAX','CH_NOFREEFAX','CH_PLANFAX'] as $m){
  echo "  $m: ".((strpos($src,$m)!==false)?"VAR ✓":"yok")."\n";
}
echo "\n== BITTI ==\n";
