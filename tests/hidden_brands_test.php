<?php
/**
 * GIZLI MARKALAR (operator, 25 Eyl 2026: *"Gucci ve Balenciaga urunlerini
 * sitede gorunmez yap ancak sonra tekrar konulabilecek sekilde...sitede hic
 * gorunmesin"*).
 *
 * NEDEN CALISTIRARAK OLCUYORUZ: kaynakta "vestra_product_brand_hidden" gormek
 * olcum degil -- bu depoda alti kez kontrolun kendisi yanlis yere bakti. Sayfalar
 * kum havuzundaki bir site kopyasinda GERCEKTEN cizdiriliyor, panel POST'u ve
 * siparis POST'u gercekten kosturuluyor, ve sonuc CIKTIDAN / DISKTEN okunuyor.
 *
 * IKI YON DE TUTULUYOR, her yerde:
 *   - gizliyken marka GORUNMEMELI, geri acilinca AYNEN GERI GELMELI
 *     (tek yon yazilsaydi, markanin ilanlarini SILEN bir kusur da yesil kalirdi);
 *   - gizli marka duserken BASKA markalar YERINDE kalmali
 *     (tek yon yazilsaydi, butun katalogu bosaltan bir kusur da yesil kalirdi);
 *   - eslesme TAM: "Gucci Kids" gizlenmez (mango/zara dersi).
 */
$root = dirname(__DIR__).'/vestra';
$ok = 0; $bad = 0;
$t = function (string $name, bool $cond) use (&$ok, &$bad) {
    if ($cond) { $ok++; echo "  ok   $name\n"; }
    else       { $bad++; echo "  HATA $name\n"; }
};

/* ── 1-3: surec ICINDE, kum havuzu veri dizini ─────────────────────────── */
$sand = sys_get_temp_dir().'/vestra_hb_'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data');
ini_set('error_log', $sand.'/php_err.log');   // bilerek tetiklenen "bozuk dosya" kaydi test ciktisini kirletmesin
require_once $root.'/inc/products.php';
require_once $root.'/inc/orders.php';
if (vestra_data_dir() !== $sand.'/data') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }

$L = function (string $id, string $brand, string $sku, string $status = 'approved'): array {
    return ['id' => $id, 'brand' => $brand, 'name' => $brand.' Probe Tee '.$id, 'sku' => $sku,
            'cat' => 'T-Shirts', 'mode' => 'fixed', 'moq' => 20, 'unit' => 'pc', 'status' => $status,
            'tiers' => [['min' => 20, 'price' => 30.0]], 'list' => 30.0, 'ships_from' => 'EU'];
};
$listings = [
    $L('guc-1', 'Gucci',      'GUC-SKU-1') + ['seller_uid' => 'hbseller01'],
    $L('guc-2', 'GUCCI',      'GUC-SKU-2', 'pending'),   // buyuk harf yazim + onay bekliyor
    $L('blc-1', 'Balenciaga', 'BLC-SKU-1'),
    $L('lac-1', 'Lacoste',    'LAC-SKU-1') + ['seller_uid' => 'hbseller01'],
    $L('gk-1',  'Gucci Kids', 'GK-SKU-1'),               // YAKIN yazim: gizlenmemeli
];
file_put_contents($sand.'/data/listings.json', json_encode($listings, JSON_PRETTY_PRINT));
$ids = fn(array $ps): array => array_values(array_map(fn($p) => (string)($p['id'] ?? ''), $ps));

