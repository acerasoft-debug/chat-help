<?php
/* TESLİMAT ADRES DEFTERİ + POSTA KODU (24 Eyl 2026, operatör: "adreslerde postcode
 * görünmüyor bu giriliyor mu; hesaplarda Lieferadresse 1. 2. 3. olarak, sipariş için
 * seçebilsin, ad koyabilsin").
 *
 * İki yön de tutuluyor: kabul edilmesi gereken (Dubai'de posta kodsuz adres,
 * Türkmenistan, eski sipariş notları) ve reddedilmesi gereken (posta kodsuz Berlin,
 * Türkiye, olmayan yuva, tarayıcıdan gelen sahte metin). Kayıt KUM HAVUZUNDA yazılıyor;
 * VESTRA_ACCOUNTS / VESTRA_DATA_DIR defined() korumalı, gerçek data/'ya dokunulmuyor. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra_ab_'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
define('VESTRA_DATA_DIR', $sand.'/data');
file_put_contents(VESTRA_ACCOUNTS, json_encode([
    ['id' => 'b1', 'type' => 'buyer', 'email' => 'b1@example.test', 'company' => 'Boutique B1',
     'country' => 'Germany', 'address' => 'Hauptstraße 1', 'postcode' => '10115', 'city' => 'Berlin'],
    ['id' => 'b2', 'type' => 'buyer', 'email' => 'b2@example.test', 'company' => 'Legacy B2',
     'country' => 'Germany', 'address' => 'Hauptstraße 1, 10115 Berlin', 'postcode' => '10115', 'city' => 'Berlin'],
]));
require $root.'/inc/products.php';
require_once $root.'/inc/auth.php';
require_once $root.'/inc/orders.php';
require_once $root.'/inc/addresses.php';
require_once $root.'/inc/invoice.php';

echo "== 1. Tek biçimlendirici ==\n";
$de = ['label' => 'Lager', 'recipient' => 'Boutique B1 – Lager', 'street' => 'Hauptstr. 12',
       'postcode' => '10115', 'city' => 'Berlin', 'country' => 'Deutschland', 'phone' => '+49 30 1'];
$t('DE: alıcı, sokak, "PLZ Ort", ülke, telefon',
   vestra_ship_addr_line($de) === 'Boutique B1 – Lager, Hauptstr. 12, 10115 Berlin, Deutschland, Tel +49 30 1');
$t('GB: şehir posta kodundan ÖNCE',
   vestra_ship_addr_town(['postcode' => 'NW1 6XE', 'city' => 'London', 'country' => 'United Kingdom']) === 'London NW1 6XE');
$t('ad (label) satıra GİRMEZ -- kurye etiketinde "Showroom Nord" yazmamalı',
   !str_contains(vestra_ship_addr_line(['label' => 'Showroom Nord'] + $de), 'Showroom Nord'));
$t('posta kodu büyük harfe', vestra_ship_addr_clean(['postcode' => 'nw1 6xe'])['postcode'] === 'NW1 6XE');
$t('satır sonu tek satıra iner', !str_contains(vestra_ship_addr_line(['street' => "A\nB 1", 'postcode' => '1', 'city' => 'C', 'country' => 'D']), "\n"));
$acc1 = auth_accounts()[0]; $acc2 = auth_accounts()[1];
$t('fatura satırı ayrı postcode/city alanlarını OKUR', vestra_account_billing_line($acc1) === 'Hauptstraße 1, 10115 Berlin');
$t('adres metni zaten içeriyorsa TEKRARLAMAZ', vestra_account_billing_line($acc2) === 'Hauptstraße 1, 10115 Berlin');
$t('posta kodu tespiti: DE/GB/NL/PL', vestra_address_has_postcode('Hauptstr 1, 10115 Berlin')
   && vestra_address_has_postcode('1 Baker St, London NW1 6XE') && vestra_address_has_postcode('Damrak 1, 1012 LG Amsterdam')
   && vestra_address_has_postcode('ul. Nowa 1, 00-001 Warszawa'));
$t('posta kodu tespiti: yalnız sokak numarası posta kodu DEĞİL', !vestra_address_has_postcode('Hauptstraße 1'));

echo "== 2. Doğrulama (iki yön) ==\n";
$base = ['street' => 'X 1', 'postcode' => '', 'city' => 'Y'];
$t('Berlin posta kodsuz REDDEDİLİR', in_array('postcode', vestra_ship_addr_errors($base + ['country' => 'Germany']), true));
$t('Dubai (UAE) posta kodsuz GEÇER', vestra_ship_addr_errors($base + ['country' => 'UAE']) === []);
$t('Katar posta kodsuz GEÇER', vestra_ship_addr_errors($base + ['country' => 'Qatar']) === []);
$t('Hong Kong posta kodsuz GEÇER', vestra_ship_addr_errors($base + ['country' => 'Hong Kong']) === []);
$t('AT (Avusturya) muaf DEĞİL -- yakın komşu', in_array('postcode', vestra_ship_addr_errors($base + ['country' => 'AT']), true));
$t('"Qatar Street Ltd" gibi alt dize muaf DEĞİL', !vestra_postcode_optional('Qatar Trading Co'));
$t('Türkiye REDDEDİLİR (KURAL 2g)', in_array('country_tr', vestra_ship_addr_errors(['street' => 'X 1', 'postcode' => '34430', 'city' => 'Istanbul', 'country' => 'Türkiye']), true));
$t('Türkmenistan GEÇER', vestra_ship_addr_errors(['street' => 'X 1', 'postcode' => '744000', 'city' => 'Ashgabat', 'country' => 'Turkmenistan']) === []);
$t('sokak/şehir/ülke zorunlu', vestra_ship_addr_errors([]) === ['street', 'city', 'country', 'postcode']);

echo "== 3. Yazma (kum havuzu, geri okunur) ==\n";
$r = vestra_ship_addr_save('b1', 2, $de);
$t('2. yuva yazıldı', !empty($r['ok']));
$book = vestra_ship_addresses(vestra_ship_addr_account('b1'));
$t('geri okunan satır yazılanla aynı', isset($book[2]) && vestra_ship_addr_line($book[2]) === vestra_ship_addr_line($de));
$t('1. yuva BOŞ (yuvalar sabit)', !isset($book[1]));
vestra_ship_addr_save('b1', 1, ['street' => 'A 1', 'postcode' => '1010', 'city' => 'Wien', 'country' => 'Österreich']);
vestra_ship_addr_delete('b1', 1);
$book = vestra_ship_addresses(vestra_ship_addr_account('b1'));
$t('1. silinince 2. hâlâ "2." -- numara KAYMAZ', array_keys($book) === [2]);
$t('4. yuva reddedilir', (vestra_ship_addr_save('b1', 4, $de)['error'] ?? '') === 'slot');
$t('0. yuva reddedilir', (vestra_ship_addr_save('b1', 0, $de)['error'] ?? '') === 'slot');
$badSave = vestra_ship_addr_save('b1', 3, ['street' => 'X 1', 'city' => 'Berlin', 'country' => 'Germany']);
$t('eksik posta kodu YAZILMAZ', ($badSave['error'] ?? '') === 'fields' && !isset(vestra_ship_addresses(vestra_ship_addr_account('b1'))[3]));
$tr = vestra_ship_addr_save('b1', 3, ['street' => 'X 1', 'postcode' => '34430', 'city' => 'Istanbul', 'country' => 'Turkey']);
$t('Türkiye YAZILMAZ ve kodu ayrı', ($tr['error'] ?? '') === 'country');
$t('başka hesaba dokunulmadı', vestra_ship_addresses(vestra_ship_addr_account('b2')) === []);

echo "== 4. Kasa seçimi SUNUCUDA çözülür ==\n";
$acc = vestra_ship_addr_account('b1');
$res = vestra_ship_addr_resolve($acc, '2', 'Sahte Str. 9, 00000 Nirgendwo');
$t('yuva: metin HESAPTAN, tarayıcının metni yok sayılır', ($res['address'] ?? '') === vestra_ship_addr_line($de));
$t('billing: teslimat notu yok', (vestra_ship_addr_resolve($acc, 'billing', 'X')['address'] ?? null) === '');
$t('other: serbest metin', (vestra_ship_addr_resolve($acc, 'other', " Rue X 5,\n Paris ")['address'] ?? '') === 'Rue X 5, Paris');
$t('eski form (pick yok) = serbest metin', (vestra_ship_addr_resolve($acc, '', 'Rue X 5')['address'] ?? '') === 'Rue X 5');
$t('boş yuva REDDEDİLİR', (vestra_ship_addr_resolve($acc, '1', '')['error'] ?? '') === 'missing');
$t('saçma değer REDDEDİLİR', (vestra_ship_addr_resolve($acc, '../2', '')['error'] ?? '') === 'pick');
$t('oturumsuz hesapta yuva REDDEDİLİR', isset(vestra_ship_addr_resolve(null, '2', '')['error']));

echo "== 5. Deliver to: adres ilk \". \"da KESİLMEZ ==\n";
foreach (['Hauptstr. 12, 10115 Berlin, Germany', 'Via S. Maria 4, 20121 Milano', 'St. Gallen Str. 3, 9000 St. Gallen'] as $a) {
    $n = 'Payment: Bank transfer. '.vestra_order_delivery_segment($a).' Colours — X: Red. alıcının notu';
    $t('tam okunur: '.$a, vestra_order_delivery_address($n) === $a);
    $l = vestra_order_lines(['items' => '', 'notes' => $n]);
    $t('renk parçası yine ayrılıyor: '.$a, !str_contains($l['notes'], 'Colours'));
}
$t('sondaki nokta adrese girmez', vestra_order_delivery_address('x. '.vestra_order_delivery_segment('Rue X 5, Paris.')) === 'Rue X 5, Paris');
$t('ESKİ not aynen okunur', vestra_order_delivery_address('Payment: Bank transfer. Deliver to: Rue X 5, Paris. not') === 'Rue X 5, Paris');
$t('ESKİ not, sonda adres', vestra_order_delivery_address('Payment: x. Deliver to: 30 chemin, 33140 Villenave, France.') === '30 chemin, 33140 Villenave, France');
$t('boş adres = parça yok', vestra_order_delivery_segment("  . ") === '');
$src = (string)file_get_contents($root.'/order.php');
$ord = (string)file_get_contents($root.'/inc/orders.php');
$t('kasa parçayı TEK yazıcıdan yazıyor', str_contains($src, 'vestra_order_delivery_segment($shipAddr)') && !str_contains($src, "'Deliver to: '.\$shipAddr"));
$t('panel yazıcısı da aynı gövdeden', substr_count($ord, 'vestra_order_delivery_segment($address)') >= 1);
$wf = (string)file_get_contents(dirname(__DIR__).'/.github/workflows/send-campaign-preview.yml');
$t('teklif-faturası sipariş yazıcısı da aynı gövdeden (iş akışı)',
   str_contains($wf, 'vestra_order_delivery_segment($buyer[\'address\'])') && !str_contains($wf, "'Deliver to: '.\$buyer['address']"));

echo "== 6. Fatura: hesap yedeği posta kodunu basar ==\n";
$row = ['ref' => 'VES-T1', 'email' => 'b1@example.test', 'company' => 'Boutique B1', 'country' => 'Germany', 'notes' => 'Payment: Bank transfer.'];
$t('teslimat notu yoksa: sokak + PLZ + şehir', (vestra_invoice_buyer($row)['address'] ?? '') === 'Hauptstraße 1, 10115 Berlin');
$row['notes'] = 'Payment: Bank transfer. '.vestra_order_delivery_segment('Hauptstr. 12, 10115 Berlin');
$t('teslimat notu varsa: TAM adres (eskiden "Hauptstr")', (vestra_invoice_buyer($row)['address'] ?? '') === 'Hauptstr. 12, 10115 Berlin');

echo "== 7. Kablolama ==\n";
$buyer = (string)file_get_contents($root.'/buyer.php');
$cart  = (string)file_get_contents($root.'/cart.php');
$t('order.php sunucuda çözüyor', str_contains($src, "vestra_ship_addr_resolve(auth_user(), \$shipPick"));
$t('sepet yalnız yuva NUMARASI gönderiyor', str_contains($cart, 'name="ship_pick" value="<?= (int)$__s ?>"'));
$t('alıcı paneli 3 yuvayı sabitten çiziyor', str_contains($buyer, '$s <= VESTRA_SHIP_ADDR_MAX'));
$t('profil postcode/city yazıyor', str_contains($buyer, "'postcode'=>") && str_contains($buyer, "'city'=>"));
$t('kayıt postcode/city saklıyor', str_contains((string)file_get_contents($root.'/inc/auth.php'), "'postcode'      =>"));
foreach (['buyer.php', 'cart.php', 'order.php', 'admin.php'] as $f)
    $t($f.' addresses.php\'yi KENDİSİ yüklüyor (KURAL 15)', str_contains((string)file_get_contents($root.'/'.$f), "require_once __DIR__.'/inc/addresses.php'"));

/* 19 Eyl'den (04990a90) beri kasa navlun fonksiyonunu çağırıp dosyasını hiç yüklemiyordu:
   HER sipariş POST'u 500. Genel bekçi: order.php'nin çağırdığı her vestra_* fonksiyonu,
   order.php'nin yüklediği dosyalarla tanımlanmış olmalı. AYRI SÜREÇTE ölçülüyor -- bu
   testin kendi require'ları ölçümü yalanlardı (KURAL 15'in dersi). */
