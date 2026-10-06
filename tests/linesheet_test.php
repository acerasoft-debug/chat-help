<?php
/**
 * LINE-SHEET'LER — hangi dosya kime gidiyor, ve icinde ne var.
 *
 * Operator (18 Eyl 2026): *"Line-sheets by brand listelerini duzelt .... fotolardan
 * baska hic bir sey cikmiyor.... komple sku dan lot a ve tum bilgilere kadar
 * herseyin daha duzgun cikmasi gereklimi"*.
 *
 * OLCUM SUNU GOSTERDI: sol sutundaki kutu UYE'ye aciliyordu ama her marka satiri
 * /catalog'a -- yani SOGUK aliciya giden fiyatsiz tanitim dosyasina -- baglaniyordu.
 * Onayli uyenin gercek 19 sutunluk listesi (artikel no, renk, beden serisi, MOQ, LOT,
 * toptan fiyat, kademe, stok, urun linki) ve fotografli PDF'i VARDI, bu ekranda
 * baglantisi YOKTU. "Bir ekranda gorunmeyen secenek olmayan secenektir."
 *
 * NEDEN CALISTIRARAK OLCUYORUZ (price_wall_test.php'nin ayni gerekcesi): kaynakta
 * "if ($PRICES)" gormek olcum degil; bu depoda alti kez kontrolun kendisi yanlis
 * yere bakti. Sayfa ve UC dosya da kum havuzunda GERCEKTEN uretiliyor.
 *
 * IKI YON DE TUTULUYOR:
 *   - onayli uye FIYATLI listeleri gormeli, tanitim dosyasini GORMEMELI;
 *   - onaysiz uye tanitim dosyasini gormeli, fiyatli listeleri GORMEMELI;
 *   - tanitim dosyasi zenginlesti ama HALA fiyat TASIMAMALI.
 * Tek yon yazilsaydi "herkese fiyatli liste ver" de testi yesil birakirdi.
 */
$root = dirname(__DIR__).'/vestra';
$ok = 0; $bad = 0;
$t = function (string $name, bool $cond) use (&$ok, &$bad) {
    if ($cond) { $ok++; echo "  ok   $name\n"; }
    else       { $bad++; echo "  HATA $name\n"; }
};

/* ── kum havuzu ───────────────────────────────────────────────────────────── */
$sand = sys_get_temp_dir().'/vestra_linesheet_'.getmypid();
@mkdir($sand, 0777, true);
exec('cp -r '.escapeshellarg($root).' '.escapeshellarg($sand.'/public_html'));
register_shutdown_function(function () use ($sand) { exec('rm -rf '.escapeshellarg($sand)); });

/* Iki ilan, bilerek: biri KARTONLU (size_step=10), biri TEK PARCA (alan hic yok).
   Lot sutununun iki yonu de ancak boyle olculur -- yalniz kartonlu ilanla yazilan
   bir test, her satira sabit "10" basan bir hatayi da yesil birakirdi. */
$BRAND = 'Zzzlinesheet';
file_put_contents($sand.'/public_html/data/listings.json', json_encode([
  [ 'id' => 'ls-carton', 'sku' => 'LSCARTON-01', 'brand' => $BRAND, 'status' => 'approved',
    'name' => 'Carton Probe Hoodie', 'cat' => 'Hoodies & Sweatshirts', 'mode' => 'fixed',
    'list' => 49.90, 'moq' => 40, 'unit' => 'pc', 'size_step' => 10, 'min_colors' => 4,
    'colors' => ['Black', 'Navy', 'Bordeaux', 'Green'],
    'sizes' => 'S x1 . M x3 . L x3 . XL x2 . XXL x1 . 10/pack',
    'tiers' => [['min' => 40, 'price' => 49.90], ['min' => 50, 'price' => 45.00]],
    'ships_from' => 'EU' ],
  [ 'id' => 'ls-single', 'sku' => 'LSSINGLE-01', 'brand' => $BRAND, 'status' => 'approved',
    'name' => 'Single Probe Tee', 'cat' => 'T-Shirts', 'mode' => 'fixed',
    'list' => 29.00, 'moq' => 20, 'unit' => 'pc',
    'colors' => ['White'], 'sizes' => 'S . M . L . XL',
    'tiers' => [['min' => 20, 'price' => 29.00]], 'ships_from' => 'EU' ],
]));

/* Sayfayi/ucu CLI'da cizdir. $who:
     'admin'   -> $_SESSION['vadmin']  : $IS_ADMIN, yani $PRICES ACIK (head.php ikisini
                  ayni satirda hesapliyor) -- onayli alici hesabi kurmadan kapinin ACIK
                  halini olcmenin en kisa yolu, price_wall_test.php'nin ayni teknigi.
     'member'  -> $_SESSION['member']  : $MEMBER var, $PRICES YOK (tam aradigimiz ara hal).
     'guest'   -> oturum yok. */
