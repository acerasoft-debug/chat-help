<?php
/**
 * Sekiz model TEK ilanda — Burberry pike polo (operator, 29 Eyl 2026:
 * "gonderdigim burberry listelerindeki fotolari ve listeleri tek bir ilanda yap").
 *
 * Sabah 8 ayri ilan yazilmis ve 100 kayitli musteriye 8 ayri baglantiyla mektup
 * gitmisti. Birlestirme dort seyi birden gerektirdi ve bu dosya dordunu de
 * IKI YONDE tutuyor:
 *
 *   1. RENK ADI MODEL NUMARASINI TASIYOR ("Black (8096425)" / "Black · Check
 *      collar (8071620)"): iki "Black" var ve "Black ×20" diye bir siparis
 *      satiri hangi artikeli soylemezdi. Palet cozumu, nokta, cevrilmis etiket
 *      ve foto eslestirmesi bu adlarla calismali; paletten olmayan bir ad
 *      ("Blueberry") ESKISI gibi atlanmali.
 *   2. RENK BASINA STOK (nested 'stock'): eski okuyanlar (fiyat listesi, Excel,
 *      PDF) toplami AYNEN gormeli; urun sayfasi ve mektup ayrimi basmali.
 *   3. LOT-1 ILANDA RENK BASINA ADET (colorqty bayragi): sayi alani, adim 1,
 *      /order ayni fonksiyonla dogruluyor; bayraksiz ilan DEGISMIYOR.
 *   4. ESKI ADRESLER 301: mektuplardaki /product?id=bur-8099164 yeni ilana gider;
 *      reddedilmis, dongulu ya da olmayan hedefe gitmez.
 */
$root = realpath(__DIR__ . '/../vestra');
require_once $root . '/inc/products.php';
require_once $root . '/inc/stock.php';
require_once $root . '/inc/email_templates.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; } else { $fail++; echo "  HATA: {$n}\n"; }
};
$src = fn(string $f) => (string)file_get_contents($root . '/' . $f);

echo "== 1. renk adi: palet tabani, CSS, etiket ==\n";
$t('duz palet adi kendisi',                       vestra_colour_base('Navy') === 'Navy');
$t('model numarali ad -> palet tabani',            vestra_colour_base('Black (8096425)') === 'Black');
$t('"· Check collar (…)" eki -> tabani Black',     vestra_colour_base('Black · Check collar (8071620)') === 'Black');
$t('UZUN ad once: "Light Blue (1)" Blue degil',    vestra_colour_base('Light Blue (1)') === 'Light Blue');
$t('harf duyarsiz',                                vestra_colour_base('navy (1)') === 'Navy');
$t('kelime siniri: "Blueberry" taban DEGIL',       vestra_colour_base('Blueberry') === null);
$t('"Navyblue" taban DEGIL (harf devam ediyor)',   vestra_colour_base('Navyblue') === null);
$t('"Other" tahmin edilmez',                       vestra_colour_base('Otherwise') === null && vestra_colour_base('Other') === 'Other');
$t('bos ad -> null',                               vestra_colour_base('  ') === null);
$t('CSS: model numarali siyah = paletin siyahi',   vestra_colour_css('Black (8096425)') === vestra_colors()['Black']);
$t('CSS: cozulmeyen ad gri (#666)',                vestra_colour_css('Blueberry') === '#666');
$dots = vestra_color_dots(['Green (8099164)', 'Blueberry', 'Black · Check collar (8071620)'], 7, true);
$t('nokta satiri: 2 cozulen nokta, Blueberry atlandi', substr_count($dots, 'class="cdot"') === 2 && !str_contains($dots, 'Blueberry'));
$t('nokta satiri: yesil nokta yesil, koyu halka',   str_contains($dots, 'background:' . vestra_colors()['Green'] . ';box-shadow:inset 0 0 0 1px rgba(255,255,255,.28)'));
$t('nokta satiri: etiket eki koruyor',              str_contains($dots, '<span class="cname">Black · Check collar (8071620)</span>'));

