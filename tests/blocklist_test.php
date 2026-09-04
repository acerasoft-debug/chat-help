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
  /* ikinci kuru kosu */
  ['The RealReal', 'x@therealreal.com', ''], ['Stadium Goods', 'x@shopmail.stadiumgoods.com', ''],
  ['Home', 'x@theoutnet.com', ''], ['Designer Shopping in Oxfordshire near London', 'x@bicestervillage.com', ''],
  ['ROS Retail Outlet Shopping', 'x@ros-management.com', ''], ['BUZZ', 'x@buzzsneakers.cz', ''],
  ['Aïshti', '', 'https://aishti.com'],
  /* ucuncu kuru kosu */
  ['Shoptiques', 'x@shoptiques.com', ''], ["Women's Designer Clothing Collections & Runway Fashion", 'x@modaoperandi.com', ''],
  ['Garmentory', 'x@garmentory.com', ''], ['Shopbop', 'x@shopbop.com', ''],
  ['エストネーション公式サイト', 'x@estnation.co.jp', ''], ['ロンハーマン オンラインストア', 'x@ronherman.jp', ''],
  ['Landquart Fashion Outlet', 'x@landquartfashionoutlet.ch', ''], ['Citadel Outlets', 'x@citadeloutlets.com', ''],
] as [$co, $em, $ws]) {
    $t("engellenmeli: $co", $blocked($co, $em, $ws));
}
echo "\n== 9d. GECMELI — komsu adlar (kisa parcalar gercek butigi yakmasin) ==\n";
foreach ([
  ["Shaw's Boutique", 'x@shawsboutique.com', ''], ['Tsumugi Kimono Store', 'x@tsumugi-store.jp', ''],
  ['Bella Rose Boutique', 'x@bellaroseboutique.com', ''], ['Small Wonders Kids', 'x@smallwonders.ie', ''],
  ['Kastner Optik', 'x@kastner-optik.at', ''], ['Village Boutique Kildare', 'x@villageboutique.ie', ''],
  ['Blue Fly Fishing', 'x@blueflyfishing.com', ''],
  ['Real Deal Vintage', 'x@realdealvintage.com', ''], ['Stadium Sportswear', 'x@stadiumsportswear.ie', ''],
  ['Outnet Boutique', 'x@outnetboutique.com', ''], ['Buzz Boutique', 'x@buzzboutique.com', ''],
  ["Ron's Menswear", 'x@ronsmenswear.com', ''], ['Herman Boutique', 'x@hermanboutique.de', ''],
  ['Citadel Vintage', 'x@citadelvintage.com', ''], ['Shop Boutique Paris', 'x@shopboutiqueparis.fr', ''],
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
          'POKER369', 'PECAH138 ✈️ Situs Game Banyak Promo',
          /* 4 Eyl 2026, Italya B partisi. Ikisi de send=false on kosusunda elle
             okundugu icin yakalandi -- tam olarak iki-kosu protokolunun sebebi. */
          'Coming soon - <p style="text-ali',      /* block60.it */
          'capriboutique.com registrato con',      /* capriboutique.com */
          /* Kirik tarama: ham HTML tasiyan bir ad firma adi degildir. */
          'Benvenuti <div class="hdr">', 'Home &nbsp; | Shop'] as $n) {
    $t("park: \"$n\"", vestra_name_is_parked_domain($n));
}
echo "\n== 10b. GECMELI — adinda 'domain' gecen GERCEK dukkan ==\n";
/* Liste bilerek dar: genel bir 'domain'/'shop' kelimesi buraya girerse
   gercek butikler sessizce elenir -- en pahali hata turu. */
foreach (['Domain Boutique Milano', 'The Sedona Store', 'Coming Soon Concept Store',
          'Suspended Animation Vintage', 'Maison Ines Ligron', 'NUBIAN',
          /* 4 Eyl 2026 eklemelerinin komsulari: "coming soon" ile BASLAYAN ama
             ayiracla degil harfle devam eden gercek adlar gecmeli; kayit
             sirketi kaliplarinin icindeki gunluk kelimeler de oyle. */
          'Coming Soon Store Berlin', 'Registro Boutique Roma', 'Con Amore Milano',
          'Este Lauder Concept', 'Style Council Vintage'] as $n) {
    $t("gecer: \"$n\"", !vestra_name_is_parked_domain($n));
}
$t('bos ad park sayilmaz', !vestra_name_is_parked_domain(''));

echo "\n== 12. Operatorun 4 Eyl 2026 Asya/Korfez listesi (45 satir) ==\n";
/* Operator "luks merkezleri" diye elle verdi ve gonderim istedi. Yerler dogru,
   karsi taraf yanlis: AVM ISLETMECISI (ev sahibi, mal almiyor), departman magaza
   ZINCIRI, ya da bolgedeki marka haklarini tutan DISTRIBUTOR. Kod 45'in 12'sini
   zaten tutuyordu; kalanlar eklendi ve karar "gonderim yok" oldu. Bu blok o
   kararin kalicilastigi yer -- liste bir daha otomatik taramadan da gecmesin. */
