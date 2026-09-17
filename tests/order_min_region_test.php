<?php
/**
 * AVRUPA DISI ASGARI SIPARIS (**BUGUN KAPALI**) + AFRIKA %8 BOLGESEL INDIRIM.
 *
 * Taban 16 Eyl 2026'da kondu (10.000 -> ayni gun 5.000) ve **17 Eyl 2026'da
 * operator kaldirdi**: *"US$5.000 Avrupa disi taban, bunu girmene gerek
 * yok.... avrupa disindan isteyen normal en az alim ile siparis verebilsin"*.
 * Davranis BILEREK degisti, o yuzden testi de duzeltildi -- bu deponun kendi
 * kurali (*"davranis bilerek degistiyse testi de duzelt"*): eski iddialar
 * artik KALDIRILMIS bir kurali pinliyordu.
 *
 * BOLGESEL INDIRIM AYRI BIR KAPI ve DOKUNULMADI: Afrika %8, Guney Amerika/
 * JP/AU/SG/HK/CZ/PL %10 aynen duruyor. Iki kapinin bagimsiz oldugunu tutan
 * iddialar burada, cunku tabani kaldiran bir degisiklik indirimi de sessizce
 * goturebilirdi.
 *
 * IKI YONU DE tutuyor ve tutmak ZORUNDA: kapsama girmesi gerekenler kadar,
 * kapsam DISINDA kalmasi gerekenler de. Tek yon yazilsaydi "herkese %8" ya da
 * "herkese taban" da yesil kalirdi -- bu depoda ayni sinif hata
 * (mango/zara) defalarca kayitli.
 */
require_once __DIR__.'/../vestra/inc/products.php';

$ok = 0; $fail = 0;
$t = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; echo "  ok   {$name}\n"; }
    else       { $fail++; echo "  HATA {$name}\n"; }
};
$u = fn(string $c) => ['country' => $c];

echo "== Afrika %8: kapsam ==\n";
foreach (['Benin','bénin','BJ','Nigeria','Senegal','Sénégal','Morocco','Maroc',
          'South Africa','Egypt','Kenya','Côte d\'Ivoire','Ghana','TG'] as $c) {
    $t("{$c} -> %8", abs(vestra_region_discount_pct($u($c)) - 8.0) < 0.001);
}
$t('54 Afrika ulkesi tabloda', count(vestra_africa_names()) === 54);

echo "\n== Afrika eklenince ESKI liste %10 KALDI ==\n";
/* Tek sabit donen eski hali, Afrika eklenince Guney Amerika'yi da %8'e
   cekerdi -- oran artik ULKENIN KENDI orani. */
foreach (['Brazil','Japan','Australia','Singapore','Czechia','Poland','Hong Kong'] as $c) {
    $t("{$c} -> %10", abs(vestra_region_discount_pct($u($c)) - 10.0) < 0.001);
}

echo "\n== indirim YOK (kapsam disi) ==\n";
/* Réunion/Mayotte cografyasi Afrika ama AB gumruk alani; sirket adi ulke
   degil; 'Nigerian' bir ulke adi degil. */
foreach (['Germany','DE','France','United States','Réunion','Mayotte',
          'Canary Islands','Nigerian Textiles Ltd','Guinea Pig Ltd',''] as $c) {
    $t(($c === '' ? '(bos)' : $c).' -> indirim yok', vestra_region_discount_pct($u($c)) === 0.0);
}
$t('hesapsiz -> indirim yok', vestra_region_discount_pct(null) === 0.0);

echo "\n== benzer ad tuzaklari: TAM eslesme ==\n";
/* Alt dize eslesmesi bu ciftlerin hepsini karistirirdi. */
$pairs = [['Niger','NE'], ['Nigeria','NG'], ['Guinea','GN'], ['Equatorial Guinea','GQ'],
          ['Guinea-Bissau','GW'], ['Congo','CG'], ['DR Congo','CD'],
          ['Sudan','SD'], ['South Sudan','SS'], ['Chad','TD']];
foreach ($pairs as [$name, $cc]) $t("{$name} -> {$cc}", vestra_country_region_discount_cc($name) === $cc);

echo "\n== Avrupa testi: kapsam DISI (taban uygulanir) ==\n";
/* Avrupa testi SILINMEDI: taban kapali olsa da bu fonksiyonu pazar sayfasi
   (vestra_seo_market_facts) ve terms_reply mektubu hala okuyor. */
foreach (['Benin','BJ','Brazil','United States','Japan','Australia','AU',
          'Singapore','SG','Turkey','Wakanda'] as $c) {
    $t("{$c} -> Avrupa DEGIL", !vestra_user_in_europe($u($c)));
    $t("{$c} -> taban YOK (kapali)", vestra_order_min_usd($u($c)) === 0.0);
}

