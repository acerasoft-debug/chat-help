<?php
/**
 * AYAKKABI DUKKANLARINA ILK TEMAS (operator, 26 Eyl 2026: *"sende tum
 * avrupadan ayakkabi dukkani bul zincir olmasin gercek email adreslerine
 * ayakkabi kategorisini ve bir kac diger kategorilerden gonder"*).
 *
 * Tutulanlar, IKI YON:
 *   - rakamlar PARAMETREDEN (metne gomulu degil) -- baska sayiyla cagirinca
 *     metin degismeli;
 *   - sifir/bos olan cumle HIC basilmaz (tur yok, cocuk yok, kutu yok, giyim
 *     yok, "Ispanyol uretici" dogrulanmadi);
 *   - "stoktan" sozu 10 dilde de YOK (ships_from bos -- KURAL 3);
 *   - fiyat listesi acik diye soz yok, kayit sarti var (KURAL 19);
 *   - abonelikten cikma linki ve kunye ayraci her dilde;
 *   - Lehce/Cekce sayi bicimi;
 *   - is akisi kablolamasi VE is akisinin GERCEK PHP'si kum havuzunda
 *     kosturuluyor (kaynak taramasi kosmayan bir kodu yesil gosterebilir).
 */
$root = dirname(__DIR__).'/vestra';
require_once $root.'/inc/products.php';
require_once $root.'/inc/notify.php';          // email_templates.php'yi de yukler

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $bad++; echo "  HATA $n\n"; }
};

$F = [
  'shoes' => 335,
  'types' => ['Sneakers'=>128, 'Flats'=>75, 'Slippers'=>44, 'Sandals'=>41, 'Boots'=>27, 'Loafers'=>17],
  'kids' => true, 'origin_es' => true, 'box_min' => 5, 'box_max' => 12,
  'apparel' => [
    ['cat'=>'Polos',    'brands'=>['Fred Perry','Lacoste','Dolce & Gabbana']],
    ['cat'=>'T-Shirts', 'brands'=>['DSQUARED2','Burberry']],
  ],
  'shots' => [
    ['img'=>'https://vestrasales.com/uploads/a.jpg', 'url'=>'https://vestrasales.com/product?id=a', 'cat'=>'Sneakers'],
    ['img'=>'https://vestrasales.com/uploads/b.jpg', 'url'=>'https://vestrasales.com/product?id=b', 'brand'=>'Fred Perry'],
    ['img'=>'', 'cat'=>'Flats'],   // gorselsiz kare atlanmali
  ],
];
$S = vestra_tpl_footwear_intro_strings();
$LANGS = ['en','de','fr','nl','it','es','pt','pl','cs','el'];

echo "== 1. Rakamlar parametreden ==\n";
[$s1, $b1, $o1] = vestra_tpl_footwear_intro('en', 'Schuhhaus Muster', $F);
$t('model sayisi konuda',            str_contains($s1, '335 footwear models'));
$t('model sayisi govdede',           str_contains($b1, '• 335 footwear models from a Spanish manufacturer: '));
$t('turler sirayla, "and" ile',      str_contains($b1, 'sneakers, flats, slippers, sandals, boots and loafers, for adults and children.'));
$t('kutu araligi',                   str_contains($b1, 'Ordered by the box: 5 to 12 pairs of one model per box.'));
$t('giyim satiri (kategori — marka)', str_contains($b1, '• Polo shirts — Fred Perry, Lacoste, Dolce & Gabbana'));
$t('ikinci giyim satiri',            str_contains($b1, '• T-shirts — DSQUARED2, Burberry'));
$t('firma adiyla hitap',             str_contains($b1, 'Hello Schuhhaus Muster,'));
$F2 = $F; $F2['shoes'] = 42; $F2['box_min'] = 12; $F2['box_max'] = 12;
[$s2, $b2] = vestra_tpl_footwear_intro('en', 'X', $F2);
$t('baska sayiyla metin degisiyor',  str_contains($s2, '42 footwear models') && !str_contains($b2, '335'));
$t('tek kutu boyu -> tek sayi cumlesi', str_contains($b2, 'Ordered by the box: 12 pairs of one model per box.') && !str_contains($b2, ' to 12'));

