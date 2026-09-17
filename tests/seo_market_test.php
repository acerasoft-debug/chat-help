<?php
/**
 * PAZAR (ulke/bolge) INIS SAYFASI — /wholesale-to/<pazar>
 * (operator, 17 Eyl 2026: "sadece avrupa degil avustralya japonya dubai qatar ve usa
 * da olsun" + "brezilya ve guney amerika, israil, singapur, g.koreyi de ekle").
 *
 * NEDEN CIZDIREREK OLCUYORUZ. Kaynakta `sprintf(t('...'), $name)` gormek olcum degil:
 * bu depoda alti kez kontrolun kendisi yanlis yere bakti, ve bu sayfanin TEK isi
 * dogru rakami basmak. Sayfa kum havuzunda GERCEKTEN kosuluyor ve HTML okunuyor.
 *
 * IKI YON DE TUTULUYOR:
 *   - olgusu OLAN pazar rakami BASMALI (Avustralya: AUD, %10, US$5.000),
 *   - olgusu OLMAYAN pazar o satiri HIC BASMAMALI (ABD'de indirim yok).
 * Tek yon yazilsaydi "her pazara her satiri basan" bir kusur da yesil gorunurdu.
 */
$root = dirname(__DIR__).'/vestra';
$ok = 0; $bad = 0;
$t = function (string $name, bool $cond) use (&$ok, &$bad) {
    if ($cond) { $ok++; echo "  ok   $name\n"; }
    else       { $bad++; echo "  HATA $name\n"; }
};

/* ── kum havuzu: sitenin kopyasi + iki urunluk katalog ────────────────────── */
$sand = sys_get_temp_dir().'/vestra_market_'.getmypid();
@mkdir($sand, 0777, true);
exec('cp -r '.escapeshellarg($root).' '.escapeshellarg($sand.'/public_html'));
register_shutdown_function(fn() => exec('rm -rf '.escapeshellarg($sand)));
file_put_contents($sand.'/public_html/data/listings.json', json_encode([
  ['id' => 'mk-tee', 'sku' => 'MK-01', 'brand' => 'Zzztestbrand', 'name' => 'Market Probe Tee',
   'cat' => 'T-Shirts', 'mode' => 'fixed', 'list' => 40.0, 'price' => 40.0, 'moq' => 10,
   'unit' => 'pc', 'sizes' => 'S-XXL', 'tiers' => [['min' => 10, 'price' => 40.0]], 'ships_from' => 'EU'],
  ['id' => 'mk-sneaker', 'sku' => 'MK-02', 'brand' => 'Yyyshoeworks', 'name' => 'Market Probe Sneaker',
   'cat' => 'Sneakers', 'section' => 'footwear', 'mode' => 'fixed', 'list' => 55.0, 'price' => 55.0,
   'moq' => 12, 'unit' => 'pr', 'sizes' => '40-45', 'tiers' => [['min' => 12, 'price' => 55.0]], 'ships_from' => 'EU'],
]));

/** Sayfayi yerel yonlendiriciyle cizdir (canlidaki .htaccess'in aynasi). */
$render = function (string $path) use ($sand): string {
    $boot = '$_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["REQUEST_URI"]='.var_export($path, true).';'
          . ' $_SERVER["HTTP_HOST"]="vestrasales.com"; $_SERVER["HTTPS"]="on";'
          . ' $u=parse_url($_SERVER["REQUEST_URI"]); parse_str($u["query"] ?? "", $_GET);'
          . ' $p=$u["path"];'
          . ' if (preg_match("#^/wholesale-to/([A-Za-z0-9-]+)$#", $p, $m)) { $_GET["market"]=$m[1]; include "market.php"; }'
          . ' else { http_response_code(404); include "404.php"; }';
    return (string)shell_exec('cd '.escapeshellarg($sand.'/public_html').' && php -r '.escapeshellarg($boot).' 2>&1');
};
$facts = function (string $html): array {
    preg_match_all('~<div class="wsfact"><b>([^<]*)</b><span>([^<]*)</span>~', $html, $m, PREG_SET_ORDER);
    $o = []; foreach ($m as $x) $o[$x[2]] = $x[1];
    return $o;
};

