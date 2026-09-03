<?php
/* Turkiye engeli — VPN-gecirmez yari (operator karari, 3 Eyl 2026: "turkiye ip
 * sini engelle acil" + "VPN ile girse bile kabul etme turkiyeyi").
 *
 * vestra_country_blocked() (inc/security.php, tests/country_block_test.php'de
 * ayrica sinanir) IP'nin cozuldugu ulkeye bakar -- bir VPN bunu kolayca baska
 * bir ulkeye gosterir. vestra_country_declares_turkey() bunun tersini yapar:
 * formda YAZILAN ulkeye bakar, IP'den bagimsiz. Iki katman birlikte calisir,
 * ayri ayri sinanir.
 *
 * Bu dosya IKI seyi tutuyor:
 *   1. vestra_country_declares_turkey() -- kod adlari, ISO kodu, aksansiz
 *      yazim, ve en onemlisi Turkmenistan TUZAGI (bir onceki kural denemesinde
 *      "turk" alt-dizesiyle eslesip Turkmenistan'i da yanlislikla yakalayan
 *      turden bir hata -- bkz. CLAUDE.md'deki mango/zara/fila dersi, ayni
 *      sinif hata burada da mumkun).
 *   2. Kapinin GERCEKTEN kayitta ve profil kaydetmede, hesap/kayit
 *      olusturulmadan ONCE calistigini -- kaynaktan, cunku auth_register()'i
 *      butun bagimliliklariyla (promo, hesap dosyasi...) izole etmek bu testin
 *      degerini artirmiyor; asil risk kontrolun unutulmasi ya da yanlis yere
 *      konmasi.
 */
$secSrc = file_get_contents(__DIR__.'/../vestra/inc/security.php');
foreach (['vestra_cc_of_country', 'vestra_country_declares_turkey'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $secSrc, $m)) { echo "HATA: $fn bulunamadi\n"; exit(1); }
    eval($m[0]);
}

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

echo "-- Turkiye'yi YAKALAMASI gereken yazimlar --\n";
foreach (['TR', 'tr', ' tr ', 'Turkey', 'TURKEY', 'turkey', 'Türkiye', 'Turkiye', 'TÜRKİYE',
          'Republic of Turkiye', 'republic of turkey'] as $v) {
    $t("'$v' -> Turkiye sayilir", vestra_country_declares_turkey($v));
}

echo "-- YAKALAMAMASI gereken (komsu ulke tuzagi + bos) --\n";
foreach (['Turkmenistan', 'turkmenistan', 'TM', 'Germany', 'France', 'United States',
          'Turkiyeli', // "turkiye" ile BASLAYAN ama ona esit olmayan uydurma kelime -- alt dize tuzaginin tersi
          '', '   '] as $v) {
    $t("'".trim($v)."' -> Turkiye SAYILMAZ", !vestra_country_declares_turkey($v));
}
// Acik tuzak vakasi ayrica, adiyla:
$t("'Turkmenistan' 'turk' alt-dizesiyle yanlislikla yakalanmiyor", !vestra_country_declares_turkey('Turkmenistan'));

echo "-- vestra_cc_of_country ile tutarli --\n";
$t("cc_of_country('Turkey') hala TR", vestra_cc_of_country('Turkey') === 'TR');
$t("cc_of_country('Türkiye') hala TR", vestra_cc_of_country('Türkiye') === 'TR');

echo "\n-- kaynakta kapi GERCEKTEN kayittan/kaydetmeden ONCE calisiyor --\n";

$authSrc = file_get_contents(__DIR__.'/../vestra/inc/auth.php');
$posGate = strpos($authSrc, 'vestra_country_declares_turkey');
$posIdCreate = strpos($authSrc, "'id'             => bin2hex(random_bytes(8))");
$t('auth_register(): kapi hesap OLUSTURULMADAN once calisiyor',
   $posGate !== false && $posIdCreate !== false && $posGate < $posIdCreate);
$t('auth_register(): country_not_served kodu donuyor', str_contains($authSrc, "return 'country_not_served'"));

foreach (['buyer.php' => 'auth_update($_SESSION', 'seller.php' => 'auth_update($_SESSION'] as $file => $needle) {
    $src = file_get_contents(__DIR__.'/../vestra/'.$file);
    $posGate2 = strpos($src, 'vestra_country_declares_turkey');
    $posUpdate = strpos($src, $needle);
    $t("$file: profil kaydetme kapisi auth_update()'den ONCE calisiyor",
       $posGate2 !== false && $posUpdate !== false && $posGate2 < $posUpdate);
}

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
