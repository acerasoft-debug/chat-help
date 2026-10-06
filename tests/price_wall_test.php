<?php
/**
 * KURAL — FIYAT LISTESI GIRISSIZ ACILMAZ (operator, 8 Eyl 2026:
 * *"giris olmadan price list acilmasin anmeldung a zorla"*).
 *
 * NEDEN CALISTIRARAK OLCUYORUZ. Kaynakta "if (!$PRICES)" gormek olcum degil:
 * bu depoda alti kez kontrolun kendisi yanlis yere bakti. Sayfalar kum
 * havuzunda GERCEKTEN kosuluyor ve ciktiya bakiliyor -- kilitli ziyaretci
 * katalogdan tek satir goruyor mu?
 *
 * IKI YON DE TUTULUYOR: kilitliyken liste GORUNMEMELI, ve acikken liste
 * GORUNMELI. Tek yon yazilsaydi, sayfayi tumden 404'e cevirmek de testi
 * yesil birakirdi.
 */
$root = dirname(__DIR__).'/vestra';
$ok = 0; $bad = 0;
$t = function (string $name, bool $cond) use (&$ok, &$bad) {
    if ($cond) { $ok++; echo "  ok   $name\n"; }
    else       { $bad++; echo "  HATA $name\n"; }
};

/* ── kum havuzu: sitenin kopyasi + tek urunluk katalog ────────────────────── */
$sand = sys_get_temp_dir().'/vestra_pricewall_'.getmypid();
@mkdir($sand, 0777, true);
exec('cp -r '.escapeshellarg($root).' '.escapeshellarg($sand.'/public_html'));
file_put_contents($sand.'/public_html/data/listings.json', json_encode([[
    'id' => 'pw-test-tee', 'sku' => 'PWTEST-01', 'brand' => 'Zzztestbrand',
    'name' => 'Wall Probe Polo', 'cat' => 'Polos', 'mode' => 'sale',
    'list' => 49.0, 'price' => 30.0, 'moq' => 20, 'unit' => 'pc',
    'sizes' => 'S-XXL', 'tiers' => [['min' => 20, 'price' => 30.0]], 'ships_from' => 'EU',
]]));

/* Sayfayi CLI'da cizdir. $admin=true head.php'nin admin yolunu acar --
   onayli bir alici hesabi kurmadan "kapi ACIK" halini olcmenin en kisa yolu
   ve head.php ikisini ayni satirda hesapliyor ($IS_ADMIN || auth_prices...). */
$render = function (string $page, bool $admin) use ($sand): string {
    $boot = '$_SERVER["REQUEST_METHOD"]="GET";'
          . ($admin ? ' session_start(); $_SESSION["vadmin"]=1;' : '')
          . ' include '.var_export($page, true).';';
    $cmd = 'cd '.escapeshellarg($sand.'/public_html').' && php -r '.escapeshellarg($boot).' 2>&1';
    return (string)shell_exec($cmd);
};

echo "== 1. /price-list — KILITLI ziyaretci ==\n";
$g = $render('price-list.php', false);
$t('kayit duvari ciziliyor',            str_contains($g, 'pw-card'));
$t('kayit dugmesi var (/register)',     str_contains($g, 'href="/register"'));
$t('giris dugmesi geri yolu tasiyor',   str_contains($g, 'login?back=%2Fprice-list'));
/* ASIL IDDIA: katalogdan tek satir bile cikmamali. Fiyat zaten 8 Eylul'den
   once de gizliydi; sizan sey URUN ADI, ARTIKEL NO, MOQ ve bedenlerdi. */
$t('urun satiri YOK',                   !str_contains($g, 'pc-row'));
$t('urun adi YOK',                      !str_contains($g, 'Wall Probe Polo'));
$t('artikel no YOK',                    !str_contains($g, 'PWTEST-01'));
$t('fiyat YOK',                         !str_contains($g, '30,00') && !str_contains($g, '30.00'));
$t('PHP uyarisi yok',                   !str_contains($g, 'Warning:') && !str_contains($g, 'Fatal error'));