echo "\n== 2. renk basina stok (nested) ==\n";
$nested = ['stock' => ['Green (8099164)' => ['S' => 2, 'M' => 6], 'Black (8096425)' => ['S' => 0, 'M' => 9, 'L' => 1]]];
$st = vestra_stock_real($nested);
$t('toplam bedenler renkler ustunden toplaniyor',   $st !== null && $st['sizes'] === ['S' => 2, 'M' => 15, 'L' => 1] && $st['total'] === 18);
$t('by_colour ilanin renk sirasinda',               array_keys($st['by_colour']) === ['Green (8099164)', 'Black (8096425)']);
$t('renk toplami dogru, SIFIR korunuyor',           $st['by_colour']['Black (8096425)']['total'] === 10 && $st['by_colour']['Black (8096425)']['sizes']['S'] === 0);
$t('vestra_stock_line toplam satirini basiyor',     vestra_stock_line($st) === 'S 2 · M 15 · L 1  (18 pcs)');
$rows = vestra_stock_rows($st);
$t('stock_rows: renk basina satir',                 count($rows) === 2 && $rows[0]['colour'] === 'Green (8099164)' && $rows[1]['total'] === 10);
$flatRows = vestra_stock_rows(vestra_stock_real(['stock' => ['S' => 1, 'M' => 2]]));
$t('stock_rows: duz stokta tek, renksiz satir',     count($flatRows) === 1 && $flatRows[0]['colour'] === '' && $flatRows[0]['total'] === 3);
$t('vestra_stock_for de ayni cevabi veriyor',       vestra_stock_for($nested + ['id' => 'x', 'cat' => 'Polos'])['total'] === 18);
$t('KARISIK sekil (duz + renk) tumden REDDEDILIR',  vestra_stock_real(['stock' => ['S' => 2, 'Black' => ['M' => 1]]]) === null);
$t('bos renk adi REDDEDILIR',                        vestra_stock_real(['stock' => ['' => ['S' => 1]]]) === null);
$t('rengin icinde bozuk adet -> tamami yok sayilir', vestra_stock_real(['stock' => ['A' => ['S' => 1], 'B' => ['S' => -1]]]) === null);
$t('rengin icinde bos harita -> yok sayilir',        vestra_stock_real(['stock' => ['A' => []]]) === null);

echo "\n== 3. lot-1 ilanda renk basina adet (colorqty) ==\n";
$cq = ['colors' => ['Green (8099164)', 'Black (8096425)'], 'min_colors' => 1, 'colorqty' => true];
$t('bayrakli lot-1 ilan adet-secici kipinde',        vestra_is_colorqty_listing($cq));
$t('bayraksiz lot-1 ilan DEGISMEDI (kip kapali)',    !vestra_is_colorqty_listing(['colors' => ['A', 'B'], 'min_colors' => 1]));
$t('paketli ilan eskisi gibi acik',                  vestra_is_colorqty_listing(['colors' => ['A'], 'min_colors' => 1, 'size_step' => 10]));
$t('bayrak var, min_colors yok -> kapali',           !vestra_is_colorqty_listing(['colors' => ['A'], 'colorqty' => true]));
$parsed = vestra_parse_colorqty($cq, ['Green (8099164)' => '7', 'Black (8096425)' => 13, 'Pink' => 5]);
$t('adim 1: 7 ve 13 yuvarlanmiyor, toplam 20',      $parsed === ['lines' => ['Green (8099164) ×7', 'Black (8096425) ×13'], 'qty' => 20]);
$t('ilanda olmayan renk ("Pink") sayilmiyor',        !str_contains(json_encode($parsed), 'Pink'));
$tok = vestra_parse_colorqty_tokens($cq, ['Green (8099164) ×7', 'Black (8096425) ×13']);
$t('token yolu (sepet) ayni sonucu veriyor',         $tok === $parsed);
$t('paketli ilanda yuvarlama DURUYOR (kontrol: 13 -> 10)',
   vestra_parse_colorqty(['colors' => ['A'], 'min_colors' => 1, 'size_step' => 10], ['A' => 13]) === ['lines' => ['A ×10'], 'qty' => 10]);

