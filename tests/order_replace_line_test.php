<?php
/* Var olan bir siparişte bir kalemin MODELİNİ değiştirme
 * (vestra_order_replace_line, 28 Eyl 2026 — operatör: "VES-60594A18 bu siparişi
 * TENNIS-CLUB-ICON-WHITE bu model ile değiştir ve tutarı aynı olacak şekilde
 * müşteriye email gönder").
 *
 * KUM HAVUZUNDA GERÇEKTEN YAZIYOR ve doğrulama satırın değişmesine değil
 * BELGENİN KENDİSİNE bakıyor: fatura yükü (vestra_order_invoice_payloads) PDF'e
 * çizdiriliyor, yeni SKU orada VAR, eskisi YOK olmalı. Tek yön yazılsaydı "iki
 * kalemi birden basan" bir kusur da yeşil kalırdı.
 *
 * Fikstür canlı iki ilanın ŞEKLİ (Casablanca, 20+ → €69,90, MOQ 20, 10'luk seri,
 * tek renk) — adlar ve rakamlar inspect-products çıktısından, uydurulmadı. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra_orl_'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);

if (!defined('VESTRA_DATA_DIR')) define('VESTRA_DATA_DIR', $sand.'/data');
require_once $root.'/inc/products.php';
require_once $root.'/inc/orders.php';
require_once $root.'/inc/invoice.php';

if (vestra_data_dir() !== $sand.'/data') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }
if (!str_starts_with(vestra_invoice_dir(), $sand)) { fwrite(STDERR, "fatura dizini kum havuzunda degil\n"); exit(1); }

$series = 'S×1 · M×3 · L×3 · XL×2 · XXL×1 · 10 pcs/pack';
$listing = fn(string $id, string $sku, string $name, array $colors, array $extra = []) => $extra + [
    'id' => $id, 'sku' => $sku, 'brand' => 'Casablanca', 'name' => $name, 'cat' => 'T-Shirts',
    'mode' => 'sale', 'list' => 69.9, 'moq' => 20, 'sizes' => $series, 'size_step' => 10,
    'tiers' => [['min' => 20, 'price' => 69.9]], 'colors' => $colors, 'status' => 'approved',
    'seller_uid' => 'sel-1', 'images' => [],
];
file_put_contents($sand.'/data/listings.json', json_encode([
    $listing('csb-larche', 'LARCHE-COLORE-PRINTED-BLACK', "L'Arche Colore Printed T-Shirt — Black", ['Black']),
    $listing('csb-tennis', 'TENNIS-CLUB-ICON-WHITE', 'Tennis Club Icon T-Shirt — White', ['White']),
    $listing('csb-multi',  'MULTI-COLOUR-TEE', 'Multi Tee', ['White', 'Navy Blue']),
    $listing('csb-low',    'LOW-MOQ-TEE', 'Low MOQ Tee', ['Green'], ['moq' => 10, 'tiers' => [['min' => 10, 'price' => 69.9]]]),
    $listing('csb-sold',   'SOLD-TEE', 'Sold Tee', ['Red'], ['sold_out' => true]),
], JSON_UNESCAPED_UNICODE));

$head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal',
         'commission','payout','total','notes','consent','terms_version','voucher_code','discount',
         'shipping','shipping_label'];
$mk = function (string $ref, string $items, string $notes, float $goods, float $ship = 0.0) use ($sand, $head) {
    $f = $sand.'/data/orders.csv';
    $new = !is_file($f);
    $h = fopen($f, $new ? 'w' : 'a');
    if ($new) fputcsv($h, $head, ',', '"', '\\');
    fputcsv($h, [date('c'), $ref, 'Easy Test', 'FR1', 'Tester', 'e@example.com', 'France', '+33', $items,
                 number_format($goods, 2, '.', ''), '0.00', number_format($goods, 2, '.', ''),
                 number_format($goods + $ship, 2, '.', ''), $notes, 'operator', '2026-06-26', '', '',
                 number_format($ship, 2, '.', ''), $ship > 0 ? 'Shipping' : ''], ',', '"', '\\');
    fclose($h);
};
$row = function (string $ref): ?array {
    foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === $ref) return $x;
    return null;
};

/* VES-60594A18'in ŞEKLİ: order_write'ın yazdığı notlar + set_delivery'nin adresi. */
$realNotes = 'Payment: Bank transfer. Unit price agreed with the buyer, outside the listed tiers. Full cartons.'
           . ' Quantity below the listed minimum order, agreed as an exception.'
           . ' Colours — LARCHE-COLORE-PRINTED-BLACK: Black. Sizes — LARCHE-COLORE-PRINTED-BLACK: S×1, M×3, L×3, XL×2, XXL×1.'
           . ' Shipping EUR 20.00. Deliver to: Jean Test, 1 rue de Test 33140 Villenave, France.';
