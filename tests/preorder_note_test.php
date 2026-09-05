<?php
/* On siparis notu (5 Eyl 2026, operator: "Rezervasyonlar icin erken siparis
 * kabul edilmektedir. Urun Ekim basi gonderilecektir").
 *
 * Bu testin VAROLUS SEBEBI: ayni not bir kez curudu. L1212'nin ilanina
 * 'Lead time' => 'Pre-order -- in stock from 5 May' SERBEST METIN olarak
 * yazilmisti ve 5 Eylul'de hala oradaydi -- dort ay boyunca gecmis bir tarihi
 * teslim sozu diye basti. CLAUDE.md bunu KURAL 3'un yasakladigi tahminle ayni
 * hata sayiyor. Tutulan sey: tarih ILANIN METNINE GOMULMEZ, makine okur bir
 * alanda durur ve suresi dolunca not KENDILIGINDEN susar.
 */
require_once __DIR__.'/../vestra/inc/products.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

echo "== 1. Tarihten cumle uretimi ==\n";
$amiri = ['preorder_ship' => '2026-10-01'];
$n = vestra_preorder_note($amiri, strtotime('2026-09-05'));
$t('not basiliyor',                 $n !== '');
$t('"Ekim basi" -> early October',  str_contains($n, 'early October 2026'));
$t('rezervasyon dili var',          stripos($n, 'reservation') !== false);
$t('ay basi/orta/sonu: 15 -> mid',  str_contains(vestra_preorder_note(['preorder_ship'=>'2026-11-15'], strtotime('2026-09-05')), 'mid November'));
$t('ay basi/orta/sonu: 25 -> late', str_contains(vestra_preorder_note(['preorder_ship'=>'2026-12-25'], strtotime('2026-09-05')), 'late December'));
$t('ay basi/orta/sonu: 10 -> early',str_contains(vestra_preorder_note(['preorder_ship'=>'2026-11-10'], strtotime('2026-09-05')), 'early November'));

echo "\n== 2. SURESI DOLUNCA SUSAR (L1212 dersinin ta kendisi) ==\n";
$t('sevk gunu hala gorunur',   vestra_preorder_note($amiri, strtotime('2026-10-01 09:00')) !== '');
$t('gunun sonuna kadar durur', vestra_preorder_note($amiri, strtotime('2026-10-01 23:00')) !== '');
$t('ertesi gun SUSAR',         vestra_preorder_note($amiri, strtotime('2026-10-02 00:30')) === '');
$t('dort ay sonra da susar',   vestra_preorder_note($amiri, strtotime('2027-02-05')) === '');
/* L1212'nin gercek vakasi: "5 May" tarihi 5 Eylul'de hala basiliyordu. */
$t('L1212 senaryosu artik bos', vestra_preorder_note(['preorder_ship'=>'2026-05-05'], strtotime('2026-09-05')) === '');

echo "\n== 3. Bos/bozuk girdi ==\n";
$t('alan yoksa bos',      vestra_preorder_note([]) === '');
$t('bos string bos',      vestra_preorder_note(['preorder_ship'=>'']) === '');
$t('bozuk tarih bos',     vestra_preorder_note(['preorder_ship'=>'yakinda']) === '');
$t('cop deger cokmez',    vestra_preorder_note(['preorder_ship'=>'2026-13-45']) === '');

echo "\n== 4. Katalogdaki gercek kayitlar ==\n";
$byId = [];
foreach (vestra_demo_products() as $p) $byId[$p['id'] ?? ''] = $p;
$ami = $byId['amiri-core-polo'] ?? [];
$t('Amiri polo preorder_ship tasiyor', ($ami['preorder_ship'] ?? '') === '2026-10-01');
$t('Amiri polosunda not uretiliyor',   vestra_preorder_note($ami, strtotime('2026-09-05')) !== '');
/* Hicbir ilanda tarih SERBEST METIN olarak durmamali -- kokun kendisi buydu. */
$stale = [];
foreach (vestra_demo_products() as $p) {
    $hay = (string)($p['desc'] ?? '') . ' ' . implode(' ', array_map('strval', (array)($p['specs'] ?? [])));
    if (preg_match('/\b(in stock from|available from|ships? from)\s+\d{1,2}\s+[A-Z][a-z]+/i', $hay)
        || preg_match('/pre-?order\b[^.]{0,40}\b\d{1,2}\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i', $hay)) {
        $stale[] = ($p['sku'] ?? $p['id'] ?? '?');
    }
}
$t('elle yazilmis sevk tarihi kalmadi: '.($stale ? implode(', ', $stale) : 'yok'), $stale === []);

echo "\n== 5. 'Pre-order' etiketi 8 dilde ==\n";
$missing = [];
foreach (['de','fr','es','it','pt','ru','ar'] as $lg) {
    $f = __DIR__.'/../vestra/inc/lang/'.$lg.'.php';
    $d = is_file($f) ? include $f : [];
    if (!is_array($d) || trim((string)($d['Pre-order'] ?? '')) === '') $missing[] = $lg;
}
$t('sozlukler tam: '.($missing ? implode(', ', $missing).' EKSIK' : 'hepsi var'), $missing === []);

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
