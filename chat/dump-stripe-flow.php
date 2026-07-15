<?php
/**
 * ChatHelp — dump-stripe-flow (SADECE OKUR) — abonelik/customer_id akisi.
 *  "Aktif abonelik bulunamadi" kok neden: odeme sonrasi stripe_customer_id
 *  DB'ye yaziliyor mu? Bul: stripe-checkout.php, webhook dosyalari, ve TUM
 *  php dosyalarinda 'stripe_customer_id' YAZAN (UPDATE/INSERT) yerler + user_plans.
 *  SIR (sk_/whsec_/price_) maskeli. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-stripe-flow.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
echo "=== dump-stripe-flow — SADECE OKUR ===\n\n";

/* stripe/webhook dosyalari */
echo "[1] Stripe/webhook dosyalari:\n";
foreach(glob($D.'/*.php') as $f){
  $b=basename($f);
  if(preg_match('/stripe|webhook|checkout|abo|billing|plan/i',$b)) echo "  ".str_pad($b,32)." ".filesize($f)." bayt\n";
}

function mask($s){ return preg_replace(array('/sk_live_[A-Za-z0-9]+/','/sk_test_[A-Za-z0-9]+/','/whsec_[A-Za-z0-9]+/','/price_[A-Za-z0-9]+/'),array('sk_live_***','sk_test_***','whsec_***','price_***'),$s); }

/* stripe-checkout.php tam (maskeli) */
$c=(string)@file_get_contents($D.'/stripe-checkout.php');
echo "\n[2] stripe-checkout.php (".strlen($c)." bayt, maskeli):\n";
echo mask($c)."\n";

/* webhook varsa */
foreach(['stripe-webhook.php','webhook.php','stripe-hook.php'] as $wf){
  $w=(string)@file_get_contents($D.'/'.$wf);
  if($w!==''){ echo "\n[3] $wf (".strlen($w)." bayt, maskeli, ilk 3000):\n".substr(mask($w),0,3000)."\n"; }
}

/* TUM php'lerde stripe_customer_id YAZAN yerler */
echo "\n[4] 'stripe_customer_id' YAZAN (UPDATE/INSERT) yerler — tum dosyalar:\n";
foreach(glob($D.'/*.php') as $f){
  $s=(string)@file_get_contents($f);
  if(strpos($s,'stripe_customer_id')===false) continue;
  $lines=preg_split('/\n/',$s);
  foreach($lines as $i=>$ln){
    if(preg_match('/stripe_customer_id/',$ln) && preg_match('/UPDATE|INSERT|SET |customer_id\s*=/i',$ln)){
      $t=trim($ln); if(strlen($t)>180)$t=substr($t,0,180).'…';
      echo "  ".basename($f).":".($i+1)." ".mask($t)."\n";
    }
  }
}

/* user_plans yazan yerler (plan atama) */
echo "\n[5] user_plans'e plan YAZAN yerler:\n";
foreach(glob($D.'/*.php') as $f){
  $s=(string)@file_get_contents($f);
  $lines=preg_split('/\n/',$s);
  foreach($lines as $i=>$ln){
    if(preg_match('/user_plans/',$ln) && preg_match('/INSERT|UPDATE|REPLACE/i',$ln)){
      $t=trim($ln); if(strlen($t)>180)$t=substr($t,0,180).'…';
      echo "  ".basename($f).":".($i+1)." ".mask($t)."\n";
    }
  }
}

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