echo "\n== 2. Bos/sifir olan cumle basilmaz ==\n";
[, $b3] = vestra_tpl_footwear_intro('en', 'X', ['shoes'=>17]);
$t('tur yoksa iki nokta yok',        str_contains($b3, '• 17 footwear models.'));
$t('cocuk bayragi yoksa cumle yok',  !str_contains($b3, 'children'));
$t('uretici dogrulanmadiysa yok',    !str_contains($b3, 'Spanish'));
$t('kutu olculemediyse cumle yok',   !str_contains($b3, 'Ordered by the box'));
$t('giyim yoksa paragraf yok',       !str_contains($b3, 'branded apparel'));
[, $b3b, $o3b] = vestra_tpl_footwear_intro('de', 'X', ['shoes'=>17, 'types'=>['Heels'=>0, 'Boots'=>6, 'Mystery'=>9]]);
$t('sifir sayili tur basilmiyor',    !str_contains($b3b, 'High Heels'));
$t('tanimsiz tur basilmiyor',        !str_contains($b3b, 'Mystery') && str_contains($b3b, 'Schuhmodelle: Stiefel.'));
$t('giyim yoksa indirme listesi yok', !isset($o3b['downloads']));
foreach ($LANGS as $lg) {
    [$sx, $bx] = vestra_tpl_footwear_intro($lg, 'X', $F);
    $leak = preg_match('/%(N|MODELS|CO|MIN|MAX)%/', $sx.$bx);
    $t("{$lg}: yer tutucu sizmiyor", !$leak);
}

echo "\n== 3. Soz verilmeyenler: stok, acik fiyat listesi (KURAL 3 / 19) ==\n";
$noStock = '/from stock|in stock|ab Lager|auf Lager|du stock|en stock|a magazzino|disponibili|desde stock|en existencias|uit voorraad|op voorraad|do stock|em stock|z magazynu|skladem|από απόθεμα|σε απόθεμα/iu';
$regOk   = '/registered businesses|registrierte Betriebe|entreprises enregistrées|geregistreerde bedrijven|aziende registrate|empresas registradas|empresas registadas|zarejestrowane firmy|registrované firmy|εγγεγραμμένες επιχειρήσεις/iu';
$listNo  = '/price list|Preisliste|liste de prix|prijslijst|listino|lista de precios|lista de preços|cennik|ceník|τιμοκατάλογ/iu';
foreach ($LANGS as $lg) {
    [$sx, $bx] = vestra_tpl_footwear_intro($lg, 'X', $F);
    $t("{$lg}: 'stoktan' sozu YOK",        !preg_match($noStock, $sx.$bx));
    $t("{$lg}: kayit sarti yaziyor",       (bool)preg_match($regOk, $bx));
    $t("{$lg}: fiyat listesi vaadi yok",   !preg_match($listNo, $bx));
}

echo "\n== 4. Cikis yolu ve kunye her dilde ==\n";
foreach ($LANGS as $lg) {
    [, $bx] = vestra_tpl_footwear_intro($lg, 'X', $F);
    $t("{$lg}: abonelikten cikma linki", str_contains($bx, 'https://vestrasales.com/lead-unsubscribe'));
    $t("{$lg}: kunye ayraci (mektup/kunye ayrimi)", substr_count($bx, "\n\n—\n") === 1);
    $t("{$lg}: Acerasoft LLC kunyede",  str_contains(explode("\n\n—\n", $bx)[1] ?? '', 'Acerasoft LLC'));
}

echo "\n== 5. Diller ==\n";
$t('tam 10 dil',                     array_keys($S) === $LANGS);
$need = array_keys($S['en']);
foreach ($LANGS as $lg) {
    $t("{$lg}: butun anahtarlar var", array_diff($need, array_keys($S[$lg])) === []);
    $t("{$lg}: 7 ayakkabi turu cevrili", count(array_intersect_key($S[$lg]['types'], array_flip(['Sneakers','Flats','Sandals','Boots','Loafers','Slippers','Heels']))) === 7);
    $t("{$lg}: Turkce harf sizmiyor",  !preg_match('/[şğıİŞĞ]/u', json_encode($S[$lg], JSON_UNESCAPED_UNICODE)));
}
[$sTr, $bTr] = vestra_tpl_footwear_intro('tr', 'X', $F);
$t('bilinmeyen dil Ingilizceye duser', str_contains($bTr, 'footwear models') && str_contains($sTr, 'for your shop'));
[, $bDe] = vestra_tpl_footwear_intro('de', 'X', $F);
$t('de: magazanin diliyle tur adlari (vitrinin kelimeleri)', str_contains($bDe, 'Sneaker, Ballerinas, Hausschuhe, Sandalen, Stiefel und Loafer'));
[, $bFr] = vestra_tpl_footwear_intro('fr', 'X', $F);
$t('fr: iki noktadan once bosluk',    str_contains($bFr, "d'un fabricant espagnol : baskets"));

