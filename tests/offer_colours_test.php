<?php
/* KABUL EDILMIS TEKLIFIN FATURASINA RENK DOKUMU (19 Eki 2026, O5AD1B; operator: "SH9623 bu
 * urunden her rengi ekle faturaya").
 *
 * Teklif faturasi satiri 'colors' => [] ile kuruluyordu; renk teklifin KENDI kaydina
 * (offer_responses.json -> invoice_colours) yazilir ve teklif kurucusu onu okur. KUM HAVUZUNDA
 * gercekten yaziyor. IKI YON: hedef kayit, fatura yuku ve siparis satiri notu degisir; adet,
 * tutar, kaydin diger alanlari, BASKA teklif ve dokumu olmayan teklif DEGISMEZ; kabul edilmemis /
 * parasi gelmis / ilana uymayan / toplami tutmayan dokum reddedilir ve hicbir sey yazilmaz. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  HATA ").$n."\n"; };

$root = dirname(__DIR__);
$sand = sys_get_temp_dir().'/vestra_offcol_'.bin2hex(random_bytes(4));
mkdir($sand.'/data/invoices', 0777, true);
if (!defined('VESTRA_DATA_DIR')) define('VESTRA_DATA_DIR', $sand.'/data');
if (!defined('VESTRA_ACCOUNTS')) define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
require_once $root.'/vestra/inc/products.php';
require_once $root.'/vestra/inc/offers.php';
require_once $root.'/vestra/inc/orders.php';
require_once $root.'/vestra/inc/invoice.php';
if (vestra_data_dir() !== $sand.'/data') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }

/* SENTETIK veri: gercek musteri yok, SKU katalogda yok (yalniz kum havuzunun listings.json'unda). */
$d = $sand.'/data';
file_put_contents($d.'/listings.json', json_encode([
    ['id' => 'zz-col-hoodie', 'brand' => 'Test', 'name' => 'Test Hoodie', 'sku' => 'ZZ-COL-1', 'status' => 'approved',
     'mode' => 'sale', 'list' => 50, 'moq' => 40, 'size_step' => 10, 'min_colors' => 4,
     'sizes' => 'S×1 · M×3 · L×3 · XL×2 · XXL×1 · 10/pack', 'tiers' => [['min' => 40, 'price' => 50]],
     'colors' => ['Beige', 'Navy', 'Black', 'Light Blue', 'Green', 'Bordeaux', 'White', 'Blue']],
]));
$h = fopen($d.'/offers.csv', 'w');
fputcsv($h, ['timestamp','ref','sku','product','qty','offer_unit','offer_total','company','email','message'], ',', '"', '\\');
foreach ([['OCOL01', 80, 30], ['OCOL02', 40, 30], ['OPEND1', 40, 30], ['OPAID1', 40, 30], ['OINV01', 40, 30], ['OMEMB1', 40, 30], ['OPLAIN', 40, 30]] as [$ref, $q, $u]) {
    fputcsv($h, ['2026-10-19T02:47:00+00:00', $ref, 'ZZ-COL-1', 'Test Hoodie', $q, $u, $q * $u, 'Test SAS', 'buyer@example.com', 'test'], ',', '"', '\\');
}
fclose($h);
file_put_contents($d.'/offer_responses.json', json_encode([
    'OCOL01' => ['status' => 'accept', 'agreed_unit' => 35, 'invoice_vat_note' => 'Intra-Community supply', 'responded_at' => '2026-10-19T10:00:00+00:00'],
    'OCOL02' => ['status' => 'accept', 'agreed_unit' => 35],
    'OPEND1' => ['status' => 'counter', 'counter_price' => 38],
    'OPAID1' => ['status' => 'accept', 'invoice_paid_at' => '2026-10-18T10:00:00+00:00'],
    'OINV01' => ['status' => 'accept', 'invoice_members' => ['OINV01', 'OMEMB1']],
    'OMEMB1' => ['status' => 'accept', 'invoice_group_ref' => 'OINV01'],
    'OPLAIN' => ['status' => 'accept'],
], JSON_PRETTY_PRINT));
file_put_contents($d.'/invoices/OINV01__vestra.json', json_encode(['no' => 'INV-2026-9101', 'seller_key' => 'vestra', 'total' => 1200, 'currency' => 'EUR', 'issued_at' => '2026-10-19']));
file_put_contents($d.'/accounts.json', json_encode([['id' => 'b1', 'type' => 'buyer', 'email' => 'buyer@example.com', 'company' => 'Test SAS', 'country' => 'France']]));