$mk('VES-R1', '10x LARCHE-COLORE-PRINTED-BLACK @50.00', $realNotes, 500.00, 20.00);

echo "== 0. Beden serisi yardımcısı ==\n";
$pT = vestra_product_by_sku('TENNIS-CLUB-ICON-WHITE');
$t('10 adet -> ilanın kendi 1-3-3-2-1 serisi', vestra_listing_size_run($pT, 10) === ['S×1', 'M×3', 'L×3', 'XL×2', 'XXL×1']);
$t('20 adet -> seri x2',                        vestra_listing_size_run($pT, 20) === ['S×2', 'M×6', 'L×6', 'XL×4', 'XXL×2']);
$t('15 adet (seri katı değil) -> BOŞ, uydurma yok', vestra_listing_size_run($pT, 15) === []);
$t('serisiz ilan -> BOŞ', vestra_listing_size_run(['sizes' => 'One size'], 10) === []);
/* Kalıp order_write'ın toptan dalıyla AYNI olmak zorunda (iki ayrı döküm yazılmasın). */
$wf = (string)@file_get_contents(dirname(__DIR__).'/.github/workflows/seller-products.yml');
$src = (string)file_get_contents($root.'/inc/orders.php');
$re  = "'/([0-9]{2,3}|XXXL|XXL|XL|XS|S|M|L)\\s*[×xX]\\s*([0-9]+)/u'";
$t('beden kalıbı order_write ile birebir aynı', str_contains($wf, $re) && str_contains($src, $re));

echo "\n== 1. KURU KOŞU hiçbir şey yazmaz, aynı sonucu verir ==\n";
$h0 = md5_file($sand.'/data/orders.csv');
$d = vestra_order_replace_line('VES-R1', 'LARCHE-COLORE-PRINTED-BLACK', 'TENNIS-CLUB-ICON-WHITE', ['dry' => true]);
$t('kuru koşu başarılı', empty($d['error']) && !empty($d['dry']));
$t('kuru koşu dosyaya DOKUNMADI', md5_file($sand.'/data/orders.csv') === $h0);
$t('kuru koşu damga yazmadı', !isset(vestra_read_json('order_statuses.json')['VES-R1']['line_changes']));
$t('kuru koşu: toplam aynı (520)', abs(($d['total'] ?? 0) - 520.00) < 0.005);

echo "\n== 2. Model değişti, TUTAR AYNI ==\n";
$r = vestra_order_replace_line('VES-R1', 'LARCHE-COLORE-PRINTED-BLACK', 'TENNIS-CLUB-ICON-WHITE');
$t('yazma başarılı', empty($r['error']));
$t('birim KORUNDU (anlaşılan 50, kademe 69.90 değil)', abs(($r['unit'] ?? 0) - 50.00) < 0.005);
$t('adet KORUNDU (10)', ($r['qty'] ?? 0) === 10);
$t('mal 500 -> 500', abs(($r['old_goods'] ?? 0) - 500) < 0.005 && abs(($r['goods'] ?? 0) - 500) < 0.005);
$t('toplam 520 -> 520', abs(($r['old_total'] ?? 0) - 520) < 0.005 && abs(($r['total'] ?? 0) - 520) < 0.005);
$t('navlun dokunulmadı (20)', abs(($r['shipping'] ?? 0) - 20) < 0.005);
$t('renk ilanın TEK renginden (White)', ($r['colours'] ?? []) === ['White']);
$t('beden yeni ilanın serisinden', ($r['sizes'] ?? []) === ['S×1', 'M×3', 'L×3', 'XL×2', 'XXL×1']);
$t('eski renk raporlandı (Black)', ($r['old_colours'] ?? []) === ['Black']);
$t('faturasız -> must_redraft FALSE', ($r['must_redraft'] ?? null) === false);
$t('feragat notları hâlâ doğru -> uyarı YOK', ($r['stale'] ?? ['x']) === []);
$t('yedek alındı', is_file($sand.'/data/'.($r['backup'] ?? '-')));

