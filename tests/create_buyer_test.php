<?php
/* Panelden ELLE alici hesabi acma (Admin ▸ Users ▸ ➕ New buyer account).
 *
 * NEDEN KUM HAVUZUNDA GERCEKTEN KOSTURULUYOR: kaynak taramasi bu isi
 * olcemezdi. Bu oturumda iki kez var olmayan bir fonksiyon adi yazildi
 * (`vestra_order_status()`, `csrf_field()`) ve IKISINI DE `php -l` gecirdi --
 * ikisi de calisma zamani hatasi. Ayni sinif: `auth_required_doc_types()`
 * cagrilmazsa satir acilmaz ve KURAL 2'nin "istek satiri yoksa belge
 * verilemez" kusuru sessizce geri gelir; bunu ancak YAZILAN KAYDI OKUYARAK
 * gorursunuz.
 *
 * Depo yolu SABIT ve `defined()` korumali (VESTRA_ACCOUNTS) -- bu test gercek
 * data/accounts.json'a DOKUNMUYOR. Korumasizken bir test bir kez uretim
 * dosyasina yazmisti. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra_cb_'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
file_put_contents($sand.'/data/accounts.json', json_encode([]));

/* admin.php'yi AYRI BIR SURECTE POST ile kosturuyoruz: handler header()+exit
 * yapiyor, yani ayni surecte cagirmak testin kendisini bitirirdi. */
$runner = $sand.'/_post.php';
file_put_contents($runner, <<<'PHP'
<?php
define('VESTRA_ACCOUNTS', getenv('CB_STORE'));
$_SESSION = [];
session_start();
$_SESSION['vadmin'] = true; $_SESSION['vadmin_csrf'] = 'tok';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin';
$_POST = json_decode(getenv('CB_POST'), true) ?: [];
$_POST['_csrf'] = 'tok';
ob_start(); include getenv('CB_ADMIN'); ob_end_clean();
PHP);

$post = function (array $fields) use ($runner, $sand, $root): string {
    /* Location basligi cikti tamponuna girmiyor; -d ile yakalayamayiz, o yuzden
       CLI'da headers_list() yerine dogrudan kaydin kendisine bakiyoruz ve
       yonlendirme mesajini `msg=` olarak ayri sorguluyoruz. CLI SAPI header()
       cagrisini yutar -- bu yuzden SONUC her zaman KAYITTAN okunuyor, ki zaten
       dogru olcum de o (panelin ne dedigi degil, diske ne indigi). */
    $env = ['CB_STORE' => $sand.'/data/accounts.json', 'CB_ADMIN' => $root.'/admin.php',
            'CB_POST'  => json_encode($fields + ['_action' => 'create_buyer'])];
    $pfx = ''; foreach ($env as $k => $v) $pfx .= $k.'='.escapeshellarg($v).' ';
    return (string)shell_exec($pfx.'php '.escapeshellarg($runner).' 2>&1');
};
$load = fn(): array => json_decode((string)file_get_contents($sand.'/data/accounts.json'), true) ?: [];

echo "== 1. hesap aciliyor ==\n";
$post(['email' => 'Buyer.One@Example.COM', 'name' => 'Jane Doe', 'company' => 'Doe Boutique SL',
       'vat_id' => 'ESB12345678', 'country' => 'Spain', 'address' => 'Calle 1', 'lang' => 'es',
       'open_gate' => '1']);
$all = $load();
$t('hesap KAYDA indi', count($all) === 1);
$a = $all[0] ?? [];
$t('e-posta kucuk harfe cevrildi', ($a['email'] ?? '') === 'buyer.one@example.com');
$t('tip buyer', ($a['type'] ?? '') === 'buyer');
$t('kunye yazildi (company/vat/adres)',
   ($a['company'] ?? '') === 'Doe Boutique SL' && ($a['vat_id'] ?? '') === 'ESB12345678'
   && ($a['address'] ?? '') === 'Calle 1');
$t('dil kaydedildi', ($a['lang'] ?? '') === 'es');

echo "\n== KURAL 2: belge istegi SATIRI acildi mi ==\n";
/* Bu, testin var olma sebebi. create_seller / sync_lesgarage /
   create_tyrex_migrate ucu de 'doc_requests'=>[] yazdi ve sonucu KURAL 2'de
   kayitli: satir yoksa yukleme dugmesi de yok. */
require_once $root.'/inc/auth.php';
$need = auth_required_doc_types('buyer');
$have = array_map(fn($r) => (string)($r['type'] ?? ''), (array)($a['doc_requests'] ?? []));
$t('doc_requests BOS DEGIL', $have !== []);
$t('zorunlu tiplerin hepsi var ('.implode(',', $need).')', array_diff($need, $have) === []);
$t('satir auth_doc_request_row bicimi (id + status)',
   isset($a['doc_requests'][0]['id'], $a['doc_requests'][0]['status']));
$t('trade_doc_required bayragi', ($a['trade_doc_required'] ?? null) === true);

echo "\n== kapi ==\n";
$t('kapi ACIK istendi -> kyb_status=approved', ($a['kyb_status'] ?? '') === 'approved');
$t('kapiyi NE actigi kayitta (kyb_auto)', ($a['kyb_auto'] ?? '') === 'operator:panel');
$t('status active', ($a['status'] ?? '') === 'active');

echo "\n== sifre ==\n";
$t('hash yazildi', strlen((string)($a['hash'] ?? '')) > 20);
$t('duz sifre HICBIR ALANDA yok', !in_array('password', array_keys($a), true));

echo "\n== 2. ayni e-posta IKINCI kez ==\n";
$post(['email' => 'buyer.one@example.com', 'name' => 'Someone Else', 'country' => 'Spain']);
$t('ikinci hesap ACILMADI', count($load()) === 1);
$t('mevcut kayit EZILMEDI', ($load()[0]['name'] ?? '') === 'Jane Doe');

