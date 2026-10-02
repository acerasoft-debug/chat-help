<?php
/* On siparis sevk tarihi YAZMA yolu: scripts/set_product.php -> preorder_ship
 * (2 Eki 2026, operator: "Gallery Dept. urunlerine stock giris tarihi ekim
 * sonu yap fakat siparisleri kabul ediyoruz").
 *
 * tests/preorder_note_test.php OKUYAN tarafi tutuyor (tarihten cumle). Bu test
 * YAZAN tarafi tutuyor ve betigi gercekten kosturuyor -- kaynak taramasi
 * burada hicbir sey olcmez, cunku asil sorular calisma zamaninda cikiyor:
 *
 *  - Genel dal "preorder_ship '(yok)' -> '2026-10-31'" yaziyordu ve SAYFANIN NE
 *    BASACAGINI soylemiyordu. Not tarihten uretiliyor ve tarih gecince
 *    kendiliginden susuyor; yani gecmis bir tarih yazmak sessizce "notu
 *    kaldirmak" demek. Yazmadan once gosterilmeli.
 *  - Eski dogrulama yalniz BICIME bakiyordu: '2026-13-45' regex'ten geciyor,
 *    strtotime() false donuyor ve not SESSIZCE susuyordu; '2026-02-31' ise 3
 *    Mart'a kayip sayfaya yanlis ay yazdiriyordu.
 *  - Siparisi ENGELLEYEN bu alan degil, sold_out. Operatorun "siparisleri kabul
 *    ediyoruz" cumlesi bu ayrimi tasiyor: SATILDI isaretli bir ilana on siparis
 *    tarihi yazmak "kabul ediyoruz" demez, bu yuzden UYARI cikmali.
 *
 * Tarihler BUGUNDEN turetilir: sabit bir "2026-10-31" test o gun gecince
 * sessizce baska bir seyi olcmeye baslardi.
 */
require_once __DIR__.'/../vestra/inc/products.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

$root     = dirname(__DIR__);
$listings = $root.'/vestra/data/listings.json';
if (file_exists($listings)) {
    fwrite(STDERR, "ATLANAMAZ: {$listings} zaten var. Bu test kendi katalogunu yaziyor;\n"
                 . "yerel dosyanizi ezmemek icin duruyor. Once tasiyin.\n");
    exit(1);
}
@mkdir(dirname($listings), 0777, true);
$home = sys_get_temp_dir().'/vestra_preorder_write_test_'.getmypid();
@mkdir($home, 0777, true);
@symlink($root.'/vestra', $home.'/public_html');

/* Ayin 28'i: "late" dilimi (21-31) ve en az 42 gun sonrasi -- ay uzunlugundan
   bagimsiz, her calistirma gununde gelecekte. */
$fut       = date('Y-m-28', strtotime('+45 days'));
$futPhrase = 'late '.date('F Y', strtotime($fut));
$futNote   = 'Pre-orders are being accepted · dispatch '.$futPhrase.'.';
$past      = date('Y-m-d', strtotime('-10 days'));

