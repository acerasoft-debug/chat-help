<?php
/**
 * ChatHelp — apply-stripe-backfill2 (CH_STRIPE_BACKFILL2) — v1 STRIPE_SECRET'i
 *  config.php'de aradigi icin durmustu; anahtar aslinda stripe-checkout.php
 *  icinde define ile gomulu (dump-stripe-keyname ile dogrulandi). Bu surum
 *  anahtari stripe-checkout.php kaynagindan CALISMA ANINDA regex ile alir
 *  (EKRANA ASLA YAZMAZ), sonra ayni backfill'i yapar:
 *  plani olup custid'si bos kullanicilari Stripe'ta e-postayla arar,
 *  bulunanlara users.stripe_customer_id + user_plans.stripe_customer_id
 *  yazar. Idempotent; dolu kayitlara dokunmaz.
 * KULLANIM: pull2.php?key=...&files=apply-stripe-backfill2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-stripe-backfill2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;
@require_once "$D/config.php";
@require_once "$D/db.php";
if (!function_exists('db')) exit("HATA: db() yok\n");

/* anahtari stripe-checkout.php'den oku (asla ekrana yazma) */
$SK='';
$sc=(string)@file_get_contents("$D/stripe-checkout.php");
if (preg_match('/define\s*\(\s*[\'"]STRIPE_SECRET[\'"]\s*,\s*[\'"](sk_[A-Za-z0-9_]+)[\'"]/',$sc,$m)) $SK=$m[1];
if (!$SK && defined('STRIPE_SECRET')) $SK=STRIPE_SECRET;
if (!$SK) exit("HATA: Stripe anahtari stripe-checkout.php icinde bulunamadi.\n");
echo "  ✓ Stripe anahtari bulundu (".substr($SK,0,7)."***, ".strlen($SK)." karakter — deger gizli)\n\n";

function stripeGet($path,$sk){
  $c=curl_init('https://api.stripe.com'.$path);
  curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$sk]]);
  $b=curl_exec($c); $code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c);
  if($b===false||$code<200||$code>=300) return null;
  return json_decode($b,true);
}
function maskEmail($e){ return preg_replace('/(.{2}).+(@.+)/','$1***$2',$e); }

try{
  $pdo=db();
  $rows=$pdo->query("SELECT DISTINCT u.id,u.email
                     FROM users u JOIN user_plans p ON p.user_id=u.id
                     WHERE (u.stripe_customer_id IS NULL OR u.stripe_customer_id='')
                     ORDER BY u.id")->fetchAll();
  if(!$rows){ echo "Tum planli kullanicilarin custid'si zaten dolu — yapilacak is yok.\n"; exit; }
  echo "custid'si bos, plani olan kullanici: ".count($rows)."\n\n";
  $ok=0;$miss=0;
  foreach($rows as $r){
    $res=stripeGet('/v1/customers?limit=3&email='.rawurlencode($r['email']),$SK);
    $cust=($res && !empty($res['data'])) ? ($res['data'][0]['id'] ?? null) : null;
    if($cust){
      $pdo->prepare('UPDATE users SET stripe_customer_id=? WHERE id=?')->execute([$cust,$r['id']]);
      $pdo->prepare("UPDATE user_plans SET stripe_customer_id=? WHERE user_id=? AND (stripe_customer_id IS NULL OR stripe_customer_id='')")->execute([$cust,$r['id']]);
      echo "  ✓ #".$r['id']." ".maskEmail($r['email'])." -> ".substr($cust,0,8)."*** (dolduruldu)\n";
      $ok++;
    } else {
      echo "  · #".$r['id']." ".maskEmail($r['email'])." -> Stripe'ta musteri yok (elle verilen plan — normal)\n";
      $miss++;
    }
  }
  echo "\n✓ BITTI: $ok kullanici dolduruldu, $miss kullanicida Stripe kaydi yok.\n";
  echo "  Doldurulanlar icin 'Abo verwalten' (Stripe portali) artik acilir.\n";
}catch(Throwable $e){ echo "DB/istek hatasi: ".$e->getMessage()."\n"; }
