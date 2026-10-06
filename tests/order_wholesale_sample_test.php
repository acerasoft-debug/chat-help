<?php
/* TOPTAN SIPARISTE "HER RENKTEN BIR PARCA" NUMUNE KOLISI (1 Eki 2026).
 *
 * Alici (30 Eyl 2026 yazismasi): "Pouvez-vous mettre tous vos articles Lacoste ?" /
 * "Une piece de chaque couleur". Operator: "catalogtaki tum Lacoste urunlerinin
 * her renginden ... toptan fiyatlardan + shipping 30 eur ... satici Vestra".
 *
 * Mevcut order_draft/order_write toptan dali tam KARTON satisi icin yazilmisti ve
 * canli taslakta (11 kalem, 71 parca) uc sey ters cikti:
 *   1. Adet ilanin seri toplamina TESADUFEN esit olan iki satirda (8 renkli 8'lik
 *      tisort; 10 renkli 10'luk sweatshirt) ilanin KARTON serisi (S×1, M×2 ...)
 *      beden dokumu diye yazilacakti -- kimse o bedenleri secmedi.
 *   2. Not "Wholesale tier pricing, FULL CARTONS ..." diyordu ve ayni notun devami
 *      "not a whole number of cartons" diyordu: ayni cumle iki ters sey.
 *   3. Notu alici KENDI siparis sayfasinda okuyor ama taslakta gorunmuyordu.
 *
 * Bu test is akisi adimini bir SITE KOPYASINDA gercekten kosturuyor (sentetik
 * katalog + sentetik alici: gercek bir musterinin adi/adresi buraya YAZILMAZ).
 * IKI YON: numune kolisinde karton serisi YAZILMAZ ve not dogru; tam karton
 * siparisinde (kontrol grubu) eski davranis AYNEN durur -- tek yon yazilsaydi
 * "her siparisten beden dokumunu silen" bir kusur da yesil kalirdi. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  HATA ").$n."\n"; };

$root = dirname(__DIR__);
$sand = sys_get_temp_dir().'/vestra_ows_'.bin2hex(random_bytes(4));
$home = $sand.'/home';
$site = $home.'/public_html';
mkdir($site.'/data', 0777, true);
exec('cp -r '.escapeshellarg($root.'/vestra/inc').' '.escapeshellarg($site.'/inc'));
$t('site kopyasi kuruldu', is_file($site.'/inc/orders.php'));

/* ---- is akisi adimini cikar ---- */
$wf = (string)file_get_contents($root.'/.github/workflows/seller-products.yml');
$a  = strpos($wf, "cat > /tmp/vestra_orddraft.php <<'PHPEOF'");
$e  = $a === false ? false : strpos($wf, "\n            PHPEOF", $a);
$php = '';
if ($a !== false && $e !== false) {
    $nl  = strpos($wf, "\n", $a) + 1;
    $blk = substr($wf, $nl, $e - $nl);
    $php = implode("\n", array_map(fn($l) => preg_replace('/^ {12}/', '', $l), explode("\n", $blk)))."\n";
}
$t('adim betigi cikarildi', str_starts_with($php, '<?php'));
file_put_contents($sand.'/step.php', $php);

