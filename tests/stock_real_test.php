<?php
/**
 * Kayitli (GERCEK) beden stogu — iki yonlu.
 *
 * 29 Eyl 2026: Burberry pike polo listesi (8 model) tedarikcinin GERCEK beden
 * stogunu tasiyordu: model basina 19-97 adet, bir modelde hic S yok. Fiyat
 * listeleri (inc/stock.php) stogu ilan kimliginden TURETIYOR ve polo icin bant
 * 100-150 -- yani 19 adetlik bir model "116 pcs in stock" diye basilacakti ve
 * ardindan gelen siparis karsilanamazdi.
 *
 * Tutulan olgular:
 *   1. Ilan 'stock' tasiyorsa O basilir (sira ve SIFIR korunur); tasimiyorsa
 *      turetilmis bant AYNEN kalir (yalniz bir rakami DAHA DOGRU yapar).
 *   2. Bozuk bir harita yarim okunmaz -- tamami yok sayilir.
 *   3. set_product.php alani GERCEKTEN yaziyor, null ile kaldiriyor, bozugu
 *      hicbir sey yazmadan reddediyor (kum havuzunda kosturuluyor).
 *   4. add-products.yml'nin dogrulama blogu is akisinin KENDI kodundan
 *      cikarilip calistiriliyor -- grep degil.
 *   5. Parti dosyasi PDF'le tutarli ve mektubun renk->foto eslestiricisi her
 *      modelde fotografini buluyor (bulamasaydi Angebot isi DURURDU).
 */
$root = realpath(__DIR__ . '/../vestra');
require_once $root . '/inc/products.php';
require_once $root . '/inc/stock.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; } else { $fail++; echo "  HATA: {$n}\n"; }
};

echo "== 1. vestra_stock_for: kayitli stok kazanir, yoksa turetilmis ==\n";
$real = ['id' => 'bur-8099166', 'brand' => 'Burberry', 'cat' => 'Polos',
         'stock' => ['S' => 0, 'M' => 9, 'L' => 9, 'XL' => 6, 'XXL' => 3]];
$st = vestra_stock_for($real);
$t('kayitli stok aynen donuyor',            $st['sizes'] === ['S' => 0, 'M' => 9, 'L' => 9, 'XL' => 6, 'XXL' => 3]);
$t('toplam = beden toplami (27)',           $st['total'] === 27);
$t('real bayragi TRUE',                     ($st['real'] ?? null) === true);
$t('SIFIR beden dusmuyor (S 0 gorunur)',    array_key_exists('S', $st['sizes']) && $st['sizes']['S'] === 0);
$t('satir: "S 0 · M 9 · ... (27 pcs)"',     vestra_stock_line($st) === 'S 0 · M 9 · L 9 · XL 6 · XXL 3  (27 pcs)');
/* Sira ilanin KENDI sirasi: sayisal bedenler (kot) da gecebilmeli. */
$jeans = vestra_stock_for(['id' => 'x', 'cat' => 'Jeans', 'stock' => [44 => 1, 46 => 3, 48 => 3]]);
/* PHP sayisal dizge anahtari ("44") int'e cevirir; olcut satirin kendisi. */
$t('sayisal beden anahtari calisiyor',      $jeans['total'] === 7 && vestra_stock_line($jeans) === '44 1 · 46 3 · 48 3  (7 pcs)');

$gen = ['id' => 'bur-8014004', 'brand' => 'Burberry', 'cat' => 'Polos'];
$g1 = vestra_stock_for($gen); $g2 = vestra_stock_for($gen);
$t('stok alani yoksa TURETILMIS (real=false)', ($g1['real'] ?? null) === false);
$t('turetilmis bant degismedi (100-150)',   $g1['total'] >= 100 && $g1['total'] <= 150);
$t('turetilmis hala deterministik',         $g1 === $g2);
$t('turetilmis bedenler toplami tutuyor',   array_sum($g1['sizes']) === $g1['total']);
/* Kontrol grubu: ayni id, stok alaniyla -- ayni fonksiyon farkli cevap. */
$t('ayni id + stok = kayitli stok',         vestra_stock_for($gen + ['stock' => ['S' => 1]])['total'] === 1);

