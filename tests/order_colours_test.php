<?php
/* Siparisin RENGINI sonradan duzeltme (vestra_order_set_colours, 17 Eyl 2026).
 *
 * NEDEN KUM HAVUZUNDA GERCEKTEN YAZIYOR: bu depoda renk notunun kalibi
 * YILLARCA hicbir gercek siparise uymadi ve kimse fark etmedi -- cunku okuma
 * tarafi tek basina kendini dogrulayamiyor. Yazma tarafi eklendiginde gorunur
 * oldu. Bu test de ayni sekilde calisir: YAZAR ve FATURANIN GORDUGUNU okur. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra_oc_'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);

/* Kum havuzu: data dizinini kendi klasorumuze cevirip gercek katalogu
   dokunmadan birakiyoruz. */
if (!defined('VESTRA_DATA_DIR')) define('VESTRA_DATA_DIR', $sand.'/data');
require_once $root.'/inc/products.php';
require_once $root.'/inc/orders.php';

if (!function_exists('vestra_data_dir') || vestra_data_dir() !== $sand.'/data') {
    fwrite(STDERR, "kum havuzu kurulamadi (vestra_data_dir=".(function_exists('vestra_data_dir') ? vestra_data_dir() : '?').")\n");
    exit(1);
}

$head = ['ref','created','company','email','items','subtotal','shipping','total','status','notes'];
$mk = function (string $ref, string $notes) use ($sand, $head) {
    $f = $sand.'/data/orders.csv';
    $new = !is_file($f);
    $h = fopen($f, $new ? 'w' : 'a');
    if ($new) fputcsv($h, $head, ',', '"', '\\');
    fputcsv($h, [$ref, date('c'), 'Test SL', 'x@example.com', '2x SKU1 @10.00', '20.00', '5.00', '25.00', 'pending', $notes], ',', '"', '\\');
    fclose($h);
};

/* Gercek bir siparisin notu: order.php notlari HER ZAMAN "Payment:" ile aciyor
   ve renk parcasi ORTADA duruyor. Uydurma bir dizge ("Colours" ile BASLAYAN)
   bu depoda bir kez testi yesil tutup canlida hic calismamisti. */
$real = 'Payment: bank transfer. Deliver to: Calle 1, Madrid. Colours — SKU1: White. Sizes — SKU1: M, L.';
$mk('VES-T1', $real);

echo "== 1. renk DEGISTI ve FATURANIN GORDUGU degisti ==\n";
$r = vestra_order_set_colours('VES-T1', 'SKU1', ['Black', 'Navy']);
$t('yazma basarili', empty($r['error']));
$t('eski renk raporlandi', ($r['before'] ?? []) === ['White']);
$t('yeni renk kayitta', ($r['colours'] ?? []) === ['Black', 'Navy']);
$t('FATURANIN gordugu ayni', array_map('strval', (array)($r['on_invoice'] ?? [])) === ['Black', 'Navy']);

echo "\n== 2. notlarin GERISINE dokunulmadi ==\n";
$n = (string)($r['notes'] ?? '');
$t('Payment parcasi duruyor',   str_contains($n, 'Payment: bank transfer.'));
$t('Deliver to parcasi duruyor', str_contains($n, 'Deliver to: Calle 1, Madrid.'));
$t('Sizes parcasi duruyor',      str_contains($n, 'Sizes — SKU1: M, L.'));
$t('Colours parcasi TEK kez',    substr_count($n, 'Colours —') === 1);
/* Beden kaybolursa alici siparis sayfasinda ne aldigini goremez; bu, renk
   kaybinin birebir kardesi ve ayni fonksiyonun ayristiricisindan geciyor. */
$back = null; foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === 'VES-T1') $back = $x;
$ln = vestra_order_lines($back)['lines'][0] ?? [];
$t('beden hala okunuyor', ($ln['sizes'] ?? []) === ['M', 'L']);

echo "\n== 3. COK SKU'lu sipariste digerine dokunulmuyor ==\n";
$mk('VES-T2', 'Payment: card. Colours — SKU1: Red | SKU2: Blue, Green.');
$r2 = vestra_order_set_colours('VES-T2', 'SKU1', ['Black']);
$t('hedef SKU degisti', ($r2['colours'] ?? []) === ['Black']);
$b2 = null; foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === 'VES-T2') $b2 = $x;
$cn = vestra_order_notes_colors((string)$b2['notes']);
$t('DIGER SKU aynen duruyor', ($cn['colors']['SKU2'] ?? []) === ['Blue', 'Green']);

