<?php
/**
 * ChatHelp — apply-stripe-backfill (CH_STRIPE_BACKFILL) — "Abo verwalten"
 *  calismiyor cunku odeme yapmis kullanicilarin stripe_customer_id'si bos.
 *  KOK NEDEN (dump-stripe-custid ile kanitli): webhook dogru yaziyor AMA
 *  mevcut planlarin cogu admin panelinden elle atandi (custid'siz INSERT).
 *  Gercek Stripe musterilerinin ID'leri Stripe tarafinda duruyor.
 *  BU BETIK: plani olan ve custid'si BOS her kullanici icin Stripe API'den
 *  e-postayla musteri arar (GET /v1/customers?email=...); bulursa
 *  users.stripe_customer_id + user_plans.stripe_customer_id doldurulur.
 *  Bulamazsa (elle verilen test plani) bos birakir — normaldir.
 *  Idempotent: dolu custid'lere DOKUNMAZ. Sirlar asla ekrana yazilmaz.
 * KULLANIM: pull2.php?key=...&files=apply-stripe-backfill.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-stripe-backfill BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;
@require_once "$D/config.php";
@require_once "$D/db.php";
if (!function_exists('db')) exit("HATA: db() yok\n");
$SK = defined('STRIPE_SECRET') ? STRIPE_SECRET : (getenv('STRIPE_SECRET') ?: '');
if (!$SK) exit("HATA: STRIPE_SECRET tanimli degil (config.php) — Stripe'a baglanamam.\n");

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
  $rows=$pdo->query("SELECT DISTINCT u.id,u.email,u.stripe_customer_id
                     FROM users u JOIN user_plans p ON p.user_id=u.id
                     WHERE (u.stripe_customer_id IS NULL OR u.stripe_customer_id='')
                     ORDER BY u.id")->fetchAll();
  if(!$rows){ echo "Tum planli kullanicilarin custid'si zaten dolu — yapilacak is yok.\n"; exit; }
  echo "custid'si bos, plani olan kullanici: ".count($rows)."\n\n";
  $ok=0;$miss=0;
  foreach($rows as $r){
    $em=$r['email'];
    $res=stripeGet('/v1/customers?limit=3&email='.rawurlencode($em),$SK);
    $cust=null;
    if($res && !empty($res['data'])){
      /* birden fazlaysa en yenisini al */
      $cust=$res['data'][0]['id'] ?? null;
    }
    if($cust){
      $pdo->prepare('UPDATE users SET stripe_customer_id=? WHERE id=?')->execute([$cust,$r['id']]);
      $pdo->prepare("UPDATE user_plans SET stripe_customer_id=? WHERE user_id=? AND (stripe_customer_id IS NULL OR stripe_customer_id='')")->execute([$cust,$r['id']]);
      echo "  ✓ #".$r['id']." ".maskEmail($em)." -> ".substr($cust,0,8)."*** (dolduruldu)\n";
      $ok++;
    } else {
      echo "  · #".$r['id']." ".maskEmail($em)." -> Stripe'ta musteri yok (elle verilen plan — normal)\n";
      $miss++;
    }
  }
  echo "\n✓ BITTI: $ok kullanici dolduruldu, $miss kullanicida Stripe kaydi yok.\n";
  echo "  Doldurulanlar icin 'Abo verwalten' (Stripe portali) artik acilir.\n";
  echo "  Stripe kaydi olmayanlar elle tanimli planlardir; yonetilecek abonelik yoktur.\n";
}catch(Throwable $e){ echo "DB/istek hatasi: ".$e->getMessage()."\n"; }
