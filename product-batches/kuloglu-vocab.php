<?php
/**
 * VESTRA — Kuloğlu import: Turkish → 8-language translation dictionary.
 *
 * NOT 638 unique translation jobs. The crawled data has no free-text description
 * field and titles are formulaic (BRAND + model number + a handful of recurring
 * descriptive words), so the actual translation surface is this finite, recurring
 * vocabulary — built from `kuloglu: "vocab"` output (4 Sep 2026) against all 638
 * crawled Bayan Sütyen records.
 *
 * IMPORTANT — checked against product.php after this file's first draft: a
 * VESTRA listing's `name`/`desc` are NEVER run through t() anywhere they're
 * printed, so there is nowhere in this codebase for a "translated title" to
 * actually display — titles get written ONCE, in English (same as the
 * footwear/Pili Pérez import before this one), built from KULOGLU_WORDS for
 * the translated title text. Category and colour ARE genuinely multi-language
 * (both pass through t()), so KULOGLU_CATEGORIES/KULOGLU_COLORS below still do
 * real work — colour additionally needs KULOGLU_COLOR_PALETTE_MAP (bottom of
 * this file) to land on a name product.php's swatch renderer will actually
 * recognise; see that table's own docblock for why a full 8-language colour
 * name isn't sufficient by itself.
 *
 * Four tables:
 *   KULOGLU_CATEGORIES    — Kuloğlu's own 7 sub-category labels ("Kategori"),
 *                            each mapped to a VESTRA taxonomy leaf too
 *   KULOGLU_COLORS        — every distinct colour word in 8 languages, merged
 *                            from BOTH the real "colors" list AND the "sizes"
 *                            list (Kuloğlu's own site data puts colour names
 *                            in the size/BEDEN field for some products — a
 *                            source data quirk, not a crawler bug; same
 *                            colour needs the same translation either way)
 *   KULOGLU_WORDS          — recurring descriptive title words (for building
 *                            the one English title), brand/model numbers and
 *                            the brand name itself already stripped by scan
 *   KULOGLU_COLOR_PALETTE_MAP — Turkish colour -> the one vestra_colors() name
 *                            an import row's `colors` field should actually
 *                            hold (see its own docblock, bottom of file)
 *
 * Deliberately NOT translated (left out of KULOGLU_WORDS on purpose, not missed):
 *   - Cup-size letters A/B/C/D — universal across every language here, "C KALIP"
 *     already reads as "C-cup fit" once KALIP translates
 *   - Already-English retail loanwords used as-is in the Turkish titles too
 *     (BRA, CROP, CUP, SOFT, MİCRO/MİKRO's root, DOUBLE, PUSH-UP, MİNİMİZER,
 *     BASIC, BODY, HALTER, BIG, SIZE, EKSTRA, MODAL) — keeping the SAME English
 *     word in the English column and a light localisation elsewhere is correct;
 *     inventing a "translation" for a word that's already borrowed would be a
 *     regression, not an improvement
 *   - Brand-name fragments the word-tokeniser split apart, not real vocabulary:
 *     LE / JARDİN (from the brand "LEJARDİN"), BEST (from "BEST BRA"), R.Y
 *   - Proper nouns / model-collection names, not descriptive words: LOTUS,
 *     BELLA, MASSIMA, NOA&NOA, PAPATYA (used here as a lace-pattern name, not
 *     literally "daisy"), SURDANTEL
 *   - Low-frequency, ambiguous-without-visual-context fragments (CUB, ST, MY,
 *     TULUMLU): left untranslated rather than guessed — a wrong translation is
 *     worse than an English fallback on a handful of titles
 * Turkish source strings are kept UPPERCASE (Kuloğlu's own site casing) as map
 * keys; consumers should uppercase their lookup before indexing.
 */

const KULOGLU_CATEGORIES = [
  // Kuloğlu's finer wired/wireless/laser distinctions collapse to VESTRA's
  // 'Bras' leaf (inc/products.php vestra_all_cats()); only the genuinely
  // different shaping-garment line gets its own leaf, 'Shapewear'. The nuance
  // (wired vs wire-free, nursing, laser-cut) belongs in the product title/desc,
  // not as a 7-way VESTRA top-level category no other seller in this catalogue
  // uses.
  'DESTEKSİZ SÜTYEN' => ['en'=>'Wire-Free Bra','fr'=>'Soutien-gorge sans armatures','es'=>'Sujetador sin aros','it'=>'Reggiseno senza ferretto','de'=>'BH ohne Bügel','pt'=>'Sutiã sem aro','ru'=>'Бюстгальтер без косточек','ar'=>'حمالة صدر بدون سلك', 'vestra_cat'=>'Bras'],
  'BAYAN SÜTYEN'      => ['en'=>"Women's Bra",'fr'=>'Soutien-gorge femme','es'=>'Sujetador de mujer','it'=>'Reggiseno donna','de'=>'Damen-BH','pt'=>'Sutiã feminino','ru'=>'Женский бюстгальтер','ar'=>'حمالة صدر نسائية', 'vestra_cat'=>'Bras'],
  'DESTEKLİ SÜTYEN'   => ['en'=>'Underwire Bra','fr'=>'Soutien-gorge armaturé','es'=>'Sujetador con aros','it'=>'Reggiseno con ferretto','de'=>'BH mit Bügel','pt'=>'Sutiã com aro','ru'=>'Бюстгальтер на косточках','ar'=>'حمالة صدر بسلك', 'vestra_cat'=>'Bras'],
  'TOPARLAYICI SÜTYEN'=> ['en'=>'Shaping Bra','fr'=>'Soutien-gorge minceur','es'=>'Sujetador reductor','it'=>'Reggiseno modellante','de'=>'Formender BH','pt'=>'Sutiã modelador','ru'=>'Утягивающий бюстгальтер','ar'=>'حمالة صدر مشدة', 'vestra_cat'=>'Shapewear'],
  'LAZER SÜTYEN'      => ['en'=>'Laser-Cut Bra','fr'=>'Soutien-gorge sans coutures','es'=>'Sujetador sin costuras','it'=>'Reggiseno senza cuciture','de'=>'Nahtloser BH','pt'=>'Sutiã sem costuras','ru'=>'Бесшовный бюстгальтер','ar'=>'حمالة صدر بدون خياطة', 'vestra_cat'=>'Bras'],
  'EMZİRME SÜTYENİ'   => ['en'=>'Nursing Bra','fr'=>'Soutien-gorge allaitement','es'=>'Sujetador de lactancia','it'=>'Reggiseno per allattamento','de'=>'Still-BH','pt'=>'Sutiã de amamentação','ru'=>'Бюстгальтер для кормления','ar'=>'حمالة صدر للرضاعة', 'vestra_cat'=>'Bras'],
  'SİLİKON SÜTYEN'    => ['en'=>'Silicone Bra','fr'=>'Soutien-gorge silicone','es'=>'Sujetador de silicona','it'=>'Reggiseno in silicone','de'=>'Silikon-BH','pt'=>'Sutiã de silicone','ru'=>'Силиконовый бюстгальтер','ar'=>'حمالة صدر سيليكون', 'vestra_cat'=>'Bras'],
];