foreach ([
    'negatif adet'      => ['S' => -1, 'M' => 3],
    'kesirli adet'      => ['S' => 2.5],
    'sayi olmayan adet' => ['S' => 'bes'],
    'bool adet'         => ['S' => true],
    'bos beden adi'     => ['' => 3],
    'bos harita'        => [],
    'dizi degil'        => 'S 2 M 6',
] as $why => $bad) {
    $b = vestra_stock_for($gen + ['stock' => $bad]);
    $t("bozuk harita ({$why}) YARIM okunmuyor -> turetilmis", ($b['real'] ?? null) === false && $b === $g1);
}

echo "\n== 2. scripts/set_product.php GERCEKTEN calistiriliyor (kum havuzu) ==\n";
$sand = sys_get_temp_dir() . '/vestra_stockreal_' . getmypid();
@mkdir($sand . '/public_html', 0777, true);
exec('cp -r ' . escapeshellarg($root . '/inc') . ' ' . escapeshellarg($sand . '/public_html/inc'));
@mkdir($sand . '/public_html/data', 0777, true);
$base = [[
    'id' => 'bur-test-polo', 'sku' => 'TEST-POLO-1', 'brand' => 'Burberry', 'name' => 'Test Polo',
    'cat' => 'Polos', 'mode' => 'fixed', 'list' => 59.9, 'moq' => 20, 'unit' => 'pc',
    'tiers' => [['min' => 20, 'price' => 59.9]],
], [
    'id' => 'bur-other', 'sku' => 'OTHER-1', 'brand' => 'Burberry', 'name' => 'Other Polo',
    'cat' => 'Polos', 'mode' => 'fixed', 'list' => 60, 'moq' => 20, 'unit' => 'pc',
    'tiers' => [['min' => 20, 'price' => 60]], 'stock' => ['M' => 4],
]];
$write = function (array $l) use ($sand) { file_put_contents($sand . '/public_html/data/listings.json', json_encode($l)); };
$run = function (array $fixes, bool $dry = false) use ($sand) {
    $env = 'HOME=' . escapeshellarg($sand) . ' P_DRY=' . ($dry ? 'true' : 'false')
         . ' P_JSON=' . escapeshellarg(base64_encode(json_encode($fixes)));
    exec($env . ' php ' . escapeshellarg(__DIR__ . '/../scripts/set_product.php') . ' 2>&1', $out, $rc);
    return [$rc, implode("\n", $out)];
};
$read = function (int $i = 0) use ($sand) {
    return json_decode((string)file_get_contents($sand . '/public_html/data/listings.json'), true)[$i] ?? [];
};

$write($base);
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'stock' => ['S' => 2, 'M' => 6, 'L' => 5, 'XL' => 4, 'XXL' => 2]]], true);
$t('kuru kosu gecti',                        $rc === 0);
$t('kuru kosu plani stoku yaziyor',          str_contains($out, 'S 2 · M 6 · L 5 · XL 4 · XXL 2 (19 ad.)'));
$t('kuru kosu HICBIR sey yazmadi',           !isset($read()['stock']));

[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'stock' => ['S' => 2, 'M' => 6, 'L' => 5, 'XL' => 4, 'XXL' => 2]]]);
$t('uygulama gecti',                         $rc === 0 && str_contains($out, 'KAYDEDILDI'));
$t('kayda INT haritasi olarak indi',         $read()['stock'] === ['S' => 2, 'M' => 6, 'L' => 5, 'XL' => 4, 'XXL' => 2]);
$t('baska ilanin stoguna dokunulmadi',       $read(1)['stock'] === ['M' => 4]);

[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'stock' => ['S' => -1]]]);
$t('negatif adet REDDEDILDI',                $rc !== 0 && str_contains($out, 'gecersiz beden/adet'));
$t('ret sonrasi kayit degismedi',            $read()['stock'] === ['S' => 2, 'M' => 6, 'L' => 5, 'XL' => 4, 'XXL' => 2]);
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'stock' => [2, 6, 5]]]);
$t('duz dizi (bedensiz) REDDEDILDI',         $rc !== 0 && str_contains($out, 'nesnesi ya da null'));
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'stock' => ['S' => '2']]]);
$t('dizge adet ("2") REDDEDILDI',            $rc !== 0);