$rec = fn(string $ref) => (array)(json_decode((string)file_get_contents($d.'/offer_responses.json'), true)[$ref] ?? []);
$all8 = ['Beige' => 10, 'Navy' => 10, 'Black' => 10, 'Light Blue' => 10, 'Green' => 10, 'Bordeaux' => 10, 'White' => 10, 'Blue' => 10];
$cells8 = array_map(fn($c) => vestra_offer_colour_cell($c, 10), ['Beige', 'Navy', 'Black', 'Light Blue', 'Green', 'Bordeaux', 'White', 'Blue']);
$backups = fn() => glob($d.'/offer_backups/O*-col-*.json') ?: [];
$fileBackups = fn() => glob($d.'/offer_backups/offer_responses-col-*.json') ?: [];

echo "== 0. DOKUMSUZ teklif: eski davranis (renk sutunu bos) ==\n";
$p = vestra_offer_invoice_payload('OPLAIN');
$t('dokum yoksa colors = []', ($p['items'][0]['colors'] ?? null) === []);

echo "\n== 1. KURU KOSU: plan doner, HICBIR SEY yazilmaz ==\n";
$before = (string)file_get_contents($d.'/offer_responses.json');
$r = vestra_offer_set_invoice_colours('OCOL01', ['beige' => 10, 'NAVY' => 10, 'Black' => 10, 'light blue' => 10, 'Green' => 10, 'Bordeaux' => 10, 'White' => 10, 'Blue' => 10], true);
$t('kuru kosu ok', !empty($r['ok']) && !empty($r['dry']));
$t('renk yazimi ILANDAN alinir (beige -> Beige, light blue -> Light Blue)', ($r['colours'] ?? []) === $all8);
$t('belge hucreleri "Renk ×adet"', ($r['cells'] ?? []) === $cells8);
$t('fatura yok -> must_redraft=false', ($r['must_redraft'] ?? null) === false);
$t('kayit dosyasi BAYT BAYT ayni', (string)file_get_contents($d.'/offer_responses.json') === $before);
$t('yedek YAZILMADI', $backups() === []);

echo "\n== 2. UYGULAMA: kayit + fatura yuku + yedek; adet/tutar DEGISMEZ ==\n";
$p0 = vestra_offer_invoice_payload('OCOL01');
$r = vestra_offer_set_invoice_colours('OCOL01', $all8);
$t('yazildi', !empty($r['ok']) && empty($r['dry']) && empty($r['error']));
$b = $rec('OCOL01');
$t('invoice_colours kayitta, ilan sirasiyla', ($b['invoice_colours'] ?? null) === $all8);
$t('iz: by=operator + tarih', ($b['invoice_colours_by'] ?? '') === 'operator' && strtotime((string)($b['invoice_colours_at'] ?? '')) > 0);
$t('kaydin DIGER alanlari korundu (durum, KDV notu, birim, yanit tarihi)',
   ($b['status'] ?? '') === 'accept' && ($b['invoice_vat_note'] ?? '') === 'Intra-Community supply'
   && (float)($b['agreed_unit'] ?? 0) === 35.0 && ($b['responded_at'] ?? '') === '2026-10-19T10:00:00+00:00');
