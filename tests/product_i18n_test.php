<?php
/* ILAN ADI VE ACIKLAMASI 9 DILDE — KURAL 21
 * (operator, 10 Eyl 2026: *"tüm dillere cevrilecek"*; adin da cevrilip
 * cevrilmeyecegi ayrica soruldu, cevap evet.)
 *
 * Tutulan ilkeler:
 *   - TEK COZUCU. `name_i18n`/`desc_i18n` yalnizca vestra_product_name() ve
 *     vestra_product_desc() tarafindan okunuyor; her sayfanin kendi
 *     `$p['name_i18n'][vlang()]` satirini yazmasi, bu deponun dort mektup
 *     govdesinde ve `desc`/`sizes` ikilisinde odedigi bedelin aynisi olurdu.
 *   - ALAN EKLEMEK YETMEZ, OKUYAN YOL DA GEREKIR. KURAL 5j: platformun banka
 *     kunyesi panelde TOPLANIYORDU, cizici o kaydi hic okumuyordu, ve alanlari
 *     doldurmak hicbir seyi degistirmiyordu. Bolum 3 bu yuzden cagri
 *     yerlerini tek tek sayiyor.
 *   - GERILEME YOK. 671 mevcut ilanin hicbirinde bu alan yok; alani olmayan
 *     ilan aynen kendi `name`/`desc`'ini basmali.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/products.php';
require_once __DIR__ . '/../product-batches/nbb-vocab.php';
require_once __DIR__ . '/../product-batches/kuloglu-vocab.php';   // KULOGLU_COLORS: cakisma denetimi icin

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents($root . '/' . $f);

echo "== 1. Cozucu ==\n";
$plain = ['name' => 'Wire-free bra 3532', 'desc' => 'Plain english', 'brand' => 'NBB'];
$t('alansiz ilan kendi adini basiyor',      vestra_product_name($plain) === 'Wire-free bra 3532');
$t('alansiz ilan kendi desc\'ini basiyor',  vestra_product_desc($plain) === 'Plain english');
$t('marka + ad',                            vestra_product_title($plain) === 'NBB Wire-free bra 3532');
$t('markasiz ilanda bosluk kalmiyor',       vestra_product_title(['name'=>'X']) === 'X');
$t('adsiz ilan bos donuyor',                vestra_product_name([]) === '');

/* vlang() surec icinde sabitleniyor (KURAL 11'in olcum tuzagi), o yuzden dil
   basina AYRI PHP sureci. Tek surecte dokuz dili gezmek dokuzunun da
   "Ingilizce" oldugunu olcerdi ve ceviriler bozukmus gibi gorunurdu. */
echo "\n== 2. Sayfanin dilinde (dil basina ayri surec) ==\n";
$pick = function (string $lang, string $field) use ($root): string {
    $code = 'error_reporting(0);'
          . '$_GET["lang"]=' . var_export($lang, true) . ';'
          . 'require ' . var_export($root . '/inc/products.php', true) . ';'
          . '$p=["name"=>"BASE","desc"=>"BASEDESC","name_i18n"=>["de"=>"DE-AD","fr"=>"FR-AD"],'
          . '"desc_i18n"=>["de"=>"DE-ACIKLAMA"]];'
          . 'echo ' . ($field === 'name' ? 'vestra_product_name($p)' : 'vestra_product_desc($p)') . ';';
    return trim((string)shell_exec('php -d error_reporting=0 -r ' . escapeshellarg($code) . ' 2>/dev/null'));
};
$t('de sayfasi Almanca adi basiyor',        $pick('de', 'name') === 'DE-AD');
$t('fr sayfasi Fransizca adi basiyor',      $pick('fr', 'name') === 'FR-AD');
/* Cevirisi OLMAYAN dil sessizce Ingilizceye duser -- bos bir baslik basmak
   yerine. Yarim cevrilmis bir katalogda dogru davranis bu. */
