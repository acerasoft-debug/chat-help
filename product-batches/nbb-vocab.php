<?php
/**
 * VESTRA — NBB ic camasiri katalogu: urun ADI ve ACIKLAMASI, 9 dilde.
 *
 * (operator, 10 Eyl 2026: *"tüm dillere cevrilecek"*, ve adin da cevrilip
 * cevrilmeyecegi ayrica soruldu — cevap **evet**. 4 Eylul'deki Kuloglu isinde
 * baslik tek ve Ingilizce yazilmisti, cunku o gun cevrilmis bir basligin
 * BASILACAGI YER yoktu; simdi var: `name_i18n` / `desc_i18n` +
 * vestra_product_name() / vestra_product_desc().)
 *
 * ── NEDEN KELIME KELIME CEVIRI DEGIL ─────────────────────────────────────────
 * kuloglu-vocab.php'nin KULOGLU_WORDS tablosu tek tek kelimeleri ceviriyor ve
 * bu TEK bir Ingilizce baslik kurmak icin yeterliydi. Dokuz dil icin degil:
 * "MİKRO DOLGULU STRAPLEZ SÜTYEN" dort kelime olarak cevrilip yan yana
 * dizilirse Almanca'da sifat sirasi, Fransizca'da sifatin isimden SONRA
 * gelmesi, Rusca'da cinsiyet uyumu ve Arapca'da belirlilik uyumu bozulur --
 * yani yedi dilde bozuk, birinde dogru.
 *
 * Bunun yerine ad IKI parcadan kuruluyor:
 *   NBB_TYPES  — giysinin kendisi, dilin kendi TAM isim tamlamasi olarak
 *   NBB_ATTRS  — ozellikler, VIRGULLE AYRILMIS bir liste olarak
 * "Sutyen, mikrofiber, dolgulu — 3520" kalibinda sifat sirasi diye bir sorun
 * yok: liste her dilde ayni sekilde dogru okunuyor ve toptan kataloglarinin
 * gercekten kullandigi yazim bu. Ayrica OLCEKLENIYOR: 48. urun geldiginde
 * yazilacak sey iki anahtar, 9 yeni cumle degil.
 *
 * ── DESC NEYI YAZMAZ ─────────────────────────────────────────────────────────
 * Aciklama BEDEN SERISINI yazmiyor. Bu depo tam o hatayi 9 Eyl 2026'da
 * Balenciaga'da yasadi: `sizes` alani duzeltildi, `desc` metni eski seriyi
 * yazmaya devam etti ve urun sayfasi ayni urun icin spec satirinda bir, bir
 * paragraf altinda baska dagilim gosterdi. Ayni sebeple GONDERIM YERI de
 * yazilmiyor -- o `ships_from` alaninin isi (KURAL 3).
 *
 * ── KAYNAK VERI ──────────────────────────────────────────────────────────────
 * 47 urun, `titles` modunun 10 Eyl 2026 dokumundan. Kategori esleri VESTRA
 * taksonomisinin "Underwear & Socks" grubunun yapraklari (vestra_all_cats()).
 * Tedarikci fiyati BU DOSYADA YOK ve olmamali (depo herkese acik).
 */