$p = vestra_offer_invoice_payload('OCOL01');
$t('FATURA YUKU renkleri goruyor', ($p['items'][0]['colors'] ?? null) === $cells8);
$t('satir TEK, adet 80, birim ve tutar AYNI', count($p['items'] ?? []) === 1 && (int)$p['items'][0]['qty'] === 80
   && abs((float)$p['items'][0]['unit'] - (float)$p0['items'][0]['unit']) < 0.005
   && abs((float)$p['items'][0]['line'] - (float)$p0['items'][0]['line']) < 0.005);
$t('yuk notu/KDV satiri korundu', ($p['meta']['vat_note'] ?? '') === ($p0['meta']['vat_note'] ?? 'x'));
$t('yazici yukun gordugunu dondu', ($r['payload_colours'] ?? []) === $cells8);
$bk = $backups();
$t('yedek dosyasi VAR (offer_backups/<ref>-col-*)', count($bk) === 1 && str_contains($bk[0], '/offer_backups/OCOL01-col-'));
$bj = json_decode((string)@file_get_contents($bk[0] ?? ''), true);
$t('yedek DEGISIKLIKTEN ONCEKI kaydi tasiyor (dokum yok)', is_array($bj) && isset($bj['OCOL01']) && !array_key_exists('invoice_colours', $bj['OCOL01']));
$fb = $fileBackups();
$fj = json_decode((string)@file_get_contents($fb[0] ?? ''), true);
$t('BUTUN dosyanin yedegi de VAR ve tum kayitlari tasiyor', count($fb) === 1 && is_array($fj) && count($fj) === 7 && isset($fj['OPLAIN'], $fj['OINV01']));
$t('BASKA teklif degismedi (OCOL02 dokumsuz)', !array_key_exists('invoice_colours', $rec('OCOL02')));
$t('KESIM YOK: fatura dosyasi olusmadi', (glob($d.'/invoices/OCOL01__*') ?: []) === []);

echo "\n== 3. SIPARIS SATIRI notu renkleri tasir (siparis sayfasi / sheet okur) ==\n";
/* vestra_offer_order_ensure kum havuzunu DEGIL sitenin kendi data/ dizinini yaziyor
   (dirname(__DIR__).'/data'). Yerel kopya once alinir, okunur, sonra BAYT BAYT geri konur. */
$ordFile = $root.'/vestra/data/orders.csv';
$hadOrd = is_file($ordFile); $ordSnap = $hadOrd ? (string)file_get_contents($ordFile) : '';
$t('siparis satiri yazildi', vestra_offer_order_ensure($p, false));
$row = null;
if (($fh = @fopen($ordFile, 'r')) !== false) {
    $head = fgetcsv($fh, null, ',', '"', '\\');
    while (($x = fgetcsv($fh, null, ',', '"', '\\')) !== false) {
        $x = array_combine($head, array_slice(array_pad($x, count($head), ''), 0, count($head)));
        if (($x['ref'] ?? '') === 'OCOL01') $row = $x;
    }
    fclose($fh);
}
if ($hadOrd) file_put_contents($ordFile, $ordSnap); else @unlink($ordFile);
$t('yerel orders.csv geri kondu', $hadOrd ? (string)file_get_contents($ordFile) === $ordSnap : !is_file($ordFile));
$t('notta "Colours — ZZ-COL-1: ..." cumlesi', $row && str_contains((string)$row['notes'], 'Colours — ZZ-COL-1: Beige, Navy, Black, Light Blue, Green, Bordeaux, White, Blue.'));
$t('notta adet dokumu "ZZ-COL-1 colour split: Beige×10, ..."', $row && str_contains((string)$row['notes'], 'ZZ-COL-1 colour split: Beige×10, Navy×10'));
$m = vestra_order_notes_colors((string)($row['notes'] ?? ''));
$t('siparis okuyucusu (vestra_order_notes_colors) 8 rengi cozuyor', ($m['colors']['ZZ-COL-1'] ?? []) === array_keys($all8));
$t('siparis satiri adet/tutar ayni (80 x 35 = 2800)', $row && (string)$row['items'] === '80x ZZ-COL-1 @35.00' && (string)$row['total'] === '2800.00');

