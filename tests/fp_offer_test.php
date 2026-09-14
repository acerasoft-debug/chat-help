<?php
/**
 * Angebot mektubu (vestra_tpl_listing_offer) — iki yonlu.
 *
 * Bu testin tuttugu sey "metin guzel mi" degil; ucu de bir kez pahaliya
 * ogrenilmis OLGULAR:
 *   1. Fiyat ALICI BASINA kapili (KURAL 2b: kapisi kapali aliciya, sayfasinin
 *      gostermedigi rakami yazmak).
 *   2. Rakamlar KAYITTAN geliyor, metne gomulu degil (sepetle celisen mektup,
 *      aliciyi kendisini reddedecek kasaya yollar).
 *   3. Govde TEK: Angebot ile "bebildertes Sortiment" ayni blok kurucusunu
 *      cagiriyor. Ikinci bir kopya bu depoda defalarca ayristi.
 */
require_once __DIR__ . '/../vestra/inc/email_templates.php';

$pass = 0; $fail = 0;
$t = function (string $what, bool $ok) use (&$pass, &$fail) {
    if ($ok) { $pass++; } else { $fail++; echo "  KIRMIZI: {$what}\n"; }
};

$mk = fn(string $id, string $name, int $moq, int $minc, int $step, array $cols, array $rungs) => [
    'p' => ['id' => $id, 'name' => $name, 'moq' => $moq, 'min_colors' => $minc, 'size_step' => $step],
    'tag' => strtoupper(substr($id, 3, 5)),
    'pairs' => array_map(fn($c) => ['colour' => $c, 'img' => '/uploads/x-' . strtolower($c) . '.jpg'], $cols),
    'rungs' => $rungs,
];
$polo  = $mk('fp-m3600-polo', 'The Fred Perry Shirt — M3600 Twin Tipped', 56, 2, 8,
             ['Navy','White','Green','Black','Bordeaux','Light Blue'],
             [['min'=>56,'price'=>39.0],['min'=>96,'price'=>35.5],['min'=>192,'price'=>32.0]]);
$sweat = $mk('fp-m7535-sweat', 'Fred Perry Crew Neck Sweatshirt — M7535', 50, 4, 10,
             ['Bordeaux','White','Navy','Green','Black'], [['min'=>50,'price'=>39.90]]);
$blocks = [$polo, $sweat];

/* ── 1. FIYAT KAPISI — iki yon de ───────────────────────────────────────── */
[$sOn,  $bOn,  $oOn]  = vestra_tpl_listing_offer('en', 'ACME', $blocks, true);
[$sOff, $bOff, $oOff] = vestra_tpl_listing_offer('en', 'ACME', $blocks, false);

$t('kapi ACIK: kademe rakamlari mektupta',        str_contains($bOn, '39.00') && str_contains($bOn, '35.50') && str_contains($bOn, '32.00'));
$t('kapi ACIK: 39.90 (sweatshirt) mektupta',      str_contains($bOn, '39.90'));
$t('kapi KAPALI: HICBIR fiyat rakami yok',       !preg_match('/\b(39\.00|35\.50|32\.00|39\.90|EUR)\b/', $bOff));
$t('kapi KAPALI: yerine "giris yapinca" cumlesi', str_contains($bOff, 'signed in'));
$t('kapi ACIK: o cumle YOK (gereksiz)',          !str_contains($bOn, 'signed in'));
/* Kart satirlari da fiyat tasiyor; kapali alicida tasimamali. */
$t('kapi KAPALI: kart satirinda da fiyat yok',   !str_contains(json_encode($oOff['rows']), '39.00'));
$t('kapi ACIK: kart satirinda fiyat VAR',         str_contains(json_encode($oOn['rows']), '39.00'));