/* Giysi turu — her dilde TAM isim tamlamasi. Adin ilk parcasi. */
const NBB_TYPES = [
'training_bra'      => ['en'=>'First bra','fr'=>'Soutien-gorge d’initiation','es'=>'Sujetador de iniciación','it'=>'Reggiseno per ragazza','de'=>'Erster BH','pt'=>'Sutiã de iniciação','ru'=>'Бюстгальтер для девочек','ar'=>'حمالة صدر أولى','ja'=>'ファーストブラ'],
'bandeau_bra'       => ['en'=>'Bandeau bra','fr'=>'Soutien-gorge bandeau','es'=>'Sujetador bandeau','it'=>'Reggiseno a fascia','de'=>'Bandeau-BH','pt'=>'Sutiã bandeau','ru'=>'Бюстгальтер-бандо','ar'=>'حمالة صدر بدون حمالات كتف','ja'=>'バンドゥブラ'],
'bandeau'           => ['en'=>'Bandeau top','fr'=>'Bustier','es'=>'Top bandeau','it'=>'Top a fascia','de'=>'Bandeau-Top','pt'=>'Top bandeau','ru'=>'Топ-бандо','ar'=>'توب بندو','ja'=>'バンドゥトップ'],
'wirefree_bra'      => ['en'=>'Wire-free bra','fr'=>'Soutien-gorge sans armatures','es'=>'Sujetador sin aros','it'=>'Reggiseno senza ferretto','de'=>'BH ohne Bügel','pt'=>'Sutiã sem aro','ru'=>'Бюстгальтер без косточек','ar'=>'حمالة صدر بدون أسلاك','ja'=>'ノンワイヤーブラ'],
'underwire_bra'     => ['en'=>'Underwired bra','fr'=>'Soutien-gorge à armatures','es'=>'Sujetador con aros','it'=>'Reggiseno con ferretto','de'=>'BH mit Bügel','pt'=>'Sutiã com aro','ru'=>'Бюстгальтер на косточках','ar'=>'حمالة صدر بأسلاك','ja'=>'ワイヤーブラ'],
'strapless_bra'     => ['en'=>'Strapless bra','fr'=>'Soutien-gorge sans bretelles','es'=>'Sujetador sin tirantes','it'=>'Reggiseno senza spalline','de'=>'Trägerloser BH','pt'=>'Sutiã sem alças','ru'=>'Бюстгальтер без бретелей','ar'=>'حمالة صدر بدون حمالات','ja'=>'ストラップレスブラ'],
'shaping_bra'       => ['en'=>'Shaping bra','fr'=>'Soutien-gorge gainant','es'=>'Sujetador reductor','it'=>'Reggiseno modellante','de'=>'Formender BH','pt'=>'Sutiã modelador','ru'=>'Утягивающий бюстгальтер','ar'=>'حمالة صدر مشدة','ja'=>'補正ブラ'],
'nursing_bra'       => ['en'=>'Nursing bra','fr'=>'Soutien-gorge d’allaitement','es'=>'Sujetador de lactancia','it'=>'Reggiseno per allattamento','de'=>'Still-BH','pt'=>'Sutiã de amamentação','ru'=>'Бюстгальтер для кормления','ar'=>'حمالة صدر للرضاعة','ja'=>'授乳ブラ'],
'nursing_vest'      => ['en'=>'Nursing camisole','fr'=>'Caraco d’allaitement','es'=>'Camiseta de lactancia','it'=>'Canotta per allattamento','de'=>'Still-Top','pt'=>'Camisola de amamentação','ru'=>'Майка для кормления','ar'=>'قميص داخلي للرضاعة','ja'=>'授乳キャミソール'],
'silicone_bra'      => ['en'=>'Silicone bra','fr'=>'Soutien-gorge silicone','es'=>'Sujetador de silicona','it'=>'Reggiseno in silicone','de'=>'Silikon-BH','pt'=>'Sutiã de silicone','ru'=>'Силиконовый бюстгальтер','ar'=>'حمالة صدر سيليكون','ja'=>'シリコンブラ'],
'brief'             => ['en'=>'Brief','fr'=>'Culotte','es'=>'Braga','it'=>'Slip','de'=>'Slip','pt'=>'Cueca feminina','ru'=>'Трусы-слип','ar'=>'سروال داخلي','ja'=>'ショーツ'],
'shaping_brief'     => ['en'=>'Shaping brief','fr'=>'Culotte gainante','es'=>'Braga moldeadora','it'=>'Slip modellante','de'=>'Formslip','pt'=>'Cueca modeladora','ru'=>'Корректирующие трусы','ar'=>'سروال داخلي مشد','ja'=>'補正ショーツ'],
'maternity_brief'   => ['en'=>'Maternity brief','fr'=>'Culotte de grossesse','es'=>'Braga de maternidad','it'=>'Slip premaman','de'=>'Umstandsslip','pt'=>'Cueca de grávida','ru'=>'Трусы для беременных','ar'=>'سروال داخلي للحوامل','ja'=>'マタニティショーツ'],
'shorts'            => ['en'=>'Boxer shorts','fr'=>'Shorty','es'=>'Culotte tipo short','it'=>'Shorts','de'=>'Panty','pt'=>'Boxer feminino','ru'=>'Трусы-шорты','ar'=>'شورت داخلي','ja'=>'ボクサーショーツ'],
'cycling_shorts'    => ['en'=>'Cycling shorts','fr'=>'Cycliste','es'=>'Culotte ciclista','it'=>'Pantaloncini ciclista','de'=>'Radlerhose','pt'=>'Calção ciclista','ru'=>'Велосипедки','ar'=>'شورت طويل','ja'=>'サイクルショーツ'],
'vest'              => ['en'=>'Camisole','fr'=>'Caraco','es'=>'Camiseta interior','it'=>'Canotta','de'=>'Unterhemd','pt'=>'Camisola interior','ru'=>'Майка','ar'=>'قميص داخلي','ja'=>'キャミソール'],
'mens_vest'         => ['en'=>'Men’s vest','fr'=>'Débardeur homme','es'=>'Camiseta interior de hombre','it'=>'Canottiera uomo','de'=>'Herren-Unterhemd','pt'=>'Camisola interior de homem','ru'=>'Мужская майка','ar'=>'فانلة رجالية','ja'=>'メンズ タンクトップ'],
'mens_briefs'       => ['en'=>'Men’s briefs','fr'=>'Slip homme','es'=>'Slip de hombre','it'=>'Slip uomo','de'=>'Herren-Slip','pt'=>'Cueca de homem','ru'=>'Мужские трусы','ar'=>'سروال داخلي رجالي','ja'=>'メンズ ブリーフ'],
'waist_shaper_brief'=> ['en'=>'Shaping brief','fr'=>'Culotte gainante','es'=>'Faja braga','it'=>'Guaina a slip','de'=>'Miederslip','pt'=>'Cinta-cueca','ru'=>'Корректирующие трусы','ar'=>'سروال مشد','ja'=>'ガードルショーツ'],
'half_slip'         => ['en'=>'Half slip','fr'=>'Jupon','es'=>'Enagua','it'=>'Sottogonna','de'=>'Unterrock','pt'=>'Saiote','ru'=>'Нижняя юбка','ar'=>'تنورة داخلية','ja'=>'ペチコート'],
'slip'              => ['en'=>'Slip','fr'=>'Fond de robe','es'=>'Combinación','it'=>'Sottoveste','de'=>'Unterkleid','pt'=>'Combinação','ru'=>'Нижнее платье','ar'=>'قميص داخلي طويل','ja'=>'スリップ'],
'nightdress'        => ['en'=>'Nightdress','fr'=>'Chemise de nuit','es'=>'Camisón','it'=>'Camicia da notte','de'=>'Nachthemd','pt'=>'Camisa de noite','ru'=>'Ночная сорочка','ar'=>'قميص نوم','ja'=>'ナイトドレス'],
'nightwear_set'     => ['en'=>'Nightdress and robe set','fr'=>'Ensemble chemise de nuit et déshabillé','es'=>'Conjunto de camisón y bata','it'=>'Completo camicia da notte e vestaglia','de'=>'Set aus Nachthemd und Morgenmantel','pt'=>'Conjunto de camisa de noite e robe','ru'=>'Комплект: сорочка и халат','ar'=>'طقم قميص نوم وروب','ja'=>'ナイトドレス＆ガウン セット'],
'tights'            => ['en'=>'Tights','fr'=>'Collant','es'=>'Medias panty','it'=>'Collant','de'=>'Strumpfhose','pt'=>'Collants','ru'=>'Колготки','ar'=>'جوارب طويلة','ja'=>'パンティストッキング'],
'trouser_socks'     => ['en'=>'Trouser socks','fr'=>'Mi-bas','es'=>'Media pantalón','it'=>'Gambaletto','de'=>'Kniestrumpf','pt'=>'Meia até ao joelho','ru'=>'Гольфы','ar'=>'جوارب تحت الركبة','ja'=>'ハイソックス'],
'knee_highs'        => ['en'=>'Knee-high socks','fr'=>'Chaussettes montantes','es'=>'Calcetines altos','it'=>'Calzini alti','de'=>'Kniestrümpfe','pt'=>'Meias altas','ru'=>'Высокие носки','ar'=>'جوارب عالية','ja'=>'ニーハイソックス'],
'no_show_socks'     => ['en'=>'No-show socks','fr'=>'Socquettes invisibles','es'=>'Calcetines invisibles','it'=>'Fantasmini','de'=>'Füßlinge','pt'=>'Meias invisíveis','ru'=>'Следки','ar'=>'جوارب خفية','ja'=>'フットカバー'],
];

