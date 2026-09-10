<?php
/**
 * VESTRA — Kuloglu ic camasiri katalogu: urun ADI ve ACIKLAMASI, 9 dilde.
 *
 * ── NEDEN URETICI BASINA AYRI DOSYA DEGIL ────────────────────────────────────
 * Dosya once yalniz NBB icindi (`nbb-vocab.php`). 10 Eyl 2026'da operator
 * *"ayni siteden visatin ve Q-EN markali urunleride cek"* dedi. Ikinci ve
 * ucuncu bir dosya acmak, giysi turlerinin ve ozelliklerin 9 DILLIK
 * tablosunu iki kez daha kopyalamak olurdu -- ve "Nachthemd"i bir gun
 * duzelten kisi ucunden yalniz birini duzeltirdi. Bu depo ayni hatayi
 * `desc`/`sizes`'ta, faturanin uc katmaninda ve dort mektup govdesinde
 * kaydetti. Bu yuzden KELIMELER ORTAK, yalnizca URUN TABLOSU uretici basina:
 * KU_PRODUCTS['nbb'], ['visatin'], ['qen'].
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
 *   KU_TYPES  — giysinin kendisi, dilin kendi TAM isim tamlamasi olarak
 *   KU_ATTRS  — ozellikler, VIRGULLE AYRILMIS bir liste olarak
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
const KU_TYPES = [
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
/* Visatin (saten gecelik/sabahlik) ve Q-EN (bambu ic giyim) ile gelen turler,
   10 Eyl 2026. Hepsi 114 urunun GERCEK basliklarindan cikti; kataloglarda
   olmayan bir tur buraya yazilmadi. */
'robe'              => ['en'=>'Robe','fr'=>'Peignoir','es'=>'Bata','it'=>'Vestaglia','de'=>'Morgenmantel','pt'=>'Robe','ru'=>'Халат','ar'=>'روب','ja'=>'ガウン'],
'bralette'          => ['en'=>'Bralette','fr'=>'Brassière','es'=>'Top sujetador','it'=>'Bralette','de'=>'Bralette','pt'=>'Top sutiã','ru'=>'Бралетт','ar'=>'برالیت','ja'=>'ブラレット'],
'mens_boxer'        => ['en'=>'Men’s boxer briefs','fr'=>'Boxer homme','es'=>'Bóxer de hombre','it'=>'Boxer uomo','de'=>'Herren-Boxershorts','pt'=>'Boxer de homem','ru'=>'Мужские боксеры','ar'=>'بوكسر رجالي','ja'=>'メンズ ボクサーパンツ'],
'thong'             => ['en'=>'Thong','fr'=>'String','es'=>'Tanga','it'=>'Perizoma','de'=>'String','pt'=>'Tanga','ru'=>'Стринги','ar'=>'سروال داخلي سترينغ','ja'=>'Tバック'],
'trouser_liner'     => ['en'=>'Trouser lining shorts','fr'=>'Short de doublure','es'=>'Short forro para pantalón','it'=>'Sottopantalone','de'=>'Hosen-Unterhose','pt'=>'Calção de forro','ru'=>'Шорты-подкладка','ar'=>'شورت بطانة للبنطال','ja'=>'パンツ用インナーショーツ'],
'leggings'          => ['en'=>'Leggings','fr'=>'Legging','es'=>'Leggings','it'=>'Leggings','de'=>'Leggings','pt'=>'Leggings','ru'=>'Легинсы','ar'=>'ليغنز','ja'=>'レギンス'],
'capri_leggings'    => ['en'=>'Capri leggings','fr'=>'Legging capri','es'=>'Leggings capri','it'=>'Leggings capri','de'=>'Capri-Leggings','pt'=>'Leggings capri','ru'=>'Легинсы-капри','ar'=>'ليغنز كابري','ja'=>'カプリレギンス'],
'short_leggings'    => ['en'=>'Short leggings','fr'=>'Legging court','es'=>'Leggings cortos','it'=>'Leggings corti','de'=>'Kurze Leggings','pt'=>'Leggings curtas','ru'=>'Короткие легинсы','ar'=>'ليغنز قصير','ja'=>'ショートレギンス'],
'turtleneck_top'    => ['en'=>'Roll-neck top','fr'=>'Haut à col roulé','es'=>'Top de cuello alto','it'=>'Top a collo alto','de'=>'Rollkragen-Oberteil','pt'=>'Top de gola alta','ru'=>'Топ с высоким воротом','ar'=>'توب برقبة عالية','ja'=>'タートルネック トップ'],
'crew_neck_top'     => ['en'=>'Crew-neck top','fr'=>'Haut col rond','es'=>'Top de cuello redondo','it'=>'Top girocollo','de'=>'Rundhals-Oberteil','pt'=>'Top de gola redonda','ru'=>'Топ с круглым вырезом','ar'=>'توب برقبة دائرية','ja'=>'クルーネック トップ'],
'long_sleeve_top'   => ['en'=>'Long-sleeve top','fr'=>'Haut à manches longues','es'=>'Top de manga larga','it'=>'Top a maniche lunghe','de'=>'Langarm-Oberteil','pt'=>'Top de manga comprida','ru'=>'Топ с длинным рукавом','ar'=>'توب بأكمام طويلة','ja'=>'長袖トップ'],
];

