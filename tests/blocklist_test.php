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
    /* 1 Eyl 2026, 100 satirlik Avrupa listesi. Blokliste 100'de 9'unu yakaladi;
       bunlar elle okununca cikti: Frasers Group'un uc zinciri, iki cok magazali
       Italyan grubu, iki AVM/department store, buyuk e-tailerlar. */
    ['Cruise Fashion',          'cruisefashion.com'],
    ['SEVENSTORE',              'sevenstore.com'],
    ['Cricket Liverpool',       'cricketfashion.com'],
    ['LN-CC',                   'ln-cc.com'],
    ['Antonioli Milano',        'antonioli.eu'],
    ['Tessabit',                'tessabit.com'],
    ['Bongénie Grieder',        'bongenie-grieder.ch'],
    ['Steffl Department Store', 'steffl-vienna.at'],
    ['Bernardelli Mantova',     'bernardellistores.it'],
    ['Tiziana Fausti',          'tizianafausti.com'],
    ["Al Duca d'Aosta",         'alducadaosta.com'],
    ['Fashion Clinic',          'fashionclinic.com'],
    ['Smets Luxembourg',        'smets.lu'],
    ['Sivasdescalzo Barcelona', 'sivasdescalzo.com'],
    ['Caliroots',               'caliroots.com'],
    ['Furest',                  'furest.com'],
    /* 1 Eyl 2026, 100 satirlik kuresel liste. Cogu SNEAKER zinciri -- VESTRA'nin
       hattindan ayri bir kanal ve hepsi cok subeli. */
    ['Harvey Nichols Dubai',    'harveynichols.ae'],
    ['Ounass',                  'ounass.ae'],
    ['Etoile La Boutique',      'etoilelaboutique.com'],
    ['Brown Thomas',            'brownthomas.com'],
    ['Footpatrol',              'footpatrol.com'],
    ['Solebox',                 'solebox.com'],
    ['Sneakersnstuff',          'sneakersnstuff.com'],
    ['Foot District',           'footdistrict.com'],
    ['Titolo',                  'titolo.ch'],
    ['10 Corso Como',           '10corsocomo.com'],
    ['Leam',                    'leam.com'],
] as [$co, $ws]) $t("engelli: $co", $blocked($co, '', $ws));

echo "\n== 3b. Ikinci el / kendi markasi (ayni listeden) ==\n";
$t('The Luxury Closet (resale)', $blocked('The Luxury Closet', '', 'theluxurycloset.com'));
$t('Juice Store (CLOT kendi markasi)', $blocked('Juice Store', '', 'juicestore.com'));
$t('Patta (kendi etiketi + zincir)',   $blocked('Patta', '', 'patta.nl'));

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
    /* Avrupa listesi: kendi fabrikasindan alan uretici markalar. */
    ['Slowear Milano',    'slowear.com'],
    ['Luigi Lardini Shop','lardini.com'],
    ['Norse Store',       'norsestore.com'],
    ['Le Fix',            'le-fix.com'],
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
    /* 1 Eyl 2026 Avrupa eklemeleri. 'slowear' ve 'lardini' 6 harften uzun,
       yani alan adinda ALT DIZE araniyordu ve gercek adlarin icinde
       buluyorlardi; sonda yakaladi, ikisi de exact-only listesine alindi. */
    ['Slowearth Vintage',    'slowearthvintage.com',   'slowear'],
    ['Lardinia Moda',        'lardinia.it',            'lardini'],
    ['Furesta Boutique',     'furesta.it',             'furest'],
    ['Smetsana Boutique',    'smetsana.com',           'smets'],
    ['Steffler Mode',        'stefflermode.de',        'steffl'],
    ['Antonioletti Moda',    'antonioletti.it',        'antonioli'],
    ['Le Fixe Concept',      'lefixeconcept.fr',       'le fix'],
    ['Cricket Club Store',   'cricketclub.co.uk',      'cricket fashion'],
    ['Il Giglio Bianco',     'giglioboutique.it',      'giglio (bilerek EKLENMEDI)'],
    ['Leamington Boutique',  'leamingtonboutique.co.uk','leam'],
    ['Pattaya Style Store',  'pattayastyle.co.th',     'patta'],
    ['Juiceberry Concept',   'juiceberry.com',         'juice store'],
    ['Havana Boutique',      'havanaboutique.ie',      'haven (EKLENMEDI: fazla genel)'],
] as [$co, $ws, $near]) $t("gecer: $co (liste: '$near')", !$blocked($co, '', $ws));

echo "\n== 7b. BILINEN DARALMA — kabul edilmis, kayda geciyor ==\n";
/* 'titolo' Italyancada "baslik" demek ve AD tarafi kelime siniriyla esliyor,
   yani adinda bu kelime gecen gercek bir Italyan butigi elenir. Alan adi
   tarafi zaten TAM eslesme istiyor (6 harf). Isvicre zinciri disinda bu adi
   tasiyan dukkan pratikte yok; bilerek birakildi. Gun gelir de bir aday
   bu yuzden elenirse, cozum 'titolo'yu ad listesinden cikarip yalnizca
   alan adina birakmaktir -- test o gun bu satiri gostersin diye burada. */