/* Ozellikler — virgulle ayrilmis liste olarak eklenir, sifat sirasi sorunu yok.
   Ceviri "sozluk karsiligi" degil, o dilin KENDI perakende terimi. */
const NBB_ATTRS = [
'combed_cotton'   => ['en'=>'combed cotton','fr'=>'coton peigné','es'=>'algodón peinado','it'=>'cotone pettinato','de'=>'gekämmte Baumwolle','pt'=>'algodão penteado','ru'=>'чесаный хлопок','ar'=>'قطن ممشط','ja'=>'コーマ綿'],
'microfibre'      => ['en'=>'microfibre','fr'=>'microfibre','es'=>'microfibra','it'=>'microfibra','de'=>'Mikrofaser','pt'=>'microfibra','ru'=>'микрофибра','ar'=>'ألياف دقيقة','ja'=>'マイクロファイバー'],
'satin'           => ['en'=>'satin','fr'=>'satin','es'=>'satén','it'=>'raso','de'=>'Satin','pt'=>'cetim','ru'=>'сатин','ar'=>'ساتان','ja'=>'サテン'],
'tactel'          => ['en'=>'Tactel','fr'=>'Tactel','es'=>'Tactel','it'=>'Tactel','de'=>'Tactel','pt'=>'Tactel','ru'=>'Tactel','ar'=>'تاكتيل','ja'=>'タクテル'],
'silicone'        => ['en'=>'silicone','fr'=>'silicone','es'=>'silicona','it'=>'silicone','de'=>'Silikon','pt'=>'silicone','ru'=>'силикон','ar'=>'سيليكون','ja'=>'シリコン'],
'seamless'        => ['en'=>'seamless','fr'=>'sans coutures','es'=>'sin costuras','it'=>'senza cuciture','de'=>'nahtlos','pt'=>'sem costuras','ru'=>'бесшовный','ar'=>'بدون خياطة','ja'=>'シームレス'],
'moulded_cup'     => ['en'=>'moulded cup','fr'=>'bonnet moulé','es'=>'copa moldeada','it'=>'coppa preformata','de'=>'vorgeformte Cups','pt'=>'taça moldada','ru'=>'формованная чашка','ar'=>'كأس مقولب','ja'=>'モールドカップ'],
'padded'          => ['en'=>'padded','fr'=>'rembourré','es'=>'con relleno','it'=>'imbottito','de'=>'wattiert','pt'=>'com enchimento','ru'=>'с пуш-ап','ar'=>'مبطن','ja'=>'パッド入り'],
'strapless'       => ['en'=>'strapless','fr'=>'sans bretelles','es'=>'sin tirantes','it'=>'senza spalline','de'=>'trägerlos','pt'=>'sem alças','ru'=>'без бретелей','ar'=>'بدون حمالات','ja'=>'ストラップレス'],
'adhesive'        => ['en'=>'self-adhesive','fr'=>'autoadhésif','es'=>'autoadhesivo','it'=>'autoadesivo','de'=>'selbstklebend','pt'=>'autoadesivo','ru'=>'самоклеящийся','ar'=>'لاصق ذاتيًا','ja'=>'粘着タイプ'],
'clear_back'      => ['en'=>'clear back','fr'=>'dos transparent','es'=>'espalda transparente','it'=>'schienale trasparente','de'=>'transparenter Rücken','pt'=>'costas transparentes','ru'=>'прозрачная спинка','ar'=>'ظهر شفاف','ja'=>'クリアバック'],
'wide_straps'     => ['en'=>'wide straps','fr'=>'bretelles larges','es'=>'tirantes anchos','it'=>'spalline larghe','de'=>'breite Träger','pt'=>'alças largas','ru'=>'широкие бретели','ar'=>'حمالات عريضة','ja'=>'太ストラップ'],
'spaghetti_straps'=> ['en'=>'spaghetti straps','fr'=>'fines bretelles','es'=>'tirantes finos','it'=>'spalline sottili','de'=>'schmale Träger','pt'=>'alças finas','ru'=>'тонкие бретели','ar'=>'حمالات رفيعة','ja'=>'細ストラップ'],
'high_waist'      => ['en'=>'high waist','fr'=>'taille haute','es'=>'talle alto','it'=>'vita alta','de'=>'hohe Taille','pt'=>'cintura subida','ru'=>'высокая посадка','ar'=>'خصر عالٍ','ja'=>'ハイウエスト'],
'plus_size'       => ['en'=>'plus size','fr'=>'grande taille','es'=>'talla grande','it'=>'taglie forti','de'=>'große Größen','pt'=>'tamanho grande','ru'=>'большие размеры','ar'=>'مقاسات كبيرة','ja'=>'大きいサイズ'],
'double_layer'    => ['en'=>'double layer','fr'=>'double épaisseur','es'=>'doble capa','it'=>'doppio strato','de'=>'doppellagig','pt'=>'camada dupla','ru'=>'двойной слой','ar'=>'طبقة مزدوجة','ja'=>'二重仕立て'],
'short_length'    => ['en'=>'short length','fr'=>'longueur courte','es'=>'largo corto','it'=>'lunghezza corta','de'=>'kurze Länge','pt'=>'comprimento curto','ru'=>'короткая длина','ar'=>'طول قصير','ja'=>'ショート丈'],
'long_length'     => ['en'=>'long length','fr'=>'longueur longue','es'=>'largo largo','it'=>'lunghezza lunga','de'=>'lange Länge','pt'=>'comprimento longo','ru'=>'длинная длина','ar'=>'طول طويل','ja'=>'ロング丈'],
'unisex'          => ['en'=>'unisex','fr'=>'mixte','es'=>'unisex','it'=>'unisex','de'=>'Unisex','pt'=>'unissexo','ru'=>'унисекс','ar'=>'للجنسين','ja'=>'ユニセックス'],
'three_pack'      => ['en'=>'three-pack','fr'=>'lot de trois','es'=>'pack de tres','it'=>'confezione da tre','de'=>'Dreierpack','pt'=>'pack de três','ru'=>'набор из трёх','ar'=>'عبوة ثلاثية','ja'=>'3枚組'],
/* Denye evrensel bir olcu birimi ama ADI cevriliyor: "15 den" Almanca'da da
   "15 den", ama "denier" kelimesinin karsiligi her dilde var ve toptanci onu
   ariyor. Rakam yer tutucudan giriyor, metne gomulu degil. */
'd15'             => ['en'=>'15 denier','fr'=>'15 deniers','es'=>'15 deniers','it'=>'15 denari','de'=>'15 den','pt'=>'15 deniers','ru'=>'15 den','ar'=>'15 دينير','ja'=>'15デニール'],
'd40'             => ['en'=>'40 denier','fr'=>'40 deniers','es'=>'40 deniers','it'=>'40 denari','de'=>'40 den','pt'=>'40 deniers','ru'=>'40 den','ar'=>'40 دينير','ja'=>'40デニール'],
];