echo "\n== 6. Sayi bicimi (Lehce / Cekce / Ingilizce) ==\n";
$pl = $S['pl']['models']; $cs = $S['cs']['models'];
$t('pl 1  -> model',   vestra_tpl_count_form(1, $pl, 'pl')   === 'model obuwia');
$t('pl 2  -> modele',  vestra_tpl_count_form(2, $pl, 'pl')   === 'modele obuwia');
$t('pl 5  -> modeli',  vestra_tpl_count_form(5, $pl, 'pl')   === 'modeli obuwia');
$t('pl 12 -> modeli',  vestra_tpl_count_form(12, $pl, 'pl')  === 'modeli obuwia');
$t('pl 22 -> modele',  vestra_tpl_count_form(22, $pl, 'pl')  === 'modele obuwia');
$t('pl 335 -> modeli', vestra_tpl_count_form(335, $pl, 'pl') === 'modeli obuwia');
$t('pl 332 -> modele', vestra_tpl_count_form(332, $pl, 'pl') === 'modele obuwia');
$t('cs 1  -> model',   vestra_tpl_count_form(1, $cs, 'cs')   === 'model obuvi');
$t('cs 3  -> modely',  vestra_tpl_count_form(3, $cs, 'cs')   === 'modely obuvi');
$t('cs 335 -> modelu', vestra_tpl_count_form(335, $cs, 'cs') === 'modelů obuvi');
$t('en 1  -> tekil',   vestra_tpl_count_form(1, $S['en']['models'], 'en') === 'footwear model');
[$sPl] = vestra_tpl_footwear_intro('pl', 'X', $F);
$t('pl konu: 335 modeli obuwia', str_contains($sPl, '335 modeli obuwia'));

echo "\n== 7. Hitap ==\n";
[, $b7] = vestra_tpl_footwear_intro('en', 'chiarulli.it', $F);
$t('ciplak alan adi hitapta kullanilmaz', str_contains($b7, "Hello,\n") && !str_contains($b7, 'chiarulli'));
[, $b7b] = vestra_tpl_footwear_intro('en', '', $F);
$t('bos ad -> notr hitap', str_starts_with($b7b, "Hello,\n"));

echo "\n== 7b. Hitap adi: sayfa basligi firma adi degildir ==\n";
/* 27 Eyl 2026 Almanya kuru kosusunda taranan GERCEK adlar. Kayda dokunulmaz;
   yalnizca hitap kuruluyor. */
foreach ([
    ['Willkommen bei Schuhhaus Zeller',              'Schuhhaus Zeller'],
    ['Startseite',                                   ''],
    ['Willkommen bei Schuh Seidl, 80796 München',    'Schuh Seidl'],
    ['Schuhhaus Tervooren · Seit 1904',              'Schuhhaus Tervooren'],
    ['Schuhhaus Zimmermann.',                        'Schuhhaus Zimmermann'],
    ['Home',                                         ''],
    ['Αρχική',                                       ''],
    ['Bienvenue chez Élan Chaussures',               'Élan Chaussures'],
    ['Schuh &amp; Sport Schöwing',                   'Schuh & Sport Schöwing'],
    // Kayittaki TAM ad (27 Eyl gonderimi "…Zeller e.K," diye gitti): noktali
    // kisaltmanin noktasi KALIR, siradan son nokta gider.
    ['Willkommen bei Schuhhaus Zeller e.K., 96047 Bamberg', 'Schuhhaus Zeller e.K.'],
    ['Calzados Luz S.L.',                            'Calzados Luz S.L.'],
    ['Moda Rossi S.p.A.',                            'Moda Rossi S.p.A.'],
    ['Chaussures Martin.',                           'Chaussures Martin'],
] as [$in, $want]) $t("hitap: '{$in}' -> '{$want}'", vestra_tpl_greeting_name($in) === $want);
/* TERS YON: gercek adlar DOKUNULMADAN kalmali. Bitisik tire ("Schuh- und
   Sporthaus") ayrac degil; "in Bremen" bir sehir, slogan degil; "Start" ile
   baslayan gercek bir ad "Start" sayfasi degil. */