$t('Titolo Moda Roma ENGELLI (bilinen daralma)', $blocked('Titolo Moda Roma', '', 'titolomoda.it'));

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

echo "\n== 9c. 2 Eyl 2026 — yeni koleksiyon havuzunda cikan zincirler ENGELLENMELI ==\n";
foreach ([
  ['B&M Bargains', 'x@bmstores.co.uk', ''], ['Arnotts', 'x@arnotts.ie', ''], ['Shaws', 'x@shaws.ie', ''],
  ['McElhinneys', 'x@mcelhinneys.com', ''], ['Kastner und Öhler Mode', 'x@kastner-oehler.at', ''],
  ['Harry Rosen', 'x@harryrosen.com', ''], ['Culture Kings', 'x@culturekings.com.au', ''],
  ['TSUM OUTLET', 'x@outlet.tsum.ru', ''], ['Mytheresa', 'x@mytheresa.com', ''],
  ['Smallable', 'x@smallable.com', ''],
  ['Nanette Lepore', 'x@bluestarall.com', ''], ['bellerose', 'x@bellerose.be', ''],
  ['Kildare Village', 'x@kildarevillage.com', ''], ['Dublin Duty Free', 'x@dublindutyfree.ie', ''],
] as [$co, $em, $ws]) {
    $t("engellenmeli: $co", $blocked($co, $em, $ws));
}
echo "\n== 9d. GECMELI — komsu adlar (kisa parcalar gercek butigi yakmasin) ==\n";
foreach ([
  ["Shaw's Boutique", 'x@shawsboutique.com', ''], ['Tsumugi Kimono Store', 'x@tsumugi-store.jp', ''],
  ['Bella Rose Boutique', 'x@bellaroseboutique.com', ''], ['Small Wonders Kids', 'x@smallwonders.ie', ''],
  ['Kastner Optik', 'x@kastner-optik.at', ''], ['Village Boutique Kildare', 'x@villageboutique.ie', ''],
  ['Blue Fly Fishing', 'x@blueflyfishing.com', ''],
] as [$co, $em, $ws]) {
    $t("gecmeli: $co", !$blocked($co, $em, $ws));
}

echo "\n== 10. PARK EDILMIS / SATILIK alan adi yakalanmali ==\n";
/* 1 Eyl 2026: klcollective.com'a mektup gitti, tarama adi "HugeDomains" getirmisti.
   NS/MX kontrolu bunu yakalayamaz -- park saglayicisi alan adini gercekten kaydeder. */
foreach (['HugeDomains', 'Sedo', 'Buy this domain', 'This domain is for sale',
          'Parked domain', 'Account Suspended', 'Website coming soon',
          /* 1 Eyl 2026: luisaboutique.it mektup ALDI, taranan ad buydu. */
          'Domain information luisaboutique.it',
          /* 2 Eyl 2026: velvetmonaco.com'un taranan adi (Alman kayit sirketi
             park sayfasi). Elle okundugu icin yakalandi. */
          'Domain im Kundenauftrag registriert', 'Domaine en vente', 'Dominio in vendita',
          'Sfera.net Park Page', 'TopDomainer Search Engine', 'Coming Soon', 'Under construction',
          /* Ele gecirilmis alan adlari (2 Eyl 2026): dukkan kapanmis, kumar sitesi almis. */
          'POKER369', 'PECAH138 ✈️ Situs Game Banyak Promo'] as $n) {
    $t("park: \"$n\"", vestra_name_is_parked_domain($n));
}
echo "\n== 10b. GECMELI — adinda 'domain' gecen GERCEK dukkan ==\n";
/* Liste bilerek dar: genel bir 'domain'/'shop' kelimesi buraya girerse
   gercek butikler sessizce elenir -- en pahali hata turu. */
foreach (['Domain Boutique Milano', 'The Sedona Store', 'Coming Soon Concept Store',
          'Suspended Animation Vintage', 'Maison Ines Ligron', 'NUBIAN'] as $n) {
    $t("gecer: \"$n\"", !vestra_name_is_parked_domain($n));
}
$t('bos ad park sayilmaz', !vestra_name_is_parked_domain(''));

echo "\n== 11. Bos/bozuk girdi cokmemeli ==\n";
$t('hepsi bos',        !$blocked('', '', ''));
$t('yalniz @ isareti', !$blocked('', '@', ''));
$t('paylasimli host adiyla yargilanmaz', !$blocked('Mystore','hi@mystore.wixsite.com','mystore.wixsite.com'));

echo "\nTOPLAM: {$ok} gecti, {$fail} kaldi\n";
exit($fail === 0 ? 0 : 1);