echo "== 1. karar noktasi ==\n";
$t('dosya yokken hicbir marka gizli degil', vestra_hidden_brands(true) === []);
$t('anahtar: bosluk + harf farki normalize', vestra_brand_key('  gucci ') === 'GUCCI');
$sha0 = sha1_file($sand.'/data/listings.json');
$r = vestra_hidden_brands_save(['Gucci', ' balenciaga ', 'GUCCI', ''], 'test');
$t('yazma geri okunarak dogrulandi', $r['ok'] === true);
$t('kume: tekrar ve bos ad dustu (2 marka)', count(vestra_hidden_brands(true)) === 2);
$t('eklenenler raporlandi', count($r['added']) === 2 && $r['removed'] === []);
$t('Gucci gizli (harf/bosluk duyarsiz)', vestra_brand_is_hidden('gucci ') && vestra_brand_is_hidden('GUCCI'));
$t('Balenciaga gizli', vestra_brand_is_hidden('Balenciaga'));
$t('"Gucci Kids" GIZLI DEGIL (tam esleme, alt dize degil)', !vestra_brand_is_hidden('Gucci Kids'));
$t('"Gucc" GIZLI DEGIL', !vestra_brand_is_hidden('Gucc'));
$t('bos marka GIZLI DEGIL', !vestra_brand_is_hidden(''));
$rec = vestra_hidden_brands_record();
$t('her markanin "since" damgasi var', isset($rec['since']['GUCCI'], $rec['since']['BALENCIAGA']));
$t('gecmis kaydi (history) yazildi', ($rec['history'][0]['hide'] ?? null) !== null && ($rec['changed_by'] ?? '') === 'test');
$t('ILAN KAYDINA DOKUNULMADI (listings.json ozeti ayni)', sha1_file($sand.'/data/listings.json') === $sha0);

echo "\n== 2. katalog okuyuculari ==\n";
$live = $ids(vestra_live_listings());
$t('canli listede Gucci YOK', !in_array('guc-1', $live, true));
$t('canli listede Balenciaga YOK', !in_array('blc-1', $live, true));
$t('canli listede Lacoste VAR (kontrol grubu)', in_array('lac-1', $live, true));
$t('canli listede "Gucci Kids" VAR (yakin yazim)', in_array('gk-1', $live, true));
$prod  = $ids(vestra_products());
$prodA = $ids(vestra_products(true));
$t('vestra_products(): gizli marka yok', !array_intersect(['guc-1', 'blc-1'], $prod));
$t('vestra_products(true) de gizli markayi DONDURMUYOR (urun sayfasi dahil her yol)', !array_intersect(['guc-1', 'blc-1'], $prodA));
$t('vestra_find(gizli) = null -> urun sayfasi 404', vestra_find('guc-1') === null);
$t('vestra_find(Lacoste) cozuluyor', (vestra_find('lac-1')['brand'] ?? '') === 'Lacoste');
/* KAYITLAR icin ham yol ACIK kalmali: gecmis siparis, teklif, fatura. */
$t('ham ilan okuyucu (teklif/mesaj) gizli ilani hala buluyor', (vestra_listing_by_id('guc-1')['brand'] ?? '') === 'Gucci');
$t('ham SKU okuyucu (teklif/fatura) gizli ilani hala buluyor', (vestra_listing_by_sku('BLC-SKU-1')['id'] ?? '') === 'blc-1');
$t('siparis satiri SKU cozucusu (vestra_product_by_sku) gizli ilani hala buluyor', (vestra_product_by_sku('GUC-SKU-1')['id'] ?? '') === 'guc-1');
/* Kodda gomulu demo urunleri ve seed katalogu da ayni kapidan. */
$demoBefore = in_array('amiri-core-polo', $prod, true);
$seedBefore = count(array_filter(vestra_products(), fn($p) => ($p['brand'] ?? '') === 'DSQUARED2'));
vestra_hidden_brands_save(['Gucci', 'Balenciaga', 'AMI Paris', 'DSQUARED2'], 'test');
vestra_hidden_brands(true);
$prod2 = vestra_products(true);
$t('kontrol: demo AMI Paris gizlenmeden once katalogdaydi', $demoBefore);
$t('demo urunu (AMI Paris) gizlenince dustu', !in_array('amiri-core-polo', $ids($prod2), true));
$t('kontrol: seed DSQUARED2 gizlenmeden once katalogdaydi ('.$seedBefore.')', $seedBefore > 0);
$t('seed katalogu (DSQUARED2) gizlenince dustu', count(array_filter($prod2, fn($p) => ($p['brand'] ?? '') === 'DSQUARED2')) === 0);
$t('demo Lacoste yerinde', in_array('lac-pique-polo', $ids($prod2), true));