echo "\n== 3. KURAL 2g: Turkiye ==\n";
$post(['email' => 'tr.buyer@example.com', 'name' => 'Ali', 'country' => 'Turkey']);
$t('Turkiye REDDEDILDI', count($load()) === 1);
$post(['email' => 'tr2.buyer@example.com', 'name' => 'Ali', 'country' => 'TR']);
$t('ciplak ISO kodu TR de reddedildi', count($load()) === 1);
/* TERS YON: komsu ulke adi elenmemeli (mango/zara dersinin cografya hali). */
$post(['email' => 'tm.buyer@example.com', 'name' => 'Aman', 'country' => 'Turkmenistan']);
$t('Turkmenistan GECTI -- alt dize eslemesi degil', count($load()) === 2);

echo "\n== 4. gecersiz e-posta ==\n";
$post(['email' => 'not-an-email', 'country' => 'Spain']);
$t('gecersiz adres REDDEDILDI', count($load()) === 2);
$post(['email' => '', 'country' => 'Spain']);
$t('bos adres REDDEDILDI', count($load()) === 2);

echo "\n== 5. kapi KAPALI istenirse ==\n";
$post(['email' => 'closed@example.com', 'name' => 'Closed', 'country' => 'France']);   // open_gate yok
$cl = null; foreach ($load() as $x) if (($x['email'] ?? '') === 'closed@example.com') $cl = $x;
$t('kapi kutucugu isaretsizken kyb_status=pending', ($cl['kyb_status'] ?? '') === 'pending');
$t('isaretsizken kyb_auto BOS', ($cl['kyb_auto'] ?? 'x') === '');
$t('belge satiri yine de acildi', !empty($cl['doc_requests']));
/* KAPI GERCEKTEN KAPALI MI -- kyb_status'e bakmak YETMIYOR.
   auth_user_approved() bir VEYA: status==='active' de kapiyi acar. Kosulsuz
   'active' yazan ilk surum, kutucuk isaretsizken bile fiyati aciyordu ve
   `kyb_status=pending` iddiasi bunu YESIL GECIYORDU. Olcut artik kapinin
   kendisi, alanlardan biri degil. */
$t('isaretsizken status active DEGIL', ($cl['status'] ?? '') !== 'active');
$t('isaretsizken KAPI KAPALI (auth_prices_unlocked)', auth_prices_unlocked($cl) === false);
$t('kutucuk ISARETLIYKEN kapi ACIK', auth_prices_unlocked($a) === true);

echo "\n== 6. form KABLOLAMASI ==\n";
$src = (string)file_get_contents($root.'/admin.php');
/* csrfField() -- bu oturumda `csrf_field()` diye yazildi ve `php -l` gecti;
   yakalayan sey formu CIZDIRMEK oldu. Iddia adi sabitliyor. */
$t('form csrfField() cagiriyor (csrf_field DEGIL)',
   str_contains($src, 'csrfField() ?>'.PHP_EOL.'    <input type="hidden" name="_action" value="create_buyer"'));

/* Kayit kurma auth_create_buyer()'a tasindi (is akisindan da aciliyor). Iddia
   YAZIMI degil OLGUyu tutuyor: kurucu tek yerde ve panel ona bagli. */
$hnd = '';
if (preg_match("/if\(\\\$act==='create_buyer'\)\{(.*?)\n  \}\n/s", $src, $m6)) $hnd = $m6[1];
$t('handler govdesi bulundu', $hnd !== '');
$t('panel auth_create_buyer() cagiriyor', str_contains($hnd, 'auth_create_buyer('));
/* IKINCI KURUCU YOK. Bu, refactor'un actigi asil risk: panelin kendi hesap
   dizisini kurmasi geri gelirse iki yol sessizce ayrisir ve ayrilik ancak bir
   hesapta eksik alan olarak gorunur. */
$t('panel hesabi KENDISI kurmuyor (ikinci yazici yok)',
   !str_contains($hnd, 'auth_save_accounts') && !str_contains($hnd, "'type'=>'buyer'")
   && !str_contains($hnd, 'password_hash'));

$asrc = (string)file_get_contents($root.'/inc/auth.php');
$body = '';
if (preg_match('/\nfunction auth_create_buyer\(.*?\n\}\n/s', $asrc, $m7)) $body = $m7[0];
$t('auth_create_buyer govdesi bulundu', $body !== '');
$t('kurucu auth_required_doc_types + auth_doc_request_row kullaniyor',
   str_contains($body, 'auth_required_doc_types(') && str_contains($body, 'auth_doc_request_row('));
$t('kurucu KURAL 2g kontrolunu cagiriyor', str_contains($body, 'vestra_country_declares_turkey('));
/* inc/security.php ACIKCA require ediliyor: kardes bir dosyanin require'ina
   yaslanmak KURAL 15'in fatal'inin kucuk hali (function_exists ile gecistirmek
   daha kotu olurdu -- dosya yuklenmemisse kontrol SESSIZCE atlanir). */
$t('kurucu inc/security.php require ediyor', str_contains($body, "require_once __DIR__.'/security.php'"));
$t('kurucu geri okuyup save_failed donuyor', str_contains($body, "return 'save_failed'"));
$t('panel save_failed -> nb_failed', str_contains($hnd, 'nb_failed'));

@array_map('unlink', glob($sand.'/data/*') ?: []);
@array_map('unlink', glob($sand.'/*.php') ?: []);
@rmdir($sand.'/data'); @rmdir($sand);

printf("\ncreate_buyer_test: %d iddia gecti, %d KIRMIZI\n", $ok, $bad);
exit($bad ? 1 : 0);