foreach ([
    'Schuh- und Sporthaus Bohmann Garrel', 'Schuhhaus Riedemann in Bremen', 'Schuhe Lüke',
    'Start Up Shoes', 'Home & Sole', 'Müller das Schuhhaus', 'CC Shoes', 'Auf großem Fuss',
] as $keep) $t("hitap: '{$keep}' aynen kalir", vestra_tpl_greeting_name($keep) === $keep);
[, $bG] = vestra_tpl_footwear_intro('de', 'Startseite', $F);
$t('de: "Startseite" -> notr hitap', str_starts_with($bG, "Guten Tag,\n"));
[, $bG2] = vestra_tpl_footwear_intro('de', 'Willkommen bei Schuhhaus Zeller', $F);
$t('de: karsilama oneki hitaptan atildi', str_starts_with($bG2, "Guten Tag Schuhhaus Zeller,\n"));

echo "\n== 8. Fotograf seridi, indirme listesi, dugme ==\n";
[, , $o8] = vestra_tpl_footwear_intro('de', 'X', $F);
$t('gorselsiz kare atlandi (2 kare)',  count($o8['shots'] ?? []) === 2);
$t('ayakkabi karesi: tur, dilinde',   ($o8['shots'][0]['label'] ?? '') === 'Sneaker');
$t('giyim karesi: marka, cevrilmez',  ($o8['shots'][1]['label'] ?? '') === 'Fred Perry');
$t('serit basligi dilinde',           ($o8['shots_title'] ?? '') === 'Aus der aktuellen Auswahl');
$many = $F; $many['shots'] = array_fill(0, 14, ['img'=>'https://x/y.jpg', 'cat'=>'Boots']);
[, , $o8b] = vestra_tpl_footwear_intro('en', 'X', $many);
$t('serit en cok 9 kare (3x3)',       count($o8b['shots']) === 9);
$dl = array_column($o8['downloads']['items'] ?? [], 'label');
$t('indirme listesi: giyim markalari, tekil', $dl === ['Fred Perry','Lacoste','Dolce & Gabbana','DSQUARED2','Burberry']);
$t('indirme linki markanin line-sheeti', ($o8['downloads']['items'][0]['url'] ?? '') === 'https://vestrasales.com/catalog?brand=Fred%20Perry');
$t('dugme ayakkabi bolmesine',        ($o8['button']['url'] ?? '') === 'https://vestrasales.com/shop?section=footwear');

echo "\n== 9. Is akisi kablolamasi (add-and-send / send-outreach) ==\n";
$wf = file_get_contents(dirname(__DIR__).'/.github/workflows/add-and-send.yml');
$t('letter girdisi, varsayilan designer', (bool)preg_match('/\n      letter:\n(?:.*\n){1,3}?\s+default: "designer"/', $wf));
$t('LETTER env + envs listesi',       str_contains($wf, 'LETTER: ${{ github.event.inputs.letter }}') && str_contains($wf, ',SAME_FIRM,LETTER'));
$pLetter = strpos($wf, "if (!in_array(\$LETTER, ['designer', 'footwear'], true))");
$pWrite  = strpos($wf, 'vestra_save_leads(');
$t('taninmayan letter lead yazmadan ONCE durur', $pLetter !== false && $pWrite !== false && $pLetter < $pWrite);
$t('ayakkabi sablonu cagriliyor',     str_contains($wf, 'vestra_tpl_footwear_intro($lang, $company, $FW)'));
$t('designer mektubu yerinde',        str_contains($wf, 'vestra_campaign_preview($company, $lang, $FEATCAT)'));
$t('gonderen VESTRA (ayakkabi)',      str_contains($wf, "\$FROM = \$LETTER === 'footwear' ? 'VESTRA' : 'Les Garage de Paris';"));
$t('damga footwear/<dil>',            str_contains($wf, "(\$LETTER === 'footwear' ? 'footwear/' : 'les-garage/').\$lang"));
$t('yeni leadin kategorisi footwear', str_contains($wf, "'category'=>(\$LETTER === 'footwear' ? 'footwear' : ''),"));
$t('bolme okuyucusu, ham alan degil', str_contains($wf, '$sec = vestra_product_section($p);'));
$t('satilmis ilan sayilmiyor',        str_contains($wf, 'if (vestra_is_sold_out($p)) continue;'));
$t('fotograf diskte dogrulaniyor',    str_contains($wf, "is_file(\$home.'/public_html'.\$im)"));
$t('dil haritasi TEK kopya',          substr_count($wf, '$LANG_MAP = [') === 1 && substr_count($wf, '$TLD_LANG = [') === 1);
$t('Luksemburg -> Fransizca',          str_contains($wf, "'luxembourg'=>'fr'"));
/* Olcu DIZININ KENDISINE bakiyor: workflow'un yorumu bu iki adi tirnak
   icinde ANIYOR ("'sion' ya da 'nyon' konsaydi"), dosyanin tamamini taramak
   dogru kodu kirmizi gosterirdi -- bu depoda kayitli "olcu yorumu okudu"
   tuzagi; ilk yazimda tam bu oldu. */
