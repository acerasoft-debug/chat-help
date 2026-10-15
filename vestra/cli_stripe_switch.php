<?php
/* Stripe ana salteri -- KOMUT SATIRI (chat-help seller-products.yml admin_mode=stripe_switch).
   Kullanim: php cli_stripe_switch.php [off|on|status] [true=yaz]
   Bos/status = yalniz durum + etki sayimi. off|on + true = yazar, AYRI surecte geri okur ve
   kapaliyken gercek bir stripe_api() cagrisinin Stripe'a gitmeden REDDEDILDIGINI kanitlar.
   Anahtar DEGERI asla basilmaz, anahtarlar silinmez. Betik is akisinda degil burada, cunku
   seller-products.yml GitHub'in 512 KB dosya sinirina dayandi (15 Eki 2026). */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '1'); error_reporting(E_ALL);
$doc = __DIR__;
require_once $doc.'/inc/env.php';
require_once $doc.'/inc/products.php';
require_once $doc.'/inc/stripe.php';
require_once $doc.'/inc/escrow.php';
require_once $doc.'/inc/pools.php';
require_once $doc.'/inc/dropship.php';
$spec  = strtolower(trim((string)($argv[1] ?? '')));
$APPLY = strtolower(trim((string)($argv[2] ?? ''))) === 'true';
if (!in_array($spec, ['', 'off', 'on', 'status'], true)) { fwrite(STDERR, "arguman: off | on | status\n"); exit(1); }

$sw = vestra_stripe_switch(true);
echo "SALTER     : ".(vestra_stripe_off() ? 'KAPALI' : 'acik')
   . (isset($sw['off_at']) ? " | son kapatma ".$sw['off_at'] : '').(isset($sw['on_at']) ? " | son acma ".$sw['on_at'] : '')."\n";
$keys = [];
foreach (['STRIPE_SECRET_KEY', 'STRIPE_PUBLISHABLE_KEY', 'STRIPE_WEBHOOK_SECRET', 'STRIPE_CONNECT_ENABLED'] as $k) {
    $v = (string)(getenv($k) ?: '');
    $kind = $v === '' ? 'YOK' : (preg_match('/^(sk|pk|rk)_live/', $v) ? 'var(live)' : (preg_match('/^(sk|pk|rk)_test/', $v) ? 'var(test)' : 'var'));
    $keys[] = $k.'='.$kind;
}
echo "anahtarlar : ".implode(' | ', $keys)."  (degerler basilmaz, SILINMEZ)\n";

/* ETKI: salter kapaninca ne durur, ne Stripe TARAFINDA surer. */
$subs = 0; $subSt = []; $conn = 0; $cust = 0;
foreach (auth_accounts() as $a) {
    if (!empty($a['stripe_account_id'])) $conn++;
    if (!empty($a['stripe_customer_id'])) $cust++;
    $ms = (string)($a['membership_status'] ?? '');
    if (!empty($a['stripe_subscription_id']) && in_array($ms, ['active', 'trialing', 'past_due'], true)) { $subs++; $subSt[$ms] = ($subSt[$ms] ?? 0) + 1; }
}
$esc = []; foreach (escrow_all() as $e) { $st = (string)($e['status'] ?? '?'); $esc[$st] = ($esc[$st] ?? 0) + 1; }
$pc  = []; foreach (pool_commits_all() as $c) { $st = (string)($c['status'] ?? '?'); $pc[$st] = ($pc[$st] ?? 0) + 1; }
$fmt = function (array $m): string { if (!$m) return '0'; arsort($m); $o = []; foreach ($m as $k => $v) $o[] = "$k=$v"; return implode(' ', $o); };
$hf  = $doc.'/data/stripe_held_events.jsonl';
echo "ETKI       : Connect hesabi olan satici {$conn} | Stripe musteri kaydi olan hesap {$cust}\n";
echo "             aktif/deneme/gecikmis UYELIK aboneligi {$subs} (".$fmt($subSt).") -- Stripe TARAFINDA faturalamaya DEVAM EDER\n";
echo "             escrow kayitlari: ".$fmt($esc)." -- serbest birakma/iade cagrisi yapilamaz\n";
echo "             grup kaporalari : ".$fmt($pc)." -- iade cron'u ERTELER, kayda dokunmaz\n";
echo "             dropship kart odemesi: ".(vestra_dropship_payments_enabled() ? 'ACIK' : 'kapali')
   . " | saklanan webhook olayi: ".(is_readable($hf) ? count(array_filter(explode("\n", (string)file_get_contents($hf)))) : 0)."\n";

if ($spec === '' || $spec === 'status') { echo "\nYalniz durum. off|on + yaz=true ile yazilir.\n"; exit(0); }
$want = ($spec === 'off');
echo "\nISTENEN    : ".($want ? 'KAPAT' : 'AC')."\n";
if (!$APPLY) { echo "KURU KOSU -- hicbir sey yazilmadi. move_apply=true ile yazilir.\n"; exit(0); }
vestra_stripe_set_off($want, 'operator-workflow');

/* Geri okuma AYRI surecte (static onbellek), kapaliyken gercek bir cagri denenir. */
$probe = 'require '.var_export($doc.'/inc/env.php', true).'; require '.var_export($doc.'/inc/stripe.php', true).';'
       . 'echo vestra_stripe_off() ? "KAPALI" : "acik"; echo "|avail=", stripe_available() ? 1 : 0;'
       . 'if (!vestra_stripe_off()) exit;'   // aciksa deneme cagrisi YAPILMAZ: Stripe'a gereksiz istek gitmesin
       . 'try { stripe_api("GET", "/v1/balance"); echo "|cagri=GITTI"; } catch (\Throwable $e) { echo "|cagri=", (str_contains($e->getMessage(), "salter") ? "REDDEDILDI(salter)" : "HATA:".substr($e->getMessage(), 0, 60)); }';
$out = trim((string)shell_exec(escapeshellcmd(PHP_BINARY ?: 'php').' -r '.escapeshellarg($probe)));
echo "GERI OKUMA : {$out}\n";
$ok = $want ? str_starts_with($out, 'KAPALI|avail=0|cagri=REDDEDILDI') : str_starts_with($out, 'acik|');
echo $ok ? "SONUC: tuttu\n" : "SONUC: TUTMADI\n";
exit($ok ? 0 : 1);