$b = $row('VES-R1');
$n = (string)($b['notes'] ?? '');
$t('items yeni SKU', trim((string)$b['items']) === '10x TENNIS-CLUB-ICON-WHITE @50.00');
$t('total kayıtta 520.00', trim((string)$b['total']) === '520.00');
$t('subtotal/payout 500.00', trim((string)$b['subtotal']) === '500.00' && trim((string)$b['payout']) === '500.00');
$t('eski SKU notlarda HİÇ geçmiyor', !str_contains($n, 'LARCHE'));
$t('Colours yeni anahtar', str_contains($n, 'Colours — TENNIS-CLUB-ICON-WHITE: White.'));
$t('Sizes yeni anahtar', str_contains($n, 'Sizes — TENNIS-CLUB-ICON-WHITE: S×1, M×3, L×3, XL×2, XXL×1.'));
$t('Colours ve Sizes TEK kez', substr_count($n, 'Colours —') === 1 && substr_count($n, 'Sizes —') === 1);
$t('Payment duruyor', str_contains($n, 'Payment: Bank transfer.'));
$t('anlaşılan fiyat notu duruyor', str_contains($n, 'Unit price agreed with the buyer, outside the listed tiers.'));
$t('asgari feragat notu duruyor', str_contains($n, 'Quantity below the listed minimum order, agreed as an exception.'));
$t('Shipping notu duruyor', str_contains($n, 'Shipping EUR 20.00.'));
$t('teslimat adresi AYNEN okunuyor', vestra_order_delivery_address($n) === 'Jean Test, 1 rue de Test 33140 Villenave, France');
$ln = vestra_order_lines($b)['lines'];
$t('okuyucu TEK kalem görüyor', count($ln) === 1);
$t('okuyucu: yeni SKU, White, beden serisi', ($ln[0]['sku'] ?? '') === 'TENNIS-CLUB-ICON-WHITE'
        && ($ln[0]['colors'] ?? []) === ['White'] && ($ln[0]['sizes'] ?? []) === ['S×1', 'M×3', 'L×3', 'XL×2', 'XXL×1']);
$lc = vestra_read_json('order_statuses.json')['VES-R1']['line_changes'] ?? [];
$t('değişiklik kaydı: from/to', count($lc) === 1 && ($lc[0]['from'] ?? '') === 'LARCHE-COLORE-PRINTED-BLACK'
        && ($lc[0]['to'] ?? '') === 'TENNIS-CLUB-ICON-WHITE');

echo "\n== 3. BELGE: yeni model VAR, eski YOK ==\n";
$pl = vestra_order_invoice_payloads('VES-R1');
$pdf = $pl ? vestra_render_invoice_pdf($pl[0]['meta'], $pl[0]['items'], null, 'INV-TEST-RL', false) : '';
/* SKU sütunu dar: uzun SKU iki satıra SARILIYOR ("TENNIS-CLUB-ICON-WH" / "ITE"),
   yani tam dizgeyi aramak doğru belgede de düşer — ilk yazımda düştü. Ölçü:
   SKU'nun sarılmayan öneki + açıklama sütunundaki ürün adı. Olumsuz iddia da
   İKİ yerden bakıyor; yalnız SKU'ya baksaydı sarılma onu boşa geçirebilirdi. */
