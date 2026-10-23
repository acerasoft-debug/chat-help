<?php
/* ARSIVDEN FATURA GERI ALMA (23 Eki 2026, vestra_invoice_restore).
 *
 * Operator: "onceden silinen USD faturasini tekrar yukle ama musteriye email
 * gonderme" (VES-B7DDA4D4 / INV-2026-1040). Olculen: kes -> sil (arsiv) ->
 * kuru kosu dokunmaz -> geri al AYNI numarayla, PDF dahil, geri okunur;
 * canli belge varken DURUR; numara baska canli belgedeyse DURUR; damga
 * secimi; ikinci silme + en yeni arsivin secilmesi.
 * Kum havuzu: VESTRA_DATA_DIR gecici dizin. IBAN'lar SENTETIK. */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra-irest-'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data');
define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
require_once $root.'/inc/products.php';
require_once $root.'/inc/auth.php';
require_once $root.'/inc/invoice.php';
require_once $root.'/inc/orders.php';
if (vestra_data_dir() !== $sand.'/data') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }
file_put_contents($sand.'/data/accounts.json', json_encode([]));
vestra_platform_seller_save(['bank_holder' => 'Acerasoft LLC', 'bank_name' => 'US Test Bank', 'bank_bic' => 'CHASUS33',
    'bank_account' => '123456789012', 'bank_routing' => '021000021', 'bank_iban' => 'DE89370400440532013000']);
$head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_version','voucher_code','discount','shipping','shipping_label'];
$h = fopen($sand.'/data/orders.csv', 'w'); fputcsv($h, $head, ',', '"', '\\');
foreach (['VES-R1', 'VES-R2'] as $r) fputcsv($h, [date('c'), $r, 'Buyer Co', '', 'Buyer', 'b@example.com', 'Italy', '', '20x VS-GD-T04 @59.90', '1198.00', '0', '1198.00', '1218.00', 'Payment: Bank transfer.', 'yes', '2026-06-26', '', '0', '20.00', 'Shipping'], ',', '"', '\\');
fclose($h);
vestra_write_json('order_statuses.json', ['VES-R1' => ['invoice_seller_uid' => 'vestra', 'shipping_set_at' => date('c'), 'invoice_bank_at' => date('c')],
                                          'VES-R2' => ['invoice_seller_uid' => 'vestra', 'shipping_set_at' => date('c'), 'invoice_bank_at' => date('c')]]);
$dir = vestra_invoice_dir();

echo "== kes, sil ==\n";
$i1 = vestra_issue_order_invoices('VES-R1');
$no1 = (string)($i1[0]['no'] ?? '');
$t('kesildi', $no1 !== '' && count(vestra_invoices_for_ref('VES-R1')) === 1);
$pdfBytes = (string)file_get_contents($i1[0]['path']);
$d = vestra_invoice_delete('VES-R1', 'vestra');
$t('arsivlendi, canlida yok', !empty($d['ok']) && $d['moved'] === 2 && !vestra_invoices_for_ref('VES-R1'));
$t('arsivde 2 dosya', count(glob($dir.'/deleted/*-VES-R1__vestra.*')) === 2);

echo "\n== kuru kosu ==\n";
$k = vestra_invoice_restore('VES-R1', 'vestra', '', false);
$t('kuru kosu: ok, numara/birim/tutar/damga/dosyalar', $k['ok'] && !$k['applied'] && $k['no'] === $no1 && $k['currency'] === 'EUR' && abs($k['total'] - 1218.0) < 0.005 && $k['stamp'] !== '' && count($k['files']) === 2);
$t('...hicbir sey tasinmadi', !vestra_invoices_for_ref('VES-R1') && count(glob($dir.'/deleted/*-VES-R1__vestra.*')) === 2);

