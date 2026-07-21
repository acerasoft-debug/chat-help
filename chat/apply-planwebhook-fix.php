<?php
/**
 * ChatHelp — apply-planwebhook-fix (CH_PLANSAFE) — KRITIK billing bug: stripe-webhook.php
 *  'checkout.session.completed' olayinda metadata.plan YOKSA 'basic' VARSAYIYORDU.
 *  Fax/Einschreiben/belge gibi PLAN-DISI tek-seferlik odemeler de bu olayi tetikler
 *  (mode==='payment') ve metadata'larinda 'plan' YOKTUR -> gercek Elite/Pro kaydi
 *  YANLISLIKLA 'basic'e DUSURULUYORDU (acerasoft@gmail.com'da gozlemlendi).
 *  COZUM: plan metadata'si YOKSA/gecersizse user_plans HIC DOKUNULMAZ (sadece
 *  stripe_customer_id guncellenir, plan asla varsayilmaz). Gercek plan-satin-alma
 *  checkout'lari (metadata.plan dogru set edilmis) davranisi AYNEN korunur.
 *  1 sayac-korumali str_replace, stripe-webhook.php. Eslesmezse HICBIR sey degismez.
 * KULLANIM: pull2.php?key=...&files=apply-planwebhook-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-planwebhook-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/stripe-webhook.php';
$src=@file_get_contents($file);
if($src===false) exit("stripe-webhook.php okunamadi\n");
if(strpos($src,'CH_PLANSAFE')!==false) exit("Zaten ekli (CH_PLANSAFE).\n");

$old = <<<'OLD'
        case 'checkout.session.completed':
            $email  = $obj['customer_details']['email'] ?? $obj['customer_email'] ?? '';
            $mode   = $obj['mode'] ?? '';
            $plan   = $obj['metadata']['plan'] ?? 'basic';
            $custId = $obj['customer'] ?? '';

            if($email && ($mode === 'subscription' || $mode === 'payment')){
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if($user){
                    $pdo->prepare('INSERT INTO user_plans (user_id,plan,stripe_customer_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE plan=?, stripe_customer_id=?')
                        ->execute([$user['id'], $plan, $custId, $plan, $custId]);
                    // Also update stripe_customer_id in users
                    $pdo->prepare('UPDATE users SET stripe_customer_id=? WHERE id=?')
                        ->execute([$custId, $user['id']]);
                }
            }
            break;
OLD;

$new = <<<'NEW'
        case 'checkout.session.completed':
            $email  = $obj['customer_details']['email'] ?? $obj['customer_email'] ?? '';
            $mode   = $obj['mode'] ?? '';
            $plan   = $obj['metadata']['plan'] ?? null; /*CH_PLANSAFE: eksik metadata'da ASLA 'basic' varsayilmaz*/
            $custId = $obj['customer'] ?? '';

            if($email && $plan && in_array($plan,['basic','pro','elite'],true) && ($mode === 'subscription' || $mode === 'payment')){
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if($user){
                    $pdo->prepare('INSERT INTO user_plans (user_id,plan,stripe_customer_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE plan=?, stripe_customer_id=?')
                        ->execute([$user['id'], $plan, $custId, $plan, $custId]);
                    // Also update stripe_customer_id in users
                    $pdo->prepare('UPDATE users SET stripe_customer_id=? WHERE id=?')
                        ->execute([$custId, $user['id']]);
                }
            } elseif($email && $custId && !$plan){
                /*CH_PLANSAFE: plan-disi tek-seferlik odeme (fax/belge/posta) — SADECE musteri-id guncellenir, PLAN ELLENMEZ*/
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if($user){
                    $pdo->prepare('UPDATE users SET stripe_customer_id=? WHERE id=?')->execute([$custId, $user['id']]);
                }
            }
            break;
NEW;

$c=substr_count($src,$old);
if($c!==1){ echo "  ✗ anchor ($c, 1 beklenir) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($old,$new,$src);
echo "  ✓ checkout.session.completed: eksik plan-metadata'da artik ASLA 'basic' varsayilmiyor\n";

$tmp=tempnam(sys_get_temp_dir(),'pw').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-planwebhook-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_PLANSAFE')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_PLANSAFE diskte (".strlen($chk)." B)\n";
echo "  ETKI: Fax/Einschreiben/belge gibi tek-seferlik odemeler ARTIK plani asla degistirmez.\n";
echo "  Gercek plan-satin-alma checkout'lari (metadata.plan dogru) davranis AYNI.\n";
echo "\n  ONEMLI: acerasoft@gmail.com'un ZATEN 'basic'e dusmus DB kaydini bu yama DUZELTMEZ\n";
echo "  (sadece GELECEKTEKI yanlis dususleri onler). Mevcut kaydi Elite'e geri almak icin\n";
echo "  DB'de manuel duzeltme (veya Stripe'tan tekrar subscription.updated event'i tetikleme)\n";
echo "  gerekir — bunu ister misin, soyle.\n";
