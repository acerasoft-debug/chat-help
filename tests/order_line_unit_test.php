<?php
/* VAR OLAN bir sipariş kaleminin birim fiyatını düzeltme (vestra_order_set_line_unit)
 * ve sipariş faturasını PDF EKİYLE gönderen mektup (order_invoice_pdf) — 28 Eyl 2026,
 * VES-D91DAB0B / Odzież Premium: "faturasını da 60 eur tam yap shipp cost free olsun"
 * + müşteri "I dont have inovice".
 *
 * KUM HAVUZUNDA GERÇEKTEN YAZIYOR. İKİ YÖN: hedef kalem değişir; aynı siparişin
 * DİĞER kalemi, notları, navlunu ve BAŞKA sipariş değişmez. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };

$root = dirname(__DIR__);
$sand = sys_get_temp_dir().'/vestra_olu_'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
if (!defined('VESTRA_DATA_DIR')) define('VESTRA_DATA_DIR', $sand.'/data');
require_once $root.'/vestra/inc/products.php';
require_once $root.'/vestra/inc/orders.php';
require_once $root.'/vestra/inc/invoice.php';
if (vestra_data_dir() !== $sand.'/data') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }

/* AMI-PL-014 koda gömülü demo ürün: listings.json olmadan çözülüyor. */
$ami = vestra_product_by_sku('AMI-PL-014');
if (!$ami) { fwrite(STDERR, "AMI-PL-014 cozulemedi\n"); exit(1); }
$list1 = round((float)vestra_unit_price($ami, 1, true), 2);
if ($list1 <= 1) { fwrite(STDERR, "liste fiyati okunamadi\n"); exit(1); }

$head = ['ref','company','email','country','items','subtotal','commission','payout','total','notes','discount','shipping','shipping_label'];
$f = $sand.'/data/orders.csv';
$h = fopen($f, 'w'); fputcsv($h, $head, ',', '"', '\\');
$row = function (string $ref, string $items, float $goods, float $ship) use ($h) {
    fputcsv($h, [$ref, 'Test Sp.', 'x@example.com', 'Poland', $items,
        number_format($goods, 2, '.', ''), '0.00', number_format($goods, 2, '.', ''),
        number_format($goods + $ship, 2, '.', ''), 'Payment: Bank transfer. Colours — AMI-PL-014: Black.',
        '', number_format($ship, 2, '.', ''), 'Shipping'], ',', '"', '\\');
};
$l1 = number_format($list1, 2, '.', '');
$row('VES-U1', "1x AMI-PL-014 @{$l1}", $list1, 20.10);
$row('VES-U2', "5x SKU-A @10.00 | 1x AMI-PL-014 @{$l1}", 50 + $list1, 0);
$row('VES-U3', "1x AMI-PL-014 @{$l1}", $list1, 0);
$row('VES-U4', "1x AMI-PL-014 @{$l1} | 2x AMI-PL-014 @{$l1}", 3 * $list1, 0);
$row('VES-OTHER', "1x AMI-PL-014 @{$l1}", $list1, 5);
fclose($h);
$get = function (string $ref) { foreach (vestra_read_csv('orders.csv') as $r) if (($r['ref'] ?? '') === $ref) return $r; return null; };

echo "== 1. ilandan UCUZ birim: yazılır, toplam aynı formülle ==\n";
$cheap = round($list1 - 1, 2);
$r = vestra_order_set_line_unit('VES-U1', 'AMI-PL-014', $cheap);
$t('yazildi', empty($r['error']));
$b = $get('VES-U1');
$t('items yeni birimi tasiyor', ($b['items'] ?? '') === '1x AMI-PL-014 @'.number_format($cheap, 2, '.', ''));
$t('navlun DOKUNULMADI (20.10)', abs((float)$b['shipping'] - 20.10) < 0.005);
$t('toplam = mal + navlun', abs((float)$b['total'] - round($cheap + 20.10, 2)) < 0.005);
$t('subtotal ve payout birlikte', abs((float)$b['subtotal'] - $cheap) < 0.005 && abs((float)$b['payout'] - $cheap) < 0.005);
$t('notlar DOKUNULMADI', ($b['notes'] ?? '') === 'Payment: Bank transfer. Colours — AMI-PL-014: Black.');
$t('faturasizda must_redraft FALSE', ($r['must_redraft'] ?? null) === false);
$t('BASKA siparis DEGISMEDI', ($get('VES-OTHER')['items'] ?? '') === "1x AMI-PL-014 @{$l1}");

