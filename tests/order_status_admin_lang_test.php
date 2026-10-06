<?php
/* Admin order status must always be English (operator instruction, 4 Sep 2026,
 * screenshotted straight from Admin ▸ Orders: "في طريقه إلى VESTRA" / "بانتظار
 * الدفع" showing where "On its way to VESTRA" / "Awaiting payment" should be —
 * "ayrica siparis durumlarinda arapca ifadeler var sadece ingilizce olmak
 * zorunda"). Root cause: vestra_order_status_label() called t(), and t()
 * resolves via vlang() — a per-REQUEST cookie/Accept-Language guess, not a
 * per-viewer-role one. The admin panel never pinned it to English, so whatever
 * language the OPERATOR's own browser happened to be carrying (device
 * language on first visit, or a leftover cookie from clicking a translated
 * storefront link) silently became the admin order-status language too.
 *
 * Fix: an optional $forceEnglish parameter, defaulting false so the genuinely
 * buyer/seller-facing callers (the order timeline, vestra_render_order_detail)
 * keep translating normally — this test holds BOTH directions, same principle
 * as every other blocklist/allowlist test in this suite: prove the fix fixes
 * the bug AND doesn't regress the legitimate case it must leave alone.
 */
$_COOKIE['vlang'] = 'ar';   // simulates exactly the browser state that produced the bug

require __DIR__.'/../vestra/inc/i18n.php';   // real t()/vlang()/vtrans()

$src = file_get_contents(__DIR__.'/../vestra/inc/orders.php');
foreach (['VESTRA_ORDER_STEPS', 'VESTRA_ORDER_CANCELLED'] as $const) {
    if (!preg_match('/^const '.$const.' = .*?;/m', $src, $m)) { echo "HATA: $const bulunamadi\n"; exit(1); }
    eval($m[0]);
}
foreach (['vestra_order_status_label', 'vestra_order_settable_statuses', 'vestra_order_status_options'] as $fn) {
    if (!preg_match('/^function '.$fn.'\(.*?^}/ms', $src, $m)) { echo "HATA: $fn bulunamadi\n"; exit(1); }
    eval($m[0]);
}

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

echo "-- vlang=ar aktifken --\n";
$t('vlang() gercekten ar cozuldu (test on-kosulu gecerli)', vlang() === 'ar');

echo "\n-- forceEnglish=false (varsayilan) -- alici/satici tarafi hala normal cevriliyor --\n";
$t('to_vestra -> Arapca metin (KURAL: alici kendi dilinde gorur)', vestra_order_status_label('to_vestra') === 'في طريقه إلى VESTRA');
$t('default/pending -> Arapca "bekliyor" metni', vestra_order_status_label('pending') === 'بانتظار الدفع');

echo "\n-- forceEnglish=true -- admin paneli her zaman Ingilizce --\n";
$t('to_vestra -> "On its way to VESTRA" (Arapca DEGIL)', vestra_order_status_label('to_vestra', true) === 'On its way to VESTRA');
$t('pending -> "Awaiting payment" (Arapca DEGIL)', vestra_order_status_label('pending', true) === 'Awaiting payment');
$t('shipped -> "Shipped"', vestra_order_status_label('shipped', true) === 'Shipped');
$t('cancelled -> "Cancelled"', vestra_order_status_label('cancelled', true) === 'Cancelled');

echo "\n-- vestra_order_status_options() (admin.php'nin tek cagirdigi yol) --\n";
$opts = vestra_order_status_options('to_vestra');
$t('secenek metni Ingilizce "On its way to VESTRA" iceriyor', str_contains($opts, 'On its way to VESTRA'));
$t('Arapca ceviri HICBIR secenekte yok', !str_contains($opts, 'في طريقه') && !str_contains($opts, 'بانتظار'));
$t('secili deger hala dogru isaretleniyor', str_contains($opts, 'value="to_vestra" selected'));

echo "\n" . ($ok) . " ok, " . $fail . " hata\n";
exit($fail > 0 ? 1 : 0);