echo "\n== 3. GERI ACMA: ilanlar aynen doner ==\n";
$r2 = vestra_hidden_brands_save(['Balenciaga'], 'test');
vestra_hidden_brands(true);
$t('geri acilan raporlandi', in_array('Gucci', $r2['removed'], true));
$t('Gucci canli listeye DONDU', in_array('guc-1', $ids(vestra_live_listings()), true));
$t('onay bekleyen Gucci ONAYLANMADI (durum korunur, gizlemek onaylamak degil)', !in_array('guc-2', $ids(vestra_live_listings()), true));
$t('Balenciaga hala gizli', !in_array('blc-1', $ids(vestra_live_listings()), true));
$t('ilk gizleme tarihi (since) korunuyor', (vestra_hidden_brands_record()['since']['BALENCIAGA'] ?? '') === ($rec['since']['BALENCIAGA'] ?? 'x'));
@unlink($sand.'/data/hidden_brands.json');
vestra_hidden_brands(true);   // surec-ici onbellegi tazele (sayfa istekleri her seferinde taze okur)
$t('dosya silinince hepsi geri gelir', in_array('blc-1', $ids(vestra_products()), true));
file_put_contents($sand.'/data/hidden_brands.json', '{bozuk');
$t('bozuk dosya: cokme yok, hicbir sey gizlenmez', vestra_hidden_brands(true) === [] && in_array('guc-1', $ids(vestra_products()), true));
$t('bozuk dosya SESSIZ degil: error_log\'a yazildi', str_contains((string)@file_get_contents($sand.'/php_err.log'), 'hidden-brands'));
@unlink($sand.'/data/hidden_brands.json'); vestra_hidden_brands(true);

echo "\n== 4. \"Coming soon\" arka kapisi ==\n";
$soon = [['name' => 'Gucci'], ['name' => 'Fred Perry'], ['name' => 'Ami Paris']];
$onSale = [['brand' => 'Fred Perry']];
$names = fn(array $s): array => array_map(fn($x) => $x['name'], $s);
$t('gizli marka "yakinda" diye BASILMAZ (harita)', $names(vestra_soon_brands_filter($soon, $onSale, ['GUCCI' => 'Gucci'])) === ['Ami Paris']);
$t('duz ad listesi de kabul (["Gucci"])', $names(vestra_soon_brands_filter($soon, $onSale, ['Gucci'])) === ['Ami Paris']);
$t('gizli degilse eskisi gibi (Gucci yakinda gorunur)', $names(vestra_soon_brands_filter($soon, $onSale, [])) === ['Gucci', 'Ami Paris']);

/* ── 5-7: SITE KOPYASI -- sayfalar, panel POST'u, siparis POST'u ────────── */
$site = $sand.'/site';
@mkdir($site, 0777, true);
exec('cp -r '.escapeshellarg($root).' '.escapeshellarg($site.'/public_html'));
$pub = $site.'/public_html';
foreach (glob($pub.'/data/*') ?: [] as $f) if (is_file($f)) @unlink($f);   // yerel kalintilar olcumu kirletmesin
/* Panel admin_pass olmadan "Admin locked" basiyor (admin_delete_buttons_test'in
   ayni kurulumu). Posta KAPALI: siparis kosturucusu gercek bir mektup denemesin. */
