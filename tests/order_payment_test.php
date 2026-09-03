<?php
/* Odeme hatirlatma / otomatik iptal (operator karari, 2 Eyl 2026, order
 * INV-2026-1001 / Daymond Proconect vesilesiyle): "siparislerin odemesi 5 is
 * gunu icerisinde gelmez ise otomatik kapanacagini soyle, eger odeme
 * yaptiysa havale dekontunu... bize gondersin."
 * Uc sey tutulur:
 *   1. vestra_business_days_after() -- hafta sonu atlayan gun sayaci,
 *   2. vestra_order_payment_grace() -- saf asama makinesi: baslamamis /
 *      isliyor / suresi dolmus / DEKONT VAR (saat DURUR -- bakilmadan
 *      otomatik iptal olmaz, KURAL 2f'nin ayni dersi),
 *   3. vestra_tpl_order_payment_due() -- Ingilizce, rakamlar parametreden.
 * Ikisi/ucu de zaman/veri parametresini disaridan alir (saf); testin sonucu
 * gercek "bugun" hangi gune denk gelirse gelsin degismez.
 */
/* vestra_business_days_after() lives in inc/escrow.php, NOT here -- the escrow
 * auto-release sweep (31 Aug 2026) already needed the identical "N business
 * days, Sat/Sun skipped" clock. It is eval'd FIRST, before
 * vestra_order_payment_grace(): that function's own lazy require
 * (`if (!function_exists(...)) require_once __DIR__.'/escrow.php'`) would
 * resolve __DIR__ against THIS file's directory under eval and fail to find
 * it -- harmless here only because function_exists() is already true by then
 * and the require is skipped, exactly as it is on every real page that loads
 * both files before either function is called. */
$esrc = file_get_contents(__DIR__.'/../vestra/inc/escrow.php');
if (!preg_match('/^function vestra_business_days_after\(.*?^}/ms', $esrc, $m)) { echo "HATA: vestra_business_days_after bulunamadi\n"; exit(1); }
eval($m[0]);
$src = file_get_contents(__DIR__.'/../vestra/inc/orders.php');
if (!preg_match('/^function vestra_order_payment_grace\(.*?^}/ms', $src, $m)) { echo "HATA: vestra_order_payment_grace bulunamadi\n"; exit(1); }
eval($m[0]);
if (!preg_match('/const VESTRA_ORDER_PAYMENT_GRACE_DAYS = (\d+);/', $src, $m)) { echo "HATA: VESTRA_ORDER_PAYMENT_GRACE_DAYS bulunamadi\n"; exit(1); }
define('VESTRA_ORDER_PAYMENT_GRACE_DAYS', (int)$m[1]);

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

echo "-- vestra_business_days_after: hafta ici baslangic -> +5 is gunu = +7 takvim gunu --\n";
foreach (['monday','tuesday','wednesday','thursday','friday'] as $day) {
    $start = strtotime("$day this week 09:00:00");
    $end = vestra_business_days_after($start, 5);
    $t("$day: +5 is gunu = tam +7 takvim gunu sonra, ayni saatte", $end === $start + 7*86400);
    $t("$day: sonuc ayni gun adina denk gelir", gmdate('N', $end) === gmdate('N', $start));
}
echo "-- hafta sonu baslangic (nadir ama ihtimal disi degil) --\n";
$sat = strtotime('saturday this week 09:00:00');
$t('cumartesi baslarsa +5 is gunu = +6 takvim gunu (pazar hic sayilmaz)', vestra_business_days_after($sat,5) === $sat + 6*86400);
$sun = strtotime('sunday this week 09:00:00');
$t('pazar baslarsa +5 is gunu = +5 takvim gunu (bastan hafta sonu yok)', vestra_business_days_after($sun,5) === $sun + 5*86400);
$t('0 gun istenirse degismez', vestra_business_days_after($sat, 0) === $sat);

echo "-- vestra_order_payment_grace: asama makinesi --\n";
$mon = strtotime('monday this week 09:00:00');

$g = vestra_order_payment_grace(['status'=>'pending'], $mon);
$t('saat hic baslamamis -> unstamped', $g['phase'] === 'unstamped');
$t('unstamped -> son tarih yok', $g['deadline'] === null);

$g = vestra_order_payment_grace(['status'=>'pending', 'payment_grace_start'=>gmdate('c',$mon)], $mon + 86400);
$t('saat basladi, 1 gun gecti, mektup henuz stamplanmadi -> running', $g['phase'] === 'running');
$t('  running -> notice_sent false', $g['notice_sent'] === false);
$t('  running -> son tarih = baslangic + 7 takvim gunu', $g['deadline'] === $mon + 7*86400);
$t('  running -> kalan gun > 0', $g['days_left'] > 0);

$g = vestra_order_payment_grace(['status'=>'pending', 'payment_grace_start'=>gmdate('c',$mon), 'payment_reminder_sent_at'=>gmdate('c',$mon)],
                                 $mon + 7*86400 + 60);