echo "\n== geri al ==\n";
$a = vestra_invoice_restore('VES-R1', 'vestra', '', true);
$t('geri alindi, ayni numara', $a['ok'] && $a['applied'] && $a['no'] === $no1);
$back = vestra_invoices_for_ref('VES-R1');
$t('canlida 1 belge, ayni numara, PDF birebir', count($back) === 1 && (string)$back[0]['no'] === $no1 && (string)@file_get_contents(vestra_invoice_file('VES-R1', 'vestra')) === $pdfBytes);
$t('arsiv bosaldi', glob($dir.'/deleted/*-VES-R1__vestra.*') === []);
$t('canli belge varken ikinci geri alma DURUR', !vestra_invoice_restore('VES-R1', 'vestra', '', true)['ok']);
$t('arsivde kaydi olmayan ref DURUR', str_contains(vestra_invoice_restore('VES-R2', 'vestra', '', true)['error'], 'arsivde'));

echo "\n== numara catismasi ==\n";
vestra_invoice_delete('VES-R1', 'vestra');
/* R2'nin CANLI belgesine R1'in numarasini yaz (kayit uzerinde simule): arsivdeki R1 geri alinamamali */
$i2 = vestra_issue_order_invoices('VES-R2');
$no2 = (string)($i2[0]['no'] ?? '');
$m2f = vestra_invoice_meta_file('VES-R2', 'vestra');
$m2 = json_decode((string)file_get_contents($m2f), true); $m2['no'] = $no1; file_put_contents($m2f, json_encode($m2));
$t('(kontrol: R2 canli belgesi R1 numarasini tasiyor)', $no2 !== '' && (string)(vestra_invoices_for_ref('VES-R2')[0]['no'] ?? '') === $no1);
$c = vestra_invoice_restore('VES-R1', 'vestra', '', true);
$t('numara baska canli belgede -> DURUR, arsiv yerinde', !$c['ok'] && str_contains($c['error'], 'CANLI') && count(glob($dir.'/deleted/*-VES-R1__vestra.*')) === 2);
$m2['no'] = $no2; file_put_contents($m2f, json_encode($m2));
vestra_invoice_delete('VES-R2', 'vestra');

echo "\n== damga secimi: en yeni ==\n";
/* R1 icin ikinci bir arsiv: yeni numarayla yeniden kes, sonra onu da sil -> iki damga */
sleep(1);
$i1b = vestra_issue_order_invoices('VES-R1');
$t('(kontrol: R1 yeni numarayla yeniden kesildi)', !empty($i1b[0]['no']) && (string)$i1b[0]['no'] !== $no1);
vestra_invoice_delete('VES-R1', 'vestra');
$stamps = array_map(fn($f) => substr(basename($f), 0, 15), glob($dir.'/deleted/*-VES-R1__vestra.json'));
sort($stamps);
$t('(kontrol: iki damga)', count($stamps) === 2 && $stamps[0] < $stamps[1]);
$k2 = vestra_invoice_restore('VES-R1', 'vestra', '', false);
$t('bos damga = EN YENI arsiv', $k2['ok'] && $k2['stamp'] === $stamps[1]);
$k3 = vestra_invoice_restore('VES-R1', 'vestra', $stamps[0], false);
$t('acik damga = o arsiv', $k3['ok'] && $k3['stamp'] === $stamps[0]);
$t('bilinmeyen damga DURUR', !vestra_invoice_restore('VES-R1', 'vestra', '19990101_000000', false)['ok']);

echo "\n== kablolama ==\n";
$sp = (string)file_get_contents(dirname(__DIR__).'/.github/workflows/seller-products.yml');
$t('is akisi: admin_mode=invoice_restore, kuru kosu + apply, e-posta yok', str_contains($sp, "admin_mode == 'invoice_restore'") && str_contains($sp, 'vestra_invoice_restore($ref, $seller, $stamp, $apply)') && str_contains($sp, 'aliciya HICBIR SEY gitmedi'));

echo "\n{$ok} ok, {$fail} hata\n";
exit($fail ? 1 : 0);