const KULOGLU_COLORS = [
  'SİYAH'          => ['en'=>'Black','fr'=>'Noir','es'=>'Negro','it'=>'Nero','de'=>'Schwarz','pt'=>'Preto','ru'=>'Чёрный','ar'=>'أسود'],
  'BEYAZ'          => ['en'=>'White','fr'=>'Blanc','es'=>'Blanco','it'=>'Bianco','de'=>'Weiß','pt'=>'Branco','ru'=>'Белый','ar'=>'أبيض'],
  'TEN'            => ['en'=>'Nude','fr'=>'Nude','es'=>'Nude','it'=>'Nude','de'=>'Nude','pt'=>'Nude','ru'=>'Телесный','ar'=>'بلون الجسد'],
  'GRİ'            => ['en'=>'Grey','fr'=>'Gris','es'=>'Gris','it'=>'Grigio','de'=>'Grau','pt'=>'Cinza','ru'=>'Серый','ar'=>'رمادي'],
  'EKRU'           => ['en'=>'Ecru','fr'=>'Écru','es'=>'Crudo','it'=>'Ecru','de'=>'Ecru','pt'=>'Cru','ru'=>'Экрю','ar'=>'إكرو'],
  'BORDO'          => ['en'=>'Burgundy','fr'=>'Bordeaux','es'=>'Burdeos','it'=>'Bordeaux','de'=>'Bordeauxrot','pt'=>'Bordô','ru'=>'Бордовый','ar'=>'خمري'],
  'GÜL KURUSU'     => ['en'=>'Dusty Rose','fr'=>'Vieux rose','es'=>'Rosa empolvado','it'=>'Rosa antico','de'=>'Altrosa','pt'=>'Rosa velho','ru'=>'Пыльная роза','ar'=>'وردي جاف'],
  'VİZON'          => ['en'=>'Mink','fr'=>'Vison','es'=>'Visón','it'=>'Visone','de'=>'Nerz','pt'=>'Marta','ru'=>'Норковый','ar'=>'بني مطفي'],
  'MÜRDÜM'         => ['en'=>'Plum','fr'=>'Prune','es'=>'Ciruela','it'=>'Prugna','de'=>'Pflaume','pt'=>'Ameixa','ru'=>'Сливовый','ar'=>'بنفسجي داكن'],
  'ASORTİ'         => ['en'=>'Assorted','fr'=>'Assorti','es'=>'Surtido','it'=>'Assortito','de'=>'Sortiert','pt'=>'Sortido','ru'=>'Ассорти','ar'=>'ألوان متنوعة'],
  'PEMBE'          => ['en'=>'Pink','fr'=>'Rose','es'=>'Rosa','it'=>'Rosa','de'=>'Pink','pt'=>'Rosa','ru'=>'Розовый','ar'=>'وردي'],
  'PUDRA'          => ['en'=>'Powder Pink','fr'=>'Rose poudré','es'=>'Rosa polvo','it'=>'Rosa cipria','de'=>'Puderrosa','pt'=>'Rosa pó','ru'=>'Пудровый','ar'=>'وردي بودرة'],
  'YEŞİL'          => ['en'=>'Green','fr'=>'Vert','es'=>'Verde','it'=>'Verde','de'=>'Grün','pt'=>'Verde','ru'=>'Зелёный','ar'=>'أخضر'],
  'SOMON'          => ['en'=>'Salmon','fr'=>'Saumon','es'=>'Salmón','it'=>'Salmone','de'=>'Lachs','pt'=>'Salmão','ru'=>'Лососевый','ar'=>'سلموني'],
  'MOR'            => ['en'=>'Purple','fr'=>'Violet','es'=>'Morado','it'=>'Viola','de'=>'Lila','pt'=>'Roxo','ru'=>'Фиолетовый','ar'=>'بنفسجي'],
  'MAVİ'           => ['en'=>'Blue','fr'=>'Bleu','es'=>'Azul','it'=>'Blu','de'=>'Blau','pt'=>'Azul','ru'=>'Синий','ar'=>'أزرق'],
  'KAHVE'          => ['en'=>'Brown','fr'=>'Marron','es'=>'Marrón','it'=>'Marrone','de'=>'Braun','pt'=>'Castanho','ru'=>'Коричневый','ar'=>'بني'],
  'Y.AĞZI'         => ['en'=>'Emerald','fr'=>'Émeraude','es'=>'Esmeralda','it'=>'Smeraldo','de'=>'Smaragd','pt'=>'Esmeralda','ru'=>'Изумрудный','ar'=>'زمردي'],
  'LACİVERT'       => ['en'=>'Navy','fr'=>'Bleu marine','es'=>'Azul marino','it'=>'Blu navy','de'=>'Marineblau','pt'=>'Azul-marinho','ru'=>'Тёмно-синий','ar'=>'كحلي'],
  'HARDAL'         => ['en'=>'Mustard','fr'=>'Moutarde','es'=>'Mostaza','it'=>'Senape','de'=>'Senfgelb','pt'=>'Mostarda','ru'=>'Горчичный','ar'=>'خردلي'],
  'KIRMIZI'        => ['en'=>'Red','fr'=>'Rouge','es'=>'Rojo','it'=>'Rosso','de'=>'Rot','pt'=>'Vermelho','ru'=>'Красный','ar'=>'أحمر'],
  'PETROL'         => ['en'=>'Petrol Blue','fr'=>'Bleu pétrole','es'=>'Azul petróleo','it'=>'Blu petrolio','de'=>'Petrolblau','pt'=>'Azul petróleo','ru'=>'Тёмно-бирюзовый','ar'=>'أزرق بترولي'],
  'KOYU YEŞİL'     => ['en'=>'Dark Green','fr'=>'Vert foncé','es'=>'Verde oscuro','it'=>'Verde scuro','de'=>'Dunkelgrün','pt'=>'Verde escuro','ru'=>'Тёмно-зелёный','ar'=>'أخضر داكن'],
  'CAPPUCCİNO'     => ['en'=>'Cappuccino','fr'=>'Cappuccino','es'=>'Capuchino','it'=>'Cappuccino','de'=>'Cappuccino','pt'=>'Cappuccino','ru'=>'Капучино','ar'=>'كابتشينو'],
  'VİŞNE'          => ['en'=>'Sour Cherry','fr'=>'Griotte','es'=>'Guinda','it'=>'Amarena','de'=>'Kirschrot','pt'=>'Cereja','ru'=>'Вишнёвый','ar'=>'كرزي'],
  'KİREMİT'        => ['en'=>'Terracotta','fr'=>'Terre cuite','es'=>'Terracota','it'=>'Terracotta','de'=>'Terrakotta','pt'=>'Terracota','ru'=>'Терракотовый','ar'=>'طيني'],
  'LAVANTA'        => ['en'=>'Lavender','fr'=>'Lavande','es'=>'Lavanda','it'=>'Lavanda','de'=>'Lavendel','pt'=>'Lavanda','ru'=>'Лавандовый','ar'=>'خزامي'],
  'MELANJ PEMBE'   => ['en'=>'Heather Pink','fr'=>'Rose chiné','es'=>'Rosa jaspeado','it'=>'Rosa melange','de'=>'Meliert Rosa','pt'=>'Rosa mesclado','ru'=>'Меланжевый розовый','ar'=>'وردي مخلط'],
  'LİLA'           => ['en'=>'Lilac','fr'=>'Lilas','es'=>'Lila','it'=>'Lilla','de'=>'Lila','pt'=>'Lilás','ru'=>'Сиреневый','ar'=>'ليلكي'],
  'ÇAĞLA YEŞİLİ'   => ['en'=>'Almond Green','fr'=>'Vert amande','es'=>'Verde almendra','it'=>'Verde mandorla','de'=>'Mandelgrün','pt'=>'Verde amêndoa','ru'=>'Миндально-зелёный','ar'=>'أخضر لوزي'],
  'TAŞ'            => ['en'=>'Stone','fr'=>'Pierre','es'=>'Piedra','it'=>'Pietra','de'=>'Steingrau','pt'=>'Pedra','ru'=>'Каменный','ar'=>'حجري'],
  'VİZON GRİ'      => ['en'=>'Mink Grey','fr'=>'Gris vison','es'=>'Gris visón','it'=>'Grigio visone','de'=>'Nerzgrau','pt'=>'Cinza marta','ru'=>'Норковый серый','ar'=>'رمادي مطفي'],
  'VİZON PEMBE'    => ['en'=>'Mink Pink','fr'=>'Rose vison','es'=>'Rosa visón','it'=>'Rosa visone','de'=>'Nerzrosa','pt'=>'Rosa marta','ru'=>'Норковый розовый','ar'=>'وردي مطفي'],
  'ZEBRA'          => ['en'=>'Zebra Print','fr'=>'Imprimé zèbre','es'=>'Estampado cebra','it'=>'Stampa zebrata','de'=>'Zebraprint','pt'=>'Estampa zebra','ru'=>'Зебра принт','ar'=>'خطوط الحمار الوحشي'],
  'NEON A.PEMBE'   => ['en'=>'Neon Light Pink','fr'=>'Rose clair néon','es'=>'Rosa claro neón','it'=>'Rosa chiaro neon','de'=>'Neon Hellrosa','pt'=>'Rosa claro neon','ru'=>'Неоновый светло-розовый','ar'=>'وردي فاتح نيون'],
  'NEON A.TURUNCU' => ['en'=>'Neon Light Orange','fr'=>'Orange clair néon','es'=>'Naranja claro neón','it'=>'Arancione chiaro neon','de'=>'Neon Hellorange','pt'=>'Laranja claro neon','ru'=>'Неоновый светло-оранжевый','ar'=>'برتقالي فاتح نيون'],
  'NEON PEMBE'     => ['en'=>'Neon Pink','fr'=>'Rose néon','es'=>'Rosa neón','it'=>'Rosa neon','de'=>'Neonpink','pt'=>'Rosa neon','ru'=>'Неоновый розовый','ar'=>'وردي نيون'],
  'NEON TURUNCU'   => ['en'=>'Neon Orange','fr'=>'Orange néon','es'=>'Naranja neón','it'=>'Arancione neon','de'=>'Neonorange','pt'=>'Laranja neon','ru'=>'Неоновый оранжевый','ar'=>'برتقالي نيون'],
  'NEON YEŞİLİ'    => ['en'=>'Neon Green','fr'=>'Vert néon','es'=>'Verde neón','it'=>'Verde neon','de'=>'Neongrün','pt'=>'Verde neon','ru'=>'Неоновый зелёный','ar'=>'أخضر نيون'],
  // Only ever seen in the "beden"/size field, not the "renk"/colour field —
  // Kuloğlu's own site quirk (see file docblock), same colour vocabulary either way.
  'FÜME'           => ['en'=>'Smoke Grey','fr'=>'Gris fumé','es'=>'Gris humo','it'=>'Grigio fumo','de'=>'Rauchgrau','pt'=>'Cinza fumê','ru'=>'Дымчато-серый','ar'=>'رمادي دخاني'],
  'HAKİ'           => ['en'=>'Khaki','fr'=>'Kaki','es'=>'Caqui','it'=>'Kaki','de'=>'Khaki','pt'=>'Cáqui','ru'=>'Хаки','ar'=>'كاكي'],
  'FUŞYA'          => ['en'=>'Fuchsia','fr'=>'Fuchsia','es'=>'Fucsia','it'=>'Fucsia','de'=>'Fuchsia','pt'=>'Fúcsia','ru'=>'Фуксия','ar'=>'فوشيا'],
  'TOPRAK'         => ['en'=>'Earth Brown','fr'=>'Brun terre','es'=>'Marrón tierra','it'=>'Marrone terra','de'=>'Erdbraun','pt'=>'Terra','ru'=>'Земляной','ar'=>'ترابي'],
  'ANTRASİT'       => ['en'=>'Anthracite','fr'=>'Anthracite','es'=>'Antracita','it'=>'Antracite','de'=>'Anthrazit','pt'=>'Antracite','ru'=>'Антрацит','ar'=>'أنثراسيت'],
  'MOCHA'          => ['en'=>'Mocha','fr'=>'Moka','es'=>'Moca','it'=>'Moka','de'=>'Mokka','pt'=>'Moca','ru'=>'Мокко','ar'=>'موكا'],
  'BRONZ TEN'      => ['en'=>'Bronze Nude','fr'=>'Nude bronze','es'=>'Nude bronce','it'=>'Nude bronzo','de'=>'Bronze Nude','pt'=>'Nude bronze','ru'=>'Бронзово-телесный','ar'=>'بلون الجسد البرونزي'],
  'AÇIK TEN'       => ['en'=>'Light Nude','fr'=>'Nude clair','es'=>'Nude claro','it'=>'Nude chiaro','de'=>'Helles Nude','pt'=>'Nude claro','ru'=>'Светло-телесный','ar'=>'بلون الجسد الفاتح'],
  'AÇIK MAVİ'      => ['en'=>'Light Blue','fr'=>'Bleu clair','es'=>'Azul claro','it'=>'Azzurro','de'=>'Hellblau','pt'=>'Azul claro','ru'=>'Голубой','ar'=>'أزرق فاتح'],
  'AÇIK KAHVE'     => ['en'=>'Light Brown','fr'=>'Marron clair','es'=>'Marrón claro','it'=>'Marrone chiaro','de'=>'Hellbraun','pt'=>'Castanho claro','ru'=>'Светло-коричневый','ar'=>'بني فاتح'],
  'BEBE MAVİSİ'    => ['en'=>'Baby Blue','fr'=>'Bleu layette','es'=>'Azul bebé','it'=>'Azzurro baby','de'=>'Babyblau','pt'=>'Azul bebê','ru'=>'Нежно-голубой','ar'=>'أزرق سماوي'],
  'BEJ'            => ['en'=>'Beige','fr'=>'Beige','es'=>'Beige','it'=>'Beige','de'=>'Beige','pt'=>'Bege','ru'=>'Бежевый','ar'=>'بيج'],
  'NAVY'           => ['en'=>'Navy','fr'=>'Bleu marine','es'=>'Azul marino','it'=>'Blu navy','de'=>'Marineblau','pt'=>'Azul-marinho','ru'=>'Тёмно-синий','ar'=>'كحلي'],
  'LEOPAR'         => ['en'=>'Leopard Print','fr'=>'Imprimé léopard','es'=>'Estampado leopardo','it'=>'Stampa leopardo','de'=>'Leopardenmuster','pt'=>'Estampa onça','ru'=>'Леопардовый принт','ar'=>'رقطاء الفهد'],
  'ZÜMRÜT'         => ['en'=>'Emerald Green','fr'=>'Vert émeraude','es'=>'Verde esmeralda','it'=>'Verde smeraldo','de'=>'Smaragdgrün','pt'=>'Verde esmeralda','ru'=>'Изумрудно-зелёный','ar'=>'أخضر زمردي'],
  'SAKS'           => ['en'=>'Saxe Blue','fr'=>'Bleu saxe','es'=>'Azul sajonia','it'=>'Blu sassonia','de'=>'Sachsischblau','pt'=>'Azul saxe','ru'=>'Саксонский синий','ar'=>'أزرق ساكس'],
  'TURUNCU'        => ['en'=>'Orange','fr'=>'Orange','es'=>'Naranja','it'=>'Arancione','de'=>'Orange','pt'=>'Laranja','ru'=>'Оранжевый','ar'=>'برتقالي'],
  'MİNT'           => ['en'=>'Mint','fr'=>'Menthe','es'=>'Menta','it'=>'Menta','de'=>'Mint','pt'=>'Menta','ru'=>'Мятный','ar'=>'نعناعي'],
];