echo "\n== 2. ilandan PAHALI birim: opt-in yoksa RED, varsa yazılır, iz kayıtta ==\n";
$r = vestra_order_set_line_unit('VES-U3', 'AMI-PL-014', $list1 + 10);
$t('above_list olmadan REDDEDILDI', !empty($r['error']) && str_contains($r['error'], 'PAHALI'));
$t('reddedilen kayit degismedi', ($get('VES-U3')['items'] ?? '') === "1x AMI-PL-014 @{$l1}");
$r = vestra_order_set_line_unit('VES-U3', 'AMI-PL-014', 60.0, false, true);
$t('above_list=1 ile YAZILDI', empty($r['error']));
$t('toplam 60.00 (navlun 0)', abs((float)($get('VES-U3')['total'] ?? 0) - 60.0) < 0.005);
$st = vestra_read_json('order_statuses.json');
$t('iz: onceki birim kayitta', ($st['VES-U3']['unit_set_prev'] ?? '') === $l1);
$t('iz: listenin ustunde isareti', ($st['VES-U3']['unit_above_list'] ?? '') === $l1);
$t('gerekce NOTLARA yazilmadi (alici sayfasinda basilir)', !str_contains((string)($get('VES-U3')['notes'] ?? ''), 'list'));

echo "\n== 3. çok kalemli sipariş: YALNIZ hedef kalem değişir ==\n";
$r = vestra_order_set_line_unit('VES-U2', 'AMI-PL-014', $cheap);
$b = $get('VES-U2');
$t('yazildi', empty($r['error']));
$t('diger kalem aynen', str_starts_with((string)$b['items'], '5x SKU-A @10.00 | '));
$t('toplam = 50 + yeni birim', abs((float)$b['total'] - round(50 + $cheap, 2)) < 0.005);

echo "\n== 4. aynı SKU iki satırda: belirsiz, RED ==\n";
$r = vestra_order_set_line_unit('VES-U4', 'AMI-PL-014', $cheap);
$t('TAM 1 sarti', !empty($r['error']) && str_contains($r['error'], 'TAM 1'));

echo "\n== 5. faturalı sipariş: opt-in yoksa RED, varsa yazar + must_redraft ==\n";
file_put_contents(vestra_invoice_meta_file('VES-U1', 'vestra'), json_encode(['no' => 'INV-TEST-1', 'seller_key' => 'vestra', 'total' => 1, 'currency' => 'EUR']));
$r = vestra_order_set_line_unit('VES-U1', 'AMI-PL-014', $cheap - 1);
$t('allow_invoiced olmadan REDDEDILDI', !empty($r['error']) && str_contains($r['error'], 'kesilmiş'));
$r = vestra_order_set_line_unit('VES-U1', 'AMI-PL-014', $cheap - 1, true);
$t('allow_invoiced ile yazildi', empty($r['error']));
$t('must_redraft TRUE', ($r['must_redraft'] ?? null) === true);

echo "\n== 6. parası gelmiş sipariş: KOŞULSUZ RED ==\n";
$st = vestra_read_json('order_statuses.json'); $st['VES-U2']['status'] = 'paid'; vestra_write_json('order_statuses.json', $st);
$r = vestra_order_set_line_unit('VES-U2', 'AMI-PL-014', $cheap - 2, true, true);
$t('odenmis siparis REDDEDILDI (opt-in de olsa)', !empty($r['error']) && str_contains($r['error'], 'parası gelmiş'));

echo "\n== 7. iş akışı: order_unit adımı ==\n";
$wf = (string)file_get_contents($root.'/.github/workflows/seller-products.yml');
$a = strpos($wf, "admin_mode == 'order_unit'");
$bd = $a === false ? '' : substr($wf, $a, strpos($wf, 'PHPEOF', strpos($wf, "<<'PHPEOF'", $a) + 10) - $a);
$t('adim var', $bd !== '');
$pDry = strpos($bd, 'if (!$APPLY)'); $pDo = strpos($bd, 'vestra_order_set_line_unit(');
$t('varsayilan KURU KOSU: yazma kuru kosu cikisindan SONRA', $pDry !== false && $pDo !== false && $pDry < $pDo);
$t('allow_invoiced ve above_list iletiliyor', str_contains($bd, 'vestra_order_set_line_unit($ref, $sku, $unit, $allowInv, $above)'));

