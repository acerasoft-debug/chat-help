<?php
/* SIPARIS FATURASI MEKTUBU: FRANSIZCA + "DUZELTILDI, HER KALEM IDENT NO. VE RENKLERIYLE"
 * (operator, 3 Eki 2026, VES-3507BF86 / Ecokemet: "bu siparisin faturasinin
 * duzeltildigini ident ve renklerin konuldugunu belirterek tekrar gonder musteriye
 * ... fransizca").
 *
 * Tutulan:
 *   1. VARSAYILAN (en, duzeltme yok) cikti onceki surumle AYNI -- eski cagrilar degismez.
 *   2. fr: hitap, tutar bicimi, son tarih, kapanis Fransizca; Ingilizce cumle YOK.
 *   3. itemsFixed: duzeltme cumlesi VAR ve "ayni numara" cumlesi IKI KEZ yazilmiyor;
 *      bayrak yokken duzeltme cumlesi YOK (iki yon).
 *   4. Is akisi: lang=en|fr disi REDDEDILIR; fixed=items belgenin KENDISINDE her SKU ve
 *      her rengi arar -- gercek bir fatura PDF'i cizdirilip blok kum havuzunda kosuyor:
 *      tam belge GECER, eksik renk / eksik SKU / renksiz kalem DURUR.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/pdf.php';
require_once $root . '/inc/money.php';
require_once $root . '/inc/products.php';
require_once $root . '/inc/invoice.php';
require_once $root . '/inc/email_templates.php';
if (!function_exists('t')) require_once $root . '/inc/i18n.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

echo "== 1. Varsayilan (en) cikti: eski metin aynen ==\n";
[$s0, $b0, $o0] = vestra_tpl_order_invoice_pdf('Test Sp.', 'VES-X', 'INV-9', 2764.6, 'EUR', true, true, 'Marco Bellini', '9 October 2026');
$t('konu eski kalip', $s0 === 'VESTRA — invoice INV-9 for order VES-X');
$t('rozet eski', ($o0['badge'] ?? '') === 'Invoice attached');
$t('hitap Dear', str_starts_with($b0, "Dear Test Sp.,\n\n"));
$t('tutar EUR 2,764.60', str_contains($b0, 'Total due: EUR 2,764.60.'));
$t('son tarih cumlesi', str_contains($b0, 'Payment is due by 9 October 2026.'));
$t('eski "same invoice number" cumlesi (redrafted)', substr_count($b0, 'keeps the same invoice number') === 1);
$t('duzeltme cumlesi YOK (bayrak yok)', !str_contains($b0, 'corrected') && !str_contains($b0, 'ident no.'));
/* Ayni tutar, dil acikca 'en' verilse de ayni metin. */
[$s0b, $b0b] = vestra_tpl_order_invoice_pdf('Test Sp.', 'VES-X', 'INV-9', 2764.6, 'EUR', true, true, 'Marco Bellini', '9 October 2026', 'en', false);
$t('lang=en acikca verilince BIREBIR ayni', $s0b === $s0 && $b0b === $b0);
/* Taninmayan dil sablonda en'e duser (is akisi zaten reddediyor). */
[, $b0c] = vestra_tpl_order_invoice_pdf('Test Sp.', 'VES-X', 'INV-9', 2764.6, 'EUR', true, true, 'Marco Bellini', '9 October 2026', 'xx');
$t('taninmayan dil -> en', $b0c === $b0);

echo "\n== 2. Fransizca ==\n";
[$s1, $b1, $o1] = vestra_tpl_order_invoice_pdf('Ecokemet', 'VES-3507BF86', 'INV-2026-1022', 2764.6, 'EUR', true, true, 'Marco Bellini', '9 octobre 2026', 'fr', false);
$t('konu fr', $s1 === 'VESTRA — facture INV-2026-1022 pour la commande VES-3507BF86');
$t('hitap Bonjour', str_starts_with($b1, "Bonjour Ecokemet,\n\n"));
$t('tutar 2 764,60 €', str_contains($b1, 'Montant à régler : 2 764,60 €.'));
$t('son tarih fr', str_contains($b1, 'au plus tard le 9 octobre 2026'));
$t('ayni numara cumlesi fr (redrafted)', substr_count($b1, 'conserve le même numéro') === 1);
$t('kapanis Cordialement + imza', str_contains($b1, "Cordialement,\n\nMarco Bellini\nVESTRA"));
$t('Ingilizce cumle YOK', !preg_match('/\b(Dear|Please|Total due|Kind regards|invoice for order)\b/', $b1));
$rowLabels = array_map(fn($r) => $r['label'], $o1['rows'] ?? []);
$t('kutu etiketleri fr', $rowLabels === ['Commande', 'Facture', 'Montant à régler', 'À régler avant le']);
$t('dugme fr', ($o1['button']['label'] ?? '') === 'Voir ma commande');
[, $b1u] = vestra_tpl_order_invoice_pdf('X', 'VES-U', 'INV-U', 100.0, 'USD', false, false, '', '', 'fr');
$t('USD tutar fr bicimde US$', str_contains($b1u, '100,00 US$'));
$t('imzasiz fr -> sirket imzasi', str_contains($b1u, "VESTRA · Acerasoft LLC"));