/* Birlestirme: ozellik ayraci ve model numarasindan onceki isaret.
   Japonca'da liste ayraci "・", Arapca'da Arap virgulu "،" -- ASCII virgul
   ikisinde de yabanci duruyor. Tire her dilde ayni (U+2014). */
const NBB_JOIN = [
  'en'=>[', ', ' — '], 'fr'=>[', ', ' — '], 'es'=>[', ', ' — '], 'it'=>[', ', ' — '],
  'de'=>[', ', ' — '], 'pt'=>[', ', ' — '], 'ru'=>[', ', ' — '], 'ar'=>['، ', ' — '],
  'ja'=>['・',  ' — '],
];

/* Aciklama: adin model numarasiz hali + toptan kurali. BEDEN SERISI YOK
   (Balenciaga dersi: ayni olgu `sizes` alaninda zaten yazili ve ikisi
   ayrisiyor), GONDERIM YERI YOK (`ships_from` alaninin isi, KURAL 3). */
/* Marka adi parcanin ARDINDAN, kendi cumleciginde. Ilk yazimda "%1$s by NBB."
   idi ve ozellik listesi yuzunden yanlis okunuyordu: "Strapless bra, microfibre,
   padded by NBB" cumlesinde "by NBB" son sifata baglaniyor -- yani sutyen degil,
   DOLGU NBB'ye aitmis gibi. Ozellik listesi noktayla kapaniyor artik. */