echo "\n== 8. order_invoice_pdf mektubu ==\n";
require_once $root.'/vestra/inc/email_templates.php';
[$sub, $body, $opts] = vestra_tpl_order_invoice_pdf('Test Sp.', 'VES-X', 'INV-2026-9', 60.0, 'EUR', true, true, 'Marco Bellini');
$t('konu fatura no + ref', str_contains($sub, 'INV-2026-9') && str_contains($sub, 'VES-X'));
$t('tutar parametreden', str_contains($body, 'EUR 60.00'));
$t('yeniden cizim cumlesi bayrakla', str_contains($body, 'replaces any earlier version'));
[, $body2] = vestra_tpl_order_invoice_pdf('Test Sp.', 'VES-X', 'INV-2026-9', 60.0, 'EUR', false);
$t('bayrak yoksa cumle YOK', !str_contains($body2, 'replaces any earlier version'));
$tpl = (string)file_get_contents($root.'/vestra/inc/email_templates.php');
$ta = strpos($tpl, 'function vestra_tpl_order_invoice_pdf'); $tb = strpos($tpl, "\n}\n", $ta);
$t('sablonda gomulu tutar yok', !preg_match('/\b60(\.00)?\b/', substr($tpl, $ta, $tb - $ta)));
$sp = (string)file_get_contents($root.'/.github/workflows/send-campaign-preview.yml');
$sa = strpos($sp, "\$letter === 'order_invoice_pdf'");
$sb = $sa === false ? '' : substr($sp, $sa, strpos($sp, "} elseif (\$letter === 'listing_reply')", $sa) - $sa);
$sc = preg_replace('~/\*.*?\*/~s', '', $sb);
$t('dal var', $sc !== '');
$t('to=order SART', str_contains($sc, 'if (!$orderRow)'));
$t('odenmis siparis DURUR', str_contains($sc, "vestra_order_payment_settled(\$oref)") && str_contains($sc, "!empty(\$opaid['settled'])"));
$pStale = strpos($sc, 'if ($recAt > $pdfAt)'); $pTpl = strpos($sc, 'vestra_tpl_order_invoice_pdf(');
$t('belge kayittan ESKIYSE mektup kurulmadan DURUR', $pStale !== false && $pTpl !== false && $pStale < $pTpl);
$t('ek PDF, belgenin kendisi', str_contains($sc, "\$opts['attachments'] = [['name' => 'Invoice-'.\$invNo.'.pdf', 'path' => \$pdf]]"));
$t('eski-belge olcusu unit_set_at ve shipping_set_at dahil', str_contains($sc, "'shipping_set_at','unit_set_at'"));

echo "\n== 9. order_note: siparise bagli serbest not ==\n";
[$ns, $nb, $no] = vestra_tpl_order_note('Test Sp.', 'VES-X', 'Size L is fine. Invoice stays as it is.', '', true, 'Marco Bellini');
$t('varsayilan konu siparis ref tasir', str_contains($ns, 'VES-X'));
$t('metin OLDUGU GIBI govdede', str_contains($nb, "Dear Test Sp.,\n\nSize L is fine. Invoice stays as it is.\n\nKind regards"));
$t('imza persona', str_contains($nb, 'Marco Bellini'));
[$ns2] = vestra_tpl_order_note('Test Sp.', 'VES-X', 'x', 'Your sample in size L');
$t('verilen konu kullanilir', $ns2 === 'Your sample in size L');
$na = strpos($sp, "\$letter === 'order_note'");
$nbk = $na === false ? '' : preg_replace('~/\*.*?\*/~s', '', substr($sp, $na, strpos($sp, "} elseif (\$letter === 'listing_reply')", $na) - $na));
$t('dal var', $nbk !== '');
$t('to=order SART', str_contains($nbk, 'if (!$orderRow)'));
$t('bos metin DURUR', str_contains($nbk, "if (\$onMsg === '')"));
$t('iptal sipariste bayraksiz DURUR', str_contains($nbk, "if (\$onSt === 'cancelled' && trim(\$E('cancelled_ok')) !== '1') { fwrite(STDERR"));
$t('metin kutuge BASILMAZ (yalniz uzunluk)', !preg_match('/echo[^;]*\$onMsg\b(?!\))/', $nbk) && str_contains($nbk, 'mb_strlen($onMsg)'));

array_map('unlink', array_filter(array_merge(glob($sand.'/data/*') ?: [], glob($sand.'/data/invoices/*') ?: []), 'is_file'));
@rmdir($sand.'/data/invoices'); @rmdir($sand.'/data'); @rmdir($sand);
echo "\n".($bad ? "FAIL: {$bad}" : "hepsi yesil").", {$ok} ok\n";
exit($bad ? 1 : 0);
