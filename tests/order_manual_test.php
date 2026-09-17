<?php
/* OPERATORUN ELLE KURDUGU SIPARIS (operator, 8 Eyl 2026: dort ilandan secilen
 * bedenler, dropship fiyati, kargo 30 EUR, tek hesaba fatura).
 *
 * Bu depoda siparis yazmanin yalnizca iki yolu vardi (alicinin kasasi ve kabul
 * edilmis teklif); operatorun elle kurdugu satis icin hicbir yol yoktu.
 * Fonksiyon PARA yaziyor, o yuzden hem mutlu yol hem retler tutuluyor.
 */
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/products.php';
require_once $root . '/inc/orders.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

/* Gercek orders.csv'ye DOKUNULMAZ: kum havuzu data dizini kurulup sonunda
   geri aliniyor. Bir testin canli sipariş dosyasina satir eklemesi, testin
   kendisini bir vakaya cevirirdi. */
$dataDir = $root . '/data';
$backup  = $dataDir . '/orders.csv.testbak-' . getmypid();
$had     = is_file($dataDir . '/orders.csv');
if (!is_dir($dataDir)) @mkdir($dataDir, 0775, true);
if ($had) @rename($dataDir . '/orders.csv', $backup);
$restore = function () use ($dataDir, $backup, $had) {
    @unlink($dataDir . '/orders.csv');
    if ($had && is_file($backup)) @rename($backup, $dataDir . '/orders.csv');
};

$acc = ['company' => 'Test Co', 'vat_id' => 'RO123', 'name' => 'Tester',
        'email' => 't@example.com', 'country' => 'Romania', 'phone' => '+40'];

echo "== 1. Retler: para yazmadan once duruyor ==\n";
$t('hesapsiz reddediyor',      isset(vestra_order_create_manual([], [['sku'=>'A','qty'=>1,'unit'=>10]])['error']));
$t('e-postasiz reddediyor',    isset(vestra_order_create_manual(['company'=>'X'], [['sku'=>'A','qty'=>1,'unit'=>10]])['error']));
$t('kalemsiz reddediyor',      isset(vestra_order_create_manual($acc, [])['error']));
$t('sifir fiyat reddediyor',   isset(vestra_order_create_manual($acc, [['sku'=>'A','qty'=>1,'unit'=>0]])['error']));
$t('sku bos reddediyor',       isset(vestra_order_create_manual($acc, [['sku'=>'','qty'=>1,'unit'=>5]])['error']));
/* Ayni SKU iki farkli fiyatla: items kolonunda tek satira toplanacagi icin
   biri sessizce kaybolurdu -- rakam kaybettirmektense reddediyor. */
$t('ayni SKU iki fiyat reddediyor',
   isset(vestra_order_create_manual($acc, [['sku'=>'A','qty'=>1,'unit'=>10],['sku'=>'A','qty'=>1,'unit'=>12]])['error']));
$t('ret hicbir satir yazmadi', !is_file($dataDir . '/orders.csv') || count(vestra_read_csv('orders.csv')) === 0);

echo "\n== 2. Operatorun gercek siparisi ==\n";
$lines = [
    ['sku'=>'S74GD1399','size'=>'M','qty'=>1,'unit'=>47.88],
    ['sku'=>'S74GD1399','size'=>'L','qty'=>1,'unit'=>47.88],
    ['sku'=>'S74GD1399','size'=>'XL','qty'=>1,'unit'=>47.88],
    ['sku'=>'662853TJW90','size'=>'L','qty'=>1,'unit'=>157.08],
    ['sku'=>'CMAA018F20JER0011016','size'=>'XL','qty'=>1,'unit'=>71.88],
    ['sku'=>'VS-MB-004','size'=>'M','qty'=>1,'unit'=>71.88],
];
$r = vestra_order_create_manual($acc, $lines, 30.00, 'Dropship pricing (wholesale +20%), single pieces.');
$t('siparis olustu',            !empty($r['ok']));
$t('ref VES- ile basliyor',     str_starts_with((string)($r['ref'] ?? ''), 'VES-'));
$t('mal toplami 444.48',        abs((float)($r['goods'] ?? 0) - 444.48) < 0.005);
$t('kargo 30.00',               abs((float)($r['shipping'] ?? 0) - 30.00) < 0.005);
$t('TOPLAM 474.48',             abs((float)($r['total'] ?? 0) - 474.48) < 0.005);