$t('PDF üretildi, sıkıştırılmamış', $pdf !== '' && !str_contains($pdf, '/FlateDecode'));
$t('belgede yeni SKU öneki VAR', str_contains($pdf, 'TENNIS-CLUB-ICON'));
$t('belgede yeni ürün adı VAR', str_contains($pdf, 'Tennis Club Icon T-Shirt'));
$t('belgede yeni renk VAR (White)', str_contains($pdf, '(White)'));
$t('belgede eski SKU öneki YOK', !str_contains($pdf, 'LARCHE'));
$t('belgede eski ürün adı YOK', !str_contains($pdf, 'Colore Printed'));
$t('belgede toplam 520.00', str_contains($pdf, '520.00'));
/* Kontrol grubu: AYNI çizici eski siparişi basıyor olsaydı eski adı görürdük —
   yoksa "YOK" iddiası çizicinin hiçbir adı basmamasından da geçebilirdi. */
$ctl = vestra_render_invoice_pdf($pl[0]['meta'], array_map(fn($i) => ['sku' => 'LARCHE-COLORE-PRINTED-BLACK',
        'name' => "L'Arche Colore Printed T-Shirt", 'brand' => 'Casablanca', 'colors' => ['Black']] + $i, $pl[0]['items']),
        null, 'INV-TEST-CTL', false);
$t('kontrol: eski kalem basılınca belgede GÖRÜNÜR', str_contains($ctl, 'LARCHE') && str_contains($ctl, 'Colore Printed'));

echo "\n== 3b. vestra_pdf_drawn_text: sarılmış SKU bütünleşir ==\n";
$txt = vestra_pdf_drawn_text($pdf);
$t('ham baytta tam SKU YOK (sarıldığı için) — ölçünün sebebi', !str_contains($pdf, 'TENNIS-CLUB-ICON-WHITE'));
$t('çizilmiş metinde tam SKU VAR', str_contains($txt, 'TENNIS-CLUB-ICON-WHITE'));
$t('çizilmiş metinde eski SKU YOK', !str_contains($txt, 'LARCHE-COLORE-PRINTED-BLACK'));
$t('kontrol: eski kalemli belgede tam eski SKU VAR', str_contains(vestra_pdf_drawn_text($ctl), 'LARCHE-COLORE-PRINTED-BLACK'));
/* Aynı öneki paylaşan iki SKU: önekle aramak ikisini ayıramazdı. */
$nav = vestra_render_invoice_pdf($pl[0]['meta'], array_map(fn($i) => ['sku' => 'TENNIS-CLUB-ICON-NAVYBLUE'] + $i, $pl[0]['items']),
        null, 'INV-TEST-NV', false);
$tn = vestra_pdf_drawn_text($nav);
$t('ortak önek: NAVYBLUE belgesinde WHITE YOK', str_contains($tn, 'TENNIS-CLUB-ICON-NAVYBLUE') && !str_contains($tn, 'TENNIS-CLUB-ICON-WHITE'));
$t('görsel akışı atlanıyor (JPEG metne karışmıyor)', !str_contains($txt, 'JFIF') && !str_contains($txt, "\xFF\xD8"));
$t('sıkıştırılmış belge -> "" (ölçülemedi, "yok" değil)', vestra_pdf_drawn_text("x /FlateDecode y") === '');

echo "\n== 4. Faturalı sipariş: opt-in yoksa RED, varsa must_redraft ==\n";
$mk('VES-R2', '10x LARCHE-COLORE-PRINTED-BLACK @50.00', 'Payment: Bank transfer. Colours — LARCHE-COLORE-PRINTED-BLACK: Black.', 500.00, 20.00);
@mkdir(vestra_invoice_dir(), 0777, true);
file_put_contents(vestra_invoice_dir().'/VES-R2__vestra.json',
    json_encode(['no' => 'INV-TEST-2', 'seller_key' => 'vestra', 'total' => 520.0, 'currency' => 'EUR']));
$h2 = md5_file($sand.'/data/orders.csv');
$i1 = vestra_order_replace_line('VES-R2', 'LARCHE-COLORE-PRINTED-BLACK', 'TENNIS-CLUB-ICON-WHITE');
$t('opt-in OLMADAN reddedildi', !empty($i1['error']) && str_contains((string)$i1['error'], 'KURAL 5f'));
$t('reddedilene HİÇBİR ŞEY yazılmadı', md5_file($sand.'/data/orders.csv') === $h2);
$i2 = vestra_order_replace_line('VES-R2', 'LARCHE-COLORE-PRINTED-BLACK', 'TENNIS-CLUB-ICON-WHITE', [], true);
$t('opt-in ile yazıldı', empty($i2['error']));
$t('must_redraft TRUE', ($i2['must_redraft'] ?? null) === true);

