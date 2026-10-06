<?php
/* Numune ODEME LINKI (25 Eyl 2026 — Ecokemet, Lacoste Zip Up Fleece Hoodie,
 * SH9626, EUR 80). Operator: "bu ürün icin bir ödeme linki olustur ve
 * müsteriye gönder" + "80 eur olucak adresini girebilsin ident nr. ve numune
 * yazsin".
 *
 * IKI YON: token dogruysa sayfa acilir, yanlissa 404; acik bir link varken
 * ikinci link kurulmaz, ODENMIS kayit acik sayilmaz; odeme platform hesabinda
 * (acct_id YOK). Sayfalar kum havuzu kopyasinda GERCEKTEN cizdiriliyor —
 * php -l bu depoda iki calisma-zamani hatasini gecirmisti. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  HATA ").$n."\n"; };

$root = dirname(__DIR__);
$sand = sys_get_temp_dir().'/vestra_splink_'.getmypid();
@mkdir($sand, 0777, true);
define('VESTRA_SAMPLES', $sand.'/samples.json');
define('VESTRA_ACCOUNTS', $sand.'/accounts.json');
require_once $root.'/vestra/inc/products.php';
require_once $root.'/vestra/inc/samples.php';
require_once $root.'/vestra/inc/notify.php';
$t('kum havuzu: samples_file gercek dosya DEGIL', samples_file() === $sand.'/samples.json');

$p = ['id'=>'lac-zip-hoodie','brand'=>'Lacoste','name'=>'Zip Up Fleece Hoodie','sku'=>'SH9626','seller_uid'=>'7ab30f26afedd840'];
$buyer = ['id'=>'0d3670a2500e5b15','email'=>'buyer@example.test','name'=>'AYMAR','company'=>'Ecokemet','lang'=>'fr'];

echo "== 1. Stripe satiri: NUMUNE kelimesi + IDENT no ==\n";
$fr = sample_link_line_name($p, 'fr');
$t('fr: "Échantillon" yazar', str_starts_with($fr, 'Échantillon — '));
$t('fr: ident no. SH9626 yazar', str_contains($fr, 'Ident n° SH9626'));
$t('fr: urunun adi yazar', str_contains($fr, 'Lacoste Zip Up Fleece Hoodie'));
$t('de: Muster + Ident-Nr.', str_starts_with(sample_link_line_name($p, 'de'), 'Muster — ') && str_contains(sample_link_line_name($p, 'de'), 'Ident-Nr. SH9626'));
$t('bilinmeyen dil -> ingilizce', str_starts_with(sample_link_line_name($p, 'xx'), 'Sample — '));
$noSku = sample_link_line_name(['brand'=>'X','name'=>'Y'], 'en');
$t('SKU yoksa bos "Ident no." satiri yazilmaz', $noSku === 'Sample — X Y');

echo "\n== 2. kayit: tutar, platform odemesi, jeton ==\n";
$rec = sample_link_record($buyer, $p, 80.0, 'fr');
$t('ref SPL-', (bool)preg_match('/^SPL-[0-9A-F]{8}$/', $rec['ref']));
$t('via=link', $rec['via'] === 'link');
$t('jeton 32 hex', (bool)preg_match('/^[0-9a-f]{32}$/', $rec['pay_token']));
$t('tutar 80.00', $rec['amount'] === 80.0);
$t('durum pending', $rec['status'] === 'pending');
$t('PLATFORM odemesi: acct_id YOK (KURAL 33)', !isset($rec['acct_id']));
$t('satici kaydi duruyor (hangi ilandan)', ($rec['seller_uid'] ?? '') === '7ab30f26afedd840');
$t('alici hesabi baglandi', $rec['buyer_id'] === '0d3670a2500e5b15');
$rec2 = sample_link_record($buyer, $p, 80.0, 'fr');
$t('iki kayit iki FARKLI jeton', $rec2['pay_token'] !== $rec['pay_token']);

echo "\n== 3. jeton kontrolu (iki yon) ==\n";
$t('dogru jeton GECER', sample_link_token_ok($rec, $rec['pay_token']));
$t('yanlis jeton REDDEDILIR', !sample_link_token_ok($rec, str_repeat('0', 32)));
$t('bos jeton REDDEDILIR', !sample_link_token_ok($rec, ''));
$t('kayit yok -> REDDEDILIR', !sample_link_token_ok(null, $rec['pay_token']));
$noLink = $rec; unset($noLink['via']);
$t('urun sayfasi numunesi (via yok) link ile ACILMAZ', !sample_link_token_ok($noLink, $rec['pay_token']));
$noTok = $rec; $noTok['pay_token'] = '';
$t('jetonsuz kayit bos jetonla ACILMAZ', !sample_link_token_ok($noTok, ''));

echo "\n== 4. acik link YENIDEN kullanilir, ikinci link kurulmaz ==\n";
sample_save($rec);
$f = sample_link_find_open('0d3670a2500e5b15', 'lac-zip-hoodie');
$t('acik kayit bulunur', ($f['ref'] ?? '') === $rec['ref']);
$t('baska ilan icin bulunmaz', sample_link_find_open('0d3670a2500e5b15', 'lac-fleece-hoodie') === null);
$t('baska alici icin bulunmaz', sample_link_find_open('nobody', 'lac-zip-hoodie') === null);
$t('URL jetonu tasiyor', sample_link_url($rec) === 'https://vestrasales.com/sample-pay?ref='.$rec['ref'].'&t='.$rec['pay_token']);

echo "\n== 5. ADRES: Stripe sayfasinda girilen adres KAYDA iner ==\n";
$sess = json_decode(json_encode([
  'shipping_details' => ['name'=>'Aymar X', 'address'=>['line1'=>'12 Rue A','line2'=>'','postal_code'=>'75001','city'=>'Paris','country'=>'FR']],
  'customer_details' => ['phone'=>'+33 1 23'],
]));
$ship = sample_ship_from_session($sess);
$t('ad', ($ship['name'] ?? '') === 'Aymar X');
$t('posta kodu', ($ship['postal_code'] ?? '') === '75001');
$t('ulke', ($ship['country'] ?? '') === 'FR');
$t('kurye telefonu', ($ship['phone'] ?? '') === '+33 1 23');
$old = json_decode(json_encode(['shipping'=>['name'=>'B','address'=>['line1'=>'L','postal_code'=>'1000','city'=>'Lisboa','country'=>'PT']]]));
$t('eski "shipping" alani da okunur', (sample_ship_from_session($old)['city'] ?? '') === 'Lisboa');
$t('adres yoksa null (uydurulmaz)', sample_ship_from_session(json_decode('{}')) === null);
$t('tek satir: "12 Rue A, 75001 Paris"', str_contains(sample_ship_line($ship), '12 Rue A, 75001 Paris, FR'));
$t('bos ship_to -> bos satir', sample_ship_line(null) === '');

$paid = sample_mark_paid($rec['ref'], 'pi_test', $ship);
$t('odendi + adres KAYITTA', ($paid['status'] ?? '') === 'paid' && (($paid['ship_to']['postal_code'] ?? '') === '75001'));
$t('ikinci isaret hicbir sey yapmaz (idempotent)', sample_mark_paid($rec['ref'], 'pi_other', null) === null);
$t('ODENMIS kayit "acik link" SAYILMAZ', sample_link_find_open('0d3670a2500e5b15', 'lac-zip-hoodie') === null);
$t('eski imza (adressiz) hala calisir', function_exists('sample_mark_paid') && (new ReflectionFunction('sample_mark_paid'))->getNumberOfRequiredParameters() === 2);

echo "\n== 6. MEKTUP: rakamlar parametreden, link metinde de ==\n";
$url = 'https://vestrasales.com/sample-pay?ref=SPL-TEST&t=abc';
[$s, $b, $o] = vestra_sample_link_text('fr', 'AYMAR', 'Lacoste Zip Up Fleece Hoodie', 'SH9626', 80.0, $url);
$t('fr konu ident no. tasiyor', str_contains($s, 'SH9626') && str_contains($s, 'échantillon'));
$t('fr tutar virgullu: 80,00 €', str_contains($b, '80,00 €'));
$t('fr "Ident n°" satiri', str_contains($b, 'Ident n° : SH9626'));
$t('fr adres Stripe sayfasinda girilir diyor', str_contains($b, 'adresse de livraison'));
$t('fr link GOVDEDE de (dugme dusebilir)', str_contains($b, $url));
$t('dugme linke gidiyor', ($o['button']['url'] ?? '') === $url);
[, $be] = vestra_sample_link_text('en', 'X', 'Y', 'Z1', 123.5, $url);
$t('en tutar noktali: €123.50', str_contains($be, '€123.50'));
[, $bx] = vestra_sample_link_text('xx', 'X', 'Y', 'Z1', 10, $url);
$t('bilinmeyen dil -> ingilizce', str_contains($bx, 'Ident no.: Z1'));
$src = (string)file_get_contents($root.'/vestra/inc/notify.php');
preg_match('/function vestra_sample_link_text\(.*?\n\}\n/s', $src, $m);
$body = $m[0] ?? '';
$t('govdede GOMULU rakam yok (80 / SH9626 metne yazilmadi)', $body !== '' && !preg_match('/\b80\b|SH9626/', $body));

echo "\n== 7. Stripe oturumu: platform, adres toplanir, basari sayfasi jetonlu ==\n";
$ssrc = (string)file_get_contents($root.'/vestra/inc/samples.php');
preg_match('/function sample_link_session\(.*?\n\}\n/s', $ssrc, $m);
$sb = $m[0] ?? '';
$t('govde bulundu', $sb !== '');
$t('adres toplanir (shipping_address_collection)', str_contains($sb, "'shipping_address_collection'"));
$t('telefon toplanir (kurye)', str_contains($sb, "'phone_number_collection'"));
$t('kind=sample (webhook ayni dali kullanir)', str_contains($sb, "'kind' => 'sample'"));
$t('PLATFORM hesabi: stripe_api 3 argumanla (Stripe-Account YOK)',
   (bool)preg_match("/stripe_api\('POST', '\/v1\/checkout\/sessions', \\\$params\)/", $sb));
$t('basari sayfasi jetonu tasiyor (girissiz alici)', str_contains($sb, "'&paid=1&t='"));
$t('AB listesi tek kaynaktan', str_contains($sb, 'sample_eu_countries()'));
$t('urun sayfasi numunesi de ayni listeyi okuyor',
   str_contains((string)file_get_contents($root.'/vestra/sample-checkout.php'), '$EU_COUNTRIES = sample_eu_countries();'));
$wh = (string)file_get_contents($root.'/vestra/stripe/webhook.php');
$t('webhook adresi KAYDA yaziyor', str_contains($wh, 'sample_mark_paid($ref, $pi, sample_ship_from_session($obj))'));

echo "\n== 8. CIZIM: sample-pay / sample-confirm kum havuzu kopyasinda ==\n";
$sb2 = sys_get_temp_dir().'/vestra_splink_site_'.getmypid();
@mkdir($sb2, 0777, true);
exec('cp -r '.escapeshellarg($root.'/vestra').' '.escapeshellarg($sb2.'/vestra').' 2>&1', $o2, $rc);
$t('kopya kuruldu', $rc === 0);
exec('rm -rf '.escapeshellarg($sb2.'/vestra/data'));
@mkdir($sb2.'/vestra/data', 0777, true);
$live = sample_link_record($buyer, $p, 80.0, 'fr');
file_put_contents($sb2.'/vestra/data/samples.json', json_encode([$live['ref'] => $live]));
$paidRec = sample_link_record($buyer, $p, 80.0, 'fr'); $paidRec['status'] = 'paid';
file_put_contents($sb2.'/vestra/data/samples.json', json_encode([$live['ref'] => $live, $paidRec['ref'] => $paidRec]));
$render = function (string $page, array $get) use ($sb2): string {
  $g = var_export($get, true);
  file_put_contents($sb2.'/r.php', "<?php error_reporting(E_ALL); ini_set('display_errors','1');\n"
    ."putenv('STRIPE_SECRET_KEY');\n"
    ."\$_GET=$g; \$_SERVER['REQUEST_METHOD']='GET'; \$_SERVER['REQUEST_URI']='/$page'; \$_SERVER['HTTP_HOST']='localhost'; \$_SERVER['REMOTE_ADDR']='127.0.0.1';\n"
    ."ob_start(); include __DIR__.'/vestra/$page.php'; echo ob_get_clean();\n");
  return (string)shell_exec('cd '.escapeshellarg($sb2).' && php r.php 2>&1');
};
$h = $render('sample-pay', ['ref'=>$live['ref'], 't'=>str_repeat('0', 32)]);
$t('yanlis jeton -> 404 sayfasi', str_contains($h, '404'));
$t('yanlis jetonda odeme yolu yok', !str_contains($h, 'payment page could not be opened'));
$h = $render('sample-pay', ['ref'=>$live['ref'], 't'=>$live['pay_token']]);
$t('dogru jeton + Stripe yok -> "acilamadi" sayfasi (fatal DEGIL)', str_contains($h, 'payment page could not be opened'));
$t('PHP uyarisi/fatal yok', !preg_match('/(Fatal error|Warning|Notice|Deprecated):/', $h));
$t('sayfa noindex', str_contains($h, 'noindex'));
/* Jeton sayfada YALNIZ dil secicinin kendi-kok baglantilarinda durur (secici
   sorgu parametrelerini korur — KURAL 12). Mutlak ya da baska bir alan adina
   giden bir URL'de gecmemeli: o, jetonu ucuncu tarafa tasimak olurdu. */