file_put_contents($pub.'/inc/config.php', "<?php return ['admin_pass'=>'x','mail_enabled'=>false];\n");
file_put_contents($pub.'/data/listings.json', json_encode($listings, JSON_PRETTY_PRINT));
file_put_contents($pub.'/data/accounts.json', json_encode([[
    'id' => 'hbbuyer01', 'email' => 'probe.buyer@example.test', 'type' => 'buyer', 'status' => 'active',
    'kyb_status' => 'approved', 'email_verified' => true, 'company' => 'Probe Handel GmbH', 'name' => 'Probe',
    'country' => 'Germany', 'lang' => 'en', 'hash' => password_hash('x'.random_int(0, 1 << 30), PASSWORD_DEFAULT),
], [
    /* showroom: bir saticinin HEM gizli HEM gorunur markasi var. Showroom
       vestra_live_listings()'i DOGRUDAN okuyor (vestra_products'tan gecmiyor),
       yani oradaki gizleme yalniz canli-liste suzgecine dayaniyor. */
    'id' => 'hbseller01', 'email' => 'probe.seller@example.test', 'type' => 'seller', 'status' => 'active',
    'kyb_status' => 'approved', 'email_verified' => true, 'company' => 'Probe Seller SRL', 'name' => 'Seller',
    'country' => 'Italy', 'lang' => 'en', 'hash' => password_hash('y'.random_int(0, 1 << 30), PASSWORD_DEFAULT),
]]));
/* "Yakinda" klasoru: gizli marka icin arka kapiyi SAYFADA olcmek icin. */
@mkdir($pub.'/uploads/coming-soon/gucci', 0777, true);
copy($root.'/inc/og-image.png', $pub.'/uploads/coming-soon/gucci/gdt01.png');
$hide = function (array $brands) use ($pub) {
    if (!$brands) { @unlink($pub.'/data/hidden_brands.json'); return; }
    file_put_contents($pub.'/data/hidden_brands.json', json_encode(['brands' => $brands]));
};
$render = function (string $page, array $get = [], bool $admin = false, string $uid = '') use ($pub): string {
    $boot = '$_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["HTTP_HOST"]="vestrasales.com";'
          . ' $_GET='.var_export($get, true).';'
          . ($admin ? ' session_start(); $_SESSION["vadmin"]=1; $_SESSION["vadmin_csrf"]="tok";' : '')
          . ($uid !== '' ? ' session_start(); $_SESSION["uid"]='.var_export($uid, true).';' : '')
          . ' include '.var_export($page, true).';';
    return (string)shell_exec('cd '.escapeshellarg($pub).' && php -r '.escapeshellarg($boot).' 2>&1');
};

echo "\n== 5. sayfalar -- GIZLIYKEN ==\n";
$hide(['Gucci', 'Balenciaga']);
$shop = $render('shop.php');
$t('vitrin: Gucci urunu YOK', !str_contains($shop, 'Gucci Probe Tee'));
$t('vitrin: Balenciaga urunu YOK', !str_contains($shop, 'Balenciaga Probe Tee'));
$t('vitrin: Lacoste VAR (kontrol grubu)', str_contains($shop, 'Lacoste Probe Tee'));
$t('vitrin: "Gucci Kids" VAR (yakin yazim gizlenmez)', str_contains($shop, 'Gucci Kids Probe Tee'));
$pg = $render('product.php', ['id' => 'guc-1']);
$t('urun sayfasi (gizli): "Product not found"', str_contains($pg, 'Product not found'));
$t('urun sayfasi (gizli): urun adi YOK', !str_contains($pg, 'Gucci Probe Tee'));
$pl = $render('product.php', ['id' => 'lac-1']);
$t('urun sayfasi (Lacoste): aciliyor', str_contains($pl, 'Lacoste Probe Tee') && !str_contains($pl, 'Product not found'));
$home = $render('index.php');
$t('ana sayfa: ciziliyor (kontrol)', str_contains($home, '</html>'));
/* Balenciaga'nin kum havuzunda YAKIN YAZIMI yok, yani sayfanin HICBIR yerinde
   gecmemeli (marka duvari, film, New arrivals, anahtar kelime, JSON-LD).
   Gucci'nin ise bilerek bir yakin yazimi var ("Gucci Kids", gizli degil), o
   yuzden onda belirli izler aranıyor. */