echo "\n== 2. /price-list — kapi ACIK ==\n";
$a = $render('price-list.php', true);
$t('urun satirlari ciziliyor',          str_contains($a, 'pc-row'));
$t('urun adi var',                      str_contains($a, 'Wall Probe Polo'));
$t('duvar YOK',                         !str_contains($a, 'pw-card'));
$t('PHP uyarisi yok',                   !str_contains($a, 'Warning:') && !str_contains($a, 'Fatal error'));

echo "\n== 3. /price-lists — marka listesi de kapali ==\n";
$g2 = $render('price-lists.php', false);
$a2 = $render('price-lists.php', true);
$t('kilitli: duvar var',                str_contains($g2, 'pw-card'));
/* Marka ADI her sayfanin altbilgisinde geciyor (SEO, KURAL 9) -- iddia bu
   yuzden TABLO isaretcisine bakiyor, marka adina degil. Ilk yazimda marka
   adina bakiyordu ve altbilgi yuzunden hep kirmiziydi. */
$t('kilitli: marka tablosu YOK',        !str_contains($g2, 'pl-brand'));
$t('kilitli: "en dusuk fiyat" YOK',     !str_contains($g2, 'pl-from'));
$t('acik: marka tablosu var',           str_contains($a2, 'pl-brand'));
$t('PHP uyarisi yok',                   !str_contains($g2, 'Warning:') && !str_contains($g2, 'Fatal error'));

echo "\n== 4. Kapi YENIDEN TANIMLANMIYOR ==\n";
foreach (['price-list.php', 'price-lists.php'] as $f) {
    $src = file_get_contents($root.'/'.$f);
    $t("$f head.php'nin \$PRICES'ini okuyor", str_contains($src, 'if (!$PRICES) {'));
    $t("$f duvardan sonra exit ediyor",       preg_match('/vestra_price_wall\(.*?exit;/s', $src) === 1);
    /* Kendi kapisini kurmasin: auth_prices_unlocked'i burada TEKRAR cagirmak
       ikinci bir kopya olurdu (bu depoda yedinci vaka). */
    $t("$f ikinci kapi kurmuyor",             !str_contains($src, 'auth_prices_unlocked('));
}

echo "\n== 5. Duvarin metni 8 DILDE (KURAL 10) ==\n";
$wall = file_get_contents($root.'/inc/pricewall.php');
/* Yorum satirlarindaki t('...') orneklerini elemek icin token taramasi:
   admin_fx_load_test'in dersi -- yoruma gore eleyen bir tarayici sessizce
   yanlis olcer. */
$keys = [];
$tk = token_get_all($wall);
for ($i = 0; $i < count($tk); $i++) {
    if (is_array($tk[$i]) && $tk[$i][0] === T_STRING && $tk[$i][1] === 't'
        && isset($tk[$i+1]) && $tk[$i+1] === '('
        && isset($tk[$i+2]) && is_array($tk[$i+2]) && $tk[$i+2][0] === T_CONSTANT_ENCAPSED_STRING) {
        $keys[] = stripcslashes(trim($tk[$i+2][1], "'"));
    }
}
$t('duvarda metin var', count($keys) >= 6);
foreach (['de','fr','it','es','pt','ru','ar'] as $lang) {
    $dict = file_get_contents($root.'/inc/lang/'.$lang.'.php');
    $miss = [];
    foreach ($keys as $k) {
        if (!str_contains($dict, "'".str_replace("'", "\\'", $k)."' =>")) $miss[] = mb_substr($k, 0, 34);
    }
    $t("$lang sozlugunde eksik yok".($miss ? ' ('.implode(' | ', $miss).')' : ''), $miss === []);
}

exec('rm -rf '.escapeshellarg($sand));
echo "\n{$ok} ok, {$bad} hata\n";
exit($bad ? 1 : 0);