preg_match_all('/(?:href|src|content|action)="([^"]*'.$live['pay_token'].'[^"]*)"/', $h, $mm);
$foreign = array_filter($mm[1], fn($u) => !str_starts_with($u, '/sample-pay?'));
$t('jeton yalniz kendi-kok baglantida (yabanci/mutlak URL YOK)', $mm[1] !== [] ? $foreign === [] : true);
$t('jeton metin olarak basilmiyor', substr_count($h, $live['pay_token']) === count($mm[1]));
$h = $render('sample-pay', ['ref'=>$paidRec['ref'], 't'=>$paidRec['pay_token']]);
$t('odenmis kayit -> yonlendirme (yeni odeme sayfasi YOK)', trim($h) === '');
$h = $render('sample-confirm', ['ref'=>$live['ref'], 't'=>$live['pay_token']]);
$t('onay sayfasi GIRISSIZ jetonla acilir', str_contains($h, 'Finishing up') && !preg_match('/(Fatal error|Warning):/', $h));
$h = $render('sample-confirm', ['ref'=>$live['ref'], 't'=>'x']);
$t('onay sayfasi yanlis jetonla ACILMAZ (girise yonlenir)', !str_contains($h, 'Finishing up'));

/* CIFT TAHSILAT MUHAFAZASI: Stripe'siz kum havuzunda bu dal kosamiyor, o
   yuzden sirasi kaynaktan olculuyor — falsifikasyonda ilk hali YESIL kalmisti
   ("hic dusemeyen bir iddia, iddia degildir"). Eski oturum ODENMIS mi sorusu,
   yeni oturum kurulmadan ONCE sorulmali; odenmisse kayit isaretlenip onaya gidilir. */
