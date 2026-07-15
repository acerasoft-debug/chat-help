<?php
/**
 * ChatHelp — apply-sub-fix (CH_KONTO_SUB) — "Aktif abonelik bulunamadi" kesin cozum.
 *  Kok neden: doBillingPortal customer_id'yi DB'de ariyor; manuel atanan planlarda
 *  (admin-users/yonetim) veya webhook kacinca customer_id bos -> hata.
 *  Cozum: customer_id bossa Stripe'ta KULLANICININ E-POSTASI ile musteri ara;
 *  bulunursa kullan + users'a yaz (bir daha sorunsuz). Gercekten musteri yoksa
 *  net mesaj. doBillingPortal icindeki tek satir str_replace (count-guard) + lint.
 * KULLANIM: pull2.php?key=...&files=apply-sub-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sub-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$authFile = __DIR__.'/auth.php';
$src = @file_get_contents($authFile);
if ($src===false) exit("HATA: auth.php okunamadi\n");
if (strpos($src,'CH_KONTO_SUB')!==false) exit("Zaten ekli (CH_KONTO_SUB).\n");

$old = "if (\$cid === '') respond(['error'=>'no_customer','message'=>'Kein aktives Abonnement/keine Zahlung gefunden. Bitte zuerst ein Paket buchen.'], 404);";
$new = <<<'PHPBLOCK'
if ($cid === '') {
        /* CH_KONTO_SUB — Stripe'ta e-posta ile musteri ara (webhook kacsa / manuel plan olsa bile) */
        try {
            $em = strtolower(trim((string)($u['email'] ?? '')));
            if ($em === '') { try { $er=$pdo->prepare('SELECT email FROM users WHERE id=? LIMIT 1'); $er->execute([$u['id']]); $em=strtolower(trim((string)($er->fetch()['email'] ?? ''))); } catch (Throwable $e) {} }
            if ($em !== '') {
                $lch = curl_init('https://api.stripe.com/v1/customers?limit=1&email='.urlencode($em));
                curl_setopt_array($lch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_USERPWD=>STRIPE_SECRET.':', CURLOPT_TIMEOUT=>15]);
                $lr = json_decode((string)curl_exec($lch), true) ?: [];
                curl_close($lch);
                if (!empty($lr['data'][0]['id'])) {
                    $cid = (string)$lr['data'][0]['id'];
                    try { $pdo->prepare('UPDATE users SET stripe_customer_id=? WHERE id=?')->execute([$cid, $u['id']]); } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) {}
    }
    if ($cid === '') respond(['error'=>'no_customer','message'=>'Für dieses Konto wurde bei Stripe kein Kunde gefunden. Falls Sie bereits bezahlt haben, kontaktieren Sie den Support — andernfalls bitte zuerst ein Paket buchen.'], 404);
PHPBLOCK;

$c = substr_count($src,$old);
if ($c!==1) exit("HATA: doBillingPortal no_customer anchor $c kez bulundu (1 bekleniyordu). DEGISTIRILMEDI.\n");
$src = str_replace($old,$new,$src);

$tmp = tempnam(sys_get_temp_dir(),'sf').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — auth.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($authFile.'.bak-subfix-'.date('Ymd-His'), (string)@file_get_contents($authFile));
$w=@file_put_contents($authFile,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($authFile);
if (strpos($chk,'CH_KONTO_SUB')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_KONTO_SUB diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Abonelik: customer_id bossa e-posta ile Stripe musterisi bulunur; abonelik yonetimi acilir.\n";
