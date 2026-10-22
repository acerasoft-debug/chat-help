<?php
/** ChatHelp — dump-stripe (SADECE OKUR) — kullanicinin KART/ABO yonetimi
 *  (Stripe Customer Portal) icin sunucudaki gercek Stripe backend'i + DB semasi.
 *  KRITIK SORU: her kullanicinin stripe_customer_id'si DB'de saklaniyor mu?
 *  (Portal oturumu acmak icin cus_... gerekli.)  HASSAS ANAHTARLAR MASKELENIR.
 *  [1] sunucudaki stripe/checkout/webhook/portal dosyalari (git'te olmayabilir)
 *  [2] stripe-checkout.php ham icerik (maskeli) — customer olusturuluyor mu, cus_ DB'ye yaziliyor mu
 *  [3] webhook handler (plan atama + customer id kaydi nerede)
 *  [4] users + user_plans tablo kolonlari + tum tablolar (stripe_customer_id / free-kota icin)
 *  [5] 'customer' + 'stripe' gecen dosyalar
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D = __DIR__;

function maskS($s){
  $s=preg_replace('/(sk_(?:live|test)_[A-Za-z0-9]{4})[A-Za-z0-9]+/','$1***MASKED***',$s);
  $s=preg_replace('/(rk_(?:live|test)_[A-Za-z0-9]{4})[A-Za-z0-9]+/','$1***MASKED***',$s);
  $s=preg_replace('/(pk_(?:live|test)_[A-Za-z0-9]{4})[A-Za-z0-9]+/','$1***MASKED***',$s);
  $s=preg_replace('/(whsec_[A-Za-z0-9]{4})[A-Za-z0-9]+/','$1***MASKED***',$s);
  $s=preg_replace("/(DB_PASS[^']*',\\s*')[^']*/i",'$1***MASKED***',$s);
  $s=preg_replace("/(DB_USER[^']*',\\s*')[^']*/i",'$1***MASKED***',$s);
  return $s;
}

echo "════ [1] sunucudaki stripe/checkout/webhook dosyalari ════\n";
foreach(glob("$D/*.php") as $f){
  $b=basename($f);
  if(preg_match('/stripe|checkout|webhook|portal|billing/i',$b) && !preg_match('/^(apply-|dump-)/',$b))
    echo "  $b (".filesize($f)." bayt)\n";
}

echo "\n════ [2] checkout backend ham icerik (maskeli) ════\n";
$found=false;
foreach(['stripe-checkout.php','checkout.php','stripe.php','create-checkout.php','stripe-create.php','pay.php'] as $f){
  $p="$D/$f";
  if(is_file($p)){ $found=true; echo "\n──── $f (".filesize($p)." bayt) ────\n".maskS((string)@file_get_contents($p))."\n"; }
}
if(!$found) echo "  (bilinen checkout dosyasi bulunamadi — [1]'deki listeye bak)\n";

echo "\n════ [3] webhook handler (plan atama + customer id kaydi) ════\n";
$wfound=false;
foreach(['stripe-webhook.php','webhook.php','stripe-hook.php','hook.php'] as $f){
  $p="$D/$f";
  if(is_file($p)){ $wfound=true; echo "\n──── $f (".filesize($p)." bayt) ────\n".maskS((string)@file_get_contents($p))."\n"; }
}
if(!$wfound) echo "  (webhook dosyasi bulunamadi)\n";

echo "\n════ [4] DB semasi (stripe_customer_id + free-kota icin) ════\n";
@require_once "$D/config.php";
@require_once "$D/db.php";
if(function_exists('db')){
  try{
    $pdo=db();
    foreach(['users','user_plans'] as $t){
      echo "\n-- $t kolonlari --\n";
      try{ foreach($pdo->query("SHOW COLUMNS FROM `$t`") as $c){ echo "  {$c['Field']}  ·  {$c['Type']}\n"; } }
      catch(Throwable $e){ echo "  (tablo yok/okunamadi: ".$e->getMessage().")\n"; }
    }
    echo "\n-- tum tablolar --\n";
    try{ foreach($pdo->query("SHOW TABLES") as $r){ echo "  ".implode('',array_values($r))."\n"; } }
    catch(Throwable $e){ echo "  (SHOW TABLES: ".$e->getMessage().")\n"; }
  }catch(Throwable $e){ echo "  DB baglanti HATASI: ".$e->getMessage()."\n"; }
} else echo "  (db() yuklenemedi — config/db.php)\n";

echo "\n════ [5] 'customer' + 'stripe' gecen dosyalar (cus_ kaydi nerede) ════\n";
foreach(glob("$D/*.php") as $f){
  $b=basename($f);
  if(preg_match('/^(apply-|dump-)/',$b)) continue;
  $c=(string)@file_get_contents($f);
  $hasCus = (stripos($c,'customer')!==false);
  $hasStr = (stripos($c,'stripe')!==false);
  $hasCol = (stripos($c,'stripe_customer_id')!==false);
  if($hasCol) echo "  ★ $b: 'stripe_customer_id' GECIYOR\n";
  elseif($hasCus && $hasStr) echo "  · $b: customer+stripe\n";
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