$t('cevirisiz dil base\'e duser (ja)',      $pick('ja', 'name') === 'BASE');
$t('en her zaman base',                     $pick('en', 'name') === 'BASE');
$t('desc de ayni yolu izliyor (de)',        $pick('de', 'desc') === 'DE-ACIKLAMA');
$t('desc cevirisi yoksa base (fr)',         $pick('fr', 'desc') === 'BASEDESC');
/* Bos dize bir CEVIRI DEGIL: onu basmak urunun adini yok ederdi. */
$t('bos ceviri base\'e duser',              vestra_i18n_pick(['en' => '  '], 'BASE') === 'BASE');

echo "\n== 3. OKUYAN YOL: cagri yerleri ==\n";
/* Alan eklemek yetmez -- KURAL 5j'nin dersi. Musteriye adi/aciklamayi GOSTEREN
   her sayfa cozucuden gecmeli. Liste elle yazili degil, dosya + beklenen cagri:
   yeni bir sayfa eklenirse burada gorunmez, ama var olan biri geri alinirsa
   kirmizi doner. */
$callers = [
    'product.php'        => ['vestra_product_name($p)', 'vestra_product_desc($p)', 'vestra_product_title($p)'],
    'shop.php'           => ['vestra_product_name($p)', 'vestra_product_title($p)'],
    'wholesale.php'      => ['vestra_product_name($p)', 'vestra_product_title($p)'],
    'price-list.php'     => ['vestra_product_name($p)'],
    'group.php'          => ['vestra_product_desc($p)'],
    'order.php'          => ['vestra_product_name($p)'],
    'linesheet.php'      => ['vestra_product_name($p)', 'vestra_product_title($p)'],
    'catalog-pdf.php'    => ['vestra_product_name($p)'],
    'wholesale-xlsx.php' => ['vestra_product_name($p)'],
    'sample-checkout.php'=> ['vestra_product_name($p)', 'vestra_product_title($p)'],
    'index.php'          => ['vestra_product_name($sp)'],
];
foreach ($callers as $file => $needles) {
    $s = $src($file);
    foreach ($needles as $n) $t("$file: $n", str_contains($s, $n));
}
/* Ham alanin GERI DONMEDIGINI de tut: ayni sayfada bir yer cozucuden, baska
   bir yer ham alandan basarsa katalog karti ile urun basligi ayri diller
   gosterir -- ve bunu ancak baska bir dilde gezen biri gorur. */
foreach (['shop.php', 'wholesale.php', 'price-list.php'] as $file) {
    $t("$file: ham \$p['name'] basimi kalmadi",
       !preg_match("~htmlspecialchars\(\s*\(string\)?\s*\(?\\\$p\['name'\]~", $src($file)));
}
/* Fatura/siparis satiri: kayit alicinin O AN gordugu adi tasimali. */
$t('siparis satiri cozucuden geciyor',
   str_contains($src('order.php'), "'name'=>vestra_product_name(\$p),"));

echo "\n== 4. NBB sozlugu: 47 urun x 9 dil ==\n";
$langs = array_keys(NBB_JOIN);
$t('9 dil tanimli',                 count($langs) === 9);
$t('47 urun',                       count(NBB_PRODUCTS) === 47);
$missing = []; $short = [];
foreach (NBB_PRODUCTS as $model => $p) {
    $n = nbb_name_i18n($model); $d = nbb_desc_i18n($model);
    if (count($n) !== 9 || count($d) !== 9) { $missing[] = $model; continue; }
    foreach ($langs as $l) {
        /* Model numarasi her dilde adin icinde olmali: toptanci urunu ona gore
           ariyor ve siparis edende o numara yazili. */
        if (!str_contains($n[$l], (string)$model)) $short[] = "$model/$l";
    }
}
$t('her urun 9 dilde ad + aciklama', $missing === []);
$t('model numarasi her dilde adda',  $short === []);
/* Her turun ve her ozelligin 9 dili tam: eksik bir dil sessizce Ingilizce
   birakirdi ve karisik dilli bir baslik cikardi ("Trägerloser BH, padded"). */
