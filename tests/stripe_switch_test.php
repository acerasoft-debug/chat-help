<?php
/* Stripe ana salteri (15 Eki 2026, operator: "tum Stripe API baglantilarini inaktif yap").
   Kapaliyken: hicbir kapi acik degil, gercek cagri Stripe'a gitmeden reddediliyor,
   Connect durumu satici kaydina YAZILMIYOR, sayfalar/cron/webhook salteri okuyor. */
$root = dirname(__DIR__);
$ok=0;$bad=0;$t=function($n,$c)use(&$ok,&$bad){ if($c){$ok++;echo "  ok   $n\n";}else{$bad++;echo "  HATA $n\n";} };
putenv('STRIPE_SECRET_KEY=sk_test_dummy'); putenv('STRIPE_CONNECT_ENABLED=1');
require $root.'/vestra/inc/products.php';
require_once $root.'/vestra/inc/stripe.php';
require_once $root.'/vestra/inc/escrow.php';
require_once $root.'/vestra/inc/dropship.php';

/* 1) dosya salteri: yaz -> geri oku -> eski haline don */
$f = vestra_stripe_switch_file(); $had = is_file($f); $bak = $had ? file_get_contents($f) : null;
@mkdir(dirname($f), 0775, true);
$t('acikken stripe_available', vestra_stripe_off() === false && stripe_available());
$t('acikken connect acik', vestra_connect_enabled());
$t('kapat -> geri okuma KAPALI', vestra_stripe_set_off(true, 'test') === true && vestra_stripe_off());
$t('kapali: stripe_available false', !stripe_available());
$t('kapali: stripe_configured false', !stripe_configured());
$msg = ''; try { stripe_api('GET', '/v1/balance'); $msg = 'GITTI'; } catch (\Throwable $e) { $msg = $e->getMessage(); }
$t('kapali: gercek cagri salterle reddedildi', str_contains($msg, 'salter'));
$t('kapali: connect durumu disabled, cagri yok', (stripe_connect_status(['stripe_account_id'=>'acct_x'])['disabled'] ?? false) === true);
$t('kapali: connect/escrow kapali', !vestra_connect_enabled());
$t('kapali: escrow_seller_ready false ve yazmadan', escrow_seller_ready(['id'=>'nope','stripe_account_id'=>'acct_x','escrow_ready'=>true], true) === false);
$t('kapali: dropship kart odemesi kapali', !vestra_dropship_payments_enabled());
$j = json_decode((string)file_get_contents($f), true);
$t('kayitta zaman + kim', !empty($j['off_at']) && ($j['by'] ?? '') === 'test');
$t('ac -> geri okuma acik', vestra_stripe_set_off(false) === false && stripe_available());
if ($had) file_put_contents($f, $bak); else @unlink($f);
vestra_stripe_switch(true);

/* 2) ortam degiskeni de kapatir (sunucu .env yedegi) */
putenv('STRIPE_DISABLED=1'); $t('STRIPE_DISABLED=1 kapatir', vestra_stripe_off() && !stripe_available()); putenv('STRIPE_DISABLED');

/* 3) kablolar */
$r = fn($p) => (string)file_get_contents($root.'/vestra/'.$p);
$wh = $r('stripe/webhook.php');
$t('webhook: imza dogrulamasindan SONRA, islemeden ONCE saklar', strpos($wh, 'vestra_stripe_hold_event') > strpos($wh, 'stripe_webhook_verify(') && strpos($wh, 'vestra_stripe_hold_event') < strpos($wh, 'switch ($type)'));
foreach (['group-checkout.php', 'sample-checkout.php'] as $p) {
  $s = $r($p);
  $t("$p: kayit acilmadan once doner", str_contains($s, "if (vestra_stripe_off()) { header('Location: ' . \$backUrl . '&error=payments_off'); exit; }")
     && strpos($s, 'vestra_stripe_off()') < (strpos($s, '_save(') ?: PHP_INT_MAX));
}
$cr = $r('cron_pool_sweep.php');
$t('havuz iade cron: kapaliyken erteler, refund_failed yazmaz', strpos($cr, 'vestra_stripe_off()') < strpos($cr, "stripe_api('POST', '/v1/refunds'"));
$t('urun sayfasi: numune dugmesi salterle gizli', str_contains($r('product.php'), '$samplePrice > 0 && !vestra_stripe_off()'));
$t('grup sayfasi: kapora formu salterle gizli', str_contains($r('group.php'), '$PAYOFF  = $DEPOSIT && vestra_stripe_off()') && str_contains($r('group.php'), '<?php if($PAYOFF): ?>'));
$t('stripe_api salteri ILK satirda soruyor', preg_match('/function stripe_api\([^)]*\): object \{\s*if \(vestra_stripe_off\(\)\)/', $r('inc/stripe.php')) === 1);
$cli = $r('cli_stripe_switch.php');
$t('cli betigi: web\'den 404 (yalniz komut satiri)', str_starts_with(ltrim(substr($cli, strpos($cli, '*/') + 2)), "if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }"));
$t('cli betigi: aciksa deneme cagrisi YAPMAZ', strpos($cli, "if (!vestra_stripe_off()) exit;") < strpos($cli, 'stripe_api("GET", "/v1/balance")'));
$t('cli betigi: anahtar degerini basmaz', !preg_match('/echo[^;]*\$v\b/', $cli));
$K = 'Online card payment is paused at the moment. Write to us and we will reserve your quantity by bank transfer.';
foreach (['de','fr','it','es','pt','ru','ar','ja'] as $l) { $a = require $root."/vestra/inc/lang/$l.php"; $t("$l: uyari cevrilmis", isset($a[$K]) && $a[$K] !== $K); }
echo "\n{$ok} gecti, {$bad} KALDI\n"; exit($bad ? 1 : 0);