echo "\n== 3. Satirin kendisi ==\n";
$row = null;
foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === $r['ref']) { $row = $x; break; }
$t('satir dosyada',             $row !== null);
$t('toplam kayitta da 474.48',  $row && trim((string)$row['total']) === '474.48');
$t('kargo AYRI kolonda',        $row && trim((string)$row['shipping']) === '30.00');
$t('mal kolonu kargosuz',       $row && trim((string)$row['subtotal']) === '444.48');
/* Ayni SKU'nun uc bedeni items'ta TEK satira toplanmali; ayristirici
   "Nx SKU @fiyat" bekliyor ve araya beden sokmak onu bozardi. */
$t('ayni SKU tek satirda 3x',   $row && str_contains((string)$row['items'], '3x S74GD1399 @47.88'));
$t('items 4 SKU tasiyor',       $row && count(explode(' | ', (string)$row['items'])) === 4);
/* BICIM, KASANIN BICIMI. Eski iddia `S74GD1399: M×1 · L×1 · XL×1` diye YAZIMI
   sabitliyordu ve tam da kusurun kendisini koruyordu: her SKU kendi NOKTASIYLA
   kapaniyor, degerler '·' ile ayriliyordu. vestra_order_notes_map() ILK noktaya
   kadar okuyup ',' ile boluyor, yani IKINCI SKU'dan itibaren dokum SESSIZCE
   kayboluyordu -- ustelik kalintisi serbest metinde kalip alicinin siparis
   sayfasina ve panele oldugu gibi basiliyordu. Iddia artik yazimi degil
   OLGUYU tutuyor: parca AYRISTIRICIDAN GERI OKUNABILIYOR mu, ve DORT SKU'nun
   dordu de. */
$t('beden dokumu NOTTA',        $row && str_contains((string)$row['notes'], 'S74GD1399: M×1, L×1, XL×1'));
$backMap = vestra_order_notes_colors((string)($row['notes'] ?? ''));
$t('beden parcasi GERI OKUNUYOR — dort SKU da', count($backMap['sizes'] ?? []) === 4);
$t('ikinci SKU kaybolmuyor',    isset($backMap['sizes']['662853TJW90']));
$t('sonuncu SKU kaybolmuyor',   isset($backMap['sizes']['VS-MB-004']));
$t('degerler LISTE, tek blob degil', ($backMap['sizes']['S74GD1399'] ?? []) === ['M×1','L×1','XL×1']);
/* Parca cikarilinca serbest metinde KALINTI kalmamali: "M7535: S×5." gibi bir
   dizge alicinin siparis sayfasinda oldugu gibi basilirdi. */
$t('serbest metinde beden kalintisi yok', !preg_match('/\b(662853TJW90|VS-MB-004):/u', (string)($backMap['notes'] ?? '')));
$t('dropship notu var',         $row && str_contains((string)$row['notes'], 'wholesale +20%'));
$t('havale notu var',           $row && str_contains((string)$row['notes'], 'Bank transfer'));
$t('kaynak operator',           $row && trim((string)$row['consent']) === 'operator');

echo "\n== 4. Panelin okudugu haliyle ==\n";
/* Fatura ve butun paneller satirlari BU fonksiyondan okuyor; yazdigimiz
   bicim onun ayristiricisindan gecmezse siparis her ekranda bos gorunur. */
/* vestra_order_lines() DUZ dizi degil ['lines'=>..,'notes'=>..] donduruyor;
   ilk yazimda count($parsed) yaziverdim ve 2 saydi. Iddia dustu ve dogru
   dustu -- kod degil, benim okumam yanlisti. */
$parsed = vestra_order_lines($row ?? []);
$t('donen yapi lines/notes',      isset($parsed['lines']) && isset($parsed['notes']));
$pl = $parsed['lines'] ?? [];
$t('ayristirici 4 satir okudu',   count($pl) === 4);
$sum = 0.0; foreach ($pl as $one) $sum += (float)($one['line'] ?? 0);
$t('ayristirilan mal toplami 444.48', abs($sum - 444.48) < 0.005);
/* Katalogda olmayan SKU satiri DUSMEZ, "artik listede yok" diye gecer --
   gecmis siparis her ekranda okunabilir kalmali. */
$t('bilinmeyen SKU satiri dusmuyor', count($pl) === 4);