$t('ana sayfa: "balenciaga" HICBIR YERDE yok', stripos($home, 'balenciaga') === false);
$t('ana sayfa: Gucci urunu yok', !str_contains($home, 'Gucci Probe Tee'));
$t('ana sayfa: Gucci marka sayfasina baglanti yok', !str_contains($home, '/wholesale/gucci"'));
$t('ana sayfa: "Coming soon: Gucci" ARKA KAPISI kapali', !str_contains($home, 'coming-soon/gucci'));
$t('ana sayfa: "Coming soon" bolumu yine de ciziliyor (kontrol: fred-perry)', str_contains($home, 'coming-soon/fred-perry'));
$t('ana sayfa: Lacoste marka sayfasina baglanti VAR', str_contains($home, '/wholesale/lacoste'));
$sm = $render('sitemap.php');
$t('sitemap: gizli urun YOK', !str_contains($sm, 'guc-1') && !str_contains($sm, 'blc-1'));
$t('sitemap: Lacoste urunu VAR', str_contains($sm, 'lac-1'));
$t('sitemap: gizli markanin /wholesale sayfasi YOK', !str_contains($sm, '/wholesale/balenciaga'));
$ws = $render('wholesale.php', ['brand' => 'balenciaga']);
$t('/wholesale/balenciaga: urun YOK', !str_contains($ws, 'Balenciaga Probe Tee'));
$wl = $render('wholesale.php', ['brand' => 'lacoste']);
$t('/wholesale/lacoste: aciliyor (kontrol)', str_contains($wl, 'Lacoste Probe Tee'));
$pr = $render('price-list.php', [], true);
$t('fiyat listesi (kapi acik): gizli marka YOK', !str_contains($pr, 'GUC-SKU-1') && !str_contains($pr, 'BLC-SKU-1'));
$t('fiyat listesi: Lacoste VAR', str_contains($pr, 'LAC-SKU-1'));
$sr = $render('showroom.php', ['id' => 'hbseller01'], false, 'hbbuyer01');
$t('showroom: ciziliyor (kontrol: Lacoste VAR)', str_contains($sr, 'Lacoste Probe Tee'));
$t('showroom: saticinin GIZLI markali ilani YOK', !str_contains($sr, 'Gucci Probe Tee'));
$all5 = $shop.$pg.$pl.$home.$sm.$ws.$wl.$pr.$sr;
$t('PHP uyarisi / fatal yok', !preg_match('/(Warning|Fatal error|Deprecated|Notice):/', $all5));

echo "\n== 6. sayfalar -- GERI ACILINCA ==\n";
$hide([]);
$shop2 = $render('shop.php');
$t('vitrin: Gucci GERI GELDI', str_contains($shop2, 'Gucci Probe Tee'));
$t('vitrin: Balenciaga GERI GELDI', str_contains($shop2, 'Balenciaga Probe Tee'));
$pg2 = $render('product.php', ['id' => 'guc-1']);
$t('urun sayfasi: Gucci yeniden aciliyor', str_contains($pg2, 'Gucci Probe Tee') && !str_contains($pg2, 'Product not found'));
$sm2 = $render('sitemap.php');
$t('sitemap: gizli urunler geri geldi', str_contains($sm2, 'guc-1') && str_contains($sm2, 'blc-1'));

echo "\n== 7. panel: gizle / geri ac dugmeleri ==\n";
$runner = $pub.'/_hb_post.php';
file_put_contents($runner, <<<'PHP'
<?php
$_SESSION = [];
session_start();
$_SESSION['vadmin'] = true; $_SESSION['vadmin_csrf'] = 'tok';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin';
$_SERVER['HTTP_HOST'] = 'vestrasales.com';
$_POST = json_decode(getenv('HB_POST'), true) ?: [];
$_POST['_csrf'] = 'tok';
ob_start(); include __DIR__.'/admin.php'; ob_end_clean();
PHP);
$post = function (array $fields) use ($pub, $runner): string {
    return (string)shell_exec('cd '.escapeshellarg($pub).' && HB_POST='.escapeshellarg(json_encode($fields))
        .' php '.escapeshellarg($runner).' 2>&1');
};
$hb = fn(): array => (json_decode((string)@file_get_contents($pub.'/data/hidden_brands.json'), true)['brands'] ?? []);
$shaL = sha1_file($pub.'/data/listings.json');
$post(['_action' => 'brand_hide', 'brand' => 'gucci']);
$t('panel: "gucci" gizlendi, KATALOGUN yazimiyla kaydedildi ("Gucci")', $hb() === ['Gucci']);
$post(['_action' => 'brand_hide', 'brand' => 'Balenciaga']);
$t('panel: ikinci marka EKLENDI, ilki korunarak', $hb() === ['Gucci', 'Balenciaga']);
$post(['_action' => 'brand_hide', 'brand' => 'Gucc']);
$t('panel: yazim hatasi ("Gucc") hicbir sey degistirmedi', $hb() === ['Gucci', 'Balenciaga']);
$t('panel: ilan kaydina DOKUNULMADI', sha1_file($pub.'/data/listings.json') === $shaL);
$adm = $render('admin.php', ['tab' => 'listings'], true);
$t('panel: "Hidden brands" karti ciziliyor', str_contains($adm, 'Hidden brands'));
/* SATIRIN KENDISINDE ara: "Hidden (brand)" ifadesi ustteki istatistik
   kartinda da geciyor ve ilk yazimda iddia rozeti degil O KARTI olcuyordu --
   rozet yine "✓ Live" basacak sekilde sabote edildiginde yesil kaldi. */