$t('AVM: Marina Bay Sands',   $blocked('The Shoppes at Marina Bay Sands','','marinabaysands.com'));
$t('AVM: ION Orchard',        $blocked('ION Orchard','','ionorchard.com'));
$t('AVM: Ginza Six',          $blocked('Ginza Six','','ginza6.tokyo'));
$t('AVM: Chadstone',          $blocked('Chadstone - The Fashion Capital','','chadstone.com.au'));
$t('AVM: The Dubai Mall',     $blocked('The Dubai Mall','','thedubaimall.com'));
$t('AVM: Esentai (KZ)',       $blocked('Esentai Mall','','esentaimall.com'));
$t('AVM: Port Baku (AZ)',     $blocked('Port Baku Mall','','portbakumall.az'));
$t('zincir: Takashimaya',     $blocked('Takashimaya Singapore','','takashimaya.com.sg'));
$t('zincir: Isetan Shinjuku', $blocked('Isetan Shinjuku','','mistore.jp'));
$t('zincir: Lotte Avenuel',   $blocked('Lotte Avenuel','','lotteshopping.com'));
$t('zincir: Hyundai',         $blocked('Hyundai Department Store','','ehyundai.com'));
$t('distributor: Rubaiyat',   $blocked('Rubaiyat','','rubaiyat.com'));
$t('distributor: Ali Bin Ali',$blocked('Ali Bin Ali Holding','','alibinali.com'));
$t('distributor: Viled (KZ)', $blocked('Viled Group','','viled.kz'));
$t('distributor: Italdizain', $blocked('Italdizain Group','','italdizain.az'));
$t('distributor: Emporium/Sinteks', $blocked('Emporium Baku','','emporium.az'));
/* .kz/.az uzantilari public-suffix listesinde YOKTU: "viled.kz" duzlesince
   "viledkz" oluyor ve listedeki "viled" hicbir zaman esitlenmiyordu. */
$t('.kz uzantisi soyuluyor', $blocked('','info@viled.kz','viled.kz'));
$t('.az uzantisi soyuluyor', $blocked('','info@italdizain.az','italdizain.az'));

echo "\n== 12b. Kendi markasinin bayrak magazalari ==\n";
/* vestra_is_monobrand() yalnizca SATTIGIMIZ 78 markaya bakiyor; satmadigimiz bir
   evin kendi butigi hicbir suzgece takilmiyordu. "Maison Hermès Ginza" listede
   tam bu bosluktan gecmisti. */
$t('Maison Hermès Ginza', $blocked('Maison Hermès Ginza','','hermes.com'));
$t('Cartier',             $blocked('Cartier Boutique','','cartier.com'));
$t('Rolex',               $blocked('Rolex Ginza','','rolex.com'));
$t('Tiffany & Co',        $blocked('Tiffany & Co Dubai','','tiffany.com'));
$t('Goyard',              $blocked('Goyard Paris','','goyard.com'));
$t('Patek Philippe',      $blocked('Patek Philippe Salon','','patek.com'));

echo "\n== 12c. GECMELI — adi bir eve BENZEYEN gercek dukkanlar ==\n";
/* En pahali hata turu sessiz elemedir. 'omega' bu yuzden listeye HIC alinmadi
   (gunluk kelime), 'tiffany' yerine 'tiffany & co' yazildi (Tiffany bir ad). */
$t('Alpha Omega Watches gecer', !$blocked('Alpha Omega Watches','','alphaomega.com'));
$t('Omega Sport gecer',         !$blocked('Omega Sport','','omegasport.gr'));
$t('Tiffany Mode gecer',        !$blocked('Tiffany Mode','','tiffanymode.it'));
$t('Sinonim Baku gecer',        !$blocked('Sinonim Baku','','sinonim.az'));
$t('Villa Rosa Boutique gecer', !$blocked('Villa Rosa Boutique','','villarosa.it'));

echo "\n== 13. 4 Eyl 2026 cok partili Avrupa listesinden eklenenler ==\n";
/* Kanalda rakip dev e-tailer GRUBU: 'the outnet' listedeydi ama sahibi YNAP ve
   kardes markalari degildi -- ayni grubun ikinci kutusu ayni kapiya cikiyor. */
$t('YNAP',          $blocked('YOOX Net-A-Porter Group','info@ynap.com','ynap.com'));
$t('Mr Porter',     $blocked('Mr Porter','info@mrporter.com','mrporter.com'));
$t('Net-a-Porter',  $blocked('Net-a-Porter','cs@net-a-porter.com','net-a-porter.com'));
$t('De Bijenkorf',  $blocked('De Bijenkorf','service@debijenkorf.nl','debijenkorf.nl'));
$t('Jelmoli',       $blocked('Jelmoli','info@jelmoli.ch','jelmoli.ch'));
$t('Footshop',      $blocked('Footshop Budapest','info@footshop.hu','footshop.hu'));
$t('Omorovicza',    $blocked('Omorovicza Boutique','info@omorovicza.com','omorovicza.com'));
$t('Magee 1866',    $blocked('Magee 1866','info@magee1866.com','magee1866.com'));
$t('Krizia',        $blocked('Krizia','info@krizia.it','krizia.it'));
$t('Trussardi',     $blocked('Trussardi','info@trussardi.com','trussardi.com'));
$t('Stefanel',      $blocked('Stefanel','customercare@stefanel.com','stefanel.com'));
$t('Fracomina',     $blocked('Fracomina','info@fracomina.it','fracomina.it'));
$t('Carla G',       $blocked('Carla G','customercare@carlag.it','carlag.it'));
$t('Sartoria Rossi',$blocked('Sartoria Rossi','info@sartoriarossi.com','sartoriarossi.com'));