echo "\n== 3. itemsFixed: duzeltme cumlesi, iki yon ==\n";
[$s2, $b2, $o2] = vestra_tpl_order_invoice_pdf('Ecokemet', 'VES-3507BF86', 'INV-2026-1022', 2764.6, 'EUR', true, true, 'Marco Bellini', '9 octobre 2026', 'fr', true);
$t('konu fr "facture corrigée"', $s2 === 'VESTRA — facture corrigée INV-2026-1022 pour la commande VES-3507BF86');
$t('duzeltme cumlesi VAR', str_contains($b2, "numéro d'identification (référence) et ses coloris"));
$t('ayni numara IKI KEZ yazilmiyor', substr_count($b2, 'même numéro') === 1);
$t('rozet "Facture corrigée"', ($o2['badge'] ?? '') === 'Facture corrigée');
$t('duzeltme cumlesi tutardan ONCE', strpos($b2, 'Nous avons corrigé') < strpos($b2, 'Montant à régler'));
$t('bayrak yokken fr duzeltme cumlesi YOK', !str_contains($b1, 'corrigé'));
[$s3, $b3] = vestra_tpl_order_invoice_pdf('A', 'VES-X', 'INV-9', 10.0, 'EUR', true, false, '', '', 'en', true);
$t('en itemsFixed konu', $s3 === 'VESTRA — corrected invoice INV-9 for order VES-X');
$t('en itemsFixed cumle', str_contains($b3, 'each item is now listed with its ident no. (style reference) and its colours'));
$t('en itemsFixed ayni numara tek kez', substr_count($b3, 'same') === 1);

echo "\n== 4. Is akisi: kablolama ==\n";
$wf = (string)file_get_contents(__DIR__ . '/../.github/workflows/send-campaign-preview.yml');
$a = strpos($wf, "\$letter === 'order_invoice_pdf'");
$b = strpos($wf, "\$letter === 'order_note'", (int)$a);
$br = $a !== false && $b !== false ? substr($wf, $a, $b - $a) : '';
$t('dal bulundu', $br !== '');
$t('lang en|fr disi reddediliyor', str_contains($br, "in_array(\$ilang, ['en', 'fr'], true)") && str_contains($br, 'desteklenmiyor (en|fr)'));
$t('fr son tarih vestra_push_date ile', str_contains($br, "vestra_push_date(") && str_contains($br, "'fr')"));
$t('sablona lang ve fixed geciyor', (bool)preg_match('/vestra_tpl_order_invoice_pdf\(.*?\$ilang, \$ifix\);/s', $br));
$t('fixed=items belgeyi drawn_text ile olcuyor', str_contains($br, 'vestra_pdf_drawn_text((string)file_get_contents($pdf))'));

echo "\n== 5. Is akisi: fixed=items blogu GERCEK bir faturada kosuyor ==\n";
/* Blok kaynaktan cikarilir; exit/STDERR yakalanabilir bicime cevrilir. */
$s = strpos($br, 'if ($ifix) {');
$e = strpos($br, '$buyerName = trim(', (int)$s);
$block = $s !== false && $e !== false ? substr($br, $s, $e - $s) : '';
$t('blok cikarildi', $block !== '' && str_contains($block, 'kayitta RENK YOK'));
$block = str_replace(['fwrite(STDERR, ', 'exit(1);'], ['$__err(', 'throw new RuntimeException("STOP");'], $block);

/* Gercek fatura: iki kalem, uzun SKU (sariliyor) + iki kelimelik renk. */
$meta = ['ref' => 'VES-FIX', 'date' => '2026-10-01T10:00:00+00:00', 'shipping' => 30.0, 'discount' => 0.0,
         'buyer' => ['company' => 'Test Buyer', 'name' => 'T', 'country' => 'France', 'address' => '1 rue X, 75001 Paris', 'vat' => '', 'reg' => '']];