$rowOf = function (string $html, string $needle): string {
    foreach (preg_split('~<tr[\s>]~', $html) as $chunk) if (str_contains($chunk, $needle)) return $chunk;
    return '';
};
$rg = $rowOf($adm, 'Gucci Probe Tee guc-1');
$rl = $rowOf($adm, 'Lacoste Probe Tee lac-1');
$t('panel: gizli ilanin SATIRINDA rozet var, "✓ Live" YOK', $rg !== '' && str_contains($rg, 'Hidden (brand)') && !str_contains($rg, '✓ Live'));
$t('panel: Lacoste satirinda "✓ Live" (kontrol)', str_contains($rl, '✓ Live'));
$t('panel: istatistik karti "Hidden (brand)" sayiyor', (bool)preg_match('~<div class="sv"[^>]*>\s*3\s*</div><div class="sl">Hidden \(brand\)</div>~', $adm));
$t('panel: gizli ilana 404 donecek "View" baglantisi YOK', !str_contains($adm, 'href="/product?id=guc-1"') && !str_contains($adm, 'href="/product?id=blc-1"'));
$t('panel: Lacoste "View" baglantisi VAR (kontrol)', str_contains($adm, 'href="/product?id=lac-1"'));
$t('panel: "Show again" dugmesi VAR', str_contains($adm, 'value="brand_show"'));
$t('panel: PHP uyarisi yok', !preg_match('/(Warning|Fatal error|Deprecated|Notice):/', $adm));
$post(['_action' => 'brand_show', 'brand' => 'Gucci']);
$t('panel: "Show again" Gucci\'yi geri acti, Balenciaga gizli kaldi', $hb() === ['Balenciaga']);

echo "\n== 8. sepet/siparis: gizli satir SESSIZCE DUSMEZ ==\n";
$orunner = $pub.'/_hb_order.php';
file_put_contents($orunner, <<<'PHP'
<?php
$_SESSION = [];
session_start();
$_SESSION['uid'] = 'hbbuyer01';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/order';
$_SERVER['HTTP_HOST'] = 'vestrasales.com';
$_POST = json_decode(getenv('HB_POST'), true) ?: [];
ob_start(); include __DIR__.'/order.php'; ob_end_clean();
PHP);
$order = function (array $cart) use ($pub, $orunner): void {
    $f = ['company' => 'Probe Handel GmbH', 'name' => 'Probe', 'email' => 'probe.buyer@example.test',
          'consent' => '1', 'country' => 'Germany', 'pay' => 'bank', 'cart' => json_encode($cart)];
    shell_exec('cd '.escapeshellarg($pub).' && HB_POST='.escapeshellarg(json_encode($f)).' php '.escapeshellarg($orunner).' 2>&1');
};
$rows = function () use ($pub): int {
    $f = $pub.'/data/orders.csv';
    return is_file($f) ? max(0, count(file($f, FILE_SKIP_EMPTY_LINES)) - 1) : 0;
};
$line = fn(string $id, string $sku) => ['id' => $id, 'sku' => $sku, 'qty' => 20, 'unit' => 30.0];
$hide(['Balenciaga']);
$n0 = $rows();
$order([$line('blc-1', 'BLC-SKU-1'), $line('lac-1', 'LAC-SKU-1')]);
$t('gizli + gorunur satir: siparis YAZILMADI (eskiden gizli satir sessizce duser, Lacoste siparisi olusurdu)', $rows() === $n0);
$order([$line('nope-404', 'NOPE'), $line('lac-1', 'LAC-SKU-1')]);
$t('bilinmeyen kimlikli satir da siparisi DURDURUR', $rows() === $n0);
/* KONTROL: kosturucu gercekten siparis yazabiliyor mu? Yazamiyorsa ustteki
   "yazilmadi" iddialari bos yere gecer. */
