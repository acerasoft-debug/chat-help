<?php
/* IPTAL EDILMIS SIPARISI YENI SON TARIHLE GERI ACMA (28 Eki 2026, vestra_order_reinstate).
 *
 * Operator: AlexaShop / O5AD1B odeme gelmedigi icin otomatik iptal oldu, aliciya "devam
 * etmek ister misiniz" yazildi, sonra "2 ek odeme suresi baslat". Olculen: kuru kosu
 * dokunmaz; uygulama durumu pending yapar, faz 'running', son tarih istenen gunden ONCE
 * bitmez, uyari "gitti" sayilir (cron ikinci "5 is gunu" mektubu yollamaz), son tarih
 * gecince faz overdue (cron yine iptal eder); iptal olmayan / odenmis / faturasiz
 * sipariste ve gecmis tarihte DURUR. Kum havuzu: VESTRA_DATA_DIR gecici dizin. */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra-reinst-'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data');
define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
require_once $root.'/inc/products.php';
require_once $root.'/inc/auth.php';
require_once $root.'/inc/orders.php';
require_once $root.'/inc/invoice.php';
require_once $root.'/inc/escrow.php';
if (vestra_data_dir() !== $sand.'/data') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }
$idir = vestra_invoice_dir(); @mkdir($idir, 0777, true);
foreach (['ORE1', 'ORE2', 'ORE4'] as $r) file_put_contents($idir.'/'.$r.'__vestra.json', json_encode(['no' => 'INV-T-'.$r, 'seller_key' => 'vestra', 'total' => 100, 'currency' => 'EUR']));

$hist = [['status' => 'cancelled', 'at' => '2026-10-28T14:00:00+00:00', 'by' => 'system', 'note' => 'Auto-cancelled']];
vestra_write_json('order_statuses.json', [
    'ORE1' => ['status' => 'cancelled', 'payment_grace_start' => '2026-10-19T21:00:00+00:00', 'payment_reminder_sent_at' => '2026-10-19T21:00:00+00:00', 'history' => $hist],
    'ORE2' => ['status' => 'pending'],
    'ORE3' => ['status' => 'cancelled'],
    'ORE4' => ['status' => 'cancelled'],
]);
/* Teklif faturasinin "odendi" isareti (Invoice approvals) -- satir iptal olsa da para gelmis. */
vestra_write_json('offer_responses.json', ['ORE4' => ['invoice_paid_at' => '2026-10-23T13:30:36+00:00']]);
$now = strtotime('2026-10-28 14:30 UTC');            // Carsamba
$dl  = strtotime('2026-10-30 23:59:59 UTC');         // Cuma gun sonu (2 is gunu)
$st  = fn(string $r) => (array)(vestra_read_json('order_statuses.json')[$r] ?? []);

$k = vestra_order_reinstate('ORE1', $dl, 'buyer asked', 'test', false, $now);
$t('kuru kosu ok, son tarih hesaplandi', $k['ok'] && $k['deadline'] !== null && $k['was'] === 'cancelled');
$t('...kuru kosu hicbir sey yazmadi', ($st('ORE1')['status'] ?? '') === 'cancelled');
$t('son tarih istenen gunden ONCE degil, en fazla 1 saat sonra', $k['deadline'] >= $dl && $k['deadline'] <= $dl + 3600);

$a = vestra_order_reinstate('ORE1', $dl, 'buyer asked', 'test', true, $now);
$e = $st('ORE1');
$t('uygulandi: durum pending', $a['ok'] && ($e['status'] ?? '') === 'pending');
$t('tarihceye geri acma satiri dustu (eski satirlar duruyor)', count($e['history'] ?? []) === 2 && str_contains(json_encode($e['history']), 'Reinstated after cancellation'));
$g = vestra_order_payment_grace($e, $now, 'ORE1');
$t("faz 'running', uyari GITTI sayiliyor (cron ikinci mektup yollamaz)", $g['phase'] === 'running' && !empty($g['notice_sent']));
$t('grace son tarihi = hesaplanan son tarih', (int)$g['deadline'] === (int)$a['deadline']);
$t('son tarihten once hala running', vestra_order_payment_grace($e, $dl - 60, 'ORE1')['phase'] === 'running');
$t('son tarihten sonra overdue (cron yine iptal eder)', vestra_order_payment_grace($e, $a['deadline'] + 60, 'ORE1')['phase'] === 'overdue');
$t('ikinci kez: artik iptal degil -> DURUR', str_contains(vestra_order_reinstate('ORE1', $dl, '', 'test', true, $now)['error'], "'pending'"));
$t('iptal olmayan sipariste DURUR', !vestra_order_reinstate('ORE2', $dl, '', 'test', true, $now)['ok']);
$t('faturasiz iptal sipariste DURUR', str_contains(vestra_order_reinstate('ORE3', $dl, '', 'test', true, $now)['error'], 'fatura'));
$t('...ORE3 iptal kaldi', ($st('ORE3')['status'] ?? '') === 'cancelled');
$t('parasi gelmis gorunen sipariste DURUR', str_contains(vestra_order_reinstate('ORE4', $dl, '', 'test', true, $now)['error'], 'GELMIS'));
$t('gecmis son tarihte DURUR', str_contains(vestra_order_reinstate('ORE4', $now - 60, '', 'test', true, $now)['error'] . vestra_order_reinstate('ORE3', $now - 60, '', 'test', true, $now)['error'], 'fatura'));
vestra_write_json('order_statuses.json', ['ORE1' => ['status' => 'cancelled']] + vestra_read_json('order_statuses.json'));
$t('gecmis son tarih: iptal+faturali sipariste DURUR', str_contains(vestra_order_reinstate('ORE1', $now - 60, '', 'test', true, $now)['error'], 'gecmiste'));

array_map('unlink', glob($sand.'/data/*/*') ?: []); array_map('unlink', array_filter(glob($sand.'/data/*') ?: [], 'is_file'));
echo "\n$ok ok, $fail hata\n";
exit($fail ? 1 : 0);
