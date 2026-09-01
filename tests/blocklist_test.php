<?php
/* KURAL 1 — zincir / distributor / kendi markasini satan firmaya kampanya GITMEZ.
 *
 * Depodaki en pahali kural buydu ve tek testi yoktu. CLAUDE.md uc ayri
 * regresyonu kayda geciriyor, ucu de burada kilitleniyor:
 *
 *   1) Kural KODA gomulmeden once add-and-send.yml kontrolu hic cagirmiyordu;
 *      elle verilen bir Korfez listesi o bosluktan gecti (Alshaya, Al Tayer,
 *      Apparel Group, BFL, Trafalgar...). Artik dort gonderim yolu da
 *      vestra_lead_is_blocked() cagiriyor -- bu test o kapinin dogru
 *      cevap verdigini dogruluyor.
 *   2) Zincirin indirim (off-price) kolu da zincirdir: 29 Agustos'ta listede
 *      'nordstrom' vardi, Saks yoktu ve Saks OFF 5TH gecti.
 *   3) TERS YON DE HATA: kisa adlar baska adlarin ICINDE bulunup gercek
 *      musteri adaylarini sessizce eliyordu (mango -> Mangobay, zara ->
 *      Zaragoza, fila -> Filaticcio, marshalls -> marshallstreet). Sessiz
 *      eleme yanlis gonderimden PAHALI, cunku kimse fark etmiyor -- bu yuzden
 *      asagidaki "gecmeli" bolumu en az "engellenmeli" kadar onemli.
 *
 * Test listeye yeni ad eklemeyi yasaklamaz; yalnizca eklenen adin komsularini
 * yakip yakmadigini soyler.
 *
 * GIZLILIK: bu dosyada GERCEK musteri adayinin ADRESI yok. Depo herkese acik ve
 * musteri listesi repoya girmez. Hedef adaylar yalnizca firma adi + acik web
 * sitesiyle yaziliyor (dukkanin zaten herkese acik sitesi); adres alani bos
 * geciliyor -- kontrol siteyi de okudugu icin kapsam ayni kaliyor. Adres gereken
 * yerlerde yerel kisim uydurma ('x@'), ve yalnizca ZINCIR alan adlarinda.
 */
require_once __DIR__.'/../vestra/inc/notify.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++;  echo "  ok   $n\n"; }
    else    { $fail++; echo "  HATA $n\n"; }
};
/* Gercek gonderim yolunun cagirdigi fonksiyonun ta kendisi. Alt fonksiyonlari
   (name/domain/monobrand) tek tek sinamak yaniltici olurdu: acik kalan delik
   her seferinde "hangi kontrol cagrilmadi" oldu, "kontrol yanlis cevap verdi"
   degil. */
$blocked = fn(string $co, string $em, string $ws = '') => vestra_lead_is_blocked(
    ['company' => $co, 'email' => $em, 'website' => $ws]
);

echo "\n== 1. Zincir / magazalar grubu ENGELLENMELI ==\n";
foreach ([
    ['Alshaya Group',           'alshaya.com'],
    ['Apparel Group',           'apparelgroup.com'],
    ['Trafalgar Luxury Group',  'trafalgarluxurygroup.com'],
    ['Dover Street Market Ginza','ginza.doverstreetmarket.com'],
    /* 1 Eyl 2026: bu ikisi 31 Agustos'ta elle okumadan kacti ve mektup aldi. */
    ['Harrolds',                'harrolds.com.au'],
    ['Incu',                    'incu.com'],
    ['Studious Tokyo',          'studious.co.jp'],
    ['Zara',                    'zara.com'],
    /* 1 Eyl 2026, ikinci APAC listesi: Japonya'nin iki devi (yuzlerce sube +
       kendi etiketleri) ve iki cok subeli sneaker zinciri. */
    ['United Arrows Premium',   'united-arrows.co.jp'],
    ['Beam Japan Custom',       'beams.co.jp'],
    ['Atmos Tokyo',             'atmos-tokyo.com'],
    ['Limited Edt Chamber',     'limitededt.com'],
] as [$co, $ws]) $t("engelli: $co", $blocked($co, '', $ws));

echo "\n== 2. Zincirin INDIRIM kolu da zincirdir (29 Agu regresyonu) ==\n";
$t('Saks OFF 5TH',   $blocked('Saks OFF 5TH',   '', 'saksoff5th.com'));
$t('Nordstrom Rack', $blocked('Nordstrom Rack', '', 'nordstromrack.com'));

echo "\n== 3. Distributor / trading house ENGELLENMELI ==\n";
foreach ([
    ['Melium',                 'melium.com'],
    ['Nepenthes Tokyo',        'nepenthes.co.jp'],
    ['Slam Jam',               'slamjam.com'],
    ['Bella Moda Distribution','bellamodadist.it'],
    /* Club 21: Asya'da onlarca markanin bolge temsilcisi. The Hour Glass: cok
       ulkeli saat zinciri + resmi distributor. Ikisi de kanalda rakip. */
    ['Club 21 Singapore',      'club21global.com'],
    ['The Hour Glass Luxury',  'thehourglass.com'],
] as [$co, $ws]) $t("engelli: $co", $blocked($co, '', $ws));

