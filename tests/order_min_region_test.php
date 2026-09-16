<?php
/**
 * AVRUPA DISI ASGARI SIPARIS (5.000 USD) + AFRIKA %8 BOLGESEL INDIRIM
 * (operator, 16 Eyl 2026: *"Avrupa disina en az alim 10 Bin USD yaz"* +
 * *"Afrika bolgesine toplam katalogtan yuzde 8 indirim yapilacagini belirt"*).
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
foreach (['Benin','BJ','Brazil','United States','Japan','Australia','AU',
          'Singapore','SG','Turkey','Wakanda'] as $c) {
    $t("{$c} -> Avrupa DEGIL", !vestra_user_in_europe($u($c)));
    $t("{$c} -> taban var", abs(vestra_order_min_usd($u($c)) - VESTRA_NONEU_MIN_ORDER_USD) < 0.001);
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
/* MUTLAK IDDIA: yukaridaki mekanizma iddialari sabite GORE yazili, yani
   esik 1 USD de olsa yesil kalirlar -- operatorun soyledigi sayiyi tutan tek
   sey sabitin adi olurdu. Bu satir rakamin kendisini sabitliyor (13 Eyl'de
   NEW rozetinin penceresi tam bu sebeple ayrica pinlenmisti). */
$t('taban 5.000 USD (operator, 16 Eyl 2026)', VESTRA_NONEU_MIN_ORDER_USD === 5000.0);
$t('(bos ulke) -> taban yok', vestra_order_min_usd($u('')) === 0.0);
$t('hesapsiz -> taban yok',   vestra_order_min_usd(null) === 0.0);

echo "\n== yakin kod tuzaklari ==\n";
$t('AT (Avusturya) Avrupa',    vestra_user_in_europe($u('AT')));
$t('AU (Avustralya) DEGIL',   !vestra_user_in_europe($u('AU')));
$t('SI (Slovenya) Avrupa',     vestra_user_in_europe($u('SI')));
$t('SG (Singapur) DEGIL',     !vestra_user_in_europe($u('SG')));
$t('IE (Irlanda) Avrupa',      vestra_user_in_europe($u('IE')));
$t('IL (Israil) DEGIL',       !vestra_user_in_europe($u('IL')));

echo "\n== esik aritmetigi (kur SABIT, agsiz) ==\n";
/* vestra_order_min_shortfall gercek kuru okuyor; burada aritmetigi kurdan
   BAGIMSIZ dogruluyoruz: fonksiyonun dondurdugu 'rate' ile yeniden hesap. */
$eu = $u('Germany'); $bj = $u('Benin');
$t('Avrupa: hicbir tutarda eksik yok', vestra_order_min_shortfall(1.0, $eu) === []
                                    && vestra_order_min_shortfall(100000.0, $eu) === []);
$r = vestra_order_min_shortfall(100.0, $bj);
$t('Avrupa disi kucuk sepet: eksik VAR', $r !== []);
if (isset($r['rate']) && $r['rate'] > 0) {
    $t('eksik = taban - (EUR x kur)', abs($r['short_usd'] - (VESTRA_NONEU_MIN_ORDER_USD - round(100.0 * $r['rate'], 2))) < 0.02);
    $big = VESTRA_NONEU_MIN_ORDER_USD / $r['rate'] + 1.0;                 // esigin hemen ustu
    $t('yeterli sepet GECIYOR', vestra_order_min_shortfall($big, $bj) === []);
    $t('tam sinir GECIYOR',     vestra_order_min_shortfall(VESTRA_NONEU_MIN_ORDER_USD / $r['rate'], $bj) === []);
} else {
    /* Kur yoksa: olcum YAPILAMADI demek, "gecti" demek DEGIL. */
    $t('kur yoksa hata donuyor', ($r['error'] ?? '') === 'fx');
    $t('kur yoksa sepet GECMIYOR', vestra_order_min_shortfall(1000000.0, $bj) !== []);
}

echo "\n== kapi SUNUCUDA (kaynak kablolamasi) ==\n";
/* Dugmeyi gizlemek kapi degildir -- bu depo bunu /offer ucunde ogrendi. */
$ord = (string)@file_get_contents(__DIR__.'/../vestra/order.php');
$t('order.php kapiyi cagiriyor', str_contains($ord, 'vestra_order_min_shortfall('));
$t('kapi HESABI geciriyor',      str_contains($ord, 'vestra_order_min_shortfall($subtotal, auth_user())'));
$t('kur hatasi ayri kod',        str_contains($ord, 'ordermin_fx'));
$crt = (string)@file_get_contents(__DIR__.'/../vestra/cart.php');
$t('sepet uyarisi var',          str_contains($crt, "'ordermin'"));
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

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