const NBB_DESC_FMT = [
  'en'=>'%1$s. NBB, wholesale only — ordered by the pack, not by the piece.',
  'fr'=>'%1$s. NBB, vente en gros uniquement — commande par lot, non à l’unité.',
  'es'=>'%1$s. NBB, solo venta al por mayor — se pide por paquete, no por unidad.',
  'it'=>'%1$s. NBB, solo ingrosso — si ordina a confezione, non al pezzo.',
  'de'=>'%1$s. NBB, nur Großhandel — Bestellung im Pack, nicht als Einzelstück.',
  'pt'=>'%1$s. NBB, apenas venda por grosso — encomenda por pack, não à unidade.',
  'ru'=>'%1$s. NBB, только опт — заказ упаковками, не поштучно.',
  'ar'=>'%1$s. NBB، بالجملة فقط — يُطلب بالعبوة وليس بالقطعة.',
  'ja'=>'%1$s。NBB、卸売専用 — 1点単位ではなくパック単位でのご注文となります。',
];

/**
 * 47 urun. Anahtar = model numarasi (baslikta "NBB <model> ..." olarak geciyor).
 * `cat` VESTRA taksonomisinin "Underwear & Socks" grubundan (vestra_all_cats()).
 * Tur ve ozellikler URUNUN KENDI BASLIGINDAN cikarildi -- uydurulmadi; baslikta
 * olmayan bir ozellik (kumas orani, kalip) buraya YAZILMIYOR.
 */
