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

/* ── 6. MARKA KAYITTAN (29 Eyl 2026) — Fred Perry ciktisi BIREBIR ayni ─── */
/* Metin eskiden "Fred Perry"ye gomuluydu. Genellestirme, canli Fred Perry
   teklifinin tek harfini degistirmemeli: eski konu/acilis/rozet birebir. */
$fpB = $blocks; foreach ($fpB as &$bb) $bb['p']['brand'] = 'Fred Perry'; unset($bb);
[$sFp, $bFp, $oFp] = vestra_tpl_listing_offer('en', 'ACME', $fpB, true);
$t('FP: konu eskisiyle birebir',   $sFp === 'VESTRA — Fred Perry offer: 2 models, 11 colours');
$t('FP: acilis eskisiyle birebir', str_contains($bFp, 'Fred Perry is in stock with us and I have put the offer together for you — the models below, with a photo for every colour we can ship.'));
$t('FP: rozet eskisiyle birebir',  ($oFp['badge'] ?? '') === 'Fred Perry offer');
[$sFpDe, $bFpDe, ] = vestra_tpl_listing_offer('de', 'X', $fpB, true);
$t('FP de: konu eskisiyle birebir', $sFpDe === 'VESTRA — Fred Perry Angebot: 2 Modelle, 11 Farben');

/* Burberry: her renk AYRI model numarasi -> "8 modeller, 8 renk" ayni sayiyi
   iki kez soylerdi; konu yalniz model sayar. */
$bur = [];
foreach (['8099164' => 'Green', '8096425' => 'Black', '8071621' => 'White'] as $sku => $col) {
    $bur[] = ['p' => ['id' => 'bur-'.$sku, 'brand' => 'Burberry', 'name' => "Burberry Cotton Piqué Polo, {$col} — {$sku}",
                      'moq' => 20, 'size_step' => 0, 'min_colors' => 0],
              'tag' => $sku, 'pairs' => [['colour' => $col, 'img' => '/uploads/b-'.$sku.'.jpg']],
              'rungs' => [['min' => 20, 'price' => 59.9], ['min' => 50, 'price' => 54.9], ['min' => 100, 'price' => 49.9]]];
}
[$sB, $bB, $oB] = vestra_tpl_listing_offer('en', 'ACME', $bur, true);
$t('Burberry: konu markayi tasiyor',        $sB === 'VESTRA — Burberry offer: 3 models');
$t('Burberry: Fred Perry HICBIR yerde yok',  !str_contains($sB.$bB.json_encode($oB), 'Fred Perry'));
$t('Burberry: acilis markayla',              str_contains($bB, 'Burberry is in stock with us'));
$t('Burberry: kademeler kayittan',           str_contains($bB, '59.90') && str_contains($bB, '54.90') && str_contains($bB, '49.90'));
$mixed = [$polo, $bur[0]]; $mixed[0]['p']['brand'] = 'Fred Perry';
[$sMx, , ] = vestra_tpl_listing_offer('en', 'X', $mixed, true);
$t('iki marka: "A & B" (biri yutulmuyor)',   str_contains($sMx, 'Fred Perry & Burberry'));

/* ── 7. KAYITLI STOK SATIRI — yalniz verildiyse ────────────────────────── */
$burS = $bur; $burS[0]['stock'] = ['sizes' => ['S' => 2, 'M' => 6, 'L' => 5, 'XL' => 4, 'XXL' => 2], 'total' => 19, 'real' => true];
[, $bS, $oS] = vestra_tpl_listing_offer('en', 'X', $burS, false);
$t('stok satiri govdede',                    str_contains($bS, 'In stock: S 2 · M 6 · L 5 · XL 4 · XXL 2 — 19 pieces'));
$t('stok satiri kartta da',                  str_contains(json_encode($oS['rows'], JSON_UNESCAPED_UNICODE), 'S 2 · M 6 · L 5 · XL 4 · XXL 2 — 19 pieces'));
$t('stok verilmeyen modelde stok satiri YOK', substr_count($bS, 'In stock:') === 1);
$t('stok FIYAT degil: kapi kapaliyken de basiliyor', str_contains($bS, 'In stock:') && !str_contains($bS, '59.90'));
[, $bSde, ] = vestra_tpl_listing_offer('de', 'X', $burS, false);
$t('stok satiri kendi dilinde (de)',          str_contains($bSde, 'Auf Lager: S 2 · M 6') && str_contains($bSde, '— 19 Stück'));
/* listing_colours ayni kurucuyu cagiriyor; stok vermeyen cagiran degismemeli. */
[, $bCol2, ] = vestra_tpl_listing_colours('Hello ACME', $blocks, 'GARAGE LE PARIS', 'en', '', true);
$t('listing_colours stok satiri basmiyor (cagiran vermedi)', !str_contains($bCol2, 'In stock:'));