/* ---- sentetik katalog: canli Lacoste ilanlarinin SEKLI, rakamlar uydurma ---- */
$listing = fn(string $id, string $sku, array $colors, string $sizes, int $step, int $moq, array $tiers) => [
    'id' => $id, 'sku' => $sku, 'brand' => 'Testbrand', 'name' => 'Test '.$id, 'cat' => 'T-Shirts',
    'mode' => 'sale', 'list' => $tiers[0][1], 'moq' => $moq, 'sizes' => $sizes, 'size_step' => $step,
    'tiers' => array_map(fn($x) => ['min' => $x[0], 'price' => $x[1]], $tiers),
    'colors' => $colors, 'min_colors' => 4, 'status' => 'approved', 'seller_uid' => 'sel-1', 'images' => [],
];
$TEE   = ['White', 'Navy', 'Black', 'Green', 'Blue', 'Beige', 'Pink', 'Red'];
$SWT   = ['Navy', 'Black', 'Bordeaux', 'Light Blue', 'Red', 'Green', 'Blue', 'White', 'Beige', 'Pink'];
$POLO  = ['Bordeaux', 'Navy', 'Black', 'White'];
$listings = [
    /* 8 renk x 8'lik seri: 8 adet = TAM BIR SERI (tesadufen) -- eski kodun tuzagi */
    $listing('zz-tee',   'ZZ-TEE-8',   $TEE,  'S×1 · M×2 · L×2 · XL×2 · XXL×1 · 8/pack',  8,  104, [[104, 19.9]]),
    /* 10 renk x 10'luk seri: 10 adet = TAM BIR SERI */
    $listing('zz-sweat', 'ZZ-SWT-10',  $SWT,  'S×1 · M×3 · L×3 · XL×2 · XXL×1 · 10/pack', 10, 50,  [[50, 39.9], [100, 35.0]]),
    /* 4 renk x 8'lik seri: 4 adet seriye esit DEGIL */
    $listing('zz-polo',  'ZZ-POLO-4',  $POLO, 'S×1 · M×2 · L×2 · XL×2 · XXL×1 · 8/pack',  8,  80,  [[80, 29.9]]),
];
$seed = function () use ($site, $listings) {
    exec('rm -f '.escapeshellarg($site.'/data/orders.csv').' '.escapeshellarg($site.'/data/order_statuses.json'));
    file_put_contents($site.'/data/listings.json', json_encode($listings, JSON_UNESCAPED_UNICODE));
    file_put_contents($site.'/data/accounts.json', json_encode([[
        'id' => 'b1', 'type' => 'buyer', 'email' => 'buyer@example.com', 'company' => 'Test SAS', 'name' => 'Tester',
        'country' => 'France', 'status' => 'active', 'kyb_status' => 'approved', 'vat_id' => 'FR00TEST',
        'address' => '1 rue de Test, 75001 Paris',
    ]]));
};
$lastRow = function () use ($site): ?array {
    $f = $site.'/data/orders.csv';
    if (!is_file($f)) return null;
    $h = fopen($f, 'r'); $head = fgetcsv($h, 0, ',', '"', '\\'); $row = null;
    while (($r = fgetcsv($h, 0, ',', '"', '\\')) !== false) if (count($r) === count($head)) $row = array_combine($head, $r);
    fclose($h);
    return $row;
};
$run = function (string $mode, string $lines, string $ship = '30') use ($home, $sand, $seed): array {
    $seed();
    $cmd = 'HOME='.escapeshellarg($home).' OD_MODE='.escapeshellarg($mode).' OD_WHO=b1 OD_SHIP='.escapeshellarg($ship)
         .' OD_LINES='.escapeshellarg($lines).' php -d display_errors=stderr '.escapeshellarg($sand.'/step.php').' 2>&1';
    $out = []; $rc = 0; exec($cmd, $out, $rc);
    return [$rc, implode("\n", $out)];
};

$W  = 'pricing=wholesale|waive_moq=1|waive_pack=1|';
/* Az renkli satirlar ilanin "en az 4 renk" kuralina takilir: ucuncu feragat acikca verilir. */
$W3 = 'pricing=wholesale|waive_moq=1|waive_pack=1|waive_min_colours=1|';
$teeAll  = 'ZZ-TEE-8:'.implode(';', $TEE).':8';
$swtAll  = 'ZZ-SWT-10:'.implode(';', $SWT).':10';
$poloAll = 'ZZ-POLO-4:'.implode(';', $POLO).':4';