const NBB_PRODUCTS = [
'1117'  => ['cat'=>'Bras',            'type'=>'training_bra',       'attrs'=>['combed_cotton']],
'1901'  => ['cat'=>'Shapewear',       'type'=>'shaping_brief',      'attrs'=>['silicone']],
'2006'  => ['cat'=>'Underwear',       'type'=>'brief',              'attrs'=>['tactel','seamless']],
'2007'  => ['cat'=>'Underwear',       'type'=>'shorts',             'attrs'=>['seamless']],
'2029'  => ['cat'=>'Underwear',       'type'=>'cycling_shorts',     'attrs'=>['seamless']],
'2403'  => ['cat'=>'Bras',            'type'=>'bandeau_bra',        'attrs'=>[]],
'2404'  => ['cat'=>'Bras',            'type'=>'bandeau',            'attrs'=>['strapless']],
'2410'  => ['cat'=>'Bras',            'type'=>'bandeau',            'attrs'=>['wide_straps']],
'2415'  => ['cat'=>'Bras',            'type'=>'bandeau',            'attrs'=>['spaghetti_straps']],
'2428'  => ['cat'=>'Bras',            'type'=>'wirefree_bra',       'attrs'=>['seamless']],
'2441'  => ['cat'=>'Basics',          'type'=>'vest',               'attrs'=>['wide_straps']],
'2465'  => ['cat'=>'Shapewear',       'type'=>'waist_shaper_brief', 'attrs'=>['high_waist']],
'2900'  => ['cat'=>'Lingerie',        'type'=>'half_slip',          'attrs'=>['short_length']],
'2901'  => ['cat'=>'Lingerie',        'type'=>'half_slip',          'attrs'=>['long_length']],
'30001' => ['cat'=>'Bras',            'type'=>'wirefree_bra',       'attrs'=>['strapless']],
'3050'  => ['cat'=>'Bras',            'type'=>'nursing_vest',       'attrs'=>[]],
'3212'  => ['cat'=>'Sleepwear',       'type'=>'nightwear_set',      'attrs'=>['combed_cotton']],
'3244'  => ['cat'=>'Sleepwear',       'type'=>'nightwear_set',      'attrs'=>['satin']],
'3412'  => ['cat'=>'Bras',            'type'=>'wirefree_bra',       'attrs'=>['moulded_cup']],
'350'   => ['cat'=>'Shapewear',       'type'=>'shaping_bra',        'attrs'=>['microfibre']],
'350-B' => ['cat'=>'Shapewear',       'type'=>'shaping_bra',        'attrs'=>['microfibre','plus_size']],
'3505'  => ['cat'=>'Bras',            'type'=>'wirefree_bra',       'attrs'=>['microfibre','moulded_cup']],
'351'   => ['cat'=>'Shapewear',       'type'=>'shaping_bra',        'attrs'=>['combed_cotton']],
'351-B' => ['cat'=>'Shapewear',       'type'=>'shaping_bra',        'attrs'=>['combed_cotton','plus_size']],
'3510'  => ['cat'=>'Bras',            'type'=>'wirefree_bra',       'attrs'=>['strapless']],
'3520'  => ['cat'=>'Bras',            'type'=>'strapless_bra',      'attrs'=>['microfibre','padded']],
'3525'  => ['cat'=>'Bras',            'type'=>'underwire_bra',      'attrs'=>['moulded_cup']],
'3532'  => ['cat'=>'Bras',            'type'=>'wirefree_bra',       'attrs'=>[]],
'3565'  => ['cat'=>'Shapewear',       'type'=>'shaping_bra',        'attrs'=>['microfibre','double_layer']],
'3565-B'=> ['cat'=>'Shapewear',       'type'=>'shaping_bra',        'attrs'=>['microfibre','double_layer','plus_size']],
'3581'  => ['cat'=>'Bras',            'type'=>'nursing_bra',        'attrs'=>[]],
'3586'  => ['cat'=>'Bras',            'type'=>'underwire_bra',      'attrs'=>['clear_back']],
'3596'  => ['cat'=>'Shapewear',       'type'=>'shaping_bra',        'attrs'=>[]],
'3616'  => ['cat'=>'Bras',            'type'=>'strapless_bra',      'attrs'=>['clear_back']],
'3628'  => ['cat'=>'Bras',            'type'=>'nursing_bra',        'attrs'=>['moulded_cup']],
'3666'  => ['cat'=>'Bras',            'type'=>'silicone_bra',       'attrs'=>['adhesive']],
'3851'  => ['cat'=>'Lingerie',        'type'=>'slip',               'attrs'=>['spaghetti_straps']],
'3963'  => ['cat'=>'Lingerie',        'type'=>'nightdress',         'attrs'=>[]],
'540'   => ['cat'=>'Underwear',       'type'=>'maternity_brief',    'attrs'=>[]],
'701'   => ['cat'=>'Basics',          'type'=>'mens_vest',          'attrs'=>[]],
'730'   => ['cat'=>'Underwear',       'type'=>'mens_briefs',        'attrs'=>['three_pack']],
'9001'  => ['cat'=>'Socks & Hosiery', 'type'=>'trouser_socks',      'attrs'=>['d15']],
'9083'  => ['cat'=>'Socks & Hosiery', 'type'=>'tights',             'attrs'=>['d15']],
'9152'  => ['cat'=>'Socks & Hosiery', 'type'=>'knee_highs',         'attrs'=>['d15']],
'9266'  => ['cat'=>'Socks & Hosiery', 'type'=>'trouser_socks',      'attrs'=>['d15']],
'9267'  => ['cat'=>'Socks & Hosiery', 'type'=>'no_show_socks',      'attrs'=>['unisex']],
'9293'  => ['cat'=>'Socks & Hosiery', 'type'=>'trouser_socks',      'attrs'=>['d40']],
];