echo "\n== Avrupa testi: MUAF olmasi gerekenler ==\n";
/* AB + EFTA + Birlesik Krallik + Balkanlar. Operator "Avrupa disina" dedi;
   AB ile sinirlamak GB/CH/NO alicilarina bugune kadar uygulanmayan bir sart
   getirirdi. Yerel yazimlar SART: form serbest metin. */
foreach (['Germany','Deutschland','Allemagne','DE','France','Frankreich','FR',
          'United Kingdom','England','GB','Switzerland','Suisse','Schweiz','CH',
          'Norway','Norge','NO','Serbia','Ukraine','Netherlands','Nederland',
          'Holland','Ireland','Éire','Austria','AT','Slovenia','SI','Italy','Italia'] as $c) {
    $t("{$c} -> Avrupa", vestra_user_in_europe($u($c)));
    $t("{$c} -> taban yok", vestra_order_min_usd($u($c)) === 0.0);
}
/* Kayit alani bos ya da hesap yoksa taban islemez: okuyamadigimiz bir alan
   yuzunden gercek bir siparisi reddetmek, hatayi musteriye odetmek olur. */
/* MUTLAK IDDIA: mekanizma iddialari sabite GORE yazilsaydi esik 1 USD de
   olsa yesil kalirlardi -- operatorun soyledigi degeri tutan tek sey sabitin
   adi olurdu (13 Eyl'de NEW rozetinin penceresi tam bu sebeple ayrica
   pinlenmisti). Bu satir KARARIN KENDISINI sabitliyor: taban KAPALI. */
$t('taban KAPALI = 0.0 (operator, 17 Eyl 2026)', VESTRA_NONEU_MIN_ORDER_USD === 0.0);
$t('(bos ulke) -> taban yok', vestra_order_min_usd($u('')) === 0.0);
$t('hesapsiz -> taban yok',   vestra_order_min_usd(null) === 0.0);

echo "\n== yakin kod tuzaklari ==\n";
$t('AT (Avusturya) Avrupa',    vestra_user_in_europe($u('AT')));
$t('AU (Avustralya) DEGIL',   !vestra_user_in_europe($u('AU')));
$t('SI (Slovenya) Avrupa',     vestra_user_in_europe($u('SI')));
$t('SG (Singapur) DEGIL',     !vestra_user_in_europe($u('SG')));
$t('IE (Irlanda) Avrupa',      vestra_user_in_europe($u('IE')));
$t('IL (Israil) DEGIL',       !vestra_user_in_europe($u('IL')));

echo "\n== kapi SUSUYOR: her ulke, her tutar ==\n";
/* Taban kapaliyken tek dogru cevap bos dizi. KUCUK tutar da olculuyor:
   "1 EUR'luk sepet geciyor mu" sorusu tam olarak operatorun kaldirdigi
   kuralin sorusu. */
$eu = $u('Germany'); $bj = $u('Benin');
foreach ([['Avrupa', $eu], ['Avrupa disi', $bj], ['hesapsiz', null], ['(bos ulke)', $u('')]] as [$lbl, $acc]) {
    foreach ([1.0, 100.0, 4999.0, 100000.0] as $amt) {
        $t("{$lbl} / {$amt} EUR -> gecer", vestra_order_min_shortfall($amt, $acc) === []);
    }
}

echo "\n== KUR KESINTISI ARTIK SIPARIS DURDURMUYOR ==\n";
/* Taban acikken bedeli yaziliydi: esik USD, katalog EUR, yani karsilastirma
   KUR istiyor ve kur yoksa siparis GECMIYORDU. Kapali sabit bu bedeli de
   kaldiriyor -- ama YALNIZCA fonksiyon kuru okumadan ONCE donuyorsa.
   Iddia bu yuzden DAVRANISSAL DEGIL KABLOLAMA: bu ortamda kur zaten
   okunamiyor, yani "bos dizi dondu" tek basina siranin dogru oldugunu
   KANITLAMAZ (kur bir gun okunabilir hale gelirse sessizce degisirdi). */
$src  = (string)@file_get_contents(__DIR__.'/../vestra/inc/products.php');
$body = '';
if (preg_match('/function vestra_order_min_shortfall\(.*?\n\}/s', $src, $m)) $body = $m[0];
$t('govde ayiklandi',            $body !== '');
$posGuard = strpos($body, 'if ($min <= 0) return [];');
$posFx    = strpos($body, 'vestra_fx(');
$t('min<=0 muhafazasi VAR',      $posGuard !== false);
$t('kur okumasi VAR (mekanizma duruyor)', $posFx !== false);
$t('muhafaza KUR OKUMASINDAN ONCE', $posGuard !== false && $posFx !== false && $posGuard < $posFx);
/* Mekanizma silinmedi: sabit geri acilirsa esik yine olculuyor. */
$t('fx hata dali hala yazili',   str_contains($body, "'error' => 'fx'"));