echo "\n== 5. Retler ==\n";
$mk('VES-R3', '10x LARCHE-COLORE-PRINTED-BLACK @50.00 | 20x TENNIS-CLUB-ICON-WHITE @60.00', 'Payment: Bank transfer.', 1700.00);
$e = vestra_order_replace_line('VES-R3', 'LARCHE-COLORE-PRINTED-BLACK', 'TENNIS-CLUB-ICON-WHITE');
$t('yeni SKU zaten siparişte -> RED', !empty($e['error']) && str_contains((string)$e['error'], 'zaten'));
$e = vestra_order_replace_line('VES-R1', 'NOPE', 'LOW-MOQ-TEE');
$t('eski SKU siparişte yok -> RED', !empty($e['error']));
$e = vestra_order_replace_line('VES-R1', 'TENNIS-CLUB-ICON-WHITE', 'DOES-NOT-EXIST');
$t('yeni SKU katalogda yok -> RED', !empty($e['error']));
$e = vestra_order_replace_line('VES-R1', 'TENNIS-CLUB-ICON-WHITE', 'TENNIS-CLUB-ICON-WHITE');
$t('aynı SKU -> RED', !empty($e['error']));
$e = vestra_order_replace_line('VES-R1', 'TENNIS-CLUB-ICON-WHITE', 'LARCHE-COLORE-PRINTED-BLACK', ['unit' => 75.00]);
$t('birim ilandan PAHALI -> RED (alıcı aleyhine)', !empty($e['error']) && str_contains((string)$e['error'], 'pahalı'));
$e = vestra_order_replace_line('VES-R1', 'TENNIS-CLUB-ICON-WHITE', 'MULTI-COLOUR-TEE');
$t('çok renkli ilan, renk verilmedi -> RED (tahmin yok)', !empty($e['error']) && str_contains((string)$e['error'], 'renk belirtilmeli'));
$e = vestra_order_replace_line('VES-R1', 'TENNIS-CLUB-ICON-WHITE', 'MULTI-COLOUR-TEE', ['colours' => ['Navy Blue'], 'dry' => true]);
$t('çok renkli ilan, renk verildi -> geçer', empty($e['error']) && ($e['colours'] ?? []) === ['Navy Blue']);
$st = vestra_read_json('order_statuses.json'); $st['VES-R3']['status'] = 'shipped'; vestra_write_json('order_statuses.json', $st);
$e = vestra_order_replace_line('VES-R3', 'TENNIS-CLUB-ICON-WHITE', 'LOW-MOQ-TEE');
$t('gönderilmiş sipariş -> RED', !empty($e['error']) && str_contains((string)$e['error'], 'shipped'));

echo "\n== 5b. Parası gelmiş sipariş: tutar aynıysa GEÇER, değişiyorsa RED ==\n";
$mk('VES-R5', '10x LARCHE-COLORE-PRINTED-BLACK @50.00', 'Payment: Bank transfer. Colours — LARCHE-COLORE-PRINTED-BLACK: Black.', 500.00, 20.00);
$st = vestra_read_json('order_statuses.json'); $st['VES-R5']['status'] = 'paid'; vestra_write_json('order_statuses.json', $st);
$p5 = vestra_order_replace_line('VES-R5', 'LARCHE-COLORE-PRINTED-BLACK', 'TENNIS-CLUB-ICON-WHITE', ['unit' => 45.00, 'dry' => true]);
$t('ödenmiş + tutar değişiyor -> RED', !empty($p5['error']) && str_contains((string)$p5['error'], 'parası gelmiş'));
$p5b = vestra_order_replace_line('VES-R5', 'LARCHE-COLORE-PRINTED-BLACK', 'TENNIS-CLUB-ICON-WHITE', ['dry' => true]);
$t('ödenmiş + tutar AYNI -> geçer (değişim meşru)', empty($p5b['error']) && abs(($p5b['total'] ?? 0) - 520) < 0.005);