/* Ozellikler — virgulle ayrilmis liste olarak eklenir, sifat sirasi sorunu yok.
   Ceviri "sozluk karsiligi" degil, o dilin KENDI perakende terimi. */
const KU_ATTRS = [
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
'midi_length'     => ['en'=>'midi length','fr'=>'longueur midi','es'=>'largo midi','it'=>'lunghezza midi','de'=>'Midi-Länge','pt'=>'comprimento midi','ru'=>'длина миди','ar'=>'طول ميدي','ja'=>'ミディ丈'],
'short_length'    => ['en'=>'short length','fr'=>'longueur courte','es'=>'largo corto','it'=>'lunghezza corta','de'=>'kurze Länge','pt'=>'comprimento curto','ru'=>'короткая длина','ar'=>'طول قصير','ja'=>'ショート丈'],
'long_length'     => ['en'=>'long length','fr'=>'longueur longue','es'=>'largo largo','it'=>'lunghezza lunga','de'=>'lange Länge','pt'=>'comprimento longo','ru'=>'длинная длина','ar'=>'طول طويل','ja'=>'ロング丈'],
'unisex'          => ['en'=>'unisex','fr'=>'mixte','es'=>'unisex','it'=>'unisex','de'=>'Unisex','pt'=>'unissexo','ru'=>'унисекс','ar'=>'للجنسين','ja'=>'ユニセックス'],
'three_pack'      => ['en'=>'three-pack','fr'=>'lot de trois','es'=>'pack de tres','it'=>'confezione da tre','de'=>'Dreierpack','pt'=>'pack de três','ru'=>'набор из трёх','ar'=>'عبوة ثلاثية','ja'=>'3枚組'],
/* Denye evrensel bir olcu birimi ama ADI cevriliyor: "15 den" Almanca'da da
   "15 den", ama "denier" kelimesinin karsiligi her dilde var ve toptanci onu
   ariyor. Rakam yer tutucudan giriyor, metne gomulu degil. */
'd15'             => ['en'=>'15 denier','fr'=>'15 deniers','es'=>'15 deniers','it'=>'15 denari','de'=>'15 den','pt'=>'15 deniers','ru'=>'15 den','ar'=>'15 دينير','ja'=>'15デニール'],
'd40'             => ['en'=>'40 denier','fr'=>'40 deniers','es'=>'40 deniers','it'=>'40 denari','de'=>'40 den','pt'=>'40 deniers','ru'=>'40 den','ar'=>'40 دينير','ja'=>'40デニール'],
/* Visatin + Q-EN ile gelen ozellikler, 10 Eyl 2026. */
'bamboo'          => ['en'=>'bamboo viscose','fr'=>'viscose de bambou','es'=>'viscosa de bambú','it'=>'viscosa di bambù','de'=>'Bambusviskose','pt'=>'viscose de bambu','ru'=>'бамбуковая вискоза','ar'=>'فيسكوز الخيزران','ja'=>'竹レーヨン'],
'reversible'      => ['en'=>'reversible','fr'=>'réversible','es'=>'reversible','it'=>'reversibile','de'=>'wendbar','pt'=>'reversível','ru'=>'двусторонний','ar'=>'قابل للعكس','ja'=>'リバーシブル'],
'sleeveless'      => ['en'=>'sleeveless','fr'=>'sans manches','es'=>'sin mangas','it'=>'senza maniche','de'=>'ärmellos','pt'=>'sem mangas','ru'=>'без рукавов','ar'=>'بدون أكمام','ja'=>'ノースリーブ'],
'long_sleeve'     => ['en'=>'long sleeves','fr'=>'manches longues','es'=>'manga larga','it'=>'maniche lunghe','de'=>'lange Ärmel','pt'=>'manga comprida','ru'=>'длинный рукав','ar'=>'أكمام طويلة','ja'=>'長袖'],
'short_sleeve'    => ['en'=>'short sleeves','fr'=>'manches courtes','es'=>'manga corta','it'=>'maniche corte','de'=>'kurze Ärmel','pt'=>'manga curta','ru'=>'короткий рукав','ar'=>'أكمام قصيرة','ja'=>'半袖'],
'adjustable_straps'=>['en'=>'adjustable straps','fr'=>'bretelles réglables','es'=>'tirantes ajustables','it'=>'spalline regolabili','de'=>'verstellbare Träger','pt'=>'alças ajustáveis','ru'=>'регулируемые бретели','ar'=>'حمالات قابلة للتعديل','ja'=>'アジャスター付きストラップ'],
'elastic_waist'   => ['en'=>'elasticated waist','fr'=>'taille élastiquée','es'=>'cintura elástica','it'=>'vita elasticizzata','de'=>'Gummibund','pt'=>'cintura elástica','ru'=>'эластичный пояс','ar'=>'خصر مطاطي','ja'=>'ゴムウエスト'],
'two_pack'        => ['en'=>'two-pack','fr'=>'lot de deux','es'=>'pack de dos','it'=>'confezione da due','de'=>'Doppelpack','pt'=>'pack de dois','ru'=>'набор из двух','ar'=>'عبوة ثنائية','ja'=>'2枚組'],
/* "ASORTİ": tedarikcinin renk alanina yazdigi, renk OLMAYAN deger. Rengi
   uydurmak yerine ozellik olarak yaziliyor -- alici karisik gelecegini bilsin
   (NBB 730'da renk hic verilmemisti ve fotograftan renk UYDURULMADI). */
'assorted_colours'=> ['en'=>'assorted colours','fr'=>'coloris assortis','es'=>'colores surtidos','it'=>'colori assortiti','de'=>'sortierte Farben','pt'=>'cores sortidas','ru'=>'ассорти цветов','ar'=>'ألوان متنوعة','ja'=>'アソートカラー'],
];