const KULOGLU_WORDS = [
  'SÜTYEN'      => ['en'=>'Bra','fr'=>'Soutien-gorge','es'=>'Sujetador','it'=>'Reggiseno','de'=>'BH','pt'=>'Sutiã','ru'=>'Бюстгальтер','ar'=>'حمالة صدر'],
  'BAYAN'       => ['en'=>"Women's",'fr'=>'Femme','es'=>'Mujer','it'=>'Donna','de'=>'Damen','pt'=>'Feminino','ru'=>'Женский','ar'=>'نسائي'],
  'DESTEKSİZ'   => ['en'=>'Wire-Free','fr'=>'Sans armatures','es'=>'Sin aros','it'=>'Senza ferretto','de'=>'Ohne Bügel','pt'=>'Sem aro','ru'=>'Без косточек','ar'=>'بدون سلك'],
  'DESTEKLİ'    => ['en'=>'Underwire','fr'=>'Armaturé','es'=>'Con aros','it'=>'Con ferretto','de'=>'Mit Bügel','pt'=>'Com aro','ru'=>'На косточках','ar'=>'بسلك'],
  'BÜSTİYER'    => ['en'=>'Bustier','fr'=>'Bustier','es'=>'Bustier','it'=>'Bustier','de'=>'Bustier','pt'=>'Bustiê','ru'=>'Бюстье','ar'=>'كورسيه صدر'],
  'TOPARLAYICI' => ['en'=>'Shaping','fr'=>'Minceur','es'=>'Reductor','it'=>'Modellante','de'=>'Formend','pt'=>'Modelador','ru'=>'Утягивающий','ar'=>'مشد'],
  'LAZER'       => ['en'=>'Laser-Cut','fr'=>'Sans coutures','es'=>'Sin costuras','it'=>'Senza cuciture','de'=>'Nahtlos','pt'=>'Sem costuras','ru'=>'Бесшовный','ar'=>'بدون خياطة'],
  'KALIP'       => ['en'=>'Fit','fr'=>'Coupe','es'=>'Copa','it'=>'Coppa','de'=>'Passform','pt'=>'Modelo','ru'=>'Крой','ar'=>'مقاس'],
  'ASKI'        => ['en'=>'Strap','fr'=>'Bretelle','es'=>'Tirante','it'=>'Spallina','de'=>'Träger','pt'=>'Alça','ru'=>'Бретель','ar'=>'حمالة'],
  'PEDLİ'       => ['en'=>'Padded','fr'=>'Rembourré','es'=>'Acolchado','it'=>'Imbottito','de'=>'Gepolstert','pt'=>'Almofadado','ru'=>'С поролоном','ar'=>'محشو'],
  'İP'          => ['en'=>'String','fr'=>'Fine','es'=>'Fino','it'=>'Sottile','de'=>'Schmal','pt'=>'Fino','ru'=>'Тонкий','ar'=>'رفيع'],
  'MİCRO'       => ['en'=>'Micro','fr'=>'Micro','es'=>'Micro','it'=>'Micro','de'=>'Micro','pt'=>'Micro','ru'=>'Микро','ar'=>'مايكرو'],
  'MİKRO'       => ['en'=>'Micro','fr'=>'Micro','es'=>'Micro','it'=>'Micro','de'=>'Micro','pt'=>'Micro','ru'=>'Микро','ar'=>'مايكرو'],
  'STRAPLEZ'    => ['en'=>'Strapless','fr'=>'Sans bretelles','es'=>'Sin tirantes','it'=>'Senza spalline','de'=>'Trägerlos','pt'=>'Sem alças','ru'=>'Без бретелей','ar'=>'بدون حمالات'],
  'KAPLI'       => ['en'=>'Covered','fr'=>'Couvrant','es'=>'Cobertura total','it'=>'Coppa piena','de'=>'Bedeckt','pt'=>'Cobertura total','ru'=>'Закрытый','ar'=>'مغطى'],
  'BOŞ'         => ['en'=>'Unlined','fr'=>'Non doublé','es'=>'Sin relleno','it'=>'Non imbottito','de'=>'Ungefüttert','pt'=>'Sem enchimento','ru'=>'Без наполнителя','ar'=>'بدون حشو'],
  'BATTAL'      => ['en'=>'Plus Size','fr'=>'Grande taille','es'=>'Talla grande','it'=>'Taglia forte','de'=>'Übergröße','pt'=>'Tamanho grande','ru'=>'Большой размер','ar'=>'مقاس كبير'],
  'KALIN'       => ['en'=>'Thick','fr'=>'Épais','es'=>'Grueso','it'=>'Spesso','de'=>'Dick','pt'=>'Grosso','ru'=>'Плотный','ar'=>'سميك'],
  'İTHAL'       => ['en'=>'Imported','fr'=>'Importé','es'=>'Importado','it'=>'Importato','de'=>'Importiert','pt'=>'Importado','ru'=>'Импортный','ar'=>'مستورد'],
  'BAMBU'       => ['en'=>'Bamboo','fr'=>'Bambou','es'=>'Bambú','it'=>'Bambù','de'=>'Bambus','pt'=>'Bambu','ru'=>'Бамбук','ar'=>'خيزران'],
  'KAŞKORSE'    => ['en'=>'Corset-Style','fr'=>'Style corset','es'=>'Estilo corsé','it'=>'Stile corsetto','de'=>'Corsage-Stil','pt'=>'Estilo espartilho','ru'=>'В стиле корсета','ar'=>'بأسلوب المشد'],
  'ASKILI'      => ['en'=>'With Straps','fr'=>'À bretelles','es'=>'Con tirantes','it'=>'Con spalline','de'=>'Mit Trägern','pt'=>'Com alças','ru'=>'С бретелями','ar'=>'بحمالات'],
  'SİLİKON'     => ['en'=>'Silicone','fr'=>'Silicone','es'=>'Silicona','it'=>'Silicone','de'=>'Silikon','pt'=>'Silicone','ru'=>'Силикон','ar'=>'سيليكون'],
  'HAFİF'       => ['en'=>'Lightweight','fr'=>'Léger','es'=>'Ligero','it'=>'Leggero','de'=>'Leicht','pt'=>'Leve','ru'=>'Лёгкий','ar'=>'خفيف'],
  'ŞEFFAF'      => ['en'=>'Sheer','fr'=>'Transparent','es'=>'Transparente','it'=>'Trasparente','de'=>'Transparent','pt'=>'Transparente','ru'=>'Прозрачный','ar'=>'شفاف'],
  'DANTELLİ'    => ['en'=>'Lace','fr'=>'Dentelle','es'=>'Encaje','it'=>'Pizzo','de'=>'Spitze','pt'=>'Renda','ru'=>'Кружевной','ar'=>'دانتيل'],
  'ATLET'       => ['en'=>'Tank Top','fr'=>'Débardeur','es'=>'Camiseta de tirantes','it'=>'Canotta','de'=>'Tanktop','pt'=>'Regata','ru'=>'Майка','ar'=>'قميص بلا أكمام'],
  'RAPORLU'     => ['en'=>'Patterned','fr'=>'Motif répété','es'=>'Estampado repetido','it'=>'Motivo ripetuto','de'=>'Musterstoff','pt'=>'Estampa repetida','ru'=>'С узором','ar'=>'بنقشة متكررة'],
  'EMZİRME'     => ['en'=>'Nursing','fr'=>'Allaitement','es'=>'Lactancia','it'=>'Allattamento','de'=>'Stillen','pt'=>'Amamentação','ru'=>'Для кормления','ar'=>'رضاعة'],
  'KAP'         => ['en'=>'Cup','fr'=>'Bonnet','es'=>'Copa','it'=>'Coppa','de'=>'Cup','pt'=>'Copa','ru'=>'Чашка','ar'=>'كأس'],
  'PENYE'       => ['en'=>'Combed Cotton','fr'=>'Coton peigné','es'=>'Algodón peinado','it'=>'Cotone pettinato','de'=>'Gekämmte Baumwolle','pt'=>'Algodão penteado','ru'=>'Гребенной хлопок','ar'=>'قطن ممشط'],
  'GENİŞ'       => ['en'=>'Wide','fr'=>'Large','es'=>'Ancho','it'=>'Largo','de'=>'Breit','pt'=>'Largo','ru'=>'Широкий','ar'=>'واسع'],
  'FİTİLLİ'     => ['en'=>'Corded','fr'=>'À cordonnet','es'=>'Con cordoncillo','it'=>'Con cordoncino','de'=>'Mit Kordel','pt'=>'Com debrum','ru'=>'С кантом','ar'=>'مزين بحبل'],
  'DOLGULU'     => ['en'=>'Padded','fr'=>'Rembourré','es'=>'Con relleno','it'=>'Imbottito','de'=>'Gepolstert','pt'=>'Com enchimento','ru'=>'С наполнителем','ar'=>'محشو'],
  'TELSİZ'      => ['en'=>'Wireless','fr'=>'Sans armatures','es'=>'Sin varillas','it'=>'Senza ferretto','de'=>'Bügellos','pt'=>'Sem aro','ru'=>'Без косточек','ar'=>'بدون سلك'],
  'DÜZ'         => ['en'=>'Plain','fr'=>'Uni','es'=>'Liso','it'=>'Tinta unita','de'=>'Einfarbig','pt'=>'Liso','ru'=>'Однотонный','ar'=>'سادة'],
  'SİLİKONLU'   => ['en'=>'With Silicone','fr'=>'Avec silicone','es'=>'Con silicona','it'=>'Con silicone','de'=>'Mit Silikon','pt'=>'Com silicone','ru'=>'С силиконом','ar'=>'بسيليكون'],
  'RİBANA'      => ['en'=>'Ribbed','fr'=>'Côtelé','es'=>'Acanalado','it'=>'A coste','de'=>'Geripptes Jersey','pt'=>'Canelado','ru'=>'В рубчик','ar'=>'مضلع'],
  'ÇAPRAZ'      => ['en'=>'Crossed','fr'=>'Croisé','es'=>'Cruzado','it'=>'Incrociato','de'=>'Gekreuzt','pt'=>'Cruzado','ru'=>'Перекрёстный','ar'=>'متقاطع'],
  'YARIM'       => ['en'=>'Half','fr'=>'Demi','es'=>'Medio','it'=>'Mezzo','de'=>'Halb','pt'=>'Meio','ru'=>'Половина','ar'=>'نصف'],
  'TÜL'         => ['en'=>'Tulle','fr'=>'Tulle','es'=>'Tul','it'=>'Tulle','de'=>'Tüll','pt'=>'Tule','ru'=>'Тюль','ar'=>'تول'],
  'SPORCU'      => ['en'=>'Sports','fr'=>'Sport','es'=>'Deportivo','it'=>'Sportivo','de'=>'Sport','pt'=>'Esportivo','ru'=>'Спортивный','ar'=>'رياضي'],
  'YIKAMA'      => ['en'=>'Laundry','fr'=>'Lavage','es'=>'Lavado','it'=>'Lavaggio','de'=>'Wäsche','pt'=>'Lavagem','ru'=>'Стирка','ar'=>'غسيل'],
  'GÖĞÜS'       => ['en'=>'Bust','fr'=>'Poitrine','es'=>'Busto','it'=>'Seno','de'=>'Brust','pt'=>'Busto','ru'=>'Бюст','ar'=>'صدر'],
  'ÇITÇITLI'    => ['en'=>'Snap-Button','fr'=>'À pression','es'=>'Con broches','it'=>'Con bottoni automatici','de'=>'Mit Druckknopf','pt'=>'Com colchete','ru'=>'На кнопках','ar'=>'بأزرار كبس'],
  'DOLU'        => ['en'=>'Full','fr'=>'Plein','es'=>'Lleno','it'=>'Pieno','de'=>'Voll','pt'=>'Cheio','ru'=>'Полный','ar'=>'ممتلئ'],
  'PETLİ'       => ['en'=>'Padded','fr'=>'Rembourré','es'=>'Acolchado','it'=>'Imbottito','de'=>'Gepolstert','pt'=>'Almofadado','ru'=>'С поролоном','ar'=>'محشو'],
  'FERMUARLI'   => ['en'=>'Zippered','fr'=>'Zippé','es'=>'Con cremallera','it'=>'Con zip','de'=>'Mit Reißverschluss','pt'=>'Com zíper','ru'=>'На молнии','ar'=>'بسحاب'],
  'KROP'        => ['en'=>'Crop','fr'=>'Crop','es'=>'Crop','it'=>'Crop','de'=>'Crop','pt'=>'Cropped','ru'=>'Кроп','ar'=>'قصير'],
  'ASKISI'      => ['en'=>'Strap','fr'=>'Bretelle','es'=>'Tirante','it'=>'Spallina','de'=>'Träger','pt'=>'Alça','ru'=>'Бретель','ar'=>'حمالة'],
  'YAKA'        => ['en'=>'Neckline','fr'=>'Encolure','es'=>'Escote','it'=>'Scollo','de'=>'Ausschnitt','pt'=>'Decote','ru'=>'Вырез','ar'=>'ياقة'],
  'KIZ'         => ['en'=>"Girl's",'fr'=>'Fille','es'=>'Niña','it'=>'Ragazza','de'=>'Mädchen','pt'=>'Menina','ru'=>'Для девочек','ar'=>'بنات'],
  'SLİKONLU'    => ['en'=>'With Silicone','fr'=>'Avec silicone','es'=>'Con silicona','it'=>'Con silicone','de'=>'Mit Silikon','pt'=>'Com silicone','ru'=>'С силиконом','ar'=>'بسيليكون'],
  'DÜĞMELİ'     => ['en'=>'Buttoned','fr'=>'Boutonné','es'=>'Con botones','it'=>'Con bottoni','de'=>'Geknöpft','pt'=>'Com botões','ru'=>'На пуговицах','ar'=>'بأزرار'],
  'DALGIÇ'      => ['en'=>'Scuba Fabric','fr'=>'Néoprène','es'=>'Neopreno','it'=>'Neoprene','de'=>'Neopren','pt'=>'Neoprene','ru'=>'Неопрен','ar'=>'نيوبرين'],
  'DESENLİ'     => ['en'=>'Patterned','fr'=>'Imprimé','es'=>'Estampado','it'=>'Fantasia','de'=>'Gemustert','pt'=>'Estampado','ru'=>'С узором','ar'=>'منقوش'],
  'LASTİK'      => ['en'=>'Elastic Band','fr'=>'Élastique','es'=>'Elástico','it'=>'Elastico','de'=>'Gummiband','pt'=>'Elástico','ru'=>'Резинка','ar'=>'مطاط'],
  'YAPIŞKANLI'  => ['en'=>'Adhesive','fr'=>'Adhésif','es'=>'Adhesivo','it'=>'Adesivo','de'=>'Selbstklebend','pt'=>'Adesivo','ru'=>'Клейкий','ar'=>'لاصق'],
  'SIRTI'       => ['en'=>'Back','fr'=>'Dos','es'=>'Espalda','it'=>'Schiena','de'=>'Rücken','pt'=>'Costas','ru'=>'Спина','ar'=>'ظهر'],
  'JAKARLI'     => ['en'=>'Jacquard','fr'=>'Jacquard','es'=>'Jacquard','it'=>'Jacquard','de'=>'Jacquard','pt'=>'Jacquard','ru'=>'Жаккард','ar'=>'جاكار'],
  'HAYALET'     => ['en'=>'Invisible','fr'=>'Invisible','es'=>'Invisible','it'=>'Invisibile','de'=>'Unsichtbar','pt'=>'Invisível','ru'=>'Невидимый','ar'=>'غير مرئي'],
  'ÇOCUK'       => ['en'=>"Child's",'fr'=>'Enfant','es'=>'Niño/a','it'=>'Bambino/a','de'=>'Kinder','pt'=>'Infantil','ru'=>'Детский','ar'=>'أطفال'],
  'MODAL'       => ['en'=>'Modal','fr'=>'Modal','es'=>'Modal','it'=>'Modal','de'=>'Modal','pt'=>'Modal','ru'=>'Модал','ar'=>'موداال'],
  'ALIŞTIRMA'   => ['en'=>'Training','fr'=>"Premier soutien-gorge",'es'=>'Sujetador de iniciación','it'=>'Primo reggiseno','de'=>'Erster BH','pt'=>'Sutiã de iniciação','ru'=>'Первый бюстгальтер','ar'=>'تدريبية'],
  'İPLİ'        => ['en'=>'String','fr'=>'À cordon','es'=>'Con cordón','it'=>'Con laccio','de'=>'Mit Kordel','pt'=>'Com cordão','ru'=>'На шнурке','ar'=>'بخيط'],
  'ÜÇGEN'       => ['en'=>'Triangle','fr'=>'Triangle','es'=>'Triángulo','it'=>'Triangolo','de'=>'Dreieck','pt'=>'Triângulo','ru'=>'Треугольный','ar'=>'مثلث'],
  'TORBASI'     => ['en'=>'Bag','fr'=>'Sac','es'=>'Bolsa','it'=>'Sacchetto','de'=>'Beutel','pt'=>'Saco','ru'=>'Мешок','ar'=>'كيس'],
  'ÇİFT'        => ['en'=>'Pair','fr'=>'Paire','es'=>'Par','it'=>'Paio','de'=>'Paar','pt'=>'Par','ru'=>'Пара','ar'=>'زوج'],
  'HALTER'      => ['en'=>'Halter','fr'=>'Dos-nu','es'=>'Halter','it'=>'Halter','de'=>'Neckholder','pt'=>'Halter','ru'=>'Халтер','ar'=>'حمالة رقبة'],
  'TELLİ'       => ['en'=>'Wired','fr'=>'Armaturé','es'=>'Con aros','it'=>'Con ferretto','de'=>'Mit Bügel','pt'=>'Com aro','ru'=>'На косточках','ar'=>'بسلك'],
  'AGRAFLI'     => ['en'=>'With Clasp','fr'=>'Avec agrafe','es'=>'Con broche','it'=>'Con gancio','de'=>'Mit Haken','pt'=>'Com fecho','ru'=>'С застёжкой','ar'=>'بمشبك'],
  'KESİM'       => ['en'=>'Cut','fr'=>'Coupe','es'=>'Corte','it'=>'Taglio','de'=>'Schnitt','pt'=>'Corte','ru'=>'Крой','ar'=>'قصة'],
  'PUANLI'      => ['en'=>'Polka Dot','fr'=>'À pois','es'=>'De lunares','it'=>'A pois','de'=>'Gepunktet','pt'=>'De bolinhas','ru'=>'В горошек','ar'=>'منقط'],
  'LİKRALI'     => ['en'=>'With Lycra','fr'=>'Avec élasthanne','es'=>'Con licra','it'=>'Con elastan','de'=>'Mit Elasthan','pt'=>'Com elastano','ru'=>'С лайкрой','ar'=>'بالليكرا'],
  'UCU'         => ['en'=>'Tip','fr'=>'Bout','es'=>'Punta','it'=>'Punta','de'=>'Spitze','pt'=>'Ponta','ru'=>'Кончик','ar'=>'طرف'],
  'ORTA'        => ['en'=>'Medium','fr'=>'Moyen','es'=>'Medio','it'=>'Medio','de'=>'Mittel','pt'=>'Médio','ru'=>'Средний','ar'=>'متوسط'],
  'KULAKLI'     => ['en'=>'Tabbed','fr'=>'À oreillettes','es'=>'Con orejetas','it'=>'Con alette','de'=>'Mit Laschen','pt'=>'Com abas','ru'=>'С ушками','ar'=>'بألسنة'],
  'BALENSİZ'    => ['en'=>'Boneless','fr'=>'Sans baleines','es'=>'Sin ballenas','it'=>'Senza stecche','de'=>'Ohne Bügelstäbe','pt'=>'Sem barbatanas','ru'=>'Без косточек','ar'=>'بدون دعامات'],
  'SPOR'        => ['en'=>'Sport','fr'=>'Sport','es'=>'Deportivo','it'=>'Sportivo','de'=>'Sport','pt'=>'Esportivo','ru'=>'Спортивный','ar'=>'رياضي'],
  'TAKIM'       => ['en'=>'Set','fr'=>'Ensemble','es'=>'Conjunto','it'=>'Completo','de'=>'Set','pt'=>'Conjunto','ru'=>'Комплект','ar'=>'طقم'],
  'SİHİRLİ'     => ['en'=>'Magic','fr'=>'Magique','es'=>'Mágico','it'=>'Magico','de'=>'Magisch','pt'=>'Mágico','ru'=>'Волшебный','ar'=>'سحري'],
  'DUBLELİ'     => ['en'=>'Double-Layer','fr'=>'Double épaisseur','es'=>'Doble capa','it'=>'Doppio strato','de'=>'Doppellagig','pt'=>'Camada dupla','ru'=>'Двухслойный','ar'=>'طبقة مزدوجة'],
  'SIRT'        => ['en'=>'Back','fr'=>'Dos','es'=>'Espalda','it'=>'Schiena','de'=>'Rücken','pt'=>'Costas','ru'=>'Спина','ar'=>'ظهر'],
  'TIRNAK'      => ['en'=>'Hook','fr'=>'Crochet','es'=>'Gancho','it'=>'Gancetto','de'=>'Haken','pt'=>'Gancho','ru'=>'Крючок','ar'=>'خطاف'],
  'AGRAF'       => ['en'=>'Clasp','fr'=>'Agrafe','es'=>'Broche','it'=>'Gancio','de'=>'Haken','pt'=>'Fecho','ru'=>'Застёжка','ar'=>'مشبك'],
  'EK'          => ['en'=>'Extra','fr'=>'Supplémentaire','es'=>'Adicional','it'=>'Aggiuntivo','de'=>'Zusätzlich','pt'=>'Adicional','ru'=>'Дополнительный','ar'=>'إضافي'],
  'FİLESİ'      => ['en'=>'Mesh','fr'=>'Résille','es'=>'Malla','it'=>'Rete','de'=>'Netzstoff','pt'=>'Tela','ru'=>'Сетка','ar'=>'شبكي'],
  'YANDAN'      => ['en'=>'Side','fr'=>'Latéral','es'=>'Lateral','it'=>'Laterale','de'=>'Seitlich','pt'=>'Lateral','ru'=>'Сбоку','ar'=>'جانبي'],
];