echo "\n== 6. Uyarılar (silinmez, söylenir) ==\n";
$w = vestra_order_replace_line('VES-R1', 'TENNIS-CLUB-ICON-WHITE', 'LOW-MOQ-TEE', ['dry' => true]);
$t('adet yeni MOQ\'yu karşılıyor -> "asgarinin altında" notu için UYARI',
   empty($w['error']) && count(array_filter((array)($w['stale'] ?? []), fn($s) => str_contains($s, 'MOQ'))) === 1);
$w = vestra_order_replace_line('VES-R1', 'TENNIS-CLUB-ICON-WHITE', 'SOLD-TEE', ['dry' => true]);
$t('satılmış ilan -> UYARI', empty($w['error']) && count(array_filter((array)($w['stale'] ?? []), fn($s) => str_contains($s, 'SATILDI'))) === 1);

echo "\n== 7. Ayrıştırılamayan segment ve colour split ==\n";
$mk('VES-R4', 'garbage-seg | 10x LARCHE-COLORE-PRINTED-BLACK @50.00 | 5x SOLD-TEE @20.00',
    'Payment: Bank transfer. LARCHE-COLORE-PRINTED-BLACK colour split: Black×10. SOLD-TEE colour split: Red×5.'
    .' Colours — LARCHE-COLORE-PRINTED-BLACK: Black | SOLD-TEE: Red.', 600.00);
$r4 = vestra_order_replace_line('VES-R4', 'LARCHE-COLORE-PRINTED-BLACK', 'TENNIS-CLUB-ICON-WHITE');
$b4 = $row('VES-R4');
$t('yazıldı', empty($r4['error']));
$t('ayrıştırılamayan segment AYNEN kaldı', str_starts_with((string)$b4['items'], 'garbage-seg | '));
$t('öteki kalem AYNEN kaldı', str_contains((string)$b4['items'], '5x SOLD-TEE @20.00'));
$t('eski SKU\'nun colour split cümlesi SİLİNDİ', !str_contains((string)$b4['notes'], 'LARCHE'));
$t('öteki SKU\'nun colour split cümlesi DURUYOR', str_contains((string)$b4['notes'], 'SOLD-TEE colour split: Red×5.'));
$t('öteki SKU\'nun rengi DURUYOR', str_contains((string)$b4['notes'], 'SOLD-TEE: Red'));

echo "\n== 8. Mektup (vestra_tpl_order_item_changed) ==\n";
require_once $root.'/inc/email_templates.php';
$fig = ['old_label' => 'Casablanca Old Tee — Black', 'new_label' => 'Casablanca New Tee — White', 'qty' => 10,
        'colours' => ['White'], 'sizes' => ['S×1', 'M×3'], 'unit' => 47.5, 'goods' => 475.0, 'shipping' => 20.0,
        'total' => 495.0, 'currency' => 'EUR'];
