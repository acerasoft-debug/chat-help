<?php
/**
 * ChatHelp — apply-billing-portal (CH_BILLING_PORTAL)
 *  Kullanicinin KENDI kartini/abonesini yonetmesi icin Stripe Customer Portal.
 *  auth.php'ye SADECE EKLEME yapar (mevcut mantik degismez):
 *   [1] db.php require'inden sonra STRIPE_SECRET tanimi
 *       (gercek deger stripe-checkout.php'den SUNUCUDA cikarilir — git'e/loga gitmez)
 *   [2] match() router'ina 'billing_portal' => doBillingPortal() satiri
 *   [3] dosya sonuna doBillingPortal() fonksiyonu (requireAuth ile JWT korumali)
 *  Frontend butonu ayri yamada. lint+yedek+dogrulama var.
 * KULLANIM: pull2.php?key=...&files=apply-billing-portal.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-billing-portal BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$authFile = __DIR__.'/auth.php';
$coFile   = __DIR__.'/stripe-checkout.php';
$src = @file_get_contents($authFile);
if ($src===false) exit("HATA: auth.php okunamadi\n");
if (strpos($src,'CH_BILLING_PORTAL')!==false) exit("Zaten ekli (CH_BILLING_PORTAL).\n");

/* ── gercek STRIPE_SECRET'i sunucudaki stripe-checkout.php'den cikar ── */
$co = @file_get_contents($coFile);
if ($co===false) exit("HATA: stripe-checkout.php okunamadi (secret kaynagi yok)\n");
if (!preg_match("/define\\(\\s*'STRIPE_SECRET'\\s*,\\s*'([^']+)'\\s*\\)/", $co, $sm))
    exit("HATA: stripe-checkout.php icinde STRIPE_SECRET tanimi bulunamadi\n");
$secret = $sm[1];
if (strpos($secret,'sk_')!==0) exit("HATA: cikarilan secret 'sk_' ile baslamiyor — iptal\n");
echo "  ✓ STRIPE_SECRET stripe-checkout.php'den cikarildi (sk_...".substr($secret,-4).", ".strlen($secret)." bayt)\n";

$done=0;

/* [1] db.php require'inden sonra STRIPE_SECRET tanimi */
$anchor1 = "require_once __DIR__ . '/db.php';";
$pos1 = strpos($src,$anchor1);
if ($pos1!==false) {
    $ins = $anchor1."\nif(!defined('STRIPE_SECRET')) define('STRIPE_SECRET', '".$secret."'); /*CH_BILLING_PORTAL*/";
    $src = substr($src,0,$pos1).$ins.substr($src,$pos1+strlen($anchor1));
    echo "  ✓ [1] STRIPE_SECRET tanimi eklendi\n"; $done++;
} else { exit("HATA: [1] db.php require anchor bulunamadi — iptal\n"); }

/* [2] router'a billing_portal case */
$cnt=0;
$src = preg_replace(
    "/('me'\\s*=>\\s*doMe\\(\\)\\s*,)/",
    "$1\n    'billing_portal' => doBillingPortal(),",
    $src, 1, $cnt
);
if ($cnt===1) { echo "  ✓ [2] router'a 'billing_portal' eklendi\n"; $done++; }
else { exit("HATA: [2] doMe() router anchor bulunamadi ($cnt) — iptal\n"); }

/* [3] doBillingPortal() fonksiyonunu ekle (kapanis ?> varsa oncesine) */
$fn = <<<'PHPFN'

/* CH_BILLING_PORTAL — Stripe Customer Portal: Kunde verwaltet Karte/Abo selbst */
function doBillingPortal(): void {
    $u = requireAuth();
    try { $pdo = db(); } catch (Throwable $e) { respond(['error'=>'db','message'=>'DB-Fehler'], 500); }
    $cid = '';
    try {
        $st = $pdo->prepare('SELECT stripe_customer_id FROM users WHERE id=? LIMIT 1');
        $st->execute([$u['id']]);
        $cid = (string)($st->fetch()['stripe_customer_id'] ?? '');
        if ($cid === '') {
            $st = $pdo->prepare('SELECT stripe_customer_id FROM user_plans WHERE user_id=? AND stripe_customer_id IS NOT NULL AND stripe_customer_id<>"" ORDER BY id DESC LIMIT 1');
            $st->execute([$u['id']]);
            $cid = (string)($st->fetch()['stripe_customer_id'] ?? '');
        }
    } catch (Throwable $e) { respond(['error'=>'db','message'=>'DB-Fehler'], 500); }
    if ($cid === '') respond(['error'=>'no_customer','message'=>'Kein aktives Abonnement/keine Zahlung gefunden. Bitte zuerst ein Paket buchen.'], 404);
    $host   = $_SERVER['HTTP_HOST'] ?? 'chat-help.de';
    $return = 'https://'.$host.'/chat/';
    $ch = curl_init('https://api.stripe.com/v1/billing_portal/sessions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['customer'=>$cid, 'return_url'=>$return]),
        CURLOPT_USERPWD        => STRIPE_SECRET.':',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $resp = json_decode($raw, true) ?: [];
    if (isset($resp['url'])) respond(['url'=>$resp['url']]);
    respond(['error'=>'stripe','message'=>$resp['error']['message'] ?? 'Stripe-Portal Fehler'], 502);
}
PHPFN;

$closeTag = strrpos($src, '?>');
if ($closeTag !== false && trim(substr($src,$closeTag+2))==='') {
    $src = substr($src,0,$closeTag).$fn."\n?>".substr($src,$closeTag+2);
} else {
    $src = rtrim($src)."\n".$fn."\n";
}
echo "  ✓ [3] doBillingPortal() fonksiyonu eklendi\n"; $done++;

if ($done<3) exit("\nHATA: beklenen 3 ekleme tamamlanmadi ($done) — auth.php DEGISTIRILMEDI.\n");

/* iz (yorum) */
$src .= "\n/* CH_BILLING_PORTAL applied */\n";

/* lint on temp */
$tmp = tempnam(sys_get_temp_dir(),'bp').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — auth.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

$bk=@file_put_contents($authFile.'.bak-billingportal-'.date('Ymd-His'), @file_get_contents($authFile));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($authFile,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($authFile);
if (strpos($chk,'CH_BILLING_PORTAL')===false || strpos($chk,'function doBillingPortal')===false) {
    echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit;
}
echo "  ✓ DOGRULAMA: CH_BILLING_PORTAL + doBillingPortal() diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Backend hazir. Endpoint: POST auth.php?action=billing_portal (Authorization: Bearer <token>)\n";
echo "   Donus: {url} → Stripe Customer Portal. Frontend butonu ayri yamada gelecek.\n";
echo "   NOT: Stripe Dashboard > Settings > Billing > Customer portal AKTIF olmali (bir kez).\n";