/**
 * Turkish colour word -> VESTRA's shared swatch palette (inc/products.php
 * vestra_colors()), for the `colors` field the import actually WRITES onto
 * each listing.
 *
 * KULOGLU_COLORS above translates a colour into all 8 SITE LANGUAGES, but
 * that is not how a listing's colour actually reaches a visitor: product.php
 * only ever calls t() on colour names that are keys of vestra_colors() (a
 * fixed swatch palette — product.php:327, inc/products.php vestra_color_dots()
 * lines 568-579), and any colour NOT in that palette is silently dropped from
 * the swatch-dot row entirely (`if(!isset($pal[$c])) continue;`). A product's
 * `name`/`desc` fields are never translated at all (no t() wrapper anywhere
 * they're printed) — this whole file's earlier docblock claim of "translated
 * titles" was corrected once that was found; see the commit history.
 *
 * So the real deliverable for colours is this MANY-TO-ONE map onto the 23
 * vestra_colors() English names (18 pre-existing + Nude/Mink/Purple/Plum/
 * Salmon, added alongside this file specifically because Nude alone covers
 * 300+ of the 638 products and had no prior match). Low-frequency shades
 * (Dusty Rose, Powder Pink, Mustard, Terracotta, Anthracite...) map to their
 * closest existing swatch rather than each getting a new palette entry —
 * extending the shared palette for a colour that appears once or twice
 * across the whole catalogue isn't worth the swatch-picker clutter for
 * every OTHER seller's listing that also uses this same palette.
 *
 * Two things are NOT solid colours and are mapped to 'Other' (the palette's
 * existing multi-colour-gradient catch-all, product.php's own answer to
 * "doesn't fit a single swatch" — see colour_other_test.php):
 *   - Animal/neon PRINTS (ZEBRA, LEOPAR, the five NEON_* entries)
 *   - ASORTİ itself isn't a colour at all — it's Kuloğlu's own marker that
 *     the PACK ships in a mix of colours the buyer doesn't choose, so there
 *     is no single swatch to show; 'Other' communicates "not one colour"
 *     honestly instead of forcing a specific wrong one.
 *
 * Lookup keys are canonical UPPERCASE Turkish (matching KULOGLU_COLORS).
 * The raw crawl data is NOT case-consistent — Kuloğlu's own "beden"/size
 * field carries some colour names in mixed case ("Vizon", "Mor", "Lila",
 * "Taş", "Mint") where the "renk"/colour field has them upper ("VİZON",
 * "MOR"...) — so a consumer MUST mb_strtoupper() (Turkish-aware: İ/I, i/ı)
 * its input before indexing this table, not assume the source casing.
 */
