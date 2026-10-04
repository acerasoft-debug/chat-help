<?php
/**
 * ChatHelp — dump-stripe-custid (SADECE OKUR) — Abo verwalten kok nedeni:
 *  odeme yapmis kullanicilarin stripe_customer_id'si neden bos?
 *  stripe-webhook.php kaynagini inceler (SIRLAR MASKELENIR: sk_live/whsec_/
 *  price_ degerleri asla gosterilmez), plan yazma + customer id kaydetme
 *  mantigini arar; user_plans/users iliskisini kontrol eder. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-stripe-custid.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
echo "=== dump-stripe-custid — SADECE OKUR ===\n\n";

function mask($s){
  $s=preg_replace('/sk_(live|test)_[A-Za-z0-9]+/','sk_***MASKED***',$s);
  $s=preg_replace('/whsec_[A-Za-z0-9]+/','whsec_***MASKED***',$s);
  $s=preg_replace('/price_[A-Za-z0-9]+/','price_***MASKED***',$s);
  $s=preg_replace('/(define\s*\(\s*[\'"]DB_[A-Z]+[\'"]\s*,\s*[\'"]).*?([\'"])/','$1***$2',$s);
  return $s;
}

$wh=(string)@file_get_contents("$D/stripe-webhook.php");
echo "[1] stripe-webhook.php boyutu: ".strlen($wh)." bayt\n\n";

echo "[2] 'stripe_customer_id' / 'customer' gecen yerler (webhook):\n";
foreach(['stripe_customer_id','->customer','[\'customer\']','customer_email'] as $needle){
  $c=substr_count($wh,$needle);
  echo "  ".str_pad($needle,22).": $c kez\n";
}
echo "\n";

echo "[3] Webhook'un plan yazdigi yer (INSERT/UPDATE user_plans, 900 kr baglam):\n";
$off=0;$n=0;
while(($p=stripos($wh,'user_plans',$off))!==false && $n<3){
  echo "  ---\n".mask(preg_replace('/\s+/',' ',substr($wh,max(0,$p-450),900)))."\n";
  $off=$p+10;$n++;
}
if($n===0) echo "  (user_plans gecmiyor!)\n";

echo "\n[4] Webhook'un users tablosuna yazdigi yerler (UPDATE users, 600 kr):\n";
$off=0;$n=0;
while(($p=stripos($wh,'UPDATE users',$off))!==false && $n<4){
  echo "  ---\n".mask(preg_replace('/\s+/',' ',substr($wh,max(0,$p-250),600)))."\n";
  $off=$p+12;$n++;
}
if($n===0) echo "  (UPDATE users hic yok — customer id HIC kaydedilmiyor olabilir)\n";

echo "\n[5] Islenen event tipleri:\n";
$off=0;$n=0;
while(preg_match('/[\'"]((checkout|customer|invoice|payment)[a-z_.]+)[\'"]/',$wh,$m,PREG_OFFSET_CAPTURE,$off)===1 && $n<15){
  echo "  ".$m[1][0]."\n"; $off=$m[1][1]+strlen($m[1][0]); $n++;
}

echo "\n[6] DB: odeme plani olan kullanicilar + stripe_customer_id durumu:\n";
@require_once "$D/config.php"; @require_once "$D/db.php";
if(function_exists('db')){
  try{
    $pdo=db();
    $cols=array_map(function($c){return $c['Field'];},$pdo->query("SHOW COLUMNS FROM users")->fetchAll());
    $has=in_array('stripe_customer_id',$cols);
    echo "  users.stripe_customer_id sutunu: ".($has?"VAR":"YOK")."\n";
    $q=$pdo->query("SELECT u.id,u.email".($has?",u.stripe_customer_id":"").",p.plan,p.status,p.starts_at
                    FROM users u JOIN user_plans p ON p.user_id=u.id ORDER BY u.id");
    foreach($q as $r){
      $em=preg_replace('/(.{2}).+(@.+)/','$1***$2',$r['email']);
      echo "  #".$r['id']." $em plan=".$r['plan']." status=".$r['status']." start=".$r['starts_at']
         .($has?" custid=".($r['stripe_customer_id']?:'(bos)'):"")."\n";
    }
    echo "\n  user_plans sutunlari: ";
    $pc=array_map(function($c){return $c['Field'];},$pdo->query("SHOW COLUMNS FROM user_plans")->fetchAll());
    echo implode(', ',$pc)."\n";
  }catch(Throwable $e){ echo "  DB HATASI: ".$e->getMessage()."\n"; }
} else echo "  db() yuklenemedi\n";

echo "\n=== BITTI (salt-okunur, sirlar maskelendi). Ciktinin TAMAMINI gonder. ===\n";