echo "\n== 4. yonlendirme cozucusu ==\n";
$sand = sys_get_temp_dir() . '/vestra_single_' . getmypid();
@mkdir($sand, 0777, true);
exec('cp -r ' . escapeshellarg($root) . ' ' . escapeshellarg($sand . '/public_html'));
$pub = $sand . '/public_html';
foreach (glob($pub . '/data/*') ?: [] as $f) if (is_file($f)) @unlink($f);
file_put_contents($pub . '/inc/config.php', "<?php return ['admin_pass'=>'x','mail_enabled'=>false];\n");
$batch = json_decode((string)file_get_contents(__DIR__ . '/../product-batches/burberry-polo-single-2909.json'), true);
$big = $batch[0] + ['status' => 'approved', 'verified' => true, 'added_at' => '2026-09-29T10:00:00+00:00'];
$mk = function (string $id, array $extra = []) {
    return array_merge(['id' => $id, 'brand' => 'Burberry', 'name' => "Old {$id}", 'cat' => 'Polos', 'sku' => strtoupper($id),
        'list' => 59.9, 'moq' => 20, 'unit' => 'pc', 'mode' => 'fixed', 'status' => 'approved',
        'tiers' => [['min' => 20, 'price' => 59.9]], 'colors' => ['Green']], $extra);
};
$listings = [
    $big,
    $mk('bur-8099164', ['status' => 'rejected', 'redirect_to' => 'bur-pique-polo-2909']),   // katlanan (gercek durum)
    $mk('old-self',    ['status' => 'rejected', 'redirect_to' => 'old-self']),              // kendine
    $mk('old-nowhere', ['status' => 'rejected', 'redirect_to' => 'yok-boyle-bir-ilan']),    // olmayan hedef
    $mk('old-dead',    ['status' => 'rejected', 'redirect_to' => 'dead-target']),           // reddedilmis hedef
    $mk('dead-target', ['status' => 'rejected']),
    $mk('loop-a',      ['redirect_to' => 'loop-b']),                                        // hedef kendisi yonlendiriyor
    $mk('loop-b',      ['redirect_to' => 'loop-a']),
    $mk('bur-8014004', ['colors' => ['Green', 'Navy'], 'min_colors' => 1]),                 // bayraksiz kontrol ilani
];
file_put_contents($pub . '/data/listings.json', json_encode($listings, JSON_UNESCAPED_UNICODE));
file_put_contents($pub . '/data/accounts.json', json_encode([[
    'id' => 'slbuyer01', 'email' => 'probe.buyer@example.test', 'type' => 'buyer', 'status' => 'active',
    'kyb_status' => 'approved', 'email_verified' => true, 'company' => 'Probe Handel GmbH', 'name' => 'Probe',
    'country' => 'Germany', 'lang' => 'en', 'hash' => password_hash('x' . random_int(0, 1 << 30), PASSWORD_DEFAULT),
]]));
/* Cozucu ayri surecte: bu surecin vestra_listings() onbellegi/dizini gercek depoya bakiyor. */
$php = function (string $code) use ($pub): string {
    return (string)shell_exec('cd ' . escapeshellarg($pub) . ' && php -r ' . escapeshellarg($code) . ' 2>&1');
};
$redir = fn(string $id) => trim($php('require "inc/products.php"; echo var_export(vestra_product_redirect(' . var_export($id, true) . '), true);'));
$t('katlanan eski id -> yeni ilan',                  $redir('bur-8099164') === "'bur-pique-polo-2909'");
$t('kendine yonlendiren -> null',                    $redir('old-self') === 'NULL');
$t('olmayan hedef -> null',                          $redir('old-nowhere') === 'NULL');
$t('REDDEDILMIS hedef -> null (404\'e yollamaz)',    $redir('old-dead') === 'NULL');
$t('hedef kendisi yonlendiriyorsa -> null (zincir/dongu yok)', $redir('loop-a') === 'NULL');
$t('yonlendirmesi olmayan id -> null',               $redir('bur-8014004') === 'NULL' && $redir('yok') === 'NULL');
$t('eski kayit acik listede YOK (rejected)',         !str_contains($php('require "inc/products.php"; foreach (vestra_products() as $p) echo $p["id"], " ";'), 'bur-8099164'));

echo "\n== 5. urun sayfasi (kum havuzunda, GERCEK HTTP -- php -S) ==\n";
/* CLI'da header()/http_response_code() OLCULEMIYOR (headers_list() bos doner), yani
   301 ve Location ancak gercek bir HTTP istegiyle gorulur. Oturum dosyasi elle
   yaziliyor: auth.php oturumlari data/sessions altinda tutuyor. */