[$sFr, $bFr, $oFr] = vestra_tpl_order_item_changed('Jean Test', 'VES-X', $fig, 'INV-9', true, true, 'Marco Bellini', 'fr');
$t('fr: konu model + tutar aynı', $sFr === 'Commande VES-X — modèle modifié, montant inchangé');
$t('fr: hitap', str_starts_with($bFr, "Bonjour Jean Test,\n"));
$t('fr: rakamlar PARAMETREDEN (47,50 / 475,00 / 495,00)', str_contains($bFr, '10 × 47,50 € = 475,00 €') && str_contains($bFr, 'Total : 495,00 €'));
$t('fr: eski ve yeni model adıyla', str_contains($bFr, 'Casablanca New Tee — White') && str_contains($bFr, 'Casablanca Old Tee — Black'));
$t('fr: güncellendi cümlesi (bayrak açık)', str_contains($bFr, 'a été mise à jour avec ce modèle et conserve son numéro'));
$t('fr: "banka bilgileri değişmedi" cümlesi', str_contains($bFr, 'Les coordonnées bancaires ne changent pas'));
$t('fr: TEK düğme, sipariş sayfası', ($oFr['button']['url'] ?? '') === 'https://vestrasales.com/buyer?tab=orders&view=VES-X');
$t('gövdede bağlantı YOK (tek bağlantı düğme)', !preg_match('~https?://~', $bFr));
$t('gövdede IBAN/banka numarası YOK', !preg_match('/\b[A-Z]{2}\d{2}[A-Z0-9]{10,}\b/', $bFr) && stripos($bFr, 'IBAN') === false);
[, $bFrN] = vestra_tpl_order_item_changed('Jean Test', 'VES-X', $fig, 'INV-9', false, true, '', 'fr');
$t('fr: bayrak kapalı -> "güncellendi" DENMEZ', !str_contains($bFrN, 'mise à jour') && str_contains($bFrN, 'indique ce modèle'));
[, $bNoInv] = vestra_tpl_order_item_changed('Jean Test', 'VES-X', $fig, '', false, true, '', 'fr');
$t('faturasız: banka cümlesi YOK (gösterecek hesap yok)', !str_contains($bNoInv, 'coordonnées bancaires'));
[$sEn, $bEn] = vestra_tpl_order_item_changed('', 'VES-X', $fig, 'INV-9', true, false, '', 'xx');
$t('tanınmayan dil -> en', $sEn === 'Order VES-X — model changed, amount unchanged' && str_contains($bEn, '€47.50'));
$t('en: ad yoksa nötr hitap', str_starts_with($bEn, "Dear Customer,\n"));
[, $bDe] = vestra_tpl_order_item_changed('Hans', 'VES-X', $fig, 'INV-9', true, true, '', 'de');
$t('de: ondalık virgül + binlik nokta biçimi', str_contains($bDe, '475,00 €') && str_starts_with($bDe, "Guten Tag Hans,\n"));
$fig2 = $fig; $fig2['currency'] = 'USD';
[, $bUsd] = vestra_tpl_order_item_changed('X', 'VES-X', $fig2, 'INV-9', true, true, '', 'en');
$t('birim metne gömülü değil (USD kayıtta USD basar)', str_contains($bUsd, 'US$495.00') && !str_contains($bUsd, '€'));

echo "\n== 9. Kablolama ==\n";
$wfL = (string)@file_get_contents(dirname(__DIR__).'/.github/workflows/send-campaign-preview.yml');
$br  = substr($wfL, (int)strpos($wfL, "\$letter === 'order_item_changed'"), 9000);
$t('mektup dalı var', str_contains($wfL, "\$letter === 'order_item_changed'"));
$t('dal belgeyi çizilmiş metinden ölçüyor (tam SKU)', str_contains($br, 'vestra_pdf_drawn_text(') && str_contains($br, 'str_contains($pdfT, $toS)')
    && str_contains($br, 'str_contains($pdfT, $fromS)'));
$t('dal: eski model belgede ise DURUYOR', (bool)preg_match('/if \(!\$newIn \|\| \$oldIn\) \{\s*fwrite\(STDERR/', $br));
$t('dal: değişiklik kaydı yoksa DURUYOR', str_contains($br, "if (\$toS === '') { fwrite(STDERR"));
$wfS = (string)@file_get_contents(dirname(__DIR__).'/.github/workflows/seller-products.yml');
$st2 = substr($wfS, (int)strpos($wfS, "admin_mode == 'order_replace_line'"), 7000);
$t('iş akışı adımı var', str_contains($wfS, "admin_mode == 'order_replace_line'"));
$t('kuru koşu yazıcının KENDİ dry kipi', str_contains($st2, "\$opt + ['dry' => true]"));
$t('uygulama move_apply ile', str_contains($st2, "if (!\$APPLY) {"));

/* Temizlik */
$rm = function ($p) use (&$rm) { if (is_dir($p)) { foreach (array_diff(scandir($p), ['.', '..']) as $f) $rm("$p/$f"); rmdir($p); } elseif (is_file($p)) unlink($p); };
$rm($sand);

echo "\nsonuc: {$ok} ok, {$bad} FAIL\n";
exit($bad ? 1 : 0);