$seed = function () use ($listings) {
    $base = ['brand'=>'Gallery Dept.','cat'=>'T-Shirts','mode'=>'fixed','moq'=>20,'list'=>59.90,
             'tiers'=>[['min'=>20,'price'=>59.90]],'status'=>'approved'];
    file_put_contents($listings, json_encode([
        $base + ['id'=>'gd-a','name'=>'Alpha Tee'],
        array_merge($base, ['id'=>'gd-b','name'=>'Beta Tee','sold_out'=>true]),
        array_merge($base, ['id'=>'gd-c','name'=>'Gamma Tee','status'=>'pending']),
        // marka kapsami denetimi icin: ayni marka, KUCUK HARFLI yazim, REDDEDILMIS
        // (vestra_products() bunu hic gostermez) -- dosyada olmayan "kardes"
        array_merge($base, ['id'=>'gd-d','name'=>'Delta Tee','brand'=>'gallery dept.','status'=>'rejected']),
        // kontrol grubu: baska marka, kendi tarihi var -- hicbiri degismemeli
        ['id'=>'lac-x','brand'=>'Lacoste','cat'=>'Polos','mode'=>'fixed','name'=>'Control Polo','moq'=>10,
         'list'=>70.20,'tiers'=>[['min'=>10,'price'=>70.20]],'status'=>'approved','preorder_ship'=>'2031-03-03'],
    ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
};
$run = function (array $fixes, bool $apply) use ($home, $root): array {
    $cmd = 'HOME='.escapeshellarg($home)
         . ' P_DRY='.escapeshellarg($apply ? 'false' : 'true')
         . ' P_JSON='.escapeshellarg(base64_encode(json_encode($fixes)))
         . ' php '.escapeshellarg($root.'/scripts/set_product.php').' 2>&1';
    $out = []; $rc = 0; exec($cmd, $out, $rc);
    return [$rc, implode("\n", $out)];
};
$byId = function () use ($listings): array {
    $m = [];
    foreach (json_decode((string)file_get_contents($listings), true) ?: [] as $p) $m[$p['id']] = $p;
    return $m;
};
$fix = fn(string $id, string $date) => ['match'=>$id, 'expect'=>1, 'preorder_ship'=>$date];
$three = fn(string $d) => [$fix('gd-a', $d), $fix('gd-b', $d), $fix('gd-c', $d)];
/* Bir ilanin PLAN satirlarini cikarir (ilan satirindan sonrakini ilan satirina
   kadar): uyarilar hangi ilana ait diye ayirmak icin. */
$block = function (string $out, string $id): string {
    $lines = explode("\n", $out); $o = []; $in = false;
    foreach ($lines as $ln) {
        if (preg_match('/^  (\S+)\s+\| /', $ln, $mm)) { $in = ($mm[1] === $id); continue; }
        if (str_starts_with($ln, 'degisecek alan:')) break;
        if ($in) $o[] = $ln;
    }
    return implode("\n", $o);
};

echo "\n== 1. KURU KOSU: sayfanin basacagi cumleyi gosterir, hicbir sey yazmaz ==\n";
$seed();
$before = (string)file_get_contents($listings);
[$rc, $out] = $run($three($fut), false);
$t('kuru kosu basarili (cikis 0)',                     $rc === 0);
$t('dosyaya dokunulmadi',                              (string)file_get_contents($listings) === $before);
$t('degisiklik satiri eski -> yeni',                   str_contains($out, "preorder_ship '(yok)' -> '{$fut}'"));
$t('SAYFANIN cumlesi basiliyor (gd-a)',                str_contains($block($out, 'gd-a'), 'sayfada: "'.$futNote.'"'));
$t('siparis kabulu acikca yaziyor',                    str_contains($block($out, 'gd-a'), 'siparis KABUL EDILMEYE devam eder'));
$t('normal ilanda UYARI YOK',                          !str_contains($block($out, 'gd-a'), 'UYARI'));
$t('SATILDI ilanda uyari (siparis alinamaz)',          str_contains($block($out, 'gd-b'), 'UYARI: ilan SATILDI') && str_contains($block($out, 'gd-b'), 'ALINAMAZ'));
$t('onay bekleyen ilanda uyari (katalogda gorunmuyor)', str_contains($block($out, 'gd-c'), "UYARI: ilan durumu 'pending'") && str_contains($block($out, 'gd-c'), 'GORUNMUYOR'));
$t('uyari yalniz ilgili ilanda (gd-a/gd-c SATILDI demiyor)', !str_contains($block($out, 'gd-a'), 'SATILDI') && !str_contains($block($out, 'gd-c'), 'SATILDI'));
$t('degisecek alan: 3',                                str_contains($out, 'degisecek alan: 3'));

echo "\n== 2. UYGULAMA: dosyaya inen deger, ve OKUYAN tarafin gordugu ==\n";
$seed();
$ctlBefore = json_encode(($byId())['lac-x'] ?? null);
[$rc2, $out2] = $run($three($fut), true);
$m = $byId();
$t('uygulama basarili',                                $rc2 === 0 && str_contains($out2, 'KAYDEDILDI'));
$t('uc ilanin uc de tarihi tasiyor',                   ($m['gd-a']['preorder_ship'] ?? '') === $fut && ($m['gd-b']['preorder_ship'] ?? '') === $fut && ($m['gd-c']['preorder_ship'] ?? '') === $fut);
$t('deger DIZGE (bos/bool/sayi degil)',                is_string($m['gd-a']['preorder_ship'] ?? null));
/* ASIL IDDIA: yazilan kaydi sayfanin kendi fonksiyonu okuyor -- "dosyada var"
   ile "sayfa basiyor" ayri seyler (okunmayan alan bu depoda bir kez yasandi). */
$t('SAYFA fonksiyonu kaydi okuyup cumleyi basiyor',    vestra_preorder_note($m['gd-a'] ?? []) === $futNote);
$t('KONTROL GRUBU: baska markanin ilani AYNEN',        json_encode($m['lac-x'] ?? null) === $ctlBefore);
$t('yedek alindi',                                     count(glob($listings.'.bak-*')) >= 1);
$t('SATILDI bayragi bu yazmada degismedi (gd-b)',      !empty($m['gd-b']['sold_out']));
$t('dosyada OLMAYAN kardes (gd-d) dokunulmadi',        !isset($m['gd-d']['preorder_ship']));
$t('uygulama kipinde de marka kapsami basiliyor',      str_contains($out2, 'MARKA KAPSAMI: Gallery Dept.'));

echo "\n== 3. IDEMPOTENT: ayni dosya ikinci kez 0 degisiklik ==\n";
[$rc3, $out3] = $run($three($fut), false);
$t('ikinci kosu: degisecek alan: 0',                   str_contains($out3, 'degisecek alan: 0'));
$t('ikinci kosu: "zaten istenen durumda" x3',          substr_count($out3, 'zaten istenen durumda') === 3);
$t('ikinci kosuda cumle/uyari tekrarlanmiyor',         !str_contains($out3, 'sayfada:') && !str_contains($out3, 'UYARI'));

echo "\n== 4. GECMIS TARIH: yazilir ama SESSIZ degil (notu kaldirmanin yolu bu) ==\n";
$seed();
[$rc4, $out4] = $run([$fix('gd-a', $past)], false);
$t('gecmis tarih kuru kosuda REDDEDILMIYOR',           $rc4 === 0);
$t('"tarih gecmis -- HIC on siparis notu basmaz" uyarisi', str_contains($out4, 'UYARI: tarih gecmis') && str_contains($out4, 'HIC on siparis notu'));
$t('gecmis tarihte "sayfada:" cumlesi YOK',            !str_contains($out4, 'sayfada:'));
$seed();
$run([$fix('gd-a', $past)], true);
$t('uygulaninca sayfa notu GERCEKTEN susuyor',         vestra_preorder_note(($byId())['gd-a'] ?? []) === '');

echo "\n== 5. GECERSIZ TARIH: HICBIR SEY YAZILMAZ ==\n";
foreach ([
    '2026-13-45' => 'ay 13 (strtotime false doner, not sessizce susardi)',
    '2026-02-31' => 'var olmayan gun (3 Mart\'a kayardi)',
    '2027-02-29' => 'artik yil degil',
    '31/10/2026' => 'yanlis bicim',
    '2026-10-3'  => 'eksik sifir',
    'yakinda'    => 'tarih degil',
] as $bad => $why) {
    $seed();
    $b4 = (string)file_get_contents($listings);
    [$rcb, $outb] = $run([$fix('gd-a', $bad)], true);
    $t("'{$bad}' ({$why}) REDDEDILDI",                 $rcb !== 0 && str_contains($outb, 'HICBIR SEY KAYDEDILMEDI'));
    $t("'{$bad}' ile dosya DEGISMEDI",                 (string)file_get_contents($listings) === $b4);
}
/* Ters yon: gecerli bir artik gunu REDDEDILMEMELI -- checkdate gevsek yazilirsa
   da sikilastirilirsa da burada yakalanir. */
$seed();
[$rcl] = $run([$fix('gd-a', '2028-02-29')], false);
$t('2028-02-29 (gecerli artik gunu) KABUL',            $rcl === 0);

echo "\n== 6. expect uyusmazligi hicbir sey yazmaz (iki ilana birden uyan ad) ==\n";
$seed();
[$rce] = $run([['match'=>'Tee', 'expect'=>1, 'preorder_ship'=>$fut]], true);
$t('"Tee" uc ilana uyar -> is DURUR',                  $rce !== 0);
/* `??` ile `===` ayni ifadede PHP'de beklenmedik bagliyor (?? daha dusuk
   oncelikli): ilk yazimda bu satir alan YAZILMISSA bile dolu dizgeyi dondurup
   gecerdi -- hic dusemeyen bir iddia. Tek ve acik: alan hic yok. */
$t('hicbir ilan yazilmadi (alan hic yok)',             !isset(($byId())['gd-a']['preorder_ship']) && !isset(($byId())['gd-b']['preorder_ship']));

echo "\n== 7. MARKA KAPSAMI: operator markayi soyler, dosya id listesidir ==\n";
/* 2 Eki 2026, Gallery Dept. / Casablanca: "Casablanca urunleri" denince dosya bir
   id LISTESI. Listede olmayan bir kardes (sonradan eklenen, onay bekleyen,
   reddedilmis) SESSIZCE eski durumunda kalir ve operator "tum marka" yazdigini
   sanir. Arac artik markanin ham listedeki TUM ilanlarini sayip dokunulmayanlari
   adiyla yaziyor. Yalniz okur: hicbir sey yazmaz. */
$seed();
[, $o7a] = $run($three($fut), false);
$t('kismi kapsam: sayi + dokunulmayan kardes adiyla yaziliyor',
   str_contains($o7a, 'MARKA KAPSAMI: Gallery Dept. kayitta 4 ilan, bu dosya 3 tanesine dokunuyor; DOKUNULMAYAN 1: gd-d[rejected]'));
$t('KUCUK HARFLI marka yazimi AYNI marka sayiliyor (4, 3 degil)', str_contains($o7a, 'kayitta 4 ilan'));
$t('REDDEDILMIS kardes de sayiliyor (ham liste, her durum)',      str_contains($o7a, 'gd-d[rejected]'));
$t('dokunulmayan marka (Lacoste) hic anilmiyor',                  !str_contains($o7a, 'MARKA KAPSAMI: Lacoste'));
$seed();
$b7 = (string)file_get_contents($listings);
[, $o7b] = $run(array_merge($three($fut), [$fix('gd-d', $fut)]), false);
$t('tam kapsam: "markanin TUM 4 ilani bu dosyada"',               str_contains($o7b, 'MARKA KAPSAMI: Gallery Dept. -- markanin TUM 4 ilani bu dosyada'));
$t('tam kapsamda "DOKUNULMAYAN" yok',                             !str_contains($o7b, 'DOKUNULMAYAN'));
$t('kuru kosu dosyaya dokunmadi (kapsam denetimi dahil)',         (string)file_get_contents($listings) === $b7);
[, $o7c] = $run(array_merge($three($fut), [$fix('gd-d', $fut), $fix('lac-x', $fut)]), false);
$t('iki marka dokunulunca IKI satir (Gallery + Lacoste)',         str_contains($o7c, 'MARKA KAPSAMI: Gallery Dept.') && str_contains($o7c, 'MARKA KAPSAMI: Lacoste -- markanin TUM 1 ilani bu dosyada'));
$t('kapsam satiri ilan baslarina karismiyor (gd-a blogu temiz)',  !str_contains($block($o7a, 'gd-a'), 'MARKA KAPSAMI'));

foreach (array_merge([$listings], glob($listings.'.bak-*') ?: []) as $f) @unlink($f);
@unlink($home.'/public_html');
@rmdir($home);

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