/* Birlestirme: ozellik ayraci ve model numarasindan onceki isaret.
   Japonca'da liste ayraci "・", Arapca'da Arap virgulu "،" -- ASCII virgul
   ikisinde de yabanci duruyor. Tire her dilde ayni (U+2014). */
const KU_JOIN = [
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
const KU_DESC_FMT = [
  'en'=>'%1$s. %2$s, wholesale only — ordered by the pack, not by the piece.',
  'fr'=>'%1$s. %2$s, vente en gros uniquement — commande par lot, non à l’unité.',
  'es'=>'%1$s. %2$s, solo venta al por mayor — se pide por paquete, no por unidad.',
  'it'=>'%1$s. %2$s, solo ingrosso — si ordina a confezione, non al pezzo.',
  'de'=>'%1$s. %2$s, nur Großhandel — Bestellung im Pack, nicht als Einzelstück.',
  'pt'=>'%1$s. %2$s, apenas venda por grosso — encomenda por pack, não à unidade.',
  'ru'=>'%1$s. %2$s, только опт — заказ упаковками, не поштучно.',
  'ar'=>'%1$s. %2$s، بالجملة فقط — يُطلب بالعبوة وليس بالقطعة.',
  'ja'=>'%1$s。%2$s、卸売専用 — 1点単位ではなくパック単位でのご注文となります。',
];

/**
 * PAKETI OLMAYAN urunun aciklamasi — paket cumlesi YOK.
 *
 * Ilk yazimda tek metin vardi ve *"paket halinde, tek parca degil"* diyordu.
 * Sunucudaki gercek kayit bunu YALANLADI: corap satirlari 6/12/24'luk paketli
 * ama korsaj/sutyen/gecelik satirlarinin `pack_qty` degeri 1. Yani aciklama
 * ilanin kendi MOQ'suyla celisen bir kural ilan ediyordu.
 *
 * Bu, bu deponun 9 Eyl 2026'da Balenciaga'da odedigi hatanin ta kendisi:
 * `sizes` alani bir sey diyor, `desc` metni baska bir sey, ayni sayfada.
 * *Bir olguyu yazmadan once "bu bilgi baska nerede yazili?" diye sor.*
 *
 * UYDURULMUS BIR PAKET ADEDI COZUM DEGIL (KURAL 3): tedarikci 1 diyorsa 1'dir;
 * "herhalde 6'lidir" demek tam olarak yasak olan tahmindir. Toptan sarti zaten
 * marka basina asgari sepet tutariyla (KURAL 21, 500 EUR) tutuluyor, tek
 * ilanin adediyle degil.
 */
const KU_DESC_FMT_NOPACK = [
  'en'=>'%1$s. %2$s, wholesale only.',
  'fr'=>'%1$s. %2$s, vente en gros uniquement.',
  'es'=>'%1$s. %2$s, solo venta al por mayor.',
  'it'=>'%1$s. %2$s, solo ingrosso.',
  'de'=>'%1$s. %2$s, nur Großhandel.',
  'pt'=>'%1$s. %2$s, apenas venda por grosso.',
  'ru'=>'%1$s. %2$s, только опт.',
  'ar'=>'%1$s. %2$s، بالجملة فقط.',
  'ja'=>'%1$s。%2$s、卸売専用。',
];

/**
 * 47 urun. Anahtar = model numarasi (baslikta "NBB <model> ..." olarak geciyor).
 * `cat` VESTRA taksonomisinin "Underwear & Socks" grubundan (vestra_all_cats()).
 * Tur ve ozellikler URUNUN KENDI BASLIGINDAN cikarildi -- uydurulmadi; baslikta
 * olmayan bir ozellik (kumas orani, kalip) buraya YAZILMIYOR.
 */
const KU_NBB_PRODUCTS = [
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
 * Liste bu yuzden `ku_photo_rejected()` uzerinden GONDERIM/YAZMA yolunda
 * okunuyor, bir yorumda durmuyor: elle eleme unutulur (KURAL 1h'nin dersi).
 *
 * Neden elendikleri, tek tek — "Turkce var" demek yetmez, hangi metin oldugu
 * yazilmali ki tedarikci bir gun kareyi degistirdiginde karar gozden gecirilebilsin:
 */
const KU_NBB_PHOTO_REJECT = [
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
function ku_photo_rejected(string $maker, string $model): ?string {
    return (KU_PHOTO_REJECT[$maker] ?? [])[strtoupper(trim($model))] ?? null;
}

/* ── URETICILER ──────────────────────────────────────────────────────────────
 *
 * `slug` = tedarikci sitesindeki urun adresinin oneki, `label` = VESTRA'da
 * basilacak marka adi, `id` = ilan id'sinin oneki.
 *
 * VISATIN'DE BIR CELISKI VAR ve uydurulmadan kayda geciyor: urun sayfasinin
 * MARKA alani "REAL PASSIONE" diyor, BASLIK "VİSATİN <model>" diyor ve
 * /marka/visatin diye bir sayfa YOK (marka dizininde de gecmiyor). Yani
 * tedarikcinin kendi kaydinda Visatin tescilli bir marka degil, Real
 * Passione'nin bir hatti gorunumunde. Operator *"visatin markali urunler"*
 * dedigi ve baslik da oyle dedigi icin VESTRA'da marka **Visatin** basiliyor;
 * celiski operator karari icin CLAUDE.md'ye yaziliyor.
 */
const KU_MAKERS = [
  'nbb'     => ['label'=>'NBB',     'slug'=>'nbb',     'id'=>'nbb'],
  'visatin' => ['label'=>'Visatin', 'slug'=>'visatin', 'id'=>'visatin'],
  'qen'     => ['label'=>'Q-EN',    'slug'=>'q-en',    'id'=>'qen'],
];

/* Urun tablosu URETICI BASINA. Kelimeler (KU_TYPES/KU_ATTRS) ortak. */
const KU_PRODUCTS = [
  'nbb' => KU_NBB_PRODUCTS,
  /* Visatin ve Q-EN'de elle yazilmis satir YOK: siniflandirma tedarikcinin
     KENDI BASLIGINDAN kural tablosuyla cikiyor (asagida). 114 satiri elle
     yazmak, 114 model numarasini elle kopyalamak demekti -- ve Actions
     kutugu "22"yi maskeledigi icin numaralarin bir kismini dogru okumak
     mumkun bile degildi. Kural tablosu hem daha kucuk hem yanlis
     kopyalanamaz. */
  'visatin' => [],
  /* Q-EN'de TEK elle yazilmis satir. Tedarikcinin kendi basligi EKSIK:
     "Q-EN 706 BAYAN BAMBU BATTAL KALIN ASKI ÇİFT YÖNLÜ" -- giysinin adi
     (ATLET) hic yazmiyor, o yuzden kural tablosu ONU COZEMIYOR ve dogrusu
     bu (cozemedigine bir tur atamak KURAL 3'un katalog hali olurdu).
     Giysi TAHMIN EDILMEDI: ayni tedarikcinin ayni model numarali kardes
     ilani "Q-EN 706 BAYAN BAMBU KALIN ASKI ÇİFT YÖNLÜ **ATLET**" diyor.
     Yani tur, tedarikcinin kendi kaydindan okundu. */
  'qen'     => [
    '706-B' => ['cat'=>'Basics', 'type'=>'vest',
                'attrs'=>['bamboo','plus_size','wide_straps','reversible']],
  ],
];
const KU_PHOTO_REJECT = [
  'nbb'     => KU_NBB_PHOTO_REJECT,
  /* 114 karenin HEPSI goz ile incelendi (kontakt sayfasi, `kuloglu: sheet`,
     10 Eyl 2026; supheli olan her kare 400-470px'te tekrar okundu). Gerekce
     tek tek yaziliyor -- "Turkce var" demek yetmez: tedarikci bir gun kareyi
     degistirdiginde karar gozden gecirilebilsin.

     GECEN ama not dusulen kalip: Visatin'in her karesinde pembe bir bant var
     ve uzerinde "VISATIN / Lingerie / CODE <no>" yaziyor -- Latin harfli,
     Ingilizce, ve SATTIGIMIZ markanin kendi logosu. NBB 9001'in ("NBB
     Lingerie®") aynisi; elemek, urunun markasini gostermeyi yasaklamak olurdu. */
  'visatin' => [
    '13100' => 'Fotograf urun degil, AMBALAJ ACILIMI (kutu flat): sol panelde '
             . '"ÖNEMLİ!" basligiyla Turkce paragraf, altinda "Made in Türkiye".',
    '13101' => 'Ayni ambalaj acilimi: "ÖNEMLİ!" + "Made in Türkiye".',
    '13102' => 'Ayni ambalaj acilimi: "ÖNEMLİ!" + "Made in Türkiye".',
    '13106' => 'Ayni ambalaj acilimi: "ÖNEMLİ!" + "Made in Türkiye".',
  ],
  /* Q-EN kartlari kendi basina Ingilizce (CODE / SIZE / BIG SIZE / COLORS /
     BAMBOO / "IN PACK 3 PIECES - PANTIES"). Elenenler o kaliptan SAPANLAR. */
  'qen'     => [
    '300'   => 'Pembe bantta "JÜPON - UNDERSKIRT" -- "JÜPON" Turkce ve kareyi '
             . 'basliklandiran yerde duruyor.',
    '707'   => 'Kirmizi okla isaretlenmis "PETLİ" (Turkce, noktali İ ile).',
    '707-B' => 'Kirmizi okla isaretlenmis "PETLİ".',
    '708'   => 'Kirmizi okla isaretlenmis "PETLİ".',
    '708-B' => 'Kirmizi okla isaretlenmis "PETLİ".',
    '711'   => 'Kirmizi okla isaretlenmis "PETLİ".',
  ],
];

/* ── BASLIKTAN SINIFLANDIRMA ─────────────────────────────────────────────────
 *
 * Tedarikcinin Turkce basligi duzenli ve bilesik: "Q-EN 712 BAYAN BAMBU
 * BALIKÇI YAKA UZUNKOL ATLET". Tur ve ozellikler oradan cikiyor.
 *
 * KURAL: COZULEMEYEN BASLIK ATLANIR. Bir varsayilan tur koymak, tanimadigimiz
 * bir giysiye tanidigimiz bir ad vermek olurdu -- KURAL 3'un katalog hali.
 *
 * Sira ONEMLI: ilk esleseme gore karar veriliyor, en OZEL kalip once. Ornegin
 * "PANTOLON ASTARI" once gelmeli, yoksa "ASTAR" iceren bir baslik baska bir
 * kolda yakalanabilir; "STRİNG KÜLOT" once gelmeli, yoksa "KÜLOT" onu yer.
 */
const KU_TITLE_TYPES = [
  ['~SABAHLIK~u',                        'robe'],
  ['~GECEL[İI]K~u',                      'nightdress'],
  ['~KOMB[İI]NEZON~u',                   'slip'],
  ['~J[İI]PON~u',                        'half_slip'],
  ['~PANTOLON\s+ASTARI~u',               'trouser_liner'],
  ['~STR[İI]NG~u',                       'thong'],
  ['~K[ÜU]LOT~u',                        'brief'],
  ['~ERKEK\b.*\bBOXER~u',                'mens_boxer'],
  ['~BOXER~u',                           'shorts'],
  ['~B[ÜU]ST[İI]YER~u',                  'bralette'],
  ['~KAPR[İI]\s+TAYT~u',                 'capri_leggings'],
  ['~(?:KISA|M[İI]N[İI])\s+TAYT~u',      'short_leggings'],
  ['~TAYT~u',                            'leggings'],
  ['~BALIK[ÇC]I\s+YAKA~u',               'turtleneck_top'],
  ['~B[İI]S[İI]KLET\s+YAKA~u',           'crew_neck_top'],
  ['~SIFIR\s+YAKA~u',                    'long_sleeve_top'],
  ['~ERKEK\b.*\bATLET~u',                'mens_vest'],
  ['~ATLET|FAN[İI]LA~u',                 'vest'],
];

/* Ozellikler: HEPSI toplanir, tablodaki sirayla basilir (sira, cumlenin
   okunusudur: once kumas, sonra kalip, sonra detay, en sonda paket). */
const KU_TITLE_ATTRS = [
  ['~BAMBU~u',                       'bamboo'],
  ['~SATEN~u',                       'satin'],
  ['~BATTAL~u',                      'plus_size'],
  ['~AYAR\s+ASKI~u',                 'adjustable_straps'],
  ['~[İI]P\s+ASKI~u',                'spaghetti_straps'],
  ['~KALIN\s+ASKI~u',                'wide_straps'],
  ['~PEDL[İI]|KABLI~u',              'padded'],
  ['~[ÇC][İI]FT\s+Y[ÖO]N~u',         'reversible'],
  ['~SIFIR\s*KOL~u',                 'sleeveless'],
  ['~UZUN\s*KOL~u',                  'long_sleeve'],
  ['~KISA\s*KOL~u',                  'short_sleeve'],
  ['~GE[ÇC]ME\s+BEL~u',              'elastic_waist'],
  ['~\bMAX[İI]\b|\bUZUN\b(?!\s*KOL)~u','long_length'],
  ['~\bM[İI]N[İI]\b~u',              'short_length'],
  ['~\bM[İI]D[İI]\b~u',              'midi_length'],
  ['~ASORT[İI]~u',                   'assorted_colours'],
  ['~\b2\s*L[İI]\b~u',               'two_pack'],
  ['~\b3\s*L[ÜU]\b~u',               'three_pack'],
];

/* Tur -> VESTRA taksonomisi yapragi (vestra_all_cats(), "Underwear & Socks").
   Her turun TEK kategorisi var: ayni giysiyi iki kategoriye koymak, katalogda
   ayni urunu iki yerde gostermek olurdu. */
const KU_TYPE_CAT = [
  'robe'            => 'Loungewear',
  'nightdress'      => 'Sleepwear',
  'nightwear_set'   => 'Sleepwear',
  'slip'            => 'Lingerie',
  'half_slip'       => 'Lingerie',
  'trouser_liner'   => 'Underwear',
  'thong'           => 'Underwear',
  'brief'           => 'Underwear',
  'mens_boxer'      => 'Underwear',
  'shorts'          => 'Underwear',
  'bralette'        => 'Bras',
  'capri_leggings'  => 'Basics',
  'short_leggings'  => 'Basics',
  'leggings'        => 'Basics',
  'turtleneck_top'  => 'Basics',
  'crew_neck_top'   => 'Basics',
  'long_sleeve_top' => 'Basics',
  'mens_vest'       => 'Basics',
  'vest'            => 'Basics',
];

/**
 * Baslik -> ['type'=>..., 'attrs'=>[...], 'cat'=>...] ya da COZULEMEDIYSE null.
 * Elle yazilmis KU_PRODUCTS satiri varsa O KAZANIR: bir gun bir urun yanlis
 * siniflanirsa duzeltmenin yeri tablodur, kuralin kendisi degil.
 */
function ku_classify(string $maker, string $sku, string $title): ?array {
    $hand = KU_PRODUCTS[$maker][$sku] ?? null;
    if ($hand) return $hand;
    $t = mb_strtoupper(trim($title));
    if ($t === '') return null;
    $type = null;
    foreach (KU_TITLE_TYPES as [$re, $key]) { if (preg_match($re, $t)) { $type = $key; break; } }
    if ($type === null) return null;
    $attrs = [];
    foreach (KU_TITLE_ATTRS as [$re, $key]) { if (preg_match($re, $t)) $attrs[] = $key; }
    /* Turun kendisi zaten soyluyorsa ozellik olarak TEKRAR yazilmaz:
       "Long-sleeve top, long sleeves" gulunc olurdu. */
    $implied = [
      'long_sleeve_top' => ['long_sleeve'],
      'capri_leggings'  => ['short_length','long_length'],
      'short_leggings'  => ['short_length','long_length'],
      'leggings'        => ['long_length'],
      'thong'           => ['short_length'],
    ][$type] ?? [];
    $attrs = array_values(array_diff($attrs, $implied));
    $cat = KU_TYPE_CAT[$type] ?? null;
    if ($cat === null) return null;
    return ['cat'=>$cat, 'type'=>$type, 'attrs'=>$attrs];
}

/**
 * ILAN ANAHTARI (SKU). Model numarasi TEK BASINA yetmiyor: tedarikci ayni
 * artikel numarasini iki ayri urunde kullaniyor ve olculdu --
 * Q-EN 705/706/800 hem normal hem BATTAL hatti olarak, Visatin 4060/4074 hem
 * tek hem 6'li asorti paket olarak listeleniyor. Ayni SKU iki ilanda
 * durursa siparis satirinin renk/beden haritasi (SKU ile anahtarli) iki
 * ilani birbirine karistirir.
 *
 * Iki ek de UYDURMA DEGIL, tedarikcinin kendi verisinden:
 *  -B  : tedarikcinin KENDI konvansiyonu (700-B, 707-B, 708-B, 802-B, 804-B
 *        slug'da boyle yaziyor); bazi satirlarda yazmayi atlamis.
 *  -N  : kaydin kendi `pack_qty` degeri, yani "6'li asorti" olan ilan.
 */
function ku_sku(string $model, string $title, int $packQty, bool $ambiguous): string {
    $s = strtoupper(trim($model));
    if ($s === '') return '';
    /* $ambiguous: bu model numarasi kumede BIRDEN FAZLA urunde geciyor mu.
       Ek YALNIZCA o zaman basiliyor. Kosulsuz eklemek, tek basina duran bir
       urune tedarikcinin hic yazmadigi bir sonek uydurmak olurdu: Visatin'in
       BATTAL geceliklerinin cogunun ikizi YOK ve artikel numaralari duz
       "10009". SKU alicinin gordugu uretici referansi; olmayan bir sonek
       eklemek onu yanlis bir numaraya bakmaya yollar (KURAL 3). */
    if (!$ambiguous) return $s;
    if (preg_match('~BATTAL~u', mb_strtoupper($title)) && !preg_match('~-B$~', $s)) $s .= '-B';
    if ($packQty > 1) $s .= '-' . $packQty;
    return $s;
}

/**
 * Baslikta yazan BEDEN LISTESI — "... GECELİK M-L-XL 6 LI" gibi.
 * Yalnizca varyant tablosu BOS oldugunda kullanilir: tedarikcinin 6'li asorti
 * paketlerinde varyant hic yok ve bedenler yalniz baslikta duruyor. Bulunan
 * her parca gercek bir beden olmak zorunda (ku_is_size), yoksa "M-L-XL"
 * benzeri her tireli dizge beden sanilirdi.
 */
function ku_sizes_from_title(string $title): array {
    $t = mb_strtoupper(trim($title));
    if (!preg_match('~\b([A-Z0-9]{1,4}(?:-[A-Z0-9]{1,4})+)\b~u', $t, $m)) return [];
    $parts = explode('-', $m[1]);
    if (count($parts) < 2) return [];
    foreach ($parts as $p) if (!ku_is_size($p)) return [];
    return array_values(array_unique($parts));
}

/* NBB'de gecen ve kuloglu-vocab.php'de HENUZ OLMAYAN uc renk. Digerleri
   (NEON'lar dahil) orada zaten var -- ikinci bir kopya yazmak, bu deponun
   tekrar tekrar kaydettigi "ayni olgu iki yerde" hatasi olurdu. */
const KU_EXTRA_COLORS = [
'SAHRA'    => ['en'=>'Sahara','fr'=>'Sahara','es'=>'Sahara','it'=>'Sahara','de'=>'Sahara','pt'=>'Saara','ru'=>'Песочный','ar'=>'صحراوي','ja'=>'サハラ', 'palette'=>'Beige'],
'BRONZ'    => ['en'=>'Bronze','fr'=>'Bronze','es'=>'Bronce','it'=>'Bronzo','de'=>'Bronze','pt'=>'Bronze','ru'=>'Бронзовый','ar'=>'برونزي','ja'=>'ブロンズ', 'palette'=>'Brown'],
'AÇIK GRİ' => ['en'=>'Light Grey','fr'=>'Gris clair','es'=>'Gris claro','it'=>'Grigio chiaro','de'=>'Hellgrau','pt'=>'Cinza claro','ru'=>'Светло-серый','ar'=>'رمادي فاتح','ja'=>'ライトグレー', 'palette'=>'Grey'],
];

/* ── Kurucular ────────────────────────────────────────────────────────────── */

/**
 * "Wire-free bra, microfibre, moulded cup" — model numarasi YOK (desc bunu
 * kullanir). $spec, ku_classify()'in dondurdugu ['type'=>..,'attrs'=>[..]].
 * Siniflandirmayi CAGIRAN yapiyor: kurucu ne elle yazilmis tabloyu ne de
 * baslik kurallarini ikinci kez sormali -- ayni olguyu iki yerden okumak,
 * bu deponun tekrar tekrar odedigi hata.
 */
function ku_phrase(array $spec, string $lang): string {
    $type = KU_TYPES[$spec['type']][$lang] ?? KU_TYPES[$spec['type']]['en'] ?? '';
    if ($type === '') return '';
    $bits = [];
    foreach ($spec['attrs'] ?? [] as $a) {
        $v = KU_ATTRS[$a][$lang] ?? KU_ATTRS[$a]['en'] ?? '';
        if ($v !== '') $bits[] = $v;
    }
    [$sep] = KU_JOIN[$lang] ?? KU_JOIN['en'];
    return $bits ? $type . $sep . implode($sep, $bits) : $type;
}

/** "Wire-free bra, microfibre, moulded cup — 3505" */
function ku_name(array $spec, string $model, string $lang): string {
    $ph = ku_phrase($spec, $lang);
    if ($ph === '') return '';
    [, $dash] = KU_JOIN[$lang] ?? KU_JOIN['en'];
    return $ph . $dash . $model;
}

/* $packQty: ilanin GERCEK paket adedi. 1 (ya da verilmemis) ise paket cumlesi
   BASILMAZ -- aciklama, ilanin MOQ'sunun soylemedigi bir kurali ilan etmez.
   $brand: marka adi metne GOMULU DEGIL, parametre -- dosya uc uretici birden
   tasiyor ve 18 cumleyi uc kez kopyalamak, "NBB" yazan bir Visatin ilani
   uretmenin en kolay yoluydu. */
function ku_desc(array $spec, string $brand, string $lang, int $packQty = 1): string {
    $ph = ku_phrase($spec, $lang);
    if ($ph === '') return '';
    $tbl = $packQty > 1 ? KU_DESC_FMT : KU_DESC_FMT_NOPACK;
    $fmt = $tbl[$lang] ?? $tbl['en'];
    /* Cumle basi buyuk harf: parca kucuk harfle basliyor ("wire-free bra") ve
       Ingilizce/Almanca cumle basinda buyuk olmali. mb_ ile, cunku Turkce'den
       gelen bir harf ASCII olmayabilir. */
    return sprintf($fmt, mb_strtoupper(mb_substr($ph, 0, 1)) . mb_substr($ph, 1), $brand);
}

/** Tum diller icin ad haritasi: ['en'=>..., 'fr'=>..., ...] — `name_i18n` alani. */
function ku_name_i18n(array $spec, string $model): array {
    $out = [];
    foreach (array_keys(KU_JOIN) as $lang) {
        $v = ku_name($spec, $model, $lang);
        if ($v !== '') $out[$lang] = $v;
    }
    return $out;
}
function ku_desc_i18n(array $spec, string $brand, int $packQty = 1): array {
    $out = [];
    foreach (array_keys(KU_JOIN) as $lang) {
        $v = ku_desc($spec, $brand, $lang, $packQty);
        if ($v !== '') $out[$lang] = $v;
    }
    return $out;
}

/**
 * Slug'dan MODEL numarasi. Onek uretici tablosundan geliyor:
 * "nbb-3565-b-bayan-..." -> "3565-B", "q-en-707-b-bayan-..." -> "707-B",
 * "visatin-11006-saten-..." -> "11006". "-b" soneki opsiyonel ve ARDINDAN
 * TIRE gelmek zorunda; aksi halde "nbb-350-bayan"in "-b"si model numarasina
 * yapisirdi (mango/zara dersi).
 */
function ku_model_of_slug(string $maker, string $slug): string {
    $pre = KU_MAKERS[$maker]['slug'] ?? '';
    if ($pre === '') return '';
    if (preg_match('~^' . preg_quote($pre, '~') . '-(\d+(?:-b)?)-~i', $slug, $m)) return strtoupper($m[1]);
    return '';
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
function ku_color_key(string $raw): string {
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
function ku_is_size(string $raw): bool {
    $s = trim(mb_strtoupper($raw));
    if ($s === '') return false;
    if (preg_match('~^(XXS|XS|S|M|L|XL|XXL|XXXL|XXXXL)$~u', $s)) return true;
    if (preg_match('~^\d{1,3}$~u', $s)) return true;              // 70..110, 1..5
    if (preg_match('~^\d{2,3}\s*[A-E]$~u', $s)) return true;       // "80 C"
    return false;
}

/**
 * "STANDART" tek beden demek, renk DEGIL -- ve tedarikci onu her iki alana da
 * yaziyor. Ayri bir kontrol, cunku ku_is_size() yalnizca olculere bakiyor.
 */
function ku_is_one_size(string $raw): bool {
    return in_array(trim(mb_strtoupper($raw)), ['STANDART', 'STD', 'TEK EBAT', 'TEK BEDEN'], true);
}

/* Dizi olarak da doner: workflow bu dosyayi raw.githubusercontent'ten cekip
   `require` ediyor (kuloglu-vocab.php ile ayni desen). Fonksiyonlar zaten
   require ile tanimlaniyor; bu dondurulen dizi tablolara sabit adi yazmadan
   erismek isteyen cagiran icin. */
return [
  'makers'   => KU_MAKERS,
  'types'    => KU_TYPES,
  'attrs'    => KU_ATTRS,
  'products' => KU_PRODUCTS,
  'reject'   => KU_PHOTO_REJECT,
  'colors'   => KU_EXTRA_COLORS,
];