@mkdir($pub . '/data/sessions', 0700, true);
$sid = bin2hex(random_bytes(16));
file_put_contents($pub . '/data/sessions/sess_' . $sid, 'uid|s:9:"slbuyer01";');
$port = 18500 + (getmypid() % 1500);
$elog = $sand . '/err.log';
$proc = proc_open(['php', '-d', 'display_errors=1', '-d', 'log_errors=1', '-d', 'error_log=' . $elog,
                   '-S', "127.0.0.1:$port", '-t', $pub, $pub . '/_router_local.php'],
                  [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pp);
$up = false;
for ($i = 0; $i < 60 && !$up; $i++) { usleep(100000); $sk = @fsockopen('127.0.0.1', $port); if ($sk) { $up = true; fclose($sk); } }
$t('php -S ayaga kalkti', $up);
$http = function (string $method, string $path, string $body = '', bool $auth = false) use ($port, $sid): array {
    $sk = @fsockopen('127.0.0.1', $port, $en, $es, 5);
    if (!$sk) return ['', '', ''];
    $req = "$method $path HTTP/1.0\r\nHost: 127.0.0.1\r\nUser-Agent: Mozilla/5.0 TestBrowser\r\n"
         . ($auth ? "Cookie: PHPSESSID=$sid\r\n" : '')
         . ($body !== '' ? "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\n" : '')
         . "\r\n" . $body;
    fwrite($sk, $req);
    $raw = stream_get_contents($sk); fclose($sk);
    [$h, $b] = array_pad(explode("\r\n\r\n", (string)$raw, 2), 2, '');
    preg_match('/^HTTP\/\d\.\d (\d{3})/', $h, $sm);
    return [$sm[1] ?? '', $h, $b];
};
[$st1, $h1, $b1] = $http('GET', '/product?id=bur-8099164');
$t('eski adres: 301',                                 $st1 === '301');
$t('eski adres: Location yeni ilan',                  (bool)preg_match('#^Location: /product\?id=bur-pique-polo-2909\s*$#mi', $h1));
$t('eski adres: sayfa govdesi basilmadi',             !str_contains($b1, '</html>'));
[$st2, , $b2] = $http('GET', '/product?id=old-dead');
$t('reddedilmis hedefli eski adres: 404 (yonlendirme yok)', $st2 === '404' && str_contains($b2, 'Product not found'));
[$st3, $h3, $b3] = $http('GET', '/product?id=loop-a');
$t('dongulu ilan: kendi sayfasi 200, sonsuz yonlendirme yok', $st3 === '200' && !preg_match('/^Location:/mi', $h3) && str_contains($b3, '</html>'));

[$st4, , $pg] = $http('GET', '/product?id=bur-pique-polo-2909', '', true);
$t('yeni ilan: 200, girisli alici, PHP uyarisi yok',  $st4 === '200' && str_contains($pg, 'Probe Handel GmbH') === false /* ad basilmaz */
                                                       && !preg_match('/(Warning|Fatal error|Deprecated|Notice):/', $pg));
$t('renk basina SAYI alani (select degil), 8 adet',   substr_count($pg, 'input type="number" min="0" step="1" value="0" inputmode="numeric"') === 8
                                                       && substr_count($pg, 'data-color="') === 8 && !str_contains($pg, '<select data-color'));
$t('ipucu adim 1\'de "multiples of" DEMIYOR',           str_contains($pg, 'Quantity per colour — at least 1 colour') && !str_contains($pg, 'multiples of'));
$t('stok tablosu: 322 adet basligi',                  str_contains($pg, '322 pcs in stock'));
$hasTbl = (bool)preg_match('/<table class="tiers stocktbl".*?<\/table>/s', $pg, $mm);
$t('stok tablosu: 8 renk satiri + Total sutunu',      $hasTbl && substr_count($mm[0], '<tr>') === 9 && str_contains($mm[0], '<th>Total</th>'));
$t('stok tablosu: White (8099166) satirinda S 0 GORUNUR', (bool)preg_match('/White \(8099166\)<\/td>\s*<td class="mono">0<\/td>/', $mm[0] ?? ''));
$t('stok tablosu: check-collar satiri 97',            (bool)preg_match('/White · Check collar \(8071621\)<\/td>\s*(?:<td class="mono">\d+<\/td>){5}\s*<td class="mono"><b>97<\/b>/', $mm[0] ?? ''));
$t('renk noktalari 8 (model numarali adlar cozuldu)', substr_count($pg, 'class="cdot" title="') === 8);
$t('galeri: 8 fotograf yolu',                         substr_count($pg, '/uploads/burberry-polo/burberry-') >= 8);
[, , $de] = $http('GET', '/product?id=bur-pique-polo-2909&lang=de', '', true);
$t('Almanca: "Schwarz · Check collar (8071620)" (taban cevrildi, ek kaldi)', str_contains($de, 'Schwarz · Check collar (8071620)'));
$t('Almanca: "Grün (8099164)"',                       str_contains($de, 'Grün (8099164)'));
$t('Almanca: stok tablosu basligi "Farben" + "Gesamt"', str_contains($de, '<th>Farben</th>') && str_contains($de, 'Gesamt'));
/* KONTROL: bayraksiz, paketsiz, renkli ilan ESKISI gibi -- adet secici yok, stok tablosu yok. */
[$st5, , $ctl] = $http('GET', '/product?id=bur-8014004', '', true);
$t('kontrol ilani: 200, sayi alani YOK, stok tablosu YOK', $st5 === '200' && !str_contains($ctl, 'data-color="') && !str_contains($ctl, 'stocktbl'));

echo "\n== 6. /order: renk basina adet kapisi sunucuda ==\n";
$order = function (array $cart) use ($http): array {
    $f = ['company' => 'Probe Handel GmbH', 'name' => 'Probe', 'email' => 'probe.buyer@example.test',
          'consent' => '1', 'country' => 'Germany', 'pay' => 'bank', 'cart' => json_encode($cart)];
    return $http('POST', '/order', http_build_query($f), true);
};
$rows = function () use ($pub): int {
    $f = $pub . '/data/orders.csv';
    return is_file($f) ? max(0, count(file($f, FILE_SKIP_EMPTY_LINES)) - 1) : 0;
};
$item = fn(array $cols, int $qty) => ['id' => 'bur-pique-polo-2909', 'sku' => 'BUR-PIQUE-POLO', 'qty' => $qty, 'unit' => 59.9, 'colors' => $cols];
$n0 = $rows();
[$os1, $oh1] = $order([$item(['Green (8099164) ×7', 'Black · Check collar (8071620) ×13'], 20)]);
$t('7 + 13 = 20: siparis YAZILDI (302 order-confirm)', $rows() === $n0 + 1 && $os1 === '302' && (bool)preg_match('#^Location: /order-confirm\?ref=#mi', $oh1));
$last = (string)(file($pub . '/data/orders.csv')[$rows()] ?? '');
$t('siparis satiri renk basina adedi tasiyor',        str_contains($last, 'Green (8099164) ×7') && str_contains($last, 'Black · Check collar (8071620) ×13'));
$t('siparis satiri SKU ve 20 adet',                   str_contains($last, '20x BUR-PIQUE-POLO'));
[$os2, $oh2] = $order([$item(['Green (8099164) ×7', 'Black (8096425) ×12'], 19)]);
$t('7 + 12 = 19 < MOQ 20: err=colors, siparis YOK',   $rows() === $n0 + 1 && $os2 === '302' && (bool)preg_match('#^Location: /cart\?err=colors#mi', $oh2));
[$os3, $oh3] = $order([$item(['Pink ×20'], 20)]);
$t('ilanda olmayan renk: err=colors, siparis YOK',    $rows() === $n0 + 1 && (bool)preg_match('#^Location: /cart\?err=colors#mi', $oh3));
$order([$item(['Green (8099164) ×20'], 20)]);
$t('tek renk 20: gecer (min_colors 1)',               $rows() === $n0 + 2);
$t('sunucu gunlugunde PHP hatasi yok',                !preg_match('/PHP (Warning|Fatal|Notice|Deprecated)/', (string)@file_get_contents($elog)));
proc_terminate($proc); proc_close($proc);
exec('rm -rf ' . escapeshellarg($sand));

echo "\n== 7. mektup: tek ilan, renk basina stok ==\n";
$sh = vestra_listing_colour_shots($big);
$t('parti: 8 rengin 8\'i fotografini buldu, bagsiz foto 0', count($sh['pairs']) === 8 && !$sh['missing'] && !$sh['unbound']);
$blk = [['p' => $big, 'pairs' => $sh['pairs'], 'rungs' => vestra_price_ladder($big), 'tag' => $big['sku'], 'stock' => vestra_stock_real($big)]];
[$s, $b, $o] = vestra_tpl_listing_offer('en', 'Probe GmbH', $blk, true, 'https://vestrasales.com/wholesale/burberry', false);
$t('konu: "1 models" DEGIL, renk sayisi',             $s === 'VESTRA — Burberry offer: 8 colours');
$t('govde: renk basina stok satiri (8) + toplam',     substr_count($b, ' — ') >= 8 && str_contains($b, '  Green (8099164): S 2 · M 6 · L 5 · XL 4 · XXL 2 — 19 pieces')
                                                       && str_contains($b, '  Total in stock: 322 pieces'));
$t('govde: White (8099166) S 0 vaat etmiyor, gosteriyor', str_contains($b, '  White (8099166): S 0 · M 9'));
$t('govde: kademeler kayittan',                       str_contains($b, 'from 20 pcs EUR 59.90 · from 50 pcs EUR 54.90 · from 100 pcs EUR 49.90'));
$t('foto seridi 8 kare, etiketler renk adi',          count($o['shots']) === 8 && $o['shots'][5]['label'] === 'Black · Check collar (8071620)');
$t('HTML satirlari: renk basina stok + toplam',       count(array_filter($o['rows'], fn($r) => str_starts_with($r['label'], 'In stock · '))) === 8
                                                       && count(array_filter($o['rows'], fn($r) => $r['label'] === 'Total in stock')) === 1);
[$sd, $bd] = vestra_tpl_listing_offer('de', 'Probe GmbH', $blk, false, '', true);
$t('DE lead: konu "8 Farben", "Gesamt auf Lager", fiyat YOK', $sd === 'VESTRA — Burberry Angebot: 8 Farben' && str_contains($bd, 'Gesamt auf Lager: 322 Stück') && !str_contains($bd, '59,90'));
$one = $blk; $one[0]['pairs'] = [$sh['pairs'][0]]; $one[0]['stock'] = null;
[$s1] = vestra_tpl_listing_offer('en', 'X', $one, false, '', false);
$t('tek ilan tek renk: konu yalniz marka',            $s1 === 'VESTRA — Burberry offer');
/* Duz stoklu, cok ilanli eski yol DEGISMEDI (fp_offer_test de tutuyor). */
$flatBlk = [['p' => ['id' => 'a', 'brand' => 'Burberry', 'name' => 'A', 'moq' => 20], 'pairs' => [['colour' => 'Green', 'img' => 'x']], 'rungs' => [],
             'stock' => ['sizes' => ['S' => 2, 'M' => 6], 'total' => 8, 'real' => true]],
            ['p' => ['id' => 'b', 'brand' => 'Burberry', 'name' => 'B', 'moq' => 20], 'pairs' => [['colour' => 'Navy', 'img' => 'y']], 'rungs' => []]];
[$sf, $bf] = vestra_tpl_listing_offer('en', 'X', $flatBlk, false, '', false);
$t('duz stok: eski tek satir bicimi korunuyor',       str_contains($bf, "In stock: S 2 · M 6 — 8 pieces\n") && $sf === 'VESTRA — Burberry offer: 2 models');

echo "\n== 8. kablolama ==\n";
$prod = $src('product.php');
$code = '';
foreach (token_get_all($prod) as $tk) { if (is_array($tk) && in_array($tk[0], [T_COMMENT, T_DOC_COMMENT], true)) continue; $code .= is_array($tk) ? $tk[1] : $tk; }
$t('product.php: kip karari TEK fonksiyondan (inline kopya yok)',
   substr_count($code, 'vestra_is_colorqty_listing($p)') === 1 && !preg_match("/\\\$p\\['size_step'\\]\\s*\\?\\?\\s*0\\)\\s*>\\s*1/", $code));
$t('product.php: JS [data-color] (select ile sinirli degil)', substr_count($code, "querySelectorAll('[data-color]')") >= 3 && !str_contains($code, "select[data-color]"));
$t('product.php: yonlendirme 404\'ten ONCE',            strpos($code, 'vestra_product_redirect(') !== false && strpos($code, 'vestra_product_redirect(') < strpos($code, "http_response_code(404)"));
$t('product.php: stok tablosu vestra_stock_real/rows',  str_contains($code, 'vestra_stock_real($p)') && str_contains($code, 'vestra_stock_rows('));
$t('product.php: inc/stock.php kendi require ediyor (KURAL 15)', str_contains($code, "require_once __DIR__.'/inc/stock.php'"));
$t('product.php: ham palet aramasi kalmadi ($pal[$cn])', !str_contains($code, "\$pal[\$cn]"));
$t('order.php/offer.php: parse fonksiyonlari degismedi', str_contains($src('order.php'), 'vestra_parse_colorqty_tokens($p') && str_contains($src('offer.php'), 'vestra_parse_colorqty($p'));
$t('sozluk: "Colours" ve "Total" 8 dilde (yeni anahtar yok)',
   count(array_filter(glob($root . '/inc/lang/*.php'), fn($f) => str_contains((string)file_get_contents($f), "'Colours'") && str_contains((string)file_get_contents($f), "'Total'"))) === 8);
$sp = (string)file_get_contents(__DIR__ . '/../scripts/set_product.php');
$t('set_product: colorqty + redirect_to izinli, bool/hedef denetimli',
   str_contains($sp, "'colorqty','redirect_to'") && str_contains($sp, "colorqty true ya da false") && str_contains($sp, "redirect_to ilanin kendisi olamaz"));
$wf = (string)file_get_contents(__DIR__ . '/../.github/workflows/add-products.yml');
$t('add-products: renk basina stok + colorqty bayragi', str_contains($wf, "stock rengi '{\$cn}' ilanin colors listesinde yok") && str_contains($wf, "\$row['colorqty'] = true;"));

echo "\n== 9. parti + katlama dosyasi ==\n";
$t('parti: tek ilan, 8 renk, 8 foto, colorqty, min_colors 1, adim YOK',
   count($batch) === 1 && count($big['colors']) === 8 && count($big['images']) === 8 && ($big['colorqty'] ?? null) === true
   && (int)$big['min_colors'] === 1 && !isset($big['size_step']) && $big['sku'] === 'BUR-PIQUE-POLO');
$t('parti: stok anahtarlari = renk listesi (sira dahil)', array_keys($big['stock']) === $big['colors']);
$pdf = ['8099164' => [2, 6, 5, 4, 2], '8096425' => [3, 9, 8, 6, 3], '8099165' => [3, 10, 10, 5, 2], '8099166' => [0, 9, 9, 6, 3],
        '8099167' => [2, 6, 6, 4, 2], '8071620' => [5, 15, 15, 10, 5], '8072661' => [5, 15, 15, 10, 5], '8071621' => [10, 28, 30, 17, 12]];
$allOk = true;
foreach ($big['stock'] as $cn => $m) {
    preg_match('/\((\d{7})\)$/', $cn, $mm);
    if (!isset($pdf[$mm[1] ?? '']) || array_values($m) !== $pdf[$mm[1]] || array_keys($m) !== ['S', 'M', 'L', 'XL', 'XXL']) $allOk = false;
}
$t('parti: her rengin stogu PDF ile birebir (322 ad.)', $allOk && vestra_stock_real($big)['total'] === 322);
$t('parti: kademeler 20/50/100 = 59,90/54,90/49,90, mode fixed',
   $big['tiers'] === [['min' => 20, 'price' => 59.9], ['min' => 50, 'price' => 54.9], ['min' => 100, 'price' => 49.9]] && $big['mode'] === 'fixed');
$t('parti: aciklama beden serisi ve stok rakami TASIMIYOR (tek kaynak stok alani)', !str_contains($big['desc'], '×') && !preg_match('/\b(19|29|30|27|50|97|322)\b/', $big['desc']));
$fold = json_decode((string)file_get_contents(__DIR__ . '/../product-fixes/burberry-polo-fold-into-one.json'), true);
$oldIds = ['bur-8099164', 'bur-8096425', 'bur-8099165', 'bur-8099166', 'bur-8099167', 'bur-8071620', 'bur-8072661', 'bur-8071621'];
$t('katlama: 8 eski id, hepsi rejected + redirect_to yeni ilan, expect 1',
   count($fold) === 8 && array_map(fn($r) => $r['match'], $fold) === $oldIds
   && !array_filter($fold, fn($r) => $r['status'] !== 'rejected' || $r['redirect_to'] !== 'bur-pique-polo-2909' || ($r['expect'] ?? 0) !== 1));

echo "\nsingle_listing_polo_test: " . ($fail === 0 ? "{$ok} iddia gecti\n" : "{$ok} gecti, {$fail} HATA\n");
exit($fail === 0 ? 0 : 1);