$t('son tarih GECTI, ilk mektup gitmisti -> overdue (iptal edilebilir)', $g['phase'] === 'overdue');
$t('  overdue -> notice_sent true', $g['notice_sent'] === true);
$t('  overdue -> kalan gun 0', $g['days_left'] === 0);

$g = vestra_order_payment_grace(['status'=>'pending', 'payment_grace_start'=>gmdate('c',$mon)], $mon + 7*86400 + 60);
$t('son tarih gecti AMA ilk mektup HIC gitmemisti -> yine overdue', $g['phase'] === 'overdue');
$t('  bu durumda notice_sent false kalir -- cron once mektubu dener, IPTAL ETMEZ', $g['notice_sent'] === false);

echo "-- dekont yuklu -> saat DURUR (KURAL 2f'nin ayni dersi: bakilmadan otomatik islem yok) --\n";
$g = vestra_order_payment_grace(['status'=>'pending', 'payment_receipt'=>['file'=>'receipt_x.pdf']], $mon + 30*86400);
$t('dekont var, saat hic baslamamis olsa bile -> has_receipt', $g['phase'] === 'has_receipt');
$g = vestra_order_payment_grace([
    'status'=>'pending', 'payment_grace_start'=>gmdate('c',$mon), 'payment_reminder_sent_at'=>gmdate('c',$mon),
    'payment_receipt'=>['file'=>'receipt_x.pdf'],
], $mon + 30*86400);
$t('dekont var, son tarih COKTAN gecmis olsa bile -> yine has_receipt, IPTAL DEGIL', $g['phase'] === 'has_receipt');

$t('sabit: operator karari 5 is gunu', VESTRA_ORDER_PAYMENT_GRACE_DAYS === 5);

echo "-- vestra_receipt_file_path: ref/dosya adi temizleniyor (path traversal yok) --\n";
$rsrc = file_get_contents(__DIR__.'/../vestra/inc/receipts.php');
if (!preg_match('/^function vestra_receipt_file_path\(.*?^}/ms', $rsrc, $m)) { echo "HATA: vestra_receipt_file_path bulunamadi\n"; exit(1); }
define('VESTRA_RECEIPTS_DIR', '/tmp/vestra-test-receipts');
eval($m[0]);
$p1 = vestra_receipt_file_path('../../etc/passwd', 'x.pdf');
$t('ref icindeki ".." temizlenir', !str_contains($p1, '..'));
$p2 = vestra_receipt_file_path('O123', '../../../etc/passwd');
$t('dosya adi basename ile kirpilir (klasor gezintisi yok)', basename($p2) === 'passwd' && !str_contains($p2, '../'));

echo "-- vestra_tpl_order_payment_due sablonu --\n";
$tsrc = file_get_contents(__DIR__.'/../vestra/inc/email_templates.php');
foreach (['vestra_display_name', 'vestra_tpl_order_payment_due'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $tsrc, $m)) { echo "HATA: $fn bulunamadi\n"; exit(1); }
    eval($m[0]);
}
[$s, $b, $o] = vestra_tpl_order_payment_due('SC Daymond Proconect SRL', 'O12345', 'INV-2026-1001', 3950.00, 'EUR', '9 September 2026', true, 'https://vestrasales.com/buyer?tab=orders&view=O12345');
$t('konu ref + fatura no tasir', str_contains($s, 'O12345') && str_contains($s, 'INV-2026-1001'));
$t('govde tutari basar (parametreden, gomulu degil)', str_contains($b, '€3,950.00'));
$t('govde son tarihi basar', str_contains($b, '9 September 2026'));
$t('"5 business days" cumlesi', str_contains($b, '5 business days'));
$t('"automatically cancelled" cumlesi', str_contains($b, 'automatically cancelled'));
$t('hesap var -> yukleme linki govdede', str_contains($b, 'https://vestrasales.com/buyer?tab=orders&view=O12345'));
$t('hesap var -> dugme ayni linke gider', ($o['button']['url'] ?? '') === 'https://vestrasales.com/buyer?tab=orders&view=O12345');
$t('bilgi kutusunda son tarih satiri', ($o['rows'][3]['value'] ?? '') === '9 September 2026');
[$s2, $b2, $o2] = vestra_tpl_order_payment_due('', 'O9', 'INV-9', 100, 'USD', '1 October 2026', false, '');
$t('bos ad -> "Customer"', str_contains($b2, 'Dear Customer,'));
$t('USD -> US$ sembolu', str_contains($b2, 'US$100.00'));
$t('hesap yok -> e-posta yaniti onerilir, dugme yok', str_contains($b2, 'replying to this e-mail') && !isset($o2['button']));
$noTurkish = fn(string $s) => !preg_match('/[şğıİçöüŞĞÇÖÜ]/u', $s);
$t('Turkce karakter yok', $noTurkish($s.$b.$s2.$b2));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