/* RENK BASINA stok (29 Eyl 2026, 8 model TEK ilanda): anahtarlar ilanin renk
   listesinde OLMALI; karisik sekil ve listede olmayan renk REDDEDILIR. */
$nest = ['Green (1)' => ['S' => 2, 'M' => 6], 'Black (2)' => ['S' => 0, 'M' => 9]];
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'stock' => $nest]]);
$t('renk basina stok, ilanda renk listesi YOK -> RED', $rc !== 0 && str_contains($out, 'ilanin renk listesinde yok'));
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'colors' => ['Green (1)', 'Black (2)'], 'stock' => $nest]], true);
$t('renk listesi AYNI istekte: kuru kosu gecer, satir basina renk basiyor', $rc === 0 && str_contains($out, 'Green (1): S 2 · M 6 (8 ad.)') && str_contains($out, 'toplam 17 ad.'));
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'colors' => ['Green (1)', 'Black (2)'], 'stock' => $nest]]);
$t('renk basina stok kayda INDI',            $rc === 0 && $read()['stock'] === $nest && $read()['colors'] === ['Green (1)', 'Black (2)']);
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'stock' => ['Green (1)' => ['S' => 1], 'Pink' => ['S' => 1]]]]);
$t('listede olmayan renk ("Pink") RED, kayit degismedi', $rc !== 0 && $read()['stock'] === $nest);
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'stock' => ['S' => 1, 'Green (1)' => ['S' => 1]]]]);
$t('KARISIK sekil RED',                       $rc !== 0 && str_contains($out, 'karisik olamaz'));
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'stock' => ['Green (1)' => ['S' => -1]]]]);
$t('rengin icinde bozuk adet RED (stock.Green)', $rc !== 0 && str_contains($out, 'stock.Green (1) icinde'));
/* colorqty (lot-1 renk basina adet) ve redirect_to (katlanan ilan) */
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'colorqty' => 'true']]);
$t('colorqty dizge "true" RED (yalniz bool)', $rc !== 0 && str_contains($out, 'colorqty true ya da false'));
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'colorqty' => true]]);
$t('colorqty true kayda GERCEK bool indi',   $rc === 0 && $read()['colorqty'] === true);
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'colorqty' => false]]);
$t('colorqty false alani KALDIRIR ("" degil)', $rc === 0 && !array_key_exists('colorqty', $read()));
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'redirect_to' => 'yok-boyle-ilan']]);
$t('redirect_to olmayan hedefe RED',         $rc !== 0 && str_contains($out, "redirect_to 'yok-boyle-ilan' kayitta yok"));
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'redirect_to' => 'bur-test-polo']]);
$t('redirect_to KENDISI olamaz',             $rc !== 0 && str_contains($out, 'kendisi olamaz'));
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'status' => 'rejected', 'redirect_to' => 'bur-other']]);
$t('redirect_to + rejected birlikte yazildi', $rc === 0 && $read()['redirect_to'] === 'bur-other' && $read()['status'] === 'rejected');
[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'redirect_to' => null]]);
$t('redirect_to null alani KALDIRIR',        $rc === 0 && !array_key_exists('redirect_to', $read()));

[$rc, $out] = $run([['match' => 'TEST-POLO-1', 'stock' => null]]);
$t('null alani KALDIRDI',                    $rc === 0 && !array_key_exists('stock', $read()));
$t('kaldirinca liste turetilmis banda doner', (vestra_stock_for($read())['real'] ?? null) === false);
exec('rm -rf ' . escapeshellarg($sand));