/**
 * FOTOGRAFI ELENEN URUNLER — operatorun sarti: *"resmin üstünde türkce
 * ifadeler varsa koyma"* (10 Eyl 2026).
 *
 * 47 karenin HEPSI goz ile incelendi (kontakt sayfasi, `kuloglu: sheet`,
 * 10 Eyl 2026; supheli dordu 480px'te tekrar bakildi). Kaynaktan
 * cevaplanamayacak tek sorunun cevabi bu liste.
 *
 * NBB'de her urunun TEK fotografi var, yani elenen kare = YAYIMLANMAYAN ILAN.
 * Liste bu yuzden `nbb_photo_rejected()` uzerinden GONDERIM/YAZMA yolunda
 * okunuyor, bir yorumda durmuyor: elle eleme unutulur (KURAL 1h'nin dersi).
 *
 * Neden elendikleri, tek tek — "Turkce var" demek yetmez, hangi metin oldugu
 * yazilmali ki tedarikci bir gun kareyi degistirdiginde karar gozden gecirilebilsin:
 */
const NBB_PHOTO_REJECT = [
  '1117' => 'Fotograf urun degil, AMBALAJ TARAMASI: "İçinizi Rahat Etsin", '
          . '"GARSON SÜTYEN", "11-14 Yaş". (Ayrica: 11-14 yas = cocuk urunu, '
          . 'operator karari bekliyor.)',
  '2006' => 'Alt bantta Turkce: "S (36-40) - M (40-44)" + "Beyaz, siyah, ten".',
  '2007' => 'Alt bantta Turkce: "S-M-L" + "Beyaz, siyah, ten".',
  '2465' => 'Sol kenarda "Slip korse", alt bantta "Toparlayıcı, silikonlu" + '
          . '"Beyaz, siyah, ten".',
  '9267' => 'Ambalaj kartlarinda "Unisex / 2 Beden" ve "3 Beden" — "beden" Turkce.',
];

/**
 * GECEN ama not dusulen kare (elenmedi, gerekce kayda gecsin):
 *   9001 — sag altta "NBB Lingerie®" logosu. Latin harfli ve "Lingerie"
 *          Ingilizce; ustelik SATTIGIMIZ markanin kendi logosu. Operatorun
 *          sarti Turkce ifadeler; marka logosu o degil. Elemek, urunun
 *          markasini gostermeyi yasaklamak olurdu.
 */
function nbb_photo_rejected(string $model): ?string {
    return NBB_PHOTO_REJECT[strtoupper(trim($model))] ?? null;
}

/* NBB'de gecen ve kuloglu-vocab.php'de HENUZ OLMAYAN uc renk. Digerleri
   (NEON'lar dahil) orada zaten var -- ikinci bir kopya yazmak, bu deponun
   tekrar tekrar kaydettigi "ayni olgu iki yerde" hatasi olurdu. */
const NBB_EXTRA_COLORS = [
'SAHRA'    => ['en'=>'Sahara','fr'=>'Sahara','es'=>'Sahara','it'=>'Sahara','de'=>'Sahara','pt'=>'Saara','ru'=>'Песочный','ar'=>'صحراوي','ja'=>'サハラ', 'palette'=>'Beige'],
'BRONZ'    => ['en'=>'Bronze','fr'=>'Bronze','es'=>'Bronce','it'=>'Bronzo','de'=>'Bronze','pt'=>'Bronze','ru'=>'Бронзовый','ar'=>'برونزي','ja'=>'ブロンズ', 'palette'=>'Brown'],
'AÇIK GRİ' => ['en'=>'Light Grey','fr'=>'Gris clair','es'=>'Gris claro','it'=>'Grigio chiaro','de'=>'Hellgrau','pt'=>'Cinza claro','ru'=>'Светло-серый','ar'=>'رمادي فاتح','ja'=>'ライトグレー', 'palette'=>'Grey'],
];

/* ── Kurucular ────────────────────────────────────────────────────────────── */

/** "Wire-free bra, microfibre, moulded cup" — model numarasi YOK (desc bunu kullanir). */
function nbb_phrase(string $model, string $lang): string {
    $p = NBB_PRODUCTS[$model] ?? null;
    if (!$p) return '';
    $type = NBB_TYPES[$p['type']][$lang] ?? NBB_TYPES[$p['type']]['en'] ?? '';
    if ($type === '') return '';
    $bits = [];
    foreach ($p['attrs'] as $a) {
        $v = NBB_ATTRS[$a][$lang] ?? NBB_ATTRS[$a]['en'] ?? '';
        if ($v !== '') $bits[] = $v;
    }
    [$sep] = NBB_JOIN[$lang] ?? NBB_JOIN['en'];
    return $bits ? $type . $sep . implode($sep, $bits) : $type;
}