$order([$line('lac-1', 'LAC-SKU-1')]);
$t('KONTROL: yalniz gorunur satirla siparis YAZILIYOR', $rows() === $n0 + 1);
$last = (string)(file($pub.'/data/orders.csv')[$rows()] ?? '');
$t('KONTROL: yazilan siparis Lacoste satirini tasiyor', str_contains($last, 'LAC-SKU-1'));
$hide([]);
$order([$line('blc-1', 'BLC-SKU-1')]);
$t('geri acilinca Balenciaga yeniden siparis edilebiliyor', $rows() === $n0 + 2);

echo "\n== 9. sepet bandi: hangi satir ve kaldirma dugmesi ==\n";
$c1 = $render('cart.php', ['err' => 'unavailable', 'id' => 'blc-1']);
$t('bant ciziliyor (id tasiyor)', str_contains($c1, 'id="cartUnavail" data-id="blc-1"'));
$t('metin 8 dilde zaten duran anahtardan', str_contains($c1, 'This item is no longer available to order.'));
$t('kaldirma dugmesi ayni mekanizmayla (data-remove-id)', str_contains($c1, 'data-remove-id="blc-1"'));
$c2 = $render('cart.php', ['err' => 'soldout', 'id' => 'lac-1', 'lang' => 'de']);
$t('SATILDI bandi da var -- eskiden hic yoktu', str_contains($c2, 'id="cartUnavail"'));
$t('Almanca: "Ausverkauft" + "nicht mehr bestellbar"', str_contains($c2, 'Ausverkauft') && str_contains($c2, 'nicht mehr bestellbar'));
$c3 = $render('cart.php', ['err' => 'unavailable', 'id' => '"><script>x</script>']);
$t('kimlik temizleniyor (enjeksiyon yok)', !str_contains($c3, '<script>x</script>'));
$c4 = $render('cart.php');
$t('hata yokken bant YOK (kontrol)', !str_contains($c4, 'id="cartUnavail"'));

echo "\n== 10. kablolama ==\n";
$src = fn(string $f) => (string)file_get_contents($root.'/'.$f);
$ord = $src('order.php');
$t('order.php: cozulemeyen satir err=unavailable ile duruyor', str_contains($ord, "header('Location: /cart?err=unavailable&id='.rawurlencode(\$cid)); exit;"));
/* YORUMSUZ kaynakta ara: yeni kodun kendi yorumu eski satiri ALINTILIYOR
   ve duz arama o yorumu okuyup kirmiziya donuyordu (bu depoda "iddia yorumu
   okudu" tuzagi kayitli -- iddia gevsetilmedi, olctugu sey daraltildi). */
$code = '';
foreach (token_get_all($ord) as $tk) { if (is_array($tk) && in_array($tk[0], [T_COMMENT, T_DOC_COMMENT], true)) continue; $code .= is_array($tk) ? $tk[1] : $tk; }
$t('order.php: eski sessiz atlama (if(!$p) continue;) YOK', !str_contains($code, "if(!\$p) continue;"));
$yml = (string)file_get_contents(dirname(__DIR__).'/.github/workflows/send-outreach.yml');
$t('ucuncu mektup: gizli ev mektuptan dusuyor, durdurmuyor', str_contains($yml, "vestra_brand_is_hidden(\$want)) { \$w3hidden[] = \$want; continue; }"));
$sp = (string)file_get_contents(dirname(__DIR__).'/.github/workflows/seller-products.yml');
$t('is akisi brand_hide ayni yaziciyi cagiriyor', str_contains($sp, "vestra_hidden_brands_save(array_values(\$want), 'operator:workflow')"));
$t('is akisi: listings.json ozeti once/sonra karsilastiriliyor', str_contains($sp, "\$h0 === \$h1"));
$adminSrc = $src('admin.php');
$t('panel ayni yaziciyi cagiriyor', str_contains($adminSrc, "vestra_hidden_brands_save(array_values(\$cur), 'operator:panel')"));
$offersSrc = $src('inc/offers.php');
$t('teklif mektubu: gizli urune link vermiyor', str_contains($offersSrc, 'vestra_product_brand_hidden($listing)) return'));

exec('rm -rf '.escapeshellarg($sand));
printf("\n%d ok, %d hata\n", $ok, $bad);
exit($bad ? 1 : 0);
