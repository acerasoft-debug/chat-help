<?php
/**
 * Marka suzgeci: BIR ad ya da VIRGULLE birden fazla ad.
 *
 * Bu testin tuttugu iki OLGU, ikisi de bu depoda pahaliya ogrenildi:
 *
 *  1. ESLESME AD BASINA TAM, alt dize DEGIL. Bloklistede `mango` bir gun
 *     "Mangobay Boutique"i, `zara` "Zaragoza Moda"yi sessizce elemisti.
 *     Burada bedeli ters yonde: "Lacoste" isteyen bir aliciya, yarin gelecek
 *     bir "Lacoste Kids"in fiyatlari da gonderilirdi.
 *  2. UC OKUYAN AYNI FONKSIYONU CAGIRIYOR. PDF (wholesale-list.php), Excel
 *     (wholesale-xlsx.php) ve price_list mektubu ayni zarfa giriyor; ucune
 *     ayri ayri bir virgul ayristirmasi yazmak, PDF'in iki marka tasirken
 *     Excel'in tek marka tasidigi bir zarf uretirdi. Tam o ayrisma xlsx'in
 *     kategori suzgeci eksikken bir kez yasandi ve wholesale-xlsx.php'nin
 *     kendi yorumunda yazili.
 */
require_once __DIR__ . '/../vestra/inc/products.php';

$pass = 0; $fail = 0;
$t = function (string $what, bool $ok) use (&$pass, &$fail) {
    if ($ok) { $pass++; } else { $fail++; echo "  KIRMIZI: {$what}\n"; }
};
$m = fn(string $b, string $f) => vestra_brand_filter_match($b, $f);

/* ── 1. BOS SUZGEC = HEPSI (mevcut davranis birebir korunuyor) ──────────── */
$t('bos suzgec her markayi gecirir',        $m('Lacoste', '') && $m('Gucci', '') && $m('', ''));
$t('yalniz bosluk da bos sayilir',          $m('Gucci', '   '));

/* ── 2. TEK MARKA ──────────────────────────────────────────────────────── */
$t('tek marka: eslesen gecer',              $m('Lacoste', 'Lacoste'));
$t('tek marka: buyuk/kucuk harf onemsiz',   $m('lacoste', 'LACOSTE'));
$t('tek marka: eslesmeyen elenir',         !$m('Gucci', 'Lacoste'));

/* ── 3. COKLU ──────────────────────────────────────────────────────────── */
$three = 'Lacoste,Fred Perry,Ralph Lauren';
$t('coklu: 1. ad gecer',                    $m('Lacoste', $three));
$t('coklu: ortadaki ad gecer',              $m('Fred Perry', $three));
$t('coklu: son ad gecer',                   $m('Ralph Lauren', $three));
$t('coklu: listede olmayan elenir',        !$m('Gucci', $three));
$t('coklu: bosluklu yazim da calisir',      $m('Ralph Lauren', 'Lacoste, Fred Perry , Ralph Lauren'));
$t('coklu: bos parcalar atlanir',           $m('Lacoste', ',,Lacoste,,'));
$t('yalniz virgul = hicbir ad',            !$m('Lacoste', ',,,'));

/* ── 4. ALT DIZE DEGIL — mango/zara dersi, HER IKI YON ──────────────────── */
$t('Lacoste suzgeci "Lacoste Kids"i ALMAZ',        !$m('Lacoste Kids', 'Lacoste'));
$t('"Lacoste Kids" suzgeci Lacoste\'u ALMAZ',      !$m('Lacoste', 'Lacoste Kids'));
$t('coklu listede de alt dize yok',                !$m('Fred Perry Junior', 'Lacoste,Fred Perry'));
$t('onek eslesmesi yok (Ralph / Ralph Lauren)',    !$m('Ralph', 'Ralph Lauren'));
$t('bos marka adi, dolu suzgecte elenir',          !$m('', 'Lacoste'));

/* ── 5. KABLOLAMA: uc okuyan da AYNI fonksiyonu cagiriyor ───────────────
   Kaynak taramasi, cunku asil kusur davranista degil KOPYADA olurdu: birinin
   kendi virgul ayristirmasini yazmasi, ayni zarftaki iki belgeyi ayristirir. */
$root = dirname(__DIR__);
$wired = [
    'vestra/wholesale-list.php'                   => 'PDF ureteci',
    'vestra/wholesale-xlsx.php'                   => 'Excel ureteci',
    '.github/workflows/send-campaign-preview.yml' => 'price_list mektubu',
];
foreach ($wired as $rel => $label) {
    $src = (string)@file_get_contents($root . '/' . $rel);
    $t("{$label} ({$rel}) OKUNABILDI", $src !== '');
    $t("{$label} paylasilan fonksiyonu cagiriyor",
       str_contains($src, 'vestra_brand_filter_match('));
    /* Eski TEK-marka karsilastirmasi geride kalmamali: kalsaydi suzgec
       coklu verildiginde o satir hicbir seye eslesmez ve liste BOS cikardi. */
    $t("{$label} eski tek-marka strcasecmp'i kalmamis",
       !preg_match('/strcasecmp\(\s*(?:trim\(\(string\)\(\$p\[.brand.\]\s*\?\?\s*..\)\)|\$brand)\s*,\s*\$(?:plBrand|brandFilter)\s*\)/', $src));
}

/* Suzgec metni musteriye giden konu satirina GIRMEMELI: `price_list` dali
   kapsam adini eslesen MARKA ADLARINDAN kuruyor, ham token'dan degil. Ayni
   hata kategori tarafinda bir kez canli kosuda goruldu ("Sweat — Preisliste").
   Ham token virgullu bir dizge, yani sizarsa gozle gorulur. */
$pv = (string)@file_get_contents($root . '/.github/workflows/send-campaign-preview.yml');
$t('kapsam adi eslesen marka adlarindan kuruluyor', str_contains($pv, '$plBrandNames = array_keys($plBrandCount);'));
$t('kapsam adi ham $plBrand token\'ini kullanmiyor', !str_contains($pv, '$plFactsScope = trim($plBrand '));

echo ($fail === 0)
    ? "brand_filter_test: {$pass} iddia gecti\n"
    : "brand_filter_test: {$pass} gecti, {$fail} KIRMIZI\n";
exit($fail === 0 ? 0 : 1);