echo "\n== 13b. GECMELI — ayni partide elenmemesi gerekenler ==\n";
/* 'stefanel' 8 harf oldugu icin alan adinda ALT DIZE araniyordu ve
   "stefanellimoda.it" icinde eslesti: Stefanelli yaygin bir Italyan soyadi,
   yani gercek bir butik sessizce elenirdi. exact_only'ye alindi.
   'guidi' (soyad) ve 'sartoria' (terzihane) ayni sebeple listeye HIC girmedi. */
$t('Stefanelli Moda gecer',    !$blocked('Stefanelli Moda','info@stefanellimoda.it','stefanellimoda.it'));
$t('Guidi Boutique gecer',     !$blocked('Guidi Boutique','info@guidiboutique.it','guidiboutique.it'));
$t('Sartoria Concept gecer',   !$blocked('Sartoria Milano Concept','info@sartoriaconcept.it','sartoriaconcept.it'));
$t('Carla Gozzi Store gecer',  !$blocked('Carla Gozzi Store','info@carlagozzi.it','carlagozzi.it'));
$t('Porter Store gecer',       !$blocked('Porter Store Lisboa','info@porterstore.pt','porterstore.pt'));
$t('Magee Fashion Cork gecer', !$blocked('Magee Fashion Cork','info@mageefashion.ie','mageefashion.ie'));
$t('Foot Corner Praha gecer',  !$blocked('Foot Corner Praha','info@footcorner.cz','footcorner.cz'));

echo "\n== 13c. Almanya partisi — kendi etiketini ureten markalar ==\n";
/* Ikisi de listede "bagimsiz magaza" diye geldi ama operatorun kendi tarifi
   marka oldugunu soyluyor: Pegador "premium sokak modasi ... bagimsiz dev
   MARKA", Stay Cold Apparel "giyim/hoodie TASARIMI ve satisi yapan". Kendi
   etiketini ureten bir firma bizden parti almaz -- KURAL 1'in "kendi markasini
   satan" dali. */
$t('Pegador',           $blocked('Pegador Streetwear','info@pegador.com','pegador.com'));
$t('Stay Cold Apparel', $blocked('Stay Cold Apparel','info@staycoldapparel.com','staycoldapparel.com'));
/* 'pegador' Ispanyolca/Portekizce bir kelime; alt dize arandiginda gercek bir
   dukkani elerdi, o yuzden exact_only'de. */
$t('Pegadores Moda gecer', !$blocked('Pegadores Moda','info@pegadoresmoda.es','pegadoresmoda.es'));
/* Ayni partideki digerleri gecmeli: hepsi cok markali bagimsiz butik. */
$t('Asphalt Gold gecer',   !$blocked('Asphalt Gold','info@asphaltgold.de','asphaltgold.de'));
$t('AFEW Store gecer',     !$blocked('AFEW Store','info@afew-store.com','afew-store.com'));
$t('Label Kitchen gecer',  !$blocked('Label Kitchen','info@labelkitchen.de','labelkitchen.de'));

echo "\n== 13d. 24S — LVMH'nin kendi kanali ==\n";
/* Alan adi tarafi "24s"i goremez (3 harf; esleyici <4'u tumden atlar), o yuzden
   ad tarafinin tuttugunu ayrica dogrula -- yoksa engelledigimizi sanip
   gonderirdik. */
$t('24S adiyla',        vestra_name_is_blocked('24S'));
$t('24S Paris adiyla',  vestra_name_is_blocked('24S Paris'));
/* Kelime siniri dar kalmali: rakamla baslayan gercek dukkan adlari gecmeli. */
$t('24seven gecer',     !$blocked('24seven Store','info@24sevenstore.com','24sevenstore.com'));
$t('Le 24 Sevres gecer',!$blocked('Le 24 Sevres','info@le24sevres.fr','le24sevres.fr'));
$t('H24 Store gecer',   !$blocked('H24 Store','info@h24store.com','h24store.com'));

echo "\n== 11. Bos/bozuk girdi cokmemeli ==\n";
$t('hepsi bos',        !$blocked('', '', ''));
$t('yalniz @ isareti', !$blocked('', '@', ''));
$t('paylasimli host adiyla yargilanmaz', !$blocked('Mystore','hi@mystore.wixsite.com','mystore.wixsite.com'));

echo "\nTOPLAM: {$ok} gecti, {$fail} kaldi\n";
exit($fail === 0 ? 0 : 1);