/** "Wire-free bra, microfibre, moulded cup — 3505" */
function nbb_name(string $model, string $lang): string {
    $ph = nbb_phrase($model, $lang);
    if ($ph === '') return '';
    [, $dash] = NBB_JOIN[$lang] ?? NBB_JOIN['en'];
    return $ph . $dash . $model;
}

function nbb_desc(string $model, string $lang): string {
    $ph = nbb_phrase($model, $lang);
    if ($ph === '') return '';
    $fmt = NBB_DESC_FMT[$lang] ?? NBB_DESC_FMT['en'];
    /* Cumle basi buyuk harf: parca kucuk harfle basliyor ("wire-free bra") ve
       Ingilizce/Almanca cumle basinda buyuk olmali. mb_ ile, cunku Turkce'den
       gelen bir harf ASCII olmayabilir. */
    return sprintf($fmt, mb_strtoupper(mb_substr($ph, 0, 1)) . mb_substr($ph, 1));
}

/** Tum diller icin ad haritasi: ['en'=>..., 'fr'=>..., ...] — `name_i18n` alani. */
function nbb_name_i18n(string $model): array {
    $out = [];
    foreach (array_keys(NBB_JOIN) as $lang) {
        $v = nbb_name($model, $lang);
        if ($v !== '') $out[$lang] = $v;
    }
    return $out;
}
function nbb_desc_i18n(string $model): array {
    $out = [];
    foreach (array_keys(NBB_JOIN) as $lang) {
        $v = nbb_desc($model, $lang);
        if ($v !== '') $out[$lang] = $v;
    }
    return $out;
}

/* ── Tedarikci alanlarinin temizligi ──────────────────────────────────────── */

/**
 * Tedarikci renk kodu -> renk adi. NBB'nin renk degerleri sik sik bir kod
 * tasiyor: "SİYAH-500", "TEN-57", "SAHRA-51", "BRONZ-38", "VİZON-86".
 * Sondaki "-<rakam>" ATILIR ve kalan ad aranir.
 *
 * DIKKAT — yalnizca rakam: "GÜL-KURUSU" gibi tireli GERCEK renk adlarini
 * bozmamak icin kalip `-\d+$`. Bu, bu deponun mango/zara dersinin renk hali.
 */
function nbb_color_key(string $raw): string {
    $s = trim(mb_strtoupper($raw));
    return (string)preg_replace('~-\s*\d+$~u', '', $s);
}

/**
 * Bir tedarikci degeri BEDEN mi? NBB'nin kaydinda renk ve beden alanlari
 * yer yer YER DEGISTIRMIS durumda -- olculdu, tahmin degil: 2404'un beden
 * alaninda "NEON TURUNCU", renk alaninda "M" var; 2900, 9266 ve 9293'te de
 * ayni karisiklik. Yani alanin ADINA guvenilemez, DEGERE bakmak gerekiyor.
 *
 * Beden sayilanlar: harfli merdiven (S..XXXXL), ciplak sayi (70..110, 1..5),
 * ve "80 C" gibi kupali band olculeri.
 */
function nbb_is_size(string $raw): bool {
    $s = trim(mb_strtoupper($raw));
    if ($s === '') return false;
    if (preg_match('~^(XXS|XS|S|M|L|XL|XXL|XXXL|XXXXL)$~u', $s)) return true;
    if (preg_match('~^\d{1,3}$~u', $s)) return true;              // 70..110, 1..5
    if (preg_match('~^\d{2,3}\s*[A-E]$~u', $s)) return true;       // "80 C"
    return false;
}

/**
 * "STANDART" tek beden demek, renk DEGIL -- ve tedarikci onu her iki alana da
 * yaziyor. Ayri bir kontrol, cunku nbb_is_size() yalnizca olculere bakiyor.
 */
function nbb_is_one_size(string $raw): bool {
    return in_array(trim(mb_strtoupper($raw)), ['STANDART', 'STD', 'TEK EBAT', 'TEK BEDEN'], true);
}

/* Dizi olarak da doner: workflow bu dosyayi raw.githubusercontent'ten cekip
   `require` ediyor (kuloglu-vocab.php ile ayni desen). Fonksiyonlar zaten
   require ile tanimlaniyor; bu dondurulen dizi tablolara sabit adi yazmadan
   erismek isteyen cagiran icin. */
return [
  'types'    => NBB_TYPES,
  'attrs'    => NBB_ATTRS,
  'products' => NBB_PRODUCTS,
  'reject'   => NBB_PHOTO_REJECT,
  'colors'   => NBB_EXTRA_COLORS,
];