echo "\n== 5. Ikinci cagri AYRI ref uretir ==\n";
$r2 = vestra_order_create_manual($acc, [['sku'=>'A','size'=>'M','qty'=>2,'unit'=>5.00]], 0.0);
$t('ikinci siparis olustu',     !empty($r2['ok']));
$t('ref cakismiyor',            ($r2['ref'] ?? '') !== ($r['ref'] ?? ''));
$t('kargosuz toplam 10.00',     abs((float)($r2['total'] ?? 0) - 10.00) < 0.005);
$t('kargo kolonu bos',          (function() use ($r2) {
        foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === $r2['ref']) return trim((string)$x['shipping']) === '';
        return false; })());

/* ─────────────────────────────────────────────────────────────────────────
   6. RENK — elle kurulan siparis rengi HIC yazamiyordu (17 Eyl 2026).
   Kasa `Colours — …` parcasini bastan beri yaziyor ve vestra_order_lines()
   onu siparis tablosuna, siparis PDF'ine ve faturanin toplama listesine
   basiyor. Elle kurulan sipariste bu alan yoktu: bir lot "siyah" mi "bordo"
   mu, satisin kendi kaydinda hicbir yerde durmuyordu.

   IKI YON de tutuluyor: renk YAZILDIGINDA geri okunmali, renk VERILMEDIGINDE
   parca hic basilmamali -- bos bir "Colours — ." parcasi ayristiriciyi
   yaniltir ve her siparise anlamsiz bir satir ekler.
   ───────────────────────────────────────────────────────────────────────── */
echo "\n== 6. Renk notta ==\n";
$r3 = vestra_order_create_manual($acc, [
    ['sku'=>'M3600', 'colour'=>'Black',    'size'=>'S×5, M×15', 'qty'=>50, 'unit'=>39.00],
    ['sku'=>'M7535', 'colour'=>'Bordeaux', 'size'=>'S×5, M×15', 'qty'=>50, 'unit'=>39.90],
], 20.00, 'Full cartons, agreed with the buyer.');
$t('renkli siparis olustu', !empty($r3['ok']));
$row3 = null;
foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === ($r3['ref'] ?? '')) { $row3 = $x; break; }
$m3 = vestra_order_notes_colors((string)($row3['notes'] ?? ''));
$t('renk parcasi GERI OKUNUYOR',      ($m3['colors']['M3600'] ?? []) === ['Black']);
$t('ikinci SKU rengi de okunuyor',    ($m3['colors']['M7535'] ?? []) === ['Bordeaux']);
$t('beden dokumu ADETLE CARPILMIYOR', ($m3['sizes']['M3600'] ?? []) === ['S×5','M×15']);
$t('renk ve beden AYRI parca',        count($m3['colors']) === 2 && count($m3['sizes']) === 2);
/* Panelin/faturanin gercekten gordugu yol. */
$pl3 = vestra_order_lines($row3 ?? [])['lines'] ?? [];
$t('vestra_order_lines rengi tasiyor',
   count($pl3) === 2 && ($pl3[0]['colors'] ?? []) === ['Black'] && ($pl3[1]['colors'] ?? []) === ['Bordeaux']);
/* TERS YON: renk verilmeyen sipariste parca HIC basilmamali. */
$r4 = vestra_order_create_manual($acc, [['sku'=>'A','size'=>'M','qty'=>1,'unit'=>5.00]], 0.0);
$row4 = null;
foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === ($r4['ref'] ?? '')) { $row4 = $x; break; }
$t('renksiz sipariste Colours parcasi YOK', $row4 && !str_contains((string)$row4['notes'], 'Colours'));
/* Deger icindeki NOKTA parcayi erken kapatir ve gerisini serbest metne doker. */
$r5 = vestra_order_create_manual($acc, [['sku'=>'A','colour'=>'Navy 2.0','qty'=>1,'unit'=>5.00]], 0.0);
$row5 = null;
foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === ($r5['ref'] ?? '')) { $row5 = $x; break; }
$m5 = vestra_order_notes_colors((string)($row5['notes'] ?? ''));
$t('degerdeki nokta parcayi bozmuyor', ($m5['colors']['A'] ?? []) === ['Navy 20']);

$restore();
echo "\n--- $ok gecti, $fail kaldi ---\n";
exit($fail ? 1 : 0);