echo "\n== 4. RED -- ve reddedilen kayit DEGISMEZ ==\n";
$snap = (string)file_get_contents($d.'/offer_responses.json');
$t('kabul edilmemis teklif', (vestra_offer_set_invoice_colours('OPEND1', ['Beige' => 10, 'Navy' => 10, 'Black' => 10, 'Blue' => 10])['code'] ?? '') === 'not_accepted');
$t('parasi gelmis satis', (vestra_offer_set_invoice_colours('OPAID1', ['Beige' => 10, 'Navy' => 10, 'Black' => 10, 'Blue' => 10])['code'] ?? '') === 'paid');
$t('toplam teklif adedine esit degil (30 != 40)', (vestra_offer_set_invoice_colours('OCOL02', ['Beige' => 10, 'Navy' => 10, 'Black' => 10])['code'] ?? '') === 'sum');
$t('koli adiminin kati degil (15)', (vestra_offer_set_invoice_colours('OCOL02', ['Beige' => 15, 'Navy' => 5, 'Black' => 10, 'Blue' => 10])['code'] ?? '') === 'pack');
$t('ilanda olmayan renk', (vestra_offer_set_invoice_colours('OCOL02', ['Pink' => 10, 'Navy' => 10, 'Black' => 10, 'Blue' => 10])['code'] ?? '') === 'colour');
$t('ayni renk iki kez (buyuk/kucuk harf)', (vestra_offer_set_invoice_colours('OCOL02', ['Navy' => 10, 'navy' => 10, 'Black' => 10, 'Blue' => 10])['code'] ?? '') === 'dup');
$t('asgari renk sayisinin altinda (2 < 4)', (vestra_offer_set_invoice_colours('OCOL02', ['Navy' => 20, 'Black' => 20])['code'] ?? '') === 'min_colors');
$t('sifir / negatif adet', (vestra_offer_set_invoice_colours('OCOL02', ['Navy' => 0, 'Black' => 20, 'Blue' => 10, 'Beige' => 10])['code'] ?? '') === 'qty');
$t('olmayan ref', (vestra_offer_set_invoice_colours('OZZZZZ', $all8)['code'] ?? '') === 'missing');
$r = vestra_offer_set_invoice_colours('OINV01', ['Beige' => 10, 'Navy' => 10, 'Black' => 10, 'Blue' => 10]);
$t('faturali teklif opt-in OLMADAN reddedilir', ($r['code'] ?? '') === 'invoiced' && in_array('INV-2026-9101', (array)($r['invoices'] ?? []), true));
$t('ret KURAL 5f yolunu (invoice_draft) soyluyor', str_contains((string)($r['error'] ?? ''), 'invoice_draft'));
$r = vestra_offer_set_invoice_colours('OMEMB1', ['Beige' => 10, 'Navy' => 10, 'Black' => 10, 'Blue' => 10]);
$t('birlesik faturanin UYESI de birincilin faturasini gorur (opt-in ister)', ($r['code'] ?? '') === 'invoiced');
file_put_contents($d.'/order_statuses.json', json_encode(['OINV01' => ['status' => 'paid']]));
$r = vestra_offer_set_invoice_colours('OMEMB1', ['Beige' => 10, 'Navy' => 10, 'Black' => 10, 'Blue' => 10], false, true);
$t('UYE: birincilin siparisi "paid" ise opt-in ile de REDDEDILIR', ($r['code'] ?? '') === 'paid' && str_contains((string)($r['error'] ?? ''), 'OINV01'));
@unlink($d.'/order_statuses.json');
$t('adet dizi gelirse uyarisiz reddedilir', (vestra_offer_set_invoice_colours('OCOL02', ['Navy' => [10], 'Black' => 10, 'Blue' => 10, 'Beige' => 10])['code'] ?? '') === 'qty');
$t('reddedilen yazimlar kaydi DEGISTIRMEDI', (string)file_get_contents($d.'/offer_responses.json') === $snap);