/* ── 2. RAKAMLAR KAYITTAN ───────────────────────────────────────────────── */
$t('MOQ kayittan (56 / 50)',       str_contains($bOn, '56 pieces') && str_contains($bOn, '50 pieces'));
$t('min renk kayittan (2 / 4)',    str_contains($bOn, 'from 2 colours') && str_contains($bOn, 'from 4 colours'));
$t('paket adimi kayittan (8 / 10)',str_contains($bOn, 'cartons of 8') && str_contains($bOn, 'cartons of 10'));
/* Sabitlenmis bir rakam degil: kaydi degistir, mektup degissin. */
$alt = $blocks; $alt[0]['p']['moq'] = 64;
[, $bAlt, ] = vestra_tpl_listing_offer('en', 'ACME', $alt, true);
$t('MOQ degisince mektup da degisiyor', str_contains($bAlt, '64 pieces') && !str_contains($bAlt, '56 pieces'));
$t('renk sayisi pairs\'ten (6+5=11)',   str_contains($sOn, '11 colours') && count($oOn['shots']) === 11);
$t('model adi kayittan, gomulu degil',  str_contains($bOn, 'M3600 Twin Tipped') && str_contains($bOn, 'Crew Neck Sweatshirt'));

/* ── 3. DIL ─────────────────────────────────────────────────────────────── */
[$sFr, $bFr, $oFr] = vestra_tpl_listing_offer('fr', 'Stock&chic', $blocks, true);
$t('fr: govde Fransizca',        str_contains($bFr, 'Bonjour Stock&chic') && str_contains($bFr, 'Minimum de commande'));
$t('fr: konu Fransizca',         str_contains($sFr, 'coloris'));
$t('fr: Ingilizce etiket SIZMAMIS', !str_contains($bFr, 'Minimum:') && !str_contains($bFr, 'Colours ('));
$t('fr: para kita yazimi (39,00 €)', str_contains($bFr, '39,00 €'));
[, $bDe, ] = vestra_tpl_listing_offer('de', 'YIDA GmbH', $blocks, true);
$t('de: govde Almanca',          str_contains($bDe, 'Mindestabnahme') && str_contains($bDe, 'Stück'));
foreach (['it'=>'Ordine minimo','es'=>'Pedido mínimo','pt'=>'Encomenda mínima','nl'=>'Minimumafname'] as $lg => $needle) {
    [, $bX, ] = vestra_tpl_listing_offer($lg, 'X', $blocks, true);
    $t("{$lg}: kendi dilinde", str_contains($bX, $needle));
}
/* Bilinmeyen dil SESSIZCE Ingilizceye duser — yarim cevrilmis mektup uretmez.
   Kayitli 58 hesabin 2'si 'ar' ve tam bu yoldan geciyor. */
[, $bAr, ] = vestra_tpl_listing_offer('ar', 'X', $blocks, true);
$t('bilinmeyen dil -> tam Ingilizce', str_contains($bAr, 'Minimum:') && str_contains($bAr, 'Hello X'));

/* ── 4. IMZA VESTRA, dukkan adi DEGIL ───────────────────────────────────── */
$t('imza VESTRA',                 str_contains($bOn, "VESTRA\nvestrasales.com"));
$t('dukkan adi imzada YOK',      !str_contains($bOn, 'GARAGE LE PARIS') && !str_contains($bOn, 'Les Garage'));
$t('abonelikten cikma cumlesi var', str_contains($bOn, 'rather not receive'));

/* ── 5. GOVDE TEK: iki mektup ayni blok kurucusunu cagiriyor ────────────── */
$t('paylasilan kurucu tanimli',  function_exists('vestra_listing_block_parts'));
$partsEn = vestra_listing_block_parts($blocks, vestra_listing_block_labels('en'), true);
[, $bCol, ] = vestra_tpl_listing_colours('Hello ACME', $blocks, 'GARAGE LE PARIS', 'en', '', true);
foreach ($partsEn['chunks'] as $ch) {
    $t('ayni blok metni iki mektupta da birebir', str_contains($bCol, trim($ch)) && str_contains($bOn, trim($ch)));
}
/* Kaynak taramasi: dongu ikinci kez YAZILMAMIS olmali. Bu iddia, birinin
   yarin kurucuyu kopyalayip kendi dongusunu yazmasini yakalar. */
$src = (string)file_get_contents(__DIR__ . '/../vestra/inc/email_templates.php');
$t('blok dongusu kaynakta TEK kez', substr_count($src, "foreach (\$pairs as \$x) {") === 1);

echo ($fail === 0)
    ? "fp_offer_test: {$pass} iddia gecti\n"
    : "fp_offer_test: {$pass} gecti, {$fail} KIRMIZI\n";
exit($fail === 0 ? 0 : 1);