echo "\n== 3. add-products.yml dogrulama blogu (is akisinin KENDI kodu) ==\n";
$wf = (string)file_get_contents(__DIR__ . '/../.github/workflows/add-products.yml');
$s = strpos($wf, "if (isset(\$r['stock'])) {");
$blk = '';
if ($s !== false) {
    /* Blogu parantez sayarak kes: sonraki satirlarin girintisi degisse de dogru. */
    $depth = 0; $i = strpos($wf, '{', $s);
    for ($j = $i; $j < strlen($wf); $j++) {
        if ($wf[$j] === '{') $depth++;
        elseif ($wf[$j] === '}') { $depth--; if ($depth === 0) { $blk = substr($wf, $s, $j - $s + 1); break; } }
    }
}
$t('dogrulama blogu is akisinda bulundu',    $blk !== '' && str_contains($blk, "\$row['stock'] = \$stk;"));
$harness = function (array $rows) use ($blk): array {
    $errors = []; $clean = [];
    /* Is akisinda colors bu bloktan ONCE satira yaziliyor; renk basina stok ona bakiyor. */
    eval('foreach ($rows as $i => $r) { $ctx = "satir ".($i+1); $id = "x".$i; $row = ["id" => $id];'
       . ' if (!empty($r["colors"])) $row["colors"] = $r["colors"];'
       . $blk . ' $clean[] = $row; }');
    return [$errors, $clean];
};
if ($blk !== '') {
    [$e, $c] = $harness([['stock' => ['S' => 0, 'M' => 9, 'L' => 9, 'XL' => 6, 'XXL' => 3]]]);
    $t('gecerli stok satira INDI',           !$e && ($c[0]['stock'] ?? null) === ['S' => 0, 'M' => 9, 'L' => 9, 'XL' => 6, 'XXL' => 3]);
    [$e, $c] = $harness([['name' => 'stoksuz']]);
    $t('stok verilmezse alan HIC yazilmiyor', !$e && !array_key_exists('stock', $c[0]));
    [$e, $c] = $harness([['stock' => ['S' => -2]], ['stock' => [1, 2]], ['stock' => ['S' => 1.5]], ['stock' => ['S M' => 1]]]);
    $t('dort bozuk satirin dordu de HATA',   count($e) === 4 && !$c);
    [$e, $c] = $harness([['stock' => ['44' => 1, '46' => 2]]]);
    $t('sayisal beden (kot) gecerli',        !$e && ($c[0]['stock'] ?? null) === ['44' => 1, '46' => 2]);
    /* Renk basina stok: ilanin colors listesine bagli (satir onu tasimali). */
    $nestRow = ['stock' => ['Green (1)' => ['S' => 2], 'Black (2)' => ['S' => 0, 'M' => 9]]];
    [$e, $c] = $harness([$nestRow + ['colors' => ['Green (1)', 'Black (2)']]]);
    $t('renk basina stok satira INDI',        !$e && ($c[0]['stock'] ?? null) === $nestRow['stock']);
    [$e, $c] = $harness([$nestRow + ['colors' => ['Green (1)']]]);
    $t('colors listesinde olmayan renk -> HATA', count($e) === 1 && str_contains($e[0], 'colors listesinde yok') && !$c);
    [$e, $c] = $harness([['colors' => ['A'], 'stock' => ['S' => 1, 'A' => ['S' => 1]]], ['colors' => ['A'], 'stock' => ['A' => ['S' => 1.5]]]]);
    $t('karisik sekil ve bozuk ic adet -> 2 HATA', count($e) === 2 && !$c);
}
/* colorqty bayragi: yalniz gercek true; colors + min_colors sart. */
$s2 = strpos($wf, "if (isset(\$r['colorqty'])) {");
$blk2 = '';
if ($s2 !== false) {
    $depth = 0; $i = strpos($wf, '{', $s2);
    for ($j = $i; $j < strlen($wf); $j++) {
        if ($wf[$j] === '{') $depth++;
        elseif ($wf[$j] === '}') { $depth--; if ($depth === 0) { $blk2 = substr($wf, $s2, $j - $s2 + 1); break; } }
    }
}
$t('colorqty blogu is akisinda bulundu',      $blk2 !== '' && str_contains($blk2, "\$row['colorqty'] = true;"));
if ($blk2 !== '') {
    $h2 = function (array $rows) use ($blk2): array {
        $errors = []; $clean = [];
        eval('foreach ($rows as $i => $r) { $ctx = "satir ".($i+1); $id = "x".$i; $row = ["id" => $id];'
           . ' if (!empty($r["colors"])) $row["colors"] = $r["colors"]; if (!empty($r["min_colors"])) $row["min_colors"] = (int)$r["min_colors"];'
           . $blk2 . ' $clean[] = $row; }');
        return [$errors, $clean];
    };
    [$e, $c] = $h2([['colors' => ['A', 'B'], 'min_colors' => 1, 'colorqty' => true]]);
    $t('colorqty true + colors + min_colors -> satira indi', !$e && ($c[0]['colorqty'] ?? null) === true);
    [$e, $c] = $h2([['colors' => ['A', 'B'], 'min_colors' => 1, 'colorqty' => 'true'], ['colors' => ['A'], 'colorqty' => true]]);
    $t('dizge "true" ve min_colors\'siz satir -> 2 HATA', count($e) === 2 && !$c);
}

