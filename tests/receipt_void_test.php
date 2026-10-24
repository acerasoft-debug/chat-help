<?php
/* DEKONTU GECERSIZ SAYMA (24 Eki 2026, vestra_receipt_void).
 *
 * Operator: "o havale musteriye geri gelecegi icin yeni bir havale ya da elden odeme
 * icin belge yuklemesi gerek" (VES-EC6D6B0D). Olculen: dekont varken faz has_receipt;
 * kuru kosu dokunmaz; uygulama dekontu void listesine tasir (dosya yerinde), faz
 * 'running' olur, son tarih soz verilen gunden ONCE bitmez, uyari "gitti" sayilir
 * (cron ikinci mektup yollamaz); odenmis/iptal sipariste DURUR; alici sayfasinda
 * yukleme kutusu ve "havale geri dondu" notu cikar.
 * Kum havuzu: VESTRA_DATA_DIR gecici dizin. */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra-rvoid-'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data');
define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
require_once $root.'/inc/products.php';
require_once $root.'/inc/auth.php';
require_once $root.'/inc/orders.php';
require_once $root.'/inc/invoice.php';
require_once $root.'/inc/escrow.php';
require_once $root.'/inc/receipts.php';
if (vestra_data_dir() !== $sand.'/data') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }

$rc = ['file' => 'receipt_abcd1234.jpeg', 'uploaded_at' => '2026-10-20T13:26:21+00:00', 'uploaded_by' => 'buyer'];
vestra_write_json('order_statuses.json', [
    'VES-V1' => ['status' => 'pending', 'payment_receipt' => $rc],
    'VES-V2' => ['status' => 'paid',    'payment_receipt' => $rc],
    'VES-V3' => ['status' => 'pending'],
]);
$ph = fn(string $r) => vestra_order_payment_grace((array)(vestra_read_json('order_statuses.json')[$r] ?? []), time(), $r);

$t('baslangic: dekontlu siparis has_receipt', $ph('VES-V1')['phase'] === 'has_receipt');

$dl = strtotime('2026-10-31 23:59:59 UTC');
$k = vestra_receipt_void('VES-V1', 'transfer returned', 'test', $dl, false);
$t('kuru kosu ok, son tarih hesaplandi', $k['ok'] && $k['deadline'] !== null);
$t('...kuru kosu hicbir sey yazmadi', $ph('VES-V1')['phase'] === 'has_receipt');
$t('son tarih soz verilen gunden ONCE degil, en fazla 3 gun sonra', $k['deadline'] >= $dl && $k['deadline'] <= $dl + 3 * 86400);

$a = vestra_receipt_void('VES-V1', 'transfer returned', 'test', $dl, true);
$e = (array)vestra_read_json('order_statuses.json')['VES-V1'];
$t('uygulandi: dekont kalkti, void listesinde (neden + kim)', $a['ok'] && empty($e['payment_receipt']) && ($e['payment_receipts_void'][0]['file'] ?? '') === 'receipt_abcd1234.jpeg'
   && ($e['payment_receipts_void'][0]['void_reason'] ?? '') === 'transfer returned' && ($e['payment_receipts_void'][0]['void_by'] ?? '') === 'test');
$t('tarihceye satir dustu', str_contains(json_encode($e['history'] ?? []), 'Payment receipt voided'));
$g = vestra_order_payment_grace($e, strtotime('2026-10-24 15:00 UTC'), 'VES-V1');
$t("faz 'running', uyari GITTI sayiliyor (cron ikinci mektup yollamaz)", $g['phase'] === 'running' && !empty($g['notice_sent']));
$t('grace son tarihi = hesaplanan son tarih', (int)$g['deadline'] === (int)$a['deadline']);
$t('son tarihten sonra faz overdue (otomatik iptal yolu)', vestra_order_payment_grace($e, $a['deadline'] + 60, 'VES-V1')['phase'] === 'overdue');
$t('ikinci kez: dekont yok -> DURUR', !vestra_receipt_void('VES-V1', 'x', 'test', null, true)['ok']);
$t('odenmis sipariste DURUR', str_contains(vestra_receipt_void('VES-V2', 'x', 'test', null, true)['error'], 'paid'));
$t('dekontsuz sipariste DURUR', !vestra_receipt_void('VES-V3', 'x', 'test', null, true)['ok']);
/* son tarih verilmezse saat kurulmaz */
vestra_write_json('order_statuses.json', ['VES-V4' => ['status' => 'pending', 'payment_receipt' => $rc]]);
$n = vestra_receipt_void('VES-V4', 'x', 'test', null, true);
$e4 = (array)vestra_read_json('order_statuses.json')['VES-V4'];
$t('son tarihsiz: saat alanlari yazilmaz', $n['ok'] && $n['deadline'] === null && empty($e4['payment_grace_start']) && empty($e4['payment_reminder_sent_at']));

echo "\n== alici sayfasi ==\n";
$src = (string)file_get_contents($root.'/inc/orders.php');
$t('alici sayfasi: void varken "havale geri dondu" notu + yukleme kutusu', preg_match("/payment_receipts_void.*?earlier transfer was returned by the bank.*?vestra_receipt_upload_form/s", $src) === 1);
$sp = (string)file_get_contents(dirname(__DIR__).'/.github/workflows/seller-products.yml');
$t('is akisi: admin_mode=receipt_void, kuru kosu + apply', str_contains($sp, "admin_mode == 'receipt_void'") && str_contains($sp, 'vestra_receipt_void($ref, $reason, \'operator\', $dl, $apply)'));

echo "\n{$ok} ok, {$fail} hata\n";
exit($fail ? 1 : 0);