echo "\n== 4. Kendi markasini satan / monobrand ENGELLENMELI ==\n";
foreach ([
    ['Kwanpen',          'kwanpen.com'],
    ['Beyond The Vines', 'beyondthevines.com'],
    ['Paul Ropp',        'paulropp.com'],
    ['Our Legacy',       'ourlegacy.com'],
    /* Adin KENDISI premium bir marka ise o markanin kendi operasyonu (flagship,
       ulke subesi, resmi distributor) -- kendi fabrikasindan aliyor, bizden asla. */
    ['Gucci Store Milano','guccistore.it'],
    /* 1 Eyl 2026, ikinci APAC listesi: kendi etiketini uretip satanlar. */
    ['Paspaley Luxury',   'paspaley.com'],
    ['Lucy Folk',         'lucyfolk.com'],
    ['Uma and Leopold',   'umaandleopold.com'],
    ['Bamboo Blonde Bali','bambooblonde.com'],
    ['Real McCoys Tokyo', 'realmccoys.co.jp'],
    ['Kim Soo Bali',      'kimsoo.com'],
    /* Alan adi (pmc.my) blokliste ile eslesmiyor; AD tarafi yakaliyor. */
    ['Pestle & Mortar',   'pmc.my'],
] as [$co, $ws]) $t("engelli: $co", $blocked($co, '', $ws));

echo "\n== 5. Kanalda alici OLMAYAN turler ENGELLENMELI ==\n";
$t('AVM isletmecisi (butik degil)', $blocked('Designers At Pavilion','', 'pavilion-kl.com'));
$t('ikinci el / konsinye',          $blocked('Luxe It Fwd','', 'luxeitfwd.com.au'));
$t('ortak calisma alani',           $blocked('Colony KL','', 'colony.work'));

echo "\n== 6. Ad tarafi COKERSE alan adi yine yakalamali ==\n";
/* Sirket adi lead'in kendi sitesinden kazaniyor ve tam da onemli olan durumda
   COKUYOR: bot duvari "Access to this page has been denied" donduruyor ve
   distributor, adi bir hata sayfasi olan kayitla kontrolden geciyordu. */
$t('ad hata sayfasi, alan adi engelli', $blocked('Access to this page has been denied','x@alshaya.com','alshaya.com'));
$t('ad bos, alan adi engelli',          $blocked('', 'x@apparelgroup.com', ''));
/* Adresin alan adi siteden FARKLI olabilir; ikisi de okunmali. */
$t('site temiz, adres zincirin alan adinda', $blocked('Concept Store','x@alshaya.com','conceptstore.example'));

echo "\n== 7. GECMELI — gercek cok markali butikler (sessiz eleme guvenligi) ==\n";
/* Bu bolum bozulursa kimse fark etmez: mektup gitmez, hata da cikmaz. Listeye
   kisa/genel bir kelime eklenirse ilk burasi kirilir. */
foreach ([
    ['Mangobay Boutique',    'mangobay.it',            'mango'],
    ['Zaragoza Moda',        'zaragozamoda.es',        'zara'],
    ['Filaticcio Milano',    'filaticcio.it',          'fila'],
    ['Marshall Street',      'marshallstreet.co.uk',   'marshalls'],
    ['Next Door Concept',    'nextdoorconcept.fr',     'next'],
    ['Atmos Green Concept',  'atmosgreen.it',          'atmos usa'],
    ['Mashburn Family Store','mashburnfamily.com',     'sid mashburn'],
    ['Dynamite Boutique',    'dynamiteboutique.it',    'dynamite'],
    ['Incubator Store',      'incubatorstore.com',     'incu'],
    /* 1 Eyl 2026 eklemelerinin komsulari. 'beams' 5 harf -> alan adinda YALNIZCA
       tam eslesir, yoksa sunbeam/beambutik elenirdi; 'the hour glass' bitisik
       yazildiginda gercek bir butigin adinin icinde gecebilir. */
    ['Beam Boutique Milano', 'beamboutique.it',        'beams'],
    ['Sunbeam Store',        'sunbeamstore.com',       'beams'],
    ['Hourglass Boutique Paris','hourglassparis.fr',   'the hour glass'],
    ['Real Style Store',     'realstylestore.com',     'real mccoys'],
] as [$co, $ws, $near]) $t("gecer: $co (liste: '$near')", !$blocked($co, '', $ws));

echo "\n== 8. GECMELI — bu listeden gelen gercek adaylar ==\n";
/* 1 Eyl 2026 APAC listesinden, elle okunarak "gercek cok markali butik" diye
   ayrilanlar. Blokliste buyudukce bunlarin sessizce elenmedigini gosterir.
   Yalnizca firma adi + acik web sitesi; adres YOK (bkz. yukaridaki gizlilik notu). */
foreach ([
    ['GR8 Tokyo',        'gr8.jp'],
    ['Parlour X',        'parlourx.com'],
    ['Marais Melbourne', 'marais.com.au'],
    ['Riada Concept',    'riadaconcept.com'],
    ['Sects Shop',       'sectsshop.com'],
    ['Jade Boutique',    'jade-boutique.com'],
] as [$co, $ws]) $t("gecer: $co", !$blocked($co, '', $ws));

echo "\n== 9. Kayitli istisna: vipshop.com (operator karari, 31 Agu 2026) ==\n";
/* VipShop Singapore Pte. Ltd. -- operator "farkli urunler satiyor" diyerek
   KURAL 1 kapsaminda gormedi. Cin'deki off-price platformuyla karistirma.
   Biri blokliste 'vipshop' eklerse bu satir dusecek ve karar hatirlanacak. */
$t('vipshop.com engellenmiyor', !$blocked('VipShop Singapore Pte. Ltd.','','vipshop.com'));

echo "\n== 10. Bos/bozuk girdi cokmemeli ==\n";
$t('hepsi bos',        !$blocked('', '', ''));
$t('yalniz @ isareti', !$blocked('', '@', ''));
$t('paylasimli host adiyla yargilanmaz', !$blocked('Mystore','hi@mystore.wixsite.com','mystore.wixsite.com'));

echo "\nTOPLAM: {$ok} gecti, {$fail} kaldi\n";
exit($fail === 0 ? 0 : 1);