echo "== 1. Avustralya: her olgu KODDAN basiliyor ==\n";
$au = $render('/wholesale-to/australia');
$fa = $facts($au);
$t('sayfa ciziliyor (wshero)',        str_contains($au, 'wshero'));
$t('PHP uyarisi yok',                 !str_contains($au, 'Warning:') && !str_contains($au, 'Fatal error'));
$t('baslik ulkeyi tasiyor',           str_contains($au, '<title>') && str_contains($au, 'Australia'));
$t('para birimi AUD (money.php)',     ($fa['prices shown in'] ?? '') === 'AUD');
$t('indirim -10% (region_discount)',  ($fa['standing trade discount'] ?? '') === '-10%');
$t('asgari US$5,000 (sabit)',         ($fa['minimum order value'] ?? '') === 'US$5,000');
$t('kapi kayitta acilir cumlesi',     str_contains($au, 'opened at sign-up'));
$t('canonical /wholesale-to/australia', str_contains($au, 'https://vestrasales.com/wholesale-to/australia"'));
$t('CollectionPage semasi',           str_contains($au, '"@type":"CollectionPage"'));
$t('about = Country',                 str_contains($au, '"@type":"Country","name":"Australia"'));
$t('BreadcrumbList var',              str_contains($au, '"@type":"BreadcrumbList"'));
$t('katalogun MARKALARI sayfada',     str_contains($au, 'Zzztestbrand') && str_contains($au, 'Yyyshoeworks'));
$t('marka sayfalarina baglaniyor',    str_contains($au, '/wholesale/zzztestbrand'));
$t('kategori sayfalarina baglaniyor', str_contains($au, '/b2b/t-shirts') && str_contains($au, '/b2b/sneakers'));
$t('diger pazarlara baglaniyor',      str_contains($au, '/wholesale-to/japan') && str_contains($au, '/wholesale-to/qatar'));
/* "Kendine baglanmiyor" iddiasi ILK yazimda YANLIS OLCTU: butun HTML'i sayiyordu ve
   altbilgi zaten HER sayfada butun pazarlari listeliyor (marka ve kategori
   baglantilari gibi), yani Avustralya sayfasinda bir kez gecmesi DOGRU. Olcut
   artik fark: govdedeki "diger pazarlar" bloku kendini atliyorsa bu sayfa 1 kez,
   digerleri 2 kez gecer (altbilgi + govde). Blok atlamayi birakirsa 2 olur. */
$t('govde kendine baglanmiyor (altbilgi 1, govde 0)',
   substr_count($au, 'href="/wholesale-to/australia"') === 1
   && substr_count($au, 'href="/wholesale-to/japan"') === 2);

echo "\n== 2. ABD: olgusu OLMAYAN satir BASILMIYOR ==\n";
$us = $render('/wholesale-to/united-states');
$fu = $facts($us);
$t('para birimi USD',                 ($fu['prices shown in'] ?? '') === 'USD');
$t('indirim satiri YOK',              !isset($fu['standing trade discount']));
$t('indirim cumlesi YOK',             !str_contains($us, 'standing'));
$t('"kayitta acilir" cumlesi YOK',    !str_contains($us, 'opened at sign-up'));
$t('asgari siparis satiri VAR',       ($fu['minimum order value'] ?? '') === 'US$5,000');

echo "\n== 3. Bolge sayfasi (Guney Amerika) ==\n";
$sa = $render('/wholesale-to/south-america');
$t('about = Place (ulke degil)',      str_contains($sa, '"@type":"Place","name":"South America"'));
$t('uc dil yaziyor',                  str_contains($sa, 'English, Español, Português'));
$t('indirim -10% (12 ulkede de ayni)', ($facts($sa)['standing trade discount'] ?? '') === '-10%');

echo "\n== 4. Dil: baslik ve govde CEVRILIYOR ==\n";
/* ULKE ADININ KENDISI de cevrilmeli, yalnizca kalip degil. Olcum "Japan" ile
   YAPILAMAZ: Japonya'nin Almancasi da "Japan" -- ilk yazimda oyleydi ve ceviriyi
   kaldiran sabotaj testi YESIL biraktı. Avustralya/Almanca ("Australien") ayirt
   ediyor; ayni tuzagin ikinci ornegi Fransizca "Australie". */
$de = $render('/wholesale-to/australia?lang=de');
$t('Almanca baslik kalibi',           str_contains($de, 'Mode-Großhandel für'));
$t('ulke adi Almanca (Australien)',   str_contains($de, 'Australien') && !str_contains($de, 'für Australia'));
$t('PHP uyarisi yok (de)',            !str_contains($de, 'Warning:') && !str_contains($de, 'Fatal error'));
$fr = $render('/wholesale-to/australia?lang=fr');
$t('ulke adi Fransizca (Australie)',  str_contains($fr, 'Australie'));
$jp = $render('/wholesale-to/japan?lang=de');
$t('Almanca Japonya sayfasi acilir',  str_contains($jp, 'Mode-Großhandel für Japan'));
$ja = $render('/wholesale-to/japan?lang=ja');
$t('Japonca sayfa <html lang="ja">',  str_contains($ja, 'lang="ja"'));
$t('Japonca og:locale ja_JP',         str_contains($ja, 'content="ja_JP"'));
$ar = $render('/wholesale-to/qatar?lang=ar');
$t('Arapca RTL',                      str_contains($ar, 'dir="rtl"'));
$t('Arapca ulke adi',                 str_contains($ar, 'قطر'));

echo "\n== 5. Olmayan pazar 404 ==\n";
$nf = $render('/wholesale-to/atlantis');
$t('404 sayfasi',                     str_contains($nf, 'Page not found') || str_contains($nf, '404'));
$t('pazar govdesi cizilmiyor',        !str_contains($nf, 'wsfact'));

echo "\n== 6. hreflang: sayfa her dil icin etiket basiyor ==\n";
$t('Avustralya sayfasinda en-AU',     str_contains($au, 'hreflang="en-AU"'));
$t('Japonya sayfasinda ja-JP',        str_contains($au, 'hreflang="ja-JP"'));
$t('x-default var',                   str_contains($au, 'hreflang="x-default"'));
$t('canonical kendi diline isaret',   preg_match('~rel="canonical" href="https://vestrasales.com/wholesale-to/australia"~', $au) === 1);

echo "\n$ok ok, $bad hata\n";
exit($bad ? 1 : 0);