echo "\n== 4. Parti dosyasi: PDF ile tutarli, mektup her modelde fotoyu buluyor ==\n";
$batch = json_decode((string)file_get_contents(__DIR__ . '/../product-batches/burberry-polo-2909.json'), true);
$t('8 model',                                 is_array($batch) && count($batch) === 8);
$pdf = ['8099164' => [2, 6, 5, 4, 2], '8096425' => [3, 9, 8, 6, 3], '8099165' => [3, 10, 10, 5, 2],
        '8099166' => [0, 9, 9, 6, 3], '8099167' => [2, 6, 6, 4, 2], '8071620' => [5, 15, 15, 10, 5],
        '8072661' => [5, 15, 15, 10, 5], '8071621' => [10, 28, 30, 17, 12]];
$tot = 0; $ids = [];
foreach ((array)$batch as $r) {
    $sku = (string)($r['sku'] ?? '');
    $ids[] = $r['id'] ?? '';
    $t("{$sku}: PDF'teki model",               isset($pdf[$sku]) && ($r['id'] ?? '') === 'bur-' . $sku);
    $t("{$sku}: beden stogu PDF'le birebir",   array_values((array)($r['stock'] ?? [])) === ($pdf[$sku] ?? null)
                                               && array_keys((array)($r['stock'] ?? [])) === ['S', 'M', 'L', 'XL', 'XXL']);
    $tot += array_sum((array)($r['stock'] ?? []));
    $t("{$sku}: MOQ 20 + kademeler 59,90 / 54,90 / 49,90",
       (int)($r['moq'] ?? 0) === 20 && ($r['tiers'] ?? null) === [['min' => 20, 'price' => 59.9], ['min' => 50, 'price' => 54.9], ['min' => 100, 'price' => 49.9]]
       && (float)($r['list'] ?? 0) === 59.9);
    $t("{$sku}: mode fixed (uydurma indirim rozeti yok)", ($r['mode'] ?? '') === 'fixed');
    $sh = vestra_listing_colour_shots($r);
    $t("{$sku}: renk->foto eslesti (Angebot isi durmaz)", count($sh['pairs']) === 1 && !$sh['missing']);
    /* Beden satiri stogun GOSTERMEDIGI bir bedeni vaat etmemeli. */
    $noS = (int)(($r['stock']['S'] ?? 1)) === 0;
    $t("{$sku}: S yoksa beden satiri S vaat etmiyor", !$noS || !preg_match('/(^|\s)S×/u', (string)($r['sizes'] ?? '')));
    $t("{$sku}: aciklama beden serisi TASIMIYOR (desc/sizes dersi)", !str_contains((string)($r['desc'] ?? ''), '×'));
}
$t('toplam 322 adet (PDF toplami)',          $tot === 322);
$t('id\'ler tekil',                          count(array_unique($ids)) === count($ids));

echo "\nstock_real_test: " . ($fail === 0 ? "{$ok} iddia gecti\n" : "{$ok} gecti, {$fail} HATA\n");
exit($fail === 0 ? 0 : 1);