echo "\n== 5. FATURALI teklif, opt-in ile: yazar ve YENIDEN CIZIM ister ==\n";
$r = vestra_offer_set_invoice_colours('OMEMB1', ['Beige' => 10, 'Navy' => 10, 'Black' => 10, 'Blue' => 10], false, true);
$t('yazildi', !empty($r['ok']));
$t('must_redraft=true, cizim birincilde (OINV01)', ($r['must_redraft'] ?? null) === true && ($r['redraw_ref'] ?? '') === 'OINV01');
$cp = vestra_offers_combined_invoice_payload(['OINV01', 'OMEMB1'], '', null, null, true);
$lines = []; foreach ((array)($cp['base']['items'] ?? $cp['items'] ?? []) as $it) $lines[(string)$it['name']] = $it['colors'];
$mem = null; $pri = null;
foreach ($lines as $n => $c) { if (str_contains($n, 'OMEMB1')) $mem = $c; if (str_contains($n, 'OINV01')) $pri = $c; }
$t('BIRLESIK yukte uyenin satiri renkleri tasiyor', $mem === array_map(fn($c) => vestra_offer_colour_cell($c, 10), ['Beige', 'Navy', 'Black', 'Blue']));
$t('BIRLESIK yukte birincilin satiri dokumsuz kaldi', $pri === []);

echo "\n== 5b. BELGE: adetli renkler aciklamanin altinda, tek sayfa ==\n";
$pi = vestra_offer_invoice_payload('OMEMB1');
$t('yuk kalemi colors_wide=true', !empty($pi['items'][0]['colors_wide']));
$pdfBytes = vestra_render_invoice_pdf($pi['meta'], $pi['items'], $pi['seller'] ?? [], '', true);
$t('PDF uretildi', is_string($pdfBytes) && strlen($pdfBytes) > 1000);
$pf = $sand.'/col.pdf'; file_put_contents($pf, $pdfBytes);
if (trim((string)shell_exec('command -v pdftotext')) !== '') {
    $txt = (string)shell_exec('pdftotext -layout '.escapeshellarg($pf).' - 2>/dev/null');
    $pages = (int)preg_match_all('/\f/', $txt) + 1;
    $t('renk sutununda "4 colours"', str_contains($txt, '4 colours'));
    $t('dokum tam: "Beige ×10, Navy ×10, Black ×10, Blue ×10" (adet rengin yaninda)',
       (bool)preg_match('/Beige\s×10,\s+Navy\s×10,\s+Black\s×10,\s+Blue\s×10/u', $txt));
    $t('belge TEK sayfa', $pages === 1 || trim((string)preg_replace('/^.*\f/s', '', $txt)) === '');
} else { echo "  (pdftotext yok -- metin denetimi atlandi)\n"; }
$po = vestra_offer_invoice_payload('OPLAIN');
$t('dokumsuz kalem colors_wide=false (siparis/eski belge degismez)', empty($po['items'][0]['colors_wide']));

echo "\n== 6. BOS dokum = KALDIR ==\n";
$r = vestra_offer_set_invoice_colours('OCOL01', []);
$t('kaldirildi', !empty($r['ok']) && !array_key_exists('invoice_colours', $rec('OCOL01')));
$t('yuk yine dokumsuz', (vestra_offer_invoice_payload('OCOL01')['items'][0]['colors'] ?? null) === []);
$t('onceki dokum raporlandi', ($r['prev'] ?? []) === $all8);

echo "\n$ok ok, $bad hata\n";
exit($bad ? 1 : 0);