echo "\n== kapi SUNUCUDA (kaynak kablolamasi) ==\n";
/* Dugmeyi gizlemek kapi degildir -- bu depo bunu /offer ucunde ogrendi. */
$ord = (string)@file_get_contents(__DIR__.'/../vestra/order.php');
$t('order.php kapiyi cagiriyor', str_contains($ord, 'vestra_order_min_shortfall('));
$t('kapi HESABI geciriyor',      str_contains($ord, 'vestra_order_min_shortfall($subtotal, auth_user())'));
$t('kur hatasi ayri kod',        str_contains($ord, 'ordermin_fx'));
$crt = (string)@file_get_contents(__DIR__.'/../vestra/cart.php');
$t('sepet uyarisi var',          str_contains($crt, "'ordermin'"));
/* Bant SABITE de bagli: taban kapaliyken elle yazilmis bir /cart?err=ordermin
   sifir dolarlik bir taban duyururdu. */
$t('sepet banti sabitle kapili', preg_match('/if\(VESTRA_NONEU_MIN_ORDER_USD > 0/', $crt) === 1);
/* Rakam metne GOMULU DEGIL (KURAL 6'nin escrow tavani dersi). */
$t('sepet rakami sabitten',      str_contains($crt, 'VESTRA_NONEU_MIN_ORDER_USD'));
$t('sepet metninde rakam gomulu degil', !preg_match('/US\$\s?\d[\d.,]{2,}/', $crt));

echo "\n== 8 dilde metin (KURAL 10) ==\n";
$k1 = 'Orders outside Europe start at US$%s. Please add to your basket, or contact us and we will look at your order individually.';
/* DOKUZ dil, sekiz degil: 'ja' de sozlukte duruyor ve ilk yazimda ATLANDI --
   yakalayan sey seo_landing_test'in de.php'ye karsi eksiksizlik taramasi oldu,
   bu test degil. O yuzden liste burada da tam yaziliyor. */
foreach (['de','fr','es','it','pt','ru','ar','ja'] as $lang) {
    $d = @include __DIR__."/../vestra/inc/lang/{$lang}.php";
    $t("{$lang}: metin var", is_array($d) && isset($d[$k1]) && trim((string)$d[$k1]) !== '');
    $t("{$lang}: %s korunmus", is_array($d) && substr_count((string)($d[$k1] ?? ''), '%s') === 1);
}

echo "\n== terms_reply mektubu: RAKAM METNE GOMULU DEGIL ==\n";
/* Mektubun ilk taslagi rakamlari duz metne yazmisti ve operator BIR SAAT
   SONRA asgariyi 10.000'den 5.000'e cekti -- gomulu olsaydi mektup o anda
   sessizce yalan soylemeye baslardi. Escrow tavaninin bes gun metinde 3.000,
   kodda 3.500 kalmasi (KURAL 6) ayni sinif. */
require_once __DIR__.'/../vestra/inc/email_templates.php';
[$tsA, $tbA, $toA] = vestra_tpl_terms_reply('Mr X', 'BJ', 'UPS Express, 1-2 weeks.', 'Marco Bellini');
/* IKI KAPI AYRI: taban kalkti, BOLGESEL INDIRIM DURUYOR. Bu satir olmasaydi
   tabani kaldiran bir degisiklik indirim cumlesini de sessizce goturebilirdi. */
$t('Benin: %8 yaziyor',            str_contains($tbA, '8%'));
/* Taban kapaliyken mektup SUSMUYOR, DOGRUSUNU yaziyor: sablonun `else` dali
   "bolgeniz icin tutar asgarisi yok" diyor ve ilanin kendi MOQ'sunu yine
   soyluyor -- operatorun cumlesindeki "normal en az alim" tam olarak bu. */
$t('Benin: US$ rakami YOK',        !str_contains($tbA, 'US$'));
$t('Benin: "tutar asgarisi yok" cumlesi',
                                   str_contains($tbA, 'no order-value minimum'));
$t('Benin: ilan asgarisi yine yazili',
                                   str_contains($tbA, 'minimum quantity') && str_contains($tbA, 'pack multiples'));
$t('Benin: gonderim cumlesi basli',str_contains($tbA, 'UPS Express, 1-2 weeks.'));
$t('konuda ULKE ADI, kod degil',   str_contains($tsA, 'Benin') && !str_contains($tsA, ' BJ'));
$t('belge: ticari kayit',          str_contains($tbA, 'business registration'));
/* Alicidan istenmeyen belgeler YAZILMAZ (KURAL 2): auth_required_doc_types()
   alici icin yalniz trade_licence donduruyor. */