preg_match_all("~require(?:_once)?\s+__DIR__\s*\.\s*'(/inc/[a-z_]+\.php)'~", $src, $rq);
preg_match_all('~\b(vestra_[a-z0-9_]+)\s*\(~', preg_replace('~function_exists\([^)]*\)~', '', $src), $calls);
$calls = array_values(array_unique($calls[1]));
$probe = $sand.'/_fnprobe.php';
file_put_contents($probe, "<?php\ndefine('VESTRA_ACCOUNTS', ".var_export(VESTRA_ACCOUNTS, true).");\ndefine('VESTRA_DATA_DIR', ".var_export(VESTRA_DATA_DIR, true).");\n"
    .implode('', array_map(fn($f) => "require_once ".var_export($root.$f, true).";\n", array_unique($rq[1])))
    ."foreach (".var_export($calls, true)." as \$f) if (!function_exists(\$f)) echo \$f, \"\\n\";\n");
$missing = trim((string)shell_exec('php '.escapeshellarg($probe).' 2>&1'));
$t('order.php\'nin çağırdığı her vestra_* fonksiyonu yüklü ('.count($calls).' fonksiyon)', $missing === '');
if ($missing !== '') echo "       eksik: ".str_replace("\n", ', ', $missing)."\n";
$t('inc/orders.php navlun çağrısından ÖNCE yükleniyor',
   ($p1 = strpos($src, "require_once __DIR__.'/inc/orders.php'")) !== false
   && $p1 < strpos($src, 'vestra_shipping_auto_schedule($lines'));

echo "== 8. Sözlük (8 dil) ==\n";
$keys = ['Postcode', 'City', 'Street and number', 'Delivery addresses', 'Address %d', 'Same as billing address',
         'Another address', 'Name for this address', 'Save address', 'Manage delivery addresses'];
foreach (['de', 'fr', 'es', 'it', 'pt', 'ru', 'ar', 'ja'] as $lg) {
    $d = require $root.'/inc/lang/'.$lg.'.php';
    $miss = array_filter($keys, fn($k) => !isset($d[$k]) || $d[$k] === $k);
    $t($lg.': yeni anahtarlar çevrili', !$miss);
    $t($lg.': "Address %d" yer tutucusu korunmuş', str_contains((string)($d['Address %d'] ?? ''), '%d'));
}

exec('rm -rf '.escapeshellarg($sand));
echo "\n{$ok} ok, {$bad} hata\n";
exit($bad ? 1 : 0);