echo "== 1. NUMUNE KOLISI: her renkten bir parca, uc ilan (iki tanesi tesadufen tam seri) ==\n";
[$rc, $out] = $run('order_write', $W.$teeAll.','.$swtAll.','.$poloAll);
$row = $lastRow(); $n = (string)($row['notes'] ?? '');
$t('rc 0 + SIPARIS YAZILDI', $rc === 0 && str_contains($out, 'SIPARIS YAZILDI'));
$t('PHP uyarisi yok', !preg_match('/\b(Warning|Notice|Deprecated|Fatal)\b/', $out));
$t('items: uc SKU, kademe fiyatlariyla', trim((string)($row['items'] ?? '')) === '8x ZZ-TEE-8 @19.90 | 10x ZZ-SWT-10 @39.90 | 4x ZZ-POLO-4 @29.90');
$t('toplam 159.20 + 399.00 + 119.60 + 30.00 = 707.80', abs((float)($row['total'] ?? 0) - 707.80) < 0.005);
$t('not: "One piece of each colour." VAR', str_contains($n, 'One piece of each colour.'));
$t('not: "full cartons" YOK (buyuk/kucuk harf)', stripos($n, 'full cartons') === false && stripos($n, 'Full cartons') === false);
$t('not: "no size breakdown" VAR (feragat cumlesi duruyor)', str_contains($n, 'no size breakdown'));
$t('not: Sizes parcasi HIC YOK (tesadufen tam seri olan 2 satir dahil)', !str_contains($n, 'Sizes —'));
$t('not: Colours parcasi her SKU icin VAR', str_contains($n, 'Colours — ZZ-TEE-8: White, Navy, Black, Green, Blue, Beige, Pink, Red')
        && str_contains($n, 'ZZ-SWT-10: Navy, Black, Bordeaux, Light Blue, Red, Green, Blue, White, Beige, Pink')
        && str_contains($n, 'ZZ-POLO-4: Bordeaux, Navy, Black, White'));
$t('not: Payment + Shipping duruyor', str_contains($n, 'Payment: Bank transfer.') && str_contains($n, 'Shipping EUR 30.00.'));
$t('taslak satirlari: iki tuzak satirinda beden dokumu YAZILMADI', substr_count($out, 'beden       : (yazilmadi)') === 3
        && !str_contains($out, 'beden       : S×'));
$t('taslak: paket adimi feragati mesaji (tesadufen tam seri olan satirlar)', substr_count($out, '(beden dokumu yazilmadi: paket adimi feragati, adet') === 2);
$t('cikti: yazilan not gorunuyor', str_contains($out, 'siparis notu   : Wholesale tier pricing, agreed with the buyer. One piece of each colour.'));

echo "\n== 2. TASLAK ayni notu gosterir, HICBIR SEY yazmaz ==\n";
[$rc, $out] = $run('order_draft', $W.$teeAll.','.$swtAll.','.$poloAll);
$t('taslak rc 0 + temiz', $rc === 0 && str_contains($out, 'taslak temiz'));
$t('taslak: orders.csv OLUSMADI', !is_file($site.'/data/orders.csv'));
$t('taslak: not satiri gorunuyor ve dogru', str_contains($out, 'siparis notu   : Wholesale tier pricing, agreed with the buyer. One piece of each colour.')
        && !stripos($out, 'full cartons'));
$t('taslak: mal 677.80 + kargo 30.00 = 707.80', str_contains($out, '677.80') && str_contains($out, '707.80'));

echo "\n== 3. KONTROL GRUBU: TAM KARTON siparisi eski davranisla AYNI ==\n";
[$rc, $out] = $run('order_write', 'pricing=wholesale|ZZ-SWT-10:Navy;Black;Red;Green;Blue:50');
$row = $lastRow(); $n = (string)($row['notes'] ?? '');
$t('tam karton: rc 0', $rc === 0);
$t('tam karton: "full cartons" cumlesi DURUYOR', str_contains($n, 'Wholesale tier pricing, full cartons, agreed with the buyer.'));
$t('tam karton: "One piece of each colour" YOK (50 adet / 5 renk)', !str_contains($n, 'One piece of each colour'));
$t('tam karton: beden dokumu YAZILDI (5 karton x seri)', str_contains($n, 'Sizes — ZZ-SWT-10: S×5, M×15, L×15, XL×10, XXL×5.'));
$t('tam karton: feragat cumleleri YOK', !str_contains($n, 'exception'));