/* ── 8. LEAD SURUMU: fiyat ASLA, kayit + abonelikten cikma baglantisi ─── */
[$sL, $bL, $oL] = vestra_tpl_listing_offer('en', 'Boutique X', $bur, true, 'https://vestrasales.com/wholesale/burberry', true);
$t('lead: withPrices=true verilse bile FIYAT YOK', !preg_match('/\b(59\.90|54\.90|49\.90|EUR)\b/', $bL) && !str_contains(json_encode($oL['rows']), '59.90'));
$t('lead: ucretsiz kayit + kayit baglantisi', str_contains($bL, 'registration is free') && str_contains($bL, 'https://vestrasales.com/register?type=buyer'));
$t('lead: abonelikten cikma baglantisi (is akisi token ekler)', str_contains($bL, 'https://vestrasales.com/lead-unsubscribe'));
$t('lead: uye cumlesi ("signed in") YOK',    !str_contains($bL, 'signed in'));
$t('lead: "iki kez yazmistik" iddiasi YOK',  !preg_match('/twice|earlier|before/i', $bL));
$t('lead: liste imzasi (Acerasoft LLC)',     str_contains($bL, "VESTRA · Acerasoft LLC"));
$t('lead: MOQ ve modeller yine var',         str_contains($bL, '20 pieces') && str_contains($bL, '8099164'));
$t('lead: dugme marka sayfasina',            ($oL['button']['url'] ?? '') === 'https://vestrasales.com/wholesale/burberry');
$t('uye surumunde abonelik baglantisi YOK',  !str_contains($bB, 'lead-unsubscribe'));
foreach (['de' => 'Gewerbeanmeldung', 'fr' => 'Kbis', 'it' => 'visura camerale', 'es' => 'licencia comercial', 'pt' => 'certidão permanente', 'nl' => 'KvK'] as $lg => $needle) {
    [, $bLx, ] = vestra_tpl_listing_offer($lg, 'X', $bur, true, '', true);
    $t("lead {$lg}: kayit cumlesi kendi dilinde, fiyat yok", str_contains($bLx, $needle) && !str_contains($bLx, '59,90'));
}

/* ── 9. IS AKISI KABLOLAMASI (send-outreach) ───────────────────────────── */
$wf = (string)file_get_contents(__DIR__ . '/../.github/workflows/send-outreach.yml');
$t('uye kipi letter=offer taniyor',          str_contains($wf, "['winter','fp_offer','wave3','offer']"));
$t('lead kipi newcoll_letter=offer taniyor', str_contains($wf, "['winter','shoes','wave3','offer']"));
$t('damga teklif basina (offer_<tag>_at)',   str_contains($wf, "return 'offer_'.\$tag.'_at';"));
$t('uye teklifi tag + pids SART',            str_contains($wf, "letter=offer: tag=") && str_contains($wf, "letter=offer: pids="));
$t('bloklar TEK kurucudan (uye + lead)',     substr_count($wf, '$buildOfferBlocks($') === 2 && substr_count($wf, 'vestra_listing_colour_shots(') === 1);
$t('stok yalniz KAYITLI stoktan',           str_contains($wf, "\$stk = vestra_stock_real(\$prod);"));
$t('lead teklifi uyeyi atliyor',             str_contains($wf, "if (\$IS_OFFER && isset(\$MEMBER_MAIL[\$email]))"));
$t('lead teklifi lead surumunu kuruyor',     str_contains($wf, "vestra_tpl_listing_offer(\$lang, \$company, \$OFFER_BLOCKS, false, \$OFFER_MORE, true)"));
$t('capraz damga lead kaydina ayni anahtarla', str_contains($wf, "'offer' => (\$MLETTER === 'offer' ? \$STAMP : '')"));
$t('yas kurali iki kipte de TUM kampanya damgalarina bakiyor', substr_count($wf, '$campaignStampTimes(') === 2);
$t('soguk kipte offer yazmak durduruyor',   str_contains($wf, 'newcoll_letter=offer yalniz new_collection=true ile'));
/* Yas kurali davranissal: kapanis is akisinin KENDI kodundan eval ediliyor. */
if (preg_match('/\$campaignStampTimes = (function \(array \$rec, array \$fixed\): array \{.*?\n            \});/s', $wf, $mm)) {
    $cstFn = eval('return ' . $mm[1] . ';');
    $rec = ['last_contacted_at' => '2026-09-01T10:00:00+00:00', 'offer_fp0917_at' => '2026-09-28T10:00:00+00:00',
            'offer_bur2909_at' => '', 'notes' => 'x', 'offer_x_at' => '2026-09-29'];
    $got = $cstFn($rec, ['last_contacted_at', 'last_wave3_at']);
    $t('yas: sabit + DINAMIK teklif damgalari toplanir', isset($got['last_contacted_at'], $got['offer_fp0917_at']));
    $t('yas: bos damga ve gecersiz ad sayilmaz', !isset($got['offer_bur2909_at']) && !isset($got['offer_x_at']) && !isset($got['last_wave3_at']));
} else {
    $t('campaignStampTimes kapanisi is akisinda bulundu', false);
}

echo ($fail === 0)
    ? "fp_offer_test: {$pass} iddia gecti\n"
    : "fp_offer_test: {$pass} gecti, {$fail} KIRMIZI\n";
exit($fail === 0 ? 0 : 1);