$render = function (string $page, string $who) use ($sand): string {
    $boot = '$_SERVER["REQUEST_METHOD"]="GET";';
    /* auth.php ONCE yukleniyor, session_start()'tan once. Duz session_start()
       ile baslayan bir kabuk, auth.php'nin "oturum yanlis depoda baslatilmisti"
       uyarisini CIKTIYA basiyor -- HTML sayfada zararsiz, ama .xlsx ve .pdf
       BINARY: o tek satir zip/PDF imzasinin onune geciyor ve dosya acilamaz
       hale geliyor. Ilk yazimda boyleydi ve olcum "dosya uretilmedi" dedi;
       uretilmisti, onune bir satir metin konmustu. */
    if ($who !== 'guest') {
        $key = $who === 'admin' ? 'vadmin' : 'member';
        $boot .= ' require "inc/auth.php"; if (session_status() !== PHP_SESSION_ACTIVE) session_start();'
               . ' $_SESSION["'.$key.'"]=1;';
    }
    $boot .= ' include '.var_export($page, true).';';
    return (string)shell_exec('cd '.escapeshellarg($sand.'/public_html').' && php -r '.escapeshellarg($boot).' 2>&1');
};
/* Sol sutundaki KUTUYU kes. Sayfanin basligindaki "Excel ↓" baglantisi da
   /wholesale-list.xlsx'e gidiyor ve herkese aciktir; tum sayfada arayan bir
   iddia, kutuyu bostan da yesil dönerdi. */
$block = function (string $html): string {
    $i = strpos($html, 'Line-sheets by brand');
    if ($i === false) return '';
    $j = strpos($html, '</aside>', $i);
    return substr($html, $i, ($j === false ? 4000 : $j - $i));
};

echo "== 1. vestra_pack_size() — tek karar noktasi ==\n";
require_once $root.'/inc/products.php';
$t('alan yok        -> 1',  vestra_pack_size([]) === 1);
$t('size_step=0     -> 1',  vestra_pack_size(['size_step' => 0]) === 1);
$t('size_step=1     -> 1',  vestra_pack_size(['size_step' => 1]) === 1);
$t('size_step=10    -> 10', vestra_pack_size(['size_step' => 10]) === 10);
$t('size_step="8"   -> 8',  vestra_pack_size(['size_step' => '8']) === 8);

echo "\n== 2. /shop sol sutunu — kapi ACIK (onayli uye) ==\n";
$bA = $block($render('shop.php', 'admin'));
$t('kutu ciziliyor',                     $bA !== '');
$t('marka satiri PDF listesine gidiyor', str_contains($bA, 'href="/wholesale-list.pdf?brand='));
$t('yaninda XLSX baglantisi var',        str_contains($bA, 'href="/wholesale-list.xlsx?brand='));
$t('TANITIM dosyasi kutuda YOK',         !str_contains($bA, 'href="/catalog?brand=') && !str_contains($bA, 'href="/catalog"'));
$t('ipucu lot/toptan fiyattan soz ediyor', str_contains($bA, 'lot size') && str_contains($bA, 'wholesale prices'));
$t('"fiyat yok" ipucu artik YAZMIYOR',   !str_contains($bA, 'no pricing'));
$t('satir bicimi kurulu (.lsrow/.lsalt)', str_contains($bA, 'class="lsrow"') && str_contains($bA, 'class="lsalt"'));

echo "\n== 3. /shop sol sutunu — UYE ama kapi KAPALI (kontrol grubu) ==\n";
$bM = $block($render('shop.php', 'member'));
$t('kutu yine ciziliyor',                $bM !== '');
$t('TANITIM dosyasina gidiyor',          str_contains($bM, 'href="/catalog?brand='));
$t('FIYATLI PDF listesi YOK',            !str_contains($bM, '/wholesale-list.pdf'));
$t('FIYATLI Excel listesi YOK',          !str_contains($bM, '/wholesale-list.xlsx'));
$t('"fiyat yok" ipucu duruyor',          str_contains($bM, 'no pricing'));

echo "\n== 4. /shop — misafir ==\n";
$g = $render('shop.php', 'guest');
$t('kutu HIC cizilmiyor',                $block($g) === '');
$t('PHP uyarisi yok',                    !str_contains($g, 'Warning:') && !str_contains($g, 'Fatal error'));

echo "\n== 5. Kapi BAGLANTIYI GIZLEMEK DEGIL, SUNUCU ==\n";
/* Bu iki uc CLI'da bilerek muaf (operator mektuba ek uretebilsin diye), yani
   kum havuzunda kapiyi DAVRANISLA olcemiyoruz. Olculen sey kablolama: her iki
   dosya da kapiyi auth_prices_unlocked() ile soruyor ve web tarafinda cikiyor.
   Baglantiyi gizlemek kapi degildir (KURAL 4b'nin /offer dersi). */