$FX = [['LAC-L1212-MUSTERSTUECK', ['Black', 'Light Blue', 'Dark Green']], ['SH9608', ['Navy', 'Bordeaux']]];
$items = [];
foreach ($FX as [$sku, $cols]) $items[] = ['sku' => $sku, 'brand' => 'Lacoste', 'name' => 'Polo', 'colors' => $cols, 'qty' => count($cols), 'unit' => 30.0, 'line' => 30.0 * count($cols)];
$seller = ['id' => 's1', 'company' => 'Seller Co', 'country' => 'FR', 'address' => '1 Allee', 'bank_iban' => 'DE89370400440532013000', 'bank_holder' => 'Seller Co'];
$S = sys_get_temp_dir() . '/vifx_' . getmypid();
@mkdir($S, 0700, true);
$pdf = $S . '/inv.pdf';
file_put_contents($pdf, vestra_render_invoice_pdf($meta, $items, $seller, 'INV-FIX', false));
$t('fatura cizildi', str_starts_with((string)file_get_contents($pdf), '%PDF'));

$GLOBALS['__lines'] = [];
if (!function_exists('vestra_order_lines')) {
    function vestra_order_lines(array $row): array { return ['lines' => $GLOBALS['__lines'], 'notes' => '']; }
}
$run = function (array $lines, string $fixed) use ($block, $pdf, $root): array {
    $GLOBALS['__lines'] = $lines;
    $doc = $root; $orderRow = ['ref' => 'VES-FIX'];
    $E = fn(string $k) => $k === 'fixed' ? $fixed : '';
    $errs = []; $__err = function (string $m) use (&$errs) { $errs[] = $m; };
    ob_start();
    try { $ifix = strtolower(trim($E('fixed'))) === 'items'; eval($block); $stopped = false; }
    catch (RuntimeException $x) { $stopped = true; }
    $out = (string)ob_get_clean();
    return ['stopped' => $stopped, 'out' => $out, 'err' => implode(' | ', $errs)];
};
$L = fn(array $fx) => array_map(fn($r) => ['sku' => $r[0], 'colors' => $r[1]], $fx);

$r = $run($L($FX), 'items');
$t('tam belge GECER ('.trim($r['out']).')', !$r['stopped'] && str_contains($r['out'], 'SKU 2/2 | renk 5/5'));
$r = $run($L([['LAC-L1212-MUSTERSTUECK', ['Black', 'Light Blue', 'Dark Green', 'Off White']], ['SH9608', ['Navy', 'Bordeaux']]]), 'items');
$t('kayitta olup belgede OLMAYAN renk -> DURUR', $r['stopped'] && str_contains($r['err'], "renk 'Off White'"));
$r = $run($L([['LAC-L1212-MUSTERSTUECK', ['Black', 'Light Blue', 'Dark Green']], ['SH9608', ['Navy', 'Bordeaux']], ['XH9624', ['Black']]]), 'items');
$t('belgede OLMAYAN SKU -> DURUR', $r['stopped'] && str_contains($r['err'], 'SKU XH9624'));
$r = $run($L([['LAC-L1212-MUSTERSTUECK', ['Black', 'Light Blue', 'Dark Green']], ['SH9608', []]]), 'items');
$t('renksiz kalem -> DURUR ("renkleri konuldu" denemez)', $r['stopped'] && str_contains($r['err'], 'kayitta RENK YOK'));
$r = $run($L([['NOPE', []]]), '');
$t('bayrak yokken blok hicbir sey yapmaz (eski davranis)', !$r['stopped'] && $r['out'] === '');
/* Ortak onekli SKU: belgede "SH9608" varken kayittaki "SH96081" BULUNMAMALI. (Ters
   yon -- kayittaki kod belgedekinin ONEKI ise -- ayiracsiz cizim yuzunden ayirt
   edilemez; bilinen sinir, CLAUDE.md'de yazili.) */
$r = $run($L([['LAC-L1212-MUSTERSTUECK', ['Black', 'Light Blue', 'Dark Green']], ['SH96081', ['Navy', 'Bordeaux']]]), 'items');
$t('ortak onekli baska SKU belgede sayilmiyor', $r['stopped'] && str_contains($r['err'], 'SKU SH96081'));

@unlink($pdf); @rmdir($S);
echo "\ninvoice_pdf_letter_fr_test: $ok iddia gecti, $fail hata\n";
exit($fail ? 1 : 0);