echo "\n== 4. reddetmeler ==\n";
$t('bilinmeyen ref REDDEDILDI', !empty(vestra_order_set_colours('VES-YOK', 'SKU1', ['Black'])['error']));
$t('bos renk listesi REDDEDILDI', !empty(vestra_order_set_colours('VES-T1', 'SKU1', ['  '])['error']));
$t('renk adinda virgul REDDEDILDI', !empty(vestra_order_set_colours('VES-T1', 'SKU1', ['Bl,ack'])['error']));
$t('sku bos REDDEDILDI', !empty(vestra_order_set_colours('VES-T1', '', ['Black'])['error']));
/* Reddedilen bir cagri HICBIR SEY yazmamali: yarim uygulanan bir yazma,
   operatorun gormedigi bir degisiklik birakirdi. */
$b3 = null; foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === 'VES-T1') $b3 = $x;
$t('red sonrasi kayit DEGISMEDI', str_contains((string)$b3['notes'], 'Colours — SKU1: Black, Navy.'));

echo "\n== 5. FATURALI siparis: varsayilan RED, opt-in ile gecer ==\n";
/* KURAL 5f: belge alicinin elinde olabilir. Sessizce yazmak, kayit ile belgeyi
   ayristirirdi -- bu depoda faturanin UC katmani tam bundan dogdu. */
mkdir($sand.'/data/invoices', 0777, true);
file_put_contents($sand.'/data/invoices/VES-T1__seller1.meta.json',
    json_encode(['no' => 'INV-2026-9999', 'ref' => 'VES-T1', 'seller_key' => 'seller1', 'total' => 25.0, 'currency' => 'EUR']));
file_put_contents($sand.'/data/invoices/VES-T1__seller1.pdf', '%PDF-1.4 test');
$inv = vestra_invoices_for_ref('VES-T1');
$t('kum havuzunda fatura GORUNUYOR (olcum gecerli)', count($inv) === 1);
$r5 = vestra_order_set_colours('VES-T1', 'SKU1', ['Pink']);
$t('faturali sipariste varsayilan RED', !empty($r5['error']));
$t('ret KURAL 5f\'e yolluyor', str_contains((string)($r5['error'] ?? ''), '5f'));
$b5 = null; foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === 'VES-T1') $b5 = $x;
$t('red sonrasi renk hala Black, Navy', str_contains((string)$b5['notes'], 'SKU1: Black, Navy'));
$r6 = vestra_order_set_colours('VES-T1', 'SKU1', ['Pink'], true);
$t('opt-in ile YAZIYOR', empty($r6['error']) && ($r6['colours'] ?? []) === ['Pink']);
/* must_redraft olmazsa cagiran belgeyi yeniden cizmeyi unutur ve kayit ile
   belge AYRISIR. Bayragin kendisi bu testin var olma sebeplerinden biri. */
$t('must_redraft bayragi TRUE', ($r6['must_redraft'] ?? null) === true);
$r7 = vestra_order_set_colours('VES-T2', 'SKU1', ['Black']);
$t('faturasiz sipariste must_redraft FALSE', ($r7['must_redraft'] ?? null) === false);

echo "\n== 6. ilanda OLMAYAN renk: yazilir ama SESSIZ kalmaz ==\n";
/* Operator musterinin GERCEKTEN aldigi mali soyluyor; katalog kaydi eksik
   olabilir. Belgeyi kataloga uydurmak icin satilan mali yanlis yazmak KURAL
   3'un tersi olurdu. Ama uyari cikmazsa kimse fark etmez. */
$t('not_listed alani var', array_key_exists('not_listed', $r7));

@array_map('unlink', glob($sand.'/data/invoices/*') ?: []);
@array_map('unlink', glob($sand.'/data/*') ?: []);
@rmdir($sand.'/data/invoices'); @rmdir($sand.'/data'); @rmdir($sand);

printf("\norder_colours_test: %d iddia gecti, %d KIRMIZI\n", $ok, $bad);
exit($bad ? 1 : 0);