echo "\n== 4. Paket adimi feragati ama her renkten bir parca DEGIL ==\n";
[$rc, $out] = $run('order_write', $W.'ZZ-POLO-4:'.implode(';', $POLO).':12');
$row = $lastRow(); $n = (string)($row['notes'] ?? '');
$t('12 adet / 4 renk: rc 0', $rc === 0);
$t('"One piece of each colour" YOK (uc parca/renk)', !str_contains($n, 'One piece of each colour'));
$t('"full cartons" YOK', stripos($n, 'full cartons') === false);
$t('not: "Wholesale tier pricing, agreed with the buyer." dogru yazim', str_contains($n, 'Wholesale tier pricing, agreed with the buyer.'));
$t('beden dokumu YOK', !str_contains($n, 'Sizes —'));

echo "\n== 5. Karisik siparis: BIR satir ihlal ederse cumle TUM siparisten kalkar ==\n";
[$rc, $out] = $run('order_write', $W.$poloAll.',ZZ-TEE-8:'.implode(';', $TEE).':16');
$row = $lastRow(); $n = (string)($row['notes'] ?? '');
$t('karisik: rc 0', $rc === 0);
$t('karisik: "One piece of each colour" YOK (tisort 16 adet / 8 renk)', !str_contains($n, 'One piece of each colour'));

echo "\n== 6. Tek renkli tek parca: ayirt edici bir sey yok, cumle yazilmaz ==\n";
[$rc, $out] = $run('order_write', $W3.'ZZ-POLO-4:Navy:1');
$row = $lastRow(); $n = (string)($row['notes'] ?? '');
$t('tek renk tek parca: rc 0', $rc === 0);
$t('"One piece of each colour" YOK (cok renkli satir yok)', !str_contains($n, 'One piece of each colour'));

echo "\n== 7. Anlasilan birim fiyatla: 'Full cartons.' yazilmaz ==\n";
[$rc, $out] = $run('order_write', $W3.'ZZ-POLO-4:Navy;Black:2:25');
$row = $lastRow(); $n = (string)($row['notes'] ?? '');
$t('anlasilan fiyat: rc 0', $rc === 0);
$t('not "Unit price agreed ..." ile basliyor, "Full cartons" YOK', str_contains($n, 'Unit price agreed with the buyer, outside the listed tiers.')
        && stripos($n, 'full cartons') === false);
$t('"One piece of each colour." VAR (2 renk / 2 adet)', str_contains($n, 'One piece of each colour.'));
$t('birim 25.00 yazildi', str_contains((string)($row['items'] ?? ''), '2x ZZ-POLO-4 @25.00'));

echo "\n== 8. Hatali girdi hala YAZMAZ ==\n";
[$rc, $out] = $run('order_write', $W.'ZZ-POLO-4:Purple;Navy:2');
$t('olmayan renk -> rc != 0 ve orders.csv OLUSMADI', $rc !== 0 && !is_file($site.'/data/orders.csv'));
$t('olmayan renk "ILANDA YOK" diyor', str_contains($out, "ILANDA YOK"));

echo "\n== 9. kablolama ==\n";
$code = preg_replace('~/\*.*?\*/~s', '', $php);
$pNote  = strpos($code, '$note = $wholesale');
$pWrite = strpos($code, "\$write = getenv('OD_MODE')");
$pShow  = strpos($code, 'siparis notu   :');
$t('not taslak dalindan ONCE kuruluyor ve basiliyor', $pNote !== false && $pWrite !== false && $pShow !== false && $pNote < $pShow && $pShow < $pWrite);
$t('"her renkten bir parca" VERIDEN turuyor (girdi bayragi yok)', !preg_match('/one_per_colour|onepercolour|one_piece/i', preg_replace('/\$onePerColour|onePerColour/', '', $code) ?? ''));
$t('beden dokumu paket adimi feragatinde kapali', str_contains($code, '$perPack > 0 && !$waivePack && $qty % $perPack === 0'));
$t('dropship notu degismedi', str_contains($code, "'Dropship pricing (wholesale +'.(int)round(VESTRA_DROPSHIP_MARKUP*100).'%), single pieces, agreed with the buyer.'"));

exec('rm -rf '.escapeshellarg($sand));
echo "\n{$ok} ok, {$bad} hata\n";
exit($bad ? 1 : 0);