$gaps = [];
foreach (['NBB_TYPES' => NBB_TYPES, 'NBB_ATTRS' => NBB_ATTRS] as $tbl => $rows) {
    foreach ($rows as $k => $row) {
        foreach ($langs as $l) if (trim((string)($row[$l] ?? '')) === '') $gaps[] = "$tbl/$k/$l";
    }
}
$t('tur ve ozellik tablolari 9 dilde tam', $gaps === [], );
$t('kullanilmayan tur/ozellik yok',
   (function () {
       $used = [];
       foreach (NBB_PRODUCTS as $p) { $used['t'][$p['type']] = 1; foreach ($p['attrs'] as $a) $used['a'][$a] = 1; }
       return !array_diff(array_keys(NBB_TYPES), array_keys($used['t'] ?? []))
           && !array_diff(array_keys(NBB_ATTRS), array_keys($used['a'] ?? []));
   })());
/* Kategoriler VESTRA taksonomisinin GERCEK yapraklari olmali: olmayan bir
   kategori t()'den gecmez, ham dizge basilir ve katalog var olmayan bir
   kategori gosterir (KURAL 14'un pano dersi). */
$leaves = [];
foreach (vestra_all_cats() as $g => $ls) foreach ((array)$ls as $l) $leaves[$l] = 1;
$badCat = [];
foreach (NBB_PRODUCTS as $m => $p) if (!isset($leaves[$p['cat']])) $badCat[] = "$m:{$p['cat']}";
$t('kategoriler taksonomide var', $badCat === []);

echo "\n== 5. Tedarikci alanlarinin temizligi ==\n";
/* Renk kodu: sondaki "-<rakam>" atiliyor. */
$t('SİYAH-500 -> SİYAH',       nbb_color_key('SİYAH-500') === 'SİYAH');
$t('TEN-57 -> TEN',            nbb_color_key('TEN-57') === 'TEN');
$t('VİZON-86 -> VİZON',        nbb_color_key('VİZON-86') === 'VİZON');
/* ...ama tireli GERCEK renk adlari bozulmuyor: kalip yalniz RAKAM ariyor.
   Bu deponun mango/zara dersinin renk hali -- gevsek bir kalip "GÜL KURUSU"nu
   ya da "NEON A.PEMBE"yi kirpardi. */
$t('GÜL KURUSU bozulmuyor',    nbb_color_key('GÜL KURUSU') === 'GÜL KURUSU');
$t('NEON A.PEMBE bozulmuyor',  nbb_color_key('NEON A.PEMBE') === 'NEON A.PEMBE');
$t('KOYU YEŞİL bozulmuyor',    nbb_color_key('KOYU YEŞİL') === 'KOYU YEŞİL');

/* Beden mi renk mi: tedarikci kaydinda iki alan yer yer YER DEGISTIRMIS
   (olculdu: 2404, 2900, 9266, 9293). Alanin ADINA guvenilemez. */
foreach (['S', 'M', 'XL', 'XXL', '75', '110', '2', '80 C', '75 B'] as $s) $t("beden: $s", nbb_is_size($s));
foreach (['SİYAH', 'NEON TURUNCU', 'SAHRA-51', 'STANDART', 'BEYAZ', ''] as $c) $t("beden DEGIL: " . ($c ?: '(bos)'), !nbb_is_size($c));
$t('STANDART tek beden',       nbb_is_one_size('STANDART') && !nbb_is_size('STANDART'));
$t('SİYAH tek beden degil',    !nbb_is_one_size('SİYAH'));

/* Yeni renkler VESTRA paletine dusuyor: paletinde olmayan bir ad
   vestra_color_dots() tarafindan SESSIZCE atlanir -- renk hic gorunmez. */
$pal = vestra_colors();
foreach (NBB_EXTRA_COLORS as $k => $row) {
    $t("$k -> palette " . $row['palette'], isset($pal[$row['palette']]));
    foreach ($langs as $l) if (trim((string)($row[$l] ?? '')) === '') { $t("$k/$l ceviri", false); }
}
$t('yeni renkler kuloglu-vocab ile cakismiyor',
   !array_intersect(array_keys(NBB_EXTRA_COLORS), array_keys(KULOGLU_COLORS)));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