foreach (['wholesale-xlsx.php', 'wholesale-list.php'] as $f) {
    $src = (string)@file_get_contents($root.'/'.$f);
    $t("$f: auth_prices_unlocked kapisi var",
       str_contains($src, "PHP_SAPI !== 'cli' && !auth_prices_unlocked(auth_user())"));
}

echo "\n== 6. FIYATLI Excel — LOT sutunu ==\n";
$xlsxRaw = $render('wholesale-xlsx.php', 'admin');   // CLI muaf: gercek dosya iner
$xf = $sand.'/priced.xlsx'; file_put_contents($xf, $xlsxRaw);
$cells = function (string $file): array {
    $z = new ZipArchive(); if ($z->open($file) !== true) return [];
    $shared = []; $s = $z->getFromName('xl/sharedStrings.xml');
    if ($s !== false && preg_match_all('~<si>(.*?)</si>~s', $s, $m))
        foreach ($m[1] as $si) { preg_match_all('~<t[^>]*>(.*?)</t>~s', $si, $tt);
                                 $shared[] = html_entity_decode(implode('', $tt[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'); }
    $sheet = (string)$z->getFromName('xl/worksheets/sheet1.xml'); $z->close();
    $rows = [];
    preg_match_all('~<row[^>]*>(.*?)</row>~s', $sheet, $rm);
    foreach ($rm[1] as $r) {
        $row = [];
        preg_match_all('~<c[^>]*?(?:\st="(\w+)")?[^>]*>(?:<v>(.*?)</v>|<is>(.*?)</is>)?</c>|<c[^>]*/>~s', $r, $cm, PREG_SET_ORDER);
        foreach ($cm as $c) {
            $ty = $c[1] ?? ''; $v = $c[2] ?? '';
            if (($c[3] ?? '') !== '') { preg_match_all('~<t[^>]*>(.*?)</t>~s', $c[3], $tt); $v = implode('', $tt[1]); }
            $row[] = $ty === 's' && $v !== '' ? ($shared[(int)$v] ?? '') : html_entity_decode($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        $rows[] = $row;
    }
    return $rows;
};
$pr = $cells($xf);
$t('dosya gercekten uretildi (xlsx)',    substr($xlsxRaw, 0, 2) === 'PK' && $pr);
$hdr = [];
foreach ($pr as $r) if (in_array('Art. No', $r, true)) { $hdr = $r; break; }
$t('baslik satiri bulundu',              (bool)$hdr);
$t('LOT sutunu var',                     in_array('Lot', $hdr, true));
$t('SKU sutunu duruyor',                 in_array('Art. No', $hdr, true));
$t('beden serisi sutunu duruyor',        in_array('Sizes', $hdr, true));
$t('MOQ sutunu duruyor',                 in_array('MOQ', $hdr, true));
$t('toptan fiyat sutunu duruyor',        in_array('Wholesale EUR', $hdr, true));
$t('urun linki sutunu duruyor',          in_array('Product link', $hdr, true));
$iLot = array_search('Lot', $hdr, true); $iMoq = array_search('MOQ', $hdr, true);
$iRef = array_search('VESTRA Ref', $hdr, true); $iLnk = array_search('Product link', $hdr, true);
$rowOf = function (string $id) use ($pr, $iRef) {
    foreach ($pr as $r) if (($r[$iRef] ?? '') === $id) return $r; return [];
};
$rc = $rowOf('ls-carton'); $rs = $rowOf('ls-single');
$t('kartonlu ilanin satiri var',         (bool)$rc);
$t('tek parca ilanin satiri var',        (bool)$rs);
$t('kartonlu ilan Lot=10',               ($rc[$iLot] ?? '') === '10');
$t('tek parca ilan Lot=1',               ($rs[$iLot] ?? '') === '1');
$t('kartonlu ilan MOQ=40',               ($rc[$iMoq] ?? '') === '40');
/* Sutun EKLEMEK sonraki tum indeksleri kaydirir: linkcols/numcols/widths
   haritalari elle guncelleniyor ve biri unutulursa link SUTUNU baska bir
   hucreye baglanir. Kaymanin gercekten duzeltildigini urun linkinden olcuyoruz. */
$t('urun linki DOGRU sutunda',           str_starts_with((string)($rc[$iLnk] ?? ''), 'https://vestrasales.com/product?id='));
$t('veri satiri basliktan daha genis degil', count($rc) <= count($hdr));

echo "\n== 7. HERKESE ACIK tanitim dosyasi — zenginlesti ama FIYATSIZ ==\n";
$catRaw = $render('catalog.php', 'guest');
$cf = $sand.'/teaser.xlsx'; file_put_contents($cf, $catRaw);
$tr = $cells($cf);
$t('dosya gercekten uretildi (xlsx)',    substr($catRaw, 0, 2) === 'PK' && $tr);
$thdr = [];
foreach ($tr as $r) if (in_array('Article / Code', $r, true)) { $thdr = $r; break; }
$t('baslik satiri bulundu',              (bool)$thdr);
$t('Category sutunu eklendi',            in_array('Category', $thdr, true));
$t('Sizes sutunu eklendi',               in_array('Sizes', $thdr, true));
$t('Lot sutunu eklendi',                 in_array('Lot', $thdr, true));
$t('Photo sutunu SON sirada',            end($thdr) === 'Photo');   // gorsel SON hucreye tutturuluyor (xlsx.php)
$tLot = array_search('Lot', $thdr, true);
$tArt = array_search('Article / Code', $thdr, true);
$tcar = [];
foreach ($tr as $r) if (($r[$tArt] ?? '') === 'LSCARTON-01') { $tcar = $r; break; }
$tsng = [];
foreach ($tr as $r) if (($r[$tArt] ?? '') === 'LSSINGLE-01') { $tsng = $r; break; }
$t('kartonlu ilan Lot=10',               ($tcar[$tLot] ?? '') === '10');
$t('tek parca ilan Lot=1',               ($tsng[$tLot] ?? '') === '1');
/* TERS YON. Bu dosya soguk aliciya gidiyor ve fiyat TASIMAMASI onun var olma
   sebebi; sutun eklerken bir fiyat alani sizmasi sessiz bir sizinti olurdu. */
$flat = '';
foreach ($tr as $r) $flat .= implode('|', $r).'|';
$t('toptan fiyat SIZMADI (49.90/45.00)', !str_contains($flat, '49.90') && !str_contains($flat, '45.00'));
$t('fiyat sutunu YOK',                   !in_array('Wholesale EUR', $thdr, true) && !in_array('Retail EUR', $thdr, true));

echo "\n== 8. FIYATLI PDF — lot yaziyor, MOQ sutununa YAZILMIYOR ==\n";
$pdf = $render('wholesale-list.php', 'admin');
$t('gercek PDF uretildi',                str_starts_with($pdf, '%PDF'));
/* Ham bayta bakabiliyoruz cunku inc/pdf.php akislari SIKISTIRMIYOR. Bu iddia
   once onu dogruluyor: bir gun Flate eklenirse "metin yok" diye YANLIS bir
   kirmizi degil, sebebi soyleyen bu satir duser. */
$t('akislar sikistirilmamis (ham metin okunabilir)', !str_contains($pdf, '/FlateDecode'));
$t('lot belgede yaziyor',                str_contains($pdf, 'lots of 10'));
/* Ayirici CP1252 orta nokta (0xB7) olarak duruyor; cift tirnak SART -- tek
   tirnakli bir dizgede \xb7 dort harftir ve iddia hicbir belgede eslesmez. */
$t('kategori ile ayni satirda',          str_contains($pdf, "Hoodies & Sweatshirts \xb7 lots of 10"));
$t('MOQ sutununa YAZILMADI',             !str_contains($pdf, "40 pc \xb7 lots") && !str_contains($pdf, '(lots of 10) Tj'));
$t('tek parca ilanda lot YAZMIYOR',      !str_contains($pdf, "T-Shirts \xb7 lots of"));
$t('SKU belgede',                        str_contains($pdf, 'LSCARTON-01'));
$t('MOQ belgede',                        str_contains($pdf, '40 pc'));
$t('beden serisi belgede',               str_contains($pdf, '10/pack'));
$t('renkler belgede',                    str_contains($pdf, 'Bordeaux'));
$t('urun linki belgede',                 str_contains($pdf, 'vestrasales.com/product?id=ls-carton'));

echo "\n== 9. Lot TEK yerden okunuyor ==\n";
/* Ayni olgu dort yerde ayri ayri okunuyordu. Yeni sutunlar tek karar
   noktasindan gecmezse, bir gun "alan yoksa 1" kurali birinde degisir ve
   ayni ilan iki listede iki farkli lot gosterir. */
foreach (['wholesale-xlsx.php', 'catalog.php', 'wholesale-list.php', 'linesheet.php'] as $f) {
    $src = (string)@file_get_contents($root.'/'.$f);
    $t("$f: vestra_pack_size cagiriyor", str_contains($src, 'vestra_pack_size('));
    $t("$f: size_step'i ELLE okumuyor",  !preg_match("~\\\$p\\['size_step'\\]~", $src));
}

echo "\n---- $ok gecti, $bad kirmizi ----\n";
exit($bad === 0 ? 0 : 1);