const KULOGLU_COLOR_PALETTE_MAP = [
  // Exact / direct match onto an existing (pre-Kuloğlu) palette entry.
  'SİYAH' => 'Black', 'BEYAZ' => 'White', 'GRİ' => 'Grey', 'BORDO' => 'Bordeaux',
  'PEMBE' => 'Pink', 'YEŞİL' => 'Green', 'MAVİ' => 'Blue', 'KAHVE' => 'Brown',
  'LACİVERT' => 'Navy', 'KIRMIZI' => 'Red', 'HAKİ' => 'Khaki', 'FUŞYA' => 'Fuchsia',
  'AÇIK MAVİ' => 'Light Blue', 'TURUNCU' => 'Orange', 'NAVY' => 'Navy', 'BEJ' => 'Beige',
  // Exact / direct match onto a NEW (Kuloğlu-motivated) palette entry.
  'TEN' => 'Nude', 'BRONZ TEN' => 'Nude', 'AÇIK TEN' => 'Nude',
  'VİZON' => 'Mink', 'VİZON GRİ' => 'Mink', 'VİZON PEMBE' => 'Mink',
  'MÜRDÜM' => 'Plum', 'MOR' => 'Purple', 'LAVANTA' => 'Purple', 'LİLA' => 'Purple',
  'SOMON' => 'Salmon',
  // Nearest existing match — each is low-frequency enough on its own (see
  // the file docblock's counts) that a dedicated new swatch isn't justified.
  'EKRU' => 'Cream', 'GÜL KURUSU' => 'Pink', 'PUDRA' => 'Pink', 'MELANJ PEMBE' => 'Pink',
  'Y.AĞZI' => 'Green', 'HARDAL' => 'Yellow', 'PETROL' => 'Navy', 'KOYU YEŞİL' => 'Green',
  'CAPPUCCİNO' => 'Brown', 'VİŞNE' => 'Bordeaux', 'KİREMİT' => 'Orange',
  'ÇAĞLA YEŞİLİ' => 'Green', 'TAŞ' => 'Beige', 'FÜME' => 'Dark Grey', 'TOPRAK' => 'Brown',
  'ANTRASİT' => 'Dark Grey', 'MOCHA' => 'Brown', 'AÇIK KAHVE' => 'Brown',
  'BEBE MAVİSİ' => 'Light Blue', 'ZÜMRÜT' => 'Green', 'SAKS' => 'Blue', 'MİNT' => 'Green',
  // Prints and the "mixed colours in the pack" marker -> the palette's own
  // multi-colour catch-all, never guessed onto a specific wrong solid colour.
  'ZEBRA' => 'Other', 'LEOPAR' => 'Other', 'ASORTİ' => 'Other',
  'NEON A.PEMBE' => 'Other', 'NEON A.TURUNCU' => 'Other', 'NEON PEMBE' => 'Other',
  'NEON TURUNCU' => 'Other', 'NEON YEŞİLİ' => 'Other',
];

return ['categories' => KULOGLU_CATEGORIES, 'colors' => KULOGLU_COLORS, 'words' => KULOGLU_WORDS, 'palette_map' => KULOGLU_COLOR_PALETTE_MAP];