$t('kimlik belgesi ISTENMIYOR',    !str_contains($tbA, 'government ID'));
$t('katalog dugmesi var',          ($toA['button']['url'] ?? '') === 'https://vestrasales.com/shop');

/* AVRUPA: taban da indirim de YOK -- iki cumle de hic basilmamali. Tek yon
   yazilsaydi "herkese 5.000 USD" diyen bir mektup da yesil kalirdi. */
[$tsB, $tbB, $toB] = vestra_tpl_terms_reply('Mr Y', 'Germany', '', 'Marco Bellini');
$t('Avrupa: US$ rakami YOK',       !str_contains($tbB, 'US$'));
$t('Avrupa: indirim cumlesi YOK',  !str_contains($tbB, 'standing discount'));
$t('Avrupa: rows bos',             ($toB['rows'] ?? []) === []);
/* KURAL 3: operator gonderim cumlesi vermediyse mektup SUSAR -- uydurma bir
   tasiyici/sure yazmak, bu deponun ships_from dersinin mektup hali. */
$t('gonderim verilmedi -> SUSUYOR', !str_contains($tbB, 'Shipping.'));

/* Kapsamda ama Avrupa DISI olmayan yok; kapsam disi + Avrupa disi bir ulke
   tabani alir ama indirim almaz -- iki kapi AYRI. */
[, $tbC, ] = vestra_tpl_terms_reply('', 'United States', '', '');
$t('ABD: taban YOK',               !str_contains($tbC, 'US$'));
$t('ABD: indirim YOK',             !str_contains($tbC, 'standing discount'));
$t('ABD: ilan asgarisi yazili',    str_contains($tbC, 'minimum quantity'));

$wf = (string)@file_get_contents(__DIR__.'/../.github/workflows/send-campaign-preview.yml');
$t('is akisinda kabloli',          str_contains($wf, "\$letter === 'terms_reply'"));
$t('govdeyi cagiriyor',            str_contains($wf, 'vestra_tpl_terms_reply('));
/* Musteri adresi girdiye yazilmaz: dal to= ile calismiyor, copy=true ile
   operatore gidiyor ya da hedef kayittan cozuluyor. */
$t('rakam is akisina gomulu degil', !preg_match('/US\$\s?5[.,]?000/', $wf));

echo "\n== to=lead: adres KAYITTAN cozuluyor ==\n";
/* Kayitsiz bir adaya yazarken geriye adresi DUZ girdiye yazmak kaliyordu ve
   girdi kosu basliginda kalici + herkese acik. account:/order: ile ayni
   desen; TAM 1 esleme sarti da ayni sebeple: sifirda kimseye gitmez,
   birden fazlada YANLIS kisiye giderdi ve gonderilmis mektup geri alinamaz. */
$wf2 = (string)@file_get_contents(__DIR__.'/../.github/workflows/send-campaign-preview.yml');
$t('lead: cozumu var',            str_contains($wf2, "str_starts_with(strtolower(\$to), 'lead:')"));
/* IDDIA BLOGA BAGLI OLMAK ZORUNDA. Ilk yazimda 'if (count($hits) !== 1) {'
   dizgesi TUM dosyada araniyordu ve o satir ACCOUNT: blogunda da var: lead
   blogundaki sarti gevsetmek (!== 1 -> < 1) hicbir iddiayi dusurmedi ve
   sabotaj YESIL gecti. "Hic dusemeyen bir iddia, iddia degildir" bu depoda
   yazili ve bu oturumda ucuncu kez oldu. Artik yalniz lead blogu okunuyor. */
$lb = '';
$lA = strpos($wf2, "str_starts_with(strtolower(\$to), 'lead:')");
$lB = $lA !== false ? strpos($wf2, "if (\$to === '') \$to = 'acerasoft@gmail.com';", $lA) : false;
if ($lA !== false && $lB !== false) $lb = substr($wf2, $lA, $lB - $lA);
$t('lead blogu bulundu',          $lb !== '');
$t('leads.php require ediliyor',  str_contains($lb, 'require_once $doc."/inc/leads.php"'));
$t('TAM 1 esleme sarti',          str_contains($lb, 'if (count($hits) !== 1) {'));
$t('adaylar MASKELI listeleniyor',str_contains($lb, '$mask((string)($h[\'email\'] ?? \'\'))'));
/* Cozulen adres kutuge MASKELI basiliyor: teshis adiminin kendi dersi. */
$t('cozulen adres maskeli basiliyor', str_contains($lb, '"  (".$mask($to).")'));
/* Firma ADIYLA araniyor, adresle DEGIL: adresi girdiye yazmamak butun
   mekanizmanin var olma sebebi. */
$t('ad alanlarinda araniyor',     str_contains($lb, "\$l['company']") && str_contains($lb, "\$l['contact_name']"));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