$ps = (string)file_get_contents($root.'/vestra/sample-pay.php');
$pPaid = strpos($ps, "if ((\$old->payment_status ?? '') === 'paid') {");
$pNew  = strpos($ps, '$session = sample_link_session(');
$t('eski oturum ODENDI mi sorusu yeni oturumdan ONCE', $pPaid !== false && $pNew !== false && $pPaid < $pNew);
$paidBranch = $pPaid !== false ? substr($ps, $pPaid, 400) : '';
$t('odenmis oturum kaydi isaretler + onaya gider (ikinci odeme sayfasi YOK)',
   str_contains($paidBranch, 'sample_mark_paid(') && str_contains($paidBranch, "header('Location: ' . \$confirmUrl"));
$t('acik oturum YENIDEN kullanilir (24 saatte bir yeni oturum, her tikta degil)',
   str_contains($ps, "if ((\$old->status ?? '') === 'open' && !empty(\$old->url))"));

echo "\n== 9. is akisi: link/jeton kutuge basilmaz, Stripe once, mektup sonra ==\n";
$yml = (string)file_get_contents($root.'/.github/workflows/seller-products.yml');
$a = strpos($yml, "admin_mode == 'sample_link'");
$z = strpos($yml, 'PHPEOF', strpos($yml, "<<'PHPEOF'", $a) + 12);
$step = $a !== false && $z !== false ? substr($yml, $a, $z - $a) : '';
$t('adim var', $step !== '');
$t('yalniz ID TAM esitlik (ad parcasi yok)', str_contains($step, "=== \$who") && !str_contains($step, 'str_contains($hay'));
$t('link kutuge "t=***" ile', str_contains($step, "&t=*** (jeton"));
$t('pay_token hicbir printf icinde degil', !preg_match("/printf\([^;]*pay_token'\]\)/", $step) || str_contains($step, "strlen((string)\$back['pay_token'])"));
$sp = strpos($step, 'sample_link_session($back'); $mp = strpos($step, 'vestra_send_mail(');
$t('Stripe oturumu MEKTUPTAN ONCE (calismayan link gitmez)', $sp !== false && $mp !== false && $sp < $mp);
$t('Stripe tutari kontrol ediliyor', str_contains($step, "amount_total"));
$t('acik link varken ikinci link yok', str_contains($step, 'sample_link_find_open('));
$t('kuru kosu varsayilan', str_contains($step, 'if (!$apply)'));

exec('rm -rf '.escapeshellarg($sand).' '.escapeshellarg($sb2));
echo "\n$ok ok, $bad hata\n";
exit($bad ? 1 : 0);