$chBlock = preg_match("/'switzerland' => \['default'=>'de','match'=>\[(.*?)\]\],/s", $wf, $chm) ? $chm[1] : '';
$t("Isvicre bolgesi: varsayilan de, Cenevre fr, Lugano it", $chBlock !== '' && str_contains($chBlock, "'genève'") && str_contains($chBlock, "'lugano'"));
$t("Isvicre bolgesi: 'sion'/'nyon' YOK (fashion/canyon tuzagi)", $chBlock !== '' && !preg_match("/'(sion|nyon)'/", $chBlock));
$so = file_get_contents(dirname(__DIR__).'/.github/workflows/send-outreach.yml');
/* Kosulun KENDISI ve arkasindaki continue olculuyor. Ilk yazimda iddia yalnizca
   ifadenin METINDE gectigine bakiyordu ve "if (false && ...)" sabotajinda YESIL
   kaldi -- hic dusemeyen bir iddia, iddia degildir (bu dosyanin kayitli dersi). */
$t("send-outreach: 'shoes' ikinci mektubu footwear/ leadini atliyor",
   (bool)preg_match("/\n\s+if \(\\\$NC_LETTER === 'shoes' && str_starts_with\(\(string\)\(\\\$l\['last_campaign'\] \?\? ''\), 'footwear\/'\)\) \{\n[^\n]*\n\s+continue;/", $so));

echo "\n== 10. Is akisinin GERCEK PHP'si kum havuzunda ==\n";
/* Kaynak taramasi kosmayan bir kodu yesil gosterebilir (php -l bu depoda iki
   calisma-zamani hatasini gecirdi). Betik workflow'dan cikariliyor, sahte bir
   katalog + lead kaydiyla, AGSIZ (bilinen alan adi + gmail adresi) ve
   send=false ile kosuyor. */
if (!preg_match("/<<'PHPEOF'\n(.*?)\n\s*PHPEOF\n/s", $wf, $m)) { $t('betik cikarilabildi', false); }
else {
    $lines = explode("\n", $m[1]);
    $ind = null;
    foreach ($lines as $ln) { if (trim($ln) === '') continue; $w = strlen($ln) - strlen(ltrim($ln, ' ')); $ind = $ind === null ? $w : min($ind, $w); }
    $php = implode("\n", array_map(fn($ln) => substr($ln, (int)$ind), $lines));
    $sb = sys_get_temp_dir().'/vestra_fw_'.bin2hex(random_bytes(4));
    $ph = $sb.'/public_html';
    @mkdir($ph.'/data', 0777, true); @mkdir($ph.'/uploads/pp', 0777, true);
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/inc', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($rii as $f) {
        $dst = $ph.'/inc/'.substr($f->getPathname(), strlen($root.'/inc/'));
        if ($f->isDir()) @mkdir($dst, 0777, true); else { @mkdir(dirname($dst), 0777, true); copy($f->getPathname(), $dst); }
    }
    foreach (['s','f','k','p'] as $x) file_put_contents($ph."/uploads/pp/{$x}.jpg", 'x');
    $mk = fn($id, $b, $cat, $sec, $img, $ex = []) => array_merge(['id'=>$id,'brand'=>$b,'name'=>"Model {$id}",'cat'=>$cat,'section'=>$sec,
        'status'=>'approved','images'=>[$img],'tiers'=>[['min'=>12,'price'=>10]],'moq'=>12,'unit'=>'pair','mode'=>'fixed','list'=>10], $ex);
    $L = [];
    for ($i = 1; $i <= 6; $i++) $L[] = $mk("pp-s{$i}", 'Pili Pérez', 'Sneakers', 'footwear', '/uploads/pp/s.jpg');
    for ($i = 1; $i <= 5; $i++) $L[] = $mk("pp-f{$i}", 'Pili Pérez', 'Flats', 'footwear', '/uploads/pp/f.jpg', ['moq'=>5]);
    for ($i = 1; $i <= 5; $i++) $L[] = $mk("pp-k{$i}", 'Pili Pérez', 'Sandals', 'footwear', '/uploads/pp/k.jpg', ['desc'=>"Model K, children's sandal"]);
    $L[] = $mk('pp-h1', 'Pili Pérez', 'Heels', 'footwear', '/uploads/pp/s.jpg');
    $L[] = $mk('pp-sold', 'Pili Pérez', 'Boots', 'footwear', '/uploads/pp/s.jpg', ['sold_out'=>true]);
    $L[] = $mk('fp-polo', 'Fred Perry', 'Polos', 'premium', '/uploads/pp/p.jpg', ['unit'=>'pc']);
    /* Iki kategoride de EN KALABALIK marka ayni (DSQUARED2): eski secim iki
       kez "DSQUARED2" basiyordu (27 Eyl canli kuru kosusu). */
    foreach (['Hoodies & Sweatshirts' => 'Givenchy', 'Jeans' => 'Dolce & Gabbana'] as $c => $other) {
        for ($i = 1; $i <= 3; $i++) $L[] = $mk('dsq-'.md5($c).$i, 'DSQUARED2', $c, 'premium', '/uploads/pp/p.jpg', ['unit'=>'pc']);
        $L[] = $mk('oth-'.md5($c), $other, $c, 'premium', '/uploads/pp/p.jpg', ['unit'=>'pc']);
    }
    $L[] = $mk('lac-miss', 'Lacoste', 'Polos', 'premium', '/uploads/pp/YOK.jpg', ['unit'=>'pc']);
    file_put_contents($ph.'/data/listings.json', json_encode($L, JSON_UNESCAPED_UNICODE));
    file_put_contents($ph.'/data/leads.json', json_encode([[ 'id'=>'LDK','company'=>'Known Schuhe','email'=>'info@known-schuhe.de',
        'website'=>'https://www.known-schuhe.de','country'=>'Germany','status'=>'new','last_contacted_at'=>'','unsub_token'=>'t']]));
    file_put_contents($sb.'/run.php', $php);
    $env = 'HOME='.escapeshellarg($sb).' IN_EMAILS='.escapeshellarg('https://known-schuhe.de, shop.test@gmail.com')
         .' DO_SEND=false LETTER=footwear IN_COUNTRY=Germany';
    $out = (string)shell_exec('cd '.escapeshellarg($ph).' && env '.$env.' php '.escapeshellarg($sb.'/run.php').' 2>&1');
    $t('kosu hatasiz bitti',                 str_contains($out, 'send=false -- gonderim yapilmadi.') && !preg_match('/Fatal|Warning|Deprecated/i', $out));
    $t('satilmis ilan sayilmadi (17 model)', str_contains($out, 'AYAKKABI: 17 model satista'));
    $t('>=5 kurali: Heels (1) mektuba girmedi', str_contains($out, 'mektuba giren turler (>=5 model): Sneakers, Flats, Sandals'));
    $t('yetiskin+cocuk olculdu',             str_contains($out, "yetiskin 12 / cocuk 5 -> 'yetiskin ve cocuk' cumlesi: VAR"));
    $t('kutu araligi birimi cift olan ilanlardan', str_contains($out, 'kutu: 5-12 cift'));
    $t('tek marka Pili Perez -> Ispanyol uretici', str_contains($out, "'Ispanyol uretici': EVET"));
    $fotoLine = preg_match('/FOTO: (\\d+) kare \\(diskte dogrulandi\\): ([^\\n]*)/', $out, $fm) ? $fm : null;
    $labels = $fotoLine ? array_map('trim', explode('|', $fotoLine[2])) : [];
    $appLabels = array_values(array_diff($labels, ['Sneakers','Flats','Sandals','Boots','Loafers','Slippers','Heels']));
    $t('diskte olmayan kare seride girmedi (Lacoste YOK)', $fotoLine !== null && !in_array('Lacoste', $labels, true));
    $t('giyim kareleri AYRI markalardan (tekrar yok)', $appLabels !== [] && count($appLabels) === count(array_unique($appLabels)));
    $t('en kalabalik marka seride bir kez', count(array_keys($appLabels, 'DSQUARED2', true)) === 1);
    $t('onizleme partinin dilinde (de)',      str_contains($out, 'ONIZLEME (dil=de') && str_contains($out, '17 Schuhmodelle eines spanischen Herstellers'));
    $t('bilinen alan adi yeniden taranmadi',  str_contains($out, 'zaten kayitli (1 adres) -- site yeniden taranmadi'));
    $t('premium giyim taramasi atlandi',      str_contains($out, 'premium giyim taramasi atlandi'));
    $leads = json_decode((string)file_get_contents($ph.'/data/leads.json'), true);
    $new = array_values(array_filter($leads, fn($l) => ($l['email'] ?? '') === 'shop.test@gmail.com'));
    $t('yeni lead kategorisi footwear',       ($new[0]['category'] ?? '') === 'footwear');
    $t('kuru kosu damga yazmadi',             trim((string)($new[0]['last_contacted_at'] ?? '')) === '');
    /* Taninmayan mektup: HICBIR lead yazilmadan durmali. */
    $before = (string)file_get_contents($ph.'/data/leads.json');
    $bad2 = (string)shell_exec('cd '.escapeshellarg($ph).' && env HOME='.escapeshellarg($sb)
          .' IN_EMAILS='.escapeshellarg('yeni.dukkan@gmail.com').' DO_SEND=false LETTER=shoes php '.escapeshellarg($sb.'/run.php').' 2>&1; echo "RC=$?"');
    $t('taninmayan letter -> cikis 1',        str_contains($bad2, 'letter gecersiz: shoes') && str_contains($bad2, 'RC=1'));
    $t('taninmayan letter -> leads.json DEGISMEDI', (string)file_get_contents($ph.'/data/leads.json') === $before);
    /* GONDERIM YOLU: cop adres (27 Eyl canli kuru kosusundaki yapisik-www kalibi)
       kayitta dursa bile mektup denenmez. Tek aday cop oldugu icin kosu
       vestra_send_mail'e hic ulasmamali; DNS kontrolu kapali -> ag yok. */
    $junkMail = 'kontakt@marke.comwww.marke-group.comangaben';
    $jl = json_decode((string)file_get_contents($ph.'/data/leads.json'), true);
    $jl[] = ['id'=>'LDJ','company'=>'Schuh Junk','email'=>$junkMail,'website'=>'https://schuh-junk.test',
             'country'=>'Germany','status'=>'new','last_contacted_at'=>'','unsub_token'=>'j'];
    file_put_contents($ph.'/data/leads.json', json_encode($jl));
    $jo = (string)shell_exec('cd '.escapeshellarg($ph).' && env HOME='.escapeshellarg($sb)
        .' IN_EMAILS='.escapeshellarg($junkMail).' DO_SEND=true DNS_CHECK=false LETTER=footwear IN_COUNTRY=Germany php '
        .escapeshellarg($sb.'/run.php').' 2>&1');
    $t('gonderim yolu: cop adres ATLANDI',    str_contains($jo, 'ATLANDI (cop/yer tutucu adres): '.$junkMail));
    $t('gonderim yolu: cop adrese mektup DENENMEDI', !str_contains($jo, 'GONDERILDI') && !str_contains($jo, 'x HATA'));
    shell_exec('rm -rf '.escapeshellarg($sb));
}

echo "\n{$ok} ok, {$bad} hata\n";
exit($bad ? 1 : 0);
